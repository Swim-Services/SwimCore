<?php

namespace core\custom\prefabs\boombox;

use pocketmine\entity\Attribute;
use pocketmine\entity\Location;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\types\entity\Attribute as NetworkAttribute;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\PropertySyncData;
use pocketmine\player\Player;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

class SmoothPrimedTNT extends PrimedTNT
{

  private bool $breaksBlocks;
  private float $blastRadius;

  public function __construct(Location $location, bool $breakBlocks = false, float $blastRadius = 3.5, ?CompoundTag $nbt = null)
  {
    parent::__construct($location, $nbt);
    $this->breaksBlocks = $breakBlocks;
    $this->blastRadius = $blastRadius;
    $this->setScale(4.0); // seems useless
  }

  public static function getNetworkTypeId(): string
  {
    return EntityIds::TNT_MINECART;
  }

  protected function broadcastMovement(bool $teleport = false): void
  {
    NetworkBroadcastUtils::broadcastPackets($this->hasSpawned, [MoveActorAbsolutePacket::create(
      $this->id,
      $this->getOffsetPosition($this->location->add(0, 0.7, 0)),
      $this->location->pitch,
      $this->location->yaw,
      $this->location->yaw,
      (
      ($this->onGround ? MoveActorAbsolutePacket::FLAG_GROUND : 0)
      )
    )]);
  }

  protected function sendSpawnPacket(Player $player): void
  {
    $player->getNetworkSession()->sendDataPacket(AddActorPacket::create(
      $this->getId(),
      $this->getId(),
      static::getNetworkTypeId(),
      $this->location->asVector3()->add(0, 0.7, 0),
      $this->getMotion(),
      $this->location->pitch,
      $this->location->yaw,
      $this->location->yaw,
      $this->location->yaw,
      array_map(function (Attribute $attr): NetworkAttribute {
        return new NetworkAttribute($attr->getId(), $attr->getMinValue(), $attr->getMaxValue(), $attr->getValue(), $attr->getDefaultValue(), []);
      }, $this->attributeMap->getAll()),
      $this->getAllNetworkData(),
      new PropertySyncData([], []),
      []
    ));
  }

  public function explode(): void
  {
    $ev = new EntityPreExplodeEvent($this, $this->blastRadius);
    $ev->call();
    if (!$ev->isCancelled()) {
      $explosion = new Explosion(Position::fromObject($this->location->add(0, $this->size->getHeight() / 2, 0), $this->getWorld()), $ev->getRadius(), $this);
      if ($this->breaksBlocks) {
        $explosion->explodeA();
      }
      $explosion->explodeB();
    }
  }

  protected function syncNetworkData(EntityMetadataCollection $properties): void
  {
    parent::syncNetworkData($properties);
    $properties->setFloat(EntityMetadataProperties::SCALE, 0.001);
  }

}