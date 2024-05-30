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

class UnbanCmd extends BaseSubCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.staff");
    parent::__construct("unban", "unban player");
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
        CordHook::sendEmbed("Unban " . $args["player"], "Staff Unban: " . $sender->getName());
        UnPunishCmd::punishmentLogic($sender, $args["player"], "ban", $this->core);
      } else {
        $sender->sendMessage(TextFormat::RED . "You do not have permissions to do this command.");
      }
    } else {
      CordHook::sendEmbed("Unban " . $args["player"], "Console Unban");
      UnPunishCmd::punishmentLogic($sender, $args["player"], "ban", $this->core); // from console
    }
  }

}