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
 * Ajax call to process donation using Authorize.Net 
 */
function bnfund_auth_net_donation() {	
    $campaign_id = $_POST['post_id'];
    $gentime = $_POST['g'];    
    $msg = array();
    if ( wp_verify_nonce( $_POST['n'],  'bnfund-donate-campaign'.$campaign_id.$gentime ) ) {
        $post = get_post( $campaign_id );
        $transaction_array = bnfund_process_authorize_net();            
        if ($transaction_array['success']) {
            bnfund_add_gift( $transaction_array, $post ); 
            $msg['success'] = true;
        } else {
            $msg['success'] = false;
            $msg['error'] = $transaction_array['error_msg'];
        }	
    } else {
        $msg['success'] = false;
        $msg['error'] =  __( 'You are not permitted to perform this action.', 'bnfund' );        
    }
	echo json_encode($msg);
	die();
}

/**
 * Convert the passed in date to iso8601 (YYYY-MM-DD) format.
 * @param string $date date to convert.
 * @param string $format current format of date.
 * @return string date in iso8601 format.
 */
function bnfund_date_to_iso8601( $date, $format ) {
	if( class_exists( 'DateTime' ) && method_exists( 'DateTime', 'createFromFormat' ) ) {
		$date = DateTime::createFromFormat( $format, $date );
		if ( $date ) {
			return $date->format( 'Y-m-d' );
		} else {
			return "";
		}
	} else {
		$date_map = array(
			'y'=>'year',
			'Y'=>'year',
			'm'=>'month',
			'n'=>'month',
			'd'=>'day',
			'j'=>'day'
		);
		$date_array = array(
			'error_count' => 0,
			'errors' => array()
		);

		$format = preg_split( '//', $format, -1, PREG_SPLIT_NO_EMPTY );
		$date = preg_split( '//', $date, -1, PREG_SPLIT_NO_EMPTY );
		$format_frag = $format[0];
		$format_idx = 0;
		$error_msg = null;

		foreach ( $date as $idx => $date_frag ) {
			if ( ! ctype_digit( $date_frag ) ) {
				$format_idx++;
				if ( !isset( $format[$format_idx] ) ) {
					$error_msg = 'An unexpected separator was encountered';
				} else {
					$format_frag = $format[$format_idx];
					if ( $date_frag != $format_frag ) {
						$error_msg = 'An unexpected separator was encountered';
					} else {
						$format_idx++;
						if ( ! isset( $format[$format_idx] ) ) {
							$error_msg = 'An unexpected character was encountered';
						} else {
							$format_frag = $format[$format_idx];
						}
					}
				}
				if ( isset( $error_msg ) ) {
					$date_array['error_count']++;
					$date_array['errors'][$idx] = $error_msg;
					break;
				}
			} else {
				$date_key = $date_map[$format_frag];
				if ( !isset( $date_array[$date_key] ) ) {
					$date_array[$date_key] = $date_frag;
				} else {
					$date_array[$date_key] .= $date_frag;
				}
			}

		}
		if ( isset( $date_array['month'] ) && isset( $date_array['day'] )
				&&  isset( $date_array['year'] ) )  {
			$gmttime = gmmktime( 0, 0, 0, $date_array['month'], $date_array['day'], $date_array['year'] );
			return gmdate( 'Y-m-d', $gmttime );
		} else {
			return '';
		}
	}
}

/**
 * Determine the short code for a specific personal fundraiser field.
 * @param string $id The id of the field.
 * @param string $type The type of field.
 * @return string the corresponding shortcode.
 */
function bnfund_determine_shortcode( $id, $type = '' ) {
	$scode = '[bnfund-'.$id;
	if ( $type == 'fixed' ) {
		$scode .= ' value="?"';
	}
	$scode .= ']';
	return $scode;
}

/**
 * Determine and return the proper location of the specified file.  This
 * function allows the use of .dev files when debugging.
 * @param string $name The name of the file, not including directory and
 * extension.
 * @param string $type The type of file.  Valid values are js or css.
 * @return string the file location to use.
 */
function bnfund_determine_file_location( $name, $type ) {
	$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';
	return bnfund_URL."$type/$name$suffix.$type";
}

/**
 * If the option is set to use ssl for campaigns, redirect campaign pages to 
 * secure.
 */
function bnfund_force_ssl_for_campaign_pages() {
	global $post;     
    if ( ! is_admin() && $post && $post->post_type == 'bnfund_campaign' ) {
        $options = get_option( 'bnfund_options' ); 
        if ( ! empty ( $options['use_ssl'] ) && ! is_ssl() ) {
            $ssl_redirect = 'https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
            $ssl_redirect = apply_filters( 'bnfund_ssl_campaign_location', $ssl_redirect );
            wp_redirect( $ssl_redirect, 301 );
	   		exit();
	   	}
	}
}

