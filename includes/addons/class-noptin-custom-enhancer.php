<?php
/**
 * This is a custom enhancer written by Jose Villalobos
 * to try to mimic the functionallities explained in this
 * official addon: https://noptin.com/product/ultimate-addons-pack/
 *
 * Mainly the features that we want to implement are the following:
 * 1. Send automated email campaigns based on all post types and taxonomies.
 * 2. Send only to users with specific tags. which were added to them, either...
 * 2.a Based on the form used to subscribe
 * 2.b Based on the tags added manually throught the editor.
 */

/**
 * Add improvements to the noptin functionallity to mimic the functionallity
 * in this official addon: https://noptin.com/product/ultimate-addons-pack/.
 */
class Noptin_Custom_Enhancer {


	// TODO: Implement feature to remember name and email with cookies.

	public function __construct()	{
		// Enable automated emails to specific post types.
		add_filter( 'noptin_post_notifications_setup_cb', array( $this, 'post_notification_setup_callback' ) );

		add_filter( 'noptin_new_post_notification_allowed_post_types', array( $this, 'allowed_post_types' ), 10, 2 );
		add_filter( 'noptin_new_post_notification_valid_for_post', array( $this, 'valid_for_post' ), 10, 3 );

		// Enable automated emails to users subscribed to a specific mail list.
		add_filter( 'noptin_mailer_new_post_automation_details', array( $this, 'filter_users_to_notify' ), 10, 3 );
		
		// Adds metabox in subscriber screen to configure mailing list.
		add_action( 'add_meta_boxes_noptin_subscribers', array( $this, 'register_subscribers_meta_box' ) );

		// Add setting to sidebar of form edit screen.
		add_action( 'noptin_optin_form_editor_sidebar_section', array( $this, 'edit_form_sidebar' ), 10, 2 );
		add_filter( 'noptin_optin_form_editor_state', array( $this, 'mail_list_state' ), 10, 2 );

		// Save the email list asociated to form as a meta data of the user.
		add_filter( 'noptin_add_ajax_subscriber_filter_details', array( $this, 'associate_form_mail_list_to_subscriber' ), 10, 2 );
	}

	/**
	 * Changes the default render callback for the
	 * post notification automation.
	 *
	 * @param array $callback The original callback used to render.
	 */
	public function post_notification_setup_callback( $callback ) {
		
		return array( $this, 'render_automation_settings' );
	}

	/**
	 * Filters/Configure default automation data.
	 * This callback is used by class-noptin-new-post-notify.php to
	 * render the metabox content used to customize to which post types sends notifications.
	 *
	 * @param WP_Post $campaign    The post object of noptin-campaign post type, info related to the campaign.
	 */
	public function render_automation_settings( $campaign ) {
		// The public registered post types.
		$post_types = get_post_types(
			array(
				'public' => true,
				'show_ui' => true,
			)
		);

		unset( $post_types['attachment'] );

		// Get the post types configured for this post automation.
		$saved_post_types = get_post_meta( $campaign->ID, 'noptin_enhancer_post_types_to_notify', true );

		$mail_lists = get_post_meta( $campaign->ID, 'noptin_enhancer_campaign_mail_lists', true );
		echo '<p><b>Notificar a los usuarios de las siguientes mail lists:</b></p>';
		echo '<p>';
		echo "<input name='noptin_enhancer_campaign_mail_lists' type='text' value='$mail_lists' />";
		echo '</p>';

		echo '<p><b>Cuando haya un nuevo post del siguiente tipo:</b> (separe por comas si hay varias)</p>';
		foreach ( $post_types as $post_type ) {
			$checked = '';
			if ( is_array( $saved_post_types) && in_array( $post_type, $saved_post_types, true ) ) {
				$checked = 'checked="checked"';
			}

			echo '<div>';
			echo "<input name='noptin_enhancer_post_types_to_notify[]' id='id-$post_type' $checked type='checkbox' value='$post_type' />";
			echo "<label for='id-$post_type'>$post_type</label>";
			
			echo '<div style="padding-left: 15px;">';
			$taxonomies = get_object_taxonomies( $post_type );
			if ( ! empty( $taxonomies ) ) {
				echo '<p>...Con los siguientes terminos (poner el <b>slug</b> de los terminos separados por comas):</p>';				
			}

			foreach ( $taxonomies as $taxonomy ) {
				// Get the terms for the current taxonomy.
				$terms = get_post_meta( $campaign->ID, "noptin_enhancer__{$post_type}__{$taxonomy}__terms", true );

				// The value attribute.
				$value = esc_attr( $terms );

				echo '<p>';
				echo "<label for='id-$taxonomy'>$taxonomy</label><br>";
				echo "<input id='id-$taxonomy' name='noptin_enhancer__{$post_type}__{$taxonomy}__terms' type='text' value='$value' />";
				echo '</p>';
			}
			echo '</div>';

			echo '</div>';
		}
	}


