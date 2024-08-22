<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\utils\cordhook\CordHook;
use core\utils\TargetArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class KickCommand extends BaseCommand
{

  private SwimCore $SwimCore;

  public function __construct(SwimCore $swimCore)
  {
    $this->SwimCore = $swimCore;
    $this->setPermission("use.staff");
    parent::__construct($swimCore, "kick", "kick player");
  }

  /**
   * @throws ArgumentOrderException
   */
  public function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player"));
    $this->registerArgument(1, new TextArgument("reason", true));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    $reason = $args["reason"] ?? "No reason provided";
    $name = $sender->getName();
    if ($sender instanceof SwimPlayer) {
      $name = $sender->getName();
    }

    $player = SeeNick::getPlayerFromNick($args["player"]);
    if (!$player) {
      $sender->sendMessage(TF::RED . "player not found");
      return;
    }

    if ($sender instanceof SwimPlayer && $player instanceof SwimPlayer) {
      if ($player->getRank()?->getRankLevel() > $sender->getRank()->getRankLevel()) {
        $sender->sendMessage(TF::RED . "you cannot kick this player");
        return;
      }
    }

    $playerName = $player->getName();
    $sender->sendMessage(TF::GREEN . "Successfully kicked " . $playerName);

    $kickMessage = TF::RED . "[KICK] " . TF::GREEN . $name . TF::WHITE . " kicked " . TF::GREEN . $playerName . TF::WHITE . ". Reason: " . TF::GREEN . $reason;
    $this->SwimCore->getServer()->broadcastMessage($kickMessage);
    CordHook::sendEmbed($name . " kicked " . $playerName . " | Reason: " . $reason, "Staff Kick");

    if ($player instanceof SwimPlayer) {
      $player->kick(TF::RED . "You have been kicked\nKicked by: " . TF::WHITE . $name . "\n" . TF::RED . "Reason: " . TF::WHITE . $reason);
    }
  }

}