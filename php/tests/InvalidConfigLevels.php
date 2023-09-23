<?php
namespace iniobject\tests;

class ConfigLevel3 {
	public string $name = '';
}

class ConfigLevel2 {
	public string $name = '';
	public ConfigLevel3 $config;
}

class ConfigLevel1 {
	public string $name = '';
	public ConfigLevel2 $config;
}

class InvalidConfigLevels {
	public string $name = '';
	public ConfigLevel1 $config;
}
