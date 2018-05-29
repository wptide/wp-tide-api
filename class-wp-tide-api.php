<?php
/**
 * Plugin Name: WP Tide API
 * Plugin URI: http://xwp.co
 * Description: This is the WP Tide API plugin.
 * Version: 0.1.0
 * Author: XWP
 * Author URI: http://xwp.co
 * License: GPL2
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tide-api
 * Domain Path: languages
 * API Version: v1
 * API Namespace: tide
 *
 * @package WP_Tide_API
 *
 * Copyright (C) 2016 XWP
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * Class WP_Tide_API
 *
 * This class is responsible for setting up the autoloader of the plugin.
 *
 * NOTE: This class is outside of the WP_Tide_API namespace.
 */
class WP_Tide_API {

	/**
	 * Plugin information.
	 *
	 * @var array|bool|mixed
	 */
	private $info = array();

	/**
	 * WP_Tide_API constructor.
	 */
	public function __construct() {

		/**
		 * If not correct version of PHP, then no point in continuing.
		 */
		if ( version_compare( phpversion(), '7.1', '<' ) ) {
			if ( defined( 'WP_CLI' ) ) {
				WP_CLI::warning( $this->version_fail_text() );
			} else {
				add_action( 'admin_notices', array( $this, 'version_fail' ) );
			}
			return;
		}

		$data       = array(
			'__FILE__'     => __FILE__,
			'library_path' => 'php',
		);
		$data       = array_merge( $data, $this->parse_header_information() );
		$this->info = $this->setup_paths( $data );

		/**
		 * If paths are messed up we need to alert the admin.
		 */
		if ( empty( $this->info['base_name'] ) ) {
			add_action( 'shutdown', array( $this, 'installation_fail' ) );
			return;
		}

		/**
		 * Register the Autoloader.
		 */
		$autoloader_path = $this->info['include_dir'] . 'class-autoloader.php';
		if ( is_readable( $autoloader_path ) ) {
			require_once $autoloader_path;
			$autoloader = 'WP_Tide_API\Autoloader';
			$autoloader = new $autoloader();
			$autoloader->register( $this->info['include_dir'] );
		}

		/**
		 * Load the plugin's text domain.
		 */
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Admin notice for incorrect PHP version.
	 */
	public function version_fail() {
		printf( '<div class="error"><p>%s</p></div>', esc_html( $this->version_fail_text() ) );
	}

	/**
	 * Version failure error message
	 *
	 * @return string
	 */
	private function version_fail_text() {
		return __( 'WP Tide API plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.3 or higher.', 'tide-api' );
	}

	/**
	 * Paths not correctly setup.
	 */
	public function installation_fail() {
		// Translators: This can't be translated if the plugin has an installation failure.
		$message      = esc_html( sprintf( '%s has not been properly installed. Please remove the plugin and try reinstalling.', 'WP Tide API' ) );
		$html_message = sprintf( '<div class="error">%s</div>', wpautop( $message ) );

		echo wp_kses_post( $html_message );
	}

	/**
	 * Load the plugin's text domain.
	 *
	 * Look for wp-tide-api-<locale>.mo file and load it.
	 *
	 * e.g. wp-tide-api-en_US.mo
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'tide-api', false, $this->info['languages_dir'] );
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {
		return;
	}

	/**
	 * Prevent unserializing
	 */
	private function __wakeup() {
		return;
	}

	/**
	 * Parse file header information into plugin $info.
	 */
	private function parse_header_information() {
		$default_headers = array(
			'name'          => 'Plugin Name',
			'plugin_uri'    => 'Plugin URI',
			'version'       => 'Version',
			'description'   => 'Description',
			'author'        => 'Author',
			'author_uri'    => 'Author URI',
			'text_domain'   => 'Text Domain',
			'domain_path'   => 'Domain Path',
			'network'       => 'Network',
			'api_version'   => 'API Version',
			'api_namespace' => 'API Namespace',
		);

		return get_file_data( __FILE__, $default_headers, 'plugin' );
	}

	/**
	 * Get plugin locations and paths.
	 *
	 * @param array $data Plugin information.
	 *
	 * @return bool|mixed
	 */
	private function setup_paths( $data ) {
		$data['plugin_dir']    = plugin_dir_path( __FILE__ );
		$data['plugin_url']    = plugins_url( '/', __FILE__ );
		$data['base_name']     = dirname( plugin_basename( __FILE__ ) );
		$data['include_dir']   = $data['plugin_dir'] . $data['library_path'] . DIRECTORY_SEPARATOR;
		$data['include_url']   = $data['plugin_url'] . $data['library_path'] . DIRECTORY_SEPARATOR;
		$data['languages_dir'] = $data['plugin_dir'] . trim( $data['domain_path'], '/' ) . DIRECTORY_SEPARATOR;
		$data['languages_url'] = $data['plugin_url'] . trim( $data['domain_path'], '/' ) . DIRECTORY_SEPARATOR;

		return $data;
	}

	/**
	 * Create the primary plugin object.
	 */
	public function launch_plugin() {
		/**
		 * Create core plugin object.
		 */
		global $wp_tide_api_plugin;
		$core               = 'WP_Tide_API\Plugin';
		$wp_tide_api_plugin = new $core( $this->info );
	}
}

require __DIR__ . '/vendor/autoload.php';

/**
 * LAUNCH!
 */
$wp_tide_api_plugin_bootstrap = new WP_Tide_API();
$wp_tide_api_plugin_bootstrap->launch_plugin();
