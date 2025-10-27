// File: hangar-schedule.js
// Purpose: Fetches and displays the pilot's duty schedule.

function initializeDutySchedule() {
    const $listContainer = $('#hangarDutyScheduleList');
    if ($listContainer.length === 0) return;

    // Show a loading indicator immediately
    $listContainer.html('<li><i class="fa fa-spinner fa-spin"></i> Loading schedule...</li>');

    $.ajax({
        url: 'hangar_get_availability.php',
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            $listContainer.empty(); // Clear the loading indicator

            if (response.success && Array.isArray(response.data) && response.data.length > 0) {
                const now = new Date();

                const futurePeriods = response.data
                    .filter(p => new Date(p.off_date + 'T23:59:59') >= now)
                    .sort((a, b) => new Date(a.on_date) - new Date(b.on_date));

                if (futurePeriods.length > 0) {
                    // =========================================================================
                    // === THE FIX IS HERE: Format the dates using Moment.js               ===
                    // =========================================================================
                    futurePeriods.forEach(p => {
                        const onDate = moment(p.on_date).format("MMM DD, YYYY");
                        const offDate = moment(p.off_date).format("MMM DD, YYYY");
                        $listContainer.append(`<li><strong>On Duty:</strong> ${onDate} | <strong>Off Duty:</strong> ${offDate}</li>`);
                    });
                    // =========================================================================
                } else {
                    $listContainer.html('<li>No future duty periods scheduled.</li>');
                }
            } else {
                $listContainer.html(`<li>${response.error || 'No duty periods scheduled.'}</li>`);
            }
        },
        error: (xhr) => {
            $listContainer.empty().html('<li class="text-danger">Error: Could not load schedule. Please refresh.</li>');
            console.error("Failed to load hangar duty schedule:", xhr.responseText);
        }
    });
}