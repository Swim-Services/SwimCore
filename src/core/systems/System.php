<?php

namespace core\systems;

use core\SwimCore;
use core\systems\player\SwimPlayer;

abstract class System
{

  protected SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
  }

  abstract public function init(): void;

  abstract public function updateTick(): void;

  abstract public function updateSecond(): void;

  abstract public function exit(): void;

  abstract public function handlePlayerLeave(SwimPlayer $swimPlayer): void;

}