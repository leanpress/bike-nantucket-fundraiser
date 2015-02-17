<?php
/*  Copyright 2015 Au Coeur Design ( http://aucoeurdesign.org)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once( bnfund_DIR . '/includes/paypalfunctions.php' );

/**
 * Add a gift donation to the campaign.  Fired from the bnfund_add_gift action.
 * This function will update the gift tally for the campaign, add a comment
 * detailing the donation and fire actions for additional processing.
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal.
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function bnfund_add_gift( $transaction_array, $post ) {
	$processed_transactions = get_post_meta( $post->ID, '_bnfund_transaction_ids' );
	$transaction_nonce = $transaction_array['transaction_nonce'];
	//Make sure this transaction hasn't already been processed.
	if ( is_array( $processed_transactions ) && in_array( $transaction_nonce, $processed_transactions ) ) {
		return;
	} else if ( !is_array( $processed_transactions ) && $processed_transactions == $transaction_nonce ) {
		return;
	}
	if ( $transaction_array['success'] == true) {
		//Update gift tally
		$tally = get_post_meta( $post->ID, '_bnfund_gift-tally', true );
		if ( $tally == '' ) {
			$tally = 0;
		}
		if ( ! empty( $transaction_array['tally_amount'] ) && is_numeric( $transaction_array['tally_amount'] ) ) {
			$tally += $transaction_array['tally_amount'];
		} else {
			$tally += $transaction_array['amount'];
		}
		update_post_meta( $post->ID, '_bnfund_gift-tally', $tally );
		add_post_meta( $post->ID, '_bnfund_transaction_ids', $transaction_nonce );

        if ( empty( $transaction_array['anonymous'] ) && (
                empty( $transaction_array['donor_email'] ) ||
                empty( $transaction_array['donor_first_name'] ) ||
                empty( $transaction_array['donor_last_name'] ) ) ) {
            $transaction_array['anonymous'] = true;
        }
        $transaction_array['date'] = new DateTime();

		add_post_meta( $post->ID, '_bnfund_transactions', $transaction_array );
		_bnfund_update_giver_tally( $post->ID );

		$options = get_option( 'bnfund_options' );
		//Add comment for transaction.
		if ( isset( $transaction_array['anonymous'] ) &&
				$transaction_array['anonymous'] == true ) {
			$commentdata = array(
				'comment_post_ID' => $post->ID,
				'comment_author' => '',
				'comment_author_email' => '',                
				'comment_content' => sprintf(
					__( 'An anonymous gift of %s%s was received.', 'bnfund' ),
					$options['currency_symbol'],
					number_format_i18n( floatval( $transaction_array['amount'] ), 2 )
				),
				'comment_approved' => 1
			);
		} else {
			$commentdata = array(
				'comment_post_ID' => $post->ID,
				'comment_author' => $transaction_array['donor_first_name'] . ' ' . $transaction_array['donor_last_name'],
				'comment_author_email' => $transaction_array['donor_email'],
				'comment_content' => sprintf(
					__( '%s %s donated %s%s.', 'bnfund' ),
					$transaction_array['donor_first_name'],
					$transaction_array['donor_last_name'],
					$options['currency_symbol'],
					number_format_i18n( floatval( $transaction_array['amount'] ), 2 )
				),
				'comment_approved' => 1
			);
		}
		if ( ! empty( $transaction_array['comment'] ) ) {
			$commentdata['comment_content'] = $transaction_array['comment'];
		}
		$commentdata['comment_author_IP'] = '';
		$commentdata['comment_author_url'] = '';
		$commentdata = wp_filter_comment( $commentdata );
		$comment_id = wp_insert_comment( $commentdata );
		add_comment_meta($comment_id, 'bnfund_trans_amount', $transaction_array['amount']);
		//Fire action for any additional processing.
		do_action( 'bnfund_processed_transaction', $transaction_array, $post );
		$goal = get_post_meta( $post->ID, '_bnfund_gift-goal', true );
		if ( $tally >= $goal ) {
			do_action('bnfund_reached_user_goal', $transaction_array, $post, $goal );
		}
	}
}

/**
 * Shortcode handler for bnfund-campaign-list to display the list of current
 * campaigns.
 * @return string HTML that contains the campaign list.
 */
function bnfund_campaign_list() {
	global $wp_query;
	wp_enqueue_style( 'bnfund-user', bnfund_determine_file_location('user','css'),
			array(), bnfund_VERSION );
	$post_query = array(
		'post_type' => 'bnfund_campaign',
		'orderby' => 'title',
		'order' => 'ASC',
		'posts_per_page' => -1
	);

	if ( isset(  $wp_query->query_vars['bnfund_event_id'] ) ) {
		$post_query['meta_query'] = array(
			array(
				'key' => '_bnfund_event_id',
				'value' => $wp_query->query_vars['bnfund_event_id']
			)
		);
	}
	$campaigns = get_posts($post_query);
	$list_content = '<ul class="bnfund-list">';
	foreach ($campaigns as $campaign) {
		$list_content .= '<li>';
		$list_content .= '	<h2>';
		$list_content .= '		<a href="'.get_permalink($campaign->ID).'">'.$campaign->post_title.'</a></h2>';
		$list_content .= '</li>';

	}
	$list_content .= '</ul>';
	return $list_content;
}

/**
 * Shortcode handler for bnfund-campaign-permalink to get the permalink for the
 * current campaign.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to get the permalink for by passing a "campaign_id" attribute.
 * @return string the permalink for the current campaign.
 */
function bnfund_campaign_permalink( $attrs ) {
    $post = _bnfund_get_shortcode_campaign( $attrs );
	if( $post->ID == null || $post->post_type != 'bnfund_campaign' ) {
		return '';
	}
	return get_permalink( $post->ID );
}

/**
 * Shortcode handler for bnfund-event-list to display the list of current events.
 * @return string HTML that contains the campaign list.
 */
function bnfund_event_list() {
	wp_enqueue_style( 'bnfund-user', bnfund_determine_file_location('user','css'),
			array(), bnfund_VERSION );
	$options = get_option( 'bnfund_options' );
	$events = get_posts(
		array(
			'post_type' => 'bnfund_event',
			'orderby' => 'title',
			'order' => 'ASC',
			'posts_per_page' => -1
		)
	);
	$campaign_list_url = '/'.$options['campaign_slug'].'/?bnfund_event_id=';


	$user_can_create = _bnfund_current_user_can_create( $options );
	$list_content = '<ul class="bnfund-list">';
	foreach ($events as $event) {
		$list_content .= '<li>';
		$list_content .= '	<h2>';
		$list_content .= '		<a href="'.$campaign_list_url.$event->ID.'">'.$event->post_title.'</a></h2>';
		$list_content .= '<p class="bnfund-event-description">';
		$event_img = get_post_meta($event->ID, '_bnfund_event_image', true);
		if ( $event_img ) {
			$list_content .= '<img class="bnfund-image" width="184" src="'.wp_get_attachment_url( $event_img ).'"/>';
		}
		$list_content .= '<span>';
		$list_content .= get_post_meta($event->ID, '_bnfund_event_description', true);
		$list_content .= '</span>';
		$list_content .= '</p>';
		if ( $user_can_create ) {
			$list_content .= '<p>';
			$list_content .= '<a href="'.get_permalink($event->ID).'">'.__( 'Create My Page', 'bnfund' ).'</a>';
			$list_content .= '</p>';
		}
		$list_content .= '</li>';

	}
	$list_content .= '</ul>';
	return $list_content;
}

/**
 * Handler for campaign comments shortcode (bnfund-comments).
 * @return string The HTML for the campaign contents.
 */
