<?php

declare(strict_types=1);

namespace Afup\Hermes\Discord\Command;

use Afup\Hermes\Discord\Command\Helper\EventHelper;
use Afup\Hermes\Discord\Command\Helper\UserHelper;
use Afup\Hermes\Repository\Event\FindEventByChannel;
use Afup\Hermes\Repository\Transport\FindUserTransportForEvent;
use Afup\Hermes\Repository\User\FindOrCreateUser;
use Discord\Builders\CommandBuilder;
use Discord\Builders\Components\ActionRow;
use Discord\Builders\Components\Button;
use Discord\Builders\MessageBuilder;
use Discord\Discord;
use Discord\Parts\Embed\Embed;
use Discord\Parts\Interactions\Interaction;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RemoveTransportCommand implements CommandInterface
{
    use EventHelper;
    use UserHelper;

    private const COMMAND_NAME = 'remove_transport';

    public function __construct(
        private FindOrCreateUser $findOrCreateUser,
        private FindEventByChannel $findEventByChannel,
        private FindUserTransportForEvent $findUserTransportForEvent,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function configure(Discord $discord): CommandBuilder
    {
        return CommandBuilder::new()
            ->setName(self::COMMAND_NAME)
            ->setDescription('Remove the transport you created for the event');
    }

    public function callback(Discord $discord): void
    {
        $discord->listenCommand(self::COMMAND_NAME, function (Interaction $interaction) use ($discord) {
            if ($interaction->user?->bot ?? false) {
                return; // ignore bots
            }

            if (false === ($event = $this->getEventForInteraction($interaction))) {
                return;
            }
            $user = $this->getUserForInteraction($interaction);

            $transport = ($this->findUserTransportForEvent)($event, $user);
            if (null === $transport) {
                $interaction->respondWithMessage(MessageBuilder::new()->setContent(':no_entry: You have no transport created.'), true);

                return;
            }

            $embed = new Embed($discord);
            $embed->setTitle(':wastebasket: Are you sure you want to delete your transport ?');

            $validation = ActionRow::new()
                ->addComponent(Button::new(Button::STYLE_DANGER)->setLabel('Delete')->setEmoji('🗑️')->setListener(function (Interaction $interaction) use ($transport): void {
                    $transportId = $transport->id;
                    $this->entityManager->remove($transport);
                    $this->entityManager->flush();

                    $interaction->respondWithMessage(MessageBuilder::new()->setContent(sprintf('🗑️ Transport #%d was removed.', $transportId)), true);
                }, $discord))
                ->addComponent(Button::new(Button::STYLE_SECONDARY)->setLabel('Cancel')->setEmoji('❌')->setListener(function (Interaction $interaction): void {
                    $interaction->respondWithMessage(MessageBuilder::new()->setContent('❌ Ignoring removal request.'), true);
                }, $discord));

            $interaction->respondWithMessage(MessageBuilder::new()->addEmbed($embed)->addComponent($validation), true);
        });
    }
}
