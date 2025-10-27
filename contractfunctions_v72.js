// js/contractfunctions.js
// This script powers the read-only contracts.php page.

$(document).ready(function () {
	console.log("contractfunctions.js loaded.");

	// Select the containers where we will render the lists.
	const $contractsContainer = $('#contracts');
	const $customersContainer = $('#customerList');

	/**
	 * Fetches and renders the list of all contracts.
	 */
	function loadContractList1() {
		$contractsContainer.html('<p class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>');

		$.ajax({
			url: 'contract_get_all_contracts.php',
			dataType: 'json',
			success: function (response) {
				if (response.success && response.contracts && response.contracts.length > 0) {
					// Use a Bootstrap list-group for a clean look.
					let listHtml = '<ul class="list-group">';
					response.contracts.forEach(contract => {
						// --- THIS IS THE KEY ---
						// The contract name is wrapped in an <a> tag that links to the details page.
						listHtml += `
                            <li class="list-group-item">
                                <a href="contract_details.php?id=${contract.contractid}" target="_blank">
                                    ${escapeHtml(contract.contract)}
                                </a>
                                <small class="text-muted d-block">Customer: ${escapeHtml(contract.customer_name)}</small>
                            </li>`;
						// --- END OF KEY ---
					});
					listHtml += '</ul>';
					$contractsContainer.html(listHtml);
				} else {
					$contractsContainer.html('<p class="text-muted">No contracts found.</p>');
				}
			},
			error: function () {
				$contractsContainer.html('<p class="text-danger">Failed to load contracts.</p>');
			}
		});
	}

	function loadContractList() {
		const $contractsContainer = $('#contracts');
		$contractsContainer.html('<p class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading contracts...</p>');

		$.ajax({
			url: 'contract_get_all_contracts.php',
			dataType: 'json',
			success: function (response) {
				if (response.success && response.contracts && response.contracts.length > 0) {

					// --- THIS IS THE FIX ---
					// Start building an HTML table.
					let tableHtml = `
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Contract Name</th>
                                <th>Customer Name</th>
                                <th>Color</th>
                            </tr>
                        </thead>
                        <tbody>`;

					// Loop through the contracts and build a table row for each one.
					response.contracts.forEach(contract => {
						tableHtml += `
                        <tr>
                            <td>
                                <!-- The contract name is a clickable link -->
                                <a href="contract_details.php?id=${contract.contractid}" target="_blank">
                                    ${escapeHtml(contract.contract)}
                                </a>
                            </td>
                            <td>${escapeHtml(contract.customer_name)}</td>
                            <td>
                                <!-- The color is displayed as a colored box -->
                                <div style="height: 25px; background-color: ${escapeHtml(contract.color)};"></div>
                            </td>
                        </tr>`;
					});

					tableHtml += '</tbody></table>';
					// --- END OF FIX ---

					$contractsContainer.html(tableHtml);
				} else {
					$contractsContainer.html('<p class="text-muted">No contracts found.</p>');
				}
			},
			error: function () {
				$contractsContainer.html('<p class="text-danger">Failed to load contracts.</p>');
			}
		});
	}

	/**
	 * Fetches and renders the list of all customers.
	 */
	function loadCustomerList() {
		$customersContainer.html('<p class="text-center text-muted"><i class="fa fa-spinner fa-spin"></i> Loading customers...</p>');

		$.ajax({
			url: 'contract_get_all_customers.php',
			dataType: 'json',
			success: function (response) {
				if (response.success && response.customers && response.customers.length > 0) {
					let listHtml = '<ul class="list-group">';
					response.customers.forEach(customer => {
						listHtml += `<li class="list-group-item">${escapeHtml(customer.customer_name)}</li>`;
					});
					listHtml += '</ul>';
					$customersContainer.html(listHtml);
				} else {
					$customersContainer.html('<p class="text-muted">No customers found.</p>');
				}
			},
			error: function () {
				$customersContainer.html('<p class="text-danger">Failed to load customers.</p>');
			}
		});
	}

	// Helper function to prevent XSS
	function escapeHtml(text) {
		if (typeof text !== 'string') return text || '';
		const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
		return text.replace(/[&<>"']/g, m => map[m]);
	}

	// --- INITIALIZE THE PAGE ---
	loadContractList();
	loadCustomerList();
});