function bnfund_comments() {
	global $post;
	if( $post->ID == null || $post->post_type != 'bnfund_campaign' ) {
		return '';
	}
	if ( $post->post_status != 'publish' ) {
		return '';
	}
	$comment_list = get_comments( array( 'post_id'=>$post->ID ) );
	$return_content = '<ul class="bnfund-comments">';
	foreach ( $comment_list as $comment ) {
		$return_content .= '<li class="bnfund-comment" id="comment-'.$comment->comment_ID.'">';
		$return_content .= '<div class="comment-author vcard">';					
		if ( function_exists( 'get_avatar' ) ) {
			$return_content .= get_avatar( $comment, 32 );
		}
		$return_content .= '<cite class="fn">';
		$return_content .= get_comment_author_link( $comment->comment_ID );
		$return_content .= '</cite>';
		$return_content .= '</div>';
		$return_content .= $comment->comment_content;
		$return_content .= '</li>';
	}
	
	$return_content .= '</ul>';
	
	if ( comments_open() ) {
		$return_content .= '<div id="bnfund-comment-resp">';
		$return_content .= '<h3>'.__( 'Post a Comment', 'bnfund' ).'</h3>';

		if ( get_option( 'comment_registration' ) && !is_user_logged_in() ) {
			$return_content .= '<p>';
			
			$return_content .= sprintf(
				__( 'You must be <a href="%s">logged in</a> to post a comment.', 'bnfund' ),
				wp_login_url( get_permalink() )
			);
			
		}  else {
			$return_content .= '<form action="'.get_option( 'siteurl' ) .'/wp-comments-post.php" method="post" id="commentform">';
			if ( ! is_user_logged_in() ) {
				$return_content .= '<p><input type="text" name="author" id="author" size="22" tabindex="1"/>';
				$return_content .= '<label for="author"><small>'.__( 'Name', 'bnfund' ).'</small></label></p>';
				$return_content .= '<p><input type="text" name="email" id="email" size="22" tabindex="2" />';
				$return_content .= '<label for="email"><small>'.__( 'Mail (will not be published)', 'bnfund' ).'</small></label></p>';
			}
			$return_content .= '<p><textarea name="comment" id="comment" cols="58" rows="10" tabindex="4"></textarea></p>';
			$return_content .= '<p><input name="submit" type="submit" id="submit" tabindex="5" value="'.esc_attr__( 'Submit Comment', 'bnfund').'" />';
			$return_content .= get_comment_id_fields();
			$return_content .= '</p>';
			$return_content .= '</form>';
		}
		$return_content .= '</div>';
	}
	return $return_content;
}

/**
 * Short code handler for bnfund-days-left shortcode.  Returns the number of days
 * before the campaign ends.  Due to timezone differences if the end date is
 * within 24 hours of the current date, days left will return 1.  If the current
 * date is over 24 hours past the end date, days left will return 0.  Otherwise
 * the actual number of days will be returned.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the days left for by passing a "campaign_id" attribute.
 * @return int The number of days left in the campaign
 */
function bnfund_days_left( $attrs ) {
    $post = _bnfund_get_shortcode_campaign( $attrs );
	if ( ! bnfund_is_bnfund_post( $post ) ){
		return '';
	}
	$postid = $post->ID;
	if ( _bnfund_is_edit_new_campaign() ) {
		$postid = _bnfund_get_new_campaign()->ID;
	}
	$end_date = get_post_meta( $post->ID, '_bnfund_end-date', true );
	$now = time();
	$diff = ( strtotime( $end_date ) - $now );
	$days = round($diff / 86400);
	if ( $days < -1 ) {
		$days = 0;
	} else if ( $days <= 1 ) {
		$days = 1;
	}
	return $days;
}

/**
 * Direct events and campaigns to the proper display template.
 * @return void
 */
function bnfund_display_template() {
	global $post;
	if ( ! bnfund_is_bnfund_post( $post, true ) ){
		//Only change the template for bnfund events and campaigns
		return;
	}
	$options = get_option( 'bnfund_options' );
	$script_reqs = array( 'jquery', 'jquery-ui-dialog', 'jquery-ui-datepicker' );
	if ( $options['allow_registration'] ) {
		$script_reqs[] = 'jquery-form';
	}
    wp_enqueue_script( 'bnfund-user', bnfund_determine_file_location('user','js'),
			$script_reqs, bnfund_VERSION, true );
	wp_enqueue_style( 'bnfund-user', bnfund_determine_file_location('user','css'),
			array(), bnfund_VERSION );
	$admin_email = get_option( 'admin_email' );
	$script_vars = array(
		'cancel_btn' => __( 'Cancel', 'bnfund' ),
		'continue_editing_btn' => __( 'Continue Editing', 'bnfund' ),
		'email_exists' => __( 'This email address is already registered', 'bnfund' ),
		'invalid_email' =>__( 'Invalid email address', 'bnfund' ),
		'login_btn' => __( 'Login', 'bnfund' ),
		'mask_passwd' => __( 'Mask password', 'bnfund' ),
		'ok_btn' => __( 'Ok', 'bnfund' ),
        'processing_msg' => __( 'Processing...', 'bnfund' ),        
		'register_btn' => __( 'Register', 'bnfund' ),
		'register_fail' => sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you.  Please contact <a href="mailto:%s">us</a>.' ), $admin_email ),
		'reg_wait_msg' => __( 'Please wait while your registration is processed.', 'bnfund' ),
		'save_warning' => __( 'Your campaign has not been saved.  If you would like to save your campaign, stay on this page, click on the Edit button and then click on the Ok button.', 'bnfund' ),
        'thank_you_msg' => __( 'Thank you for your donation!', 'bnfund' ),
		'unmask_passwd' => __( 'Unmask password', 'bnfund' ),
		'username_exists' => __( 'This username is already registered', 'bnfund' )        
	);
	if ( ! empty( $options['date_format'] ) ) {
		$script_vars['date_format'] = _bnfund_get_jquery_date_fmt( $options['date_format'] );
	}
	$login_fn = apply_filters( 'bnfund_login_javascript_function', '' );
	if ( ! empty( $login_fn ) ) {
		$script_vars['login_fn'] = $login_fn;
	}
	$register_fn = apply_filters( 'bnfund_register_javascript_function', '' );
	if ( ! empty( $register_fn ) ) {
		$script_vars['register_fn'] = $register_fn;
	}
    $script_vars['validation_rules'] = bnfund_get_validation_js();
	wp_localize_script( 'bnfund-user', 'bnfund', $script_vars );

    wp_enqueue_style( 'jquery-ui-bnfund', bnfund_URL.'css/smoothness/jquery.ui.bnfund.css', array(), '1.8.14' );    

	wp_enqueue_script( 'jquery-validationEngine', bnfund_URL.'js/jquery.validationEngine.js', array( 'jquery'), 1.7, true );
	wp_enqueue_style( 'jquery-validationEngine', bnfund_URL.'css/jquery.validationEngine.css', array(), 1.7 );

	$templates[] = 'page.php';
	$template = apply_filters( 'page_template', locate_template( $templates ) );

	if( '' != $template ) {
		load_template( $template );
		// The exit tells WP to not try to load any more templates
		exit;
	}
}

/**
 * Shortcode handler for bnfund-donate shortcode.  Displays a button for
 * accepting donations.
 * @return string HTML for a donate button.
 */
function bnfund_donate_button() {
	global $post;
	$options = get_option( 'bnfund_options' );
	if ( ! bnfund_is_bnfund_post() ) {
		return '';
	}	
	$page_url = get_permalink( $post );
	$gentime = time();
	$returnparms = array(
		'g' => $gentime,
		'n' => 	wp_create_nonce( 'bnfund-donate-campaign'.$post->ID.$gentime ),
		'bnfund_action'=>'donate-campaign',
		't' => 'pp'
	);
	$return_url = $page_url . '?' . http_build_query($returnparms);
	$returnparms['t'] = 'ipn';
	$notify_url = $page_url . '?' . http_build_query($returnparms);
	$donate_btn = $options['paypal_donate_btn'];
	if ( ! empty( $donate_btn ) ) {
		$btn_doc = new DOMDocument();
		$btn_doc->loadHTML( $donate_btn );
		$form_node = $btn_doc->getElementsByTagName('form')->item(0);
		$form_node->setAttribute( 'class' , 'bnfund-donate-form' );
		_bnfund_create_input_node( $btn_doc, $form_node, 'return', $return_url );
		_bnfund_create_input_node( $btn_doc, $form_node, 'cancel_return', $page_url );
		_bnfund_create_input_node( $btn_doc, $form_node, 'notify_url', $notify_url );
		$tmp_node = $btn_doc->createElement( 'br' );
		$form_node->appendChild($tmp_node);
		$tmp_node = $btn_doc->createElement( 'label',
				__('Anonymous gift', 'bnfund')
		);
		$tmp_node->setAttribute( 'for' , 'bnfund-anonymous-donate' );
		$form_node->appendChild($tmp_node);
		$tmp_node = _bnfund_create_input_node( $btn_doc, $form_node, 'custom', 'anon', 'checkbox' );
		$tmp_node->setAttribute( 'id', 'bnfund-anonymous-donate' );
		$donate_btn = $btn_doc->saveHTML();
		$form_start = strpos( $donate_btn, '<form' );
		$form_length = (strpos( $donate_btn, '</form>', $form_start ) - $form_start) + 7;
		$donate_btn = substr( $donate_btn, $form_start, $form_length );
	}
	$donate_btn = apply_filters( 'bnfund_donate_button', $donate_btn, $page_url, $return_url );
	return $donate_btn;
}


