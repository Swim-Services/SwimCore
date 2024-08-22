<?php

namespace core\custom\prefabs\hub;

use core\systems\entity\entities\Actor;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

class FinnEntity extends Actor
{

  public static function getNetworkTypeId(): string
  {
    return "swim:finn";
  }

  public function __construct(Location $location, ?Scene $parentScene = null)
  {
    parent::__construct($location, $parentScene);
    $this->setMaxHealth(999);
    $this->setHasGravity(false);
    $this->setNameTag(TextFormat::GREEN . "Swimfan72 but real");
    $this->setScale(1);
    $this->anchored = true;
  }

  protected function attackedByPlayer(EntityDamageByEntityEvent $source, SwimPlayer $player)
  {
    $this->interact($player);
  }

  protected function playerInteract(SwimPlayer $player, Vector3 $clickPos): void
  {
    $this->interact($player);
  }

  private function interact(SwimPlayer $player): void
  {
    if ($player->getSceneHelper()->getScene()->getSceneName() == "Hub") {
      // this would open a form of some sort
    }
  }

}