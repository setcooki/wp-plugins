<?php

global $argc, $argv;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

if(preg_match('=\-\-debug=i', implode(' ', $argv)))
{
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', '1');
}

require dirname(__FILE__) . '/../vendor/autoload.php';

$GLOBALS['logger'] = new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_VERBOSE));

(new \Setcooki\Wp\Plugin\Installer\App())->run();