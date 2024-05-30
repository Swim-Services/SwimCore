<?php

namespace core\utils;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\world\particle\BlockBreakParticle;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\Position;
use pocketmine\world\sound\ExplodeSound;
use pocketmine\world\World;

class CoolAnimations
{

  public static function lightningBolt(Position $pos, World $world): void
  {
    // create a lightning bolt entity as an add actor packet
    $lightning = AddActorPacket::create(Entity::nextRuntimeId(), 1, "minecraft:lightning_bolt", $pos->asVector3(), null,
      0, 0, 0, 0, [], [], new PropertySyncData([], []), []);
    // create a sound of the lightning bolt as a sound packet
    $sound = PlaySoundPacket::create("ambient.weather.thunder", $pos->getX(), $pos->getY(), $pos->getZ(), 1, 1);
    // do block break particle animation
    $block = $world->getBlock($pos->floor()->down());
    $particle = new BlockBreakParticle($block);
    $world->addParticle($pos, $particle, $world->getPlayers());
    // send packets
    PacketsHelper::broadCastPacketsToPlayers($world->getPlayers(), [$lightning, $sound]);
  }

  public static function bloodDeathAnimation(Position $pos, World $world): void
  {
    $world->addParticle(new Vector3($pos->getX(), $pos->getY(), $pos->getZ()), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
    $world->addParticle(new Vector3($pos->getX(), $pos->getY() + 1, $pos->getZ()), new BlockBreakParticle(VanillaBlocks::REDSTONE()));
  }

  public static function explodeAnimation(Position $pos, World $world): void
  {
    $world->addParticle($pos, new HugeExplodeSeedParticle());
    $world->addSound($pos, new ExplodeSound(), $world->getPlayers());
  }

}