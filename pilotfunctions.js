// ==========================================================
// === UNIFIED SCRIPT FOR PILOTS PAGE (pilots.php)        ===
// ==========================================================

$(document).ready(function () {
	loadAllCraftTypesForFilter();
	updatePilotList();
	$('#search_pilot, #sortBy, #craftTypeFilter').on('keyup change', applyFiltersAndSort);
	$('#pilots_list').on('click', '.pilot-item', function (event) {
		event.preventDefault();
		$('#pilots_list .pilot-item').removeClass('active');
		$(this).addClass('active');
		getPilotDetails($(this).data('id'));
	});
});

function updatePilotList() {
	$.ajax({
		url: 'pilots_get_all_pilots.php',
		dataType: 'json',
		success: function (response) {
			if (response.success && response.data) {
				renderPilotList(response.data);
			} else {
				$('#pilots_list').html('<p class="text-danger p-3">Error loading pilot list.</p>');
			}
		},
		error: function () {
			$('#pilots_list').html('<p class="text-danger p-3">A server error occurred while loading pilots.</p>');
		}
	});
}

function loadAllCraftTypesForFilter() {
	$.ajax({
		url: 'pilots_get_all_crafts.php',
		dataType: 'json',
		success: function (response) {
			if (response.success && Array.isArray(response.crafts)) {
				const $select = $('#craftTypeFilter');
				response.crafts.forEach(craft => {
					if (craft.craft_type) {
						$select.append(`<option value="${escapeHtml(craft.craft_type)}">${escapeHtml(craft.craft_type)}</option>`);
					}
				});
			}
		}
	});
}

function getPilotDetails(pilotId) {
	$('#pilot_info').html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
	$('#default_pilot_message').hide();
	$.ajax({
		url: 'pilots_get_pilot_details.php',
		data: { pilot_id: pilotId },
		dataType: 'json',
		success: function (response) {
			if (response.success) {
				renderPilotDetails(response.data);
			} else {
				$('#pilot_info').html(`<div class="alert alert-danger">${escapeHtml(response.error) || 'Error loading details.'}</div>`);
			}
		},
		error: function () {
			$('#pilot_info').html('<div class="alert alert-danger">A server error occurred while fetching pilot details.</div>');
		}
	});
}

function renderPilotList(pilots) {
	const $list = $('#pilots_list').empty();
	if (pilots && pilots.length > 0) {
		pilots.forEach(pilot => {
			const qualifications = pilot.qualifications ? pilot.qualifications.toLowerCase() : '';
			$list.append(`
                <a href="#" class="list-group-item list-group-item-action pilot-item" 
                   data-id="${pilot.id}"
                   data-name="${escapeHtml(pilot.name)}"
                   data-qualifications="${escapeHtml(qualifications)}"
                   data-hire-date="${pilot.hire_date || ''}">
                    ${escapeHtml(pilot.name)}
                </a>`);
		});
		applyFiltersAndSort();
	} else {
		$list.html('<p class="text-muted p-3">No pilots found.</p>');
	}
}

function applyFiltersAndSort() {
	const searchText = ($('#search_pilot').val() || '').toLowerCase();
	const craftFilter = $('#craftTypeFilter').val();
	const sortBy = $('#sortBy').val();
	$('#pilots_list .pilot-item').each(function () {
		const $pilot = $(this);
		const name = $pilot.data('name').toLowerCase();
		const qualifications = $pilot.data('qualifications');
		const searchMatch = name.includes(searchText);
		const craftMatch = (craftFilter === 'all' || qualifications.includes(craftFilter.toLowerCase()));
		let positionMatch = true;
		if (sortBy === 'filter_pic') {
			positionMatch = qualifications.includes('-pic');
		} else if (sortBy === 'filter_sic') {
			positionMatch = qualifications.includes('-sic');
		}
		$pilot.toggle(searchMatch && craftMatch && positionMatch);
	});
	const $visiblePilots = $('#pilots_list .pilot-item:visible').sort(function (a, b) {
		const nameA = $(a).data('name');
		const nameB = $(b).data('name');
		if (sortBy === 'seniority') {
			const dateA = $(a).data('hire-date'), dateB = $(b).data('hire-date');
			const aHasDate = dateA && dateA !== '0000-00-00', bHasDate = dateB && dateB !== '0000-00-00';
			if (aHasDate && !bHasDate) return -1;
			if (!aHasDate && bHasDate) return 1;
			if (!aHasDate && !bHasDate) return 0;
			return new Date(dateA) - new Date(dateB);
		}
		return nameA.localeCompare(nameB);
	});
	$('#pilots_list').append($visiblePilots);
}


/**
 * Renders the detailed HTML view for a selected pilot.
 * THIS IS THE REFACTORED VERSION
 */
