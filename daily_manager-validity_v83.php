// daily_manager-validity.js v83
$(document).ready(function () {
    // =========================================================================
    // === "CHECK VALIDITIES" TAB with AUTOCOMPLETE for Pilot Search         ===
    // =========================================================================
    if ($('div[data-tab-toggle="check-validities"]').length > 0) {
        function loadAndBuildValiditiesReport() {
            const $container = $("#validities-report-container");
            $container.html('<p class="text-center" style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Checking pilot validities...</p>');

            $.ajax({
                type: "GET",
                url: "daily_manager_check_validities.php",
                dataType: "json",
                success: function (response) {
                    if (!response.success) {
                        $container.html('<p class="text-danger text-center">Error: ' + (response.message || 'Could not load data.') + '</p>');
                        return;
                    }

                    $container.data('full-validity-data', response.data);
                    buildValidityTable(response.data);

                    const pilotNames = [...new Set(response.data.map(item => item.pilot_name))];
                    if ($.ui && $.ui.autocomplete) {
                        $("#validityPilotSearch").autocomplete({
                            source: pilotNames,
                            minLength: 1,
                            select: function (event, ui) {
                                $(this).val(ui.item.value).trigger('keyup');
                                return false;
                            },
                            change: function (event, ui) {
                                if (!ui.item) $(this).trigger('keyup');
                            }
                        });
                    }
                },
                error: function () {
                    $container.html('<p class="text-danger text-center">A network error occurred.</p>');
                }
            });
        }

        function buildValidityTable(data) {
            const $container = $("#validities-report-container");
            if (data.length === 0) {
                $container.html('<p class="text-muted text-center">No matching validities found.</p>');
                return;
            }

            let tableHtml = `<div class="table-responsive"><table class="table table-bordered table-striped" id="validity-report-table">
                <thead><tr><th>Pilot Name</th><th>Validity Type</th><th>Expiry Date</th><th>Status</th></tr></thead><tbody>`;

            data.forEach(item => {
                let statusHtml = '';
                if (item.days_left < 0) {
                    statusHtml = `<span class="label" style="background-color: red; color: white;">Expired ${Math.abs(item.days_left)} days ago</span>`;
                } else if (item.days_left <= 15) {
                    statusHtml = `<span class="label" style="background-color: orange; color: white;">Expires in ${item.days_left} days</span>`;
                } else {
                    statusHtml = `<span class="label" style="background-color: green; color: white;">Expires in ${item.days_left} days</span>`;
                }
                tableHtml += `<tr><td>${escapeHtml(item.pilot_name)}</td><td>${escapeHtml(item.validity_name)}</td><td>${item.expiry_date}</td><td>${statusHtml}</td></tr>`;
            });

            tableHtml += `</tbody></table></div>`;
            $container.html(tableHtml);
        }

        // Event Handlers for "Check Validities"
        $('div[data-tab-toggle="check-validities"]').on('click', loadAndBuildValiditiesReport);

        $('#validityPilotSearch').on('keyup', debounce(function () {
            const searchTerm = $(this).val().toLowerCase();
            const fullData = $("#validities-report-container").data('full-validity-data') || [];
            const filteredData = searchTerm === '' ? fullData : fullData.filter(item => item.pilot_name.toLowerCase().includes(searchTerm));
            buildValidityTable(filteredData);
        }, 250));

        $('#printValiditiesBtn').on('click', function () {
            // ... (Code for printing report, depends on external functions) ...
            console.log("Print Validities button clicked.");
        });

        $('#downloadValiditiesPdfBtn').on('click', function () {
            // ... (Code for PDF download, depends on jspdf/html2canvas) ...
            console.log("Download PDF button clicked.");
        });
    }

    // =========================================================================
    // === "LICENCES VALIDITY" (Field Management) TAB FUNCTIONALITY          ===
    // =========================================================================
    if ($('div[data-tab-toggle="licences-validity"]').length > 0) {
        const $licencesAlertContainer = $('#licence-fields-alert-container');

        function loadLicenceFields() {
            const $container = $('#licence-fields-table-container');
            $container.html('<p class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading fields...</p>');

            $.ajax({
                url: 'daily_manager_licences_validity.php', type: 'GET', dataType: 'json',
                success: function (response) {
                    if (!response.success) {
                        $container.html(`<p class="text-center text-danger">Error: ${response.message}</p>`);
                        return;
                    }
                    if (response.fields.length === 0) {
                        $container.html('<p class="text-center text-muted">No standard fields have been created yet.</p>');
                        return;
                    }

                    let tableHtml = `<div class="alert alert-info"><i class="fa fa-info-circle"></i> Drag and drop rows to change their display order.</div>
                        <table class="table table-bordered table-striped">
                        <thead><tr><th style="width:50px;">Order</th><th>Field Label</th><th>Field Key</th><th style="width:100px;">Actions</th></tr></thead>
                        <tbody class="sortable-fields">`;

                    response.fields.forEach(field => {
                        tableHtml += `<tr data-field-id="${field.id}">
                            <td class="drag-handle" style="cursor: move; text-align:center;"><i class="fa fa-bars"></i></td>
                            <td>${escapeHtml(field.field_label)}</td>
                            <td><code>${escapeHtml(field.field_key)}</code></td>
                            <td class="text-center"><button class="btn btn-xs btn-danger delete-licence-field-btn" data-field-id="${field.id}" data-field-label="${escapeHtml(field.field_label)}"><i class="fa fa-trash"></i> Delete</button></td>
                        </tr>`;
                    });
                    tableHtml += '</tbody></table>';
                    $container.html(tableHtml);

                    if ($.ui && $.ui.sortable) {
                        $container.find('.sortable-fields').sortable({
                            handle: '.drag-handle', placeholder: 'ui-state-highlight', axis: 'y',
                            update: function (event, ui) {
                                const orderedIds = $(this).sortable('toArray', { attribute: 'data-field-id' });
                                showNotification($licencesAlertContainer, 'info', 'Saving new order...');
                                $.ajax({
                                    url: 'daily_manager_licence_validity_order.php', type: 'POST',
                                    data: { field_order: orderedIds, form_token: $("#form_token_manager").val() },
                                    dataType: 'json',
                                    success: function (saveResponse) {
                                        showNotification($licencesAlertContainer, saveResponse.success ? 'success' : 'danger', saveResponse.message);
                                        if (!saveResponse.success) $(event.target).sortable('cancel');
                                    },
                                    error: function () {
                                        showNotification($licencesAlertContainer, 'danger', 'A server error occurred.');
                                        $(event.target).sortable('cancel');
                                    }
                                });
                            }
                        }).disableSelection();
                    }
                },
                error: function () {
                    $container.html('<p class="text-center text-danger">A server error occurred.</p>');
                }
            });
        }

        // Event Handlers for "Licences Validity"
        $('div[data-tab-toggle="licences-validity"]').one('click', loadLicenceFields);
        if ($('div.tabs div[data-tab-toggle="licences-validity"].active').length) {
            loadLicenceFields();
        }

        $('#addNewLicenceFieldForm').on('submit', function (e) {
            e.preventDefault();
            const $btn = $('#addNewFieldBtn');
            const originalBtnHtml = $btn.html();
            $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');
            $.ajax({
                url: 'daily_manager_create_licence_validity.php', type: 'POST',
                data: {
                    field_label: $('#newFieldLabel').val(),
                    field_key: $('#newFieldKey').val(),
                    form_token: $("#form_token_manager").val()
                },
                dataType: 'json',
                success: function (response) {
                    showNotification($licencesAlertContainer, response.success ? 'success' : 'danger', response.message);
                    if (response.success) {
                        $('#addNewLicenceFieldForm')[0].reset();
                        loadLicenceFields();
                    }
                },
                error: function (xhr) {
                    const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                    showNotification($licencesAlertContainer, 'danger', errorMsg);
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalBtnHtml);
                }
            });
        });

        $('#licence-fields-table-container').on('click', '.delete-licence-field-btn', function () {
            const $btn = $(this);
            const field_id = $btn.data('field-id');
            const field_label = $btn.data('field-label');
            if (!confirm(`Are you sure you want to delete "${field_label}"?\n\nWARNING: This will permanently delete the field and ALL associated dates/documents for ALL pilots. This cannot be undone.`)) return;

            $btn.closest('tr').css('opacity', '0.5');
            $.ajax({
                url: 'daily_manager_delete_licence_validity.php', type: 'POST',
                data: { field_id: field_id, form_token: $("#form_token_manager").val() },
                dataType: 'json',
                success: function (response) {
                    showNotification($licencesAlertContainer, response.success ? 'success' : 'danger', response.message);
                    if (response.success) {
                        loadLicenceFields();
                    } else {
                        $btn.closest('tr').css('opacity', '1');
                    }
                },
                error: function (xhr) {
                    const errorMsg = xhr.responseJSON ? xhr.responseJSON.message : 'A server error occurred.';
                    showNotification($licencesAlertContainer, 'danger', errorMsg);
                    $btn.closest('tr').css('opacity', '1');
                }
            });
        });
    }
});