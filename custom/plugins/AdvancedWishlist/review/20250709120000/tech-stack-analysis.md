# Technology Stack Analysis

## Current Stack Status

### PHP Version Analysis

#### Plugin Requirements
- **Minimum Required**: PHP 8.1 (based on enum usage)
- **Shopware 6.6 Requires**: PHP 8.2+
- **Latest Stable PHP**: 8.4 (Released November 21, 2024)
- **Next Version**: PHP 8.5 (Scheduled November 2025)

#### PHP Feature Usage
```php
// ✅ Currently Using
- PHP 8.0: Union types, attributes, constructor property promotion
- PHP 8.1: Enums, readonly properties (partial)
- PHP 8.2: None of the new features

// ❌ Not Using (but should)
- PHP 8.3: Typed class constants, #[\Override] attribute
- PHP 8.4: Property hooks, asymmetric visibility, new array functions
```

### Symfony Version Analysis

#### Current Status
- **Shopware 6.6**: Uses Symfony 7.2
- **Plugin Uses**: Mixed patterns (Symfony 5/6 style annotations)
- **Latest Symfony**: 7.3.1 (LTS: 6.4.23)

#### Deprecated Patterns Found
```php
// ❌ Old Pattern (Found in plugin)
/**
 * @RouteScope(scopes={"storefront"})
 */
class WishlistController

// ✅ Symfony 7 Pattern (Should use)
#[Route(defaults: ['_routeScope' => ['storefront']])]
class WishlistController
```

## Compatibility Matrix

| Component | Plugin Uses | Shopware 6.6 Requires | Latest Available | Status |
|-----------|-------------|----------------------|------------------|---------|
| PHP | 8.1+ | 8.2+ | 8.4 | ⚠️ Needs Update |
| Symfony | 5.x/6.x patterns | 7.x | 7.3.1 | ❌ Incompatible |
| Doctrine | 2.x patterns | 3.x | 3.x | ⚠️ Review Needed |
| Twig | 3.x | 3.x | 3.x | ✅ Compatible |

## PHP 8.4 Features Not Utilized

### 1. Property Hooks
```php
// Current approach
private ?float $totalValue = null;

public function getTotalValue(): float
{
    return $this->totalValue ?? 0.0;
}

public function setTotalValue(float $totalValue): void
{
    $this->totalValue = $totalValue;
}

// PHP 8.4 approach
public float $totalValue {
    get => $this->totalValue ?? 0.0;
    set => $value >= 0 ? $value : throw new \InvalidArgumentException();
}
```

### 2. Asymmetric Visibility
```php
// Could use
public private(set) string $id;
public protected(set) string $status;
```

### 3. New Array Functions
```php
// Could replace
$firstItem = reset($items) ?: null;
// With
$firstItem = array_first($items);
```

## Symfony 7 Features Not Utilized

### 1. Native Type Declarations
- All properties should have native PHP types
- Method return types should be explicit

### 2. Attribute-Based Configuration
- Replace all annotation-based routing
- Use PHP attributes for validation

### 3. New Security Features
- OAuth2 token introspection
- Fine-grained error exposure
- Callable-based access checks

## Dependency Analysis

### Required Updates
```json
{
    "require": {
        "php": ">=8.3",
        "shopware/core": "~6.7.0",
        "symfony/framework-bundle": "~7.2",
        "symfony/validator": "~7.2",
        "doctrine/orm": "^3.0"
    }
}
```

### Deprecated Dependencies
- Remove any Symfony < 7.0 polyfills
- Update Doctrine annotations to attributes
- Replace deprecated event dispatcher usage

## Migration Roadmap

### Phase 1: Compatibility (1-2 weeks)
1. Update minimum PHP to 8.2
2. Replace deprecated Symfony patterns
3. Fix Doctrine compatibility issues

### Phase 2: Modernization (2-3 weeks)
1. Implement PHP 8.3 features
2. Adopt Symfony 7 best practices
3. Update dependency injection

### Phase 3: Optimization (1-2 weeks)
1. Implement PHP 8.4 features where beneficial
2. Optimize for Symfony 7 performance
3. Add proper type coverage

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| PHP version mismatch | High | Set explicit version constraints |
| Symfony deprecations | High | Immediate pattern updates needed |
| Breaking changes | Medium | Comprehensive testing required |
| Performance regression | Low | Benchmark before/after updates |

## Recommendations

1. **Immediate**: Update to PHP 8.3 minimum requirement
2. **Short-term**: Migrate all Symfony patterns to v7
3. **Long-term**: Adopt PHP 8.4 features for better code quality
4. **Continuous**: Monitor Symfony 7.4 LTS (November 2025) for migration