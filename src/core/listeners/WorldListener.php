<?php

namespace core\listeners;

use core\SwimCore;
use core\systems\player\components\ClickHandler;
use core\systems\player\components\NetworkStackLatencyHandler;
use core\systems\player\PlayerSystem;
use core\systems\player\SwimPlayer;
use core\systems\SystemManager;
use Exception;
use pocketmine\block\BlockTypeIds;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockBurnEvent;
use pocketmine\event\block\BlockGrowEvent;
use pocketmine\event\block\BlockMeltEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\inventory\PlayerOffHandInventory;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\network\mcpe\protocol\NetworkStackLatencyPacket;
use pocketmine\network\mcpe\protocol\PlayerAuthInputPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\ResourcePackStackPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\SetActorMotionPacket;
use pocketmine\network\mcpe\protocol\SetTimePacket;
use pocketmine\network\mcpe\protocol\StartGamePacket;
use pocketmine\network\mcpe\protocol\types\BoolGameRule;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\entity\StringMetadataProperty;
use pocketmine\network\mcpe\protocol\types\Experiments;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\network\mcpe\protocol\types\LevelSoundEvent;
use pocketmine\network\mcpe\protocol\types\PlayerAuthInputFlags;
use pocketmine\network\mcpe\protocol\types\resourcepacks\ResourcePackStackEntry;
use pocketmine\utils\TextFormat as TF;
use pocketmine\world\World;
use ReflectionClass;
use ReflectionException;

// this listener is just a bunch of tweaks to make global vanilla events better

class WorldListener implements Listener
{

  private SwimCore $core;
  private PlayerSystem $playerSystem;
  private SystemManager $systemManager;

