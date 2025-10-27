// daily_manager-reports.js
$(document).ready(function () {
    /**
     * Fetches on-duty pilots via AJAX and generates a simple printable HTML report.
     */
    function printSimpleOnDutyList() {
        showLoadingOverlay("Generating On-Duty Report...");

        $.ajax({
            url: 'daily_manager_onduty_pilots.php',
            type: 'GET',
            dataType: 'json',
            success: function (response) {
                hideLoadingOverlay();

                if (!response.success || !Array.isArray(response.pilots) || response.pilots.length === 0) {
                    alert('No pilots are currently marked as on-duty.');
                    return;
                }

                const companyName = $('#brand').text() || 'Company Schedule';
                const reportDate = new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                let printHtml = `<html><head><title>On-Duty Pilots List</title><style>
                    body { font-family: Arial, sans-serif; font-size: 11pt; }
                    .print-header { text-align: center; margin-bottom: 25px; }
                    .print-header h1 { margin: 0; } .print-header p { margin: 5px 0; color: #555; }
                    table { width: 100%; border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    </style></head><body>
                    <div class="print-header"><h1>${escapeHtml(companyName)}</h1><p>On-Duty Pilots Report (as of ${reportDate})</p></div>
                    <table><thead><tr><th>Pilot Name</th><th>On Duty From</th><th>Off Duty Until</th></tr></thead><tbody>`;

                response.pilots.forEach(pilot => {
                    printHtml += `<tr>
                        <td>${escapeHtml(pilot.pilot_name)}</td>
                        <td>${escapeHtml(pilot.on_date)}</td>
                        <td>${escapeHtml(pilot.off_date)}</td>
                    </tr>`;
                });
                printHtml += `</tbody></table></body></html>`;

                const printWindow = window.open('', '_blank');
                printWindow.document.write(printHtml);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                    printWindow.close();
                }, 500);
            },
            error: function (xhr) {
                hideLoadingOverlay();
                alert('An error occurred while generating the report. Please try again.');
                console.error("Error fetching on-duty pilots:", xhr.responseText);
            }
        });
    }

    // --- Hook up the event listener for the simple print button ---
    $(document).on('click', '#printOnDutyPilotsSimpleBtn', printSimpleOnDutyList);
});