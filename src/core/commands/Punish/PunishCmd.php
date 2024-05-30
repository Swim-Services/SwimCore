<?php

namespace core\commands\punish;

use core\database\SwimDB;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\BaseCommand;
use Generator;
use pocketmine\command\CommandSender;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;
use poggit\libasynql\SqlThread;

class PunishCmd extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.staff");
    parent::__construct($core, "punish", "punish player");
  }

  public function prepare(): void
  {
    $this->registerSubCommand(new BanCmd($this->core));
    $this->registerSubCommand(new MuteCmd($this->core));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    $sender->sendMessage(TF::RED . "Incorrect usage. Proper usage: /punish ban|mute, 'playerName', int severity 1-3, optional: 'reason'");
  }

  public static function punishmentLogic(CommandSender $sender, string $punishment, array $args, SwimCore $core): void
  {
    if (count($args) >= 2) {
      $playerName = $args["player"]; // player to punish
      $severity = intval($args["severity"]); // 1 = week, 2 = month, 3 = year
      if ($severity > 3 || $severity <= 0) {
        $sender->sendMessage(TF::RED . "Incorrect punishment level specified, must be 1, 2, or 3, which maps to week, month, or year");
        return;
      }
      $reason = $args["reason"] ?? "Breaking Server Rules"; // Set the reason (it is an optional argument)
      // if ban or mute
      if (str_starts_with($punishment, "m")) {
        self::applyPunishment($sender, $playerName, $severity, $reason, 'mute', $core);
      } elseif (str_starts_with($punishment, "b")) {
        self::applyPunishment($sender, $playerName, $severity, $reason, 'ban', $core);
      } else {
        $sender->sendMessage(TF::RED . "Incorrect punishment type specified, must be ban or mute");
      }
    } else {
      $sender->sendMessage(TF::RED . "Incorrect usage.");
    }
  }

  private static function applyPunishment(CommandSender $sender, string $playerName, int $severity, string $reason, string $type, SwimCore $core): void
  {
    switch ($severity) {
      default:
      case 1:
        $timeOffset = 7 * 24 * 60 * 60; // 1 week
        $durationString = "week";
        break;
      case 2:
        $timeOffset = 30 * 24 * 60 * 60; // 1 month (approx.)
        $durationString = "month";
        break;
      case 3:
        $timeOffset = 365 * 24 * 60 * 60; // 1 year (approx.)
        $durationString = "year";
        break;
    }
    $formattedTime = date("F j, Y", time() + $timeOffset); // Format time for display
    $message = TF::GREEN . "Player " . $playerName . " has been " . $type . "d for 1 " . $durationString . ". Un" . $type . " date: " . $formattedTime;

    $player = $core->getServer()->getPlayerExact($playerName); // Get player if possible
    $xuid = "offline";
    $ip = "offline";

    // if player online grab xuid and ip and apply the punishment immediately onto the client first
    if ($player) {
      $xuid = $player->getXuid();
      $ip = $player->getNetworkSession()->getIp();
      if ($type === 'mute') {
        $sp = Server::getInstance()->getPlayerExact($playerName);
        if ($sp instanceof SwimPlayer) {
          $sp->getChatHandler()->setMute($reason, time() + $timeOffset);
          $player->sendMessage(TF::RED . "You have been muted for 1 " . $durationString);
          $player->sendMessage(TF::RED . "Reason: " . TF::YELLOW . $reason);
        }
      } elseif ($type === 'ban') {
        $money = "\n" . TF::BLUE . "Purchase Unban at " . TF::AQUA . "swim.tebex.io" . TF::GRAY . " | " . TF::BLUE . "Support: " . TF::AQUA . "discord.gg/swim";
        $banMessage = TF::RED . "You are banned for 1 " . $durationString . "\nReason: " . TF::YELLOW . $reason . $money;
        $player->kick("Banned by staff", null, $banMessage);
      }
    }

    // Retrieve xuid from the Connections' table if they were offline
    if ($xuid == "offline") {
      Await::f2c(function () use ($sender, $playerName, $timeOffset, $reason, $message, $type): Generator {
        $query = "SELECT xuid FROM Connections WHERE name = ?";
        $rows = yield from Await::promise(fn($resolve, $reject) => SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$playerName]], [0 => SqlThread::MODE_SELECT], $resolve, $reject));
        // If found
        if (isset($rows[0]->getRows()[0])) {
          $data = $rows[0]->getRows()[0];
          $xuid = $data['xuid'];
          // Now set data in the database
          self::setPunishmentData($playerName, $xuid, $timeOffset, $reason, $type);
          // Inform the sender
          $sender?->sendMessage($message);
        } else {
          $sender?->sendMessage(TF::RED . "Player not found to apply punishment to");
        }
      });
    } else {
      // Now set data in the database
      self::setPunishmentData($playerName, $xuid, $timeOffset, $reason, $type);
      // Inform the sender
      $sender->sendMessage($message);
    }
  }

  private static function setPunishmentData(string $playerName, string $xuid, int $timeOffset, string $reason, string $type): void
  {
    if ($type === 'ban') {
      SwimDB::getDatabase()->executeImplRaw(
        [0 => "INSERT INTO Punishments (xuid, name, banned, unbanTime, banReason) 
                 VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), banned = 1, unbanTime = VALUES(unbanTime), banReason = VALUES(banReason)"],
        [0 => [$xuid, $playerName, time() + $timeOffset, $reason]],
        [0 => SqlThread::MODE_GENERIC],
        function () {
        },
        null
      );
    } elseif ($type === 'mute') {
      SwimDB::getDatabase()->executeImplRaw(
        [0 => "INSERT INTO Punishments (xuid, name, muted, unmuteTime, muteReason) 
                 VALUES (?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), muted = 1, unmuteTime = VALUES(unmuteTime), muteReason = VALUES(muteReason)"],
        [0 => [$xuid, $playerName, time() + $timeOffset, $reason]],
        [0 => SqlThread::MODE_GENERIC],
        function () {
        },
        null
      );
    }
  }

}
