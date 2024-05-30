<?php

namespace core\custom\prefabs\boombox;

use core\systems\player\SwimPlayer;
use core\utils\InventoryUtil;
use pocketmine\block\Block;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\Random;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\IgniteSound;

class KnockerBox extends BaseBox
{

  public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool
  {
    if ($player instanceof SwimPlayer) {
      $pos = $blockReplace->getPosition();
      $primedTnt = new KnockerBoxEntity(Location::fromObject($pos->add(0.5, 0, 0.5), $pos->getWorld()), $player);
      $mot = (new Random())->nextSignedFloat() * M_PI * 2;
      $primedTnt->setMotion(new Vector3(-sin($mot) * 0.02, 0.2, -cos($mot) * 0.02));
      $primedTnt->setFuse(15);
      $primedTnt->spawnToAll();
      $primedTnt->broadcastSound(new IgniteSound());
      InventoryUtil::forceItemPop($player, $item);
    }

    return true;
  }

}