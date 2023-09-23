<?php
namespace iniobject;

/**
 * Gateway for loading and saving configs
 */
class Gateway {
	/**
	 * Parse ini with sections
	 */
	protected bool $withSections = true;
	
	/**
	 * Ini parser mode
	 * raw mode is not supporting multiline strings
	 */
	protected int $scannerMode = INI_SCANNER_RAW;
	
	/**
	 * Array separator character
	 */
	protected string $arraySep = ',';
	
	/**
	 * Boolean values in string
	 */
	protected array $boolValues = [
		true => [ '1', 'true', 'on', 'yes' ],
		false => [ '0', 'false', 'off', 'no' ]
	];
	
	/**
	 * Null value for string
	 */
	protected string $nullValue = 'null';
	
	/**
	 * Load config from file
	 * 
	 * @param string $filename full file path readable
	 * @param string $className class of object to create and fill
	 * @return object|null created from class declaration or null if class is not instantiable
	 * @throws \RuntimeException if failed to access or parse file
	 */
	public function LoadFromFile( string $filename, string $className ) : ?object {
		if ( !is_file( $filename ) || !is_readable( $filename ) ) {
			throw new \RuntimeException( "file '$filename' is not file or not readable" );
		}
		
		$values = @parse_ini_file( $filename, $this->withSections, $this->scannerMode );
		if ( ( $values === false ) || !is_array( $values ) ) {
			throw new \RuntimeException( "failed to parse '$filename'" );
		}
		
		return  $this->LoadFromArray( $values, $className );
	}
	
	/**
	 * Load from string
	 * 
	 * @param string $text text of ini file
	 * @param string $className class of object to create and fill
	 * @return object|null created from class declaration or null if class is not instantiable
	 * @throws \RuntimeException if failed to access or parse file
	 */
	public function LoadFromString( string $text, string $className ) : ?object {
		$values = @parse_ini_string( $text, $this->withSections, $this->scannerMode );
		if ( ( $values === false ) || !is_array( $values ) ) {
			throw new \RuntimeException( "failed to parse string" );
		}
		
		return $this->LoadFromArray( $values, $className );
	}
	
	/**
	 * Load from array
	 * 
	 * @param array $values associative array of values
	 * @param string $className class of object to create and fill
	 * @return object|null created from class declaration or null if class is not instantiable
	 */
	public function LoadFromArray( array $values, string $className ) : ?object {
		$ref = new \ReflectionClass( $className );
		
		if ( $ref->isAbstract( ) || $ref->isInterface( ) ) { // dependency injection not supported
			return null;
		}
		
		$config = new $className;
		$fields = $ref->getProperties( );
		foreach( $fields as $field ) {
			$fieldName = $this->CamelToSnake( $field->getName( ) );
			
			if ( array_key_exists( $fieldName, $values ) ) {
				$this->ImportConfigValue( $config, $field, $values[ $fieldName ] );
			}
		}
		
		return $config;
	}
	
	/**
	 * Set config field
	 * 
	 * @param object $config object for setting field value
	 * @param \ReflectionProperty $field field description
	 * @param mixed $value input value
	 * @return void
	 */
	protected function ImportConfigValue( object $config, \ReflectionProperty $field, mixed $value ) : void {
		$setter = 'Set'.$field->getName( );
		
		if ( method_exists( $config, $setter ) ) { // customized setting
			$config->{ $setter }( $value );
			return;
		}
		if ( !$field->isPublic( ) ) { // cant set non public fields
			return;
		}
		if ( !$field->hasType( ) ) { // dont care about input value type
			$config->{ $field->getName( ) } = $value;
			return;
		}
		
		$type = $field->getType( );
		
		if ( $type->allowsNull( ) && is_string( $value ) && ( $value == $this->nullValue ) ) {
			$config->{ $field->getName( ) } = null;
			return;
		}
		if ( $type instanceof \ReflectionNamedType ) { // single type
			$this->ImportTypedValue( $config, $field->getName( ), $field, $value );
		}
	}
	
