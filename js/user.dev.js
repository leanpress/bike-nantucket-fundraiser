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

//Based on http://www.unwrongest.com/projects/show-password/
(function($){
	$.fn.extend({
		maskPassword: function(maskAction) {
			return this.each(function() {
				var createMask = function(el){
					var el = $(el);
					var masked = $("<input type='password' />");
					masked.insertAfter(el).attr({
						'id':el.attr('id')+'_mask',
						'class':el.attr('class'),
						'style':el.attr('style')
					});
					masked.hide();
					return masked;
				};

				var update = function($this,$that){
					$that.val($this.val());
				};

				var maskPassword = function(anEvent) {
					if (!$maskAction.data('masked')) {
						$maskAction.html(bnfund.unmask_passwd);
						$unmasked.hide();
						$masked.show();
						update($masked,$unmasked);
						$maskAction.data('masked',true);
					} else {
						$maskAction.html(bnfund.mask_passwd);
						$masked.hide();
						$unmasked.show();
						update($unmasked,$masked);
						$maskAction.data('masked',false);
					}
					return false;
				};

				var $masked = createMask(this),
					$unmasked = $(this),
					$maskAction = $(maskAction);
				$maskAction.toggle(maskPassword,maskPassword);
				$unmasked.keyup(function(){update($unmasked,$masked);});
				$masked.keyup(function(){update($masked,$unmasked);});
			});
		}
	});
})(jQuery);

