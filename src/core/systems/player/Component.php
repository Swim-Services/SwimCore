<?php

namespace core\systems\player;

use core\SwimCore;

abstract class Component
{

  protected SwimCore $core;
  protected SwimPlayer $swimPlayer;
  protected bool $doesUpdate;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer, bool $doesUpdate = false)
  {
    $this->core = $core;
    $this->swimPlayer = $swimPlayer;
    $this->doesUpdate = $doesUpdate;
  }

  public final function doesUpdate(): bool
  {
    return $this->doesUpdate;
  }

  public final function setDoesUpdate(bool $value): void
  {
    $this->doesUpdate = $value;
  }

  public final function getPlayer(): SwimPlayer
  {
    return $this->swimPlayer;
  }

  // below is optional to implement for the derived classes

  public function init(): void
  {
  }

  public function updateSecond(): void
  {
  }

  public function updateTick(): void
  {
  }

  public function exit(): void
  {
  }

  // intended for when the player needs their behavior state fully cleared and reset, called when switching to a new scene
  public function clear(): void
  {
  }

}