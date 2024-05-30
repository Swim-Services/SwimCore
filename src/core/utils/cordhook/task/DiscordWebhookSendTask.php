<?php

namespace core\utils\cordhook\task;

use pocketmine\scheduler\AsyncTask;
use core\utils\cordhook\Webhook;
use core\utils\cordhook\Message;
use pocketmine\Server;

class DiscordWebhookSendTask extends AsyncTask
{

  protected string $webhookData;
  protected string $messageData;

  public function __construct(Webhook $webhook, Message $message)
  {
    $this->webhookData = json_encode(['url' => $webhook->getURL()]);
    $this->messageData = json_encode($message->toArray());
  }

  public function onRun(): void
  {
    $webhookData = json_decode($this->webhookData, true);
    $messageData = json_decode($this->messageData, true);

    $webhook = new Webhook($webhookData['url']);
    $message = Message::fromArray($messageData);

    $ch = curl_init($webhook->getURL());
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    $this->setResult([curl_exec($ch), curl_getinfo($ch, CURLINFO_RESPONSE_CODE)]);
    curl_close($ch);
  }

  public function onCompletion(): void
  {
    $response = $this->getResult();
    if (!in_array($response[1], [200, 204])) {
      Server::getInstance()->getLogger()->error("Error: ($response[1]): " . $response[0]);
    }
  }

}
