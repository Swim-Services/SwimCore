<?php

namespace core\utils\config;

use pocketmine\utils\TextFormat;

class SwimConfig
{

  public DatabaseConfig $database;

  public array $motds = [
    TextFormat::AQUA . "SWIM.GG",
    TextFormat::BLUE . "SCRIMS",
    TextFormat::LIGHT_PURPLE . "MODDED SG",
    TextFormat::RED . "BRIDGE",
    TextFormat::DARK_AQUA . "SKYWARS"
  ];

}
