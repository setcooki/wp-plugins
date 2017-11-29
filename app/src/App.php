<?php

namespace Setcooki\Wp\Plugin\Installer;

use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Logger\ConsoleLogger;

/**
 * Class App
 * @package Setcooki\Wp\Plugin\Installer
 */
class App
{
    public $args = [];

    public $config = null;

    public $path = null;

    public $url = null;

    public $debug = false;

    protected $plugins = [];

    public static $logger = null;

    public static $cli = null;


    /**
     * App constructor.
     */
    public function __construct()
    {
        static::$logger = new ConsoleLogger(new ConsoleOutput(ConsoleOutput::VERBOSITY_VERBOSE));

        global $argc, $argv;

        $args = [];
        for ($i = 0; $i < $argc; $i++)
        {
            if(preg_match('=^--([a-z]{1,})(?:(?:\=)(.*))?$=i', $argv[$i], $m))
            {
                if(isset($m[2]) && !empty($m[2]))
                {
                    $args[strtolower(trim($m[1]))] = trim($m[2]);
                }else{
                    $args[strtolower(trim($m[1]))] = null;
                }
            }
        }

        if(array_key_exists('debug', $args))
        {
            $this->debug = true;
        }
        if(!array_key_exists('config', $args))
        {
            static::$logger->log(LogLevel::ERROR, "Missing argument config file --config");
            exit(1);
        }
        if(!array_key_exists('url', $args))
        {
            static::$logger->log(LogLevel::ERROR, "Missing argument wordpress url --url");
            exit(1);
        }
        if(!is_file($args['config']) || ($args['config'] = realpath($args['config'])) === false)
        {
            static::$logger->log(LogLevel::ERROR, "Plugin config file --config={$args['config']} could not be resolved");
            exit(1);
        }

        if(array_key_exists('path', $args) && !empty($args['path']) && !is_dir($args['path']))
        {
            static::$logger->log(LogLevel::ERROR, "Plugin argument wordpress path --path={$args['path']} could not be resolved");
            exit(1);
        }else if(array_key_exists('path', $args) && empty($args['path'])){
            $args['path'] = '';
        }

        $this->args = $args;
        $this->config = $args['config'];
        $this->path = $args['path'];
        $this->url = $args['url'];

        $globals =
        [
            '--url' => $this->url,
            '--path' => $this->path,
        ];
        if($this->debug)
        {
            $globals['--debug'] = null;
        }
        static::$cli = Cli::instance($globals);
    }


    /**
     *
     */
    public function run()
    {
        $i = -1;
        $items = [];
        try
        {
            $yaml = Yaml::parse(file_get_contents($this->config), Yaml::PARSE_OBJECT);
            foreach($yaml as $item)
            {
                $i++;
                if(is_array($item))
                {
                    $item = (object)$item;
                }
                if(isset($item->name) && !empty($item->name))
                {
                    $this->plugins[strtolower(trim($item->name))] = $item;
                }else{
                    static::$logger->log(LogLevel::WARNING, "item at index: $i has no name and will be skipped");
                    continue;
                }
                if(isset($item->location) && (!empty($item->location) && ($location = $this->probeFile($item->location)) === false))
                {
                    static::$logger->log(LogLevel::WARNING, "item at index: $i has a invalid (not found) location and will be skipped");
                    continue;
                }else{
                    $item->location = $location;
                }
                if(!isset($item->version) || empty($item->version))
                {
                    static::$logger->log(LogLevel::WARNING, "item at index: $i has no version defined and will be skipped");
                    continue;
                }
                if(!isset($item->status) || empty($item->status))
                {
                    static::$logger->log(LogLevel::WARNING, "item at index: $i has no status defined and will be skipped");
                    continue;
                }
                $items[] = $item;
            }

            if(sizeof($items) > 0)
            {
                static::$logger->log(LogLevel::NOTICE, "< sync config against installed plugins");
                foreach($items as $item)
                {
                    $this->install($item);
                    sleep(1);
                }
                static::$logger->log(LogLevel::NOTICE, "> sync installed plugins against config");
                $this->uninstall();
            }
        }
        catch(ParseException $e)
        {
            static::$logger->log(LogLevel::ERROR, "config file --config={$args['config']} could not be read: " . $e->getMessage());
            exit(0);
        }
    }


