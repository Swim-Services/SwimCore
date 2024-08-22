<?php

namespace core;

class SwimCoreInstance
{

  public static SwimCore $core;

  public static function setInstance(SwimCore $core): void
  {
    self::$core = $core;
  }

  public static function getInstance(): SwimCore
  {
    return self::$core;
  }

}