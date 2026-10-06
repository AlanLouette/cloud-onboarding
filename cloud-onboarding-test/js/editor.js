(function(App) {
    App.editor = App.editor || {};

    const DEFAULT_EDITOR_COLORS = [
        {
            color: '#000000',
            label: 'Black'
        },
        {
            color: '#666666',
            label: 'Dark Gray'
        },
        {
            color: '#FFFFFF',
            label: 'White'
        },
        {
            color: '#D93025',
            label: 'Red'
        },
        {
            color: '#F9AB00',
            label: 'Yellow'
        },
        {
            color: '#188038',
            label: 'Green'
        },
        {
            color: '#1A73E8',
            label: 'Blue'
        },
        {
            color: '#9334E6',
            label: 'Purple'
        },
        {
            color: '#DC006B',
            label: '4BS Pink'
        },
        {
            color: '#ED7B01',
            label: '4BS Orange'
        },
        {
            color: '#5E9728',
            label: '4BS Green'
        },
        {
            color: '#002C5A',
            label: '4BS Blue'
        }
    ];

    App.editor.getEditor = function(lang) {
        return App.state.editorInstances[lang] || null;
    };

    App.editor.getStoredRange = function(lang) {
        return App.state.editorSelections[lang] || null;
    };

    App.editor.isFocused = function(editor) {
        return !!editor?.editing?.view?.document?.isFocused;
    };

    App.editor.enhanceColorDropdowns = function(root = document) {
        root.querySelectorAll('.ck-color-grid').forEach(grid => {
            if (grid.dataset.brandColorsEnhanced === '1') {
                return;
            }

            const labeledItems = Array.from(grid.querySelectorAll('[aria-label], [title]'));
            const firstBrandButton = labeledItems.find(button => {
                const label = button.getAttribute('aria-label') || button.getAttribute('title') || '';
                return label.includes('4BS ');
            });

            const buttonItems = Array.from(grid.querySelectorAll('button, .ck-button, [role="button"]'));
            const insertionTarget = (firstBrandButton && (firstBrandButton.closest('.ck-button') || firstBrandButton))
                || buttonItems[8]
                || null;

            if (!insertionTarget) {
                return;
            }

            const label = document.createElement('div');
            label.className = 'ck-4bs-color-group-label';
            label.textContent = '4BS Merkkleuren';
            insertionTarget.before(label);
            grid.dataset.brandColorsEnhanced = '1';
        });
    };

    App.editor.initColorDropdownEnhancer = function() {
        if (App.state.colorDropdownObserver || !document.body) {
            return;
        }

        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                mutation.addedNodes.forEach(node => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }

                    if (node.matches('.ck-color-grid')) {
                        App.editor.enhanceColorDropdowns(node.parentElement || node);
                        return;
                    }

                    App.editor.enhanceColorDropdowns(node);
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        App.state.colorDropdownObserver = observer;
    };


    App.editor.normalizeColorsToHexHtml = function(html) {
        if (!html) {
            return html;
        }

        const hslRegex = /hsla?\(\s*(-?\d*\.?\d+)\s*(?:deg)?\s*,\s*(-?\d*\.?\d+)%\s*,\s*(-?\d*\.?\d+)%\s*(?:,\s*(-?\d*\.?\d+)\s*)?\)/gi;
        const rgbRegex = /rgba?\(\s*([^,\)]+)\s*,\s*([^,\)]+)\s*,\s*([^,\)]+)\s*(?:,\s*([0-9]*\.?[0-9]+)\s*)?\)/gi;

        const toHexChannel = (value) => {
            const rounded = Math.max(0, Math.min(255, Math.round(value)));
            return rounded.toString(16).padStart(2, '0').toUpperCase();
        };

        const toHex = (r, g, b, a = null) => {
            let hex = `#${toHexChannel(r)}${toHexChannel(g)}${toHexChannel(b)}`;
            if (a !== null) {
                const alpha = Math.max(0, Math.min(1, a));
                hex += toHexChannel(alpha * 255);
            }
            return hex;
        };

        const parseRgbComponent = (value) => {
            const text = String(value).trim();
            if (text.endsWith('%')) {
                const percent = parseFloat(text.slice(0, -1));
                return (Math.max(0, Math.min(100, percent)) * 255) / 100;
            }
            return Math.max(0, Math.min(255, parseFloat(text)));
        };

        let normalized = html.replace(rgbRegex, (_, r, g, b, a) => {
            const alpha = a !== undefined && a !== '' ? parseFloat(a) : null;
            return toHex(parseRgbComponent(r), parseRgbComponent(g), parseRgbComponent(b), alpha);
        });

        normalized = normalized.replace(hslRegex, (_, h, s, l, a) => {
            let hue = parseFloat(h) % 360;
            if (hue < 0) hue += 360;

            const sat = Math.max(0, Math.min(100, parseFloat(s))) / 100;
            const light = Math.max(0, Math.min(100, parseFloat(l))) / 100;

            let red = light;
            let green = light;
            let blue = light;

            if (sat > 0) {
                const chroma = (1 - Math.abs(2 * light - 1)) * sat;
                const hPrime = hue / 60;
                const x = chroma * (1 - Math.abs((hPrime % 2) - 1));

                if (hPrime < 1) [red, green, blue] = [chroma, x, 0];
                else if (hPrime < 2) [red, green, blue] = [x, chroma, 0];
                else if (hPrime < 3) [red, green, blue] = [0, chroma, x];
                else if (hPrime < 4) [red, green, blue] = [0, x, chroma];
                else if (hPrime < 5) [red, green, blue] = [x, 0, chroma];
                else [red, green, blue] = [chroma, 0, x];

                const m = light - chroma / 2;
                red += m;
                green += m;
                blue += m;
            }

            const alpha = a !== undefined && a !== '' ? parseFloat(a) : null;
            return toHex(red * 255, green * 255, blue * 255, alpha);
        });

        return normalized;
    };

    App.editor.storeSelection = function(lang, force = false) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.warn(`[Editor] No editor found for language: ${lang}`);
            return;
        }
        if (!force && !App.editor.isFocused(editor)) {
            console.log(`[Editor] Editor not focused, skipping selection store for ${lang}`);
            return;
        }
        
        try {
            const selection = editor.model.document.selection;
            const range = selection.getFirstRange();
            
            // Store position data instead of range object to survive focus changes
            if (range) {
                const root = editor.model.document.getRoot();
                const startPath = range.start.path;
                const endPath = range.end.path;
                
                App.state.editorSelections[lang] = {
                    startPath: startPath.slice(),
                    endPath: endPath.slice(),
                    isCollapsed: range.isCollapsed
                };
                console.log(`[Editor] Stored selection for ${lang}:`, App.state.editorSelections[lang]);
            } else {
                App.state.editorSelections[lang] = null;
                console.log(`[Editor] No range to store for ${lang}`);
            }
        } catch (error) {
            console.error(`[Editor] Error storing selection for ${lang}:`, error);
            App.state.editorSelections[lang] = null;
        }
    };

    App.editor.getFallbackRange = function(editor) {
        if (!editor) {
            return null;
        }
        try {
            const root = editor.model.document.getRoot();
            if (!root) {
                console.error('[Editor] No root element found');
                return null;
            }
            return editor.model.createRange(
                editor.model.createPositionAt(root, 'end')
            );
        } catch (error) {
            console.error('[Editor] Error creating fallback range:', error);
            return null;
        }
    };

    App.editor.getInsertionRange = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor for ${lang}`);
            return null;
        }
        
        try {
            const storedSelection = App.state.editorSelections[lang];
            
            // Try to recreate range from stored selection data
            if (storedSelection && storedSelection.startPath) {
                const root = editor.model.document.getRoot();
                if (root) {
                    try {
                        const startPos = editor.model.createPositionFromPath(root, storedSelection.startPath);
                        const endPos = editor.model.createPositionFromPath(root, storedSelection.endPath);
                        const recreatedRange = editor.model.createRange(startPos, endPos);
                        console.log(`[Editor] Recreated range from stored selection for ${lang}`);
                        return recreatedRange;
                    } catch (error) {
                        console.warn(`[Editor] Could not recreate range from stored selection:`, error);
                    }
                }
            }
            
            // Fall back to current selection
            const selectionRange = editor.model.document.selection.getFirstRange();
            if (selectionRange) {
                console.log(`[Editor] Using current selection for ${lang}`);
                return selectionRange;
            }
            
            // Last resort: end of document
            console.log(`[Editor] Using fallback position (end of document) for ${lang}`);
            return App.editor.getFallbackRange(editor);
        } catch (error) {
            console.error(`[Editor] Error getting insertion range for ${lang}:`, error);
            return App.editor.getFallbackRange(editor);
        }
    };

    App.editor.focusEditor = function(editor) {
        if (!editor) {
            return;
        }
        editor.editing.view.focus();
    };

    App.editor.insertHtml = function(lang, html) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }
        
        try {
            console.log(`[Editor] Inserting HTML into ${lang}:`, html);
            App.editor.focusEditor(editor);
            
            editor.model.change(writer => {
                const targetRange = App.editor.getInsertionRange(lang);
                if (targetRange) {
                    writer.setSelection(targetRange);
                } else {
                    console.warn(`[Editor] No target range found for ${lang}, using current position`);
                }
                
                try {
                    const viewFragment = editor.data.processor.toView(html);
                    console.log('[Editor] View fragment created:', viewFragment);
                    
                    const modelFragment = editor.data.toModel(viewFragment);
                    console.log('[Editor] Model fragment created:', modelFragment);
                    
                    editor.model.insertContent(modelFragment);
                    console.log(`[Editor] Successfully inserted content into ${lang}`);
                    
                    // Log what was actually inserted by checking editor content
                    setTimeout(() => {
                        const currentData = editor.getData();
                        console.log('[Editor] Current editor data after insert:', currentData.substring(currentData.length - 500));
                    }, 100);
                } catch (error) {
                    console.error(`[Editor] Error converting/inserting HTML:`, error);
                    throw error;
                }
            });
            
            // Clear stored selection after successful insert
            App.state.editorSelections[lang] = null;
        } catch (error) {
            console.error(`[Editor] Error inserting HTML into ${lang}:`, error);
            alert(`Error inserting content: ${error.message}`);
        }
    };

    App.editor.insertText = function(lang, text) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }
        
        try {
            console.log(`[Editor] Inserting text into ${lang}:`, text);
            App.editor.focusEditor(editor);
            
            editor.model.change(writer => {
                const targetRange = App.editor.getInsertionRange(lang);
                if (targetRange) {
                    writer.setSelection(targetRange);
                } else {
                    console.warn(`[Editor] No target range found for ${lang}, using current position`);
                }
                
                editor.model.insertContent(writer.createText(text));
                console.log(`[Editor] Successfully inserted text into ${lang}`);
            });
            
            // Clear stored selection after successful insert
            App.state.editorSelections[lang] = null;
        } catch (error) {
            console.error(`[Editor] Error inserting text into ${lang}:`, error);
            alert(`Error inserting text: ${error.message}`);
        }
    };

    App.editor.insertStyledButton = function(lang, url, buttonText, buttonStyle) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }
        
        const style = buttonStyle || {
            backgroundColor: '#5e9728',
            color: '#ffffff',
            fontSize: '16px',
            textDecoration: 'none',
            padding: '15px 30px',
            borderRadius: '0px',
            display: 'inline-block'
        };
        
        // Build inline style string
        const styleString = Object.entries(style)
            .map(([key, value]) => {
                // Convert camelCase to kebab-case
                const cssKey = key.replace(/([A-Z])/g, '-$1').toLowerCase();
                return `${cssKey}:${value}`;
            })
            .join('; ');
        
        const buttonHtml = `<p style="text-align:center; margin:24px 0;"><a href="${url}" style="${styleString};">${buttonText}</a></p>`;
        
        console.log('[Editor] Attempting to insert styled button HTML:', buttonHtml);
        
        // Check if GeneralHtmlSupport is available
        const hasHtmlSupport = editor.plugins.has('GeneralHtmlSupport');
        console.log('[Editor] GeneralHtmlSupport available:', hasHtmlSupport);
        
        if (!hasHtmlSupport) {
            console.warn('[Editor] ⚠️ Using fallback method - directly manipulating editor data');
            // Fallback: Insert by directly manipulating the HTML
            App.editor.insertButtonViaDataManipulation(lang, buttonHtml);
            return;
        }
        
        // Try normal insertion if HTML Support is available
        try {
            App.editor.focusEditor(editor);
            
            editor.model.change(writer => {
                const targetRange = App.editor.getInsertionRange(lang);
                if (targetRange) {
                    writer.setSelection(targetRange);
                }
                
                const viewFragment = editor.data.processor.toView(buttonHtml);
                const modelFragment = editor.data.toModel(viewFragment);
                
                editor.model.insertContent(modelFragment);
                console.log('[Editor] Styled button inserted via normal method');
                
                // Log what was actually inserted
                setTimeout(() => {
                    const currentData = editor.getData();
                    const lastPart = currentData.substring(Math.max(0, currentData.length - 500));
                    console.log('[Editor] Last 500 chars of editor:', lastPart);
                }, 100);
            });
            
            App.state.editorSelections[lang] = null;
        } catch (error) {
            console.error('[Editor] Normal insertion failed, trying fallback:', error);
            App.editor.insertButtonViaDataManipulation(lang, buttonHtml);
        }
    };

    App.editor.insertAlertBox = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }
        
        // Yellow warning box with inline styles
        const html = `<div class="alert-box" style="background-color:#FFF3CD; border:1px solid #FFE69C; padding:15px; margin:20px 0; border-radius:4px;">
    <p><strong>⚠️ Opgelet:</strong> Add your warning message here.</p>
