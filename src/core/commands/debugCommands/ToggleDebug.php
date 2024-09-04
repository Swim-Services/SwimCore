<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class ToggleDebug extends Command
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct("debug", "toggle server debug mode");
    $this->core = $core;
    $this->setPermission("use.op");
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if ($rank == Rank::OWNER_RANK) {
        SwimCore::$DEBUG = !SwimCore::$DEBUG;
        $str = SwimCore::$DEBUG ? "true" : "false";
        $sender->sendMessage(TextFormat::GREEN . "Debug toggled to " . $str . " (players can now start duels with themselves in queue)");
      } else {
        $sender->sendMessage(TextFormat::RED . "You can not use this");
      }
    }
    return true;
  }

}