/**
 * Shortcode handler for bnfund-authorize-net-donate-form shortcode.  
 * Displays Authorize.Net donate form
 * @return string HTML for donate form.
 */
function bnfund_authorize_net_donate_form() {
	global $post;
	$options = get_option( 'bnfund_options' );
	if ( ! bnfund_is_bnfund_post() ) {
		return '';
	}	
	
	$api_login_id = $options['authorize_net_api_login_id'];
	$transaction_key = $options['authorize_net_transaction_key'];
	$donate_form = '';
	
	if ( ! empty( $transaction_key ) && ! empty( $api_login_id ) ) {
        $gentime = time();
		$donate_form = '<div class="bnfund-auth-net-donate"><a href="#">Donate</a></div>';
        $donate_form .= '<form class="bnfund-auth-net-form">';
        $donate_form .= _bnfund_auth_net_input( 'cc_first_name', __( 'First Name', 'bnfund' ) );
		$donate_form .= _bnfund_auth_net_input( 'cc_last_name', __( 'Last Name', 'bnfund' ) );
        $donate_form .= _bnfund_auth_net_input( 'cc_address', __( 'Address', 'bnfund' ) );
        $donate_form .= _bnfund_auth_net_input( 'cc_city', __( 'City', 'bnfund' ) );
		$donate_form .= _bnfund_auth_net_state_options();
        $donate_form .= _bnfund_auth_net_input( 'cc_zip', __( 'Zip', 'bnfund' ) );
		$donate_form .= _bnfund_auth_net_input( 'cc_email', __( 'Email', 'bnfund' ) );
        $donate_form .= _bnfund_auth_net_input( 'cc_num', __( 'Credit Card Number', 'bnfund' ) );
		$donate_form .= _bnfund_auth_net_month_options();
        $donate_form .= _bnfund_auth_net_year_options();
        $donate_form .= _bnfund_auth_net_input( 'cc_cvv2', __( 'Security Code', 'bnfund' ) );
		$donate_form .= _bnfund_auth_net_input( 'cc_amount', __( 'Donation Amount', 'bnfund' ) );
        $donate_form .= '<div id="bnfund-input-anonymous"><input id="bnfund-input-anonymous-checkbox" type="checkbox" name="anonymous" value="1"> Anonymous gift</div>';
        $donate_form .= '<input type="hidden" name="post_id" value="' . $post->ID . '">';
        $donate_form .= '<input type="hidden" name="g" value="' . $gentime . '">';
        $donate_form .= '<button type="submit" value="Donate" id="bnfund_donate_button">'.__( 'Donate', 'bnfund' ).'</button>';
        $donate_form .= wp_nonce_field( 'bnfund-donate-campaign'.$post->ID.$gentime, 'n', false, false );
        $donate_form .= '<div class="bnfund-auth-net-secure-donations"><a href="#" class="bnfund-auth-net-secure-donations-link">';
        $donate_form .= __( 'Donations are processed securely via Authorize.Net', 'bnfund' ).'</a></div>';
        $donate_form .= _bnfund_auth_net_secure_donation_text();
        $donate_form .='</form>';
        wp_enqueue_script( 'bnfund-auth-net', bnfund_determine_file_location('auth.net','js'), array(), bnfund_VERSION, true );        
	}
	return $donate_form;
}

/**
 * Shortcode handler for bnfund-edit to generate campaign creation/editing form
 * and button to edit the personal fundraising fields.
 * @return string HTML for form and edit button.
 */
function bnfund_edit() {
	global $post, $current_user;
	$current_user = wp_get_current_user();
	if ( ! bnfund_is_bnfund_post() ){
		return '';
	} else if ( ! _bnfund_current_user_can_create( ) ) {
		return '';	
	} else if ( $post->post_type == 'bnfund_campaign' && $post->post_author != $current_user->ID ) {
		return '';
	}
	if( $post->post_type == 'bnfund_event' ) {
		$editing_campaign = _bnfund_is_edit_new_campaign();
		if ( $editing_campaign ) {
			$campaign = _bnfund_get_new_campaign();
			$campaign_id = $campaign->ID;
			$campaign_title = $campaign->post_title;
		} else {
			$campaign_title = $post->post_title;			
			$campaign_id = null;
		}
		$default_goal = get_post_meta( $post->ID, '_bnfund_event_default_goal', true);
	} else {
		$editing_campaign = true;
		$campaign_id = $post->ID;
		$campaign_title = $post->post_title;
		$campaign = $post;
		$default_goal = '';
	}

	$wait_title = esc_attr__( 'Please wait', 'bnfund' );
	if ( $editing_campaign ) {
		$dialog_title = esc_attr__( 'Edit Campaign', 'bnfund' );
		$dialog_desc = esc_html__( 'Change your campaign by editing the information below.', 'bnfund' );
		$wait_desc = esc_html__( 'Please wait while your campaign is updated.', 'bnfund' );
		$dialog_id = 'bnfund-edit-dialog';
	} else {
		$dialog_title = esc_attr__( 'Create Campaign', 'bnfund' );
		$dialog_desc = esc_html__( 'Please fill in the following information to create your campaign.', 'bnfund' );
		$wait_desc = esc_html__( 'Please wait while your campaign is created.', 'bnfund' );
		$dialog_id = 'bnfund-add-dialog';
	}
	$return_form = '<div id="bnfund-wait-dialog" style="display:none;" title="'.$wait_title.'">';
	$return_form .= '<div>'.$wait_desc.'</div>';
	$return_form .= '</div>';
	$return_form .= '<div id="'.$dialog_id.'" style="display:none;" title="'.$dialog_title.'">';
	$return_form .= '<div>'.$dialog_desc.'</div>';
	$return_form .= '<form enctype="multipart/form-data" action="" method="post" name="bnfund_form" id="bnfund-form">';

	if ( $editing_campaign ) {
		$return_form .= '	<input type="hidden" name="bnfund_action" value="update-campaign"/>';
		$return_form .= '	<input id="bnfund-campaign-id" type="hidden" name="bnfund_campaign_id" value="'.$campaign_id.'"/>';
		$return_form .= wp_nonce_field( 'bnfund-update-campaign'.$campaign_id, 'n', true , false );
	} else {
		$return_form .= '	<input type="hidden" name="bnfund_action" value="create-campaign"/>';
		$return_form .= wp_nonce_field( 'bnfund-create-campaign'.$post->ID, 'n', true , false );
	}
	$return_form .= bnfund_render_fields( $campaign_id, $campaign_title, $editing_campaign, $default_goal );
	$return_form .= '</form>';
	$return_form .= '</div>';
	$validateSlug = array(
		'file' => bnfund_URL.'validate-slug.php',
		'alertTextLoad' => __( 'Please wait while we validate this location', 'bnfund' ),
		'alertText' => __( '* This location is already taken', 'bnfund' )
	);
	if ( $editing_campaign ) {
		$validateSlug['extraData'] = $campaign_id;
	}
	$return_form .= '<button class="bnfund-edit-btn">'. __( 'Edit', 'bnfund' ).'</button>';
	return $return_form;	
}

/**
 * Shortcode handler for bnfund-giver-list shortcode.  Returns markup for the
 * list of supporters for the current campaign.
 * @param array $attrs the attributes for the shortcode.  The supported
 * attributes are:
 * -- row_max -- Number of supporters to display in one row
 * -- row_end_class -- Class to apply to last support in a row.
 * -- campaign_id -- The id of the campaign to display the giver list for.
 * @return string the HTML representing the list of supporters for the current
 * campaign.
 */
