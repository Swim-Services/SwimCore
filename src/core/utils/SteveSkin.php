<?php

namespace core\utils;

use core\SwimCore;
use JsonException;
use pocketmine\entity\Skin;
use Symfony\Component\Filesystem\Path;

class SteveSkin
{

  // private Skin $skin;

  private static Skin $skin; // this might need to be made new each time

  /**
   * @throws JsonException
   */
  public static function loadInSkin(): void
  {
    $jsonPath = Path::join(SwimCore::$assetFolder, "steve.json");

    // Read the JSON file
    $jsonContents = file_get_contents($jsonPath);

    // Decode the JSON file into an associative array
    $skinData = json_decode($jsonContents, true);

    // Extract the fields into strings
    $skinId = $skinData['skinId'];
    $skinDataInfo = base64_decode($skinData['skinData']); // decode this fat blob
    $capeData = $skinData['capeData'];
    $geometryName = $skinData['geometryName'];
    $geometryData = $skinData['geometryData'];

    // build
    self::$skin = new Skin($skinId, $skinDataInfo, $capeData, $geometryName, $geometryData);
  }

  /**
   * @throws JsonException
   */
  public static function getSteveSkin(): Skin
  {
    if (!self::$skin) {
      self::loadInSkin();
    }
    return self::$skin;
  }

}