<?php
/**
 * This is a custom enhancer written by Jose Villalobos
 * to integrate the 'rejilla-programación' plugin
 * with the noptin plugin.
 * 
 * This class will be in charge of schedule the email notification
 * as soon as the user registers to the hour of the program user has decided
 * to be notified.
 */

/**
 * Integrates the noptin plugin with the 'rejilla-programacion' pluging
 * allowing users to get notified via email when a program is soon to start.
 */
class Noptin_Rejilla_Programacion {

  /**
   * Construct the class.
   */
  public function __construct() {
    // Schedule the notification, at this point user is already inserted into the database.
    // if user is already inserted still gets invoked.
    add_action( 'noptin_add_ajax_subscriber', array( $this, 'maybe_schedule_notification' ), 10, 2 );

    // Invoked when a program the user requested the notification is close to start.
    add_action( 'noptin_rejilla_program_notification', array( $this, 'maybe_send_notification' ), 10, 5 );

    // Registers an email automation trigger in the admin menu.
    add_filter( 'noptin_email_automation_triggers', array( $this, 'register_automation_trigger_option' ), 10, 2 );

    // 'program_notification' trigger type is registered in the register_automation_trigger_option method.
    $automation_type = 'program_notification';
    add_action( "add_meta_boxes_noptin_automations_$automation_type", array( $this, 'add_edit_metabox' ), 10, 2 );

    // Adds the default data for the program_notification email.
    add_filter( 'noptin_email_automation_setup_data', array( $this, 'default_automation_data' ) );
  }

  /**
   * Add the custom fields to the subscriber fields to ensure the plugin
   * insert them as subscriber's metadata.
   *
   * @param array       $subscriber_data    Associative array with the data user put into the subscriber form, example: email, name. if the key is not for a form field it's considered a subscriber metadata.
	 * @param Noptin_Form $form               The form used by the user to subscribe.
   */
  public function add_rejilla_fields( $subscriber_data, $form ) {
    if ( isset( $_POST['noptin_rejilla_program_name'] ) && ! empty( $_POST['noptin_rejilla_program_name'] ) ) {
      $subscriber_data['noptin_rejilla_program_name'] = sanitize_text_field( urldecode( $_POST['noptin_rejilla_program_name'] ) );
    }

    if ( isset( $_POST['noptin_rejilla_emission_timestamp'] ) && ! empty( $_POST['noptin_rejilla_emission_timestamp'] ) ) {
      $subscriber_data['noptin_rejilla_emission_timestamp'] = sanitize_text_field( urldecode( $_POST['noptin_rejilla_emission_timestamp'] ) );
    }

    return $subscriber_data;
  }

