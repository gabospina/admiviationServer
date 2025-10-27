/**
 * scheduleHomeReadOnlyFunctions.js
 */
let allAircraftData = [];

$(document).ready(function () {
    // === FIX FOR HOME.PHP WEEK SELECTOR ===
    const homeWeekPicker = flatpickr("#sched_week", {
        plugins: [new weekSelect()], // Requires the weekSelect plugin
        altInput: true,
        altFormat: "M d, Y", // Your desired display format
        dateFormat: "Y-m-d",
        onClose: function (selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                // This is where you call your function to reload the schedule
                // for the selected week.
                const mondayOfWeek = moment(selectedDates[0]).startOf('isoWeek');
                loadScheduleForWeek(mondayOfWeek.format("YYYY-MM-DD"));
            }
        }
    });

    // Set initial date to today and load schedule
    const today = new Date();
    homeWeekPicker.setDate(today, true); // 'true' triggers the onClose event
    // === END FIX ===

    /**
     * Fetches assignments for a specific week and rebuilds the schedule.
     * @param {string} startDate - The Monday of the week (YYYY-MM-DD).
     * @param {string} endDate - The Sunday of the week (YYYY-MM-DD).
     */
    /**
     * ===================================================================
     * === UPDATE BOTH SCHEDULES                                       ===
     * ===================================================================
     * Fetches assignments for a specific week and rebuilds BOTH the
     * "Full Schedule" and "My Schedule" tables.
     */
    function loadScheduleForWeek(startDate, endDate) {
        // Show a loading indicator if you have one
        console.log("Fetching assignments for the selected week...");

        $.ajax({
            url: "schedule_get_existing_assignments.php",
            dataType: "json",
            data: {
                start_date: startDate,
                end_date: endDate
            }
        })
            .done(function (assignmentsResponse) {
                const assignmentsForWeek = (assignmentsResponse?.success) ? assignmentsResponse.assignments : [];

                // 1. Rebuild the main schedule
                buildFullSchedule(allAircraftData, assignmentsForWeek);

                // 2. ***  Rebuild "My Schedule" with the new data ***
                if (typeof pilotData !== 'undefined' && pilotData.user_id) {
                    buildMySchedule(pilotData, assignmentsForWeek);
                }
            })
            .fail(function (xhr, status, error) {
                console.error("Failed to load assignments for the selected week.", status, error);
            })
            .always(function () {
                // Hide loading indicator
                // e.g., hideLoadingOverlay();
            });
    }

    /**
     * Loads the initial data for the entire page on first load.
     */
    function initializeHomePageSchedules() {
        $.when(
            $.ajax({ url: "schedule_get_aircraft.php", dataType: "json" }),
            $.ajax({ url: "schedule_get_existing_assignments.php", dataType: "json" }),
            $.ajax({ url: "contract_get_all_contracts.php", dataType: "json" })
        )
            .done(function (aircraftResponse, assignmentsResponse, contractsResponse) {
                allAircraftData = (aircraftResponse[0]?.success) ? aircraftResponse[0].schedule : [];
                const initialAssignments = (assignmentsResponse[0]?.success) ? assignmentsResponse[0].assignments : [];
                const contractsData = (contractsResponse[0]?.success) ? contractsResponse[0].contracts : [];

                // Set the datepicker to today, which will trigger onSelect for the first time
                $("#sched_week").datepicker('setDate', new Date());

                updateDateHeaders();
                populateContractDropdown(contractsData);
                populateAircraftDropdown(allAircraftData);
                populateContractLegend(contractsData);
                buildFullSchedule(allAircraftData, initialAssignments);

                if (typeof pilotData !== 'undefined' && pilotData.user_id) {
                    buildMySchedule(pilotData, initialAssignments);
                }
            })
            .fail(function (xhr, status, error) {
                console.error("Failed to load critical schedule data.", status, error);
            });
    }

    /**
     * =================================================================
     * === CORRECTED: Skips the 'Aircraft' column to prevent shift ===
     * =================================================================
     */
    function updateDateHeaders(startDate) {
        if (!startDate) {
            const today = new Date();
            const dayOfWeek = today.getDay();
            startDate = new Date(today);
            startDate.setDate(today.getDate() - (dayOfWeek === 0 ? 6 : dayOfWeek - 1));
        }

        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        for (let i = 0; i < 7; i++) {
            const currentDay = new Date(startDate);
            currentDay.setDate(startDate.getDate() + i);
            const dateString = `${monthNames[currentDay.getMonth()]} ${currentDay.getDate()}`;

            // --- THE FIX ---
            // We now use .eq(i + 1) to skip the first column ('Aircraft').
            const fullSchedHeaderCell = $('table.fullsched thead tr th').eq(i + 1);
            if (fullSchedHeaderCell.length) {
                fullSchedHeaderCell.html(`${dayNames[i]}<br><span class="date">${dateString}</span>`);
            }

            // The "My Schedule" table doesn't have an "Aircraft" column, so .eq(i) is correct here.
            const mySchedHeaderCell = $('table.mysched thead tr th').eq(i);
            if (mySchedHeaderCell.length) {
                mySchedHeaderCell.find('.day-name').text(dayNames[i]);
                mySchedHeaderCell.find('.date').text(dateString);
            }
        }
    }

    /**
     * Builds the main schedule grid using the provided data.
     */
    function buildFullSchedule1(aircraftList, allAssignments) {
        const $tbody = $('table.fullsched tbody');
        $tbody.empty();

        if (!aircraftList || aircraftList.length === 0) {
            $tbody.html('<tr><td colspan="8" class="text-center">No aircraft scheduled.</td></tr>');
            return;
        }

        const assignmentsMap = {};
        allAssignments.forEach(a => {
            const key = `${a.registration}-${a.day_index}-${a.position}`;
            assignmentsMap[key] = a.pilot_display_name;
        });

        const aircraftByType = {};
        aircraftList.forEach(craft => {
            if (!aircraftByType[craft.craft_type]) {
                aircraftByType[craft.craft_type] = [];
            }
            aircraftByType[craft.craft_type].push(craft);
        });

        for (const craftType in aircraftByType) {
            // $tbody.append(`<tr class="spacer-above-type"><td colspan="8"></td></tr>`);
            $tbody.append(`
            <tr class="type-header" style="line-height: 2; margin: 0; padding: 0;">
            <th colspan="8" style="padding: 0; margin: 0; font-size: 15px; text-align: center; font-weight: bold; border-top: 2px solid gray; border-bottom: 5px solid gray; background-color: transparent !important; line-height: 2.0; height: 30px;">${craftType}</th>
        </tr>
        `);

            aircraftByType[craftType].forEach(craft => {
                const color = craft.color;
                const headerStyle = `style="padding: 1px 3px; background-color: ${color || '#f2f2f2'}; color: #000; font-weight: bold;"`;
                let rowHtml = `<tr><th ${headerStyle}>${craft.registration}</th>`;

                for (let day = 0; day < 7; day++) {
                    const picName = assignmentsMap[`${craft.registration}-${day}-com`] || '---';
                    const sicName = assignmentsMap[`${craft.registration}-${day}-pil`] || '---';
                    rowHtml += `<td style="padding: 1px 3px;"><div style="margin: 0; line-height: 1.1;">${picName}</div><div style="margin: 0; line-height: 1.1;">${sicName}</div></td>`;
                }
                rowHtml += `</tr>`;
                $tbody.append(rowHtml);
            });
        }
    }

    function buildFullSchedule(aircraftList, allAssignments) {
        const $tbody = $('table.fullsched tbody');
        $tbody.empty();

        if (!aircraftList || aircraftList.length === 0) {
            $tbody.html('<tr><td colspan="8" class="text-center">No aircraft scheduled.</td></tr>');
            return;
        }

        // This part remains the same
        const assignmentsMap = {};
        allAssignments.forEach(a => {
            const key = `${a.registration}-${a.day_index}-${a.position}`;
            assignmentsMap[key] = a.pilot_display_name;
        });

        const aircraftByType = {};
        aircraftList.forEach(craft => {
            (aircraftByType[craft.craft_type] = aircraftByType[craft.craft_type] || []).push(craft);
        });

        for (const craftType in aircraftByType) {
            $tbody.append(`<tr class="type-header"><th colspan="8">${craftType}</th></tr>`);
            aircraftByType[craftType].forEach(craft => {
                const headerStyle = `style="background-color: ${craft.color || '#f2f2f2'};"`;
                let rowHtml = `<tr><th ${headerStyle}>${craft.registration}</th>`;

                for (let day = 0; day < 7; day++) {
                    // ========================================================
                    // === CHANGE #4: Look for 'PIC' and 'SIC' keys         ===
                    // ========================================================
                    const picName = assignmentsMap[`${craft.registration}-${day}-PIC`] || '---';
                    const sicName = assignmentsMap[`${craft.registration}-${day}-SIC`] || '---';
                    rowHtml += `<td><div>${picName}</div><div>${sicName}</div></td>`;
                }
                rowHtml += `</tr>`;
                $tbody.append(rowHtml);
            });
        }
    }

    /**
     * ===================================================================
     * === FINAL & RESILIENT "MY SCHEDULE" FUNCTION                    ===
     * ===================================================================
     * This function is now fully standardized on 'user_id' and includes
     * robust de-duplication to guarantee a clean display.
     * @param {object} currentUser - The pilotData object, which must have a 'user_id' property.
     * @param {Array} allAssignments - The full, clean list of assignments from the new PHP script.
     */

    function buildMySchedule(currentUser, allAssignments) {
        const $myScheduleRow = $('#userSched');
        $myScheduleRow.empty();

        const uniqueAssignments = new Set();
        const myAssignmentsByDay = Array(7).fill(null).map(() => []);

        allAssignments.forEach(assignment => {
            // Check if the assignment belongs to the currently logged-in user.
            if (assignment.user_id && currentUser.user_id && assignment.user_id == currentUser.user_id) {

                const dayIndex = parseInt(assignment.day_index, 10);

                // ====================================================================
                // === THE FIX: Directly use the position from the server data.     ===
                // The PHP script has already converted 'com'/'pil' to 'PIC'/'SIC'.
                // ====================================================================
                const position = assignment.position; // This is the only line that needed to change.

                // Create a unique key for this specific assignment to prevent any possible duplicates.
                const assignmentKey = `${dayIndex}-${assignment.registration}-${position}`;

                if (!uniqueAssignments.has(assignmentKey)) {
                    if (dayIndex >= 0 && dayIndex < 7) {

                        const craftDetails = `${assignment.craft_type || 'N/A'} (${assignment.registration || 'N/A'})`;

                        myAssignmentsByDay[dayIndex].push({
                            position: position, // The 'position' variable is now correct.
                            craft: craftDetails
                        });

                        uniqueAssignments.add(assignmentKey);
                    }
                }
            }
        });

        // This loop builds the final HTML and will now work correctly.
        for (let day = 0; day < 7; day++) {
            const $cell = $('<td style="vertical-align: top; text-align: center;">');
            if (myAssignmentsByDay[day].length > 0) {
                myAssignmentsByDay[day].forEach(item => {
                    $cell.append(`
                    <div style="margin-bottom: 8px;">
                        <strong style="color: black;">${item.craft}</strong><br>
                        <span class="badge" style="background-color: #337ab7;">${item.position}</span>
                    </div>
                `);
                });
            } else {
                $cell.html('<span class="text-muted">Off</span>');
            }
            $myScheduleRow.append($cell);
        }
    }

    function populateContractLegend(contracts) {
        const $legendContainer = $('#legendColors'); // Target the div from your HTML
        $legendContainer.empty(); // Clear old items

        if (contracts && contracts.length > 0) {
            contracts.forEach(contract => {
                // Create the new, structured HTML for each contract item
                const legendItemHtml = `
                <div class="legend-item">
                    <span class="color-box" style="background-color: ${contract.color || '#ccc'};"></span>
                    <span>${contract.contract}</span>
                </div>
            `;
                $legendContainer.append(legendItemHtml);
            });
        }
    }

    function populateContractDropdown(contracts) {
        const $contractSelect = $('#contract-select');
        $contractSelect.find('option:gt(0)').remove();
        if (contracts && contracts.length > 0) {
            contracts.forEach(contract => {
                $contractSelect.append(`<option value="${contract.id}">${contract.contract}</option>`);
            });
        }
    }

    function populateAircraftDropdown(aircraft) {
        const $craftSelect = $('#craft-select');
        $craftSelect.find('option:gt(0)').remove();
        if (aircraft && aircraft.length > 0) {
            aircraft.forEach(ac => {
                $craftSelect.append(`<option value="${ac.registration}">${ac.registration}</option>`);
            });
        }
    }

    // ========================================================
    // === END: Full & My Schedule ===
    // ========================================================

    // ===========================================================
    // === START: INTEGRATED FUNCTIONS FROM HOMEFUNCTIONS.JS   ===
    // ===========================================================

    function initializeThoughtsForm() {
        $('#submitThoughts').on('click', function () {
            const $button = $(this);
            const message = $('#thoughtsMessage').val().trim();
            if (message === '') {
                alert('Please enter a message.');
                return;
            }
            $button.prop('disabled', true).text('Sending...');
            $.ajax({
                url: 'hangar_send_thoughts.php',
                type: 'POST',
                data: { email: $('#thoughtsEmail').val(), name: $('#thoughtsName').val(), message: message },
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        alert('Thank you! Your message has been sent.');
                        $('#thoughtsEmail, #thoughtsName, #thoughtsMessage').val('');
                    } else { alert('Error: ' + (response.error || 'Could not send message.')); }
                },
                error: function () { alert('A network error occurred.'); },
                complete: function () { $button.prop('disabled', false).text('Send Message'); }
            });
        });
    }

    // ========================================================
    // === END: INTEGRATED FUNCTIONS FROM HOMEFUNCTIONS.JS  ===
    // ========================================================

    // --- MODIFIED ---
    $(document).ready(function () {

        initializeThoughtsForm();

        // Re-initialize the datepicker with the onSelect event handler
        $("#sched_week").datepicker({
            showWeek: true,
            firstDay: 1, // Monday
            dateFormat: 'yy-mm-dd', // Use ISO format for consistency

            // --- THIS IS THE CRITICAL ADDITION ---
            onSelect: function (dateText) {
                // Calculate the Monday and Sunday of the selected week
                const selectedDate = new Date(dateText + 'T12:00:00Z');
                const dayOfWeek = selectedDate.getUTCDay(); // 0=Sun, 1=Mon
                const diff = selectedDate.getUTCDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Adjust for Sunday
                const monday = new Date(selectedDate.setUTCDate(diff));
                const sunday = new Date(monday);
                sunday.setUTCDate(monday.getUTCDate() + 6);

                // Format dates as YYYY-MM-DD strings
                const startDate = monday.toISOString().split('T')[0];
                const endDate = sunday.toISOString().split('T')[0];

                console.log(`New week selected. Fetching data for: ${startDate} to ${endDate}`);

                // Update the visual date headers on the page
                updateDateHeaders(monday);

                // Call the function to reload the schedule for the new week
                loadScheduleForWeek(startDate, endDate);
            }
        });

        // Wire up the buttons (this remains the same)
        $('#printScheduleBtn').on('click', function (e) {
            e.preventDefault();
            printSchedule();
        });

        $('#downloadScheduleBtn').on('click', function (e) {
            e.preventDefault();
            downloadSchedulePdf();
        });

        // Main function to load all initial data for the CURRENT week
        initializeHomePageSchedules();
    });

    /**
     * ===================================================================
     * === FINAL VERSION with Correct Legend Alignment for Printing ====
     * ===================================================================
     * This function rebuilds the legend's HTML in memory for perfect
     * alignment in the pop-up print window.
     */
    function printSchedule() {
        // --- 1. PREPARATION (Finding elements) ---
        const printableArea = document.getElementById('printable-area');
        const companyNameElement = document.querySelector('#brand');
        const contractLegendElement = document.getElementById('contractLegend');

        if (!printableArea || !companyNameElement || !contractLegendElement) {
            alert("Print Error: A required element could not be found.");
            return;
        }

        const companyName = companyNameElement.innerText;
        const scheduleContent = printableArea.innerHTML;

        // --- 2. *** THE FIX: MANIPULATE THE LEGEND'S HTML IN MEMORY *** ---
        console.log("Rebuilding legend HTML for printing...");
        const legendClone = contractLegendElement.cloneNode(true);

        // Remove the buttons from the clone so they don't appear in the printout
        if (legendClone.querySelector('.action-buttons-container')) {
            legendClone.querySelector('.action-buttons-container').remove();
        }

        // Extract the title and items' HTML from the clone
        const titleText = legendClone.querySelector('h4').textContent;
        const itemsHtml = legendClone.querySelector('#legendColors').innerHTML;

        // Build the NEW, CORRECT HTML structure as a string
        const rebuiltLegendHtml = `
        <div class="legend-wrapper">
            <div class="legend-title">${titleText}</div>
            <div class="legend-items-container">${itemsHtml}</div>
        </div>
    `;

        // --- 3. *** THE FIX: DEFINE THE CSS NEEDED FOR ALIGNMENT *** ---
        const printStyles = `
        <style>
            /* CSS for aligning the contract legend */
            .legend-wrapper {
                display: inline-flex;
                flex-direction: column;
                align-items: flex-start;
                font-size: 9pt; /* Adjust size as needed for printing */
            }
            .legend-title {
                font-weight: bold;
                margin-bottom: 4px;
            }
            .legend-items-container {
                display: flex;
                flex-wrap: wrap;
                gap: 2px 10px;
            }
            .legend-item {
                display: flex;
                align-items: center;
                white-space: nowrap; /* Prevents long contract names from wrapping */
            }
            .color-box {
                width: 12px;
                height: 12px;
                margin-right: 5px;
                border: 1px solid #000;
                flex-shrink: 0;
            }
        </style>
    `;

        // --- 4. CONSTRUCT THE FINAL HEADER WITH THE REBUILT LEGEND ---
        const printHeaderHtml = `
        <table style="width: 100%; border-bottom: 2px solid #ccc; margin-bottom: 15px;">
            <tr>
                <td style="text-align: left; vertical-align: top;"><h1 style="margin: 0; font-size: 22px;">${companyName}</h1></td>
                <td style="text-align: right; vertical-align: top;">${rebuiltLegendHtml}</td>
            </tr>
        </table>`;

        // --- 5. OPEN THE PRINT WINDOW AND INJECT ALL CONTENT ---
        const printWindow = window.open('', '_blank', 'height=700,width=1000');
        if (!printWindow) {
            alert('Please allow pop-ups for this website to print.');
            return;
        }

        // Inject our new styles along with the link to the external print.css
        printWindow.document.write(`
        <html>
            <head>
                <title>Print Schedule</title>
                ${printStyles} 
                <link rel="stylesheet" href="css/print.css" type="text/css" />
            </head>
            <body>
                ${printHeaderHtml}
                ${scheduleContent}
            </body>
        </html>
    `);

        printWindow.document.close();

        // --- 6. TRIGGER THE PRINT DIALOG (Unchanged) ---
        printWindow.onload = function () {
            setTimeout(function () {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 500);
        };
    }

    /**
     * ===================================================================
     * === jsPDF + html2canvas & Legend Alignment ==
     * ===================================================================
     * This function manually converts the HTML to an image and places it
     * on a PDF to guarantee no top margin and a single-page fit.
     */
    function downloadSchedulePdf() {
        console.log("Starting PDF generation with jsPDF + html2canvas and legend fix...");

        // --- 1. PREPARATION ---
        const printableArea = document.getElementById('printable-area');
        const companyNameElement = document.querySelector('#brand');
        const contractLegendElement = document.getElementById('contractLegend');

        if (!printableArea || !companyNameElement || !contractLegendElement) {
            alert("PDF Generation Error: A required element could not be found.");
            return;
        }

        const companyName = companyNameElement.innerText;
        const scheduleContent = printableArea.innerHTML;

        // --- 2. *** THE FIX: MANIPULATE THE LEGEND'S HTML IN MEMORY *** ---
        const legendClone = contractLegendElement.cloneNode(true);

        // Extract the title text and the items' HTML from the clone
        const titleText = legendClone.querySelector('h4').textContent;
        const itemsHtml = legendClone.querySelector('#legendColors').innerHTML;

        // Build the NEW, CORRECT HTML structure as a string
        const legendHtml = `
        <div class="legend-wrapper">
            <div class="legend-title">${titleText}</div>
            <div class="legend-items-container">${itemsHtml}</div>
        </div>
    `;

        // --- 3. CREATE A TEMPORARY, OFF-SCREEN CONTAINER FOR A CLEAN SCREENSHOT ---
        const pdfContainer = document.createElement('div');
        pdfContainer.style.position = 'absolute';
        pdfContainer.style.left = '-9999px';
        pdfContainer.style.width = '1200px';

        // --- 4. PREPARE PDF-SPECIFIC HTML AND STYLES ---
        const pdfStyles = `
        <style>
            html, body { margin: 0 !important; padding: 0 !important; }
            #pdf-content { padding: 10px; }
            * { box-sizing: border-box; }
            body { font-family: Arial, sans-serif; font-size: 7pt; }
            h1 { font-size: 14px !important; margin: 0 !important; }
            .fullsched { border-collapse: collapse !important; width: 100%; }
            .fullsched th, .fullsched td {
                padding: 1px 2px !important; line-height: 1.05 !important; vertical-align: middle !important;
            }
            tr.type-header > th {
                border-top: 1px solid #ccc !important; border-bottom: 1px solid #ccc !important;
                font-size: 8pt !important; padding: 2px 0 !important;
            }

            /* --- NEW, CORRECTED CSS FOR THE LEGEND --- */
            .legend-wrapper {
                display: inline-flex; flex-direction: column; align-items: flex-start;
            }
            .legend-title {
                font-weight: bold; margin-bottom: 4px; font-size: 7.5pt !important;
            }
            .legend-items-container {
                display: flex; flex-wrap: wrap; gap: 2px 10px;
            }
            .legend-item {
                display: flex; align-items: center;
            }
            .color-box {
                width: 10px; height: 10px; margin-right: 4px;
                border: 1px solid #666; flex-shrink: 0;
            }
        </style>
    `;

        // --- 5. INJECT CONTENT AND STYLES INTO THE TEMPORARY CONTAINER ---
        pdfContainer.innerHTML = `
        ${pdfStyles}
        <div id="pdf-content">
            <table style="width: 100%; border-bottom: 1px solid #666; margin-bottom: 5px;">
                <tr>
                    <td style="text-align: left; vertical-align: top;"><h1>${companyName}</h1></td>
                    <!-- NOTE: We use text-align: right on the container cell -->
                    <td style="text-align: right; vertical-align: top;">${legendHtml}</td>
                </tr>
            </table>
            ${scheduleContent}
        </div>
    `;
        document.body.appendChild(pdfContainer);

        // --- 6. THE CORE LOGIC: CONVERT HTML TO IMAGE, THEN ADD IMAGE TO PDF ---
        html2canvas(pdfContainer, { scale: 2, useCORS: true })
            .then(canvas => {
                const imgData = canvas.toDataURL('image/jpeg', 0.98);
                const pdf = new jspdf.jsPDF({
                    orientation: 'landscape', unit: 'in', format: 'letter'
                });

                const pdfWidth = pdf.internal.pageSize.getWidth();
                const pdfHeight = pdf.internal.pageSize.getHeight();
                const canvasAspectRatio = canvas.width / canvas.height;
                let imgWidth = pdfWidth;
                let imgHeight = imgWidth / canvasAspectRatio;

                if (imgHeight > pdfHeight) {
                    imgHeight = pdfHeight;
                    imgWidth = imgHeight * canvasAspectRatio;
                }

                pdf.addImage(imgData, 'JPEG', 0, 0, imgWidth, imgHeight);
                pdf.save(`Schedule-${new Date().toISOString().slice(0, 10)}.pdf`);
            })
            .finally(() => {
                // --- 7. CLEANUP ---
                document.body.removeChild(pdfContainer);
                console.log("PDF generation complete. Temporary container removed.");
            });
    }
});