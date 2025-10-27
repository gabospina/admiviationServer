// File: hangar_clock.js
// Purpose: Manages the user's primary timezone setting and the live clock display.
// REVISED AND INTEGRATED VERSION

// Wrap all logic in a document ready to ensure HTML elements exist
$(document).ready(function() {

    // --- 1. INITIALIZE VARIABLES ---
    
    // The user's timezone is read directly from the dropdown, which was pre-selected by PHP.
    // This is more efficient than a separate AJAX call.
    let currentUserTimezone = $('#clock-timezone').val(); 
    
    // --- 2. DEFINE CORE FUNCTIONS ---

    /**
     * Updates all clock displays on the page (in the header and on the card)
     * based on the currently selected timezone.
     */
    function updateClockDisplay() {
        try {
            const now = new Date();
            
            // Define formatting options
            const timeOnlyFormat = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false };
            const dateTimeFormat = { ...timeOnlyFormat, month: '2-digit', day: '2-digit', year: 'numeric' };

            // Update the small clock in the main site header
            $('#header-local-time').text(now.toLocaleTimeString('en-US', { ...timeOnlyFormat, timeZone: currentUserTimezone }));
            $('#header-utc-time').text(now.toLocaleTimeString('en-US', { ...timeOnlyFormat, timeZone: 'UTC' }));

            // Update the detailed clock display on the hangar page card
            $('#display-local-time').text(now.toLocaleString('en-US', { ...dateTimeFormat, timeZone: currentUserTimezone }).replace(',', ''));
            $('#display-utc-time').text(now.toLocaleString('en-US', { ...dateTimeFormat, timeZone: 'UTC' }).replace(',', ''));
            
            // Update the clock name to be generic and clear
            $('#display-clock-name').text("Your Selected Timezone");

        } catch (error) {
            // This will catch errors if an invalid timezone string is somehow used
            console.error('Clock Display Error:', error);
            $('#display-local-time').text('Invalid Timezone');
        }
    }

    // --- 3. BIND EVENT HANDLERS ---

    /**
     * Handles the click event for the 'Save Clock Settings' button.
     * This now saves the user's primary timezone for their entire account.
     */
    $('#saveClockSettings').on('click', function() {
        const $button = $(this);
        const selectedTimezone = $("#clock-timezone").val();

        // Basic validation
        if (!selectedTimezone) {
            new Noty({ type: 'warning', text: 'Please select a valid timezone.', timeout: 4000 }).show();
            return;
        }
        
        // Provide user feedback while saving
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');

        $.ajax({
            url: base_url + "hangar_clock.php", // Targets our new, secure PHP endpoint
            type: "POST",
            data: { 
                timezone: selectedTimezone 
            },
            dataType: "json",
            success: function(response) {
                if (response.success) {
                    // On success, update the current timezone for the live clock
                    currentUserTimezone = selectedTimezone;
                    // Inform the user
                    new Noty({ type: 'success', text: response.message, timeout: 3000 }).show();
                } else {
                    // Show a detailed error from the server
                    new Noty({ type: 'error', text: 'Error: ' + response.error, timeout: 5000 }).show();
                }
            },
            error: function() {
                // Handle network or fatal server errors
                new Noty({ type: 'error', text: "A critical server error occurred.", timeout: 5000 }).show();
            },
            complete: function() {
                // Re-enable the button regardless of success or failure
                $button.prop('disabled', false).html('<i class="fa fa-save"></i> Save Clock Settings');
            }
        });
    });

    /**
     * When the user changes the dropdown, update the live clock immediately
     * so they can see the effect before saving.
     */
    $('#clock-timezone').on('change', function() {
        currentUserTimezone = $(this).val();
        updateClockDisplay();
    });

    // --- 4. INITIALIZE THE CLOCK ---
    updateClockDisplay(); // Run once on page load to set the initial time
    setInterval(updateClockDisplay, 1000); // Update the clock every second

});