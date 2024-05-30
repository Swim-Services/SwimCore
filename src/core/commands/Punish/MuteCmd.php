<?php

namespace core\commands\punish;

use core\SwimCore;
use core\systems\player\SwimPlayer;
use core\utils\cordhook\CordHook;
use core\utils\TargetArgument;
use CortexPE\Commando\args\IntegerArgument;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class MuteCmd extends BaseSubCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.staff");
    parent::__construct("mute", "mute player");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", false));
    $this->registerArgument(1, new IntegerArgument("severity", false));
    $this->registerArgument(2, new TextArgument("reason", true));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if ($rank >= 9) {
        CordHook::sendEmbed("Muted " . $args["player"] . " | Reason: " . $args["reason"] . " | Severity: " . $args["severity"], "Staff Mute: " . $sender->getName());
        PunishCmd::punishmentLogic($sender, "mute", $args, $this->core);
      } else {
        $sender->sendMessage(TextFormat::RED . "You do not have permissions to do this command.");
      }
    } else {
      CordHook::sendEmbed("Muted " . $args["player"] . " | Reason: " . $args["reason"] . " | Severity: " . $args["severity"], "Console Mute");
      PunishCmd::punishmentLogic($sender, "mute", $args, $this->core); // from console
    }
  }

}