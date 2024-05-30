<?php

namespace core\forms\hub;

use core\SwimCore;
use core\systems\event\EventSystem;
use core\systems\event\ServerGameEvent;
use core\systems\player\components\Rank;
use core\systems\player\SwimPlayer;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\utils\TextFormat;

class FormEvents
{

  public static function eventForm(SwimCore $core, SwimPlayer $player): void
  {
    $eventSystem = $core->getSystemManager()->getEventSystem();

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($eventSystem, $core) {
      if ($data === null) {
        return; // Player closed the form
      }

      if ($data == 0) {
        // rank requirements are subject to change
        if ($player->getRank()->getRankLevel() < Rank::MEDIA_RANK) {
          $player->sendMessage(TextFormat::YELLOW . "You need to buy MVP rank to Host Events! " . TextFormat::DARK_GRAY . " | " . TextFormat::AQUA . "swim.tebex.io");
        } else {
          self::createEventsForm($player, $core, $eventSystem);
        }
      } elseif ($data == 1) {
        self::joinEventsForm($player, $core);
      }
    });

    $form->setTitle(TextFormat::GREEN . "Events");

    $form->addButton(TextFormat::GREEN . "Create Event");
    $form->addButton(TextFormat::GREEN . "Join Events " . TextFormat::DARK_GRAY
      . "[" . TextFormat::YELLOW . $eventSystem->getInQueueEventsCount() . TextFormat::DARK_GRAY . "]");

    $player->sendForm($form);
  }

  private static function joinEventsForm(SwimPlayer $swimPlayer, SwimCore $core): void
  {
    $buttons = [];

    $form = new SimpleForm(function (SwimPlayer $player, $data) use ($core, &$buttons) {
      if ($data === null) return;

      $eventNames = array_keys($buttons);
      if (!isset($eventNames[$data])) return;
      $eventName = $eventNames[$data];
      if (!isset($buttons[$eventName])) return;
      $event = $buttons[$eventName];

      if ($event instanceof ServerGameEvent) {
        if (!$event->isStarted() && !$event->isBlocked($player) && $event->canAdd()) {
          $event->addPlayer($player);
          $event->joinMessage($player);
          $sh = $player->getSceneHelper();
          $sh->setNewScene("EventQueue");
        } else {
          $player->sendMessage(TextFormat::RED . "Event no longer available to join (either started, full, or you are blocked from joining)");
        }
      }
    });

    // add the non started events to the buttons
    foreach ($core->getSystemManager()->getEventSystem()->getInQueueEvents() as $eventName => $event) {
      if ($event instanceof ServerGameEvent && !$event->isStarted()) {
        $playerCount = $event->formatPlayerCount();
        $timeToStart = $event->formatTimeToStart();
        $buttons[$eventName] = $event;
        $form->addButton($eventName . TextFormat::DARK_GRAY . " | " . $playerCount . TextFormat::DARK_GRAY . " | " . TextFormat::YELLOW . $timeToStart);
      }
    }

    $form->setTitle(TextFormat::AQUA . "Events Available to Join");

    $swimPlayer->sendForm($form);
  }

  // TO DO : Tournaments, massive wars of any mode (parties can already do this), scrims + customization for things like shop prices (toggle if free shop or not)
  private static function createEventsForm(SwimPlayer $swimPlayer, SwimCore $core, EventSystem $eventSystem): void
  {
    $form = new CustomForm(function (SwimPlayer $player, $data) use ($eventSystem, $core) {
      if ($data === null) {
        return; // Player closed the form
      }

      $mode = $data[0];

      // first mode index 0 is modded sg
      if ($mode == 0) {
        // self::moddedSGForm($player, $core, $eventSystem);
      }
    });

    $form->setTitle(TextFormat::GREEN . "Create an Event");
    $form->addDropdown("Select Event Mode", ["Modded SG"], 0);

    $swimPlayer->sendForm($form);
  }

  /* commented out, but this code shows how to do an event form, using modded sg as an example *the mode is not implemented in this lightweight engine)
  private static function moddedSGForm(SwimPlayer $swimPlayer, SwimCore $core, EventSystem $eventSystem): void
  {
    // get the available sg maps we can select from
    $maps = $core->getSystemManager()->getMapsData()->getAvailableSGMaps();
    if (empty($maps)) {
      $swimPlayer->sendMessage(TextFormat::RED . "Sorry, currently all SG maps are in use");
      return;
    }

    $form = new CustomForm(function (SwimPlayer $player, $data) use ($eventSystem, $core, &$maps) {
      if ($data === null) {
        return; // Player closed the form
      }

      // fetch needed data
      $mapIndex = $data[0]; // returns the index of the array
      $teamSize = $data[1];

      $map = array_keys($maps)[$mapIndex]; // use that index to get the map key from maps array

      // safety check
      if (!isset($maps[$map])) {
        $player->sendMessage(TextFormat::RED . "If you are seeing this, tell Swimfan he has a horrible bug in his survival games event map manager code");
        return;
      }
      $mapData = $maps[$map];

      // check if valid and not in use
      if ($mapData instanceof SurvivalGamesMapInfo && $mapData->mapIsActive()) {
        $player->sendMessage(TextFormat::RED . "Sorry, your selected SG map is currently in use");
        return;
      }

      // make the event
      $eventName = $player->getNicks()->getNick() . " | Modded SG"; // create the internal name of the event to serve as the array key in the event system
      if (!$eventSystem->eventNameExists($eventName)) {
        $mapData->setActive(true); // remember to mark as active
        $event = new SurvivalGamesEvent($core, $eventSystem, $eventName, $player, $map, 8, 24, $teamSize);
        $event->setMapInfo($mapData);
        $eventSystem->registerEvent($player, $event);
      } else {
        $player->sendMessage(TextFormat::RED . "Error: event with name '" . TextFormat::YELLOW . $eventName . TextFormat::RED . "' already exists.");
      }
    });

    $form->setTitle("Configure Modded SG");
    $form->addDropdown("Select Map", array_keys($maps), 0);
    $form->addSlider("Team Size", 1, 4);
    $swimPlayer->sendForm($form);
  }
  */

}