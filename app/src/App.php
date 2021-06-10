<?php

namespace Setcooki\Wp\Plugin\Installer;

use Psr\Log\LogLevel;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Class App
 * @package Setcooki\Wp\Plugin\Installer
 */
class App
{
    /**
     * @var array
     */
    public $args = [];

    /**
     * @var bool|null|string
     */
    public $config = null;

    /**
     * @var null|string
     */
    public $path = null;

    /**
     * @var mixed|null
     */
    public $url = null;

    /**
     * @var bool
     */
    public $debug = false;

    /**
     * @var bool
     */
    public $allowRoot = false;

    /**
     * @var array
     */
    public $ignore = [];

    /**
     * @var array
     */
    protected $plugins = [];

    /**
     * @var array
     */
    protected $pluginNames = [];

    /**
     * @var array
     */
    protected $foundPlugins = [];

    /**
     * @var null
     */
    public static $cli = null;


    /**
     * App constructor.
     */
    public function __construct()
    {
        global $argc, $argv;

        $args = [];
        for ($i = 0; $i < $argc; $i++)
        {
            if(preg_match('=^--([a-z\-]{1,})(?:(?:\=)(.*))?$=i', $argv[$i], $m))
            {
                if(isset($m[2]) && !empty($m[2]))
                {
                    $args[strtolower(trim($m[1]))] = trim($m[2]);
                }else{
                    $args[strtolower(trim($m[1]))] = null;
                }
            }
        }
        if(array_key_exists('allow-root', $args))
        {
            $this->allowRoot = true;
        }
        if(array_key_exists('debug', $args))
        {
            $this->debug = true;
        }
        if(array_key_exists('ignore', $args))
        {
            $this->ignore = preg_split('=\s*\,\s*=i', trim($args['ignore'], ' ,'));
        }
        if(!array_key_exists('config', $args))
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "Missing argument config file --config");
            exit(1);
        }
        if(!array_key_exists('url', $args))
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "Missing argument wordpress url --url");
            exit(1);
        }
        if(!array_key_exists('path', $args))
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "Missing argument wordpress path --path");
            exit(1);
        }
        if(!is_file($args['config']) || ($args['config'] = realpath($args['config'])) === false)
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "Argument config file --config={$args['config']} could not be resolved");
            exit(1);
        }
        if(!empty($args['path']))
        {
            if(is_dir($args['path']))
            {
                $args['path'] = rtrim($args['path'], DIRECTORY_SEPARATOR);
            }else{
                $GLOBALS['logger']->log(LogLevel::ERROR, "Argument wordpress path --path={$args['path']} could not be resolved");
                exit(1);
            }
        }else{
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
        if($this->allowRoot)
        {
            $globals['--allow-root'] = null;
        }
        $flags =
        [
            '--memory-limit' => ((array_key_exists('memory-limit', $args)) ? $args['memory-limit'] : '512m'),
            '--max-execution-time' => ((array_key_exists('max-execution-time', $args)) ? (int)$args['max-execution-time'] : 120),
        ];

        static::$cli = Cli::instance($globals, $flags);

        $this->init();
    }


    /**
     *
     */
    protected function init()
    {
        if(defined('ABSPATH') && !function_exists( 'get_plugins'))
        {
            define('SHORTINIT', false);
            define('WP_USE_THEMES', false);
            require_once ABSPATH . 'wp-load.php';
        }
        if(defined('ABSPATH') && !function_exists('get_plugin_data'))
        {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if(function_exists('get_plugins') && function_exists('plugin_basename'))
        {
            $this->foundPlugins = array_map(function($file)
            {
                $parts = explode(DIRECTORY_SEPARATOR, $file);
                return array_shift($parts);
            }, array_keys(get_plugins()));
        }
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
            foreach($yaml as &$item)
            {
                $i++;
                if(is_array($item))
                {
                    $item = (object)$item;
                }
                if(isset($item->name) && !empty($item->name))
                {
                    $this->pluginNames[] = $item->name;
                    $this->plugins[$this->slugify($item->name)] = $item;
                }else if(isset($item->slug) && !empty($item->slug)){
                    $this->pluginNames[] = $item->slug;
                    $this->plugins[$this->slugify($item->slug)] = $item;
                }else{
                    $GLOBALS['logger']->log(LogLevel::WARNING, "item at index: $i has no name or slug and will be skipped");
                    continue;
                }
                if(isset($item->location) && !empty($item->location))
                {
                    if(($location = $this->probeFile($item->location)) !== false)
                    {
                        $item->location = $location;
                    }else{
                        $GLOBALS['logger']->log(LogLevel::WARNING, "item at index: $i has a invalid (not found) location and will be skipped");
                        continue;
                    }
                }
                if(!isset($item->version) || $item->version === '' || $item->version === null)
                {
                    $GLOBALS['logger']->log(LogLevel::WARNING, "item at index: $i has no version defined and will be skipped");
                    continue;
                }
                if(!isset($item->status) || $item->status === '' || $item->status === null)
                {
                    $GLOBALS['logger']->log(LogLevel::WARNING, "item at index: $i has no status defined and will be skipped");
                    continue;
                }
                if(isset($item->skip) && !empty($item->skip))
                {
                    $skip = preg_split('=\s*\,\s*=i', trim($item->skip));
                    foreach((array)$skip as $s)
                    {
                        if(preg_match(sprintf('=%s=i', $s), $this->url))
                        {
                            $GLOBALS['logger']->log(LogLevel::WARNING, "item at index: $i satisfies url skip rule and will be skipped");
                            continue 2;
                        }
                    }
                }
                $items[] = $item;
            }

            if(sizeof($items) > 0)
            {
                $GLOBALS['logger']->log(LogLevel::NOTICE, "< sync config against installed plugins");
                foreach($items as $item)
                {
                    $this->install($item);
                }
                $GLOBALS['logger']->log(LogLevel::NOTICE, "> sync installed plugins against config");
                $this->uninstall();
            }
        }
        catch(ParseException $e)
        {
            $GLOBALS['logger']->log(LogLevel::ERROR, "config file --config={$this->args['config']} could not be read: " . $e->getMessage());
            exit(0);
        }
    }


    /**
     * @param $item
     */
    protected function install($item)
    {
        $GLOBALS['logger']->log(LogLevel::NOTICE, "processing plugin: {$item->name}");

        $status = (int)$item->status;
        $plugin = static::$cli->exec(['plugin get %s', $item->name], ['--quiet'], $return, false, true);

        if(empty($plugin))
        {
            $plugin = (array)get_plugin_data( WP_PLUGIN_DIR . '/' . $item->name);
        }

        //not installed yet
        if(empty($plugin))
        {
            $GLOBALS['logger']->log(LogLevel::NOTICE, "install plugin: {$item->name} ({$item->version})");
            if((int)$item->status === -1)
            {
                static::$cli->exec(['plugin install %s', ((isset($item->location) && !empty($item->location)) ? $item->location : $item->name)], ["--version={$item->version}"], $return, true);
            }else if((int)$item->status === 1){
                static::$cli->exec(['plugin install %s', ((isset($item->location) && !empty($item->location)) ? $item->location : $item->name)], ["--version={$item->version}", "--activate"], $return, true);
            }
        //is already installed
        } else if(is_array($plugin)){
            $plugin = $this->parsePluginInfo($plugin);
            if((array_key_exists('version', $plugin) && $plugin['version'] != $item->version) || !array_key_exists('version', $plugin))
            {
                if((isset($item->location) && !empty($item->location)) || !array_key_exists('version', $plugin))
                {
                    $GLOBALS['logger']->log(LogLevel::NOTICE, "installing plugin: {$item->name} ({$item->version})");
                    static::$cli->exec(['plugin install %s', ((isset($item->location) && !empty($item->location)) ? $item->location : $item->name)], ["--version={$item->version}", "--force", "--activate"], $return, true);
                }else{
                    $GLOBALS['logger']->log(LogLevel::NOTICE, "updating plugin: {$item->name} from: {$plugin['version']} to: {$item->version}");
                    static::$cli->exec(['plugin update %s', $item->name], ["--version={$item->version}"], $return, true);
                }
            }else if($status === -1 && $plugin['status'] === 'active'){
                static::$cli->exec(['plugin deactivate %s', $item->name]);
            }else if($status === 1 && $plugin['status'] === 'inactive'){
                static::$cli->exec(['plugin activate %s', $item->name]);
            }
        }else{
            $GLOBALS['logger']->log(LogLevel::NOTICE, "unable to install/update plugin: {$item->name} since no plugin data found");
        }
        if(isset($item->init) && !empty($item->init))
        {
            foreach((array)preg_split('=\s?\|\s?=i', trim($item->init)) as $cmd)
            {
                $cmd = preg_replace('=^(.*wp(-cli\.phar)?)?=i', '', trim($cmd));
                $GLOBALS['logger']->log(LogLevel::NOTICE, "running init cmd: {$cmd} for plugin: {$item->name}");
                static::$cli->exec(['%s', $cmd]);
            }
        }
    }


    /**
     *
     */
    protected function uninstall()
    {
        $plugins = static::$cli->exec('plugin list', ['--format=csv', '--quiet'], $return, false, true);
        if(!empty($plugins))
        {
            foreach((array)$plugins as $plugin)
            {
                if(preg_match('=^(([^,]{1,})\,){3,}=i', $plugin))
                {
                    $plugin = str_getcsv($plugin);
                    if(isset($plugin[0]) && !empty($plugin[0]) && preg_match('=^([a-z0-9\.\-\_]{1,})$=i', $plugin[0]))
                    {
                        $e = 0;
                        $slug =  $this->slugify($plugin[0]);
                        if(strcasecmp($plugin[0], 'name') === 0)
                        {
                            continue;
                        }
                        if(strcasecmp($plugin[1], 'must-use') === 0)
                        {
                            continue;
                        }
                        if(strcasecmp($plugin[1], 'dropin') === 0)
                        {
                            continue;
                        }
                        if(!empty($this->ignore) && preg_match('=^('.implode('|', $this->ignore).')$=i', $plugin[0]))
                        {
                            continue;
                        }
                        if(!empty($this->ignore) && preg_match('=^('.implode('|', $this->ignore).')$=i', $slug))
                        {
                            continue;
                        }
                        if(array_key_exists($slug, $this->plugins))
                        {
                            $e++;
                        }
                        if(in_array($plugin[0], $this->pluginNames))
                        {
                            $e++;
                        }
                        if($e === 0)
                        {
                            $GLOBALS['logger']->log(LogLevel::NOTICE, "uninstall plugin: $plugin[0]");
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
     * @param $name
     * @return null|string|string[]
     */
    protected function slugify($name)
    {
        $name = strtolower(trim($name, ' -'));
        $name = preg_replace("/[\/_|+ -]+/", '-', $name);
        return $name;
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
            $i = (array)preg_split("=\s+=i", $i);
            if(isset($i[0]) && !empty($i[0]))
            {
                if(isset($i[1]))
                {
                    $tmp[trim($i[0])] = trim($i[1]);
                }else{
                    $tmp[trim($i[0])] = null;
                }
            }
        }
        return $tmp;
    }
}