jQuery(document).ready(function($) {
    $('#manual-export').on('click', function() {
        var button = $(this);
        var resultDiv = $('#export-result');

        button.prop('disabled', true).text(connecteurCorailAdmin.exportInProgress);
        resultDiv.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'export_corail_data',
                nonce: connecteurCorailAdmin.exportNonce
            },
            success: function(response) {
                if (response.success) {
                    var message = response.data.message;
                    var downloadLinks = '';

                    if (response.data.files && response.data.files.length > 0) {
                        downloadLinks = '<div class="download-links">';
                        downloadLinks += '<p><strong>' + connecteurCorailAdmin.downloadFiles + '</strong></p>';
                        response.data.files.forEach(function(file) {
                            downloadLinks += '<p><a href="' + file.url + '" download class="button button-secondary">' + connecteurCorailAdmin.download + ' ' + file.name + '</a></p>';
                        });
                        downloadLinks += '</div>';
                    }

                    resultDiv.html('<div class="notice notice-success"><p>' + message + '</p>' + downloadLinks + '</div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error"><p>' + connecteurCorailAdmin.exportError + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text(connecteurCorailAdmin.launchExport);
            }
        });
    });

    $('#status-file').on('change', function() {
        var importButton = $('#import-status');
        if (this.files.length > 0) {
            importButton.prop('disabled', false);
        } else {
            importButton.prop('disabled', true);
        }
    });

    $('#import-status').on('click', function() {
        var button = $(this);
        var resultDiv = $('#import-result');
        var fileInput = $('#status-file')[0];

        if (!fileInput.files.length) {
            resultDiv.html('<div class="notice notice-error"><p>' + connecteurCorailAdmin.selectFile + '</p></div>');
            return;
        }

        var formData = new FormData();
        formData.append('action', 'import_corail_status');
        formData.append('nonce', connecteurCorailAdmin.importNonce);
        formData.append('status_file', fileInput.files[0]);

        button.prop('disabled', true).text(connecteurCorailAdmin.importInProgress);
        resultDiv.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error"><p>' + connecteurCorailAdmin.importError + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text(connecteurCorailAdmin.importStatuses);
            }
        });
    });

    $('#clear-logs').on('click', function() {
        if (!confirm(connecteurCorailAdmin.clearLogsConfirm)) {
            return;
        }

        var button = $(this);
        var resultDiv = $('#clear-logs-result');

        button.prop('disabled', true).text(connecteurCorailAdmin.clearLogsInProgress);
        resultDiv.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'clear_corail_logs',
                nonce: connecteurCorailAdmin.clearLogsNonce
            },
            success: function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                    // Reload the page to refresh the logs display
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                resultDiv.html('<div class="notice notice-error"><p>' + connecteurCorailAdmin.clearLogsError + '</p></div>');
            },
            complete: function() {
                button.prop('disabled', false).text(connecteurCorailAdmin.clearLogsButton);
            }
        });
    });

    // Status mapping functionality
    var mappingRowCounter = 0;

    function getWooCommerceStatusOptions() {
        var select = $('.wc-status-select').first();
        if (select.length > 0) {
            return select.html();
        }
        return '<option value="">-- Sélectionner --</option>';
    }

    function addMappingRow() {
        mappingRowCounter++;
        var rowId = 'mapping_' + mappingRowCounter + '_' + Date.now();
        var wcStatusOptions = getWooCommerceStatusOptions();
        
        var rowHtml = '<div class="connecteur-corail-mapping-row" data-row-id="' + rowId + '">';
        rowHtml += '<input type="text" name="connecteur_corail_settings[status_mapping_keys][]" value="" class="corail-status-input" placeholder="Statut Corail" />';
        rowHtml += '<select name="connecteur_corail_settings[status_mapping_values][]" class="wc-status-select">' + wcStatusOptions + '</select>';
        rowHtml += '<button type="button" class="button button-link-delete remove-mapping-row">Supprimer</button>';
        rowHtml += '</div>';
        
        $('#status-mapping-rows').append(rowHtml);
    }

    $(document).on('click', '#add-mapping-row', function(e) {
        e.preventDefault();
        addMappingRow();
    });

    $(document).on('click', '.remove-mapping-row', function(e) {
        e.preventDefault();
        $(this).closest('.connecteur-corail-mapping-row').remove();
    });
});