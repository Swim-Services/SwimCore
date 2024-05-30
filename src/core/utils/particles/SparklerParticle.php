<?php

declare(strict_types=1);

namespace core\utils\particles;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelEventPacket;
use pocketmine\network\mcpe\protocol\types\ParticleIds;
use pocketmine\world\particle\ProtocolParticle;

class SparklerParticle extends ProtocolParticle{
	public function __construct(private int $color){}
	public static function rgb(int $red, int $green, int $blue): self {
		return new self(($red << 16) + ($green << 8) + $blue);
	}

	public function encode(Vector3 $pos) : array{
		return [LevelEventPacket::standardParticle(ParticleIds::SPARKLER, $this->color, $pos, $this->protocolId)];
	}
}
