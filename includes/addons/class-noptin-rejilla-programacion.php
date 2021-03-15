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
    add_action( 'noptin_rejilla_program_notification', array( $this, 'maybe_send_notification' ), 10, 6 );

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
      // TODO: Program name lowercase doesnt works with tildes.
      // TODO: Check if mb_strtolower() fucntion fixed the tildes problem.
      $program_name      = mb_strtolower( urldecode( $_POST['noptin_rejilla_program_name'] ), 'UTF-8' );
      $program_name_slug = sanitize_title_with_dashes( $program_name );
    }

    if ( empty( $program_name_slug ) ) {
      return;
    }

    // Example shape of program info, this is defined in the rejilla plugin.
    // $program_info = array(
    //   'program_name'        => $name,
    //   'transmission_date'   => ( new DateTime( $program['transmission_date'], wp_timezone() ) )->format( 'Y-m-d H:i:s' ),
    //   'program_description' => $description,
    //   'viewable_from_web'   => $program['viewable_from_web'],
    //   'link_to'             => $link_to,
    // );
    // Gets the program info.
    $program_info = array();
    if ( isset( $_POST['noptin_rejilla_program_info'] ) && ! empty( $_POST['noptin_rejilla_program_info'] ) ) {
      // Required to call stripslashes() fucntion to strip back slashes and decode the json string.
      // see related issue: https://stackoverflow.com/questions/47914737/how-to-access-json-decode-data-after-passing-through-ajax.
      // otherwise json_decade() won't parse the string.
      $program_info = json_decode( stripslashes( $_POST['noptin_rejilla_program_info'] ), true );
    }

    // Use this 
    $notification_key = "_program_notification_$program_name_slug--$timestamp";

    // If this subscriber already requested a notification for this program, dont schedule again.
    if ( noptin_subscriber_meta_exists( $subscriber_id, $notification_key ) ) {
      return;
    }

    // This subscriber's meta is just used to fetch the initial user to notify
    // not to prevent the notification from being sent multiple times.
    // for that is used the 'key' option in the bg_mailer $item.
    // see maybe_send_notification() method.
    update_noptin_subscriber_meta( $subscriber_id, $notification_key, 'registered' );

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
         * @param array  $program_info          Associative array with human-legible info about the program, used to generate some email merge tags.
         */
        schedule_noptin_background_action(
          $timestamp,
          'noptin_rejilla_program_notification', // action hook name.
          // Parameters passed when the hook is called.
          $automation->ID, // Passed id instead of the WP_Post to prevent serialization/unserialization from converting the object into an array.
          $notification_key,
          $program_name,
          $program_name_slug,
          $timestamp,
          // Program info used to generate the merge tags.
          $program_info
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
   * @param array  $program_info          Associative array with human-legible info about the program, used to generate some email merge tags.
   */
  public function maybe_send_notification( $automation_id, $notification_key, $program_name, $program_name_slug, $timestamp, $program_info ) {

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

    $noptin = noptin();

    $item = array(
      'campaign_id'       => $automation->ID,
      // This key is used to prevent, send the same notification multiple times
      // to same user, it's managed by bg_mailer.
      'key'               => $key,
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
            // Not required.
            'value'   => 'registered',
          ),
        ),
      ),
			'campaign_data'     => array(
        'campaign_id' => $automation->ID,
        // Used in the email [[tag_name]]
        'merge_tags'  => $this->get_merge_tags( $program_name, $program_info ),
			),
    );
    
    $noptin->bg_mailer->push_to_queue( $item );
    $noptin->bg_mailer->save()->dispatch();
  }

  /**
   * Get the merge tags used in the email.
   *
   * @param string $program_name          The program name lowercased.
   * @param array  $program_info          Associative array with human-legible info about the program, used to generate some email merge tags.
   */
  private function get_merge_tags( $program_name, $program_info ) {
    $tags = array();

    // Example shape of program info, this is defined in the rejilla plugin.
    // in class-ta-prog-all-programs-grid-v2-shortcode.php shortcode file.
    // $program_info = array(
    //   'program_name'        => $name,
    //   'transmission_date'   => ( new DateTime( $program['transmission_date'], wp_timezone() ) )->format( 'Y-m-d H:i:s' ),
    //   'program_description' => $description,
    //   'viewable_from_web'   => $program['viewable_from_web'],
    //   'link_to'             => $link_to,
    //   'live_page_url'       => $this->live_page_url,
    // );

    $tags['program_name'] = noptin_mb_ucfirst( $program_name );

    if ( ! empty( $program_info['program_description'] ) ) {
      $tags['program_description'] = $program_info['program_description'];
    }

    if ( ! empty( $program_info['transmission_date'] ) ) {
      // Convert from string to DateTime object.
      $transmision_date               = DateTime::createFromFormat( 'Y-m-d H:i:s', $program_info['transmission_date'], wp_timezone() );
      $tags['transmission_hour']      = $transmision_date->format( 'H:i' );
      // Eg: jueves 24 de octubre
      $tags['transmission_day']       = wp_date( 'l j \d\e F', $transmision_date->getTimestamp(), wp_timezone() );
      $tags['transmission_full_date'] = $program_info['transmission_date'];
    }

    $tags['transmission_timezone']           = 'Bogotá';
    $tags['transmission_timezone_wordpress'] = wp_timezone_string();

    $tags['program_url'] = esc_url( $program_info['link_to'] );
    
    // Button to show: 'al-aire' page.
    $tags['al_aire_link']    = esc_url( $program_info['live_page_url'] );
    $tags['al_aire_button']  = $this->program_button( $tags['al_aire_link'] );
    $tags['/al_aire_button'] = '</a></div>';

    if ( filter_var( $program_info['viewable_from_web'], FILTER_VALIDATE_BOOLEAN ) ) {
      $tags['if_viewable_from_web']  = '';
      $tags['/if_viewable_from_web'] = '';

      // Just start an html comment, this is because the display: none, is not
      // widely supported across email clients.
      // See:
      // https://stackoverflow.com/questions/48253050/how-to-use-display-none-on-outlook-2007-2010-2013-in-html-email
      // https://www.litmus.com/blog/gmail-now-supports-display-none-what-it-means-for-your-email-designs/
      // https://github.com/mjmlio/mjml/issues/770
      $tags['if_no_viewable_from_web']  = "<!--";
      $tags['/if_no_viewable_from_web'] = '-->';
    } else {
      $tags['if_viewable_from_web']  = "<!--";
      $tags['/if_viewable_from_web'] = '-->';

      $tags['if_no_viewable_from_web']  = '';
      $tags['/if_no_viewable_from_web'] = '';
    }

    // Button to program play.teleantioquia.co page.
    $tags['program_button'] = $this->program_button( $program_info['link_to'] );
    $tags['/program_button'] = '</a></div>';

    return $tags;
  }

  /**
	 * Generates read more button markup
	 */
	public function program_button( $url ) {
		$url = esc_url( $url );
		return "<div style='text-align: left; padding: 20px;' align='left'> <a href='$url' class='noptin-round' style='background: #14cc60; display: inline-block; padding: 16px 36px; font-size: 16px; color: #ffffff; text-decoration: none; border-radius: 6px;'>";
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
			$data['subject']      = '«[[program_name]]» esta a punto de empezar';
			$data['preview_text'] = 'Míralo desde nuestra señal online o sintonizando nuestro canal de televisión';
		}
		return $data;

	}

}