function bnfund_giver_list( $attrs ) {
    $post = _bnfund_get_shortcode_campaign( $attrs );
	if ( ! empty( $attrs ) && ! empty( $attrs['row_max'] ) &&
			! empty( $attrs['row_end_class'] ) ) {
		$row_end_class = $attrs['row_end_class'];
		$row_max = $attrs['row_max'];
	} else {
		$row_end_class = 'row-end clearfix';
		$row_max = 3;
	}
	$max_givers = -1;
	if ( ! empty( $attrs['max_givers'] ) ) {
		$max_givers = intval( $attrs['max_givers'] );
	}
	if ( ! bnfund_is_bnfund_post() ) {
		return '';
	} else {		
		$givers = get_post_meta( $post->ID, '_bnfund_givers', true );
		if ( empty( $givers ) ) {
			return '';
		}
		if ( $max_givers > -1 && count($givers) > $max_givers ) {
			$email_array = array_rand( $givers, $max_givers );
		} else {
			$email_array = array_keys( $givers );
		}
		$giver_count = 0;
		$list = '<ul class="bnfund-supporters-list">';
		foreach( $email_array as $email ) {
			$donor = $givers[$email];
			$giver_count++;
			$class = 'bnfund-supporter';
			if ($giver_count % $row_max == 0) {
				$class .= ' '.$row_end_class;
			}
			$list .= '<li class="'.$class.'">';
			$list .= '	<span class="bnfund-supporter-img">';
			$list .= get_avatar($email, '50');
			$list .= '	</span>';
			$list .= '	<span class="bnfund-supporter-name">';
			$list .= $donor['first_name'].' '.$donor['last_name'];
			$list .= '	</span>';
			$list .= '</li>';
		}
		$list .= '</ul>';
		return $list;
	}
}

/**
 * Handler for actions performed on a event.
 * @param mixed $posts The current posts provided by the_posts filter.
 * @return mixed $posts The current posts provided by the_posts filter.
 */
function bnfund_handle_action( $posts ) {
	global $bnfund_processed_action, $bnfund_processing_action, $wp_query;
	if ( empty ( $posts ) ) {
		return $posts;
	}
	$post = $posts[0];
	if ( isset( $wp_query->query_vars['bnfund_action'] ) 
			&& ! $bnfund_processed_action && ! $bnfund_processing_action ) {
		$bnfund_processing_action = true;
		$action = $wp_query->query_vars['bnfund_action'];
		if ( ! in_array( $action, array( 'event-list', 'campaign-list' ) ) ) {			
			if ( ! bnfund_is_bnfund_post( $post ) ){
				return $posts;
			}
			if ( _bnfund_is_edit_new_campaign() ) {
				$referer_action = 'bnfund-'.$action._bnfund_get_new_campaign()->ID;
			} else {
				$referer_action = 'bnfund-'.$action.$post->ID;
			}
			if( $action == 'donate-campaign' ) {
				$referer_action .= $_REQUEST['g'];
			}			
			if( $action == 'user-login' ) {
				global $current_user;
				get_currentuserinfo();
				$save_user = $current_user;
				$current_user = new WP_User(0);
				check_admin_referer( $referer_action, 'n' );
				$current_user = $save_user;
			} else {
				check_admin_referer( $referer_action, 'n' );
			}
		}			
		switch( $action ) {
			case 'campaign-list':
				$posts = _bnfund_campaign_list_page();
				break;
			case 'event-list':
				$posts = _bnfund_event_list_page();
				break;
			case 'create-campaign':
				_bnfund_save_camp( $post, 'add' );
				break;
            case 'campaign-created':
                _bnfund_campaign_created( $post );
                break;
			case 'donate-campaign':
				_bnfund_process_donate( $post );
				break;
			case 'donate-thanks':
				_bnfund_display_thanks();
				break;
			case 'update-campaign':
				_bnfund_save_camp( $post, 'update' );
				break;
			case 'user-login':
				_bnfund_save_camp( $post, 'user-login' );
				break;
		}
		if( ! empty( $posts ) ) {
			$wp_query->is_home = false;
			$wp_query->queried_object = $posts[0];
			$wp_query->queried_object_id = $posts[0]->ID;
			$wp_query->is_page = true;
			$wp_query->is_singular = true;
		}
		$bnfund_processed_action = true;
		$bnfund_processing_action = false;
	}
	return $posts;
}

/**
 * For personal fundraising campaigns, if the user just updated a campaign
 * title, pull the new value from the request; otherwise just use the
 * saved title.
 * @param string $atitle The current title.
 * @param int $post_id The post to display the title for.
 * @return string the title to display.
 */
function bnfund_handle_title( $atitle, $post_id = 0 ) {
    global $post;
	if ( ! bnfund_is_bnfund_post( ) || $post_id != $post->ID ){
		return $atitle;
	}
	return bnfund_get_value( $_REQUEST, 'bnfund-camp-title', $atitle );
}

/**
 * Shortcode handler for bnfund-progress-bar shortcode.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the progress bar for by passing a "campaign_id" attribute.
 * @return string HTML markup for progress bar.
 */
function bnfund_progress_bar( $attrs ) {
    $post = _bnfund_get_shortcode_campaign( $attrs );
	if ( ! bnfund_is_bnfund_post( $post ) ){
		return '';
	}
	$postid = $post->ID;
	if ( _bnfund_is_edit_new_campaign() ) {
		$postid = _bnfund_get_new_campaign()->ID;
	}

	$options = get_option( 'bnfund_options' );
	$goal = get_post_meta( $postid, '_bnfund_gift-goal', true );
	$tally = get_post_meta( $postid, '_bnfund_gift-tally', true );
	if ( $tally == '' ) {
		$tally = 0;
	}
	$remaining = ($goal - $tally);
	$funding_percentage = 1;
	if ( $remaining <= 0 ) {
		$funding_percentage = 1;
	} else if ( $tally < $goal ) {
		$funding_percentage = ($tally / $goal);
	}
	$goal_length = intval( ( 240 * $funding_percentage ) );
	$return_content = '<div class="bnfund-progress-meter ">';
	$return_content .= '	<p class="bnfund-progress-met">';
	$return_content .= '		<span class="bnfund-amount"><sup>'.$options['currency_symbol'].'</sup>';
	$return_content .=				number_format_i18n( floatval( $tally ) );
	$return_content .= '		</span> '.__('Raised', 'bnfund');
	$return_content .= '	</p>';
	$return_content .= '	<div class="bnfund-progress-bar">';
	$return_content .= '		<div class="bnfund-amount-raised" style="width:'.$goal_length.'px;"></div>';
	$return_content .= '	</div>';
	$return_content .= '	<p class="bnfund-progress-goal">'.__('Goal:', 'bnfund').' ';
	$return_content .= '		<span class="bnfund-amount"><sup>'.$options['currency_symbol'].'</sup>';
	$return_content .=				number_format_i18n( floatval( $goal ) );
	$return_content .= '		</span>';
	$return_content .= '	</p>';
	$return_content .= '</div>';
	return $return_content;
}

