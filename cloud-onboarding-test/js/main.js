(function(App) {
    document.addEventListener('DOMContentLoaded', function() {
        const templatesDataEl = document.getElementById('templates-data');
        App.state.templatesData = templatesDataEl ? JSON.parse(templatesDataEl.textContent) : {};

        App.editor.initExistingEditors();
        App.variables.initVariableControls();
        App.variables.rebuildVariableSelects();
        App.variables.rebuildConditionalSelects();
        App.languages.initLanguageTabs();
        App.single.initSingleEmail();
        App.bulk.initBulkEmail();

        // Run diagnostic after a short delay to ensure editors are initialized
        setTimeout(() => {
            if (Object.keys(App.state.editorInstances).length > 0) {
                console.log('');
                console.log('🔍 Running CKEditor diagnostic...');
                App.editor.diagnoseHtmlSupport();
                console.log('');
                console.log('💡 TIP: If styles are being filtered, check the diagnostic output above.');
            }
        }, 2000);

        const templateForm = document.getElementById('templateForm');
        if (templateForm) {
            templateForm.addEventListener('submit', function(event) {
                let isValid = true;
                Object.entries(App.state.editorInstances).forEach(([lang, instance]) => {
                    const textarea = document.getElementById(`editor_${lang}`);
                    if (textarea && instance) {
                        const editorHtml = instance.getData();
                        textarea.value = App.editor.normalizeColorsToHexHtml(editorHtml);
                    }
                });
                document.querySelectorAll('.subject-field').forEach(subject => {
                    const lang = subject.dataset.lang;
                    if (!lang) {
                        return;
                    }
                    if (!subject.value.trim()) {
                        subject.focus();
                        isValid = false;
                    }
                });
                document.querySelectorAll('.rich-editor').forEach(textarea => {
                    const content = textarea.value.trim();
                    if (!content) {
                        textarea.focus();
                        isValid = false;
                    }
                });

                if (!isValid) {
                    event.preventDefault();
                }
            });
        }
    });
})(window.App);