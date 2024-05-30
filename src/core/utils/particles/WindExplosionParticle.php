<?php

namespace core\utils\particles;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\world\particle\ProtocolParticle;

class WindExplosionParticle extends ProtocolParticle
{

  public function __construct()
  {
  }

  public function encode(Vector3 $pos): array
  {
    return [LevelEventPacket::standardParticle(ParticleIds::WIND_EXPLOSION, 0, $pos, $this->protocolId)];
  }

}