/**
 * Send an email when a campaign receives a donation
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal. 
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function bnfund_send_donate_email( $transaction_array, $post ) {
    $options = get_option( 'bnfund_options' );
    $author_data = bnfund_get_contact_info( $post, $options );
	if ( ! empty( $author_data->user_email ) && 
            apply_filters ('bnfund_mail_on_donate', true, $transaction_array, $author_data ) ) {		
		$campaignUrl = get_permalink( $post );
		$trans_amount = number_format_i18n( floatval( $transaction_array['amount'] ), 2 );
		if ( $options['mandrill'] ) {
			$merge_vars = array(
				'NAME' => $author_data->display_name,
				'CAMP_TITLE' => $post->post_title,
				'CAMP_URL' => $campaignUrl,
				'DONATE_AMT' => $options['currency_symbol'].$trans_amount
			);			
			if ( isset( $transaction_array['anonymous'] ) &&
					$transaction_array['anonymous'] == true ) {
				$merge_vars['DONOR_ANON'] = 'true';
			} else {
				$merge_vars['DONOR_ANON'] = 'false';
				$merge_vars['DONOR_FNAM'] = $transaction_array['donor_first_name'];
				$merge_vars['DONOR_LNAM'] = $transaction_array['donor_last_name'];
				$merge_vars['DONOR_EMAL'] = $transaction_array['donor_email'];
			}
            bnfund_send_mandrill_email( $author_data->user_email, $merge_vars, __( 'A donation has been received', 'bnfund' ), 'mandrill_email_donate');
		} else {
			$pub_message = sprintf(__( 'Dear %s,', 'bnfund' ), $author_data->display_name ).PHP_EOL;
			if ( isset( $transaction_array['anonymous'] ) &&
				$transaction_array['anonymous'] == true ) {
				$pub_message .= sprintf(__( 'An anonymous gift of %s%s has been received for your campaign, %s.', 'bnfund' ),
						$options['currency_symbol'],
						$trans_amount,
						$post->post_title).PHP_EOL;
			} else {
				$pub_message .= sprintf(__( '%s %s donated %s%s to your campaign, %s.', 'bnfund' ),
						$transaction_array['donor_first_name'],
						$transaction_array['donor_last_name'],
						$options['currency_symbol'],
						$trans_amount,
						$post->post_title).PHP_EOL;
				$pub_message .= sprintf(__( 'If you would like to thank %s, you can email %s at %s.', 'bnfund' ),
						$transaction_array['donor_first_name'],
						$transaction_array['donor_first_name'],
						$transaction_array['donor_email']).PHP_EOL;
			}			
			$pub_message .= sprintf(__( 'You can view your campaign at: %s.', 'bnfund' ), $campaignUrl ).PHP_EOL;
			wp_mail($author_data->user_email, __( 'A donation has been received', 'bnfund' ) , $pub_message);
		}
	}
}

/**
 * Send an email when a campaign goal has been reached.
 * @param array $transaction_array array detailing the donation with the
 * following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   anonymous -- boolean indicating if the gift was anonymous.
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal.
 *		paypal_returned_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 * @param mixed $post the post object containing the campaign.
 */
function bnfund_send_goal_reached_email( $transaction_array, $post, $goal ) {
    $options = get_option( 'bnfund_options' );
    $author_data = bnfund_get_contact_info( $post, $options );
	if ( ! empty( $author_data->user_email ) && 
            apply_filters ('bnfund_mail_on_goal_reached', true, $transaction_array, $author_data  ) ) {		
		$campaignUrl = get_permalink( $post );
		if ( $options['mandrill'] ) {
			$merge_vars = array(
				'NAME' => $author_data->display_name,
				'CAMP_TITLE' => $post->post_title,
				'CAMP_URL' => $campaignUrl,
				'GOAL_AMT' => $options['currency_symbol'].number_format_i18n( floatval( $goal ) )
			);
            bnfund_send_mandrill_email( $author_data->user_email, $merge_vars, __( 'Campaign goal met!', 'bnfund' ), 'mandrill_email_goal');
		} else {
			$pub_message = sprintf(__( 'Dear %s,', 'bnfund' ), $author_data->display_name).PHP_EOL;
		
			$pub_message .= sprintf(__( 'Congratulations!  Your campaign goal of %s has been met!', 'bnfund' ),
					number_format_i18n( floatval( $goal ) ),
					$post->post_title ).PHP_EOL;
			$pub_message .= sprintf(__( 'You can view your campaign at: %s.', 'bnfund' ), $campaignUrl ).PHP_EOL;
			wp_mail( $author_data->user_email, __( 'Campaign goal met!', 'bnfund' ) , $pub_message );
		}
	}
}

/**
 * Setup the short codes that bike nantucket fundraiser uses.
 */
function bnfund_setup_shortcodes() {
	add_shortcode( 'bnfund-authorize-net-donate-form', 'bnfund_authorize_net_donate_form' );
	add_shortcode( 'bnfund-campaign-list', 'bnfund_campaign_list' );
	add_shortcode( 'bnfund-campaign-permalink', 'bnfund_campaign_permalink');
	add_shortcode( 'bnfund-event-list', 'bnfund_event_list' );
	add_shortcode( 'bnfund-comments', 'bnfund_comments' );
	add_shortcode( 'bnfund-days-left', 'bnfund_days_left' );
	add_shortcode( 'bnfund-donate', 'bnfund_donate_button' );
	add_shortcode( 'bnfund-edit', 'bnfund_edit' );
	add_shortcode( 'bnfund-giver-list', 'bnfund_giver_list' );
	add_shortcode( 'bnfund-progress-bar', 'bnfund_progress_bar' );
    add_shortcode( 'bnfund-total-campaigns', 'bnfund_get_total_published_campaigns' );
	add_shortcode( 'bnfund-user-avatar', 'bnfund_user_avatar' );        
	$options = get_option( 'bnfund_options' );
	if ( isset( $options['fields'] ) ) {
		foreach ( $options['fields'] as $field_id => $field ) {
			add_shortcode( 'bnfund-'.$field_id, '_bnfund_dynamic_shortcode' );
		}
	}
}

/**
 * Get the avatar for the user associated to this campaign.
 * @param array $attrs the attributes for the shortcode.  You can specify the
 * size of the avatar by passing a "size" attribute.
 * @return the avatar for the user associated to this campaign.
 */
function bnfund_user_avatar( $attrs ){
	global $post;
	if( $post->ID == null || $post->post_type != 'bnfund_campaign' ) {
		$user_email = '';
	} else {
		$options = get_option( 'bnfund_options' );
		$contact_info = bnfund_get_contact_info( $post, $options );
		$user_email = $contact_info->user_email;
	}
	if ( ! empty ( $attrs['size'] ) ) {
		$size = $attrs['size'];
	} else {
		$size = '';
	}
	return get_avatar( $user_email, $size );
}

/**
 * Generate an input field for the Authorize.Net donation form.
 * @param type $name the name/id of the field.
 * @param type $label the label to display for the field.
 * @return string the generated HTML.
 */
function _bnfund_auth_net_input( $name, $label ) {
	$retval = '<div id="bnfund-input-' . $name . '">';
    $retval .= '<label for="' . $name . '">' . $label . ':</label>';
    $retval .= '<input type="text" name="' . $name . '" id="'. $name .'" class="validate[required]">';
	$retval .= '</div>';
    return $retval;
}

/**
 * Generate the markup for the month options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _bnfund_auth_net_month_options() {
	$retval = '<div>';
    $retval .= '<label for="cc_exp_month">';
    $retval .=  __( 'Expiration Month:', 'bnfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_exp_month" name="cc_exp_month">';
	for ( $i=1; $i<=12; $i++ ) {
		$padded = ( $i < 10 ? '0' : '' ) . $i;
		$retval .= '<option value="' . $padded . '">' . $padded . ' - ' . date("M", mktime(0, 0, 0, $i, 1, 2012)) . '</option>';
    }
    $retval .= '</select></div>';
    return $retval;
}

/**
 * Generate the markup the Authorize.Net security message.
 * @return string the generated HTML.
 */
function _bnfund_auth_net_secure_donation_text() {
    $donation_text = '<div class="bnfund-auth-net-secure-donations-text">';        
    $donation_text .= '<p>';
    $donation_text .= sprintf( __( 'You can donate to %s with confidence.  ', 'bnfund' ), get_bloginfo('name') );
    $donation_text .= __( 'We have partnered with <a target="_blank" href="http://www.authorize.net">Authorize.Net</a>, a leading payment gateway since 1996, to accept credit cards and electronic check payments safely and securely for our donors.', 'bnfund' );
    $donation_text .= '</p>';
    $donation_text .= '<p>';
    $donation_text .= __( 'The Authorize.Net Payment Gateway manages the complex routing of sensitive customer information through the electronic check and credit card processing networks.  ', 'bnfund' );
    $donation_text .= __( 'See an <a target="_blank" href="http://www.authorize.net/resources/howitworksdiagram/">online payments diagram</a> to see how it works.  ', 'bnfund' );
    $donation_text .= '</p>';
    $donation_text .= '<p>'. __(  'The company adheres to strict industry standards for payment processing, including:', 'bnfund' ) . '</p>';
    $donation_text .= '<ul>';
    $donation_text .= '<li>' . __( '128-bit Secure Sockets Layer (SSL) technology for secure Internet Protocol (IP) transactions.', 'bnfund' ) . '</li>';
    $donation_text .= '<li>' . __( 'Industry leading encryption hardware and software methods and security protocols to protect customer information.', 'bnfund' ) . '</li>';
    $donation_text .= '<li>' . __( 'Compliance with the Payment Card Industry Data Security Standard (PCI DSS).', 'bnfund' ) . '</li>';
    $donation_text .= '</ul>';
    $donation_text .= '<p>' . __( 'For additional information regarding the privacy of your sensitive cardholder data, please read the <a target="_blank" href="http://www.authorize.net/company/privacy/">Authorize.Net Privacy Policy</a>.', 'bnfund' ).'</p>';
    $donation_text .= '</div>';
    return $donation_text;
}

