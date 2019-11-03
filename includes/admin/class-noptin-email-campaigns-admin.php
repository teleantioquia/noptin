<?php

/**
 * Provides hooks for displaying various email campaign sections
 */

/**
 * email campaigns table class.
 */
class Noptin_Email_Campaigns_Admin {

	/**
	 *  Constructor function.
	 */
	function __construct() {

		//Display the newsletters page
		add_action( 'noptin_email_campaigns_tab_newsletters', array( $this, 'show_newsletters' ) );
		add_action( 'noptin_newsletters_section_view_campaigns', array( $this, 'view_newsletter_campaigns' ) );
		add_action( 'noptin_newsletters_section_new_campaign', array( $this, 'render_email_campaign_form' ) );
		add_action( 'noptin_newsletters_section_edit_campaign', array( $this, 'render_email_campaign_form' ) );

		//Display the automations page
		add_action( 'noptin_email_campaigns_tab_automations', array( $this, 'show_automations' ) );
		add_action( 'noptin_automations_section_view_campaigns', array( $this, 'view_automation_campaigns' ) );
		add_action( 'noptin_automations_section_edit_campaign', array( $this, 'render_automation_campaign_form' ) );

		//Maybe save campaigns
		add_action( 'wp_loaded', array( $this, 'maybe_save_campaign' ) );

		//Maybe send a campaign
		add_action( 'transition_post_status', array( $this, 'maybe_send_campaign' ), 100, 3 );

		//Delete campaign stats
		add_action( 'delete_post', array( $this, 'maybe_delete_stats' ) );

	}

	/**
	 *  Displays the newsletters section
	 */
	function show_newsletters() {

		$sub_section = empty( $_GET['sub_section'] )   ? 'view_campaigns' : $_GET['sub_section'];

		/**
    	 * Runs before displaying the newsletters section
    	 *
    	 */
		do_action( 'noptin_before_display_newsletters_section', $sub_section );

		/**
    	 * Runs when displaying a specific newsletters section.
    	 *
    	 */
		do_action( "noptin_newsletters_section_$sub_section" );

		/**
    	 * Runs after displaying the newsletters section
    	 *
    	 */
		do_action( 'noptin_after_display_newsletters_section', $sub_section );

	}

	/**
	 *  Displays a list of available newsletters
	 */
	function view_newsletter_campaigns() {
		$table = new Noptin_Email_Newsletters_Table();
		$table->prepare_items();

		$add_new_campaign_url = get_noptin_new_newsletter_campaign_url();

	?>
		<div class="wrap">
			<form id="noptin-newsletter-campaigns-table" method="GET">
				<input type="hidden" name="page" value="noptin-email-campaigns"/>
				<input type="hidden" name="section" value="newsletters"/>
				<div class="noptin-campaign-action-links">
					<a href="<?php echo $add_new_campaign_url; ?>" class="button-secondary create-new-campaign"><?php _e( 'Compose New Email', 'newsletter-optin-box' ); ?></a>
				</div>

				<?php $table->display(); ?>
				<p class="description"><?php _e( 'Use this page to send one-time emails to your email subscribers', 'newsletter-optin-box' );?></p>
			</form>
		</div>
	<?php

	}

	/**
	 *  Displays the campaign creation form
	 */
	function render_email_campaign_form() {

		$id       = empty( $_GET['id'] ) ? 0 : $_GET['id'];
		$campaign = false;

		if ( $id ) {
			$campaign = get_post( $id );
		}

		$noptin_admin = Noptin_Admin::instance();
		include $noptin_admin->admin_path . 'templates/newsletter-campaign-form.php';

	}


	/**
	 *  Displays the automations section
	 */
	function show_automations() {

		$sub_section = empty( $_GET['sub_section'] )   ? 'view_campaigns' : $_GET['sub_section'];

		/**
    	 * Runs before displaying the automations section
    	 *
    	 */
		do_action( 'noptin_before_display_automations_section', $sub_section );

		/**
    	 * Runs when displaying a specific automations section.
    	 *
    	 */
		do_action( "noptin_automations_section_$sub_section" );

		/**
    	 * Runs after displaying the automations section
    	 *
    	 */
		do_action( 'noptin_after_display_automations_section', $sub_section );

	}

