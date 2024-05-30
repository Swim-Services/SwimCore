<?php

namespace core\database\queries;

use core\database\SwimDB;
use core\systems\player\SwimPlayer;
use core\utils\TimeHelper;
use Generator;
use pocketmine\utils\TextFormat as TF;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;
use poggit\libasynql\SqlThread;

class ConnectionHandler
{

  public static function handlePlayerJoin(SwimPlayer $player): void
  {
    // Get needed info
    $xuid = $player->getXuid();
    $name = $player->getName();

    // Update the player connection info in the database
    SwimDB::getDatabase()->executeImplRaw(
      [
        0 => "
            INSERT INTO Connections (xuid, name) 
            VALUES ('$xuid', '$name') 
            ON DUPLICATE KEY UPDATE 
            xuid = '$xuid', 
            name = '$name'"
      ],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function ()  {
      },
      null
    );

    // check for punishments
    self::checkPunishments($player, $xuid);
  }

  // this is purposefully programmed to not have any alt checks or extra security beyond per account punishments with XUID
  // in the prod server, you bet your ass we do alt security checks
  private static function checkPunishments(SwimPlayer $swimPlayer, string $xuid): void
  {
    Await::f2c(function () use ($swimPlayer, $xuid): Generator {
      // set up query
      $query = "SELECT * FROM Punishments WHERE (xuid = ?) AND (muted = 1 OR banned = 1)";

      // check if they have a punishment (this should return a single row of rows)
      $rowsResult = yield from Await::promise(fn($resolve, $reject) => SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$xuid]], [0 => SqlThread::MODE_SELECT], $resolve, $reject));
      // Get the rows and needed data
      $rows = $rowsResult[0]->getRows();
      $hasBeenMuted = false;
      $xuidFound = false;
      // Iterate through the rows and deal with any of the punishments
      if (!empty($rows)) {
        foreach ($rows as $row) {
          // check if it has banned account
          $banned = self::checkBan($row);
          if ($banned) {
            $timeString = TimeHelper::formatTime($row['unbanTime'] - time());
            $money = "\n" . TF::BLUE . "Purchase Unban at " . TF::AQUA . "swim.tebex.io" . TF::GRAY . " | " . TF::BLUE . "Support: " . TF::AQUA . "discord.gg/swim";
            $banMessage = TF::RED . "You are Banned for: " . TF::YELLOW . $row['banReason'] . "\n" . TF::GOLD . "Ban expires in " . $timeString . $money;
            $swimPlayer->kick("banned player connection rejected", null, $banMessage);
            return; // if they are banned we can crash out of the entire function from here
          }
          // check if it has muted account
          $muted = self::checkMute($row);
          if ($muted && !$hasBeenMuted) {
            $hasBeenMuted = true; // so we know there is no point to mute them again
            $swimPlayer?->getChatHandler()->setMute($row['muteReason'], $row['unmuteTime']);
          }
          // confirms we found the account that is currently logging in
          if ($row['xuid'] === $xuid) {
            $xuidFound = true;
          }
        }
        // by here it has been evaluated they are not banned or alting,
        // so we can delete the entire punishment log row if the specific player is in there, which is told by xuidFound
        if (!$hasBeenMuted && $xuidFound) {
          $query = "DELETE FROM Punishments WHERE xuid = ?";
          SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$xuid]], [0 => SqlThread::MODE_SELECT], function () {
          }, null);
        }
      }
      // finally load their data in if still online after checks
      if ($swimPlayer->isOnline()) {
        $swimPlayer->loadData();
      }
    });
  }

  private static function checkMute($row): bool
  {
    $muted = $row['muted'];
    if ($muted) {
      $muteTime = $row['unmuteTime'];
      if ($muteTime > time()) {
        return true;
      } else {
        $xuid = $row['xuid'];
        // Unmute the specific account by setting muted and unmuteTime equal to null
        SwimDB::getDatabase()->executeImplRaw(
          [0 => "UPDATE Punishments SET muted = NULL, unmuteTime = NULL WHERE xuid = ?"],
          [0 => [$xuid]],
          [0 => SqlThread::MODE_GENERIC],
          function () {
          },
          null
        );
      }
    }
    return false;
  }

  private static function checkBan($row): bool
  {
    $banned = $row['banned'];
    if ($banned) {
      $banTime = $row['unbanTime'];
      if ($banTime > time()) {
        return true;
      } else {
        $xuid = $row['xuid'];
        // Unban the specific account by setting banned and unbanTime equal to null
        SwimDB::getDatabase()->executeImplRaw(
          [0 => "UPDATE Punishments SET banned = NULL, unbanTime = NULL WHERE xuid = ?"],
          [0 => [$xuid]],
          [0 => SqlThread::MODE_GENERIC],
          function () {
          },
          null
        );
      }
    }
    return false;
  }

}