/**
 * Generate the markup for the month options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _bnfund_auth_net_state_options() {
	$states = array(
	 'AL' => 'Alabama',
	 'AK' => 'Alaska',
	 'AZ' => 'Arizona',
	 'AR' => 'Arkansas',
	 'CA' => 'California',
	 'CO' => 'Colorado',
	 'CT' => 'Connecticut',
	 'DE' => 'Delaware',
	 'DC' => 'Dist. of Columbia',
	 'FL' => 'Florida',
	 'GA' => 'Georgia',
	 'HI' => 'Hawaii',
	 'ID' => 'Idaho',
	 'IL' => 'Illinois',
	 'IN' => 'Indiana',
	 'IA' => 'Iowa',
	 'KS' => 'Kansas',
	 'KY' => 'Kentucky',
	 'LA' => 'Louisiana',
	 'ME' => 'Maine',
	 'MD' => 'Maryland',
	 'MA' => 'Massachusetts',
	 'MI' => 'Michigan',
	 'MN' => 'Minnesota',
	 'MS' => 'Mississippi',
	 'MO' => 'Missouri',
	 'MT' => 'Montana',
	 'NE' => 'Nebraska',
	 'NV' => 'Nevada',
	 'NH' => 'New Hampshire',
	 'NJ' => 'New Jersey',
	 'NM' => 'New Mexico',
	 'NY' => 'New York',
	 'NC' => 'North Carolina',
	 'ND' => 'North Dakota',
	 'OH' => 'Ohio',
	 'OK' => 'Oklahoma',
	 'OR' => 'Oregon',
	 'PA' => 'Pennsylvania',
	 'RI' => 'Rhode Island',
	 'SC' => 'South Carolina',
	 'SD' => 'South Dakota',
	 'TN' => 'Tennessee',
	 'TX' => 'Texas',
	 'UT' => 'Utah',
	 'VT' => 'Vermont',
	 'VA' => 'Virginia',
	 'WA' => 'Washington',
	 'WV' => 'West Virginia',
	 'WI' => 'Wisconsin',
	 'WY' => 'Wyoming',
	);
		
	$retval = '<div>';
    $retval .= '<label for="cc_state">';
    $retval .= __( 'State:', 'bnfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_state" name="cc_state">';
	foreach ( $states as $abbr => $state ) {
		$retval .= '<option value="' . $abbr . '">' . $abbr . ' - ' . $state . '</option>';
	}
	$retval .= '</select></div>';	
    return $retval;
}

/**
 * Generate the markup for the year options for the Authorize.Net donation form.
 * @return string the generated HTML.
 */
function _bnfund_auth_net_year_options() {
	$retval = '<div>';
    $retval .= '<label for="cc_exp_year">';
    $retval .= __( 'Expiration Year:', 'bnfund' );
    $retval .= '</label>';
    $retval .= '<select id="cc_exp_year" name="cc_exp_year">';
	$year = date("Y");
	for ( $i=$year; $i<=$year + 8; $i++ ) {
		$retval .= '<option value="' . $i . '">' . $i . '</option>';
    }
    $retval .= '</select></div>';
    return $retval;
}

/**
 * Display the list of current campaigns
 * @return array with 1 page/post that contains the campaign list.
 */
function _bnfund_campaign_list_page() {
	$options = get_option( 'bnfund_options' );
	$page = get_page( $options['campaign_root']);
	$page->post_title = __( 'Campaign List', 'bnfund' );
	$page->post_content = bnfund_campaign_list();
	return array( $page );

}

function _bnfund_campaign_created( $campaign ) {
    $update_title = esc_attr__( 'Campaign added', 'bnfund');    
    $camp_url = get_permalink( $campaign->ID );
    $update_message = __( 'Your campaign has been created and is now available for public viewing here at <a href="%s">%s</a>.', 'bnfund' );
    $update_content = sprintf( $update_message, $camp_url, $camp_url );
    _bnfund_set_update_message( $update_title, $update_content);    
}

/**
 * Display the list of current events
 * @return array with 1 page/post that contains the campaign list.
 */
function _bnfund_event_list_page() {
	$options = get_option( 'bnfund_options' );
	$page = get_page( $options['event_root']);
	$page->post_title = __( 'event List', 'bnfund' );
	$page->post_content = bnfund_event_list();
	return array( $page );
}

/**
 * Create an HTML input field in the specified document.
 * @param DOMDocument $doc The HTML document to add the field to.
 * @param DOMNode $parent The parent to add the field to.
 * @param string $name The name of the input field.
 * @param string $value The value for the input field.
 * @param string $type The type of input field.  Defaults to hidden.
 * @return DOMNode The node representing the input field.
 */
function _bnfund_create_input_node( $doc, $parent, $name, $value, $type = 'hidden' ) {
	$input_node = $doc->createElement('input');
	$input_node->setAttribute( 'type', $type );
	$input_node->setAttribute( 'name' , $name );
	$input_node->setAttribute( 'value' , $value );
	return $parent->appendChild( $input_node );
}

/**
 * Determines if current user can create campaigns or edit the current campaign.
 * @param array $bnfund_options The current options for bike nantucket fundraiser.
 * If the value isn't passed in, the option is retrieved.
 * @return boolean true if user can create; otherwise false.
 */
function _bnfund_current_user_can_create( $bnfund_options = array() ) {
	global $bnfund_current_user_can_create;

	if ( isset ($bnfund_current_user_can_create) ) {
		return $bnfund_current_user_can_create;
	} else {
		$bnfund_current_user_can_create = false;
		if ( empty( $bnfund_options ) ) {
			$bnfund_options = get_option( 'bnfund_options' );
		}
		if ( !is_user_logged_in() ) {
			$bnfund_current_user_can_create = ! $bnfund_options['login_required'];
		} else  {
			$bnfund_current_user_can_create = _bnfund_current_user_can_submit( $bnfund_options );
		}
		return $bnfund_current_user_can_create;
	}
}

/**
 * Determines if current user can submit campaigns
 * @param array $bnfund_options The current options for bike nantucket fundraiser.
 * If the value isn't passed in, the option is retrieved.
 * @return boolean true if user can submit; otherwise false.
 */
function _bnfund_current_user_can_submit( $bnfund_options = array() ) {
	if ( empty( $bnfund_options ) ) {
		$bnfund_options = get_option( 'bnfund_options' );
	}
	if ( ! empty ($bnfund_options['submit_role']) ) {
		foreach ( $bnfund_options['submit_role'] as $role ) {
			if ( current_user_can ( $role ) ) {
				return true;
			}
		}
	}
	return false;
}

/**
 * Determine what the status of the campaign should be set to.
 * @param string $current_status The current status of the campaign.
 * @return string the status to use.
 */
function _bnfund_determine_campaign_status( $current_status = '') {
	$options = get_option( 'bnfund_options' );
	if ($current_status == 'publish') {
		return $current_status ;
	}
	if ( _bnfund_current_user_can_submit() ) {
		if ( $options['approval_required'] ) {
			return 'pending';
		} else {
			return 'publish';
		}
	} else {
		return 'draft';		
	}
}

/**
 * Displays a thank you message after a donation has been received.
 */
function _bnfund_display_thanks() {
	global $bnfund_update_message;
	$title = esc_attr__( 'Thanks for donating', 'bnfund');
	$bnfund_update_message = '<div id="bnfund-update-dialog" style="display:none;" title="'.$title.'">';
	$bnfund_update_message .= '<div>'.__( 'Thank you for your donation!', 'bnfund' ).'</div></div>';
}

/**
 * Handler for the dynamic shortcodes created by bike nantucket fundraiser fields.
 * @param array $attrs the attributes for the shortcode
 * @param string $content the content between the shortcode begin and end tags.
 * @param string $tag the name of the shortcode.
 * @return string The data associated to the specified bike nantucket fundraiser field.
 */
