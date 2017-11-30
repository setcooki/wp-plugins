<?php

namespace Setcooki\Wp\Plugin\Installer;

use Psr\Log\LogLevel;

/**
 * Class Cli
 * @package Setcooki\Wp\Plugin\Installer
 */
class Cli
{
    protected $globals = [];

    static protected $_instance = null;


    /**
     * Cli constructor.
     * @param array $globals
     */
    protected function __construct(Array $globals = [])
    {
        $this->globals = $globals;
        $phar = 'phar://wp-cli.phar';

        if(empty($phar))
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "unable to find wp-cli.phar executable");
            exit(0);
        }
        if(!defined('PHP_BINARY') || (defined('PHP_BINARY') && empty(PHP_BINARY)))
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "unable to find php binary");
            exit(0);
        }
    }


    /**
     * @param array $globals
     * @return null
     */
    public static function  instance(Array $globals = [])
    {
        if(static::$_instance === null)
        {
            static::$_instance = new static($globals);
        }
        return static::$_instance;
    }


    /**
     * @param $command
     * @param null $args
     * @param string $return
     * @param bool $interactive
     * @param bool $noerror
     * @return array
     */
    public function exec($command, $args = null, &$return = '', $interactive = false, $noerror = false)
    {
        if(is_array($command))
        {
            $command = vsprintf($command[0], array_slice($command, 1));
        }
        if(!empty($args) && is_string($args))
        {
            $args = array($args);
        }

        $debug = (array_key_exists('--debug', $this->globals)) ? true : false;
        $output = [];
        $cmd = [];
        $cmd[] = PHP_BINARY;
        if((bool)$debug)
        {
            $cmd[] = '-d error_reporting="E_ALL & ~E_NOTICE"';
        }
        $cmd[] = '-d memory_limit="512M"';
        $cmd[] = '-d max_execution_time="120"';
        $cmd[] = './wp-cli.phar';
        $cmd[] = trim($command);
        if(!empty($args))
        {
            $cmd[] = implode(' ', $args);
        }
        foreach($this->globals as $k => $v)
        {
            if($v === null)
            {
                $cmd[] = trim($k);
            }else if($v === ''){
                $cmd[] = trim(sprintf('%s=""', trim($k)));
            }else{
                $cmd[] = trim(sprintf('%s="%s"', trim($k), trim($v)));
            }
        }

        if((bool)$noerror)
        {
            $cmd[] = "2>/dev/null";
        }

        $cmd = implode(' ', $cmd);
        if((bool)$debug)
        {
            $GLOBALS['logger']->log(LogLevel::NOTICE, sprintf('exec wp-cli command: %s', $cmd));
        }

        if((bool)$interactive)
        {
            system($cmd, $return);
        }else{
            exec($cmd, $output, $return);
        }

        return $output;
    }
}