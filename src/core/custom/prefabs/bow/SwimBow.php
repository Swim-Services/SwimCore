<?php

namespace core\custom\prefabs\bow;

use pocketmine\entity\Location;
use pocketmine\event\entity\EntityShootBowEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\Bow;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\item\ItemTypeIds as Ids;
use pocketmine\item\ItemUseResult;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\sound\BowShootSound;
use pocketmine\entity\projectile\Arrow as ArrowEntity;
use pocketmine\entity\projectile\Projectile;

class SwimBow extends Bow
{

  public function __construct()
  {
    parent::__construct(new IID(Ids::BOW), "Bow");
    $this->setUnbreakable();
  }

  public function onReleaseUsing(Player $player, array &$returnedItems): ItemUseResult
  {
    $arrow = VanillaItems::ARROW();
    $inventory = match (true) {
      $player->getOffHandInventory()->contains($arrow) => $player->getOffHandInventory(),
      $player->getInventory()->contains($arrow) => $player->getInventory(),
      default => null
    };

    if ($player->hasFiniteResources() && $inventory === null) {
      return ItemUseResult::FAIL;
    }

    $location = $player->getLocation();

    $diff = $player->getItemUseDuration();
    $p = $diff / 20;
    $baseForce = min((($p ** 2) + $p * 2) / 3, 1);
    $entity = new SwimArrow(Location::fromObject(
      $player->getEyePos(),
      $player->getWorld(),
      ($location->yaw > 180 ? 360 : 0) - $location->yaw,
      -$location->pitch
    ), $player, $baseForce >= 1);
    $entity->setMotion($player->getDirectionVector());

    // fixed values
    if ($diff < 9) {
      $entity->setBaseDamage(1);
    } else if ($diff < 14) {
      $entity->setBaseDamage(6);
    } else if ($diff < 30) {
      $entity->setBaseDamage(9);
    } else {
      $entity->setBaseDamage(10);
    }

    $infinity = $this->hasEnchantment(VanillaEnchantments::INFINITY());
    if ($infinity) {
      $entity->setPickupMode(ArrowEntity::PICKUP_CREATIVE);
    }
    if (($punchLevel = $this->getEnchantmentLevel(VanillaEnchantments::PUNCH())) > 0) {
      $entity->setPunchKnockback($punchLevel);
    }
    if (($powerLevel = $this->getEnchantmentLevel(VanillaEnchantments::POWER())) > 0) {
      $entity->setBaseDamage($entity->getBaseDamage() + (($powerLevel + 1) / 2));
    }
    if ($this->hasEnchantment(VanillaEnchantments::FLAME())) {
      $entity->setOnFire(intdiv($entity->getFireTicks(), 20) + 100);
    }
    $ev = new EntityShootBowEvent($player, $this, $entity, $baseForce * 3);

    if ($baseForce < 0.1 || $diff < 3 || $player->isSpectator()) {
      $ev->cancel();
    }

    $ev->call();

    $entity = $ev->getProjectile(); //This might have been changed by plugins

    if ($ev->isCancelled()) {
      $entity->flagForDespawn();
      return ItemUseResult::FAIL;
    }

    $entity->setMotion($entity->getMotion()->multiply($ev->getForce()));

    if ($entity instanceof Projectile) {
      $projectileEv = new ProjectileLaunchEvent($entity);
      $projectileEv->call();
      if ($projectileEv->isCancelled()) {
        $ev->getProjectile()->flagForDespawn();
        return ItemUseResult::FAIL;
      }

      $ev->getProjectile()->spawnToAll();
      $location->getWorld()->addSound($location, new BowShootSound());
    } else {
      $entity->spawnToAll();
    }

    if ($player->hasFiniteResources()) {
      if (!$infinity) {
        $inventory?->removeItem($arrow);
      }
      $this->applyDamage(1);
    }

    return ItemUseResult::SUCCESS;
  }

}