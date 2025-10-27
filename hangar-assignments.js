// File: hangar_assignments.js
// Purpose: Handles display of assigned qualifications (crafts) and contracts.

function initializeAssignments() {
    if ($("#pilotContainer").length === 0) return;
    $.ajax({
        url: "hangar_get_assigned_crafts.php", dataType: 'json',
        success: res => {
            const $list = $("#assignedCraftsList").empty();
            if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                res.data.forEach(item => $list.append(`<li>${escapeHtml(item.craft_type)} (${escapeHtml(item.position)})</li>`));
            } else { $list.append('<li class="text-muted">No craft qualifications assigned.</li>'); }
        },
        error: () => $("#assignedCraftsList").html('<li class="text-danger">Error loading craft qualifications.</li>')
    });
    $.ajax({
        url: "hangar_get_assigned_contracts.php", dataType: 'json',
        success: res => {
            const $list = $("#assignedContractsList").empty();
            if (res.success && Array.isArray(res.data) && res.data.length > 0) {
                res.data.forEach(item => $list.append(`<li>${escapeHtml(item.contract_name)}</li>`));
            } else { $list.append('<li class="text-muted">No contracts assigned.</li>'); }
        },
        error: () => $("#assignedContractsList").html('<li class="text-danger">Error loading assigned contracts.</li>')
    });
}