<?php

namespace core\commands\Unpunish;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\utils\cordhook\CordHook;
use core\utils\TargetArgument;
use CortexPE\Commando\BaseSubCommand;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class UnmuteCmd extends BaseSubCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.staff");
    parent::__construct("unmute", "unmute player");
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", false));
  }

  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if ($sender instanceof SwimPlayer) {
      $rank = $sender->getRank()->getRankLevel();
      if ($rank >= Rank::MOD_RANK) {
        CordHook::sendEmbed("Unmute " . $args["player"], "Staff Unmute: " . $sender->getName());
        UnPunishCmd::punishmentLogic($sender, $args["player"], "mute", $this->core);
      } else {
        $sender->sendMessage(TextFormat::RED . "You do not have permissions to do this command.");
      }
    } else {
      CordHook::sendEmbed("Unmute " . $args["player"], "Console Unmute");
      UnPunishCmd::punishmentLogic($sender, $args["player"], "mute", $this->core); // from console
    }
  }

}