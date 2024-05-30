<?php

namespace core\custom\behaviors\player_event_behaviors;

use core\SwimCore;
use core\systems\player\components\behaviors\EventBehaviorComponent;
use core\systems\player\SwimPlayer;
use core\utils\particles\ColorFlameParticle;
use core\utils\particles\SonicBoomParticle;
use core\utils\particles\SparklerParticle;
use core\utils\particles\TotemParticle;
use core\utils\particles\WindExplosionParticle;
use core\utils\PositionHelper;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\particle\CriticalParticle;
use pocketmine\world\particle\EnchantmentTableParticle;
use pocketmine\world\particle\EndermanTeleportParticle;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\particle\HeartParticle;
use pocketmine\world\particle\InkParticle;
use pocketmine\world\particle\LavaDripParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\world\particle\ProtocolParticle;
use pocketmine\world\particle\RainSplashParticle;
use pocketmine\world\particle\RedstoneParticle;
use pocketmine\world\Position;

class ParticleEmitter extends EventBehaviorComponent
{

  private Server $server;
  private Position $lastPosition;
  private ProtocolParticle $particleArchetype;
  private int $ticks = 0;
  private bool $explosive = false;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer, bool $hasLifeTime = false, int $tickLifeTime = 120)
  {
    $this->server = $core->getServer();
    parent::__construct("particleEmitter", $core, $swimPlayer, true, $hasLifeTime, $tickLifeTime);
  }

  public function init(): void
  {
    $this->lastPosition = $this->swimPlayer->getPosition();
    $particle = $this->swimPlayer->getCosmetics()->getHubParticleEffect();

    $this->particleArchetype = match ($particle) {
      "chroma_flame" => ColorFlameParticle::rgb(0, 0, 0),
      "chroma_sparkler" => SparklerParticle::rgb(0, 0, 0),
      "crits" => new CriticalParticle(),
      "enchant" => new EnchantmentTableParticle(),
      "ender" => new EndermanTeleportParticle(),
      "flame" => new FlameParticle(),
      "heart" => new HeartParticle(),
      "lava" => new LavaDripParticle(),
      "portal" => new PortalParticle(),
      "rain" => new RainSplashParticle(),
      "redstone" => new RedstoneParticle(),
      "sonic_boom" => new SonicBoomParticle(),
      "sparkler" => SparklerParticle::rgb(255, 140, 0),
      "totem" => new TotemParticle(),
      "wind_explosion" => new WindExplosionParticle(),
      default => new InkParticle(), // bubble
    };

    if ($particle == "ender" || $particle == "sonic_boom" || $particle == "wind_explosion") {
      $this->explosive = true;
    }
  }

  public function eventUpdateTick(): void
  {
    $this->ticks++;
    $pos = $this->swimPlayer->getPosition();
    // only creates the particle if we moved
    if (!$this->lastPosition->equals($pos)) {
      // if explosive it spawns less often and not at a variadic Y level
      if ($this->explosive) {
        if ($this->ticks % 10 == 0) {
          $vec3 = new Vector3($pos->getX(), $pos->getY() + 1.0, $pos->getZ());
          $particle = clone $this->particleArchetype;
          $this->swimPlayer->getWorld()->addParticle(PositionHelper::vecToPos($vec3, $pos->world), $particle);
          $this->ticks = 0;
        }
      } else {
        switch ($this->ticks % 3) {
          case 0:
          default:
            $vec3 = new Vector3($pos->getX(), $pos->getY() + 0.15, $pos->getZ());
            break;
          case 1:
            $vec3 = new Vector3($pos->getX(), $pos->getY() + 1.0, $pos->getZ());
            break;
          case 2:
            $vec3 = new Vector3($pos->getX(), $pos->getY() + 1.5, $pos->getZ());
            break;
        }
        $particle = clone $this->particleArchetype;
        $this->swimPlayer->getWorld()->addParticle(PositionHelper::vecToPos($vec3, $pos->world), $particle);
        $this->ticks = 0;
      }
    }

    // update last position for this tick
    $this->lastPosition = $pos;
  }

}