function renderPilotDetails(data) {
	if (!data || !data.details) {
		$('#pilot_info').html('<div class="alert alert-danger">Error: Could not render pilot details.</div>');
		return;
	}

	const { details, standard_validity_fields, pilot_validity_data, assigned_crafts, assigned_contracts } = data;
	let html = '';

	const formattedHireDate = details.hire_date ? moment(details.hire_date).format("MMM DD, YYYY") : 'N/A';
	let secondaryPhoneHtml = details.phonetwo ? `<li><strong>Secondary Phone:</strong> ${escapeHtml(details.phonetwo)}</li>` : '';

	// --- TOP ROW: Personal Info, Crafts, Contracts ---
	html += `<div class="row">
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header"><h5>Personal Information</h5></div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><strong>Name:</strong> ${escapeHtml(details.firstname)} ${escapeHtml(details.lastname)}</li>
                                <li><strong>Username:</strong> ${escapeHtml(details.username)}</li>
                                <li><strong>Nationality:</strong> ${escapeHtml(details.user_nationality) || 'N/A'}</li>
                                <li><strong>Hire Date:</strong> ${formattedHireDate}</li>
                                <li><strong>License:</strong> ${escapeHtml(details.nal_license) || 'N/A'}</li>
                                <li><strong>Foreign License:</strong> ${escapeHtml(details.for_license) || 'N/A'}</li>
                                <li><strong>E-mail:</strong> ${escapeHtml(details.email)}</li>
                                <li><strong>Phone:</strong> ${escapeHtml(details.phone) || 'N/A'}</li>
                                ${secondaryPhoneHtml}
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                     <div class="card mb-3">
                        <div class="card-header"><h5>Craft Endorsements</h5></div>
                        <div class="card-body">
                           <ul class="list-unstyled" style="margin-bottom: 0;">${assigned_crafts.length > 0
			? assigned_crafts.map(c => `<li>${escapeHtml(c.craft_type)} (${escapeHtml(c.position)})</li>`).join('')
			: '<li class="text-muted">No endorsements.</li>'
		}</ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                     <div class="card mb-3">
                        <div class="card-header"><h5>Assigned Contracts</h5></div>
                        <div class="card-body">
                           <ul class="list-unstyled" style="margin-bottom: 0;">${assigned_contracts.length > 0
			? assigned_contracts.map(c => `<li>${escapeHtml(c.contract_name)}</li>`).join('')
			: '<li class="text-muted">No contracts.</li>'
		}</ul>
                        </div>
                    </div>
                </div>
            </div>`;

	// --- DYNAMIC VALIDITY TABLE GENERATION (REFACTORED) ---
	html += `<div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><h5>Pilot Certifications & Validities</h5></div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Certification</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                            <!-- FIX: Add the new 'Document' column -->
                                            <th width="20%">Document</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

	if (standard_validity_fields && standard_validity_fields.length > 0) {
		standard_validity_fields.forEach(field => {
			const expiryDate = pilot_validity_data[field.field_key] || '';
			// --- FIX: Get the document path. It will be undefined for non-managers. ---
			const documentPath = pilot_validity_data[field.field_key + '_doc'] || '';

			let statusHtml = '<span class="text-muted">No Date</span>';
			const formattedExpiryDate = expiryDate ? moment(expiryDate).format("MMM DD, YYYY") : 'N/A';

			if (expiryDate) {
				const expiry = new Date(expiryDate), today = new Date();
				today.setHours(0, 0, 0, 0);
				if (isNaN(expiry.getTime())) {
					statusHtml = '<span class="text-danger">Invalid Date</span>';
				} else {
					statusHtml = expiry >= today ? '<span class="text-success">Valid</span>' : '<span class="text-danger">Expired</span>';
				}
			}

			// --- FIX: Conditionally create the HTML for the document cell ---
			// EXPLANATION: This logic checks if a documentPath exists. If it does (because the user is a manager),
			// it creates the "View" button. If not, it displays placeholder text. This works for both
			// managers and regular pilots without needing to change the JavaScript for different roles.
			let documentHtml = '<span class="text-muted">No Document</span>';
			if (documentPath) {
				documentHtml = `<a href="${escapeHtml(documentPath)}" target="_blank" class="btn btn-xs btn-info">
                                    <i class="fa fa-eye"></i> View Document
                                </a>`;
			}
			// --- END OF CONDITIONAL FIX ---

			html += `<tr>
                        <td><strong>${escapeHtml(field.field_label)}</strong></td>
                        <td>${formattedExpiryDate}</td>
                        <td>${statusHtml}</td>
                        <!-- Add the new document cell to the table row -->
                        <td>${documentHtml}</td>
                     </tr>`;
		});
	} else {
		// FIX: Update colspan to 4 to account for the new column
		html += `<tr><td colspan="4" class="text-center text-muted">No standard validity fields are configured.</td></tr>`;
	}

	html += `</tbody></table></div></div></div></div></div>`;

	$('#pilot_info').html(html);
}

function escapeHtml(text) {
	if (typeof text !== 'string') return text || '';
	const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
	return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}