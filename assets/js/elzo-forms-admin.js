// TinyMCE initialization
function ElzoFormsInitTinyMCE(scope = document) {
    if (typeof tinymce === 'undefined') {
        console.error('TinyMCE is not defined.');
        return;
    }

    const searchScope = scope && typeof scope.querySelectorAll === 'function' ? scope : document;

    // Get all tinymce textareas (.elzo-forms-tinymce-field-control)
    const tinymceFields = Array.from(searchScope.querySelectorAll('.elzo-forms-tinymce-field-control')).filter(tinymceField => {
        return true;
    });

    // Check if there are any tinymce textareas
    if (tinymceFields.length > 0) {
        // Loop through the tinymce textareas
        tinymceFields.forEach(tinymceField => {
            // Get the field ID
            let fieldId = tinymceField.getAttribute('id');

            if (!fieldId) {
                const idTemplate = tinymceField.getAttribute('data-id-template');

                if (idTemplate) {
                    fieldId = idTemplate;
                    tinymceField.setAttribute('id', fieldId);
                } else {
                    const fieldName = (tinymceField.getAttribute('name') || 'elzo-forms-tinymce-field').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-+|-+$/g, '');
                    fieldId = fieldName !== '' ? fieldName : 'elzo-forms-tinymce-field';

                    let suffix = 1;
                    let candidateId = fieldId;
                    while (document.getElementById(candidateId)) {
                        suffix += 1;
                        candidateId = fieldId + '-' + suffix;
                    }

                    fieldId = candidateId;
                    tinymceField.setAttribute('id', fieldId);
                }
            }

            if (tinymce.get(fieldId)) {
                return;
            }

            // Initialize tinyMCE
            tinymce.init({
                target: tinymceField,
                menubar: false,
                toolbar: 'formatselect | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | link | h2 h3 blockquote',
                plugins: 'link lists',
                height: 240,
                setup: function(editor) {
                    editor.on('change input undo redo', function() {
                        editor.save();
                    });
                }
            });
        });
    }
}

function ElzoFormsSyncTinyMCEValues(scope) {
    if (!scope || typeof scope.querySelectorAll !== 'function' || typeof tinymce === 'undefined') {
        return;
    }

    scope.querySelectorAll('.elzo-forms-tinymce-field-control').forEach(tinymceField => {
        const fieldId = tinymceField.getAttribute('id');
        const editor = fieldId ? tinymce.get(fieldId) : null;

        if (editor) {
            tinymceField.value = editor.getContent();
        }
    });
}

function ElzoFormsDestroyTinyMCE(scope) {
    if (!scope || typeof tinymce === 'undefined') {
        return;
    }

    const fields = [];
    if (scope.classList && scope.classList.contains('elzo-forms-tinymce-field-control')) {
        fields.push(scope);
    }
    if (typeof scope.querySelectorAll === 'function') {
        scope.querySelectorAll('.elzo-forms-tinymce-field-control').forEach(field => fields.push(field));
    }

    fields.forEach(tinymceField => {
        const fieldId = tinymceField.getAttribute('id');
        const editor = fieldId ? tinymce.get(fieldId) : null;
        if (!editor) {
            return;
        }

        if (typeof editor.save === 'function') {
            editor.save();
        }
        if (typeof editor.remove === 'function') {
            editor.remove();
        } else if (typeof tinymce.remove === 'function') {
            tinymce.remove(editor);
        }
    });
}

function ElzoFormsSetRichTextHtmlMode(toggle, editAsHtml) {
    if (!toggle) {
        return;
    }

    const group = toggle.closest('.elzo-forms-rich-text-control, .elzo-forms-field-control-group');
    const textareaField = group ? group.querySelector('textarea.elzo-forms-field-control') : null;
    if (!textareaField) {
        return;
    }

    if (editAsHtml) {
        ElzoFormsDestroyTinyMCE(textareaField);
        textareaField.classList.remove('elzo-forms-tinymce-field-control');
        textareaField.style.display = '';
        textareaField.removeAttribute('aria-hidden');
    } else {
        textareaField.classList.add('elzo-forms-tinymce-field-control');
        textareaField.style.display = '';
        ElzoFormsInitTinyMCE(group);
    }

    textareaField.dispatchEvent(new Event('input', { bubbles: true }));
    textareaField.dispatchEvent(new Event('change', { bubbles: true }));
}

function ElzoFormsPrepareTinyMCEClone(scope) {
    if (!scope || typeof scope.querySelectorAll !== 'function') {
        return;
    }

    scope.querySelectorAll('.mce-container, .tox-tinymce').forEach(editorContainer => {
        const textarea = editorContainer.previousElementSibling && editorContainer.previousElementSibling.classList.contains('elzo-forms-tinymce-field-control')
            ? editorContainer.previousElementSibling
            : editorContainer.parentElement && editorContainer.parentElement.querySelector('.elzo-forms-tinymce-field-control');

        if (textarea) {
            textarea.style.display = '';
            textarea.removeAttribute('aria-hidden');
        }

        editorContainer.remove();
    });
}
// Hex to RGBA
function ElzoFormsHexToRgba(hex, alpha) {
    let r = 0, g = 0, b = 0;
    if (hex.length == 4) {
        r = parseInt(hex[1] + hex[1], 16);
        g = parseInt(hex[2] + hex[2], 16);
        b = parseInt(hex[3] + hex[3], 16);
    } else if (hex.length == 7) {
        r = parseInt(hex[1] + hex[2], 16);
        g = parseInt(hex[3] + hex[4], 16);
        b = parseInt(hex[5] + hex[6], 16);
    }
    return `rgba(${r},${g},${b},${alpha})`;
}

// Hex darker
function ElzoFormsHexDarker(hex, percent) {
    // Ensure the hex value is valid
    if (hex.length !== 7 || hex[0] !== '#') {
        throw new Error('Invalid hex color');
    }

    // Parse the hex color
    const f = parseInt(hex.slice(1), 16);
    const t = percent < 0 ? 0 : 255;
    const p = Math.abs(percent);
    const R = f >> 16;
    const G = (f >> 8) & 0x00FF;
    const B = f & 0x0000FF;

    // Calculate the new color values
    const newR = Math.round((t - R) * p) + R;
    const newG = Math.round((t - G) * p) + G;
    const newB = Math.round((t - B) * p) + B;

    // Return the new hex color
    return `#${(0x1000000 + (newR * 0x10000) + (newG * 0x100) + newB).toString(16).slice(1).toUpperCase()}`;
}

// Hex hover
function ElzoFormsHexHover(hex, percent) {
    // Ensure the hex value is valid
    if (hex.length !== 7 || hex[0] !== '#') {
        throw new Error('Invalid hex color');
    }

    // Check if color dark or light
    const R = parseInt(hex.slice(1, 3), 16);
    const G = parseInt(hex.slice(3, 5), 16);
    const B = parseInt(hex.slice(5, 7), 16);

    // Calculate the brightness
    const brightness = ((R * 299) + (G * 587) + (B * 114)) / 1000;

    // Calculate the hover color
    if (brightness < 128) {
        return ElzoFormsHexDarker(hex, percent);
    } else {
        return ElzoFormsHexDarker(hex, -percent * 0.65);
    }
}

