<?php
namespace iniobject\examples\countdown;

require_once( __DIR__.'/../../autoload.php' );
require_once( __DIR__.'/Config.php' );

use iniobject\Gateway;

$gateway = new Gateway;

$config = $gateway->LoadFromFile( __DIR__.'/countdown.ini', Config::class );

for( $i = 0; $i < $config->iterationsNumber; ++$i ) {
	echo sprintf( $config->message, $i + 1 )."\n";
	sleep( $config->sleep );
}
