<?php
/**
 * Plugin Name: Staging CDN
 * Plugin URI: https://wordpress.org/
 * Description: This plugin allows you to reference all media content from your live site.
 * Version: 1.0.1
 * Author: Ronan Mockett
 * Author URI: http://wordpress.org/
 * Text Domain: stgcdn
 * License: GPL v2 or later
 * Requires at least: 7.0
 * Tested up to: 5.2.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists ('stagingCDN')){

  class stagingCDN {
    private $stgcdn_urls;
    private $stgcdn_status;
    private $stgcdn_error;
    private $media_path;
    private $stgcdn_plugin_settings;
    //Initial plugin defaults
    private $stgcdn_init_plugin_settings = array(
      'stgcdn_check_local' => 'enabled',
    );
    //Default args for plugin settings to revert back to.
    private $stgcdn_default_plugin_settings = array(
      'stgcdn_check_local' => NULL,
    );
    
    public function __construct(){
      //Save plugin settings
      add_action( 'init', array( $this, 'stgcdn_save_settings' ) );
      //Initialise plugin variables after settings have updated.
      add_action( 'init', array($this, '__plugin_init') );

      add_action( 'admin_menu', array( $this, 'add_stgcdn_menu_page' ) );
      add_filter( 'wp_get_attachment_url', array($this, 'stgcdn_set_attachment_url'), 10, 2 );
      add_filter('wp_get_attachment_image_src', array($this, 'stgcdn_set_attachment_image_src'), 10, 4 );
      add_filter('wp_calculate_image_srcset', array($this, 'stgcdn_calculate_image_srcset'), 10, 4 );
    }

    public function __plugin_init(){
      $test = $this->stgcdn_plugin_settings = (!empty(get_option('stgcdn_plugin_settings')) && is_array(get_option('stgcdn_plugin_settings'))) ? get_option('stgcdn_plugin_settings') : $this->stgcdn_init_plugin_settings;
      $this->stgcdn_urls = array(
        'current_url' => get_option('stgcdn_current_url') ? get_option('stgcdn_current_url') : get_site_url(),
        'staging_url' =>  get_site_url(),
      );
      $this->media_path = dirname( __DIR__ , 2);
    }

    public function add_stgcdn_menu_page(){
      add_menu_page(
            __( 'Staging CDN', 'stgcdn' ),
            __( 'Staging CDN', 'stgcdn' ),
            'manage_options',
            'stgcdn-admin',
            array( $this, 'stgcdn_admin_output' ),
            'dashicons-editor-ul'
        );
    }

    public function stgcdn_admin_output(){ 
      extract($this->stgcdn_urls);
      $local_check_setting = ($this->stgcdn_plugin_settings['stgcdn_check_local'] === 'enabled');
      if ($this->stgcdn_status === 'failed') {
        echo "<div><p style='display: flex; padding: 15px; max-width: 600px; background: white; box-shadow: rgba(0, 0, 0, 0.1) 0px 1px 1px 0px;border-left: 4px solid #ce2020;'><strong style='text-transform:capitalize;color:#ce2020;margin-right:3px;'>$this->stgcdn_status :</strong> $this->stgcdn_error</p></div>";
      } elseif ($this->stgcdn_status === 'success') {
        echo "<div><p style='display: flex; padding: 15px; max-width: 600px; background: white; box-shadow: rgba(0, 0, 0, 0.1) 0px 1px 1px 0px;border-left: 4px solid #55af5a;'><strong style='text-transform:capitalize;color:#55af5a;margin-right:3px;'>$this->stgcdn_status :</strong> Your settings have been saved.</p></div>";
      } ?>

      <div style="margin-top:1em;max-width: 600px;background: white;padding: 15px 15px 30px 15px;box-shadow: rgba(0, 0, 0, 0.1) 0px 1px 1px 0px;border-left: 4px rgba(0,0,0,0.4) solid;">
        <h1 style="margin-top:15px;">Staging CDN</h1>
        <form action="?page=stgcdn-admin" method="post">
        <div style="display: flex; flex-direction: column; align-items: flex-start;">
          <label>Current URL media is being referenced from.</label>
          <input name="stgcdn_current_url" type="text" value="<?php echo isset($_POST['stgcdn_new_url']) && !empty($_POST['stgcdn_new_url']) ? $_POST['stgcdn_new_url'] : $current_url; ?>" style="min-width:350px" disabled/><br/>
          <label>Your Staging URL</label>
          <input name="stgcdn_staging_url" type="text" value="<?php echo $staging_url; ?>" style="min-width:350px" disabled/><br/>
          <label>New URL you would like to use</label>
          <input name="stgcdn_new_url" type="text" value="" style="min-width:350px"/>
          <input name="stgcdn_save_url" type="text" value="true" style="min-width:350px" hidden/>
        </div>
        <button type="submit" style="all: unset;padding: 15px 65px;width: 100%;max-width: 350px;box-sizing: border-box;margin-top: 15px;text-align: center;box-shadow: 0 0 1px black inset;">Update URL</button>
        </form>
      </div>


      <div style="margin-top:1em;max-width: 600px;background: white;padding: 15px 15px 30px 15px;box-shadow: rgba(0, 0, 0, 0.1) 0px 1px 1px 0px;border-left: 4px rgba(0,0,0,0.4) solid;">
        <h1 style="margin-top:15px;">Settings</h1>
        <form action="?page=stgcdn-admin" method="post">
        <div style="display: flex; flex-direction: column; align-items: flex-start;">
          
          <label for="local_checkbox" >
            <input id="local_checkbox" name="stgcdn_check_local" type="checkbox" value="enabled" style="margin-top:0;" <?php echo $local_check_setting === true ? 'checked' : ''; ?>/>
            Use sites own media if available?
          </label>
          <!-- <p>This is slightly slower but allows you to use your own media library as well.</p> -->
          
          
          <input name="stgcdn_save_settings" type="text" value="true" style="min-width:350px" hidden/>
        </div>
        <button type="submit" style="all: unset;padding: 15px 65px;width: 100%;max-width: 350px;box-sizing: border-box;margin-top: 15px;text-align: center;box-shadow: 0 0 1px black inset;">Update Settings</button>
        </form>
      </div>

    <?php }

    public function stgcdn_save_settings(){
      if (isset($_POST['stgcdn_save_url']) && $_POST['stgcdn_save_url'] === 'true') {
        $this->stgcdn_save_staging_url();
      } else if ( isset($_POST['stgcdn_save_settings']) && $_POST['stgcdn_save_settings'] === 'true' ) {
        $this->stgcdn_save_plugin_settings();
      } 
    }
    
    private function stgcdn_save_staging_url(){
      //Run checks
      if (empty($_POST['stgcdn_new_url'])) {
        $this->stgcdn_settings_saved_status('failed', 'You did not enter a new url, please try again.');
        return;
      }
      if (! $this->stgcdn_ping_live_url() ){
        $this->stgcdn_settings_saved_status('failed', 'Live site url did not return a valid response code (2xx), please try again.');
        return;
      }
      
      $new_url = $_POST['stgcdn_new_url'];
      // Removes '/' from end of URL if it exists.
      $_POST['stgcdn_new_url'] = $new_url = substr($new_url, -1) === '/' ? substr( $new_url, 0, (strlen($new_url)-1) ) : $new_url;
      update_option('stgcdn_current_url', $new_url);
      $this->stgcdn_settings_saved_status('success');
    }

    private function stgcdn_save_plugin_settings(){
      $settings = array();
      foreach($_POST as $key => $__post) {
        if (strpos($key, 'stgcdn_') !== false ) {
          $settings[$key] = $__post !== '' ? $__post : NULL;
        }
      }
      unset($settings['stgcdn_save_settings']);

      if (is_array($settings)){
        $settings = array_merge($this->stgcdn_default_plugin_settings, $settings);
        update_option('stgcdn_plugin_settings', $settings);
        $this->stgcdn_settings_saved_status('success');
      } else {
        $this->stgcdn_settings_saved_status('failed', 'Something went wrong, please try again.');
      }

    }

    public function stgcdn_set_attachment_url($url, $post_id){
      extract($this->stgcdn_urls);
      if ($staging_url === $current_url || empty($current_url)) {
        return $url;
      }

      //Check if media exists locally.
      return $this->stgcdn_media_check( $url, $staging_url, $current_url );

      // if ( $this->stgcdn_media_check( $url ) ) {
      //   return $url;
      // }
      // $stgcdn_url = str_replace($staging_url, $current_url, $url);
      // return $stgcdn_url;
    }

    public function stgcdn_set_attachment_image_src($image, $attachment_id, $size, $icon){
      extract($this->stgcdn_urls);
      if ($staging_url === $current_url || empty($current_url) || $image === false) {
        return $image;
      }
      // if ( $this->stgcdn_media_check( $image[0] ) ) {
      //   return $image;
      // } 
      // //If media does not exist locally then return Staging CDN url.
      // $stgcdn_url = str_replace($staging_url, $current_url, $image[0]);
      // $image[0] = $stgcdn_url;
      // return $image;

      //Check if media exists locally.
      $image[0] = $this->stgcdn_media_check( $image[0], $staging_url, $current_url );
      return $image;
    }

    public function stgcdn_calculate_image_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id = 0) {
      extract($this->stgcdn_urls);
      if ($staging_url === $current_url || empty($current_url)) {
        return $sources;
      }
      // Check each image src to see if stored locally. Set all non-existing media to Staging CDN url.
      return $this->stgcdn_media_check_srcset( $sources, $staging_url, $current_url );
      // return $stgcdn_srcset;
    }

    private function stgcdn_settings_saved_status($status, $error = '') {
      $this->stgcdn_status = $status;
      if (!empty($error)) {
        $this->stgcdn_error = $error;
      }
    }

    /** 
     * Checks if media exists locally.
     *
     * @param string $url - Current media url.
     * @return boolean True or False
     */
    private function stgcdn_media_check($url, $staging_url, $current_url) {
      $local_check_on = ($this->stgcdn_plugin_settings['stgcdn_check_local'] === 'enabled');
      $file_path = str_replace($staging_url . '/wp-content', '', $url);

      if ($local_check_on && file_exists( $this->media_path . $file_path )) {
        return $url; // Return Local path
      }

      return str_replace($staging_url, $current_url, $url);  // Return Staging CDN path
    }

    /** 
     * Checks if srcset media exist locally.
     *
     * @param string $sources - Current media sources.
     * @return array $sources - Returns sources array with updated urls.
     */
    private function stgcdn_media_check_srcset($sources, $staging_url, $current_url){
      $local_check_on = ($this->stgcdn_plugin_settings['stgcdn_check_local'] === 'enabled');

      foreach($sources as $key => $source) {
        $file_path = str_replace($staging_url . '/wp-content', '', $source['url']);
        //If file does not exist set url for current attachment to the Staging CDN url.
        if ($local_check_on && file_exists($this->media_path . $file_path )){
          continue;
        } else {
          $sources[$key]['url'] = str_replace($staging_url, $current_url, $sources[$key]['url']);
        }
      }
      
      return $sources; // Return new $sources with both Local & Staging CDN paths where applicable.
    }

    private function stgcdn_ping_live_url() {
      $url_status = true;
      $url = $_POST['stgcdn_new_url'];
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_TIMEOUT, 5);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if($httpcode>=200 && $httpcode<300){
        $url_status = true;
      } else {
        $url_status = false;
      }

      if ($_POST['stgcdn_new_url'] === get_site_url()) {
        $url_status = true;
      }

      return $url_status;
    }
  }

  // ------------ Deprecated/Retired -------------

  // __construct => add_filter( 'wp_get_attachment_image_attributes', array($this, 'stgcdn_set_attachment_image_attributes'), 10, 3 );
  // public function stgcdn_set_attachment_image_attributes($attr, $attachment, $size){
  //   extract($this->stgcdn_urls);
  //   if ($staging_url === $current_url || empty($current_url)) {
  //     return $attr;
  //   }
  //   // Check each image src to see if stored locally. Set all non-existing media to Staging CDN url.
  //   $stgcdn_srcset = $this->stgcdn_media_check_attr( $attr['srcset'] );
  //   $attr['srcset'] = $stgcdn_srcset;
  //   return $attr;
  // }
  // private function stgcdn_media_check_attr($srcset){
  //   extract($this->stgcdn_urls);
  //   $file_path = str_replace($staging_url . '/wp-content', '', $srcset);
  //   // Check each attribute path for existing media, if it does not exist then return Staging CDN url.
  //   $attr_paths = explode( ', ', $file_path );
  //   $new_srcset = explode(', ', $srcset);
  //   foreach($attr_paths as $index => $url_file_path) {
  //     // Get position of space in url and return a substr of just the url.
  //     $url_break_pos = strpos($url_file_path, ' ');
  //     if ($url_break_pos !== FALSE) {
  //       $url_file_path = substr($url_file_path, 0, $url_break_pos);
  //     }
  //     //If file does not exist set url for current attachment to the Staging CDN url.
  //     if (!file_exists( $this->media_path . $url_file_path )) {
  //       $new_srcset[$index] = str_replace($staging_url, $current_url, $new_srcset[$index]);
  //     }
  //   }
  //   return implode(', ', $new_srcset); // Return srcset with both Local & Staging CDN paths where applicable.
  // }










}
new stagingCDN();