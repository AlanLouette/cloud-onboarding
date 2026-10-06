(function(App) {
    App.languages = App.languages || {};

    App.languages.addLanguageTab = function(lang) {
        const tabs = document.getElementById('languageTabs');
        const content = document.getElementById('languageTabContent');
        if (!tabs || !content) {
            return;
        }
        if (document.getElementById(`tab-${lang}`)) {
            return;
        }
        const isFirst = !tabs.querySelector('.nav-link');
        const tabItem = document.createElement('li');
        tabItem.className = 'nav-item';
        tabItem.setAttribute('role', 'presentation');
        tabItem.dataset.lang = lang;
        tabItem.innerHTML = `
            <button class="nav-link ${isFirst ? 'active' : ''}" id="tab-${lang}" data-bs-toggle="tab" data-bs-target="#content-${lang}" type="button" role="tab">
                ${lang.toUpperCase()}
            </button>
        `;
        tabs.appendChild(tabItem);

        const pane = document.createElement('div');
        pane.className = `tab-pane fade ${isFirst ? 'show active' : ''}`;
        pane.id = `content-${lang}`;
        pane.dataset.lang = lang;
        pane.setAttribute('role', 'tabpanel');
        pane.innerHTML = `
            <div class="mb-3">
                <label class="form-label">Email Subject <span class="text-danger">*</span></label>
                <input type="text" name="subject_${lang}" class="form-control subject-field" placeholder="Use **VARIABLE** for dynamic content" data-lang="${lang}">
                <small class="text-muted">Example: Your migration on **DATE**</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Body <span class="text-danger">*</span></label>
                <div class="editor-toolbar">
                    <div class="editor-picker">
                        <select class="form-select form-select-sm variable-select" data-lang="${lang}" data-variable-select data-placeholder="Select variable"></select>
                        <button type="button" class="btn btn-sm btn-outline-primary insert-variable-inline" data-lang="${lang}"><i class="bi bi-code-square"></i> Variable</button>
                    </div>
                    <div class="editor-picker">
                        <select class="form-select form-select-sm conditional-select" data-lang="${lang}" data-conditional-select data-placeholder="Select conditional"></select>
                        <button type="button" class="btn btn-sm btn-outline-info insert-conditional-inline" data-lang="${lang}"><i class="bi bi-info-square"></i> Conditional (Blue)</button>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary insert-info-box" data-lang="${lang}" title="Insert blue info box"><i class="bi bi-info-circle"></i> Info Box</button>
                    <button type="button" class="btn btn-sm btn-outline-warning insert-alert-box" data-lang="${lang}" title="Insert yellow warning box"><i class="bi bi-exclamation-triangle"></i> Alert Box</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary insert-variable-link" data-lang="${lang}"><i class="bi bi-link-45deg"></i> Link from variable</button>
                    <button type="button" class="btn btn-sm btn-outline-success insert-variable-button" data-lang="${lang}"><i class="bi bi-app"></i> Button from variable</button>
                </div>
                <textarea name="body_${lang}" id="editor_${lang}" class="form-control rich-editor" data-lang="${lang}"></textarea>
            </div>
        `;
        content.appendChild(pane);
        App.variables.rebuildVariableSelects();
        App.variables.rebuildConditionalSelects();
        App.editor.initRichEditor(pane.querySelector('.rich-editor'));
    };

    App.languages.removeLanguageTab = function(lang) {
        const instance = App.editor.getEditor(lang);
        if (instance) {
            instance.destroy().catch(error => {
                console.error('Failed to destroy CKEditor 5 instance', error);
            });
            delete App.state.editorInstances[lang];
        }
        document.querySelector(`#languageTabs [data-lang="${lang}"]`)?.remove();
        document.getElementById(`content-${lang}`)?.remove();
        const firstTab = document.querySelector('#languageTabs .nav-link');
        const firstPane = document.querySelector('#languageTabContent .tab-pane');
        if (firstTab) {
            firstTab.classList.add('active');
        }
        if (firstPane) {
            firstPane.classList.add('show', 'active');
        }
    };

    App.languages.initLanguageTabs = function() {
        document.querySelectorAll('.language-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const lang = this.value;
                if (!lang) {
                    return;
                }
                if (this.checked) {
                    App.languages.addLanguageTab(lang);
                } else {
                    App.languages.removeLanguageTab(lang);
                }
            });
        });
    };
})(window.App);
