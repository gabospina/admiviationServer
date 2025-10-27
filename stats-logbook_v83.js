// ==========================================================================
// === stats-logbook.js - Logbook Display, Editing, and Data Management ===
// ==========================================================================

function getLogbook(init) {
    var page = init ? 0 : ($(".page-number.selected").data("page") || 0);

    // =========================================================================
    // === THE FIX IS HERE: Get date directly from the Flatpickr instance    ===
    // =========================================================================
    const logDatePicker = document.querySelector("#log-date")._flatpickr;
    const selectedDate = logDatePicker && logDatePicker.selectedDates.length > 0 ? logDatePicker.selectedDates[0] : null;
    const displayDate = logDatePicker ? logDatePicker.input.value : "";
    var startDateYMD = selectedDate ? moment(selectedDate).format('YYYY-MM-DD') : "";
    // =========================================================================

    if (!startDateYMD) {
        $("#manageHoursSection").html("<div class='alert alert-warning text-center'>Please select a valid starting date.</div>");
        setUpPages(0);
        return;
    }

    console.log(`getLogbook - Page: ${page}, Start Date: ${startDateYMD}, Init: ${init}`);
    var $section = $("#manageHoursSection");
    $section.html('<div class="text-center p-3"><i class="fa fa-spinner fa-spin fa-lg"></i> Loading Logbook...</div>');

    $.ajax({
        type: "GET", url: "stats_get_pilot_statistics.php",
        data: { page: page, start: startDateYMD, init: init },
        dataType: "json",
        success: function (response) {
            if (response && response.success && response.data) {
                var res = response.data;
                if (res.entries && res.entries.length > 0) {
                    var tableHtml = `<table class="table table-striped table-bordered table-hover"><thead><tr>
						<th class="text-center">Date</th><th class="text-center">Model</th><th class="text-center">Registration</th>
						<th class="text-center">PIC</th><th class="text-center">SIC</th><th class="text-center">Route</th>
						<th class="text-center">IFR</th><th class="text-center">VFR</th><th class="text-center">Night Time</th>
						<th class="text-center">Total Hours</th><th class="text-center">Hour Type</th><th class="text-center">Delete</th>
						</tr></thead><tbody>`;
                    res.entries.forEach(entry => {
                        tableHtml += `
								<tr>
									<td class='text-center'><span class='hourEditDate editable-click' data-pk='${entry.id}'>${formatDate(entry.date, 'MMM-DD-YYYY')}</span></td>
									<td class='text-center'><span class='hourEditCraftType editable-click' data-pk='${entry.id}'>${entry.craft_type || ''}</span></td>
									<td class='text-center'><span class='hourEditRegistration editable-click' data-pk='${entry.id}'>${entry.registration || ''}</span></td>
									<td class='text-center'><span class='hourEditPIC editable-click' data-pk='${entry.id}'>${entry.PIC || ''}</span></td>
									<td class='text-center'><span class='hourEditSIC editable-click' data-pk='${entry.id}'>${entry.SIC || ''}</span></td>
									<td class='text-center'><span class='hourEditRoute editable-click' data-pk='${entry.id}'>${entry.route || ''}</span></td>
									<td class='text-center'><span class='hourEditIFR editable-click' data-pk='${entry.id}'>${parseFloat(entry.ifr || 0).toFixed(1)}</span></td>
									<td class='text-center'><span class='hourEditVFR editable-click' data-pk='${entry.id}'>${parseFloat(entry.vfr || 0).toFixed(1)}</span></td>
									<td class='text-center'><span class='hourEditNightTime editable-click' data-pk='${entry.id}'>${parseFloat(entry.night_time || 0).toFixed(1)}</span></td>
									<td class='text-center'><span class='hourEditHour editable-click' data-pk='${entry.id}'>${parseFloat(entry.hours || 0).toFixed(1)}</span></td>
									<td class='text-center'>${entry.hour_type ? entry.hour_type.charAt(0).toUpperCase() + entry.hour_type.slice(1) : ''}</td>
									<td class='text-center'><button class='btn btn-xs btn-danger deleteEntry' data-pk='${entry.id}' title="Delete"><i class='fa fa-times'></i></button></td>
								</tr>`;
                    });
                    tableHtml += "</tbody></table>";
                    $section.html(tableHtml);
                    setUpHoursEdit();
                    setUpPages(parseInt(res.total || 0), init);
                } else {
                    $section.html("<h4 class='text-center text-muted p-4'>You have no logbook entries from " + displayDate + " forward.</h4>");
                    setUpPages(0);
                }
            } else {
                $section.html("<div class='alert alert-danger text-center'>Error loading logbook data. " + (response ? response.message : 'Invalid response') + "</div>");
                setUpPages(0);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error("AJAX Error fetching logbook:", textStatus, errorThrown);
            $section.html("<div class='alert alert-danger text-center'>AJAX Error loading logbook data. Please try again.</div>");
            setUpPages(0);
        }
    });
}

function setUpPages(totalEntries, isReset) {
    const itemsPerPage = 18;
    const $pageContainer = $("#page-container");
    const $pagesDiv = $pageContainer.find(".pages");
    $pagesDiv.empty();

    if (totalEntries > itemsPerPage) {
        const totalPages = Math.ceil(totalEntries / itemsPerPage);
        let currentPage = isReset ? totalPages - 1 : ($(".page-number.selected").data("page") || 0);
        currentPage = Math.min(Math.max(0, currentPage), totalPages - 1);

        for (let i = 0; i < totalPages; i++) {
            $("<div class='page-number display-inline'></div>").attr("data-page", i).text(i + 1)
                .toggleClass("selected", i === currentPage).appendTo($pagesDiv);
        }

        $pagesDiv.off("click.paginate").on("click.paginate", ".page-number", function () {
            if (!$(this).hasClass("selected")) {
                $(".page-number").removeClass("selected");
                $(this).addClass("selected");
                getLogbook(false);
            }
        });
        $pageContainer.show();
    } else {
        $pageContainer.hide();
    }
}

function setUpHoursEdit() {
    $(".hourEditDate, .hourEditHour, .hourEditCraftType, .hourEditRegistration, .hourEditPIC, .hourEditSIC, .hourEditRoute, .hourEditIFR, .hourEditVFR, .hourEditNightTime").editable("destroy");
    const editableOptions = {
        url: "stats_update_hour_entry.php",
        ajaxOptions: { 
            type: "POST", 
            dataType: 'json',
            data: {
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF TOKEN
            }
        },
        pk: function () { return $(this).data('pk'); },
        success: function (response) { if (!response || !response.success) return response.message || "Error."; },
        error: function () { return "AJAX Error"; }
    };
    $(".hourEditDate").editable({ ...editableOptions, type: "date", format: "yyyy-mm-dd", viewformat: "yyyy-mm-dd", name: "date" });
    $(".hourEditHour").editable({ ...editableOptions, type: "text", name: "hours", emptytext: "0.0", validate: v => { if (isNaN(parseFloat(v)) || parseFloat(v) <= 0) return 'Must be a number > 0'; } });
    $(".hourEditCraftType").editable({ ...editableOptions, type: "select", name: "craft_type", emptytext: "N/A", source: editableCraftsTypes });
    $(".hourEditRegistration").editable({ ...editableOptions, type: "select", name: "registration", emptytext: "N/A", source: editableRegistration });
    $(".hourEditPIC").editable({ ...editableOptions, type: "text", name: "PIC", emptytext: "N/A" });
    $(".hourEditSIC").editable({ ...editableOptions, type: "text", name: "SIC", emptytext: "N/A" });
    $(".hourEditRoute").editable({ ...editableOptions, type: "textarea", name: "route", emptytext: "N/A" });
    $(".hourEditIFR").editable({ ...editableOptions, type: "text", name: "ifr", emptytext: "0.0", validate: v => { if (isNaN(parseFloat(v)) || parseFloat(v) < 0) return 'Must be a number >= 0'; } });
    $(".hourEditVFR").editable({ ...editableOptions, type: "text", name: "vfr", emptytext: "0.0", validate: v => { if (isNaN(parseFloat(v)) || parseFloat(v) < 0) return 'Must be a number >= 0'; } });
    $(".hourEditNightTime").editable({ ...editableOptions, type: "text", name: "night_time", emptytext: "0.0", validate: v => { if (isNaN(parseFloat(v)) || parseFloat(v) < 0) return 'Must be a number >= 0'; } });

    $("#manageHoursSection").off('click.deleteEntry').on('click.deleteEntry', '.deleteEntry', function () {
        const $button = $(this), $row = $button.closest("tr"), pk = $button.data("pk");
        if (!pk || !confirm("Are you sure you want to delete this log entry?")) return;
        $button.prop('disabled', true).find('.fa').removeClass('fa-times').addClass('fa-spinner fa-spin');
        $.ajax({
            type: "POST", 
            url: "stats_log_book_entry_delete.php", 
            data: { 
                pk: pk,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF TOKEN
            }, 
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    $row.fadeOut(400, () => $(this).remove());
                    getExperience();
                } else {
                    alert(response.message || "Failed to delete entry.");
                    $button.prop('disabled', false).find('.fa').removeClass('fa-spinner fa-spin').addClass('fa-times');
                }
            },
            error: () => {
                alert("AJAX Error deleting entry.");
                $button.prop('disabled', false).find('.fa').removeClass('fa-spinner fa-spin').addClass('fa-times');
            }
        });
    });
}

