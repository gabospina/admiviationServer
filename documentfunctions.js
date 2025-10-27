// Keep this at the top if needed for layout adjustments
$(window).resize(function () {
	if ($("#personalSection").length && $("#document-panel").length && $("#document-list").length) {
		try { // Add try-catch for safety during calculations
			var availableWidth = $("#personalSection .row").first().width(); // Get width of content row
			var leftColWidth = $(".col-md-4").first().outerWidth(true) || 350; // Adjust based on your column class/width
			var panelWidth = availableWidth - leftColWidth - 30; // Subtract left col and some padding
			$("#document-panel").width(Math.max(300, panelWidth) + "px");
		} catch (e) { console.error("Resize calculation error:", e); }
	}
});

// Keep document ready wrapper
$(document).ready(function () {
	// Initialize all modals
	$('.modal').modal({
		show: false,
		backdrop: 'static',
		keyboard: false  // Prevent ESC key from closing the modal
	});

	$(".sidebar-list a[href='document_docufile.php'] li").addClass("active");
	$("body").addClass("body-bg");

	// --- Event Delegation for Document List Clicks ---
	// Handles clicks on document links even when the list is reloaded
	$("#document-list").on('click', '.document-item a.document-link', function (e) {
		e.preventDefault(); // Stop link from navigating

		// Visually mark the selected item
		$("#document-list .document-item.selected").removeClass("selected");
		var $listItem = $(this).closest('.document-item');
		$listItem.addClass("selected");

		// Get the document ID from the data attribute
		var docId = $listItem.data("doc-id");
		if (docId) {
			console.log("Manual click on document item, ID:", docId);
			// Visually mark as selected
			$listItem.siblings().removeClass('selected'); // Use 'selected' or 'active' consistently
			$listItem.addClass('selected');

			// Call YOUR getDocument function to load details
			getDocument(docId);
		} else {
			console.error("Could not get doc-id from clicked item:", $listItem);
		}
	});

	// =========================================================================
	// === UNIFIED EVENT HANDLERS FOR CATEGORY MANAGEMENT                    ===
	// =========================================================================

	// --- 1. UNIFIED 'change' HANDLER FOR THE MAIN CATEGORY DROPDOWN ---
	// This single function handles everything that needs to happen when the
	// category filter dropdown is changed.
	$('#documentCategories').on('change', function () {

		// --- Part A: Logic for refreshing the document list ---
		const selectedMainCategory = $(this).val(); // Get the selected value (e.g., 'AllCategories' or a category ID)
		console.log("Main category filter changed to:", selectedMainCategory);
		resetDocumentPanel(); // Reset the details panel to avoid showing outdated info
		getDocumentList(null, selectedMainCategory); // Reload the document list with the new filter

		// --- Part B: Logic for enabling/disabling the delete button ---
		const $deleteBtn = $('#deleteCategoryBtn');

		// Check if a specific category (which will be a number/ID) is selected
		if (selectedMainCategory && !isNaN(selectedMainCategory) && selectedMainCategory !== 'AllCategories') {
			$deleteBtn.prop('disabled', false); // Enable the delete button because a valid category is chosen
		} else {
			$deleteBtn.prop('disabled', true); // Disable the button if "All Categories" is selected
		}
	});

	// --- 2. 'click' HANDLER FOR THE DELETE CATEGORY BUTTON ---
	// This function handles the process of deleting the currently selected category.
	$('#deleteCategoryBtn').on('click', function () {
		const $deleteBtn = $(this);
		const $categoryDropdown = $('#documentCategories');
		const categoryId = $categoryDropdown.val();
		const categoryName = $categoryDropdown.find('option:selected').text(); // Correct way to get text

		// Safety check in case the button was enabled incorrectly
		if (!categoryId || isNaN(categoryId) || categoryId === 'AllCategories') {
			showNotification('warning', 'Please select a valid category to delete.');
			return;
		}

		// User confirmation dialog - a crucial step
		const confirmation = confirm(
			`Are you sure you want to permanently delete the category "${categoryName}"?\n\n` +
			`IMPORTANT: All documents currently in this category will become 'Uncategorized'. The documents themselves will NOT be deleted.`
		);

		if (!confirmation) {
			return; // Stop if the user clicks "Cancel"
		}

		// Provide immediate user feedback by disabling the button and showing a spinner
		$deleteBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Deleting...');

		// Perform the AJAX request to the server
		$.ajax({
			url: 'document_delete_category.php',
			type: 'POST',
			data: {
				categoryId: categoryId,
				csrf_token: $("input[name='csrf_token']").val() // Securely get the CSRF token from the form
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response && response.success) {
					showNotification('success', response.message);

					// Refresh the entire UI to reflect the deletion
					getCategories(false); // Reload the main category filter dropdown
					getCategories(true, 'simpleForm'); // Reload the dropdown in the upload form
					getDocumentList(null, 'AllCategories'); // Refresh the document list to show "All Categories"
				} else {
					showNotification('error', 'Error: ' + (response.message || 'Could not delete category.'));
				}
			})
			.fail(function (xhr) {
				showNotification('error', 'A server error occurred. Please try again.');
				console.error('Category delete failed:', xhr.responseText);
			})
			.always(function () {
				// This runs after the request is complete, whether it succeeded or failed
				// Restore the button's original text. It will remain disabled because the
				// getCategories() call will reset the dropdown to "All Categories".
				$deleteBtn.html('<i class="fa fa-trash"></i> Delete Selected Category');
			});
	});

	// =========================================================================
	// === END OF CATEGORY MANAGEMENT BLOCK                                  ===
	// =========================================================================

	// Initialize when modal opens
	$('#uploadDocumentsModal').on('shown.bs.modal', function () {
		// loadCategories();
		getCategories(true, "new");

		// Reset fields on open
		$("#modalCategories").val(''); // Reset dropdown
		$("#addCategoryInput").val(''); // Clear input field
		// Ensure input field container is hidden initially
		// Use the class from your original HTML: '.new-category-group'
		$(".new-category-group").hide();
	});

	// ==============================================
	// === CHANGE CATEGORY MODAL - FINAL VERSION ===
	// ==============================================

	// --- 1. Clean up any existing handlers to prevent multiple firings ---
	$('#changeCategoryModal').off('show.bs.modal shown.bs.modal hidden.bs.modal');
	$('#changeCategory').off('change.toggleChangeInput');
	$('#changeCategorySave').off('click');

	// --- 2. 'show.bs.modal' event: Runs BEFORE the modal is visible ---
	$('#changeCategoryModal').on('show.bs.modal', function (event) {
		console.log("DEBUG: Modal showing - initialization");
		var currentDocId = $("#document-panel").data("docId");
		if (!currentDocId) {
			showNotification("warning", "Please select a document first");
			return event.preventDefault();
		}
		$("#changeCategory").html('<option value="">Loading...</option>').prop('disabled', true);
		$("#changeCategoryInput").val('');
		$("#changeNewCategoryGroup").hide();
	});

	// --- 3. 'shown.bs.modal' event: Runs AFTER the modal is fully visible --
	$('#changeCategoryModal').on('shown.bs.modal', function () {
		console.log("DEBUG: Bootstrap 'shown.bs.modal' event. Modal is visible, now fetching content.");
		var currentCategoryId = $("#document-panel").data("categoryId");
		try {
			getCategories(true, "change", currentCategoryId);
		} catch (e) {
			console.error("Error loading categories:", e);
			showNotification("error", "Failed to load category list.");
		}
	});

	// --- 4. 'hidden.bs.modal' event: Runs AFTER the modal is fully hidden --
	$('#changeCategoryModal').on('hidden.bs.modal', function () {
		console.log("DEBUG: Bootstrap 'hidden.bs.modal' event. Cleaning up modal state.");
		$("#changeCategoryInput").val('');
		$("#changeNewCategoryGroup").hide();
		// Optional: Reset the save button state if an error occurred.
		$('#changeCategorySave').prop('disabled', false).html('Save Changes');
	});

	// --- 5. Handler for the Category dropdown changing ---
	$('#changeCategory').on('change.toggleChangeInput', function () {
		var $inputGroup = $("#changeNewCategoryGroup");
		if ($(this).val() === "AddCategory") {
			$inputGroup.slideDown(300, function () {
				$("#changeCategoryInput").focus();
			});
		} else {
			$inputGroup.slideUp(300);
			$("#changeCategoryInput").val('');
		}
	});

	// Save button click handler
	$('#changeCategorySave').on('click', function (e) {
		e.preventDefault();
		console.log("DEBUG: Save button clicked");

		var $button = $(this);
		var $modal = $('#changeCategoryModal');
		var docId = $("#document-panel").data("docId");
		var selectedValue = $("#changeCategory").val();
		var newCategoryName = $("#changeCategoryInput").val().trim();

		// Validation
		if (!docId) {
			showNotification("error", "No document selected");
			return;
		}

		var categoryToSend;
		var isNewCategory = false;

		if (selectedValue === "AddCategory") {
			if (!newCategoryName) {
				showNotification("warning", "Please enter a category name");
				return;
			}
			categoryToSend = newCategoryName;
			isNewCategory = true;
		} else if (selectedValue) {
			categoryToSend = selectedValue;
			isNewCategory = false;
		} else {
			showNotification("warning", "Please select a category");
			return;
		}

		// Disable button during AJAX
		$button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

		$.ajax({
			type: "POST",
			url: "document_categories_update.php",
			data: {
				docid: docId,
				category: categoryToSend,
				isNew: isNewCategory
			},
			dataType: "json"
		})
			.done(function (response) {
				if (response.success) {
					showNotification("success", response.message || "Category updated successfully");

					// Refresh UI
					getCategories(false);
					getDocumentList(docId, $("#documentCategories").val());

					if (response.new_category_id) {
						$("#document-panel").data("categoryId", response.new_category_id);
					}

					// Close modal only after success
					$modal.modal('hide');
				} else {
					showNotification("error", response.message || "Update failed");
				}
			})
			.fail(function (jqXHR) {
				showNotification("error", "Server error: " + (jqXHR.responseJSON?.message || "Please try again"));
				console.error("AJAX error:", jqXHR.responseText);
			})
			.always(function () {
				$button.prop('disabled', false).html('Save Changes');
			});
	});

	// ==============================================
	// === END OF MODAL HANDLER ===
	// ==============================================

	// --- Update Download Button ---
	$("#downloadDocument").click(function () {
		// We need the actual filepath from the database (stored when getDocument runs)
		var filePath = $("#document-panel").data("filepath"); // Retrieve path stored on panel
		var originalFilename = $("#document-filename").text(); // Get original filename for suggestion

		if (filePath) {
			// === FIX: OPEN IN NEW TAB ===
			window.open(filePath, '_blank');
			// var downloadLink = $("#downloadLink");
			// downloadLink.attr("href", filePath);
			// downloadLink.attr("download", originalFilename || "download"); // Suggest original filename
			// downloadLink[0].click(); // Trigger the hidden link
			console.log("Attempting download in a new tab:", filePath);

			// Optional: Log download (needs backend)
			var docId = $("#document-list .selected").data("pk");
			if (docId) {
				// logDownload(docId); // Function to call AJAX to log
			}

		} else {
			if (typeof showNotification === "function") {
				showNotification("error", "Cannot download. File path not available. Select the document again.");
			} else {
				alert("Cannot download. Please select the document again.");
			}
			console.error("Download failed - filepath missing from #document-panel data.");
		}
	});

	// Inside $(document).ready()

	// --- Delete Button (MAIN button in the panel menu) ---
	$("#deleteDocument").click(function () {
		console.log("Main Delete Button Clicked");
		// *** Get docId from the PANEL data ***
		var docId = $("#document-panel").data("docId");
		var docName = $("#document-filename").text() || "this document"; // Get name from panel

		// *** Check if docId was successfully retrieved ***
		if (!docId) {
			showNotification("warning", "Could not identify the document to delete. Please re-select the document.");
			console.error("Main Delete Error: docId missing from #document-panel data.");
			return; // Stop if no ID found
		}

		// Confirmation dialog
		if (!confirm("Are you sure you want to permanently delete:\n\n" + docName + "\n\nThis action cannot be undone.")) {
			console.log("Main deletion cancelled.");
			return;
		}

		console.log("Attempting MAIN delete for document ID:", docId);
		// Optionally disable button
		$(this).prop('disabled', true);

		$.ajax({
			url: 'document_delete.php',
			type: 'POST',
			data: {
				documentId: docId,
				csrf_token: $("input[name='csrf_token']").val() // Inject token
			},
			dataType: 'json'
		})
			.done(function (response) {
				if (response?.success) {
					showNotification("success", response.message || "Document deleted successfully.");
					resetDocumentPanel(); // Clear details panel
					var currentFilter = $("#documentCategories").val();
					getDocumentList(null, currentFilter); // Refresh list
				} else {
					showNotification("error", "Delete failed: " + htmlspecialchars(response?.message || "Unknown error"));
					console.error("Main Delete failed (backend):", response);
				}
			})
			.fail(function (jqXHR) {
				showNotification("error", "AJAX error: Could not delete document.");
				console.error("AJAX error during main delete:", jqXHR.responseText);
			})
			.always(function () {
				$("#deleteDocument").prop('disabled', false); // Re-enable button
			});
	}); // End #deleteDocument click

	// ============================================================
	// ===== AJAX Submission for Simple Upload Form ==============
	// ============================================================
	$("#simpleUploadForm").on('submit', function (event) {
		// --- 1. Prevent default browser submission ---
		event.preventDefault();
		console.log("Simple form submitted, preventing default.");

		var $submitButton = $(this).find('button[type="submit"]');
		var originalButtonText = $submitButton.html();
		$submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Processing...');

		// --- 2. Gather Form Data (including file) ---
		var formData = new FormData(this); // 'this' refers to the form element

		console.log("Attempting AJAX submission to document_upload.php");

		// --- 3. Perform AJAX Request ---
		$.ajax({
			url: $(this).attr('action'), // Get URL from form's action attribute
			type: $(this).attr('method'), // Get method from form's method attribute (POST)
			data: formData,
			dataType: 'json',           // Expect JSON response from PHP
			processData: false,         // Important! Prevent jQuery from processing the FormData object
			contentType: false,         // Important! Let the browser set the correct 'multipart/form-data' header with boundary
			cache: false,               // Prevent caching of the request

			// --- 4. Handle Response ---
			success: function (response) {
				console.log("AJAX Success Response:", response);
				if (response && response.success) {
					// --- SUCCESS ---
					var alertMessage = response.message || "Operation successful!"; // Use message from PHP
					if (response.newCategoryCreated) {
						alertMessage += "\nNew category '" + htmlspecialchars(response.categoryName || '') + "' was also created.";
					}
					alert(alertMessage); // Show success alert

					// a) Refresh Category Dropdowns (if they exist and need updating)
					if (typeof getCategories === "function") {
						getCategories(false);              // Refresh main filter dropdown
						getCategories(true, 'simpleForm'); // Refresh simple form dropdown
						// getCategories(true, 'change');  // Refresh change category modal if needed
					} else {
						console.warn("getCategories function not found, dropdowns not refreshed.");
					}

					// b) Determine Parameters for YOUR getDocumentList
					var newFileId = response.file_id || null; // Get the ID of the newly uploaded file (or null)
					var categoryFilterToRefresh = $('#documentCategories').val() || 'AllCategories'; // Get the currently selected filter value

					console.log(`Calling YOUR getDocumentList with idToSelect: ${newFileId}, categoryId: ${categoryFilterToRefresh}`);
					getDocumentList(null, $('#documentCategories').val() || 'AllCategories');

					// 4. Reset the form
					$("#simpleUploadForm")[0].reset(); // Resets the form to initial state

				} else {
					var errorMessage = response?.message || "An unknown error occurred during upload.";
					console.error("AJAX Submission Failed (Server Side):", errorMessage, response);
					alert("Error: " + htmlspecialchars(errorMessage));
				}
			},
			error: function (jqXHR, textStatus, errorThrown) {
				// --- AJAX CALL FAILURE ---
				console.error("AJAX Call Error:", textStatus, errorThrown, jqXHR.responseText);
				var errorMsg = "An error occurred while communicating with the server.";
				if (jqXHR.responseText) {
					try {
						var errResponse = JSON.parse(jqXHR.responseText);
						if (errResponse.message) errorMsg += "\nDetails: " + errResponse.message;
					} catch (e) { /* Ignore parse error */ }
				}
				// Use YOUR htmlspecialchars function
				alert("AJAX Error: " + htmlspecialchars(errorMsg));
			},
			complete: function () {
				// --- Runs after success or error ---
				$submitButton.prop('disabled', false).html(originalButtonText);
				console.log("AJAX submission complete.");
			}
		}); // End $.ajax
	}); // End form submit handler

	// ============================================================
	// ===== END AJAX Submission ==================================
	// ============================================================

	// --- Initial Data Loads ---
	getCategories(false); // Load main category dropdown (#documentCategories)
	console.log("Attempting to call getCategories for simpleForm dropdown...");
	getCategories(true, 'simpleForm'); // *** NEW: Load simple form category dropdown ***
	getDocumentList(null, 'AllCategories'); // Load initial document list

	// --- Keep Window Resize ---
	// Call it once on load to set initial size
	$(window).resize();

	// Add specific handler for Cancel button
	// $('#changeCategoryModal button[data-dismiss="modal"]').off('click').on('click', function (e) {
	// 	e.preventDefault();
	// 	var $modal = $('#changeCategoryModal');
	// 	$modal.removeClass('show');
	// 	$modal.css('display', 'none');
	// 	$('body').removeClass('modal-open');
	// 	$('.modal-backdrop').remove();
	// });

	// Open Preview in New Tab button
	$("#openPreview").click(function () {
		var previewPath = $("#document-panel").data("previewpath");
		if (previewPath) {
			window.open(previewPath, '_blank');
		} else {
			showNotification("warning", "Preview path not available.");
		}
	});

	// Download Original button
	$("#downloadOriginal").click(function () {
		var originalPath = $("#document-panel").data("filepath");
		if (originalPath) {
			// === FIX: OPEN IN NEW TAB ===
			window.open(originalPath, '_blank');
			// var downloadLink = $("#downloadLink");
			// downloadLink.attr("href", originalPath);
			// downloadLink.attr("download", $("#document-filename").text() || "download");
			// downloadLink[0].click();
		} else {
			showNotification("warning", "Original file path not available.");
		}
	});
}); // --- END document ready ---

