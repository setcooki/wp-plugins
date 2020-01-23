WP-PLUGINS
======

###wp-cli based plugins auto deploy manager

[WP-CLI](https://wp-cli.org/) is the command-line interface for [WordPress](https://wordpress.org/). You can update plugins, configure multisite installs and much more, without using a web browser.

Quick links: [Installing](#installing) &#124; [Using](#using) &#124; [Support](#support)

## Using

WP-PLUGINS provides a command-line interface to auto deploy and manage plugins in wordpress installation. The deployment configuration is managed by a yaml config file,
The auto deployment is triggered by calling the following php phar:

```bash
$ php wp-plugins.phar --config=/path/to/your/config.yml --path --url
```

Its recommendable to use the wp-cli optional arguments `--path` and `--url` so wp-cli will find your wordpress install.

The auto deploy will either install, activate, deactivate or uninstall the plugins currently present in wordpress install - After deploy the plugins in your wordpress install
are in sync with your config file.

the config yml file structure looks like the following

```yml
- name: "Wordpress Importer"
  location: ""
  version: "0.6.3"
  status: 1
- slug: "advanced-custom-fields-pro"
  location: "./wp-plugins/advanced-custom-fields-pro.5.7.1.zip"
  version: "5.7.1"
  status: 1
- name: "bbpress"
  location: ""
  version: "2.5.14"
  status: -1
  init: language plugin install bbpress de_DE
```

### Properties per item

- __name__: (string) - The name of the plugin (NOTE: either name or slug must be set)
- __slug__: (string) - The plugin slug (NOTE: either name or slug must be set)
- __location__:  (string, required) - The location/path of the plugin zip on your local filesystem, remote public filesystem or an empty string for automatic lookup in wordpress plugin repo
- __version__: (string, required) - The plugin version as stated in plugin file or wordpress repo
- __status__: (number, required) - The plugin status (-1 deactivate, 0 = do nothing, 1 = activate)
- __init__: (string) - optional wp-cli commands separated by | (NOTE: commands are expected without leading wp(-cli.phar) executable

Plugin not in the config file but installed in wordpress will be delete while auto deploying!


## Installing

Just like wp-cli download via curl from:


```bash
curl -O https://raw.githubusercontent.com/setcooki/wp-plugins/master/dist/wp-plugins.phar
```

## Support

https://github.com/setcooki/wp-plugins/issues