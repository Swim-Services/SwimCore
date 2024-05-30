<?php

namespace core\utils;

use pocketmine\network\mcpe\protocol\types\DeviceOS;
use pocketmine\player\Player;

class PlayerInfoHelper
{

  public static function getOS(Player $player): string
  {
    $playerInfo = $player->getNetworkSession()->getPlayerInfo();
    $playerExtraData = $playerInfo->getExtraData();
    $playerOSEnum = (int)$playerExtraData["DeviceOS"];
    if ($playerExtraData["DeviceModel"] == "" && $playerOSEnum == DeviceOS::ANDROID) $playerOSEnum = 15; // detect linux launcher
    return self::getOSString($playerOSEnum);
  }

  private static function getOSString(int $os): string
  {
    return match ($os) {
      DeviceOS::ANDROID => "Android",
      DeviceOS::IOS => "iOS",
      DeviceOS::OSX => "MacOS",
      DeviceOS::AMAZON => "FireOS",
      DeviceOS::GEAR_VR => "GearVR",
      DeviceOS::HOLOLENS => "HoloLens",
      DeviceOS::WINDOWS_10 => "Windows 10",
      DeviceOS::WIN32 => "Win32",
      DeviceOS::DEDICATED => "Dedicated",
      DeviceOS::TVOS => "tvOS",
      DeviceOS::PLAYSTATION => "PS4",
      DeviceOS::NINTENDO => "Switch",
      DeviceOS::XBOX => "Xbox",
      DeviceOS::WINDOWS_PHONE => "Windows Phone",
      15 => "Linux",
      default => "Unknown",
    };
  }

}