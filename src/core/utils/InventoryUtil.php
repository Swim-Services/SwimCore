<?php

namespace core\utils;

use core\custom\prefabs\pearl\SwimPearlItem;
use core\custom\prefabs\pot\SwimPotItem;
use core\systems\player\SwimPlayer;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\item\Item;
use pocketmine\item\PotionType;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class InventoryUtil
{

  // clears all inventories of a player
  public static function clearInventory(Player $player): void
  {
    $player->getInventory()->clearAll();
    $player->getArmorInventory()->clearAll();
    $player->getCursorInventory()->clearAll();
    $player->getCraftingGrid()->clearAll();
  }

  // clear XP
  public static function clearXP(Player $player): void
  {
    $player->getXpManager()?->setXplevel(0);
    $player->getXpManager()?->setXpProgress(0.0);
  }

  // clears a player's inventory, effects, xp, hp, food, flight, and sets them to adventure
  // sets all player states back as well, such as flying, input, and visibility
  public static function fullPlayerReset(Player $player): void
  {
    self::clearInventory($player);
    self::clearXP($player);
    $player->getEffects()->clear();
    $player->setHealth($player->getMaxHealth());
    $player->setAbsorption(0.0);
    $player->setAllowFlight(false);
    $player->setGamemode(GameMode::ADVENTURE());
    $player->setFlying(false);
    $player->setNoClientPredictions(false);
    $player->setInvisible(false);
  }

  // get the amount of an item in a player's inventory
  public static function getItemCount(Player $player, Item $item): int
  {
    $count = 0;
    $inventory = $player->getInventory();
    $id = $item->getTypeId();
    foreach ($inventory->getContents() as $currentItem) {
      if ($currentItem->getTypeId() == $id) {
        $count += $currentItem->getCount();
      }
    }
    return $count;
  }

  // give a player full unbreakable diamond armor
  public static function diamondArmor(Player $player): void
  {
    $player->getArmorInventory()->setHelmet(VanillaItems::DIAMOND_HELMET()->setUnbreakable());
    $player->getArmorInventory()->setChestplate(VanillaItems::DIAMOND_CHESTPLATE()->setUnbreakable());
    $player->getArmorInventory()->setLeggings(VanillaItems::DIAMOND_LEGGINGS()->setUnbreakable());
    $player->getArmorInventory()->setBoots(VanillaItems::DIAMOND_BOOTS()->setUnbreakable());
  }

  // give the player unbreakable diamond sword
  public static function diamondSword(Player $player): void
  {
    $sword = VanillaItems::DIAMOND_SWORD();
    $sword->setUnbreakable();
    $player->getInventory()->setItem(0, $sword);
  }

  // this is such a common load out its fine to put here in inv util
  public static function potKit(SwimPlayer $swimPlayer, bool $giveSpeed = true): void
  {
    $swimPlayer->setHealth($swimPlayer->getMaxHealth()); // for ffa logic to heal
    self::diamondSword($swimPlayer);
    // $player->getInventory()->setItem(1, VanillaItems::ENDER_PEARL()->setCount(16));
    $swimPlayer->getInventory()->setItem(1, new SwimPearlItem($swimPlayer, 16));
    self::diamondArmor($swimPlayer);
    // give pots
    $pot = new SwimPotItem(TextFormat::RED . "Splash Potion of Healing");
    $pot->setType(PotionType::STRONG_HEALING())->setCount(34);
    $swimPlayer->getInventory()->addItem($pot);
    // give speed
    if ($giveSpeed) {
      $speed = new EffectInstance(VanillaEffects::SPEED());
      $speed->setVisible(false);
      $speed->setDuration(20 * 60000); // a long time
      $speed->setAmplifier(0);
      $swimPlayer->getEffects()->add($speed);
    }
  }

  // gives a player n amount of items, but does not overflow, so if a player has 2 gaps,
  // and I refill them with count 3 then it caps off at 3, essentially only giving them +1 gap
  public static function refill(Player $player, Item $item, int $amount): void
  {
    $inv = $player->getInventory();

    // Calculate the current total count of the specified item
    $currentTotalCount = 0;
    foreach ($inv->getContents() as $invItem) {
      if ($invItem->equals($item)) {
        $currentTotalCount += $invItem->getCount();
      }
    }

    // Calculate the required amount to refill (but not exceed) $amount
    $requiredAmount = $amount - $currentTotalCount;
    if ($requiredAmount <= 0) {
      // If the inventory already has $amount or more, no refilling is needed
      return;
    }

    // Refill existing items first
    foreach ($inv->getContents() as $slot => $invItem) {
      if ($invItem->equals($item)) {
        // Determine how much can be added to this slot
        $addAmount = min($invItem->getMaxStackSize() - $invItem->getCount(), $requiredAmount);

        // Increase the count of the item in this slot
        $invItem->setCount($invItem->getCount() + $addAmount);
        $inv->setItem($slot, $invItem);

        // Decrease the required amount by the added amount
        $requiredAmount -= $addAmount;

        // Break if no more items are needed
        if ($requiredAmount <= 0) {
          return;
        }
      }
    }

    // If required amount is still left, find an empty slot to add a new stack
    $emptySlot = $inv->firstEmpty();
    if ($emptySlot !== -1) {
      $newItem = clone $item; // didn't know php could do this!
      $newItem->setCount(min($newItem->getMaxStackSize(), $requiredAmount));
      $inv->setItem($emptySlot, $newItem);
    }
  }

  // because item->pop() just doesn't like to work
  public static function forceItemPop(Player $player, Item $item): void
  {
    $player->getInventory()->setItem($player->getInventory()->getHeldItemIndex(), $item->setCount($item->getCount() - 1));
  }

  public static function boxingKit(Player $player): void
  {
    $player->setHealth($player->getMaxHealth());
    self::diamondSword($player);
  }

  public static function midfKit(Player $player): void
  {
    $player->setHealth($player->getMaxHealth());
    self::diamondSword($player);
    self::diamondArmor($player);
  }

  public static function midfPearlKit(SwimPlayer $player): void
  {
    self::midfKit($player);
    $player->getInventory()->setItem(2, new SwimPearlItem($player));
  }

  public static function bbMidfKit(Player $player): void
  {
    self::midfKit($player);
    $player->getInventory()->setItem(2, VanillaBlocks::TNT()->asItem()->setCount(1)->setCustomName(TextFormat::DARK_PURPLE . "Knocker Box"));
  }

  // this function only works properly if the player has the actual item in their inventory
  // if they don't slot -1 is returned which is pretty bad
  public static function getSlotItemIsIn(SwimPlayer $player, Item $item): int
  {
    return $player->getInventory()->first($item);
  }

}
