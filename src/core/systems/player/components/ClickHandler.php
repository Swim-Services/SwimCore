<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;

class ClickHandler extends Component
{

  public const CPS_MAX = 16;

  private array $cps = [];
  private bool $showCPS;
  private float $lastSwingTime = 0;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->showCPS = true;
  }

  public function getLastSwingTime(): float
  {
    return $this->lastSwingTime;
  }

  public function setLastSwingTime(float $time)
  {
    $this->lastSwingTime = $time;
  }

  public function showCPS(bool $toggle): void
  {
    $this->showCPS = $toggle;
  }

  public function click(): void
  {
    $currentTime = microtime(true);
    $this->cps[] = $currentTime;
    $this->cleanUpCpsArray($currentTime);

    // rainbow cps score tag
    if ($this->showCPS) {
      $cps = $this->getCPS();
      $cpsTag = "§a$cps";
      if ($cps >= 13 and $cps < 15) {
        $cpsTag = "§e$cps";
      } elseif ($cps >= 15 and $cps < 18) {
        $cpsTag = "§6$cps";
      } elseif ($cps >= 18) {
        $cpsTag = "§c$cps";
      }
      $this->swimPlayer->sendPopup("§b" . $cpsTag . " §3CPS");
    }
  }

  public function getCPS(): int
  {
    $currentTime = microtime(true);
    $this->cleanUpCpsArray($currentTime);

    return count($this->cps);
  }

  private function cleanUpCpsArray(float $currentTime): void
  {
    $this->cps = array_filter($this->cps, static function (float $timestamp) use ($currentTime): bool {
      return ($currentTime - $timestamp) <= 1;
    });
  }

}
