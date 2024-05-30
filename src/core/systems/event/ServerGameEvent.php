<?php

namespace core\systems\event;

use core\scenes\hub\EventQueue;
use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\utils\TimeHelper;
use jackmd\scorefactory\ScoreFactoryException;
use JsonException;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

// SurvivalGamesEvent and ScrimEvent will extend this class and implement the onStart method
abstract class ServerGameEvent
{

  protected SwimCore $core;

  // the player that created the event, TO DO : handle if hosts leave (cancel event?)
  protected SwimPlayer $host;

  protected int $maxTeamSize = 1;
  protected int $requiredPlayersToStart = 8;
  protected int $maxPlayers = 24;

  // this should be set with a default value by the implementing class, the host can change this if they want
  protected string $eventName = "PlaceHolder";

  protected string $mapName;

  protected string $internalName;

  // tells when stuff happens like it was created and periodically how many people joined and how soon it will start
  protected bool $announcesUpdatesInChat = true;

  // as more players join, if players required to start is reached, this value changes to be 30 seconds if it is still above that
  protected int $seconds = 120;

  // the amount of seconds this event has been going for
  protected int $totalSeconds = 0;

  /**
   * This value should only be subtracted when an event is finished or stopped by the host.
   * And this value should only be incremented if we can create a new instance of the specified event.
   */
  protected static int $instances = 0;

  // the amount of the same event which can be used (maybe this could be changed via how many maps are available)
  protected static int $maxInstances = 5;

  // if the event started or not yet
  protected bool $started = false;

  /**
   * @var SwimPlayer[]
   * key is player ID
   */
  private array $players = array();

  /**
   * @var SwimPlayer[]
   * @brief this is an array of players who got kicked/blocked by host from joining the event, once kicked they can not join the event unless the host unblocks them
   * key is player ID
   */
  private array $blockedPlayers = array();

  /**
   * @var EventTeam[]
   * key is team ID
   */
  protected array $teams = array();

  // parent event system
  private EventSystem $eventSystem;

  // for id stuff
  private int $totalTeamsMade = 0;

  public function __construct
  (
    SwimCore    $core,
    EventSystem $eventSystem,
    string      $eventName,
    SwimPlayer  $host,
    string      $mapName,
    int         $requiredPlayersToStart = 8,
    int         $maxPlayers = 24,
    int         $teamSize = 1
  )
  {
    $this->core = $core;
    $this->eventSystem = $eventSystem;
    $this->internalName = $eventName;
    $this->host = $host;
    $this->requiredPlayersToStart = $requiredPlayersToStart;
    $this->maxPlayers = $maxPlayers;
    $this->maxTeamSize = $teamSize;
    $this->mapName = $mapName;
    $this->addPlayer($host);
  }

  public function __destruct()
  {
    echo $this->eventName . " event freed\n";
  }

  // the implementing class must define what happens once the event starts
  abstract protected function startEvent(): void;

  public function eventCreated(): void
  {
    $rank = $this->host->getRank();
    $msg = $rank->rankString() . TextFormat::GREEN . " Has Started ";
    $extra = "";
    if ($this->maxTeamSize == 2) {
      $extra = TextFormat::BLUE . " Duos";
    } elseif ($this->maxTeamSize == 3) {
      $extra = TextFormat::GOLD . " Trios";
    } elseif ($this->maxTeamSize == 4) {
      $extra = TextFormat::GREEN . " Squads";
    }
    $this->eventName .= $extra;
    $msg .= TextFormat::LIGHT_PURPLE . $this->eventName . " " . $this->formatMap();

    $msg2 = TextFormat::GREEN . "Join it from the Hub via the" . TextFormat::DARK_PURPLE . " Event NetherStar!";

    $server = Server::getInstance();
    $server->broadcastMessage($msg);
    $server->broadcastMessage($msg2);
  }

  // each second check if required players to start was hit and if so wait for 30 seconds then start

  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  public function updateSecond(): void
  {
    if (!$this->started) {
      $this->preStartLogic();
    }
  }

  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  private function preStartLogic(): void
  {
    // lifetime update + player count check
    $this->totalSeconds++;
    if ($this->totalSeconds >= 60 * 5 || $this->getPlayerCount() == 0) {
      $this->eventMessage(TextFormat::RED . "Event took too long to start, ending early.");
      $this->end();
      return;
    }

    // check if we can count down
    if ($this->getPlayerCount() >= $this->requiredPlayersToStart) {
      $this->seconds--;
    } else {
      $this->seconds = 120; // restart back to 2 minutes to wait for more to join
    }

    // if we have announcements on then every 30 seconds announce in chat the event
    if ($this->announcesUpdatesInChat) {
      $this->periodicUpdateMessage();
    }

    // if we reached the time needed
    if ($this->seconds <= 0) {
      $this->started = true;
      $this->eventMessage(TextFormat::GREEN . "Event is starting!"); // we might not even want this here if an event has something else to do optionally
      $this->eventSystem->eventStarted($this);
      $this->startEvent();
    }
  }

