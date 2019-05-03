<?php

global $argc, $argv;

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

if(!defined('DIRECTORY_SEPARATOR'))
{
    define('DIRECTORY_SEPARATOR', ((isset($_ENV['OS']) && strpos('win', $_ENV['OS']) !== false) ? '\\' : '/'));
}
if(preg_match('=\-\-debug=i', implode(' ', $argv)))
{
    error_reporting(E_ALL | E_STRICT);
    ini_set('display_errors', '1');
}
if(preg_match('=\-\-path\=([^\s]+)=i', implode(' ', $argv), $m))
{
    define('ABSPATH', realpath(rtrim($m[1], ' \\/')) . DIRECTORY_SEPARATOR);
}

if(!defined('ABSPATH'))
{
    if(isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT']))
    {
        define('ABSPATH', rtrim($_SERVER['DOCUMENT_ROOT'], ' /\\') . DIRECTORY_SEPARATOR);
    }else if(isset($_SERVER['SCRIPT_FILENAME']) && !empty($_SERVER['SCRIPT_FILENAME'])){
        define('ABSPATH', preg_replace('=wp-content.*=i', '', dirname($_SERVER['SCRIPT_FILENAME'])));
    }
}
if(!defined('WPINC'))
{
    define('WPINC', 'wp-includes');
}

require dirname(__FILE__) . '/../vendor/autoload.php';

$GLOBALS['logger'] = new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_VERBOSE));

(new \Setcooki\Wp\Plugin\Installer\App())->run();