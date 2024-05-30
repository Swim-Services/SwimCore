<?php

namespace core\scenes\hub;

use core\custom\prefabs\boombox\KnockerBox;
use core\custom\prefabs\boombox\ThrowingTNT;
use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Stick;
use pocketmine\item\VanillaItems;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

// this scene allows anything to happen

class GodMode extends scene
{

  public function init(): void
  {

  }

  private function movementKit(Player $player): void
  {
    $player->getEffects()->add(new EffectInstance(VanillaEffects::RESISTANCE(), 255 * 999999, 255, false)); // make invincible
    $player->getInventory()->setItem(0, VanillaItems::STICK()->setCustomName(TextFormat::GREEN . "broomstick")
      ->addEnchantment(new EnchantmentInstance(VanillaEnchantments::UNBREAKING())));

    // tnt adding
    $tnt = VanillaBlocks::TNT()->asItem();
    $tnt->setCount(64);

    // Knock back TNT
    $knockerBox = (new KnockerBox())->asItem();
    $knockerBox->setCustomName(TextFormat::RESET . TextFormat::LIGHT_PURPLE . "Knocker Box");
    $knockerBox->setCount(64);
    $player->getInventory()->addItem($knockerBox);

    // Throwing TNT
    $throwingTNT = (new ThrowingTNT())->asItem();
    $throwingTNT->setCustomName(TextFormat::RESET . TextFormat::RED . "Throwing TNT");
    $throwingTNT->setCount(64);
    $player->getInventory()->addItem($throwingTNT);
  }

  public function sceneItemUseEvent(PlayerItemUseEvent $event, SwimPlayer $swimPlayer): void
  {
    // god stick logic
    $item = $event->getItem();
    if ($item instanceof Stick && $item->getCustomName() === TextFormat::GREEN . "broomstick") {
      $directionVector = $event->getPlayer()->getDirectionVector();
      $event->getPlayer()->setMotion($directionVector->multiply(5));
    }
  }

  // no fall
  public function sceneEntityDamageEvent(EntityDamageEvent $event, SwimPlayer $swimPlayer): void
  {
    $cause = $event->getCause();
    if ($cause == EntityDamageEvent::CAUSE_FALL || $cause == EntityDamageEvent::CAUSE_VOID) {
      $event->cancel();
    }
  }

  public function playerAdded(SwimPlayer $player): void
  {
    $player->sendMessage(TextFormat::GREEN . "entering god mode");
    $player->setGamemode(GameMode::SURVIVAL());
    $this->movementKit($player);
    // $doubleJump = new DoubleJump("DoubleJump", $this->core, $player, true, false);
    // $player->registerBehavior($doubleJump);
  }

  public function playerRemoved(SwimPlayer $player): void
  {
    $player->getBehaviorManager()?->removeComponent("DoubleJump");
  }

}