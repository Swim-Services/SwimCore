<?php

namespace core\custom\behaviors\player_event_behaviors;

use core\SwimCore;
use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\systems\player\SwimPlayer;
use pocketmine\event\entity\EntityDamageEvent;

class NoFall extends EventBehaviorComponent
{

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer, bool $hasLifeTime = true, int $tickLifeTime = 120)
  {
    parent::__construct("nofall", $core, $swimPlayer, true, $hasLifeTime, $tickLifeTime);
  }

  protected function entityDamageEvent(EntityDamageEvent $event): void
  {
    if ($event->getCause() === EntityDamageEvent::CAUSE_FALL) {
      $event->cancel();
    }
  }

}