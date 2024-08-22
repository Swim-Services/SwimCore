<?php

namespace core\utils;

use CortexPE\Commando\args\BaseArgument;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;

class TargetArgument extends BaseArgument
{
  private int $length = 1;

	public function __construct(string $name, bool $optional = false, int $length = 1) {
    $this->length = $length;
    parent::__construct($name, $optional);
  }

  public function getNetworkType(): int
  {
    return AvailableCommandsPacket::ARG_TYPE_TARGET;
  }

  public function getTypeName(): string
  {
    return "target";
  }

  public function canParse(string $testString, CommandSender $sender): bool
  {
    return true;
  }

  public function getSpanLength(): int {
		return $this->length;
	}

  public function parse(string $argument, CommandSender $sender): string
  {
    return $argument;
  }

}