	// use log_noptin_message() function to log messages.

	/**
	 * Sends a list of the allowed post types by user configuration.
	 *
	 * @param array   $post_types   Array of post type slugs for this automation. by default only array( 'post' ).
	 * @param WP_Post $automation   The automation post, Same as the named $campaign in render_automation_settings.
	 */
	public function allowed_post_types( $post_types, $automation ) {
		// Get the post types configured for this post automation.
		$saved_post_types = get_post_meta( $automation->ID, 'noptin_enhancer_post_types_to_notify', true );

		return $saved_post_types;
	}

	/**
	 * Checks if the new post notification should sent emails for the give $post.
	 *
	 * @param bool $is_valid        Should sent the emails, by default true.
	 * @param WP_Post $automation   The automation post, Same as the named $campaign in render_automation_settings.
	 * @param WP_Post $post         The post that just have been published.
	 */
	public function valid_for_post( $is_valid, $automation, $post ) {
		// Get the taxonomies for the published post.
		$taxonomies = get_object_taxonomies( $post->post_type );

		// To track if all the saved taxonomy terms fields are empty.
		// If all are empty, just return true. otherwise do the computations.
		$all_saved_terms_empty = true;

		// To check if any of the $automation saved terms is on the published post
		// note that is an OR condition. That is, with the first found term this will be true.
		// this flag is ignored if $all_saved_terms_empty is true.
		$post_has_term = false;

		foreach ( $taxonomies as $taxonomy ) {
			$saved_terms_key = "noptin_enhancer__{$post->post_type}__{$taxonomy}__terms";
			$saved_terms     = get_post_meta( $automation->ID, $saved_terms_key, true );

			if ( ! empty( $saved_terms ) ) {
				$all_saved_terms_empty = false;
				// Removes white spaces.
				$saved_terms = str_replace( ' ', '', $saved_terms);
				// Convert to array.
				$saved_terms = explode( ',', $saved_terms );

				foreach ( $saved_terms as $saved_term ) {
					// In case user inputs someting like: 'term1,term2,'
					// note that trailing comma.
					if ( empty( $saved_term ) ) {
						continue;
					}

					if ( has_term( $saved_term, $taxonomy, $post ) ) {
						$post_has_term = true;
						// Break the two foreach loops.
						break 2;
					}
				}
			}
		}

		if ( $all_saved_terms_empty ) {
			return true;
		} else {
			return $post_has_term;
		}
	}

	/**
	 * Registers mataboxes to the subscribers edit screen.
	 *
	 * @param Noptin_Subscriber $subscriber   The subscriber object.
	 */
	public function register_subscribers_meta_box( $subscriber ) {
		add_meta_box(
			'noptin_enhancer_subscriber_mail_list',
			'Mail Lists',
			array( $this, 'subscriber_mail_list' ),
			'noptin_page_noptin-subscribers',
			'side',
			'default'
		);
	}

	/**
	 * Renders the Mail List metabox content.
	 *
	 * @param Noptin_Subscriber $subscriber   The subscriber object.
	 */
	public function subscriber_mail_list( $subscriber ) {
		$mail_lists = get_noptin_subscriber_meta( $subscriber->id, 'noptin_enhancer_mail_lists', true );

		echo '<p>';
		echo "<input value='$mail_lists' name='noptin_enhancer_mail_lists' type='text' placeholder='Mail Lists' style='width: 100%;' />";
		echo '</p>';
		echo '<p>Use las <b>Mail List</b> para agrupar a los distintos suscriptores.</p>';
		echo '<p>Pueden ser utiles para por ejemplo: Enviar correos automatizados solo a los usuarios que pertenezcan a un mail list.</p>';
		echo '<p>Tambien puede definir en el formulario de suscripcion a que mail list inscribir los usuarios que se registren a travez de el.</p>';
		echo '<p>Separe por comas si va a incluir al usuario a multiples <b>mail lists</b>.</p>';
		echo '<p>Tenga en cuenta que actualmente se estan implementando de una forma rudimentaria, que lo unico que permiten es enviar correos de forma mas especializada.</p>';
	}

