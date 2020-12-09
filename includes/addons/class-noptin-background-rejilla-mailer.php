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

defined( 'ABSPATH' ) || exit;


/**
 * The background mailing class to notify about new programs.
 */
class Noptin_Background_Rejilla_Mailer extends Noptin_Background_Process {

	/**
	 * The background action for our mailing process.
	 * @var string
	 */
	protected $action = 'noptin_rejilla_bg_mailer';

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param array $item The current item being processed.
	 *
	 * @return mixed
	 */
	public function task( $item ) {
		
		return false;
	}


}