	/**
	 * Import value base on type
	 * 
	 * @param object $config object for setting fields
	 * @param string $fieldName object field name
	 * @param \ReflectionProperty $field field description
	 * @param mixed $value input value
	 * @return void
	 */
	protected function ImportTypedValue( object $config, string $fieldName, \ReflectionProperty $field, mixed $value ) : void {
		$type = $field->getType( );
		
		if ( $type->isBuiltin( ) ) { // standard type
			if ( $type->getName( ) == 'array' ) {
				$this->ImportArrayValue( $config, $fieldName, $value );
			} else if ( $type->getName( ) == 'bool' ) {
				$this->ImportBoolValue( $config, $fieldName, $value );
			} else {
				$config->$fieldName = $value;
			}
			
			return;
		}
		
		$obj = $this->LoadFromArray( $value, $type->getName( ) );
		
		if ( !is_null( $obj ) || $type->allowsNull( ) ) {
			$config->{ $fieldName } = $obj;
		}
	}
	
	/**
	 * Import array value
	 * 
	 * @param object $config object for setting fields
	 * @param string $fieldName object field name
	 * @param mixed $value input value
	 * @return void
	 */
	protected function ImportArrayValue( object $config, string $fieldName, mixed $value ) : void {
		if ( is_array( $value ) ) {
			$config->$fieldName = $value;
		} else if ( $value != '' ) {
			$config->$fieldName = array_map( 'trim', explode( $this->arraySep, $value ) ); // explode always returns array
		} else { // was set as empty in text
			$config->$fieldName = [ ];
		}
	}
	
	/**
	 * Import boolean value
	 * 
	 * @param object $config object for setting fields
	 * @param string $fieldName object field name
	 * @param mixed $value input value
	 * @return void
	 */
	protected function ImportBoolValue( object $config, string $fieldName, mixed $value ) : void {
		$config->$fieldName = in_array( $value, $this->boolValues[ true ] );
	}
	
	/**
	 * Save config to file
	 * 
	 * @param string $filename full file path writeable
	 * @param object $config object to save
	 * @return bool success of file saving
	 * @throws \RuntimeException if failed to access file
	 */
	public function SaveToFile( string $filename, object $config ) : bool {
		if ( !is_file( $filename ) || !is_writable( $filename ) ) {
			throw new \RuntimeException( "file '$filename' is not file or not writeable" );
		}
		
		return file_put_contents( $filename, $this->SaveToString( $config ) );
	}

	/**
	 * Export config to string
	 * 
	 * @param object $config object to convert
	 * @return string full text of config in ini format
	 */
	public function SaveToString( object $config ) : string {
		$lines = [ ];
		$ref = new \ReflectionClass( $config );
		
		if ( $docComment = $ref->getDocComment( ) ) { // config header
			$title = $this->FetchTitle( $docComment );
			if ( $title != '' ) {
				$lines[ ] = ";\n; ".$title;
			}
		}
		
		$this->ExportObjectToLines( $config, $ref, $lines, 1 );
		
		return join( "\n", $lines )."\n"; // blank line at the end of file
	}
	
	/**
	 * Export object to config lines
	 * 
	 * @param object $config object to export
	 * @param \ReflectionClass $ref reflection of config class
	 * @param array $lines array for saving result
	 * @param int $level level of config object
	 * @return void
	 */
	protected function ExportObjectToLines( object $config, \ReflectionClass $ref, array& $lines, int $level ) : void {
		if ( $level > 2 ) { // ini file are 2 levels only
			return;
		}
		
		$fields = $ref->getProperties( );
		foreach( $fields as $field ) {
			$fieldName = $this->CamelToSnake( $field->getName( ), false );
			$value = $this->ExportConfigValue( $config, $fieldName, $field, $lines, $level );
			if ( !is_null( $value ) ) {
				if ( $docComment = $field->getDocComment( ) ) { // field title
					$title = $this->FetchTitle( $docComment );
					if ( $title != '' ) {
						$lines[ ] = "\n; ".$title;
					}
				}
				
				$lines[ ] = trim( $fieldName.' = '.$value );
			}
		}
	}
	
	/**
	 * Export config field value
	 * 
	 * @param object $config object to export
	 * @param string $fieldName object field name
	 * @param \ReflectionProperty $field field description
	 * @param array $lines array for saving result
	 * @param int $level level of config object
	 * @return string|null string or null if skip export value
	 */
	protected function ExportConfigValue( object $config, string $fieldName, \ReflectionProperty $field, array& $lines, int $level ) : ?string {
		$getter = 'Get'.$field->getName( );
		
		if ( method_exists( $config, $getter ) ) { // customized getting
			return ( string ) $config->{ $getter }( );
		}
		if ( !$field->isPublic( ) ) {
			return null;
		}
		if ( !$field->hasType( ) ) { // without type
			return ( string ) $config->{ $field->getName( ) };
		}
		
		$type = $field->getType( );
		
		if ( $type->allowsNull( ) && is_null( $config->{ $field->getName( ) } ) ) {
			return $this->nullValue;
		}
		if ( $type instanceof \ReflectionNamedType ) {
			return $this->ExportTypedValue( $config, $fieldName, $field, $lines, $level );
		}
		
		return '';
	}
	
