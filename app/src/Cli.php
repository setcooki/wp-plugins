<?php

namespace Setcooki\Wp\Plugin\Installer;

use Psr\Log\LogLevel;

/**
 * Class Cli
 * @package Setcooki\Wp\Plugin\Installer
 */
class Cli
{
    /**
     * @var array
     */
    protected $globals = [];

    /**
     * @var array
     */
    protected $flags = [];

    /**
     * @var null
     */
    static protected $_instance = null;


    /**
     * Cli constructor.
     * @param array $globals
     * @param array $flags
     */
    protected function __construct(Array $globals = [], Array $flags = [])
    {
        $this->globals = $globals;
        $this->flags = $flags;
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
     * @param array $flags
     * @return null
     */
    public static function instance(Array $globals = [], Array $flags = [])
    {
        if(static::$_instance === null)
        {
            static::$_instance = new static($globals, $flags);
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
        $cmd[] = sprintf('-d memory_limit="%s"', ((array_key_exists('memory-limit', $this->flags) && !empty($this->flags['memory-limit'])) ? $this->flags['memory-limit'] : '512m'));
        $cmd[] = sprintf('-d max_execution_time="%d"', ((array_key_exists('max-execution-time', $this->flags)) ? (int)$this->flags['max-execution-time'] : 120));
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