	/**
	 *  Displays a list of available automations
	 */
	function view_automation_campaigns() {

		$triggers     = $this->get_automation_triggers();
		$noptin_admin = Noptin_Admin::instance();
		$table 		  = new Noptin_Email_Automations_Table();
		$table->prepare_items();

		add_thickbox();

	?>
		<div class="wrap">
			<form id="noptin-automation-campaigns-table" method="GET">
				<input type="hidden" name="page" value="noptin-email-campaigns"/>
				<input type="hidden" name="section" value="automations"/>
				<div class="noptin-campaign-action-links">
					<a title='Create A New Automation' href="#TB_inline?&width=780&height=430&inlineId=noptin-create-automation" class="button-secondary create-new-campaign thickbox"><?php _e( 'Create New Automation', 'newsletter-optin-box' ); ?></a>
				</div>

				<?php $table->display(); ?>
				<p class="description"><?php _e( 'Use this page to create emails that will be automatically emailed to your subscribers', 'newsletter-optin-box' );?></p>
			</form>
			<div id="noptin-create-automation" style="display:none;">
				<?php include $noptin_admin->admin_path . 'templates/new-email-automations-popup.php'; ?>
			</div>
		</div>
	<?php

	}

	/**
	 *  Returns a list of all automations
	 */
	function get_automation_triggers() {

		$triggers = array(
			'post_notifications' => array(
				'title'			=> __( "Post Notifications", 'newsletter-optin-box' ),
				'description'	=> __( "Notify your subscribers everytime you publish a new blog post.", 'newsletter-optin-box' ),
				'support_delay' => __( "After a post is published", 'newsletter-optin-box' ),
				'support_filter'=> true,
				'setup_cb'		=> array( $this, 'post_notifications_cb' ),
			),
			'post_digests' 		=> array(
				'title'			=> __( "Post Digests", 'newsletter-optin-box' ),
				'description'	=> __( "Send a daily, weekly or monthly digest of new blog posts.", 'newsletter-optin-box' ),
				'support_filter'=> true,
			),
			'welcome_email' 	=> array(
				'title'			=> __( "Welcome Email", 'newsletter-optin-box' ),
				'description'	=> __( "Introduce yourself to new subscribers or set up a series of welcome emails to act as an email course.", 'newsletter-optin-box' ),
				'support_delay' => true,
			),
			'subscriber_tag' 	=> array(
				'title'			=> __( "Subscriber Tag", 'newsletter-optin-box' ),
				'description'	=> __( "Send an email to a subscriber when you tag them.", 'newsletter-optin-box' ),
				'support_delay' => true,
			),
			'previous_email' 	=> array(
				'title'			=> __( "Previous Email", 'newsletter-optin-box' ),
				'description'	=> __( "Send an email to a subscriber when they receive, open or click on a link in another email.", 'newsletter-optin-box' ),
				'support_delay' => true,
			),
		);

		return apply_filters( 'noptin_email_automation_triggers', $triggers, $this );

	}

	/**
	 *  Displays the automation setup form
	 */
	public function post_notifications_cb() {
		?>
		<tr>

			<th>
				<label><b><?php _e( 'Categories', 'newsletter-optin-box' ); ?></b></label>
			</th>

			<td>
				<?php

					$args = array(
						'show_count'   		=> 1,
						'hide_empty'   		=> 0,
						'hierarchical' 		=> 1,
						'show_option_all'   => __( 'All Categories', 'newsletter-optin-box' ),
					);
					wp_dropdown_categories( $args );

				?>
				<p class="description">Select a category to send out new post notification for.</p>
			</td>

		</tr>
		<?php
	}

	/**
	 *  Displays the automation campaign creation form
	 */
	function render_automation_campaign_form( $id = 0 ) {

		$id       = empty( $_GET['id'] ) ? $id : $_GET['id'];
		$campaign = false;

		if ( $id ) {
			$campaign = get_post( $id );
		}

		$noptin_admin = Noptin_Admin::instance();
		include $noptin_admin->admin_path . 'templates/automation-campaign-form.php';

	}

