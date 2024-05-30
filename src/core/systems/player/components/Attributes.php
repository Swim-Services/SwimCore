<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;

class Attributes extends Component
{

  private array $attributes;

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer, bool $doesUpdate = false)
  {
    parent::__construct($core, $swimPlayer, $doesUpdate);
    $this->attributes = [];
  }

  public function getAttribute(string $attribute)
  {
    if (isset($this->attributes[$attribute])) {
      return $this->attributes[$attribute];
    }
    return null;
  }

  public function hasAttribute(string $attribute): bool
  {
    return isset($this->attributes[$attribute]);
  }

  public function setAttribute(string $attribute, $value): void
  {
    $this->attributes[$attribute] = $value;
  }

  // Shortcut for incrementing or decrementing an integer attribute.
  // Will set it to 1 or -1 if it does not exist yet depending on the subtraction params value.
  public function emplaceIncrementIntegerAttribute(string $attribute, bool $subtract = false): int
  {
    if (isset($this->attributes[$attribute])) {
      if (!$subtract) {
        $this->attributes[$attribute]++;
      } else {
        $this->attributes[$attribute]--;
      }
    } else {
      $this->attributes[$attribute] = $subtract ? -1 : 1;
    }
    // returns the amount this attribute is currently at
    return $this->attributes[$attribute];
  }

  public function clear(): void
  {
    $this->attributes = [];
  }

  public function removeAttribute(string $attribute): void
  {
    unset($this->attributes[$attribute]);
  }

}