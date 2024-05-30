<?php

namespace core\scenes\duel\behaviors;

use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\utils\TimeHelper;
use pocketmine\entity\ExperienceManager;
use pocketmine\item\VanillaItems;

class ArrowRecharge extends EventBehaviorComponent
{

  private int $coolDown = 5; // default value
  private int $maxCoolDown;
  private float $time;
  private ExperienceManager $manager;

  public function init(): void
  {
    $this->manager = $this->swimPlayer->getXpManager();
    $this->maxCoolDown = $this->coolDown;
    $this->time = (float)$this->coolDown;
    $this->tickLifeTime = TimeHelper::secondsToTicks($this->coolDown);
    $this->manager->setXpAndProgress($this->coolDown, 1);
  }

  public function eventUpdateTick(): void
  {
    $this->time -= 0.05;
    if ($this->time <= 0) {
      $this->setDestroy();
    } else {
      $percent = $this->time / $this->maxCoolDown;
      $this->manager->setXpAndProgress(ceil($this->time), $percent);
    }
  }

  public function exit(): void
  {
    $this->swimPlayer->getInventory()->addItem(VanillaItems::ARROW());
    $this->manager->setXpAndProgress(0, 0);
  }

}