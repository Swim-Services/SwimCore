<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use Exception;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\Server;

class NetworkStackLatencyHandler extends Component
{

  private const NSL_INTERVAL = 2;
  private const PKS_PER_READING = 50; // before was 75
  private const SUBTRACT_AMOUNT = 5;

  private array $pingArr = [];
  private array $idArr = [];
  private int $finalPing;
  private int $recentReading;
  private int $jitter;
  private Server $server;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer, true);
    $this->server = $this->core->getServer();
  }

  /**
   * @throws Exception
   */
  private function send()
  {
    if (!$this->swimPlayer->isConnected()) {
      return;
    }
    $rNum = self::randomIntNoZeroEnd();
    $this->idArr[self::intrev($rNum)] = microtime(true) * 1000;
    $this->swimPlayer->getNetworkSession()->sendDataPacket(NetworkStackLatencyPacket::create($rNum * 1000, true), true);
  }

  public function onNsl(NetworkStackLatencyPacket $pk)
  {
    if (!isset($this->idArr[self::intRev($pk->timestamp)])) return;
    $this->pingArr[] = (int)((microtime(true) * 1000) - $this->idArr[self::intRev($pk->timestamp)]);
    if (count($this->pingArr) % 5 == 0) {
      $recentArr = array_slice($this->pingArr, max(0, count($this->pingArr) - 6));
      $this->recentReading = (int)(array_sum($recentArr) / count($recentArr));
    }
    if (count($this->pingArr) >= self::PKS_PER_READING) {
      $this->finalPing = (int)(array_sum($this->pingArr) / count($this->pingArr)) - self::SUBTRACT_AMOUNT;
      $this->jitter = (int)self::std_deviation($this->pingArr);
      unset($this->pingArr);
    }
    unset($this->idArr[self::intRev($pk->timestamp)]);
  }

  /**
   * @throws Exception
   */
  public function updateTick(): void
  {
    if (!($this->server->getTick() % self::NSL_INTERVAL == 0)) return;
    $this->send();
  }

  public function getPing(): int
  {
    return $this->finalPing ?? $this->swimPlayer->getNetworkSession()->getPing() ?? 0;
  }

  public function getRecentPing(): int
  {
    return $this->recentReading ?? $this->swimPlayer->getNetworkSession()->getPing() ?? 0;
  }

  public function getLastRawReading(): int
  {
    if (!isset($this->pingArr)) return $this->finalPing ?? $this->swimPlayer->getNetworkSession()->getPing();
    return end($this->pingArr) ?? $this->finalPing ?? $this->swimPlayer->getNetworkSession()->getPing();
  }

  public function getJitter(): int
  {
    return $this->jitter ?? -1;
  }

  /**
   * @throws Exception
   */
  public static function randomIntNoZeroEnd(): int
  {
    $num = random_int(1, 2147483647); // this should probably use int max
    if ($num % 10 == 0) $num = self::randomIntNoZeroEnd();
    return $num;
  }

  public static function intRev(int $num): int
  {
    $revnum = 0;
    while ($num != 0) {
      $revnum = $revnum * 10 + $num % 10;
      $num = (int)($num / 10); // cast is essential to round remainder towards zero
    }
    return $revnum;
  }

  private static function std_deviation($arr): float
  {
    $arr_size = count($arr);
    $mu = array_sum($arr) / $arr_size;
    $ans = 0;
    foreach ($arr as $elem) {
      $ans += pow(($elem - $mu), 2);
    }
    return sqrt($ans / $arr_size);
  }

}