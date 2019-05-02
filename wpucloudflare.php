<?php

/*
Plugin Name: WPU Cloudflare
Description: Handle Cloudflare reverse proxy
Plugin URI: https://github.com/WordPressUtilities/wpucloudflare
Version: 0.2.0
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUCloudflare {

    private $plugin_id = 'wpucloudflare';
    private $plugin_name = 'WPU Cloudflare';
    private $plugin_level = 'manage_options';
    private $wpusettings = false;
    private $settings_values = false;

    public function __construct() {
        /* Load plugin */
        add_action('plugins_loaded', array(&$this, 'plugins_loaded'));

        /* Actions save post */
        add_action('save_post', array(&$this, 'save_post'));

        /* Clear all */
        add_action('wpubasesettings_after_content_settings_page_wpucloudflare', array(&$this, 'form_clear'));
        add_action('admin_init', array(&$this, 'form_clear_postAction'));
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    public function plugins_loaded() {
        $this->settings_details = array(
            'create_page' => true,
            'plugin_name' => $this->plugin_name,
            'plugin_id' => $this->plugin_id,
            'parent_page' => 'options-general.php',
            'user_cap' => $this->plugin_level,
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
        $this->admin_url = admin_url($this->settings_details['parent_page'] . '?page=' . $this->plugin_id);
        $this->wpusettings = new \wpucloudflare\WPUBaseSettings($this->settings_details, $this->settings);
        $this->settings_values = $this->wpusettings->get_setting_values();
    }

    /* ----------------------------------------------------------
      Admin page
    ---------------------------------------------------------- */

    public function form_clear() {
        echo '<hr />';
        echo '<form action="' . $this->admin_url . '" method="post">';
        wp_nonce_field('wpucloudflareclearall', 'wpucloudflareclearall_clear');
        submit_button(__('Clear all cache', 'wpucloudflare'), 'primary', 'wpucloudflareclearall');
        echo '</form>';
    }

    public function form_clear_postAction() {
        if (!is_admin()) {
            return;
        }
        if (!current_user_can($this->plugin_level)) {
            return;
        }
        if (empty($_POST)) {
            return;
        }
        if (!isset($_POST['wpucloudflareclearall'])) {
            return;
        }
        if (!isset($_POST['wpucloudflareclearall_clear']) || !wp_verify_nonce($_POST['wpucloudflareclearall_clear'], 'wpucloudflareclearall')) {
            return;
        }

        $this->purge_everything();
        wp_redirect($this->admin_url . '&purge_success=1');
        die;
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
