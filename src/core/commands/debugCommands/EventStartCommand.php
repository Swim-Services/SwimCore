<?php

namespace core\commands\debugCommands;

use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\SwimCoreInstance;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class EventStartCommand extends Command
{

  public function __construct()
  {
    parent::__construct("es", "insta start the event you are in");
    $this->setPermission("use.op");
  }

  /**
   * @inheritDoc
   */
  public function execute(CommandSender $sender, string $commandLabel, array $args)
  {
    if ($sender instanceof SwimPlayer) {
      if ($sender->getRank()->getRankLevel() == Rank::OWNER_RANK) {
        $event = $sender->getSceneHelper()->getEvent();
        if ($event) {
          $event->setStarted();
          SwimCoreInstance::getInstance()->getSystemManager()->getEventSystem()->eventStarted($event);
          $event->startEvent();
        } else {
          $sender->sendMessage("You need to be in an event to use this");
        }
      }
    }
  }

}