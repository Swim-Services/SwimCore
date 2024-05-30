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
        $settings->setToggle('showCPS', $data[0]);
        $settings->setToggle('showScoreboard', $data[1]);
        $settings->setToggle('duelInvites', $data[2]);
        $settings->setToggle('partyInvites', $data[3]);
        $settings->setToggle('showCords', $data[4]);
        $settings->setToggle('showScoreTags', $data[5]);
        $settings->setToggle('msg', $data[6]);
        $settings->setToggle('pearl', $data[7]);
        $settings->setToggle('nhc', $data[8]);

        // day time is special because it is a dropdown of options
        $time = TimeHelper::timeIndexToRaw($data[9]);
        $settings->setToggleInt('personalTime', $time);
        // var_dump("Chose " . TimeHelper::timeIntToString($data[6]) . " Which raw is " . $time);

        $settings->updateSettings();
        $swimPlayer->sendMessage("§aSaved Settings");
      }
      return true;
    });

    $form->setTitle(TextFormat::GREEN . $swimPlayer->getName() . "'s Settings");

    // bool settings
    $form->addToggle("CPS Counter", $toggles['showCPS']);
    $form->addToggle("Show Scoreboard", $toggles['showScoreboard']);
    $form->addToggle("Allow Duel Requests", $toggles['duelInvites']);
    $form->addToggle("Allow Party Invites", $toggles['partyInvites']);
    $form->addToggle("Show Coordinates", $toggles['showCords']);
    $form->addToggle("Show Score Tags", $toggles['showScoreTags']);
    $form->addToggle("Allow Messages", $toggles['msg']);
    $form->addToggle("Animated Pearl TP", $toggles['pearl']);
    $form->addToggle("No hurt cam (camera shake must be enabled)", $toggles['nhc']);

    // misc
    // var_dump("Time raw from settings is " . $toggles['personalTime']);
    $form->addDropdown("Personal Time", ["sunrise", "day", "noon", "sunset", "midnight"], TimeHelper::getTimeIndex($toggles['personalTime']));

    $swimPlayer->sendForm($form);
  }

}