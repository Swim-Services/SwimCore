<?php

namespace core\systems\map;

use core\maps\pool\BasicDuelMaps;
use core\systems\player\SwimPlayer;
use core\systems\System;

class MapsData extends System
{

  private BasicDuelMaps $basicDuelMaps; // used flat basic duel maps for pot, boxing, buhc, etc..
  private BasicDuelMaps $miscDuelMaps; // unused, but all the OG swim.gg kohi maps are in this

  /** @var MapPool[] */
  private array $mapPools; // key is duel type as string

  public function init(): void
  {
    // basic duels
    $this->basicDuelMaps = new BasicDuelMaps($this->core, "BasicDuelMaps.json");
    $this->miscDuelMaps = new BasicDuelMaps($this->core, "MiscDuelMaps.json");

    // in a data structure because much more scalable for our getters
    $this->mapPools = [
      'basic' => $this->basicDuelMaps,
      'misc' => $this->miscDuelMaps,
    ];
  }

  public function modeHasAvailableMap(string $mode): bool
  {
    if (isset($this->mapPools[$mode])) {
      return $this->mapPools[$mode]->hasAvailableMap();
    }
    return $this->mapPools['basic']->hasAvailableMap();
  }

  public function getRandomMapFromMode(string $mode): ?MapInfo
  {
    if (isset($this->mapPools[$mode])) {
      return $this->mapPools[$mode]->getRandomMap();
    }
    return $this->mapPools['basic']->getRandomMap();
  }

  public function getNamedMapFromMode(string $mode, string $name): ?MapInfo
  {
    if (isset($this->mapPools[$mode])) {
      return $this->mapPools[$mode]->getMapInfoByName($name);
    }
    return $this->mapPools['basic']->getMapInfoByName($name);
  }

  /**
   * @return BasicDuelMaps
   */
  public function getBasicDuelMaps(): BasicDuelMaps
  {
    return $this->basicDuelMaps;
  }

  /**
   * @return BasicDuelMaps
   */
  public function getMiscDuelMaps(): BasicDuelMaps
  {
    return $this->miscDuelMaps;
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