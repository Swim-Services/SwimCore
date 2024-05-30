<?php

namespace core\custom\prefabs\boombox;

use core\custom\behaviors\player_event_behaviors\NoFall;
use core\systems\player\SwimPlayer;
use pocketmine\math\Vector3;
use pocketmine\world\Explosion;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\ExplodeSound;

class KnockerBoxExplosion extends Explosion
{

  private SwimPlayer $target;
  private bool $noFall;

  public function __construct(Position $source, SwimPlayer $target, bool $noFall = true)
  {
    $this->target = $target;
    $this->noFall = $noFall;
    parent::__construct($source, 1);
  }

  public function explodeB(): bool
  {
    $source = (new Vector3($this->source->x, $this->source->y, $this->source->z))->floor();

    $entityPos = $this->target->getPosition();
    $distance = $entityPos->distance($this->source);
    $this->world->addParticle($source, new HugeExplodeSeedParticle());
    $this->world->addSound($source, new ExplodeSound());

    if ($distance > 5) return true; // if farther away then don't do kb at all to target entity

    $motion = $entityPos->subtractVector($this->source)->normalize();

    $impact = (5 - $distance) * 0.75;

    $motionVec = $this->target->getMotion()->addVector($motion->multiply($impact));
    $motionVec->y /= 3;
    $motionVec->y += 0.75;
    $this->target->setMotion($motionVec);

    // default 2 seconds of no fall
    if ($this->noFall) {
      $this->target->registerBehavior(new NoFall($this->target->getSwimCore(), $this->target));
    }

    return true;
  }

}