function _bnfund_dynamic_shortcode( $attrs, $content, $tag ) {
    $post = _bnfund_get_shortcode_campaign( $attrs );
	if ( ! bnfund_is_bnfund_post( $post ) ){
		return '';
	}

	$postid = $post->ID;	
	if ( _bnfund_is_edit_new_campaign() ) {
		$postid = _bnfund_get_new_campaign()->ID;
	}

	$options = get_option( 'bnfund_options' );
	$field_id = substr( $tag, 6 );
	$field = $options['fields'][$field_id];

	$data = get_post_meta( $postid, '_bnfund_'.$field_id, true );
	$return_data = '';
	switch ( $field['type'] ) {
		case 'end_date':
		case 'date':
			if( ! empty( $data ) ) {
				$return_data = bnfund_format_date( $data , $options['date_format'] );
			}
			break;
		case 'text':
		case 'textarea':
			$return_data = wpautop( make_clickable( $data ) );
			break;
		case 'image':
			if( empty( $data ) && isset( $attrs['default'] ) ) {
				$img_src = $attrs['default'];
			} else if ( ! empty( $data ) ) {
				$img_src = wp_get_attachment_url( $data );
			}
			if ( ! empty( $img_src ) ) {
				$return_data = '<img class="bnfund-img" src="' .$img_src. '" />';
			}
			break;
		case 'user_goal':
		case 'gift_tally':
		case 'giver_tally':
			if ( empty ( $data ) ) {
				$data = '0';
			}
			$return_data = number_format_i18n( floatval( $data ) );
			break;
		default:
			$return_data = apply_filters( 'bnfund_'.$field['type'].'_shortcode', $data );
	}
	if ( ! empty ( $attrs['esc_js'] ) && $attrs['esc_js'] == 'true' ) {
		$return_data = esc_js($return_data);
	}
	return $return_data;
}

/**
 * Convert php date format to jquery date format.
 * Derived from http://icodesnip.com/snippet/php/convert-php-date-style-dateformat-to-the-equivalent-jquery-ui-datepicker-string
 * @param string $date_format php date format to convert.
 * @return string corresponding jquery date format.
 */
function _bnfund_get_jquery_date_fmt( $date_format ) {
    $php_patterns = array(
        //day
        '/d/',        //day of the month
        '/j/',        //day of the month with no leading zeros
        //month
        '/m/',        //numeric month leading zeros
        '/n/',        //numeric month no leading zeros
        //year
        '/Y/',        //full numeric year
        '/y/'     //numeric year: 2 digit
    );
    $jquery_formats = array(
        'dd','d',
        'mm','m',
        'yy','y'
    );
    return preg_replace($php_patterns, $jquery_formats, $date_format);
}

/**
 * When a new campaign has been created, get that campaign.
 * @return mixed the new campaign or null if the current campaign isn't a
 * new campaign.
 */
function _bnfund_get_new_campaign() {
	global $bnfund_new_campaign;
	
	$new_campaign_actions = array( 'update-campaign', 'user-login' );
	$action = bnfund_get_value( $_REQUEST, 'bnfund_action' );
	if ( ! isset( $bnfund_new_campaign ) &&
			isset( $_REQUEST['bnfund_campaign_id'] )
			&& in_array( $action, $new_campaign_actions ) ) {
		$campaign_id = $_REQUEST['bnfund_campaign_id'];
		$campaign = get_post( $campaign_id );
		$referer_action = 'bnfund-'.$action.$campaign_id;
		if( $action == 'user-login' ) {
			global $current_user;
			get_currentuserinfo();
			$save_user = $current_user;
			$current_user = new WP_User( 0 );
		}
		if ( wp_verify_nonce( $_REQUEST['n'], $referer_action ) ) {
			$bnfund_new_campaign = $campaign;
		}
		if( $action == 'user-login' ) {
			$current_user = $save_user;
		}
	}
	return $bnfund_new_campaign;	
}

/*
 * Get the campaign to use for a shortcode based on the attributes passed to 
 * the shortcode.
 * @param array $attrs the attributes for the shortcode.  You can specify which
 * campaign to display the progress bar for by passing a "campaign_id" attribute.
 * @return the campaign to use for the shortcode.
 */
function _bnfund_get_shortcode_campaign($attrs) {
    global $post;
    if ( ! empty ( $attrs['campaign_id'] ) ) {
        $campaign = get_post( $attrs['campaign_id'] );
        wp_enqueue_style( 'bnfund-user', bnfund_determine_file_location('user','css'), 
                array(), bnfund_VERSION );
    } else {
        $campaign = $post;
    }
    return $campaign;
}

/**
 * Generate HTML text input fields.
 * @param string $id DOM id for field.
 * @param string $label Label to display with text input field.
 * @param string $name Input field name.
 * @param string $class Class to apply to input field.
 * @param string $additional_content Additional content to display.
 * @return string The generated HTML.
 */
function _bnfund_generate_input_field( $id, $label, $name, $class, $additional_content = '' ) {
	$input_field = '<li>';
	$input_field .= '	<label for="'.$id.'">'.$label;
	$input_field .= '		<abbr title="'.esc_attr__( 'required', 'bnfund' ).'">*</abbr>';
	$input_field .= '	</label><br/>';
	$input_field .= '	<input id="'.$id.'" type="text" name="'.$name.'" class="'.$class.'" value=""/>';	
	if ( ! empty( $additional_content ) ) {
		$input_field .= $additional_content;
	}
	$input_field .= '</li>';
	return $input_field;
}

/**
 * Determine if a new campaign is being edited
 * @return boolean true if a new campaign is being edited; false otherwise.
 */
function _bnfund_is_edit_new_campaign() {	
	global $bnfund_new_campaign, $bnfund_is_edit_new_campaign;
	if ( ! isset( $bnfund_is_edit_new_campaign ) ) {
		_bnfund_get_new_campaign();
		if ( isset( $bnfund_new_campaign ) ){
			$bnfund_is_edit_new_campaign = true;
		} else {
			$bnfund_is_edit_new_campaign = false;
		}
	}
	return $bnfund_is_edit_new_campaign ;
}
/**
 * Process a donation to a campaign.
 */
function _bnfund_process_donate( $post ){
	//Handle various payment platforms.
	$confirmation_type = $_REQUEST['t'];
	switch( $confirmation_type ) {
		case 'pp':
			$transaction_array = bnfund_process_paypal_pdt();
			break;
		case 'ipn':
			$transaction_array = bnfund_process_paypal_ipn();
			break;
		default:
			$transaction_array = array();
			if (isset($_REQUEST['a'])) {
				$transaction_array['amount'] = $_REQUEST['a'];
				$transaction_array['success'] = true;
			}
	}

	if (isset($_REQUEST['n'])) {
		$transaction_array['transaction_nonce'] = $_REQUEST['n'];
	}

	//Allow integration of other transactions processing systems
	$transaction_array = apply_filters( 'bnfund_transaction_array', $transaction_array );
	if ( ! empty( $transaction_array ) ) {
		do_action( 'bnfund_add_gift', $transaction_array, $post );
	}
	
	//For IPN response exit since this is a server-side call.
	if ( $confirmation_type == 'ipn' ) {
		exit();
	}
	_bnfund_display_thanks();
}

/**
 * Save the campaign
 * @param mixed $post the current event or campaign to use to save the campaign.
 * @param string $update_type either 'add' or 'update.
 */
