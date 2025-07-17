<?php

declare(strict_types=1);

namespace AdvancedWishlist\Tests\Unit\Core\CQRS\Command;

use AdvancedWishlist\Core\CQRS\Command\CommandBus;
use AdvancedWishlist\Core\CQRS\Command\CommandHandlerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Unit tests for the CommandBus class.
 */
class CommandBusTest extends TestCase
{
    private ContainerInterface $container;
    private CommandBus $commandBus;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->commandBus = new CommandBus($this->container);
    }

    public function testDispatchCallsHandlerWithCommand(): void
    {
        // Create a mock command
        $command = new \stdClass();
        $command->id = 'test-id';

        // Create a mock handler
        $handler = $this->createMock(CommandHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->identicalTo($command))
            ->willReturn('result');

        // Configure the container to return the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with('stdClassHandler')
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with('stdClassHandler')
            ->willReturn($handler);

        // Dispatch the command
        $result = $this->commandBus->dispatch($command);

        // Assert the result
        $this->assertEquals('result', $result);
    }

    public function testDispatchThrowsExceptionWhenHandlerNotFound(): void
    {
        // Create a mock command
        $command = new \stdClass();
        $command->id = 'test-id';

        // Configure the container to not have the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with('stdClassHandler')
            ->willReturn(false);

        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No handler found for command stdClass');

        // Dispatch the command
        $this->commandBus->dispatch($command);
    }

    public function testDispatchThrowsExceptionWhenHandlerDoesNotImplementInterface(): void
    {
        // Create a mock command
        $command = new \stdClass();
        $command->id = 'test-id';

        // Create a handler that doesn't implement the interface
        $handler = new \stdClass();

        // Configure the container to return the handler
        $this->container->expects($this->once())
            ->method('has')
            ->with('stdClassHandler')
            ->willReturn(true);

        $this->container->expects($this->once())
            ->method('get')
            ->with('stdClassHandler')
            ->willReturn($handler);

        // Expect an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Handler stdClass must implement '.CommandHandlerInterface::class);

        // Dispatch the command
        $this->commandBus->dispatch($command);
    }
}
