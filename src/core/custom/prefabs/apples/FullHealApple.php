<?php

namespace core\custom\prefabs\apples;

use pocketmine\entity\Living;
use pocketmine\item\GoldenApple;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\utils\TextFormat;

class FullHealApple extends GoldenApple
{

  public function __construct
  (
    ItemIdentifier $identifier = new ItemIdentifier(ItemTypeIds::GOLDEN_APPLE),
    string         $name = TextFormat::MINECOIN_GOLD . "Heal Apple",
    array          $enchantmentTags = []
  )
  {
    parent::__construct($identifier, $name, $enchantmentTags);
  }

  public function onConsume(Living $consumer): void
  {
    $consumer->setHealth($consumer->getMaxHealth());
  }

}