  /**
   * (Maybe) Schedule an email notification 10 minutes before the program starts.
   * This gets correctly invoked even if user already exists.
   *
   * @param array       $subscriber_id    The id of the subscribed user.
	 * @param Noptin_Form $form             The form used by the user to subscribe.
   */
  public function maybe_schedule_notification( $subscriber_id, $form ) {
    // Program UTC emission date.
    $emission_date = '';
    if ( isset( $_POST['noptin_rejilla_emission_timestamp'] ) && ! empty( $_POST['noptin_rejilla_emission_timestamp'] ) ) {
      $emission_date = $_POST['noptin_rejilla_emission_timestamp'];
    }

    if ( empty( $emission_date ) ) {
      return;
    }

    if ( ! is_numeric( $emission_date ) ) {
      return;
    }

    // Timestamp is 10 minutes before program begins.
    $timestamp = (int) $emission_date;
    $current_timestamp = time();

    // Cheks if passed timestamp is in the past.
    if ( $timestamp <= $current_timestamp ) {
      return;
    }

    $program_name_slug = '';
    $program_name      = '';
    if ( isset( $_POST['noptin_rejilla_program_name'] ) && ! empty( $_POST['noptin_rejilla_program_name'] ) ) {
      $program_name      = strtolower( urldecode( $_POST['noptin_rejilla_program_name'] ) );
      $program_name_slug = sanitize_title_with_dashes( $program_name );
    }

    if ( empty( $program_name_slug ) ) {
      return;
    }

    // Use this 
    $notification_key = "_program_notification_$program_name_slug--$timestamp";

    // Can have, 'not_sent' and 'sent' as values.
    add_noptin_subscriber_meta( $subscriber_id, $notification_key, 'not_sent' );

    log_noptin_message_file( 'Noptin_Rejilla_Programacion::maybe_schedule_notification()' );
    log_noptin_message_file( $notification_key );

    // See class-noptin-new-post-notify.php to see inspiration about this.
    // Are there any new post automations.
    $automations = $this->get_automations();
    if ( empty( $automations ) ) {
			return;
    }

    foreach ( $automations as $automation ) {

      // TODO: For far future, support multiple templates for different program names.
      // Check if the automation applies here.
      if ( $this->is_automation_valid_for( $automation, $program_name ) ) {
        log_noptin_message_file( 'scheduling notification' );
        log_noptin_message_file( $automation );
        /**
         * Invoked when the program the user requested the notification is close to start.
         * Note that it can happen that two different users register to the same program + transmission date
         * in this case Action Scheduler will trigger this only once, we don't have to write logic to manage that usecase.
         *
         * @param int    $automation_id         The id of the noptin-campaign post used for this automation.
         * @param string $notification_key      The subscriber's meta key to keep status of whether the notification was sent or not.
         * @param string $program_name          The program name lowercased.
         * @param string $program_name_slug     The slug for the program, example if the program was called 'CASA DEPORTES', here will receive: 'casa-deportes'.
         * @param int    $timestamp             The unix timestamp at which the program will be transmitted.
         */
        schedule_noptin_background_action(
          $timestamp,
          'noptin_rejilla_program_notification', // action hook name.
          // Parameters passed when the hook is called.
          $automation->ID, // Passed id instead of the WP_Post to prevent serialization/unserialization from converting the object into an array.
          $notification_key,
          $program_name,
          $program_name_slug,
          $timestamp
        );
      }
    }
  }

  /**
	 * Checks if a given notification is valid for a given post
   * 
   * @param WP_Post The automation post.
   * @param string  The program string, right now, this is just a dummy parameter to explain the intention in the future to support multiple templates for different programs.
	 */
	public function is_automation_valid_for( $automation, $program_name ) {

		return true;
	}


  /**
   * Invoked when the program the user requested the notification is close to start.
   *
   * @param int    $automation_id         The id of the noptin-campaign post used for this automation.
   * @param string $notification_key      The subscriber's meta key to keep status of whether the notification was sent or not.
   * @param string $program_name          The program name lowercased.
   * @param string $program_name_slug     The slug for the program, example if the program was called 'CASA DEPORTES', here will receive: 'casa-deportes'.
   * @param int    $timestamp             The unix timestamp at which the program will be transmitted.
   */
  public function maybe_send_notification( $automation_id, $notification_key, $program_name, $program_name_slug, $timestamp ) {
    log_noptin_message_file( 'Noptin_Rejilla_Programacion::maybe_send_notification()' );
    log_noptin_message_file( $program_name );
    log_noptin_message_file( $notification_key );

    $automation = get_post( $automation_id );

    if ( ! $automation ) {
      return;
    }

    if ( 'noptin-campaign' !== $automation->post_type ) {
      return;
    }

    $automation_type = get_post_meta( $automation_id, 'automation_type', true );

    if ( 'program_notification' !== $automation_type ) {
      return;
    }

    $key = $automation->ID . '_' . $program_name_slug . '--' . $timestamp;
    log_noptin_message_file( $key );

    $noptin = noptin();

    $item = array(
      'campaign_id'       => $automation->ID,
      // By default, send this to all active subscribers.
      // with the $notification_key subscriber's meta data.
			'subscribers_query' => array(
        // The subscribers query follows the same sintax as WordPress *_Query class.
        // see: Noptin_Background_Mailer::fetch_subscriber_from_query() and
        // Noptin_Subscriber_Query classes.
        // However, the ralation will be always 'AND'.
        'meta_query' => array(
          array(
            'key'     => $notification_key,
            'compare' => '=',
            'value'   => 'not_sent',
          ),
        ),
      ),
      // This key is used to prevent, send the same notification multiple times
      // to same user.
      'key'               => $key,
			'campaign_data'     => array(
				'campaign_id' => $automation->ID
			),
    );
    
    $noptin->bg_mailer->push_to_queue( $item );
    $noptin->bg_mailer->save()->dispatch();
  }

