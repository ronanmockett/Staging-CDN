<?php
/**
 * Plugin Name: Staging CDN
 * Plugin URI: https://wordpress.org/
 * Description: This plugin allows you to reference all media content from your live site.
 * Version: 1.0.3
 * Author: Ronan Mockett
 * Author URI: https://ronanmockett.co.uk/
 * Text Domain: stgcdn
 * License: GPL v2 or later
 * Requires PHP: 7.0
 * Tested up to: 5.3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists ('stagingCDN')){

  class stagingCDN {
        private $urls, $status, $error, $media_path, $plugin_settings, $plugin_dir;

		public static $textDomain = "stgcdn";

        //Initial plugin defaults
        private $init_plugin_settings = array(
            'check_local' => 'enabled',
        );
        
        public function __construct(){
            if ( is_admin() && !wp_doing_ajax() ) {
                add_action( 'init', array( $this, 'save_settings' ) ); //Save plugin settings
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10);
                add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
            }

            add_action( 'init', array($this, '__plugin_init') ); //Initialise plugin variables after settings have updated.
            add_filter( 'wp_get_attachment_url', array($this, 'set_attachment_url'), 10, 2 ); 
            add_filter( 'wp_get_attachment_image_src', array($this, 'set_attachment_image_src'), 10, 4 );
            add_filter( 'wp_calculate_image_srcset', array($this, 'calculate_image_srcset'), 10, 4 );
            // Final check on 'the_content' hook for any images that are output in the content and are not output via the above Wordpress image filters.
            add_filter( 'the_content', array($this, 'content_image_src' ), 10, 1 ); 
        }

        public function enqueue_scripts() {
            wp_enqueue_style( 'stgcdn_admin_styles', plugin_dir_url( __FILE__ ) . 'admin/dist/styles.css', array(), filemtime($this->plugin_dir . 'admin/dist/styles.css') );
        }

        public function __plugin_init(){
			$plugin_settings = get_option('stgcdn_settings');

            $this->plugin_dir = plugin_dir_path( __DIR__ ) . 'staging-cdn/';
            $this->plugin_settings = (!empty($plugin_settings) && is_array($plugin_settings)) ? $plugin_settings : $this->init_plugin_settings;
            $this->urls = array(
                'replacement_url' => get_option('stgcdn_replacement_url') ?? get_site_url(),
                'staging_url' =>  get_site_url(),
            );
            $this->media_path = dirname( __DIR__ , 2);
        }

        public function add_menu_page(){
            add_menu_page(
                __( 'Staging CDN', 'stgcdn' ),
                __( 'Staging CDN', 'stgcdn' ),
                'manage_options',
                'stgcdn-admin',
                array( $this, 'admin_output' ),
                'dashicons-editor-ul'
            );
        }

        public function admin_output(){ 
            extract($this->urls);
            $local_check_setting = ($this->plugin_settings['stgcdn_check_local'] === 'enabled');
            include( $this->plugin_dir . 'admin/admin_settings.php');
        }

        public function save_settings(){
            if (isset($_POST['stgcdn_save_url']) && $_POST['stgcdn_save_url'] === 'true') {
                $this->save_staging_url();
            } else if ( isset($_POST['stgcdn_save_settings']) && $_POST['stgcdn_save_settings'] === 'true' ) {
                $this->save_plugin_settings();
            } 
        }
        
        private function save_staging_url(){
            if (empty($_POST['stgcdn_new_url'])) {
                $this->settings_saved_status('failed', 'You did not enter a new url, please try again.');
                return;
            }

			$validation = $this->validate_url($_POST['stgcdn_new_url']);
			$url = $validation['url'];

			if ( !$validation['success'] ) {
				$this->settings_saved_status( 'failed', $validation['msg'] );
				return;
			}

            // Strip trailing slash if it exists and update global variable which is displayed on page load.
			$_POST['stgcdn_updated_url'] = $url = ( substr($url, -1) === '/' ? substr( $url, 0, (strlen($url)-1) ) : $url );
            update_option('stgcdn_replacement_url', $url);

            $this->settings_saved_status('success');
        }

        private function save_plugin_settings(){
            $settings = array();
            $default_args = array(
                'check_local' => NULL,
            );
            
            foreach($_POST as $key => $value) {
                if (strpos($key, 'stgcdn_') !== false ) {
                    $settings[$key] = $value !== '' ? $value : NULL;
                }
            }

            unset($settings['stgcdn_save_settings']);

            if (is_array($settings)){
                $settings = array_merge($default_args, $settings);
                update_option('stgcdn_settings', $settings);
                $this->settings_saved_status('success');
            } else {
                $this->settings_saved_status('failed', 'Something went wrong, please try again.');
            }

        }

        public function set_attachment_url($attachment_url, $post_id) : string {
            extract($this->urls);
            if ($staging_url === $replacement_url || empty($replacement_url)) {
                return $attachment_url;
            }
            //Check if media exists locally, rewrites url if image does not exist..
            return $this->rewrite_media_url( $attachment_url, $staging_url, $replacement_url );
        }

        public function set_attachment_image_src( $image, $attachment_id, $size, $icon ) {
            extract($this->urls);
            if ($staging_url === $replacement_url || empty($replacement_url) || $image === false) {
                return $image;
            }
            //Check if media exists locally.
            $image[0] = $this->rewrite_media_url( $image[0], $staging_url, $replacement_url );
            return $image;
        }

        public function calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id = 0) : array {
            extract($this->urls);
            if ($staging_url === $replacement_url || empty($replacement_url)) {
                return $sources;
            }
            // Check each image src to see if stored locally. Set all non-existing media to Staging CDN url.
            return $this->rewrite_media_srcset( $sources, $staging_url, $replacement_url );
        }

        /** 
         * Checks if media exists locally. Returns original string if it exists locally, else rewrites with staging url.
         *
         * @param string $url - Current media url.
         * @return string 
         */
        private function rewrite_media_url(string $image_url, $staging_url, $replacement_url) : string {
            $local_check_on = ($this->plugin_settings['check_local'] === 'enabled');
            $file_path = str_replace($staging_url . '/wp-content', '', $image_url);
            if ($local_check_on && file_exists( $this->media_path . $file_path )) {
                return $image_url; // Return Local path
            }
            return str_replace($staging_url, $replacement_url, $image_url);  // Return Staging CDN path
        }

        /** 
         * Checks if srcset media exists locally. Returns original array, if it exists locally, else rewrites with staging url.
         *
         * @param string $sources - Current media sources.
         * @return array $sources - Returns sources array with updated urls.
         */
        private function rewrite_media_srcset(array $sources, string $staging_url, string $replacement_url) : array {
            $local_check_on = ($this->plugin_settings['check_local'] === 'enabled');
            foreach($sources as $key => $source) {
                $file_path = str_replace($staging_url . '/wp-content', '', $source['url']);
                //If file does not exist set url for current attachment to the Staging CDN url.
                if ($local_check_on && file_exists($this->media_path . $file_path )){
                    continue;
                } else {
                    $sources[$key]['url'] = str_replace($staging_url, $replacement_url, $sources[$key]['url']);
                }
            }
            return $sources; // Return new $sources with both Local & Staging CDN paths where applicable.
        }

        private function rewrite_media_content(array $sources, string $staging_url, string $replacement_url) : array {
            $local_check_on = ($this->plugin_settings['check_local'] === 'enabled');
            foreach($sources as $key => $source) {
                $file_path = str_replace($staging_url . '/wp-content', '', $source);
                //If file does not exist set url for current attachment to the Staging CDN url.
                if ($local_check_on && file_exists($this->media_path . $file_path )){
                    continue;
                } else {
                    $sources[$key] = str_replace($staging_url, $replacement_url, $sources[$key]);
                }
            }
            return $sources; // Return new $sources with both Local & Staging CDN paths where applicable.
        }

        public function content_image_src( string $content ) : string {
            extract($this->urls);
            if ($staging_url === $replacement_url || empty($replacement_url)) {
                return $content;
            }

            $preg_match = preg_match_all("/\<img.+src\=(?:\"|\')(.+?)(?:\"|\')(?:.+?)\>/", $content, $matches);

            if ($preg_match) {
                $src_matches = $matches[1];
                $returned_srcs = $this->rewrite_media_content($src_matches, $staging_url, $replacement_url);
                foreach($returned_srcs as $index => $src) {
                    $content = str_replace($src_matches[$index], $src, $content);
                }
            }
        
            return $content;
        }

        private function settings_saved_status($status, $error = '') {
            $this->status = $status;
            if (!empty($error)) {
                $this->error = $error;
            }
        }

        /** 
         * Check url has a valid response code beteween 200 and 300.
         *
         * @param string $url = replacement_url
         * @return bool
         */
        public static function validate_url( string $url ) : array {
            $response_code = self::get_url_response_code($url);
			$response = array(
				'success' => false,
				'response_code' => null,
				'msg' => '',
				'url' => $url
			);

			if ( $response_code === 301 || $response_code === 302 ) { // If redirected then test the Url with/without appended '/'
				$test_url = ( (substr($url, -1) === '/' ) ? substr($url, 0, -1) : $url . '/' ); // 
				$response_code = self::get_url_response_code($test_url);
			}

			switch( true ) {
				case ( $response_code >= 200 && $response_code < 300 ) : 
					$response['success'] = true;
					$response['response_code'] = $response_code;
					$response['url'] = !empty( $test_url ) ? $test_url : $url;
					break;
				case ( $response_code === 301 || $response_code === 302 ) : 
					$response['response_code'] = $response_code;
					$response['msg'] = "Url redirected, please use fully resolved url. Response Code ( {$response_code} )";
					break;
				case ( $response_code === 504 ) : 
					$response['response_code'] = $response_code;
					$response['msg'] = "Could not validate url, validation timed out. Please check the url and try again.";
					break;
				default : 
					$response['response_code'] = $response_code;
					$response['msg'] = "Unexpected response code. Please check url and try again. Response Code ( {$response_code} )";
			}

			return $response;
        }

		/** 
         * Gets http response code via cURL
         *
         * @param string $url = new url / replacement_url
         * @return bool
         */
		private static function get_url_response_code( string $url ) : int {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_exec($ch);
			$cURL_error = curl_errno( $ch ); // Returns 0 if no error
			$httpcode = ( 
				$cURL_error > 0
				? ( $cURL_error === 28 ? 504 : 500 ) // If there is an error set the most suitable httpcode
				: curl_getinfo($ch, CURLINFO_HTTP_CODE) // No error, set $httpcode
			);
			curl_close($ch);
			return $httpcode;
		}

    }
}
new stagingCDN();