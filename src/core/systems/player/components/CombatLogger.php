<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;

class CombatLogger extends Component
{

  private ?SwimPlayer $lastHit = null; // who last hit us
  private ?SwimPlayer $lastHitBy = null; // who was last hit by us
  private ?SwimPlayer $currentlyFighting = null;

  private bool $usingCombatCoolDown; // if we can only hit a player that we are in combat with currently
  private int $comboCounter;
  private float $lastSwingTime; // last time swung fist
  private float $lastDamagedTime; // last time was hit
  private int $coolDownTime; // time to set combat cool down too on starting combat
  private bool $isProtected; // used for preventing 3rd partying
  private bool $inCombat; // if in combat currently
  private int $combatCoolDown; // in seconds for how long it takes until inCombat is no longer activated

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer, bool $doesUpdate = true)
  {
    parent::__construct($core, $swimPlayer, $doesUpdate);
  }

  public function init(): void
  {
    $this->reset();
  }

  public function clear(): void
  {
    $this->reset();
  }

  public function reset(): void
  {
    /* this is bad and was giving uninitialized field issues, we simply want to make the pointer null
    unset($this->lastHit);
    unset($this->lastHitBy);
    unset($this->currentlyFighting);
    */
    $this->lastHit = null;
    $this->lastHitBy = null;
    $this->currentlyFighting = null;

    // Resetting other variables to their default states
    $this->usingCombatCoolDown = false;
    $this->comboCounter = 0;
    $this->lastSwingTime = 0.0;
    $this->lastDamagedTime = 0.0;
    $this->coolDownTime = 0;
    $this->isProtected = false;
    $this->inCombat = false;
    $this->combatCoolDown = 0;
  }

  public function updateSecond(): void
  {
    if ($this->combatCoolDown > 0) {
      $this->combatCoolDown--;
    }
    if ($this->combatCoolDown <= 0) {
      $this->inCombat = false;
      $this->currentlyFighting = null;
    }
  }

  // returns if attack was processed through
  public function handleAttack(SwimPlayer $victim): bool
  {
    if ($this->canAttack($victim)) {
      $this->logAttack($victim);
      $victim->getCombatLogger()->logDamage($this->swimPlayer);
      return true;
    }
    return false;
  }

  /*
   * to allow damage, both players should:
   * not be protected
   * OR
   * currently fighting them
   * not be in combat with someone else
   */
  public function canAttack(SwimPlayer $victim): bool
  {
    $victimCombatLogger = $victim->getCombatLogger();
    if (!$this->isProtected && !$victimCombatLogger->isProtected()) {
      return true;
    }

    if ($this->isProtected && $victimCombatLogger->isProtected) {
      if ($this->isWhoWeAreCurrentlyFighting($victim)) {
        return true;
      } else {
        if (!$this->isInCombat() && !$victimCombatLogger->isInCombat()) {
          return true;
        }
      }
    }

    return false;
  }

  public function isWhoWeAreCurrentlyFighting(SwimPlayer $attacker): bool
  {
    if (!isset($this->currentlyFighting)) return false;
    return $this->currentlyFighting->getId() == $attacker->getId();
  }

  // what to do when we get hit
  public function logDamage(SwimPlayer $attacker): void
  {
    $this->lastHitBy = $attacker;
    $this->currentlyFighting = $attacker;
    $this->inCombat = true;
    $this->comboCounter = 0;
    $this->combatCoolDown = $this->coolDownTime;
  }

  // what to do when we hit someone
  public function logAttack(SwimPlayer $victim): void
  {
    $this->lastHit = $victim;
    $this->currentlyFighting = $victim;
    $this->inCombat = true;
    $this->comboCounter++;
    $this->combatCoolDown = $this->coolDownTime;
  }

  /**
   * @param int $coolDownTime
   */
  public function setCoolDownTime(int $coolDownTime): void
  {
    $this->coolDownTime = $coolDownTime;
  }

  /**
   * @return int
   */
  public function getCoolDownTime(): int
  {
    return $this->coolDownTime;
  }

  /**
   * @param bool $isProtected
   */
  public function setIsProtected(bool $isProtected): void
  {
    $this->isProtected = $isProtected;
  }

  /**
   * @return bool
   */
  public function isProtected(): bool
  {
    return $this->isProtected;
  }

  /**
   * @return bool
   */
  public function isUsingCombatCoolDown(): bool
  {
    return $this->usingCombatCoolDown;
  }

  /**
   * @param bool $usingCombatCoolDown
   */
  public function setUsingCombatCoolDown(bool $usingCombatCoolDown): void
  {
    $this->usingCombatCoolDown = $usingCombatCoolDown;
  }

  /**
   * @return int
   */
  public function getCombatCoolDown(): int
  {
    return $this->combatCoolDown;
  }

  /**
   * @param int $combatCoolDown
   */
  public function setCombatCoolDown(int $combatCoolDown): void
  {
    $this->combatCoolDown = $combatCoolDown;
  }

  /**
   * @return int
   */
  public function getComboCounter(): int
  {
    return $this->comboCounter;
  }

  /**
   * @param int $comboCounter
   */
  public function setComboCounter(int $comboCounter): void
  {
    $this->comboCounter = $comboCounter;
  }

  /**
   * @return ?SwimPlayer
   */
  public function getLastHitBy(): ?SwimPlayer
  {
    if (!$this->lastHitBy?->isOnline()) return null; // stupid patch | TO DO: Fix player clean up in memory to be much more manual
    return $this->lastHitBy ?? null;
  }

  /**
   * @param ?SwimPlayer $lastHitBy
   */
  public function setLastHitBy(?SwimPlayer $lastHitBy): void
  {
    $this->lastHitBy = $lastHitBy;
  }

  /**
   * @param SwimPlayer $currentlyFighting
   */
  public function setCurrentlyFighting(SwimPlayer $currentlyFighting): void
  {
    $this->currentlyFighting = $currentlyFighting;
  }

  /**
   * @return ?SwimPlayer
   */
  public function getCurrentlyFighting(): ?SwimPlayer
  {
    return $this->currentlyFighting ?? null;
  }

  /**
   * @param SwimPlayer $lastHit
   */
  public function setLastHit(SwimPlayer $lastHit): void
  {
    $this->lastHit = $lastHit;
  }

  /**
   * @return ?SwimPlayer
   */
  public function getLastHit(): ?SwimPlayer
  {
    return $this->lastHit ?? null;
  }

  /**
   * @return bool
   */
  public function isInCombat(): bool
  {
    return $this->inCombat;
  }

  /**
   * @param bool $inCombat
   */
  public function setInCombat(bool $inCombat): void
  {
    $this->inCombat = $inCombat;
  }

  /**
   * @param float $lastDamagedTime
   */
  public function setLastDamagedTime(float $lastDamagedTime): void
  {
    $this->lastDamagedTime = $lastDamagedTime;
  }

  /**
   * @return float
   */
  public function getLastDamagedTime(): float
  {
    return $this->lastDamagedTime;
  }

  /**
   * @param float $lastSwingTime
   */
  public function setLastSwingTime(float $lastSwingTime): void
  {
    $this->lastSwingTime = $lastSwingTime;
  }

  /**
   * @return float
   */
  public function getLastSwingTime(): float
  {
    return $this->lastSwingTime;
  }

}