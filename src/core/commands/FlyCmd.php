<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class FlyCmd extends Command
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct("fly", "Start flying!");
    $this->core = $core;
    $this->setPermission("use.all");
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    if ($sender instanceof SwimPlayer) {
      $world = $sender->getWorld()->getFolderName();
      $rank = $sender->getRank()->getRankLevel();
      if ($world == "hub" || $rank == Rank::OWNER_RANK) {
        if ($rank > 0) {
          if ($sender->isFlying()) {
            $sender->sendMessage(TextFormat::AQUA . "Stopped flying");
            $sender->setFlying(false);
            $sender->setAllowFlight(false);
          } else {
            $sender->sendMessage(TextFormat::AQUA . "Started flying");
            $sender->setFlying(true);
            $sender->setAllowFlight(true);
          }
        } else {
          $sender->sendMessage(TextFormat::YELLOW . "You need a rank to do this command.");
          $sender->sendMessage(TextFormat::YELLOW . "Buy one at" . TextFormat::AQUA . " swim.tebex.io"
            . TextFormat::YELLOW . " or boost" . TextFormat::LIGHT_PURPLE . " discord.gg/swim");
        }
      } else {
        $sender->sendMessage(TextFormat::RED . "You can't use that here!");
      }
    }
    return true;
  }

}