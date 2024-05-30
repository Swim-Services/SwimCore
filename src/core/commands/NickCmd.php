<?php

namespace core\commands;

use core\SwimCore;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;

class NickCmd extends Command
{

  private SwimCore $core;

  public function __construct(SwimCore $core)
  {
    parent::__construct("nick", "type clear or reset if you want to reset your nick. MVP ranks can type anything else to set a specific nick");
    $this->core = $core;
    $this->setUsage("nick, clear|reset");
    $this->setPermission("use.all");
  }

  public function execute(CommandSender $sender, string $commandLabel, array $args): bool
  {
    if ($sender instanceof SwimPlayer) {
      $sceneName = strtolower($sender->getSceneHelper()->getScene()->getSceneName());
      // this should only be done in the hub or hubparty scenes
      if ($sceneName === "hub" || $sceneName === "hubparty") {
        $rankLevel = $sender->getRank()->getRanklevel();
        if ($rankLevel < 1) {
          $sender->sendMessage(TextFormat::YELLOW . "You do not have perms to set your nick name! Buy a rank at "
            . TextFormat::GREEN . "swim.tebex.io " . TextFormat::YELLOW . "or boost " . TextFormat::LIGHT_PURPLE . "discord.gg/swim");
        } else {
          // if we have perms then check if clearing or generating a new name tag
          if (isset($args[0]) && ($args[0] == 'clear' || $args[0] == 'reset')) { // resetting
            $sender->getNicks()->resetNick();
            // $sender->getCosmetics()->tagNameTag();
            $sender->genericNameTagHandling();
            $sender->sendMessage(TextFormat::GREEN . "Reset your nickname back to your real name!");
            $this->staffAlert($sender);
          } elseif (isset($args[0])) { // directly setting
            if ($rankLevel >= Rank::MVP) {
              $name = $args[0];
              $lower = strtolower($name);

              // length check
              if (strlen($name) > 12) {
                $sender->sendMessage(TextFormat::RED . "That nick is too long!");
                return false;
              }

              // check legality
              $disallowedNames = ["swedeachu", "gameparrot", "gxmeparrot", "swimfan"];
              foreach ($disallowedNames as $disallowedName) {
                if (str_contains($lower, $disallowedName)) {
                  $sender->sendMessage(TextFormat::RED . "You cannot nick as another player on the server");
                  return false;
                }
              }

              // check online legality
              foreach ($this->core->getServer()->getOnlinePlayers() as $player) {
                if ($player instanceof SwimPlayer && (strtolower($player->getName()) == $lower || $player->getNicks()->getNick() == $lower)) {
                  $sender->sendMessage(TextFormat::RED . "You can not nick as another player on the server");
                  return false;
                }
              }

              // set name tag
              $sender->getNicks()->setNickTo($name);
              // $sender->getCosmetics()->tagNameTag();
              $sender->genericNameTagHandling();
              $sender->setNameTag(TextFormat::GRAY . $sender->getNicks()->getNick());
              $this->staffAlert($sender);
            } else {
              $sender->sendMessage(TextFormat::RED . "You need MVP to set a specific nick: " . TextFormat::AQUA . "swim.tebex.io");
            }
          } else { // randomly setting
            $sender->getNicks()->setRandomNick();
            $sender->setNameTag(TextFormat::GRAY . $sender->getNicks()->getNick());
            $this->staffAlert($sender);
          }
        }
      } else {
        $sender->sendMessage(TextFormat::RED . "You can only use this command in the Hub!");
      }
    }
    return true;
  }

  private function staffAlert(SwimPlayer $plr): void
  {
    $level = $plr->getRank()->getRankLevel();
    $message = TextFormat::AQUA . $plr->getName() . " nicked as " . $plr->getNicks()->getNick();

    foreach ($this->core->getServer()->getOnlinePlayers() as $player) {
      if ($plr instanceof SwimPlayer) {
        // don't message self
        if ($player === $plr) continue;
        // can see other messages if their rank level is at least helper and their rank is higher than both of the messagers
        $rank = $plr->getRank()->getRankLevel();
        if ($rank >= Rank::HELPER_RANK && $rank > $level) {
          $player->sendMessage($message);
        }
      }
    }
  }

}