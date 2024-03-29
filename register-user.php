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
if (!defined('ABSPATH')) {
	require_once dirname(__FILE__) . '/../../../wp-load.php';
}
require_once( ABSPATH . 'wp-admin/includes/post.php' );

$registerResult = bnfund_register_user();
if (is_wp_error($registerResult) ) {
    echo json_encode($registerResult); 
} else {
    echo '{"success":true}';
}

?>