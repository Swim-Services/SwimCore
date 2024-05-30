<?php

namespace core\systems\player\components;

// this class holds a map of itemID => float

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;

class CoolDowns extends Component
{

  private array $coolDowns = [];
  private float $focusedMaxTime;
  private int $focusedItemID;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer, true);
    $this->focusedItemID = -69420;
  }

  // look up enums
  private const TIME = 1;
  private const NAME = 2;
  private const NOTIFY = 3;

  public function setCoolDown(Item $item, float $time, bool $notify = true): void
  {
    $this->focusedMaxTime = $time;
    $this->coolDowns[$item->getTypeId()] = [self::TIME => $time, self::NAME => $item->getName(), self::NOTIFY => $notify];
  }

  public function updateCoolDowns(): void
  {
    foreach ($this->coolDowns as $itemId => &$coolDown) {
      $coolDown[self::TIME] -= 0.05;
      if ($coolDown[self::TIME] <= 0) {
        if ($coolDown[self::NOTIFY]) {
          $this->swimPlayer->sendMessage(TextFormat::GREEN . "Your " . $coolDown[self::NAME] . TextFormat::GREEN . " cool down has expired");
        }
        unset($this->coolDowns[$itemId]);
        if ($this->focusedItemID == $itemId) {
          $this->swimPlayer->getXpManager()->setXpAndProgress(0, 0);
          $this->focusedItemID = -69420; // back to unreachable ID
        }
      } elseif ($this->focusedItemID == $itemId) {
        $percent = $coolDown[self::TIME] / $this->focusedMaxTime;
        $this->swimPlayer->getXpManager()->setXpAndProgress(ceil($coolDown[self::TIME]), $percent);
      }
    }
  }

  // the focused item cool down is what controls the xp bar appearance
  public function setFocused(Item $item): void
  {
    $this->focusedItemID = $item->getTypeId();
  }

  // check if an item is on cool down
  public function onCoolDown(Item $item): bool
  {
    return isset($this->coolDowns[$item->getTypeId()]);
  }

  public function getCoolDownTime(Item $item): float
  {
    if (isset($this->coolDowns[$item->getTypeId()])) {
      return $this->coolDowns[$item->getTypeId()][self::TIME];
    }
    return 0;
  }

  public function clearAll(): void
  {
    $this->coolDowns = [];
    $this->focusedItemID = -69420;
    $this->swimPlayer->getXpManager()->setXpAndProgress(0, 0);
  }

  public function clear(): void
  {
    $this->clearAll();
  }

  public function triggerItemCoolDownEvent(PlayerItemUseEvent $event, Item $item, int $seconds = 15, bool $focused = true, bool $sendMessage = true): void
  {
    if ($this->onCoolDown($item)) {
      $event->cancel();
      if ($sendMessage) {
        $this->swimPlayer->sendMessage(TextFormat::RED . $this->coolDowns[$item->getTypeId()][self::NAME] . " is on cool down for "
          . number_format(round($this->coolDowns[$item->getTypeId()][self::TIME], 2), 2, '.', '')
          . " Seconds");
      }
    } else {
      $this->setCoolDown($item, $seconds, $sendMessage);
      if ($focused) {
        $this->setFocused($item);
      }
    }
  }

  public function updateTick(): void
  {
    $this->updateCoolDowns();
  }

}
