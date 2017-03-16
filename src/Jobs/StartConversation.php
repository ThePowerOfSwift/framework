<?php

declare(strict_types=1);

namespace FondBot\Jobs;

use FondBot\Channels\Driver;
use Illuminate\Bus\Queueable;
use FondBot\Channels\ChannelManager;
use FondBot\Conversation\StoryManager;
use Illuminate\Queue\SerializesModels;
use FondBot\Conversation\ContextManager;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use FondBot\Contracts\Events\MessageReceived;
use FondBot\Conversation\ConversationManager;
use FondBot\Contracts\Database\Entities\Channel;
use FondBot\Contracts\Database\Services\ParticipantService;

class StartConversation implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    private $channel;
    private $request;

    public function __construct(Channel $channel, array $request)
    {
        $this->channel = $channel;
        $this->request = $request;
    }

    public function handle(
        ChannelManager $channelManager,
        ContextManager $contextManager,
        StoryManager $storyManager,
        ConversationManager $conversationManager,
        ParticipantService $participantService
    ) {
        /** @var Driver $driver */
        $driver = $channelManager->createDriver($this->request, $this->channel);

        /** @var ParticipantService $participantService */
        $participant = $participantService->findByChannelAndIdentifier(
            $this->channel,
            $driver->getSender()->getIdentifier()
        );

        // Resolve context
        $context = $contextManager->resolve($driver);

        // Fire an event that message was received
        $this->events()->dispatch(
            new MessageReceived(
                $participant,
                $driver->getMessage()->getText()
            )
        );

        // Find story
        $story = $storyManager->find($context, $driver->getMessage());

        // No story found
        if ($story === null) {
            return;
        }

        // Start Conversation
        $conversationManager->start($context, $driver, $this->channel, $story);
    }

    private function events(): Dispatcher
    {
        return resolve(Dispatcher::class);
    }
}
