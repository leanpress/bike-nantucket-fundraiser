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

/**
 * Adds a meta box to the campaign edit screen so that admins can manually add outside donations.
 */
function bnfund_add_donation_box() {
    global $post;
    if ( isset( $post ) && $post->post_status == 'publish' ) {
?>
        <ul>
            <li>
                <label for="bnfund-donor-first-name">Donor First Name</label>
                <input id="bnfund-donor-first-name" name="bnfund-donor-first-name" type="text" />
            </li>
            <li>
                <label for="bnfund-donor-last-name">Donor Last Name</label>
                <input id="bnfund-donor-last-name" name="bnfund-donor-last-name" type="text" />
            </li>
            <li>
                <label for="bnfund-donor-last-name">Donor Email</label>
                <input name="bnfund-donor-email" type="text" />
            </li>
            <li>
                <label for="bnfund-donation-ammount">Donation Amount</label>
                <input class="validate[required]" id="bnfund-donation-amount" name="bnfund-donation-amount" type="text" />
            </li>
            <li>
                <label for="bnfund-anonyous-donation">Anonymous</label>
                <input id="bnfund-anonyous-donation" name="bnfund-anonyous-donation" type="checkbox" value="true"/>
            </li>
            <li>
                <label for="bnfund-donation-comment">Comment</label>
                <textarea id="bnfund-donation-comment" name="bnfund-donation-comment"></textarea>
            </li>
            <li>
                <input class="button-primary" id="bnfund-add-donation" name="bnfund-add-donation" type="submit" value="<?php esc_attr_e('Add Donation', 'bnfund' ) ?>" />
            </li>
        </ul>
<?php
    } else {
        _e('Campaign must be published before donations can be accepted.', 'bnfund');
    }
}
/**
 * Add the admin css to the page if applicable.
 */
function bnfund_admin_css() {
	if ( bnfund_is_bnfund_post() ) {
		wp_enqueue_style( 'bnfund_admin', bnfund_determine_file_location('admin','css'), array(), bnfund_VERSION );
	}
}

/**
 * Fired by redirect_post_location so that we can notify the admin that the
 * donation they manually added was processed.
 * @param string $location the redirect location to navigate to.
 * @return string the redirect location to navigate to with a parameter
 * specifying that a donation was processed.
 */
function bnfund_admin_donation_added( $location ) {
	$location = add_query_arg( 'message', 1001, $location );
	return $location;
}

/**
 * Initialize administrator functionality.
 */