  /**
   * @throws ScoreFactoryException
   * @throws JsonException
   * @breif Removes all players from the event and sends them back to hub
   */
  private function end(): void
  {
    foreach ($this->players as $player) {
      $sh = $player->getSceneHelper();
      $sh->setEvent(null);
      $sh->setNewScene("Hub");
    }

    $this->exit();
    $this->eventSystem->removeEvent($this);
  }

  private function periodicUpdateMessage(): void
  {
    if ($this->totalSeconds % 30 == 0) {
      $rank = $this->host->getRank();

      $msg = $rank->rankString() . TextFormat::GREEN . " is Hosting " . TextFormat::LIGHT_PURPLE . $this->eventName . " " . $this->formatMap() . " " . $this->formatPlayerCount()
        . TextFormat::GRAY . " | " . TimeHelper::digitalClockFormatter($this->seconds);

      Server::getInstance()->broadcastMessage($msg);
    }
  }

  private function formatMap(): string
  {
    return TextFormat::GRAY . "(" . TextFormat::YELLOW . ucfirst($this->mapName) . TextFormat::GRAY . ")";
  }

  public function formatTimeToStart(): string
  {
    return TimeHelper::digitalClockFormatter($this->seconds - 1);
  }

  public function formatPlayerCount(): string
  {
    return TextFormat::GRAY . "(" . TextFormat::YELLOW . $this->getPlayerCount() . TextFormat::GRAY . "/"
      . TextFormat::YELLOW . $this->maxPlayers . TextFormat::GRAY . ")";
  }

  public function eventMessage(string $message): void
  {
    foreach ($this->players as $player) {
      $player->sendMessage($message);
    }
  }

  /**
   * @return bool
   */
  public function isStarted(): bool
  {
    return $this->started;
  }

  /**
   * @return SwimPlayer
   */
  public function getHost(): SwimPlayer
  {
    return $this->host;
  }

  /**
   * @param SwimPlayer $host
   */
  public function setHost(SwimPlayer $host): void
  {
    $this->host = $host;
  }

  /**
   * @return bool
   */
  public function isAnnouncesUpdatesInChat(): bool
  {
    return $this->announcesUpdatesInChat;
  }

  /**
   * @param bool $announcesUpdatesInChat
   */
  public function setAnnouncesUpdatesInChat(bool $announcesUpdatesInChat): void
  {
    $this->announcesUpdatesInChat = $announcesUpdatesInChat;
  }

  /**
   * @param string $eventName
   */
  public function setEventName(string $eventName): void
  {
    $this->eventName = $eventName;
  }

  /**
   * @return string
   */
  public function getEventName(): string
  {
    return $this->eventName;
  }

  /**
   * @return string
   */
  public function getInternalName(): string
  {
    return $this->internalName;
  }

  /**
   * @return bool
   * returns if you can create a new instance for this event or not
   */
  public static function canCreate(): bool
  {
    return static::$instances < static::$maxInstances;
  }

  public function canAdd(): bool
  {
    return count($this->players) < $this->maxPlayers;
  }

  public function getPlayerCount(): int
  {
    return count($this->players);
  }

  /**
   * @return int
   */
  public static function getMaxInstances(): int
  {
    return static::$maxInstances;
  }

  /**
   * @param int $instances
   * @breif should be implemented as: static::$instances = $instances;
   */
  abstract public static function setInstances(int $instances): void;

  /**
   * @return int
   * @brief should be implemented as: return static::$instances;
   */
  abstract public static function getInstances(): int;

  /**
   * @return int
   */
  public function getRequiredPlayersToStart(): int
  {
    return $this->requiredPlayersToStart;
  }

  /**
   * @return int
   */
  public function getMaxPlayers(): int
  {
    return $this->maxPlayers;
  }

  /**
   * @param int $maxInstances
   */
  public static function setMaxInstances(int $maxInstances): void
  {
    static::$maxInstances = $maxInstances;
  }

  /**
   * @param int $maxPlayers
   */
  public function setMaxPlayers(int $maxPlayers): void
  {
    $this->maxPlayers = $maxPlayers;
  }

  /**
   * @param int $requiredPlayersToStart
   */
  public function setRequiredPlayersToStart(int $requiredPlayersToStart): void
  {
    $this->requiredPlayersToStart = $requiredPlayersToStart;
  }

  /**
   * @return int
   */
  public function getMaxTeamSize(): int
  {
    return $this->maxTeamSize;
  }

  /**
   * @param int $maxTeamSize
   */
  public function setMaxTeamSize(int $maxTeamSize): void
  {
    $this->maxTeamSize = $maxTeamSize;
  }

  /**
   * @param string $mapName
   */
  public function setMapName(string $mapName): void
  {
    $this->mapName = $mapName;
  }

  /**
   * @return string
   */
  public function getMapName(): string
  {
    return $this->mapName;
  }

  /**
   * @return SwimPlayer[]
   */
  public function getPlayers(): array
  {
    return $this->players;
  }

  // this updates the player's scene helper for the event they are in
  public function addPlayer(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->getSceneHelper()->setEvent($this);
    $this->players[$swimPlayer->getId()] = $swimPlayer;
    $this->createSoloTeam($swimPlayer); // they get put on their own solo team
  }