function initializeCraftSelection() {
    $('#addCraftType').on('change.craftType', function () { $('#addCraftTypeInput').toggle($(this).val() === 'other'); });
    $('#addCraftRegistration').on('change.craftReg', function () { $('#addCraftRegistrationInput').toggle($(this).val() === 'other'); });
    loadCraftData();
}

function loadCraftData() {
    $.ajax({
        type: "GET", url: "stats_get_all_crafts.php", dataType: "json",
        success: function (response) {
            if (!response || !response.success || !Array.isArray(response.crafts)) {
                console.error("Invalid craft data received:", response); return;
            }
            allCraftsData = response.crafts;
            const craftTypesSet = new Set();
            allCraftsData.forEach(craft => {
                if (craft.craft_type) {
                    craftTypesSet.add(craft.craft_type);
                    if (!groupedRegistrations[craft.craft_type]) groupedRegistrations[craft.craft_type] = new Set();
                    if (craft.registration) groupedRegistrations[craft.craft_type].add(craft.registration);
                }
            });
            const sortedCraftTypes = [...craftTypesSet].sort();
            const $craftTypeSelect = $('#addCraftType').empty().append('<option value="">Select Craft Type</option>');
            sortedCraftTypes.forEach(type => $craftTypeSelect.append(`<option value="${type}">${type}</option>`));
            $craftTypeSelect.append('<option value="other">Other</option>');

            const $registrationSelect = $('#addCraftRegistration').empty().append('<option value="">Select Registration</option>');
            sortedCraftTypes.forEach(type => {
                if (groupedRegistrations[type] && groupedRegistrations[type].size > 0) {
                    const optgroup = $(`<optgroup label="${type}"></optgroup>`);
                    [...groupedRegistrations[type]].sort().forEach(reg => optgroup.append(`<option value="${reg}">${reg}</option>`));
                    $registrationSelect.append(optgroup);
                }
            });
            $registrationSelect.append('<option value="other">Other</option>');

            editableCraftsTypes = sortedCraftTypes.map(type => ({ value: type, text: type }));
            editableRegistration = [];
            sortedCraftTypes.forEach(type => {
                if (groupedRegistrations[type] && groupedRegistrations[type].size > 0) {
                    editableRegistration.push({ text: type, children: [...groupedRegistrations[type]].sort().map(reg => ({ value: reg, text: reg })) });
                }
            });

            console.log("Craft data loaded. Calling initial getLogbook/getExperience/statsGraph...");
            getLogbook(true);
            getExperience();

            // =============================================================
            // === ADD THIS LINE HERE ===
            // This ensures the graph is triggered only after the primary
            // data dependency (craft types) is loaded.
            $(".view-change[data-view='past7']").trigger("click");
            // =============================================================

        },
        error: (xhr, status, error) => console.error('AJAX error loading craft data:', error)
    });
}

