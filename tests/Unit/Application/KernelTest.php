<?php

declare(strict_types=1);

namespace FondBot\Tests\Unit\Application;

use Mockery;
use FondBot\Drivers\Driver;
use FondBot\Tests\TestCase;
use FondBot\Channels\Channel;
use FondBot\Application\Kernel;
use FondBot\Conversation\Intent;
use FondBot\Conversation\Context;
use FondBot\Drivers\ReceivedMessage;
use FondBot\Conversation\Interaction;
use FondBot\Conversation\IntentManager;
use FondBot\Conversation\ContextManager;
use FondBot\Drivers\Exceptions\InvalidRequest;
use FondBot\Drivers\Extensions\WebhookVerification;

class KernelTest extends TestCase
{
    public function test_context_and_driver()
    {
        Kernel::createInstance($this->container, $this->mock(Channel::class), $driver = $this->mock(Driver::class));

        Kernel::getInstance()->setContext($context = $this->mock(Context::class));

        $this->assertSame($context, Kernel::getInstance()->getContext());
        $this->assertSame($driver, Kernel::getInstance()->getDriver());
    }

    public function test_clearContext()
    {
        Kernel::createInstance($this->container, $this->mock(Channel::class), $this->mock(Driver::class));

        Kernel::getInstance()->setContext($context = $this->mock(Context::class));

        $this->container->bind(ContextManager::class, $contextManager = $this->mock(ContextManager::class));

        $contextManager->shouldReceive('clear')->with($context)->once();

        Kernel::getInstance()->clearContext();
        $this->assertNull(Kernel::getInstance()->getContext());
    }

    public function test_process_new_dialog()
    {
        Kernel::createInstance(
            $this->container,
            $channel = $this->mock(Channel::class),
            $driver = $this->mock(Driver::class)
        );

        $kernel = Kernel::getInstance();

        $this->container->bind(ContextManager::class, $contextManager = $this->mock(ContextManager::class));
        $this->container->bind(IntentManager::class, $intentManager = $this->mock(IntentManager::class));

        $channel->shouldReceive('getName')->andReturn($channelName = $this->faker()->userName);
        $driver->shouldReceive('verifyRequest')->once();
        $contextManager->shouldReceive('resolve')
            ->with($channelName, $driver)
            ->andReturn($context = $this->mock(Context::class))
            ->once();

        $context->shouldReceive('getInteraction')->andReturn(null)->once();

        $driver->shouldReceive('getMessage')->andReturn($receivedMessage = $this->mock(ReceivedMessage::class))->once();
        $intentManager->shouldReceive('find')
            ->with($receivedMessage)
            ->andReturn($intent = $this->mock(Intent::class))
            ->once();

        $context->shouldReceive('setIntent')->with($intent)->once();
        $context->shouldReceive('setInteraction')->with(null)->once();
        $context->shouldReceive('setValues')->with([])->once();
        $intent->shouldReceive('handle')->with($kernel)->once();
        $contextManager->shouldReceive('save')->with($context)->once();

        $kernel->process();
    }

    public function test_process_continue_dialog()
    {
        Kernel::createInstance(
            $this->container,
            $channel = $this->mock(Channel::class),
            $driver = $this->mock(Driver::class)
        );

        $kernel = Kernel::getInstance();

        $this->container->bind(ContextManager::class, $contextManager = $this->mock(ContextManager::class));
        $this->container->bind(IntentManager::class, $intentManager = $this->mock(IntentManager::class));

        $channel->shouldReceive('getName')->andReturn($channelName = $this->faker()->userName);
        $driver->shouldReceive('verifyRequest')->once();
        $contextManager->shouldReceive('resolve')
            ->with($channelName, $driver)
            ->andReturn($context = $this->mock(Context::class))
            ->once();

        $context->shouldReceive('getInteraction')->andReturn($interaction = $this->mock(Interaction::class))->atLeast()->once();

        $intentManager->shouldReceive('find')->never();
        $interaction->shouldReceive('handle')->with($kernel)->once();

        $contextManager->shouldReceive('save')->with($context)->once();

        $kernel->process();
    }

    public function test_process_invalid_request()
    {
        Kernel::createInstance(
            $this->container,
            $channel = $this->mock(Channel::class),
            $driver = $this->mock(Driver::class)
        );

        $kernel = Kernel::getInstance();

        $channel->shouldReceive('getName')->andReturn($channelName = $this->faker()->userName);
        $driver->shouldReceive('verifyRequest')->andThrow(new InvalidRequest('Invalid request.'));

        $this->assertSame('Invalid request.', $kernel->process());
    }

    public function test_process_with_webhook_verification()
    {
        /** @var Mockery\Mock|mixed $driver */
        $driver = Mockery::mock(Driver::class, WebhookVerification::class);

        Kernel::createInstance($this->container, $channel = $this->mock(Channel::class), $driver);

        $request = ['verification' => $this->faker()->sha1];
        $kernel = Kernel::getInstance();

        $channel->shouldReceive('getName')->andReturn($channelName = $this->faker()->userName);
        $driver->shouldReceive('isVerificationRequest')->andReturn(true);
        $driver->shouldReceive('verifyWebhook')->andReturn($request['verification']);

        $result = $kernel->process();

        $this->assertSame($request['verification'], $result);
    }
}
