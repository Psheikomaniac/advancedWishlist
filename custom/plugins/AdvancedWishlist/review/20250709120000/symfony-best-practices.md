# Symfony 7 Best Practices Analysis

## Current Symfony Usage Issues

### 1. **Outdated Routing Annotations** 🔴
```php
// ❌ Current (Symfony 5/6 style)
/**
 * @RouteScope(scopes={"storefront"})
 */
class WishlistController extends StorefrontController
{
    /**
     * @Route("/store-api/wishlist", name="store-api.wishlist.list", methods={"GET"})
     */
    public function list() {}
}

// ✅ Symfony 7 with PHP Attributes
#[Route(defaults: ['_routeScope' => ['storefront']])]
class WishlistController extends StorefrontController
{
    #[Route('/store-api/wishlist', name: 'store-api.wishlist.list', methods: ['GET'])]
    public function list() {}
}
```

### 2. **Service Configuration** 🔴
```xml
<!-- ❌ Current XML configuration -->
<service id="AdvancedWishlist\Core\Service\WishlistService">
    <argument type="service" id="wishlist.repository"/>
</service>

<!-- ✅ Should use autowiring and autoconfiguration -->
```

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        
    AdvancedWishlist\:
        resource: '../src/'
        exclude:
            - '../src/Entity/'
            - '../src/Kernel.php'
```

## Symfony 7 Features to Implement

### 1. **Native PHP Types** ✅
Symfony 7 adds native PHP types to all properties and method return values.

```php
// ✅ All properties and methods should be typed
class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistService $wishlistService,
        private readonly SerializerInterface $serializer,
    ) {}
    
    #[Route('/wishlist/{id}', methods: ['GET'])]
    public function show(string $id): Response
    {
        // Typed parameters and return
    }
}
```

### 2. **MapRequestPayload Attribute** 🆕
```php
// ❌ Current manual mapping
public function create(Request $request): Response
{
    $data = json_decode($request->getContent(), true);
    $dto = new CreateWishlistRequest();
    $dto->assign($data);
}

// ✅ Symfony 7 with MapRequestPayload
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/wishlist', methods: ['POST'])]
public function create(
    #[MapRequestPayload] CreateWishlistRequest $request
): Response {
    // $request is automatically validated and mapped
}
```

### 3. **Security Improvements** 🆕

#### Deprecated eraseCredentials()
```php
// ❌ Old approach
class User implements UserInterface
{
    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }
}

// ✅ Symfony 7 approach
class User implements UserInterface
{
    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // Use DTOs or handle in AuthenticationTokenCreatedEvent
    }
}
```

#### Fine-grained Access Control
```php
// ✅ Symfony 7.3 with callable support (when PHP 8.5 is available)
#[IsGranted(
    attribute: 'WISHLIST_EDIT',
    subject: 'wishlist',
    statusCode: 403,
    exceptionCode: 'WISHLIST_ACCESS_DENIED'
)]
public function edit(Wishlist $wishlist): Response
{
    // Automatic access control
}
```

### 4. **Improved Dependency Injection**

#### Service Decorators
```php
// services.yaml
services:
    AdvancedWishlist\Core\Service\CachedWishlistService:
        decorates: AdvancedWishlist\Core\Service\WishlistService
        arguments:
            - '@.inner'
            - '@cache.app'
```

#### Tagged Services
```php
// Automatically tag services
#[AutoconfigureTag('wishlist.processor', ['priority' => 100])]
class PriorityWishlistProcessor implements WishlistProcessorInterface
{
    // Automatically registered and prioritized
}
```

### 5. **Event System Best Practices**

```php
// ✅ Use Event classes with public readonly properties
final class WishlistCreatedEvent
{
    public function __construct(
        public readonly Wishlist $wishlist,
        public readonly DateTime $createdAt = new DateTime(),
    ) {}
}

// Event Subscriber with attributes
#[AsEventListener(event: WishlistCreatedEvent::class, method: 'onWishlistCreated')]
class WishlistNotificationListener
{
    public function onWishlistCreated(WishlistCreatedEvent $event): void
    {
        // Handle event
    }
}
```

## Modern Symfony Architecture

### 1. **Domain-Driven Structure**
```
src/
├── Wishlist/                    # Bounded Context
│   ├── Domain/                  # Business Logic
│   │   ├── Entity/
│   │   ├── ValueObject/
│   │   ├── Repository/
│   │   └── Service/
│   ├── Application/             # Use Cases
│   │   ├── Command/
│   │   ├── Query/
│   │   └── EventHandler/
│   ├── Infrastructure/          # Technical Details
│   │   ├── Doctrine/
│   │   └── Symfony/
│   └── Presentation/            # UI/API
│       ├── Controller/
│       └── Form/
```

### 2. **Command Query Responsibility Segregation (CQRS)**

```php
// Command
final readonly class CreateWishlistCommand
{
    public function __construct(
        public string $name,
        public string $customerId,
        public WishlistType $type = WishlistType::PRIVATE,
    ) {}
}

