<?php

namespace core\systems\map;

use core\utils\PositionHelper;
use pocketmine\math\Vector3;

// this is intended for spawn info for warping players/teams in and determining if map is available to use

class MapInfo
{

  private bool $active;
  private string $mapName;
  private Vector3 $spawnPos1;
  private Vector3 $spawnPos2;

  // if swap is passed as true, spawn pos 1 and 2 will swap values, this is to fix json inconsistencies
  public function __construct
  (
    string  $mapName,
    Vector3 $spawnPos1 = new Vector3(0, 0, 0),
    Vector3 $spawnPos2 = new Vector3(0, 0, 0),
    bool    $swap = false
  )
  {
    $this->active = false;
    $this->mapName = $mapName;

    if ($swap) {
      $this->spawnPos1 = PositionHelper::centerVector($spawnPos2);
      $this->spawnPos2 = PositionHelper::centerVector($spawnPos1);
    } else {
      $this->spawnPos1 = PositionHelper::centerVector($spawnPos1);
      $this->spawnPos2 = PositionHelper::centerVector($spawnPos2);
    }
  }

  public function mapIsActive(): bool
  {
    return $this->active;
  }

  public function setActive(bool $state): void
  {
    $this->active = $state;
  }

  public function getMapName(): string
  {
    return $this->mapName;
  }

  public function getSpawnPos1(): Vector3
  {
    return $this->spawnPos1;
  }

  public function getSpawnPos2(): Vector3
  {
    return $this->spawnPos2;
  }

  public function swapSpawnPoints(): void
  {
    // Using list destructuring to swap the values directly (php is freaky AF)
    [$this->spawnPos1, $this->spawnPos2] = [$this->spawnPos2, $this->spawnPos1];
  }

}
