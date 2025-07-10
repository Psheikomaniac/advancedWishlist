<?php declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\CQRS\Query;

use AdvancedWishlist\Core\CQRS\Query\QueryBus;
use AdvancedWishlist\Core\CQRS\Query\QueryHandlerInterface;
use AdvancedWishlist\Core\CQRS\Query\QueryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for the QueryBus class
 */
class QueryBusTest extends TestCase
{
    private ContainerInterface $container;
    private QueryBus $queryBus;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->queryBus = new QueryBus($this->container);
    }

    public function testDispatchCallsHandlerWithQuery(): void
    {
        // Create a mock query
        $query = $this->createMock(QueryInterface::class);

        // Create a mock handler
        $handler = $this->createMock(QueryHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($query))
            ->willReturn('result');

        // Get the class name of the query
        $queryClass = get_class($query);

        // Configure the container to return the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with($queryClass . 'Handler')
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($queryClass . 'Handler')
            ->willReturn($handler);

        // Dispatch the query
        $result = $this->queryBus->dispatch($query);

        // Assert the result
        $this->assertEquals('result', $result);
    }

    public function testDispatchThrowsExceptionWhenHandlerNotFound(): void
    {
        // Create a mock query
        $query = $this->createMock(QueryInterface::class);

        // Get the class name of the query
        $queryClass = get_class($query);

        // Configure the container to not have the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with($queryClass . 'Handler')
            ->willReturn(false);

        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No handler found for query ' . $queryClass);

        // Dispatch the query
        $this->queryBus->dispatch($query);
    }

    public function testDispatchThrowsExceptionWhenHandlerDoesNotImplementInterface(): void
    {
        // Create a mock query
        $query = $this->createMock(QueryInterface::class);

        // Create a handler that doesn't implement the interface
        $handler = new \stdClass();

        // Get the class name of the query
        $queryClass = get_class($query);

        // Configure the container to return the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with($queryClass . 'Handler')
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with($queryClass . 'Handler')
            ->willReturn($handler);

        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler stdClass must implement ' . QueryHandlerInterface::class);

        // Dispatch the query
        $this->queryBus->dispatch($query);
    }
}