  private array $eduItemIds = []; // education item crap to filter out from the client

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->systemManager = $this->core->getSystemManager();
    $this->playerSystem = $this->systemManager->getPlayerSystem();
  }

  // void can't kill unless we are really low
  public function onEntityVoid(EntityDamageEvent $event)
  {
    if ($event->getCause() == EntityDamageEvent::CAUSE_VOID) {
      $entity = $event->getEntity();
      if ($entity->getPosition()->getY() > -200) {
        $event->cancel();
      }
    }
  }

  public function onLeavesDecay(LeavesDecayEvent $event)
  {
    $event->cancel();
  }

  public function onGrow(BlockGrowEvent $event): void
  {
    $event->cancel();
  }

  public function onBurn(BlockBurnEvent $event): void
  {
    $event->cancel();
  }

  public function onMelt(BlockMeltEvent $event): void
  {
    $event->cancel();
  }

  // only cancels door opens in the hub
  public function hubBlockInteract(PlayerInteractEvent $event)
  {
    $player = $event->getPlayer();
    if (!$player->isCreative() && $player->getWorld()->getFolderName() === "hub") {
      $blockName = strtolower($event->getBlock()->getName());
      if (str_contains($blockName, "door") || str_contains($blockName, "gate")) {
        $event->cancel();
      }
    }
  }

  // remove offhand functionality
  public function preventOffHanding(InventoryTransactionEvent $event)
  {
    $inventories = $event->getTransaction()->getInventories();
    foreach ($inventories as $inventory) {
      if ($inventory instanceof PlayerOffHandInventory) {
        $event->cancel();
      }
    }
  }

  // disable sending the chemistry pack to players on joining so particles look fine
  public function onDataPacketSendEvent(DataPacketSendEvent $event): void
  {
    $protocol = ProtocolInfo::CURRENT_PROTOCOL;
    if (isset($event->getTargets()[0])) {
      $protocol = $event->getTargets()[0]->getProtocolId();
    }

    $packets = $event->getPackets();
    foreach ($packets as $packet) {
      if ($packet instanceof ResourcePackStackPacket) {
        $stack = $packet->resourcePackStack;
        foreach ($stack as $key => $pack) {
          if ($pack->getPackId() === "0fba4063-dba1-4281-9b89-ff9390653530") {
            unset($packet->resourcePackStack[$key]);
            break;
          }
        }
        // experiment resource pack
        if ($protocol == 671) {
          $packet->experiments = new Experiments(["updateAnnouncedLive2023" => true], true);
          $stack[] = new ResourcePackStackEntry("d8989e4d-5217-4d57-a6f6-1787c620be97", "0.0.1", "");
        }
        break;
      }
    }
  }

  // rod sound
  /* leave this to the custom rod class to implement
  public function rodCastSound(PlayerItemUseEvent $event)
  {
    $player = $event->getPlayer();
    $item = $event->getItem();
    if ($item->getName() == "Fishing Rod") {
      ServerSounds::playSoundToPlayer($player, "random.bow", 2, 1);
    }
  }
  */

  // prevent player drops (be mindful of this event's existence if we are ever programming a game where we want entity drops to go somewhere like a chest)
  public function onPlayerDeath(PlayerDeathEvent $event)
  {
    $event->setDrops([]);
    $event->setXpDropAmount(0);
  }

  // prevent switch hits
  /* we already do this in player listener
  public function onEntityDamagedByEntity(EntityDamageByEntityEvent $event)
  {
    if ($event->getModifier(EntityDamageEvent::MODIFIER_PREVIOUS_DAMAGE_COOLDOWN) < 0) {
      $event->cancel();
    }
  }
  */

  public function onBlockInteract(PlayerInteractEvent $event)
  {
    $player = $event->getPlayer();
    if (!$player->isCreative()) {
      $block = $event->getBlock();
      $id = $block->getTypeId();
      if ($id == BlockTypeIds::CHEST || $id == BlockTypeIds::ENDER_CHEST) return; // if it's a chest we can interact with it so exit out early

      // cancel log stripping
      $heldItem = $player->getInventory()->getItemInHand();
      if (str_contains(strtolower($heldItem->getName()), "axe")) {
        $event->cancel();
      }

      // cancel sign editing
      if (str_contains(strtolower($event->getBlock()->getName()), "sign")) {
        $event->cancel();
      }
    }
  }

  // never have exhaust
  public function onExhaust(PlayerExhaustEvent $event)
  {
    $event->cancel();
  }

  // cancel swimming animation (don't think this works)
  /*
  public function onSwim(PlayerToggleSwimEvent $event)
  {
    $event->cancel();
  }
  */

  // cancel weird drops
  public function onBlockBreak(BlockBreakEvent $event)
  {
    $id = $event->getBlock()->getTypeId();
    if ($id == BlockTypeIds::TALL_GRASS
      || $id == BlockTypeIds::DOUBLE_TALLGRASS
      || $id == BlockTypeIds::SUNFLOWER
      || $id == BlockTypeIds::COBWEB
      || $id == BlockTypeIds::LARGE_FERN) {
      $event->setDrops([]);
    }
  }

  // make it so anyone can see swing animations
  public function onDataPacketReceive(DataPacketReceiveEvent $event): void
  {
    $player = $event->getOrigin()->getPlayer();
    if ($player !== null) {
      $packet = $event->getPacket();
      if ($packet instanceof AnimatePacket && $packet->action === AnimatePacket::ACTION_SWING_ARM
        || $packet instanceof LevelSoundEventPacket && $packet->sound == LevelSoundEvent::ATTACK_NODAMAGE) {
        $event->cancel(); // why cancel?
        $player->broadcastAnimation(new ArmSwingAnimation($player), $player->getViewers());
      }
    }
  }

  /**
   * @throws ReflectionException
   * @throws Exception
   */
  public function onDataPacketSend(DataPacketSendEvent $event): void
  {
    $packets = $event->getPackets();

    foreach ($packets as $key => $packet) {
      if ($this->processActorDataPackets($packet, $packets, $event, $key)) continue;
      if ($this->processSetTimePacket($packet, $packets, $key)) continue;
      if ($this->processPlayerListPacket($packet)) continue;
      if ($this->processStartGamePacket($packet, $event, $key)) continue;
      if ($this->processCreativeContentPacket($packet, $event, $key)) continue;
      if ($this->processCraftingPacket($packet, $packets, $key)) continue;
      $this->processAck($packet, $packets, $event);
    }

    $event->setPackets($packets);
  }

  // disabled crafting completely, intended for 1.21 at the moment
  private function processCraftingPacket($packet, &$packets, $key): bool
  {
    if ($packet instanceof CraftingDataPacket) {
      unset($packets[$key]);
      return true;
    }
    return false;
  }

  private function processSetTimePacket($packet, &$packets, $key): bool
  {
    if ($packet instanceof SetTimePacket) {
      if ($packet->time >= 2000000000) {
        $packet->time -= 2000000000;
      } else {
        unset($packets[$key]);
      }
      return true;
    }
    return false;
  }

  private function processPlayerListPacket($packet): bool
  {
    if ($packet instanceof PlayerListPacket) {
      foreach ($packet->entries as $entry) {
        $entry->xboxUserId = "";
      }
      return true;
    }
    return false;
  }

  private function processStartGamePacket($packet, $event, $key): bool
  {
    if ($packet instanceof StartGamePacket) {
      for ($i = 0; $i < count($packet->itemTable); $i++) {
        if (str_contains($packet->itemTable[$i]->getStringId(), "element") ||
          str_contains($packet->itemTable[$i]->getStringId(), "chemistry")) {
          $this->eduItemIds[$event->getTargets()[$key]->getPlayer()->getName() ?? "null"][] =
            $packet->itemTable[$i]->getNumericId();
          unset($packet->itemTable[$i]);
        }
      }
      $packet->levelSettings->gameRules["dodaylightcycle"] = new BoolGameRule(false, false);
      $packet->levelSettings->time = World::TIME_DAY;
      $experiments = ["deferred_technical_preview" => true];

      $protocol = ProtocolInfo::CURRENT_PROTOCOL;
      if (isset($event->getTargets()[0])) {
        $protocol = $event->getTargets()[0]->getProtocolId();
      }

      if ($protocol == 671) {
        $experiments["updateAnnouncedLive2023"] = true;
      }
      $packet->levelSettings->experiments = new Experiments($experiments, true);

      return true;
    }
    return false;
  }

  /**
   * @throws ReflectionException
   */
  private function processCreativeContentPacket($packet, $event, $key): bool
  {
    if ($packet instanceof CreativeContentPacket) {
      $entries = $packet->getEntries();
      for ($i = 0; $i < count($entries); $i++) {
        if (isset($entries[$i]) && in_array($entries[$i]->getItem()->getId(),
            $this->eduItemIds[$event->getTargets()[$key]->getPlayer()->getName() ?? "null"])) {
          unset($entries[$i]);
        }
      }

      (new ReflectionClass($packet))->getProperty("entries")->setValue($packet, $entries);
      return true;
    }
    return false;
  }

  // pass the packets array by reference, so we can modify it
  private function processActorDataPackets($packet, &$packets, $event, $key): bool
  {
    if ($packet instanceof SetActorDataPacket || $packet instanceof AddActorPacket || $packet instanceof AddPlayerPacket) {
      if (isset($event->getTargets()[0]) && count($event->getTargets()) == 1) {
        $target = $event->getTargets()[0];
        $player = $target->getPlayer();
        $swimPlayer = $this->playerSystem->getSwimPlayer($player);
        if ($swimPlayer) {
          if (!$swimPlayer->getSettings()->getToggle('showScoreTags')) {
            $packet->metadata[EntityMetadataProperties::SCORE_TAG] = new StringMetadataProperty("");
          } else if (!isset($packet->metadata[EntityMetadataProperties::SCORE_TAG])) {
            foreach ($this->core->getServer()->getOnlinePlayers() as $pl) {
              if ($pl->getId() == $packet->actorRuntimeId) {
                $packet->metadata[EntityMetadataProperties::SCORE_TAG] = new StringMetadataProperty($pl->getScoreTag());
                break;
              }
            }
          }
        }
      } else {
        foreach ($event->getTargets() as $target) {
          $target->sendDataPacket(clone($packet));
        }
        unset($packets[$key]);
      }
      return true;
    }
    return false;
  }

  /**
   * @throws Exception
   */
  private function processAck($packet, &$packets, $event): void
  {
    // add move actor absolute packets
    if ($packet instanceof MoveActorAbsolutePacket || $packet instanceof AddActorPacket || $packet instanceof AddPlayerPacket) {
      $timestamp = NetworkStackLatencyHandler::randomIntNoZeroEnd();
      $tp = false;
      if (isset($packet->flags)) {
        $tp = $packet->flags & MoveActorAbsolutePacket::FLAG_TELEPORT > 0;
      }
      foreach ($event->getTargets() as $target) {
        /** @var SwimPlayer $pl */
        $pl = $target->getPlayer();
        $pl->getAckHandler()?->add($packet->actorRuntimeId, $packet->position, $timestamp, $tp);
      }

      $packets[] = NetworkStackLatencyPacket::create($timestamp * 1000, true);
    }

    // and then remove from the ack handler if needed
    if ($packet instanceof RemoveActorPacket) {
      $timestamp = NetworkStackLatencyHandler::randomIntNoZeroEnd();
      foreach ($event->getTargets() as $target) {
        /** @var SwimPlayer $pl */
        $pl = $target->getPlayer();
        $pl->getAckHandler()?->addRemoval($packet->actorUniqueId, $timestamp);
      }
      $packets[] = NetworkStackLatencyPacket::create($timestamp * 1000, true);
    }

    // add motion if needed
    if ($packet instanceof SetActorMotionPacket) {
      $timestamp = NetworkStackLatencyHandler::randomIntNoZeroEnd();
      foreach ($event->getTargets() as $target) {
        /** @var SwimPlayer $pl */
        $pl = $target->getPlayer();
        if ($pl->getId() != $packet->actorRuntimeId) continue;
        $pl->getAckHandler()?->addKb($packet->motion, $timestamp);
        $pl->getNetworkSession()->sendDataPacket(NetworkStackLatencyPacket::create($timestamp, true));
      }
    }
  }

  /**
   * @priority LOWEST
   * @brief main heart beat listener for what would be the anti cheat.
   * Instead for simplicity some hardcoded in function calls for gameplay related things the anticheat might do.
   * Such as DC prevent and auto sprint handling based on user settings.
   */
  public function onPacketReceive(DataPacketReceiveEvent $event)
  {
    /* @var SwimPlayer $player */
    $player = $event->getOrigin()->getPlayer();
    if (!isset($player)) return;

    $this->processNSL($event, $player); // update the network stack latency component for the player
    $this->handleInput($event, $player); // update player info based on input
    $this->processSwing($event, $player); // when player swings there fist (left click)
  }

  private function processNSL(DataPacketReceiveEvent $event, SwimPlayer $player): void
  {
    $pk = $event->getPacket();
    if ($pk instanceof NetworkStackLatencyPacket) {
      if (!$player->getAckHandler()->receive($pk)) $player->getNslHandler()->onNsl($pk); // if receive returns false then call onNsl
    }
  }

  private function handleInput(DataPacketReceiveEvent $event, SwimPlayer $swimPlayer): void
  {
    $packet = $event->getPacket();
    if (!($packet instanceof PlayerAuthInputPacket)) return;

    $swimPlayer->setExactPosition($packet->getPosition()->subtract(0, 1.62, 0)); // I don't know what the point of exact position is, something from GameParrot

    // auto sprint
    $settings = $swimPlayer->getSettings();
    if ($settings) {
      if ($settings->isAutoSprint()) {
        if ($packet->getMoveVecZ() > 0.5) {
          $swimPlayer->setSprinting();
        } else {
          $swimPlayer->setSprinting(false);
        }
      }
    }
  }

  private static string $spacer = TF::GRAY . " | " . TF::RED;

  ///* commented out since needs anticheat data which is not provided in the SwimCore public release, but shows how to do DC prevent logic
  private function processSwing(DataPacketReceiveEvent $event, SwimPlayer $swimPlayer): void
  {
    $packet = $event->getPacket();
    $swung = false;

    // enum from swim.gg anticheat code
    $LAST_CLICK_TIME = 0;

    if ($packet instanceof PlayerAuthInputPacket) {
      $swung = (($packet->getInputFlags() & (1 << PlayerAuthInputFlags::MISSED_SWING)) !== 0);
    }

    if ($packet instanceof LevelSoundEventPacket) {
      $swung = $packet->sound == LevelSoundEvent::ATTACK_NODAMAGE;
    }

    if ($swung || ($packet instanceof InventoryTransactionPacket && $packet->trData instanceof UseItemOnEntityTransactionData)) {
      $ch = $swimPlayer->getClickHandler();
      if ($ch) {

        $isRanked = $swimPlayer->getSceneHelper()?->getScene()->isRanked() ?? false;

        // dc prevent logic if enabled or in a ranked scene
        $settings = $swimPlayer->getSettings();
        if ($isRanked || ($settings?->dcPreventOn())) {
          if (((microtime(true) * 1000) - ($swimPlayer->getAntiCheatData()->getData($LAST_CLICK_TIME) ?? 0)) < 45) {
            $event->cancel(); // block the swing
          } else {
            $swimPlayer->getAntiCheatData()->setData($LAST_CLICK_TIME, microtime(true) * 1000);
          }
        }

        // if dc prevent didn't cancel the click then we can call it
        if (!$event->isCancelled()) {
          $ch->click();
        }

        // only does this notification in ranked marked scenes
        if ($isRanked && $ch->getCPS() > ClickHandler::CPS_MAX) {
          $msg = TF::RED . "Clicked above " . TF::YELLOW . ClickHandler::CPS_MAX . TF::RED . " CPS" . self::$spacer . TF::YELLOW . "Attacks will deal Less KB";
          $swimPlayer->sendActionBarMessage($msg);
        }
      }
    }
  }
  //*/

  /* Region and cross server query stuff is not in SwimCore public release, but leaving this commented out to show how to do this in a psuedo way.
  public function onQueryRegenerate(QueryRegenerateEvent $ev)
  {
    if (!$this->core->getRegionInfo()->isHub) return;
    $count = $this->core->getRegionPlayerCounts()->getTotalPlayerCount() + count($this->core->getServer()->getOnlinePlayers());
    $ev->getQueryInfo()->setPlayerCount($count);
    $ev->getQueryInfo()->setMaxPlayerCount($count + 1);
  }
  */

}