<?php

namespace core\utils;

use core\SwimCore;
use pocketmine\scheduler\Task;

class TaskUtils
{

  public static function delayed(SwimCore $swimCore, int $delay, callable $cb): void
  {
    $swimCore->getScheduler()->scheduleDelayedTask(new class($cb) extends Task {
      public function __construct(private $cb)
      {
      }

      public function onRun(): void
      {
        call_user_func($this->cb);
      }
    }, $delay);
  }

  public static function repeating(SwimCore $swimCore, int $interval, callable $cb): void
  {
    $swimCore->getScheduler()->scheduleRepeatingTask(new class($cb) extends Task {
      public function __construct(private $cb)
      {
      }

      public function onRun(): void
      {
        call_user_func($this->cb);
      }
    }, $interval);
  }

}