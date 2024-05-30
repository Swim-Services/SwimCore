<?php

namespace core\commands\debugCommands;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;

class PositionCommand extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct($core, "pos", "set a position");
    $this->setPermission("use.staff");
    $this->core = $core;
  }

  /**
   * @inheritDoc
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new IntegerArgument("pos", false));
  }

  /**
   * @inheritDoc
   * saves the given position number in the player's attribute component
   */
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      $senderPosition = $sender->getPosition();
      $pos = $args['pos'];
      $posString = $senderPosition->x . ", " . $senderPosition->y . ", " . $senderPosition->z;
      $sender->getAttributes()->setAttribute("pos " . $pos, $senderPosition);
      $sender->sendMessage("Pos " . $pos . ": " . $posString);
    }
  }
}