<?php

namespace core\systems\player;

use core\systems\System;
use pocketmine\player\Player;

// the main purpose of this class since the SwimPlayer base class refactor is to update components swim player's have

class PlayerSystem extends System
{

  /**
   * @var SwimPlayer[]
   */
  private array $players = []; // an array of SwimPlayers

  public function init(): void
  {
    // nothing to init
  }

  // this is useless now
  public function getSwimPlayer(Player $player): ?SwimPlayer
  {
    if (isset($this->players[$player->getId()])) {
      return $this->players[$player->getId()];
    }
    return null;
  }

  // adds a swim player to the map
  public function registerPlayer(Player $player): void
  {
    $this->players[$player->getId()] = $player;
  }

  // this can just use a normal player object because the key is the ID of the player entity
  private function unregisterPlayer(Player $player): void
  {
    if (isset($this->players[$player->getId()])) {
      $this->players[$player->getId()]->exit();
      unset($this->players[$player->getId()]);
    }
  }

  public function updateTick(): void
  {
    foreach ($this->players as $swimPlayer) {
      $swimPlayer->updateTick();
    }
  }

  public function updateSecond(): void
  {
    foreach ($this->players as $swimPlayer) {
      $swimPlayer->updateSecond();
    }
  }

  public function exit(): void
  {
    foreach ($this->players as $swimPlayer) {
      $swimPlayer->exit();
    }
    $this->players = [];
  }

  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    $this->unregisterPlayer($swimPlayer);
  }

  /**
   * @return SwimPlayer[]
   */
  public function getPlayers(): array
  {
    return $this->players;
  }

}