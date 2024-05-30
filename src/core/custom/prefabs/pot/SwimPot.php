<?php

namespace core\custom\prefabs\pot;

use pocketmine\color\Color;
use pocketmine\entity\effect\InstantEffect;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\SplashPotion;
use pocketmine\event\entity\ProjectileHitEntityEvent;
use pocketmine\event\entity\ProjectileHitEvent;
use pocketmine\item\PotionType;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\particle\PotionSplashParticle;
use pocketmine\world\sound\PotionSplashSound;

class SwimPot extends SplashPotion
{

  public function __construct(Location $location, ?Entity $shootingEntity, PotionType $potionType, ?CompoundTag $nbt = null)
  {
    parent::__construct($location, $shootingEntity, $potionType, $nbt);
    $this->setCanSaveWithChunk(false);
    $this->gravity = 0.065;
    $this->drag = 0.0025;
  }

  protected function onHit(ProjectileHitEvent $event): void
  {
    // Flag to check if the projectile has effects
    $hasEffects = true;
    // Get the potion effects from the projectile
    $effects = $this->getPotionEffects();
    // If there are no effects, set a default particle and update the flag
    if (count($effects) === 0) {
      $particle = new PotionSplashParticle(PotionSplashParticle::DEFAULT_COLOR());
      $hasEffects = false;
    } else {
      // If there are effects, mix the colors for each effect level
      $colors = [];
      foreach ($effects as $effect) {
        $level = $effect->getEffectLevel();
        for ($i = 0; $i < $level; ++$i) {
          $colors[] = $effect->getColor();
        }
      }
      $particle = new PotionSplashParticle(Color::mix(...$colors));
    }
    // Add the particle to the world
    $this->getWorld()->addParticle($this->location, $particle);
    // Broadcast the sound of the splash
    $this->broadcastSound(new PotionSplashSound());
    // If there are effects, apply them to nearby entities
    if ($hasEffects) {
      $entityHit = null;
      // Check if the hit event involved an entity (Player)
      if ($event instanceof ProjectileHitEntityEvent && $event->getEntityHit() instanceof Player) {
        $entityHit = $event->getEntityHit()->getId();
      }
      // Get nearby entities and apply the effects
      foreach ($this->getWorld()->getNearbyEntities($this->boundingBox->expandedCopy(1.75, 3, 1.75), $this) as $entity) {
        if ($entity instanceof Player) {
          foreach ($effects as $effect) {
            if (!$effect->getType() instanceof InstantEffect) {
              // Calculate the new duration based on whether the entity is the one hit or not
              $newDuration = (int)round($effect->getDuration() * 0.75 * ($entity->getId() === $entityHit ? 1.0325 : 0.9025));
              if ($newDuration < 20) {
                continue;
              }
              $effect->setDuration($newDuration);
              $entity->getEffects()->add($effect);
            } else {
              // Apply an instant effect with different modifiers based on whether the entity is the one hit
              $effect->getType()->applyEffect($entity, $effect, $entity->getId() === $entityHit ? 1.0325 : 0.9025, $this);
            }
          }
        }
      }
    }
  }

}