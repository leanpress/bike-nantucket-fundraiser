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
 * Activate the plugin
 */
function bnfund_activate( $flush_rules = true ) {
	if ( version_compare( get_bloginfo( 'version' ), '3.6', '<' ) ) {
		deactivate_plugins( bnfund_BASENAME );
	} else {
		$options_changed = false;
		$bnfund_options = get_option( 'bnfund_options' );
		if ( ! $bnfund_options ) {
			//Setup default options
			$bnfund_options = array(
				'allow_registration' => false,
				'campaign_slug' => 'give',
				'event_slug' => 'events',
				'currency_symbol' => '$',
				'date_format' => 'm/d/y',
				'login_required' => true,
				'mandrill' => false,
				'use_ssl' => false,
				'authorize_net_test_mode' => false,
				'submit_role' => array( 'administrator' ),
				'fields' => array(
					'camp-title' => array(
						'label' => __( 'Title', 'bnfund' ),
						'desc' => __( 'The title of your campaign', 'bnfund' ),
						'type' => 'camp_title',
						'required' => true
					),
					'camp-location' => array(
						'label' => __( 'URL', 'bnfund' ),
						'desc' => __( 'The URL for your campaign', 'bnfund' ),
						'type' => 'camp_location',
						'required' => true
					),
					'end-date'  => array(
						'label' => __( 'End Date', 'bnfund' ),
						'desc' => __( 'The date your campaign ends', 'bnfund' ),
						'type' => 'end_date',
						'required' => false
					),
					'gift-goal' => array(
						'label' => __( 'Goal', 'bnfund' ),
						'desc' => __( 'The amount you hope to raise', 'bnfund' ),
						'type' => 'user_goal',
						'required' => true
					),
					'gift-tally' => array(
						'label' => __( 'Total Raised', 'bnfund' ),
						'desc' => __( 'Total donations received', 'bnfund' ),
						'type' => 'gift_tally',
						'required' => true
					),
					'giver-tally' => array(
						'label' => __( 'Giver Tally', 'bnfund' ),
						'desc' => __( 'The number of unique givers for the campaign.', 'bnfund' ),
						'type' => 'giver_tally',
						'required' => true
					)
				)
			);
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['event_root'] ) ) {
			$page = array(
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'post_status' => 'publish',
				'post_content' => '',
				'post_name' => '_events_listing',
				'post_title' => __( 'events Listing', 'bnfund' ),
				'post_content' => '',
				'post_type' => 'bnfund_event_list'
			);
			$event_root_id = wp_insert_post( $page );
			$bnfund_options['event_root'] = $event_root_id;
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['campaign_root'] ) ) {
			$page = array(
				'comment_status' => 'closed',
				'ping_status' => 'closed',
				'post_status' => 'publish',
				'post_content' => '',
				'post_name' => '_campaign_listing',
				'post_title' => __( 'Campaign Listing', 'bnfund' ),
				'post_content' => '',
				'post_type' => 'bnfund_campaign_list'
			);
			$event_root_id = wp_insert_post( $page );
			$bnfund_options['campaign_root'] = $event_root_id;
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['date_format'] ) ) {
			$bnfund_options['date_format'] = 'm/d/y';
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['mandrill'] ) ) {
			$bnfund_options['mandrill'] = false;
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['paypal_sandbox'] ) ) {
			$bnfund_options['paypal_sandbox'] = false;
			$options_changed = true;
		}
		
		if ( ! isset( $bnfund_options['use_ssl'] ) ) {
			$bnfund_options['use_ssl'] = false;
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['authorize_net_test_mode'] ) ) {
			$bnfund_options['authorize_net_test_mode'] = false;
			$options_changed = true;
		}
		
		
		if ( ! isset( $bnfund_options['fields']['end-date'] ) ) {
			$bnfund_options['fields']['end-date'] = array(
				'label' => __( 'End Date', 'bnfund' ),
				'desc' => __( 'The date your campaign ends', 'bnfund' ),
				'type' => 'end_date',
				'required' => false
			);
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['fields']['giver-tally'] ) ) {
			$bnfund_options['fields']['giver-tally'] = array(
				'label' => __( 'Giver Tally', 'bnfund' ),
				'desc' => __( 'The number of unique givers for the campaign.', 'bnfund' ),
				'type' => 'giver_tally',
				'required' => true
			);
			$options_changed = true;
		}
		if ( ! isset( $bnfund_options['campaign_listing'] ) ) {
			$bnfund_options['campaign_listing'] = true;
			$options_changed = true;
		}

		if ( ! isset( $bnfund_options['event_listing'] ) ) {
			$bnfund_options['event_listing'] = true;
			$options_changed = true;
		}

        if ( ! isset( $bnfund_options['mandrill_email_publish_html'] ) ) {
            $bnfund_options['mandrill_email_publish_html'] = '<h1>Your campaign has been approved</h1>'.PHP_EOL;
            $bnfund_options['mandrill_email_publish_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_publish_html'] .= 'Your campaign, *|CAMP_TITLE|* has been approved.<br/>'.PHP_EOL;
            $bnfund_options['mandrill_email_publish_html'] .= 'You can view your campaign at: *|CAMP_URL|*.<br/>'.PHP_EOL;
        }
        
        if ( ! isset( $bnfund_options['mandrill_email_publish_text'] ) ) {
            $bnfund_options['mandrill_email_publish_text'] = 'Dear *|NAME|*'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_publish_text'] .= 'Your campaign, *|CAMP_TITLE|* has been approved.'.PHP_EOL;
            $bnfund_options['mandrill_email_publish_text'] .= 'You can view your campaign at: *|CAMP_URL|*.'.PHP_EOL;
        }        
        
        if ( ! isset( $bnfund_options['mandrill_email_donate_html'] ) ) {
            $bnfund_options['mandrill_email_donate_html'] = '<h1>Your fundraiser received a donation!</h1>'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= '*|IF:DONOR_ANON=true|*'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= 'An anonymous gift of *|DONATE_AMT|* has been received for your fundraiser, *|CAMP_TITLE|*.<br/>'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= '*|ELSE:|*'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= '*|DONOR_FNAM|* *|DONOR_LNAM|* donated *|DONATE_AMT|* to your fundraiser, <a href="*|CAMP_URL|*">*|CAMP_TITLE|*</a>.<br/>'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= 'If you would like to thank *|DONOR_FNAM|*, you can email *|DONOR_FNAM|* at *|DONOR_EMAL|*.<br/>'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_html'] .= '*|END:IF|*'.PHP_EOL;
        }
        
        if ( ! isset( $bnfund_options['mandrill_email_donate_text'] ) ) {
            $bnfund_options['mandrill_email_donate_text'] = 'Dear *|NAME|*,'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='*|IF:DONOR_ANON=true|*'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='An anonymous gift of *|DONATE_AMT|* has been received for your fundraiser, *|CAMP_TITLE|*.'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='*|ELSE:|*'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='*|DONOR_FNAM|* *|DONOR_LNAM|* donated *|DONATE_AMT|* to your fundraiser, *|CAMP_TITLE|*.'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='If you would like to thank *|DONOR_FNAM|*, you can email *|DONOR_FNAM|* at *|DONOR_EMAL|*.'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='*|END:IF|*'.PHP_EOL;
            $bnfund_options['mandrill_email_donate_text'] .='You can view your fundraiser at: *|CAMP_URL|*.'.PHP_EOL;
        }
        
        if ( ! isset( $bnfund_options['mandrill_email_goal_html'] ) ) {
            $bnfund_options['mandrill_email_goal_html'] = '<h1>Your fundraiser has reached its goal!</h1>'.PHP_EOL;
            $bnfund_options['mandrill_email_goal_html'] .= 'Dear *|NAME|*,<br/><br/>'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_goal_html'] .= 'Congratulations!  Your campaign goal of *|GOAL_AMT|* has been met!<br/>'.PHP_EOL.PHP_EOL;
			$bnfund_options['mandrill_email_goal_html'] .= 'You can view your campaign at: *|CAMP_URL|*.<br/>'.PHP_EOL.PHP_EOL;
        }
                
        if ( ! isset( $bnfund_options['mandrill_email_goal_text'] ) ) {
            $bnfund_options['mandrill_email_goal_text'] = 'Dear *|NAME|*,'.PHP_EOL.PHP_EOL;
            $bnfund_options['mandrill_email_goal_text'] .= 'Congratulations!  Your campaign goal of *|GOAL_AMT|* has been met!'.PHP_EOL;
			$bnfund_options['mandrill_email_goal_text'] .= 'You can view your campaign at: *|CAMP_URL|*.'.PHP_EOL;
        }
        
		if ( isset( $bnfund_options['version'] ) ) {
			$old_version = 	$bnfund_options['version'];
		} else {
			$old_version = 	'0.7';
		}

		if ( version_compare( $old_version, '0.7.3', '<' ) ) {
			_bnfund_add_sample_event();
			if ( ! in_array( 'administrator', $bnfund_options['submit_role'] ) ) {
				$bnfund_options['submit_role'][] = 'administrator';
				$options_changed = true;
			}
		}

		if ( empty( $bnfund_options['paypal_donate_btn'] ) ) {
			$sample_btn = '<form action="" method="post">';
			$sample_btn .= '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" onclick="alert(\'This is a test button.  Please view the readme to setup your PayPal donate button.\');return false;">';
			$sample_btn .= '<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">';
			$sample_btn .= '</form>';
			$bnfund_options['paypal_donate_btn'] = $sample_btn;
			$options_changed = true;
		}

		if ( $old_version != bnfund_VERSION ) {
			$bnfund_options['version'] = bnfund_VERSION;
			$options_changed = true;
		}
		if ( $options_changed == true ) {
			update_option( 'bnfund_options', $bnfund_options );
		}
	}
	_bnfund_register_types();

	$role = get_role( 'administrator' );
	if ( !empty( $role ) ) {
		$role->add_cap( 'edit_campaign' );
	}
	bnfund_add_rewrite_rules( $flush_rules );
}

