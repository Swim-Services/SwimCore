<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\args\RawStringArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;

class TebexRank extends BaseCommand
{

  private SwimCore $core;
  private Server $server;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->server = $this->core->getServer();
    $this->setPermission("use.op");
    parent::__construct($core, "tebexrank");
  }

  /**
   * @inheritDoc
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new RawStringArgument("username"));
    $this->registerArgument(1, new RawStringArgument("packageName"));
    $this->registerArgument(2, new RawStringArgument("price"));
    $this->registerArgument(3, new RawStringArgument("id"));
  }

  /**
   * @inheritDoc
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if (!($sender instanceof ConsoleCommandSender)) return; // console only

    $user = $args["username"];
    $packageName = $args["packageName"];
    $xuid = $args["id"];
    $price = $args["price"];

    $rankLevel = Rank::getRankLevelFromPackageName($packageName);

    $alert = TF::DARK_GRAY . "[" . TF::RED . "ALERT" . TF::DARK_GRAY . "] " . TF::RESET;
    $message = TF::AQUA . $user . TF::LIGHT_PURPLE . " Purchased " . TF::GREEN . $packageName . TF::DARK_GRAY . " (" . TF::GREEN . "$" . $price . TF::DARK_GRAY . ")";
    $site = TF::DARK_GRAY . " | " . TF::AQUA . "swim.tebex.io";
    $this->server->broadcastMessage($alert . $message . $site);

    // first attempt to get player online
    $player = $this->core->getServer()->getPlayerExact($user);
    if ($player instanceof SwimPlayer) {
      $rank = $player->getRank();
      $level = $rank->getRankLevel();
      if ($rankLevel > $level) { // checks if it is an upgrade, if so then rank upgrade them
        $rank->setOnlinePlayerRank($rankLevel);
      }
    } else { // otherwise do it offline
      Rank::attemptRankUpgrade($xuid, $rankLevel);
    }
  }
}