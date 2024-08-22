<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\event\ServerGameEvent;
use core\systems\party\Party;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use jackmd\scorefactory\ScoreFactoryException;
use core\systems\scene\SceneSystem;
use JsonException;
use pocketmine\utils\TextFormat;

// this is a simple helper class to cache what scene and party the player is in and do some automated actions

class SceneHelper extends Component
{

  // the scene the player is currently in
  private Scene $scene;

  // the party, if at all, the player is currently in
  private ?Party $party = null; // TO DO : make sure this gets set to null when party disbands and when player leaves the party

  // this holds the ID of the current team they are in (only useful for the scene they are in)
  private int $teamNumber;

  // the event they are in, if they are in one at all
  private ?ServerGameEvent $event;

  // pointer to scene system
  private SceneSystem $sceneSystem;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->teamNumber = -1;
    $this->sceneSystem = $this->core->getSystemManager()->getSceneSystem();
    $this->scene = $this->sceneSystem->getScene('Loading'); // default scene right away as server loads the player's data
  }

  public function getScene(): ?Scene
  {
    return $this->scene;
  }

  /**
   * @param ServerGameEvent|null $event
   */
  public function setEvent(?ServerGameEvent $event): void
  {
    $this->event = $event;
  }

  /**
   * @return ServerGameEvent|null
   */
  public function getEvent(): ?ServerGameEvent
  {
    return $this->event ?? null;
  }

  /**
   * @return Party|null
   */
  public function getParty(): ?Party
  {
    return $this->party;
  }

  /**
   * @param Party|null $party
   */
  public function setParty(?Party $party): void
  {
    $this->party = $party;
  }

  public function isInParty(): bool
  {
    return isset($this->party);
  }

  // this only sets the scene pointer for caching
  public function setScene(Scene $scene): void
  {
    $this->scene = $scene;
  }

  /**
   * @param int $teamNumber
   */
  public function setTeamNumber(int $teamNumber): void
  {
    $this->teamNumber = $teamNumber;
  }

  /**
   * @return int
   */
  public function getTeamNumber(): int
  {
    return $this->teamNumber;
  }

  /**
   * @brief Sets the scene the player is in
   * @return bool if worked
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  public function setNewScene(string $sceneName): bool
  {
    $scene = $this->sceneSystem->getScene($sceneName);
    if ($scene) {
      $this->sceneSystem->setScene($this->swimPlayer, $scene);
      return true;
    } else {
      $this->swimPlayer->sendMessage(TextFormat::RED . "Failed to join scene: " . $sceneName);
      return false;
    }
  }

}