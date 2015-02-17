/*  Copyright 2015 Au Coeur Design (http://aucoeurdesign.com)

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
jQuery(function($) {

    function showAuthNetDonateForm(e) {
        e.preventDefault();
        $('.bnfund-auth-net-form').slideDown();
        $(this).hide();
    }
    
	function submitAuthNetDonation() {
        $form = $(this);
        if ($form.validationEngine({returnIsValid:true})) {
            $form.find('.error').remove();
            $('#bnfund_donate_button').attr("disabled", "disabled").html(bnfund.processing_msg);

            var data = $(this).serialize() + '&action=bnfund_auth_net_donation';
            var url = "/wp-admin/admin-ajax.php";

            $.post(url, data, function(json) {
                if(json.success) {
                    $form.find('#bnfund_donate_button').after('<div class="success">'+bnfund.thank_you_msg+'</div>');
                    setTimeout(function() {
                        window.location.href=window.location.href;
                    }, 2500);
                } else {
                    $form.find('#bnfund_donate_button').after('<div class="error">' + json.error + '</div>');
                    $('#bnfund_donate_button').removeAttr("disabled").html('Donate');
                }
            }, 'json');
        }

        return false;
    }
    
    function showAuthNetSecurityMessage(e) {
        e.preventDefault();
        $('.bnfund-auth-net-secure-donations-text').slideDown();
    }	   
    
    $('.bnfund-auth-net-donate a').click(showAuthNetDonateForm);
    $('form.bnfund-auth-net-form').submit(submitAuthNetDonation);
    $('a.bnfund-auth-net-secure-donations-link').click(showAuthNetSecurityMessage);
});