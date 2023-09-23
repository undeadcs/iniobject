<?php
namespace iniobject\tests;

interface LogConfig {
	public function info( string $message ) : void;
}

abstract class NotifyConfig {
	public string $url;
	
	abstract public function notify( ) : void;
}

class DirmonConfig {
	public string $appName = 'directory_monitor';
	public string $version = '0.11.3';
	public array $dirs;
	public LogConfig $logs;
	public NotifyConfig $notify;
}
