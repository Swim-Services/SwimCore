<?php

namespace core\custom\prefabs\boombox;

use core\systems\player\SwimPlayer;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\world\Position;

class KnockerBoxEntity extends PrimedTNT
{

  private SwimPlayer $target;

  public function __construct(Location $location, SwimPlayer $target)
  {
    $this->target = $target;
    parent::__construct($location);
  }

  public function explode(): void
  {
    $explosion = new KnockerBoxExplosion(Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()), $this->target);
    $explosion->explodeB();
  }

}