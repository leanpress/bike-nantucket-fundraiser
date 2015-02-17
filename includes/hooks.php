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

//Activation of plugin
register_activation_hook( bnfund_FOLDER . 'personal-fundraiser.php', 'bnfund_activate' );
//Deactivation of plugin
//register_deactivation_hook( bnfund_FOLDER . 'personal-fundraiser.php', 'bnfund_deactivate' );

// Make sure everything is set after upgrade.
add_filter( 'upgrader_post_install', 'bnfund_activate' );

add_action( 'init', 'bnfund_init' );

if ( is_admin() ) {
	add_action( 'add_meta_boxes_bnfund_campaign', 'bnfund_admin_js' );

	add_action( 'admin_init', 'bnfund_admin_init' );

	add_action( 'admin_menu', 'bnfund_admin_setup' );

	add_action( 'admin_print_styles-post.php', 'bnfund_admin_css' );

    add_action( 'comment_row_actions', 'bnfund_comment_row_actions' );

	add_action( 'manage_edit-bnfund_campaign_sortable_columns', 'bnfund_campaign_sortable_columns' );
	
	add_filter( 'manage_bnfund_campaign_posts_columns', 'bnfund_campaign_posts_columns' );

	add_action( 'manage_bnfund_campaign_posts_custom_column', 'bnfund_campaign_posts_custom_column', 10, 2 );

	add_filter( 'plugin_action_links', 'bnfund_plugin_action_links', 10, 2 );

	add_action( 'post_edit_form_tag', 'bnfund_edit_form_tag' );

	add_filter( 'post_updated_messages', 'bnfund_post_updated_messages' );

	add_filter( 'pre_update_option_bnfund_options', 'bnfund_pre_update_options', 10, 2 );
	
	add_action( 'publish_bnfund_campaign', 'bnfund_handle_publish', 10, 2 );

	add_action( 'save_post', 'bnfund_save_meta', 10, 2);

	add_action( 'update_option_bnfund_options', 'bnfund_update_options', 10, 2 );

	add_action( 'wp_ajax_bnfund_get_donations_list', 'bnfund_get_donations_list' );
}

add_action( 'bnfund_add_gift', 'bnfund_add_gift', 10, 2 );

add_action( 'bnfund_processed_transaction', 'bnfund_send_donate_email', 10, 2 );

add_action( 'bnfund_reached_user_goal', 'bnfund_send_goal_reached_email', 10, 3 );

add_filter( 'bnfund_render_field_list_item', 'bnfund_render_field_list_item', 10, 2 );

add_action( 'template_redirect', 'bnfund_display_template' );

add_filter( 'the_posts', 'bnfund_handle_action' ) ;

add_filter( 'the_content', 'bnfund_handle_content' );

add_filter( 'the_title', 'bnfund_handle_title', 10, 2 );

add_filter( 'query_vars', 'bnfund_query_vars' );

add_action( 'wp', 'bnfund_force_ssl_for_campaign_pages' );

add_action( 'wp_ajax_nopriv_bnfund_auth_net_donation', 'bnfund_auth_net_donation' );

add_action( 'wp_ajax_bnfund_auth_net_donation', 'bnfund_auth_net_donation' );

?>