  public function createSoloTeam(SwimPlayer $swimPlayer): void
  {
    ++$this->totalTeamsMade;
    $team = new EventTeam($this->core, $this, $swimPlayer, $this->totalTeamsMade);
    $team->setMaxTeamSize($this->maxTeamSize);
    $this->teams[$this->totalTeamsMade] = $team;
  }

  public function getInvitablePlayers(SwimPlayer $swimPlayer): array
  {
    $selfTeam = $this->getTeamPlayerIsIn($swimPlayer);
    $players = array();
    foreach ($this->teams as $team) {
      // skip self
      if ($team->getID() == $selfTeam->getID()) continue;
      // can invite solo team
      if ($team->getCurrentTeamSize() == 1) {
        $players[] = $team->getOwner();
      }
    }
    return $players;
  }

  // the passed in player param is the player that just left
  public function assignNewHost(SwimPlayer $swimPlayer): void
  {
    // if the host left, assign a new one if possible
    if ($this->host->getId() == $swimPlayer->getId() && count($this->players) > 0) {
      $this->host = reset($this->players);
      $this->host->sendMessage(TextFormat::GREEN . "The old host left, so you are now the host");
      EventQueue::kit($this->host);
    }
  }

  public function inEvent(SwimPlayer $swimPlayer): bool
  {
    return isset($this->players[$swimPlayer->getId()]);
  }

  // if they are still in the event as a solo team
  public function shouldInvite(SwimPlayer $swimPlayer): bool
  {
    if ($this->inEvent($swimPlayer)) {
      return $this->getTeamPlayerIsIn($swimPlayer)?->getCurrentTeamSize() == 1;
    }
    return false;
  }

  /**
   * @throws JsonException
   * @throws ScoreFactoryException
   */
  public function leave(SwimPlayer $swimPlayer): void
  {
    $sh = $swimPlayer->getSceneHelper();
    $this->removePlayer($swimPlayer);
    $this->removeMessage($swimPlayer);
    $sh->setNewScene("Hub");
  }

  public function getTeamPlayerIsIn(SwimPlayer $swimPlayer): ?EventTeam
  {
    // shortcut
    $id = $swimPlayer->getSceneHelper()->getTeamNumber();
    if (isset($this->teams[$id])) {
      $t = $this->teams[$id];
      if ($t) return $t;
    }

    // long checks
    foreach ($this->teams as $team) {
      if ($team->hasPlayer($swimPlayer)) return $team;
    }
    return null;
  }

  public function removePlayer(SwimPlayer $swimPlayer): void
  {
    if (isset($this->players[$swimPlayer->getId()])) {
      unset($this->players[$swimPlayer->getId()]);
    }
    // check if we need to assign a new host
    $this->assignNewHost($swimPlayer);
    // reset back to null (no event)
    $sh = $swimPlayer->getSceneHelper();
    $sh->setEvent(null);
  }

  public function hasPlayer(SwimPlayer $swimPlayer): bool
  {
    return isset($this->players[$swimPlayer->getId()]);
  }

  // returns a bool saying if it removed the player or not

  /**
   * @throws ScoreFactoryException
   * @throws JsonException
   */
  public function removeIfContains(SwimPlayer $swimPlayer): bool
  {
    if (isset($this->players[$swimPlayer->getId()])) {
      unset($this->players[$swimPlayer->getId()]);
      $this->assignNewHost($swimPlayer); // need to check to assign a new host
      $this->getTeamPlayerIsIn($swimPlayer)->leave($swimPlayer, false);
      return true;
    }
    return false;
  }

  public function joinMessage(SwimPlayer $swimPlayer): void
  {
    $nameFormat = $swimPlayer->getRank()->rankString();
    $msg = $nameFormat . TextFormat::GREEN . " has joined the event! " . $this->formatPlayerCount();
    $this->eventMessage($msg);
  }

  public function removeMessage(SwimPlayer $swimPlayer): void
  {
    $nameFormat = $swimPlayer->getRank()->rankString();
    $msg = $nameFormat . TextFormat::GREEN . " has left the event! " . $this->formatPlayerCount();
    $this->eventMessage($msg);
  }

  public function isBlocked(Player $swimPlayer): bool
  {
    return isset($this->blockedPlayers[$swimPlayer->getId()]);
  }

  /**
   * @return SwimPlayer[]
   */
  public function getBlockedPlayers(): array
  {
    return $this->blockedPlayers;
  }

  public function removeFromBlockedList(SwimPlayer $swimPlayer): void
  {
    if (isset($this->blockedPlayers[$swimPlayer->getId()])) {
      unset($this->blockedPlayers[$swimPlayer->getId()]);
    }
  }

  public function addToBlockedList(Player $swimPlayer): void
  {
    $this->blockedPlayers[$swimPlayer->getId()] = $swimPlayer;
  }

  // clear lists
  public function exit(): void
  {
    $this->blockedPlayers = [];
    $this->players = [];
  }

  public function removeTeam(EventTeam $team): void
  {
    unset($this->teams[$team->getID()]);
  }

}