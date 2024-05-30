<?php

namespace core\utils;

use pocketmine\math\AxisAlignedBB;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\Position;
use pocketmine\world\World;

class ServerSounds
{

  // Plays sound to a player
  public static function playSoundToPlayer(Player $player, string $soundName, float $volume = 0, float $pitch = 0): void
  {
    $packet = new PlaySoundPacket();
    $packet->soundName = $soundName;
    $packet->x = $player->getPosition()->getX();
    $packet->y = $player->getPosition()->getY();
    $packet->z = $player->getPosition()->getZ();
    $packet->volume = $volume;
    $packet->pitch = $pitch;
    $player->getNetworkSession()->sendDataPacket($packet);
  }

  // Plays sound globally to all players in a world
  public static function playSoundToWholeWorld(World $world, string $soundName, float $volume = 0, float $pitch = 0): void
  {
    foreach ($world->getPlayers() as $player) {
      self::playSoundToPlayer($player, $soundName, $volume, $pitch);
    }
  }

  // Play sound to everyone
  public static function playSoundToEveryone(string $soundName, float $volume = 0, float $pitch = 0): void
  {
    foreach (Server::getInstance()->getOnlinePlayers() as $player) {
      self::playSoundToPlayer($player, $soundName, $volume, $pitch);
    }
  }

  // a much more flexible way to play a sound
  public static function playCustomSoundEffectInWorld(string $sound, World $world, Position $position, $volume = 3, $pitch = 1, int $radius = 40): void
  {
    if ($sound === "") {
      return; // no custom sound set
    }

    // Calculate the min and max coordinates for the AABB centered around the position
    $minX = $position->x - $radius;
    $maxX = $position->x + $radius;
    $minY = $position->y - $radius;
    $maxY = $position->y + $radius;
    $minZ = $position->z - $radius;
    $maxZ = $position->z + $radius;

    // Create a new AxisAlignedBB centered at the position and expanded by the radius
    $AABB = new AxisAlignedBB($minX, $minY, $minZ, $maxX, $maxY, $maxZ);

    // Retrieve entities within this expanded bounding box
    foreach ($world->getNearbyEntities($AABB) as $p) {
      if ($p instanceof Player) {
        if ($p->isOnline()) {
          $spk = new PlaySoundPacket();
          $spk->soundName = $sound;
          $location = $p->getLocation();
          $spk->x = $location->getX();
          $spk->y = $location->getY();
          $spk->z = $location->getZ();
          $spk->volume = $volume;
          $spk->pitch = $pitch;
          $p->getNetworkSession()->sendDataPacket($spk);
        }
      }
    }
  }


}