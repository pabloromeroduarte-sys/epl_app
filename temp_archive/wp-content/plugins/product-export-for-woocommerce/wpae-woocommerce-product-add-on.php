<?php
/*
Plugin Name: WP All Export - WooCommerce Product Export Add-On
Plugin URI: https://www.wpallimport.com/
Description: Drag & drop to export WooCommerce products to any CSV or XML. A paid upgrade is available for premium support, exporting advanced WooCommerce product data, and more.
Version: 1.0.5
Author: Soflyy
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin root dir with forward slashes as directory separator regardless of actuall DIRECTORY_SEPARATOR value
 * @var string
 */
define('PMWPE_ROOT_DIR', str_replace('\\', '/', dirname(__FILE__)));
/**
 * Plugin root url for referencing static content
 * @var string
 */
define('PMWPE_ROOT_URL', rtrim(plugin_dir_url(__FILE__), '/'));
/**
 * Plugin prefix for making names unique (be aware that this variable is used in conjuction with naming convention,
 * i.e. in order to change it one must not only modify this constant but also rename all constants, classes and functions which
 * names composed using this prefix)
 * @var string
 */
define('PMWPE_PREFIX', 'pmwpe_');

define('PMWPE_VERSION', '1.0.5');


define('PMWPE_EDITION', 'free');

/**
 * Main plugin file, Introduces MVC pattern
 *
 * @singletone
 * @author Maksym Tsypliakov <maksym.tsypliakov@gmail.com>
 */
final class PMWPE_Plugin
{
    /**
     * Singletone instance
     * @var PMWPE_Plugin
     */
    protected static $instance;

    /**
     * Plugin root dir
     * @var string
     */
    const ROOT_DIR = PMWPE_ROOT_DIR;
    /**
     * Plugin root URL
     * @var string
     */
    const ROOT_URL = PMWPE_ROOT_URL;
    /**
     * Prefix used for names of shortcodes, action handlers, filter functions etc.
     * @var string
     */
    const PREFIX = PMWPE_PREFIX;
    /**
     * Plugin file path
     * @var string
     */
    const FILE = __FILE__;

    /**
     * Return singletone instance
     * @return PMWPE_Plugin
     */
    static public function getInstance()
    {
        if (self::$instance == NULL) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    static public function getEddName()
    {
        return 'WooCommerce Product Export Add-On';
    }

    /**
     * Common logic for requestin plugin info fields
     */
    public function __call($method, $args)
    {
        if (preg_match('%^get(.+)%i', $method, $mtch)) {
            $info = get_plugin_data(self::FILE);
            if (isset($info[$mtch[1]])) {
                return $info[$mtch[1]];
            }
        }
        throw new Exception(esc_html("Requested method " . get_class($this) . "::$method doesn't exist."));
    }

    /**
     * Get path to plagin dir relative to wordpress root
     * @param bool [optional] $noForwardSlash Whether path should be returned withot forwarding slash
     * @return string
     */
    public function getRelativePath($noForwardSlash = false)
    {
        $wp_root = str_replace('\\', '/', ABSPATH);
        return ($noForwardSlash ? '' : '/') . str_replace($wp_root, '', self::ROOT_DIR);
    }

    /**
     * Check whether plugin is activated as network one
     * @return bool
     */
    public function isNetwork()
    {
        if (!is_multisite())
            return false;

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[plugin_basename(self::FILE)]))
            return true;

        return false;
    }

    /**
     * Class constructor containing dispatching logic
     * @param string $rootDir Plugin root dir
     * @param string $pluginFilePath Plugin main file
     */
    protected function __construct()
    {

        include_once 'src' . DIRECTORY_SEPARATOR . 'Common' . DIRECTORY_SEPARATOR . 'Bootstrap' . DIRECTORY_SEPARATOR . 'Autoloader.php';
        $autoloader = new \Pmwpe\Common\Bootstrap\Autoloader(self::ROOT_DIR, self::PREFIX);
        // create/update required database tables

        // register autoloading method
        spl_autoload_register(array($autoloader, 'autoload'));

        register_activation_hook(self::FILE, array($this, 'activation'));

        $autoloader->init();

        // register admin page pre-dispatcher
        add_action('admin_init', array($this, 'adminInit'));
        add_action('init', array($this, 'init'));

    }

    public function init()
    {

    }

    /**
     * pre-dispatching logic for admin page controllers
     */
    public function adminInit()
    {
        // Reserved for future admin initialization logic
    }

    /**
     * Dispatch shorttag: create corresponding controller instance and call its index method
     * @param array $args Shortcode tag attributes
     * @param string $content Shortcode tag content
     * @param string $tag Shortcode tag name which is being dispatched
     * @return string
     */
    public function shortcodeDispatcher($args, $content, $tag)
    {

        $controllerName = self::PREFIX . preg_replace_callback('%(^|_).%', array($this, "replace_callback"), $tag);// capitalize first letters of class name parts and add prefix
        $controller = new $controllerName();
        if (!$controller instanceof PMWPE_Controller) {
            throw new Exception(esc_html("Shortcode `$tag` matches to a wrong controller type."));
        }
        ob_start();
        $controller->index($args, $content);
        return ob_get_clean();
    }

    public function replace_callback($matches)
    {
        return strtoupper($matches[0]);
    }

    /**
     * Plugin activation logic
     */
    public function activation()
    {
        // Uncaught exception doesn't prevent plugin from being activated, therefore replace it with fatal error so it does.
        set_exception_handler(function ($e) {
            trigger_error(esc_html($e->getMessage()), E_USER_ERROR);
        });
    }
}

PMWPE_Plugin::getInstance();

// retrieve our license key from the DB
$wpae_woocommerce_addon_options = get_option('PMXI_Plugin_Options');