jQuery(function($) {
    $.validationEngineLanguage = {
        allRules: bnfund.validation_rules
    };
    
    var campaignId = $('#bnfund-campaign-id');
    if (campaignId.length > 0) {
        $.validationEngineLanguage.allRules.bnfundSlug.extraData = campaignId.val();
    }
	var campaignValid = false;
	var dialogSettings = {
		modal:true,
		resizable: false,
		close: closeDialog,
		width: 600,
		buttons: [
			{
				text: bnfund.ok_btn,
				click:  updateCampaign
			},
			{
				text: bnfund.cancel_btn,
				click:  closeDialog
			}
		],
		zIndex: 99999
	};

	/**
	 * Before the window is unloaded , check if any of the form elements
	 * have been modified and if so warn the user.
	 * @param e window.onbeforeunload event
	 */
	function checkForDirtyForm(e) {
		if (!$('#bnfund-form').data('dirty')) {
			return;
		}
		var e = e || window.event;
		var warningMsg = bnfund.save_warning;			
		// For IE and Firefox prior to version 4
		if (e) {
			e.returnValue = warningMsg;
		}
		// For Safari
		return warningMsg;
	};

	/**
	 * Close the currently open dialog.
	 */
	function closeDialog(e) {
		if (e.type != 'dialogclose') {
			$(this).dialog('close');
		}
		$.validationEngine.closePrompt('.formError',true);
	}

	/**
	 * Handler to mark the form as dirty when a user modifies a field.
	 */
	function inputChanged() {
		campaignValid = false;
		$('#bnfund-form').data('dirty',true);
	}

    /**
     * Perform the actual login for the user.
     */
	function loginUser() {
		document.location.href = $('#bnfund-login-link').attr('href');
	}

	/**
	 * Handler when XHR fails to register user.
	 */
	function registerFail() {
		$('#bnfund-wait-dialog').dialog('close');
		$.validationEngine.buildPrompt('#bnfund-register-email',bnfund.register_fail,'error');
	}

	/**
	 * Handler for XHR register user.  If there are errors registering the user, the
	 * error will be displayed;otherwise the register form will close and login the
	 * user.
	 */
	function registerResult(data) {
		if (data.success) {
			$('#bnfund-user-login').val($('#bnfund-register-username').val());
			$('#bnfund-user-pass').val($('#bnfund-register-pass').val());
			$('#bnfund-login-form').submit();			
		} else {
			$('#bnfund-wait-dialog').dialog('close');
			if (data.errors) {
				if (data.errors['invalid_email']) {
					$.validationEngine.buildPrompt('#bnfund-register-email',bnfund.invalid_email,'error');
				}
				if (data.errors['email_exists']) {
					$.validationEngine.buildPrompt('#bnfund-register-email',bnfund.email_exists,'error');
				}
				if (data.errors['username_exists']) {
					$.validationEngine.buildPrompt('#bnfund-register-username',bnfund.username_exists,'error');
				}
				if (data.errors['registerfail']) {
					registerFail();
				}
			} else {
				registerFail();
			}
		}
	}

    /**
     * Register a new user.
     */
	function registerUser() {
		var registerForm = $('#bnfund-create-account-form');
        if (registerForm.validationEngine({returnIsValid:true,scroll: false})) {
			$('#bnfund-wait-dialog').html(bnfund.reg_wait_msg);
			showWaitDialog();
	        registerForm.ajaxSubmit({
	            dataType: 'json',
	            success:  registerResult,
	            error: registerFail
	        });
        }
		
	}

	/**
	 * Display the proper edit dialog in a modal window.
	 */
	function showEditDialog() {
		var editDialog = $('#bnfund-edit-dialog');
		if (editDialog.length > 0) {
			editDialog.dialog(dialogSettings);
		} else {
			$('#bnfund-add-dialog').dialog(dialogSettings);
		}
	}

    /**
     * Display the popup to allow a user to register.
     */
	function showRegister() {
		$('#bnfund-update-dialog').dialog('close');
		$('#bnfund-register-dialog').dialog({		
			modal:true,
			width: 300,
			resizable: false,
			buttons: [{
				text: bnfund.register_btn,
				click:  registerUser
			},
			{
				text: bnfund.cancel_btn,
				click:  closeDialog
			}],
			zIndex: 99999
		});
		$("#bnfund-register-pass").maskPassword("#bnfund-mask-pass");
	}

	/**
	 * Display the please wait dialog
	 */
	function showWaitDialog() {
		$('#bnfund-wait-dialog').dialog({
			closeOnEscape: false,
            draggable: false,
			modal:true,
			resizable: false,
			zIndex: 99999
		});
	}

	/**
	 * Perform the actual submit to persist the campaign.
	 */
	function submitForm() {
		$('.ui-dialog').dialog().dialog('close');
		$('#bnfund-form').data('dirty',false);
		showWaitDialog();
		document.bnfund_form.submit();
	}

	/**
	 * Handler for Ok button on dialog.  This function kicks off the validation
	 * process of the form and if that is successful, the form will be submitted.
	 */
	function updateCampaign() {
		if ($('.bnfund-camp-locationformError').hasClass('ajaxed')) {
			$('.bnfund-camp-locationformError').removeClass('ajaxed');
		}
		var campaignForm = $('#bnfund-form');
			$(document).unbind('ajaxSuccess');
			$(document).bind('ajaxSuccess',{
				errId: 'bnfund-camp-locationformError'
			},validateAjax);

		campaignValid = campaignForm.validationEngine({
			scroll: false,
			returnIsValid: true
		});
	}

	/**
	 * Handler for ajax validation.  If the ajax validation is successful,
	 * submit the form.
	 * @param event the ajaxSuccess event.
	 * @param xhr the XHR object used for the ajax validation.
	 * @param settings object used to configure ajax validation.
	 */
	function validateAjax(event, xhr, settings) {
		if (settings.url.indexOf('validate-slug') > -1) {
			var errorMsg = $('.'+event.data.errId);
			if ((errorMsg.length == 0 || !errorMsg.hasClass('ajaxed')) && campaignValid) {
				submitForm();
			}
		}
	}

	$('#bnfund-form input').change(inputChanged);
	$('#bnfund-add-dialog').dialog(dialogSettings);
	$('.bnfund-edit-btn').click(showEditDialog);
	if (bnfund.date_format) {
		$('.bnfund-date').datepicker({
			dateFormat: bnfund.date_format
		});
	} else {
		$('.bnfund-date').datepicker();
	}

	var update_btns = [];
	var close_btn = '';
	if ($('#bnfund-login-link').length == 1) {
		var login_btn_fn = loginUser;
		if (bnfund.login_fn) {
			login_btn_fn  = window[bnfund.login_fn];
		}
		update_btns.push({
			text: bnfund.login_btn,
			id: 'bnfund-login-btn',
			click:  login_btn_fn
		});
		if ($('#bnfund-register-link').length == 1) {
			var register_btn_fn = showRegister;
			if (bnfund.register_fn) {
				register_btn_fn  = window[bnfund.register_fn];
			}
			update_btns.push({
				text: bnfund.register_btn,
				id: 'bnfund-register-btn',
				click:  register_btn_fn
			});
		}
		close_btn = bnfund.continue_editing_btn;
	} else {
		close_btn = bnfund.ok_btn;
	}
	update_btns.push({
		text: close_btn,
		click:  closeDialog
	});


	$('#bnfund-update-dialog').dialog({
		modal: true,
		resizable: false,
		buttons: update_btns,
		zIndex: 99999
	});
	$('#bnfund-register-link').click(showRegister);
	
	window.onbeforeunload = checkForDirtyForm;
});

function bnfund_validate_required_file() {    
    var currentItem,
        i,
        itemsToCheck = jQuery("input[class*='requiredFile']");
    for (i=0;i< itemsToCheck.length;i++) {
        currentItem = jQuery(itemsToCheck[i]);
        if (itemsToCheck[i].value === '' && !currentItem.data('bnfundFileSet') ) {
            return false;
        }        
    }
    return true;

}


