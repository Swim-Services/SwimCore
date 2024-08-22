<?php

namespace core\systems\scene\misc;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\utils\InventoryUtil;
use core\utils\ServerSounds;
use core\utils\StackTracer;
use pocketmine\block\utils\DyeColor;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class Team
{

  /**
   * @var SwimPlayer[] Array of SwimPlayer objects indexed by int ID keys
   */
  private array $players = array();

  /**
   * @var Position[] Array of positions
   */
  private array $spawnPoints = array();

  /**
   * @var SwimPlayer[] Array of the original players indexed by their names
   */
  private array $originalPlayers = array();

  private bool $respawn;
  private int $score;
  private int $targetScore;
  private string $teamColor;
  private string $teamName;

  private int $teamID;
  private bool $specTeam;

  private Scene $parentScene;

  public function __construct(string $teamName, string $teamColor, bool $respawn, int $targetScore, Scene $parentScene, int $id)
  {
    $this->respawn = $respawn;
    $this->score = 0;
    $this->targetScore = $targetScore;
    $this->teamColor = $teamColor;
    $this->teamName = $teamName;
    $this->parentScene = $parentScene;
    $this->teamID = $id;
    $this->specTeam = false;
  }

  /**
   * @return SwimPlayer[]
   */
  public function getOriginalPlayers(): array
  {
    return $this->originalPlayers;
  }

  /**
   * @return bool
   */
  public function isSpecTeam(): bool
  {
    return $this->specTeam;
  }

  /**
   * @param bool $specTeam
   */
  public function setSpecTeam(bool $specTeam): void
  {
    $this->specTeam = $specTeam;
  }

  /**
   * @return int
   */
  public function getTeamID(): int
  {
    return $this->teamID;
  }

  public function getTeamSize(): int
  {
    return count($this->players);
  }

  public function addPlayer(SwimPlayer $swimPlayer, bool $customizeTagColor = true): void
  {
    if (SwimCore::$DEBUG) {
      echo("adding " . $swimPlayer->getName() . " to team " . $this->teamName . "\n");
      if ($this->isSpecTeam()) {
        StackTracer::PrintStackTrace();
      }
    }

    $this->players[$swimPlayer->getId()] = $swimPlayer;
    $swimPlayer->getSceneHelper()->setTeamNumber($this->teamID);

    // bolted on way to give spectator kits
    if ($this->isSpecTeam()) {
      self::applySpectatorKit($swimPlayer);
    }

    // applies team color
    if ($customizeTagColor) {
      $swimPlayer->setNameTag($this->teamColor . $swimPlayer->getNicks()->getNick());
    }

    // add to original players list
    $this->originalPlayers[$swimPlayer->getNicks()->getNick()] = $swimPlayer;
  }

  // Returns null if the positions array is empty
  public function getRandomSpawnPosition(): ?Position
  {
    if (!empty($this->positions)) {
      $randomKey = array_rand($this->positions);
      return $this->positions[$randomKey];
    }

    return null;
  }

  public function getFormattedScore(): string
  {
    // Create a string of '0's equal to the target score
    $scoreString = str_repeat('O', $this->targetScore);

    // Split the string into two parts: scored and remaining
    $coloredPart = substr($scoreString, 0, $this->score);
    $whitePart = substr($scoreString, $this->score);

    // Color the scored part
    $coloredPart = $this->teamColor . $coloredPart;

    // Concatenate the parts
    $formattedScore = $coloredPart . TextFormat::WHITE . $whitePart;

    // Format the final string
    return $this->teamColor . "[" . ucfirst($this->teamName[0]) . "] : " . $formattedScore . TextFormat::WHITE;
  }

  public function formattedScoreParenthesis(): string
  {
    return TextFormat::GRAY . "(" . $this->teamColor . $this->score . TextFormat::GRAY . ")";
  }

  public static function applySpectatorKit(SwimPlayer $player): void
  {
    InventoryUtil::fullPlayerReset($player);
    $player->setGamemode(GameMode::SPECTATOR());
    $inv = $player->getInventory();
    $inv->setItem(8, VanillaItems::DYE()->setColor(DyeColor::RED)->setCustomName(TextFormat::RED . "Leave"));
    $inv->setItem(0, new SpectatorCompass());
  }

  public function removePlayer(SwimPlayer $swimPlayer): void
  {
    if (isset($this->players[$swimPlayer->getId()])) {
      if (SwimCore::$DEBUG) {
        echo("removing " . $swimPlayer->getName() . " from team " . $this->teamName . "\n");
        StackTracer::PrintStackTrace();
      }
      unset($this->players[$swimPlayer->getId()]);
      $swimPlayer->getSceneHelper()->setTeamNumber(-1); // sets back to invalid since they aren't in a team anymore anywhere
    }
  }

  public function getFirstPlayer(): ?SwimPlayer
  {
    return $this->players[array_key_first($this->players)] ?? null;
  }

  // cursed but useful
  public function getFirstTwoPlayers(): array
  {
    // Reset the internal pointer to the beginning of the array
    reset($this->players);

    // Get the first element
    $firstPlayer = current($this->players);

    // Move the internal pointer to the next element
    next($this->players);

    // Get the second element
    $secondPlayer = current($this->players);

    return array($firstPlayer, $secondPlayer);
  }

  public function isInTeam(SwimPlayer $swimPlayer): bool
  {
    return isset($this->players[$swimPlayer->getId()]);
  }

  // TO DO : pop up messages
  public function teamMessage(string $msg): void
  {
    foreach ($this->players as $player) {
      $player->sendMessage($msg);
    }
  }

  public function teamSound(string $soundName, float $volume = 0, float $pitch = 0): void
  {
    foreach ($this->players as $player) {
      ServerSounds::playSoundToPlayer($player, $soundName, $volume, $pitch);
    }
  }

  /**
   * @param Scene $parentScene
   */
  public function setParentScene(Scene $parentScene): void
  {
    $this->parentScene = $parentScene;
  }

  /**
   * @return Scene
   */
  public function getParentScene(): Scene
  {
    return $this->parentScene;
  }

  // the index param is the spawn position number, such as position 0, 1, 2, etc
  // a scrim would have 4 spawn points per team for example
  public function addSpawnPoint(int $index, Position $position): void
  {
    $this->spawnPoints[$index] = $position;
  }

  public function removeSpawnPoint(int $index): void
  {
    if (isset($this->spawnPoints[$index])) {
      unset($this->spawnPoints[$index]);
    }
  }

  public static function swapSpawnPointsAtIndex(Team $teamOne, Team $teamTwo, int $index): void
  {
    $one = $teamOne->getSpawnPoint($index);
    $two = $teamTwo->getSpawnPoint($index);
    $teamOne->addSpawnPoint($index, $two);
    $teamTwo->addSpawnPoint($index, $one);
  }

  public function getSpawnPoint(int $index): ?Position
  {
    return $this->spawnPoints[$index] ?? null;
  }

  /**
   * @return string
   */
  public function getTeamName(): string
  {
    return $this->teamName;
  }

  /**
   * @param string $teamName
   */
  public function setTeamName(string $teamName): void
  {
    $this->teamName = $teamName;
  }

  /**
   * @return int
   */
  public function getScore(): int
  {
    return $this->score;
  }

  /**
   * @param int $score
   */
  public function setScore(int $score): void
  {
    $this->score = $score;
  }

  /**
   * @return array
   */
  public function getPlayers(): array
  {
    return $this->players;
  }

  /**
   * @return array
   */
  public function getSpawnPoints(): array
  {
    return $this->spawnPoints;
  }

  /**
   * @return string
   */
  public function getTeamColor(): string
  {
    return $this->teamColor;
  }

  /**
   * @param string $teamColor
   */
  public function setTeamColor(string $teamColor): void
  {
    $this->teamColor = $teamColor;
  }

  /**
   * @return int
   */
  public function getTargetScore(): int
  {
    return $this->targetScore;
  }

  /**
   * @param int $targetScore
   */
  public function setTargetScore(int $targetScore): void
  {
    $this->targetScore = $targetScore;
  }

  /**
   * @return bool
   */
  public function canRespawn(): bool
  {
    return $this->respawn;
  }

  /**
   * @param bool $respawn
   */
  public function setRespawn(bool $respawn): void
  {
    $this->respawn = $respawn;
  }

}