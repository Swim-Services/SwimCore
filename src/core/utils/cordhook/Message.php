<?php

namespace core\utils\cordhook;

use JsonSerializable;
use ReturnTypeWillChange;

class Message implements JsonSerializable
{

  protected array $data = [];

  public function setContent(string $content): void
  {
    $this->data["content"] = $content;
  }

  public function getContent(): ?string
  {
    return $this->data["content"];
  }

  public function getUsername(): ?string
  {
    return $this->data["username"];
  }

  public function setUsername(string $username): void
  {
    $this->data["username"] = $username;
  }

  public function getAvatarURL(): ?string
  {
    return $this->data["avatar_url"];
  }

  public function setAvatarURL(string $avatarURL): void
  {
    $this->data["avatar_url"] = $avatarURL;
  }

  public function addEmbed(Embed $embed): void
  {
    if (!empty(($arr = $embed->asArray()))) {
      $this->data["embeds"][] = $arr;
    }
  }

  public function setTextToSpeech(bool $ttsEnabled): void
  {
    $this->data["tts"] = $ttsEnabled;
  }

  #[ReturnTypeWillChange]
  public function jsonSerialize()
  {
    return $this->data;
  }

  public function toArray(): array
  {
    return $this->data;
  }

  public static function fromArray(array $data): self
  {
    $message = new self();
    $message->data = $data;
    return $message;
  }

}
