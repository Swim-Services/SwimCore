<?php

namespace core\systems\scene\managers;

use core\SwimCore;
use core\utils\PositionHelper;
use core\utils\TimeHelper;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class BlocksManager
{

  /**
   * key is Vector3
   * @var Block[]
   */
  private array $placedBlocks; // logs the blocks placed by player

  /**
   * @var int[]
   */
  private array $allowedToBreakFromMap; // blocks the player is allowed to break in the map

  /**
   * key is Vector3
   * @var Block[]
   */
  private array $brokenMapBlocks; // logs the blocks that were part of the map and got broken

  private bool $canPlaceBlocks;
  private bool $canBreakBlocks; // only applies to player placed blocks
  private bool $canBreakRegisteredBlocks; // if we can break blocks registered in our allow list, this override can break map blocks
  private bool $canBreakMapBlocks; // also applies to breaking pre-made map blocks
  private bool $prunes; // if replaces broken and placed blocks

  private World $world; // so server knows what world to place blocks back in during clean up

  private int $brokenLifeTime;
  private int $placedLifeTime;

  private const TIME = 0;
  private const BLOCK = 1;

  private SwimCore $core;

  public function __construct
  (
    SwimCore $core,
    World    $world,
    bool     $canPlaceBlocks = false,
    bool     $canBreakBlocks = false,
    bool     $canBreakMapBlocks = false,
    bool     $prune = false
  )
  {
    $this->core = $core;
    $this->world = $world;
    $this->placedBlocks = [];
    $this->brokenMapBlocks = [];
    $this->allowedToBreakFromMap = [];
    $this->canPlaceBlocks = $canPlaceBlocks;
    $this->canBreakBlocks = $canBreakBlocks;
    $this->canBreakMapBlocks = $canBreakMapBlocks;
    $this->canBreakRegisteredBlocks = $canBreakMapBlocks;
    $this->prunes = $prune;

    // how long in ticks for a block to be replaced back to what it was, by default is 5 minutes
    $this->brokenLifeTime = TimeHelper::secondsToTicks(60 * 5);
    $this->placedLifeTime = $this->brokenLifeTime;
  }

  public function addToAllowedToBreakList(int $blockId): void
  {
    $this->allowedToBreakFromMap[$blockId] = true;
  }

  public function removeFromAllowedToBreakList(int $blockId): void
  {
    unset($this->allowedToBreakFromMap[$blockId]);
  }

  public function allowedToBreak(int $blockId): bool
  {
    return isset($this->allowedToBreakFromMap[$blockId]);
  }

  /**
   * @return bool
   */
  public function isPrunes(): bool
  {
    return $this->prunes;
  }

  /**
   * @param bool $prunes
   */
  public function setPrunes(bool $prunes = true): void
  {
    $this->prunes = $prunes;
  }

  /**
   * @return int
   */
  public function getBrokenLifeTime(): int
  {
    return $this->brokenLifeTime;
  }

  /**
   * @return int
   */
  public function getPlacedLifeTime(): int
  {
    return $this->placedLifeTime;
  }

  /**
   * @param int $brokenLifeTime
   */
  public function setBrokenLifeTime(int $brokenLifeTime): void
  {
    $this->brokenLifeTime = $brokenLifeTime;
  }

  /**
   * @param int $placedLifeTime
   */
  public function setPlacedLifeTime(int $placedLifeTime): void
  {
    $this->placedLifeTime = $placedLifeTime;
  }

  /**
   * Checks if a hashed Vector3 key exists in the given array.
   * @param Vector3 $vector The Vector3 object to generate the hash key.
   * @param array $array The array to check for the key.
   * @return bool True if the key exists, false otherwise.
   */
  private function isHashKeyInArray(Vector3 $vector, array $array): bool
  {
    return isset($array[PositionHelper::getVectorHashKey($vector)]);
  }

  /**
   * Adds a Vector3 object to an array using a hashed Vector3 key.
   * @param array $array The array to add to.
   * @param Vector3 $vector The Vector3 object to add.
   * @param mixed $item The item to add to the array.
   */
  private function addItemToArrayWithVector3Key(array &$array, Vector3 $vector, mixed $item): void
  {
    $array[PositionHelper::getVectorHashKey($vector)] = $item;
  }

  public function handleBlockPlace(BlockPlaceEvent $event): void
  {
    $time = $this->core->getServer()->getTick() + $this->placedLifeTime;
    if ($this->canPlaceBlocks) {
      foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
        $this->addItemToArrayWithVector3Key($this->placedBlocks, new Vector3($x, $y, $z), [self::BLOCK => $block, self::TIME => $time]);
      }
    } else {
      // how would you have blocks to place if the duel has block placements disabled?
      $event->cancel();
    }
  }

  public function handleBlockBreak(BlockBreakEvent $event): void
  {
    // first check if we are allowed to break blocks, if not then cancel and return
    if (!$this->canBreakBlocks) {
      $event->cancel();
      return;
    }

    // get the block
    $block = $event->getBlock();
    $time = $this->core->getServer()->getTick() + $this->brokenLifeTime;

    // if we can break map blocks, or we can break registered blocks and the block is registered, then we can just log and return
    // checking via state id is kinda sus
    if ($this->canBreakMapBlocks || ($this->canBreakRegisteredBlocks && ($this->allowedToBreak($block->getTypeId()) || $this->allowedToBreak($block->getStateId())))) {
      $this->addItemToArrayWithVector3Key($this->brokenMapBlocks, $block->getPosition()->asVector3(), [self::BLOCK => $block, self::TIME => $time]);
      return;
    }

    // check if the broken block cords is in the placedBlocks array, meaning it was allowed to be broken since it was also placed by a player
    if ($this->isInPlacedBlocks($block->getPosition()->asVector3())) {
      $this->addItemToArrayWithVector3Key($this->brokenMapBlocks, $block->getPosition()->asVector3(), [self::BLOCK => $block, self::TIME => $time]);
    } else { // if we are not allowed to break this block, cancel the event
      $event->cancel();
    }
  }

  private function isInPlacedBlocks(Vector3 $vector3): bool
  {
    return $this->isHashKeyInArray($vector3, $this->placedBlocks);
  }

  // sets all placed blocks back to air
  public function clearPlacedBlocks(): void
  {
    foreach ($this->placedBlocks as $data) {
      $block = $data[self::BLOCK];
      $this->world->setBlock($block->getPosition(), VanillaBlocks::AIR());
    }
    $this->placedBlocks = [];
  }

  // sets all blocks broken from the map back to what they were
  public function replaceBrokenMapBlocks(): void
  {
    foreach ($this->brokenMapBlocks as $data) {
      $block = $data[self::BLOCK];
      $this->world->setBlock($block->getPosition(), $block);
    }
    $this->brokenMapBlocks = [];
  }

  public function cleanMap(): void
  {
    $this->clearPlacedBlocks();
    $this->replaceBrokenMapBlocks();
  }

  // Getter for canPlaceBlocks
  public function getCanPlaceBlocks(): bool
  {
    return $this->canPlaceBlocks;
  }

  // Setter for canPlaceBlocks
  public function setCanPlaceBlocks(bool $canPlaceBlocks): void
  {
    $this->canPlaceBlocks = $canPlaceBlocks;
  }

  // Getter for canBreakBlocks
  public function getCanBreakBlocks(): bool
  {
    return $this->canBreakBlocks;
  }

  // Setter for canBreakBlocks
  public function setCanBreakBlocks(bool $canBreakBlocks): void
  {
    $this->canBreakBlocks = $canBreakBlocks;
  }

  // Getter for canBreakMapBlocks
  public function getCanBreakMapBlocks(): bool
  {
    return $this->canBreakMapBlocks;
  }

  // Setter for canBreakMapBlocks
  public function setCanBreakMapBlocks(bool $canBreakMapBlocks): void
  {
    $this->canBreakMapBlocks = $canBreakMapBlocks;
  }

  /**
   * @return bool
   */
  public function isCanBreakRegisteredBlocks(): bool
  {
    return $this->canBreakRegisteredBlocks;
  }

  /**
   * @param bool $canBreakRegisteredBlocks
   */
  public function setCanBreakRegisteredBlocks(bool $canBreakRegisteredBlocks): void
  {
    $this->canBreakRegisteredBlocks = $canBreakRegisteredBlocks;
  }

  // place an array of vector3 positions of a single block type
  public function placeBlocks(array $positions, Block $block, bool $log = true): void
  {
    $time = $this->core->getServer()->getTick();
    foreach ($positions as $pos) {
      $this->world->setBlock($pos, $block);
      if ($log) {
        $this->addItemToArrayWithVector3Key($this->placedBlocks, $pos, [self::BLOCK => $block, self::TIME => $time]);
      }
    }
  }

  // prunes all old blocks (this can maybe get expensive)
  public function updateSecond(): void
  {
    if (!$this->prunes) return;

    $time = $this->core->getServer()->getTick();

    // set back the placed blocks to air
    foreach ($this->placedBlocks as $key => $data) {
      if ($time >= $data[self::TIME]) {
        $block = $data[self::BLOCK];
        $this->world->setBlock($block->getPosition(), VanillaBlocks::AIR());
        unset($this->placedBlocks[$key]);
      }
    }

    // set back the broken map blocks to what they were
    foreach ($this->brokenMapBlocks as $key => $data) {
      if ($time >= $data[self::TIME]) {
        $block = $data[self::BLOCK];
        $this->world->setBlock($block->getPosition(), $block);
        unset($this->brokenMapBlocks[$key]);
      }
    }
  }

}