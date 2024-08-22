<?php

namespace core\systems\scene\managers;

use core\systems\scene\misc\Team;
use core\systems\scene\Scene;
use core\utils\PositionHelper;

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
      if ($t !== $team && !$t->isSpecTeam()) {
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

  public function lookAtEachOther(): void
  {
    /** @var Team[] $validTeams */
    $validTeams = [];

    // Filter out the spectator team
    foreach ($this->teams as $team) {
      if (!$team->isSpecTeam()) {
        $validTeams[] = $team;
      }
    }

    // Ensure we have exactly two valid teams for this
    if (count($validTeams) == 2) {
      $team1 = $validTeams[0];
      $team2 = $validTeams[1];

      $pos1 = $team1->getSpawnPoint(0);
      $pos2 = $team2->getSpawnPoint(0);

      foreach ($team1->getPlayers() as $player) {
        $player->teleport($player->getPosition(), PositionHelper::getYawTowards($pos1, $pos2), PositionHelper::getPitchTowards($pos1, $pos2, $player->getEyeHeight()));
      }

      foreach ($team2->getPlayers() as $player) {
        $player->teleport($player->getPosition(), PositionHelper::getYawTowards($pos2, $pos1), PositionHelper::getPitchTowards($pos2, $pos1, $player->getEyeHeight()));
      }
    }
  }

}