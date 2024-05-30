<?php

namespace core\systems\scene\misc;

use Exception;
use pocketmine\item\Item;

abstract class LootTable
{

  public const Weapon = 0;
  public const Armor = 1;
  public const Movement = 2;
  public const Healing = 3;
  public const Misc = 4;

  protected array $items = [
    self::Weapon => [],
    self::Armor => [],
    self::Movement => [],
    self::Healing => [],
    self::Misc => []
  ];

  /**
   * @brief Parent class will implement this and use this function as where to call the register functions
   */
  abstract public function init();

  /**
   * Retrieves a balanced array of 1-2 items from each category.
   * @return Item[] Returns an array consisting of 1-2 items from each category.
   */
  public function getRandomLoot(): array
  {
    $loot = [];

    foreach ($this->items as $itemsInCategory) {
      if (!empty($itemsInCategory)) {
        shuffle($itemsInCategory); // Randomize the order of items

        // Always add the first item if available
        $this->addItem($itemsInCategory, $loot, 0);

        // Randomly decide to add a second item from the same category, if it exists (50% chance)
        if (count($itemsInCategory) > 1 && rand(0, 1)) {
          $this->addItem($itemsInCategory, $loot, 1);
        }
      }
    }

    return $loot;
  }

  private function addItem(array $itemCategory, array &$loot, int $index): void
  {
    $item = clone $itemCategory[$index]; // needs to be a fresh clone
    if ($item instanceof Item) {
      // also apply random stack size if stackable
      if ($item->getMaxStackSize() > 1) {
        $item->setCount(rand(1, 3));
      }
      $loot[] = $item;
    }
  }

  /**
   * Retrieves a single randomly selected item from all categories.
   * @return Item Returns a randomly selected item.
   */
  public function getRandomItem(): Item
  {
    $allItems = array_merge(...array_values($this->items));
    return $allItems[array_rand($allItems)];
  }

  /**
   * Retrieves a random item of a specified category.
   * @param int $category The category from which to retrieve the item ('weapon', 'armor', 'movement', 'healing', 'misc').
   * @return Item Returns an item of the specified category.
   * @throws Exception
   */
  public function getRandomItemOfCategory(int $category): Item
  {
    if (!empty($this->items[$category])) {
      $itemsInCategory = $this->items[$category];
      return $itemsInCategory[array_rand($itemsInCategory)];
    }

    throw new Exception("No items available in category: $category");
  }

  /**
   * Registers an item under a specified category.
   * @param int $category The category to register the item under.
   * @param Item $item The item to register.
   */
  public function registerItem(int $category, Item $item): void
  {
    if (isset($this->items[$category])) {
      $this->items[$category][] = $item;
    }
  }

}
