<?php

namespace core\systems\entity\entities;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\TextFormat;

class FloatingText extends Actor
{

  protected bool $alwaysShowNameTag = true;
  protected bool $noClientPredictions = true;

  public static function getNetworkTypeId(): string
  {
    return EntityIds::ITEM;
  }

  protected function getInitialDragMultiplier(): float
  {
    return 0;
  }

  protected function getInitialGravity(): float
  {
    return 0;
  }

  protected function getInitialSizeInfo(): EntitySizeInfo
  {
    return new EntitySizeInfo(0.0001, 0.0001);
  }

  public function attack(EntityDamageEvent $source): void
  {
  }

  public function setText(string $title, string $text)
  {
    $fullText = $title . "\n" . TextFormat::RESET . $text;
    if ($this->getNameTag() !== $fullText) {
      $this->setNameTag($fullText);
    }
  }

  protected function syncNetworkData(EntityMetadataCollection $properties): void
  {
    parent::syncNetworkData($properties);

    $properties->setFloat(EntityMetadataProperties::SCALE, 0.01); //zero causes problems on debug builds
    $properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);
    $properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);
  }

}