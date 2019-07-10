<?php

ini_set( 'display_errors', true );
define( 'START_TIME', microtime( true ) );

define( 'DIR', __DIR__. DIRECTORY_SEPARATOR );

require_once( DIR. 'engine.php' );

$minifier = new MagmaCss\Engine( DIR. 'tmp/', true );

// $file = $minifier->js( [ DIR. 'js/babel.js' ], '1' );
$file = $minifier->go( DIR. 'css/test.mgcss' );

readfile( DIR. 'tmp/'. $file );

echo "\n". (microtime(true) - START_TIME). 'ms';