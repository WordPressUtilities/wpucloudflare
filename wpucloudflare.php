<?php

/*
Plugin Name: WPU Cloudflare
Description: Handle Cloudflare reverse proxy
Plugin URI: https://github.com/WordPressUtilities/wpucloudflare
Version: 0.3.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUCloudflare {

    private $plugin_version = '0.3.2';
    private $plugin_id = 'wpucloudflare';
    private $plugin_name = 'WPU Cloudflare';
    private $plugin_level = 'manage_options';
    private $wpusettings = false;
    private $settings_values = false;

    public function __construct() {

        /* Load plugin */
        add_action('plugins_loaded', array(&$this, 'load_translation'));
        add_action('plugins_loaded', array(&$this, 'load_autoupdate'));
        add_action('plugins_loaded', array(&$this, 'load_settings'));

        /* Actions save post */
        add_action('save_post', array(&$this, 'save_post'));

        /* Clear all */
        add_action('wpubasesettings_after_content_settings_page_wpucloudflare', array(&$this, 'form_clear'));
        add_action('admin_init', array(&$this, 'form_clear_postAction'));

        /* External hooks */
        add_action('wpucloudflare__purge_everything', array(&$this, 'purge_everything'));

        /* NoCache */
        add_action('init', array(&$this, 'enable_nocache_urls'));
    }

    /* ----------------------------------------------------------
      Translation
    ---------------------------------------------------------- */

    public function load_translation() {
        load_plugin_textdomain('wpucloudflare', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /* ----------------------------------------------------------
      Auto-Update
    ---------------------------------------------------------- */

    public function load_autoupdate() {
        include dirname(__FILE__) . '/inc/WPUBaseUpdate/WPUBaseUpdate.php';
        $this->settings_update = new \wpucloudflare\WPUBaseUpdate(
            'WordPressUtilities',
            $this->plugin_id,
            $this->plugin_version);
    }

    /* ----------------------------------------------------------
      Settings
    ---------------------------------------------------------- */

    public function load_settings() {
        $this->settings_details = array(
            'create_page' => true,
            'plugin_name' => $this->plugin_name,
            'plugin_basename' => plugin_basename(__FILE__),
            'plugin_id' => $this->plugin_id,
            'parent_page' => 'options-general.php',
            'user_cap' => $this->plugin_level,
            'option_id' => $this->plugin_id . '_options',
            'sections' => array(
                'api_settings' => array(
                    'name' => __('API Settings', 'wpucloudflare')
                ),
                'tools' => array(
                    'name' => __('Tools', 'wpucloudflare')
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
            ),
            'nocache' => array(
                'section' => 'tools',
                'type' => 'checkbox',
                'label' => __('NoCache', 'wpucloudflare'),
                'label_check' => __('Enable NoCache', 'wpucloudflare'),
                'help' => __('Adds a unique parameter to all urls for logged users to avoid cache.', 'wpucloudflare')
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

    public function enable_nocache_urls() {

        /* If option enabled */
        if (!$this->settings_values['nocache']) {
            return;
        }
        /* If user logged in */
        if (!is_user_logged_in()) {
            return;
        }

        /* Parse all available links */
        add_filter('home_url', array(&$this, 'add_nocache'), 20, 1);
        add_filter('post_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('page_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('post_type_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('category_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('tag_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('author_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('day_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('month_link', array(&$this, 'add_nocache'), 20, 1);
        add_filter('year_link', array(&$this, 'add_nocache'), 20, 1);
    }

    public function save_post($post_id) {
        if (empty($_POST)) {
            return;
        }

        $continue_purge = apply_filters('wpucloudflare__save_post__can_clear', true, $post_id);
        if (!$continue_purge) {
            return;
        }

        $urls = apply_filters('wpucloudflare__save_post__urls', array(
            get_permalink($post_id)
        ), $post_id);

        $this->purge_urls($urls);
    }

    /* ----------------------------------------------------------
      Methods
    ---------------------------------------------------------- */

    public function purge_urls($urls = array()) {
        if (!is_array($urls)) {
            $urls = array($urls);
        }
        if ($this->settings_values['nocache']) {
            foreach ($urls as &$url) {
                $url = remove_query_arg('nocache', $url);
            }
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

    public function add_nocache($url) {
        return add_query_arg('nocache', time(), $url);
    }

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
