// File: hangar_validity.js
// Purpose: Manages the validity checks table, including date saving and document uploads/removals.

function initializeValidityChecks() {
    const $validityTable = $('#validityTable');
    if ($validityTable.length === 0) return;

    function updateValidityStatus(row, dateText) {
        const $statusCell = $(row).find('.status-cell');
        if (!dateText) { $statusCell.html('<span class="text-muted">No Date</span>'); return; }
        const expiry = new Date(dateText), today = new Date();
        today.setHours(0, 0, 0, 0);
        if (isNaN(expiry.getTime())) { $statusCell.html('<span class="text-danger">Invalid Date</span>'); return; }
        $statusCell.html(expiry >= today ? '<span class="text-success">Valid</span>' : '<span class="text-danger">Expired</span>');
    }

    flatpickr("#validityTable .datepicker", {
        altInput: true,             // Creates the user-friendly visible input
        altFormat: "M d, Y",        // The format you want to see (e.g., Aug 31, 2025)
        dateFormat: "Y-m-d",        // The format sent to the server (e.g., 2025-08-31)
        allowInput: true            // Allows users to type in the date if they want
    });

    $validityTable.find('tbody tr[data-field]').each(function () {
        const dateValueForStatus = $(this).find('.validity-date')[0]._flatpickr.input.value;
        updateValidityStatus($(this), dateValueForStatus);
    });

    $validityTable.on('click', '.save-validity', function () {
        const $button = $(this), $row = $button.closest('tr'), fieldName = $row.data('field'), dateValue = $row.find('.validity-date').val();
        if (!fieldName) { return showNotification('error', 'Could not save: Field identifier missing.'); }
        $button.prop('disabled', true).find('i').removeClass('fa-save').addClass('fa-spinner fa-spin');
        $.ajax({
            url: 'hangar_update_validity.php', type: 'POST', data: { field: fieldName, value: dateValue, csrf_token: $('#csrf_token_manager').val() }, dataType: 'json',
            success: (res) => res.success ? (showNotification('success', 'Date saved!'), updateValidityStatus($row, dateValue)) : showNotification('error', res.error || 'Failed to save.'),
            error: (xhr) => showNotification('error', (xhr.responseJSON && xhr.responseJSON.error) || 'A server error occurred.'),
            complete: () => $button.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-save')
        });
    });

    $validityTable.on('click', '.upload-license-btn', function () {
        $('#validityFieldInput').val($(this).data('field'));
        $('#licenseUploadInput').click();
    });

    $('#licenseUploadInput').on('change', function () {
        if (this.files.length > 0) {
            const formData = new FormData($('#licenseUploadForm')[0]), field = $('#validityFieldInput').val(), $cell = $validityTable.find(`tr[data-field="${field}"] .document-cell`);
            $cell.html('<i class="fa fa-spinner fa-spin"></i> Uploading...');
            $.ajax({
                url: 'hangar_upload_license.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showNotification('success', res.message);
                        $cell.html(`<a href="${res.newPath}" target="_blank" class="btn btn-xs btn-info"><i class="fa fa-eye"></i> View</a> <button class="btn btn-xs btn-warning remove-license-btn" data-field="${field}"><i class="fa fa-trash"></i> Remove</button>`);
                    } else {
                        showNotification('error', 'Upload failed: ' + res.error);
                        $cell.html(`<button class="btn btn-xs btn-success upload-license-btn" data-field="${field}"><i class="fa fa-upload"></i> Upload</button>`);
                    }
                },
                error: (xhr) => {
                    showNotification('error', (xhr.responseJSON && xhr.responseJSON.error) || 'A server error occurred.');
                    $cell.html(`<button class="btn btn-xs btn-success upload-license-btn" data-field="${field}"><i class="fa fa-upload"></i> Upload</button>`);
                }
            });
        }
        $(this).val('');
    });

    $validityTable.on('click', '.remove-license-btn', function () {
        if (!confirm('Are you sure you want to permanently remove this document?')) return;
        const field = $(this).data('field'), $cell = $(this).closest('.document-cell');
        $cell.html('<i class="fa fa-spinner fa-spin"></i> Removing...');
        $.ajax({
            url: 'hangar_remove_license.php', type: 'POST', data: { validityField: field, csrf_token: $('#csrf_token_manager').val() }, dataType: 'json',
            success: (res) => res.success ? (showNotification('success', res.message), $cell.html(`<button class="btn btn-xs btn-success upload-license-btn" data-field="${field}"><i class="fa fa-upload"></i> Upload</button>`)) : showNotification('error', 'Removal failed: ' + res.error),
            error: (xhr) => showNotification('error', (xhr.responseJSON && xhr.responseJSON.error) || 'A server error occurred.')
        });
    });
}