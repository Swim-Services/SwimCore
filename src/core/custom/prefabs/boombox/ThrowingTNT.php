<?php

namespace core\custom\prefabs\boombox;

use core\systems\player\SwimPlayer;
use core\utils\InventoryUtil;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\BlockTransaction;
use pocketmine\world\sound\IgniteSound;

class ThrowingTNT extends BaseBox
{

  public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null): bool
  {
    if ($player instanceof SwimPlayer) {
      $cd = $player->getCoolDowns();

      // if on cool down then don't do throwing tnt logic
      if ($cd->onCoolDown($item)) {
        return false;
      }

      // place the primed tnt
      $pos = $blockReplace->getPosition();
      $primedTnt = new SmoothPrimedTNT(Location::fromObject($pos->add(0.5, 1, 0.5), $pos->getWorld()));
      $primedTnt->setFuse(20);
      $primedTnt->spawnToAll();
      $primedTnt->broadcastSound(new IgniteSound());

      // then add a cool down to it
      $cd->setCoolDown($item, 1, false);
      $cd->setFocused($item);
      InventoryUTil::forceItemPop($player, $item);
    }

    return true;
  }

}

// Below here is the worst, hacky, welded on solution I have ever made for something in PocketMine

class TNT_Listener implements Listener
{

  /**
   * @priority LOWEST
   */
  function onUse(PlayerItemUseEvent $event): void
  {
    if ($event->isCancelled()) return;

    $item = $event->getItem();
    $player = $event->getPlayer();
    if (($item->getTypeId() == -BlockTypeIds::TNT) && ($player instanceof SwimPlayer) && ($item->getCustomName() === TextFormat::RESET . TextFormat::RED . "Throwing TNT")) {
      // if on cool down then don't do throwing tnt logic
      if ($player->getCoolDowns()->onCoolDown($item)) {
        return;
      }

      // trigger cool down
      $player->getCoolDowns()->triggerItemCoolDownEvent($event, $player->getInventory()->getItemInHand(), 1, true, false);

      // if the item cool down did not cancel the event (meaning we aren't on cool down) then do tnt throwing

      $tnt = new SmoothPrimedTNT(Location::fromObject($player->getEyePos(), $player->getWorld()));
      $tnt->setScale(0.5);
      $tnt->setGravity(0.025);
      $tnt->setMotion($player->getDirectionVector()->multiply(1.2));
      $tnt->setNoClientPredictions(false);
      $tnt->setFuse(40);
      $tnt->spawnToAll();
      $tnt->broadcastSound(new IgniteSound());
      InventoryUTil::forceItemPop($player, $item);
    }
  }

}

// exists to cause the construct to happen, only constructed once
static $throwable = new ThrowableBlock();

class ThrowableBlock
{

  private ?Plugin $swimCore;
  private static bool $constructed = false;

  public function __construct()
  {
    if (self::$constructed) {
      echo "\nAlready Constructed Throwable TNT Listener\n";
      return;
    }

    echo "\nConstructing Throwable TNT Listener\n";
    $server = Server::getInstance();
    $pluginManager = $server->getPluginManager();
    $this->swimCore = $pluginManager->getPlugin("swim");
    if ($this->swimCore) {
      $pluginManager->registerEvents(new TNT_Listener(), $this->swimCore);
      self::$constructed = true;
    } else {
      echo "\nHorrible error | SwimCore plugin not found when making throwing tnt listener\n";
    }
  }

}