</div>`;
        
        console.log('[Editor] Inserting alert box');
        App.editor.insertHtml(lang, html);
    };

    App.editor.insertInfoBox = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }

        const html = `<div class="info-box" style="background-color:#E7F3FF; border-left:4px solid #002C5A; padding:15px; margin:20px 0;">
    <p><strong>Info:</strong> Add your informational message here.</p>
</div>`;

        console.log('[Editor] Inserting info box');
        App.editor.insertHtml(lang, html);
    };

    App.editor.insertConditionalNotice = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            alert(`Error: Editor not found for language ${lang}`);
            return;
        }
        
        // Blue conditional notice with inline styles (will be wrapped in [[IF]] block)
        const html = `<div class="single-app-notice" style="background-color:#E7F3FF; border-left:4px solid #002C5A; padding:15px; margin:20px 0;">
    <p><strong>Belangrijk:</strong> Add your conditional notice content here.</p>
</div>`;
        
        console.log('[Editor] Inserting conditional notice template');
        App.editor.insertHtml(lang, html);
    };

    App.editor.insertButtonViaDataManipulation = function(lang, buttonHtml) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor found for ${lang}`);
            return;
        }
        
        try {
            // Get current cursor position info before we lose it
            let insertMarker = `<!--INSERT_HERE_${Date.now()}-->`;
            
            // First, insert a marker at the cursor position
            editor.model.change(writer => {
                const targetRange = App.editor.getInsertionRange(lang);
                if (targetRange) {
                    writer.setSelection(targetRange);
                }
                editor.model.insertContent(writer.createText(insertMarker));
            });
            
            // Get the full HTML with the marker
            let currentData = editor.getData();
            console.log('[Editor] Current data with marker:', currentData.substring(0, 200));
            
            // Replace the marker with our styled button HTML
            const newData = currentData.replace(insertMarker, buttonHtml);
            
            console.log('[Editor] Setting new data with button...');
            
            // Set the data directly (this bypasses the filtering on input)
            editor.setData(newData);
            
            console.log('[Editor] ✅ Button inserted via data manipulation');
            
            // Verify it worked
            setTimeout(() => {
                const verifyData = editor.getData();
                if (verifyData.includes('background-color')) {
                    console.log('[Editor] ✅ Styles preserved!');
                } else {
                    console.error('[Editor] ❌ Styles were still filtered!');
                    console.log('[Editor] Current data:', verifyData.substring(verifyData.length - 500));
                }
            }, 100);
            
            App.state.editorSelections[lang] = null;
        } catch (error) {
            console.error('[Editor] Fallback insertion failed:', error);
            alert(`Error inserting button: ${error.message}`);
        }
    };

    App.editor.restoreSelection = function(lang) {
        const editor = App.editor.getEditor(lang);
        if (!editor) {
            console.error(`[Editor] No editor for ${lang}`);
            return;
        }
        
        try {
            App.editor.focusEditor(editor);
            
            editor.model.change(writer => {
                const storedSelection = App.state.editorSelections[lang];
                
                // Try to recreate range from stored selection data
                if (storedSelection && storedSelection.startPath) {
                    const root = editor.model.document.getRoot();
                    if (root) {
                        try {
                            const startPos = editor.model.createPositionFromPath(root, storedSelection.startPath);
                            const endPos = editor.model.createPositionFromPath(root, storedSelection.endPath);
                            const recreatedRange = editor.model.createRange(startPos, endPos);
                            writer.setSelection(recreatedRange);
                            console.log(`[Editor] Restored selection for ${lang}`);
                            return;
                        } catch (error) {
                            console.warn(`[Editor] Could not restore selection:`, error);
                        }
                    }
                }
                
                // Fall back to end of document
                const fallbackRange = App.editor.getFallbackRange(editor);
                if (fallbackRange) {
                    writer.setSelection(fallbackRange);
                    console.log(`[Editor] Used fallback selection for ${lang}`);
                }
            });
        } catch (error) {
            console.error(`[Editor] Error restoring selection for ${lang}:`, error);
        }
    };

    App.editor.initRichEditor = function(textarea) {
        if (!textarea) {
            console.warn('[Editor] No textarea provided for initialization');
            return null;
        }
        
        const lang = textarea.dataset.lang;
        if (!lang) {
            console.error('[Editor] Textarea missing data-lang attribute');
            return null;
        }
        
        if (App.state.editorInstances[lang]) {
            console.log(`[Editor] Editor already initialized for ${lang}`);
            return App.state.editorInstances[lang];
        }
        
        if (!window.CKEDITOR || !CKEDITOR.ClassicEditor) {
            console.error('[Editor] CKEditor not loaded');
            return null;
        }
        
        const config = {
            licenseKey: 'GPL', // Use GPL license for open source version
            toolbar: {
                items: [
                    'bold',
                    'italic',
                    'underline',
                    'fontColor',
                    'fontBackgroundColor',
                    '|',
                    'bulletedList',
                    'numberedList',
                    'alignment',
                    '|',
                    'link',
                    'insertTable',
                    'blockQuote',
                    'sourceEditing',
                    '|',
                    'undo',
                    'redo'
                ],
                shouldNotGroupWhenFull: true
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            },
            fontColor: {
                colors: DEFAULT_EDITOR_COLORS,
                colorPicker: {
                    format: 'hex'
                },
                columns: 4,
                documentColors: 10
            },
            fontBackgroundColor: {
                colors: DEFAULT_EDITOR_COLORS,
                colorPicker: {
                    format: 'hex'
                },
                columns: 4,
                documentColors: 10
            },
            // Restrictive HTML Support - only allow styles on our custom elements
            htmlSupport: {
                allow: [
                    // Allow full styling on our custom notice/alert boxes
                    {
                        name: 'div',
                        classes: ['alert-box', 'single-app-notice', 'info-box'],
                        styles: true,
                        attributes: true
                    },
                    // Allow styling on links (for buttons)
                    {
                        name: 'a',
                        styles: true,
                        attributes: true
                    },
                    // Allow paragraph with limited styles (alignment, margin - but NOT font styles)
                    {
                        name: 'p',
                        styles: {
                            'text-align': true,
                            'margin': true,
                            'margin-top': true,
                            'margin-bottom': true,
                            'margin-left': true,
                            'margin-right': true
                        }
                    },
                    // Allow other elements but NO inline styles
                    {
                        name: /^(strong|em|b|i|u|ul|ol|li|br|span|h1|h2|h3|h4|table|thead|tbody|tr|td|th)$/,
                        styles: false
                    }
                ],
                disallow: [
                    // Explicitly block font styling on generic elements
                    {
                        name: /^(p|span|strong|em|b|i|u|li)$/,
                        styles: {
                            'font-family': true,
                            'font-size': true,
                            'color': true,
                            'background-color': true
                        }
                    }
                ]
            },
            // Configure paste behavior
            clipboard: {
                // Strip most formatting when pasting from Word/browsers
                contentRules: {
                    // Remove font families
                    removeAttributes: ['font-family', 'font-size'],
                    // Keep basic formatting
                    allowedContent: 'p strong em u a[href] ul ol li br h1 h2 h3'
                }
            },
            removePlugins: [
                // Disable autoformatting to prevent **text** from becoming bold
                // This would interfere with our **VARIABLE** syntax
                'Autoformat',
                // Remove all collaboration and premium plugins
                'Comments',
                'TrackChanges',
                'TrackChangesData',
                'RealTimeCollaborativeComments',
                'RealTimeCollaborativeTrackChanges',
                'RealTimeCollaborativeRevisionHistory',
                'PresenceList',
                'RevisionHistory',
                'CollaborationCloud',
                'Pagination',
                'DocumentOutline',
                'DocumentOutlineUI',
                'DocumentOutlineEditing',
                'WProofreader',
                'MathType',
                'SlashCommand',
                'Template',
                'ExportPdf',
                'ExportWord',
                'CKBox',
                'CKFinder',
                'EasyImage',
                'TableOfContents',
                'FormatPainter',
                'PasteFromOfficeEnhanced'
            ]
        };

        console.log(`[Editor] Initializing CKEditor for ${lang}...`);
        console.log('[Editor] Config:', config);
        
        CKEDITOR.ClassicEditor.create(textarea, config)
            .then(editor => {
                App.state.editorInstances[lang] = editor;
                console.log(`[Editor] Successfully initialized editor for ${lang}`);
                App.editor.initColorDropdownEnhancer();
                
                // Check if GeneralHtmlSupport plugin is available
                const hasHtmlSupport = editor.plugins.has('GeneralHtmlSupport');
                console.log(`[Editor] GeneralHtmlSupport plugin available: ${hasHtmlSupport}`);
                
                if (!hasHtmlSupport) {
                    console.warn('[Editor] ⚠️ GeneralHtmlSupport plugin NOT available - inline styles will be filtered!');
                    console.warn('[Editor] You may need to use a CKEditor build that includes this plugin.');
                }
                
                // Log available plugins for debugging
                console.log('[Editor] Available plugins:', Array.from(editor.plugins._plugins.keys()));
                
                // Add paste event listener to clean unwanted styles
                editor.editing.view.document.on('clipboardInput', (evt, data) => {
                    const content = data.dataTransfer.getData('text/html');
                    if (!content) return;
                    
                    console.log('[Editor] Paste detected, cleaning unwanted styles...');
                    
                    // Create a temporary div to parse the HTML
                    const temp = document.createElement('div');
                    temp.innerHTML = content;
                    
                    // Remove font styling from all elements EXCEPT our custom boxes
                    const allElements = temp.querySelectorAll('*:not(.alert-box):not(.single-app-notice):not(.info-box):not(.alert-box *):not(.single-app-notice *):not(.info-box *)');
                    allElements.forEach(el => {
                        // Remove font-related inline styles
                        el.style.removeProperty('font-family');
                        el.style.removeProperty('font-size');
                        el.style.removeProperty('color');
                        el.style.removeProperty('background-color');
                        el.style.removeProperty('font-weight');
                        el.style.removeProperty('font-style');
                        
                        // Remove font-related attributes
                        el.removeAttribute('face');
                        el.removeAttribute('size');
                        el.removeAttribute('color');
                        
                        // If style attribute is empty, remove it
                        if (el.style.length === 0) {
                            el.removeAttribute('style');
                        }
                    });
                    
                    // Update the clipboard data with cleaned HTML
                    data.dataTransfer.setData('text/html', temp.innerHTML);
                    console.log('[Editor] Paste cleaned successfully');
                });
                
                // Set up selection change listener
                editor.model.document.selection.on('change:range', () => {
                    App.editor.storeSelection(lang);
                });
                
                // Log when editor gains/loses focus
                editor.editing.view.document.on('focus', () => {
                    console.log(`[Editor] Editor ${lang} gained focus`);
                });
                
                editor.editing.view.document.on('blur', () => {
                    console.log(`[Editor] Editor ${lang} lost focus`);
                });
            })
            .catch(error => {
                console.error(`[Editor] Failed to initialize CKEditor for ${lang}:`, error);
                alert(`Failed to initialize editor for ${lang}. Check console for details.`);
            });
        
        return null;
    };

    App.editor.initExistingEditors = function() {
        document.querySelectorAll('.rich-editor').forEach(textarea => {
            App.editor.initRichEditor(textarea);
        });
    };

    App.editor.diagnoseHtmlSupport = function() {
        console.log('=== CKEditor HTML Support Diagnostic ===');
        
        const editors = Object.entries(App.state.editorInstances);
        if (editors.length === 0) {
            console.log('❌ No editors initialized yet');
            return;
        }
        
        const [lang, editor] = editors[0];
        console.log(`Testing editor for language: ${lang}`);
        
        // Check if GeneralHtmlSupport plugin exists
        const hasHtmlSupport = editor.plugins.has('GeneralHtmlSupport');
        console.log(`GeneralHtmlSupport plugin: ${hasHtmlSupport ? '✅ Available' : '❌ NOT Available'}`);
        
        // Check if Autoformat is disabled (good for our use case)
        const hasAutoformat = editor.plugins.has('Autoformat');
        console.log(`Autoformat plugin: ${hasAutoformat ? '⚠️ ENABLED - may convert **text** to bold' : '✅ DISABLED - good!'}`);
        
        if (hasAutoformat) {
            console.warn('⚠️ WARNING: Autoformat plugin is enabled!');
            console.warn('This will convert **text** to bold, which interferes with **VARIABLE** syntax.');
            console.warn('Consider adding "Autoformat" to the removePlugins array in editor.js');
        }
        
        if (!hasHtmlSupport) {
            console.warn('⚠️ WARNING: Your CKEditor build does NOT include the GeneralHtmlSupport plugin!');
            console.warn('This means inline styles will be filtered out.');
            console.warn('');
            console.warn('SOLUTION: You need to use a CKEditor build that includes HTML Support.');
            console.warn('');
            console.warn('Option 1: Use CKEditor 5 Superbuild');
            console.warn('  https://cdn.ckeditor.com/ckeditor5/[version]/super-build/ckeditor.js');
            console.warn('');
            console.warn('Option 2: Create custom build at https://ckeditor.com/ckeditor-5/online-builder/');
            console.warn('  Make sure to include "General HTML Support" plugin');
            console.warn('');
            console.warn('Option 3: Use npm/yarn to install with HTML Support:');
            console.warn('  npm install @ckeditor/ckeditor5-html-support');
        } else {
            console.log('✅ HTML Support is available!');
            
            // Test if styles actually work
            console.log('Testing style preservation...');
            const testHtml = '<p style="color:red; font-size:20px;">Test</p>';
            const viewFragment = editor.data.processor.toView(testHtml);
            const modelFragment = editor.data.toModel(viewFragment);
            
            // Convert back to HTML to see what survived
            const testResult = editor.data.stringify(modelFragment);
            console.log('Input HTML:', testHtml);
            console.log('Output HTML:', testResult);
            
            if (testResult.includes('color:red') && testResult.includes('font-size:20px')) {
                console.log('✅ Inline styles are preserved!');
            } else if (testResult.includes('color') || testResult.includes('font-size')) {
                console.warn('⚠️ Some styles preserved, but filtering may still be active');
            } else {
                console.error('❌ Inline styles are being filtered despite HTML Support being available');
                console.error('Check your htmlSupport configuration');
            }
        }
        
        // List all available plugins
        console.log('');
        console.log('Available plugins:', Array.from(editor.plugins._plugins.keys()).sort());
        console.log('=== End Diagnostic ===');
    };
})(window.App);