	/**
	 * Export value base on type
	 * 
	 * @param object $config object to export
	 * @param string $fieldName object field name
	 * @param \ReflectionProperty $field field description
	 * @param array $lines array for saving result
	 * @param int $level level of config object
	 * @return string|null string or null if skip export value
	 */
	protected function ExportTypedValue( object $config, string $fieldName, \ReflectionProperty $field, array& $lines, int $level ) : ?string {
		$type = $field->getType( );
		
		if ( $type->isBuiltin( ) ) { // standard type
			if ( $type->getName( ) == 'array' ) {
				return $this->ExportArrayValue( $fieldName, $field, $config->{ $field->getName( ) }, $lines );
			} else if ( $type->getName( ) == 'bool' ) {
				return $this->ExportBoolValue( $config->{ $field->getName( ) } );
			}
			
			return ( string ) $config->{ $field->getName( ) };
		}
		
		if ( $field->isInitialized( $config ) && ( $level < 2 ) ) {
			$obj = $config->{ $field->getName( ) };
			$ref = new \ReflectionClass( $obj );
			
			if ( ( $docComment = $field->getDocComment( ) ) || ( $docComment = $ref->getDocComment( ) ) ) { // section title
				$title = $this->FetchTitle( $docComment );
				if ( $title != '' ) {
					$lines[ ] = "\n; ".$title;
				}
			} else {
				$lines[ ] = '';
			}
			
			$lines[ ] = '['.$fieldName.']';
			$this->ExportObjectToLines( $obj, $ref, $lines, $level + 1 );
		}
		
		return null;
	}
	
	/**
	 * Export array value
	 * 
	 * @param string $fieldName object field name
	 * @param \ReflectionProperty $field field description
	 * @param array $values values to export
	 * @param array $lines array for saving result
	 * @return string|null string or null if skip export value
	 */
	protected function ExportArrayValue( string $fieldName, \ReflectionProperty $field, array $values, array& $lines ) : ?string {
		$keys = array_keys( $values );
		foreach( $keys as $key ) {
			if ( is_string( $key ) ) { // assoc
				if ( $docComment = $field->getDocComment( ) ) {
					$title = $this->FetchTitle( $docComment );
					if ( $title != '' ) {
						$lines[ ] = "\n; ".$title;
					}
				}
				
				foreach( $values as $key => $value ) { // override $key variable, anyway not used later
					$lines[ ] = $fieldName.'['.$key.'] = '.$value;
				}
				
				return null; // skip saving name
			}
		}
		
		return join( $this->arraySep.' ', $values );
	}
	
	/**
	 * Export boolean value
	 * 
	 * @param bool $value value of field
	 * @return string
	 */
	protected function ExportBoolValue( bool $value ) : string {
		return $value ? 'true' : 'false';
	}
	
	/**
	 * Fetch description of field from PHPDoc comment
	 * 
	 * @param string $docComment text of comment
	 * @return string title or empty string if not found
	 */
	protected function FetchTitle( string $docComment ) : string {
		// @todo support different comment formats
		$matches = [ ];
		
		if ( preg_match( '/\/\*\*\n(?:[\s*]+)?([^\n]+)\n/', $docComment, $matches ) ) { // fetch first line
			return preg_replace( '/^@var\s+/', '', trim( $matches[ 1 ] ) );
		}
		
		return '';
	}
	
	protected function CamelToSnake( string $value ) : string {
		$parts = [ ];
		$part = '';
		$n = strlen( $value );
		for( $i = 0; $i < $n; ++$i ) {
			$char = $value[ $i ];
			
			if ( ctype_upper( $char ) && ( $part != '' ) ) {
				$parts[ ] = $part;
				$part = '';
			}
			
			$part .= $char;
		}
		
		if ( $part != '' ) {
			$parts[ ] = $part;
		}
		
		return join( '_', array_map( 'strtolower', $parts ) );
	}
	
	protected function SnakeToCamel( string $value, bool $firstUpper = true ) : string {
		$parts = explode( '_', $value );
		$parts = array_map( 'ucfirst', $parts );
		$ret = join( '', $parts );
		
		return $firstUpper ? $ret : lcfirst( $ret );
	}
}
