// In permission-controls.js - UPDATE 9/9/2025the document ready function
$(document).ready(function () {
    // Apply read-only restrictions if applicable
    if (userPermissions.isReadOnly) {
        console.log('Read-only user detected, applying restrictions');
        applyReadOnlyRestrictions();
        addReadOnlyBadge();
        setupReadOnlyAlerts();
        preventAccidentalActions();
        setupTabSpecificAlerts();
    }
});

function applyReadOnlyRestrictions() {
    // Only apply restrictions on daily_manager page
    if (userPermissions.currentPage !== 'daily_manager') {
        console.log('Read-only restrictions not applied on this page:', userPermissions.currentPage);
        return;
    }

    if (!userPermissions.isReadOnly) {
        console.log('User has edit permissions on daily_manager');
        return;
    }

    console.log('Applying read-only restrictions for user role:', userPermissions.userRole);

    // Disable ALL interactive elements
    $('button, input, select, textarea, .btn, a.btn').not('[data-always-enabled]').each(function () {
        // Skip elements that are already disabled by default or have specific classes
        if ($(this).hasClass('no-readonly-disable') || $(this).data('no-disable')) {
            return;
        }
        $(this).prop('disabled', true).attr('title', 'Read-only mode: This action is disabled');
    });

    // Specifically disable AI Assistant button
    $('#ai-assistant-btn').prop('disabled', true).attr('title', 'Read-only mode: AI Assistant is disabled');

    // Remove click handlers from tabs to prevent navigation
    $('.tab').off('click').css('cursor', 'not-allowed');

    // Disable modal triggers
    $('[data-toggle="modal"]').off('click').prop('disabled', true);

    // Disable datepickers and other UI widgets
    $('.datepicker').prop('disabled', true);

    // Add visual class to body
    $('body').addClass('read-only-mode');

    // Prevent form submissions
    $('form').on('submit', function (e) {
        if (userPermissions.isReadOnly) {
            e.preventDefault();
            showReadOnlyMessage();
            return false;
        }
    });

    // Prevent click events on actionable elements
    $('.action-btn, .edit-btn, .delete-btn, .toggle-btn').on('click', function (e) {
        if (userPermissions.isReadOnly) {
            e.preventDefault();
            e.stopPropagation();
            showReadOnlyMessage();
            return false;
        }
    });
}

function addReadOnlyBadge() {
    // Remove existing badge if any
    $('.read-only-badge').remove();

    // Create and add new badge
    const badge = $('<div class="read-only-badge">📖 Read-Only Mode</div>');
    $('body').append(badge);

    // Make badge draggable for user convenience
    badge.draggable({
        containment: "body",
        scroll: false
    });
}

function showReadOnlyMessage() {
    // You can use your existing notification system or a simple alert
    alert('Read-Only Mode: This action is not permitted. Please contact an administrator if you need edit permissions.');
}

// permission-controls.js - ADD these functions
function showReadOnlyAlert(action = "perform this action") {
    // Use your existing notification system or create a simple alert
    const alertMessage = `Read-Only Mode: Pilots are not authorized to ${action}. Please contact an administrator if you need editing permissions.`;

    // Option 1: Use your existing notification system
    if (typeof showNotification === 'function') {
        showNotification($('#manage-pilots-alert-container'), 'warning', alertMessage);
    }
    // Option 2: Use browser alert (fallback)
    else {
        alert(alertMessage);
    }

    // Option 3: Use Noty.js if available (you seem to have it included)
    if (typeof Noty !== 'undefined') {
        new Noty({
            type: 'warning',
            text: alertMessage,
            timeout: 5000,
            progressBar: true,
            theme: 'mint'
        }).show();
    }
}

