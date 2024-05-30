<?php

namespace core\commands;

use core\scenes\duel\Duel;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\utils\TargetArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class DuelCommand extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;

    $this->setPermission("use.all");
    parent::__construct($core, "duel", "send a duel");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", false));
    $this->registerArgument(1, new TextArgument("mode", false)); // maybe make this have autofill args from an array
  }

  /**
   * @inheritDoc
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if (!$sender instanceof SwimPlayer) return;

    if ($sender->getSceneHelper()->getScene()->getSceneName() !== "Hub") {
      $sender->sendMessage(TF::RED . "You must be in the Hub out of a party and not queued to use this!");
      return;
    }

    if (!isset($args["player"]) || !isset($args["mode"])) {
      $sender->sendMessage(TF::RED . "Usage: /duel <player> <mode>");
      return;
    }

    if ($args["player"] === $sender->getName()) {
      $sender->sendMessage(TF::RED . "You can not duel your self!");
      return;
    }

    if (!in_array($args["mode"], Duel::$MODES)) {
      $sender->sendMessage(TF::RED . "Invalid Mode passed, Available Games: " . implode(", ", DUEL::$MODES));
      return;
    }

    $targetPlayer = $this->core->getServer()->getPlayerExact($args["player"]);
    if (!($targetPlayer instanceof SwimPlayer)) {
      $sender->sendMessage(TF::RED . "Could not find player " . $args["player"]);
      return;
    }

    if ($targetPlayer->getSceneHelper()->getScene()->getSceneName() === "Hub") {
      $targetPlayer->getInvites()->duelInvitePlayer($sender, $args["mode"]);
    } else {
      $sender->sendMessage(TF::RED . "Player must be in the Hub out of a party and not queued to use this!");
    }
  }

}