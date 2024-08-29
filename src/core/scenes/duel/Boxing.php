<?php

namespace core\scenes\duel;

use core\systems\player\SwimPlayer;
use core\systems\scene\misc\Team;
use core\utils\BehaviorEventEnums;
use core\utils\InventoryUtil;
use core\utils\TimeHelper;
use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class Boxing extends Duel
{

  public static function getIcon(): string
  {
    return "textures/items/diamond_sword";
  }

  public function init(): void
  {
    $this->registerCanceledEvents([
      BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT,
      BehaviorEventEnums::BLOCK_BREAK_EVENT,
      BehaviorEventEnums::BLOCK_PLACE_EVENT
    ]);
    $this->kb = 0.404;
    $this->vertKB = 0.404;
  }

  protected function applyKit(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->setGamemode(GameMode::ADVENTURE);
    // $player->getEffects()->add(new EffectInstance(VanillaEffects::RESISTANCE(), 9999999, 255, false)); // give resistance is an alternative way to do it
    InventoryUtil::boxingKit($swimPlayer);
  }

  protected function duelStart(): void
  {
    $highestCount = 0;
    foreach ($this->teamManager->getTeams() as $team) {
      $size = $team->getTeamSize();
      if ($size > $highestCount) {
        $highestCount = $size;
      }
    }
    // set all teams target hits to the highest team member count times 100
    foreach ($this->teamManager->getTeams() as $team) {
      $team->setTargetScore($highestCount * 100);
    }
  }

  /**
   * @throws ScoreFactoryException
   */
  protected function playerHit(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    // get team of the hitter, so we can update and win check the hit counts
    $team = $this->getPlayerTeam($attacker);
    if (!$team) {
      $event->cancel();
      return;
    }
    $team = $this->getPlayerTeam($attacker);

    // no damage
    $event->setModifier(-999, 0);

    // update the hit counts for the team by 1
    $hits = $team->getScore() + 1;
    $team->setScore($hits);

    // manually update the scoreboard so the hit counts aren't outdated
    $this->duelScoreboard($attacker);
    $this->duelScoreboard($victim);

    // end duel
    if ($hits >= $team->getTargetScore()) {
      $this->scoreBasedDuelEnd($team);
    }
  }

  protected function duelOver(Team $winners, Team $losers): void
  {
    if ($winners->getTeamSize() > 1) {
      $this->partyWin($winners->getTeamName());
    } else { // assumes that it was a 1v1 and the 1 loser in the losers array is who we just beat
      $loser = $this->losers[array_key_first($this->losers)];
      $players = $winners->getPlayers();
      $winner = $players[array_key_first($players)];
      if ($loser && $winner) {
        // get winner hits and loser hits
        $winnerHits = $winners->getScore();
        $attackerString = TextFormat::GRAY . " [" . TextFormat::GREEN . $winnerHits . TextFormat::GRAY . "]";
        $loserString = TextFormat::GRAY . " [" . TextFormat::GREEN . $losers->getScore() . TextFormat::GRAY . "]";

        $attackerName = $winner->getNicks()->getNick();
        $boxing = TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::AQUA . "Boxing" . TextFormat::GRAY . "]" . TextFormat::RESET . " ";
        $msg = $boxing . TextFormat::GREEN . $attackerName . $attackerString . TextFormat::YELLOW
          . " Defeated " . TextFormat::RED . $loser->getNicks()->getNick() . $loserString;

        $this->core->getSystemManager()->getSceneSystem()->getScene("Hub")?->sceneAnnouncement($msg);
        $this->sceneAnnouncement($msg);
        // then do spectators message
        $this->specMessage();
      }
    }
  }

  private function partyWin(string $winnerTeamName): void
  {
    $boxing = TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::AQUA . "Boxing Parties" . TextFormat::GRAY . "]" . TextFormat::RESET . " ";

    $loserTeamsArray = [];
    foreach ($this->teamManager->getTeams() as $team) {
      if (!$team->isSpecTeam() && $team->getTeamSize() == 0) {
        $loserTeamsArray[] = $team->getTeamName();
      }
    }
    $loserTeams = implode(', ', $loserTeamsArray);

    // $this->core->getServer()->broadcastMessage($boxing . TextFormat::YELLOW . $winnerTeamName . TextFormat::GREEN . " Defeated " . TextFormat::YELLOW . $loserTeams);
    $msg = $boxing . TextFormat::YELLOW . $winnerTeamName . TextFormat::GREEN . " Defeated " . TextFormat::YELLOW . $loserTeams;
    $this->core->getSystemManager()->getSceneSystem()->getScene("Hub")?->sceneAnnouncement($msg);
    $this->sceneAnnouncement($msg);
    $this->specMessage();
  }

  /**
   * @throws ScoreFactoryException
   */
  private function spectatorBoxingScoreboard(SwimPlayer $player): void
  {
    if ($player->isScoreboardEnabled()) {
      try {
        $this->startDuelScoreBoard($player);
        $line = 2;
        // show all team scores
        foreach ($this->teamManager->getTeams() as $team) {
          if ($team->isSpecTeam()) continue;
          $name = $team->getTeamName();
          $score = $team->getScore();
          $target = $team->getTargetScore();
          $color = $team->getTeamColor();
          $teamString = $color . $name . TextFormat::GRAY . ": " . $color . $score . TextFormat::GRAY . "/" . $color . $target;
          ScoreFactory::setScoreLine($player, ++$line, " " . $teamString);
        }
        // submit
        $this->submitScoreboardWithBottomFromLine($player);
      } catch (ScoreFactoryException $e) {
        Server::getInstance()->getLogger()->info($e->getMessage());
      }
    }
  }

  // This only works for one team vs one team! (might want to revamp this)
  public function duelScoreboard(SwimPlayer $player): void
  {
    if ($player->isScoreboardEnabled()) {
      try {
        $player->refreshScoreboard(TextFormat::AQUA . "Swimgg.club");

        $team = $this->getPlayerTeam($player);
        if (!$team) return;
        if ($team->isSpecTeam()) {
          $this->spectatorBoxingScoreboard($player);
          return;
        }
        $opTeam = null;

        // get first opponent possible
        foreach ($this->players as $duelPlayer) {
          if (!$this->arePlayersInSameTeam($player, $duelPlayer)) { // there might be a better way to do this
            $opTeam = $this->getPlayerTeam($duelPlayer);
            break;
          }
        }
        if (!$opTeam) return;

        $pHits = $team->getScore();
        $opHits = $opTeam->getScore();

        ScoreFactory::sendObjective($player);
        // variables needed
        $ping = $player->getNslHandler()->getPing();
        $time = TimeHelper::digitalClockFormatter($this->seconds);
        // define lines
        ScoreFactory::setScoreLine($player, 1, " §bPing: §3" . $ping);
        ScoreFactory::setScoreLine($player, 2, " §a" . $pHits . "§7 |§c " . $opHits . ($opHits > $pHits ? " §c(" : " §a(+") . $pHits - $opHits . ")");
        ScoreFactory::setScoreLine($player, 3, " §b" . $time);

        $this->submitScoreboardWithBottomFromLine($player);
      } catch (ScoreFactoryException $e) {
        Server::getInstance()->getLogger()->info($e->getMessage());
      }
    }
  }

}