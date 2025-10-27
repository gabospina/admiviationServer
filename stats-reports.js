// ==========================================================================
// === stats-reports.js - Modal and Report Generation Handlers (FLATPICKR) ===
// ==========================================================================

function initializeReportModals() {

    // --- Modal Openers (Unchanged) ---
    $("#openViewLogbookModalBtn").on("click", (e) => { e.preventDefault(); $('#viewLogbookModal').modal('show'); });
    $("#openMonthlyReportModalBtn").on("click", (e) => { e.preventDefault(); $('#monthlyReportModal').modal('show'); });
    $("#openPrintExperienceModalBtn").on("click", (e) => { e.preventDefault(); $('#printExperienceModal').modal('show'); });

    // =========================================================================
    // === THE FIX IS HERE: Set default values when the modal is opened      ===
    // =========================================================================
    $('#monthlyReportModal').on('show.bs.modal', function () {
        // Get the current month (1-12). 'MM' format ensures a leading zero (01, 02, etc.)
        const currentMonth = moment().format('MM');

        // Set the select dropdown to the current month
        $('#reportMonth').val(currentMonth);

        // Set the year input to your desired default value
        $('#reportYear').val('2025');
    });
    // =========================================================================

    // --- Monthly Report PDF Generation (Unchanged) ---
    $("#launchLog").click(function () {
        const $button = $(this), month = $("#reportMonth").val(), year = $("#reportYear").val();
        if (!month || !year || year.length !== 4) { alert("Please select a valid month and year."); return; }

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        const firstDay = moment(`${year}-${month}-01`, "YYYY-MM-DD");
        const startDateYMD = firstDay.format('YYYY-MM-DD');
        const endDateYMD = firstDay.endOf('month').format('YYYY-MM-DD');

        $.ajax({
            url: 'stats_generate_logbook_report.php', type: 'POST',
            data: { print_type: 'monthly_report', start_date: startDateYMD, end_date: endDateYMD },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.pdf_url) {
                    var newWindow = window.open(response.pdf_url, '_blank');
                    if (!newWindow || newWindow.closed) { // Popup blocked
                        $button.hide();
                        $button.siblings('.generated-pdf-link').remove();
                        $('<a></a>').attr({ href: response.pdf_url, target: '_blank' })
                            .addClass('btn btn-info btn-block generated-pdf-link')
                            .text('Popup Blocked - Click Here for Report').appendTo($button.parent());
                    } else {
                        $('#monthlyReportModal').modal('hide'); // Changed ID from viewLogModal
                    }
                } else {
                    alert(response.message || 'Failed to generate report.');
                }
            },
            error: () => alert("AJAX error generating report."),
            complete: () => { if ($button.is(':visible')) $button.prop('disabled', false).html('View Report as PDF'); }
        });
    });

    // --- Logbook Print PDF Generation ---
    // NOTE: The "Logbook Print PDF Generation" code that was here before is now removed,
    // as it is not part of your current implementation.
    // $("#printLog").click(function () {
    //     const $button = $(this);

    //     // =========================================================================
    //     // === THE FIX IS HERE: How to get dates from Flatpickr instances        ===
    //     // =========================================================================
    //     const startDatePicker = document.querySelector("#logbookStartDate")._flatpickr;
    //     const endDatePicker = document.querySelector("#logbookEndDate")._flatpickr;

    //     // Check if the pickers and dates exist before trying to format
    //     const startDate = startDatePicker && startDatePicker.selectedDates.length > 0 ? startDatePicker.selectedDates[0] : null;
    //     const endDate = endDatePicker && endDatePicker.selectedDates.length > 0 ? endDatePicker.selectedDates[0] : null;

    //     if (!startDate || !endDate) {
    //         alert("Please select a valid start and end date.");
    //         return;
    //     }

    //     // Use moment.js (already loaded on your site) to format the date object
    //     const startDateYMD = moment(startDate).format('YYYY-MM-DD');
    //     const endDateYMD = moment(endDate).format('YYYY-MM-DD');
    //     // =========================================================================

    //     $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');

    //     $.ajax({
    //         url: 'stats_generate_logbook_report.php', type: 'POST',
    //         data: { print_type: 'logbook', start_date: startDateYMD, end_date: endDateYMD },
    //         dataType: 'json',
    //         success: function (response) {
    //             if (response.success && response.pdf_url) {
    //                 window.open(response.pdf_url, '_blank');
    //                 $('#viewLogbookModal').modal('hide');
    //             } else {
    //                 alert(response.message || 'Failed to generate logbook PDF.');
    //             }
    //         },
    //         error: () => alert("AJAX error generating logbook PDF."),
    //         complete: () => $button.prop('disabled', false).html('View Log as PDF')
    //     });
    // });

    // --- Experience Report Print (Unchanged) ---
    $("#confirmPrintExperienceBtn").on("click", function () {
        window.open('stats_print_experience.php', '_blank');
        $('#printExperienceModal').modal('hide');
    });

    // --- Reset Monthly Report Modal on Close (Unchanged) ---
    $('#viewLogModal').on('hidden.bs.modal', function () {
        $("#launchLog").show().prop('disabled', false).html('View Report as PDF');
        $(this).find('.modal-footer .generated-pdf-link').remove();
    });
}