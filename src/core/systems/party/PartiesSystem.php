<?php

namespace core\systems\party;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\systems\System;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class PartiesSystem extends System
{

  private array $parties;

  public function __construct(SwimCore $core)
  {
    parent::__construct($core);
    $this->parties = [];
  }

  /**
   * @return array
   */
  public function getParties(): array
  {
    return $this->parties;
  }

  public function addParty(Party $party): void
  {
    $this->parties[$party->getPartyName()] = $party;
  }

  /**
   * @throws ScoreFactoryException|JsonException
   * this should maybe be a method of Party
   */
  public function disbandParty(Party $party): void
  {
    foreach ($party->getPlayers() as $player) {
      $player->sendMessage(TextFormat::YELLOW . "The party has been disbanded.");
      $player->getSceneHelper()->setNewScene("Hub");
      $player->getSceneHelper()->setParty(null);
    }
    // delete the party
    if (isset($this->parties[$party->getPartyName()])) {
      unset($this->parties[$party->getPartyName()]);
    }
  }

  public function getPartyCount(): int
  {
    return count($this->parties);
  }

  public function getPartyPlayerIsIn(Player $player): ?Party
  {
    foreach ($this->parties as $party) {
      if ($party->hasPlayer($player)) {
        return $party;
      }
    }
    return null;
  }

  public function isInParty(SwimPlayer $player): bool
  {
    foreach ($this->parties as $party) {
      if ($party->hasPlayer($player)) {
        return true;
      }
    }
    return false;
  }

  public function partyNameTaken(string $name): bool
  {
    foreach ($this->parties as $party) {
      if ($party->getPartyName() === $name) {
        return true;
      }
    }
    return false;
  }

  // TO DO : make sure to have proper handling for when this happens during a duel
  // this probably currently does not have proper handling
  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  public function handlePlayerLeave(SwimPlayer $swimPlayer): void
  {
    $party = $this->getPartyPlayerIsIn($swimPlayer);
    if ($party) {
      $party->removePlayerFromParty($swimPlayer);
      if ($party->getCurrentPartySize() <= 0) {
        unset($this->parties[$party->getPartyName()]);
      }
    }
  }

  public function init(): void
  {
    // TODO: Implement init() method.
  }

  public function updateTick(): void
  {
    // TODO: Implement updateTick() method.
  }

  public function updateSecond(): void
  {
    // TODO: Implement updateSecond() method.
  }

  public function exit(): void
  {
    // TODO: Implement exit() method.
  }

}