function setupReadOnlyAlerts() {
    if (!userPermissions.isReadOnly) return;

    console.log('Setting up read-only alerts for pilot user');

    // Prevent all form submissions
    $('form').on('submit', function (e) {
        if (userPermissions.isReadOnly) {
            e.preventDefault();
            showReadOnlyAlert("submit forms");
            return false;
        }
    });

    // Prevent button clicks (edit, create, delete actions)
    $('.btn:not([data-always-enabled])').on('click', function (e) {
        if (userPermissions.isReadOnly) {
            const $btn = $(this);
            const buttonText = $btn.text().trim() || $btn.attr('title') || 'perform this action';

            // Allow some safe buttons (navigation, tabs, etc.)
            const safeButtons = [
                'tab', 'navigation', 'filter', 'search', 'refresh',
                'view', 'print', 'download', 'export', 'collapse', 'expand'
            ];

            const isSafe = safeButtons.some(safe =>
                $btn.attr('class')?.includes(safe) ||
                $btn.attr('id')?.includes(safe) ||
                buttonText.toLowerCase().includes(safe)
            );

            if (!isSafe) {
                e.preventDefault();
                e.stopPropagation();
                showReadOnlyAlert(buttonText.toLowerCase());
                return false;
            }
        }
    });

    // Specific prevention for common action buttons
    const actionSelectors = [
        '.edit-btn', '.create-btn', '.delete-btn', '.save-btn',
        '.update-btn', '.toggle-btn', '.add-btn', '.remove-btn',
        '[data-toggle="modal"]', '[data-target]'
    ];

    $(actionSelectors.join(', ')).on('click', function (e) {
        if (userPermissions.isReadOnly) {
            e.preventDefault();
            e.stopPropagation();

            const $element = $(this);
            const actionType = $element.data('toggle') === 'modal' ? 'open modals' : 'perform this action';
            showReadOnlyAlert(actionType);

            return false;
        }
    });
}

// Add to permission-controls.js
function setupTabSpecificAlerts() {
    if (!userPermissions.isReadOnly) return;

    // Schedule Tab
    $('.pilot-select').on('change', function (e) {
        e.preventDefault();
        showReadOnlyAlert("change pilot assignments");
    });

    // Queue Tab
    $('#queue-check-all, .queue-checkbox').on('change', function (e) {
        e.preventDefault();
        showReadOnlyAlert("select notifications for sending");
    });

    $('#sendPreparedNotiBtn').on('click', function (e) {
        e.preventDefault();
        showReadOnlyAlert("send notifications");
    });

    // Max Times Tab
    $('.limit-input').on('change keypress', function (e) {
        e.preventDefault();
        showReadOnlyAlert("modify time limits");
    });

    $('#saveMaxTimesBtn').on('click', function (e) {
        e.preventDefault();
        showReadOnlyAlert("save time limit changes");
    });

    // Create Pilot Tab
    $('#createNewPilotForm').on('submit', function (e) {
        e.preventDefault();
        showReadOnlyAlert("create new pilots");
    });

    // Manage Pilots Tab
    $('.edit-pilot-btn, .toggle-pilot-status-btn, .delete-pilot-btn').on('click', function (e) {
        e.preventDefault();
        showReadOnlyAlert("manage pilot accounts");
    });

    // Add more tab-specific handlers as needed...
}

// Call this in your document ready
$(document).ready(function () {
    if (userPermissions.isReadOnly) {
        setupTabSpecificAlerts();
    }
});

// Add extra protection against accidental actions
function preventAccidentalActions1() {
    if (!userPermissions.isReadOnly) return;

    // Prevent right-click context menu
    $(document).on('contextmenu', function (e) {
        if ($(e.target).closest('.read-only-mode').length) {
            e.preventDefault();
            showReadOnlyAlert("use context menus");
            return false;
        }
    });

    // Prevent drag-and-drop
    $(document).on('dragstart drop', function (e) {
        e.preventDefault();
        return false;
    });

    // Prevent text selection (optional)
    $('body').css({
        'user-select': 'none',
        '-webkit-user-select': 'none',
        '-moz-user-select': 'none',
        '-ms-user-select': 'none'
    });
}

// More targeted text selection prevention
function preventAccidentalActions() {
    if (!userPermissions.isReadOnly) return;

    // Prevent right-click context menu on interactive elements only
    $('.btn, input, select, textarea, form').on('contextmenu', function (e) {
        e.preventDefault();
        showReadOnlyAlert("use context menus");
        return false;
    });

    // Prevent drag-and-drop on interactive elements
    $('.btn, input, select, textarea').on('dragstart drop', function (e) {
        e.preventDefault();
        showReadOnlyAlert("drag and drop items");
        return false;
    });

    // Only prevent text selection on buttons and forms, not entire page
    $('.btn, form, .panel-heading').css({
        'user-select': 'none',
        '-webkit-user-select': 'none',
        '-moz-user-select': 'none',
        '-ms-user-select': 'none'
    });

    console.log('Accidental actions prevention enabled for read-only user');
}

// Optional: Export functions for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        applyReadOnlyRestrictions,
        addReadOnlyBadge,
        showReadOnlyMessage,
        showReadOnlyAlert,
        setupReadOnlyAlerts,
        preventAccidentalActions,
        setupTabSpecificAlerts
    };
}