<?php

namespace core\scenes\duel\behaviors;

use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\systems\player\SwimPlayer;
use core\utils\TimeHelper;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\AnvilFallSound;
use pocketmine\world\sound\ItemBreakSound;

class AttackProtection extends EventBehaviorComponent
{

  private bool $destroyOnAttack = true;
  private int $lastPlayedSoundEffectTick = 100;

  public function init(): void
  {
    $this->swimPlayer->sendMessage(TextFormat::GREEN . "Giving you " . TimeHelper::ticksToSeconds($this->tickLifeTime) . " seconds of spawn protection!");
  }

  /**
   * @return bool
   */
  public function isDestroyOnAttack(): bool
  {
    return $this->destroyOnAttack;
  }

  /**
   * @param bool $destroyOnAttack
   */
  public function setDestroyOnAttack(bool $destroyOnAttack): void
  {
    $this->destroyOnAttack = $destroyOnAttack;
  }

  protected function entityDamageByEntityEvent(EntityDamageByEntityEvent $event): void
  {
    $event->cancel();
    $damager = $event->getDamager();
    if ($damager instanceof SwimPlayer) {
      $tick = $this->core->getServer()->getTick();
      // 5 tick cool down so doesn't absolutely spam the sound effect
      if (abs($this->lastPlayedSoundEffectTick - $tick <= 5)) {
        $damager->getWorld()->addSound($this->swimPlayer->getEyePos()->asVector3(), new AnvilFallSound());
        $this->lastPlayedSoundEffectTick = $tick;
      }
    }
  }

  protected function entityDamageByChildEntityEvent(EntityDamageByChildEntityEvent $event): void
  {
    $event->cancel();
  }

  public function attackedPlayer(EntityDamageByEntityEvent $event, SwimPlayer $victim): void
  {
    if ($this->destroyOnAttack && $victim->getSceneHelper()->getTeamNumber() != $this->swimPlayer->getSceneHelper()->getTeamNumber()) {
      $this->destroyMe = true;
    }
  }

  private function loseSpawnProtection(): void
  {
    $this->swimPlayer->sendMessage(TextFormat::YELLOW . "You lost your spawn protection!");
    $this->swimPlayer->getWorld()->addSound($this->swimPlayer->getEyePos()->asVector3(), new ItemBreakSound());
  }

  public function exit(): void
  {
    $this->loseSpawnProtection();
  }

}