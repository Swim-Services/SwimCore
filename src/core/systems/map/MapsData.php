<?php

namespace core\systems\map;

use core\maps\pool\BasicDuelMaps;
use core\systems\player\SwimPlayer;
use core\systems\System;

// in a full server implementation, this is where you would put all your map classes and stuff for bridge and bed fight etc
class MapsData extends System
{

  private BasicDuelMaps $basicDuelMaps;

  public function init(): void
  {
    // basic duels
    $this->basicDuelMaps = new BasicDuelMaps($this->core, "BasicDuelMaps.json");
  }

  // in a full server implementation this would branch to the different MapInfo classes for each mode
  public function getRandomMapFromMode(string $mode): ?MapInfo
  {
    switch ($mode) {
      default:
        return $this->basicDuelMaps->getRandomMap();
    }
  }

  // in a full server implementation this would branch to the different MapInfo classes for each mode
  public function getNamedMapFromMode(string $mode, string $name): ?MapInfo
  {
    switch ($mode) {
      default:
        return $this->basicDuelMaps->getMapInfoByName($name);
    }
  }

  /**
   * @return BasicDuelMaps
   */
  public function getBasicDuelMaps(): BasicDuelMaps
  {
    return $this->basicDuelMaps;
  }

  public function updateTick(): void
  {
    // TODO: Implement updateTick() method.
  }

  public function updateSecond(): void
  {
    // TODO: Implement updateSecond() method.
  }

  public function exit(): void
  {
    // TODO: Implement exit() method.
  }

  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    // TODO: Implement handlePlayerLeave() method.
  }

}