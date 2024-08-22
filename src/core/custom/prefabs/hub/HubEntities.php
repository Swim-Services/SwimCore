<?php

namespace core\custom\prefabs\hub;

use core\systems\scene\Scene;
use core\SwimCoreInstance;
use pocketmine\entity\Location;
use pocketmine\world\Position;
use ReflectionException;

class HubEntities
{

  /**
   * @throws ReflectionException
   */
  public static function spawnToScene(Scene $scene): void
  {
    $hub = SwimCoreInstance::getInstance()->getServer()->getWorldManager()->getWorldByName("hub");
    if (!isset($hub)) {
      echo "Could not find hub world\n";
      return;
    }

    // make the finn entity
    $finnPos = new Position(1576, 129, 591, $hub);
    $finnLocation = Location::fromObject($finnPos->round()->add(0.5, 0, 0.5), $hub);
    $finnLocation->yaw = 0;
    new FinnEntity($finnLocation, $scene);
  }

}