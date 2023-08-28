<?php

namespace RPurinton\moomoo;

require_once(__DIR__ . "/vendor/autoload.php");

class ConfigLoader
{
	public $config = [];
	public $lang = [];
	public $commands = [];

	function __construct()
	{
		$configfiles = [];
		exec("ls " . __DIR__ . "/conf.d/*.json", $configfiles);
		foreach ($configfiles as $configfile) {
			$section = substr($configfile, 0, strpos($configfile, ".json"));
			$section = substr($section, strpos($section, "conf.d/") + 7);
			$this->config[$section] = json_decode(file_get_contents($configfile), true);
		}
		$configfiles = [];
		exec("ls " . __DIR__ . "/lang.d/*.json", $configfiles);
		foreach ($configfiles as $configfile) {
			$section = substr($configfile, 0, strpos($configfile, ".json"));
			$section = substr($section, strpos($section, "lang.d/") + 7);
			$this->lang[$section] = json_decode(file_get_contents($configfile), true);
		}
		$configfiles = [];
		exec("ls " . __DIR__ . "/commands.d/*.json", $configfiles);
		foreach ($configfiles as $configfile) {
			$section = substr($configfile, 0, strpos($configfile, ".json"));
			$section = substr($section, strpos($section, "commands.d/") + 11);
			$this->commands[$section] = json_decode(file_get_contents($configfile), true);
		}
	}
}