	/**
	 * To add customo coptions to the sidebar of noptin-form post edit screen.
	 * 
	 * @param array              $fields        Associative array with the fields.
	 * @param Noptin_Form_Editor $form_editor   The instance to the form editor class.
	 */
	public function edit_form_sidebar( $fields, $form_editor ) {

		// A field to set the mail list to associate withe the registered users.
		$fields['settings']['mailLists'] = array(
			'el'       => 'panel',
			'title'    => __( 'Mail Lists', 'newsletter-optin-box' ),
			'id'       => 'mailListsSettings',
			'children' => $this->get_mail_lists_settings(),
		);

		return $fields;
	}

	/**
	 * Returns setting fields for the mail lists panel.
	 */
	private function get_mail_lists_settings() {
		return array(
			'enhancerMailLists' => array(
				'el'          => 'input',
				'label'       => 'Asociar usuarios con estas mail lists:',
				'placeholder' => 'noticias, enterate, blog',
				'tooltip'     => 'El nombre de los mail lists que se debe asociar a los usuarios que se registran a travez de este formulario, separe por comas si desea asociar a varias.',
			),
		);
	}

	/**
	 * Registers state to the editor object to persist user changes.
	 * This is required to save/retrive into/from the database the changes to the mail lists input text.
	 *
	 * @param array              $state         The state object saved as meta data of the 'noptin-form' post.
	 * @param Noptin_Form_Editor $form_editor   The instance to the form editor class.
	 */
	public function mail_list_state( $state, $form_editor ) {
		// Sets the state key only if not set.
		if ( ! isset( $state['enhancerMailLists'] ) ) {
			$state['enhancerMailLists'] = '';
		}

		return $state;
	}

	/**
	 * Associates the form mail list to the subscriber mail list when
	 * creating the subscriber.
	 *
	 * @param array       $subscriber_data    Associative array with the data user put into the subscriber form, example: email, name. if the key is not for a form field it's considered a subscriber metadata.
	 * @param Noptin_Form $form               The form used by the user to subscribe.
	 */
	public function associate_form_mail_list_to_subscriber( $subscriber_data, $form ) {

		$form_data = $form->get_all_data();
		if ( isset( $form_data['enhancerMailLists'] ) ) {
			// The subscriber meta data with the mail list.
			$subscriber_data['noptin_enhancer_mail_lists'] = str_replace( ' ', '', trim( $form_data['enhancerMailLists'] ) );
		}

		return $subscriber_data;
	}

	/**
	 * Filters the users to notify, based on othe mail list to which they are subscribed
	 * and the mail list this campaign is configured.
	 *
	 * @param array $item         Associate array with information to send this campaign.
	 * @param int   $post_id      The id of the post that was published.
	 * @param int   $campaign_id  The id of the campaign that triggered this email send.
	 */
	public function filter_users_to_notify( $item, $post_id, $campaign_id ) {

		$campaign_mail_list = get_post_meta( $campaign_id, 'noptin_enhancer_campaign_mail_lists', true );

		if ( ! empty( $campaign_mail_list ) ) {
			$campaign_mail_list = str_replace( ' ', '', trim( $campaign_mail_list ) );
			$campaign_mail_list = explode( ',', $campaign_mail_list );
			// Array filter without callback, just remove empty elements.
			$campaign_mail_list = array_filter( $campaign_mail_list );

			log_noptin_message_file( 'Noptin_Custom_Enhancer::filter_users_to_notify()' );
			log_noptin_message_file( $campaign_mail_list );

			$regex_exp = implode( '|', $campaign_mail_list );
			log_noptin_message_file( 'regex: ' . $regex_exp );

			if ( ! empty( $regex_exp ) ) {
				$item['subscribers_query'] = array(
					// The subscribers query follows the same sintax as WordPress *_Query class.
					// see: Noptin_Background_Mailer::fetch_subscriber_from_query() and
					// Noptin_Subscriber_Query classes.
					'meta_query' => array(
						array(
							'key'     => 'noptin_enhancer_mail_lists',
							'compare' => 'REGEXP',
							'value'   => $regex_exp,
						),
					),
				);
			}
		}

		return $item;
	}

}
