<?php

namespace core\systems\map;

use core\SwimCore;
use pocketmine\math\Vector3;
use Symfony\Component\Filesystem\Path;

abstract class MapPool
{

  protected string $mapFile;
  protected string $mapsFolder;
  protected SwimCore $core;

  /**
   * @var MapInfo[]
   */
  protected array $maps = [];

  public function __construct(SwimCore $core, string $mapFile)
  {
    $this->core = $core;
    $this->mapFile = $mapFile;
    $this->mapsFolder = Path::join($this->core::$customDataFolder, "maps");
    // calls load on construction
    $this->loadMapData();
  }

  /**
   * Loads map data. This method needs to be implemented by subclasses.
   */
  abstract protected function loadMapData(): void;

  /**
   * @return bool
   * @breif checks if we have any non-active maps in the map pool
   */
  public final function hasAvailableMap(): bool
  {
    foreach ($this->maps as $map) {
      if (!$map->mapIsActive()) return true;
    }
    return false;
  }

  protected final function readPosition(mixed $data, string $pos): Vector3
  {
    return new Vector3($data[$pos]['x'], $data[$pos]['y'], $data[$pos]['z']);
  }

  public final function getMapInfoByName(string $mapName): ?MapInfo
  {
    return $this->maps[$mapName] ?? null;
  }

  /**
   * Gets a random inactive map and sets it to active.
   */
  public final function getRandomMap(bool $setActive = true): ?MapInfo
  {
    // Shuffle map keys
    $mapKeys = array_keys($this->maps);
    shuffle($mapKeys);

    // Iterate through the shuffled keys and find the first inactive map
    foreach ($mapKeys as $key) {
      $mapInfo = $this->maps[$key];
      // Check if the map is inactive
      if (!$mapInfo->mapIsActive()) {
        if ($setActive) $this->maps[$key]->setActive(true);
        return $mapInfo;
      }
    }

    // Return null if no inactive maps were found
    return null;
  }

}