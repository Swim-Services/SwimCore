<?php

namespace core\custom\prefabs\boombox;

use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\TNT;
use pocketmine\block\VanillaBlocks;

abstract class BaseBox extends TNT
{

  public function __construct()
  {
    $block = VanillaBlocks::TNT();
    $typeID = $block->getTypeId();

    parent::__construct(new BlockIdentifier($typeID), "Custom TNT", new BlockTypeInfo($block->getBreakInfo()));
  }

}