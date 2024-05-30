<?php

namespace core\tasks;

use pocketmine\Server;
use pocketmine\scheduler\Task;

class RandomMessageTask extends Task
{

  private int $index = 0;
  private array $messages;

  public function __construct()
  {
    $this->messages = array(
      "1. This message sends every few minutes, change it in RandomMessageTask.php",
      "2. This message sends every few minutes, change it in RandomMessageTask.php", // 2 of the same message just to show this iterates through
    );
  }

  public function onRun(): void
  {
    $alert = "§8[§bSWIM§8] ";
    Server::getInstance()->broadcastMessage($alert . $this->messages[$this->index]);
    $this->index++;
    if ($this->index >= count($this->messages)) {
      $this->index = 0;
    }
  }

}
