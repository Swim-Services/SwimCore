<?php

namespace core\custom\behaviors\player_event_behaviors;

use core\systems\player\components\behaviors\EventBehaviorComponent;

class MaxDistance extends EventBehaviorComponent
{

  // if player goes further than 200 blocks they go back to spawn, this is used for hub scenes
  public function eventUpdateSecond(): void
  {
    $spawn = $this->swimPlayer->getWorld()->getSafeSpawn();
    if ($this->swimPlayer->getPosition()->distance($spawn) > 200) {
      $this->swimPlayer->teleport($spawn);
    }
  }

}