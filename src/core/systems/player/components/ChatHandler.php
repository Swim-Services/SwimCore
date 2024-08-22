<?php

namespace core\systems\player\components;

use core\SwimCore;
use core\systems\player\Component;
use core\systems\player\SwimPlayer;
use core\utils\Colors;
use core\utils\FilterHelper;
use core\utils\TimeHelper;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

class ChatHandler extends Component
{

  private Rank $rank;
  private bool $isMuted;
  private string $muteReason;
  private int $unmuteTime;
  private string $lastMessagedPlayerName = '';

  private static string $adMsg = TextFormat::DARK_AQUA . "Buy a rank on " .
  TextFormat::AQUA . "swim.tebex.io" .
  TextFormat::DARK_AQUA . " or boost " .
  TextFormat::LIGHT_PURPLE . "discord.gg/swim" .
  TextFormat::DARK_AQUA . " to send longer messages";

  public function __construct(SwimCore $core, SwimPlayer $swimPlayer)
  {
    parent::__construct($core, $swimPlayer);
    $this->isMuted = false;
  }

  public function init(): void
  {
    $this->rank = $this->swimPlayer->getRank();
  }

  /**
   * @param string $lastMessagedPlayerName
   */
  public function setLastMessagedPlayerName(string $lastMessagedPlayerName): void
  {
    $this->lastMessagedPlayerName = $lastMessagedPlayerName;
  }

  /**
   * @return SwimPlayer|null
   * @brief returns the last player that messaged us, used in the /reply command.
   */
  public function getLastMessagedPlayer(): ?SwimPlayer
  {
    /** @var ?SwimPlayer $player */
    $player = $this->core->getServer()->getPlayerExact($this->lastMessagedPlayerName);
    return $player ?? null;
  }

  public function setMute(string $reason, int $unmuteTime): void
  {
    $this->isMuted = true;
    $this->muteReason = $reason;
    $this->unmuteTime = $unmuteTime;
  }

  public function unMute(): void
  {
    $this->isMuted = false;
    $this->swimPlayer->sendMessage(TextFormat::GREEN . "You have been unmuted!");
  }

  public function getIsMuted(): bool
  {
    return $this->isMuted;
  }

  public function getUnmuteTime(): int|null
  {
    return $this->unmuteTime ?? null;
  }

  public function getMuteReason(): string|null
  {
    return $this->muteReason ?? null;
  }

  public function handleChat(string $message): void
  {
    if ($this->rank->getRankLevel() <= Rank::DEFAULT_RANK && strlen($message) > 80) {
      $this->swimPlayer->sendMessage(TextFormat::RED . "Your message is too long (over 80 characters)");
      $this->swimPlayer->sendMessage(self::$adMsg);
      return;
    }

    if (FilterHelper::chatFilter($message)) {
      $this->sendFormattedMessage($message, false);
    } else if (!$this->isMuted) {
      $this->sendFormattedMessage($message, true);
    } else {
      $this->sendMutedMessage();
    }
  }

  private function sendFormattedMessage(string $message, bool $broadcast): void
  {
    $noColor = str_replace("§", "", $message);
    $recolored = $this->swimPlayer->getNicks()->isNicked() ? $noColor : Colors::handleMessageColor($this->swimPlayer->getCosmetics()->getChatFormat(), $noColor);
    $formattedMessage = $this->rank->rankChatFormat($recolored);
    if ($broadcast) {
      Server::getInstance()->broadcastMessage($formattedMessage);
    } else { // false broadcast means shadow muted the message so only sending to self
      $this->swimPlayer->sendMessage($formattedMessage);
    }
  }

  private function sendMutedMessage(): void
  {
    $this->swimPlayer->sendMessage(TextFormat::YELLOW . "You are muted for: " . TextFormat::GREEN . $this->muteReason);
    $this->swimPlayer->sendMessage(TextFormat::YELLOW . "Expires: " . TextFormat::GREEN . TimeHelper::formatTime($this->unmuteTime - time()));
  }

}