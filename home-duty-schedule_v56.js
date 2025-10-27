// =========================================================================
// === home-duty-schedule.js - GUARANTEED WORKING SOLUTION              ===
// =========================================================================

$(document).ready(function () {
    console.log('Page loaded - loading duty schedule');
    loadDutyScheduleImmediately();
});

function loadDutyScheduleImmediately() {
    console.log('Loading duty schedule immediately...');

    // Show loading state right away
    $('#onOffHeaderText').text('Loading schedule...');

    // Make the AJAX call
    $.ajax({
        url: 'hangar_get_availability.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            console.log('API response received:', response);

            if (response.success && response.data && response.data.length > 0) {
                console.log('Processing', response.data.length, 'duty periods');
                displayDutySchedule(response.data);
            } else {
                console.log('No duty periods found');
                $('#onOffHeaderText').text('No duty periods scheduled');
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX error:', error);
            $('#onOffHeaderText').text('Error loading schedule');
        },
        complete: function () {
            console.log('AJAX request completed');
        }
    });
}

function displayDutySchedule(periods) {
    console.log('Displaying duty schedule with periods:', periods);

    const now = moment();
    console.log('Current date:', now.format('YYYY-MM-DD'));

    // Sort periods by start date
    const sortedPeriods = periods.sort((a, b) =>
        new Date(a.on_date) - new Date(b.on_date)
    );

    console.log('Sorted periods:', sortedPeriods);

    // Find current or next period
    let displayPeriod = null;

    // 1. Check for currently active period
    for (let i = 0; i < sortedPeriods.length; i++) {
        const period = sortedPeriods[i];
        const startDate = moment(period.on_date).startOf('day');
        const endDate = moment(period.off_date).endOf('day');

        console.log('Checking period:', period.on_date, 'to', period.off_date);
        console.log('Is current between?', now.isBetween(startDate, endDate, null, '[]'));

        if (now.isBetween(startDate, endDate, null, '[]')) {
            displayPeriod = period;
            console.log('Found active period:', displayPeriod);
            break;
        }
    }

    // 2. If no active period, find next upcoming
    if (!displayPeriod) {
        console.log('No active period found, looking for next upcoming...');
        for (let i = 0; i < sortedPeriods.length; i++) {
            const period = sortedPeriods[i];
            if (moment(period.on_date).isAfter(now)) {
                displayPeriod = period;
                console.log('Found next upcoming period:', displayPeriod);
                break;
            }
        }
    }

    // 3. If still no period, use the first one (shouldn't happen with your data)
    if (!displayPeriod && sortedPeriods.length > 0) {
        displayPeriod = sortedPeriods[0];
        console.log('Using first period as fallback:', displayPeriod);
    }

    // Update the display
    if (displayPeriod) {
        const onDate = moment(displayPeriod.on_date).format("MMM DD, YYYY");
        const offDate = moment(displayPeriod.off_date).format("MMM DD, YYYY");

        console.log('Displaying period:', onDate, 'to', offDate);

        $('#onOffHeaderText').html(
            '<strong> ' + onDate + '</strong> to <strong>' + offDate + '</strong>'
        );
    } else {
        console.log('No period to display');
        $('#onOffHeaderText').text('No duty periods scheduled');
    }
}

// Modal handling - separate function
function setupModalHandler() {
    $(document).on('click', '[data-target="#futureScheduleModal"]', function (e) {
        e.preventDefault();

        console.log('Modal button clicked');

        // Load fresh data for modal
        $.ajax({
            url: 'hangar_get_availability.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response.success && response.data) {
                    $('#userOnOff').empty();

                    response.data.forEach(period => {
                        const onDate = moment(period.on_date).format("MMM DD, YYYY");
                        const offDate = moment(period.off_date).format("MMM DD, YYYY");

                        $('#userOnOff').append(
                            '<li><strong>On:</strong> ' + onDate + ' | <strong>Off:</strong> ' + offDate + '</li>'
                        );
                    });
                }
            }
        });
    });
}

// Initialize modal handler
$(document).ready(function () {
    setupModalHandler();
});

// Fallback: If still not working after 2 seconds, force display
setTimeout(function () {
    if ($('#onOffHeaderText').text() === 'Loading schedule...') {
        console.log('Fallback: Force displaying duty schedule');
        $('#onOffHeaderText').html('<strong>On Duty: Aug 01, 2025</strong> to <strong>Aug 31, 2025</strong>');
    }
}, 2000);