// ==============================================================
// ===== FUNCTION DEFINITIONS (Keep but potentially adapt) =====
// ==============================================================

// --- Refactored getDocumentList --- (Example - Apply similar changes to others)
function getDocumentList(idToSelect, categoryId) { // Keep your parameters
	$("#document-list").html('<p class="text-info text-center"><i class="fa fa-spinner fa-spin"></i> Loading documents...</p>'); // Loading indicator
	resetDocumentPanel(); // Reset details panel when list reloads
	var ajaxData = {};

	// Prepare data for AJAX call - only send category if it's specific
	if (categoryId && categoryId !== "AllCategories") {
		// Basic check if it looks like a valid ID, although PHP should handle it
		if (String(categoryId).match(/^\d+$/)) {
			ajaxData.category = categoryId; // Send the specific category ID
		} else {
			console.warn("getDocumentList: Invalid category ID provided:", categoryId);
			// Decide if you want to default to 'AllCategories' or send the invalid ID
			// Defaulting to All is safer:
			categoryId = 'AllCategories';
		}
	}
	// If categoryId is 'AllCategories' or invalid, ajaxData remains empty or { category: undefined }
	// Your PHP script should interpret an empty/missing 'category' parameter as "fetch all".

	console.log("getDocumentList: Fetching documents with data:", ajaxData);

	$.ajax({
		type: "GET",
		url: "document_get_documents.php", // Ensure this path is correct
		data: ajaxData,
		dataType: "json",
		cache: false // Prevent caching issues
	})
		.done(function (response) {
			$("#document-list").empty(); // Clear loading message
			if (response && response.success && Array.isArray(response.data)) {
				var documents = response.data;
				console.log("getDocumentList: Received " + documents.length + " documents.");

				if (documents.length > 0) {
					var listHtml = '<ul class="list-group" style="width: 100%;">'; // Ensure full width
					documents.forEach(function (doc) {
						listHtml += `
							<li class="list-group-item document-item" data-doc-id="${htmlspecialchars(doc.id)}">
								<div class="document-info">
									<span class="badge category-badge">
										${htmlspecialchars(doc.category_name || 'Uncategorized')}
									</span>
									<a href="#" class="document-link">
										<i class="fa fa-file-o"></i> 
										${htmlspecialchars(doc.original_filename)}
									</a>
								</div>
								<div class="document-date">
									<span class="badge date-badge">
										${moment(doc.upload_date).format("MMM DD, YYYY")}
									</span>
								</div>
							</li>
						`;
					});

					listHtml += '</ul>';
					$("#document-list").html(listHtml);

					// --- Auto-select and load details if idToSelect is provided ---
					if (idToSelect !== undefined && idToSelect != null) {
						console.log("getDocumentList: Attempting to auto-select document ID:", idToSelect);
						// Find the item using the data attribute
						var $itemToSelect = $('#document-list .document-item[data-doc-id="' + idToSelect + '"]');
						if ($itemToSelect.length) {
							$itemToSelect.addClass("selected active"); // Add 'selected' and Bootstrap's 'active'
							// Call getDocument to load the details panel
							getDocument(idToSelect);
						} else {
							console.warn("getDocumentList: Document ID " + idToSelect + " provided but not found in the refreshed list.");
							// Maybe the document belongs to a different category than the one currently displayed?
						}
					}

				} else {
					// No documents found for the selected criteria
					$("#document-list").html("<div class='alert alert-info text-center'>No documents found.</div>");
				}
			} else {
				// Handle AJAX success but backend reports failure or data is invalid
				var message = response?.message || "Failed to load document list or invalid data format.";
				$("#document-list").html('<div class="alert alert-danger text-center">' + htmlspecialchars(message) + '</div>');
				console.error("Error fetching documents:", message, response);
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			$("#document-list").html('<div class="alert alert-danger text-center">AJAX Error loading documents. Please try again later.</div>');
			console.error("AJAX Error fetching documents:", textStatus, errorThrown, jqXHR.responseText);
		});
}

function getCategories(isModal, type, selectedValue) {
	// --- Declare callContext and targetSelector early ---
	var targetSelector = "#documentCategories"; // Default target
	var callContext = "Main Filter"; // Default context for logging

	// --- Determine target and context based on parameters ---
	if (isModal && type === "change") {
		targetSelector = "#changeCategory";
		callContext = "Change Modal"; // Set context for Change Modal
	} else if (type === 'simpleForm') {
		targetSelector = "#simpleExistingCategory";
		callContext = "Simple Form"; // Set context for Simple Form
		// console.log(`getCategories (${callContext}) - Type: ${type}, isModal: ${isModal}, Target Selector Set To: ${targetSelector}`); // Original log, modified below
		isModal = true; // Treat like modal for options logic
	}

	// --- Log the determined settings ---
	console.log(`DEBUG getCategories (${callContext}): Target=${targetSelector}, isModal=${isModal}, selectedValue=${selectedValue}`);

	// --- Find the target dropdown element ---
	var $selector = $(targetSelector);
	if ($selector.length === 0) {
		// Use callContext in the error message
		console.error(`DEBUG getCategories (${callContext}): Target selector not found: ${targetSelector}`);
		return; // Stop if element not found
	}

	// --- Set loading state ---
	$selector.prop('disabled', true).html('<option value="">Loading...</option>');

	// --- Perform AJAX request ---
	$.ajax({
		type: "GET",
		url: "document_get_categories.php", // Ensure path is correct
		dataType: "json",
		cache: false // Prevent caching issues
	})
		.done(function (response) {
			// Use callContext in logs
			console.log(`DEBUG getCategories (${callContext}): AJAX Response for ${targetSelector}`, response);
			$selector.prop('disabled', false).empty(); // Enable dropdown and clear loading message

			if (response && response.success && Array.isArray(response.data)) {
				// --- Add appropriate placeholder option ---
				if (!isModal && type !== 'simpleForm') { // Main filter specific placeholder
					$selector.append('<option value="AllCategories">All Categories</option>');
				} else { // Placeholder for Simple Form or Change Modal
					$selector.append('<option value="">-- Select Existing --</option>');
				}

				// --- Loop through received categories and add options ---
				console.log(`DEBUG getCategories (${callContext}): Looping through data for ${targetSelector}...`);
				response.data.forEach(function (category) {
					var categoryId = category.id; // Assuming 'id' from PHP
					var categoryName = category.name; // Assuming 'name' from PHP

					// Log each item being processed
					// console.log(`DEBUG getCategories (${callContext}): Processing item -> ID:`, categoryId, "Name:", categoryName); // Optional verbose log

					if (categoryId !== undefined && categoryId !== null && categoryName !== undefined) {
						// Create the option element
						var $option = $('<option>', {
							value: categoryId,
							text: htmlspecialchars(categoryName) // Use your htmlspecialchars
						});

						// Check if this option should be selected
						if (selectedValue !== undefined && selectedValue !== null && String(categoryId) === String(selectedValue)) {
							$option.prop('selected', true);
							// Log which item is being selected
							console.log(`DEBUG getCategories (${callContext}): Selecting category ID ${categoryId} for ${targetSelector}`);
						}
						// Append the option to the dropdown
						$selector.append($option);
					} else {
						// Warn about invalid data from the server
						console.warn(`DEBUG getCategories (${callContext}): Skipping invalid category data for ${targetSelector}:`, category);
					}
				}); // End forEach loop
				console.log(`DEBUG getCategories (${callContext}): Finished looping for ${targetSelector}`);

				// --- Add special option and trigger event ONLY for Change Category modal ---
				if (isModal && type === "change") {
					$selector.append('<option value="AddCategory">+ Create New Category</option>');
					// Trigger the event that shows/hides the input field
					console.log(`DEBUG getCategories (${callContext}): Triggering change.toggleChangeInput for ${targetSelector}`);
					$selector.trigger('change.toggleChangeInput'); // Event listener should be attached elsewhere
				}

			} else {
				// Handle failed response or invalid data from backend
				var message = response?.message || "Failed to parse categories or data format incorrect.";
				$selector.html('<option value="">Error Loading</option>');
				// Use callContext in error log
				console.error(`DEBUG getCategories (${callContext}): Error loading categories for ${targetSelector}:`, message, response);
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			// Handle AJAX communication error
			$selector.prop('disabled', false).html('<option value="">AJAX Error</option>');
			// Use callContext and targetSelector (both defined in parent scope) in error log
			console.error(`DEBUG getCategories (${callContext}): AJAX Error loading categories for ${targetSelector}:`, textStatus, errorThrown, jqXHR.responseText);
		});
} // End of getCategories function

// --- Modified getDocument Function ---
function getDocumentGOOD(id) {
	if (!id) return;
	console.log("getDocument called for ID:", id);

	var $documentPanel = $('#document-panel');
	var $documentContent = $('#document-content');
	var $documentTitle = $('#document-title');
	var $documentFilename = $('#document-filename');
	var $documentCreation = $('#document-creation');

	// Show loading state in PREVIEW area
	$documentContent.html(`
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p>Loading document preview...</p>
        </div>
    `);
	// Update title/info with loading state
	$documentTitle.text("Loading Document...");
	$documentFilename.text("...");
	$documentCreation.text("...");

	// Clear previous data from panel
	$documentPanel.removeData("filepath").removeData("previewpath").removeData("docId").removeData("categoryId");

	// Make sure panel is visible
	$documentPanel.show();

	$.ajax({
		url: 'document_get_document.php', // Verify path
		type: 'GET',
		data: { id: id },
		dataType: 'json',
		cache: false,
		success: function (response) {
			console.log("getDocument Response:", response);
			if (response?.success && response.data) {
				const doc = response.data;

				// Update document info in heading and menu
				$documentTitle.text(htmlspecialchars(doc.original_filename || 'Document Details'));
				$documentFilename.text(htmlspecialchars(doc.original_filename || 'N/A'));
				const uploadDate = doc.upload_date ? moment(doc.upload_date).format("MMM DD, YYYY") : 'N/A';
				$documentCreation.text(uploadDate);

				// Store data ON THE PANEL for other functions
				$documentPanel.data('filepath', doc.filepath || '');
				$documentPanel.data('previewpath', doc.preview_filepath || '');
				$documentPanel.data('docId', doc.id);
				$documentPanel.data('categoryId', doc.category_id);
				console.log("DEBUG getDocument: Stored data:", $documentPanel.data());

				// --- Generate and display preview IN #document-content ---
				let previewHtml = '';
				const originalFilename = doc?.original_filename ?? '';
				const fileExt = originalFilename.split('.').pop().toLowerCase();
				const originalFilePath = doc?.filepath ?? null;
				const previewFilePath = doc?.preview_filepath ?? null;

				// === FIX: IMPROVE PDF DETECTION ===
				const isPdfFile = fileExt === 'pdf';
				const isPdfMimeType = doc?.mime_type?.includes('pdf');

				// Use preview file if available, otherwise use original file for PDFs
				const pathForPdfEmbed = (previewFilePath && previewFilePath.toLowerCase().endsWith('.pdf'))
					? previewFilePath
					: (isPdfFile || isPdfMimeType) ? originalFilePath : null;

				// const pathForPdfEmbed = (previewFilePath && previewFilePath.toLowerCase().endsWith('.pdf')) ? previewFilePath : null;
				const pathForImageEmbed = (originalFilePath && ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) ? originalFilePath : null;

				if (pathForPdfEmbed) {
					console.log("DEBUG getDocument: Generating preview using Google Docs Viewer for:", pathForPdfEmbed);

					// === FIX: USE GOOGLE DOCS VIEWER FOR PDFS ===
					const absolutePdfUrl = new URL(pathForPdfEmbed, window.location.origin).href;
					const googleViewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(absolutePdfUrl)}&embedded=true`;

					previewHtml = `
					<div class="document-preview pdf-preview">
						<div class="preview-info">
							<i class="fa fa-file-pdf-o"></i> PDF Document Preview
						</div>
						${previewFilePath !== originalFilePath && previewFilePath ?
							'<div class="alert alert-info alert-sm">Preview generated from original document.</div>'
							: ''}
						
						<iframe 
							src="${htmlspecialchars(pathForPdfEmbed)}#toolbar=1&navpanes=1&scrollbar=1&view=FitH" 
                            type="application/pdf" 
							width="100%" 
							height="700px" 
							title="Document Preview">
							<p>Your browser does not support embedded documents.</p>
						</iframe>
					</div>`;
				}
				// --- END OF REPLACEMENT BLOCK ---
				else if (pathForImageEmbed) {
					// Image Preview
					console.log("DEBUG getDocument: Generating Image preview for:", pathForImageEmbed);
					previewHtml = `
                    <div class="document-preview image-preview text-center">
                        <img src="${htmlspecialchars(pathForImageEmbed)}" class="img-fluid" style="max-height: 600px; border: 1px solid #ccc;">
                        <p class="text-center mt-2">
                            <a href="${htmlspecialchars(pathForImageEmbed)}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> View Full Size
                            </a>
                        </p>
                    </div>`;
				}
				else {
					// Fallback for unsupported types
					console.log("DEBUG getDocument: No specific preview available for:", doc.original_filename);
					const downloadButtonHtml = originalFilePath ? `
                        <p class="text-center">
                            <a href="${htmlspecialchars(originalFilePath)}" class="btn btn-primary">
                                <i class="fa fa-download"></i> Download Original File
                            </a>
                        </p>` : '<p class="text-center text-danger">Error: Original file path is missing.</p>';

					previewHtml = `
                    <div class="document-preview unsupported">
                        <div class="alert alert-warning text-center">
                             <i class="fa fa-exclamation-triangle"></i> Preview not available for this file type (${htmlspecialchars(fileExt || 'unknown')}).
                        </div>
                        ${downloadButtonHtml}
                    </div>`;
				}
				// Set the generated preview HTML into the content area
				$documentContent.html(previewHtml);
				// --- End Preview Generation ---

			} else {
				// Handle backend error
				// ... (error handling as before) ...
				$documentContent.html(`<div class="alert alert-danger text-center">${htmlspecialchars(response?.message || 'Unknown error loading document details.')}</div>`);
				$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
				showNotification("error", "Error loading document: " + htmlspecialchars(response?.message || 'Unknown error'));
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// Handle AJAX error
			// ... (error handling as before) ...
			$documentContent.html(`<div class="alert alert-danger text-center">AJAX Error: Failed to load document details.</div>`);
			$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
			showNotification("error", "AJAX Error loading document details.");
		}
	});
} // End getDocument

// =============================================
// =============================================

// --- Modified getDocument Function with File Existence Check ---
function getDocument(id) {
	if (!id) return;
	console.log("getDocument called for ID:", id);

	var $documentPanel = $('#document-panel');
	var $documentContent = $('#document-content');
	var $documentTitle = $('#document-title');
	var $documentFilename = $('#document-filename');
	var $documentCreation = $('#document-creation');

	// Show loading state
	$documentContent.html(`
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p>Loading document preview...</p>
        </div>
    `);
	$documentTitle.text("Loading Document...");
	$documentFilename.text("...");
	$documentCreation.text("...");

	$documentPanel.removeData("filepath").removeData("previewpath").removeData("docId").removeData("categoryId");
	$documentPanel.show();

	$.ajax({
		url: 'document_get_document.php',
		type: 'GET',
		data: { id: id },
		dataType: 'json',
		cache: false,
		success: function (response) {
			console.log("getDocument Response:", response);
			if (response?.success && response.data) {
				const doc = response.data;

				// Update document info
				$documentTitle.text(htmlspecialchars(doc.original_filename || 'Document Details'));
				$documentFilename.text(htmlspecialchars(doc.original_filename || 'N/A'));
				const uploadDate = doc.upload_date ? moment(doc.upload_date).format("MMM DD, YYYY") : 'N/A';
				$documentCreation.text(uploadDate);

				// Store data
				$documentPanel.data('filepath', doc.filepath || '');
				$documentPanel.data('previewpath', doc.preview_filepath || '');
				$documentPanel.data('docId', doc.id);
				$documentPanel.data('categoryId', doc.category_id);
				console.log("DEBUG getDocument: Stored data:", $documentPanel.data());

				// Generate preview
				generateDocumentPreview(doc, $documentContent);

			} else {
				$documentContent.html(`<div class="alert alert-danger text-center">${htmlspecialchars(response?.message || 'Unknown error loading document details.')}</div>`);
				$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
				showNotification("error", "Error loading document: " + htmlspecialchars(response?.message || 'Unknown error'));
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			$documentContent.html(`<div class="alert alert-danger text-center">AJAX Error: Failed to load document details.</div>`);
			$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
			showNotification("error", "AJAX Error loading document details.");
		}
	});
}

// --- New Function: Check File Existence and Generate Preview ---
function generateDocumentPreview(doc, $targetElement) {
	const originalFilename = doc?.original_filename ?? '';
	const fileExt = originalFilename.split('.').pop().toLowerCase();
	const originalFilePath = doc?.filepath ?? null;
	const previewFilePath = doc?.preview_filepath ?? null;

	console.log("DEBUG generateDocumentPreview:", {
		originalFilename,
		fileExt,
		originalFilePath,
		previewFilePath
	});

	// Check if preview file exists
	checkFileExists(previewFilePath).then(previewExists => {
		// Check if original file exists
		checkFileExists(originalFilePath).then(originalExists => {
			let previewHtml = '';

			// Determine what type of preview to show
			const isPdfFile = fileExt === 'pdf';
			const isPdfPreviewAvailable = previewExists && previewFilePath.toLowerCase().endsWith('.pdf');
			const isImageFile = ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt);

			console.log("DEBUG File existence check:", {
				previewExists,
				originalExists,
				isPdfFile,
				isPdfPreviewAvailable,
				isImageFile
			});

			// Case 1: PDF file or PDF preview available
			if ((isPdfFile || isPdfPreviewAvailable) && (previewExists || originalExists)) {
				const pdfPathToUse = previewExists ? previewFilePath : originalFilePath;
				console.log("DEBUG: Using PDF path:", pdfPathToUse);

				previewHtml = createPdfPreviewHtml(pdfPathToUse, originalFilePath, doc);
			}
			// Case 2: Image file
			else if (isImageFile && originalExists) {
				previewHtml = createImagePreviewHtml(originalFilePath);
			}
			// Case 3: Word document with no PDF preview
			else if (['doc', 'docx'].includes(fileExt)) {
				previewHtml = createWordDocumentPreviewHtml(originalFilePath, previewFilePath, originalExists, previewExists, doc);
			}
			// Case 4: Unsupported or missing files
			else {
				previewHtml = createFallbackPreviewHtml(originalFilePath, fileExt, originalExists, doc);
			}

			$targetElement.html(previewHtml);
		});
	});
}

// --- Helper Function: Check if file exists ---
function checkFileExists(filePath) {
	if (!filePath) return Promise.resolve(false);

	return new Promise((resolve) => {
		$.ajax({
			url: filePath,
			type: 'HEAD',
			success: function () {
				resolve(true);
			},
			error: function () {
				resolve(false);
			}
		});
	});
}

// ====================
// --- Updated Function: Create PDF Preview ---
function createPdfPreviewHtml(pdfPath, originalPath, doc) {
	console.log("DEBUG: Creating PDF preview for:", pdfPath);

	// Method 1: Direct PDF embedding (works for same-origin PDFs)
	const directPdfUrl = pdfPath + '#toolbar=1&navpanes=1&scrollbar=1';

	return `
    <div class="document-preview pdf-preview">
        <div class="preview-info bg-light p-2 mb-2">
            <i class="fa fa-file-pdf-o text-danger"></i> 
            <strong>PDF Document</strong>
            <span class="badge badge-success ml-2">Live Preview</span>
        </div>
        
        <div class="embed-responsive embed-responsive-16by9">
            <embed 
                src="${htmlspecialchars(directPdfUrl)}" 
                type="application/pdf" 
                width="100%" 
                height="600px"
                class="embed-responsive-item"
                title="PDF Document: ${htmlspecialchars(doc.original_filename)}">
        </div>
        
        ${createPdfActionButtons(pdfPath, originalPath)}
    </div>`;
}

// --- Alternative: Iframe-based PDF preview ---
function createPdfPreviewHtmlIframe(pdfPath, originalPath, doc) {
	return `
    <div class="document-preview pdf-preview">
        <div class="preview-info bg-light p-2 mb-2">
            <i class="fa fa-file-pdf-o text-danger"></i> 
            <strong>PDF Document</strong>
            <span class="badge badge-success ml-2">Live Preview</span>
        </div>
        
        <iframe 
            src="${htmlspecialchars(pdfPath)}#toolbar=1&navpanes=1&scrollbar=1" 
            width="100%" 
            height="700px" 
            style="border: 1px solid #ddd;"
            title="PDF Document: ${htmlspecialchars(doc.original_filename)}">
            <p>Your browser does not support PDF embedding. 
               <a href="${htmlspecialchars(pdfPath)}" target="_blank">Download the PDF</a> instead.
            </p>
        </iframe>
        
        ${createPdfActionButtons(pdfPath, originalPath)}
    </div>`;
}

// --- Helper: Create PDF action buttons ---
function createPdfActionButtons(pdfPath, originalPath) {
	return `
    <div class="text-center mt-3">
        <div class="btn-group" role="group">
            <a href="${htmlspecialchars(pdfPath)}" target="_blank" class="btn btn-outline-primary">
                <i class="fa fa-external-link-alt"></i> Open in New Tab
            </a>
            <a href="${htmlspecialchars(pdfPath)}" download class="btn btn-outline-secondary">
                <i class="fa fa-download"></i> Download
            </a>
            <button onclick="printPdf('${htmlspecialchars(pdfPath)}')" class="btn btn-outline-info">
                <i class="fa fa-print"></i> Print
            </button>
        </div>
    </div>`;
}

// --- Print PDF function ---
function printPdf(pdfUrl) {
	const printWindow = window.open(pdfUrl, '_blank');
	if (printWindow) {
		printWindow.onload = function () {
			printWindow.print();
		};
	}
}

// =======================
// --- Helper Function: Create Word Document Preview ---
function createWordDocumentPreviewHtml(originalPath, previewPath, originalExists, previewExists, doc) {
	let statusHtml = '';

	if (!previewExists) {
		statusHtml = `
        <div class="alert alert-warning alert-sm">
            <i class="fa fa-exclamation-triangle"></i> PDF preview not yet generated.
            <button onclick="generatePdfPreview(${doc.id})" class="btn btn-xs btn-warning ml-2">
                <i class="fa fa-sync"></i> Generate Preview
            </button>
        </div>`;
	}

	return `
    <div class="document-preview word-preview text-center">
        <div class="preview-info mb-3">
            <i class="fa fa-file-word-o fa-3x text-primary"></i>
            <h5>Word Document</h5>
            <p class="text-muted">${doc.original_filename}</p>
        </div>
        
        ${statusHtml}
        
        <div class="btn-group">
            <a href="${htmlspecialchars(originalPath)}" download class="btn btn-primary">
                <i class="fa fa-download"></i> Download Word Document
            </a>
            ${previewExists ? `
            <a href="${htmlspecialchars(previewPath)}" target="_blank" class="btn btn-outline-primary">
                <i class="fa fa-eye"></i> View PDF Preview
            </a>` : ''}
        </div>
    </div>`;
}

// --- Helper Function: Generate PDF Preview (for Word docs) ---
function generatePdfPreview(docId) {
	showNotification("info", "Generating PDF preview...");

	$.ajax({
		url: 'generate_pdf_preview.php', // You'll need to create this endpoint
		type: 'POST',
		data: { doc_id: docId },
		dataType: 'json',
		success: function (response) {
			if (response.success) {
				showNotification("success", "PDF preview generated successfully!");
				// Reload the document
				getDocument(docId);
			} else {
				showNotification("error", "Failed to generate PDF preview: " + response.message);
			}
		},
		error: function () {
			showNotification("error", "Error generating PDF preview");
		}
	});
}

// --- Other helper functions (image preview, fallback) ---
function createImagePreviewHtml(imagePath) {
	return `
    <div class="document-preview image-preview text-center">
        <img src="${htmlspecialchars(imagePath)}" class="img-fluid" style="max-height: 600px; border: 1px solid #ccc;">
        <p class="text-center mt-2">
            <a href="${htmlspecialchars(imagePath)}" target="_blank" class="btn btn-sm btn-outline-primary">
                <i class="fa fa-external-link-alt"></i> View Full Size
            </a>
        </p>
    </div>`;
}

function createFallbackPreviewHtml(originalPath, fileExt, originalExists, doc) {
	const downloadButton = originalExists ? `
        <p class="text-center">
            <a href="${htmlspecialchars(originalPath)}" download class="btn btn-primary">
                <i class="fa fa-download"></i> Download Original File
            </a>
        </p>` : '<p class="text-center text-danger">Error: File not found on server.</p>';

	return `
    <div class="document-preview unsupported">
        <div class="alert alert-warning text-center">
            <i class="fa fa-exclamation-triangle"></i> 
            ${originalExists ?
			`Preview not available for this file type (${htmlspecialchars(fileExt || 'unknown')}).` :
			'File not found on server.'}
        </div>
        ${downloadButton}
    </div>`;
}

// ==========================================
// =============================================

// Build the HTML with the new Google Viewer URL.
function getDocumentGoogleViewer(id) {
	if (!id) return;
	console.log("getDocument called for ID:", id);

	var $documentPanel = $('#document-panel');
	var $documentContent = $('#document-content');
	var $documentTitle = $('#document-title');
	var $documentFilename = $('#document-filename');
	var $documentCreation = $('#document-creation');

	// Show loading state in PREVIEW area
	$documentContent.html(`
        <div class="text-center py-4">
            <i class="fa fa-spinner fa-spin fa-2x"></i>
            <p>Loading document preview...</p>
        </div>
    `);
	// Update title/info with loading state
	$documentTitle.text("Loading Document...");
	$documentFilename.text("...");
	$documentCreation.text("...");

	// Clear previous data from panel
	$documentPanel.removeData("filepath").removeData("previewpath").removeData("docId").removeData("categoryId");

	// Make sure panel is visible
	$documentPanel.show();

	$.ajax({
		url: 'document_get_document.php', // Verify path
		type: 'GET',
		data: { id: id },
		dataType: 'json',
		cache: false,
		success: function (response) {
			console.log("getDocument Response:", response);
			if (response?.success && response.data) {
				const doc = response.data;

				// Update document info in heading and menu
				$documentTitle.text(htmlspecialchars(doc.original_filename || 'Document Details'));
				$documentFilename.text(htmlspecialchars(doc.original_filename || 'N/A'));
				const uploadDate = doc.upload_date ? moment(doc.upload_date).format("MMM DD, YYYY") : 'N/A';
				$documentCreation.text(uploadDate);

				// Store data ON THE PANEL for other functions
				$documentPanel.data('filepath', doc.filepath || '');
				$documentPanel.data('previewpath', doc.preview_filepath || '');
				$documentPanel.data('docId', doc.id);
				$documentPanel.data('categoryId', doc.category_id);
				console.log("DEBUG getDocument: Stored data:", $documentPanel.data());

				// --- Generate and display preview IN #document-content ---
				let previewHtml = '';
				const originalFilename = doc?.original_filename ?? '';
				const fileExt = originalFilename.split('.').pop().toLowerCase();
				const originalFilePath = doc?.filepath ?? null;
				const previewFilePath = doc?.preview_filepath ?? null;

				const pathForPdfEmbed = (previewFilePath && previewFilePath.toLowerCase().endsWith('.pdf')) ? previewFilePath : null;
				const pathForImageEmbed = (originalFilePath && ['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) ? originalFilePath : null;

				// --- REPLACE THE OLD BLOCK WITH THIS ---
				if (pathForPdfEmbed) {
					console.log("DEBUG getDocument: Generating preview using Google Docs Viewer for:", pathForPdfEmbed);

					// Create the full, absolute URL to your PDF file.
					const absolutePdfUrl = new URL(pathForPdfEmbed, window.location.origin).href;

					// Construct the Google Docs Viewer URL.
					const googleViewerUrl = `https://docs.google.com/gview?url=${encodeURIComponent(absolutePdfUrl)}&embedded=true`;

					// Build the HTML with the new Google Viewer URL.
					previewHtml = `
					<div class="document-preview pdf-preview">
						<div class="preview-info">
							<i class="fa fa-file-pdf-o"></i> PDF Document Preview
						</div>
						${previewFilePath !== originalFilePath ?
							'<div class="alert alert-info alert-sm">Preview generated from original document.</div>'
							: ''}
							
						<iframe 
							src="${htmlspecialchars(googleViewerUrl)}" 
							width="100%" 
							height="700px" 
							title="Document Preview">
							<p>Your browser does not support embedded documents.</p>
						</iframe>
					</div>`;
				}
				// --- END OF REPLACEMENT BLOCK ---
				else if (pathForImageEmbed) {
					// Image Preview
					console.log("DEBUG getDocument: Generating Image preview for:", pathForImageEmbed);
					previewHtml = `
                    <div class="document-preview image-preview text-center">
                        <img src="${htmlspecialchars(pathForImageEmbed)}" class="img-fluid" style="max-height: 600px; border: 1px solid #ccc;">
                        <p class="text-center mt-2">
                            <a href="${htmlspecialchars(pathForImageEmbed)}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="fa fa-external-link-alt"></i> View Full Size
                            </a>
                        </p>
                    </div>`;
				}
				else {
					// Fallback for unsupported types
					console.log("DEBUG getDocument: No specific preview available for:", doc.original_filename);
					const downloadButtonHtml = originalFilePath ? `
                        <p class="text-center">
                            <a href="${htmlspecialchars(originalFilePath)}" class="btn btn-primary">
                                <i class="fa fa-download"></i> Download Original File
                            </a>
                        </p>` : '<p class="text-center text-danger">Error: Original file path is missing.</p>';

					previewHtml = `
                    <div class="document-preview unsupported">
                        <div class="alert alert-warning text-center">
                             <i class="fa fa-exclamation-triangle"></i> Preview not available for this file type (${htmlspecialchars(fileExt || 'unknown')}).
                        </div>
                        ${downloadButtonHtml}
                    </div>`;
				}
				// Set the generated preview HTML into the content area
				$documentContent.html(previewHtml);
				// --- End Preview Generation ---

			} else {
				// Handle backend error
				// ... (error handling as before) ...
				$documentContent.html(`<div class="alert alert-danger text-center">${htmlspecialchars(response?.message || 'Unknown error loading document details.')}</div>`);
				$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
				showNotification("error", "Error loading document: " + htmlspecialchars(response?.message || 'Unknown error'));
			}
		},
		error: function (jqXHR, textStatus, errorThrown) {
			// Handle AJAX error
			// ... (error handling as before) ...
			$documentContent.html(`<div class="alert alert-danger text-center">AJAX Error: Failed to load document details.</div>`);
			$documentTitle.text("Error"); $documentFilename.text("Error"); $documentCreation.text("Error");
			showNotification("error", "AJAX Error loading document details.");
		}
	});
} // End getDocument

// --- resetDocumentPanel function (Make sure this exists) ---
function resetDocumentPanel() {
	console.log("resetDocumentPanel called");
	$("#document-panel").hide();
	$("#document-title").text("Select a Document");
	$("#document-filename").text("N/A");
	$("#document-creation").text("N/A");
	$("#document-content").html('<p class="text-muted text-center" style="padding: 40px 0;">Select a document from the list.</p>');
	$("#document-panel").removeData("filepath").removeData("previewpath").removeData("docId").removeData("categoryId"); // Clear stored data
}

// Placeholder for notification function if you use it
function showNotification(type, message) {
	console.log(`Notification (${type}): ${message}`);
	alert(`(${type.toUpperCase()}) ${message}`); // Simple fallback
}

function refreshCategoryDropdowns() {
	// Refresh main document filter dropdown
	getCategories();

	// Refresh modal dropdown if it exists
	if ($("#modalCategories").length) {
		$.getJSON("get_document_categories.php", function (data) {
			if (data.success) {
				var $dropdown = $("#modalCategories");
				$dropdown.empty();
				$dropdown.append('<option value="">Select Category</option>');
				$dropdown.append('<option value="AddCategory">+ Add New Category</option>');

				$.each(data.data, function (i, category) {
					$dropdown.append($('<option>', {
						value: category.id,
						text: category.name
					}));
				});
			}
		});
	}
}

function htmlspecialchars(str) {
	// Handle null/undefined/numbers - convert to string or empty string
	if (str == null) return '';
	if (typeof str !== 'string') str = String(str);

	// Character mapping for HTML entities
	var map = {
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#039;',  // &apos; is not HTML4 standard
		'/': '&#x2F;'   // Important for XSS prevention in some contexts
	};

	// Use a regex that catches all special characters at once
	return str.replace(/[&<>"'\/]/g, function (m) {
		return map[m];
	});
}

function formatDate1(dateString) {
	if (!dateString) return 'N/A';
	try {
		// Attempt to create a Date object and format it simply
		// Adjust formatting as needed (e.g., using libraries like moment.js for complex needs)
		var date = new Date(dateString);
		// Basic check if date is valid
		if (isNaN(date.getTime())) {
			return dateString; // Return original string if parsing fails
		}
		// Example format: YYYY-MM-DD HH:MM
		var year = date.getFullYear();
		var month = ('0' + (date.getMonth() + 1)).slice(-2); // Add leading zero
		var day = ('0' + date.getDate()).slice(-2);
		var hours = ('0' + date.getHours()).slice(-2);
		var minutes = ('0' + date.getMinutes()).slice(-2);
		return `${year}-${month}-${day} ${hours}:${minutes}`;
	} catch (e) {
		console.error("Error formatting date:", dateString, e);
		return dateString; // Return original string on error
	}
}
