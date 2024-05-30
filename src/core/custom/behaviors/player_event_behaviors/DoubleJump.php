<?php

namespace core\custom\behaviors\player_event_behaviors;

use core\systems\player\components\behaviors\EventBehaviorComponent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;

class DoubleJump extends EventBehaviorComponent
{

  private float $yVelocity;
  private float $zxVelocity;
  private float $jumpInputCoolDown = 0;
  private bool $canJumpAgain = true;

  public function init(): void
  {
    $this->yVelocity = 1.6;
    $this->zxVelocity = 2;
    $this->jumpInputCoolDown = microtime(true);
  }

  public function eventUpdateTick(): void
  {
    // resets can jump flag if they are on the ground
    if ($this->swimPlayer->isOnGround()) {
      $this->canJumpAgain = true;
    }
  }

  protected function dataPacketReceiveEvent(DataPacketReceiveEvent $event): void
  {
    if (!$this->canJumpAgain) return;

    $packet = $event->getPacket();

    // check if auth input flag
    if (!$packet instanceof PlayerAuthInputPacket) {
      return;
    }

    // checks if is a starting input from the ground, we return since we only care about air jumps
    if ($packet->hasFlag(PlayerAuthInputFlags::START_JUMPING)) {
      return;
    }

    // check if a jump input on air
    if (!$packet->hasFlag(PlayerAuthInputFlags::NORTH_JUMP)) {
      return;
    }

    $time = microtime(true);
    $debounceTime = $time - $this->jumpInputCoolDown;

    if ($debounceTime >= 0.05 && $debounceTime <= 0.28) {
      $this->jump();
    }

    // update cool down
    $this->jumpInputCoolDown = $time;
  }

  private function jump(): void
  {
    // Get the speed of the player
    $speed = $this->swimPlayer->getMovementSpeed();

    // Get the direction the player is facing
    $direction = $this->swimPlayer->getDirectionVector();

    // Apply the speed to the x and z components of the direction
    $motionX = $direction->x * ($speed * $this->zxVelocity);
    $motionZ = $direction->z * ($speed * $this->zxVelocity);

    // Use the jump velocity for the y component
    $motionY = $this->swimPlayer->getJumpVelocity() * $this->yVelocity;

    // Set the player's motion
    $this->swimPlayer->setMotion(new Vector3($motionX, $motionY, $motionZ));

    // Make them unable to jump again, will be reset on next jump input and if on the ground
    $this->canJumpAgain = false;
  }

  /**
   * @return bool
   */
  public function isCanJumpAgain(): bool
  {
    return $this->canJumpAgain;
  }

}