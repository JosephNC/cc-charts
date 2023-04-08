<?php
/**
 * Plugin Name: CC Charts
 * Description: A simple plugin that display recharts' graph.
 * Version: 1.0
 * Author Name: JosephNC
 * Author URI: https://github.com/josephnc
 * Plugin URI: https://github.com/josephnc/cc-charts
 * Text Domain: cc-charts
 * Requires at least: 5.0
 * Requires PHP: 8.0
 */

class CC_Charts
{
    /**
     * Plugin name
     * @var string|null
     */
    public string|null $name = null;

    /**
     * Plugin slug/text domain
     * @var string|null
     */
    public string|null $slug = null;

    /**
     * Plugin uri
     * @var string|null
     */
    public string|null $uri = null;

    /**
     * Plugin path
     * @var string|null
     */
    public string|null $path = null;

    /**
     * Plugin version
     * @var string|null
     */
    public string|null $version = null;

    /**
     * Plugin table name
     * @var string|null
     */
    public string|null $table_name = null;

    public function __construct()
    {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'cc_charts';

        add_action( 'init', [ $this, 'init' ], 9999 );
        add_action( 'rest_api_init', [ $this, 'rest_api' ], 9999 );
        add_action( 'wp_dashboard_setup', [ $this, 'dashboard_widget' ], 9999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'scripts' ] );

        add_filter( 'script_loader_tag', [ $this, 'filter_scripts' ], 9999, 3 );

        register_activation_hook(__FILE__, [$this, 'create_db_table']);
        register_activation_hook(__FILE__, [$this, 'create_table_data']);
    }

    /**
     * Init
     *
     * @return void
     */
    public function init()
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $plugin_data = get_plugin_data( __FILE__ );

        // Register properties
        $this->name = $plugin_data[ 'Name' ];
        $this->slug = $plugin_data[ 'TextDomain' ];
        $this->version = $plugin_data[ 'Version' ];
        $this->uri = plugin_dir_url( __FILE__ );
        $this->path = plugin_dir_path( __FILE__ );
    }

    /**
     * Init
     *
     * @return void
     */
    public function rest_api(): void
    {
        register_rest_route( $this->slug . '/v1', '/data/(?P<days>\d+)', [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => [ $this, 'rest_api_callback' ],
            'args' => [
                'days' => [
                    'validate_callback' => fn($param, $request, $key) => is_numeric( $param )
                ],
            ],
        ]);
    }

    public function rest_api_callback( \WP_REST_Request $request ): WP_Error|WP_REST_Response|WP_HTTP_Response
    {
        global $wpdb;

        $table = $this->table_name;

        $allowed = [ 7, 15, 30 ];

        $days = (int) $request->get_param( 'days' );

        if ( ! in_array( $days, $allowed, true ) )
            return new \WP_Error( 'invalid_argument', __( 'Invalid no of days specified.', 'cc-charts' ) );

        $days_ago = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

        $data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE `date` >= %s AND `date` < %s",
                [ $days_ago, current_time( 'mysql' ) ]
            ),
            ARRAY_A
        );

        return rest_ensure_response( $data );
    }

    public function dashboard_widget(): void
    {
        wp_add_dashboard_widget(
            $this->slug . '_dashboard_widget',
            esc_html__( 'CC Chart', 'cc-charts' ),
            [ $this, 'dashboard_widget_render' ]
        );
    }

    public function dashboard_widget_render(): void
    {
        ?>
        <div id="<?php echo $this->slug . '_widget' ?>"></div>
        <?php
    }

    public function scripts(): void
    {
        wp_enqueue_style(
            $this->slug . '-main-css',
            $this->uri . 'main.css',
            [],
            time()
        );

        wp_enqueue_script(
            $this->slug . '-babel-js',
            'https://unpkg.com/@babel/standalone/babel.min.js',
            [],
            time(),
            true
        );

        // Only needed for development

        /*
        wp_enqueue_script(
            $this->slug . '-react-js',
            'https://unpkg.com/react/umd/react.development.js',
            [],
            time(),
            true
        );

        wp_enqueue_script(
            $this->slug . '-react-dom-js',
            'https://unpkg.com/react-dom/umd/react-dom.development.js',
            [],
            time(),
            true
        );
        */

        wp_enqueue_script(
            $this->slug . '-prop-types-js',
            'https://unpkg.com/prop-types/prop-types.min.js',
            // $this->slug . '-react-js', $this->slug . '-react-dom-js' ], // Only needed for dev
            [ 'wp-element' ],
            time(),
            true
        );

        wp_enqueue_script(
            $this->slug . '-recharts-js',
            'https://unpkg.com/recharts/umd/Recharts.js',
            [ $this->slug . '-prop-types-js' ],
            time(),
            true
        );

        wp_enqueue_script(
            $this->slug . '-main-js',
            $this->uri . 'main.js',
            [ $this->slug . '-babel-js', $this->slug . '-recharts-js' ],
            time(),
            true
        );
    }

    public function filter_scripts( $tag, $handle, $src )
    {
        if ( $this->slug . '-main-js' !== $handle ) return $tag;

        return '<script type="text/babel" src="' . esc_url( $src ) . '"></script>';
    }

    public function create_db_table(): void
    {
        global $wpdb;

        $table_name = $this->table_name;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            uv bigint(10) NOT NULL,
            pv bigint(10) NOT NULL,
            amt bigint(10) NOT NULL,
            date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }

    public function create_table_data(): void
    {
        global $wpdb;

        $table_name = $this->table_name;

        // If total table rows is less than 30
        if ( count( $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A ) ) >= 30 )
            return;

        for ($i = 0; $i < 10; $i++) {
            $number = rand( 2, 30 );

            $wpdb->insert( $table_name, [
                'name' => $this->generate_random_string( 5 ),
                'uv' => rand(1000, 5000),
                'pv' => rand(1000, 5000),
                'amt' => rand(1000, 5000),
                'date' => date( 'Y-m-d H:i:s', strtotime( "-$number days" ) )
            ] );
        }
    }

    public function generate_random_string($length = 10): string
    {
        $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $string = str_repeat( $x, ceil( $length / strlen( $x ) ) );
        $string = str_shuffle( $string );
        return substr( $string,1, $length);
    }
}

new CC_Charts();