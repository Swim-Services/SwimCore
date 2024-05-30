<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class HubCmd extends Command
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct("hub", "Teleport to hub!");
    $this->core = $core;
    $this->setPermission("use.all");
  }

  /**
   * @throws ScoreFactoryException|JsonException
   */
  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    if ($sender instanceof SwimPlayer) {
      $sh = $sender->getSceneHelper();

      // party check
      if ($sh->isInParty()) {
        $party = $sh->getParty();
        $party->removePlayerFromParty($sender);
        $party->partyMessage(TextFormat::YELLOW . $sender->getNicks()->getNick() . TextFormat::GREEN . " Left the Party");
        $sender->sendMessage(TextFormat::YELLOW . "Hubbing made you leave your party, if you were the only player in the party it is now disbanded");
      }

      // event check
      $event = $sh->getEvent();
      if ($event) {
        $sender->sendMessage(TextFormat::YELLOW . "Hubbing made you leave the event!");
        $event->removePlayer($sender);
        $event->removeMessage($sender);
      }

      // trolled
      if ($sender->getCombatLogger()->isInCombat()) {
        $sender->sendMessage(TextFormat::RED . "Hubbing while in combat is for pussies!");
        return false;
      }

      // set the scene to Hub
      $sh->getScene()->playerElimination($sender);
      $sh->setNewScene('Hub');
      $sender->sendMessage("§7Teleporting to hub...");
    }

    return true;
  }

}