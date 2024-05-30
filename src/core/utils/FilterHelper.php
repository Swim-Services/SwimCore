<?php

namespace core\utils;

class FilterHelper
{

  private static string $chatPattern;
  private static string $ignPattern;

  // children, shield your eyes
  // should we base64 encode these words for the sake of children
  private static array $chatFilter = [
    "nigger",
    "nlgger",
    "nllgger",
    "nllgg3r",
    "nligger",
    "nilgger",
    "nilgg3r",
    "n1lgger",
    "nl1gger",
    "n1gger",
    "n1igger",
    "ni1gger",
    "n11gger",
    "n11gg3r",
    "n1igg3r",
    "ni1gg3r",
    "n1gg3r",
    "nigg3r",
    "niigg3r",
    "niigger",
    "nigger",
    "niggar",
    "niggor",
    "niig4er",
    "nig4er",
    "nii4ger",
    "ni4ger",
    "faggot",
    "kike",
    "chink",
    "jigaboo",
    "neeger",
    "nigar",
    "neger",
    "nigor",
    "pajeet"
  ];

  private static array $ignFilter = [
    "nigga",
    "n1gga",
    "niigga",
    "ni1gga",
    "n1igga",
    "nllgga",
    "n11gga",
    "n1lgga",
    "nligga",
    "nilgga",
    "niga",
    "n1ga",
    "niiga",
    "ni1ga",
    "n1iga",
    "nllga",
    "n11ga",
    "n1lga",
    "nliga",
    "nilga",
    "niggga",
    "n1ggga",
    "niiggga",
    "ni1ggga",
    "n1iggga",
    "nllggga",
    "n11ggga",
    "n1lggga",
    "nliggga",
    "nilggga"
  ];

  public static function chatFilter($str): bool
  {
    // make pattern on first get
    if (!isset(self::$chatPattern)) {
      self::$chatPattern = '/' . implode('|', array_map('preg_quote', self::$chatFilter)) . '/i';
    }
    // Use preg_match to check if the pattern matches any part of the string
    return preg_match(self::$chatPattern, $str) === 1;
  }

  public static function ignFilter($str): bool
  {
    // make pattern on first get
    if (!isset(self::$ignPattern)) {
      self::$ignPattern = '/' . implode('|', array_map('preg_quote', array_merge(self::$ignFilter, self::$chatFilter))) . '/i';
    }
    // Use preg_match to check if the pattern matches any part of the string
    return preg_match(self::$ignPattern, $str) === 1;
  }

}