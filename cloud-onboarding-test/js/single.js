(function(App) {
    App.single = App.single || {};

    App.single.initSingleEmail = function() {
        const singleForm = document.getElementById('singleEmailForm');
        const previewSingleBtn = document.getElementById('previewSingleBtn');
        const singleTemplateSelect = document.getElementById('singleTemplateSelect');
        const singleLanguageSelect = document.getElementById('singleLanguageSelect');
        const singleDynamicFields = document.getElementById('singleDynamicFields');

        if (singleTemplateSelect) {
            singleTemplateSelect.addEventListener('change', function() {
                const template = App.state.templatesData[this.value];
                App.templates.buildDynamicFields(this.value, singleDynamicFields);
                App.templates.buildLanguageOptions(singleLanguageSelect, template);
            });
        }

        if (previewSingleBtn) {
            previewSingleBtn.addEventListener('click', function() {
                if (!singleForm) {
                    return;
                }
                const formData = new FormData(singleForm);
                formData.append('action', 'preview');

                fetch('send.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.text())
                    .then(html => {
                        const iframe = document.getElementById('previewFrame');
                        iframe.srcdoc = html;
                    })
                    .catch(error => {
                        App.utils.showAlert('Error loading preview: ' + error.message, 'danger');
                    });
            });
        }

        if (singleForm) {
            singleForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const submitBtn = singleForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';

                const formData = new FormData(singleForm);
                formData.append('action', 'send_single');

                fetch('send.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            App.utils.showAlert(
                                `<i class="bi bi-check-circle"></i> Email sent successfully to ${formData.get('email')}`,
                                'success'
                            );
                            singleForm.reset();
                            if (singleDynamicFields) {
                                singleDynamicFields.innerHTML = '';
                            }
                        } else {
                            App.utils.showAlert(`<i class="bi bi-x-circle"></i> Failed to send email: ${data.message}`, 'danger');
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
    };
})(window.App);
