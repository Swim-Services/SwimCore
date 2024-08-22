<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextFormat as TF;

class ReplyCommand extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.all");
    parent::__construct($core, "r", "whisper a message", ["reply"]);
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TextArgument("message", false));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if (!isset($args["message"])) {
      $sender->sendMessage(TF::RED . "Usage: /tell <msg>");
    } else if ($sender instanceof SwimPlayer) {
      $player = $sender->getChatHandler()?->getLastMessagedPlayer() ?? null;
      if ($player) {
        $args["player"] = $player->getName();
        TellCmd::messageLogic($sender, $args);
      } else {
        $sender->sendMessage(TextFormat::RED . "You have no one to reply to!");
      }
    }
  }

}