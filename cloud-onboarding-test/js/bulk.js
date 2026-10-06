(function(App) {
    App.bulk = App.bulk || {};

    function parseJsonResponse(response) {
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (error) {
                const snippet = text.trim().slice(0, 200);
                const detail = snippet ? ` Server response: ${snippet}` : '';
                throw new Error(`Unexpected server response (${response.status}).${detail}`);
            }
        });
    }

    function displayCSVPreview(data, bulkTemplateSelect) {
        const container = document.getElementById('csvPreviewContainer');
        const messagesDiv = document.getElementById('csvValidationMessages');
        const tableDiv = document.getElementById('csvPreviewTable');
        const controlsDiv = document.getElementById('bulkSendControls');

        if (!container || !messagesDiv || !tableDiv || !controlsDiv) {
            return;
        }

        container.style.display = 'block';

        let messagesHtml = '';
        if (data.total_valid > 0) {
            messagesHtml += `<div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <strong>${data.total_valid} valid email(s)</strong> ready to send
            </div>`;
        }
        if (data.total_invalid > 0) {
            messagesHtml += `<div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> <strong>${data.total_invalid} invalid row(s)</strong> will be skipped:
                <ul class="mb-0 mt-2">`;
            data.invalid.forEach(item => {
                messagesHtml += `<li>Row ${item.row}: ${item.reason}</li>`;
            });
            messagesHtml += `</ul></div>`;
        }
        messagesDiv.innerHTML = messagesHtml;

        if (data.valid.length > 0) {
            const template = App.state.templatesData[bulkTemplateSelect.value];
            const variables = template ? template.fields.variables : [];
            const toggles = template ? template.fields.toggles : [];

            let tableHtml = `
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Row</th>
                                <th>Email</th>
                                <th>Language</th>
                                ${variables.map(v => `<th>${v}</th>`).join('')}
                                ${toggles.map(v => `<th>${v}</th>`).join('')}
                                <th>Auto-Fixed</th>
                            </tr>
                        </thead>
                        <tbody>`;

            data.valid.forEach(item => {
                const fixedBadge = item.fixed && item.fixed.length > 0
                    ? `<span class="badge bg-info" title="${item.fixed.join(', ')}">Yes</span>`
                    : '<span class="badge bg-secondary">No</span>';

                tableHtml += `
                    <tr>
                        <td>${item.row}</td>
                        <td>${item.email}</td>
                        <td><span class="badge bg-primary">${item.language.toUpperCase()}</span></td>
                        ${variables.map(v => `<td>${item.variables[v] ?? ''}</td>`).join('')}
                        ${toggles.map(v => `<td>${item.toggles[v] ? '✓' : '✗'}</td>`).join('')}
                        <td>${fixedBadge}</td>
                    </tr>`;
            });

            tableHtml += `</tbody></table></div>`;
            tableDiv.innerHTML = tableHtml;
            controlsDiv.style.display = 'block';
        }
    }

    App.bulk.initBulkEmail = function() {
        const csvUploadForm = document.getElementById('csvUploadForm');
        const bulkTemplateSelect = document.getElementById('bulkTemplateSelect');
        const bulkColumnsHint = document.getElementById('bulkColumnsHint');
        const confirmCheckbox = document.getElementById('confirmBulkSend');
        const sendBulkBtn = document.getElementById('sendBulkBtn');

        if (bulkTemplateSelect) {
            bulkTemplateSelect.addEventListener('change', function() {
                App.templates.updateBulkColumnsHint(this.value, bulkColumnsHint);
            });
        }

        if (csvUploadForm) {
            csvUploadForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = csvUploadForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';

                const formData = new FormData(csvUploadForm);
                formData.append('action', 'upload_csv');

                fetch('send.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(parseJsonResponse)
                    .then(data => {
                        if (data.success) {
                            displayCSVPreview(data, bulkTemplateSelect);
                        } else {
                            App.utils.showAlert(`<i class="bi bi-x-circle"></i> ${data.message}`, 'danger');
                        }
                    })
                    .catch(error => {
                        App.utils.showAlert('Error: ' + error.message, 'danger');
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    });
            });
        }

        if (confirmCheckbox) {
            confirmCheckbox.addEventListener('change', function() {
                if (sendBulkBtn) {
                    sendBulkBtn.disabled = !this.checked;
                }
            });
        }

        if (sendBulkBtn) {
            sendBulkBtn.addEventListener('click', function() {
                sendBulkBtn.disabled = true;
                if (confirmCheckbox) {
                    confirmCheckbox.disabled = true;
                }

                const progressDiv = document.getElementById('bulkSendProgress');
                const progressBar = document.getElementById('progressBar');
                const progressStatus = document.getElementById('progressStatus');

                if (!progressDiv || !progressBar || !progressStatus) {
                    return;
                }

                progressDiv.style.display = 'block';
                progressBar.style.width = '0%';
                progressBar.textContent = '0%';
                progressBar.setAttribute('aria-valuenow', '0');
                progressBar.classList.remove('bg-danger', 'bg-success');
                progressBar.classList.add('progress-bar-striped', 'progress-bar-animated');
                progressStatus.innerHTML = `
                    <div class="alert alert-info">
                        Preparing to send emails... 0/0
                    </div>`;

                function finalizeError(message) {
                    progressBar.style.width = '100%';
                    progressBar.textContent = 'Error';
                    progressBar.setAttribute('aria-valuenow', '100');
                    progressBar.classList.add('bg-danger');
                    progressBar.classList.remove('progress-bar-animated');
                    progressStatus.innerHTML = `<div class="alert alert-danger">Error: ${App.utils.escapeHtml(message)}</div>`;
                    sendBulkBtn.disabled = false;
                    if (confirmCheckbox) {
                        confirmCheckbox.disabled = false;
                    }
                }

                function finalizeSuccess(data) {
                    progressBar.style.width = '100%';
                    progressBar.textContent = '100%';
                    progressBar.setAttribute('aria-valuenow', '100');
                    progressBar.classList.remove('progress-bar-animated');
                    progressBar.classList.add('bg-success');

                    progressStatus.innerHTML = `
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <strong>Bulk send completed!</strong><br>
                            Sent: ${data.sent} | Failed: ${data.failed} | Total: ${data.total}
                        </div>`;

                    if (data.failed > 0) {
                        let failedHtml = '<div class="alert alert-warning mt-2"><strong>Failed emails:</strong><ul>';
                        data.results.forEach(result => {
                            if (result.status === 'failed') {
                                failedHtml += `<li>${App.utils.escapeHtml(result.email)}: ${App.utils.escapeHtml(result.message)}</li>`;
                            }
                        });
                        failedHtml += '</ul></div>';
                        progressStatus.innerHTML += failedHtml;
                    }

                    if (csvUploadForm) {
                        csvUploadForm.reset();
                    }
                    if (confirmCheckbox) {
                        confirmCheckbox.checked = false;
                        confirmCheckbox.disabled = false;
                    }

                    setTimeout(() => {
                        document.getElementById('csvPreviewContainer').style.display = 'none';
                    }, 10000);
                }

                function updateProgress(data) {
                    const percent = data.total > 0 ? Math.round((data.current / data.total) * 100) : 0;
                    progressBar.style.width = `${percent}%`;
                    progressBar.textContent = `${percent}%`;
                    progressBar.setAttribute('aria-valuenow', String(percent));
                    progressStatus.innerHTML = `
                        <div class="alert alert-info">
                            Sending emails... ${data.current}/${data.total} (${percent}%)<br>
                            Sent: ${data.sent} | Failed: ${data.failed}
                        </div>`;
                }

                function sendNextBatch() {
                    const formData = new FormData();
                    formData.append('action', 'send_bulk');
                    formData.append('batch_size', '1');

                    fetch('send.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(parseJsonResponse)
                        .then(data => {
                            if (!data.success) {
                                finalizeError(data.message || 'Bulk send failed');
                                return;
                            }

                            updateProgress(data);

                            if (data.complete) {
                                finalizeSuccess(data);
                                return;
                            }

                            window.setTimeout(sendNextBatch, 250);
                        })
                        .catch(error => {
                            finalizeError(error.message);
                        });
                }

                sendNextBatch();
            });
        }
    };
})(window.App);
