// =========================================================================
// === craftfunctions.js - READ-ONLY Version for crafts.php              ===
// =========================================================================

$(document).ready(function () {
	// As soon as the page is ready, load the fleet data.
	loadReadOnlyCrafts();
});

/**
 * A shared utility function to escape HTML special characters to prevent XSS.
 */
function escapeHtml(text) {
	if (text === null || typeof text === 'undefined') { return ""; }
	const map = {
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
	};
	return String(text).replace(/[&<>"']/g, function (m) { return map[m]; });
}

/**
 * Fetches fleet data from the server and builds a simple, read-only table.
 */
function loadReadOnlyCrafts() {
	const $container = $("#crafts-display-container");
	$container.html('<p class="text-center"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Loading fleet information...</p>');

	$.ajax({
		type: "GET",
		url: "crafts_get_fleet.php", // This PHP script is correct for this purpose
		dataType: "json",
		success: function (response) {
			if (response && response.success === true && Array.isArray(response.crafts)) {
				if (response.crafts.length === 0) {
					$container.html("<p class='text-center text-muted'>No aircraft have been registered in the fleet.</p>");
					return;
				}

				// Build the table HTML string
				let tableHtml = `<table class='table table-striped table-bordered'>
                    <thead>
                        <tr>
                            <th>Craft Type</th>
                            <th>Registration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>`;

				// Loop through each craft and create a simple, non-interactive row
				response.crafts.forEach(craft => {
					tableHtml += `
                        <tr>
                            <td>${escapeHtml(craft.craft_type)}</td>
                            <td>${escapeHtml(craft.registration)}</td>
                            <td>${craft.alive == 1 ? '<span class="text-success">Active</span>' : '<span class="text-muted">Inactive</span>'}</td>
                        </tr>`;
				});

				tableHtml += `</tbody></table>`;
				$container.html(tableHtml);

			} else {
				const errorMessage = response.error || 'Could not load fleet data.';
				$container.html(`<p class='text-danger text-center'>${escapeHtml(errorMessage)}</p>`);
			}
		},
		error: function () {
			$container.html("<p class='text-danger text-center'>A network error occurred while loading the fleet list.</p>");
		}
	});
}