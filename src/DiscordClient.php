<?php

namespace RPurinton\moomoo;

use React\EventLoop\Loop;
use Discord\Discord;
use Discord\WebSockets\Intents;
use Discord\Builders\MessageBuilder;
use Discord\Parts\Interactions\Interaction;
use Discord\Parts\Interactions\Command\Command;

require_once(__DIR__ . "/BunnyAsyncClient.php");

class DiscordClient extends ConfigLoader
{

	private $loop = null;
	private $bunny = null;
	private $discord = null;
	private $guild_langs = [];

	function __construct()
	{
		parent::__construct();
		$this->loop = Loop::get();
		$this->config["discord"]["loop"] = $this->loop;
		$this->config["discord"]["intents"] = Intents::getDefaultIntents() | Intents::GUILD_MEMBERS;
		$this->config["discord"]["loadAllMembers"] = true;
		$this->discord = new \Discord\Discord($this->config["discord"]);
		$this->discord->on("ready", $this->ready(...));
		$this->discord->run();
	}

	private function ready()
	{
		$activity = new \Discord\Parts\User\Activity($this->discord);
		$activity->name = "MIR4";
		$activity->type = 0;
		$this->discord->updatePresence($activity, false, "online", false);
		$this->bunny = new BunnyAsyncClient($this->loop, "moomoo_outbox", $this->outbox(...));
		$this->discord->on("raw", $this->inbox(...));
		$this->discord->application->commands->freshen()->done(function ($cmds) {
			foreach ($cmds as $cmd) {
				$this->discord->application->commands->delete($cmd);
			};
		});
		foreach ($this->commands as $command) {
			$slashcommand = new Command($this->discord, $command);
			$this->discord->application->commands->save($slashcommand);
			$this->discord->listenCommand($command["name"], $this->interaction(...));
		}
	}

	private function interaction(Interaction $interaction)
	{
		echo ("DiscordClient::interaction()\n");
		$interaction_array = json_decode(json_encode($interaction), true);
		$interaction_array["bot_id"] = $this->discord->id;
		$interaction_array["guild_lang"] = $this->guild_langs[$interaction["guild_id"]];
		$message["t"] = "INTERACTION_CREATE";
		$message["d"] = $interaction_array;
		$this->bunny->publish("moomoo_inbox", $message);
		$lang = isset($this->lang[$interaction->locale]) ? $this->lang[$interaction->locale] : $this->lang["en-US"];
		$interaction->respondWithMessage(MessageBuilder::new()->setContent($lang["Please Wait..."]));
	}

	private function inbox($message, \Discord\Discord $discord)
	{
		print_r($message);
		$message_array = json_decode(json_encode($message), true);
		$message_array["d"]["bot_id"] = $discord->id;
		if (!is_null($message->d)) $message_array["d"]["guild_lang"] = isset($this->lang[$message->d->guild->preferred_locale]) ? $message->d->guild->preferred_locale : "en-US";
		$this->bunny->publish("moomoo_inbox", $message);
	}

	private function outbox($message)
	{
		switch ($message["function"]) {
			case "MESSAGE_CREATE":
				return $this->MESSAGE_CREATE($message);
			case "MESSAGE_REPLY":
				return $this->MESSAGE_REPLY($message);
			case "MESSAGE_UPDATE":
				return $this->MESSAGE_UPDATE($message);
			case "MESSAGE_DELETE":
				return $this->MESSAGE_DELETE($message);
			case "GET_CHANNEL":
				return $this->GET_CHANNEL($message);
		}
		return true;
	}

	private function GET_CHANNEL($message)
	{
		$response["channel"] = $this->discord->getChannel($message["channel_id"]);
		if (!is_null($response["channel"])) $response["guild"] = $response["channel"]->guild;
		$this->bunny->publish($message["queue"], $response);
		return true;
	}

	private function MESSAGE_CREATE($message)
	{
		$this->discord->getChannel($message["channel_id"])->sendMessage($this->builder($message))->then(function ($sentMessage) use ($message) {
			$message["to_id"] = $sentMessage->id;
			$this->bunny->publish("4_sent", $message);
		});
		return true;
	}

	private function MESSAGE_REPLY($message)
	{
		$this->discord->getChannel($message["channel_id"])->messages->fetch($message["reply_to"])->then(function ($originalMessage) use ($message) {
			$originalMessage->reply($this->builder($message))->then(function ($sentMessage) use ($originalMessage, $message) {
				if (!$message["command-reply"]) {
					$message["to_id"] = $sentMessage->id;
					$this->bunny->publish("4_sent", $message);
				}
			});
		});
		return true;
	}

	private function MESSAGE_UPDATE($message)
	{
		$this->discord->getChannel($message["channel_id"])->messages->fetch($message["id"])->then(function ($originalMessage) use ($message) {
			$originalMessage->edit($this->builder($message));
		});
		return true;
	}

	private function MESSAGE_DELETE($message)
	{
		$this->discord->getChannel($message["channel_id"])->messages->fetch($message["id"])->then(function ($originalMessage) {
			$originalMessage->delete();
		});
		return true;
	}

	private function builder($message)
	{
		$builder = \Discord\Builders\MessageBuilder::new();
		if (isset($message["content"])) {
			if (strlen($message["content"]) < 2000) $builder->setContent($message["content"]);
			else $builder->addFileFromContent("content.txt", $message["content"]);
		}
		if (isset($message["addFileFromContent"])) {
			foreach ($message["addFileFromContent"] as $attachment) {
				$builder->addFileFromContent($attachment["filename"], $attachment["content"]);
			}
		}
		if (isset($message["attachments"])) {
			foreach ($message["attachments"] as $attachment) {
				$embed = new \Discord\Parts\Embed\Embed($this->discord);
				$embed->setTitle($attachment["filename"]);
				$embed->setURL($attachment["url"]);
				$embed->setImage($attachment["url"]);
				$builder->addEmbed($embed);
			}
		}
		if (isset($message["embeds"])) foreach ($message["embeds"] as $old_embed) {
			if ($old_embed["type"] == "rich") {
				$new_embed = new \Discord\Parts\Embed\Embed($this->discord);
				$new_embed->fill($old_embed);
				$builder->addEmbed($new_embed);
			}
		}
		if (isset($message["mentions"])) {
			$allowed_users = array();
			foreach ($message["mentions"] as $mention) $allowed_users[] = $mention["id"];
			$allowed_mentions["parse"] = array("roles", "everyone");
			$allowed_mentions["users"] = $allowed_users;
			$builder->setAllowedMentions($allowed_mentions);
		}
		return $builder;
	}
}
