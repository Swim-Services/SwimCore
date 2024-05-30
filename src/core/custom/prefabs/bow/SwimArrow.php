<?php

namespace core\custom\prefabs\bow;

use pocketmine\entity\projectile\Arrow;

class SwimArrow extends Arrow
{

  public function getResultDamage(): int
  {
    return floor($this->damage);
  }

}