	/**
	 *  Saves a newsletter campaign
	 */
	function maybe_save_campaign() {

		if ( wp_doing_ajax() ) {
            return;
		}

		$admin  = Noptin_Admin::instance();

		if(! empty( $_GET['edited'] ) ) {
			$admin->show_success( __( 'Your campaign was saved.' ) );
		}

		if(! isset( $_POST['noptin-action'] ) || 'save-newsletter-campaign' != $_POST['noptin-action'] ) {
			return;
		}

		//Verify nonce
		if( empty( $_POST['noptin_campaign_nonce'] ) || !wp_verify_nonce( $_POST['noptin_campaign_nonce'], 'noptin_campaign' ) ) {
			return $admin->show_error( __( 'Unable to save your campaign' ) );
		}

		//Defaults
		$id     = false;
		$status = 'draft';

		//Set post status
		if( !empty( $_POST['id'] ) ) {
			$id     = (int) $_POST['id'];
			$status = ( 'draft' == get_post_status( $id ) ) ? 'draft' : 'publish';
		}

		if( !empty( $_POST['draft'] ) ) {
			$status = 'draft';
		}

		if( !empty( $_POST['publish'] ) ) {
			$status = 'publish';
		}

		//Prepare post args
		$post = array(
			'post_status'      => $status,
			'post_type'        => 'noptin-campaign',
			'post_date_gmt'    => current_time( 'mysql', true ),
			'post_date'        => current_time( 'mysql' ),
			'edit_date'        => 'true',
			'post_title'	   => trim( $_POST['email_subject'] ),
			'post_content'	   => trim( $_POST['email_body'] ),
			'meta_input'	   => array(
				'campaign_type'           => 'newsletter',
				'preview_text'            => sanitize_text_field( $_POST['preview_text'] ),
				'noptin_sends_after'      => (int) $_POST['noptin-email-schedule'],
				'noptin_sends_after_unit' => sanitize_text_field( $_POST['noptin-email-schedule-unit'] ),
			),
		);

		if( 'publish' == $status & !empty( $_POST['noptin-email-schedule'] ) ) {

			$count      = (int) $_POST['noptin-email-schedule'];
			$unit       = sanitize_text_field( $_POST['noptin-email-schedule-unit'] );
			$time       = current_time( 'mysql' );
			$time_gmt   = current_time( 'mysql', true );

			$post['post_status']   = 'future';
			$post['post_date']     = gmdate( 'Y-m-d H:i:s', strtotime("$time +$count $unit") );
			$post['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime("$time_gmt +$count $unit") );

		}

		$post = apply_filters( 'noptin_save_newsletter_campaign_details', $post );

		if( empty( $id ) ) {
			$post = wp_insert_post( $post, true );
		} else {
			$post['ID'] = $id;
			$post       = wp_update_post( $post, true );
		}

		if( is_wp_error( $post ) ) {
			return $admin->show_error( $post->get_error_message() );
		}

		$url = get_noptin_newsletter_campaign_url( $post );
		wp_safe_redirect( add_query_arg( 'edited', '1', $url ) );
		exit;

	}

	/**
	 *  Deletes campaign stats
	 */
	function maybe_delete_stats( $post_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'noptin_subscriber_meta';
		$wpdb->delete( $table, array(
			'meta_key' => "_campaign_$post_id",
		) );

	}

	/**
	 *  (Maybe) Sends a newsletter campaign
	 */
	function maybe_send_campaign( $new_status, $old_status, $post ) {

		//Maybe abort early
		if( 'publish' != $new_status || 'publish' == $old_status ) {
			return;
		}

		//Ensure this is a newsletter campaign
		if( 'noptin-campaign' == $post->post_type && 'newsletter' == get_post_meta( $post->ID, 'campaign_type', true ) ) {
			$this->send_campaign( $post );
		}

	}

	/**
	 *  Sends a newsletter campaign
	 */
	function send_campaign( $post ) {

		$noptin = noptin();

		$item   = array(
			'campaign_id' 		=> $post->ID,
			'subscribers_query' => '1=1', //By default, send this to all active subscribers
			'campaign_data'		=> array(
				'template' => get_noptin_include_dir( 'admin/templates/email-templates/paste.php' ),
			),
		);

		$noptin->bg_mailer->push_to_queue( $item );

		$noptin->bg_mailer->save()->dispatch();

	}


}