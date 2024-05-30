<?php

namespace core\scenes\duel;

use core\systems\player\SwimPlayer;
use core\systems\scene\misc\Team;
use core\utils\CustomDamage;
use core\utils\PositionHelper;
use core\utils\ServerSounds;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

// for the common logic in a game like bridge or battle rush, this is left in the lightweight engine to show what real prod gameplay scripting looks like
abstract class SkyGoalGame extends Duel
{

  protected int|float $voidLevel;
  protected int|float $buildHeight;
  protected int|float $buildLimitXZ;
  protected Vector3 $midPoint;
  protected bool $clearOnGoal = false;

  protected Position $redSpawn;
  protected Position $blueSpawn;
  protected ?Team $redTeam;
  protected ?Team $blueTeam;

  private bool $countingDown = false;
  private int $countDownSeconds = 4;
  private int $currentCountDown = 4;

  public function duelUpdateSecond(): void
  {
    if ($this->countingDown) {
      $this->currentCountDown--;
      if ($this->currentCountDown <= 0) {
        $this->countingDown = false;
        $this->sceneSound("random.orb");
        $this->sceneAnnouncement(TextFormat::GREEN . "Go!");
        foreach ($this->players as $player) {
          $player->setNoClientPredictions(false); // move again
        }
      } else {
        $this->sceneAnnouncement(TextFormat::GRAY . "Starting in " . TextFormat::YELLOW . $this->currentCountDown);
        $this->sceneSound("random.click");
      }
    }
  }

  public function duelScoreboard(SwimPlayer $player): void
  {
    $this->duelScoreboardWithScore($player);
  }

  public function sceneBlockBreakEvent(BlockBreakEvent $event, SwimPlayer $swimPlayer): void
  {
    parent::sceneBlockBreakEvent($event, $swimPlayer);
    $event->setDrops([]);
  }

  public function sceneBlockPlaceEvent(BlockPlaceEvent $event, SwimPlayer $swimPlayer): void
  {
    // can't place blocks yet
    if ($this->countingDown) {
      $event->cancel();
      return;
    }

    foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
      // check if too high or too low
      if ($y >= $this->buildHeight || $y <= $this->voidLevel) {
        $swimPlayer->sendMessage(TextFormat::RED . "Build Limit Reached!");
        $event->cancel();
        return;
      }

      // check if spawn trapping
      $tempVec = new Vector3($x, $y, $z);
      if (PositionHelper::sameXZ($tempVec, $this->redSpawn) || PositionHelper::sameXZ($tempVec, $this->blueSpawn)) {
        $swimPlayer->sendMessage(TextFormat::RED . "You can't place blocks on top of spawn points!");
        $event->cancel();
        return;
      }

      // check if too far away
      $distance = $tempVec->distance($this->midPoint);
      if ($distance >= $this->buildLimitXZ) {
        $swimPlayer->sendMessage(TextFormat::RED . "Build Limit Reached!");
        $event->cancel();
        return;
      }

      // check if in water
      if ($this->checkBlocksForWater($x, $y, $z)) {
        $swimPlayer->sendMessage(TextFormat::RED . "You can't place blocks around the goal!");
        $event->cancel();
        return;
      }
    }

