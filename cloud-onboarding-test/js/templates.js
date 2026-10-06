(function(App) {
    App.templates = App.templates || {};

    App.templates.buildLanguageOptions = function(selectEl, template) {
        if (!selectEl) {
            return;
        }
        selectEl.innerHTML = '';
        if (!template) {
            ['nl', 'en', 'fr'].forEach(lang => {
                const option = document.createElement('option');
                option.value = lang;
                option.textContent = lang.toUpperCase();
                selectEl.appendChild(option);
            });
            return;
        }

        template.languages.forEach(lang => {
            const option = document.createElement('option');
            option.value = lang;
            option.textContent = lang.toUpperCase();
            selectEl.appendChild(option);
        });
    };

    App.templates.buildDynamicFields = function(templateId, container) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const template = App.state.templatesData[templateId];
        if (!template) {
            return;
        }

        if (template.fields.variables.length > 0) {
            const header = document.createElement('h6');
            header.className = 'text-uppercase text-muted small mt-4';
            header.textContent = 'Text variables';
            container.appendChild(header);
        }

        template.fields.variables.forEach(variable => {
            const group = document.createElement('div');
            group.className = 'mb-3';
            group.innerHTML = `
                <label class="form-label">${variable}</label>
                <input type="text" name="variables[${variable}]" class="form-control" required>
            `;
            container.appendChild(group);
        });

        if (template.fields.toggles.length > 0) {
            const header = document.createElement('h6');
            header.className = 'text-uppercase text-muted small mt-4';
            header.textContent = 'Checkbox sections';
            container.appendChild(header);

            const toggleCard = document.createElement('div');
            toggleCard.className = 'toggle-card';
            template.fields.toggles.forEach(toggle => {
                const group = document.createElement('div');
                group.className = 'form-check mb-2';
                group.innerHTML = `
                    <input class="form-check-input" type="checkbox" name="toggles[${toggle}]" value="1" id="toggle-${toggle}">
                    <label class="form-check-label" for="toggle-${toggle}">
                        ${toggle}
                    </label>
                `;
                toggleCard.appendChild(group);
            });
            container.appendChild(toggleCard);
        }
    };

    App.templates.updateBulkColumnsHint = function(templateId, hintEl) {
        if (!hintEl) {
            return;
        }
        const template = App.state.templatesData[templateId];
        if (!template) {
            hintEl.textContent = 'Select a template to see the required CSV columns.';
            return;
        }
        const columns = ['email', 'language']
            .concat(template.fields.variables.map(v => v.toLowerCase()))
            .concat(template.fields.toggles.map(v => v.toLowerCase()));
        hintEl.innerHTML = `Required columns: <code>${columns.join(', ')}</code>`;
    };
})(window.App);
