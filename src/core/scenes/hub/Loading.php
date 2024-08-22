<?php

namespace core\scenes\hub;

use core\systems\player\SwimPlayer;
use core\systems\scene\Scene;
use core\utils\BehaviorEventEnums;
use core\utils\PositionHelper;
use core\utils\ServerSounds;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class Loading extends Scene
{

  private Position $safeSpawn;

  public static function AutoLoad(): bool
  {
    return true;
  }

  public function init(): void
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
    $hub = $this->core->getServer()->getWorldManager()->getWorldByName("hub");
    $this->safeSpawn = PositionHelper::centerPosition($hub->getSafeSpawn());
  }

  public function exit(): void
  {

  }

  public function updateTick(): void
  {
    // TODO: Implement updateTick() method.
  }

  public function updateSecond(): void
  {
    foreach ($this->players as $player) {
      $player->sendMessage(TextFormat::GRAY . "Loading your Data...");
    }
  }

  private function goHubAndWait(Player $player): void
  {
    $player->teleport($this->safeSpawn);
    $player->setNoClientPredictions();
    $player->setInvisible();
    $player->setGamemode(GameMode::ADVENTURE);
  }

  public function playerAdded(SwimPlayer $player): void
  {
    $this->restart($player);
  }

  public function playerRemoved(SwimPlayer $player): void
  {
    $player->sendMessage(TextFormat::GREEN . "Data Loaded!");
    // join message announcement
    $this->core->getServer()->broadcastMessage("§a[+] §e" . $player->getName());
    // play a little jingle and greeting
    ServerSounds::playSoundToPlayer($player, 'random.levelup', 2, 1);
    $player->sendMessage(TextFormat::GREEN . "Welcome to " . TextFormat::AQUA . "Swim.gg" . TextFormat::BLUE . " 2.0");
  }

  public function restart(SwimPlayer $swimPlayer): void
  {
    $swimPlayer->sendMessage(TextFormat::GRAY . "Loading your Data...");
    $this->goHubAndWait($swimPlayer);
  }

}