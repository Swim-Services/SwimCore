<?php

namespace core\custom\prefabs\pearl;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\particle\EndermanTeleportParticle;
use pocketmine\world\sound\EndermanTeleportSound;

class SwimPearl extends EnderPearl
{

  private bool $animated;

  public function __construct(bool $animated, Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null)
  {
    parent::__construct($location, $shootingEntity, $nbt);
    $this->animated = $animated;
  }

  public function onHit(ProjectileHitEvent $event): void
  {
    $owner = $this->getOwningEntity();
    if ($owner instanceof Player) {
      // teleport particles and sound effects at original position
      $origin = $owner->getPosition();
      $this->getWorld()->addParticle($origin, new EndermanTeleportParticle());
      $this->getWorld()->addSound($origin, new EndermanTeleportSound());

      // position of where pearl hit
      $target = $event->getRayTraceResult()->getHitVector();

      // if animated or not
      if ($this->animated) {
        $owner->setPosition($target);
        $owner->getNetworkSession()->syncMovement($target);
      } else {
        $owner->teleport($target); // vanilla TP
      }

      // play teleport sound
      $this->getWorld()->addSound($target, new EndermanTeleportSound());
    }
  }

}