    /**
     * @param $item
     */
    protected function install($item)
    {
        $return = 0;
        $status = (int)$item->status;
        static::$cli->exec(['plugin is-installed %s', $item->name], ['--quiet'], $return);

        //not installed yet
        if((int)$return === 1)
        {
            static::$logger->log(LogLevel::NOTICE, "install plugin: {$item->name}");
            switch($status)
            {
                case -1:
                    static::$cli->exec(['plugin deactivate %s', $item->name]);
                    break;
                case 0:
                    static::$cli->exec(['plugin install %s', ((isset($item->location) && !empty($item->location)) ? $item->location : $item->name)], ["--version={$item->version}"]);
                    break;
                case 1:
                    static::$cli->exec(['plugin install %s', ((isset($item->location) && !empty($item->location)) ? $item->location : $item->name)], ["--version={$item->version}", "--activate"]);
                    break;
                default:
                    static::$logger->log(LogLevel::WARNING, "plugin status: $status of plugin: {$item->name} is not valid - skipping plugin");
                    return;
            }
        //is already installed
        }else{
            static::$logger->log(LogLevel::NOTICE, "update plugin: {$item->name}");
            $output = static::$cli->exec(['plugin get %s', $item->name], ["--quiet"]);
            $output = $this->parsePluginInfo($output);
            if(!empty($output))
            {
                if($output['version'] != $item->version)
                {
                    if(isset($item->location) && !empty($item->location))
                    {
                        static::$cli->exec(['plugin deactivate %s', $item->name], ["--uninstall"]);
                        static::$cli->exec(['plugin install "%s"', $item->location], ["--version={$item->version}", "--force", "--activate"]);
                    }else{
                        static::$cli->exec(['plugin update %s', $item->name], ["--version={$item->version}"]);
                    }
                }else if($status === -1 && $output['status'] === 'active'){
                    static::$cli->exec(['plugin deactivate %s', $item->name]);
                }else if($status === 1 && $output['status'] === 'inactive'){
                    static::$cli->exec(['plugin activate %s', $item->name]);
                }
            }
        }
    }


    /**
     *
     */
    protected function uninstall()
    {
        $plugins = static::$cli->exec('plugin list', ['--format=csv']);
        if(!empty($plugins))
        {
            foreach((array)$plugins as $plugin)
            {
                $plugin = str_getcsv($plugin);
                if(isset($plugin[0]) && !empty($plugin[0]))
                {
                    if(strcasecmp($plugin[0], 'name') === 0)
                    {
                        continue;
                    }
                    if(!array_key_exists(strtolower($plugin[0]), $this->plugins))
                    {
                        static::$logger->log(LogLevel::NOTICE, "uninstall plugin: $plugin[0]");
                        if(strtolower($plugin[1]) === 'active')
                        {
                            static::$cli->exec(['plugin deactivate %s', $plugin[0]], '--uninstall');
                        }else{
                            static::$cli->exec(['plugin uninstall %s', $plugin[0]]);
                        }
                    }
                }
            }
        }
    }


    /**
     * @param $file
     * @return bool
     */
    protected function probeFile($file)
    {
        if(preg_match('=^ftp|http(s)?\:\/\/.*=i', $file))
        {
            return $this->probeFileFromUrl($file);
        }else{
            return $this->probeFileFromDir($file);
        }
    }


    /**
     * @param $file
     * @return bool
     */
    protected function probeFileFromDir($file)
    {
        $cnt = 0;
        if(is_link($_SERVER['PHP_SELF']))
        {
            $cnt = substr_count(ltrim(readlink($_SERVER['PHP_SELF']), DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
        }

        //phar runs from symbolic link so we need to auto correct relative plugin locations
        if(preg_match('=^\/?\.\.\/.*=i', $file) && $cnt > 0)
        {
            for($i = 0; $i < $cnt; $i++)
            {
                $file = preg_replace('=^(?:\/?\.\.\/)(.*)=i', '\\1', $file);
            }
            if($file[0] !== '.' && $file[0] !== DIRECTORY_SEPARATOR)
            {
                $file = '.' . DIRECTORY_SEPARATOR . $file;
            }
        }

        //config file is from absolute path so we need to auto correct plugin path also
        if(!preg_match('=^\/?[\.]{1,2}=i', $this->config))
        {
            $file = rtrim(dirname($this->config), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($file, DIRECTORY_SEPARATOR);
        }

        if(is_file($file))
        {
            return $file;
        }
        if(is_file(dirname(__FILE__)) . $file)
        {
            return $file;
        }
        return false;
    }


    /**
     * @param $file
     * @return bool
     */
    protected function probeFileFromUrl($file)
    {
        $ch = curl_init($file);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if((int)$code == 200)
        {
            $status = $file;
        }else{
            $status = false;
        }
        @curl_close($ch);
        return $status;
    }


    /**
     * @param array|null $info
     * @return array
     */
    protected function parsePluginInfo(Array $info = null)
    {
        $tmp = [];
        foreach((array)$info as $i)
        {
            $i = preg_split("=\s+=i", $i);
            $tmp[trim($i[0])] = trim($i[1]);
        }
        return $tmp;
    }
}