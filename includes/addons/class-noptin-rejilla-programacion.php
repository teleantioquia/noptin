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

    // Registers custom triggers to the automation rules.
    add_action( 'noptin_automation_rules_load', array( $this, 'load_custom_automation_rules' ) );
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

    $program_name = '';
    if ( isset( $_POST['noptin_rejilla_program_name'] ) && ! empty( $_POST['noptin_rejilla_program_name'] ) ) {
      $program_name = strtolower( sanitize_title_with_dashes( urldecode( $_POST['noptin_rejilla_program_name'] ) ) );
    }

    if ( empty( $program_name ) ) {
      return;
    }

    $program_hook = "$program_name--$timestamp";
    add_noptin_subscriber_meta( $subscriber_id, 'noptin_rejilla_programas_to_notify', $program_hook );

    log_noptin_message_file( 'Noptin_Rejilla_Programacion::maybe_schedule_notification()' );
    log_noptin_message_file( $current_timestamp );
    log_noptin_message_file( $timestamp );

    // Different hook names for each program the user wants to be notified about.
    $hookname = "noptin_rejilla_program_notification_$program_hook";
    // A different group for each user id.
    $group = "noptin_rejilla_subscriber_$subscriber_id";
    return as_schedule_single_action(
			$timestamp,
			$hookname,
			array(
        $subscriber_id,
      ),
			$group
		);
    // return schedule_noptin_background_action( $timestamp, 'noptin_rejilla_program_beginning_notification', $subscriber_id );
  }

  /**
   * Loads custom automation rules.
   *
   * @param Noptin_Automation_Rules $rules The automation rules class.
   */
  public function load_custom_automation_rules( $rules ) {
    $rules->add_trigger( new Noptin_Rejilla_Program_Beginning_Trigger() );
  }

}
