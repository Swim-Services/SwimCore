<?php

namespace core\tasks;

use core\SwimCore;
use core\systems\SystemManager;
use pocketmine\scheduler\Task;

// Runs every tick

class SystemUpdateTask extends Task
{

  private SwimCore $core;
  private SystemManager $systemManager;
  private int $tps;
  private int $tick;

  public function __construct(SwimCore $core)
  {
    $this->tick = 0;
    $this->core = $core;
    $this->systemManager = $this->core->getSystemManager();
    $this->tps = $this->core->getServer()->getTicksPerSecond();
    echo "Running server updates at " . $this->tps . " TPS\n";
  }

  public function onRun(): void
  {
    $this->tick++;
    $this->systemManager->updateTick();
    if ($this->tick % $this->tps == 0) {
      $this->systemManager->updateSecond();
    }
  }

}