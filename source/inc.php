<?php

/**
 * 
 * 
 * @author Jordan Skoblenick <parkinglotlust@gmail.com> 2015-03-10
 */

mb_internal_encoding('UTF-8');
header('Content-Type: text/html; charset=utf-8');

define('PROJECT_STATUS', 'live'); // flag to switch between development and live

define('TEMPLATES_DIR', __DIR__.'/templates/');
define('TEMPLATES_C_DIR', __DIR__.'/templates_c/');
define('CACHE_DIR', __DIR__.'/cache/');

require_once(__DIR__.'/credentials.php');

require_once(__DIR__.'/Skoba.php');

// autoload extras
spl_autoload_register(function($class) {
	$file = __DIR__."/classes/{$class}.class.php";
	if (file_exists($file)) {
		require_once($file);
	}	
});
