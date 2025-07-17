<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/custom/plugins/AdvancedWishlist/src')
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;