/**
 * Add personal fundraiser rewrite rules
 * @param boolean $flush_rules If true, flush the rewrite rules
 */
function bnfund_add_rewrite_rules( $flush_rules = true ) {
	$options = get_option( 'bnfund_options' );
	$campaign_root = $options['campaign_slug'];
	$event_root = $options['event_slug'];	
	if ( $options['campaign_listing'] ) {
		add_rewrite_rule("$campaign_root$", "index.php?bnfund_action=campaign-list",'top');
	}
	add_rewrite_rule($campaign_root.'/([0-9]+)/?', 'index.php?post_type=bnfund_campaign&p=$matches[1]&preview=true','top');
	if ( $options['event_listing']  ) {
		add_rewrite_rule("$event_root$", "index.php?bnfund_action=event-list",'top');
	}
	if ( $flush_rules ) {
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin.
 */
function bnfund_init() {
	global $bnfund_processed_action;
	$bnfund_options = get_option( 'bnfund_options' );
	if ( ! isset( $bnfund_options['version'] ) || $bnfund_options['version'] != bnfund_VERSION ) {
        //DO NOT flush rewrite rules in this case, beevent this was triggered by the init action which
        //means not all rewrite rules have been declared.
		bnfund_activate(false);
	}
	$bnfund_processed_action = false;
	_bnfund_load_translation_file();
	_bnfund_register_types();
	bnfund_add_rewrite_rules( false );	
	if ( ! is_admin() ) {
		bnfund_setup_shortcodes();
	}
}


/**
 * Before personal fundraiser options are saved, add/update sort order for the
 * fields.
 * @param mixed $new_options The options that are about to be saved.
 * @param mixed $old_options The current options.
 * @return mixed the options to save.
 */
function bnfund_pre_update_options( $new_options, $old_options ) {
	$i=0;
	foreach ( $new_options['fields'] as $idx => $field) {
		$field['sortorder'] = $i++;		
		$new_options['fields'][$idx] = $field;		
	}

	$checkboxes = array( 'allow_registration', 'approval_required', 
		'campaign_listing','event_listing','login_required',  'mandrill',
		'paypal_sandbox', 'use_ssl', 'authorize_net_test_mode' );
	foreach ( $checkboxes as $field_name) {
		if ( isset( $new_options[$field_name] )
				&& $new_options[$field_name] == 'true' ) {
			$new_options[$field_name] = true;
		} else {
			$new_options[$field_name] = false;
		}
	}

    if ( is_array( $old_options ) ) {
        $new_options = array_merge( $old_options, $new_options );
    }
	return $new_options;
}


/**
 * Add personal fundraiser query vars.
 * @param array $query_array current list of query vars
 * @return array updated list of query vars.
 */
function bnfund_query_vars( $query_array ) {
	$query_array[] = 'bnfund_action';
	$query_array[] = 'bnfund_event_id';
	return $query_array;
}


/**
 * Handler that fires when personal fundraiser options are updated.
 * @param mixed $oldvalue options before they were updated.
 * @param mixed $newvalue options after they were updated.
 */
function bnfund_update_options( $oldvalue, $newvalue ) {
	_bnfund_register_types();
	$current_submit_roles = $newvalue['submit_role'];

	global $wp_roles;
	$avail_roles = $wp_roles->get_names();
	foreach ( $avail_roles as $key => $desc ) {
		$role = get_role( $key );
		if( in_array ( $key, $current_submit_roles ) ) {
			$role->add_cap( 'edit_campaign' );		
		} else {
			$role->remove_cap( 'edit_campaign' );
		}
	}
	bnfund_add_rewrite_rules();	
}

/**
 * Adds a sample event for plugin demonstration purposes.
 */
function _bnfund_add_sample_event() {
	$stat_li = '<li class="bnfund-stat"><span class="highlight">%s</span>%s</li>';
	$sample_content = '<ul>';
	$sample_content .= sprintf( $stat_li, '$[bnfund-gift-goal]', __( 'funding goal', 'bnfund' ) );
	$sample_content .= sprintf( $stat_li, '$[bnfund-gift-tally]', __( 'raised', 'bnfund' ) );
	$sample_content .= sprintf( $stat_li, '[bnfund-giver-tally]', __( 'givers', 'bnfund' ) );
	$sample_content .= sprintf( $stat_li, '[bnfund-days-left]', __( 'days left', 'bnfund' ) );
	$sample_content .= '</ul>';
	$sample_content .= '<div style="clear: both;">';
	$sample_content .= '	<p>'.__( 'I have an event on [bnfund-end-date] that I am involved with for my event.', 'bnfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'I am hoping to raise $[bnfund-gift-goal] for my event.', 'bnfund' ).'</p>';
	$sample_content .= '	<p>'.__( 'So far I have raised $[bnfund-gift-tally].  If you would like to contribute to my event, click on the donate button below:', 'bnfund' ).'</p>';
	$sample_content .= '	<p>[bnfund-donate]<p>';
	$sample_content .= '</div>';
	$sample_content .= '[bnfund-edit]';
		
	$event = array(
		'post_name' => 'sample-event',
		'post_title' => __( 'Help Raise Money For My event', 'bnfund' ),
		'post_content' => $sample_content,
		'post_status' => 'publish',
		'post_type' => 'bnfund_event'
	);
	$event_root_id = wp_insert_post( $event );
}

/**
 * Loads the translation file; fired from init action.
 */
function _bnfund_load_translation_file() {
	load_plugin_textdomain( 'bnfund', false, bnfund_FOLDER . 'translations' );
}

/**
 * Register the post types used by personal fundraiser.
 */
function _bnfund_register_types() {
	$bnfund_options = get_option( 'bnfund_options' );
	$template_def = array(
		'public' => true,
		'query_var' => 'bnfund_event',
		'rewrite' => array(
			'slug' => $bnfund_options['event_slug'],
			'with_front' => false,
		),
		'label' => __( 'events', 'bnfund' ),
		'labels' => array(
			'name' => __( 'events', 'bnfund' ),
			'singular_name' => __( 'event', 'bnfund' ),
			'add_new' => __( 'Add New event', 'bnfund' ),
			'add_new_item' => __( 'Add New event', 'bnfund' ),
			'edit_item' => __( 'Edit event', 'bnfund' ),
			'view_item' => __( 'View event', 'bnfund' ),
			'search_items' => __( 'Search events', 'bnfund' ),
			'not_found' => __( 'No events Found', 'bnfund' ),
			'not_found_in_trash' => __( 'No events Found In Trash', 'bnfund' ),
		)
	);
	register_post_type( 'bnfund_event', $template_def );
	register_post_type( 'bnfund_event_list' );

	$campaign_def = array(
		'public' => true,
		'query_var' => 'bnfund_campaign',
		'rewrite' => array(
			'slug' => $bnfund_options['campaign_slug'],
			'with_front' => false
		),
		'label' => __( 'Campaigns', 'bnfund' ),
		'labels' => array(
			'name' => __( 'Campaigns', 'bnfund' ),
			'singular_name' => __( 'Campaign', 'bnfund' ),
			'add_new' => __( 'Add New Campaign', 'bnfund' ),
			'add_new_item' => __( 'Add New Campaign', 'bnfund' ),
			'edit_item' => __( 'Edit Campaign', 'bnfund' ),
			'view_item' => __( 'View Campaign', 'bnfund' ),
			'search_items' => __( 'Search Campaigns', 'bnfund' ),
			'not_found' => __( 'No Campaigns Found', 'bnfund' ),
			'not_found_in_trash' => __( 'No Campaigns Found In Trash', 'bnfund' ),
		),
		'supports' => array(
			'title','comments', 'thumbnail',
		),
		'capabilities' => array(
			'edit_post' => 'edit_campaign'
		),
		'map_meta_cap' => true
	);
	register_post_type( 'bnfund_campaign', $campaign_def );
	register_post_type( 'bnfund_campaign_list' );
}

// hook into the init action and call create_campaign_team_taxonomies when it fires
add_action( 'init', 'create_campaign_team_taxonomies', 0 );

// create team taxonomy for the post type "campaign"
function create_campaign_team_taxonomies() {
		// Add new taxonomy, NOT hierarchical (like tags)
	$labels = array(
		'name'                       => _x( 'Teams', 'taxonomy general name' ),
		'singular_name'              => _x( 'Team', 'taxonomy singular name' ),
		'search_items'               => __( 'Search Teams' ),
		'popular_items'              => __( 'Popular Teams' ),
		'all_items'                  => __( 'All Teams' ),
		'parent_item'                => null,
		'parent_item_colon'          => null,
		'edit_item'                  => __( 'Edit Team' ),
		'update_item'                => __( 'Update Team' ),
		'add_new_item'               => __( 'Add New Team' ),
		'new_item_name'              => __( 'New Team Name' ),
		'separate_items_with_commas' => __( 'Separate teams with commas' ),
		'add_or_remove_items'        => __( 'Add or remove teams' ),
		'choose_from_most_used'      => __( 'Choose from the most used teams' ),
		'not_found'                  => __( 'No teams found.' ),
		'menu_name'                  => __( 'Teams' ),
	);

	$args = array(
		'hierarchical'          => false,
		'labels'                => $labels,
		'show_ui'               => true,
		'show_admin_column'     => true,
		'update_count_callback' => '_update_post_term_count',
		'query_var'             => true,
		'rewrite'               => array( 'slug' => 'team' ),
	);

	register_taxonomy( 'team', 'bnfund_campaign', $args );
}

?>