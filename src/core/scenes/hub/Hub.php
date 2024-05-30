<?php

namespace core\scenes\hub;

use core\custom\prefabs\hub\HubEntities;
use core\forms\hub\FormDuelRequests;
use core\forms\hub\FormDuels;
use core\forms\hub\FormEvents;
use core\forms\hub\FormFFA;
use core\forms\hub\FormSettings;
use core\forms\hub\FormSpectate;
use core\forms\parties\FormPartyCreate;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\utils\BehaviorEventEnums;
use jackmd\scorefactory\ScoreFactory;
use jackmd\scorefactory\ScoreFactoryException;
use jojoe77777\FormAPI\ModalForm;
use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use ReflectionException;

class Hub extends Scene
{

  /**
   * @throws ReflectionException
   */
  function init(): void
  {
    $this->registerCanceledEvents([
      BehaviorEventEnums::ENTITY_DAMAGE_EVENT,
      BehaviorEventEnums::ENTITY_DAMAGE_BY_ENTITY_EVENT,
      BehaviorEventEnums::ENTITY_DAMAGE_BY_CHILD_ENTITY_EVENT,
      BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT,
      BehaviorEventEnums::PROJECTILE_LAUNCH_EVENT,
      BehaviorEventEnums::BLOCK_BREAK_EVENT,
      BehaviorEventEnums::BLOCK_PLACE_EVENT,
      BehaviorEventEnums::PLAYER_ITEM_CONSUME_EVENT
    ]);
    // spawn in our hub entities for the scene
    HubEntities::spawnToScene($this);
  }

  public function playerAdded(SwimPlayer $player): void
  {
    $this->restart($player);
  }

  // when leaving the hub we remove any duel invites they sent to any players who are in the hub
  public function playerRemoved(SwimPlayer $player): void
  {
    $id = $player->getId();
    $name = $player->getName();
    foreach ($this->players as $plr) {
      if ($plr instanceof SwimPlayer) {
        if ($plr->getId() != $id) {
          $plr->getInvites()->prunePlayerFromDuelInvites($name);
        }
      }
    }
  }

  public function restart(SwimPlayer $swimPlayer): void
  {
    $this->teleportToHub($swimPlayer);
    $this->setHubKit($swimPlayer);
    $this->setHubTags($swimPlayer);
    // $swimPlayer->getCosmetics()->refresh();
    $swimPlayer->setGamemode(GameMode::ADVENTURE);
  }

  private function teleportToHub(SwimPlayer $player): void
  {
    $hub = $this->core->getServer()->getWorldManager()->getWorldByName("hub");
    $safeSpawn = $hub->getSafeSpawn();
    $player->teleport(new Position($safeSpawn->getX() + 0.5, $safeSpawn->getY(), $safeSpawn->getZ() + 0.5, $hub));
  }

  private function setHubKit(SwimPlayer $player): void
  {
    $inventory = $player->getInventory();
    $inventory->setHeldItemIndex(4);
    $inventory->clearAll();
    /*
    $inventory->setItem(0, VanillaItems::DIAMOND_SWORD()->setCustomName("§bFFA §7[Right Click]"));
    $inventory->setItem(1, VanillaItems::IRON_SWORD()->setCustomName("§fDuels §7[Right Click]"));
    $inventory->setItem(2, VanillaItems::TOTEM()->setCustomName("§aDuel Requests §7[Right Click]"));
    $inventory->setItem(3, VanillaItems::PAPER()->setCustomName("§bSpectate Matches §7[Right Click]"));
    $inventory->setItem(5, VanillaItems::NETHER_STAR()->setCustomName("§bEvents §7[Right Click]"));
    $inventory->setItem(6, VanillaItems::EMERALD()->setCustomName("§dEdit Kits §7[Right Click]"));
    $inventory->setItem(7, VanillaBlocks::CAKE()->asItem()->setCustomName("§aParties §7[Right Click]"));
    */
    $inventory->setItem(8, VanillaItems::BOOK()->setCustomName("§bManage Settings §7[Right Click]"));
  }

  private function setHubTags(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->genericNameTagHandling();
    // $swimPlayer->getCosmetics()->tagNameTag();
    $swimPlayer->setScoreTag("");
  }

  // at scene update we call the scoreboard behavior function

