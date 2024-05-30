<?php

namespace core\custom\prefabs\pearl;

use core\systems\player\SwimPlayer;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\item\EnderPearl;
use pocketmine\item\ItemIdentifier as IID;
use pocketmine\item\ItemTypeIds as Ids;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class SwimPearlItem extends EnderPearl
{

  private ?SwimPlayer $swimPlayer;
  private ?bool $animated;

  public function __construct(?SwimPlayer $swimPlayer, int $count = 1)
  {
    parent::__construct(new IID(Ids::ENDER_PEARL), "Ender Pearl");
    $this->setCount($count);
    $this->setCustomName(TextFormat::RESET . TextFormat::DARK_PURPLE . "Ender Pearl");
    if (isset($this->swimPlayer)) {
      $this->swimPlayer = $swimPlayer;
      $this->animated = $swimPlayer->getSettings()->getToggle("pearl");
    } else {
      $this->animated = false;
    }
  }

  protected function createEntity(Location $location, Player $thrower): Throwable
  {
    // if we don't have the swim player we can get it again here
    if (!isset($this->swimPlayer) && $thrower instanceof SwimPlayer) {
      $this->swimPlayer = $thrower;
      $this->animated = $thrower->getSettings()->getToggle("pearl");
    }
    return new SwimPearl($this->animated, $location, $thrower);
  }

}