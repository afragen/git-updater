<?php
namespace Appsero;

/**
 * Appsero Insights
 *
 * This is a tracker class to track plugin usage based on if the customer has opted in.
 * No personal information is being tracked by this class, only general settings, active plugins, environment details
 * and admin email.
 */
class Insights {

    /**
     * The notice text
     *
     * @var string
     */
    public $notice;

    /**
     * Wheather to the notice or not
     *
     * @var boolean
     */
    protected $show_notice = true;

    /**
     * If extra data needs to be sent
     *
     * @var array
     */
    protected $extra_data = array();

    /**
     * AppSero\Client
     *
     * @var object
     */
    protected $client;

    /**
     * @var boolean
     */
    private $plugin_data = false;


    /**
     * Initialize the class
     *
     * @param      $client
     * @param null $name
     * @param null $file
     */
    public function __construct( $client, $name = null, $file = null ) {

        if ( is_string( $client ) && ! empty( $name ) && ! empty( $file ) ) {
            $client = new Client( $client, $name, $file );
        }

        if ( is_object( $client ) && is_a( $client, 'Appsero\Client' ) ) {
            $this->client = $client;
        }
    }

    /**
     * Don't show the notice
     *
     * @return \self
     */
    public function hide_notice() {
        $this->show_notice = false;

        return $this;
    }

    /**
     * Add plugin data if needed
     *
     * @return \self
     */
    public function add_plugin_data() {
        $this->plugin_data = true;

        return $this;
    }

    /**
     * Add extra data if needed
     *
     * @param array $data
     *
     * @return \self
     */
    public function add_extra( $data = array() ) {
        $this->extra_data = $data;

        return $this;
    }

    /**
     * Set custom notice text
     *
     * @param  string $text
     *
     * @return \self
     */
    public function notice($text='' ) {
        $this->notice = $text;

        return $this;
    }

    /**
     * Initialize insights
     *
     * @return void
     */
    public function init() {
        if ( $this->client->type == 'plugin' ) {
            $this->init_plugin();
        } else if ( $this->client->type == 'theme' ) {
            $this->init_theme();
        }
    }

    /**
     * Initialize theme hooks
     *
     * @return void
     */
    public function init_theme() {
        $this->init_common();

        add_action( 'switch_theme', array( $this, 'deactivation_cleanup' ) );
        add_action( 'switch_theme', array( $this, 'theme_deactivated' ), 12, 3 );
    }

    /**
     * Initialize plugin hooks
     *
     * @return void
     */
    public function init_plugin() {
        // plugin deactivate popup
        if ( ! $this->is_local_server() ) {
            add_filter( 'plugin_action_links_' . $this->client->basename, array( $this, 'plugin_action_links' ) );
            add_action( 'admin_footer', array( $this, 'deactivate_scripts' ) );
        }

        $this->init_common();

        register_activation_hook( $this->client->file, array( $this, 'activate_plugin' ) );
        register_deactivation_hook( $this->client->file, array( $this, 'deactivation_cleanup' ) );
    }

    /**
     * Initialize common hooks
     *
     * @return void
     */
    protected function init_common() {

        if ( $this->show_notice ) {
            // tracking notice
            add_action( 'admin_notices', array( $this, 'admin_notice' ) );
        }

        add_action( 'admin_init', array( $this, 'handle_optin_optout' ) );

        // uninstall reason
        add_action( 'wp_ajax_' . $this->client->slug . '_submit-uninstall-reason', array( $this, 'uninstall_reason_submission' ) );

        // cron events
        add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
        add_action( $this->client->slug . '_tracker_send_event', array( $this, 'send_tracking_data' ) );
        // add_action( 'admin_init', array( $this, 'send_tracking_data' ) ); // test
    }

    /**
     * Send tracking data to AppSero server
     *
     * @param  boolean  $override
     *
     * @return void
     */
    public function send_tracking_data( $override = false ) {
        if ( ! $this->tracking_allowed() && ! $override ) {
            return;
        }

        // Send a maximum of once per week
        $last_send = $this->get_last_send();

        if ( $last_send && $last_send > strtotime( '-1 week' ) ) {
            return;
        }

        $tracking_data = $this->get_tracking_data();

        $response = $this->client->send_request( $tracking_data, 'track' );

        update_option( $this->client->slug . '_tracking_last_send', time() );
    }