function bnfund_admin_init() {
	$options = get_option( 'bnfund_options' );	
	register_setting( 'bnfund_options', 'bnfund_options' );
	add_settings_section( 'bnfund_main_options', __( 'Bike Nantucket Fundraiser Options', 'bnfund' ), 'bnfund_main_section_text', 'bnfund' );
	add_settings_field(
		'bnfund_campaign_slug',
		__( 'Campaign Slug', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'campaign_slug',
			'value' => $options['campaign_slug']
		)
	);
	add_settings_field(
		'bnfund_campaign_listing',
		__( 'Use Campaign Listing Page', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'campaign_listing',
			'type' => 'checkbox',
			'value' => $options['campaign_listing']
		)
	);
	add_settings_field(
		'bnfund_event_slug',
		__( 'event Slug', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'event_slug',
			'value' => $options['event_slug']
		)
	);
	add_settings_field(
		'bnfund_event_listing',
		__( 'Use event Listing Page', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'event_listing',
			'type' => 'checkbox',
			'value' => $options['event_listing']
		)
	);

	add_settings_field(
		'bnfund_currency_symbol',
		__( 'Currency Symbol', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'currency_symbol',
			'value' => $options['currency_symbol']
		)
	);
	add_settings_field(
		'bnfund_date_format',
		__( 'Date Format', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_main_options',
		array(
			'name' => 'date_format',
			'value' => bnfund_get_value($options, 'date_format', 'm/d/y' )
		)
	);
	add_settings_section(
		'bnfund_permission_options',
		__( 'Campaign Creation Options', 'bnfund' ),
		'bnfund_permissions_section_text',
		'bnfund'
	);
	add_settings_field(
		'bnfund_login_required',
		__( 'Login Required to Create', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_permission_options',
		array(
			'name' => 'login_required',
			'type' => 'checkbox',
			'value' => $options['login_required']
		)
	);
	add_settings_field(
		'bnfund_allow_registration',
		__( 'Allow Users To Register', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_permission_options',
		array(
			'name' => 'allow_registration',
			'type' => 'checkbox',
			'value' => $options['allow_registration']
		)
	);
	add_settings_field(
		'bnfund_approval_required',
		__( 'Campaigns Require Approval', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_permission_options',
		array(
			'name' => 'approval_required',
			'type' => 'checkbox',
			'value' => $options['approval_required']
		)
	);
	add_settings_field(
		'bnfund_submit_role',
		__( 'User Roles that can submit campaigns', 'bnfund' ),
		'bnfund_role_select_field',
		'bnfund',
		'bnfund_permission_options',
		array(
			'name' => 'submit_role',
			'value' => $options['submit_role']
		)
	);
	add_settings_section(
		'bnfund_paypal_options',
		__( 'PayPal Options', 'bnfund' ),
		'bnfund_paypal_section_text',
		'bnfund'
	);
	add_settings_field(
		'bnfund_paypal_donate_btn',
		__( 'Donate Button Code', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_paypal_options',
		array(
			'name' => 'paypal_donate_btn',
			'value' => bnfund_get_value( $options, 'paypal_donate_btn' )
		)
	);
	add_settings_field(
		'bnfund_paypal_pdt_token',
		__( 'Payment Data Transfer Token', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_paypal_options',
		array(
			'name' => 'paypal_pdt_token',
			'value' => bnfund_get_value( $options, 'paypal_pdt_token' )
		)
	);
	$paypal_sandbox = bnfund_get_value( $options , 'paypal_sandbox',  false );
	add_settings_field(
		'bnfund_paypal_sandbox',
		__( 'Use PayPal Sandbox', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_paypal_options',
		array(
			'name' => 'paypal_sandbox',
			'type' => 'checkbox',
			'value' => $paypal_sandbox
		)
	);

	
	// Authorize.Net settings
	add_settings_section(
		'bnfund_authorize_net_options',
		__( 'Authorize.Net Options', 'bnfund' ),
		'bnfund_authorize_net_section_text',
		'bnfund'
	);
	add_settings_field(
		'bnfund_authorize_net_api_login_id',
		__( 'API Login ID', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_authorize_net_options',
		array(
			'name' => 'authorize_net_api_login_id',
			'value' => bnfund_get_value( $options, 'authorize_net_api_login_id' ),
            'class' => 'regular-text code'
		)
	);
	add_settings_field(
		'bnfund_authorize_net_transaction_key',
		__( 'Transaction Key', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_authorize_net_options',
		array(
			'name' => 'authorize_net_transaction_key',
			'value' => bnfund_get_value( $options, 'authorize_net_transaction_key' ),
            'class' => 'regular-text code'
		)
	);
	add_settings_field(
		'authorize_net_product_name',
		__( 'Product/Donation name (for Authorize.Net reports)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_authorize_net_options',
		array(
			'name' => 'authorize_net_product_name',
			'value' => bnfund_get_value( $options, 'authorize_net_product_name' ),
            'class' => 'regular-text code'
		)
	);
	$use_ssl = bnfund_get_value( $options, 'use_ssl', false ); 
	add_settings_field(
		'bnfund_use_ssl',
		__( 'Use SSL (Required for Authorize.Net - only turn off for testing)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_authorize_net_options',
		array(
			'name' => 'use_ssl',
			'type' => 'checkbox',
			'value' => $use_ssl
		)
	);
	$auth_net_test_mode = bnfund_get_value( $options, 'authorize_net_test_mode', false ); 
	add_settings_field(
		'bnfund_authorize_net_test_mode',
		__( 'Test Mode (Requires Authorize.Net Test Account)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_authorize_net_options',
		array(
			'name' => 'authorize_net_test_mode',
			'type' => 'checkbox',
			'value' => $auth_net_test_mode
		)
	);
	add_settings_section(
		'bnfund_mandrill_options',
		__( 'Mandrill Options', 'bnfund' ),
		'bnfund_mandrill_section_text',
		'bnfund'
	);
	$use_mandrill = bnfund_get_value( $options, 'mandrill', false );
	add_settings_field(
		'bnfund_use_mandrill',
		__( 'Use Mandrill to send emails', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill',
			'type' => 'checkbox',
			'value' => $use_mandrill
		)
	);
	add_settings_field(
		'bnfund_mandrill_key',
		__( 'Mandrill API key', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_api_key',
			'value' => bnfund_get_value( $options, 'mandrill_api_key' ),
            'class' => 'regular-text code'
		)
	);
	add_settings_field(
		'bnfund_mandrill_email_publish_html',
		__( 'Campaign Approval HTML Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_publish_html',
			'value' => bnfund_get_value( $options, 'mandrill_email_publish_html' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);    
	add_settings_field(
		'bnfund_mandrill_email_publish_text',
		__( 'Campaign Approval Text Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_publish_text',
			'value' => bnfund_get_value( $options, 'mandrill_email_publish_text' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);    
    
	add_settings_field(
		'bnfund_mandrill_email_publish_template',
		__( 'Campaign Approval Email Template (Optional)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_publish_template',
			'value' => bnfund_get_value( $options, 'mandrill_email_publish_template' ),
            'class' => 'regular-text code'
		)
	);
	add_settings_field(
		'bnfund_mandrill_email_donate_html',
		__( 'Campaign Donation HTML Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_donate_html',
			'value' => bnfund_get_value( $options, 'mandrill_email_donate_html' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);    
	add_settings_field(
		'bnfund_mandrill_email_donate_text',
		__( 'Campaign Donation Text Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_donate_text',
			'value' => bnfund_get_value( $options, 'mandrill_email_donate_text' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);
	add_settings_field(
		'bnfund_mandrill_email_donate_template',
		__( 'Campaign Donation Email Template (Optional)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_donate_template',
			'value' => bnfund_get_value( $options, 'mandrill_email_donate_template' ),
            'class' => 'regular-text code'
		)
	);
	add_settings_field(
		'bnfund_mandrill_email_goal_html',
		__( 'Goal Reached HTML Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_goal_html',
			'value' => bnfund_get_value( $options, 'mandrill_email_goal_html' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);    
	add_settings_field(
		'bnfund_mandrill_email_goal_text',
		__( 'Goal Reached Text Email', 'bnfund' ),
		'bnfund_option_text_area',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_goal_text',
			'value' => bnfund_get_value( $options, 'mandrill_email_goal_text' ),
            'attrs' => 'rows="10" cols="50"'
		)
	);    
	add_settings_field(
		'bnfund_mandrill_email_goal_template',
		__( 'Goal Reached Email Template (Optional)', 'bnfund' ),
		'bnfund_option_text_field',
		'bnfund',
		'bnfund_mandrill_options',
		array(
			'name' => 'mandrill_email_goal_template',
			'value' => bnfund_get_value( $options, 'mandrill_email_goal_template' ),
            'class' => 'regular-text code'
		)
	);    
	add_settings_section(
		'bnfund_field_options',
		__( 'bike nantucket fundraiser Fields', 'bnfund' ),
		'bnfund_field_section_text',
		'bnfund'
	);
}

/**
 * Add admin specific javascript
 */
function bnfund_admin_js() {
	wp_enqueue_script( 'bnfund_admin', bnfund_determine_file_location('admin','js'),
			array( 'jquery'), bnfund_VERSION, true );
	wp_enqueue_script( 'jquery-validationEngine', bnfund_URL.'js/jquery.validationEngine.js', array( 'jquery'), 1.7, true );
	wp_enqueue_style( 'jquery-validationEngine', bnfund_URL.'css/jquery.validationEngine.css', array(), 1.7 );    
    wp_dequeue_script( 'autosave' );
}

/**
 * Initialize admin
 */
function bnfund_admin_setup() {
	$menu = add_options_page( __( 'bike nantucket fundraiser Settings', 'bnfund' ), __( 'bike nantucket fundraiser', 'bnfund' ),
			'manage_options', 'personal-fundraiser-settings', 'bnfund_options_page');
	add_action( 'load-'.$menu, 'bnfund_admin_js' );
	add_meta_box( 'bnfund-campaign-meta', __( 'Personal Fundraising fields', 'bnfund' ), 'bnfund_campaign_meta', 'bnfund_campaign', 'normal', 'high' );
    add_meta_box( 'bnfund-shortcode-list', __( 'Personal Fundraising shortcodes', 'bnfund' ), 'bnfund_shortcode_list', 'bnfund_campaign', 'normal', 'high' );
	add_meta_box( 'commentsdiv', __( 'Donation Listing', 'bnfund' ), 'bnfund_transaction_listing', 'bnfund_campaign', 'normal', 'high' );
    add_meta_box( 'bnfund-add-donation-fields', __( 'Add Donation', 'bnfund' ), 'bnfund_add_donation_box', 'bnfund_campaign', 'side');
	add_meta_box( 'bnfund-event-meta', __( 'Personal Fundraising fields', 'bnfund' ), 'bnfund_event_meta', 'bnfund_event', 'normal', 'high' );
    add_meta_box( 'bnfund-reset-author', __( 'Reset Author', 'bnfund'), 'bnfund_reset_author', 'bnfund_campaign', 'side');
}

/**
 * Display the meta fields for the specified campaign.
 * @param mixed $post The campaign to display meta fields for.
 */
function bnfund_campaign_meta( $post ) {
	$event_id = get_post_meta( $post->ID, '_bnfund_event_id', true );
	$events = get_posts(
		array(
			'post_type' => 'bnfund_event',
			'orderby' => 'title',
			'order' => 'ASC',
			'posts_per_page' => -1
		)
	);
	$event_select = '<select name="bnfund-event-id" id="bnfund-event-id">';
	foreach ($events as $event) {
		$event_select .= '<option value="'.$event->ID.'"'.selected($event_id, $event->ID, false).'>';
		$event_select .= $event->post_title;
		$event_select .= '</option>';		
	}
	$event_select .= '</select>';
?>	
	<ul>
        <?php
            echo bnfund_render_field_list_item( $event_select, array(
                'name' => 'bnfund-event-id',
                'label' => __( 'event', 'bnfund' )
			) );
            echo bnfund_render_fields( $post->ID, $post->post_title );
        ?>
	</ul>
<?php
}

/**
 * Add custom columns to the campaign listing in admin.
 * @param array $columns The currently defined columns.
 * @return array The list of columns to display.
 */
function bnfund_campaign_posts_columns( $columns ) {
    $columns['event'] = __( 'event', 'bnfund' );
	$columns['user'] = __( 'User', 'bnfund' );
	$columns['goal'] = __( 'Goal', 'bnfund' );
	$columns['tally'] = __( 'Raised', 'bnfund' );
    return $columns;
}

/**
 * Get the data for the custom columns in the campaign listing in admin.
 * @param string $column_name the name of the column to retrieve data for.
 * @param string $campaign_id the id of the campaign to retrieve data for.
 */
function bnfund_campaign_posts_custom_column( $column_name, $campaign_id ) {
	switch ( $column_name ) {
		case 'event':
			$event_id = get_post_meta( $campaign_id, '_bnfund_event_id', true );
			$event = get_post( $event_id );
            if ( ! isset( $event ) ) {
                return;
            }
			$edit_link = get_edit_post_link( $event_id );
			$post_type_object = get_post_type_object( $event->post_type );
			$can_edit_post = current_user_can( $post_type_object->cap->edit_post,  $event_id  );

			echo '<strong>';
			if ( $can_edit_post && $event->post_status != 'trash' ) {
?>
				<a class="row-title" href="<?php echo $edit_link; ?>" title="<?php echo esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $event->post_title ) ); ?>"><?php echo $event->post_title ?></a>
<?php

			} else {
				echo $event->post_title;

			}
			echo '</strong>';
			break;
		case 'goal':
			echo get_post_meta( $campaign_id, '_bnfund_gift-goal', true );
			break;
		case 'tally':
			echo get_post_meta( $campaign_id, '_bnfund_gift-tally', true );
			break;
		case 'user':
			global $post;
			$author = get_userdata( $post->post_author );
			if ( $author ) {
				echo strip_tags( $author->display_name );
			}
			break;
	}
}

/**
 * Add custom sortable columns to the campaign listing in admin.
 * @param array $columns The currently defined sortable columns.
 * @return array The list of sortable columns.
 */
function bnfund_campaign_sortable_columns( $columns ) {
	$columns['event'] = 'event';
	$columns['user'] = 'user';
	return $columns;
}

/**
 * Fired by the comment_row_actions action to filter the comment actions
 * for bike nantucket fundraiser campaigns since most of the comments actions are not
 * applicable for bike nantucket fundraiser campaigns.
 * @param array $actions the current comment row actions.
 * @return array the comment row actions to use.
 */
function bnfund_comment_row_actions( $actions ) {
    global $post;
    if ( isset( $post ) && $post->post_type == 'bnfund_campaign') {
        $newactions = array();
        if ( isset( $actions['edit'] ) ) {
            $newactions['edit'] = $actions['edit'];
        }
        return $newactions;
    }
    return $actions;
}

/**
 * Display the meta fields for the specified event.
 * @param mixed $post The event to display meta fields for.
 */
function bnfund_event_meta( $post ) {
	$event_description = get_post_meta( $post->ID, '_bnfund_event_description', true);
	$event_default_goal = get_post_meta( $post->ID, '_bnfund_event_default_goal', true);

?>
	<ul>
		<li>
			<label for="bnfund-event-description"><?php _e( 'event Description', 'bnfund' );?></label>
			<textarea class="bnfund-textarea" id="bnfund-event-description" name="bnfund-event-description" rows="10" cols="50"><?php echo $event_description;?></textarea>
		</li>
		<li>
			<label for="bnfund-event-default-goal"><?php _e( 'Default Goal', 'bnfund' );?></label>
			<input type ="text" id="bnfund-event-default-goal" name="bnfund-event-default-goal" value="<?php echo $event_default_goal;?>"/>
		</li>
<?php
		$event_image = get_post_meta( $post->ID, '_bnfund_event_image', true );
		echo _bnfund_render_image_field( array(
			'name' => 'bnfund-event-image',
			'label' =>__( 'event Image', 'bnfund' ),
			'value' => $event_image
		) );
?>
	</ul>
<?php
}

/**
 * Modify admin form to allow file uploads.
 */
function bnfund_edit_form_tag() {
	global $post;
	if ( bnfund_is_bnfund_post() ){
		echo ' enctype="multipart/form-data"';
	}
}

/**
 * Text to display in personal fundraising settings in the bike nantucket fundraiser Fields
 * section.
 */
function bnfund_field_section_text() {
	echo '<p>'.__( 'Define your fields for bike nantucket fundraisers', 'bnfund' ).'</p>';
}

/**
 * Send an email when a campaign gets published (approved).
 * @param int $post_id Id of the campaign.
 * @param mixed $post the post object containing the campaign
 */
function bnfund_handle_publish( $post_id, $post ) {
	$sent_mail = get_post_meta( $post_id, '_bnfund_emailed_published', true );
    $options = get_option( 'bnfund_options' );
    $author_data = bnfund_get_contact_info( $post, $options );
	$campaignUrl = get_permalink( $post );    
	if ( empty( $sent_mail ) && ! empty( $author_data->user_email ) && 
            apply_filters( 'bnfund_mail_on_publish', true, $post, $author_data, $campaignUrl ) ) {		
		if ( $options['mandrill'] ) {
			$merge_vars = array(
				'NAME' => $author_data->display_name,
				'CAMP_TITLE' => $post->post_title,
				'CAMP_URL' => $campaignUrl
			);
			bnfund_send_mandrill_email( $author_data->user_email, $merge_vars, __( 'Your campaign has been approved', 'bnfund' ), 'mandrill_email_publish');
		} else {
			$pub_message = sprintf( __( 'Dear %s,', 'bnfund' ), $author_data->display_name ).PHP_EOL;
			$pub_message .= sprintf( __( 'Your campaign, %s has been approved.', 'bnfund' ), $post->post_title).PHP_EOL;
			$pub_message .= sprintf( __( 'You can view your campaign at: %s.', 'bnfund' ), $campaignUrl ).PHP_EOL;
			wp_mail( $author_data->user_email, __( 'Your campaign has been approved', 'bnfund' ) , $pub_message );
		}
		add_post_meta( $post_id, '_bnfund_emailed_published', true );
	}
}

/**
 * AJAX function to get the list of donations for the current campaign.
 */
function bnfund_get_donations_list() {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-comments-list-table.php' );
	require_once( bnfund_DIR . '/includes/class-bnfund-donor-list-table.php' );    
	global $post_id;

    check_ajax_referer( 'get-donations' );

	set_current_screen( 'bnfund-donations-list' );

	$wp_list_table = new bnfund_Donor_List_Table( array( 
        'screen' => get_current_screen()
    ));

	if ( !current_user_can( 'edit_post', $post_id ) ) {
		die( '-1' );
    }

	$wp_list_table->prepare_items();
	if ( !$wp_list_table->has_items() ) {
		die( '1' );
    }

	$x = new WP_Ajax_Response();
	ob_start();
	foreach ( $wp_list_table->items as $comment ) {
		get_comment( $comment );
		$wp_list_table->single_row( $comment );
	}
	$donation_list = ob_get_contents();
	ob_end_clean();

	$x->add( array(
		'what' => 'donations',
		'data' => $donation_list
	) );
	$x->send();	
}

/**
 * Text to display in personal fundraising settings in the Mandrill section.
 */
function bnfund_mandrill_section_text() {
	echo '<p>'.__( 'Mandrill settings for bike nantucket fundraiser', 'bnfund' ).'</p>';
	$options = get_option( 'bnfund_options' );
}

/**
 * Text to display in personal fundraising settings in the main section. 
 */
function bnfund_main_section_text() {
	$options = get_option( 'bnfund_options' );
	echo '<input type="hidden" value="'.$options['version'].'" name="bnfund_options[version]">';
	echo '<p>'.__( 'General settings for bike nantucket fundraiser', 'bnfund' ).'</p>';
}

/**
 * Render a textarea field in the personal fundraising settings.
 * @param array $config Array containing the name and value of to use to
 * render the textarea field.
 */
function bnfund_option_text_area( $config ) {
	$value = $config['value'];
	$name = $config['name'];
    if ( isset( $config['attrs'] ) ) {
        $additional_attributes = $config['attrs'];
    } else {
        $additional_attributes = "";
    }
	echo "<textarea class='large-text code' $additional_attributes name='bnfund_options[$name]'>$value</textarea>";
}

/**
 * Render an input text field in the personal fundraising settings.
 * @param array $config Array containing the name and value of to use to
 * render the input text field.
 */
function bnfund_option_text_field( $config ) {
	$value = $config['value'];
	$name = $config['name'];
	$type = bnfund_get_value( $config, 'type', 'text' );
	if ( $type == 'checkbox' ) {
		$checked = checked($value, true, false);
		$value = "true";
	} else {
		$checked = '';
	}
    if ( isset( $config['class'] ) ) {
        $class = "class='".$config['class']."'";
    } else {
        $class = "";
    }

	// echo the field
	echo "<input id='$name' name='bnfund_options[$name]' type='$type' value='$value' $checked $class/>";
}

/**
 * Render the personal fundraising options page.
 */
function bnfund_options_page() {
?>
	<div class="wrap">
	<?php screen_icon(); ?>
		<h2><?php _e( 'bike nantucket fundraiser', 'bnfund' );?></h2>
		<form action="options.php" method="post">
		<?php
			settings_fields( 'bnfund_options' );
			do_settings_sections( 'bnfund' );
			_bnfund_option_fields();

		?>
		<input name="Submit" type="submit" value="<?php esc_attr_e( 'Save Changes', 'bnfund' );?>">
		</form>
		<table style="display:none;">
		<?php
			_bnfund_render_option_field( '_bnfund-template-row', array( 'type' => 'text' ) );
		?>
		</table>

	</div>
<?php
}

/**
 * Text to display in personal fundraising settings in the PayPal section.
 */
function bnfund_paypal_section_text() {
	echo '<p>'.__( 'PayPal settings for bike nantucket fundraiser', 'bnfund' ).'</p>';
}

/**
 * Text to display in personal fundraising settings in the permissions section.
 */
function bnfund_permissions_section_text() {
	echo '<p>'.__( 'Settings to determine who can create or submit campaigns', 'bnfund' ).'</p>';
}


/**
 * Text to display in personal fundraising settings in the Authorize.Net section.
 */
function bnfund_authorize_net_section_text() {
	echo '<p>'.__( 'Authorize.Net settings for bike nantucket fundraiser', 'bnfund' ).'</p>';
}


/**
 * Add a settings link to the plugin listing
 * @param array $links Array of links for plugin listing
 * @param string $file Name of plugin file
 * @return array the array of links for plugin listing.
 */
function bnfund_plugin_action_links( $links, $file ) {
	if( bnfund_BASENAME == $file ) {
		$links[] = sprintf( '<a href="admin.php?page=personal-fundraiser-settings">%s</a>', __('Settings') );
	}
	return $links;
}

/**
 * Use custom updated messages for bike nantucket fundraiser events and campaigns.
 * Fires through the post_updated_messages filter.
 * @param array $messages the currently defined messages.
 * @return array the messages appropriate to the type of post.
 */
function bnfund_post_updated_messages( $messages ) {
	global $post;
	if ( isset( $post ) ) {
		switch ($post->post_type) {
			case 'bnfund_event':
				$messages['post'][1] = sprintf( __('event updated. <a href="%s">View event</a>', 'bnfund'), esc_url( get_permalink( $post->ID ) ) );
				$messages['post'][4] = __( 'event updated.', 'bnfund' );
				$messages['post'][6] = sprintf( __('event published. <a href="%s">View event</a>', 'bnfund'), esc_url( get_permalink( $post->ID ) ) );
				$messages['post'][10] = sprintf( __('event draft updated. <a target="_blank" href="%s">Preview event</a>', 'bnfund'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) );
				break;
			case 'bnfund_campaign':
				$messages['post'][1] = sprintf( __('Campaign updated. <a href="%s">View campaign</a>', 'bnfund'), esc_url( get_permalink( $post->ID ) ) );
				$messages['post'][4] = __( 'Campaign updated.', 'bnfund' );
				$messages['post'][6] = sprintf( __('Campaign published. <a href="%s">View campaign</a>', 'bnfund'), esc_url( get_permalink( $post->ID ) ) );
				$messages['post'][10] = sprintf( __('Campaign draft updated. <a target="_blank" href="%s">Preview campaign</a>', 'bnfund'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) );
				$messages['post'][1001] = __( 'Donation added.', 'bnfund' );
				$messages['post'][1002] = __( 'Author set to email address.', 'bnfund');
				break;
		}
	}
	return $messages;
}

/**
 * Adds a meta box to the campaign edit screen so that you can set the user email
 * as the author of the post.
 */
function bnfund_reset_author() {
    global $post;
    if ( isset( $post ) && $post->post_status == 'publish' ) {
?>
    <ul>
        <li>
                <input class="button-primary" id="bnfund-reset-author" name="bnfund-reset-author" type="submit" value="<?php esc_attr_e('Reset Author', 'bnfund' ) ?>" />
        </li>
    </ul>
<?php
    } else {
        _e('Campaign must be published before resetting the author.', 'bnfund');
    }
}

/**
 * Fired by _bnfund_reset_author so that we can notify the admin that the
 * post author has been updated.
 * @param string $location the redirect location to navigate to.
 * @return string the redirect location to navigate to with a parameter
 * specifying that the author has been updated.
 */
function bnfund_reset_author_location ( $location ) {
        $location = add_query_arg( 'message', 1002, $location);
        return $location;
} 

/**
 * Render an role drop down field in the personal fundraising settings.
 * @param array $config Array containing the name and current value of to use
 * to render the drop down field.
 */
function bnfund_role_select_field( $config ) {
	$value = $config['value'];
	$name = $config['name'];
	global $wp_roles;
	$avail_roles = $wp_roles->get_names();
	echo '<fieldset>';
	$i=0;
	foreach ( $avail_roles as $key => $desc ) {
		echo '<label for="bnfund_options['.$name.']_'.$i.'">';
		echo '<input type="checkbox" value="'.$key.'"'.checked( in_array( $key, $value ), true, false ).' name="bnfund_options['.$name.']['.$i.']"/>';
		echo $desc;
		echo '</label><br/>';
		$i++;
	}
	echo '</fieldset>';
}

/**
 * Save the personal fundraising fields when saving the post.
 * @param string $post_id The id of the post to save the fields for.
 */
function bnfund_save_meta( $post_id, $post ) {
	switch ($post->post_type) {
		case 'bnfund_event':
			_bnfund_save_event_fields( $post_id );
			break;
		case 'bnfund_campaign':
			if ( isset ( $_REQUEST['bnfund-add-donation'] ) ) {
				_bnfund_add_admin_donation( $post_id, $post );
			} else if (isset ( $_REQUEST['bnfund-reset-author'] ) ) {
                _bnfund_reset_author( $post_id, $post );
            } else {
                if ( isset ( $_REQUEST['bnfund-event-id'] ) ) {
                    update_post_meta($post_id, '_bnfund_event_id', $_REQUEST['bnfund-event-id'] );
                } 
                update_post_meta($post_id, '_bnfund_camp-location', $post->post_name );
                update_post_meta($post_id, '_bnfund_camp-title', $post->post_title );
                bnfund_save_campaign_fields( $post_id );
            }           
            break;
	}		
}

/**
 * Display the shortcodes for the specified campaign.
 * @param mixed $post The campaign to display shortcodes for.
 */
function bnfund_shortcode_list( $post ) {
?>
    <ul>
    <?php
        _bnfund_create_shortcode( __( 'Permalink', 'bnfund' ), 'bnfund-campaign-permalink', $post);
        _bnfund_create_shortcode( __( 'Days Left', 'bnfund' ), 'bnfund-days-left', $post);
        _bnfund_create_shortcode( __( 'Funding Goal', 'bnfund' ), 'bnfund-gift-goal', $post);
        _bnfund_create_shortcode( __( 'Funding Raised', 'bnfund' ), 'bnfund-gift-tally', $post);
        _bnfund_create_shortcode( __( 'Number of Contributors', 'bnfund' ), 'bnfund-giver-tally', $post);
        _bnfund_create_shortcode( __( 'List of Contributors','bnfund' ), 'bnfund-giver-list', $post);
        _bnfund_create_shortcode( __( 'Progress Bar', 'bnfund' ), 'bnfund-progress-bar', $post);
    ?>
    </ul>    
<?php    
}

/**
 * Display the list of donations for a campaign.
 * @param mixed $post the campaign to display the list of donations.
 */
function bnfund_transaction_listing( $post ){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-comments-list-table.php' );
    require_once( bnfund_DIR . '/includes/class-bnfund-donor-list-table.php' );
    global $wpdb, $post_ID;

	$total = $wpdb->get_var( $wpdb->prepare( "SELECT count(1) FROM $wpdb->comments WHERE comment_post_ID = '%d' AND ( comment_approved = '0' OR comment_approved = '1')", $post_ID));

	if ( 1 > $total ) {
		echo '<p>' . __( 'No donations yet.', 'bnfund' ) . '</p>';
		return;
    }

	wp_nonce_field( 'get-donations', 'bnfund_get_donations_nonce' );

	$wp_list_table = new bnfund_Donor_List_Table();
	$wp_list_table->display( true );

    $csv_link = bnfund_URL.'csv-export.php';  
    $csv_link = add_query_arg( array(
        'p' => $post_ID,
        'n' => wp_create_nonce ('bnfund-campaign-csv'.$post_ID )
    ), $csv_link );

?>
<p class="hide-if-no-js"><a href="#donationstatusdiv" id="bnfund-show-donations" data-bnfund-donation-start="0" data-bnfund-donation-total="<?php echo $total;?>"><?php _e('Show donations','bnfund'); ?></a> <img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" /></p>
<p><a href="<?php echo $csv_link;?>"><?php _e('Download CSV', 'bnfund');?></a>

<?php
    $script_vars = array(
		'show_more_donations' => __( 'Show more donations', 'bnfund' ),
		'no_more_donations' => __( 'No more donations found.', 'bnfund' ),
    );
    $script_vars['validation_rules'] = bnfund_get_validation_js();
    wp_localize_script( 'bnfund_admin', 'bnfund', $script_vars);
}

function _bnfund_add_admin_donation( $post_id, $post ) {
	$transaction_time = time();
	$transaction_array = array(        
		'amount' => floatval( $_REQUEST['bnfund-donation-amount'] ),
		'anonymous' => isset( $_REQUEST['bnfund-anonyous-donation'] ),
		'donor_email' => is_email( $_REQUEST['bnfund-donor-email'] ),
		'donor_first_name'=> strip_tags( $_REQUEST['bnfund-donor-first-name'] ),
		'donor_last_name'=>  strip_tags( $_REQUEST['bnfund-donor-last-name'] ),
		'success' => true,
		'transaction_nonce' => wp_create_nonce( 'bnfund-donate-campaign'.$post_id.$transaction_time),
		'comment' => strip_tags( $_REQUEST['bnfund-donation-comment'] )
	);
	do_action( 'bnfund_add_gift', $transaction_array, $post );
    add_filter( 'redirect_post_location', 'bnfund_admin_donation_added' );
}

function _bnfund_create_shortcode( $label, $shortcode, $campaign ) {
    $campaign_attr = 'campaign_id="'.$campaign->ID.'"';
    echo "<li>$label: <b>[$shortcode  $campaign_attr]</b></li>";
}

/**
 * Render a hidden type field in the admin options page.
 * @param string $field_id the id of the field this field type is for.
 * @param string $field_type the field type value.
 */
function _bnfund_hidden_type_field( $field_id, $field_type ) {
?>
	<input name="bnfund_options[fields][<?php echo $field_id; ?>][type]" type="hidden" value="<?php echo $field_type;?>">
<?php
	
}

/**
 * Display the personal fundraising fields
 */
function _bnfund_option_fields() {
?>
	<table id="bnfund-fields-table" class="widefat page">
		<thead>
			<tr>
				<th scope="col"><?php _e( 'Label', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Description', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Type', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Data', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Required', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Shortcode', 'bnfund' ) ?></th>
				<th scope="col"><?php _e( 'Actions', 'bnfund' ) ?></th>
			</tr>
		</thead>
        <tbody>
<?php
	$options = get_option( 'bnfund_options' );
	$fields = bnfund_get_value( $options, 'fields', array() );
	if ( count( $fields ) == 0 ) {
		$fields[1] = array(
			'type' => 'text'
		);
	}
	foreach ( $fields as $field_id => $field ) {		
		_bnfund_render_option_field( $field_id, $field );
	}
?>
			<tr class="bnfund-add-row">
				<td colspan="5" style="text-align: right;">
					<a href="#" class="bnfund-add-field"><?php _e( 'Add New Field', 'bnfund' ) ?></a>
				</td>
			</tr>
		</tbody>
	</table>
<?php
}

/**
 * Render a row on the bike nantucket fundraiser fields section of the settings.
 * @param string $field_id The id of the field to add.
 * @param array $field the definition of the field.
 */
function _bnfund_render_option_field( $field_id, $field ) {
	$fieldtypes = array(
		'date' => __( 'Date Selector', 'bnfund' ),
		'textarea' => __( 'Large Text Input (textarea)', 'bnfund' ),
		'image' => __( 'Image', 'bnfund' ),
		'select' => __( 'Select Dropdown', 'bnfund' ),
		'text' => __( 'Text Input', 'bnfund' ),
		'fixed' => __( 'Fixed Input', 'bnfund' ),
		'user_email' => __( 'User Email', 'bnfund' ),
		'user_displayname' => __( 'User Display Name', 'bnfund' )
	);
	$fieldtypes = apply_filters( 'bnfund_field_types' , $fieldtypes );
	$field_label = bnfund_get_value( $field, 'label' );
	$field_desc = bnfund_get_value( $field, 'desc' );
?>
	<tr class="form-table bnfund-field-row" id="<?php echo $field_id; ?>">
		<td>
			<input class="bnfund-label-field"  name="bnfund_options[fields][<?php echo $field_id; ?>][label]" type='text' value="<?php echo $field_label;?>"/>
		</td>
		<td>
			<textarea class="bnfund-desc-field"  name="bnfund_options[fields][<?php echo $field_id; ?>][desc]"><?php echo $field_desc;?></textarea>
		</td>
		<td>
<?php
		$can_delete_field = false;
		switch( $field['type'] ) {
			case 'camp_location':
				_e( 'Campaign URL slug', 'bnfund' );
				break;
			case 'camp_title':
				_e( 'Campaign Title', 'bnfund' );
				break;
			case 'end_date':
				_e( 'End Date', 'bnfund' );
				break;
			case 'user_goal':
				_e( 'User Goal', 'bnfund' );
				break;
			case 'gift_tally':
				_e( 'Total Raised', 'bnfund' );
				break;
			case 'giver_tally':
				_e( 'Giver Tally', 'bnfund' );				
				break;
			default:
				$can_delete_field = true;
?>
				<select id="bnfund-field-select-<?php echo $field_id; ?>" class="bnfund-type-field" name="bnfund_options[fields][<?php echo $field_id; ?>][type]">
<?php
					foreach( $fieldtypes as $type => $label ) {
?>
						<option value="<?php echo $type ?>"<?php selected( $field['type'], $type );?>><?php echo $label ?></option>
<?php
					}
?>
				</select>
<?php
		}
		if ( ! $can_delete_field ) {
			_bnfund_hidden_type_field( $field_id, $field['type'] );
		}
?>
		</td>
		<td>
<?php
			$content = '';
			$sample_style = "display:none;";
			$field_data = bnfund_get_value( $field, 'data' );
			switch( $field['type'] ) {
				case 'select':
					$sample_style = "";
					$content .= bnfund_render_select_field( $field_data );
					$content .= '<br/>';
					break;
			}
?>
			<div class="bnfund-data-type-sample" style="<?php echo $sample_style;?>">
				<div class="bnfund-data-sample-view">
					<?php echo $content; ?>
				</div>
				<a href="#" class="bnfund-data-field-edit"><?php _e( 'Edit', 'bnfund' );?></a>
			</div>
			<div class="bnfund-data-type-edit" style="display:none;">
				<textarea class="large-text code" name="bnfund_options[fields][<?php echo $field_id; ?>][data]"><?php echo $field_data; ?></textarea>
				<br/><a href="#" class="bnfund-data-field-update"><?php _e( 'Update', 'bnfund' );?></a>
			</div>

		</td>
		<td>
<?php
			if ( $can_delete_field || $field['type'] == 'end_date' ) {
				$required = bnfund_get_value( $field, 'required', false );
?>
				<input class="bnfund-required-field"  name="bnfund_options[fields][<?php echo $field_id; ?>][required]" type='checkbox' value="true" <?php checked( $required, 'true' );?> />
<?php
			} else {
				_e( 'Yes', 'bnfund' );
?>
				<input name="bnfund_options[fields][<?php echo $field_id; ?>][required]" type='hidden' value="true">
<?php
			}
?>

		</td>
		<td class="bnfund-shortcode-field">
<?php

			if ( isset ( $field['label'] ) ) {
				echo bnfund_determine_shortcode( $field_id, $field['type'] );
			}
?>
		</td>
		<td>
<?php
			if ( $can_delete_field ) {
?>
				<a class="bnfund-delete-field" href="#" field-id="<?php echo $field_id; ?>">Delete</a><br/>
<?php
			}
?>
				<a class="bnfund-move-up-field" href="#" field-id="<?php echo $field_id; ?>">Move Up</a><br/>
				<a class="bnfund-move-dn-field" href="#" field-id="<?php echo $field_id; ?>">Move Down</a><br/>
		</td>

	</tr>
<?php
}

function _bnfund_reset_author( $post_id, $post ) {
    $options = get_option('bnfund_options');
    $author_data = bnfund_get_contact_info( $post, $options);
    
    if( ! empty( $author_data->user_email)) {
        $author_user = get_user_by('email', $author_data->user_email );
        if ( $author_user) {
            $author_user_id = $author_user->ID;
        } else {
            $new_user = array (
                'bnfund_user_login' => $author_data->user_email,
                'bnfund_user_email' => $author_data->user_email
            );
            if( isset($_POST['bnfund-first-name'])) {
                $new_user['bnfund_user_first_name'] = $_POST['bnfund-first-name'];
            }
            if( isset($_POST['bnfund-last-name'])) {
                $new_user['bnfund_user_last_name'] = $_POST['bnfund-last-name'];
            }
            require_once( bnfund_DIR . '/includes/functions.php' ); 
            $author_user_id = bnfund_register_user( $new_user );
        }
        remove_action('save_post', 'bnfund_save_meta');
        wp_update_post(array(
            'post_author' => $author_user_id,
            'ID' => $post_id
            ));
        add_action('save_post', 'bnfund_save_meta');
    }
    add_filter( 'redirect_post_location', 'bnfund_reset_author_location' );
}    
/**
 * Save the meta fields for the specified event.
 * @param string $event_id The id of the event to save meta fields for.
 */
function _bnfund_save_event_fields( $event_id ) {
	_bnfund_attach_uploaded_image( 'bnfund-event-image', $event_id, '_bnfund_event_image' );
	if ( isset( $_REQUEST['bnfund-event-description'] ) ) {
		update_post_meta( $event_id, "_bnfund_event_description",
				strip_tags( $_REQUEST['bnfund-event-description'] ) );
	}
	if ( isset( $_REQUEST['bnfund-event-default-goal'] ) ) {
		update_post_meta( $event_id, "_bnfund_event_default_goal",
				intval( $_REQUEST['bnfund-event-default-goal'] ) );
	}
}

?>