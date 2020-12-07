<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' )  ) {
	die;
}

/**
 * Fires when a program a user requested to be notified
 * is beginning.
 */
class Noptin_Rejilla_Program_Beginning_Trigger extends Noptin_Abstract_Trigger {

  /**
   * Constructor.
   *
   * @since 1.3.0
   * @return string
   */
  public function __construct() {

    // Triggered when a program a user requested notification is close to begin.
    add_action( 'noptin_rejilla_program_beginning_notification', array( $this, 'maybe_trigger' ), 10, 1 );
  }

  /**
   * @inheritdoc
   */
  public function get_id() {
    return 'rejilla_program_beginning';
  }

  /**
   * @inheritdoc
   */
  public function get_name() {
    return 'Rejilla - Programa va a empezar';
  }

  /**
   * @inheritdoc
   */
  public function get_description() {
    return 'Cuando un programa al cual un suscriptor pidio ser notificado esta a punto de empezar';
  }


  /**
   * @inheritdoc
   */
  public function get_settings() {
    return array(
      'url' => array(
				'el'          => 'input',
				'label'       => __( 'URL', 'newsletter-optin-box' ),
				'description' => __( 'Enter the URL to watch for clicks or leave empty to watch for all URLs.', 'newsletter-optin-box' ),
      )
    );
  }

  /**
   * Called when a program a subscriber registered to is 10 minutes
   * from beginning.
   *
   * @param int $subscriber_id The subscriber in question.
   */
  public function maybe_trigger( $subscriber_id ) {

    log_noptin_message_file( 'Noptin_Rejilla_Program_Beginning_Trigger::maybe_trigger()' );
    log_noptin_message_file( $subscriber_id );

    // Trigger the action associated with this trigger on current rule.
    $this->trigger( $subscriber_id, array() );
  }

}
