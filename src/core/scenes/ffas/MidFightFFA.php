<?php

namespace core\scenes\ffas;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\Utils\BehaviorEventEnums;
use core\utils\CustomDamage;
use core\utils\InventoryUtil;
use jackmd\scorefactory\ScoreFactoryException;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\GameMode;
use pocketmine\utils\TextFormat;

class MidFightFFA extends FFA
{

  public function __construct(SwimCore $core, string $name)
  {
    $this->world = $core->getServer()->getWorldManager()->getWorldByName("midFFA");
    parent::__construct($core, $name);
  }

  function init(): void
  {
    parent::init();
    $this->registerCanceledEvents([
      BehaviorEventEnums::PLAYER_DROP_ITEM_EVENT,
      BehaviorEventEnums::BLOCK_BREAK_EVENT
    ]);

    // arena center
    $this->x = 223;
    $this->y = 77;
    $this->z = 191;
    $this->spawnOffset = 20;

    $this->interruptAllowed = true;
    $this->world = $this->core->getServer()->getWorldManager()->getWorldByName("midFFA");
  }

  public function playerAdded(SwimPlayer $player): void
  {
    $this->restart($player);
  }

  public function restart(SwimPlayer $swimPlayer): void
  {
    $this->teleportToArena($swimPlayer);
    InventoryUtil::midfKit($swimPlayer);
    $this->ffaNameTag($swimPlayer);

    // enable 3rd party protection
    $logger = $swimPlayer->getCombatLogger();
    $logger->setIsProtected(!$this->interruptAllowed);
    $logger->setCoolDownTime(5);
    $logger->setUsingCombatCoolDown(!$this->interruptAllowed);

    $swimPlayer->setGamemode(GameMode::ADVENTURE);
  }

  protected function playerKilled(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    $this->midfKillMessage($attacker, $victim);
    $attacker->getCosmetics()->killMessageLogic($victim);
    $attacker->setHealth($attacker->getMaxHealth());
  }

  protected function playerHit(SwimPlayer $attacker, SwimPlayer $victim, EntityDamageByEntityEvent $event): void
  {
    // apply no critical custom damage
    CustomDamage::customDamageHandle($event);
  }

  private function midfKillMessage(SwimPlayer $attacker, SwimPlayer $victim): void
  {
    $attackerName = $attacker->getNicks()->getNick();
    $attackerHP = round($attacker->getHealth(), 1);
    $attackerHealthString = " " . TextFormat::GRAY . "[" . TextFormat::YELLOW . $attackerHP . " HP" . TextFormat::GRAY . "]";

    $victimName = $victim->getNicks()->getNick();
    // $victimHP = $victim->getHealth();
    //$victimHealthString = " " . TextFormat::GRAY . "[" . TextFormat::YELLOW . $victimHP . " HP" . TextFormat::GRAY . "]";

    $midFFA = TextFormat::BOLD . TextFormat::GRAY . "[" . TextFormat::AQUA . "MID FFA" . TextFormat::GRAY . "]" . TextFormat::RESET . " ";

    $msg = $midFFA . TextFormat::GREEN . $attackerName . $attackerHealthString . TextFormat::YELLOW
      . " Killed " . TextFormat::RED . $victimName;

    $this->sceneAnnouncement($msg);
  }

  /**
   * @throws ScoreFactoryException
   */
  public function updateSecond(): void
  {
    parent::updateSecond();

    foreach ($this->players as $player) {
      $this->ffaScoreboard($player);
      $this->ffaScoreTag($player);
    }
  }

}