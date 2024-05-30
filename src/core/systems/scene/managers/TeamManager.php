<?php

namespace core\systems\scene\managers;

use core\systems\scene\misc\Team;
use core\systems\scene\Scene;

class TeamManager
{

  /**
   * @var Team[] Array of Teams
   */
  private array $teams = array();

  private Scene $parentScene;
  private int $totalCreatedTeams; // for team id

  private Team $specTeam;

  public function __construct(Scene $scene)
  {
    $this->parentScene = $scene;
    $this->totalCreatedTeams = 0;
  }

  /**
   * Array key is string team name
   * @return Team[]
   */
  public function getTeams(): array
  {
    return $this->teams;
  }

  /**
   * @param Team $specTeam
   */
  public function setSpecTeam(Team $specTeam): void
  {
    $this->specTeam = $specTeam;
  }

  public function getTeamByColor(string $color): ?Team
  {
    foreach ($this->teams as $team) {
      if ($team->getTeamColor() === $color) {
        return $team;
      }
    }
    // not found
    return null;
  }

  /**
   * @return Team
   */
  public function getSpecTeam(): Team
  {
    return $this->specTeam;
  }

  public function getFirstOpposingTeam(Team $team): ?Team
  {
    foreach ($this->teams as $t) {
      if ($t !== $team) {
        return $t;
      }
    }
    return null;
  }

  /**
   * Array key is string team name
   * @return Team[]
   */
  public function getAllOpposingTeams(Team $team): array
  {
    $tempTeam = [];
    foreach ($this->teams as $t) {
      if ($t !== $team) {
        $tempTeam[$t->getTeamName()] = $t;
      }
    }
    return $tempTeam;
  }

  /**
   * @return Scene
   */
  public function getParentScene(): Scene
  {
    return $this->parentScene;
  }

  /**
   * @param Scene $parentScene
   */
  public function setParentScene(Scene $parentScene): void
  {
    $this->parentScene = $parentScene;
  }

  public function getTeamByID(int $id): ?Team
  {
    foreach ($this->teams as $team) {
      if ($team->getTeamID() == $id) {
        return $team;
      }
    }
    // not found
    return null;
  }

  // constructs and registers and returns the team
  public function makeTeam(string $teamName, string $teamColor, bool $respawn = false, int $targetScore = 1): Team
  {
    $team = new Team($teamName, $teamColor, $respawn, $targetScore, $this->parentScene, $this->totalCreatedTeams);
    $this->teams[$teamName] = $team;
    $this->totalCreatedTeams++; // increment team count
    return $team;
  }

  public function getTeam(string $teamName): ?Team
  {
    if (isset($this->teams[$teamName])) {
      return $this->teams[$teamName];
    }
    return null;
  }

}