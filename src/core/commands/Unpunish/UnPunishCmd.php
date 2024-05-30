<?php

namespace core\commands\Unpunish;

use core\database\SwimDB;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\BaseCommand;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use poggit\libasynql\SqlThread;

class UnPunishCmd extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.staff");
    parent::__construct($core, "unpunish", "unpunish a player");
  }

  public function prepare(): void
  {
    $this->registerSubCommand(new UnbanCmd($this->core));
    $this->registerSubCommand(new UnmuteCmd($this->core));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    $sender->sendMessage(TextFormat::RED . "Incorrect usage. Proper usage: /unpunish ban|mute, 'playerName'");
  }

  public static function punishmentLogic(CommandSender $sender, string $playerName, string $punishment, SwimCore $core): void
  {
    if (str_starts_with($punishment, "m")) {
      self::removePunishment($sender, $playerName, 'muted');
      self::unmuteInGame($playerName, $core);
    } elseif (str_starts_with($punishment, "b")) {
      self::removePunishment($sender, $playerName, 'banned');
    } else {
      $sender->sendMessage(TextFormat::RED . "Incorrect punishment type specified, must be ban or mute");
    }
  }

  private static function removePunishment(CommandSender $sender, string $playerName, string $type): void
  {
    $db = SwimDB::getDatabase();

    // Set the value of either 'banned' or 'muted' to 0 based on $type
    $db->executeImplRaw(
      [0 => "UPDATE Punishments SET $type = 0 WHERE name = ?"],
      [0 => [$playerName]],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

    // Check if both 'banned' and 'muted' are 0, then delete the row
    $db->executeImplRaw(
      [0 => "DELETE FROM Punishments WHERE name = ? AND banned = 0 AND muted = 0"],
      [0 => [$playerName]],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );

    $sender->sendMessage(TextFormat::GREEN . "Un" . $type . " " . $playerName);
  }

  private static function unmuteInGame(string $playerName, SwimCore $core): void
  {
    $player = $core->getServer()->getPlayerExact($playerName);
    if ($player instanceof SwimPlayer && $player->isOnline()) {
      $player->getChatHandler()->unMute();
    }
  }

}