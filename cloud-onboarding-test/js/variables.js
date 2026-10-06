(function(App) {
    App.variables = App.variables || {};

    App.variables.extractFromEditor = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.warn(`[Variables] No editor found for ${lang}`);
            return { variables: [], conditionals: [] };
        }
        
        const html = editor.getData();
        
        // Extract variables: **VARIABLE**
        const variables = [];
        const varMatches = html.matchAll(/\*\*([A-Za-z0-9_]+)\*\*/g);
        for (const match of varMatches) {
            const varName = match[1].toUpperCase();
            if (!variables.includes(varName)) {
                variables.push(varName);
            }
        }
        
        // Extract conditionals: [[IF CONDITIONAL]]
        const conditionals = [];
        const condMatches = html.matchAll(/\[\[IF\s+([A-Za-z0-9_]+)\s*\]\]/g);
        for (const match of condMatches) {
            const condName = match[1].toUpperCase();
            if (!conditionals.includes(condName)) {
                conditionals.push(condName);
            }
        }
        
        console.log(`[Variables] Extracted from ${lang}:`, { variables, conditionals });
        return { variables, conditionals };
    };

    App.variables.scanAllEditors = function() {
        const allVariables = new Set();
        const allConditionals = new Set();
        
        // Scan all active editors
        Object.keys(App.state.editorInstances).forEach(lang => {
            const extracted = App.variables.extractFromEditor(lang);
            extracted.variables.forEach(v => allVariables.add(v));
            extracted.conditionals.forEach(c => allConditionals.add(c));
        });
        
        console.log('[Variables] Total extracted:', { 
            variables: Array.from(allVariables), 
            conditionals: Array.from(allConditionals) 
        });
        
        return {
            variables: Array.from(allVariables),
            conditionals: Array.from(allConditionals)
        };
    };

    App.variables.syncWithEditorContent = function() {
        const extracted = App.variables.scanAllEditors();
        
        // Get currently defined variables and conditionals
        const currentVariables = Array.from(document.querySelectorAll('#variablesList .variable-item'))
            .map(item => item.dataset.varName);
        const currentConditionals = Array.from(document.querySelectorAll('#conditionalsList .variable-item'))
            .map(item => item.dataset.condName);
        
        let addedVars = 0;
        let addedConds = 0;
        
        // Add any variables found in editor that aren't in the UI
        extracted.variables.forEach(varName => {
            if (!currentVariables.includes(varName)) {
                App.variables.addVariable(varName, varName);
                addedVars++;
            }
        });
        
        // Add any conditionals found in editor that aren't in the UI
        extracted.conditionals.forEach(condName => {
            if (!currentConditionals.includes(condName)) {
                App.variables.addConditional(condName, condName);
                addedConds++;
            }
        });
        
        if (addedVars > 0 || addedConds > 0) {
            alert(`✅ Synced with editor content:\n\n• ${addedVars} variable(s) added\n• ${addedConds} conditional(s) added`);
        } else {
            alert('✅ Already in sync! No new variables or conditionals found in editor content.');
        }
    };

    App.variables.rebuildVariableSelects = function() {
        const variables = Array.from(document.querySelectorAll('#variablesList .variable-item')).map(item => {
            const name = item.dataset.varName;
            const label = item.querySelector('.variable-label')?.value || name;
            return { name, label };
        });
        App.state.cachedVariables = variables;

        document.querySelectorAll('[data-variable-select]').forEach(select => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            const placeholderText = select.dataset.placeholder || 'Select variable';
            placeholder.textContent = variables.length ? placeholderText : 'No variables yet';
            select.appendChild(placeholder);
            variables.forEach(variable => {
                const option = document.createElement('option');
                option.value = variable.name;
                option.textContent = `${variable.label} (**${variable.name}**)`;
                select.appendChild(option);
            });
        });
    };

    App.variables.rebuildConditionalSelects = function() {
        const conditionals = Array.from(document.querySelectorAll('#conditionalsList .variable-item')).map(item => {
            const name = item.dataset.condName;
            const label = item.querySelector('.conditional-label')?.value || name;
            return { name, label };
        });

        document.querySelectorAll('[data-conditional-select]').forEach(select => {
            select.innerHTML = '';
            const placeholder = document.createElement('option');
            placeholder.value = '';
            const placeholderText = select.dataset.placeholder || 'Select conditional';
            placeholder.textContent = conditionals.length ? placeholderText : 'No conditionals yet';
            select.appendChild(placeholder);
            conditionals.forEach(conditional => {
                const option = document.createElement('option');
                option.value = conditional.name;
                option.textContent = `${conditional.label} ([[IF ${conditional.name}]])`;
                select.appendChild(option);
            });
        });
    };

    App.variables.addVariable = function(name, label) {
        const clean = name.toUpperCase().replace(/[^A-Z0-9_]/g, '');
        if (!clean) {
            return;
        }
        if (document.querySelector(`#variablesList [data-var-name="${clean}"]`)) {
            console.log(`[Variables] Variable ${clean} already exists in UI`);
            return;
        }
        const html = `
            <div class="variable-item" data-var-name="${clean}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="variable-tag type-text">**${clean}**</span>
                    <button type="button" class="btn btn-sm btn-danger remove-variable"><i class="bi bi-x"></i></button>
                </div>
                <input type="text" class="form-control form-control-sm variable-label" value="${label || clean}" placeholder="Label">
            </div>
        `;
        document.getElementById('variablesList')?.insertAdjacentHTML('beforeend', html);
        console.log(`[Variables] Added variable ${clean} to UI (remember to use **${clean}** in your editor)`);
        App.variables.rebuildVariableSelects();
    };

    App.variables.addConditional = function(name, label) {
        const clean = name.toUpperCase().replace(/[^A-Z0-9_]/g, '');
        if (!clean) {
            return;
        }
        if (document.querySelector(`#conditionalsList [data-cond-name="${clean}"]`)) {
            console.log(`[Variables] Conditional ${clean} already exists in UI`);
            return;
        }
        const html = `
            <div class="variable-item" data-cond-name="${clean}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="variable-tag type-conditional">[[IF ${clean}]]</span>
                    <button type="button" class="btn btn-sm btn-danger remove-conditional"><i class="bi bi-x"></i></button>
                </div>
                <input type="text" class="form-control form-control-sm conditional-label" value="${label || clean}" placeholder="Checkbox label">
            </div>
        `;
        document.getElementById('conditionalsList')?.insertAdjacentHTML('beforeend', html);
        console.log(`[Variables] Added conditional ${clean} to UI (remember to use [[IF ${clean}]] in your editor)`);
        App.variables.rebuildConditionalSelects();
    };

    App.variables.getVariableLabel = function(name) {
        return App.state.cachedVariables.find(variable => variable.name === name)?.label || name;
    };

    App.variables.openVariableLinkModal = function(lang) {
        const linkModal = App.variables.modals?.linkModal || null;
        const linkTextInput = document.getElementById('linkTextInput');
        const linkVariableSelect = document.getElementById('linkVariableSelect');
        if (!linkModal) {
            return;
        }
        App.editor.storeSelection(lang, true);
        App.state.activeEditorLang = lang;
        if (linkTextInput) {
            linkTextInput.value = '';
        }
        if (linkVariableSelect) {
            linkVariableSelect.value = '';
        }
        linkModal.show();
    };

    App.variables.openVariableButtonModal = function(lang) {
        const buttonModal = App.variables.modals?.buttonModal || null;
        const buttonTextInput = document.getElementById('buttonTextInput');
        const buttonVariableSelect = document.getElementById('buttonVariableSelect');
        if (!buttonModal) {
            return;
        }
        App.editor.storeSelection(lang, true);
        App.state.activeEditorLang = lang;
        if (buttonTextInput) {
            buttonTextInput.value = '';
        }
        if (buttonVariableSelect) {
            buttonVariableSelect.value = '';
        }
        buttonModal.show();
    };

    App.variables.initVariableControls = function() {
        const linkVariableSelect = document.getElementById('linkVariableSelect');
        const linkTextInput = document.getElementById('linkTextInput');
        const buttonVariableSelect = document.getElementById('buttonVariableSelect');
        const buttonTextInput = document.getElementById('buttonTextInput');
        const linkModalEl = document.getElementById('linkVariableModal');
        const linkModal = linkModalEl ? new bootstrap.Modal(linkModalEl) : null;
        const buttonModalEl = document.getElementById('buttonVariableModal');
        const buttonModal = buttonModalEl ? new bootstrap.Modal(buttonModalEl) : null;
        App.variables.modals = { linkModal, buttonModal };

        document.addEventListener('click', function(event) {
            const linkButton = event.target.closest('.insert-variable-link');
            if (linkButton) {
                App.variables.openVariableLinkModal(linkButton.dataset.lang);
                return;
            }

            const buttonInsert = event.target.closest('.insert-variable-button');
            if (buttonInsert) {
                App.variables.openVariableButtonModal(buttonInsert.dataset.lang);
                return;
            }

            const variableInsert = event.target.closest('.insert-variable-inline');
            if (variableInsert) {
                const lang = variableInsert.dataset.lang;
                const select = document.querySelector(`.variable-select[data-lang="${lang}"]`);
                if (select?.value) {
                    App.editor.insertText(lang, `**${select.value}**`);
                }
                return;
            }

            const conditionalInsert = event.target.closest('.insert-conditional-inline');
            if (conditionalInsert) {
                const lang = conditionalInsert.dataset.lang;
                const select = document.querySelector(`.conditional-select[data-lang="${lang}"]`);
                if (select?.value) {
                    // IMPORTANT: Conditionals use [[IF NAME]] format WITHOUT ** around the name
                    // Only use ** for variables inside the conditional content
                    // Insert with blue notice styling (inline styles for email compatibility)
                    App.editor.insertHtml(
                        lang,
                        `[[IF ${select.value}]]<div class="single-app-notice" style="background-color:#E7F3FF; border-left:4px solid #002C5A; padding:15px; margin:20px 0;"><p><strong>Belangrijk:</strong> Update this conditional notice content.</p></div>[[ENDIF]]`
                    );
                }
                return;
            }

            const alertBoxInsert = event.target.closest('.insert-alert-box');
            if (alertBoxInsert) {
                const lang = alertBoxInsert.dataset.lang;
                console.log('[Variables] Inserting alert box for', lang);
                App.editor.insertAlertBox(lang);
                return;
            }

            const infoBoxInsert = event.target.closest('.insert-info-box');
            if (infoBoxInsert) {
                const lang = infoBoxInsert.dataset.lang;
                console.log('[Variables] Inserting info box for', lang);
                App.editor.insertInfoBox(lang);
                return;
            }

            const removeVariable = event.target.closest('.remove-variable');
            if (removeVariable) {
                const item = removeVariable.closest('.variable-item');
                const varName = item?.dataset.varName;
                
                if (varName && confirm(`Remove "${varName}" from the list?\n\nIMPORTANT: This only removes it from the UI. If you have **${varName}** in your email content, you should remove those too, or the variable will reappear when you save.`)) {
                    item.remove();
                    App.variables.rebuildVariableSelects();
                }
                return;
            }

            const removeConditional = event.target.closest('.remove-conditional');
            if (removeConditional) {
                const item = removeConditional.closest('.variable-item');
                const condName = item?.dataset.condName;
                
                if (condName && confirm(`Remove "${condName}" from the list?\n\nIMPORTANT: This only removes it from the UI. If you have [[IF ${condName}]] blocks in your email content, you should remove those too, or the conditional will reappear when you save.`)) {
                    item.remove();
                    App.variables.rebuildConditionalSelects();
                }
            }
        });

        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('variable-label')) {
                App.variables.rebuildVariableSelects();
            }
            if (event.target.classList.contains('conditional-label')) {
                App.variables.rebuildConditionalSelects();
            }
        });

        document.getElementById('addVariableBtn')?.addEventListener('click', () => {
            const name = document.getElementById('newVariableName')?.value || '';
            const label = document.getElementById('newVariableLabel')?.value || '';
            if (!name.trim()) {
                alert('Enter a variable name.');
                return;
            }
            App.variables.addVariable(name, label);
            if (document.getElementById('newVariableName')) document.getElementById('newVariableName').value = '';
            if (document.getElementById('newVariableLabel')) document.getElementById('newVariableLabel').value = '';
        });

        document.getElementById('addConditionalBtn')?.addEventListener('click', () => {
            const name = document.getElementById('newConditionalName')?.value || '';
            const label = document.getElementById('newConditionalLabel')?.value || '';
            if (!name.trim()) {
                alert('Enter a conditional name.');
                return;
            }
            App.variables.addConditional(name, label);
            if (document.getElementById('newConditionalName')) document.getElementById('newConditionalName').value = '';
            if (document.getElementById('newConditionalLabel')) document.getElementById('newConditionalLabel').value = '';
        });

        // Add handler for sync button (if it exists)
        document.getElementById('syncVariablesBtn')?.addEventListener('click', () => {
            App.variables.syncWithEditorContent();
        });

        linkVariableSelect?.addEventListener('change', () => {
            if (!linkTextInput || linkTextInput.value.trim()) {
                return;
            }
            const selected = linkVariableSelect.value;
            if (selected) {
                linkTextInput.value = App.variables.getVariableLabel(selected);
            }
        });

        buttonVariableSelect?.addEventListener('change', () => {
            if (!buttonTextInput || buttonTextInput.value.trim()) {
                return;
            }
            const selected = buttonVariableSelect.value;
            if (selected) {
                buttonTextInput.value = App.variables.getVariableLabel(selected);
            }
        });

        document.getElementById('insertVariableLinkBtn')?.addEventListener('click', () => {
            console.log('[Variables] Insert variable link button clicked');
            console.log('[Variables] Active editor lang:', App.state.activeEditorLang);
            
            if (!App.state.activeEditorLang) {
                console.error('[Variables] No active editor language set');
                alert('Error: No editor selected');
                return;
            }
            
            const editor = App.editor.getEditor(App.state.activeEditorLang);
            if (!editor) {
                console.error(`[Variables] Editor not found for ${App.state.activeEditorLang}`);
                alert('Error: Editor not initialized');
                return;
            }
            
            const variable = linkVariableSelect?.value;
            if (!variable) {
                alert('Select a variable for the link URL.');
                return;
            }
            
            const linkText = linkTextInput?.value.trim() || App.variables.getVariableLabel(variable);
            const html = `<a href="**${variable}**" style="color:#2563eb; text-decoration:underline;">${App.utils.escapeHtml(linkText)}</a>`;
            
            console.log('[Variables] Inserting link HTML:', html);
            
            // First restore selection, then insert
            App.editor.restoreSelection(App.state.activeEditorLang);
            
            // Small delay to ensure editor is focused
            setTimeout(() => {
                App.editor.insertHtml(App.state.activeEditorLang, html);
                linkModal?.hide();
            }, 100);
        });

        document.getElementById('insertVariableButtonBtn')?.addEventListener('click', () => {
            console.log('[Variables] Insert variable button clicked');
            console.log('[Variables] Active editor lang:', App.state.activeEditorLang);
            
            if (!App.state.activeEditorLang) {
                console.error('[Variables] No active editor language set');
                alert('Error: No editor selected');
                return;
            }
            
            const editor = App.editor.getEditor(App.state.activeEditorLang);
            if (!editor) {
                console.error(`[Variables] Editor not found for ${App.state.activeEditorLang}`);
                alert('Error: Editor not initialized');
                return;
            }
            
            const variable = buttonVariableSelect?.value;
            if (!variable) {
                alert('Select a variable for the button URL.');
                return;
            }
            
            const buttonText = buttonTextInput?.value.trim() || App.variables.getVariableLabel(variable);
            const url = `**${variable}**`;
            
            console.log('[Variables] Inserting button with text:', buttonText, 'and URL:', url);
            
            // First restore selection, then insert
            App.editor.restoreSelection(App.state.activeEditorLang);
            
            // Small delay to ensure editor is focused
            setTimeout(() => {
                // Try the specialized button insertion method
                App.editor.insertStyledButton(
                    App.state.activeEditorLang,
                    url,
                    App.utils.escapeHtml(buttonText)
                );
                buttonModal?.hide();
            }, 100);
        });
    };
})(window.App);
