// training-modal-utilities.js
// Contains globally accessible utility functions for managing common modals.

/**
 * Shows the global loading modal used during application startup and year view loading.
 * Assumes a modal with ID #yearLoadingModal exists in the HTML.
 */
function showLoadingModal() {
    const $modal = $("#yearLoadingModal");
    if ($modal.length && typeof $modal.modal === 'function') {
        $modal.modal("show");
    } else {
        console.error("showLoadingModal: Modal with ID #yearLoadingModal not found or Bootstrap JS not loaded.");
    }
}

/**
 * Hides the global loading modal.
 */
function hideLoadingModal() {
    const $modal = $("#yearLoadingModal");
    if ($modal.length && typeof $modal.modal === 'function') {
        $modal.modal("hide");
    }
}

// This prevents the "aria-hidden" console warning when a modal is open.
$(document).ready(function () {
    $('#yearLoadingModal').on('shown.bs.modal', function () {
        $(this).removeAttr('aria-hidden');
    });
});

// This prevents the "aria-hidden" console warning when any modal is open.
$(document).ready(function () {
    // Use a general selector to apply to all modals
    $('.modal').on('shown.bs.modal', function () {
        $(this).removeAttr('aria-hidden');
    });
});