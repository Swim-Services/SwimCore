<?php

namespace core\scenes\duel;

class IconHelper
{

  public static function getIcon(string $string): ?string
  {
    return match (strtolower($string)) {
      default => '',
      'nodebuff' => Nodebuff::getIcon(),
      'boxing' => Boxing::getIcon(),
      'midfight' => Midfight::getIcon(),
    };
  }

}