// ==========================================================================
// === stats-reports.js - Modal and Report Generation Handlers ===
// ==========================================================================

function initializeReportModals() {

    // --- Modal Openers ---
    $("#openViewLogbookModalBtn").on("click", (e) => { e.preventDefault(); $('#viewLogbookModal').modal('show'); });
    $("#openMonthlyReportModalBtn").on("click", (e) => { e.preventDefault(); $('#monthlyReportModal').modal('show'); });
    $("#openPrintExperienceModalBtn").on("click", (e) => { e.preventDefault(); $('#printExperienceModal').modal('show'); });

    // --- Monthly Report PDF Generation ---
    $("#launchLog").click(function () {
        const $button = $(this), month = $("#reportMonth").val(), year = $("#reportYear").val();
        if (!month || !year || year.length !== 4) { alert("Please select a valid month and year."); return; }

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');
        let startDateYMD, endDateYMD;
        if (typeof moment === 'function') {
            const firstDay = moment(`${year}-${month}-01`, "YYYY-MM-DD");
            startDateYMD = firstDay.format('YYYY-MM-DD');
            endDateYMD = firstDay.endOf('month').format('YYYY-MM-DD');
        } else {
            const firstDay = new Date(year, parseInt(month) - 1, 1);
            const lastDay = new Date(year, parseInt(month), 0);
            startDateYMD = formatDate(firstDay, 'YYYY-MM-DD');
            endDateYMD = formatDate(lastDay, 'YYYY-MM-DD');
        }

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
                        $('#viewLogModal').modal('hide');
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
    $("#printLog").click(function () {
        const $button = $(this);
        const startDateYMD = formatDate($("#logbookStartDate").datepicker('getDate'), 'YYYY-MM-DD');
        const endDateYMD = formatDate($("#logbookEndDate").datepicker('getDate'), 'YYYY-MM-DD');

        if (!startDateYMD || !endDateYMD) { alert("Please select a valid start and end date."); return; }
        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Generating...');
        $.ajax({
            url: 'stats_generate_logbook_report.php', type: 'POST',
            data: { print_type: 'logbook', start_date: startDateYMD, end_date: endDateYMD },
            dataType: 'json',
            success: function (response) {
                if (response.success && response.pdf_url) {
                    window.open(response.pdf_url, '_blank');
                    $('#viewLogbookModal').modal('hide');
                } else {
                    alert(response.message || 'Failed to generate logbook PDF.');
                }
            },
            error: () => alert("AJAX error generating logbook PDF."),
            complete: () => $button.prop('disabled', false).html('View Log as PDF')
        });
    });

    // --- Experience Report Print ---
    $("#confirmPrintExperienceBtn").on("click", function () {
        window.open('stats_print_experience.php', '_blank');
        $('#printExperienceModal').modal('hide');
    });

    // --- Reset Monthly Report Modal on Close ---
    $('#viewLogModal').on('hidden.bs.modal', function () {
        $("#launchLog").show().prop('disabled', false).html('View Report as PDF');
        $(this).find('.modal-footer .generated-pdf-link').remove();
    });
}