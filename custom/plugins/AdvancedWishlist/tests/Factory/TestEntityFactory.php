<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Factory;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Base class for test entity factories.
 */
abstract class TestEntityFactory
{
    /**
     * Create an entity with the given data.
     */
    protected function create(
        EntityRepository $repository,
        array $data,
        Context $context,
    ): string {
        $id = Uuid::randomHex();
        $data['id'] = $id;

        $repository->create([$data], $context);

        return $id;
    }

    /**
     * Get an entity by ID.
     */
    protected function get(
        EntityRepository $repository,
        string $id,
        Context $context,
        array $associations = [],
    ): ?object {
        $criteria = new Criteria([$id]);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $repository->search($criteria, $context)->first();
    }

    /**
     * Find an entity by field value.
     */
    protected function findOneBy(
        EntityRepository $repository,
        string $field,
        $value,
        Context $context,
        array $associations = [],
    ): ?object {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($field, $value));
        $criteria->setLimit(1);

        foreach ($associations as $association) {
            $criteria->addAssociation($association);
        }

        return $repository->search($criteria, $context)->first();
    }

    /**
     * Delete an entity by ID.
     */
    protected function delete(
        EntityRepository $repository,
        string $id,
        Context $context,
    ): void {
        $repository->delete([['id' => $id]], $context);
    }

    /**
     * Generate random data for testing.
     */
    protected function getRandomData(): array
    {
        return [];
    }

    /**
     * Merge default data with provided data.
     */
    protected function mergeData(array $defaults, array $data): array
    {
        return array_merge($defaults, $data);
    }
}
