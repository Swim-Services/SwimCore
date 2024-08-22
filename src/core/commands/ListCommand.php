<?php

namespace core\commands;

use core\SwimCore;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class ListCommand extends Command
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct("list", "lists all players online in a comma seperated list", null, ["ls"]);
    $this->core = $core;
    $this->setPermission("use.all");
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    $players = $this->core->getServer()->getOnlinePlayers();
    $count = count($players);
    $playerNames = [];

    // Iterate over each player and get their name
    foreach ($players as $player) {
      $playerNames[] = $player->getName();
    }

    // Join the names into a comma-separated string
    $playerList = implode(", ", $playerNames);

    // Send the message to the sender
    $sender->sendMessage(TextFormat::AQUA . $count . " online: " . $playerList);

    return true;
  }

}