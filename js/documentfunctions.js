// Keep this at the top if needed for layout adjustments
$(window).resize(function () {
	// Consider if this calculation is still accurate with your CSS
	$("#document-panel").width($("#personalSection").width() - 375 + "px");
});

// Keep document ready wrapper
$(document).ready(function () {
	// --- Keep these lines ---
	Dropzone.autoDiscover = false; // Important: Keep this BEFORE initializing Dropzone
	$(".sidebar-list a[href='docufile.php'] li").addClass("active");
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

		if (docId !== undefined) {
			console.log("Document item clicked, ID:", docId);
			getDocument(docId); // Call function to fetch and display details
		} else {
			console.error("Could not find data-doc-id on clicked item.");
			resetDocumentPanel(); // Reset panel if ID is missing
		}
	});

	// --- Keep Modal Event Handlers ---

	$("#uploadDocumentsModal").on("shown.bs.modal", function () {
		// Load categories when modal opens (Your existing function)
		// Ensure the 'selector' logic inside getCategories handles #modalCategories if you add it back
		getCategories(true, "new");

		// Set up category change handler
		// $("#modalCategories").on("change", function () {
		// 	if ($(this).val() === "AddCategory") {
		// 		$("#newCategoryGroup")
		// 			.addClass("show")
		// 			.css("display", "block"); // Fallback
		// 		$("#addCategoryInput").focus();
		// 	} else {
		// 		$("#newCategoryGroup")
		// 			.removeClass("show")
		// 			.css("display", "none"); // Fallback
		// 	}
		// });
		$("#modalCategories").change(function () {
			if ($(this).val() === "AddCategory") {
				$("#addCategoryContainer").show();
				$("#addCategoryInput").focus();
			} else {
				$("#addCategoryContainer").hide();
			}
		});

		// Handle category selection change
		// $('#modalCategories').change(function () {
		// 	if ($(this).val() === 'AddCategory') {
		// 		// Show new category input field
		// 		$('.new-category-group').slideDown();
		// 		$('#addCategoryInput').focus();
		// 	} else {
		// 		// Hide new category input field
		// 		$('.new-category-group').slideUp();
		// 		$('#addCategoryInput').val('');
		// 	}
		// });
		// // Initialize - hide new category input by default
		// $('#addCategoryInput').closest('.form-group').hide();

	});

	$("#uploadDocumentsModal").on("hidden.bs.modal", function () {
		// Reset category selection
		$("#modalCategories").val('');
		$("#addCategoryGroup").hide();
		$("#addCategoryInput").val('');
	});

	// --- Keep button click to show modal ---
	// Ensure the button ID matches your docufile.php HTML (#uploadModalBtn)
	$("#uploadModalBtn").click(function () {
		$("#uploadDocumentsModal").modal("show"); // Use correct modal ID
		return false; // Prevent default if it's an anchor
	});

	// ===== MODIFIED DROPZONE INITIALIZATION =======================
	// ==============================================================
	if ($("#myAwesomeDropzone").length > 0 && Dropzone.instances.length === 0) {
		var myDropzone = new Dropzone("#myAwesomeDropzone", {
			url: "document_upload.php",
			paramName: "documentFile",
			maxFilesize: 15, // MB
			acceptedFiles: ".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.png,.gif",
			autoProcessQueue: true,
			addRemoveLinks: true,
			// parallelUploads: 1,
			// uploadMultiple: false,
			// timeout: 300000, // 5 minutes
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			},

			// Preview template with error handling
			previewTemplate: `
			<div class="dz-preview dz-file-preview">
				<div class="dz-details">
					<div class="dz-filename"><span data-dz-name></span></div>
					<div class="dz-size" data-dz-size></div>
					<img data-dz-thumbnail />
				</div>
				<div class="dz-progress"><span class="dz-upload" data-dz-uploadprogress></span></div>
				<div class="dz-success-mark"><span>✔</span></div>
				<div class="dz-error-mark"><span>✘</span></div>
				<div class="dz-error-message"><span data-dz-errormessage></span></div>
			</div>`,

			init: function () {
				var dzInstance = this;

				// this.on("sending", function (file, xhr, formData) {
				// 	// Clear any existing category data
				// 	formData.delete('isNewCategory');
				// 	formData.delete('newCategoryName');
				// 	formData.delete('categoryId');

				// 	// Get the new category input value
				// 	const newCategoryName = $("#addCategoryInput").val().trim();

				// 	// If new category name exists, use it
				// 	if (newCategoryName) {
				// 		formData.append('isNewCategory', '1');
				// 		formData.append('newCategoryName', newCategoryName);
				// 		console.log('Sending new category:', newCategoryName);
				// 	}
				// 	// Otherwise use the selected category if one exists
				// 	else {
				// 		const categoryId = $("#modalCategories").val();
				// 		if (categoryId && categoryId !== "") {
				// 			formData.append('categoryId', categoryId);
				// 			console.log('Sending existing category ID:', categoryId);
				// 		}
				// 	}

				// 	// Debug output
				// 	console.log('Final formData:', Array.from(formData.entries()));
				// });

				// In the sending event of Dropzone, modify to:
				this.on("sending", function (file, xhr, formData) {
					var categoryId = $("#modalCategories").val();
					var categoryName = "";

					if (categoryId === "AddCategory") {
						categoryName = $("#addCategoryInput").val().trim();
						if (!categoryName) {
							dzInstance.emit("error", file, "Please enter a category name");
							return false;
						}
						formData.append("isNewCategory", "1");
						formData.append("newCategoryName", categoryName);
					}
					else if (categoryId && categoryId !== "AddCategory") {
						categoryName = $("#modalCategories option:selected").text();
						formData.append("categoryId", categoryId);
					}

					formData.append("categoryName", categoryName || "Uncategorized");
				});

				// Success event - refresh categories if new one was created
				// this.on("success", function (file, response) {
				// 	if (response.success) {
				// 		let message = "File uploaded successfully";
				// 		if (response.newCategoryCreated) {
				// 			message += ` - New category created: ${response.categoryName}`;
				// 			// Refresh categories list
				// 			getCategories();
				// 		}
				// 		showNotification("success", message);

				// 		// Refresh documents list
				// 		getDocumentList();
				// 	}
				// });

				// this.on("success", function (file, response) {
				// 	if (response && response.success) {
				// 		if (typeof showNotification === "function") {
				// 			var msg = "File uploaded successfully";
				// 			if (response.newCategoryCreated) {
				// 				msg += " (new category created)";
				// 				refreshCategoryDropdowns();
				// 			}
				// 			showNotification("success", msg);
				// 		}
				// 		getDocumentList(response.file_id);
				// 	}
				// });

				this.on("success", function (file, response) {
					if (response && response.success) {
						if (response.newCategoryCreated) {
							getCategories(); // Refresh the category dropdown
						}
						getDocumentList(response.file_id);

						// Reset the form
						$("#modalCategories").val('');
						$("#addCategoryInput").val('').closest('.form-group').hide();
					}
				});

				// --- Error Event ---
				this.on("error", function (file, errorMessage, xhr) {
					var message = typeof errorMessage === "string" ? errorMessage :
						(errorMessage.message || "Upload failed");

					if (xhr && xhr.responseText) {
						try {
							var jsonResponse = JSON.parse(xhr.responseText);
							message = jsonResponse.message || message;
						} catch (e) { }
					}

					$(file.previewElement).addClass("dz-error")
						.find('.dz-error-message').text(message);

					if (typeof showNotification === "function") {
						showNotification("error", message);
					}
				});
			}
		});
	}
	// ===== END MODIFIED DROPZONE INITIALIZATION ===================
	// ==============================================================


	// --- Keep Category Input Handling ---
	// This works with your getCategories logic that shows/hides these inputs
	$("#addCategoryInput").keyup(function () {
		$("#categoryValue").val($(this).val());
	});
	$("#changeCategoryInput").keyup(function () {
		$("#changeCategoryValue").val($(this).val());
	});

	// --- Keep Change Category Save ---
	$("#changeCategorySave").click(function () {
		var categoryValue = $("#changeCategoryValue").val(); // Get value from hidden input updated by keyup/change
		var selectedCategoryName = $("#changeCategory option:selected").val(); // Get selected dropdown value
		// Determine the final category name to send
		var categoryToSend = (selectedCategoryName === 'AddCategory') ? $("#changeCategoryInput").val().trim() : categoryValue;

		var docid = $("#document-list .selected").data("pk"); // Use .selected class added by your getDocumentList click handler

		console.log("Saving category change:", { docid: docid, category: categoryToSend });

		if (docid != null && categoryToSend != "") {
			$.ajax({
				type: "POST",
				// --- UPDATE PHP URL ---
				url: "update_document_categories.php", // Point to php folder
				data: { category: categoryToSend, docid: docid },
				// Expect JSON response from PHP for consistency
				dataType: "json",
			})
				.done(function (response) { // Use done()
					if (response && response.success) {
						if (typeof showNotification === "function") {
							showNotification("success", "Successfully updated the document's category.");
						} else {
							alert("Category updated successfully.");
						}
						// Refresh main category filter AND the list itself (to show updated category if visible)
						getCategories(); // Refresh main filter list
						var currentFilter = $("#documentCategories").val(); // Get current filter
						getDocumentList(docid, currentFilter); // Refresh list, re-selecting the current doc

						// Update category dropdown in the details panel if needed
						getCategories(true, "change", categoryToSend); // Update modal dropdown
						// Also update the category displayed in the main panel if you show it there

					} else {
						var message = (response && response.message) ? response.message : "Updating category failed.";
						if (typeof showNotification === "function") {
							showNotification("error", message);
						} else {
							alert("Error: " + message);
						}
						console.error("Category update failed:", message);
					}
				})
				.fail(function (jqXHR, textStatus, errorThrown) {
					console.error("AJAX Error:", {
						status: jqXHR.status,
						responseText: jqXHR.responseText,
						statusText: jqXHR.statusText
					});
					try {
						// Try to parse the response anyway
						var response = JSON.parse(jqXHR.responseText);
						if (response.message) {
							alert("Error: " + response.message);
						}
					} catch (e) {
						// If not JSON, show the raw response
						alert("Server error: " + jqXHR.responseText);
					}
				})
				.always(function () { // Use always()
					// Hide modal regardless of success/failure
					$("#changeCategoryModal").modal("hide");
				});
		} else {
			if (typeof showNotification === "function") {
				showNotification("warning", "Please select a document and ensure a category is chosen or entered.");
			} else {
				alert("Please select a document and category.");
			}
		}
	});

	// --- Keep Initial Data Loads ---
	getCategories();
	getDocumentList(); // Load all initially

	// --- Update Download Button ---
	$("#downloadDocument").click(function () {
		// We need the actual filepath from the database (stored when getDocument runs)
		var filePath = $("#document-panel").data("filepath"); // Retrieve path stored on panel
		var originalFilename = $("#document-filename").text(); // Get original filename for suggestion

		if (filePath) {
			var downloadLink = $("#downloadLink");
			downloadLink.attr("href", filePath);
			downloadLink.attr("download", originalFilename || "download"); // Suggest original filename
			downloadLink[0].click(); // Trigger the hidden link
			console.log("Attempting download:", filePath);

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

	// --- Keep Delete Button Logic ---
	$("#deleteDocument").click(function () {
		var selectedDoc = $("#document-list .selected");
		var docId = selectedDoc.data("pk");
		// We need the STORED filename (or filepath) to delete the correct file on the server
		var filePathToDelete = $("#document-panel").data("filepath"); // Get path stored by getDocument

		if (!docId || !filePathToDelete) {
			if (typeof showNotification === "function") {
				showNotification("warning", "Please select a document to delete.");
			} else {
				alert("Please select a document.");
			}
			return; // Stop if nothing is selected or path is missing
		}

		// --- Confirmation Dialog --- (Recommended)
		if (!confirm("Are you sure you want to permanently delete the document '" + $("#document-title").text() + "'?")) {
			return; // Stop if user cancels
		}

		$.ajax({
			type: "POST",
			// --- UPDATE PHP URL ---
			url: "delete_document.php", // Point to php folder
			// Send ID and the FILEPATH to delete
			data: { id: docId, filepath: filePathToDelete },
			dataType: "json", // Expect JSON response
		})
			.done(function (response) {
				if (response && response.success) {
					selectedDoc.remove(); // Remove from list
					resetDocumentPanel(); // Clear details panel
					if (typeof showNotification === "function") {
						showNotification("success", "Successfully deleted the document.");
					} else {
						alert("Document deleted.");
					}
				} else {
					var message = (response && response.message) ? response.message : "Failed to delete document.";
					if (typeof showNotification === "function") {
						showNotification("error", message);
					} else {
						alert("Error: " + message);
					}
					console.error("Delete failed:", message);
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				if (typeof showNotification === "function") {
					showNotification("error", "AJAX Error deleting document.");
				} else {
					alert("AJAX Error deleting document.");
				}
				console.error("AJAX Error deleting document:", textStatus, errorThrown);
			});
	});


	// --- Keep View Stats Modal Logic ---
	$("#viewStatsModal").on("show.bs.modal", function () {
		var docId = $("#document-list .selected").data("pk");
		if (!docId) {
			// Prevent modal opening or show message if no doc selected
			if (typeof showNotification === "function") {
				showNotification("warning", "Please select a document first.");
			} else {
				alert("Please select a document first.");
			}
			return false; // Stop modal from showing
		}

		// Clear previous stats
		$("#viewedList, #notviewedList").empty().html("<li>Loading...</li>");

		$.ajax({
			type: "GET",
			// --- UPDATE PHP URL ---
			url: "get_document_views.php", // Point to php folder
			data: { id: docId },
			dataType: "json", // Expect JSON
		})
			.done(function (response) {
				$("#viewedList, #notviewedList").empty(); // Clear loading message

				if (response && response.success && response.data) {
					var stats = response.data; // Assuming PHP returns {success: true, data: {viewed: [...], notviewed: [...]}}

					if (stats.viewed && stats.viewed.length > 0) {
						for (var i = 0; i < stats.viewed.length; i++) {
							// Sanitize output
							$("#viewedList").append("<li>" + htmlspecialchars(stats.viewed[i]) + "</li>");
						}
					} else {
						$("#viewedList").append("<li>None</li>");
					}

					if (stats.notviewed && stats.notviewed.length > 0) {
						for (var i = 0; i < stats.notviewed.length; i++) {
							// Sanitize output
							$("#notviewedList").append("<li>" + htmlspecialchars(stats.notviewed[i]) + "</li>");
						}
					} else {
						$("#notviewedList").append("<li>None</li>");
					}
				} else {
					var message = (response && response.message) ? response.message : "Failed to load view stats.";
					$("#viewedList").append('<li>Error loading data.</li>');
					$("#notviewedList").append('<li>Error loading data.</li>');
					console.error("Error loading view stats:", message);
				}
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				$("#viewedList, #notviewedList").empty();
				$("#viewedList").append('<li>AJAX Error</li>');
				$("#notviewedList").append('<li>AJAX Error</li>');
				console.error("AJAX Error getting view stats:", textStatus, errorThrown);
			});
	});

	// --- Keep Window Resize ---
	// Call it once on load to set initial size
	$(window).resize();

}); // --- END document ready ---


// ==============================================================
// ===== FUNCTION DEFINITIONS (Keep but potentially adapt) =====
// ==============================================================

// --- Refactored getDocumentList --- (Example - Apply similar changes to others)
function getDocumentList(idToSelect, category) {
	$("#document-list").html('<p class="text-info">Loading documents...</p>');
	resetDocumentPanel();
	var ajaxData = {};
	// Pass category name; PHP script needs to handle 'AllCategories' or absence of parameter
	if (category && category !== "AllCategories") {
		ajaxData.category = category;
	}

	$.ajax({
		type: "GET",
		// --- UPDATE PHP URL ---
		url: "get_documents.php", // Point to php folder
		data: ajaxData,
		dataType: "json"
	})
		.done(function (response) {
			$("#document-list").empty();
			// Expect PHP to return {success: true, data: [documents]}
			if (response && response.success && response.data) {
				var documents = response.data;
				if (documents.length > 0) {
					var listHtml = '<ul class="list-group">';
					for (var i = 0; i < documents.length; i++) {
						var doc = documents[i];
						// Use original_filename from DB
						// Store necessary data attributes (use doc-id for clarity)
						listHtml += '<li class="list-group-item document-item" ' +
							'data-doc-id="' + doc.id + '" ' +
							// Store other data if needed by getDocument, but filepath will be fetched separately
							// 'data-type="' + htmlspecialchars(doc.mime_type) + '" ' + // Use mime_type if available
							// 'data-category="' + htmlspecialchars(doc.category_name) + '" ' + // Use category name if available
							// 'data-timestamp="' + doc.upload_date + '" ' + // Use upload_date if available
							'>' +
							'<a href="#" class="document-link">' +
							'<i class="fa fa-file-o"></i> ' +
							htmlspecialchars(doc.original_filename) + // Display original name
							'</a>' +
							// Optional badge for category or date
							'<span class="badge pull-right">' + formatDate(doc.upload_date) + '</span>' + // Example: Show upload date
							'</li>';
					}
					listHtml += '</ul>';
					$("#document-list").html(listHtml);

					// Select specific document if ID provided
					if (idToSelect !== undefined && idToSelect != null) {
						// Ensure the click handler is attached before triggering
						// It might be better to call getDocument directly here if needed immediately
						$('#document-list .document-item[data-doc-id="' + idToSelect + '"]').addClass("selected");
						getDocument(idToSelect); // Fetch details for the newly uploaded/selected item
					}

				} else {
					$("#document-list").append("<div class='alert alert-info'>There are no documents found.</div>");
				}
			} else {
				var message = (response && response.message) ? response.message : "Failed to parse document list.";
				$("#document-list").html('<div class="alert alert-danger">' + htmlspecialchars(message) + '</div>');
				console.error("Error fetching documents:", message);
			}
		})
		// In your AJAX calls
		.fail(function (jqXHR) {
			try {
				var errorResponse = JSON.parse(jqXHR.responseText);
				alert(errorResponse.error || 'Unknown error');
			} catch (e) {
				alert('Server error: ' + jqXHR.responseText);
			}
		})
}

// --- Refactored getDocument ---
function getDocument(id) {
	if (!id) { return; }
	console.log("Fetching details for document ID:", id);

	// Show loading state & Reset parts of panel
	resetDocumentPanel(); // Reset first
	$("#document-panel").show(); // Show panel structure immediately
	$("#document-title").text("Loading...");
	$("#document-content").html('<p class="text-center text-info"><i class="fa fa-spinner fa-spin"></i> Loading details...</p>');
	$("#document-menu").hide(); // Keep menu hidden until data loads

	$.ajax({
		type: "GET",
		// --- UPDATE PHP URL ---
		url: "get_document.php", // Point to php folder
		data: { id: id },
		dataType: "json"
	})
		.done(function (response) {
			// Expect PHP to return {success: true, data: {document_details}}
			if (response && response.id) {

				var doc = response;
				console.log("Document details loaded:", doc);

				// Use original_filename and upload_date from DB
				$("#document-title").text(htmlspecialchars(doc.original_filename));
				$("#document-filename").text(htmlspecialchars(doc.original_filename));
				$("#document-creation").text(formatDate(doc.upload_date)); // Use formatter

				// --- Store essential data on the panel ---
				// **MODIFICATION:** Use the filepath returned from DB
				$("#document-panel").data("filepath", htmlspecialchars(doc.filepath));
				$("#document-panel").data("current-doc-id", doc.id);

				// --- Update 'Change Category' modal dropdown ---
				getCategories(true, "change", doc.category_id); // Pass category_id

				// --- Generate Preview ---
				var previewHtml = generateDocumentPreview(doc);
				$("#document-content").html(previewHtml);

				// Show the menu now that data is loaded
				$("#document-menu").show();

			} else {
				var message = response?.message || "Failed to load document details or invalid data received.";
				console.error("Error loading document details:", message, response);
				resetDocumentPanel(); // Reset panel on error
				$("#document-title").text("Error Loading Details");
				showNotification("error", message);
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			resetDocumentPanel();
			$("#document-title").text("AJAX Error");
			console.error("AJAX Error fetching document:", textStatus, errorThrown, jqXHR.responseText);
			showNotification("error", "AJAX Error loading document details.");
		});
}

// --- resetDocumentPanel function (Make sure this exists) ---
function resetDocumentPanel() {
	$("#document-content").html('<p class="text-muted text-center" style="padding-top: 50px;">Select a document from the list to view details.</p>');
	$("#document-menu").hide();
	$("#document-title").text("Select a Document");
	$("#document-filename").text("");
	$("#document-creation").text("");
	$("#document-panel").removeData("filepath").removeData("current-doc-id");
	// Hide the panel itself when resetting
	$("#document-panel").hide();
	console.log("Document panel reset and hidden.");
}

// --- Refactored getCategories ---

function getCategories(isModal, type, selectedValue) {
	var targetSelector = "#documentCategories"; // Default selector
	var isModalNew = (isModal && type == "new");
	var isModalChange = (isModal && type == "change");

	if (isModalNew) {
		targetSelector = "#modalCategories";
	} else if (isModalChange) {
		targetSelector = "#changeCategory";
	}

	var $selector = $(targetSelector);
	if ($selector.length === 0 && (isModalNew || isModalChange)) {
		console.warn("Category selector not found:", targetSelector);
		return;
	}

	$selector.prop('disabled', true).html('<option value="">Loading...</option>');

	$.ajax({
		type: "GET",
		url: "get_document_categories.php", // Correct path
		dataType: "json"
	})
		.done(function (response) {
			console.log('Categories loaded for ' + targetSelector + ':', response);
			$selector.prop('disabled', false).empty();

			if (response && response.success && Array.isArray(response.data)) {
				// Add appropriate placeholder/default option
				if (!isModal) { // Main filter
					$selector.append('<option value="AllCategories">All Categories</option>');
				} else if (isModalNew) { // Upload modal
					$selector.append('<option value="">-- Select Existing --</option>'); // Default for upload modal
				} else { // Change modal
					$selector.append('<option value="">-- Select Category --</option>'); // Default for change modal
				}

				// Add categories from response
				response.data.forEach(function (category) {
					var categoryId = (typeof category === 'object' && category !== null) ? category.id : category;
					var categoryName = (typeof category === 'object' && category !== null) ? category.name : category;
					if (categoryId !== undefined && categoryName !== undefined) {
						$selector.append($('<option>', { value: categoryId, text: htmlspecialchars(categoryName) }));
					}
				});

				// --- REMOVED: Add "-- Add New Category --" option ---
				// if (isModal) { $selector.append('<option value="AddCategory">...</option>'); }

				// --- ADDED: Add "-- Add New Category --" ONLY for the 'Change Category' modal ---
				if (isModalChange) {
					$selector.append('<option value="AddCategory">-- Add New Category --</option>');
				}

				// Set selected value if provided
				if (selectedValue !== undefined && selectedValue !== null) {
					$selector.val(selectedValue);
					if ($selector.val() !== selectedValue && $selector.find('option[value="' + selectedValue + '"]').length === 0) {
						console.warn("Selected category value '" + selectedValue + "' not found in dropdown " + targetSelector);
						$selector.val(''); // Reset if value not found
					}
				}

				// --- NO NEED TO TRIGGER CHANGE HANDLER HERE ---
				// The input box visibility is no longer controlled by this dropdown change event.
				// $selector.trigger("change.categoryToggle"); // REMOVE THIS TRIGGER

			} else {
				var message = response?.message || "Failed to parse categories or data is not an array.";
				$selector.html('<option value="">Error Loading</option>');
				console.error("Error loading categories for " + targetSelector + ":", message, response);
			}
		})
		.fail(function (jqXHR, textStatus, errorThrown) {
			$selector.prop('disabled', false).html('<option value="">AJAX Error</option>');
			console.error("AJAX Error loading categories for " + targetSelector + ":", textStatus, errorThrown, jqXHR.responseText);
		});
}

// ==============================  =============================

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

// ================================  =========================

// Function to load categories into dropdown
function loadCategories1() {
	$.ajax({
		url: 'get_document_categories.php',
		type: 'GET',
		dataType: 'json',
		success: function (response) {
			if (response.success && response.data) {
				var $dropdown = $('#modalCategories');
				// Keep first two options (Select and Add New)
				$dropdown.find('option:gt(1)').remove();

				// Add existing categories
				$.each(response.data, function (i, category) {
					$dropdown.append($('<option>', {
						value: category.id,
						text: category.name
					}));
				});
			}
		},
		error: function (xhr, status, error) {
			console.error('Error loading categories:', error);
		}
	});
}
// ===============================   =========================


// --- generateDocumentPreview function (Make sure this exists) ---
// (Using the version from previous examples)
function generateDocumentPreview(doc) {
	// ... (logic to create image, PDF object, or download link based on doc.mime_type and doc.filepath) ...
	var previewHtml = '<p class="text-muted">No preview available.</p>'; // Default
	var safeFilepath = htmlspecialchars(doc.filepath);
	var safeFilename = htmlspecialchars(doc.original_filename);
	var mimeType = doc.mime_type || '';

	try { /* ... image/pdf/text logic ... */
		if (mimeType.startsWith("image/")) {
			previewHtml = "<div class='document-image-preview text-center'><img src='" + safeFilepath + "' style='max-width:100%; max-height: 400px; height:auto;' alt='Preview'/></div>";
		} else if (mimeType === "application/pdf") {
			previewHtml = "<div class='document-pdf-preview' style='height: 500px;'><object data='" + safeFilepath + "#view=FitH' type='application/pdf' width='100%' height='100%'><p>Cannot display PDF. <a href='" + safeFilepath + "' download='" + safeFilename + "'>Download PDF</a></p></object></div>";
		}
	} catch (e) { /* ... */ }
	return previewHtml;
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

function formatDate(dateString) {
	// Implement date formatting as shown previously if not already globally available
	// Requires Moment.js if you kept the original m.format(...) logic
	if (!dateString) return 'N/A';
	try {
		if (typeof moment === 'function') { // Check if Moment.js is loaded
			// Original logic using Moment.js (requires Moment.js library)
			var ts = parseInt(dateString) * 1000; // Assumes timestamp was passed
			// OR if dateString is like 'YYYY-MM-DD HH:MM:SS'
			// var m = moment(dateString + 'Z'); // Treat as UTC if needed
			var m = moment(dateString); // Let moment parse it
			return m.format("MMM Do, YYYY h:mm A"); // Adjusted format
		} else {
			// Fallback using native Date if Moment.js is not available
			var date = new Date(dateString.replace(' ', 'T') + 'Z'); // Treat as UTC
			return date.toLocaleDateString(undefined, {
				year: 'numeric', month: 'short', day: 'numeric',
				hour: '2-digit', minute: '2-digit', hour12: true
			});
		}
	} catch (e) {
		console.warn("Error formatting date:", dateString, e);
		return dateString; // Return original string if formatting fails
	}
}