// Handler
#[AsMessageHandler]
final class CreateWishlistHandler
{
    public function __construct(
        private readonly WishlistRepository $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}
    
    public function __invoke(CreateWishlistCommand $command): void
    {
        $wishlist = Wishlist::create(
            $command->name,
            $command->customerId,
            $command->type
        );
        
        $this->repository->save($wishlist);
        
        $this->eventDispatcher->dispatch(
            new WishlistCreatedEvent($wishlist)
        );
    }
}
```

### 3. **Symfony Messenger Integration**

```php
// Async message handling
#[AsMessage]
final readonly class SendWishlistNotification
{
    public function __construct(
        public string $wishlistId,
        public string $recipientEmail,
        public NotificationType $type,
    ) {}
}

#[AsMessageHandler]
final class SendWishlistNotificationHandler
{
    public function __invoke(SendWishlistNotification $message): void
    {
        // Handle asynchronously
    }
}
```

## Validation Best Practices

### 1. **Use Symfony Validator**
```php
use Symfony\Component\Validator\Constraints as Assert;

final class CreateWishlistRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    public string $name;
    
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $customerId;
    
    #[Assert\Choice(callback: [WishlistType::class, 'cases'])]
    public WishlistType $type = WishlistType::PRIVATE;
    
    #[Assert\Valid]
    public ?WishlistSettings $settings = null;
}
```

### 2. **Custom Validators**
```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class UniqueWishlistName extends Constraint
{
    public string $message = 'A wishlist with this name already exists.';
}

class UniqueWishlistNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly WishlistRepository $repository
    ) {}
    
    public function validate(mixed $value, Constraint $constraint): void
    {
        if ($this->repository->findByName($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ name }}', $value)
                ->addViolation();
        }
    }
}
```

## Performance Optimization

### 1. **Use Symfony Cache**
```php
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CachedWishlistRepository
{
    public function __construct(
        private readonly WishlistRepository $repository,
        private readonly TagAwareCacheInterface $cache,
    ) {}
    
    public function find(string $id): ?Wishlist
    {
        return $this->cache->get(
            "wishlist_{$id}",
            function (ItemInterface $item) use ($id) {
                $item->expiresAfter(3600);
                $item->tag(['wishlist', "user_{$userId}"]);
                
                return $this->repository->find($id);
            }
        );
    }
    
    public function invalidateUserCache(string $userId): void
    {
        $this->cache->invalidateTags(["user_{$userId}"]);
    }
}
```

### 2. **HTTP Cache Headers**
```php
class WishlistController extends AbstractController
{
    #[Route('/public/wishlist/{id}')]
    #[Cache(maxage: 3600, public: true)]
    public function publicShow(string $id): Response
    {
        $response = $this->render('wishlist/show.html.twig', [
            'wishlist' => $this->wishlistService->find($id),
        ]);
        
        $response->setEtag(md5($response->getContent()));
        $response->setPublic();
        $response->isNotModified($this->getRequest());
        
        return $response;
    }
}
```

## Security Best Practices

### 1. **Use Voters**
```php
class WishlistVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const DELETE = 'delete';
    
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Wishlist
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE]);
    }
    
    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token
    ): bool {
        $user = $token->getUser();
        
        return match($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            default => false,
        };
    }
}
```

### 2. **API Security**
```php
#[Route('/api/wishlist')]
#[Security("is_granted('ROLE_USER')")]
class ApiWishlistController extends AbstractController
{
    #[Route('', methods: ['POST'])]
    #[RateLimiter('api')]
    public function create(
        #[MapRequestPayload] CreateWishlistRequest $request
    ): JsonResponse {
        // Automatic rate limiting
    }
}
```

## Testing with Symfony

### Functional Tests
```php
class WishlistControllerTest extends WebTestCase
{
    use ResetDatabaseTrait;
    use Factories;
    
    public function testCreateWishlist(): void
    {
        $client = static::createClient();
        $this->logIn($client);
        
        $client->jsonRequest('POST', '/api/wishlist', [
            'name' => 'Test Wishlist',
            'type' => 'private',
        ]);
        
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Test Wishlist', $data['name']);
    }
}
```

## Migration Checklist

- [ ] Replace annotation routing with attributes
- [ ] Implement autowiring for all services
- [ ] Use MapRequestPayload for DTOs
- [ ] Implement proper validation
- [ ] Add security voters
- [ ] Configure caching properly
- [ ] Set up Messenger for async operations
- [ ] Add comprehensive functional tests
- [ ] Use Symfony profiler for debugging
- [ ] Implement proper error handling