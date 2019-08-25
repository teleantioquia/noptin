<?php
/**
 * Admin section
 *
 * Simple WordPress optin form
 *
 * @since             1.0.0
 *
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    die;
}

/**
 * Admin main class
 *
 * @since       1.0.0
 */

class Noptin_Admin {

    /**
     * Local path to this plugins admin directory
     * @access      public
     * @since       1.0.0
     */
    public $admin_path = null;

    /**
     * Web path to this plugins admin directory
     * @access      public
     * @since       1.0.0
     */
    public $admin_url = null;

    /**
     * @access      private
     * @var        obj $instance The one true noptin
     * @since       1.0.0
     */
    private static $instance = null;

    /**
     * Get active instance
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public static function instance() {

        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Class Constructor.
     */
    public function __construct() {

        /**
         * Runs right before admin module loads.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_admin_load', $this);

        //Set global variables
        $noptin = noptin();
        $this->admin_path  = plugin_dir_path(__FILE__);
        $this->admin_url   = plugins_url('/', __FILE__);
        $this->assets_url  = $noptin->plugin_url . 'includes/assets/';
		$this->assets_path = $noptin->plugin_path . 'includes/assets/';
		$this->new_posts_notifier = new Noptin_New_Post_Notify();

        // Include core files
		$this->includes();

        //initialize hooks
        $this->init_hooks();

        /**
         * Runs right after admin module loads.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_admin_loaded', $this);
    }

    /**
     * Include necessary files
     *
     * @access      public
     * @since       1.0.0
     * @return      void
     */
    private function includes() {

		//Settings
		require_once $this->admin_path . 'settings.php';

        // Include the rating hooks
        require_once $this->admin_path . 'ratings.php';

        //Editor
        require_once $this->admin_path . 'forms-editor.php';

		//notifications
		require_once $this->admin_path . 'forms-editor-quick.php';

        /**
         * Runs right after including admin files.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_admin_includes', $this);
    }

    /**
     * Run action and filter hooks
     *
     * @access      private
     * @since       1.0.0
     * @return      void
     */
    private function init_hooks() {

        /**
         * Runs right before registering admin hooks.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_admin_init_hooks', $this);

        //Admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqeue_scripts'));

        //(maybe) do an action
        add_action('admin_init', array($this, 'maybe_do_action'));

        //Register new menu pages
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action( 'admin_head', array($this, 'remove_menus') );

        //Runs when fetching select2 options
        add_action('wp_ajax_noptin_select_ajax', array($this, 'select_ajax'));

        //Runs when saving a new opt-in form
        add_action('wp_ajax_noptin_save_optin_form', array($this, 'save_optin_form'));

        //Runs when saving a form as a template
        add_action('wp_ajax_noptin_save_optin_form_as_template', array($this, 'save_optin_form_as_template'));

		//Maybe notify subscribers of new posts publish_$post_types
		add_action('publish_post', array($this, 'notify_new_post'));

        /**
         * Runs right after registering admin hooks.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_admin_init_hooks', $this);
    }


    /**
     * Register admin scripts
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function enqeue_scripts() {
        global $pagenow;

        //Only enque on our pages
        if( 'admin.php' != $pagenow || empty( $_GET['page'] ) || false === stripos( $_GET['page'], 'noptin') ){
            return;
        }

        //Admin styles
        $version = filemtime( $this->assets_path . 'css/admin.css' );
        wp_enqueue_style('noptin', $this->assets_url . 'css/admin.css', array(), $version);

        //Tooltips https://iamceege.github.io/tooltipster/
        wp_enqueue_script('tooltipster', $this->assets_url . 'js/tooltipster.bundle.min.js', array( 'jquery' ), '4.2.6');
        wp_enqueue_style('tooltipster', $this->assets_url . 'css/tooltipster.bundle.min.css', array(), '4.2.6');

        //Slick selects https://designwithpc.com/Plugins/ddSlick#demo
        wp_enqueue_script('slick', $this->assets_url . 'js/jquery.ddslick.min.js', array( 'jquery' ), '4.2.6');
		wp_enqueue_style('slick', $this->assets_url . 'css/slick.css', array(), '4.2.6');

		wp_enqueue_script('select2', $this->assets_url . 'js/select2.js', array( 'jquery' ), '4.0.9');

        //Enque media for image uploads
        wp_enqueue_media();

        //Codemirror for editor css
        wp_enqueue_code_editor(
			array(
                'type'       => 'css',
                'codemirror' => array(
                    'indentUnit'        => 1,
                    'tabSize'           => 4,
                    'indentWithTabs'    =>  true,
                    'lineNumbers'       => false,
                ),
            )
		);

        //Vue js
        wp_enqueue_script('vue', $this->assets_url . 'js/vue.js', array(), '2.6.10');

        //Custom admin scripts
        $version = filemtime( $this->assets_path . 'js/admin-bundled.js' );
        wp_register_script('noptin', $this->assets_url . 'js/admin-bundled.js', array('vue'), $version, true);

        // Pass variables to our js file, e.g url etc
        $params = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'api_url' => get_home_url( null, 'wp-json/wp/v2/'),
			'nonce'   => wp_create_nonce('noptin_admin_nonce'),
			'icon'    => $this->assets_url . 'images/checkmark.png',
        );

        // localize and enqueue the script with all of the variable inserted
		wp_localize_script('noptin', 'noptin_params', $params);

		if(! empty( $_GET['page'] ) && 'noptin-settings' == $_GET['page'] ) {
			wp_localize_script('noptin', 'noptinSettings', Noptin_Settings::get_state());
		}

        wp_enqueue_script('noptin');
    }

    /**
     * Register admin page
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function add_menu_page() {

        //The main admin page
        add_menu_page(
            'Noptin',
            'Noptin',
            'manage_options',
            'noptin',
            array($this, 'render_main_page'),
            'dashicons-forms',
            67);

        //Add the optin forms page
        add_submenu_page(
            'noptin',
            esc_html__('Newsletter opt-in forms', 'noptin'),
            esc_html__('Newsletter Forms', 'noptin'),
            'manage_options',
            'noptin-forms',
            array($this, 'render_forms_page')
        );

        //Link to new forms creation page
        add_submenu_page(
            'noptin',
            esc_html__('Add New Form', 'noptin'),
            esc_html__('Add New Form', 'noptin'),
            'manage_options',
            'noptin-new-form',
            array($this, 'render_add_new_page')
		);


        //Add the subscribers page
        add_submenu_page(
            'noptin',
            esc_html__('Subscribers', 'noptin'),
            esc_html__('Subscribers', 'noptin'),
            'manage_options',
            'noptin-subscribers',
            array($this, 'render_subscribers_page')
		);

		//Settings
        add_submenu_page(
            'noptin',
            esc_html__('Settings', 'noptin'),
            esc_html__('Settings', 'noptin'),
            'manage_options',
            'noptin-settings',
            array($this, 'render_settings_page')
		);

		do_action( 'noptin_after_register_menus', $this );

		//Link to documentation
        add_submenu_page(
            'noptin',
            esc_html__('Documentation', 'noptin'),
            esc_html__('Documentation', 'noptin'),
            'manage_options',
            'noptin-docs',
            array($this, 'render_add_new_page')
		);

		//Welcome page
		add_dashboard_page(
            esc_html__('Noptin Welcome', 'noptin-mailchimp'),
            esc_html__('Noptin Welcome', 'noptin-mailchimp'),
            'read',
            'noptin-welcome',
            array($this, 'welcome_screen_content')
        );
	}

	/**
     * Renders main admin page
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function remove_menus() {
		remove_submenu_page( 'index.php', 'noptin-welcome' );
	}

	/**
     * Display the welcome page
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function welcome_screen_content() {
        include $this->admin_path . 'welcome-screen.php';
    }

    /**
     * Renders main admin page
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function render_main_page() {

        if (!current_user_can('manage_options')) {
            return;
        }

        /**
         * Runs before displaying the main menu page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_admin_main_page', $this);

		$today_date				 = date("Y-m-d");
        $forms_url    	   		 = esc_url( get_noptin_forms_overview_url() );
		$new_form_url 	   	     = esc_url( get_noptin_new_form_url() );
		$subscribers_url   		 = esc_url( get_noptin_subscribers_overview_url() );
		$subscribers_total       = get_noptin_subscribers_count();
		$subscribers_today_total = get_noptin_subscribers_count( "`date_created`='$today_date'" );
		$subscribers_growth_rate = get_noptin_subscribers_growth();

		$popups = noptin_count_optin_forms('popup');
		$inpost = noptin_count_optin_forms('inpost');
		$widget = noptin_count_optin_forms('sidebar');


        include $this->admin_path . 'welcome.php';

        /**
         * Runs after displaying the main menu page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_admin_main_page', $this);
    }

    /**
     * Renders view subscribers page
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function render_subscribers_page() {

        if (!current_user_can('manage_options')) {
            return;
        }

        /**
         * Runs before displaying the suscribers page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_admin_subscribers_page', $this);

        $download_url = add_query_arg(
            array(
                'action' => 'noptin_download_subscribers',
                'admin_nonce' => urlencode(wp_create_nonce('noptin_admin_nonce')),
            ),
            admin_url('admin-ajax.php')
        );
        $logo_url    = $this->assets_url . 'images/logo.png';
        $subscribers = $this->get_subscribers();
        include $this->admin_path . 'subscribers.php';

        /**
         * Runs after displaying the subscribers page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_admin_subscribers_page', $this);
    }

    /**
     * Renders forms page
     *
     * @access      public
     * @since       1.0.4
     * @return      self::$instance
     */
    public function render_forms_page() {

        if (!current_user_can('manage_options')) {
            return;
        }

        /**
         * Runs before displaying the forms page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_admin_forms_page', $this);

        //The optin form currently being edited
        $form = false;

        //Is the user trying to edit a new optin form?
        if( isset( $_GET['form_id'] ) ){
            $form   = absint( $_GET['form_id'] );
        }

        if( $form ){
            if( empty( $_GET['created']) && empty( $_GET['editor_quick']) ) {
                $editor = new Noptin_Form_Editor( $form, true );
            } else {
				//@todo $editor = new Noptin_Form_Editor_Quick( $form, true );
				$editor = new Noptin_Form_Editor( $form, true );
            }
            $editor->output();
        } else {

            //Fetch forms
            $forms = noptin_get_optin_forms();

            //No forms?
            if(! $forms ){

                //Ask the user to add some
                include $this->admin_path . 'templates/forms-empty.php';

            } else {

                //Show them to the user
                include $this->admin_path . 'templates/forms-list.php'; //welcome

            }


        }


        /**
         * Runs after displaying the forms page.
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_admin_forms_page', $this);
    }

    /**
     * Renders add_new page
     *
     * @access      public
     * @since       1.0.4
     * @return      self::$instance
     */
    public function render_add_new_page(){
        wp_redirect( admin_url("admin.php?page=noptin-forms&action=new"), 301 );
	    exit;
	}

	/**
     * Renders the settings page
     *
     * @access      public
     * @since       1.0.6
     * @return      self::$instance
     */
    public function render_settings_page(){
		Noptin_Settings::output();
	}

    /**
     * Downloads subscribers
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function select_ajax() {

        if (!current_user_can('manage_options')) {
            wp_send_json( array() );
        }

        //Check nonce
        check_ajax_referer( 'noptin_admin_nonce' );

        /**
         * Runs before fetching select options ajax
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_select_ajax', $this);

        $items  = empty( $_GET['items'] ) ? 'all_posts' : trim( $_GET['items'] );

        //Currently we only support all posts
        if( $items != 'all_posts' ) {
            wp_send_json( array() );
        }

        //Prepare the query args
        $query  = array(
            'post_type'             => array_keys( noptin_get_post_types() ),
            'post_status'           => 'publish',
            'posts_per_page'        => 10,
            'paged'                 => empty( $_GET['page'] ) ? 1 : intval( trim( $_GET['page'] ) ) ,
            'ignore_sticky_posts'   => true,
            'order'                 => 'ASC',
            'orderby'               => 'title'
        );

        //Maybe include a search term
        $search = empty( $_GET['term'] ) ? '' : trim( $_GET['term'] );
        if(! empty( $search ) ) {
            $query['orderby'] = 'relevance';
            $query['order'] = 'DESC';
            $query['s'] = $search;
        }

        //Retrieve the posts from the db
        $query  = new WP_Query( $query );
        $posts  = array(
            'results' => array()
        );

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                $posts['results'][] = array(
                    'id'            => $query->post->ID,
                    'text'          => "[{$query->post->post_type}] " . get_the_title( $query->post->ID ),
                );
            }

            // Restore original Post Data
            wp_reset_postdata();
        }

        //Pagination parameters
        if( count( $posts['results'] ) == 10 ) {
            $posts['pagination'] = array( 'more' => true );
        }

        wp_send_json( $posts );
        exit; //This is important
}

/**
     * Downloads subscribers
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function save_optin_form() {

        if (!current_user_can('manage_options')) {
            return;
        }

        //Check nonce
        check_ajax_referer( 'noptin_admin_nonce' );

        /**
         * Runs before saving a form
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_save_form', $this);

        //Prepare the args
        $ID        = trim( $_POST['state']['id'] );
		$state     = $_POST['state'];
		$status    = 'draft';

		if( 'true' == $state['optinStatus'] ) {
			$status = 'publish';
		}

        $postarr   = array(
            'post_title'        => $state['optinName'],
            'ID'                => $ID,
            'post_content'      => $_POST['html'],
            'post_status'       => $status,
        );

        $post = wp_update_post( $postarr, true );
        if( is_wp_error( $post ) ) {
            status_header(400);
            die( $post->get_error_message() );
		}

		if( empty( $_POST['state']['showPostTypes'] ) ) {
			$_POST['state']['showPostTypes'] = array();
		}

        update_post_meta( $ID, '_noptin_state', $_POST['state'] );
        update_post_meta( $ID, '_noptin_optin_type', $_POST['state']['optinType'] );

        /**
         * Runs after saving a form
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_save_form', $this);

    exit; //This is important
}

    /**
     * Saves an optin form as a template
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function save_optin_form_as_template() {

        if (!current_user_can('manage_options')) {
            return;
        }

        //Check nonce
        check_ajax_referer( 'noptin_admin_nonce' );

        /**
         * Runs before saving a form as a template
         *
         * @param array $this The admin instance
         */
        do_action('noptin_before_save_form_as_template', $this);

        $templates = get_option( 'noptin_templates' );

        if(! is_array( $templates ) ) {
            $templates = array();
        }

        $fields = noptin_get_form_design_props();
        $data   = array();

        foreach( $fields as $field ){

			if( 'optinType' == $field ) {
				continue;
			}

            if( isset( $_POST['state'][$field] ) ) {

                $value = $_POST['state'][$field];

                if( 'false' == $value ) {
                    $data[$field] = false;
                    continue;
                }

                if( 'true' == $value ) {
                    $data[$field] = true;
                    continue;
                }

                $data[$field] = $value;
            }
        }

		$title = sanitize_text_field( $_POST['state']['optinName'] );
		$key   = wp_generate_password( '4', false ) . time();
		$templates[ $key ] = array(
			'title' => $title,
			'data'  => $data,
		);

        update_option( 'noptin_templates', $templates );

        /**
         * Runs after saving a form as a template
         *
         * @param array $this The admin instance
         */
        do_action('noptin_after_save_form_as_template', $this);

    exit; //This is important
}




    /**
     * Retrieves the subscribers list,, limited to 100
     *
     * @access      public
     * @since       1.0.0
     * @return      self::$instance
     */
    public function get_subscribers() {
        global $wpdb;

        $table = $wpdb->prefix . 'noptin_subscribers';
        $sql = "SELECT *
                    FROM $table
                    ORDER BY date_created DESC
                    LIMIT 100";
        return $wpdb->get_results($sql);

	}

	/**
     * Notify subscribers of new posts
     *
     * @access      public
     * @since       1.0.6
     */
    public function notify_new_post( $post_id ) {

		//If a notification has already been send abort...
		if( get_post_meta( $post_id, 'noptin_subscribers_notified_of_post', true) ) {
			return;
		}

		// abort if we are not sending out new post notifications
		if(! get_noptin_option('notify_new_post') ) {
			return;
		}

		update_post_meta( $post_id, 'noptin_subscribers_notified_of_post', '1');

		$this->new_posts_notifier->data( array( 'post' => $post_id ) )->dispatch();

    }


    /**
     * Does an action
     *
     * @access      public
     * @since       1.0.5
     * @return      self::$instance
     */
    public function maybe_do_action() {

		//Redirect to welcome page
		if (! get_option( '_noptin_has_welcomed', false ) && !wp_doing_ajax() ) {

			// Ensure were not activating from network, or bulk
			if (! is_network_admin() && !isset( $_GET['activate-multi'] ) ) {

				// Prevent further redirects
				update_option( '_noptin_has_welcomed', '1' );

				// Redirect to the welcome page
				wp_safe_redirect( add_query_arg( array( 'page' => 'noptin-welcome' ), admin_url( 'index.php' ) ) );
				exit;

			}


		}

        //New form creation
        if( isset( $_GET['page'] ) && 'noptin-new-form' == $_GET['page'] ) {
            wp_redirect( admin_url("admin.php?page=noptin-forms&action=new"), 301 );
	        exit;
		}

		//Docs page
        if( isset( $_GET['page'] ) && 'noptin-docs' == $_GET['page'] ) {
            wp_redirect( 'https://noptin.com/guide/', 301 );
	        exit;
        }

        //Ensure that this is our page...
        if(! isset( $_GET['page'] ) || 'noptin-forms' != $_GET['page'] ) {
            return;
        }

        //... and that there is an action
        if(! isset( $_GET['action'] ) ) {
            return;
        }

        //Is the user deleting an optin form?
        if( 'delete' == $_GET['action'] ){
            noptin_delete_optin_form( $_GET['delete'] );
            wp_safe_redirect( admin_url( 'admin.php?page=noptin-forms' ) );
            exit;
        }

        //Is the user duplicating an optin form?
        if( 'duplicate' == $_GET['action'] ){
            $form   = noptin_duplicate_optin_form( $_GET['duplicate'] );
            wp_safe_redirect( admin_url( "admin.php?page=noptin-forms&form_id=$form" ) );
            exit;
        }

        //Is the user creating a new optin form?
        if( 'new' == $_GET['action'] ){
            $form   = noptin_create_optin_form();
            if( is_int( $form ) ) {
                wp_safe_redirect( admin_url( "admin.php?page=noptin-forms&form_id=$form&created=1" ) );
                exit;
            }

            die( $form->get_error_message());
		}


    }

}
