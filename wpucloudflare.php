<?php

/*
Plugin Name: WPU Cloudflare
Description: Handle Cloudflare reverse proxy
Plugin URI: https://github.com/WordPressUtilities/wpucloudflare
Version: 0.1.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUCloudflare {

    private $plugin_id = 'wpucloudflare';
    private $plugin_name = 'WPU Cloudflare';
    private $wpusettings = false;
    private $settings_values = false;

    public function __construct() {
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('save_post', array(&$this, 'save_post'));
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    public function plugins_loaded() {
        $this->settings_details = array(
            'create_page' => true,
            'plugin_name' => $this->plugin_name,
            'plugin_id' => $this->plugin_id,
            'option_id' => $this->plugin_id . '_options',
            'sections' => array(
                'api_settings' => array(
                    'name' => __('API Settings', 'wpucloudflare')
                )
            )
        );
        $this->settings = array(
            'email' => array(
                'label' => __('Account Email', 'wpucloudflare')
            ),
            'zone' => array(
                'label' => __('Zone ID', 'wpucloudflare')
            ),
            'key' => array(
                'label' => __('API Key', 'wpucloudflare')
            )
        );

        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->wpusettings = new \wpucloudflare\WPUBaseSettings($this->settings_details, $this->settings);
        $this->settings_values = $this->wpusettings->get_setting_values();
    }

    /* ----------------------------------------------------------
      Events
    ---------------------------------------------------------- */

    public function save_post($post_id) {
        if (empty($_POST)) {
            return;
        }
        $this->purge_urls(get_permalink($post_id));
    }

    /* ----------------------------------------------------------
      Methods
    ---------------------------------------------------------- */

    public function purge_urls($urls = array()) {
        if (!is_array($urls)) {
            $urls = array($urls);
        }
        $this->cloudflare_request(
            'purge_cache',
            'DELETE',
            json_encode(array('files' => $urls))
        );
    }

    public function purge_everything() {
        $this->cloudflare_request(
            'purge_cache',
            'DELETE',
            '{"purge_everything":true}'
        );
    }

    /* ----------------------------------------------------------
      Helpers
    ---------------------------------------------------------- */

    public function cloudflare_request($endpoint = '', $request = 'DELETE', $postfields = '') {
        $ch = curl_init('https://api.cloudflare.com/client/v4/zones/' . $this->settings_values['zone'] . '/' . $endpoint);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Auth-Email: ' . $this->settings_values['email'],
            'X-Auth-Key: ' . $this->settings_values['key'],
            'Content-Type: application/json'
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        $ex = curl_exec($ch);

        if (WP_DEBUG) {
            error_log($ex);
        }
    }

}

$WPUCloudflare = new WPUCloudflare();
