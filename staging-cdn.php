<?php
/**
 * Plugin Name: Staging CDN
 * Plugin URI: https://wordpress.org/staging-cdn
 * Description: This plugin allows you to reference all media content from your live site.
 * Version: 1.0.0
 * Author: Ronan Mockett
 * Author URI: https://ronanmockett.co.uk/
 * Text Domain: stgcdn
 * License: GPL v2 or later
 * Requires PHP: 7.0
 * Tested up to: 5.3.2
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists ('stagingCDN')){

  class stagingCDN {
        private $urls, $status, $error, $uploads_dir, $uploads_url, $plugin_settings, $plugin_dir, $check_local;

        //Initial plugin defaults
        private $init_plugin_settings = array(
            'stgcdn_check_local' => 'enabled',
        );
        
        public function __construct(){
            if ( is_admin() && !wp_doing_ajax() ) {
                add_action( 'init', array( $this, 'save_settings' ) ); //Save plugin settings
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10);
                add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
				add_filter( 'plugin_action_links_staging-cdn/staging-cdn.php', array( $this, 'add_plugin_settings_link' ) );
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
			$plugin_settings = unserialize(get_option('stgcdn_settings'));

            $this->plugin_dir = plugin_dir_path( __DIR__ ) . 'staging-cdn/';
            $this->plugin_settings = (!empty($plugin_settings) && is_array($plugin_settings)) ? $plugin_settings : $this->init_plugin_settings;
            $this->urls = array(
                'replacement_url' => esc_url(!empty($replacement_url = get_option('stgcdn_replacement_url')) ? $replacement_url : get_site_url()),
                'staging_url' =>  get_site_url(),
            );
			$this->check_local = ( $this->plugin_settings['stgcdn_check_local'] === 'on' );

			$this->uploads_dir =  wp_get_upload_dir()['basedir'];
            $this->uploads_url = wp_get_upload_dir()['baseurl'];
        }

        public function add_menu_page(){
			add_submenu_page(
				'tools.php',
				__( 'Staging CDN', 'stgcdn' ),
				__( 'Staging CDN', 'stgcdn' ),
				'manage_options',
				'stgcdn-admin',
				array( $this, 'admin_output' )
			);
        }

		function add_plugin_settings_link( $links ) {
			$url = esc_url( add_query_arg(
				'page',
				'stgcdn-admin',
				get_admin_url() . 'tools.php'
			) );
			$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}

        public function admin_output(){ 
            extract($this->urls);
            include( $this->plugin_dir . 'admin/admin_settings.php');
        }

        public function save_settings(){
			if ( isset($_GET['reset']) && $_GET['reset'] === '1' ) {
				$url = add_query_arg('url_reset', '1', remove_query_arg('reset'));
				header( 'Location: ' . $url );
				update_option( 'stgcdn_replacement_url', '' );
				die();
			}

			if ( isset($_GET['url_reset']) && $_GET['url_reset'] === '1' ) {
				$this->update_error_notice('success');
			}

            if (isset($_POST['stgcdn_save_url']) && $_POST['stgcdn_save_url'] === 'true') {
                $this->save_staging_url();
            } else if ( isset($_POST['stgcdn_save_settings']) && $_POST['stgcdn_save_settings'] === 'true' ) {
                $this->save_plugin_settings();
            } 
        }
        
        private function save_staging_url(){
            if (empty($_POST['stgcdn_new_url'])) {
                $this->update_error_notice('failed', 'You did not enter a new url, please try again.');
                return;
            }

			$validation = $this->validate_url( esc_url_raw( $_POST['stgcdn_new_url'] ) );
			$url = $validation['url'];

			if ( !$validation['success'] ) {
				$this->update_error_notice( 'failed', $validation['msg'] );
				return;
			}

            // Strip trailing slash if it exists and update global variable which is displayed on page load.
			$_POST['stgcdn_updated_url'] = $url = ( substr($url, -1) === '/' ? substr( $url, 0, (strlen($url)-1) ) : $url );
            update_option('stgcdn_replacement_url', $url);

            $this->update_error_notice('success');
        }

        private function save_plugin_settings(){
            $settings = array();

            $default_args = array(
                'stgcdn_check_local' => NULL,
            );
            
            foreach($_POST as $key => $value) {
                if (strpos($key, 'stgcdn_') !== false && array_key_exists($key, $default_args) ) {
					$s_value = sanitize_text_field($value);
					$settings[sanitize_key($key)] = !empty($s_value) ? sanitize_text_field($s_value) : NULL;
                }
            }

            if (is_array($settings)){
                $settings = array_merge($default_args, $settings);
                $test = update_option('stgcdn_settings', serialize($settings) );
                $this->update_error_notice('success');
            } else {
                $this->update_error_notice('failed', 'Something went wrong, please try again.');
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
			$file_path = str_replace($this->uploads_url, '', $image_url);

            if ($this->check_local && file_exists( $this->uploads_dir . $file_path )) {
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
            foreach($sources as $key => $source) {
				$file_path = str_replace($this->uploads_url, '', $source['url']);
                //If file does not exist set url for current attachment to the Staging CDN url.
                if ($this->check_local && file_exists($this->uploads_dir . $file_path )){
                    continue;
                } else {
                    $sources[$key]['url'] = str_replace($staging_url, $replacement_url, $sources[$key]['url']);
                }
            }
            return $sources; // Return new $sources with both Local & Staging CDN paths where applicable.
        }

        private function rewrite_media_content(array $sources, string $staging_url, string $replacement_url) : array {
            foreach($sources as $key => $source) {
				$file_path = str_replace($this->uploads_url, '', $source);
                //If file does not exist set url for current attachment to the Staging CDN url.
                if ($this->check_local && file_exists($this->uploads_dir . $file_path )){
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
		

        private function update_error_notice(string $status, string $error = '') {
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
			$request = wp_remote_head( $url );

			if ( !is_wp_error($request) ) { // If redirected then test the Url with/without appended '/'
				$response_code = $request['response']['code'];
				if ( $response_code === 301 || $response_code === 302 ) {
					$test_url = ( (substr($url, -1) === '/' ) ? substr($url, 0, -1) : $url . '/' ); // 
					$request = wp_remote_head( $test_url );
				}
			}

			if ( is_wp_error($request) ) {
				$response = array(
					'success' => false,
					'response_code' => 500,
					'msg' => $request->errors['http_request_failed'][0],
					'url' => $url
				);
			} else {
				$response_code = $request['response']['code'];
				$response = array(
					'success' => false,
					'response_code' => null,
					'msg' => '',
					'url' => $url // Sanitized URL for database
				);

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
			}

			return $response;
        }

    }
}
new stagingCDN();