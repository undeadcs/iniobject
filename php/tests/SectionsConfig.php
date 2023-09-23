<?php
namespace iniobject\tests;

/**
 * Config with sections
 */
class SectionsConfig {
	/**
	 * Application name
	 */
	public string $appName = '';
	
	/**
	 * Version
	 */
	public string $version = '';
	
	public ?ExternalApiUrls $externalApiUrls = null;
	
	/**
	 * System directories
	 */
	public ?DirsConfig $dirs = null;
	
	public ?DbConfig $db = null;
}
