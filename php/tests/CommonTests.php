<?php
namespace iniobject\tests;

require_once( __DIR__.'/ValuesConfig.php' );
require_once( __DIR__.'/ExternalApiUrls.php' );
require_once( __DIR__.'/DirsConfig.php' );
require_once( __DIR__.'/DbConfig.php' );
require_once( __DIR__.'/SectionsConfig.php' );
require_once( __DIR__.'/DirmonConfig.php' );
require_once( __DIR__.'/InvalidConfigLevels.php' );

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use iniobject\Gateway;

/**
 * Common tests
 */
class CommonTests extends TestCase {
	protected string $tmpFilename = '';
	
	public function setUp( ) : void {
		$this->tmpFilename = tempnam( __DIR__, 'test_' );
	}
	
	public function tearDown( ) : void {
		unlink( $this->tmpFilename );
	}
	
	public static function simpleProvider( ) : array {
		$valuesConfig = new ValuesConfig;
		$valuesConfig->appName = 'my-"application"';
		$valuesConfig->index = 2;
		$valuesConfig->accuracy = 0.5;
		$valuesConfig->enabled = true;
		$valuesConfig->disabled = false;
		$valuesConfig->nulled = null;
		$valuesConfig->trustedIps = [ '127.0.0.1', '192.168.0.1' ];
		$valuesConfig->untyped = 'doesnt matter';
		$valuesConfig->point = [ 'a' => 1.1, 'b' => 2.2, 'c' => 3.3 ];
		$valuesConfig->mustBeEmpty = [ ];
		$valuesConfig->SetForTesting( 'test' );
		
		$sectionsConfig = new SectionsConfig;
		$sectionsConfig->appName = 'my-"application"';
		$sectionsConfig->version = '1.2.3';
		$sectionsConfig->externalApiUrls = new ExternalApiUrls;
		$sectionsConfig->externalApiUrls->restV1 = 'http://127.0.0.1/v1';
		$sectionsConfig->externalApiUrls->restV2 = 'http://127.0.0.1/v2';
		$sectionsConfig->dirs = new DirsConfig;
		$sectionsConfig->dirs->runDir = '/run/my-application';
		$sectionsConfig->dirs->workingDir = '/var/lib/my-application';
		$sectionsConfig->dirs->logDir = '/var/log/my-application';
		$sectionsConfig->db = new DbConfig;
		$sectionsConfig->db->host = 'localhost';
		$sectionsConfig->db->dbname = 'my_app_db';
		$sectionsConfig->db->username = 'my_app_user';
		
		return [
			[ __DIR__.'/values.ini', ValuesConfig::class, $valuesConfig ],
			[ __DIR__.'/sections.ini', SectionsConfig::class, $sectionsConfig ]
		];
	}
	
	/**
	 * Two way tests
	 */
	#[ DataProvider( 'simpleProvider' ) ]
	public function testValues( string $filename, string $className, object $config ) : void {
		$gateway = new Gateway;
		$text = file_get_contents( $filename );
		$values = parse_ini_file( $filename, true, INI_SCANNER_RAW );
		
		$this->assertEquals( $config, $gateway->LoadFromFile( $filename, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromString( $text, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromArray( $values, $className ) );
		$this->assertEquals( $text, $gateway->SaveToString( $config ) );
		$this->assertTrue( $gateway->SaveToFile( $this->tmpFilename, $config ) );
		$this->assertEquals( $text, file_get_contents( $this->tmpFilename ) );
	}
	
	/**
	 * Test load with some values unavailable in config text
	 */
	public function testDefaultLoad( ) : void {
		$filename = __DIR__.'/skipped_in.ini';
		$className = DirmonConfig::class;
		$gateway = new Gateway;
		$text = file_get_contents( $filename );
		$values = parse_ini_file( $filename, true, INI_SCANNER_RAW );
		
		$config = new DirmonConfig;
		$config->appName = 'dirmon';
		$config->version = '0.11.3';
		$config->dirs = [ 'zones' => '/var/lib/dirmon/zones', 'events' => '/var/lib/dirmon/events' ];
		
		$this->assertEquals( $config, $gateway->LoadFromFile( $filename, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromString( $text, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromArray( $values, $className ) );
		
		$config->notify = new class extends NotifyConfig {
			public function notify( ) : void { }
		};
		$config->notify->url = 'unix:///dev/null';
		
		// version and notify should be saved
		$filename = __DIR__.'/skipped_out.ini';
		$text = file_get_contents( $filename );
		$this->assertEquals( $text, $gateway->SaveToString( $config ) );
		$this->assertTrue( $gateway->SaveToFile( $this->tmpFilename, $config ) );
		$this->assertEquals( $text, file_get_contents( $this->tmpFilename ) );
	}
	
	/**
	 * Levels limited at save
	 */
	public function testLevelsLimit( ) : void {
		$filename = __DIR__.'/invalid_levels.ini';
		$className = InvalidConfigLevels::class;
		$gateway = new Gateway;
		$text = file_get_contents( $filename );
		$values = parse_ini_file( $filename, true, INI_SCANNER_RAW );
		
		$config = new InvalidConfigLevels;
		$config->name = 'root';
		$config->config = new ConfigLevel1;
		$config->config->name = 'lvl1';
		
		$this->assertEquals( $config, $gateway->LoadFromFile( $filename, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromString( $text, $className ) );
		$this->assertEquals( $config, $gateway->LoadFromArray( $values, $className ) );
		
		$config->config->config = new ConfigLevel2;
		$config->config->config->name = 'lvl2';
		$config->config->config->config = new ConfigLevel3;
		$config->config->config->config->name = 'lvl3';
		
		$this->assertEquals( $text, $gateway->SaveToString( $config ) );
		$this->assertTrue( $gateway->SaveToFile( $this->tmpFilename, $config ) );
		$this->assertEquals( $text, file_get_contents( $this->tmpFilename ) );
	}
}
