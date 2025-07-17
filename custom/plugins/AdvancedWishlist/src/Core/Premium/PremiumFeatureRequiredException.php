<?php

declare(strict_types=1);

namespace AdvancedWishlist\Core\Premium;

/**
 * Exception thrown when premium features are accessed without valid license.
 */
class PremiumFeatureRequiredException extends \Exception
{
    public function __construct(string $message = 'This feature requires a premium license')
    {
        parent::__construct($message);
    }
}
