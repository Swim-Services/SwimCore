<?php

namespace core\utils;

use pocketmine\event\entity\EntityDamageEvent;

class CustomDamage
{

  public static function customDamageHandle(EntityDamageEvent $ev, bool $critEnabled = false): void
  {
    $event = $ev;
    if ($event->getCause() != 11 && $event->getBaseDamage() != 0) {
      $crit = (($event->getModifier(EntityDamageEvent::MODIFIER_CRITICAL) != 0) && $critEnabled);

      $event->setModifier($event->getModifier(EntityDamageEvent::MODIFIER_ARMOR) * ($event->getBaseDamage() / ($event->getBaseDamage()
            + $event->getModifier(EntityDamageEvent::MODIFIER_CRITICAL))), EntityDamageEvent::MODIFIER_ARMOR); // remove armor resistance crit

      $event->setModifier(($crit ? 0.5 : 0.0), EntityDamageEvent::MODIFIER_CRITICAL); // disables crits

      if ($event->getCause() != EntityDamageEvent::CAUSE_PROJECTILE) {
        $event->setModifier(-fmod($event->getFinalDamage(), 1.0), 0); // changes by the amount to make it round down
      } else {
        $event->setModifier(1.0 - fmod($event->getFinalDamage(), 1.0), 0); // changes by the amount to make it round up
      }

      if ($event->getModifier(EntityDamageEvent::MODIFIER_ABSORPTION) != 0 && $event->getEntity()->getAbsorption() != 0) {
        $base = $event->getBaseDamage(); // get base damage, so we can calculate how much absorption should be lost
        $damageArr = $event->getModifiers(); // get modifiers so we can apply them

        if (isset($damageArr[EntityDamageEvent::MODIFIER_ARMOR]) && isset($damageArr[EntityDamageEvent::MODIFIER_CRITICAL])) {
          $damageArr[EntityDamageEvent::MODIFIER_ARMOR] = $event->getModifier(EntityDamageEvent::MODIFIER_ARMOR) * ($event->getBaseDamage()
              / ($event->getBaseDamage() + $event->getModifier(EntityDamageEvent::MODIFIER_CRITICAL))); // remove armor resistance crit from absorption calc
        }

        if (isset($damageArr[EntityDamageEvent::MODIFIER_CRITICAL])) {
          $damageArr[EntityDamageEvent::MODIFIER_CRITICAL] = ($crit ? 0.5 : 0.0); // remove crit from absorption calc
        }

        $damageArr[EntityDamageEvent::MODIFIER_ABSORPTION] = 0.0; // remove absorption mod from absorption calc

        $base += array_sum($damageArr); // apply modifications to absorption calc
        if ($event->getCause() != EntityDamageEvent::CAUSE_PROJECTILE) {
          $base = floor($base); // round down
        } else {
          $base = ceil($base);
        }

        // apply our custom absorption damage
        if ($event->getEntity()->getAbsorption() - $base > 0.0) {
          $event->getEntity()->setAbsorption($event->getEntity()->getAbsorption() - $event->getModifier(EntityDamageEvent::MODIFIER_ABSORPTION) - $base);
        } else {
          // set the absorption modifier to the absorption value when absorption will run out
          $event->setModifier(-$event->getEntity()->getAbsorption(), EntityDamageEvent::MODIFIER_ABSORPTION);
          $event->getEntity()->setAbsorption(0); // if it would be less than 0 set it to 0 to avoid crash
        }
      }
    }
  }

}