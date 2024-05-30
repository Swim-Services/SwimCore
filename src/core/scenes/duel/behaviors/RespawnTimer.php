<?php

namespace core\scenes\duel\behaviors;

use core\scenes\duel\Duel;
use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\systems\scene\misc\Team;
use core\utils\TimeHelper;
use pocketmine\utils\TextFormat;

class RespawnTimer extends EventBehaviorComponent
{

  private ?Duel $duel;
  private Team $team;

  private int $secondsAlive = 0;

  private function message(): void
  {
    $coolDown = TimeHelper::ticksToSeconds($this->tickLifeTime) - $this->secondsAlive;
    $this->swimPlayer->sendActionBarMessage(TextFormat::WHITE . "Respawning in " . TextFormat::RED . $coolDown . "..");
  }

  public function init(): void
  {
    $this->message();
  }

  public function setDuel(Duel $duel): void
  {
    $this->duel = $duel;
  }

  public function setTeam(Team $team): void
  {
    $this->team = $team;
  }

  public function eventUpdateSecond(): void
  {
    $this->secondsAlive++;
    $this->message();
  }

  // duel needs respawn to be implemented, this class is more just an example
  // for the main server this behavior is used in bed fight, where respawn is implemented and this class field is specifically a BedFight duel
  public function exit(): void
  {
    // $this->duel?->respawn($this->swimPlayer, $this->team);
  }

}