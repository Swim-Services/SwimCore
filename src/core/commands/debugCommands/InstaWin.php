<?php

namespace core\commands\debugCommands;

use core\scenes\duel\Duel;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class InstaWin extends Command
{

  public function __construct()
  {
    parent::__construct("win", "debug winning screen");
    $this->setPermission("use.staff");
  }

  /**
   * @inheritDoc
   */
  public function execute(CommandSender $sender, string $commandLabel, array $args)
  {
    if ($sender instanceof SwimPlayer) {
      if ($sender->getRank()->getRankLevel() == Rank::OWNER_RANK) {
        $scene = $sender->getSceneHelper()->getScene();
        if ($scene instanceof Duel) {
          $team = $scene->getPlayerTeam($sender);
          if (isset($team))
            $scene->scoreBasedDuelEnd($team);
        }
      }
    }
  }
}