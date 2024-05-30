<?php

namespace core\utils;

use CortexPE\Commando\args\BaseArgument;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;

class ArrayEnumArgument extends BaseArgument
{

  private array $values;
  private string $aName;

  public function __construct(string $name, array $values, bool $optional = false)
  {
    parent::__construct($name, $optional);
    $this->values = $values;
    $this->aName = $name;
    $this->parameterData->enum = new CommandEnum("", $this->getEnumValues());
  }

  public function getNetworkType(): int
  {
    // this will be disregarded by PM anyway because this will be considered as a string enum
    return -1;
  }

  public function getTypeName(): string
  {
    return $this->aName;
  }

  public function canParse(string $testString, CommandSender $sender): bool
  {
    return (bool)preg_match(
      "/^(" . implode("|", array_map("\\strtolower", $this->getEnumValues())) . ")$/iu",
      $testString
    );
  }

  public function getValue(string $string): string
  {
    return strtolower($string);
  }

  public function getEnumValues(): array
  {
    return $this->values;
  }

  public function parse(string $argument, CommandSender $sender): string
  {
    return $this->getValue($argument);
  }

}