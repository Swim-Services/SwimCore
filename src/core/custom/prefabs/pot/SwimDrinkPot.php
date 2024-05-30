<?php

namespace core\custom\prefabs\pot;

use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\Potion;
use pocketmine\item\VanillaItems;

class SwimDrinkPot extends Potion
{

  public function __construct(ItemIdentifier $identifier = new ItemIdentifier(ItemTypeIds::POTION), string $name = "Unknown", array $enchantmentTags = [])
  {
    parent::__construct($identifier, $name, $enchantmentTags);
  }

  // don't give a glass bottle
  public function getResidue(): Item
  {
    return VanillaItems::AIR();
  }

}