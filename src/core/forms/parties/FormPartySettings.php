<?php

namespace core\forms\parties;

use core\SwimCore;
use core\systems\party\Party;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat;

class FormPartySettings
{

  public static function partySettingsForm(SwimCore $core, SwimPlayer $player, Party $party): void
  {
    $form = new CustomForm(function (SwimPlayer $player, array $data = null) use ($core, $party) {
      if ($data === null) return;

      // apply party name if was changed
      $name = $data[0];
      $partyName = $party->getPartyName();
      $partySystem = $core->getSystemManager()->getPartySystem();

      if ($name != $partyName) {
        if ($partySystem->partyNameTaken($name)) {
          $player->sendMessage(TextFormat::RED . "We can not change the party name to this, it is already taken by another party!");
        } else {
          $party->setPartyName($name);
          $party->partyMessage(TextFormat::GREEN . "Your party name was changed to " . TextFormat::YELLOW . $name);
        }
      }

      // apply new settings
      $party->setSetting('allowDuelInvites', $data[1]);
      $party->setSetting('allowJoinRequests', $data[2]);
      $party->setSetting('openJoin', $data[3]);
      $party->setSetting('membersCanInvite', $data[4]);
      $party->setSetting('membersCanAllowJoin', $data[5]);
      $party->setSetting('membersCanQueue', $data[6]);
      $party->setSetting('membersCanDuel', $data[7]);
      $party->setSetting('membersCanAcceptDuel', $data[8]);

      // send a message to the player confirming the changes
      $player->sendMessage(TextFormat::GREEN . "Party settings have been updated.");

      // re-kit the party members with the new party control items based on the new settings
      $party->setHubKits();
    });

    $form->setTitle(TextFormat::GREEN . "Party Settings");
    $form->addInput("Party Name", $party->getPartyName(), $party->getPartyName());
    $form->addToggle("Allow Duel Requests", $party->getSetting('allowDuelInvites'));
    $form->addToggle("Allow Join Requests", $party->getSetting('allowJoinRequests'));
    $form->addToggle("Open Join", $party->getSetting('openJoin'));
    $form->addToggle("Members can Invite", $party->getSetting('membersCanInvite'));
    $form->addToggle("Members can Accept Join Invites", $party->getSetting('membersCanAllowJoin'));
    $form->addToggle("Members can Queue", $party->getSetting('membersCanQueue'));
    $form->addToggle("Members can Duel Request", $party->getSetting('membersCanDuel'));
    $form->addToggle("Members can Accept Duels", $party->getSetting('membersCanAcceptDuel'));

    $player->sendForm($form);
  }

}