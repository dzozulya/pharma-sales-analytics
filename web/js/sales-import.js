(function ($) {
    'use strict';

    const $form = $('#csv-upload-form');
    const $uploadButton = $('#upload-button');
    const $transferButton = $('#transfer-button');
    const $reportButton = $('#report-button');

    const $progressWrapper = $('#upload-progress-wrapper');
    const $progressBar = $('#upload-progress-bar');
    const $processingIndicator = $('#processing-indicator');
    const $result = $('#import-result');

    function resetProgress() {
        $progressWrapper.hide();
        $progressBar
            .css('width', '0%')
            .text('0%');
    }

    function showProcessing(message) {
        $processingIndicator
            .html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> ' + message)
            .show();
    }

    function hideProcessing() {
        $processingIndicator.hide();
    }

    function showResult(type, title, data) {
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';

        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + title + '</strong>';

        if (data) {
            html += '<pre>' + escapeHtml(JSON.stringify(data, null, 2)) + '</pre>';
        }

        html += '</div>';

        $result.html(html).show();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $form.on('submit', function (event) {
        event.preventDefault();

        const formData = new FormData(this);

        resetProgress();

        $result.hide().empty();
        $reportButton.hide();
        $transferButton.prop('disabled', true);
        $uploadButton.prop('disabled', true);

        $progressWrapper.show();
        showProcessing('Uploading CSV file...');

        $.ajax({
            url: salesImportConfig.uploadUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,

            xhr: function () {
                const xhr = $.ajaxSettings.xhr();

                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function (event) {
                        if (event.lengthComputable) {
                            const percent = Math.round((event.loaded / event.total) * 100);

                            $progressBar
                                .css('width', percent + '%')
                                .text(percent + '%');

                            if (percent >= 100) {
                                showProcessing('File uploaded. Importing rows into MongoDB...');
                            }
                        }
                    });
                }

                return xhr;
            },

            success: function (response) {
                if (response.success) {
                    showResult('success', response.message, response.result);
                    $transferButton.prop('disabled', false);
                } else {
                    showResult('error', response.message || 'Import failed.', response.errors || null);
                }
            },

            error: function (xhr) {
                showResult('error', 'Request failed.', {
                    status: xhr.status,
                    response: xhr.responseText
                });
            },

            complete: function () {
                hideProcessing();
                $uploadButton.prop('disabled', false);
            }
        });
    });

    $transferButton.on('click', function () {
        $transferButton.prop('disabled', true);
        $reportButton.hide();
        $result.hide().empty();

        showProcessing('Transferring data to Elasticsearch/OpenSearch...');

        $.ajax({
            url: salesImportConfig.transferUrl,
            type: 'POST',
            data: {
                _csrf: yii.getCsrfToken()
            },

            success: function (response) {
                if (response.success) {
                    showResult('success', response.message, response.result);

                    $reportButton
                        .hide()
                        .fadeIn(250);
                } else {
                    showResult('error', response.message || 'Transfer failed.', null);
                    $transferButton.prop('disabled', false);
                }
            },

            error: function (xhr) {
                showResult('error', 'Request failed.', {
                    status: xhr.status,
                    response: xhr.responseText
                });

                $transferButton.prop('disabled', false);
            },

            complete: function () {
                hideProcessing();
            }
        });
    });

})(jQuery);