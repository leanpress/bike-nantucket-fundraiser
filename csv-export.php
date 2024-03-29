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

	if ( !defined( 'ABSPATH' ) ) {
		require_once dirname( __FILE__ ) . '/../../../wp-load.php';
	}
    $post_id = $_REQUEST['p'];
    check_admin_referer( 'bnfund-campaign-csv'.$post_id,'n' );

    header( 'Cache-Control: no-cache' );
	header( 'Expires: -1' );

	if( ! current_user_can( 'edit_post', $post_id ) ) {
		header( $_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden' );
		header( 'Refresh: 0; url=' . admin_url() );
		echo '<html><head><title>403 Forbidden</title></head><body><p>Access is forbidden.</p></body></html>';
		exit;
	}

	header( 'Content-Type: application/force-download' );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Type: application/download' );
	header( 'Content-Disposition: attachment; filename=donations.csv' );

    $out = fopen( 'php://output', 'w' );
    //Allow additional information to be exported to CSV file.
    do_action('bnfund_csv_export', $out);
    $transactions = get_post_meta( $post_id, '_bnfund_transactions' );
    if (! empty( $transactions ) ) {
        fputcsv( $out, array(
            'Date', 'First Name', 'Last Name', 'Email', 'Anonymous', 'Amount', 'Comment'
        ) );
        foreach ( $transactions as $transaction ) {
            $data = array();
            if ( isset( $transaction['date'] ) ) {
                $data[] = $transaction['date']->format('m/d/Y');
            } else {
                $data[] = '';
            }
            $data[] = $transaction['donor_first_name'];
            $data[] = $transaction['donor_last_name'];
            $data[] = $transaction['donor_email'];
            if (isset( $transaction['anonymous'] ) &&
				$transaction['anonymous'] == true ) {
                $data[] = "Y";
            } else {
                $data[] = "N";
            }
            $data[] = $transaction['amount'];
            $data[] = $transaction['comment'];
            fputcsv( $out, $data );
        }
    }
	fclose( $out );
	exit;
?>
