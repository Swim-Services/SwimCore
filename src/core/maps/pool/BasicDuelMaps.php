<?php

namespace core\maps\pool;

use core\systems\map\MapInfo;
use core\systems\map\MapPool;
use Symfony\Component\Filesystem\Path;

class BasicDuelMaps extends MapPool
{

  protected function loadMapData(): void
  {
    $basicDuelMapsJson = file_get_contents(Path::join($this->mapsFolder, $this->mapFile));
    $basicDuelMapsData = json_decode($basicDuelMapsJson, true);

    foreach ($basicDuelMapsData as $mapName => $spawnPoints) {
      $spawnPoint1 = $this->readPosition($spawnPoints, 'spawnPoint1');
      $spawnPoint2 = $this->readPosition($spawnPoints, 'spawnPoint2');

      // Construct map info object
      $this->maps[$mapName] = new MapInfo($mapName, $spawnPoint1, $spawnPoint2);
    }
  }

}