    /**
     * Get the tracking data points
     *
     * @return array
     */
    protected function get_tracking_data() {
        $all_plugins = $this->get_all_plugins();

        $users = get_users( array(
            'role'    => 'administrator',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
            'paged'   => 1,
        ) );

        $admin_user =  ( is_array( $users ) && ! empty( $users ) ) ? $users[0] : false;
        $first_name = $last_name = '';

        if ( $admin_user ) {
            $first_name = $admin_user->first_name ? $admin_user->first_name : $admin_user->display_name;
            $last_name  = $admin_user->last_name;
        }

        $data = array(
            'url'              => esc_url( home_url() ),
            'site'             => $this->get_site_name(),
            'admin_email'      => get_option( 'admin_email' ),
            'first_name'       => $first_name,
            'last_name'        => $last_name,
            'hash'             => $this->client->hash,
            'server'           => $this->get_server_info(),
            'wp'               => $this->get_wp_info(),
            'users'            => $this->get_user_counts(),
            'active_plugins'   => count( $all_plugins['active_plugins'] ),
            'inactive_plugins' => count( $all_plugins['inactive_plugins'] ),
            'ip_address'       => $this->get_user_ip_address(),
            'project_version'  => $this->client->project_version,
            'tracking_skipped' => false,
            'is_local'         => $this->is_local_server(),
        );

        // Add Plugins
        if ($this->plugin_data) {
            
            $plugins_data = array();

            foreach ($all_plugins['active_plugins'] as $slug => $plugin) {
                $slug = strstr($slug, '/', true);
                if (! $slug) {
                    continue;
                }

                $plugins_data[ $slug ] = array(
                    'name' => isset($plugin['name']) ? $plugin['name'] : '',
                    'version' => isset($plugin['version']) ? $plugin['version'] : '',
                );
            }

            if (array_key_exists($this->client->slug, $plugins_data)) {
                unset($plugins_data[$this->client->slug]);
            }
            
            $data['plugins'] = $plugins_data;
        }

        // Add metadata
        if ( $extra = $this->get_extra_data() ) {
            $data['extra'] = $extra;
        }

        // Check this has previously skipped tracking
        $skipped = get_option( $this->client->slug . '_tracking_skipped' );

        if ( $skipped === 'yes' ) {
            delete_option( $this->client->slug . '_tracking_skipped' );

            $data['tracking_skipped'] = true;
        }

        return apply_filters( $this->client->slug . '_tracker_data', $data );
    }

    /**
     * If a child class wants to send extra data
     *
     * @return mixed
     */
    protected function get_extra_data() {
        if ( is_callable( $this->extra_data ) ) {
            return call_user_func( $this->extra_data );
        }

        if ( is_array( $this->extra_data ) ) {
            return $this->extra_data;
        }

        return array();
    }

    /**
     * Explain the user which data we collect
     *
     * @return array
     */
    protected function data_we_collect() {
        $data = array(
            'Server environment details (php, mysql, server, WordPress versions)',
            'Number of users in your site',
            'Site language',
            'Number of active and inactive plugins',
            'Site name and URL',
            'Your name and email address',
        );

        if ($this->plugin_data) { 
            array_splice($data, 4, 0, ["active plugins' name"]);
        }

        return $data;
    }

    /**
     * Check if the user has opted into tracking
     *
     * @return bool
     */
    public function tracking_allowed() {
        $allow_tracking = get_option( $this->client->slug . '_allow_tracking', 'no' );

        return $allow_tracking == 'yes';
    }

    /**
     * Get the last time a tracking was sent
     *
     * @return false|string
     */
    private function get_last_send() {
        return get_option( $this->client->slug . '_tracking_last_send', false );
    }

    /**
     * Check if the notice has been dismissed or enabled
     *
     * @return boolean
     */
    public function notice_dismissed() {
        $hide_notice = get_option( $this->client->slug . '_tracking_notice', null );

        if ( 'hide' == $hide_notice ) {
            return true;
        }

        return false;
    }

    /**
     * Check if the current server is localhost
     *
     * @return boolean
     */
    private function is_local_server() {

        $host       = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $ip         = isset( $_SERVER['SERVER_ADDR'] ) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
        $is_local   = false;

        if( in_array( $ip,array( '127.0.0.1', '::1' ) )
            || ! strpos( $host, '.' )
            || in_array( strrchr( $host, '.' ), array( '.test', '.testing', '.local', '.localhost', '.localdomain' ) )
        ) {
            $is_local = true;
        }

        return apply_filters( 'appsero_is_local', $is_local );
    }

    /**
     * Schedule the event weekly
     *
     * @return void
     */
    private function schedule_event() {
        $hook_name = $this->client->slug . '_tracker_send_event';

        if ( ! wp_next_scheduled( $hook_name ) ) {
            wp_schedule_event( time(), 'weekly', $hook_name );
        }
    }

    /**
     * Clear any scheduled hook
     *
     * @return void
     */
    private function clear_schedule_event() {
        wp_clear_scheduled_hook( $this->client->slug . '_tracker_send_event' );
    }