// Tooltip
function ElzoFormsTooltip(element, tooltipText) {
    // Exit if no element is passed
    if (!element) return;

    // Remove all confirmation tooltips and classes
    ElzoFormsRemoveConfirmationElements();

    // Create tooltip element
    const tooltip = document.createElement('div');
    tooltip.className = 'elzo-forms-tooltip';
    tooltip.textContent = tooltipText;
    document.body.appendChild(tooltip);

    // Position tooltip
    function positionTooltip() {
        const rect = element.getBoundingClientRect();
        tooltip.style.left = `${rect.left + window.scrollX + rect.width / 2 - tooltip.offsetWidth / 2}px`;
        tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight - 5}px`;
    }

    // Show tooltip
    tooltip.style.display = 'block';
    positionTooltip();

    // Position tooltip on scroll and resize
    window.addEventListener('scroll', positionTooltip);
    window.addEventListener('resize', positionTooltip);
}

// Fade out animation
function ElzoFormsFadeOut(element, duration = 300) {
    element.style.transition = `opacity ${duration}ms`;
    element.style.opacity = 0;

    setTimeout(() => {
        element.style.display = 'none';
    }, duration);
}

// Remove confirmation tooltip and class
function ElzoFormsRemoveConfirmationElements() {
    // Remove all confirmation tooltips
    const confirmationTooltips = document.querySelectorAll('.elzo-forms-tooltip');
    confirmationTooltips.forEach(tooltip => tooltip.remove());

    // Remove all waiting confirmation classes
    const waitingConfirmationButtons = document.querySelectorAll('.elzo-forms-waiting-confirmation');
    waitingConfirmationButtons.forEach(button => button.classList.remove('elzo-forms-waiting-confirmation'));
}

// Toggle field
function ElzoFormsToggleField(field) {
    // Exit if no field is passed
    if (!field) return;

    // Exit if it is not a field
    if (!field.classList.contains('elzo-forms-field')) return;

    // Select the field body and icon
    const body = field.querySelector('.elzo-forms-field-body');
    const icon = field.querySelector('.elzo-forms-field-toggle-icon');

    if (field.classList.contains('active')) {
        field.classList.remove('active');
        body.style.display = 'none';
        icon.classList.remove('elzo-icon-chevron-up');
        icon.classList.add('elzo-icon-chevron-down');
    } else {
        field.classList.add('active');
        body.style.display = 'block';
        icon.classList.remove('elzo-icon-chevron-down');
        icon.classList.add('elzo-icon-chevron-up');
    }
}

// Toggle all fields in a wrapper (expand or collapse)
function ElzoFormsToggleAll(wrapper, targetSelector, expand) {
    if (!wrapper) return;
    wrapper.querySelectorAll(targetSelector).forEach(function(field) {
        const isActive = field.classList.contains('active');
        if (expand && !isActive) ElzoFormsToggleField(field);
        else if (!expand && isActive) ElzoFormsToggleField(field);
    });
}

// Handle toggling for field-like cards.
function ElzoFormsHandleCardToggleClick(target) {
    if (!target) return false;

    const explicitToggleSelectors = [
        '.elzo-forms-field-toggle-button, .elzo-forms-field-header-toggler',
    ];
    const explicitToggleTrigger = target.closest(explicitToggleSelectors.join(', '));

    if (explicitToggleTrigger) {
        ElzoFormsToggleField(explicitToggleTrigger.closest('.elzo-forms-field'));
        return true;
    }

    const header = target.closest('.elzo-forms-field-header');
    if (!header) return false;

    // Keep native interactions intact for controls inside the header.
    if (target.closest('button, a, input, select, textarea, label, .elzo-forms-sortable-dragger')) {
        return false;
    }

    ElzoFormsToggleField(header.closest('.elzo-forms-field'));
    return true;
}

// Color Picker Update Function
function ElzoFormsUpdateColorPicker(colorPickerWrapper, colorPickerValue){
    // Exit if no color picker wrapper is passed
    if (!colorPickerWrapper) return;

    const colorPicker = colorPickerWrapper.querySelector('.elzo-color-picker');
    const facadePreview = colorPickerWrapper.querySelector('.elzo-color-picker-facade-preview');
    const colorPickerInput = colorPickerWrapper.querySelector('.elzo-color-picker-value');
    const colorPickerInputDefault = colorPickerInput.getAttribute('placeholder');
    const colorPickerInputDark = colorPickerWrapper.querySelector('.elzo-color-picker-value-dark');
    const colorPickerInputLight = colorPickerWrapper.querySelector('.elzo-color-picker-value-light');
    const colorPickerInputHover = colorPickerWrapper.querySelector('.elzo-color-picker-value-hover');
    const colorPickerInput50 = colorPickerWrapper.querySelector('.elzo-color-picker-value-50');
    const colorPickerInput25 = colorPickerWrapper.querySelector('.elzo-color-picker-value-25');
    const opacityInput = colorPickerWrapper.querySelector('.elzo-color-picker-opacity');
    const clearButton = colorPickerWrapper.querySelector('.elzo-color-picker-facade-clear');
    const colorString = typeof colorPickerValue !== 'undefined' && colorPickerValue ? colorPickerValue : ( colorPicker.value ? colorPicker.value : colorPickerInputDefault );
    
    let newColorString = colorString;
    const opacity = !opacityInput || opacityInput.value == '' ? 1 : opacityInput.value / 100;

    if(opacity < 1){
        newColorString = ElzoFormsHexToRgba(colorString, opacity);
    }

    // Change preview color
    facadePreview.style.backgroundColor = newColorString;

    // Update the input value
    colorPickerInput.value = newColorString;

    // Update the input HEX value
    if(colorPicker) colorPicker.value = colorString;

    // Update the clear button visibility
    clearButton.style.display = newColorString !== colorPickerInputDefault ? 'inline-block' : 'none';

    // Update alpha 50% value if exists
    if(colorPickerInput50) colorPickerInput50.value = ElzoFormsHexToRgba(newColorString, 0.5);

    // Update alpha 25% value if exists
    if(colorPickerInput25) colorPickerInput25.value = ElzoFormsHexToRgba(newColorString, 0.25);

    // Update dark value if exists
    if(colorPickerInputDark) colorPickerInputDark.value = ElzoFormsHexDarker(newColorString, -0.025);

    // Update light value if exists
    if(colorPickerInputLight) colorPickerInputLight.value = ElzoFormsHexDarker(newColorString, 0.9);

    // Update hover value if exists
    if(colorPickerInputHover) colorPickerInputHover.value = ElzoFormsHexHover(newColorString, 0.1);
}

function ElzoFormsAddSubmissionSpamStatusOptions() {
    const spamStatus = (window.ElzoFormsAdmin && window.ElzoFormsAdmin.submissionSpamStatus)
        ? window.ElzoFormsAdmin.submissionSpamStatus
        : null;

    if (!spamStatus || !spamStatus.isSubmissionScreen || !spamStatus.spamLabel) {
        return;
    }

    const spamLabel = String(spamStatus.spamLabel);

    function addSpamOption(statusSelect) {
        if (!statusSelect || statusSelect.querySelector('option[value="spam"]')) {
            return;
        }

        const spamOption = document.createElement('option');
        spamOption.value = 'spam';
        spamOption.textContent = spamLabel;
        statusSelect.appendChild(spamOption);
    }

    const statusSelect = document.getElementById('post_status');
    if (statusSelect) {
        addSpamOption(statusSelect);

        if (String(spamStatus.currentPostStatus || '') === 'spam') {
            statusSelect.value = 'spam';

            const postStatusDisplay = document.getElementById('post-status-display');
            if (postStatusDisplay) {
                postStatusDisplay.textContent = spamLabel;
            }
        }
    }

    document.querySelectorAll('.inline-edit-status select').forEach(addSpamOption);
}

function ElzoFormsLockJsonBackedFormEditor() {
    const lockedBox = document.querySelector('.elzo-forms-json-readonly-box');
    const postForm = document.getElementById('post');

    if (!lockedBox || !postForm) {
        return;
    }

    const lockedLabel = lockedBox.getAttribute('data-locked-label') || 'Read-only';
    const lockedTitle = lockedBox.getAttribute('data-locked-title') || lockedLabel;
    const publishButton = document.getElementById('publish');

    postForm.classList.add('elzo-forms-json-locked');

    if (publishButton) {
        if ('value' in publishButton) {
            publishButton.value = lockedLabel;
        } else {
            publishButton.textContent = lockedLabel;
        }

        publishButton.setAttribute('title', lockedTitle);
    }

    postForm.querySelectorAll('input:not([type="hidden"]), textarea, select, button').forEach(function(control) {
        control.disabled = true;
        control.setAttribute('aria-disabled', 'true');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    // Get Elzo Forms texts
    const ElzoFormsTexts = (window.ElzoFormsAdmin && window.ElzoFormsAdmin.texts)
        ? window.ElzoFormsAdmin.texts
        : {};
    ElzoFormsAddSubmissionSpamStatusOptions();
    
    // Initialize tinyMCE on page load
    ElzoFormsInitTinyMCE();

    /*
     * Re-point a DOM ID (or a "for" / conditional-logic reference) from the field
     * ID it was built with to the field ID the field carries now.
     *
     * Field IDs are not necessarily numeric: forms created outside the admin UI
     * can use readable string IDs such as "contact_email". IDs are therefore
     * swapped as whole "-{id}" tokens instead of by stripping trailing digits,
     * which silently left string IDs behind.
     */
    function ElzoFormsSwapFieldIdToken(value, previousFieldId, fieldId) {
        if (typeof value !== 'string' || value === '') return value;

        const previousToken = String(previousFieldId);
        const nextToken = String(fieldId);

        if (previousToken === '' || previousToken === nextToken) return value;

        // Trailing token: "elzo-forms-field-label-{id}"
        if (value.endsWith('-' + previousToken)) {
            return value.slice(0, value.length - previousToken.length) + nextToken;
        }

        // Embedded token: "elzo-forms-field-width-{id}-{keypoint}"
        const embeddedToken = '-' + previousToken + '-';
        const embeddedAt = value.indexOf(embeddedToken);

        if (embeddedAt !== -1) {
            return value.slice(0, embeddedAt + 1) + nextToken + value.slice(embeddedAt + embeddedToken.length - 1);
        }

        return value;
    }

    // Repeater Update Function
    function ElzoFormsUpdateRepeaterFields(repeater) {
        // Get template field ID
        const template = document.getElementById('elzo-forms-repeater-field-template');
        const templateField = template ? template.querySelector('.elzo-forms-field') : null;
        const templateFieldId = templateField ? String(templateField.getAttribute('data-id') || '') : '';

        /*
         * Field IDs already in use, compared as strings. Parsing them as integers
         * turned every non-numeric ID into NaN, so all of those fields ended up
         * sharing a single "NaN" ID: duplicated DOM IDs in the editor and, once
         * saved, a form whose fields all render as a copy of the first one.
         */
        const usedFieldIDs = new Set();

        if (templateFieldId !== '') {
            usedFieldIDs.add(templateFieldId);
        }

        // Generated IDs continue above the highest numeric ID in the form, so a
        // replacement ID can never collide with an ID a later field still holds.
        let lastGeneratedFieldId = 0;

        const knownNumericIDs = [templateFieldId].concat(
            Array.from(repeater.querySelectorAll('.elzo-forms-field'), function(field) {
                return String(field.getAttribute('data-id') || '');
            })
        );

        knownNumericIDs.forEach(function(knownId) {
            const numericId = parseInt(knownId, 10);

            if (Number.isSafeInteger(numericId) && numericId > lastGeneratedFieldId) {
                lastGeneratedFieldId = numericId;
            }
        });

        function ElzoFormsGenerateFieldId() {
            do {
                lastGeneratedFieldId++;
            } while (usedFieldIDs.has(String(lastGeneratedFieldId)));

            return String(lastGeneratedFieldId);
        }

        // Get all steps and count them
        const steps = repeater.querySelectorAll('.elzo-forms-step');
        const stepsCount = steps.length;

        // Track the running field index across steps
        let runningFieldIndex = 1;

        // Check if there are any steps
        if(stepsCount > 0) {
            steps.forEach(function(step, stepIndex) {
                // Add 1 to the step index to start from 1
                stepIndex += 1;

                const stepIndexValues = step.querySelectorAll('.step-index-value');
                const fields = step.querySelectorAll('.elzo-forms-field');
                const fieldIndexValues = step.querySelectorAll('.field-index-value');
                const fieldIndexTexts = step.querySelectorAll('.field-index-text');
                const stepFields = step.querySelectorAll('.elzo-forms-step-field');
                const emptyMessage = step.querySelector('.elzo-forms-fields-empty-message');

                if(stepIndexValues.length > 0) {
                    stepIndexValues.forEach(function(stepIndexValue) {
                        // Update the step index value
                        stepIndexValue.value = stepIndex;
                    });
                }

                // For this step, assign field indexes starting from runningFieldIndex
                if(fields.length > 0) {
                    fields.forEach(function(field, fieldIndex) {
                        // The fieldIndex for this field is runningFieldIndex
                        const currentFieldIndex = runningFieldIndex;

                        // Get field ID, keeping non-numeric IDs as they are
                        const previousFieldId = String(field.getAttribute('data-id') || '');
                        let fieldId = previousFieldId;

                        // Ensure field ID is unique
                        if(fieldId === '' || usedFieldIDs.has(fieldId)) {
                            fieldId = ElzoFormsGenerateFieldId();
                        }

                        // Track the (potentially new) fieldId to keep IDs unique
                        usedFieldIDs.add(fieldId);

                        // Update the field data ID
                        field.setAttribute('data-id', fieldId);

                        // Update the field ID
                        field.setAttribute('id', 'elzo-forms-field-' + fieldId);

                        // Select input, select, and textarea elements within the field
                        const elements = field.querySelectorAll('input, select, textarea');

                        // Select labels
                        const labels = field.querySelectorAll('.elzo-forms-field-control-label');
                        
                        // Select elements with data-ef-logic attribute
                        const dataLogicElements = field.querySelectorAll('[data-ef-logic]');

                        // Select conditional logic group elements
                        const logicGroups = field.querySelectorAll('.elzo-forms-field-logic-group');

                        if(elements.length > 0) {
                            elements.forEach(function(element) {
                                const name = element.getAttribute('name');

                                if (name) {
                                    // This pattern matches the first two step index and field index placeholders
                                    const pattern = /^(elzo_form_fields)\[\d+\]\[fields\]\[\d+\]/;
                                    const replacement = `$1[${stepIndex}][fields][${currentFieldIndex}]`;

                                    // Replace the matched pattern with the new indices
                                    const newName = name.replace(pattern, replacement);

                                    // Update the name attribute
                                    element.setAttribute('name', newName);
                                }

                                const id = element.getAttribute('id');

                                if (id) {
                                    // Re-point the element ID at the field ID it belongs to
                                    const newId = ElzoFormsSwapFieldIdToken(id, previousFieldId, fieldId);

                                    // Update the id attribute
                                    if (newId !== id) {
                                        element.setAttribute('id', newId);
                                    }
                                }

                                // Update the field index value
                                if(element.classList.contains('field-index-value')) {
                                    element.value = currentFieldIndex;
                                }

                                // Update the field ID value
                                if(element.classList.contains('field-id-value')) {
                                    element.value = fieldId;
                                }
                            });
                        }

                        if(labels.length > 0) {
                            labels.forEach(function(label) {
                                const forAttr = label.getAttribute('for');

                                if (forAttr) {
                                    // Re-point the label at the field ID it belongs to
                                    const newForAttr = ElzoFormsSwapFieldIdToken(forAttr, previousFieldId, fieldId);

                                    // Update the for attribute
                                    if (newForAttr !== forAttr) {
                                        label.setAttribute('for', newForAttr);
                                    }
                                }
                            });
                        }

                        // Update data-ef-logic attributes
                        if(dataLogicElements.length > 0) {
                            dataLogicElements.forEach(function(dataLogicElement) {
                                const dataLogic = dataLogicElement.getAttribute('data-ef-logic');
                                
                                if (dataLogic) {
                                    try {
                                        // Parse the JSON data-ef-logic
                                        let conditionGroups = JSON.parse(dataLogic);
                                        
                                        /*
                                         * A condition names its field through settings.field_id,
                                         * or, for the internal input condition, through settings.id.
                                         * Both reference DOM IDs of controls inside this very field,
                                         * so they follow the field ID rather than the last hyphen:
                                         * string field IDs may contain hyphens.
                                         */
                                        conditionGroups.forEach(function(group) {
                                            group.forEach(function(condition) {
                                                const idKey = condition?.settings && typeof condition.settings.field_id === 'string' ? 'field_id'
                                                    : condition?.settings && typeof condition.settings.id === 'string' ? 'id'
                                                    : null;
                                                if (!idKey || condition.settings[idKey] === '') {
                                                    return;
                                                }

                                                condition.settings[idKey] = ElzoFormsSwapFieldIdToken(condition.settings[idKey], previousFieldId, fieldId);
                                            });
                                        });
                                        
                                        // Update the data-ef-logic attribute with the new JSON
                                        dataLogicElement.setAttribute('data-ef-logic', JSON.stringify(conditionGroups));
                                        
                                    } catch (e) {
                                        console.error('Error parsing data-ef-logic JSON during update:', e);
                                    }
                                }
                            });
                        }

                        if(logicGroups.length > 0) {
                            logicGroups.forEach(function(logicGroup, logicGroupIndex) {
                                // Add 1 to the logic group index to start from 1
                                logicGroupIndex += 1;

                                // Select conditional logic group rules
                                const logicGroupRules = logicGroup.querySelectorAll('.elzo-forms-field-logic-group-rule');

                                // Check if there are any rules
                                if(logicGroupRules.length > 0) {
                                    logicGroupRules.forEach(function(logicGroupRule, logicGroupRuleIndex) {
                                        // Add 1 to the logic group rule index to start from 1
                                        logicGroupRuleIndex += 1;

                                        // Select input, select, and textarea elements within the logic group rule
                                        const logicGroupRuleFields = logicGroupRule.querySelectorAll('input, select, textarea');

                                        // Check if there are any fields
                                        if(logicGroupRuleFields.length > 0) {
                                            logicGroupRuleFields.forEach(function(logicGroupRuleField) {
                                                const name = logicGroupRuleField.getAttribute('name');

                                                if (name) {
                                                    // This pattern matches the first two step index and field index placeholders
                                                    const pattern = /^(elzo_form_fields)\[\d+\]\[fields\]\[\d+\]\[rules\]\[\d+\]\[\d+\]/;
                                                    const replacement = `$1[${stepIndex}][fields][${currentFieldIndex}][rules][${logicGroupIndex}][${logicGroupRuleIndex}]`;

                                                    // Replace the matched pattern with the new indices
                                                    const newName = name.replace(pattern, replacement);

                                                    // Update the name attribute
                                                    logicGroupRuleField.setAttribute('name', newName);
                                                }
                                            });
                                        }
                                    });
                                }
                            });
                        }

                        // Update the field header title
                        ElzoFormsBuildFieldHeaderTitle(field);

                        // Update field-index-text for this field
                        const fieldIndexText = field.querySelector('.field-index-text');
                        if(fieldIndexText) {
                            fieldIndexText.textContent = currentFieldIndex;
                        }

                        // Increment runningFieldIndex for next field
                        runningFieldIndex++;
                    });
                }
                
                if (emptyMessage) {
                    emptyMessage.style.display = fields.length > 0 ? 'none' : 'block';
                }
            });
        }

        const removeStepButtons = document.querySelectorAll('.elzo-forms-step-remove-button');
        const stepHeaders = document.querySelectorAll('.elzo-forms-step-header');

        if(stepsCount > 1) {
            // Show the remove step button
            removeStepButtons.forEach(function(button) {
                button.style.display = 'inline-block';
            });

            // Show the step headers
            stepHeaders.forEach(function(header) {
                header.style.display = 'block';
            });
        } else {
            // Hide the remove step button
            removeStepButtons.forEach(function(button) {
                button.style.display = 'none';
            });

            // Hide the step headers
            stepHeaders.forEach(function(header) {
                header.style.display = 'none';
            });
        }

        // Keep field keys normalized and unique whenever fields are re-indexed.
        ElzoFormsRefreshAllFieldKeys();

        // Update conditional logic dropdowns after repeater update
        ElzoFormsFieldSelects();

        // Refresh logic elements
        document.dispatchEvent(new CustomEvent('elzo-forms-refresh-logic-elements'));
    }

    function ElzoFormsTransliterateFieldKey(value) {
        const map = {
            'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'ґ': 'g', 'д': 'd', 'е': 'e', 'є': 'ie', 'ё': 'e',
            'ж': 'zh', 'з': 'z', 'и': 'i', 'і': 'i', 'ї': 'i', 'й': 'i', 'к': 'k', 'л': 'l', 'м': 'm',
            'н': 'n', 'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't', 'у': 'u', 'ф': 'f', 'х': 'kh',
            'ц': 'ts', 'ч': 'ch', 'ш': 'sh', 'щ': 'shch', 'ъ': '', 'ы': 'y', 'ь': '', 'э': 'e',
            'ю': 'yu', 'я': 'ya',
        };

        return String(value || '').replace(/[А-Яа-яЁёЄєІіЇїҐґ]/g, function(ch) {
            const lower = ch.toLowerCase();
            const transliterated = map[lower] || '';
            return ch === lower ? transliterated : transliterated.charAt(0).toUpperCase() + transliterated.slice(1);
        });
    }

    function ElzoFormsSlugifyFieldKey(value) {
        const maxLength = 64;

        if (typeof value !== 'string') {
            value = String(value || '');
        }

        const original = value.trim();

        let output = ElzoFormsTransliterateFieldKey(value).toLowerCase().trim();

        if (output.normalize) {
            output = output.normalize('NFKD').replace(/[\u0300-\u036f]/g, '');
        }

        output = output.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');

        if (!output) {
            const hasNonAscii = /[^\x00-\x7F]/.test(original);

            if (hasNonAscii) {
                let encodedHex = '';

                if (typeof TextEncoder !== 'undefined') {
                    const bytes = new TextEncoder().encode(original);
                    encodedHex = Array.from(bytes).map(byte => byte.toString(16).padStart(2, '0')).join('');
                } else {
                    encodedHex = encodeURIComponent(original).replace(/%/g, '').toLowerCase();
                }

                if (encodedHex) {
                    output = 'field-' + encodedHex.slice(0, 24);
                }
            }
        }

        if (!output) {
            output = 'field';
        }

        if (output.length > maxLength) {
            output = output.slice(0, maxLength).replace(/-+$/g, '');
        }

        return output;
    }

    function ElzoFormsBuildFieldKeySource(field) {
        if (!field) return 'field';

        const fieldId = field.getAttribute('data-id') || '';
        const adminLabelInput = document.getElementById('elzo-forms-field-admin-label-' + fieldId);
        const labelInput = document.getElementById('elzo-forms-field-label-' + fieldId);
        const placeholderInput = document.getElementById('elzo-forms-field-placeholder-' + fieldId);

        const adminLabel = adminLabelInput ? adminLabelInput.value.trim() : '';
        const label = labelInput ? labelInput.value.trim() : '';
        const placeholder = placeholderInput ? placeholderInput.value.trim() : '';

        return adminLabel || label || placeholder || ('field-' + fieldId);
    }

    function ElzoFormsMakeUniqueFieldKey(baseKey, usedKeys) {
        const maxLength = 64;
        let normalizedBaseKey = baseKey || 'field';

        if (normalizedBaseKey.length > maxLength) {
            normalizedBaseKey = normalizedBaseKey.slice(0, maxLength).replace(/-+$/g, '');
        }

        if (!normalizedBaseKey) {
            normalizedBaseKey = 'field';
        }

        let candidate = normalizedBaseKey;
        let suffix = 1;

        while (usedKeys[candidate]) {
            const suffixPart = '-' + suffix;
            const availableBaseLength = Math.max(1, maxLength - suffixPart.length);
            let candidateBase = normalizedBaseKey.slice(0, availableBaseLength).replace(/-+$/g, '');

            if (!candidateBase) {
                candidateBase = 'field'.slice(0, availableBaseLength).replace(/-+$/g, '');
                if (!candidateBase) {
                    candidateBase = 'f';
                }
            }

            candidate = candidateBase + suffixPart;
            suffix += 1;
        }

        usedKeys[candidate] = true;

        return candidate;
    }

    function ElzoFormsRefreshAllFieldKeys() {
        const usedKeys = {};
        const fields = document.querySelectorAll('.elzo-forms-form-fields .elzo-forms-field');

        fields.forEach(function(field) {
            const keyInput = field.querySelector('.elzo-forms-field-key');
            if (!keyInput) {
                return;
            }

            const isManual = keyInput.dataset.fieldKeyManual === '1';
            const currentValue = keyInput.value ? keyInput.value.trim() : '';
            const baseSource = (!isManual || currentValue === '')
                ? ElzoFormsBuildFieldKeySource(field)
                : currentValue;
            const baseKey = ElzoFormsSlugifyFieldKey(baseSource) || 'field';

            keyInput.value = ElzoFormsMakeUniqueFieldKey(baseKey, usedKeys);
        });
    }
    // Page and URL rules are typed into a plain value input; the placeholder is the
    // only hint about the expected format. Mirrored in admin-logic-item.php so the
    // hint is present before the first change event.
    function ElzoFormsLogicValuePlaceholder(type, operator) {
        if (type === 'page') return String(operator).startsWith('post_type_') ? 'page, post' : '12, 15';
        if (type === 'url') return 'https://example.com/pricing/';

        return '';
    }

    /**
     * Refresh UI of a single field-visibility condition item after type or operator changes.
     * Mirrors ElzoFormsRefreshAutomationConditionItem but for .elzo-forms-field-logic-condition-item elements.
     */
    function ElzoFormsRefreshFieldLogicItem(item) {
        if (!item) return;

        const typeSelect = item.querySelector('.elzo-forms-field-logic-condition-type-select');
        if (!typeSelect) return;

        const type           = typeSelect.value;
        const operatorsMap   = JSON.parse(item.getAttribute('data-operators-map')   || '{}');
        const operatorLabels = JSON.parse(item.getAttribute('data-operator-labels') || '{}');
        const operatorSelect = item.querySelector('.elzo-forms-field-logic-condition-operator-select');

        // Rebuild operator options for the selected type
        if (operatorSelect && Array.isArray(operatorsMap[type])) {
            const currentOperator = operatorSelect.value;
            operatorSelect.innerHTML = '';
            operatorsMap[type].forEach(function(op) {
                const option = document.createElement('option');
                option.value = op;
                option.textContent = operatorLabels[op] || op;
                if (op === currentOperator) option.selected = true;
                operatorSelect.appendChild(option);
            });
        }

        // Show/hide field selector wrapper (only for type=field)
        const fieldIdWrapper = item.querySelector('.elzo-forms-field-logic-condition-field-id-wrapper');
        if (fieldIdWrapper) fieldIdWrapper.style.display = type === 'field' ? '' : 'none';

        // Show/hide cookie name wrapper (only for type=cookie)
        const nameWrapper = item.querySelector('.elzo-forms-field-logic-condition-name-wrapper');
        if (nameWrapper) nameWrapper.style.display = type === 'cookie' ? '' : 'none';

        // Show/hide value wrapper — hidden for auth; hidden for cookie exists/not_exists
        const valueWrapper = item.querySelector('.elzo-forms-field-logic-group-rule-value');
        if (valueWrapper) {
            const currentOp = operatorSelect ? operatorSelect.value : '';
            const hideValue = type === 'auth'
                || (type === 'cookie' && ['exists', 'not_exists'].includes(currentOp));
            valueWrapper.style.display = hideValue ? 'none' : '';
        }

        if (type !== 'field') {
            const valueInput = ElzoFormsEnsureLogicValueInput(item, type === 'date_time' ? 'datetime-local' : 'text');
            if (valueInput) {
                valueInput.readOnly = false;
                valueInput.removeAttribute('placeholder');

                const valuePlaceholder = ElzoFormsLogicValuePlaceholder(type, operatorSelect ? operatorSelect.value : '');
                if (valuePlaceholder) valueInput.setAttribute('placeholder', valuePlaceholder);

                if (type !== 'date_time') {
                    valueInput.removeAttribute('step');
                } else {
                    valueInput.setAttribute('step', '1');
                }
            }
        }

        item.setAttribute('data-condition-type', type);

        // When type=field, also rebuild value input and operators from selected field
        if (type === 'field') {
            ElzoFormsRefreshLogicRule(item);
        }
    }

    function ElzoFormsRefreshFieldLogicItems() {
        document.querySelectorAll('.elzo-forms-field-logic-condition-item').forEach(ElzoFormsRefreshFieldLogicItem);
    }

    function ElzoFormsResetClonedConditionFields(container) {
        if (!container) return;

        container.querySelectorAll('input, select, textarea').forEach(function(element) {
            if (element.tagName === 'SELECT') {
                if (element.classList.contains('elzo-forms-field-select')) {
                    element.removeAttribute('data-selected-value');
                }

                if (element.options.length > 0) {
                    element.selectedIndex = 0;
                } else {
                    element.value = '';
                }

                return;
            }

            if (element.type === 'checkbox' || element.type === 'radio') {
                element.checked = false;
                return;
            }

            if (element.type !== 'hidden') {
                element.value = '';
            }
        });
    }
    function ElzoFormsBuildFieldHeaderTitle(field) {
        if(typeof field === 'undefined' || !field) return;

        const id = field.getAttribute('id').replace('elzo-forms-field-', '');
        const adminLabel = document.getElementById('elzo-forms-field-admin-label-' + id) ? document.getElementById('elzo-forms-field-admin-label-' + id).value : '';
        const label = document.getElementById('elzo-forms-field-label-' + id) ? document.getElementById('elzo-forms-field-label-' + id).value : '';
        const placeholder = document.getElementById('elzo-forms-field-placeholder-' + id) ? document.getElementById('elzo-forms-field-placeholder-' + id).value : '';
        const titleRaw = adminLabel ? adminLabel : (label ? label : placeholder);
        const title = titleRaw != '' ? ': ' + titleRaw : '';
        const typeSelect = document.getElementById('elzo-forms-field-type-' + id);
        if (!typeSelect) return;
        const type = typeSelect.value;
        const typeLabel = typeSelect.querySelector('option[value="' + type + '"]')?.textContent || type;
        const required = document.getElementById('elzo-forms-field-required-' + id)?.checked ? ' *' : '';
        // Full width is the default, so "1/1" tells nothing: show the first width that narrows the field
        const fieldWidths = Array.from(field.querySelectorAll('.elzo-forms-width-subfield')).map(input => input.value ? input.value.trim() : '').filter(value => value !== '' && value !== '1/1');
        const fieldWidth = fieldWidths.length > 0 ? ' (' + fieldWidths[0] + ')' : '';
        const fieldIndex = field.querySelector('.field-index-value').value;
        const hasLogic = field.querySelector('.elzo-forms-field-logic-checkbox') ? field.querySelector('.elzo-forms-field-logic-checkbox').checked : false;

        const headerTitle = field.querySelector('.elzo-forms-field-header-title');
        if (!headerTitle) return;

        const headerTitleInner = document.createElement('span');
        headerTitleInner.className = 'elzo-forms-field-header-title-inner';
        headerTitleInner.textContent = fieldIndex + '. ' + typeLabel + title;

        headerTitle.replaceChildren(
            headerTitleInner,
            document.createTextNode(fieldWidth + (hasLogic ? ' [?=]' : '') + required)
        );
        field.querySelector('.elzo-forms-tab-button-logic').classList.toggle('has-logic', hasLogic);
    }

    function ElzoFormsSyncReadOnlyFieldSettings(field, isReadOnly) {
        if (!field) return;

        field.dataset.readOnly = isReadOnly ? '1' : '0';

        field.querySelectorAll('[data-disable-for-read-only]').forEach(control => {
            control.disabled = isReadOnly;
            if (isReadOnly && control.matches('input[type="checkbox"], input[type="radio"]')) {
                control.checked = false;
            }

            const group = control.closest('.elzo-forms-field-control-group');
            if (group) group.classList.toggle('elzo-forms-field-setting-disabled', isReadOnly);
        });

        ElzoFormsBuildFieldHeaderTitle(field);
    }

    function ElzoFormsTruncateLogicOptionLabel(label, maxLength) {
        // Use passed length, fall back to window variable, then default to 50
        if (maxLength === undefined) {
            maxLength = window.ElzoFormsAdmin?.logicLabelMaxLength || 50;
        }
        
        if (!label || label.length <= maxLength) {
            return label;
        }

        return label.slice(0, Math.max(0, maxLength - 3)).trimEnd() + '...';
    }

    function ElzoFormsParseLogicOperators(value) {
        if (!value) return null;

        try {
            const parsedValue = JSON.parse(value);
            return Array.isArray(parsedValue) ? parsedValue : null;
        } catch (error) {
            console.error('Error parsing logic operators:', error);
        }

        return null;
    }

    function ElzoFormsGetFieldLogicOptionData(field) {
        if (!field) return null;

        const fieldId = field.getAttribute('data-id');
        const fieldDataInput = field.querySelector('.field-initial-value');

        let fieldData = null;

        if (!fieldId) return null;

        try {
            fieldData = fieldDataInput && fieldDataInput.value ? JSON.parse(fieldDataInput.value) : null;
        } catch (error) {
            console.error('Error parsing field data:', error);
        }

        const typeInput = document.getElementById('elzo-forms-field-type-' + fieldId);
        const optionsInput = document.getElementById('elzo-forms-field-options-' + fieldId);
        const fieldType = typeInput ? typeInput.value : (fieldData && fieldData.type ? fieldData.type : '');
        let fieldOptions = [];

        if (optionsInput) {
            fieldOptions = optionsInput.value
                .split('\n')
                .map(function(line) {
                    const trimmedLine = line.trim();

                    if (!trimmedLine) {
                        return null;
                    }

                    const parts = trimmedLine.split(':');
                    const value = (parts.shift() || '').trim();
                    const rawLabel = parts.join(':').trim();
                    const labelParts = rawLabel.split('||');
                    const label = (labelParts[0] || '').trim();

                    if (!value) {
                        return null;
                    }

                    return {
                        value: value,
                        label: label || value,
                    };
                })
                .filter(Boolean);
        } else if (fieldData && Array.isArray(fieldData.logic_value_options)) {
            fieldOptions = fieldData.logic_value_options
                .map(function(option) {
                    if (!option || !option.value) {
                        return null;
                    }

                    return {
                        value: option.value,
                        label: option.label || option.value,
                    };
                })
                .filter(Boolean);
        }

        const logicOperators = fieldData && Array.isArray(fieldData.logic_operators) ? fieldData.logic_operators : null;
        let logicValueSource = fieldData && fieldData.logic_value_source ? fieldData.logic_value_source : (fieldOptions.length > 0 ? 'options' : 'text');
        const supportsOptionValues = ['select', 'radio', 'checkbox'].includes(fieldType);

        // Keep option-based fields on dropdown mode when comparable options exist.
        if (supportsOptionValues && fieldOptions.length > 0) {
            logicValueSource = 'options';
        }

        return {
            type: fieldType,
            options: fieldOptions,
            logicValueSource: logicValueSource,
            logicOperators: logicOperators,
        };
    }

    function ElzoFormsGetLogicRuleValueField(rule) {
        if (!rule) return null;

        return rule.querySelector('.elzo-forms-field-logic-group-rule-value-input, .elzo-forms-field-logic-group-rule-value-select');
    }
    function ElzoFormsEnsureLogicValueInput(rule, inputType) {
        if (!rule) return null;

        const desiredType = inputType === 'datetime-local' ? 'datetime-local' : 'text';
        const currentValueField = ElzoFormsGetLogicRuleValueField(rule);

        if (!currentValueField) return null;

        if (currentValueField.classList.contains('elzo-forms-field-logic-group-rule-value-input')) {
            currentValueField.type = desiredType;
            return currentValueField;
        }

        const replacementField = document.createElement('input');
        replacementField.type = desiredType;
        replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-input';

        const fieldName = currentValueField.getAttribute('name');
        if (fieldName) {
            replacementField.setAttribute('name', fieldName);
        }

        replacementField.value = currentValueField.value || '';
        currentValueField.replaceWith(replacementField);

        return replacementField;
    }

    function ElzoFormsBuildLogicValueInput(rule) {
        if (!rule) return;

        let conditionTypeSelect = rule.querySelector('.elzo-forms-field-logic-condition-type-select');
        if (conditionTypeSelect && conditionTypeSelect.value !== 'field') return;

        let fieldSelect = rule.querySelector('.elzo-forms-field-rule-field-select');
        let operatorSelect = rule.querySelector('.elzo-forms-field-logic-group-rule-operator-select');
        const valueWrapper = rule.querySelector('.elzo-forms-field-logic-group-rule-value');
        const currentValueField = ElzoFormsGetLogicRuleValueField(rule);

        if (!fieldSelect || !operatorSelect || !valueWrapper || !currentValueField) return;

        const selectedOption = fieldSelect.options[fieldSelect.selectedIndex];
        const rawFieldOptions = selectedOption ? selectedOption.getAttribute('data-field-options') : '[]';
        const logicValueSource = selectedOption ? selectedOption.getAttribute('data-logic-value-source') : 'text';
        const currentValue = currentValueField.value;
        const fieldName = currentValueField.getAttribute('name');
        const selectOperators = ['==', '!='];
        let fieldOptions = [];

        try {
            fieldOptions = rawFieldOptions ? JSON.parse(rawFieldOptions) : [];
        } catch (error) {
            console.error('Error parsing logic field options:', error);
        }

        const shouldUseSelect = logicValueSource === 'options' && selectOperators.includes(operatorSelect.value) && fieldOptions.length > 0;
        let replacementField;

        if (shouldUseSelect) {
            replacementField = document.createElement('select');
            replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-select';
            replacementField.setAttribute('name', fieldName);

            fieldOptions.forEach(function(option) {
                const optionElement = document.createElement('option');
                const optionValue = typeof option === 'object' && option !== null ? (option.value || '') : option;
                const optionLabel = typeof option === 'object' && option !== null ? (option.label || optionValue) : optionValue;
                const truncatedLabel = ElzoFormsTruncateLogicOptionLabel(optionLabel);

                optionElement.value = optionValue;
                optionElement.textContent = truncatedLabel;
                optionElement.title = optionLabel;

                if (String(optionValue) === String(currentValue)) {
                    optionElement.selected = true;
                }

                replacementField.appendChild(optionElement);
            });
        } else {
            replacementField = document.createElement('input');
            replacementField.type = 'text';
            replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-input';
            replacementField.setAttribute('name', fieldName);
            replacementField.value = currentValue;

            if (logicValueSource === 'disabled') {
                replacementField.value = '';
                replacementField.placeholder = 'Not available for this field type';
                replacementField.readOnly = true;
            }
        }

        currentValueField.replaceWith(replacementField);
    }

    function ElzoFormsUpdateLogicRuleOperators(rule) {
        if (!rule) return;

        const fieldSelect = rule.querySelector('.elzo-forms-field-rule-field-select');
        const operatorSelect = rule.querySelector('.elzo-forms-field-logic-group-rule-operator-select');

        if (!fieldSelect || !operatorSelect) return;

        const selectedOption = fieldSelect.options[fieldSelect.selectedIndex];
        const allowedOperators = selectedOption ? ElzoFormsParseLogicOperators(selectedOption.getAttribute('data-logic-operators')) : null;
        const currentValue = operatorSelect.value;

        if (!operatorSelect.dataset.originalOptions) {
            operatorSelect.dataset.originalOptions = operatorSelect.innerHTML;
        }

        if (allowedOperators === null) {
            operatorSelect.innerHTML = operatorSelect.dataset.originalOptions;
            operatorSelect.value = currentValue;

            if (!operatorSelect.value && operatorSelect.options.length > 0) {
                operatorSelect.selectedIndex = 0;
            }

            return;
        }

        const optionTemplate = document.createElement('select');
        optionTemplate.innerHTML = operatorSelect.dataset.originalOptions;
        const filteredOptions = Array.from(optionTemplate.options).filter(function(option) {
            return allowedOperators.includes(option.value);
        });

        if (filteredOptions.length === 0) {
            operatorSelect.innerHTML = '<option value="">Not available</option>';
            operatorSelect.value = '';
            return;
        }

        operatorSelect.innerHTML = '';
        filteredOptions.forEach(function(option) {
            operatorSelect.appendChild(option);
        });

        operatorSelect.value = currentValue;

        if (!operatorSelect.value && operatorSelect.options.length > 0) {
            operatorSelect.selectedIndex = 0;
        }
    }

    function ElzoFormsRefreshLogicRule(rule) {
        if (!rule) return;

        ElzoFormsUpdateLogicRuleOperators(rule);
        ElzoFormsBuildLogicValueInput(rule);
    }

    function ElzoFormsRefreshLogicRules() {
        document.querySelectorAll('.elzo-forms-field-logic-group-rule').forEach(function(rule) {
            if (rule.classList.contains('elzo-forms-field-logic-condition-item')) {
                ElzoFormsRefreshFieldLogicItem(rule);
            } else {
                ElzoFormsRefreshLogicRule(rule);
            }
        });
    }

    // Conditional logic fields dropdown
    function ElzoFormsFieldSelects(){
        const fields = document.querySelectorAll('.elzo-forms-form-fields .elzo-forms-field');
        const options = [];
        let optionsHTML = '';

        if(fields.length > 0) {
            fields.forEach(function(field) {
                // Get the field index
                const indexField = field.querySelector('.field-index-value');
                const index = indexField ? indexField.value : null;

                // Ensure the field is not the first one (with index 0)
                if(index){
                    // Get field ID and title
                    const id = field.getAttribute('data-id');
                    const title = field.querySelector('.elzo-forms-field-header-title-inner').textContent;
                    const fieldLogicData = ElzoFormsGetFieldLogicOptionData(field);
                    
                    // Add it and its title to the options array
                    options.push({
                        id: id,
                        title: title,
                        type: fieldLogicData ? fieldLogicData.type : '',
                        fieldOptions: fieldLogicData ? fieldLogicData.options : [],
                        logicValueSource: fieldLogicData ? fieldLogicData.logicValueSource : 'text',
                        logicOperators: fieldLogicData ? fieldLogicData.logicOperators : null,
                    });
                }
            });
        }

        // Check if there are any options
        if(options){
            // Loop through the options array and populate <select> with <option>s
            options.forEach(function(option) {
                const optionType = option.type ? String(option.type).replace(/"/g, '&quot;') : '';
                const optionFieldOptions = JSON.stringify(option.fieldOptions || []).replace(/"/g, '&quot;');
                const optionLogicValueSource = option.logicValueSource ? String(option.logicValueSource).replace(/"/g, '&quot;') : 'text';
                const optionLogicOperators = JSON.stringify(option.logicOperators).replace(/"/g, '&quot;');
                const optionTitle = option.title ? String(option.title).replace(/"/g, '&quot;') : '';
                const optionLabelText = option.type ? (option.title || '') + ' - ' + option.type.replace(/[_-]+/g, ' ') + ' field' : (option.title || '');
                const optionLabel = ElzoFormsTruncateLogicOptionLabel(optionLabelText);
                optionsHTML = optionsHTML + '<option value="' + option.id + '" title="' + optionTitle + '" data-field-type="' + optionType + '" data-field-options="' + optionFieldOptions + '" data-logic-value-source="' + optionLogicValueSource + '" data-logic-operators="' + optionLogicOperators + '">' + optionLabel + '</option>';
            });

            // Get all field dropdowns
            const selects = document.querySelectorAll('.elzo-forms-field-select');
            
            // Check if there are any selects
            if(selects.length > 0) {
                // Add options to all selects
                selects.forEach(function(select) {
                    // Get select value if exists — fall back to data-selected-value for
                    // select fields that start empty (no PHP-rendered options).
                    const selectOldValue = select.value ? select.value : (select.getAttribute('data-selected-value') || null);

                    // Get select value
                    const selectValue = selectOldValue ? selectOldValue : select.value;

                    const placeholderLabelRaw = (window.ElzoFormsAdmin && window.ElzoFormsAdmin.selectFieldPlaceholder)
                        ? String(window.ElzoFormsAdmin.selectFieldPlaceholder)
                        : 'Select a field';
                    const placeholderLabel = placeholderLabelRaw
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                    const selectOptionsHTML = '<option value="" disabled selected>' + placeholderLabel + '</option>' + optionsHTML;

                    // Update the select with the new options
                    select.innerHTML = selectOptionsHTML;

                    // Set the select value
                    select.value = selectValue;

                    // Action settings must require an explicit field choice. Other
                    // field selectors keep their historical first-option default.
                    if (!select.value) {
                        if (select.options.length > 1) {
                            select.selectedIndex = 1;
                        } else {
                            select.selectedIndex = 0;
                        }
                    }
                });
            }
        }

        ElzoFormsRefreshLogicRules();
    }

    // Rebuild field header on title change
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('elzo-forms-field-key')) {
            const fieldKeyValue = e.target.value ? e.target.value.trim() : '';
            e.target.dataset.fieldKeyManual = fieldKeyValue !== '' ? '1' : '0';
            e.target.value = ElzoFormsSlugifyFieldKey(fieldKeyValue);
            ElzoFormsRefreshAllFieldKeys();
        }

        if (e.target.classList.contains('elzo-forms-field-header-part')) {
            const field = e.target.closest('.elzo-forms-field');
            const affectsFieldKey = e.target.classList.contains('elzo-forms-field-admin-label')
                || (e.target.id && e.target.id.startsWith('elzo-forms-field-label-'))
                || (e.target.id && e.target.id.startsWith('elzo-forms-field-placeholder-'));
            const fieldKeyInput = field ? field.querySelector('.elzo-forms-field-key') : null;

            if (affectsFieldKey && fieldKeyInput && fieldKeyInput.dataset.fieldKeyManual !== '1') {
                fieldKeyInput.value = '';
            }

            ElzoFormsRefreshAllFieldKeys();
            ElzoFormsBuildFieldHeaderTitle(field);

            // Update the field selects
            ElzoFormsFieldSelects();
        }

        if (e.target.classList.contains('elzo-forms-field-rule-field-select')) {
            ElzoFormsRefreshLogicRule(e.target.closest('.elzo-forms-field-logic-group-rule'));
        }
        if (e.target.classList.contains('elzo-forms-field-logic-condition-type-select')) {
            ElzoFormsRefreshFieldLogicItem(e.target.closest('.elzo-forms-field-logic-condition-item'));
        }

        if (e.target.classList.contains('elzo-forms-field-logic-condition-operator-select')) {
            // Re-evaluate value wrapper visibility when cookie operator changes
            ElzoFormsRefreshFieldLogicItem(e.target.closest('.elzo-forms-field-logic-condition-item'));
        }
        if (e.target.classList.contains('elzo-forms-field-logic-group-rule-operator-select')
            && !e.target.classList.contains('elzo-forms-field-logic-condition-operator-select')) {
            ElzoFormsBuildLogicValueInput(e.target.closest('.elzo-forms-field-logic-group-rule'));
        }

        if (e.target.id && e.target.id.startsWith('elzo-forms-field-options-')) {
            ElzoFormsFieldSelects();
        }

        if(e.target.id === 'elzo_forms_style_settings_input_border_radius'){
            const borderRadius = e.target.value ? parseFloat(e.target.value) : e.target.getAttribute('placeholder');
            const borderRadius05 = borderRadius / 2;

            // Update the border radius input
            if(e.target.nextElementSibling) e.target.nextElementSibling.value = borderRadius05 ? Math.floor(borderRadius05) < 1 ? 1 : Math.floor(borderRadius05) : '';
        }

        if(e.target.id === 'elzo_forms_style_settings_input_border_width'){
            const borderWidth = e.target.value ? parseFloat(e.target.value) : e.target.getAttribute('placeholder');
            const borderWidth05 = borderWidth / 2;

            // Update the border width input
            if (e.target.nextElementSibling) {
                if (!isNaN(borderWidth05) && borderWidth05 > 0) {
                    const normalizedBorderWidth05 = Math.max(1, Math.min(3, Math.round(borderWidth05)));
                    e.target.nextElementSibling.value = normalizedBorderWidth05;
                } else {
                    e.target.nextElementSibling.value = '';
                }
            }
        }

        if(e.target.classList.contains('elzo-forms-textarea-is-html-check')){
            ElzoFormsSetRichTextHtmlMode(e.target, e.target.checked);
        }

        if(e.target.classList.contains('elzo-forms-width-subfield')) {
            // Get the field
            const groupWrapper = e.target.closest('.elzo-forms-field-control-group-wrapper');
            const fields = groupWrapper.querySelectorAll('.elzo-forms-width-subfield');

            let lastKeyframe = '1/1';

            // Loop through the fields and update the field header title
            fields.forEach(function(field) {
                const autoOption = field.querySelector('option[value=""]');
                if (autoOption) {
                    // Get textContent before the "(" symbol
                    const autoText = autoOption.textContent.split('(')[0].trim();
                    
                    // Update the auto option textContent with the last keyframe
                    autoOption.textContent = autoText + ' (' + lastKeyframe + ')';
                }

                // Update lastKeyframe variable
                if(field.value) lastKeyframe = field.value;
            });
        }

        if(e.target.classList.contains('elzo-forms-field-primary-checkbox')) {
            // If checked, uncheck all other primary checkboxes
            if(e.target.checked) {
                const primaryCheckboxes = document.querySelectorAll('.elzo-forms-field-primary-checkbox');
                primaryCheckboxes.forEach(function(checkbox) {
                    if(checkbox !== e.target) {
                        checkbox.checked = false;
                    }
                });
            }
        }
    });

    document.body.addEventListener('click', function(e) {
        // Remove confirmation tooltip and class
        const waitingConfirmationTarget = e.target.closest ? e.target.closest('.elzo-forms-waiting-confirmation') : null;
        if(!waitingConfirmationTarget){
            ElzoFormsRemoveConfirmationElements();
        }

        // Expand / collapse all
        const toggleAllBtn = e.target.closest('.elzo-forms-toggle-all-button');
        if (toggleAllBtn) {
            e.preventDefault();
            let wrapper = toggleAllBtn.closest('.elzo-forms-repeater-wrapper');
            const targetSelector = toggleAllBtn.getAttribute('data-elzo-toggle-target') || '.elzo-forms-field';
            ElzoFormsToggleAll(wrapper, targetSelector, toggleAllBtn.getAttribute('data-elzo-toggle-all') === 'expand');
            return;
        }

        // Add field
        if (e.target.classList.contains('elzo-forms-add-field-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const step = e.target.closest('.elzo-forms-step');
            const stepFieldsRepeater = step ? step.querySelector('.elzo-forms-fields-repeater') : null;
            const template = document.getElementById('elzo-forms-repeater-field-template');
            const templateHTML = template ? template.innerHTML : '';

            if (stepFieldsRepeater) {
                // Insert the template before the add button
                stepFieldsRepeater.insertAdjacentHTML('beforeend', templateHTML);

                // Toggle the field
                ElzoFormsToggleField(stepFieldsRepeater.lastElementChild);
            }

            // Update repeater
            ElzoFormsUpdateRepeaterFields(repeater);
        }

        // Remove field
        if (e.target.classList.contains('elzo-forms-field-remove-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const field = e.target.closest('.elzo-forms-field');

            // Remove the field
            if(e.target.classList.contains('elzo-forms-waiting-confirmation')){
                // Fade out the field
                ElzoFormsFadeOut(field);

                // Remove the field after the animation
                setTimeout(() => {
                    // Remove the field
                    field.remove();

                    // Remove confirmation tooltip and class
                    ElzoFormsRemoveConfirmationElements();

                    // Update repeater
                    ElzoFormsUpdateRepeaterFields(repeater);
                }, 350);
            } else {
                // Show confirmation tooltip
                ElzoFormsTooltip(e.target, ElzoFormsTexts.removeFieldConfirmation);

                // Add waiting confirmation class
                e.target.classList.add('elzo-forms-waiting-confirmation');
            }
        }

        // Duplicate field
        if (e.target.classList.contains('elzo-forms-field-duplicate-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const field = e.target.closest('.elzo-forms-field');
            const clonedField = field.cloneNode(true);

            // Let the duplicated field generate a fresh key automatically.
            const clonedFieldKeyInput = clonedField.querySelector('.elzo-forms-field-key');
            if (clonedFieldKeyInput) {
                clonedFieldKeyInput.value = '';
                clonedFieldKeyInput.dataset.fieldKeyManual = '0';
            }

            // Preserve all form values and states in the cloned field
            const originalInputs = field.querySelectorAll('input, select, textarea');
            const clonedInputs = clonedField.querySelectorAll('input, select, textarea');

            originalInputs.forEach((originalInput, index) => {
                const clonedInput = clonedInputs[index];
                if (clonedInput) {
                    // Preserve the value
                    if (originalInput.type === 'checkbox' || originalInput.type === 'radio') {
                        clonedInput.checked = originalInput.checked;
                    } else {
                        clonedInput.value = originalInput.value;
                    }

                    // Preserve selected state for select options
                    if (originalInput.tagName === 'SELECT') {
                        const originalOptions = originalInput.querySelectorAll('option');
                        const clonedOptions = clonedInput.querySelectorAll('option');
                        originalOptions.forEach((originalOption, optionIndex) => {
                            if (clonedOptions[optionIndex]) {
                                clonedOptions[optionIndex].selected = originalOption.selected;
                            }
                        });
                    }
                }
            });

            // Preserve any dynamically added content (like TinyMCE)
            const originalTinyMCEs = field.querySelectorAll('.mce-container');
            originalTinyMCEs.forEach((originalTinyMCE, index) => {
                const textareaId = originalTinyMCE.previousElementSibling?.id;
                if (textareaId) {
                    const editor = tinymce.get(textareaId);
                    if (editor) {
                        // Get the content from the TinyMCE editor
                        const content = editor.getContent();
                        // Find the corresponding textarea in the cloned field and set its value
                        const clonedTextarea = clonedField.querySelector(`#${textareaId}`);
                        if (clonedTextarea) {
                            clonedTextarea.value = content;
                        }
                    }
                }
            });

            // Insert the cloned field after the original field
            field.insertAdjacentElement('afterend', clonedField);

            // Update repeater
            ElzoFormsUpdateRepeaterFields(repeater);

            // Re-initialize TinyMCE for the cloned field if needed
            const clonedTinyMCETextareas = clonedField.querySelectorAll('.elzo-forms-tinymce-field-control');
            if (clonedTinyMCETextareas.length > 0) {
                // Small delay to ensure DOM is updated
                setTimeout(() => {
                    ElzoFormsInitTinyMCE();
                }, 100);
            }
        }

        // Toggle field-like cards from dedicated buttons or by clicking header/title.
        if (ElzoFormsHandleCardToggleClick(e.target)) {
            return;
        }

        // Add step
        if (e.target.classList.contains('elzo-forms-add-step-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const template = document.querySelector('.elzo-forms-step');
            const templateHTML = template ? template.outerHTML : '';

            if (repeater) {
                // Insert the template before the add button
                repeater.insertAdjacentHTML('beforeend', templateHTML);

                // Remove all fields from the new step
                const step = repeater.lastElementChild;
                const fields = step.querySelectorAll('.elzo-forms-field');

                fields.forEach(function(field) {
                    field.remove();
                });
            }

            // Update repeater
            ElzoFormsUpdateRepeaterFields(repeater);

            // Update sortables
            ElzoFormsInitRepeatersSortable();
        }

        // Remove step
        if (e.target.classList.contains('elzo-forms-step-remove-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const step = e.target.closest('.elzo-forms-step');
            // Remove the step
            if(e.target.classList.contains('elzo-forms-waiting-confirmation')){
                // Fade out the step
                ElzoFormsFadeOut(step);

                // Remove the step after the animation
                setTimeout(() => {
                    // Remove the step
                    step.remove();

                    // Remove confirmation tooltip and class
                    ElzoFormsRemoveConfirmationElements();

                    // Update repeater
                    ElzoFormsUpdateRepeaterFields(repeater);
                }, 350);
            } else {
                // Show confirmation tooltip
                ElzoFormsTooltip(e.target, ElzoFormsTexts.removeStepConfirmation);

                // Add waiting confirmation class
                e.target.classList.add('elzo-forms-waiting-confirmation');
            }
        }

        // Duplicate step
        if (e.target.classList.contains('elzo-forms-step-duplicate-button')) {
            const repeater = document.getElementById('elzo-forms-steps-repeater');
            const step = e.target.closest('.elzo-forms-step');
            const clonedStep = step.cloneNode(true);

            step.insertAdjacentElement('afterend', clonedStep);

            // Update repeater
            ElzoFormsUpdateRepeaterFields(repeater);
        }
        // Add conditional logic group
        if (e.target.classList.contains('elzo-forms-field-logic-add-group-button')) {
            const wrapper = e.target.closest('.elzo-forms-field-logic-repeater-wrapper');
            const repeater = wrapper.querySelector('.elzo-forms-field-logic-repeater');
            const template = wrapper.querySelector('.elzo-forms-field-logic-group');
            const hiddenTemplate = wrapper.querySelector('.elzo-forms-field-logic-group-template');
            const templateHTML = hiddenTemplate
                ? hiddenTemplate.innerHTML.trim()
                : (template ? template.outerHTML : '');
            
            if (repeater && templateHTML !== '') {
                // Insert the template before the add button
                repeater.insertAdjacentHTML('beforeend', templateHTML);

                const newGroup = repeater.lastElementChild;
                if (!newGroup) {
                    return;
                }

                // Left only one item
                const groupItems = newGroup.querySelectorAll('.elzo-forms-field-logic-group-rule');

                if(groupItems.length > 1) {
                    groupItems.forEach(function(element, index) {
                        if(index > 0) {
                            element.remove();
                        }
                    });
                }

                // Clear the group fields
                ElzoFormsResetClonedConditionFields(newGroup);

                // Update repeater
                ElzoFormsUpdateRepeaterFields(document.getElementById('elzo-forms-steps-repeater'));

                // Refresh whichever condition item type was just cloned
                const newFieldLogicItem = newGroup ? newGroup.querySelector('.elzo-forms-field-logic-condition-item') : null;
                if (newFieldLogicItem) ElzoFormsRefreshFieldLogicItem(newFieldLogicItem);
            }
        }

        // Add conditional logic item
        if (e.target.classList.contains('elzo-forms-field-logic-add-rule-button')) {
            const repeater = e.target.closest('.elzo-forms-field-logic-group-rules');
            const template = repeater.querySelector('.elzo-forms-field-logic-group-rule');
            const templateHTML = template ? template.outerHTML : '';

            if (repeater) {
                // Insert the template before the add button
                repeater.insertAdjacentHTML('beforeend', templateHTML);

                // Clear the item fields
                ElzoFormsResetClonedConditionFields(repeater.lastElementChild);

                // Update repeater
                ElzoFormsUpdateRepeaterFields(document.getElementById('elzo-forms-steps-repeater'));

                // Refresh whichever condition item type was just cloned
                const newFieldLogicItem = repeater.lastElementChild;
                if (newFieldLogicItem && newFieldLogicItem.classList.contains('elzo-forms-field-logic-condition-item')) {
                    ElzoFormsRefreshFieldLogicItem(newFieldLogicItem);
                }
            }
        }

        // Remove conditional logic item
        const removeRuleButton = e.target.closest ? e.target.closest('.elzo-forms-field-logic-remove-rule-button') : null;
        if (removeRuleButton) {
            if (removeRuleButton.classList.contains('elzo-forms-waiting-confirmation')) {
                const wrapper = removeRuleButton.closest('.elzo-forms-field-logic-repeater-wrapper');
                const group = removeRuleButton.closest('.elzo-forms-field-logic-group');
                const item = removeRuleButton.closest('.elzo-forms-field-logic-group-rule');

                if (!wrapper || !group || !item) {
                    ElzoFormsRemoveConfirmationElements();
                    return;
                }

                // Check if wrapper allows complete removal of all conditions
                const allowEmptyConditions = wrapper.classList.contains('elzo-forms-allow-empty-conditions');

                // Check if there is more than one group
                if(wrapper.querySelectorAll('.elzo-forms-field-logic-group').length > 1) {
                    // Check if there is more than one item
                    if(group.querySelectorAll('.elzo-forms-field-logic-group-rule').length > 1) {
                        item.remove();
                    } else {
                        group.remove();
                    }
                } else {
                    // Check if there is more than one item
                    if(group.querySelectorAll('.elzo-forms-field-logic-group-rule').length > 1) {
                        item.remove();
                    } else if (allowEmptyConditions) {
                        // Allow complete removal of all conditions if wrapper has the allow class
                        group.remove();
                    } else {
                        // Keep at least one condition (clear its values)
                        item.querySelectorAll('input, select, textarea').forEach(function(element) {
                            element.value = '';
                        });
                    }
                }

                ElzoFormsRemoveConfirmationElements();

                // Update repeater
                ElzoFormsUpdateRepeaterFields(document.getElementById('elzo-forms-steps-repeater'));
            } else {
                ElzoFormsTooltip(removeRuleButton, removeRuleButton.getAttribute('data-click-confirmation') || 'Delete?');
                removeRuleButton.classList.add('elzo-forms-waiting-confirmation');
            }
        }

        // Tab navigation
        if (e.target.classList.contains('elzo-forms-tab-button')) {
            const tab = e.target;
            const wrapper = tab.closest('.elzo-forms-tabs');
            const tabsheader = wrapper.querySelector('.elzo-forms-tabs-header');
            const tabsbody = wrapper.querySelector('.elzo-forms-tabs-body');
            const navitems = Array.from(tabsheader.children);
            const tabs = Array.from(tabsbody.children);
            const target = tab.getAttribute('data-tab-target');
            const url = wrapper.getAttribute('data-elzo-forms-tabs-url');

            // Loop through the nav items
            navitems.forEach(function(navitem) {
                // Toggle the active class
                if (navitem === tab) {
                    navitem.classList.add('active');
                    navitem.setAttribute('aria-selected', 'true');
                } else {
                    navitem.classList.remove('active');
                    navitem.setAttribute('aria-selected', 'false');
                }
            });

            // Loop through the tabs
            tabs.forEach(function(tab) {
                // Toggle the active class and visibility
                if (tab.getAttribute('data-tab') === target) {
                    tab.classList.add('active');
                    tab.style.display = 'block';
                } else {
                    tab.classList.remove('active');
                    tab.style.display = 'none';
                }
            });

            if(url){
                const currentUrl = new URL(window.location);
                currentUrl.searchParams.set('elzo-forms-tab', target);
                window.history.replaceState({}, '', currentUrl);
            }
        }
    });

    // Reusable function to initialize jQuery UI Sortable for repeaters
    function ElzoFormsInitRepeatersSortable() {
        if (typeof jQuery !== 'undefined' && typeof jQuery.ui !== 'undefined') {
            jQuery('.elzo-forms-fields-repeater').sortable({
                handle: '.elzo-forms-field-header-dragger',
                placeholder: 'elzo-forms-field-sortable-placeholder',
                forcePlaceholderSize: true,
                stop: function(event, ui) {
                    // Update repeater
                    ElzoFormsUpdateRepeaterFields(document.getElementById('elzo-forms-steps-repeater'));
                }
            });

            jQuery('.elzo-forms-steps-repeater').sortable({
                handle: '.elzo-forms-step-header-dragger',
                placeholder: 'elzo-forms-step-sortable-placeholder',
                forcePlaceholderSize: true,
                stop: function(event, ui) {
                    // Update repeater
                    ElzoFormsUpdateRepeaterFields(document.getElementById('elzo-forms-steps-repeater'));
                }
            });
        }
    }
    // Initial call on DOMContentLoaded
    ElzoFormsInitRepeatersSortable();
    document.querySelectorAll('.elzo-forms-form-fields .elzo-forms-field').forEach(field => {
        ElzoFormsSyncReadOnlyFieldSettings(field, field.dataset.readOnly === '1');
    });
    document.body.addEventListener('change', function(e) {
        if (e.target.classList.contains('elzo-forms-field-type-select')) {
            // Prepare variables
            const field = e.target.closest('.elzo-forms-field');
            const fieldDataInput = field.querySelector('.field-initial-value');
            let fieldData = null;
            const fieldSpecificSettingsWrappers = field.querySelectorAll('.elzo-forms-field-specific-settings-wrapper');
            const formData = new FormData();
            
            // Try to parse the field data
            try {
                // Parse the field data
                fieldData = fieldDataInput && fieldDataInput.value ? JSON.parse(fieldDataInput.value) : null;
            } catch (error) {
                // Log the error message
                console.error(error);
            }
            
            // Add the required data to the form data object
            formData.append('action', 'elzo_forms');
            formData.append('nonce', ElzoFormsAdmin.nonce);
            formData.append('field_type', e.target.value);
            formData.append('field_id', field.querySelector('.field-id-value').value);
            formData.append('field_index', field.querySelector('.field-index-value').value);
            formData.append('step_index', field.closest('.elzo-forms-step').querySelector('.step-index-value').value);

            // Add "loading" class to all field specific settings wrappers
            fieldSpecificSettingsWrappers.forEach(wrapper => {
                wrapper.classList.add('loading');
            });

            // Make the AJAX request
            fetch(ElzoFormsAdmin.ajaxurl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Check if the request was successful
                if (!data.success){
                    // Log the error message
                    console.error('An error occurred: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));

                    // Break the function
                    return;
                }

                const responseData = data.data || {};

                fieldSpecificSettingsWrappers.forEach(wrapper => {
                    // Remove the "loading" class from the field specific settings wrapper
                    wrapper.classList.remove('loading');

                    // Get wrapper category
                    const category = wrapper.getAttribute('data-setting-category');

                    // Check if the request was successful
                    if (responseData.html && responseData.html[category]) {
                        // Update the field specific settings
                        wrapper.innerHTML = responseData.html[category];

                        // Show the wrapper
                        wrapper.style.display = 'block';

                        // Fill in new fields with the initial values if they exist
                        wrapper.querySelectorAll('.elzo-forms-field-control').forEach(newField => {
                            // Get the new field name
                            const newFieldFullName = newField.getAttribute('name');
                            const newFieldName = newFieldFullName.split('[').pop().replace(']', '');

                            // Check if the field data exists and the new field name is in it
                            if (fieldData && fieldData[newFieldName]) {
                                // Fill in the new field with the initial value
                                newField.value = fieldData[newFieldName];
                            }
                        });
                    } else {
                        // Empty the field specific settings
                        wrapper.innerHTML = '';

                        // Hide the wrapper
                        wrapper.style.display = 'none';
                    }
                });

                if (fieldDataInput) {
                    const nextFieldData = responseData.field ? responseData.field : Object.assign({}, fieldData || {}, { type: e.target.value });
                    fieldDataInput.value = JSON.stringify(nextFieldData, null, 4);
                }

                ElzoFormsSyncReadOnlyFieldSettings(field, !!responseData.field?.read_only);

                // Initialize tinyMCE
                ElzoFormsInitTinyMCE(field);

                // Update conditional logic dropdowns after field settings change
                ElzoFormsFieldSelects();

                // Refresh logic elements
                document.dispatchEvent(new CustomEvent('elzo-forms-refresh-logic-elements'));
            })
            .catch(error => {
                // Log the error message
                console.error('Error:', error);
            });
        }
        if(e.target.classList.contains('elzo-color-picker-opacity')){
            ElzoFormsUpdateColorPicker(e.target.closest('.elzo-color-picker-wrapper'));
        }
    });

    document.body.addEventListener('input', function(e) {
        if (e.target.classList.contains('elzo-forms-field-key')) {
            const fieldKeyValue = e.target.value ? e.target.value.trim() : '';
            e.target.dataset.fieldKeyManual = fieldKeyValue !== '' ? '1' : '0';
            e.target.value = ElzoFormsSlugifyFieldKey(fieldKeyValue);
            ElzoFormsRefreshAllFieldKeys();
        }

        if (e.target.classList.contains('elzo-forms-field-admin-label')
            || (e.target.id && e.target.id.startsWith('elzo-forms-field-label-'))
            || (e.target.id && e.target.id.startsWith('elzo-forms-field-placeholder-'))) {
            const field = e.target.closest('.elzo-forms-field');
            const fieldKeyInput = field ? field.querySelector('.elzo-forms-field-key') : null;

            if (fieldKeyInput && fieldKeyInput.dataset.fieldKeyManual !== '1') {
                fieldKeyInput.value = '';
                ElzoFormsRefreshAllFieldKeys();
            }
        }
    });

    ElzoFormsRefreshLogicRules();
    // Initialize field order numbers and header titles on page load.
    // Without this, PHP-rendered indices would remain 0 for forms where the
    // index was never stored (e.g. newly created forms).
    const stepsRepeaterOnLoad = document.getElementById('elzo-forms-steps-repeater');
    if (stepsRepeaterOnLoad) {
        ElzoFormsUpdateRepeaterFields(stepsRepeaterOnLoad);
    }

    function ElzoFormsAdminSearchSettings(section) {
        const searchInput = section.querySelector('.elzo-forms-admin-search-input');
        const nothingFoundElement = section.querySelector('.elzo-forms-admin-search-nothing-found');
        const searchClearButton = section.querySelector('.elzo-forms-admin-search-clear');
        const searchLists = section.querySelectorAll('.elzo-forms-admin-search-list');

        if (!searchInput || !searchClearButton || searchLists.length === 0) {
            return;
        }

        // Detect layout: TBODY = table settings, DIV = module grid
        const isTable = searchLists[0].tagName === 'TBODY';

        // For grid layout, collect cards once
        const gridItems = isTable ? null : searchLists[0].querySelectorAll('.elzo-forms-module-card');

        // Hide the clear button by default
        searchClearButton.style.display = 'none';

        function performSearch(searchTerm) {
            let nothingFound = true;

            if (isTable) {
                searchLists.forEach(function(list) {
                    const rows = list.querySelectorAll('tr');
                    let listHasMatch = false;

                    rows.forEach(function(item) {
                        const label = item.querySelector('label');
                        const description = item.querySelector('.description');
                        const input = item.querySelector('input');
                        const textarea = item.querySelector('textarea');
                        const select = item.querySelector('select');
                        const inputValue = input ? input.value.toLowerCase() : '';
                        const textareaValue = textarea ? textarea.value.toLowerCase() : '';
                        const selectValue = select ? select.value.toLowerCase() : '';
                        const inputPlaceholder = input && input.getAttribute('placeholder') ? input.getAttribute('placeholder').toLowerCase() : '';
                        const textareaPlaceholder = textarea && textarea.getAttribute('placeholder') ? textarea.getAttribute('placeholder').toLowerCase() : '';
                        const selectPlaceholder = select && select.getAttribute('placeholder') ? select.getAttribute('placeholder').toLowerCase() : '';
                        const labelText = label ? label.textContent.toLowerCase() : '';
                        const descriptionText = description ? description.textContent.toLowerCase() : '';

                        const matchesSearch = !searchTerm || labelText.includes(searchTerm) || descriptionText.includes(searchTerm) || inputValue.includes(searchTerm) || textareaValue.includes(searchTerm) || selectValue.includes(searchTerm) || inputPlaceholder.includes(searchTerm) || textareaPlaceholder.includes(searchTerm) || selectPlaceholder.includes(searchTerm);

                        item.style.display = matchesSearch ? '' : 'none';
                        if (matchesSearch) {
                            listHasMatch = true;
                        }
                    });

                    // Show/hide the parent table and its preceding h3 heading
                    const table = list.closest('table');
                    if (table) {
                        table.style.display = listHasMatch ? '' : 'none';
                        let prev = table.previousElementSibling;
                        while (prev && !prev.classList.contains('elzo-forms-admin-search-heading')) {
                            prev = prev.previousElementSibling;
                        }
                        if (prev && prev.classList.contains('elzo-forms-admin-search-heading')) {
                            prev.style.display = listHasMatch ? '' : 'none';
                        }
                    }

                    if (listHasMatch) {
                        nothingFound = false;
                    }
                });
            } else {
                // Grid search (for modules)
                gridItems.forEach(function(item) {
                    const moduleName = item.querySelector('.elzo-forms-module-name');
                    const moduleDescription = item.querySelector('.elzo-forms-module-description');
                    const nameText = moduleName ? moduleName.textContent.toLowerCase() : '';
                    const descriptionText = moduleDescription ? moduleDescription.textContent.toLowerCase() : '';

                    const matchesSearch = !searchTerm || nameText.includes(searchTerm) || descriptionText.includes(searchTerm);

                    item.style.display = matchesSearch ? '' : 'none';
                    if (matchesSearch) {
                        nothingFound = false;
                    }
                });
            }

            if (nothingFoundElement) nothingFoundElement.style.display = nothingFound ? 'block' : 'none';
        }

        searchInput.addEventListener('input', function() {
            const searchTerm = searchInput.value.toLowerCase();
            searchClearButton.style.display = searchTerm ? 'inline-block' : 'none';
            performSearch(searchTerm);
        });

        // Clear the search input and reset all items
        searchClearButton.addEventListener('click', function() {
            searchInput.value = '';
            searchClearButton.style.display = 'none';
            performSearch('');
        });
    }
    
    // Run the search settings function for each section
    document.querySelectorAll('.elzo-forms-admin-search-section').forEach(section => {
        ElzoFormsAdminSearchSettings(section);
    });

    // Color picker
    if (typeof jQuery !== 'undefined' && typeof jQuery.wp !== 'undefined') {
        jQuery('.elzo-color-picker').wpColorPicker({
            change: function(event, ui) {
                ElzoFormsUpdateColorPicker(event.target.closest('.elzo-color-picker-wrapper'), ui.color.toString());
            },
            clear: function(event, ui) {
                ElzoFormsUpdateColorPicker(event.target.closest('.elzo-color-picker-wrapper'), '');
            }
        });

        jQuery(document).on('click', '.elzo-color-picker-facade-clear', function(){
            const colorPickerWrapper = jQuery(this).closest('.elzo-color-picker-wrapper');
            const colorPicker = jQuery('.elzo-color-picker', colorPickerWrapper);
            const opacityInput = jQuery('.elzo-color-picker-opacity', colorPickerWrapper);

            // Reset opacity input
            opacityInput.val('');
            
            // Reset color picker
            colorPicker.val('').change();
        });

        jQuery(document).on('click', '.elzo-color-picker-facade-toggler', function(){
            jQuery('.wp-color-result', jQuery(this).closest('.elzo-color-picker-wrapper')).click();
        });
    }

    const submissionEditToggle = document.getElementById('elzo-submission-edit-toggle');

    if(submissionEditToggle) {
        submissionEditToggle.addEventListener('click', function() {
            const buttonText = this.textContent;
            const submissionDataTable = document.getElementById('elzo-submission-data-table');
            const valueFields = submissionDataTable.querySelectorAll('.elzo-submission-line-value-field');
            const valueTextElements = submissionDataTable.querySelectorAll('.elzo-submission-line-value-text');

            // Toggle the visibility of the value fields
            valueFields.forEach(function(valueField) {
                if (valueField.style.display === 'none') {
                    valueField.style.display = 'block';
                } else {
                    valueField.style.display = 'none';
                }
            });

            // Toggle the visibility of the text elements
            valueTextElements.forEach(function(valueTextElement) {
                if (valueTextElement.style.display === 'none') {
                    const valueField = valueTextElement.nextElementSibling;

                    // If the next element is a value field, copy its value to the text element
                    if (valueField && valueField.classList.contains('elzo-submission-line-value-field')) {
                        const valueSubFields = valueField.querySelectorAll('.elzo-submission-line-value-subfield');
                        if( valueSubFields.length > 0) {
                            // If there are subfields, filter and join their values
                            valueTextElement.textContent = Array.from(valueSubFields).map(subField => subField.value).filter(value => value).join(', ');
                        } else {
                            // If there are no subfields, use the value of the field
                            valueTextElement.textContent = valueField.value ? valueField.value : '-';
                        }
                    }

                    valueTextElement.style.display = 'block';
                } else {
                    valueTextElement.style.display = 'none';
                }
            });

            // Toggle the text of the button
            this.textContent = this.getAttribute('data-toggle-text');
            this.setAttribute('data-toggle-text', buttonText);
        });
    }
    
    // Module settings modal handling
    const moduleSettingsButtons = document.querySelectorAll('.elzo-forms-module-settings-button');
    const moduleWarningIcons = document.querySelectorAll('.elzo-forms-module-warning-icon');
    
    if (moduleSettingsButtons.length > 0 || moduleWarningIcons.length > 0) {
        // Handle settings button click
        moduleSettingsButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const moduleId = this.getAttribute('data-module-id');
                const modal = document.getElementById('elzo-forms-module-modal-' + moduleId);
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            });
        });
        
        // Handle warning icon click
        moduleWarningIcons.forEach(icon => {
            icon.addEventListener('click', function(e) {
                e.preventDefault();
                const moduleId = this.getAttribute('data-module-id');
                const modal = document.getElementById('elzo-forms-module-modal-' + moduleId);
                if (modal) {
                    modal.style.display = 'block';
                    document.body.style.overflow = 'hidden';
                }
            });
        });
        
        // Close modal handlers
        const closeButtons = document.querySelectorAll('.elzo-forms-module-modal-close');
        const overlays = document.querySelectorAll('.elzo-forms-module-modal-overlay');
        
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                const modal = this.closest('.elzo-forms-module-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
        
        overlays.forEach(overlay => {
            overlay.addEventListener('click', function() {
                const modal = this.closest('.elzo-forms-module-modal');
                if (modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Close on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const visibleModal = document.querySelector('.elzo-forms-module-modal[style*="display: block"]');
                if (visibleModal) {
                    visibleModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            }
        });
    }

    ElzoFormsLockJsonBackedFormEditor();
});


document.addEventListener('DOMContentLoaded', function() {
    const LOGIC_CONTEXT = window.ElzoFormsAjax?.logicContext || {};
    const SERVER_NOW_UTC_SECONDS = Number(LOGIC_CONTEXT.serverNowUtc || 0);
    const SERVER_NOW_UTC_MS = Number.isFinite(SERVER_NOW_UTC_SECONDS) && SERVER_NOW_UTC_SECONDS > 0
        ? SERVER_NOW_UTC_SECONDS * 1000
        : null;
    const CLIENT_BOOT_MONOTONIC_MS = typeof performance !== 'undefined' && typeof performance.now === 'function'
        ? performance.now()
        : null;
    const CLIENT_BOOT_WALLCLOCK_MS = Date.now();

    const SUPPORTED_LOGIC_OPERATORS = Array.isArray(window.ElzoFormsAjax?.logicOperators)
        ? window.ElzoFormsAjax.logicOperators
        : ['==', '!=', '>', '<', 'like', 'not_like', 'starts_with', 'ends_with', 'pattern'];

    function getCurrentUtcMs() {
        if (SERVER_NOW_UTC_MS === null) {
            return Date.now();
        }

        // Use monotonic elapsed time when available to avoid jumps if the user
        // changes their system clock while the page is open.
        const elapsedMs = CLIENT_BOOT_MONOTONIC_MS !== null && typeof performance !== 'undefined' && typeof performance.now === 'function'
            ? (performance.now() - CLIENT_BOOT_MONOTONIC_MS)
            : (Date.now() - CLIENT_BOOT_WALLCLOCK_MS);

        return SERVER_NOW_UTC_MS + elapsedMs;
    }

    /*
     * PHP compiles every user pattern as /…/u (Conditional_Logic::matches_pattern_for_all_values),
     * so the client asks for the same Unicode semantics. A few patterns PCRE reads as literals —
     * a lone "{", an identity escape such as \- outside a character class — are syntax errors
     * under the JS "u" flag, so those retry without it instead of hiding a field the server
     * would have matched. Returns null only when the pattern is invalid either way.
     */
    function compileLogicPattern(patternBody) {
        const source = String(patternBody ?? '');

        try {
            return new RegExp(source, 'u');
        } catch (unicodeError) {
            try {
                return new RegExp(source);
            } catch (error) {
                return null;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Field-value comparison (mirrors PHP Conditional_Logic::compare_values)
    // -------------------------------------------------------------------------

    function compareLogicValues(values, operator, expectedValue) {
        const expected = String(expectedValue ?? '');

        switch (operator) {
            case '==':
                return values.includes(expected);
            case '!=':
                return values.every(value => value !== expected);
            case '>':
                return values.every(value => parseFloat(value) > parseFloat(expected));
            case '<':
                return values.every(value => parseFloat(value) < parseFloat(expected));
            case 'like':
                return values.some(value => value.includes(expected));
            case 'not_like':
                return values.every(value => !value.includes(expected));
            case 'starts_with':
                return values.some(value => value.startsWith(expected));
            case 'ends_with':
                return values.some(value => value.endsWith(expected));
            case 'pattern': {
                const regex = compileLogicPattern(expected);
                return regex !== null && values.every(value => regex.test(value));
            }
            default:
                return false;
        }
    }

    // -------------------------------------------------------------------------
    // Condition item evaluators
    // -------------------------------------------------------------------------

    function isComparableInputElement(element) {
        if (!element || !element.tagName) return false;
        return ['INPUT', 'SELECT', 'TEXTAREA'].includes(element.tagName);
    }

    function collectComparableInputValues(elements, collectFields) {
        const values = [];

        elements.forEach(function(element) {
            if (collectFields && !logicElements.includes(element)) {
                logicElements.push(element);
            }

            const type = (element.type || '').toLowerCase();
            if (['checkbox', 'radio'].includes(type)) {
                if (element.checked) values.push(element.value);
            } else {
                values.push(element.value);
            }
        });

        if (values.length === 0) values.push('');
        return values;
    }

    /**
     * Resolve the form that owns the element being evaluated, so several forms
     * on one page cannot read each other's fields.
     */
    function getLogicRoot(element) {
        return element?.closest?.('form') || document;
    }

    function escapeAttributeValue(value) {
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function findInRoot(root, selector) {
        return root.querySelector(selector) || (root === document ? null : document.querySelector(selector));
    }

    function findFieldWrapper(root, fieldId) {
        return findInRoot(root, '[data-ef-field-id="' + escapeAttributeValue(fieldId) + '"]');
    }

    function evaluateFieldCondition(operator, settings, collectFields, root = document) {
        const fieldId = settings.field_id || '';
        if (!fieldId) return false;

        const fieldWrapper = findFieldWrapper(root, fieldId);
        const fields = fieldWrapper
            ? Array.from(fieldWrapper.querySelectorAll('.elzo-forms-field'))
            : [];

        const values = collectComparableInputValues(fields, collectFields);

        return SUPPORTED_LOGIC_OPERATORS.includes(operator)
            ? compareLogicValues(values, operator, settings.value ?? '')
            : false;
    }

    /**
     * Internal-only condition type for arbitrary form controls.
     *
     * Supported settings:
     *   id       => element id (input/select/textarea id, or container id)
     *   selector => CSS selector fallback for matching controls
     *   value    => expected value for comparison
     */
    function evaluateInputCondition(operator, settings, collectFields, root = document) {
        const targetId = String(settings.id ?? '').trim();
        const selector = String(settings.selector ?? '').trim();
        let elements = [];

        if (targetId) {
            const targetElement = findInRoot(root, '[id="' + escapeAttributeValue(targetId) + '"]');
            if (targetElement) {
                if (isComparableInputElement(targetElement)) {
                    elements = [targetElement];
                } else {
                    elements = Array.from(targetElement.querySelectorAll('input, select, textarea'));
                }
            }
        }

        if (elements.length === 0 && selector) {
            try {
                elements = Array.from(root.querySelectorAll(selector)).filter(isComparableInputElement);

                if (elements.length === 0 && root !== document) {
                    elements = Array.from(document.querySelectorAll(selector)).filter(isComparableInputElement);
                }
            } catch (error) {
                return false;
            }
        }

        if (elements.length === 0) return false;

        const values = collectComparableInputValues(elements, collectFields);

        const normalizedOperator = SUPPORTED_LOGIC_OPERATORS.includes(operator ?? '')
            ? operator
            : null;

        return normalizedOperator
            ? compareLogicValues(values, normalizedOperator, settings.value ?? '')
            : false;
    }

    function evaluateAuthCondition(operator) {
        const ctx = window.ElzoFormsAjax?.logicContext;
        const isLoggedIn = ctx?.isLoggedIn === true;
        return operator === 'is_guest' ? !isLoggedIn : isLoggedIn;
    }

    /**
     * Parse a user-supplied id the way PHP intval() does, so that a list entry
     * such as '12abc' or 'none' resolves to the same number on both sides.
     */
    function toLogicInt(value) {
        const parsed = parseInt(String(value ?? '').trim(), 10);
        return Number.isNaN(parsed) ? 0 : parsed;
    }

    function evaluateUserCondition(operator, settings) {
        const ctx = LOGIC_CONTEXT;
        if (!ctx) return false;

        const roles  = Array.isArray(ctx.userRoles) ? ctx.userRoles : [];
        const userId = Number(ctx.userId) || 0;

        /*
         * Guests fail every user condition, matching the early return in PHP
         * Conditional_Logic::evaluate_user_item(). Without this, the negative
         * operators (role_not_equals, role_not_in, id_not_equals, id_not_in)
         * would pass here against empty roles / id 0 but fail server side, so a
         * logged-out visitor would see a field whose value is later discarded.
         */
        if (userId <= 0) return false;

        /*
         * PHP builds the list with array_filter(), which drops both '' and '0',
         * and the equality operators read only its first entry — so a rule
         * written as "editor, author" matches on "editor" there and must not be
         * compared as a whole string here.
         */
        const list = String(settings.value ?? '')
            .split(',')
            .map(s => s.trim())
            .filter(s => s !== '' && s !== '0');
        const first = list.length ? list[0] : null;
        const ids   = list.map(toLogicInt);

        switch (operator) {
            case 'role_equals':     return first !== null && roles.includes(first);
            case 'role_not_equals': return first !== null && !roles.includes(first);
            case 'role_in':         return list.some(r => roles.includes(r));
            case 'role_not_in':     return !list.some(r => roles.includes(r));
            case 'id_equals':       return first !== null && userId === toLogicInt(first);
            case 'id_not_equals':   return first !== null && userId !== toLogicInt(first);
            case 'id_in':           return ids.includes(userId);
            case 'id_not_in':       return !ids.includes(userId);
            default:                return false;
        }
    }

    function parseCookies() {
        const cookies = {};
        document.cookie.split(';').forEach(function(part) {
            const idx = part.indexOf('=');
            if (idx < 0) return;
            const k = decodeURIComponent(part.slice(0, idx).trim());
            const v = decodeURIComponent(part.slice(idx + 1).trim());
            cookies[k] = v;
        });
        return cookies;
    }

    function evaluateCookieCondition(operator, settings) {
        const name     = String(settings.name  ?? '').trim();
        const expected = String(settings.value ?? '');
        if (!name) return false;

        const cookies = parseCookies();
        const exists  = Object.prototype.hasOwnProperty.call(cookies, name);
        const actual  = exists ? cookies[name] : '';

        switch (operator) {
            case 'exists':      return exists;
            case 'not_exists':  return !exists;
            case 'equals':      return exists && actual === expected;
            case 'not_equals':  return exists && actual !== expected;
            case 'contains':    return exists && expected !== '' && actual.includes(expected);
            case 'not_contains':return exists && !actual.includes(expected);
            case 'pattern': {
                if (!exists || !expected) return false;
                const regex = compileLogicPattern(expected);
                return regex !== null && regex.test(actual);
            }
            default:            return false;
        }
    }

    function sanitizeLogicKey(value) {
        return String(value ?? '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
    }

    function collectConditionPageIds(settings) {
        const rawIds = settings?.page_ids;
        let pageIds = Array.isArray(rawIds) ? rawIds.map(id => parseInt(id, 10) || 0) : [];

        if (!pageIds.length && settings?.page_id !== undefined) {
            pageIds = [parseInt(settings.page_id, 10) || 0];
        }

        // Fallback: comma-separated value
        if (!pageIds.length && settings?.value) {
            pageIds = String(settings.value).split(',').map(id => parseInt(id, 10) || 0).filter(Boolean);
        }

        return pageIds;
    }

    function evaluatePageCondition(operator, settings) {
        // The admin screens reuse this file without a page context; without it the
        // condition stays closed, matching PHP Conditional_Logic::evaluate_page_item().
        if (LOGIC_CONTEXT.currentPageId === undefined && LOGIC_CONTEXT.currentPostType === undefined) return false;

        const currentPageId   = parseInt(LOGIC_CONTEXT.currentPageId, 10) || 0;
        const currentPostType = sanitizeLogicKey(LOGIC_CONTEXT.currentPostType);

        const pageIds = collectConditionPageIds(settings);
        const postType = sanitizeLogicKey(settings?.post_type) || sanitizeLogicKey(settings?.value);

        switch (operator) {
            case 'page_id_equals':       return !!pageIds[0] && currentPageId === pageIds[0];
            case 'page_id_not_equals':   return !!pageIds[0] && currentPageId !== pageIds[0];
            case 'page_id_in':           return pageIds.includes(currentPageId);
            case 'page_id_not_in':       return !pageIds.includes(currentPageId);
            case 'post_type_equals':     return postType !== '' && currentPostType === postType;
            case 'post_type_not_equals': return postType !== '' && currentPostType !== postType;
            default:                     return false;
        }
    }

    function evaluateUrlCondition(operator, settings) {
        const expected = String(settings?.value ?? '');
        const actual   = typeof window.location !== 'undefined' ? String(window.location.href) : '';

        switch (operator) {
            case 'not_equals':   return actual !== expected;
            case 'contains':     return expected !== '' && actual.toLowerCase().includes(expected.toLowerCase());
            case 'not_contains': return expected !== '' && !actual.toLowerCase().includes(expected.toLowerCase());
            case 'starts_with':  return expected !== '' && actual.startsWith(expected);
            case 'ends_with':    return expected !== '' && actual.endsWith(expected);
            case 'pattern': {
                if (!expected) return false;
                const regex = compileLogicPattern(expected);
                return regex !== null && regex.test(actual);
            }
            case 'equals':
            default:             return actual === expected;
        }
    }

    function parseDateTimeValueToUtcMs(value) {
        const raw = String(value ?? '').trim();
        if (!raw) return null;

        if (/[zZ]|[+\-]\d{2}:?\d{2}$/.test(raw)) {
            const parsed = Date.parse(raw);
            return Number.isNaN(parsed) ? null : parsed;
        }

        const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?$/);
        if (match) {
            const year = Number(match[1]);
            const month = Number(match[2]);
            const day = Number(match[3]);
            const hour = Number(match[4]);
            const minute = Number(match[5]);
            const second = Number(match[6] || 0);
            return Date.UTC(year, month - 1, day, hour, minute, second);
        }

        const fallback = Date.parse(raw + 'Z');
        return Number.isNaN(fallback) ? null : fallback;
    }

    function getDateTimeSettingUtcMs(settings, key) {
        const timestampKey = key + '_timestamp';
        const rawTimestamp = Number(settings?.[timestampKey]);
        if (Number.isFinite(rawTimestamp) && rawTimestamp > 0) {
            return rawTimestamp > 1e12 ? rawTimestamp : rawTimestamp * 1000;
        }

        return parseDateTimeValueToUtcMs(settings?.[key]);
    }

    function evaluateDateTimeCondition(operator, settings) {
        const nowUtcMs = getCurrentUtcMs();
        const startUtcMs = getDateTimeSettingUtcMs(settings, 'start_time')
            ?? parseDateTimeValueToUtcMs(settings?.value);

        switch (operator) {
            case 'before':
                return startUtcMs !== null && nowUtcMs < startUtcMs;
            case 'after':
                return startUtcMs !== null && nowUtcMs > startUtcMs;
            default:
                return false;
        }
    }

    function evaluateDefaultCondition(type, operator, settings, collectFields, root) {
        switch (type) {
            case 'field':
                return evaluateFieldCondition(operator, settings, collectFields, root);
            case 'input':
                return evaluateInputCondition(operator, settings, collectFields, root);
            case 'auth':
                return evaluateAuthCondition(operator);
            case 'user':
                return evaluateUserCondition(operator, settings);
            case 'cookie':
                return evaluateCookieCondition(operator, settings);
            case 'page':
                return evaluatePageCondition(operator, settings);
            case 'url':
                return evaluateUrlCondition(operator, settings);
            default:
                return false;
        }
    }

    /**
     * Evaluate a single condition item {type, operator, settings} — matches PHP
     * Conditional_Logic::evaluate_condition_item().
     */
    function evaluateConditionItem(item, collectFields, root) {
        const type     = item.type     || 'field';
        const operator = item.operator || '==';
        const settings = item.settings || {};

        switch (type) {
            case 'date_time':
                return evaluateDateTimeCondition(operator, settings);
            default:
                return evaluateDefaultCondition(type, operator, settings, collectFields, root);
        }
    }

    // -------------------------------------------------------------------------
    // Conditional logic
    // -------------------------------------------------------------------------

    let logicElements = [];
    let isLogicElementsPopulated = false;

    function elzoConditionalLogic() {
        document.querySelectorAll('[data-ef-logic]').forEach(function(element) {
            let conditionGroups;
            let field = element.querySelector('.elzo-forms-field');

            try {
                conditionGroups = JSON.parse(element.getAttribute('data-ef-logic'));
            } catch (e) {
                console.error('Error parsing data-ef-logic JSON: ' + e);
                return;
            }

            // OR-of-ANDs evaluation
            const root = getLogicRoot(element);
            let conditionMet = false;

            for (let i = 0; i < conditionGroups.length; i++) {
                const group = conditionGroups[i];
                let allConditionsMet = true;

                for (let j = 0; j < group.length; j++) {
                    const item = group[j];
                    const collectFields = !isLogicElementsPopulated;

                    if (!evaluateConditionItem(item, collectFields, root)) {
                        allConditionsMet = false;
                        if (isLogicElementsPopulated) break;
                    }
                }

                if (allConditionsMet) {
                    conditionMet = true;
                    if (isLogicElementsPopulated) break;
                }
            }

            // Promote to column wrapper if applicable
            if (element.parentElement && element.parentElement.classList.contains('elzo-forms-column')) {
                element = element.parentElement;
            }

            element.style.display = conditionMet ? '' : 'none';

            if (field && field.classList.contains('elzo-forms-field-required')) {
                field.required = conditionMet;
            }

            if (field && field.tagName === 'SELECT') {
                const placeholderOption = field.querySelector('option[data-placeholder="true"]');
                if (!conditionMet) {
                    field.value = '';
                    if (placeholderOption) placeholderOption.disabled = false;
                } else {
                    if (placeholderOption) placeholderOption.disabled = true;
                }
            }
        });

        isLogicElementsPopulated = true;
    }

    elzoConditionalLogic();

    document.addEventListener('change', function(e) {
        if (logicElements.includes(e.target)) {
            elzoConditionalLogic();
        }
    });

    document.addEventListener('elzo-forms-refresh-logic-elements', function() {
        setTimeout(function() {
            logicElements = [];
            isLogicElementsPopulated = false;
            elzoConditionalLogic();
        }, 100);
    });
});