/**
 * Filter to campaigns to use content from the event they where created from.
 * @param string $content The current post content
 * @return string The event content if the post is a personal fundraiser
 * campaign; otherwise return the content unmodified.
 */
function bnfund_handle_content( $content ) {
	global $post, $bnfund_update_message;
	if( $post->ID == null || ! bnfund_is_bnfund_post() ) {
		return $content;
	} else if ( $post->post_type == 'bnfund_campaign' ) {
		$eventid = get_post_meta( $post->ID, '_bnfund_event_id', true ) ;
		$event = get_post( $eventid );
		return $event->post_content.$bnfund_update_message;
	} else if ( $post->post_type == 'bnfund_event' ) {
		return $post->post_content.$bnfund_update_message;
	}
}

/**
 * Determine if current post is a personal fundraiser post type.
 * @param mixed $post_to_check the post to check.  If this is not passed, the
 * global $post object is used.
 * @param boolean $include_lists flag to indicate if the list post_types should
 * be checked as well.
 * @return boolean true if the current post is a personal fundraiser post type;
 * otherwise return false.
 */
function bnfund_is_bnfund_post( $post_to_check = false, $include_lists = false ) {
	if ( ! $post_to_check ) {
		global $post;
		$post_to_check = $post;
	}
	$bnfund_post_types = array( 'bnfund_event', 'bnfund_campaign' );
	if ( $include_lists ) {
		$bnfund_post_types[] = 'bnfund_event_list';
		$bnfund_post_types[] = 'bnfund_campaign_list';
	}
	if( $post_to_check && $post_to_check->ID != null && in_array( $post_to_check->post_type, $bnfund_post_types ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Process an Authorize.Net donation.
 * @return array with the following keys:
 *   success -- boolean indicating if transaction was successful.
 *   amount -- Transaction amount
 *   donor_first_name -- Donor first name
 *   donor_last_name -- Donor last name
 *   donor_email -- Donor email
 *   error_code -- When an error occurs, one of the following values is returned:
 *		no_response_returned -- A response was not received from PayPal.
 *		auth_net_failure -- PayPal returned a failure.
 *		wp_error -- A WP error was returned.
 *		exception_encountered -- An unexpected exception was encountered.
 *	 wp_error -- If the error_code is wp_error, the WP_Error object returned.
 *	 error_msg -- Text message describing error encountered.
 */
function bnfund_process_authorize_net() {
	$return_array = array( 'success' => false );
	if ( ! (int)$_POST['cc_num'] || ! (int)$_POST['cc_amount'] || ! $_POST['cc_email'] || ! $_POST['cc_first_name']
		 || ! $_POST['cc_last_name'] || ! $_POST['cc_address'] || ! $_POST['cc_city'] || ! $_POST['cc_zip']) {
		if ( ! (int)$_POST['cc_num']) {
			$return_array['error_msg'] = __( 'Error: Please enter a valid Credit Card number.', 'bnfund' );
		} elseif ( ! (int)$_POST['cc_amount']) {
			$return_array['error_msg'] = __( 'Error: Please enter a donation amount.', 'bnfund' );
		} elseif ( ! $_POST['cc_email']) {
			$return_array['error_msg'] = __( 'Error: Please enter a valid email address.', 'bnfund' );
		} elseif ( ! $_POST['cc_first_name']) {
			$return_array['error_msg'] = __( 'Error: Please enter your first name.', 'bnfund' );
		} elseif ( ! $_POST['cc_last_name']) {
			$return_array['error_msg'] = __( 'Error: Please enter your last name.', 'bnfund' );
		} elseif ( ! $_POST['cc_address']) {
			$return_array['error_msg'] = __( 'Error: Please enter your address.', 'bnfund' );
		} elseif ( ! $_POST['cc_city']) {
			$return_array['error_msg'] = __( 'Error: Please enter your city.', 'bnfund' );
		} elseif ( ! $_POST['cc_zip']) {
			$return_array['error_msg'] = __( 'Error: Please enter your zip code.', 'bnfund' );
		}
		return $return_array;
	}
	
	//process Authorize.Net donation
	require('AuthnetAIM.class.php');
	 
	try {
		$bnfund_options = get_option('bnfund_options');
	    $email   = $_POST['cc_email'];
	    $product = ($bnfund_options['authorize_net_product_name'] !='') ? $bnfund_options['authorize_net_product_name'] : 'Donation';
	    $firstname = $_POST['cc_first_name'];
	    $lastname  = $_POST['cc_last_name'];
	    $address   = $_POST['cc_address'];
	    $city      = $_POST['cc_city'];
	    $state     = $_POST['cc_state'];
	    $zipcode   = $_POST['cc_zip'];
	 	    
	    $creditcard = $_POST['cc_num'];
	    $expiration = $_POST['cc_exp_month'] . '-' . $_POST['cc_exp_year'];
	    $total      = $_POST['cc_amount'];
	    $cvv        = $_POST['cc_cvv2'];
	    $invoice    = substr(time(), 0, 6);	    
	    
	    
	    $api_login = $bnfund_options['authorize_net_api_login_id'];
	    $transaction_key = $bnfund_options['authorize_net_transaction_key']; 
	 
	    $payment = new AuthnetAIM( $api_login, $transaction_key, ( $bnfund_options['authorize_net_test_mode']==1 ) ? true : false );

	    $payment->setTransaction($creditcard, $expiration, $total, $cvv, $invoice);
	    $payment->setParameter("x_duplicate_window", 180);
	    $payment->setParameter("x_customer_ip", $_SERVER['REMOTE_ADDR']);
	    $payment->setParameter("x_email", $email);
	    $payment->setParameter("x_email_customer", FALSE);
	    $payment->setParameter("x_first_name", $firstname);
	    $payment->setParameter("x_last_name", $lastname);
	    $payment->setParameter("x_address", $address);
	    $payment->setParameter("x_city", $city);
	    $payment->setParameter("x_state", $state);
	    $payment->setParameter("x_zip", $zipcode);
	    $payment->setParameter("x_description", $product);

	    $payment->process();
	 
	    if ($payment->isApproved())  {
			// if success, return array
			$return_array['amount'] = $total;
			$return_array['donor_email'] = $email;

			if ( isset( $_POST['anonymous'] ) && $_POST['anonymous']==1) {
				$return_array['anonymous'] = true;
			} else {
				$return_array['donor_first_name'] = $firstname;
				$return_array['donor_last_name'] = $lastname;
			}
			$return_array['transaction_nonce'] = $_POST['n'];
			$return_array['success'] = true;

	    } else if ($payment->isDeclined()) {
	        // Get reason for the decline from the bank. This always says,
	        // "This credit card has been declined". Not very useful.
	        $reason = $payment->getResponseText();	 
	        $return_array['error_msg'] = __( 'This credit card has been declined.  Please use another form of payment.', 'bnfund' );
	    } else if ($payment->isError()) {	 
	        // Capture a detailed error message. No need to refer to the manual
	        // with this one as it tells you everything the manual does.
	        $return_array['error_msg'] =  $payment->getResponseMessage();
	 
	        // We can tell what kind of error it is and handle it appropriately.
	        if ($payment->isConfigError()) {
	            // We misconfigured something on our end.
	            //$return_array['error_msg'] .= " Please notify the webmaster of this error.";
	        } else if ($payment->isTempError()) {
	            // Some kind of temporary error on Authorize.Net's end. 
	            // It should work properly "soon".
	            $return_array['error_msg'] .= __( '  Please try your donation again.', 'bnfund' );
	        } else {
	            // All other errors.
	        }
	 
	    }
	} catch (AuthnetAIMException $e) {
	    $return_array['error_msg'] = sprintf( __( 'There was an error processing the transaction. Here is the error message: %s', 'bnfund' ),  $e->__toString() );
	}
	return $return_array;
}

/**
 * Render the input fields for the personal fundraising fields.
 * @param int $postid The id of the campaign that is being edited.
 * @param string $campaign_title The title of the campaign being edited.
 * @param boolean $editing_campaign true if campaign is being edited;false if new campaign.
 * Defaults to true.
 * @param string $default_goal default goal for campaign.  Defaults to empty.
 * @return string The HTML for the input fields.
 */
function bnfund_render_fields( $postid, $campaign_title, $editing_campaign = true, $default_goal = '' ) {
	global $current_user, $post;
	$options = get_option( 'bnfund_options' );
	$inputfields = array();
	$matches = array();
	$result = preg_match_all( '/'.get_shortcode_regex().'/s', bnfund_handle_content( $post->post_content ), $matches );
	$tags = $matches[2];
	$attrs = $matches[3];
	if ( is_admin() ) {
		$render_type = 'admin';
		if ( isset( $options['fields'] ) ) {
			foreach ( $options['fields'] as $field_id => $field ) {
				$field_value = get_post_meta( $postid, '_bnfund_'.$field_id, true );
				$inputfields['bnfund-'.$field_id] = array(
					'field' => $field,
					'value' => $field_value
				);
			}
			$content_idx = array_search('bnfund-'.$field_id, $tags);
			if ( $content_idx !== false ){
				$inputfields['bnfund-'.$field_id]['attrs'] = $attrs[$content_idx];
			}
		}
		$content = '';
	} else {
		$render_type = 'user';
		get_currentuserinfo();
		$inputfields = array();
		foreach( $tags as $idx => $tag ) {
			if ( $tag == 'bnfund-days-left' ) {
				$tag = 'bnfund-end-date';
			}
			$field_id = substr( $tag, 6 );			
			$field_value = get_post_meta( $postid, '_bnfund_'.$field_id, true );
			if ( isset( $options['fields'][$field_id] ) ) {
				$inputfields[$tag] = array(
					'field' => $options['fields'][$field_id],
					'attrs' => $attrs[$idx],
					'value' => $field_value
				);
			}
		}
		$content = '<ul class="bnfund-list">';
	}

	if ( ! isset( $inputfields['bnfund-camp-title'] ) ) {
		$inputfields['bnfund-camp-title'] = array(
			'field' => $options['fields']['camp-title'],
			'value' => $campaign_title
		);
	}

	if ( ! isset( $inputfields['bnfund-camp-location'] ) ) {
		$inputfields['bnfund-camp-location'] = array(
			'field' => $options['fields']['camp-location']
		);
	}

	if ( ! isset( $inputfields['bnfund-gift-goal'] ) ) {
		$current_goal = get_post_meta( $postid, '_bnfund_gift-goal', true );
		$inputfields['bnfund-gift-goal'] = array(
			'field' => $options['fields']['gift-goal'],
			'value' => $current_goal
		);
	}
	if ( empty( $inputfields['bnfund-gift-goal']['value'] ) ) {
		$inputfields['bnfund-gift-goal']['value'] = $default_goal;
	}

	if ( ! isset( $inputfields['bnfund-gift-tally'] ) ) {
		$current_tally = get_post_meta( $postid, '_bnfund_gift-tally', true );
		$inputfields['bnfund-gift-tally'] = array(
			'field' => $options['fields']['gift-tally'],
			'value' => $current_tally
		);
	}

	uasort( $inputfields, '_bnfund_sort_fields' );
	$hidden_inputs = '';
	$field_idx = 0;
	foreach( $inputfields as $tag => $field_data ) {
		$field = $field_data['field'];
		$value = bnfund_get_value( $field_data, 'value' );

		$field_options = array(			
			'name' => $tag,
			'desc' => bnfund_get_value( $field, 'desc' ),
			'label' => bnfund_get_value( $field, 'label' ),
			'value' => $value,
			'render_type' => $render_type,
			'field_count' => $field_idx,
			'required' => bnfund_get_value( $field, 'required', false )
		);
		if ( isset( $field_data['attrs'] ) ) {
			$field_options['attrs']= shortcode_parse_atts( $field_data['attrs'] );
		}
		switch ( $field['type'] ) {
			case 'camp_title':
				if ( ! is_admin() ) {
					$field_options['value'] = $campaign_title;
					$content .= _bnfund_render_text_field( $field_options );					
					$field_idx++;
				}
				break;
			case 'camp_location':
				if ( ! is_admin() ) {
					if ( $editing_campaign ) {
						require_once( ABSPATH . 'wp-admin/includes/post.php' );
						list( $permalink, $post_name ) = get_sample_permalink( $postid );
					} else {
						$post_name = '';
					}
					$field_options['custom_validation'] = 'ajax[bnfundSlug]';
					$field_options['value'] = $post_name;
					$field_options['pre_input'] = trailingslashit( get_option( 'siteurl' ) ).trailingslashit( $options['campaign_slug'] );					
					$content .= _bnfund_render_text_field( $field_options );
					$field_idx++;
				}
				break;
			case 'fixed':
			case 'gift_tally':
				if ( is_admin() ) {
					$content .= _bnfund_render_text_field( $field_options );
				} else if ( $field['type'] == 'fixed' ) {
					$attr = shortcode_parse_atts( $field_data['attrs'] );
					$hidden_inputs .= '	<input type="hidden" name="'.$tag.'" value="'.$attr["value"].'"/>';
				}
				break;
			case 'end_date':
			case 'date':
				$field_options['class'] = 'bnfund-date';
				$field_options['value'] = bnfund_format_date( 
						$field_options['value'],  
						$options['date_format']
				);
				$content .= _bnfund_render_text_field( $field_options );
				$field_idx++;
				break;
			case 'giver_tally':
				if ( is_admin() ) {
					$content .= _bnfund_render_text_field( $field_options );
					$field_idx++;					
				}
				break;
			case 'user_goal':
				$field_options['custom_validation'] = 'custom[onlyNumber]';
				$content .= _bnfund_render_text_field( $field_options );
				$field_idx++;
				break;
			case 'text':
				$content .= _bnfund_render_text_field( $field_options );
				$field_idx++;
				break;
			case 'textarea':
                $field_options['class'] = 'bnfund-textarea';
                $field_options = _bnfund_add_validation_class($field_options);
				$field_content = '<textarea class="'.$field_options['class'].'" id="'.$tag.'" name="'.$tag.'" rows="10" cols="50" type="textarea">'.$value.'</textarea>';
				$content .= bnfund_render_field_list_item( $field_content, $field_options);
				$field_idx++;
				break;
			case 'image':
                if ( ( isset( $field_options['required'] ) && $field_options['required'] ) ) {                
                    $field_options['custom_validation'] = 'funcCall[requiredFile]';
                }
				$content .= _bnfund_render_image_field( $field_options );
				$field_idx++;
				break;
			case 'select':
				$field_content = bnfund_render_select_field( $field['data'], $tag, $value );
				$content .= bnfund_render_field_list_item( $field_content, $field_options );
				$field_idx++;
				break;
			case 'user_email':
				if ( empty ( $value ) && !is_admin() ) {
					$value = $current_user->user_email;
					$field_options['value'] = $value;
				}
				$field_options['custom_validation'] = 'custom[email]';
				$content .= _bnfund_render_text_field( $field_options );
				$field_idx++;
				break;
			case 'user_displayname':
				if ( empty ($value) && !is_admin() ) {
					$value = $current_user->display_name;
					$field_options['value'] = $value;
				}				
				$content .= _bnfund_render_text_field( $field_options );
				$field_idx++;
				break;
			default:
				$content .= apply_filters( 'bnfund_'.$field['type'].'_input', $field_options );
				$field_idx++;
		}
	}
	if ( ! is_admin() ) {
		$content .= '</ul>';
	}
	$content .= $hidden_inputs;
	return $content;

}

/**
 * Render a drop down
 * @param string $values newline delimited values for the dropdown
 * @param string $name name of drop down
 * @param string $currentValue the value in the drop down that should be
 * selected.
 * @return string The HTML for the dropdown.
 */
function bnfund_render_select_field( $values, $name = '', $currentValue = '' ) {
	$values = preg_split( "/[\n]+/", $values );
	$content = '<select name="'.$name.'" value="'.$name.'>';
	foreach( $values as $value ) {
		$content .= '<option value="' . trim( $value ) . '"'.selected( $currentValue, $value, false ).'>'.$value.'</option>';
	}
	$content .= '</select>';
	return $content;
}

/**
 * Save the personal fundraising fields for the specified campaign.
 * @param string $campid The id of the campaign to save the personal fundraising
 * fields to.
 */
function bnfund_save_campaign_fields( $campid ) {
	$options = get_option( 'bnfund_options' );	
	if ( isset( $options['fields'] ) ) {
		$fieldname = '';
		foreach ( $options['fields'] as $field_id => $field ) {
			$fieldname = 'bnfund-'.$field_id;			
			switch( $field['type'] ) {
				case 'end_date':
				case 'date':
					if ( isset( $_REQUEST[$fieldname] ) ) {
						$date_format = bnfund_get_value( $options, 'date_format', 'm/d/y' );
						if ( isset( $_REQUEST[$fieldname] ) && empty( $_REQUEST[$fieldname] ) ) {
							$date_to_save = $_REQUEST[$fieldname];
						} else {
							$date_to_save = bnfund_date_to_iso8601( $_REQUEST[$fieldname] , $date_format );
						}
						update_post_meta( $campid, "_bnfund_".$field_id, $date_to_save );
					}
				case 'image':
					 _bnfund_attach_uploaded_image( $fieldname, $campid, "_bnfund_".$field_id );
					break;
				case 'user_goal':
				case 'gift_tally':
					if ( isset( $_REQUEST[$fieldname] ) ) {
						update_post_meta( $campid, "_bnfund_".$field_id, absint( $_REQUEST[$fieldname] ) );
					}
					break;
				default:
					if ( isset( $_REQUEST[$fieldname] ) ) {
						if ( is_array( $_REQUEST[$fieldname] ) ) {
							$value_to_save = $_REQUEST[$fieldname];
						} else {
							$value_to_save = strip_tags( $_REQUEST[$fieldname] );
						}
						update_post_meta( $campid, "_bnfund_".$field_id, $value_to_save );
					}
					break;
			}
		}
	}
}

/**
 * Send a mandrill transactional email
 * @param string $email the email address to send to.
 * @param array $merge_vars An array of the email merge variables.
 * @param string $subject the subject for the email.
 * @param string $config the prefix name of the bnfund options containing the mandrill
 * properties for the text version of the email, the html version and the optional template.
 * @param string $html the name of the bnfund option containing the html version 
 * of the email.  
 * @return boolean flag indicating if send was successful.
 */
function bnfund_send_mandrill_email($email, $merge_vars, $subject, $config) {
	$options = get_option( 'bnfund_options' );
    $api_key = $options['mandrill_api_key'];    
    $from_email = apply_filters( 'wp_mail_from', get_option( 'admin_email' ) );
	$from_name = apply_filters( 'wp_mail_from_name',  '');
    $text = $options[$config.'_text'];
    $html = $options[$config.'_html'];
    $template = '';
    if ( isset( $options[$config.'_template'] ) ) {
        $template = $options[$config.'_template'];
    }
    $global_merge_vars = array();
    foreach ($merge_vars as $key => $value) {
        $global_merge_vars[] = array(
            'name'=>$key,
            'content'=>$value
        );
    }       
    $message_array = array (
        'key' => $api_key,
        'message'=> array (
            'html' => $html,
            'text' => $text,
            'subject'=> $subject,
            'from_email' => $from_email,
            'to' => array(
                array(
                    'email' => $email
                )
            ),
            'global_merge_vars' => $global_merge_vars
        )
    );
    if ( ! empty( $from_name ) ) {
        $message_array['message']['from_name'] = $from_name;
    }
    
    $action = 'send.json';
    if ( ! empty( $template ) ) {
        $message_array['template_name'] = $template;
        $message_array['template_content'] = array(
            array(
                'name' => 'unused',
                'content' => ''
            )
        ); //Use an empty array for the template since we are not modifying parts of the template.
        $action = 'send-template.json';
    }
    $response_info = wp_remote_post("https://mandrillapp.com/api/1.0/messages/$action", 
        array(
            'body' => $message_array
        )
    );
    
    $response_body = null;
    if (is_array( $response_info ) ) {
        $response_body = $response_info['body'];
    } else {
        error_log( "Mandrill call to send email failed.  The response was: " . print_r( $response_info, true ) );
    }

    $obj = json_decode($response_body);        
    if ( is_array( $obj ) ) {
        $result = $obj[0];
    } else {
        $result = $obj;
    }
    if ( $result->status === 'sent' ) {
        return true;
    } else {
        if ( $result->status === 'error' ) {
            error_log( "Mandrill call returned an error.  The response was: " . print_r( $result, true ) );
        }
        return false;
    }
}

/**
 * Add the specified image file upload to the specified post
 * @param string $fieldname Name of the file in the request.
 * @param string $postid The id of the post to attach the file to.
 * @param string $metaname The name of the metadata field to store the
 * attachment in.
 */
function _bnfund_attach_uploaded_image( $fieldname, $postid, $metaname ) {
	if( isset( $_FILES[$fieldname] ) && is_uploaded_file( $_FILES[$fieldname]['tmp_name'] ) ) {
		$data = media_handle_upload( $fieldname, $postid, array( 'post_status' => 'private' ) );
		if( is_wp_error( $data ) ) {
			error_log("error adding image for personal fundraising:".print_r( $data, true ) );
		} else {
			update_post_meta( $postid, $metaname, $data );
		}
	}
}

/**
 * Format the specified date with the specified format.
 * @param string $date either an iso8601 (YYYY-MM-DD) formatted date or a
 * mm/dd/yy date.
 * @param string $format the format to return the date in.
 * @return string the formatted date.
 */
function bnfund_format_date( $date, $format ) {
	if ( empty($date) ) {
		return $date;
	}
	//Date is stored in old format of m/d/y
	if ( strlen( $date ) == 8 ) {
		$date = bnfund_date_to_iso8601( $date, 'm/d/y' );
	}
	return gmdate( $format, strtotime( $date ) );
}

/**
 * Determine the proper contact information for the specified campaign.  If the
 * campaign has a user display name and user email field, use those values instead
 * of the post author's contact information.  This function is necessary for use
 * cases where the campaign is created by an administrator, but the notifications
 * should be sent to another contact.
 * @param <mixed> $post The post representing the campaign to get the contact
 * information for
 * @param <mixed> $options The current personal fundraiser options.
 * @return <mixed> a WP_User object containing the contact information for
 * the specified campaign.
 */
function bnfund_get_contact_info( $post, $options = array() ) {
	$metavalues = get_post_custom( $post->ID );
	$contact_email = '';
	$contact_name = '';
	foreach( $metavalues as $metakey => $metavalue ) {
		if ( strpos( $metakey, "_bnfund_" ) === 0 ) {
			$field_id = substr( $metakey , 7);
			if ( isset($options['fields'][$field_id]) ) {
				$field_info = $options['fields'][$field_id];
				if ( ! empty( $field_info )  && ! empty( $metavalue[0] ) ) {
					switch( $field_info['type'] ) {
						case 'user_email':
							$contact_email = $metavalue[0];
							break;
						case 'user_displayname':
							$contact_name = $metavalue[0];
							break;
					}
					if ( ! empty( $contact_email ) && ! empty( $contact_name ) ) {
						break;
					}
				}
			}
		}
	}
	$contact_data = clone get_userdata($post->post_author);
	if ( $contact_data->user_email != $contact_email ) {
		$contact_data->user_email = $contact_email;
		$contact_data->display_name = $contact_name;
		$contact_data->ID = -1;
	}
	return $contact_data;
}

/**
 * Convenience function to count number of published fundraisers
 * @return int number of published fundraising campaigns.
 */
function bnfund_get_total_published_campaigns() {
    $count_posts = wp_count_posts( 'bnfund_campaign' );
    $published_posts = $count_posts->publish;
    return number_format( $published_posts, 0, ".", "," );
}

function bnfund_get_validation_js() {
	$validateSlug = array(
		'file' => bnfund_URL.'validate-slug.php',
		'alertTextLoad' => __( 'Please wait while we validate this location', 'bnfund' ),
		'alertText' => __( '* This location is already taken', 'bnfund' )
	);
    $required_validation = array(
        'regex' => 'none',
        'alertText' =>  __( '* This field is required', 'bnfund' ),
        'alertTextCheckboxMultiple' =>  __( '* Please select an option', 'bnfund' ),
        'alertTextCheckboxe' =>  __( '* This checkbox is required', 'bnfund' )
    );
    $required_file_validation = array(
        'nname' =>  'bnfund_validate_required_file',
        'alertText' =>  __( '* This field is required', 'bnfund' )
    );    
    $length_validation = array(
        'regex' => 'none',
        'alertText' =>  __( '*Between ', 'bnfund' ),
        'alertText2' => __( ' and ', 'bnfund' ),
        'alertText3' => __( ' characters allowed', 'bnfund' )
    );
    $email_validation = array(
        'regex' => '/^[a-zA-Z0-9_\.\-]+\@([a-zA-Z0-9\-]+\.)+[a-zA-Z0-9]{2,4}$/',
        'alertText' =>  __( '* Invalid email address', 'bnfund' )
    );
    $number_validation = array(
        'regex' => '/^[0-9\ ]+$/',
        'alertText' =>  __( '* Numbers only', 'bnfund' )
    );
    return array(
        'bnfundSlug' => $validateSlug,
        'required' => $required_validation,
        'length' => $length_validation,
        'email' => $email_validation,
        'onlyNumber' => $number_validation,
        'requiredFile' => $required_file_validation
    );
}

/**
 * Utility function to get value from array.  If the value doesn't exist,
 * return the specified default value.
 * @param array $array The array to pull the value from.
 * @param string $key The array key to use to get the value.
 * @param mixed $default The optional default to use if the key doesn't exist.
 * This value defaults to an empty string.
 * @return mixed The specified value from the array or the default if it doesn't
 * exist
 */
function bnfund_get_value( $array, $key, $default = '' ) {
	if ( isset( $array[$key] ) ) {
		return $array[$key];
	} else {
		return $default;
	}
}

/**
 * Handles registering a new user.
 *
 * @param array $value_array Array of fields to create user.
 * @return int|WP_Error Either user's ID or error on failure.
 */
function bnfund_register_user($value_array=array()) {
    if (empty($value_array)) {
        $value_array = $_POST;
    }
    $user_login = $value_array['bnfund_user_login'];
    $user_pass = $value_array['bnfund_user_pass'];
    if (empty($user_pass)) {
        $user_pass = wp_generate_password();        
    }    
    $user_email = $value_array['bnfund_user_email'];
    $first_name = $value_array['bnfund_user_first_name'];
    $last_name = $value_array['bnfund_user_last_name'];

    $errors = new WP_Error();

    $sanitized_user_login = sanitize_user( $user_login );
    $user_email = apply_filters( 'user_registration_email', $user_email );

    // Check the username
    if ( $sanitized_user_login == '' ) {
        $errors->add( 'empty_username', 'true');
    } elseif ( ! validate_username( $user_login ) ) {
        $errors->add( 'invalid_username', 'true');
        $sanitized_user_login = '';
    } elseif ( username_exists( $sanitized_user_login ) ) {
        $errors->add( 'username_exists', 'true');
    }

    // Check the e-mail address
    if ( $user_email == '' ) {
        $errors->add( 'empty_email', 'true');
    } elseif ( ! is_email( $user_email ) ) {
        $errors->add( 'invalid_email', 'true');
        $user_email = '';
    } elseif ( email_exists( $user_email ) ) {
        $errors->add( 'email_exists', 'true');
    }

    do_action( 'register_post', $sanitized_user_login, $user_email, $errors );

    $errors = apply_filters( 'registration_errors', $errors, $sanitized_user_login, $user_email );

    if ( $errors->get_error_code() )
        return $errors;


    $user_login = $sanitized_user_login;
    $userdata = compact('user_login', 'user_email', 'user_pass','first_name','last_name');
    $user_id = wp_insert_user($userdata);
    if (! $user_id ) {
        $errors->add( 'registerfail', 'true' );
        return $errors;
    }


    wp_new_user_notification($user_id, $user_pass);

    return $user_id;
}


/**
 * Render the field using the specified render type.
 * @param string $field_contents actual input field to render.*
 * @param array $field_options named options for field.
 * @return string the rendered HTML.
 */
function bnfund_render_field_list_item( $field_contents, $field_options ) {
	$content = '<li>';
	$content .= '	<label for="'.$field_options['name'].'">'.$field_options['label'];
	if ( isset( $field_options['required'] ) && $field_options['required'] ) {
		$content .= '<abbr title="'.esc_attr__( 'required', 'bnfund' ).'">*</abbr>';
	}
	$content .= '</label>';
	$content .= $field_contents;
	if ( isset( $field_options['render_type'] ) &&  
			$field_options['render_type'] == 'user' &&
			! empty( $field_options['desc'] ) ) {
		$content .= '<div class="bnfund-field-desc"><em><small>'.$field_options['desc'].'</small></em></div>';
	}
	$content .= '</li>';
	return $content;
}

/**
 * Add validation class to the specified field options if field needs validation.
 * @param array $field_options named options for field.
 * @return array updated field options including validation class if needed.
 */
function _bnfund_add_validation_class($field_options) {
	if ( ( isset( $field_options['required'] ) && $field_options['required'] ) ||
			isset( $field_options['custom_validation'] ) ) {
		$field_options['class'] .= ' validate[';
		if ( $field_options['required'] ) {
			$field_options['class'] .= 'required';
			if ( isset( $field_options['custom_validation'] ) ) {
				$field_options['class'] .= ',';
			}
		}
		if ( isset( $field_options['custom_validation'] ) ) {
			$field_options['class'] .=  $field_options['custom_validation'];
		}
		$field_options['class'] .= ']';
	}
    return $field_options;
}

/**
 * Render an image input field, including a display of the current image.
 * @param array $field_options named options for field.  Keys are:
 *	--name name of the field
 *	--label label to display with field.
 *	--value link to current image.
 * @return string HTML markup for image file upload/display.
 */
function _bnfund_render_image_field( $field_options ) {
	if ( ! empty ( $field_options['value'] ) ) {
		$field_options['additional_content'] = '<img class="bnfund-image" width="184" src="'.wp_get_attachment_url( $field_options['value'] ).'">';
	}
	$field_options['class'] = 'bnfund-image';
	$field_options['type'] = 'file';
	return _bnfund_render_text_field( $field_options );
	
}

/**
 * Render the HTML for a text input field
 * @param array $field_options named options for field.  Keys are:
 *	--name the name/id of the text field
 *	--label The label to display next to the input field.
 *	--class  The class name for the input field.
 *	--value The value for the input field.
 *	--type The type of input field.  Defaults to text.
 *	--additional_content Additional HTML to display.
 * @return string The HTML of a text input field.
 */
function _bnfund_render_text_field( $field_options = '') {
	$defaults = array(
		'class' => 'bnfund-text',
		'type' => 'text',
		'value' => '',
	);
	$field_options = array_merge( $defaults, $field_options );
    $field_options = _bnfund_add_validation_class($field_options);
	$content = '';
	if ( isset( $field_options['pre_input'] ) ) {
		$content .= $field_options['pre_input'];
	}
	$content .= '	<input class="'.$field_options['class'].'" id="'.$field_options['name'].'"';
	$content .= '		type="'.$field_options['type'].'" name="'.$field_options['name'].'"';
	if ( $field_options['type'] == 'file' ) {
        if ( ! empty( $field_options['value'] ) ) {
            $content .= ' data-bnfund-file-set="true"';
        }
	} else {
        $content .= ' value="'.esc_attr( $field_options['value'] ).'"';
    }
	$content .= '/>';
	if ( isset( $field_options['additional_content'] ) ) {
		$content .= $field_options['additional_content'];
	}
	return bnfund_render_field_list_item( $content, $field_options );
}

/**
 * Sort the specified fields using the fields sortorder.
 * @param mixed $field the original field
 * @param mixed $compare_field the field to compare.
 * @return int indicating if fields are equal, greater than or less than one
 * another.
 */
function _bnfund_sort_fields( $field, $compare_field ) {
	$field_order = $field['field']['sortorder'];
	$compare_order = $compare_field['field']['sortorder'];

	if($field_order == $compare_order) {
		return 0;
	} else {
		return ( $field_order < $compare_order ) ? -1 : 1;
	}
	
}
?>
