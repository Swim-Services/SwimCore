<?php

namespace core\systems\player\components;

use core\database\SwimDB;
use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use Generator;
use pocketmine\permission\DefaultPermissions;
use pocketmine\utils\TextFormat;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;
use poggit\libasynql\SqlThread;

class Rank extends Component
{

  public const DEFAULT_RANK = 0;
  public const BOOSTER_RANK = 1;
  public const VIP = 2;
  public const MEDIA_RANK = 3;
  public const YOUTUBE_RANK = 4;
  public const FAMOUS_RANK = 5;
  public const MVP = 6;
  public const HELPER_RANK = 7;
  public const BUILDER_RANK = 8;
  public const MOD_RANK = 9;
  public const OWNER_RANK = 10;

  public static array $ranks = [
    TextFormat::WHITE . "Player", // 0 rank players are just called normal players
    TextFormat::DARK_PURPLE . "Booster", // 1
    TextFormat::AQUA . "VIP", // 2
    TextFormat::LIGHT_PURPLE . "Media", // 3
    TextFormat::RED . "You" . TextFormat::WHITE . "Tube", // 4
    TextFormat::GOLD . "Famous", // 5
    TextFormat::BLUE . "MVP", // 6
    TextFormat::DARK_GREEN . "Builder", // 7
    TextFormat::GREEN . "Helper", // 8
    TextFormat::RED . "Mod", // 9
    TextFormat::YELLOW . "Owner" // 10
  ];

  public static array $rankAbbreviations = [
    "", // no rank tag for default 0 rank
    TextFormat::DARK_PURPLE . "B",
    TextFormat::AQUA . "+",
    TextFormat::LIGHT_PURPLE . "M",
    TextFormat::RED . "Y" . TextFormat::WHITE . "T",
    TextFormat::GOLD . "F",
    TextFormat::BLUE . "++",
    TextFormat::GREEN . "H",
    TextFormat::DARK_GREEN . "B",
    TextFormat::RED . "M",
    TextFormat::YELLOW . "O"
  ];

  public static array $rankColors = [
    TextFormat::GRAY,
    TextFormat::DARK_PURPLE,
    TextFormat::AQUA,
    TextFormat::LIGHT_PURPLE,
    TextFormat::RED,
    TextFormat::GOLD,
    TextFormat::BLUE,
    TextFormat::GREEN,
    TextFormat::DARK_GREEN,
    TextFormat::RED,
    TextFormat::YELLOW
  ];

