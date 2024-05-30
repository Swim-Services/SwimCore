<?php

namespace core\custom\prefabs\snowball;

use pocketmine\entity\Location;
use pocketmine\entity\projectile\Snowball;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\ProjectileItem;
use pocketmine\player\Player;

class SnowBall_Item extends ProjectileItem
{

  public function getMaxStackSize(): int
  {
    return 64;
  }

  protected function createEntity(Location $location, Player $thrower): Throwable
  {
    return new Snowball($location, $thrower);
  }

  public function getThrowForce(): float
  {
    return 1.5;
  }

  public function getCooldownTicks(): int
  {
    return 0;
  }
}