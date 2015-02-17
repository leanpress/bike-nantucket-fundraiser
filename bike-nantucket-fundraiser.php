<?php
/*
Plugin Name: Bike Nantucket Fundraiser
Plugin URI: http://bikenantucket.org
Description: Expand your fundraising base by getting your event participants involved in the fundraising process.
Version: 1.0
Author: Au Coeur Design
Author URI: http://aucoeurdesign.com
License: GPLv2
*/

/*  Copyright 2013 CURE International  (email : info@cure.org)

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

define( 'bnfund_VERSION', '0.8.3' );
define( 'bnfund_FOLDER', str_replace( basename( __FILE__), '', plugin_basename( __FILE__ ) ) );
// Define the URL to the plugin folder

define( 'bnfund_URL', plugin_dir_url(__FILE__ ) );

// Define the basename
define( 'bnfund_BASENAME', plugin_basename( __FILE__ ) );

// Define the complete directory path
define( 'bnfund_DIR', dirname( __FILE__ ) );


//
// Load plugin
//

// Personal fundraiser global functions
require_once( bnfund_DIR . '/includes/functions.php' );

// Personal fundraiser setup (for installation/upgrades)
require_once( bnfund_DIR . '/includes/setup.php' );

// Personal fundraiser admin
if (is_admin () ) {
    require_once( bnfund_DIR . '/includes/admin.php' );
}

// Personal fundraiser user
require_once( bnfund_DIR . '/includes/user.php' );

// Add hooks at the end
require_once( bnfund_DIR . '/includes/hooks.php' );

?>