  /**
   * @throws ScoreFactoryException
   */
  function updateSecond(): void
  {
    foreach ($this->players as $player) {
      $this->hubBoard($player);
    }
  }

  /**
   * @throws ScoreFactoryException
   */
  private function hubBoard(SwimPlayer $swimPlayer): void
  {
    $player = $swimPlayer;
    if ($swimPlayer->isScoreboardEnabled()) {
      try {
        $swimPlayer->refreshScoreboard(TextFormat::AQUA . "Swimgg.club");
        ScoreFactory::sendObjective($player);
        // variables needed
        $onlineCount = count($player->getServer()->getOnlinePlayers());
        $maxPlayers = $player->getServer()->getMaxPlayers();
        $ping = $swimPlayer->getNslHandler()->getPing();
        $indent = "  ";
        // define lines
        ScoreFactory::setScoreLine($player, 1, "  =============   ");
        ScoreFactory::setScoreLine($player, 2, $indent . "§bOnline: §f" . $onlineCount . "§7 / §3" . $maxPlayers . $indent);
        ScoreFactory::setScoreLine($player, 3, $indent . "§bPing: §3" . $ping . $indent);
        ScoreFactory::setScoreLine($player, 4, $indent . "§bdiscord.gg/§3swim" . $indent);
        ScoreFactory::setScoreLine($player, 5, "  =============  ");
        // send lines
        ScoreFactory::sendLines($player);
      } catch (ScoreFactoryException $e) {
        Server::getInstance()->getLogger()->info($e->getMessage());
      }
    }
  }

  // when placing cake
  public function sceneBlockPlaceEvent(BlockPlaceEvent $event, SwimPlayer $swimPlayer): void
  {
    foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
      if ($block instanceof Block) {
        if ($block->getTypeId() == VanillaBlocks::CAKE()->getTypeId()) {
          FormPartyCreate::partyBaseForm($this->core, $swimPlayer);
        }
      }
    }
    $event->cancel();
  }

  // using hub items to open forms
  // TO DO : use custom item classes with their own embedded on use callbacks to avoid this string switch statement malarkey
  public function sceneItemUseEvent(PlayerItemUseEvent $event, SwimPlayer $swimPlayer): void
  {
    $item = $swimPlayer->getInventory()->getItemInHand();
    $name = $item->getCustomName();

    // party items instead
    $sh = $swimPlayer->getSceneHelper();
    if ($sh->isInParty()) {
      $sh->getParty()?->partyItemHandle($swimPlayer, $name);
      return;
    }

    // regular hub items
    switch ($name) {
      case "§bFFA §7[Right Click]":
        FormFFA::ffaSelectionForm($swimPlayer);
        break;
      case "§fDuels §7[Right Click]":
        FormDuels::duelSelectionForm($swimPlayer);
        break;
      case "§bManage Settings §7[Right Click]":
        FormSettings::settingsForm($swimPlayer);
        break;
      case "§aParties §7[Right Click]":
        FormPartyCreate::partyBaseForm($this->core, $swimPlayer);
        break;
      case "§bSpectate Matches §7[Right Click]":
        FormSpectate::spectateSelectionForm($this->core, $swimPlayer);
        break;
      case "§aDuel Requests §7[Right Click]":
        FormDuelRequests::duelSelectionBase($this->core, $swimPlayer);
        break;
      case "§dEdit Kits §7[Right Click]":
        $this->editKitConfirm($swimPlayer);
        break;
      case "§bEvents §7[Right Click]":
        FormEvents::eventForm($this->core, $swimPlayer);
        break;
    }
  }

  // TO DO: update the form api virion to work properly for modal form because null is returned on closing and that isn't handled properly
  public static function editKitConfirm(SwimPlayer $swimPlayer): void
  {
    $form = new ModalForm(function (SwimPlayer $player, $data) {
      if ($data === null) {
        return false;
      }

      if ($data == 1) {
        $player->getSceneHelper()->setNewScene('Kits');
      }
      return true;
    });

    $form->setTitle("Kit Editor");
    $form->setContent("Go to kit Editor?");
    $form->setButton1(TextFormat::GREEN . "Yes");
    $form->setButton2(TextFormat::RED . "No, Stay in Hub");
    $swimPlayer->sendForm($form);
  }

} // class Hub
