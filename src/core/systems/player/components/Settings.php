<?php

namespace core\systems\player\components;

use core\database\SwimDB;
use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use Generator;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\network\mcpe\protocol\CameraShakePacket;
use pocketmine\network\mcpe\protocol\GameRulesChangedPacket;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;
use poggit\libasynql\SqlThread;

class Settings extends Component
{

  private array $toggles;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->toggles = [
      'showCPS' => true,
      'showScoreboard' => true,
      'duelInvites' => true,
      'partyInvites' => true,
      'showCords' => false,
      'showScoreTags' => true,
      'msg' => true,
      'pearl' => false, // animated pearl teleport animation
      'nhc' => false, // animated pearl teleport animation
      'personalTime' => 1000 // default day time
    ];
  }

  /**
   * @throws ScoreFactoryException
   */
  public function updateSettings(): void
  {
    // toggle viewable score tag cps for that player
    $this->swimPlayer->getClickHandler()->showCPS($this->getToggle('showCPS'));
    // turn on cords for that player
    $pk = new GameRulesChangedPacket();
    $pk->gameRules = ["showCoordinates" => new BoolGameRule($this->getToggle('showCords'), false)];
    $this->swimPlayer->getNetworkSession()->sendDataPacket($pk);
    // remove scoreboard if toggled on
    if (!$this->getToggle('showScoreboard')) {
      $this->swimPlayer->removeScoreboard();
    }
    $this->swimPlayer->getNetworkSession()->sendDataPacket(CameraShakePacket::create(0.00001, 3000000000000000000, CameraShakePacket::TYPE_ROTATIONAL, CameraShakePacket::ACTION_STOP)); // stop existing shake if present
    if ($this->getToggle('nhc')) {
      $this->swimPlayer->getNetworkSession()->sendDataPacket(CameraShakePacket::create(0.00001, 3000000000000000000, CameraShakePacket::TYPE_ROTATIONAL, CameraShakePacket::ACTION_ADD)); // hack to suppress hurt cam
    }
    // set time
    if ($this->getToggleInt('personalTime')) {
      $this->swimPlayer->getNetworkSession()->sendDataPacket(SetTimePacket::create($this->getToggleInt('personalTime') + 2000000000));
    }
  }

  public function setToggle(string $setting, bool $state): void
  {
    if (isset($this->toggles[$setting])) {
      $this->toggles[$setting] = $state;
    }
  }

  public function setToggleInt(string $setting, int $state): void
  {
    if (isset($this->toggles[$setting])) {
      $this->toggles[$setting] = $state;
    }
  }

  public function getToggle(string $setting): ?bool
  {
    return $this->toggles[$setting] ?? null;
  }

  public function getToggleInt(string $setting): ?int
  {
    if (!isset($this->toggles[$setting])) return null;
    return (int)$this->toggles[$setting] ?? null;
  }

  // saves settings to the database
  public function saveSettings(): void
  {
    $xuid = $this->swimPlayer->getXuid();
    $showCPS = $this->getToggleInt('showCPS');
    $showScoreboard = $this->getToggleInt('showScoreboard');
    $duelInvites = $this->getToggleInt('duelInvites');
    $partyInvites = $this->getToggleInt('partyInvites');
    $showCords = $this->getToggleInt('showCords');
    $showScoreTags = $this->getToggleInt('showScoreTags');
    $msg = $this->getToggleInt('msg');
    $pearl = $this->getToggleInt('pearl');
    $nhc = $this->getToggleInt('nhc');
    $personalTime = $this->getToggleInt('personalTime') ?? 1000;

    $query = "
        INSERT INTO Settings (xuid, showCPS, showScoreboard, duelInvites, partyInvites, showCords, showScoreTags, msg, pearl, nhc, personalTime) 
        VALUES ('$xuid', '$showCPS', '$showScoreboard', '$duelInvites', '$partyInvites', '$showCords', '$showScoreTags', '$msg', '$pearl', '$nhc', '$personalTime')
        ON DUPLICATE KEY UPDATE 
            xuid = '$xuid', 
            showCPS = '$showCPS', 
            showScoreboard = '$showScoreboard', 
            duelInvites = '$duelInvites', 
            partyInvites = '$partyInvites',
            showCords = '$showCords',
            showScoreTags = '$showScoreTags',
            msg = '$msg',
            pearl = '$pearl',
            nhc = '$nhc',
            personalTime = '$personalTime'
    ";

    SwimDB::getDatabase()->executeImplRaw(
      [0 => $query],
      [0 => []],
      [0 => SqlThread::MODE_GENERIC],
      function () {
      },
      null
    );
  }

  // this function should be called on player joining server

  /**
   * @throws ScoreFactoryException
   */
  public function loadSettings(): Generator
  {
    $xuid = $this->swimPlayer->getXuid();
    $query = "SELECT * FROM Settings WHERE xuid = '$xuid'";
    // Use Await::f2c outside the executeImplRaw since we are waiting for data
    $rows = yield from Await::promise(fn($resolve, $reject) => SwimDB::getDatabase()->executeImplRaw([0 => $query], [0 => []], [0 => SqlThread::MODE_SELECT], $resolve, $reject));
    if (isset($rows[0]->getRows()[0])) {
      $data = $rows[0]->getRows()[0];
      // if still online then apply the settings cast as booleans into the toggles map
      if ($this->swimPlayer->isConnected() && $this->swimPlayer->isOnline()) {
        $this->toggles = [
          'showCPS' => (bool)$data['showCPS'],
          'showScoreboard' => (bool)$data['showScoreboard'],
          'duelInvites' => (bool)$data['duelInvites'],
          'partyInvites' => (bool)$data['partyInvites'],
          'showCords' => (bool)$data['showCords'],
          'showScoreTags' => (bool)$data['showScoreTags'],
          'msg' => (bool)$data['msg'],
          'pearl' => (bool)$data['pearl'],
          'nhc' => (bool)$data['nhc'],
          'personalTime' => $data['personalTime']
        ];
        $this->updateSettings();
      }
    } else {
      $this->saveSettings(); // saving default settings is a way to register them into the database for the first time
      $this->updateSettings();
    }
  }

  public function getToggles(): array
  {
    return $this->toggles;
  }

}