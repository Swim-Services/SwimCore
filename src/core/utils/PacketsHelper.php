<?php

namespace core\utils;

use pocketmine\player\Player;

class PacketsHelper
{

  // send an array of data packets to an array of players
  public static function broadCastPacketsToPlayers(array $players, array $packets): void
  {
    foreach ($players as $player) {
      if ($player instanceof Player) {
        self::broadCastPackets($player, $packets);
      }
    }
  }

  // send an array of data packets to a player
  public static function broadCastPackets(Player $player, array $packets): void
  {
    foreach ($packets as $packet) {
      $player->getNetworkSession()->sendDataPacket($packet, true);
    }
  }

}
