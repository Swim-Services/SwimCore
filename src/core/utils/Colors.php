<?php

namespace core\utils;

use pocketmine\utils\TextFormat as TF;

// main purpose of this class is to put the colors in an array which can be indexed as int key -> color
class Colors
{

  public const BLUE = 0;
  public const GREEN = 1;
  public const AQUA = 2;
  public const RED = 3;
  public const LIGHT_PURPLE = 4;
  public const YELLOW = 5;
  public const MINECOIN_GOLD = 6;
  public const DARK_BLUE = 7;
  public const DARK_GREEN = 8;
  public const DARK_AQUA = 9;
  public const DARK_RED = 10;
  public const DARK_PURPLE = 11;
  public const GOLD = 12;
  public const GRAY = 13;
  public const DARK_GRAY = 14;
  public const BLACK = 15;
  public const WHITE = 16;

  public const COLORS = [
    self::BLUE => TF::BLUE,
    self::GREEN => TF::GREEN,
    self::AQUA => TF::AQUA,
    self::RED => TF::RED,
    self::LIGHT_PURPLE => TF::LIGHT_PURPLE,
    self::YELLOW => TF::YELLOW,
    self::MINECOIN_GOLD => TF::MINECOIN_GOLD,
    self::DARK_BLUE => TF::DARK_BLUE,
    self::DARK_GREEN => TF::DARK_GREEN,
    self::DARK_AQUA => TF::DARK_AQUA,
    self::DARK_RED => TF::DARK_RED,
    self::DARK_PURPLE => TF::DARK_PURPLE,
    self::GOLD => TF::GOLD,
    self::GRAY => TF::GRAY,
    self::DARK_GRAY => TF::DARK_GRAY,
    self::BLACK => TF::BLACK,
    self::WHITE => TF::WHITE,
  ];

  public const BLACK_AND_WHITE = [TF::DARK_GRAY, TF::WHITE];
  public const CHROMA = [TF::DARK_RED, TF::RED, TF::GOLD, TF::YELLOW, TF::GREEN, TF::AQUA, TF::BLUE, TF::DARK_PURPLE];

  public const COLOR_LIST = [
    "black" => TF::BLACK,
    "dark_blue" => TF::DARK_BLUE,
    "dark_green" => TF::DARK_GREEN,
    "dark_aqua" => TF::DARK_AQUA,
    "dark_red" => TF::DARK_RED,
    "dark_purple" => TF::DARK_PURPLE,
    "gold" => TF::GOLD,
    "gray" => TF::GRAY,
    "dark_gray" => TF::DARK_GRAY,
    "blue" => TF::BLUE,
    "green" => TF::GREEN,
    "aqua" => TF::AQUA,
    "red" => TF::RED,
    "light_purple" => TF::LIGHT_PURPLE,
    "yellow" => TF::YELLOW,
    "white" => TF::WHITE,
    "chroma" => self::CHROMA
  ];

  public static function colorize(array $colorSeq, string $text) : string {
    $colorizedString = "";
    for ($i = 0; $i < strlen($text); $i++) {
      $colorizedString .= $colorSeq[($i % count($colorSeq))] . $text[$i];
    }
    return $colorizedString;
  }

  public static function handleMessageColor(string $color, string $msg): string {
    $colorizedString = "";
    if (array_key_exists($color, self::COLOR_LIST)) {
      if ($color == "chroma") {
        $colorizedString = self::colorize(self::CHROMA, $msg);
      } else {
        $colorValue = self::COLOR_LIST[$color];
        $colorizedString = $colorValue . $msg;
      }
    }
    return $colorizedString;
  }

}