    // otherwise, handle normally
    $this->blocksManager->handleBlockPlace($event);
  }

  private function checkBlocksForWater(int|float $x, int|float $y, int|float $z, int $checks = 4): bool
  {
    for ($i = 0; $i < $checks; $i++) {
      if ($this->world->getBlockAt($x, $y - $i, $z)->getTypeId() == BlockTypeIds::WATER) return true;
    }
    return false;
  }


  /**
   * @throws ScoreFactoryException
   */
  public function updateTick(): void
  {
    if ($this->started && !$this->countingDown && !$this->finished) {
      foreach ($this->teamManager->getTeams() as $team) {
        if ($team->isSpecTeam()) continue;
        foreach ($team->getPlayers() as $player) {
          if (!$player->isSpectator()) {
            $position = $player->getPosition();

            // void check
            if ($position->y <= $this->voidLevel) {
              // kill them, but try to get the most recent attacker from combat logger
              $attacker = $player->getCombatLogger()->getlastHitBy();
              $this->deathHandle($player, $team, $attacker);
              continue;
            }

            // the goals are in water, so if a player is in water then they are in a goal
            if ($this->world->getBlock($position)->getTypeId() == BlockTypeIds::WATER) { // is this expensive?
              if ($this->jumpedInGoal($player)) return; // stop iterating for this tick if a goal was scored
            }
          }
        }
      }
    }
  }

  /**
   * @param SwimPlayer $swimPlayer
   * @return bool if it was a goal scored or not, false indicating they jumped in their own goal
   * @throws ScoreFactoryException
   */
  private function jumpedInGoal(SwimPlayer $swimPlayer): bool
  {
    $position = $swimPlayer->getPosition();
    $distanceToRed = $position->distanceSquared($this->redSpawn);
    $distanceToBlue = $position->distanceSquared($this->blueSpawn);

    $isBlueTeam = $this->blueTeam->isInTeam($swimPlayer);
    $isInBlueGoal = $distanceToBlue < $distanceToRed;

    if ($isInBlueGoal && $isBlueTeam) { // if blue player jumped in own goal
      $this->respawn($swimPlayer, $this->blueTeam);
      return false;
    } else if ($isInBlueGoal && !$isBlueTeam) { // if red player jumped in blue goal
      $this->scored($swimPlayer, $this->redTeam, $this->blueTeam);
      return true;
    } else if (!$isInBlueGoal && $isBlueTeam) { // if blue player jumped in red goal
      $this->scored($swimPlayer, $this->blueTeam, $this->redTeam);
      return true;
    } else if (!$isInBlueGoal && !$isBlueTeam) { // if red player jumped in own goal
      $this->respawn($swimPlayer, $this->redTeam);
      return false;
    }
    return false;
  }

  /**
   * @throws ScoreFactoryException
   */
  private function scored(SwimPlayer $scorer, Team $scorerTeam, Team $scoredOnTeam): void
  {
    // update score
    $scorerTeam->setScore($scorerTeam->getScore() + 1);
    $this->updateBoardsForAll($this);

    // send a title
    $color = $scorerTeam->getTeamColor();
    $title = $color . $scorer->getNicks()->getNick() . " Scored!";
    $subtitle = $color . $this->blueTeam->getScore() . " - " . $this->redTeam->getScore();
    $this->sceneTitle($title, $subtitle, 5, 40);

    // end duel if needed
    if ($scorerTeam->getScore() >= $scorerTeam->getTargetScore()) {
      $this->scoreBasedDuelEnd($scorerTeam);
      return;
    }

    // warp back
    $this->sendTeamBackToSpawnAndFreeze($this->redTeam, $this->redSpawn);
    $this->sendTeamBackToSpawnAndFreeze($this->blueTeam, $this->blueSpawn);

    // put the duel in count down mode
    $this->countingDown = true;
    $this->currentCountDown = $this->countDownSeconds;

    if ($this->clearOnGoal) {
      $this->blocksManager->clearPlacedBlocks();
    }
  }

  private function sendTeamBackToSpawnAndFreeze(Team $team, Position $position): void
  {
    foreach ($team->getPlayers() as $player) {
      $player->teleport($position);
      $this->applyKit($player);
      $player->setNoClientPredictions(); // no moving
    }
  }

  protected function playerHit(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    // apply no critical custom damage
    CustomDamage::customDamageHandle($event);
    // update who last hit them
    $victim->getCombatLogger()->setlastHitBy($attacker);
  }

  protected function playerKilled(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    $victimTeam = $this->getPlayerTeam($victim);
    $this->deathHandle($victim, $victimTeam, $attacker);
  }

  protected function playedDiedToMiscDamage(EntityDamageEvent $event, SwimPlayer $swimPlayer): void
  {
    $victimTeam = $this->getPlayerTeam($swimPlayer);
    // validate team
    if (!$victimTeam || $victimTeam->isSpecTeam()) return; // this is bad but shouldn't ever happen
    // get last hit by
    $lastHitBy = $swimPlayer->getCombatLogger()->getLastHitBy();
    $this->deathHandle($swimPlayer, $victimTeam, $lastHitBy);
  }

  protected function playerDiedToChildEntity(EntityDamageByChildEntityEvent $event, SwimPlayer $victim, SwimPlayer $attacker, Entity $childEntity): void
  {
    $victimTeam = $this->getPlayerTeam($victim);
    // validate team
    if (!$victimTeam || $victimTeam->isSpecTeam()) return; // this is bad but shouldn't ever happen
    $this->deathHandle($victim, $victimTeam, $attacker);
  }

  protected function defaultDeathHandle(SwimPlayer $attacker, SwimPlayer $victim): void
  {
    // no op (this was causing a bug not having its overrides, kind of the base classes fault)
  }

  private function deathHandle(SwimPlayer $swimPlayer, Team $team, ?SwimPlayer $attacker = null): void
  {
    // if no attacker get most recent killer
    if (!isset($attacker)) {
      $attacker = $swimPlayer->getCombatLogger()->getlastHitBy();
    }
    // send kill message to attacker
    if (isset($attacker)) {
      $this->processKillFromPlayer($attacker, $swimPlayer, $team);
    }
    // update deaths count
    $swimPlayer->getAttributes()->emplaceIncrementIntegerAttribute("deaths");
    // respawn them
    $this->respawn($swimPlayer, $team);
  }

  // does a message and updates the kills count
  private function processKillFromPlayer(SwimPlayer $attacker, SwimPlayer $victim, Team $victimTeam): void
  {
    $this->sceneAnnouncement($this->getPlayerTeam($attacker)->getTeamColor() . $attacker->getNicks()->getNick() . TextFormat::YELLOW . " killed "
      . $victimTeam->getTeamColor() . $victim->getNicks()->getNick());
    ServerSounds::playSoundToPlayer($attacker, "random.orb", 2, 1);

    $attacker->getAttributes()->emplaceIncrementIntegerAttribute("kills");
  }

  private function respawn(SwimPlayer $swimPlayer, Team $team): void
  {
    if ($team === $this->redTeam) {
      $swimPlayer->teleport($this->redSpawn);
    } else {
      $swimPlayer->teleport($this->blueSpawn);
    }
    $swimPlayer->getCombatLogger()->reset();
    $this->applyKit($swimPlayer);
  }

}