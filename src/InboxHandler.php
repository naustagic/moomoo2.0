<?php

namespace RPurinton\moomoo;

//require_once(__DIR__ . "/SqlClient.php");
require_once(__DIR__ . "/BunnyAsyncClient.php");

class InboxHandler extends ConfigLoader
{
	//private $sql = null;
	private $bunny = null;

	function __construct()
	{
		parent::__construct();
		//$this->sql = new SqlClient();
		$loop = \React\EventLoop\Loop::get();
		$this->bunny = new BunnyAsyncClient($loop, "moomoo_inbox", $this->process(...));
		$loop->run();
	}

	private function process($message)
	{
		switch ($message["t"]) {
			case "INTERACTION_CREATE":
				return $this->INTERACTION_CREATE($message["d"]);
			case "MESSAGE_CREATE":
				return $this->MESSAGE_CREATE($message["d"]);
			case "MESSAGE_UPDATE":
				return $this->MESSAGE_UPDATE($message["d"]);
			case "MESSAGE_DELETE":
				return $this->MESSAGE_DELETE($message["d"]);
		}
		return true;
	}

	private function INTERACTION_CREATE($interaction)
	{
		echo ("InboxHandler::INTERACTION_CREATE\n" . print_r($interaction, true) . "\n");
		return true;
	}

	private function MESSAGE_CREATE($message)
	{
		return true;
	}

	private function MESSAGE_UPDATE($message)
	{
		return true;
	}

	private function MESSAGE_DELETE($message)
	{
		return true;
	}

	private function reply($message, $reply)
	{
		$reply["command-reply"] = true;
		$reply["function"] = "MESSAGE_REPLY";
		$reply["reply_to"] = $message["id"];
		$reply["channel_id"] = $message["channel_id"];
		$this->bunny->publish("moomoo_outbox", $reply);
		return true;
	}
}
