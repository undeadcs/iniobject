<?php
namespace iniobject\tests;

/**
 * Values
 */
class ValuesConfig {
	/**
	 * String value
	 */
	public string $appName = '';
	
	/**
	 * Integer value
	 */
	public int $index = 0;
	
	/**
	 * Float value
	 */
	public float $accuracy = 0.0;
	
	/**
	 * Bool value
	 */
	public bool $enabled = false;
	
	/**
	 * Bool value
	 */
	public bool $disabled = false;
	
	/**
	 * Null value
	 */
	public ?int $nulled = 1;
	
	/**
	 * Array value
	 */
	public array $trustedIps = [ ];
	
	/**
	 * Untyped value
	 */
	public $untyped = '';
	
	/**
	 * Filtered value
	 */
	protected string $filtered = '';
	
	/**
	 * Indexed array
	 */
	public array $point = [ 'x' => 1.1, 'y' => 2.2 ];
	
	/**
	 * Empty if in text
	 */
	public array $mustBeEmpty = [ 1, 2, 3 ];
	
	public function SetFiltered( string $value ) : ValuesConfig {
		$this->filtered = hex2bin( str_replace( ' ', '', $value ) );
		
		return $this;
	}
	
	public function GetFiltered( ) : string {
		return join( ' ', str_split( bin2hex( $this->filtered ), 2 ) );
	}
	
	public function SetForTesting( string $value ) : void {
		$this->filtered = $value;
	}
}
