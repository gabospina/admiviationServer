// ==========================================================================
// === stats-experience.js - v83 Craft Experience Table and Form Management ===
// ==========================================================================

function getExperience() {
    var $tbody = $("#crafts #experience-section table tbody");
    if (!$tbody.length) return;
    $tbody.html('<tr><td colspan="8" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

    $.ajax({
        type: "GET", url: "stats_get_craft_experience.php", dataType: "json",
        success: function (response) {
            if (!response || !response.success || typeof response.data !== 'object') {
                $tbody.html(`<tr><td colspan="8" class="text-center text-danger">${response.message || 'Failed to load experience data.'}</td></tr>`);
                return;
            }

            let tableRows = "", count = 0;
            let grandTotals = { PIC: 0, SIC: 0, IFR: 0, VFR: 0, Night: 0, Total: 0 };

            $.each(response.data, function (craft, hours) {
                count++;
                let h = {
                    PIC: parseFloat(hours.PIC) || 0, SIC: parseFloat(hours.SIC) || 0,
                    IFR: parseFloat(hours.IFR) || 0, VFR: parseFloat(hours.VFR) || 0,
                    Night: parseFloat(hours.Night) || 0, Total: parseFloat(hours.Total) || 0
                };
                Object.keys(grandTotals).forEach(key => grandTotals[key] += h[key]);
                tableRows += `<tr>
					<td class="text-center">${craft}</td>
					<td class="text-right">${formatNumberWithCommas(h.PIC)}</td>
					<td class="text-right">${formatNumberWithCommas(h.SIC)}</td>
					<td class="text-right">${formatNumberWithCommas(h.IFR)}</td>
					<td class="text-right">${formatNumberWithCommas(h.VFR)}</td>
					<td class="text-right">${formatNumberWithCommas(h.Night)}</td>
					<td class="text-right">${formatNumberWithCommas(h.Total)}</td>
					<td class='text-center'><button class='btn btn-xs btn-danger deleteExperience' data-craft='${craft}' title='Delete ALL initial experience for ${craft}'><i class='fa fa-trash'></i></button></td>
				</tr>`;
            });

            if (count === 0) {
                tableRows = `<tr><td colspan="8" class="text-center text-muted">No craft experience recorded yet.</td></tr>`;
            } else {
                tableRows += `<tr style='font-weight: bold;' class='table-secondary'>
					<td class="text-center"><strong>Totals</strong></td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.PIC)}</td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.SIC)}</td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.IFR)}</td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.VFR)}</td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.Night)}</td>
					<td class="text-right">${formatNumberWithCommas(grandTotals.Total)}</td>
					<td class="text-center"></td>
				</tr>`;
            }
            $tbody.html(tableRows);
            attachDeleteExperienceHandler();
        },
        error: () => $tbody.html(`<tr><td colspan="8" class="text-center text-danger">A network error occurred.</td></tr>`)
    });
}

function attachDeleteExperienceHandler() {
    $("#crafts #experience-section").off('click.deleteExp').on('click.deleteExp', '.deleteExperience', function () {
        var $button = $(this), craftTypeValue = $button.data("craft");
        if (!craftTypeValue || !confirm(`Delete ALL initial experience for '${craftTypeValue}'? This cannot be undone.`)) return;

        $button.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.ajax({
            type: "POST", 
            url: "stats_delete_experience.php", 
            data: { 
                craft_type: craftTypeValue,
                form_token: $('#form_token_manager').val() // ✅ ADD THIS
            }, 
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    alert(response.message || "Experience deleted.");
                    getExperience();
                } else {
                    alert(response.message || "Failed to delete experience.");
                    $button.prop('disabled', false).html('<i class="fa fa-trash"></i>');
                }
            },
            error: () => {
                alert("AJAX Error deleting experience.");
                $button.prop('disabled', false).html('<i class="fa fa-trash"></i>');
            }
        });
    });
}

function calculatePicTotalDisplay() {
    const total = (parseFloat($("#addInitPicIfrHours").val()) || 0) + (parseFloat($("#addInitPicVfrHours").val()) || 0);
    $("#addInitPicTotalDisplay").text(total.toFixed(1));
}

function calculateSicTotalDisplay() {
    const total = (parseFloat($("#addInitSicIfrHours").val()) || 0) + (parseFloat($("#addInitSicVfrHours").val()) || 0);
    $("#addInitSicTotalDisplay").text(total.toFixed(1));
}

function initializeExperienceForm() {
    $("#addInitPicIfrHours, #addInitPicVfrHours").on("input change", calculatePicTotalDisplay);
    $("#addInitSicIfrHours, #addInitSicVfrHours").on("input change", calculateSicTotalDisplay);
    calculatePicTotalDisplay();
    calculateSicTotalDisplay();

    $("#addExperienceForm").on('submit.addInitExp', function (event) {
        event.preventDefault();
        const $submitButton = $(this).find('#addExperienceBtn');
        
        const values = {
            craft_type: $("#addInitCraftType").val().trim(),
            pic_ifr_hours: parseFloat($("#addInitPicIfrHours").val()),
            pic_vfr_hours: parseFloat($("#addInitPicVfrHours").val()),
            pic_night_hours: parseFloat($("#addInitPicNightHours").val()),
            sic_ifr_hours: parseFloat($("#addInitSicIfrHours").val()),
            sic_vfr_hours: parseFloat($("#addInitSicVfrHours").val()),
            sic_night_hours: parseFloat($("#addInitSicNightHours").val()),
        };
    
        if (!values.craft_type || Object.values(values).some(v => typeof v === 'number' && (isNaN(v) || v < 0))) {
            alert("Please fill all fields with valid numbers (>= 0)."); return;
        }
        if (values.pic_night_hours > values.pic_ifr_hours + values.pic_vfr_hours) {
            alert("PIC Night Hours cannot exceed total PIC hours."); return;
        }
        if (values.sic_night_hours > values.sic_ifr_hours + values.sic_vfr_hours) {
            alert("SIC Night Hours cannot exceed total SIC hours."); return;
        }
    
        $submitButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        
        // ✅ FIXED: Use the values object and add CSRF token
        $.ajax({
            type: "POST", 
            url: "stats_add_experience.php", 
            data: { 
                ...values, // Spread the existing values
                form_token: $('#form_token_manager').val() // ✅ Add CSRF token
            }, 
            dataType: "json",
            success: function (response) {
                if (response && response.success) {
                    alert(response.message || "Initial experience updated.");
                    $("#addExperienceForm")[0].reset();
                    calculatePicTotalDisplay();
                    calculateSicTotalDisplay();
                    getExperience();
                } else {
                    alert(response.message || "Failed to update experience.");
                }
            },
            error: () => alert("AJAX Error saving experience."),
            complete: () => $submitButton.prop('disabled', false).html('<i class="fa fa-plus"></i> Add / Update')
        });
    });
}