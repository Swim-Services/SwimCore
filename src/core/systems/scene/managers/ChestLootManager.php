<?php

namespace core\systems\scene\managers;

use core\systems\scene\misc\LootTable;
use core\utils\PositionHelper;
use pocketmine\block\tile\Chest as ChestTile;

// use pocketmine\block\tile\EnderChest as EnderChestTile;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\world\Position;

// TO DO : item tiers (common, rare, epic) this can be used for any type of rarity or value to organize good and mid-tier items
class ChestLootManager
{

  /**
   * @var ChestTile[]
   * key is int from vector3 hash
   */
  private array $fixedChests = [];

  /**
   * @var ChestTile[]
   * key is int from vector3 hash
   */
  private array $lootedChests = [];

  // the class must be constructed with a loot table
  private LootTable $lootTable;

  public function __construct(LootTable $lootTable)
  {
    $this->lootTable = $lootTable;
    $this->lootTable->init();
  }

  /**
   * @return LootTable&
   * @breif returns a reference since this is such a big object you might want to modify in place
   */
  public function &getLootTable(): LootTable
  {
    return $this->lootTable;
  }

  /**
   * @param LootTable $lootTable
   */
  public function setLootTable(LootTable $lootTable): void
  {
    $this->lootTable = $lootTable;
  }

  /**
   * First checks if we haven't fixed this chest yet
   * If we haven't fixed and opened it yet then fill the chest with random loot
   * Then log the chest as opened
   */
  public function openAndLootChestWithLog(ChestTile $chestTile): void
  {
    $key = PositionHelper::getVectorHashKey($chestTile->getPosition());
    if (!isset($this->fixedChests[$key])) {
      $this->fixedChests[$key] = $chestTile;
      $this->lootedChests[$key] = $chestTile;
      $chestTile->getRealInventory()->clearAll(); // this is our first time opening the chest so clear it
      $this->refillChest($chestTile);
    }
  }

  public function logLooted(ChestTile $chestTile): void
  {
    $key = PositionHelper::getVectorHashKey($chestTile->getPosition());
    $this->lootedChests[$key] = $chestTile;
  }

  public function isFixed(Position $position): bool
  {
    $key = PositionHelper::getVectorHashKey($position);
    return isset($this->fixedChests[$key]);
  }

  public function isLooted(Position $position): bool
  {
    $key = PositionHelper::getVectorHashKey($position);
    return isset($this->lootedChests[$key]);
  }

  // clears all the inventories of the opened chests and the data
  public function clearLootedChestsInventory(): void
  {
    foreach ($this->lootedChests as $chest) {
      $chest->getRealInventory()->clearAll();
    }
    $this->lootedChests = [];
  }

  // clears the logged looted data only, the chests inventory remain
  public function clearLootedChestsData(): void
  {
    $this->lootedChests = [];
  }

  // refills all chests that have been opened at some point in time
  public function refillAll(): void
  {
    foreach ($this->lootedChests as $chest) {
      $this->refillChest($chest);
    }
  }

  // fills a chest with 1-2 different random items from each category loot category
  public function refillChest(ChestTile $chest): void
  {
    $items = $this->lootTable->getRandomLoot();
    $inventory = $chest->getRealInventory();
    self::fillInventory($inventory, $items);
  }

  /**
   * @param Inventory $inventory
   * @param Item[] $items
   * @param bool $attemptToStack
   * @return void
   * @breif fills an inventory with an array of items at random slots
   */
  public static function fillInventory(Inventory $inventory, array $items, bool $attemptToStack = true): void
  {
    $maxSlots = $inventory->getSize(); // Get the number of slots in the chest inventory

    // Determine which slots are empty and not empty and save them to an array
    $emptySlots = [];
    $notEmptyButStackable = [];
    for ($i = 0; $i < $maxSlots; $i++) {
      if ($inventory->isSlotEmpty($i)) {
        $emptySlots[] = $i;
      } else if ($inventory->getItem($i)->getMaxStackSize() > 1) {
        $notEmptyButStackable[] = $i;
      }
    }

    // return if no slots are empty
    if (empty($emptySlots)) return;

    shuffle($emptySlots); // Shuffle the array to randomize slot selection

    // Distribute the items in available randomized order of slots
    foreach ($items as $item) {
      if (empty($emptySlots) && empty($notEmptyButStackable)) {
        break; // Break if there are no more empty slots
      }

      // attempt to add to stack on refill
      if ($attemptToStack && $item->getMaxStackSize() > 1) {
        $found = false;
        foreach ($notEmptyButStackable as $slot) {
          $tempItem = $inventory->getItem($slot);
          if ($tempItem->getTypeId() == $item->getTypeId() && $tempItem->getCustomName() == $item->getCustomName()) {
            $tempItem->setCount($tempItem->getCount() + $item->getCount());
            $inventory->setItem($slot, $tempItem); // reset
            $found = true;
            break;
          }
        }
        // if we found an item stack to add too, then continue on looping through to the next item
        if ($found) continue;
      }

      // Get the slot from the randomized order of empty slots and remove it from available slots
      $randomSlotIndex = array_shift($emptySlots);
      if ($randomSlotIndex != null) { // this did have a very scary crash that happened once so hopefully this fixes it
        $inventory->setItem($randomSlotIndex, $item);
      }
    }
  }

}
