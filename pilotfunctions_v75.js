// ==========================================================
// === UNIFIED SCRIPT FOR PILOTS PAGE (pilots.php)        ===
// ==========================================================

$(document).ready(function () {
	// This is the main entry point.
	// 1. Load data for the filter dropdowns first.
	loadAllCraftTypesForFilter();

	// 2. Then, load the initial list of all pilots.
	updatePilotList();

	// 3. Attach a single event handler to all filter/sort controls.
	$('#search_pilot, #sortBy, #craftTypeFilter').on('keyup change', applyFiltersAndSort);

	// 4. Attach event handler for clicking on a pilot in the list.
	// Use event delegation for dynamically loaded items.
	$('#pilots_list').on('click', '.pilot-item', function (event) { // <-- Add 'event' here
		// === THE FIX IS HERE ===
		// This line stops the browser from adding '#' to the URL.
		event.preventDefault();
		$('#pilots_list .pilot-item').removeClass('active');
		$(this).addClass('active');
		getPilotDetails($(this).data('id'));
	});
});

/**
 * Fetches all pilots for the company and triggers the rendering of the sidebar list.
 */
function updatePilotList() {
	$.ajax({
		url: 'pilots_get_all_pilots.php', // This API must return hire_date and qualifications
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

/**
 * Fetches unique craft types to populate the "Filter by Craft" dropdown.
 */
function loadAllCraftTypesForFilter() {
	$.ajax({
		url: 'pilots_get_all_crafts.php', // Corrected URL
		dataType: 'json',
		success: function (response) {
			if (response.success && Array.isArray(response.crafts)) {
				const $select = $('#craftTypeFilter');
				response.crafts.forEach(craft => {
					if (craft.craft_type) { // Ensure craft_type is not null/empty
						$select.append(`<option value="${escapeHtml(craft.craft_type)}">${escapeHtml(craft.craft_type)}</option>`);
					}
				});
			}
		}
	});
}

/**
 * Fetches all details for a single pilot when their name is clicked.
 */
function getPilotDetails(pilotId) {
	$('#pilot_info').html('<div class="text-center p-5"><i class="fa fa-spinner fa-spin fa-3x"></i></div>');
	$('#default_pilot_message').hide();

	$.ajax({
		url: 'pilots_get_pilot_details.php',
		data: { pilot_id: pilotId }, // The PHP script expects 'pilot_id' in the GET request
		dataType: 'json',
		success: function (response) {
			if (response.success) {
				renderPilotDetails(response.data); // Pass only the 'data' object
			} else {
				$('#pilot_info').html(`<div class="alert alert-danger">${escapeHtml(response.error) || 'Error loading details.'}</div>`);
			}
		},
		error: function () {
			$('#pilot_info').html('<div class="alert alert-danger">A server error occurred while fetching pilot details.</div>');
		}
	});
}

/**
 * Renders the HTML for the sidebar list of pilots.
 */
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

/**
 * Applies all current filter and sort criteria to the sidebar pilot list.
 * THIS VERSION IS ADAPTED TO YOUR PREFERRED DROPDOWN LAYOUT.
 */
function applyFiltersAndSort() {
	const searchText = ($('#search_pilot').val() || '').toLowerCase();
	const craftFilter = $('#craftTypeFilter').val();
	const sortBy = $('#sortBy').val();

	// 1. FILTER: Show/hide pilots based on all criteria
	$('#pilots_list .pilot-item').each(function () {
		const $pilot = $(this);
		const name = $pilot.data('name').toLowerCase();
		const qualifications = $pilot.data('qualifications');

		const searchMatch = name.includes(searchText);
		const craftMatch = (craftFilter === 'all' || qualifications.includes(craftFilter.toLowerCase()));

		// === THE FIX IS HERE: Handle PIC/SIC filtering from the sortBy dropdown ===
		let positionMatch = true;
		if (sortBy === 'filter_pic') {
			positionMatch = qualifications.includes('-pic');
		} else if (sortBy === 'filter_sic') {
			positionMatch = qualifications.includes('-sic');
		}

		$pilot.toggle(searchMatch && craftMatch && positionMatch);
	});

	// 2. SORT: Reorder only the visible items
	const $visiblePilots = $('#pilots_list .pilot-item:visible').sort(function (a, b) {
		const nameA = $(a).data('name');
		const nameB = $(b).data('name');

		if (sortBy === 'seniority') {
			// === ROBUST DATE SORTING (Most senior = earliest date) ===
			const dateA = $(a).data('hire-date');
			const dateB = $(b).data('hire-date');

			const aHasDate = dateA && dateA !== '0000-00-00';
			const bHasDate = dateB && dateB !== '0000-00-00';

			if (aHasDate && !bHasDate) return -1; // Pilots with dates always come before those without.
			if (!aHasDate && bHasDate) return 1;  // Pilots without dates are pushed to the end.
			if (!aHasDate && !bHasDate) return 0;  // If neither has a date, their order doesn't matter relative to each other.

			// If both have valid dates, compare them. Earliest date comes first.
			return new Date(dateA) - new Date(dateB);
		}

		// Default sort for 'name', 'filter_pic', and 'filter_sic' is alphabetical
		return nameA.localeCompare(nameB);
	});

	$('#pilots_list').append($visiblePilots);
}

/**
 * Renders the detailed HTML view for a selected pilot.
 * THIS VERSION FORMATS ALL DATES CORRECTLY.
 */
function renderPilotDetails(data) {
	if (!data || !data.details) {
		$('#pilot_info').html('<div class="alert alert-danger">Error: Could not render pilot details.</div>');
		return;
	}

	const { details, standard_validity_fields, pilot_validity_data, assigned_crafts, assigned_contracts } = data;
	let html = '';

	// Format the Hire Date before using it
	const formattedHireDate = details.hire_date ? moment(details.hire_date).format("MMM DD, YYYY") : 'N/A';

	// --- THIS IS THE FIX ---
	// Create a variable for the secondary phone HTML, only if it exists.
	let secondaryPhoneHtml = '';
	if (details.phonetwo) {
		secondaryPhoneHtml = `<li><strong>Secondary Phone:</strong> ${escapeHtml(details.phonetwo)}</li>`;
	}
	// --- END OF FIX ---

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
                                <!-- Use the newly formatted date variable -->
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

	// --- DYNAMIC VALIDITY TABLE GENERATION ---
	html += `<div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header"><h5>My Certifications & Validities</h5></div>
                        <div class="card-body" style="padding: 0;">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Certification</th>
                                            <th>Expiry Date</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

	if (standard_validity_fields && standard_validity_fields.length > 0) {
		standard_validity_fields.forEach(field => {
			const expiryDate = pilot_validity_data[field.field_key] || '';
			let statusHtml = '<span class="text-muted">No Date</span>';

			// =========================================================================
			// === THE FIX IS HERE: Format the Expiry Dates for the table          ===
			// =========================================================================
			const formattedExpiryDate = expiryDate ? moment(expiryDate).format("MMM DD, YYYY") : 'N/A';
			// =========================================================================

			if (expiryDate) {
				const expiry = new Date(expiryDate);
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				if (isNaN(expiry.getTime())) {
					statusHtml = '<span class="text-danger">Invalid Date</span>';
				} else if (expiry >= today) {
					statusHtml = '<span class="text-success">Valid</span>';
				} else {
					statusHtml = '<span class="text-danger">Expired</span>';
				}
			}

			html += `<tr>
                        <td><strong>${escapeHtml(field.field_label)}</strong></td>
                        <!-- Use the newly formatted date variable -->
                        <td>${formattedExpiryDate}</td>
                        <td>${statusHtml}</td>
                     </tr>`;
		});
	} else {
		html += `<tr><td colspan="3" class="text-center text-muted">No standard validity fields are configured.</td></tr>`;
	}

	html += `</tbody></table></div></div></div></div></div>`;

	$('#pilot_info').html(html);
}

// Helper function to prevent XSS
function escapeHtml(text) {
	if (typeof text !== 'string') return text || '';
	const map = {
		'&': '&', '<': '<', '>': '>', '"': '"', "'": ''
	};
	return text.replace(/[&<>"']/g, function (m) { return map[m]; });
}