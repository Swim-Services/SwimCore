<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use core\utils\TargetArgument;
use core\utils\TimeHelper;
use CortexPE\Commando\args\TextArgument;
use CortexPE\Commando\BaseCommand;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use CortexPE\Commando\exception\ArgumentOrderException;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as TF;

class TellCmd extends BaseCommand
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    $this->core = $core;
    $this->setPermission("use.all");
    parent::__construct($core, "msg", "whisper a message", ["w", "whisper", "msg"]);
  }

  /**
   * @throws ArgumentOrderException
   */
  protected function prepare(): void
  {
    $this->registerArgument(0, new TargetArgument("player", false));
    $this->registerArgument(1, new TextArgument("message", false));
  }

  // TO DO:
  // alerts to staff for each message said
  // use nickname? maybe make use nick optional arg, we might need to change nicking system to change more than just player name tag
  public function onRun(CommandSender $sender, string $aliasUsed, array $args): void
  {
    if (!isset($args["player"]) || !isset($args["message"])) {
      $sender->sendMessage(TF::RED . "Usage: /tell <player> <msg>");
      return;
    }

    $target = $this->core->getServer()->getPlayerExact($args["player"]);
    if (!$target) {
      $sender->sendMessage(TF::RED . "Could not find player " . $args["player"]);
      return;
    }

    if (!($sender instanceof SwimPlayer) || !($target instanceof SwimPlayer)) return;

    if ($sender === $target) {
      $sender->sendMessage(TF::RED . "You can not message your self!");
      return;
    }

    if (!$sender->getSettings()->getToggle("msg")) {
      $sender->sendMessage(TF::RED . "You can not message players when you have messages disabled");
      return;
    }

    if (!$target->getSettings()->getToggle("msg")) {
      $sender->sendMessage(TF::RED . "This player has messages disabled");
      return;
    }

    $chatHandler = $sender->getChatHandler();
    if ($chatHandler->getIsMuted()) {
      $sender->sendMessage(TF::YELLOW . "You are muted for: " . TF::GREEN . $chatHandler->getMuteReason());
      $sender->sendMessage(TF::YELLOW . "Expires: " . TF::GREEN . TimeHelper::formatTime($chatHandler->getUnmuteTime() - time()));
      return;
    }

    $msg = str_replace("§", "", $args["message"]);
    $target->sendMessage(TF::AQUA . "From " . $sender->getRank()->rankString() . TF::GRAY . " » " . TF::WHITE . $msg);
    $sender->sendMessage(TF::AQUA . "To " . $target->getRank()->rankString() . TF::GRAY . " » " . TF::WHITE . $msg);

    $this->staffMessage($sender, $target, $msg);

    $target->getNetworkSession()->sendDataPacket(PlaySoundPacket::create("random.orb",
      $target->getPosition()->getX(), $target->getPosition()->getY(), $target->getPosition()->getZ(), 2.0, 1.0));
  }

  private function staffMessage(SwimPlayer $sender, SwimPlayer $receiver, string $msg): void
  {
    $senderRankLevel = $sender->getRank()->getRankLevel();
    $receiverRankLevel = $receiver->getRank()->getRankLevel();
    $players = $this->core->getServer()->getOnlinePlayers();
    $message = TF::AQUA . "From: " . TF::YELLOW . $sender->getName() . TF::AQUA . " To: " . TF::YELLOW . $receiver->getName() . TF::DARK_GRAY . " | " . TF::RESET . $msg;
    foreach ($players as $player) {
      if ($player instanceof SwimPlayer) {
        // don't message self
        if ($player === $sender || $player === $receiver) continue;
        // can see other messages if their rank level is at least helper and their rank is higher than both of the messagers
        $rank = $player->getRank()->getRankLevel();
        if ($rank >= Rank::HELPER_RANK && $rank > $senderRankLevel && $rank > $receiverRankLevel) {
          $player->sendMessage($message);
        }
      }
    }
  }

}
