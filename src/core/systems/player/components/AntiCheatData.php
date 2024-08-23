<?php

namespace core\systems\player\components;

use core\systems\player\Component;
use pocketmine\math\Vector3;

// This is an INSANELY stripped down version of the actual per player anti cheat component.
// This provides the bare minimum shared data container that the ack and nsl handler needs (acData).
class AntiCheatData extends Component
{

  // misc array of data
  private array $acData = [];

  public ?Vector3 $currentMotion = null;

  public function setData(int $type, mixed $val): void
  {
    $this->acData[$type] = $val;
  }

  public function unsetData(int $type): void
  {
    unset($this->acData[$type]);
  }

  public function getData(int $type)
  {
    return $this->acData[$type] ?? null;
  }

  public function pushData(int $type, mixed $val)
  {
    $this->acData[$type][] = $val;
  }

  public function spliceData(int $type, int $offset, int|null $len = null)
  {
    if (!isset($this->acData[$type])) return;
    array_splice($this->acData[$type], $offset, $len);
  }

}