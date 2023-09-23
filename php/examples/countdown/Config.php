<?php
namespace iniobject\examples\countdown;

class Config {
	/**
	 * Message to print at every cycle
	 */
	public string $message = 'message %s';
	
	/**
	 * Number of lopp iterations
	 */
	public int $iterationsNumber = 3;
	
	/**
	 * Number of seconds to sleep after message output
	 */
	public int $sleep = 1;
}
