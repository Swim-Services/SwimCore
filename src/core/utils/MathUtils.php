<?php

namespace core\utils;

class MathUtils
{

  public static function interpolate(float $float1, float $float2, float $percentage): float
  {
    return ($float1 + (($float2 - $float1) * $percentage));
  }

}