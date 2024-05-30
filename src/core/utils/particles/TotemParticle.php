<?php

declare(strict_types=1);

namespace core\utils\particles;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\world\particle\ProtocolParticle;

class TotemParticle extends ProtocolParticle{
	public function __construct(){}

	public function encode(Vector3 $pos) : array{
		return [LevelEventPacket::standardParticle(ParticleIds::TOTEM, 0, $pos, $this->protocolId)];
	}
}
