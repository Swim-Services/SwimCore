<?php

namespace core\forms\hub;

use core\systems\player\SwimPlayer;
use core\utils\TimeHelper;
use jojoe77777\FormAPI\CustomForm;
use pocketmine\utils\TextFormat;

class FormSettings
{

  public static function settingsForm(SwimPlayer $swimPlayer): void
  {
    // get toggle settings
    $settings = $swimPlayer->getSettings();
    $toggles = $settings->getToggles();

    $form = new CustomForm(function (SwimPlayer $swimPlayer, array $data = null) use ($settings) {
      if ($data !== null) {

        // bool settings
        $index = -1;
        $settings->setToggle('showCPS', $data[++$index]);
        $settings->setToggle('nhc', $data[++$index]);
        $settings->setToggle('sprint', $data[++$index]);
        $settings->setToggle('showScoreboard', $data[++$index]);
        $settings->setToggle('dc', $data[++$index]);
        $settings->setToggle('duelInvites', $data[++$index]);
        $settings->setToggle('partyInvites', $data[++$index]);
        $settings->setToggle('showCords', $data[++$index]);
        $settings->setToggle('showScoreTags', $data[++$index]);
        $settings->setToggle('msg', $data[++$index]);
        $settings->setToggle('pearl', $data[++$index]);

        // day time is special because it is a dropdown of options
        $time = TimeHelper::timeIndexToRaw($data[++$index]);
        $settings->setToggleInt('personalTime', $time);

        $settings->updateSettings();
        $swimPlayer->sendMessage("§aSaved Settings");
      }
      return true;
    });

    $form->setTitle(TextFormat::GREEN . $swimPlayer->getName() . "'s Settings");

    // bool settings
    $form->addToggle("CPS Counter", $toggles['showCPS']);
    $form->addToggle("No hurt cam (camera shake must be enabled)", $toggles['nhc']);
    $form->addToggle("Auto Sprint", $toggles['sprint']);
    $form->addToggle("Show Scoreboard", $toggles['showScoreboard']);
    $form->addToggle("DC Prevent", $toggles['dc']);
    $form->addToggle("Allow Duel Requests", $toggles['duelInvites']);
    $form->addToggle("Allow Party Invites", $toggles['partyInvites']);
    $form->addToggle("Show Coordinates", $toggles['showCords']);
    $form->addToggle("Show Score Tags", $toggles['showScoreTags']);
    $form->addToggle("Allow Messages", $toggles['msg']);
    $form->addToggle("Animated Pearl TP", $toggles['pearl']);

    // misc
    $form->addDropdown("Personal Time", ["sunrise", "day", "noon", "sunset", "midnight"], TimeHelper::getTimeIndex($toggles['personalTime']));

    $swimPlayer->sendForm($form);
  }

}