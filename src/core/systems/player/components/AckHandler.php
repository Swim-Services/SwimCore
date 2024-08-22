<?php

namespace core\systems\player\components;

use core\systems\entity\entities\ClientEntity;
use core\systems\player\Component;
use core\utils\AcData;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;

// A mini simulation of entities on each player's client for more precise reporting of positions and other related properties in physics.
class AckHandler extends Component
{

  const INTERPOLATION_TICKS = 3;

  /** @var ClientEntity[] */
  private array $entities = [];

  private array $acks = [];

  public function receive(NetworkStackLatencyPacket $packet): bool
  {
    $ts = NetworkStackLatencyHandler::intRev($packet->timestamp);
    if (!isset($this->acks[$ts])) return false;
    $ack = $this->acks[$ts];

    if (!isset($this->entities[$ack[0]])) {
      $this->entities[$ack[0]] = new ClientEntity();
    }

    if (count($ack) == 1) {
      $this->remove($ack[0]);
      unset($this->acks[$ts]);
      return true;
    }

    if (count($ack) == 2) {
      $data = $this->swimPlayer->getAntiCheatData();
      $data->setData(AcData::RUN_VELO, true);
      $data->currentMotion = $ack[1];
      unset($this->acks[$ts]);
      return true;
    }

    $entity = $this->entities[$ack[0]];
    $entity->update($ack[1], self::INTERPOLATION_TICKS);
    unset($this->acks[$ts]);

    return true;
  }

  public function remove(int $id): void
  {
    unset($this->entities[$id]);
  }

  public function get(int $id): ?Vector3
  {
    if (!isset($this->entities[$id])) return null;
    return $this->entities[$id]->getPosition();
  }

  public function getPrev(int $id): ?Vector3
  {
    if (!isset($this->entities[$id])) return null;
    return $this->entities[$id]->getPrevPosition();
  }

  public function add(int $id, Vector3 $pos, int $ts, bool $tp): void
  {
    $this->acks[NetworkStackLatencyHandler::intRev($ts)] = [$id, $pos, $tp];
  }

  public function addKb(Vector3 $motion, int $ts) {
    $this->acks[NetworkStackLatencyHandler::intRev($ts)] = [0, $motion];
  }

  public function addRemoval(int $id, int $ts)
  {
    $this->acks[NetworkStackLatencyHandler::intRev($ts)] = [$id];
  }

  public function updateTick(): void
  {
    foreach ($this->entities as $entity) {
      $entity->tick();
    }
  }

}