function calculateTotalHours() {
    const totalHours = (parseFloat($('#addIFRInstrument').val()) || 0) + (parseFloat($('#addVFRVisual').val()) || 0);
    $('#addTotalFlightTime').val(totalHours.toFixed(1));
}

function initializeAddHourForm1() {
    $('#addIFRInstrument, #addVFRVisual').on('input.calc change.calc', calculateTotalHours);
    calculateTotalHours();

    $('#addHourForm').on('submit.addHour', function (e) {
        e.preventDefault();
        var $form = $(this), $submitButton = $form.find('button[type="submit"]');
        let dateObject = $('#addDate').datepicker('getDate');
        let formDateYMD = dateObject ? formatDate(dateObject, 'YYYY-MM-DD') : '';
        let formTotalHours = $('#addTotalFlightTime').val();
        let formNightTime = $('#addNightTime').val() || "0.0";

        if (!formDateYMD || !$('#addCraftType').val() || !$('#addCraftRegistration').val() || !formTotalHours || parseFloat(formTotalHours) <= 0) {
            alert('Please fill all required fields correctly.'); return;
        }
        if (parseFloat(formNightTime) > parseFloat(formTotalHours)) {
            alert('Night Time cannot exceed Total Hours.'); return;
        }

        const dataToSend = {
            date: formDateYMD,
            craft_type: $('#addCraftType').val(),
            registration: $('#addCraftRegistration').val(),
            PIC: $('#addPIC').val().trim(),
            SIC: $('#addSIC').val().trim(),
            route: $('#addRoute').val().trim(),
            ifr: $('#addIFRInstrument').val() || "0.0",
            vfr: $('#addVFRVisual').val() || "0.0",
            hours: formTotalHours,
            night_time: formNightTime,
            hour_type: (parseFloat(formNightTime) > 0) ? 'night' : 'day',
            crew_role: $('input[name="crew_role"]:checked').val()
        };

        $submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
        $.ajax({
            url: 'stats_log_book_entry_add.php', method: 'POST', data: dataToSend, dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    alert(response.message || 'Flight hours added successfully!');
                    $form[0].reset();
                    $('#addDate').datepicker('setDate', new Date());
                    calculateTotalHours();
                    getLogbook(true);
                    getExperience();
                } else {
                    alert(response.message || 'Error adding flight hours.');
                }
            },
            error: () => alert('AJAX Error: Could not add flight hours.'),
            complete: () => $submitButton.prop('disabled', false).html('Add Entry')
        });
    });
}

