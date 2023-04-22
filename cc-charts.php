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

class CC_Charts {
	/**
	 * Plugin name
	 *
	 * @var string|null
	 */
	public string|null $name = null;

	/**
	 * Plugin slug/text domain
	 *
	 * @var string|null
	 */
	public string|null $slug = null;

	/**
	 * Plugin uri
	 *
	 * @var string|null
	 */
	public string|null $uri = null;

	/**
	 * Plugin path
	 *
	 * @var string|null
	 */
	public string|null $path = null;

	/**
	 * Plugin version
	 *
	 * @var string|null
	 */
	public string|null $version = null;

	/**
	 * Plugin table name
	 *
	 * @var string|null
	 */
	public string|null $table_name = null;

	/**
	 * Class constructor
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name = $wpdb->prefix . 'cc_charts';

		add_action( 'init', array( $this, 'init' ), 9999 );
		add_action( 'rest_api_init', array( $this, 'rest_api' ), 9999 );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_widget' ), 9999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );

		add_filter( 'script_loader_tag', array( $this, 'filter_scripts' ), 9999, 3 );

		register_activation_hook( __FILE__, array( $this, 'create_db_table' ) );
		register_activation_hook( __FILE__, array( $this, 'create_table_data' ) );
	}

	/**
	 * Init
	 *
	 * @return void
	 */
	public function init() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$plugin_data = get_plugin_data( __FILE__ );

		// Register properties.
		$this->name    = $plugin_data['Name'];
		$this->slug    = $plugin_data['TextDomain'];
		$this->version = $plugin_data['Version'];
		$this->uri     = plugin_dir_url( __FILE__ );
		$this->path    = plugin_dir_path( __FILE__ );
	}

	/**
	 * Init
	 *
	 * @return void
	 */
	public function rest_api() {
		register_rest_route(
			$this->slug . '/v1',
			'/data/(?P<days>\d+)',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'rest_api_callback' ),
				'permission_callback' => array( $this, 'rest_api_permission_callback' ),
				'args'     => array(
					'days' => array(
						'validate_callback' => fn( $param ) => is_numeric( $param ),
					),
				),
			),
		);
	}

	/**
	 * Check REST API permission
	 */
	public function rest_api_permission_callback() {

		// Restrict endpoint editors or admins
		if ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			esc_html__( 'You are not authorized to view this chart.', 'cc-charts' ),
			array( 'status' => 401 )
		);
	}

	public function rest_api_callback( \WP_REST_Request $request ) {
		global $wpdb;

		$table = $this->table_name;

		$allowed = array( 7, 15, 30 );

		$days = (int) $request->get_param( 'days' );

		if ( ! in_array( $days, $allowed, true ) ) {
			return new \WP_Error( 'invalid_argument', __( 'Invalid no of days specified.', 'cc-charts' ) );
		}

		$days_ago = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		$cache_key = $this->slug . '_rest_api_data_' . $days_ago;

		$data = wp_cache_get( $cache_key );

		if ( ! $data || empty( (array) $data ) ) {
			$data = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $table WHERE `date` >= %s AND `date` < %s",
					array(
						$days_ago,
						current_time( 'mysql' ),
					),
				),
				ARRAY_A,
			);

			wp_cache_set( $cache_key, $data );
		}

		return rest_ensure_response( (array) $data );
	}

	public function dashboard_widget() {
		wp_add_dashboard_widget(
			$this->slug . '_dashboard_widget',
			esc_html__( 'CC Chart', 'cc-charts' ),
			array( $this, 'dashboard_widget_render' ),
		);
	}

	public function dashboard_widget_render() {
		?>
		<div id="<?php echo esc_attr( $this->slug . '_widget' ); ?>"></div>
		<?php
	}

	/**
	 * Enqueue admin scripts
	 * 
	 * @param string $hook_suffix
	 */
	public function scripts( $hook_suffix ) {
		// Only enqueue on dashboard page
		if ( $hook_suffix !== 'index.php' && get_current_screen()?->id !== 'dashboard' ) {
			return;
		}

		wp_enqueue_script(
			$this->slug . '-babel-js',
			'https://unpkg.com/@babel/standalone/babel.min.js',
			array(),
			time(),
			true,
		);

		wp_enqueue_script(
			$this->slug . '-prop-types-js',
			'https://unpkg.com/prop-types/prop-types.min.js',
			array( 'wp-element', 'wp-components', 'wp-i18n' ),
			time(),
			true,
		);

		wp_enqueue_script(
			$this->slug . '-recharts-js',
			'https://unpkg.com/recharts/umd/Recharts.js',
			array( $this->slug . '-prop-types-js' ),
			time(),
			true,
		);

		wp_enqueue_script(
			$this->slug . '-main-js',
			$this->uri . 'main.js',
			array( $this->slug . '-babel-js', $this->slug . '-recharts-js' ),
			time(),
			true,
		);

		// Set translation script.
		wp_set_script_translations(
			$this->slug . '-translation',
			'cc-charts',
			$this->path . 'languages'
		);
	}

	public function filter_scripts( $tag, $handle, $src ) {
		if ( $this->slug . '-main-js' !== $handle ) {
			return $tag;
		}

		return '<script type="text/babel" src="' . esc_url( $src ) . '"></script>';
	}

	public function create_db_table() {
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

		dbDelta( $sql );
	}

	public function create_table_data() {
		global $wpdb;

		$table_name = $this->table_name;

		// If total table rows is less than 30.
		$cache_key = $this->slug . '_table_data_';

		$results = wp_cache_get( $cache_key );

		if ( ! $results ) {
			$results = $wpdb->get_results( "SELECT * FROM $table_name", ARRAY_A );

			wp_cache_set( $cache_key, $results );
		}

		if ( count( (array) $results ) >= 30 ) {
			return;
		}

		for ( $i = 0; $i < 10; $i++ ) {
			$number = wp_rand( 2, 30 );

			$wpdb->insert(
				$table_name,
				array(
					'name' => $this->generate_random_string( 5 ),
					'uv'   => wp_rand( 1000, 5000 ),
					'pv'   => wp_rand( 1000, 5000 ),
					'amt'  => wp_rand( 1000, 5000 ),
					'date' => gmdate( 'Y-m-d H:i:s', strtotime( "-$number days" ) ),
				),
			);
		}
	}

	public function generate_random_string( $length = 10 ) {
		$x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

		$string = str_repeat( $x, ceil( $length / strlen( $x ) ) );
		$string = str_shuffle( $string );

		return substr( $string, 1, $length );
	}
}

new CC_Charts();