  private int $rank;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->rank = $swimPlayer->hasPermission(DefaultPermissions::ROOT_OPERATOR) ? self::OWNER_RANK : 0;
  }

  // basically a string to rank level function
  public static function getRankLevelFromPackageName(string $packageName): int
  {
    return match ($packageName) {
      "VIP" => self::VIP,
      "MVP" => self::MVP,
      default => self::DEFAULT_RANK
    };
  }

  public static function attemptRankUpgrade(string $xuid, int $rankLevel): void
  {
    // Query to update the player rank if the current rank is less than the provided rank level
    $updateQuery = "UPDATE Ranks SET playerRank = ? WHERE xuid = ? AND playerRank < ?";
    SwimDB::getDatabase()->executeImplRaw(
      [0 => $updateQuery],
      [0 => [$rankLevel, $xuid, $rankLevel]],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );
  }

  public function getRankLevel(): int
  {
    return $this->rank;
  }

  public static function getRankColor(int $rank): string
  {
    return self::$rankColors[$rank] ?? TextFormat::RESET;
  }

  public static function getRankNameString(int $rank): string
  {
    return self::$ranks[$rank] ?? TextFormat::RESET;
  }

  public static function getRankAbbreviationString(int $rank): string
  {
    return self::$rankAbbreviations[$rank] ?? TextFormat::RESET;
  }

  public function loadRank(): Generator
  {
    $xuid = $this->swimPlayer->getXuid();
    $name = $this->swimPlayer->getName();
    $query = "SELECT playerRank FROM Ranks WHERE xuid = ?";
    $rows = yield from Await::promise(fn($resolve, $reject) => SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$xuid]], [0 => SqlThread::MODE_SELECT], $resolve, $reject));
    if (isset($rows[0]->getRows()[0])) {
      $data = $rows[0]->getRows()[0];
      $rank = $data['playerRank'];
      $this->rank = $this->swimPlayer->hasPermission(DefaultPermissions::ROOT_OPERATOR) ? self::OWNER_RANK : $rank;
      // echo("The rank of player with XUID $xuid is $rank \n");
    } else {
      // echo("No rank found for player with XUID $xuid \n");
      $this->rank = $this->swimPlayer->hasPermission(DefaultPermissions::ROOT_OPERATOR) ? self::OWNER_RANK : self::DEFAULT_RANK;
      self::insertUpdateRankInDatabase($xuid, $name, 0);
    }

    $this->updatePerms();
  }

  private function updatePerms(): void
  {
    $staff = $this->rank >= Rank::MOD_RANK;
    $this->swimPlayer->setBasePermission("use.staff", $staff);
  }

  public function setOnlinePlayerRank(int $rank): void
  {
    $this->rank = $rank;
    $this->swimPlayer->sendMessage(TextFormat::GREEN . "Your Rank has been Updated to " . self::getRankNameString($rank));
    $this->updatePerms();
    self::insertUpdateRankInDatabase($this->swimPlayer->getXuid(), $this->swimPlayer->getName(), $rank);
  }

  // update the rank (can be used if player is offline just insert the player name)
  public static function setRankInDatabase(string $name, int $playerRank): void
  {
    $query = "UPDATE Ranks SET playerRank = ? WHERE name = ?";
    SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$playerRank, $name]], [0 => SqlThread::MODE_GENERIC], function () {
    }, null);
  }

  // insert or update the rank (all info needed)
  public static function insertUpdateRankInDatabase(string $xuid, string $name, int $playerRank): void
  {
    $query = "
    INSERT INTO Ranks (xuid, name, playerRank) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE 
    name = ?, 
    playerRank = ?";
    SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => [$xuid, $name, $playerRank, $name, $playerRank]], [0 => SqlThread::MODE_GENERIC], function () {
    }, null);
  }

  // set the player's score tag to be of their rank
  public function rankScoreTag(): void
  {
    $rankStr = self::getRankNameString($this->rank);
    $this->swimPlayer->setScoreTag($rankStr);
  }

  // set the player's name tag
  public function rankNameTag(): void
  {
    $rankStr = self::getRankNameString($this->rank);
    $rankColor = self::getRankColor($this->rank);
    if ($this->rank > 0) {
      $this->swimPlayer->setNameTag(TextFormat::GRAY . "[" . $rankStr . TextFormat::GRAY . "] " . $rankColor . $this->swimPlayer->getName());
    } else {
      $this->swimPlayer->setNameTag(TextFormat::GRAY . $this->swimPlayer->getName());
    }
  }

  // send a message in the format from being sent by a player
  public function rankChatFormat(string $message): string
  {
    return $this->rankString() . TextFormat::GRAY . " » " . TextFormat::WHITE . $message;
  }

  // return the rank string of a player
  public function rankString(): string
  {
    if ($this->rank == 0 || $this->swimPlayer->getNicks()->isNicked()) {
      return TextFormat::GRAY . $this->swimPlayer->getNicks()->getNick();
    }
    $rankStr = TextFormat::GRAY . "[" . self::getRankNameString($this->rank) . TextFormat::GRAY . "]";
    return $rankStr . " " . TextFormat::GREEN . $this->swimPlayer->getName();
    // return $rankStr . $this->swimPlayer->getCosmetics()->getNameColor() . " " . $this->swimPlayer->getName(); // should have cosmetics getChatColor()
  }

}