function initializeAddHourForm() {
    $('#addIFRInstrument, #addVFRVisual').on('input.calc change.calc', calculateTotalHours);
    calculateTotalHours();

    $('#addHourForm').on('submit.addHour', function (e) {
        e.preventDefault();
        var $form = $(this), $submitButton = $form.find('button[type="submit"]');

        // =========================================================================
        // === THE FIX IS HERE: More robust front-end validation                 ===
        // =========================================================================
        const datePicker = document.querySelector('#addDate')._flatpickr;
        const formDateYMD = datePicker && datePicker.selectedDates.length > 0 ? moment(datePicker.selectedDates[0]).format('YYYY-MM-DD') : '';
        const formCraftType = $('#addCraftType').val();
        const formRegistration = $('#addCraftRegistration').val();
        const formPIC = $('#addPIC').val().trim();
        const formIFR = parseFloat($('#addIFRInstrument').val()) || 0;
        const formVFR = parseFloat($('#addVFRVisual').val()) || 0;
        const formTotalHours = $('#addTotalFlightTime').val();

        // Standard required fields check
        if (!formDateYMD || !formCraftType || !formRegistration || !formPIC) {
            alert('Please fill all required fields marked with an asterisk (*).');
            return;
        }

        // Special IFR/VFR validation
        if (formIFR <= 0 && formVFR <= 0) {
            alert('You must enter a value for IFR, VFR, or both.');
            return;
        }

        // Total hours validation
        if (!formTotalHours || parseFloat(formTotalHours) <= 0) {
            alert('Total Flight Time must be greater than zero.');
            return;
        }

        const formNightTime = $('#addNightTime').val() || "0.0";
        if (parseFloat(formNightTime) > parseFloat(formTotalHours)) {
            alert('Night Time cannot exceed Total Hours.');
            return;
        }
        // =========================================================================

        const dataToSend = {
            date: formDateYMD,
            craft_type: formCraftType,
            registration: formRegistration,
            PIC: formPIC,
            SIC: $('#addSIC').val().trim(),
            route: $('#addRoute').val().trim(),
            ifr: formIFR,
            vfr: formVFR,
            hours: formTotalHours,
            night_time: formNightTime,
            hour_type: (parseFloat(formNightTime) > 0) ? 'night' : 'day',
            crew_role: $('input[name="crew_role"]:checked').val()
        };

        $submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
        $.ajax({
            url: 'stats_log_book_entry_add.php',
            method: 'POST',
            data: {
                ...dataToSend,
                form_token: $('#form_token_manager').val() // ✅ ADD CSRF TOKEN
            },
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    alert(response.message || 'Flight hours added successfully!');
                    $form[0].reset();
                    document.querySelector('#addDate')._flatpickr.setDate(new Date(), true);
                    calculateTotalHours();
                    getExperience(); // Refresh experience totals

                    // =========================================================================
                    // === THE FIX IS HERE: Smartly refresh the logbook                    ===
                    // =========================================================================
                    const logDatePicker = document.querySelector("#log-date")._flatpickr;
                    const logStartDate = logDatePicker.selectedDates[0];
                    const newEntryDate = moment(formDateYMD).toDate();

                    // If the new entry is before the current logbook start date,
                    // update the logbook start date to include the new entry.
                    if (moment(newEntryDate).isBefore(logStartDate, 'day')) {
                        logDatePicker.setDate(newEntryDate, true); // This will trigger a logbook refresh
                    } else {
                        // Otherwise, just refresh the current view
                        getLogbook(true);
                    }
                    // =========================================================================

                } else {
                    alert(response.message || 'Error adding flight hours.');
                }
            },
            error: () => alert('AJAX Error: Could not add flight hours.'),
            complete: () => $submitButton.prop('disabled', false).html('Add Entry')
        });
    });
}