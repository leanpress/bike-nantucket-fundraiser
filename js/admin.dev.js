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
    if (typeof bnfund !== "undefined" && bnfund.validation_rules) {
        $.validationEngineLanguage = {
            allRules: bnfund.validation_rules
        };
    }
    /**
     * Add a personal fundraising field.
     */
	function addField(e) {
		if (e) {
			e.preventDefault();
		}
		var insertBefore = $(this).closest('tbody').find('tr.bnfund-add-row');
		var newRow = $('#_bnfund-template-row').clone(true);
		newRow.find('.bnfund-data-type-edit textarea').val('');
		updateId(newRow, "***", true);
		newRow.find('.bnfund-shortcode-field').html('');
		newRow.find('.bnfund-data-sample-view').html('');
		insertBefore.before(newRow);
		var typeField = newRow.find('.bnfund-type-field');
		typeField.val('text');
		$.proxy(typeFieldChanged, typeField)();
	}

    /**
     * Delete a personal fundraising field.
     */
	function deleteField(e) {
		e.preventDefault();
		var field = $(this);
		fieldRowCount = field.closest('tbody').children('tr.bnfund-field-row').length;
		if (fieldRowCount == 1) {
			$.proxy(addField, this)();
		}
		field.closest('tr.bnfund-field-row').remove();
	}

    /**
     * Display a sample rendering of the specified field.
     */
	function displayDataTypeSample(dataValues, fieldType, sampleField) {
		var values = dataValues.split("\n");
		var sample = "";
		var i=0;
		if (fieldType === 'select') {
			sample += '<select>';
			for (i=0; i< values.length; i++) {
				sample += '<option>' + values[i] +'</option>';
			}
			sample += '</select><br/>';
		}
		sampleField.html(sample);
	}

    /**
     * Edit the current field's data
     */
	function editDataField(e) {
		e.preventDefault();
		var sampleField = $(this).closest('.bnfund-data-type-sample');
		sampleField.hide();
		sampleField.next('.bnfund-data-type-edit').show();
	}

    /**
     * When a field's label changes, update the rest of the field data to
     * reflect that change.
     */
	function labelFieldChanged() {
		var label = $(this).val().toLowerCase();
		label = label.replace(/\s/g, '-');
		var currentRow = $(this).closest('tr');
		var fieldType = currentRow.find('select.bnfund-type-field');
		if (fieldType.length) {
			var shortCodeField = currentRow.find('.bnfund-shortcode-field');
			var shortCode = '';
			if (label.length > 0) {
				shortCode += '[bnfund-' + label;
				if (fieldType.val() === 'fixed') {
					shortCode += ' value="?"';
				}
				shortCode += ']';
			}
			shortCodeField.html(shortCode);
			updateId(currentRow, label);
		}
	}

    /**
     * Handler to move field down in display order.
     */
	function moveFieldDown(e) {
		e.preventDefault();
		var currentRow = $(this).closest('tr');
		currentRow.insertAfter(currentRow.next());
	}

    /**
     * Handler to move field up in display order.
     */
	function moveFieldUp(e) {
		e.preventDefault();
		var currentRow = $(this).closest('tr');
		currentRow.insertBefore(currentRow.prev());
	}

    /**
     * Use ajax call to display donations in campaign edit screen.
     */
	function showDonations(e) {
        var showLink = $(this);
		var st = showLink.data('bnfund-donation-start');
        var num = 20;

		showLink.data('bnfund-donation-start', st+num);
		
		$('#commentsdiv img.waiting').show();

		var data = {
			'action' : 'bnfund_get_donations_list',
            'mode' : 'single',
			'_ajax_nonce' : $('#bnfund_get_donations_nonce').val(),
			'p' : $('#post_ID').val(),
			'start' : st,
			'number' : num
		};

		$.post(ajaxurl, data,
			function(r) {
				r = wpAjax.parseAjaxResponse(r);
				$('#commentsdiv .widefat').show();
				$('#commentsdiv img.waiting').hide();

				if ( 'object' == typeof r && r.responses[0] ) {
					$('#the-comment-list').append( r.responses[0].data );

					theList = theExtraList = null;
					$("a[className*=':']").unbind();

					if ( showLink.data('bnfund-donation-start') > showLink.data('bnfund-donation-total') )
						$('#bnfund-show-donations').hide();
					else
						$('#bnfund-show-donations').html(bnfund.show_more_donations);
					return;
				} else if ( 1 == r ) {
					$('#show-donations').parent().html(bnfund.no_more_donations);
					return;
				}

				$('#the-comment-list').append('<tr><td colspan="2">'+wpAjax.broken+'</td></tr>');
			}
		);
		e.preventDefault();
		return false;
	}


    /**
     * Handler when a personal fundraiser field's type is changed.
     */
	function typeFieldChanged() {
		var typeField = $(this);
		var fieldType = typeField.val();
		var currentRow = typeField.closest('tr');
		var dataField = currentRow.find('.bnfund-data-type-edit');
		var dataVal = dataField.find('textarea').first().val();
		var sampleField = currentRow.find('.bnfund-data-type-sample');
		switch (fieldType) {
			case 'select':
				if (dataVal === '') {
					dataField.show();
					sampleField.hide();
				} else {
					dataField.hide();
					displayDataTypeSample(dataVal, fieldType, sampleField.find('.bnfund-data-sample-view'));
					sampleField.show();
				}
				break;
			default:
				dataField.hide();
				sampleField.hide();
		}
		var labelField = currentRow.find('.bnfund-label-field');
		$.proxy(labelFieldChanged, labelField)();
	}

    /**
     * Apply the changes from the data textarea to the current field.
     */
	function updateDataField(e) {
		e.preventDefault();
		var dataField = $(this).closest('.bnfund-data-type-edit');
		var dataVal = dataField.find('textarea').first().val();
		var sampleField = dataField.prev('.bnfund-data-type-sample');
		var fieldType = $(this).closest('tr').find('select.bnfund-type-field').val();
		dataField.hide();
		displayDataTypeSample(dataVal, fieldType, sampleField.find('.bnfund-data-sample-view'));
		sampleField.show();
	}

    /**
     * Update the field id for the specified field.
     */
	function updateId(aRow, newId, clearValues) {		
		var oldId = aRow[0].id;
		aRow.find('[name^="bnfund_options"]').each(function() {
			var name = $(this).attr('name');
			name = name.replace('['+oldId+']','['+newId+']');
			$(this).attr('name',name);
			if (clearValues) {
				$(this).val('');
			}
		});
		aRow.attr('id',newId);
	}

    /**
     * Validate the add donation form to ensure that the required fields are
     * filled in.
     */
    function validateDonationAdd(e) {        
        if (!$('#bnfund-add-donation-fields').validationEngine({
                returnIsValid:true,
                promptPosition: 'topLeft'
            })) {
            e.preventDefault();
        }        
    }

	$('.bnfund-add-field').click(addField);
	$('.bnfund-delete-field').click(deleteField);
	$('.bnfund-data-field-edit').click(editDataField);
	$('.bnfund-data-field-update').click(updateDataField);
	$('.bnfund-move-dn-field').click(moveFieldDown);
	$('.bnfund-move-up-field').click(moveFieldUp);	
	$('.bnfund-type-field').change(typeFieldChanged);
	$('.bnfund-label-field').change(labelFieldChanged);

	$('#bnfund-show-donations').click(showDonations);

    $('#bnfund-add-donation').click(validateDonationAdd);

});