  /**
	 * Returns an array of all published new post notifications
	 */
	public function get_automations() {

		$args = array(
			'numberposts' => -1,
			'post_type'   => 'noptin-campaign',
			'meta_query'  => array(
				array(
					'key'   => 'campaign_type',
					'value' => 'automation',
				),
				array(
					'key'   => 'automation_type',
					'value' => 'program_notification',
				),
			),
		);
		return get_posts( $args );

	}

  /**
   * Registers an email automation trigger in the admin menu.
   * 
   * @param array                        $triggers         Associative array with the automation options.
   * @param Noptin_Email_Campaigns_Admin $campaigns_admin  The email campaigns admin handler.
   */
  public function register_automation_trigger_option( $triggers, $campaigns_admin ) {

    $result = array_merge(
      array_slice( $triggers, 0, 1, true ),
      array(
        'program_notification' => array(
          'setup_title'    => 'Rejilla - Notificación de programa',
          'title'          => 'Rejilla - Notificación de programa',
          'description'    => 'Envia una notificación cuando un programa de la rejilla de programación esta a punto de empezar.<p></p>',
          // 'support_delay'  => __( 'After someone subscribes', 'newsletter-optin-box' ),
          // 'support_filter' => __( 'All new subscribers', 'newsletter-optin-box' ),
          // Required to mark this trigger option as enabled, this is what is rendered on the edit page.
          'setup_cb'       => array( $this, 'render_rejilla_notification_edit' ),
        ),
      ),
      array_slice( $triggers, 1, null, true )
    );
    return $result;
  }

  /**
   * Render the edit screen.
   */
  public function render_rejilla_notification_edit() {
    echo 'Configuración basica';
  }

  /**
   * Adds the metaboxes for the edit screen of 'program_notification'.
   *
   * @param int   $campaign           The campaign post id.
   * @param array $automations_types  The triggers array, changed in register_automation_trigger_option.
   */
  public function add_edit_metabox( $campaign, $automations_types ) {
    add_meta_box(
      'noptin_automation_setup_cb',
      __('Options','newsletter-optin-box'),
      array( $this, 'render_automation_setup_metabox' ),
      'noptin_page_noptin-automation',
      'advanced',
      'default',
      $automations_types['program_notification']['setup_cb']
    );
  }

  /**
	 * Displays the setup metabox.
	 *
	 * @since 1.2.9
	 */
	public function render_automation_setup_metabox( $campaign, $cb ) {
		call_user_func( $cb['args'], $campaign );
  }
  
  /**
	 * Filters default automation data.
	 *
	 * @param array $data The automation data.
	 */
	public function default_automation_data( $data ) {

    $email_body_location = locate_noptin_template( 'rejilla-default-new-program-notification-body.php', null, get_noptin_plugin_path( 'includes/addons/templates' ) );

		if ( 'program_notification' === $data['automation_type'] ) {
			$data['email_body']   = noptin_ob_get_clean( $email_body_location );
			$data['subject']      = '[[post_title]]';
			$data['preview_text'] = __( 'New article published on [[blog_name]]', 'newsletter-optin-box' );
		}
		return $data;

	}

}