function _bnfund_save_camp( $post, $update_type = 'add' ) {
	global $bnfund_new_campaign, 			
			$bnfund_is_edit_new_campaign;
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php');

	$options = get_option( 'bnfund_options' );

	if ( ! _bnfund_current_user_can_create( $options ) ) {
		return;
	}

	if ( $update_type == 'user-login' ) {
		$campaign_fields = array();
	} else {
		$campaign_fields = array(
			'post_name' => strip_tags( $_REQUEST['bnfund-camp-location'] ),
			'post_title' => strip_tags( $_REQUEST['bnfund-camp-title'] )
		);
	}

	if ( $update_type == 'update' || $update_type == 'user-login' ) {
		if ( $post->post_type == 'bnfund_event' ) {
			if ( _bnfund_is_edit_new_campaign() ) {
				$campaign = _bnfund_get_new_campaign();
				$campaign_id = $campaign->ID;
				$current_status = $campaign->post_status;
			} else {
				return;
			}
		} else {
			$campaign_id = $_REQUEST['bnfund_campaign_id'];
			$campaign = get_post( $campaign_id );
			$current_status = $campaign->post_status;
		}
	} else {
		$current_status = '';
	}
	$status = _bnfund_determine_campaign_status( $current_status );
	if ( $status != 'publish' ) {
		$campaign_fields['post_status'] = $status;
	}

	if ( $update_type == 'add' ) {
		$campaign_fields['post_type'] = 'bnfund_campaign';
		$campaign_id = wp_insert_post( $campaign_fields );
		update_post_meta( $campaign_id, '_bnfund_event_id', $post->ID );
		$bnfund_is_edit_new_campaign = true;
		$update_title = esc_attr__( 'Campaign added', 'bnfund');
        if ( $status == 'pending' && is_user_logged_in() ) {
            $update_message = __( 'Your campaign has been created and submitted for approval.  Once your campaign has been approved you will receive an email notifying you.', 'bnfund' );
        } else {
            $update_message = __( 'Your campaign has been created. %s','bnfund' );
        }
	} else {
		$campaign_fields['ID'] = $campaign_id;
		if ( $status != 'publish' ) {
			$campaign_fields['post_status'] = $status;
		}
		wp_update_post( $campaign_fields );
		$update_title = esc_attr__( 'Campaign updated', 'bnfund' );
		if ( $status == 'publish' ) {
			$update_message = __( 'Your campaign has been updated and is available for public viewing at <a href="%s">%s</a>.', 'bnfund' );            
		} else if ( $status == 'pending' && is_user_logged_in() ) {
			$update_message = __( 'Your campaign has been updated and submitted for approval.  Once your campaign has been approved you will receive an email notifying you.', 'bnfund' );
		} else {			
			$update_message = __( 'Your campaign has been updated.  %s', 'bnfund' );			
		}
	}
	if ( $update_type != 'user-login' ) {
		bnfund_save_campaign_fields( $campaign_id );
	}
	
	if ($bnfund_is_edit_new_campaign) {
		$bnfund_new_campaign = get_post( $campaign_id );
	}
	
	$additional_content = '';
	if ( $status == 'publish' || ( $status == 'pending' && is_user_logged_in() ) ) {
        if ( $status == 'publish' ) {
            wp_publish_post( $campaign_id );		
            $camp_url = get_permalink( $campaign_id );
            if ( $update_type == 'add' || $update_type == 'user-login' ) {
                $added_parms = array(
                    'bnfund_action' => 'campaign-created',
                    'n' => 	wp_create_nonce( 'bnfund-campaign-created'.$campaign_id ),
                );
                $camp_url .= '?'. http_build_query( $added_parms );
                wp_safe_redirect( $camp_url );
                return;
            }
        } else if ( $status == 'pending' && is_user_logged_in() ) {
            $camp_url = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['campaign_slug'] ).$campaign_id;           
        }
        $update_content = sprintf( $update_message, $camp_url, $camp_url );
	} else {
		$previewparms = array(
			'bnfund_action' => 'user-login',
			'n' => 	wp_create_nonce( 'bnfund-user-login'.$campaign_id ),
			'bnfund_campaign_id' =>$campaign_id
		);
		$preview_url = get_permalink( $post->ID ) . '?'. http_build_query( $previewparms );
		$login_link = wp_login_url( $preview_url );
		if ( $options['allow_registration'] ) {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="bnfund-login-link" href="%s">Login</a> or <a id="bnfund-register-link" href="#">Register</a>.', 'bnfund' ) );
			$additional_content = '<div id="bnfund-register-dialog" style="display:none;" title="'.esc_attr__( 'Register','bnfund' ).'">';
			$additional_content .= '<form name="bnfund_create_account_form" id="bnfund-create-account-form" action="'.bnfund_URL.'register-user.php" method="post">';
			$additional_content .= '<ul class="bnfund-list">';
			$additional_content .= _bnfund_generate_input_field( 
					'bnfund-register-username',
					__( 'Username', 'bnfund' ),
					'bnfund_user_login',
					'validate[required,length[0,60]]' );
			$mask_password = '<div class="bnfund-field-desc"><small><a id="bnfund-mask-pass" href="#">'.__( 'Mask password', 'bnfund' ).'</a></small></div>';
			$additional_content .= _bnfund_generate_input_field( 
					'bnfund-register-pass', __( 'Password', 'bnfund' ),
					'bnfund_user_pass',
					'validate[required,length[0,20]]',
					$mask_password );
			$additional_content .= _bnfund_generate_input_field(
					'bnfund-register-email',
					__( 'Email', 'bnfund' ),
					'bnfund_user_email',
					'validate[required,custom[email],length[0,100]]' );
			$additional_content .= _bnfund_generate_input_field(
					'bnfund-register-fname',
					__( 'First Name', 'bnfund' ),
					'bnfund_user_first_name',
					'validate[required,length[0,100]]' );
			$additional_content .= _bnfund_generate_input_field( 
					'bnfund-register-lname',
					__( 'Last Name', 'bnfund' ),
					'bnfund_user_last_name',
					'validate[required,length[0,100]]' );
			$additional_content .= '</form>';
			$additional_content .= '<form name="bnfund_login_form" id="bnfund-login-form" action="'.wp_login_url().'" method="post">';
			$additional_content .= '<input type="hidden" name="log" id="bnfund-user-login">';
			$additional_content .= '<input type="hidden" name="pwd" id="bnfund-user-pass">';
			$additional_content .= '<input type="hidden" name="redirect_to" value="'.$preview_url.'">';
			$additional_content .= '</ul">';
			$additional_content .= '</form>';
			$additional_content .= '</div>';
		} else {
			$update_message = sprintf( $update_message,
					__( 'To make this campaign available for others to view, please <a id="bnfund-login-link" href="%s">Login</a>.', 'bnfund' ) );
		}
		$update_content = sprintf( $update_message, $login_link );
	}
	_bnfund_set_update_message( $update_title, $update_content. $additional_content );
}

/**
 * Update the total number of unique givers for this campaign.  A giver is
 * considered unique by email address.  All anonymous gifts are considered
 * unique givers.
 * @param <type> $campaign_id
 */
function _bnfund_update_giver_tally( $campaign_id ) {
	$givers = get_post_meta( $campaign_id, '_bnfund_givers', true );
	if ( empty( $givers )) {
		$givers = array();
	}
	$total_giver_tally = 0;
	$new_giver = false;
	$transactions = get_post_meta( $campaign_id, '_bnfund_transactions' );
	foreach ( $transactions as $transaction ) {
		if ( isset( $transaction['anonymous'] ) &&
				$transaction['anonymous'] == true ) {
			$total_giver_tally++;
			$new_giver = true;
		} else if ( ! empty( $transaction['donor_email']) &&
				! array_key_exists( $transaction['donor_email'], $givers ) ) {
			$total_giver_tally++;
			$new_giver = true;
			$givers[$transaction['donor_email']] = array(
				'first_name' => $transaction['donor_first_name'],
				'last_name' => $transaction['donor_last_name'],
			);
		}
	}
	$giver_tally = get_post_meta( $campaign_id, '_bnfund_giver-tally', true );
	if ( empty( $giver_tally ) ) {
		$giver_tally = $total_giver_tally;
	} else if ( $new_giver ) {
		$giver_tally++;
	}
	update_post_meta( $campaign_id, '_bnfund_giver-tally', $giver_tally );
	update_post_meta( $campaign_id, '_bnfund_givers', $givers );
}

/**
 * Create the update message dialog box.
 * @param type $update_title The title to display in the dialog box.
 * @param type $update_content The content to display in the dialog box.
 * @param type $additional_content Additional content that needs to be displayed
 * alongside the update message.
 */
function _bnfund_set_update_message( $update_title, $update_content, $additional_content = '' ) {
    global $bnfund_update_message; 
    $bnfund_update_message = '<div id="bnfund-update-dialog" style="display:none;" title="'.$update_title.'">';
	$bnfund_update_message .= '<div>'.$update_content.'</div></div>';
	$bnfund_update_message .= $additional_content;
}
?>