    /**
     * Display the admin notice to users that have not opted-in or out
     *
     * @return void
     */
    public function admin_notice() {

        if ( $this->notice_dismissed() ) {
            return;
        }

        if ( $this->tracking_allowed() ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // don't show tracking if a local server
        if ( $this->is_local_server() ) {
            return;
        }

        $optin_url  = add_query_arg( $this->client->slug . '_tracker_optin', 'true' );
        $optout_url = add_query_arg( $this->client->slug . '_tracker_optout', 'true' );

        if ( empty( $this->notice ) ) {
            $notice = sprintf( $this->client->__trans( 'Want to help make <strong>%1$s</strong> even more awesome? Allow %1$s to collect non-sensitive diagnostic data and usage information.' ), $this->client->name );
        } else {
            $notice = $this->notice;
        }

        $policy_url = 'https://' . 'appsero.com/privacy-policy/';

        $notice .= ' (<a class="' . $this->client->slug . '-insights-data-we-collect" href="#">' . $this->client->__trans( 'what we collect' ) . '</a>)';
        $notice .= '<p class="description" style="display:none;">' . implode( ', ', $this->data_we_collect() ) . '. No sensitive data is tracked. ';
        $notice .= 'We are using Appsero to collect your data. <a href="' . $policy_url . '" target="_blank">Learn more</a> about how Appsero collects and handle your data.</p>';

        echo '<div class="updated"><p>';
            echo $notice;
            echo '</p><p class="submit">';
            echo '&nbsp;<a href="' . esc_url( $optin_url ) . '" class="button-primary button-large">' . $this->client->__trans( 'Allow' ) . '</a>';
            echo '&nbsp;<a href="' . esc_url( $optout_url ) . '" class="button-secondary button-large">' . $this->client->__trans( 'No thanks' ) . '</a>';
        echo '</p></div>';

        echo "<script type='text/javascript'>jQuery('." . $this->client->slug . "-insights-data-we-collect').on('click', function(e) {
                e.preventDefault();
                jQuery(this).parents('.updated').find('p.description').slideToggle('fast');
            });
            </script>
        ";
    }

    /**
     * handle the optin/optout
     *
     * @return void
     */
    public function handle_optin_optout() {

        if ( isset( $_GET[ $this->client->slug . '_tracker_optin' ] ) && $_GET[ $this->client->slug . '_tracker_optin' ] == 'true' ) {
            $this->optin();

            wp_redirect( remove_query_arg( $this->client->slug . '_tracker_optin' ) );
            exit;
        }

        if ( isset( $_GET[ $this->client->slug . '_tracker_optout' ] ) && $_GET[ $this->client->slug . '_tracker_optout' ] == 'true' ) {
            $this->optout();

            wp_redirect( remove_query_arg( $this->client->slug . '_tracker_optout' ) );
            exit;
        }
    }

    /**
     * Tracking optin
     *
     * @return void
     */
    public function optin() {
        update_option( $this->client->slug . '_allow_tracking', 'yes' );
        update_option( $this->client->slug . '_tracking_notice', 'hide' );

        $this->clear_schedule_event();
        $this->schedule_event();
        $this->send_tracking_data();
    }

    /**
     * Optout from tracking
     *
     * @return void
     */
    public function optout() {
        update_option( $this->client->slug . '_allow_tracking', 'no' );
        update_option( $this->client->slug . '_tracking_notice', 'hide' );

        $this->send_tracking_skipped_request();

        $this->clear_schedule_event();
    }

    /**
     * Get the number of post counts
     *
     * @param  string  $post_type
     *
     * @return integer
     */
    public function get_post_count( $post_type ) {
        global $wpdb;

        return (int) $wpdb->get_var( "SELECT count(ID) FROM $wpdb->posts WHERE post_type = '$post_type' and post_status = 'publish'");
    }

    /**
     * Get server related info.
     *
     * @return array
     */
    private static function get_server_info() {
        global $wpdb;

        $server_data = array();

        if ( isset( $_SERVER['SERVER_SOFTWARE'] ) && ! empty( $_SERVER['SERVER_SOFTWARE'] ) ) {
            $server_data['software'] = $_SERVER['SERVER_SOFTWARE'];
        }

        if ( function_exists( 'phpversion' ) ) {
            $server_data['php_version'] = phpversion();
        }

        $server_data['mysql_version']        = $wpdb->db_version();

        $server_data['php_max_upload_size']  = size_format( wp_max_upload_size() );
        $server_data['php_default_timezone'] = date_default_timezone_get();
        $server_data['php_soap']             = class_exists( 'SoapClient' ) ? 'Yes' : 'No';
        $server_data['php_fsockopen']        = function_exists( 'fsockopen' ) ? 'Yes' : 'No';
        $server_data['php_curl']             = function_exists( 'curl_init' ) ? 'Yes' : 'No';

        return $server_data;
    }

    /**
     * Get WordPress related data.
     *
     * @return array
     */
    private function get_wp_info() {
        $wp_data = array();

        $wp_data['memory_limit'] = WP_MEMORY_LIMIT;
        $wp_data['debug_mode']   = ( defined('WP_DEBUG') && WP_DEBUG ) ? 'Yes' : 'No';
        $wp_data['locale']       = get_locale();
        $wp_data['version']      = get_bloginfo( 'version' );
        $wp_data['multisite']    = is_multisite() ? 'Yes' : 'No';
        $wp_data['theme_slug']   = get_stylesheet();

        $theme = wp_get_theme( $wp_data['theme_slug'] );

        $wp_data['theme_name']    = $theme->get( 'Name' );
        $wp_data['theme_version'] = $theme->get( 'Version' );
        $wp_data['theme_uri']     = $theme->get( 'ThemeURI' );
        $wp_data['theme_author']  = $theme->get( 'Author' );

        return $wp_data;
    }

    /**
     * Get the list of active and inactive plugins
     *
     * @return array
     */
    private function get_all_plugins() {
        // Ensure get_plugins function is loaded
        if ( ! function_exists( 'get_plugins' ) ) {
            include ABSPATH . '/wp-admin/includes/plugin.php';
        }

        $plugins             = get_plugins();
        $active_plugins_keys = get_option( 'active_plugins', array() );
        $active_plugins      = array();

        foreach ( $plugins as $k => $v ) {
            // Take care of formatting the data how we want it.
            $formatted = array();
            $formatted['name'] = strip_tags( $v['Name'] );

            if ( isset( $v['Version'] ) ) {
                $formatted['version'] = strip_tags( $v['Version'] );
            }

            if ( isset( $v['Author'] ) ) {
                $formatted['author'] = strip_tags( $v['Author'] );
            }

            if ( isset( $v['Network'] ) ) {
                $formatted['network'] = strip_tags( $v['Network'] );
            }

            if ( isset( $v['PluginURI'] ) ) {
                $formatted['plugin_uri'] = strip_tags( $v['PluginURI'] );
            }

            if ( in_array( $k, $active_plugins_keys ) ) {
                // Remove active plugins from list so we can show active and inactive separately
                unset( $plugins[$k] );
                $active_plugins[$k] = $formatted;
            } else {
                $plugins[$k] = $formatted;
            }
        }

        return array( 'active_plugins' => $active_plugins, 'inactive_plugins' => $plugins );
    }

    /**
     * Get user totals based on user role.
     *
     * @return array
     */
    public function get_user_counts() {
        $user_count          = array();
        $user_count_data     = count_users();
        $user_count['total'] = $user_count_data['total_users'];

        // Get user count based on user role
        foreach ( $user_count_data['avail_roles'] as $role => $count ) {
            if ( ! $count ) {
                continue;
            }

            $user_count[ $role ] = $count;
        }

        return $user_count;
    }

    /**
     * Add weekly cron schedule
     *
     * @param array  $schedules
     *
     * @return array
     */
    public function add_weekly_schedule( $schedules ) {

        $schedules['weekly'] = array(
            'interval' => DAY_IN_SECONDS * 7,
            'display'  => 'Once Weekly',
        );

        return $schedules;
    }

    /**
     * Plugin activation hook
     *
     * @return void
     */
    public function activate_plugin() {
        $allowed = get_option( $this->client->slug . '_allow_tracking', 'no' );

        // if it wasn't allowed before, do nothing
        if ( 'yes' !== $allowed ) {
            return;
        }

        // re-schedule and delete the last sent time so we could force send again
        $hook_name = $this->client->slug . '_tracker_send_event';
        if ( ! wp_next_scheduled( $hook_name ) ) {
            wp_schedule_event( time(), 'weekly', $hook_name );
        }

        delete_option( $this->client->slug . '_tracking_last_send' );

        $this->send_tracking_data( true );
    }

    /**
     * Clear our options upon deactivation
     *
     * @return void
     */
    public function deactivation_cleanup() {
        $this->clear_schedule_event();

        if ( 'theme' == $this->client->type ) {
            delete_option( $this->client->slug . '_tracking_last_send' );
            delete_option( $this->client->slug . '_allow_tracking' );
        }

        delete_option( $this->client->slug . '_tracking_notice' );
    }

    /**
     * Hook into action links and modify the deactivate link
     *
     * @param  array  $links
     *
     * @return array
     */
    public function plugin_action_links( $links ) {

        if ( array_key_exists( 'deactivate', $links ) ) {
            $links['deactivate'] = str_replace( '<a', '<a class="' . $this->client->slug . '-deactivate-link"', $links['deactivate'] );
        }

        return $links;
    }

    /**
     * Plugin uninstall reasons
     *
     * @return array
     */
    private function get_uninstall_reasons() {
        $reasons = array(
			array(
				'id'          => 'could-not-understand',
				'text'        => $this->client->__trans( "Couldn't understand" ),
				'placeholder' => $this->client->__trans( 'Would you like us to assist you?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 23 23"><g fill="none"><g fill="#3B86FF"><path d="M11.5 0C17.9 0 23 5.1 23 11.5 23 17.9 17.9 23 11.5 23 10.6 23 9.6 22.9 8.8 22.7L8.8 22.6C9.3 22.5 9.7 22.3 10 21.9 10.3 21.6 10.4 21.3 10.4 20.9 10.8 21 11.1 21 11.5 21 16.7 21 21 16.7 21 11.5 21 6.3 16.7 2 11.5 2 6.3 2 2 6.3 2 11.5 2 13 2.3 14.3 2.9 15.6 2.7 16 2.4 16.3 2.2 16.8L2.1 17.1 2.1 17.3C2 17.5 2 17.7 2 18 0.7 16.1 0 13.9 0 11.5 0 5.1 5.1 0 11.5 0ZM6 13.6C6 13.7 6.1 13.8 6.1 13.9 6.3 14.5 6.2 15.7 6.1 16.4 6.1 16.6 6 16.9 6 17.1 6 17.1 6.1 17.1 6.1 17.1 7.1 16.9 8.2 16 9.3 15.5 9.8 15.2 10.4 15 10.9 15 11.2 15 11.4 15 11.6 15.2 11.9 15.4 12.1 16 11.6 16.4 11.5 16.5 11.3 16.6 11.1 16.7 10.5 17 9.9 17.4 9.3 17.7 9 17.9 9 18.1 9.1 18.5 9.2 18.9 9.3 19.4 9.3 19.8 9.4 20.3 9.3 20.8 9 21.2 8.8 21.5 8.5 21.6 8.1 21.7 7.9 21.8 7.6 21.9 7.3 21.9L6.5 22C6.3 22 6 21.9 5.8 21.9 5 21.8 4.4 21.5 3.9 20.9 3.3 20.4 3.1 19.6 3 18.8L3 18.5C3 18.2 3 17.9 3.1 17.7L3.1 17.6C3.2 17.1 3.5 16.7 3.7 16.3 4 15.9 4.2 15.4 4.3 15 4.4 14.6 4.4 14.5 4.6 14.2 4.6 13.9 4.7 13.7 4.9 13.6 5.2 13.2 5.7 13.2 6 13.6ZM11.7 11.2C13.1 11.2 14.3 11.7 15.2 12.9 15.3 13 15.4 13.1 15.4 13.2 15.4 13.4 15.3 13.8 15.2 13.8 15 13.9 14.9 13.8 14.8 13.7 14.6 13.5 14.4 13.2 14.1 13.1 13.5 12.6 12.8 12.3 12 12.2 10.7 12.1 9.5 12.3 8.4 12.8 8.3 12.8 8.2 12.8 8.1 12.8 7.9 12.8 7.8 12.4 7.8 12.2 7.7 12.1 7.8 11.9 8 11.8 8.4 11.7 8.8 11.5 9.2 11.4 10 11.2 10.9 11.1 11.7 11.2ZM16.3 5.9C17.3 5.9 18 6.6 18 7.6 18 8.5 17.3 9.3 16.3 9.3 15.4 9.3 14.7 8.5 14.7 7.6 14.7 6.6 15.4 5.9 16.3 5.9ZM8.3 5C9.2 5 9.9 5.8 9.9 6.7 9.9 7.7 9.2 8.4 8.2 8.4 7.3 8.4 6.6 7.7 6.6 6.7 6.6 5.8 7.3 5 8.3 5Z"/></g></g></svg>'
			),
			array(
				'id'          => 'found-better-plugin',
				'text'        => $this->client->__trans( 'Found a better plugin' ),
				'placeholder' => $this->client->__trans( 'Which plugin?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 23 23"><g fill="none"><g fill="#3B86FF"><path d="M17.1 14L22.4 19.3C23.2 20.2 23.2 21.5 22.4 22.4 21.5 23.2 20.2 23.2 19.3 22.4L19.3 22.4 14 17.1C15.3 16.3 16.3 15.3 17.1 14L17.1 14ZM8.6 0C13.4 0 17.3 3.9 17.3 8.6 17.3 13.4 13.4 17.2 8.6 17.2 3.9 17.2 0 13.4 0 8.6 0 3.9 3.9 0 8.6 0ZM8.6 2.2C5.1 2.2 2.2 5.1 2.2 8.6 2.2 12.2 5.1 15.1 8.6 15.1 12.2 15.1 15.1 12.2 15.1 8.6 15.1 5.1 12.2 2.2 8.6 2.2ZM8.6 3.6L8.6 5C6.6 5 5 6.6 5 8.6L5 8.6 3.6 8.6C3.6 5.9 5.9 3.6 8.6 3.6L8.6 3.6Z"/></g></g></svg>',
			),
			array(
				'id'          => 'not-have-that-feature',
				'text'        => $this->client->__trans( "Missing a specific feature" ),
				'placeholder' => $this->client->__trans( 'Could you tell us more about that feature?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="17" viewBox="0 0 24 17"><g fill="none"><g fill="#3B86FF"><path d="M19.4 0C19.7 0.6 19.8 1.3 19.8 2 19.8 3.2 19.4 4.4 18.5 5.3 17.6 6.2 16.5 6.7 15.2 6.7 15.2 6.7 15.2 6.7 15.2 6.7 14 6.7 12.9 6.2 12 5.3 11.2 4.4 10.7 3.3 10.7 2 10.7 1.3 10.8 0.6 11.1 0L7.6 0 7 0 6.5 0 6.5 5.7C6.3 5.6 5.9 5.3 5.6 5.1 5 4.6 4.3 4.3 3.5 4.3 3.5 4.3 3.5 4.3 3.4 4.3 1.6 4.4 0 5.9 0 7.9 0 8.6 0.2 9.2 0.5 9.7 1.1 10.8 2.2 11.5 3.5 11.5 4.3 11.5 5 11.2 5.6 10.8 6 10.5 6.3 10.3 6.5 10.2L6.5 10.2 6.5 17 6.5 17 7 17 7.6 17 22.5 17C23.3 17 24 16.3 24 15.5L24 0 19.4 0Z"/></g></g></svg>',
			),
			array(
				'id'          => 'is-not-working',
				'text'        => $this->client->__trans( 'Not working' ),
				'placeholder' => $this->client->__trans( 'Could you tell us a bit more whats not working?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 23 23"><g fill="none"><g fill="#3B86FF"><path d="M11.5 0C17.9 0 23 5.1 23 11.5 23 17.9 17.9 23 11.5 23 5.1 23 0 17.9 0 11.5 0 5.1 5.1 0 11.5 0ZM11.8 14.4C11.2 14.4 10.7 14.8 10.7 15.4 10.7 16 11.2 16.4 11.8 16.4 12.4 16.4 12.8 16 12.8 15.4 12.8 14.8 12.4 14.4 11.8 14.4ZM12 7C10.1 7 9.1 8.1 9 9.6L10.5 9.6C10.5 8.8 11.1 8.3 11.9 8.3 12.7 8.3 13.2 8.8 13.2 9.5 13.2 10.1 13 10.4 12.2 10.9 11.3 11.4 10.9 12 11 12.9L11 13.4 12.5 13.4 12.5 13C12.5 12.4 12.7 12.1 13.5 11.6 14.4 11.1 14.9 10.4 14.9 9.4 14.9 8 13.7 7 12 7Z"/></g></g></svg>',
			),
			array(
				'id'          => 'looking-for-other',
				'text'        => $this->client->__trans( "Not what I was looking" ),
				'placeholder' => $this->client->__trans( 'Could you tell us a bit more?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="17" viewBox="0 0 24 17"><g fill="none"><g fill="#3B86FF"><path d="M23.5 9C23.5 9 23.5 8.9 23.5 8.9 23.5 8.9 23.5 8.9 23.5 8.9 23.4 8.6 23.2 8.3 23 8 22.2 6.5 20.6 3.7 19.8 2.6 18.8 1.3 17.7 0 16.1 0 15.7 0 15.3 0.1 14.9 0.2 13.8 0.6 12.6 1.2 12.3 2.7L11.7 2.7C11.4 1.2 10.2 0.6 9.1 0.2 8.7 0.1 8.3 0 7.9 0 6.3 0 5.2 1.3 4.2 2.6 3.4 3.7 1.8 6.5 1 8 0.8 8.3 0.6 8.6 0.5 8.9 0.5 8.9 0.5 8.9 0.5 8.9 0.5 8.9 0.5 9 0.5 9 0.2 9.7 0 10.5 0 11.3 0 14.4 2.5 17 5.5 17 7.3 17 8.8 16.1 9.8 14.8L14.2 14.8C15.2 16.1 16.7 17 18.5 17 21.5 17 24 14.4 24 11.3 24 10.5 23.8 9.7 23.5 9ZM5.5 15C3.6 15 2 13.2 2 11 2 8.8 3.6 7 5.5 7 7.4 7 9 8.8 9 11 9 13.2 7.4 15 5.5 15ZM18.5 15C16.6 15 15 13.2 15 11 15 8.8 16.6 7 18.5 7 20.4 7 22 8.8 22 11 22 13.2 20.4 15 18.5 15Z"/></g></g></svg>',
			),
			array(
				'id'          => 'did-not-work-as-expected',
				'text'        => $this->client->__trans( "Didn't work as expected" ),
				'placeholder' => $this->client->__trans( 'What did you expect?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="23" height="23" viewBox="0 0 23 23"><g fill="none"><g fill="#3B86FF"><path d="M11.5 0C17.9 0 23 5.1 23 11.5 23 17.9 17.9 23 11.5 23 5.1 23 0 17.9 0 11.5 0 5.1 5.1 0 11.5 0ZM11.5 2C6.3 2 2 6.3 2 11.5 2 16.7 6.3 21 11.5 21 16.7 21 21 16.7 21 11.5 21 6.3 16.7 2 11.5 2ZM12.5 12.9L12.7 5 10.2 5 10.5 12.9 12.5 12.9ZM11.5 17.4C12.4 17.4 13 16.8 13 15.9 13 15 12.4 14.4 11.5 14.4 10.6 14.4 10 15 10 15.9 10 16.8 10.6 17.4 11.5 17.4Z"/></g></g></svg>',
			),
			array(
				'id'          => 'other',
				'text'        => $this->client->__trans( 'Others' ),
				'placeholder' => $this->client->__trans( 'Could you tell us a bit more?' ),
                'icon'        => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="23" viewBox="0 0 24 6"><g fill="none"><g fill="#3B86FF"><path d="M3 0C4.7 0 6 1.3 6 3 6 4.7 4.7 6 3 6 1.3 6 0 4.7 0 3 0 1.3 1.3 0 3 0ZM12 0C13.7 0 15 1.3 15 3 15 4.7 13.7 6 12 6 10.3 6 9 4.7 9 3 9 1.3 10.3 0 12 0ZM21 0C22.7 0 24 1.3 24 3 24 4.7 22.7 6 21 6 19.3 6 18 4.7 18 3 18 1.3 19.3 0 21 0Z"/></g></g></svg>',
			),
		);

        return $reasons;
    }

    /**
     * Plugin deactivation uninstall reason submission
     *
     * @return void
     */
    public function uninstall_reason_submission() {

        if ( ! isset( $_POST['reason_id'] ) ) {
            wp_send_json_error();
        }

        if ( ! wp_verify_nonce( $_POST['nonce'], 'appsero-security-nonce' ) ) {
            wp_send_json_error( 'Nonce verification failed' );
        }

        $data                = $this->get_tracking_data();
        $data['reason_id']   = sanitize_text_field( $_POST['reason_id'] );
        $data['reason_info'] = isset( $_REQUEST['reason_info'] ) ? trim( stripslashes( $_REQUEST['reason_info'] ) ) : '';

        $this->client->send_request( $data, 'deactivate' );

        wp_send_json_success();
    }

    /**
     * Handle the plugin deactivation feedback
     *
     * @return void
     */
    public function deactivate_scripts() {
        global $pagenow;

        if ( 'plugins.php' != $pagenow ) {
            return;
        }

        $this->deactivation_modal_styles();
        $reasons = $this->get_uninstall_reasons();
        $custom_reasons = apply_filters( 'appsero_custom_deactivation_reasons', array() );
        ?>

        <div class="wd-dr-modal" id="<?php echo $this->client->slug; ?>-wd-dr-modal">
            <div class="wd-dr-modal-wrap">
                <div class="wd-dr-modal-header">
                    <h3><?php $this->client->_etrans( 'Goodbyes are always hard. If you have a moment, please let us know how we can improve.' ); ?></h3>
                </div>

                <div class="wd-dr-modal-body">
                    <ul class="wd-de-reasons">
                        <?php foreach ( $reasons as $reason ) { ?>
                            <li data-placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>">
                                <label>
                                    <input type="radio" name="selected-reason" value="<?php echo $reason['id']; ?>">
                                    <div class="wd-de-reason-icon"><?php echo $reason['icon']; ?></div>
                                    <div class="wd-de-reason-text"><?php echo $reason['text']; ?></div>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php if ( $custom_reasons && is_array( $custom_reasons ) ) : ?>
                    <ul class="wd-de-reasons wd-de-others-reasons">
                        <?php foreach ( $custom_reasons as $reason ) { ?>
                            <li data-placeholder="<?php echo esc_attr( $reason['placeholder'] ); ?>" data-customreason="true">
                                <label>
                                    <input type="radio" name="selected-reason" value="<?php echo $reason['id']; ?>">
                                    <div class="wd-de-reason-icon"><?php echo $reason['icon']; ?></div>
                                    <div class="wd-de-reason-text"><?php echo $reason['text']; ?></div>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                    <?php endif; ?>
                    <div class="wd-dr-modal-reason-input"><textarea></textarea></div>
                    <p class="wd-dr-modal-reasons-bottom">
                       <?php
                       echo sprintf(
	                       $this->client->__trans( 'We share your data with <a href="%1$s" target="_blank">Appsero</a> to troubleshoot problems &amp; make product improvements. <a href="%2$s" target="_blank">Learn more</a> about how Appsero handles your data.'),
	                       esc_url( 'https://appsero.com/' ),
                           esc_url( 'https://appsero.com/privacy-policy' )
                       );
                       ?>
                    </p>
                </div>

                <div class="wd-dr-modal-footer">
                    <a href="#" class="dont-bother-me wd-dr-button-secondary"><?php $this->client->_etrans( "Skip & Deactivate" ); ?></a>
                    <button class="wd-dr-button-secondary wd-dr-cancel-modal"><?php $this->client->_etrans( 'Cancel' ); ?></button>
                    <button class="wd-dr-submit-modal"><?php $this->client->_etrans( 'Submit & Deactivate' ); ?></button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
            (function($) {
                $(function() {
                    var modal = $( '#<?php echo $this->client->slug; ?>-wd-dr-modal' );
                    var deactivateLink = '';

                    // Open modal
                    $( '#the-list' ).on('click', 'a.<?php echo $this->client->slug; ?>-deactivate-link', function(e) {
                        e.preventDefault();

                        modal.addClass('modal-active');
                        deactivateLink = $(this).attr('href');
                        modal.find('a.dont-bother-me').attr('href', deactivateLink).css('float', 'left');
                    });

                    // Close modal; Cancel
                    modal.on('click', 'button.wd-dr-cancel-modal', function(e) {
                        e.preventDefault();
                        modal.removeClass('modal-active');
                    });

                    // Reason change
                    modal.on('click', 'input[type="radio"]', function () {
                        var parent = $(this).parents('li');
                        var isCustomReason = parent.data('customreason');
                        var inputValue = $(this).val();

                        if ( isCustomReason ) {
                            $('ul.wd-de-reasons.wd-de-others-reasons li').removeClass('wd-de-reason-selected');
                        } else {
                            $('ul.wd-de-reasons li').removeClass('wd-de-reason-selected');

                            if ( "other" != inputValue ) {
                                $('ul.wd-de-reasons.wd-de-others-reasons').css('display', 'none');
                            }
                        }

                        // Show if has custom reasons
                        if ( "other" == inputValue ) {
                            $('ul.wd-de-reasons.wd-de-others-reasons').css('display', 'flex');
                        }

                        parent.addClass('wd-de-reason-selected');
                        $('.wd-dr-modal-reason-input').show();

                        $('.wd-dr-modal-reason-input textarea').attr('placeholder', parent.data('placeholder')).focus();
                    });

                    // Submit response
                    modal.on('click', 'button.wd-dr-submit-modal', function(e) {
                        e.preventDefault();

                        var button = $(this);

                        if ( button.hasClass('disabled') ) {
                            return;
                        }

                        var $radio = $( 'input[type="radio"]:checked', modal );
                        var $input = $('.wd-dr-modal-reason-input textarea');

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                nonce: '<?php echo wp_create_nonce( 'appsero-security-nonce' ); ?>',
                                action: '<?php echo $this->client->slug; ?>_submit-uninstall-reason',
                                reason_id: ( 0 === $radio.length ) ? 'none' : $radio.val(),
                                reason_info: ( 0 !== $input.length ) ? $input.val().trim() : ''
                            },
                            beforeSend: function() {
                                button.addClass('disabled');
                                button.text('Processing...');
                            },
                            complete: function() {
                                window.location.href = deactivateLink;
                            }
                        });
                    });
                });
            }(jQuery));
        </script>

        <?php
    }

    /**
     * Run after theme deactivated
     * @param  string $new_name
     * @param  object $new_theme
     * @param  object $old_theme
     * @return void
     */
    public function theme_deactivated( $new_name, $new_theme, $old_theme ) {
        // Make sure this is appsero theme
        if ( $old_theme->get_template() == $this->client->slug ) {
            $this->client->send_request( $this->get_tracking_data(), 'deactivate' );
        }
    }

    /**
     * Get user IP Address
     */
    private function get_user_ip_address() {
        $response = wp_remote_get( 'https://icanhazip.com/' );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $ip = trim( wp_remote_retrieve_body( $response ) );

        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return '';
        }

        return $ip;
    }

    /**
     * Get site name
     */
    private function get_site_name() {
        $site_name = get_bloginfo( 'name' );

        if ( empty( $site_name ) ) {
            $site_name = get_bloginfo( 'description' );
            $site_name = wp_trim_words( $site_name, 3, '' );
        }

        if ( empty( $site_name ) ) {
            $site_name = esc_url( home_url() );
        }

        return $site_name;
    }

    /**
     * Send request to appsero if user skip to send tracking data
     */
    private function send_tracking_skipped_request() {
        $skipped = get_option( $this->client->slug . '_tracking_skipped' );

        $data = array(
            'hash'               => $this->client->hash,
            'previously_skipped' => false,
        );

        if ( $skipped === 'yes' ) {
            $data['previously_skipped'] = true;
        } else {
            update_option( $this->client->slug . '_tracking_skipped', 'yes' );
        }

        $this->client->send_request( $data, 'tracking-skipped' );
    }

    /**
     * Deactivation modal styles
     */
    private function deactivation_modal_styles() {
        ?>
        <style type="text/css">
            .wd-dr-modal {
                position: fixed;
                z-index: 99999;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                background: rgba(0,0,0,0.5);
                display: none;
                box-sizing: border-box;
                overflow: scroll;
            }
            .wd-dr-modal * {
                box-sizing: border-box;
            }
            .wd-dr-modal.modal-active {
                display: block;
            }
            .wd-dr-modal-wrap {
                max-width: 870px;
                width: 100%;
                position: relative;
                margin: 10% auto;
                background: #fff;
            }
            .wd-dr-modal-header {
                border-bottom: 1px solid #E8E8E8;
                padding: 20px 20px 18px 20px;
            }
            .wd-dr-modal-header h3 {
                line-height: 1.8;
                margin: 0;
                color: #4A5568;
            }
            .wd-dr-modal-body {
                padding: 5px 20px 20px 20px;
            }
            .wd-dr-modal-body .reason-input {
                margin-top: 5px;
                margin-left: 20px;
            }
            .wd-dr-modal-footer {
                border-top: 1px solid #E8E8E8;
                padding: 20px;
                text-align: right;
            }
            .wd-dr-modal-reasons-bottom {
                margin: 0;
            }
            ul.wd-de-reasons {
                display: flex;
                margin: 0 -5px 0 -5px;
                padding: 15px 0 20px 0;
            }
            ul.wd-de-reasons.wd-de-others-reasons {
                padding-top: 0;
                display: none;
            }
            ul.wd-de-reasons li {
                padding: 0 5px;
                margin: 0;
                width: 14.26%;
            }
            ul.wd-de-reasons label {
                position: relative;
                border: 1px solid #E8E8E8;
                border-radius: 4px;
                display: block;
                text-align: center;
                height: 100%;
                padding: 15px 3px 8px 3px;
            }
            ul.wd-de-reasons label:after {
                width: 0;
                height: 0;
                border-left: 8px solid transparent;
                border-right: 8px solid transparent;
                border-top: 10px solid #3B86FF;
                position: absolute;
                left: 50%;
                top: 100%;
                margin-left: -8px;
            }
            ul.wd-de-reasons label input[type="radio"] {
                position: absolute;
                left: 0;
                right: 0;
                visibility: hidden;
            }
            .wd-de-reason-text {
                color: #4A5568;
                font-size: 13px;
            }
            .wd-de-reason-icon {
                margin-bottom: 7px;
            }
            ul.wd-de-reasons li.wd-de-reason-selected label {
                background-color: #3B86FF;
                border-color: #3B86FF;
            }
            li.wd-de-reason-selected .wd-de-reason-icon svg,
            li.wd-de-reason-selected .wd-de-reason-icon svg g {
                fill: #fff;
            }
            li.wd-de-reason-selected .wd-de-reason-text {
                color: #fff;
            }
            ul.wd-de-reasons li.wd-de-reason-selected label:after {
                content: "";
            }
            .wd-dr-modal-reason-input {
                margin-bottom: 15px;
                display: none;
            }
            .wd-dr-modal-reason-input textarea {
                background: #FAFAFA;
                border: 1px solid #287EB8;
                border-radius: 4px;
                width: 100%;
                height: 100px;
                color: #524242;
                font-size: 13px;
                line-height: 1.4;
                padding: 11px 15px;
                resize: none;
            }
            .wd-dr-modal-reason-input textarea:focus {
                outline: 0 none;
                box-shadow: 0 0 0;
            }
            .wd-dr-button-secondary, .wd-dr-button-secondary:hover {
                border: 1px solid #EBEBEB;
                border-radius: 3px;
                font-size: 13px;
                line-height: 1.5;
                color: #718096;
                padding: 5px 12px;
                cursor: pointer;
                background-color: transparent;
                text-decoration: none;
            }
            .wd-dr-submit-modal, .wd-dr-submit-modal:hover {
                border: 1px solid #3B86FF;
                background-color: #3B86FF;
                border-radius: 3px;
                font-size: 13px;
                line-height: 1.5;
                color: #fff;
                padding: 5px 12px;
                cursor: pointer;
                margin-left: 4px;
            }
        </style>
        <?php
    }

}
