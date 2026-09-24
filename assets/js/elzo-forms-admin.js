// TinyMCE initialization
function ElzoFormsInitTinyMCE(scope = document) {
    if (typeof tinymce === 'undefined') {
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

    field.querySelectorAll(':scope > .elzo-forms-field-header .elzo-forms-automation-toggle-button, :scope > .elzo-forms-field-header .elzo-forms-automation-action-toggle-button').forEach(button => {
        button.setAttribute('aria-expanded', field.classList.contains('active') ? 'true' : 'false');
    });
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

        output = output.replace(/[^a-z0-9_]+/g, '-').replace(/^-+|-+$/g, '');

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
        const usedKeys = Object.create(null);
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

    // -------------------------------------------------------------------------
    // Content picker
    //
    // Searchable selector for content that a condition references by ID, used by
    // page conditions in field logic and in automations. The value control it
    // wraps stays the single source of truth: the picker only reads the IDs
    // stored there and writes back the ones a user picks, so a cloned rule and a
    // reloaded form both rebuild from stored data alone.
    // -------------------------------------------------------------------------

    let ElzoFormsContentPickerSequence = 0;

    // Titles resolved so far, keyed by post ID. Lets a picker label a stored ID
    // without a request when another picker already looked it up.
    const ElzoFormsContentLabelCache = {};

    function ElzoFormsContentPickerText(key, fallback) {
        const strings = window.ElzoFormsAdmin && window.ElzoFormsAdmin.contentPicker
            ? window.ElzoFormsAdmin.contentPicker
            : {};
        const message = strings && typeof strings[key] === 'string' ? strings[key] : '';

        return message || fallback || '';
    }

    function ElzoFormsContentPickerFormat(key, fallback, value) {
        return ElzoFormsContentPickerText(key, fallback).replace('%d', String(value)).replace('%s', String(value));
    }

    function ElzoFormsContentPostTypeOptions() {
        const options = window.ElzoFormsAdmin && window.ElzoFormsAdmin.contentPostTypes;

        return Array.isArray(options) ? options : [];
    }

    function ElzoFormsPostTypeLabel(value) {
        const match = ElzoFormsContentPostTypeOptions().find(option => String(option.value || '') === String(value));

        return match ? String(match.label || match.value || '') : '';
    }

    function ElzoFormsPostTypeSearch(params, callback) {
        const query = String(params.search || '').trim().toLowerCase();
        const limit = Math.max(1, parseInt(params.limit || 20, 10) || 20);
        const page = Math.max(1, parseInt(params.page || 1, 10) || 1);
        const matches = ElzoFormsContentPostTypeOptions().filter(option => {
            const value = String(option.value || '');
            const label = String(option.label || value);

            return value && (!query || value.toLowerCase().includes(query) || label.toLowerCase().includes(query));
        });
        const offset = (page - 1) * limit;
        const items = matches.slice(offset, offset + limit).map(option => ({
            id: String(option.value || ''),
            title: String(option.label || option.value || ''),
        }));

        callback(items, '', offset + limit < matches.length);
    }

    function ElzoFormsReadIntegerArray(value) {
        let values = [];

        try {
            const parsed = JSON.parse(value || '[]');
            if (Array.isArray(parsed)) {
                values = parsed;
            } else if (parsed !== null && typeof parsed !== 'undefined') {
                values = [parsed];
            }
        } catch (error) {
            values = String(value || '').split(/[,\s]+/);
        }

        const seen = {};
        return values.map(item => String(item).trim())
            .filter(item => /^[+-]?\d+$/.test(item))
            .map(item => parseInt(item, 10))
            .filter(item => {
                if (seen[item]) return false;
                seen[item] = true;
                return true;
            });
    }

    function ElzoFormsReadPostTypeArray(value) {
        const seen = {};

        return String(value || '').split(/[,\s]+/)
            .map(item => item.trim().toLowerCase().replace(/[^a-z0-9_-]/g, ''))
            .filter(item => {
                if (!item || !/^[a-z_][a-z0-9_-]*$/.test(item) || seen[item]) return false;
                seen[item] = true;
                return true;
            });
    }

    function ElzoFormsContentSearch(params, callback) {
        const admin = window.ElzoFormsAdmin || {};

        if (!admin.ajaxurl || !admin.nonce || typeof fetch !== 'function') {
            callback([], ElzoFormsContentPickerText('failed', 'Search failed.'));
            return;
        }

        const formData = new FormData();
        formData.append('action', 'elzo_forms_content_search');
        formData.append('nonce', admin.nonce);
        formData.append('limit', String(params.limit || 20));
        formData.append('page', String(params.page || 1));

        if (params.search) formData.append('search', params.search);
        if (params.include && params.include.length) formData.append('include', params.include.join(','));
        if (params.postTypes && params.postTypes.length) formData.append('post_types', params.postTypes.join(','));

        fetch(admin.ajaxurl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
        }).then(response => response.json())
            .then(data => {
                if (data && data.success && data.data && Array.isArray(data.data.items)) {
                    data.data.items.forEach(item => {
                        const id = parseInt(item.id, 10);
                        if (id > 0) ElzoFormsContentLabelCache[id] = item;
                    });
                    callback(data.data.items, '', data.data.has_more === true);
                    return;
                }

                const message = data && data.data && data.data.message
                    ? data.data.message
                    : ElzoFormsContentPickerText('failed', 'Search failed.');
                callback([], message, false);
            })
            .catch(() => {
                callback([], ElzoFormsContentPickerText('failed', 'Search failed.'), false);
            });
    }

    function ElzoFormsContentPickerLabel(picker, id) {
        const settings = ElzoFormsContentPickerSettings(picker);
        if (settings.label) return settings.label(id);

        const item = ElzoFormsContentLabelCache[id];
        if (!item) return '';

        return item.type_label ? item.title + ' (' + item.type_label + ')' : item.title;
    }

    function ElzoFormsContentPickerSettings(picker) {
        const runtime = picker.elzoContentPickerOptions || {};

        return {
            multiple: picker.getAttribute('data-multiple') === '1',
            format: picker.getAttribute('data-format') === 'json' ? 'json' : 'csv',
            valueType: picker.getAttribute('data-value-type') === 'string' ? 'string' : 'integer',
            postTypes: (picker.getAttribute('data-post-types') || '').split(',').filter(Boolean),
            limit: Math.max(1, parseInt(runtime.limit || 20, 10) || 20),
            minSearchLength: Math.max(0, parseInt(runtime.minSearchLength ?? 2, 10) || 0),
            search: typeof runtime.search === 'function' ? runtime.search : ElzoFormsContentSearch,
            label: typeof runtime.label === 'function' ? runtime.label : null,
            unavailableLabel: typeof runtime.unavailableLabel === 'function' ? runtime.unavailableLabel : null,
            meta: typeof runtime.meta === 'function' ? runtime.meta : null,
        };
    }

    function ElzoFormsContentPickerNormalizeValue(picker, value) {
        if (ElzoFormsContentPickerSettings(picker).valueType === 'string') {
            return ElzoFormsReadPostTypeArray(value)[0] || '';
        }

        return parseInt(value, 10) || 0;
    }

    function ElzoFormsContentPickerValueControl(picker) {
        const selector = picker.getAttribute('data-value-selector') || '';

        return selector ? picker.parentElement.querySelector(selector) : null;
    }

    function ElzoFormsContentPickerReadIds(picker) {
        const valueControl = ElzoFormsContentPickerValueControl(picker);

        if (!valueControl) return [];

        return ElzoFormsContentPickerSettings(picker).valueType === 'string'
            ? ElzoFormsReadPostTypeArray(valueControl.value)
            : ElzoFormsReadIntegerArray(valueControl.value);
    }

    function ElzoFormsContentPickerWriteIds(picker, ids) {
        const valueControl = ElzoFormsContentPickerValueControl(picker);
        if (!valueControl) return;

        const settings = ElzoFormsContentPickerSettings(picker);
        const value = settings.format === 'json'
            ? (settings.multiple ? JSON.stringify(ids) : (ids.length ? String(ids[0]) : ''))
            : ids.join(', ');

        valueControl.value = value;
        valueControl.dispatchEvent(new Event('input', { bubbles: true }));
        valueControl.dispatchEvent(new Event('change', { bubbles: true }));

        ElzoFormsRenderContentPickerSelection(picker);
    }

    function ElzoFormsSyncContentPickerPresentation(picker) {
        const search = picker.querySelector('.elzo-forms-content-picker-search');
        const hasSelection = ElzoFormsContentPickerReadIds(picker).length > 0;
        const searchLabel = ElzoFormsContentPickerText('searchPlaceholder', 'Search by title');

        picker.classList.toggle('has-selection', hasSelection);
        picker.classList.toggle('has-query', !!(search && search.value.trim()));

        if (search) {
            search.setAttribute('placeholder', hasSelection ? '' : searchLabel);
            search.setAttribute('aria-label', searchLabel);
        }
    }

    function ElzoFormsRenderContentPickerSelection(picker) {
        const selection = picker.querySelector('.elzo-forms-content-picker-selection');
        if (!selection) return;

        const ids = ElzoFormsContentPickerReadIds(picker);
        const unresolved = [];
        const settings = ElzoFormsContentPickerSettings(picker);

        selection.replaceChildren();
        selection.hidden = ids.length === 0;

        ids.forEach(id => {
            const label = ElzoFormsContentPickerLabel(picker, id);
            if (!label && settings.valueType === 'integer') unresolved.push(id);

            const token = document.createElement('span');
            token.className = 'elzo-forms-content-picker-token';
            if (!label) token.classList.add('is-unresolved');

            const text = document.createElement('span');
            text.className = 'elzo-forms-content-picker-token-label';
            text.textContent = label
                || (settings.unavailableLabel
                    ? settings.unavailableLabel(id)
                    : ElzoFormsContentPickerFormat('unavailable', '#%d (unavailable)', id));
            text.title = settings.valueType === 'integer' ? text.textContent + ' — ID ' + id : text.textContent;
            token.appendChild(text);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'elzo-forms-content-picker-token-remove';
            remove.setAttribute('data-content-id', String(id));
            remove.setAttribute('aria-label', ElzoFormsContentPickerText('remove', 'Remove') + ': ' + text.textContent);
            remove.innerHTML = '<span class="elzo-icon elzo-icon-close"></span>';
            token.appendChild(remove);

            selection.appendChild(token);
        });

        ElzoFormsSyncContentPickerPresentation(picker);

        if (unresolved.length) ElzoFormsResolveContentPickerLabels(picker, unresolved);
        document.dispatchEvent(new CustomEvent('elzoforms:content-picker-rendered', { detail: { root: picker } }));
    }

    /**
     * Look up titles for stored IDs that have never been searched for.
     *
     * IDs already requested are remembered on the picker so a re-render caused by
     * an unrelated change cannot start the same request again.
     */
    function ElzoFormsResolveContentPickerLabels(picker, ids) {
        const requested = (picker.dataset.resolvedIds || '').split(',').filter(Boolean);
        const pending = ids.filter(id => requested.indexOf(String(id)) === -1);
        if (!pending.length) return;

        picker.dataset.resolvedIds = requested.concat(pending.map(String)).join(',');

        const settings = ElzoFormsContentPickerSettings(picker);
        settings.search({ include: pending, postTypes: settings.postTypes, limit: pending.length, page: 1 }, items => {
            if (!items.length || !picker.isConnected) return;
            ElzoFormsRenderContentPickerSelection(picker);
        });
    }

    function ElzoFormsRenderContentPickerResults(picker, items, append) {
        const results = picker.querySelector('.elzo-forms-content-picker-results');
        if (!results) return;

        const selected = ElzoFormsContentPickerReadIds(picker);
        const settings = ElzoFormsContentPickerSettings(picker);
        const rendered = {};

        if (!append) results.replaceChildren();
        results.querySelectorAll('.elzo-forms-content-picker-result').forEach(option => {
            const value = ElzoFormsContentPickerNormalizeValue(picker, option.getAttribute('data-content-id'));
            rendered[value] = true;
            option.setAttribute('aria-selected', selected.indexOf(value) !== -1 ? 'true' : 'false');
        });

        items.forEach(item => {
            const id = ElzoFormsContentPickerNormalizeValue(picker, item.id);
            if (!id || rendered[id]) return;
            rendered[id] = true;

            const option = document.createElement('li');
            option.className = 'elzo-forms-content-picker-result';
            option.id = results.id + '-option-' + id;
            option.setAttribute('role', 'option');
            option.setAttribute('data-content-id', String(id));
            option.setAttribute('aria-selected', selected.indexOf(id) !== -1 ? 'true' : 'false');

            const title = document.createElement('span');
            title.className = 'elzo-forms-content-picker-result-title';
            title.textContent = item.title;
            option.appendChild(title);

            const metaText = settings.meta
                ? settings.meta(item, id)
                : (item.type_label || item.type || '') + ' · ' + id;
            if (metaText) {
                const meta = document.createElement('span');
                meta.className = 'elzo-forms-content-picker-result-meta';
                meta.textContent = metaText;
                option.appendChild(meta);
            }

            results.appendChild(option);
        });

        ElzoFormsToggleContentPickerResults(picker, results.children.length > 0);
    }

    function ElzoFormsToggleContentPickerResults(picker, open) {
        const results = picker.querySelector('.elzo-forms-content-picker-results');
        const search = picker.querySelector('.elzo-forms-content-picker-search');
        if (!results || !search) return;

        results.hidden = !open;
        search.setAttribute('aria-expanded', open ? 'true' : 'false');
        picker.classList.toggle('is-open', open);

        if (!open) {
            search.removeAttribute('aria-activedescendant');
            results.querySelectorAll('.is-active').forEach(option => option.classList.remove('is-active'));
        }
    }

    function ElzoFormsMoveContentPickerActive(picker, step) {
        const results = picker.querySelector('.elzo-forms-content-picker-results');
        if (!results || results.hidden) return;

        const options = Array.from(results.querySelectorAll('.elzo-forms-content-picker-result'));
        if (!options.length) return;

        const currentIndex = options.findIndex(option => option.classList.contains('is-active'));
        const nextIndex = (currentIndex + step + options.length) % options.length;

        options.forEach(option => option.classList.remove('is-active'));
        options[nextIndex].classList.add('is-active');
        options[nextIndex].scrollIntoView({ block: 'nearest' });

        const search = picker.querySelector('.elzo-forms-content-picker-search');
        if (search) search.setAttribute('aria-activedescendant', options[nextIndex].id);
    }

    function ElzoFormsSelectContentPickerOption(picker, id) {
        if (!id) return;

        const settings = ElzoFormsContentPickerSettings(picker);
        const ids = ElzoFormsContentPickerReadIds(picker);

        if (settings.multiple) {
            if (ids.indexOf(id) === -1) ids.push(id);
            ElzoFormsContentPickerWriteIds(picker, ids);
        } else {
            ElzoFormsContentPickerWriteIds(picker, [id]);
        }

        const search = picker.querySelector('.elzo-forms-content-picker-search');
        const status = picker.querySelector('.elzo-forms-content-picker-status');

        if (search) {
            search.value = '';
            ElzoFormsSyncContentPickerPresentation(picker);
            if (settings.multiple) {
                const alreadyFocused = document.activeElement === search;
                search.focus();
                if (alreadyFocused) ElzoFormsRunContentPickerSearch(picker, true);
            }
        }
        if (status) status.textContent = '';

        if (!settings.multiple) ElzoFormsToggleContentPickerResults(picker, false);
    }

    function ElzoFormsLoadContentPickerPage(picker, query, page, append) {
        const search = picker.querySelector('.elzo-forms-content-picker-search');
        const status = picker.querySelector('.elzo-forms-content-picker-status');
        if (!search) return;

        const sequence = (picker.elzoSearchSequence || 0) + 1;
        picker.elzoSearchSequence = sequence;
        picker.elzoSearchLoading = true;
        picker.setAttribute('aria-busy', 'true');

        if (status) status.textContent = ElzoFormsContentPickerText('loading', 'Searching...');

        const settings = ElzoFormsContentPickerSettings(picker);
        settings.search({ search: query, postTypes: settings.postTypes, limit: settings.limit, page: page }, (items, error, hasMore) => {
            if (picker.elzoSearchSequence !== sequence || !picker.isConnected) return;

            picker.elzoSearchLoading = false;
            picker.removeAttribute('aria-busy');
            picker.elzoSearchQuery = query;
            picker.elzoSearchPage = page;
            picker.elzoSearchHasMore = hasMore;

            ElzoFormsRenderContentPickerResults(picker, items, append);
            if (status) status.textContent = error || (!append && !items.length ? ElzoFormsContentPickerText('noResults', 'Nothing found.') : '');

            const results = picker.querySelector('.elzo-forms-content-picker-results');
            if (hasMore && results && results.scrollHeight - results.clientHeight <= 48) {
                window.setTimeout(() => {
                    if (picker.elzoSearchSequence === sequence) ElzoFormsLoadMoreContentPickerResults(picker);
                }, 0);
            }
        });
    }

    function ElzoFormsRunContentPickerSearch(picker, immediate) {
        const search = picker.querySelector('.elzo-forms-content-picker-search');
        const status = picker.querySelector('.elzo-forms-content-picker-status');
        if (!search) return;

        const query = search.value.trim();
        const settings = ElzoFormsContentPickerSettings(picker);

        window.clearTimeout(picker.elzoSearchTimer);

        if (query.length > 0 && query.length < settings.minSearchLength) {
            picker.elzoSearchSequence = (picker.elzoSearchSequence || 0) + 1;
            picker.elzoSearchLoading = false;
            picker.removeAttribute('aria-busy');
            ElzoFormsToggleContentPickerResults(picker, false);
            if (status) status.textContent = ElzoFormsContentPickerText('searchHint', 'Type at least 2 characters to search.');
            return;
        }

        const load = () => ElzoFormsLoadContentPickerPage(picker, query, 1, false);
        if (immediate) load();
        else picker.elzoSearchTimer = window.setTimeout(load, 250);
    }

    function ElzoFormsLoadMoreContentPickerResults(picker) {
        if (!picker || picker.elzoSearchLoading || !picker.elzoSearchHasMore) return;

        ElzoFormsLoadContentPickerPage(
            picker,
            picker.elzoSearchQuery || '',
            (picker.elzoSearchPage || 1) + 1,
            true
        );
    }

    /**
     * Build or refresh a content picker inside a wrapper.
     *
     * Safe to call repeatedly on the same wrapper: existing markup is reused and
     * only the parts driven by the stored value are rebuilt, so a picker keeps
     * focus and typed text across the refreshes a condition editor triggers.
     *
     * @param {HTMLElement} wrapper Element the picker lives in, next to the value control.
     * @param {Object} options Picker options: valueSelector, multiple, format, valueType, postTypes.
     */
    function ElzoFormsMountContentPicker(wrapper, options = {}) {
        if (!wrapper) return null;

        let picker = wrapper.querySelector('.elzo-forms-content-picker');

        if (!picker) {
            ElzoFormsContentPickerSequence += 1;

            picker = document.createElement('div');
            picker.className = 'elzo-forms-content-picker';

            const control = document.createElement('div');
            control.className = 'elzo-forms-field-control elzo-forms-content-picker-control';
            picker.appendChild(control);

            const selection = document.createElement('div');
            selection.className = 'elzo-forms-content-picker-selection';
            control.appendChild(selection);

            const search = document.createElement('input');
            search.type = 'search';
            search.autocomplete = 'off';
            search.className = 'elzo-forms-content-picker-search';
            search.setAttribute('role', 'combobox');
            search.setAttribute('aria-autocomplete', 'list');
            search.setAttribute('aria-expanded', 'false');
            search.setAttribute('aria-controls', 'elzo-forms-content-picker-results-' + ElzoFormsContentPickerSequence);
            control.appendChild(search);

            const results = document.createElement('ul');
            results.className = 'elzo-forms-content-picker-results';
            results.id = 'elzo-forms-content-picker-results-' + ElzoFormsContentPickerSequence;
            results.setAttribute('role', 'listbox');
            results.hidden = true;
            picker.appendChild(results);

            const status = document.createElement('p');
            status.className = 'screen-reader-text elzo-forms-content-picker-status';
            status.setAttribute('aria-live', 'polite');
            picker.appendChild(status);

            wrapper.appendChild(picker);
        }

        // A cloned rule arrives carrying the original's element ids; the first
        // element to claim an id keeps it and the clone takes a fresh one.
        const results = picker.querySelector('.elzo-forms-content-picker-results');
        const search = picker.querySelector('.elzo-forms-content-picker-search');
        if (results && (!results.id || document.getElementById(results.id) !== results)) {
            ElzoFormsContentPickerSequence += 1;
            results.id = 'elzo-forms-content-picker-results-' + ElzoFormsContentPickerSequence;

            // cloneNode() copies data attributes but not the in-flight request
            // that data-resolved-ids describes. Let a cloned picker resolve any
            // uncached selected IDs for itself instead of leaving stale labels.
            picker.removeAttribute('data-resolved-ids');
        }
        if (results && search) search.setAttribute('aria-controls', results.id);

        // Results belong to the query that produced them, never to a clone.
        if (results) results.replaceChildren();

        picker.setAttribute('data-multiple', options.multiple ? '1' : '0');
        picker.setAttribute('data-format', options.format === 'json' ? 'json' : 'csv');
        picker.setAttribute('data-value-type', options.valueType === 'string' ? 'string' : 'integer');
        picker.setAttribute('data-post-types', (options.postTypes || []).join(','));
        picker.setAttribute('data-value-selector', options.valueSelector);
        picker.elzoContentPickerOptions = {
            search: typeof options.search === 'function' ? options.search : ElzoFormsContentSearch,
            limit: options.limit || 20,
            minSearchLength: options.minSearchLength ?? 2,
            label: typeof options.label === 'function' ? options.label : null,
            unavailableLabel: typeof options.unavailableLabel === 'function' ? options.unavailableLabel : null,
            meta: typeof options.meta === 'function' ? options.meta : null,
        };

        ElzoFormsToggleContentPickerResults(picker, false);
        ElzoFormsRenderContentPickerSelection(picker);

        return picker;
    }

    function ElzoFormsRemoveContentPicker(wrapper) {
        const picker = wrapper ? wrapper.querySelector('.elzo-forms-content-picker') : null;
        if (picker) picker.remove();
    }

    /**
     * Reusable admin content picker API.
     *
     * A caller owns the hidden value control and supplies a search adapter with
     * the same callback contract as the built-in WordPress content endpoint.
     * This keeps the UI reusable for future admin content selectors while
     * conditional logic remains only one consumer of it today.
     */
    const ElzoFormsAdminContentPicker = Object.freeze({
        mount: ElzoFormsMountContentPicker,
        unmount: ElzoFormsRemoveContentPicker,
        refresh(target) {
            const picker = target && target.classList && target.classList.contains('elzo-forms-content-picker')
                ? target
                : (target ? target.querySelector('.elzo-forms-content-picker') : null);
            if (picker) ElzoFormsRenderContentPickerSelection(picker);
            return picker;
        },
    });

    window.ElzoFormsAdminContentPicker = ElzoFormsAdminContentPicker;

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('elzo-forms-content-picker-search')) {
            const picker = e.target.closest('.elzo-forms-content-picker');
            if (picker) {
                ElzoFormsSyncContentPickerPresentation(picker);
                ElzoFormsRunContentPickerSearch(picker);
            }
        }
    });

    document.addEventListener('focusin', function(e) {
        if (!e.target.classList || !e.target.classList.contains('elzo-forms-content-picker-search')) return;

        const picker = e.target.closest('.elzo-forms-content-picker');
        if (picker) ElzoFormsRunContentPickerSearch(picker, true);
    });

    document.addEventListener('scroll', function(e) {
        const results = e.target && e.target.classList && e.target.classList.contains('elzo-forms-content-picker-results')
            ? e.target
            : null;
        if (!results || results.hidden || results.scrollHeight - results.scrollTop - results.clientHeight > 48) return;

        ElzoFormsLoadMoreContentPickerResults(results.closest('.elzo-forms-content-picker'));
    }, true);

    document.addEventListener('keydown', function(e) {
        if (!e.target.classList || !e.target.classList.contains('elzo-forms-content-picker-search')) return;

        const picker = e.target.closest('.elzo-forms-content-picker');
        if (!picker) return;

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            ElzoFormsMoveContentPickerActive(picker, e.key === 'ArrowDown' ? 1 : -1);
            return;
        }

        if (e.key === 'Enter') {
            const active = picker.querySelector('.elzo-forms-content-picker-result.is-active');
            // Enter with no highlighted result must not submit the form editor.
            e.preventDefault();
            if (active) ElzoFormsSelectContentPickerOption(picker, ElzoFormsContentPickerNormalizeValue(picker, active.getAttribute('data-content-id')));
            return;
        }

        if (e.key === 'Backspace' && e.target.value === '') {
            const ids = ElzoFormsContentPickerReadIds(picker);
            if (ids.length) {
                ids.pop();
                ElzoFormsContentPickerWriteIds(picker, ids);
            }
            return;
        }

        if (e.key === 'Escape') {
            ElzoFormsToggleContentPickerResults(picker, false);
        }
    });

    document.addEventListener('click', function(e) {
        const option = e.target.closest ? e.target.closest('.elzo-forms-content-picker-result') : null;
        if (option) {
            const picker = option.closest('.elzo-forms-content-picker');
            if (picker) {
                e.preventDefault();
                ElzoFormsSelectContentPickerOption(picker, ElzoFormsContentPickerNormalizeValue(picker, option.getAttribute('data-content-id')));
            }
            return;
        }

        const remove = e.target.closest ? e.target.closest('.elzo-forms-content-picker-token-remove') : null;
        if (remove) {
            const picker = remove.closest('.elzo-forms-content-picker');
            if (picker) {
                e.preventDefault();
                const removedId = ElzoFormsContentPickerNormalizeValue(picker, remove.getAttribute('data-content-id'));
                ElzoFormsContentPickerWriteIds(picker, ElzoFormsContentPickerReadIds(picker).filter(id => id !== removedId));
            }
            return;
        }

        const control = e.target.closest ? e.target.closest('.elzo-forms-content-picker-control') : null;
        if (control) {
            const search = control.querySelector('.elzo-forms-content-picker-search');
            if (search) search.focus();
            return;
        }

        document.querySelectorAll('.elzo-forms-content-picker').forEach(picker => {
            if (!picker.contains(e.target)) ElzoFormsToggleContentPickerResults(picker, false);
        });
    });
    // URL rules are typed into a plain value input; the placeholder is the only
    // hint about the expected format. Mirrored in admin-logic-item.php so the
    // hint is present before the first change event.
    function ElzoFormsLogicValuePlaceholder(type) {
        if (type === 'url') return 'https://example.com/pricing/';

        return '';
    }

    /**
     * Turn a condition value control into a select, keeping its name and value.
     */
    function ElzoFormsEnsureLogicValueSelect(rule) {
        const currentValueField = ElzoFormsGetLogicRuleValueField(rule);
        if (!currentValueField) return null;

        if (currentValueField.tagName === 'SELECT') {
            currentValueField.hidden = false;
            return currentValueField;
        }

        const replacementField = document.createElement('select');
        replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-select';

        const fieldName = currentValueField.getAttribute('name');
        if (fieldName) replacementField.setAttribute('name', fieldName);

        currentValueField.replaceWith(replacementField);

        return replacementField;
    }

    /**
     * Offer the registered post types instead of a hand-typed slug.
     */
    function ElzoFormsApplyLogicPostTypeSelect(rule, multiple) {
        const valueWrapper = rule.querySelector('.elzo-forms-field-logic-group-rule-value');

        if (multiple) {
            const valueInput = ElzoFormsEnsureLogicValueInput(rule, 'text');
            if (!valueWrapper || !valueInput) return;

            const postTypes = ElzoFormsReadPostTypeArray(valueInput.value);
            valueInput.value = postTypes.join(', ');
            valueInput.hidden = true;
            valueInput.readOnly = false;

            ElzoFormsAdminContentPicker.mount(valueWrapper, {
                valueSelector: '.elzo-forms-field-logic-group-rule-value-input',
                multiple: true,
                format: 'csv',
                valueType: 'string',
                search: ElzoFormsPostTypeSearch,
                minSearchLength: 0,
                label: ElzoFormsPostTypeLabel,
                unavailableLabel(value) {
                    return ElzoFormsContentPickerFormat('postTypeUnavailable', '%s (unavailable)', value);
                },
                meta(item, value) {
                    return value;
                },
            });
            return;
        }

        ElzoFormsAdminContentPicker.unmount(valueWrapper);

        const currentValueField = ElzoFormsGetLogicRuleValueField(rule);
        const storedValue = currentValueField ? String(currentValueField.value || '') : '';
        // Switching over from a page ID operator leaves IDs behind, which are not slugs.
        const currentValue = ElzoFormsReadPostTypeArray(storedValue)[0] || '';
        const select = ElzoFormsEnsureLogicValueSelect(rule);
        if (!select) return;

        select.replaceChildren();

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = ElzoFormsContentPickerText('selectPostType', 'Select a post type');
        select.appendChild(placeholder);

        let matched = false;

        ElzoFormsContentPostTypeOptions().forEach(function(postType) {
            const value = String(postType.value || '');
            if (!value) return;

            const option = document.createElement('option');
            option.value = value;
            option.textContent = postType.label || value;
            if (value === currentValue) matched = true;
            select.appendChild(option);
        });

        // A slug stored before its post type was unregistered stays selectable, so
        // re-saving the form cannot silently drop the condition.
        if (currentValue !== '' && !matched) {
            const option = document.createElement('option');
            option.value = currentValue;
            option.textContent = ElzoFormsContentPickerFormat('postTypeUnavailable', '%s (unavailable)', currentValue);
            select.appendChild(option);
        }

        select.value = currentValue;
    }

    /**
     * Replace the ID input with a searchable picker over the site's content.
     */
    function ElzoFormsApplyLogicPagePicker(rule, multiple) {
        const valueWrapper = rule.querySelector('.elzo-forms-field-logic-group-rule-value');
        const valueInput = ElzoFormsEnsureLogicValueInput(rule, 'text');
        if (!valueWrapper || !valueInput) return;

        valueInput.readOnly = false;
        valueInput.removeAttribute('placeholder');
        valueInput.removeAttribute('step');
        valueInput.hidden = true;

        // Single-page operators compare against one ID, and a post type slug left
        // behind by another operator is not an ID at all: keep only what the
        // current operator can actually use.
        const ids = ElzoFormsReadIntegerArray(valueInput.value);
        const normalizedValue = (multiple ? ids : ids.slice(0, 1)).join(', ');
        if (valueInput.value !== normalizedValue) valueInput.value = normalizedValue;

        ElzoFormsAdminContentPicker.mount(valueWrapper, {
            valueSelector: '.elzo-forms-field-logic-group-rule-value-input',
            multiple: multiple,
            format: 'csv',
            postTypes: [],
        });
    }

    function ElzoFormsApplyLogicPageValueControl(rule, operator) {
        if (String(operator).startsWith('post_type_')) {
            ElzoFormsApplyLogicPostTypeSelect(rule, operator === 'post_type_in' || operator === 'post_type_not_in');
            return;
        }

        ElzoFormsApplyLogicPagePicker(rule, operator === 'page_id_in' || operator === 'page_id_not_in');
    }

    /**
     * Refresh UI of a single field-visibility condition item after type or operator changes.
     * Mirrors ElzoFormsRefreshAutomationConditionItem but for .elzo-forms-field-logic-condition-item elements.
     */
    function ElzoFormsSyncConditionTypeButton(item, type, picker = ElzoFormsConditionTypePicker) {
        const button = item.querySelector('.elzo-forms-condition-type-button');
        if (!button) return;

        const option = picker.options.find(candidate => candidate.type === type);
        if (!option) return; // Preserve PHP's defensive fallback for unknown saved types.

        const label = button.querySelector('.elzo-forms-condition-type-button-label');
        if (label) label.textContent = option.labelText;

        const oldIcon = button.querySelector('.elzo-forms-field-type-icon');
        const optionIcon = option.element.querySelector('.elzo-forms-field-type-icon');
        if (oldIcon && optionIcon) oldIcon.replaceWith(optionIcon.cloneNode(true));
    }

    function ElzoFormsRefreshFieldLogicItem(item) {
        if (!item) return;

        const typeSelect = item.querySelector('.elzo-forms-field-logic-condition-type-select');
        if (!typeSelect) return;

        const type           = typeSelect.value;
        ElzoFormsSyncConditionTypeButton(item, type);
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

        if (type === 'page') {
            ElzoFormsApplyLogicPageValueControl(item, operatorSelect ? operatorSelect.value : '');
        } else if (type !== 'field') {
            ElzoFormsAdminContentPicker.unmount(item.querySelector('.elzo-forms-field-logic-group-rule-value'));

            const valueInput = ElzoFormsEnsureLogicValueInput(item, type === 'date_time' ? 'datetime-local' : 'text');
            if (valueInput) {
                valueInput.hidden = false;
                valueInput.readOnly = false;
                valueInput.removeAttribute('placeholder');

                const valuePlaceholder = ElzoFormsLogicValuePlaceholder(type);
                if (valuePlaceholder) valueInput.setAttribute('placeholder', valuePlaceholder);

                if (type !== 'date_time') {
                    valueInput.removeAttribute('step');
                } else {
                    valueInput.setAttribute('step', '1');
                }
            }
        }

        item.setAttribute('data-condition-type', type);

        // Settings of a rule whose type is not registered right now are only
        // posted back, and its notice only shown, while the rule keeps that type.
        item.querySelectorAll('.elzo-forms-field-logic-stored-setting').forEach(function(input) {
            input.disabled = input.getAttribute('data-stored-type') !== type;
        });
        item.querySelectorAll('.elzo-forms-field-logic-unavailable-notice').forEach(function(notice) {
            notice.hidden = notice.getAttribute('data-stored-type') !== type;
        });

        // When type=field, also rebuild value input and operators from selected field
        if (type === 'field') {
            ElzoFormsAdminContentPicker.unmount(item.querySelector('.elzo-forms-field-logic-group-rule-value'));
            ElzoFormsRefreshLogicRule(item);
        }
    }

    function ElzoFormsRefreshFieldLogicItems() {
        document.querySelectorAll('.elzo-forms-field-logic-condition-item').forEach(ElzoFormsRefreshFieldLogicItem);
    }

    function ElzoFormsReadFieldLogicConditionShape(item) {
        if (!item) return null;

        const typeSelect = item.querySelector('.elzo-forms-field-logic-condition-type-select');
        const operatorSelect = item.querySelector('.elzo-forms-field-logic-condition-operator-select');

        return {
            type: typeSelect ? typeSelect.value : '',
            operator: operatorSelect ? operatorSelect.value : '',
        };
    }

    function ElzoFormsResetClonedConditionFields(container) {
        if (!container) return;

        // Stored settings belong to the saved rule they came from, not to a copy.
        container.querySelectorAll('.elzo-forms-field-logic-stored-setting').forEach(function(input) {
            input.remove();
        });

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

    function ElzoFormsApplyFieldLogicConditionShape(item, conditionShape) {
        if (!item || !conditionShape) return;

        const typeSelect = item.querySelector('.elzo-forms-field-logic-condition-type-select');
        if (!typeSelect) return;

        typeSelect.value = conditionShape.type || '';

        // Rebuild the operator list before restoring its value. A new OR group
        // comes from the hidden default template, whose operator options may not
        // contain the operator used by the visible Page rule being continued.
        ElzoFormsRefreshFieldLogicItem(item);

        const operatorSelect = item.querySelector('.elzo-forms-field-logic-condition-operator-select');
        if (operatorSelect && conditionShape.operator) {
            operatorSelect.value = conditionShape.operator;
        }

        ElzoFormsRefreshFieldLogicItem(item);
    }

    function ElzoFormsCopyFormControlState(source, clone) {
        if (!source || !clone) return;

        const sourceControls = source.querySelectorAll('input, select, textarea');
        const clonedControls = clone.querySelectorAll('input, select, textarea');

        sourceControls.forEach(function(sourceControl, index) {
            const clonedControl = clonedControls[index];
            if (!clonedControl) return;

            // Search text and an open result list are transient picker UI, not
            // part of the configured condition being duplicated.
            if (sourceControl.classList.contains('elzo-forms-content-picker-search')) {
                clonedControl.value = '';
                return;
            }

            if (sourceControl.type === 'checkbox' || sourceControl.type === 'radio') {
                clonedControl.checked = sourceControl.checked;
            } else if (sourceControl.type !== 'file') {
                clonedControl.value = sourceControl.value;
            }

            if (sourceControl.tagName === 'SELECT') {
                const sourceOptions = sourceControl.querySelectorAll('option');
                const clonedOptions = clonedControl.querySelectorAll('option');

                sourceOptions.forEach(function(sourceOption, optionIndex) {
                    if (clonedOptions[optionIndex]) {
                        clonedOptions[optionIndex].selected = sourceOption.selected;
                    }
                });
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
        const typeInput = document.getElementById('elzo-forms-field-type-' + id);
        if (!typeInput) return;
        // The Type control shows the type's picker name ("Email", "File Upload").
        const typeLabel = field.querySelector('.elzo-forms-field-type-button-label')?.textContent.trim() || typeInput.value;
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
            logicValuePlaceholder: fieldData && fieldData.logic_value_placeholder
                ? String(fieldData.logic_value_placeholder)
                : '',
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

    /**
     * Whether an operator compares a number of values rather than a value.
     */
    function ElzoFormsIsLogicCountOperator(operator) {
        const countOperators = Array.isArray(window.ElzoFormsAdmin?.logicCountOperators)
            ? window.ElzoFormsAdmin.logicCountOperators
            : [];

        return countOperators.includes(operator);
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
        const logicValuePlaceholder = selectedOption ? (selectedOption.getAttribute('data-logic-value-placeholder') || '') : '';
        const currentValue = currentValueField.value;
        const fieldName = currentValueField.getAttribute('name');
        const selectOperators = ['==', '!='];
        let fieldOptions = [];

        try {
            fieldOptions = rawFieldOptions ? JSON.parse(rawFieldOptions) : [];
        } catch (error) {
            console.error('Error parsing logic field options:', error);
        }

        const isCountOperator = ElzoFormsIsLogicCountOperator(operatorSelect.value);
        const shouldUseSelect = !isCountOperator
            && logicValueSource === 'options'
            && selectOperators.includes(operatorSelect.value)
            && fieldOptions.length > 0;
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
        } else if (isCountOperator) {
            replacementField = document.createElement('input');
            replacementField.type = 'number';
            replacementField.min = '0';
            replacementField.step = '1';
            replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-input';
            replacementField.setAttribute('name', fieldName);
            replacementField.value = /^\d+$/.test(currentValue) ? currentValue : '';
            replacementField.placeholder = '0';
        } else {
            replacementField = document.createElement('input');
            replacementField.type = 'text';
            replacementField.className = 'elzo-forms-field-control elzo-forms-field-logic-group-rule-value-input';
            replacementField.setAttribute('name', fieldName);
            replacementField.value = currentValue;

            if (logicValueSource === 'disabled') {
                replacementField.value = '';
                replacementField.placeholder = (window.ElzoFormsAdmin && window.ElzoFormsAdmin.logicValueUnavailable) || 'Not available for this field type';
                replacementField.readOnly = true;
            } else if (logicValuePlaceholder) {
                // The field says what its value looks like; without the hint an
                // author cannot tell what a file condition compares against.
                replacementField.placeholder = logicValuePlaceholder;
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
            // Without a list from the field, offer the value operators only:
            // fields that submit several values list the count operators.
            Array.from(operatorSelect.options).forEach(function(option) {
                if (ElzoFormsIsLogicCountOperator(option.value)) {
                    option.remove();
                }
            });
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
            const unavailableOption = document.createElement('option');
            unavailableOption.value = '';
            unavailableOption.textContent = (window.ElzoFormsAdmin && window.ElzoFormsAdmin.logicOperatorUnavailable) || 'Not available';
            operatorSelect.innerHTML = '';
            operatorSelect.appendChild(unavailableOption);
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
                        logicValuePlaceholder: fieldLogicData ? fieldLogicData.logicValuePlaceholder : '',
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
                const optionLogicValuePlaceholder = option.logicValuePlaceholder ? String(option.logicValuePlaceholder).replace(/"/g, '&quot;') : '';
                const optionLogicOperators = JSON.stringify(option.logicOperators).replace(/"/g, '&quot;');
                const optionTitle = option.title ? String(option.title).replace(/"/g, '&quot;') : '';
                const optionLabel = ElzoFormsTruncateLogicOptionLabel(option.title || '');
                optionsHTML = optionsHTML + '<option value="' + option.id + '" title="' + optionTitle + '" data-field-type="' + optionType + '" data-field-options="' + optionFieldOptions + '" data-logic-value-source="' + optionLogicValueSource + '" data-logic-value-placeholder="' + optionLogicValuePlaceholder + '" data-logic-operators="' + optionLogicOperators + '">' + optionLabel + '</option>';
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

                    // Action settings and automation conditions must require an
                    // explicit field choice. Other field selectors keep their
                    // historical first-option default.
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

    /**
     * Add a field at the end of a step.
     *
     * The field is created from the builder's field template, a Text field,
     * and then changed to the requested type in one step.
     *
     * @param {HTMLElement} step Step to add the field to.
     * @param {string} type Field type, e.g. "textarea" or "text:email"; empty keeps Text.
     * @returns {HTMLElement|null} The new field.
     */
    function ElzoFormsAddField(step, type) {
        const repeater = document.getElementById('elzo-forms-steps-repeater');
        const stepFieldsRepeater = step ? step.querySelector('.elzo-forms-fields-repeater') : null;
        const template = document.getElementById('elzo-forms-repeater-field-template');

        if (!repeater || !stepFieldsRepeater || !template) return null;

        stepFieldsRepeater.insertAdjacentHTML('beforeend', template.innerHTML);

        const field = stepFieldsRepeater.lastElementChild;

        ElzoFormsToggleField(field);
        ElzoFormsUpdateRepeaterFields(repeater);

        if (type) {
            ElzoFormsChangeFieldType(field, type);
        }

        return field;
    }

    // Show a field's type in its Type control and header.
    function ElzoFormsSetFieldType(field, type) {
        const typeInput = document.getElementById('elzo-forms-field-type-' + field.getAttribute('data-id'));
        if (!typeInput) return;

        typeInput.value = type;

        const option = ElzoFormsFieldPicker.options.find(item => item.type === type);
        const button = field.querySelector('.elzo-forms-field-type-button');

        if (option && button) {
            const icon = button.querySelector('.elzo-forms-field-type-icon');
            const optionIcon = option.element.querySelector('.elzo-forms-field-type-icon');
            const label = button.querySelector('.elzo-forms-field-type-button-label');

            if (icon && optionIcon) icon.replaceWith(optionIcon.cloneNode(true));
            if (label) label.textContent = option.labelText;
        }

        ElzoFormsBuildFieldHeaderTitle(field);
    }

    /**
     * Change a field's type as one operation.
     *
     * The complete type ("text:email") is shown at once and sent to the
     * server in a single request, which returns settings that already belong
     * to it.
     */
    function ElzoFormsChangeFieldType(field, type) {
        const typeInput = field ? document.getElementById('elzo-forms-field-type-' + field.getAttribute('data-id')) : null;
        if (!typeInput || !type || typeInput.value === type) return;

        // The type whose settings are on screen; a failed change returns to it.
        if (field.elzoSettledType === undefined) {
            field.elzoSettledType = typeInput.value;
        }

        ElzoFormsSetFieldType(field, type);
        ElzoFormsLoadFieldTypeSettings(field, type);
    }

    // A setting's name inside its field: "[placeholder]", "[range][min]".
    function ElzoFormsFieldSettingKey(name) {
        const match = /^elzo_form_fields\[\d+\]\[fields\]\[\d+\](\[.+)$/.exec(name || '');

        return match ? match[1] : '';
    }

    function ElzoFormsIsCarriedSettingControl(control) {
        return !['hidden', 'file', 'button', 'submit', 'reset', 'image'].includes(control.type);
    }

    /**
     * Read the current values of a field's type-specific settings.
     *
     * Values come from the controls on screen, so unsaved edits are included.
     */
    function ElzoFormsReadFieldSettingValues(field, wrappers) {
        const values = {};

        ElzoFormsSyncTinyMCEValues(field);

        wrappers.forEach(wrapper => {
            wrapper.querySelectorAll('input[name], select[name], textarea[name]').forEach(control => {
                const key = ElzoFormsFieldSettingKey(control.getAttribute('name'));
                if (!key || !ElzoFormsIsCarriedSettingControl(control)) return;

                if (control.type === 'checkbox') {
                    if (key.endsWith('[]')) {
                        values[key] = values[key] || [];
                        if (control.checked) values[key].push(control.value);
                    } else {
                        values[key] = control.checked;
                    }
                } else if (control.type === 'radio') {
                    if (control.checked) values[key] = control.value;
                } else if (control.tagName === 'SELECT' && control.multiple) {
                    values[key] = Array.from(control.selectedOptions, option => option.value);
                } else {
                    values[key] = control.value;
                }
            });
        });

        return values;
    }

    // Give a rebuilt settings wrapper the carried values of the settings it has.
    function ElzoFormsRestoreFieldSettingValues(wrapper, values) {
        wrapper.querySelectorAll('input[name], select[name], textarea[name]').forEach(control => {
            const key = ElzoFormsFieldSettingKey(control.getAttribute('name'));
            if (!key || !ElzoFormsIsCarriedSettingControl(control) || !Object.prototype.hasOwnProperty.call(values, key)) return;

            const value = values[key];

            if (control.type === 'checkbox') {
                control.checked = Array.isArray(value) ? value.includes(control.value) : !!value;
            } else if (control.type === 'radio') {
                control.checked = control.value === value;
            } else if (control.tagName === 'SELECT') {
                const wanted = Array.isArray(value) ? value : [String(value)];
                const options = Array.from(control.options);

                if (control.multiple) {
                    options.forEach(option => { option.selected = wanted.includes(option.value); });
                } else if (options.some(option => option.value === wanted[0])) {
                    // A value the new type does not offer keeps the new default.
                    control.value = wanted[0];
                }
            } else if (typeof value === 'string') {
                control.value = value;
            }
        });
    }

    /**
     * Load the type-specific settings of a field's new type.
     *
     * Settings the new type shares with earlier ones keep their current
     * values, unsaved edits included: they are read from the controls on
     * screen and remembered for the field across type changes, so switching
     * Text, Textarea and back to Text loses nothing. ".field-initial-value"
     * only holds the server's data for the current type.
     *
     * A newer change aborts the pending request, and a response only applies
     * while its request is still current, so quick changes (Text, Email,
     * Number) always end on the last choice.
     */
    function ElzoFormsLoadFieldTypeSettings(field, type) {
        const fieldDataInput = field.querySelector('.field-initial-value');
        const fieldSpecificSettingsWrappers = field.querySelectorAll('.elzo-forms-field-specific-settings-wrapper');
        const formData = new FormData();

        field.elzoCarriedSettings = Object.assign(
            field.elzoCarriedSettings || {},
            ElzoFormsReadFieldSettingValues(field, fieldSpecificSettingsWrappers)
        );

        if (field.elzoTypeRequest) {
            field.elzoTypeRequest.abort();
        }

        const request = new AbortController();
        field.elzoTypeRequest = request;

        formData.append('action', 'elzo_forms');
        formData.append('nonce', ElzoFormsAdmin.nonce);
        formData.append('field_type', type);
        formData.append('field_id', field.querySelector('.field-id-value').value);
        formData.append('field_index', field.querySelector('.field-index-value').value);
        formData.append('step_index', field.closest('.elzo-forms-step').querySelector('.step-index-value').value);

        fieldSpecificSettingsWrappers.forEach(wrapper => wrapper.classList.add('loading'));

        const fail = function(message) {
            if (request.signal.aborted) return;

            field.elzoTypeRequest = null;
            fieldSpecificSettingsWrappers.forEach(wrapper => wrapper.classList.remove('loading'));

            // The settings on screen still belong to the last loaded type.
            ElzoFormsSetFieldType(field, field.elzoSettledType);
            console.error(message);
        };

        fetch(ElzoFormsAdmin.ajaxurl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            signal: request.signal
        })
        .then(response => response.json())
        .then(data => {
            if (request.signal.aborted) return;

            if (!data.success) {
                fail('An error occurred: ' + (data.data && data.data.message ? data.data.message : 'Unknown error'));
                return;
            }

            field.elzoTypeRequest = null;
            field.elzoSettledType = type;

            const responseData = data.data || {};

            fieldSpecificSettingsWrappers.forEach(wrapper => {
                wrapper.classList.remove('loading');

                // Editors of the replaced markup would otherwise outlive their textareas.
                ElzoFormsDestroyTinyMCE(wrapper);

                const category = wrapper.getAttribute('data-setting-category');

                if (responseData.html && responseData.html[category]) {
                    wrapper.innerHTML = responseData.html[category];
                    wrapper.style.display = 'block';
                    ElzoFormsRestoreFieldSettingValues(wrapper, field.elzoCarriedSettings || {});
                } else {
                    wrapper.innerHTML = '';
                    wrapper.style.display = 'none';
                }
            });

            if (fieldDataInput && responseData.field) {
                fieldDataInput.value = JSON.stringify(responseData.field, null, 4);
            }

            ElzoFormsSyncReadOnlyFieldSettings(field, !!responseData.field?.read_only);
            ElzoFormsBuildFieldHeaderTitle(field);
            ElzoFormsInitTinyMCE(field);
            ElzoFormsFieldSelects();
            document.dispatchEvent(new CustomEvent('elzo-forms-refresh-logic-elements'));
        })
        .catch(error => fail(error));
    }

    /*
     * Shared type-picker behavior.
     *
     * The field and conditional-type pickers use the same search, ranking,
     * keyboard, focus and viewport-positioning implementation. Focus stays in
     * the active search input; aria-activedescendant follows the listbox.
     */
    const ElzoFormsFieldPicker = {
        picker: document.getElementById('elzo-forms-field-picker'),
        search: null,
        list: null,
        results: null,
        status: null,
        trigger: null,
        current: '',
        onChoose: null,
        options: [],
        visible: [],
        activeIndex: -1,
    };

    const ElzoFormsConditionTypePicker = {
        picker: document.getElementById('elzo-forms-condition-type-picker'),
        search: null,
        list: null,
        results: null,
        status: null,
        trigger: null,
        current: '',
        onChoose: null,
        options: [],
        visible: [],
        activeIndex: -1,
    };

    const ElzoFormsPickers = [ElzoFormsFieldPicker, ElzoFormsConditionTypePicker];
    // Case-, accent- and whitespace-insensitive form used for matching.
    function ElzoFormsNormalizePickerText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLocaleLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    /*
     * Rank an option against a query; lower is better, null is no match.
     *
     * Matching is deliberately literal: names match by prefix, keywords by
     * whole word prefixes, and a name only matches in the middle for queries
     * of three characters or more. Single characters match names only.
     */
    function ElzoFormsScorePickerOption(option, query, tokens) {
        if (option.label === query) return 0;
        if (option.label.startsWith(query)) return 1;
        if (option.labelWords.some(word => word.startsWith(query))) return 2;
        if (query.length < 2) return null;
        if (option.terms.includes(query)) return 3;
        if (option.terms.some(term => term.startsWith(query))) return 4;
        if (tokens.every(token => option.words.some(word => word.startsWith(token)))) return 5;
        if (query.length >= 3 && option.label.includes(query)) return 6;

        return null;
    }

    function ElzoFormsRevealPickerOption(state, option) {
        const list = state.list;
        const element = option.element;
        const previous = element.previousElementSibling;

        // The first option of a category brings its heading along.
        const top = (previous && previous.classList.contains('elzo-forms-field-picker-group-label') ? previous : element).offsetTop;
        const bottom = element.offsetTop + element.offsetHeight;
        const padding = parseFloat(getComputedStyle(list).paddingTop) || 0;

        if (top - padding < list.scrollTop) {
            list.scrollTop = Math.max(0, top - padding);
        } else if (bottom + padding > list.scrollTop + list.clientHeight) {
            list.scrollTop = bottom + padding - list.clientHeight;
        }
    }

    function ElzoFormsSetPickerActive(state, index, reveal = true) {
        state.options.forEach(option => {
            option.element.classList.remove('is-active');
            option.element.setAttribute('aria-selected', 'false');
        });

        state.activeIndex = state.visible[index] ? index : -1;

        const option = state.visible[state.activeIndex];
        if (!option) {
            state.search.removeAttribute('aria-activedescendant');
            return;
        }

        option.element.classList.add('is-active');
        option.element.setAttribute('aria-selected', 'true');
        state.search.setAttribute('aria-activedescendant', option.element.id);

        if (reveal) ElzoFormsRevealPickerOption(state, option);
    }

    function ElzoFormsMovePickerActive(state, step) {
        const count = state.visible.length;
        if (!count) return;

        const current = state.activeIndex;
        const next = current < 0
            ? (step > 0 ? 0 : count - 1)
            : (current + step + count) % count;

        ElzoFormsSetPickerActive(state, next);
    }

    /*
     * Show the options matching the search input.
     *
     * An empty search shows every option under its category. A search shows a
     * flat list ordered by relevance, so the best match is always first.
     */
    function ElzoFormsFilterPicker(state) {
        const query = ElzoFormsNormalizePickerText(state.search.value);
        const tokens = query ? query.split(' ') : [];

        // Options return to their categories, in order, before every search.
        state.options.forEach(option => option.group.appendChild(option.element));

        if (query === '') {
            state.visible = state.options.slice();
        } else {
            state.visible = state.options
                .map(option => ({ option: option, score: ElzoFormsScorePickerOption(option, query, tokens) }))
                .filter(match => match.score !== null)
                .sort((a, b) => a.score - b.score || a.option.index - b.option.index)
                .map(match => match.option);

            state.visible.forEach(option => state.results.appendChild(option.element));
        }

        state.results.hidden = query === '';
        state.list.querySelectorAll('.elzo-forms-field-picker-group').forEach(group => {
            group.hidden = query !== '';
        });

        const hasResults = state.visible.length > 0;
        state.list.hidden = !hasResults;
        state.search.setAttribute('aria-expanded', hasResults ? 'true' : 'false');
        state.status.textContent = hasResults ? '' : (state.status.getAttribute('data-empty-message') || '');

        // An empty search starts on the field's current type, when there is one.
        const currentIndex = query === '' ? state.visible.findIndex(option => option.type === state.current) : -1;

        state.list.scrollTop = 0;
        ElzoFormsSetPickerActive(state, hasResults ? Math.max(0, currentIndex) : -1);
    }

    /*
     * Place the picker next to its button, inside the viewport.
     *
     * It opens below the button unless there is more room above. Opening
     * above anchors its bottom edge to the button (see .is-above), so the
     * picker stays attached while a search shortens the list.
     */
    function ElzoFormsPositionPicker(state) {
        const picker = state.picker;
        const trigger = state.trigger;

        if (!picker || picker.hidden || !trigger) return;

        const margin = 8;
        const gap = 4;
        const rect = trigger.getBoundingClientRect();
        const viewportWidth = document.documentElement.clientWidth;
        const viewportHeight = window.innerHeight;
        const adminBar = document.getElementById('wpadminbar');
        const topLimit = adminBar && getComputedStyle(adminBar).position === 'fixed'
            ? Math.max(0, adminBar.getBoundingClientRect().bottom)
            : 0;

        // Measure at the natural size before fitting the list to the room available.
        picker.classList.remove('is-above');
        picker.style.maxWidth = (viewportWidth - margin * 2) + 'px';
        state.list.style.maxHeight = '';

        const naturalHeight = picker.offsetHeight;
        const spaceBelow = viewportHeight - rect.bottom - gap - margin;
        const spaceAbove = rect.top - topLimit - gap - margin;
        const placeAbove = naturalHeight > spaceBelow && spaceAbove > spaceBelow;
        const available = placeAbove ? spaceAbove : spaceBelow;

        if (naturalHeight > available) {
            state.list.style.maxHeight = Math.max(120, state.list.offsetHeight - (naturalHeight - available)) + 'px';
        }

        const width = picker.offsetWidth;
        const alignRight = getComputedStyle(trigger).direction === 'rtl';
        const left = Math.min(
            Math.max(alignRight ? rect.right - width : rect.left, margin),
            Math.max(margin, viewportWidth - width - margin)
        );

        picker.classList.toggle('is-above', placeAbove);
        picker.style.left = (left + window.scrollX) + 'px';
        picker.style.top = ((placeAbove ? rect.top - gap : rect.bottom + gap) + window.scrollY) + 'px';
    }

    /**
     * Open the picker for a trigger.
     *
     * @param {HTMLElement} trigger Button that opened the picker; focus returns to it.
     * @param {{label: string, current: string, onChoose: function(Object)}} settings
     *        Dialog label, the current type (if any) and the chosen item handler.
     */
    function ElzoFormsOpenPicker(state, trigger, settings) {
        ElzoFormsPickers.forEach(pickerState => ElzoFormsClosePicker(pickerState, false));

        state.trigger = trigger;
        state.current = settings.current || '';
        state.onChoose = settings.onChoose;
        trigger.setAttribute('aria-expanded', 'true');
        state.picker.setAttribute('aria-label', settings.label || '');
        state.options.forEach(option => option.element.classList.toggle('is-current', option.type === state.current));

        // Every opening starts from a clean search.
        state.search.value = '';
        ElzoFormsFilterPicker(state);

        state.picker.hidden = false;
        trigger.scrollIntoView({ block: 'nearest' });
        ElzoFormsPositionPicker(state);

        // The active option can only be scrolled to once the list is laid out.
        if (state.visible[state.activeIndex]) {
            ElzoFormsRevealPickerOption(state, state.visible[state.activeIndex]);
        }

        state.search.focus({ preventScroll: true });
    }

    function ElzoFormsClosePicker(state, restoreFocus) {
        if (!state.picker || state.picker.hidden) return;

        const trigger = state.trigger;

        state.picker.hidden = true;
        state.trigger = null;
        state.current = '';
        state.onChoose = null;

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
            if (restoreFocus && trigger.isConnected) trigger.focus({ preventScroll: true });
        }
    }

    function ElzoFormsTogglePicker(state, trigger, settings) {
        if (!state.picker.hidden && state.trigger === trigger) {
            ElzoFormsClosePicker(state, true);
        } else {
            ElzoFormsOpenPicker(state, trigger, settings);
        }
    }

    function ElzoFormsToggleAddFieldPicker(button) {
        const step = button.closest('.elzo-forms-step');

        // Without the picker markup, keep the builder usable: add a Text field.
        if (!ElzoFormsFieldPicker.search) {
            ElzoFormsAddField(step, '');
            return;
        }

        ElzoFormsTogglePicker(ElzoFormsFieldPicker, button, {
            label: ElzoFormsFieldPicker.picker.getAttribute('data-label-add'),
            onChoose: function(option) {
                const field = ElzoFormsAddField(step, option.type);
                const header = field ? field.querySelector('.elzo-forms-field-header') : null;

                if (header) header.scrollIntoView({ block: 'nearest' });
            },
        });
    }

    function ElzoFormsToggleFieldTypePicker(button) {
        const field = button.closest('.elzo-forms-field');
        const typeInput = field ? document.getElementById('elzo-forms-field-type-' + field.getAttribute('data-id')) : null;

        if (!typeInput || !ElzoFormsFieldPicker.search) return;

        ElzoFormsTogglePicker(ElzoFormsFieldPicker, button, {
            label: ElzoFormsFieldPicker.picker.getAttribute('data-label-change'),
            current: typeInput.value,
            onChoose: function(option) {
                ElzoFormsChangeFieldType(field, option.type);
            },
        });
    }

    const ElzoFormsConditionProModal = {
        modal: document.getElementById('elzo-forms-condition-pro-modal'),
        trigger: null,
        bodyOverflow: '',
    };

    function ElzoFormsOpenConditionProModal(trigger) {
        const state = ElzoFormsConditionProModal;
        if (!state.modal) return;

        state.trigger = trigger;
        state.bodyOverflow = document.body.style.overflow;
        state.modal.hidden = false;
        document.body.style.overflow = 'hidden';

        const focusTarget = state.modal.querySelector('.elzo-forms-admin-modal-close')
            || state.modal.querySelector('[role="dialog"]');
        if (focusTarget) focusTarget.focus({ preventScroll: true });
    }

    function ElzoFormsCloseConditionProModal() {
        const state = ElzoFormsConditionProModal;
        if (!state.modal || state.modal.hidden) return;

        const trigger = state.trigger;
        state.modal.hidden = true;
        state.trigger = null;
        document.body.style.overflow = state.bodyOverflow;

        if (trigger && trigger.isConnected) trigger.focus({ preventScroll: true });
    }

    function ElzoFormsToggleConditionTypePicker(button) {
        const rule = button.closest('.elzo-forms-field-logic-condition-item');
        const typeSelect = rule ? rule.querySelector('.elzo-forms-field-logic-condition-type-select') : null;
        if (!typeSelect || !ElzoFormsConditionTypePicker.search) return;

        ElzoFormsTogglePicker(ElzoFormsConditionTypePicker, button, {
            label: ElzoFormsConditionTypePicker.picker.getAttribute('data-label-change'),
            current: typeSelect.value,
            onChoose: function(option) {
                if (!option.available) {
                    ElzoFormsOpenConditionProModal(button);
                    return;
                }

                if (typeSelect.value === option.type) return;
                typeSelect.value = option.type;
                typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            },
        });
    }
    function ElzoFormsChoosePickerOption(state, option) {
        if (!option || state.picker.hidden) return;

        const trigger = state.trigger;
        const onChoose = state.onChoose;

        // Close first: the picker is hidden before the choice is applied, so
        // a repeated Enter or click can never apply it twice.
        ElzoFormsClosePicker(state, false);

        if (trigger && trigger.isConnected) trigger.focus({ preventScroll: true });
        if (onChoose) onChoose(option);
    }

    function ElzoFormsInitPicker(state) {
        const picker = state.picker;
        if (!picker) return;

        state.search = picker.querySelector('.elzo-forms-field-picker-search-input');
        state.list = picker.querySelector('.elzo-forms-field-picker-list');
        state.results = picker.querySelector('.elzo-forms-field-picker-results');
        state.status = picker.querySelector('.elzo-forms-field-picker-status');

        if (!state.search || !state.list || !state.results || !state.status) {
            state.search = null;
            return;
        }

        state.options = Array.from(picker.querySelectorAll('.elzo-forms-field-picker-option'), (element, index) => {
            const labelElement = element.querySelector('.elzo-forms-field-picker-option-label');
            const label = ElzoFormsNormalizePickerText(labelElement ? labelElement.textContent : '');
            const labelWords = label.split(' ');
            const terms = (element.getAttribute('data-picker-keywords') || '')
                .split('|')
                .map(ElzoFormsNormalizePickerText)
                .filter(Boolean);

            return {
                element: element,
                group: element.parentElement,
                index: index,
                label: label,
                labelWords: labelWords,
                terms: terms,
                words: labelWords.concat(...terms.map(term => term.split(' '))),
                type: element.getAttribute('data-picker-type') || '',
                available: element.getAttribute('data-picker-available') !== '0',
                labelText: labelElement ? labelElement.textContent.trim() : '',
            };
        });

        // Positioned against the page, the picker lives directly in <body>: no
        // ancestor can clip it, it is outside the post form, and it is never
        // inside a step that "Add Step" or "Duplicate Step" copies.
        document.body.appendChild(picker);

        state.search.addEventListener('input', function() {
            ElzoFormsFilterPicker(state);
        });

        state.search.addEventListener('keydown', function(e) {
            // Keys that confirm an IME composition belong to the composition.
            if (e.isComposing || e.keyCode === 229) return;

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                ElzoFormsMovePickerActive(state, e.key === 'ArrowDown' ? 1 : -1);
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                ElzoFormsChoosePickerOption(state, state.visible[state.activeIndex]);
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                ElzoFormsClosePicker(state, true);
                return;
            }

            if (e.key === 'Tab') {
                // Focus returns to the button first, so Tab continues from
                // there instead of from the end of the page.
                ElzoFormsClosePicker(state, true);
            }
        });

        // Hover and keyboard share one active option. mousemove, unlike
        // mouseover, does not fire when the list scrolls under a still pointer.
        state.list.addEventListener('mousemove', function(e) {
            const element = e.target.closest('.elzo-forms-field-picker-option');
            const index = element ? state.visible.findIndex(option => option.element === element) : -1;

            if (index !== -1 && index !== state.activeIndex) {
                ElzoFormsSetPickerActive(state, index, false);
            }
        });

        // Keep focus in the search input when an option or heading is pressed.
        state.list.addEventListener('mousedown', function(e) {
            if (e.target.closest('.elzo-forms-field-picker-option, .elzo-forms-field-picker-group-label')) {
                e.preventDefault();
            }
        });

        state.list.addEventListener('click', function(e) {
            const element = e.target.closest('.elzo-forms-field-picker-option');
            if (element) {
                ElzoFormsChoosePickerOption(state, state.options.find(option => option.element === element));
            }
        });

        // Capture phase: close before any other click handler runs, so a step
        // cloned by the same click never copies an expanded button state.
        document.addEventListener('click', function(e) {
            if (picker.hidden || picker.contains(e.target)) return;
            if (state.trigger && state.trigger.contains(e.target)) return;

            ElzoFormsClosePicker(state, false);
        }, true);

        // Keep the picker attached to its button when the viewport changes.
        window.addEventListener('resize', function() {
            ElzoFormsPositionPicker(state);
        });
    }

    ElzoFormsPickers.forEach(ElzoFormsInitPicker);

    if (ElzoFormsConditionProModal.modal) {
        // Keep the fixed dialog outside #poststuff/.postbox so WordPress admin
        // heading rules and clipping ancestors cannot alter its presentation.
        document.body.appendChild(ElzoFormsConditionProModal.modal);

        ElzoFormsConditionProModal.modal.addEventListener('click', function(e) {
            if (e.target.closest('[data-condition-pro-modal-close]')) {
                ElzoFormsCloseConditionProModal();
            }
        });

        ElzoFormsConditionProModal.modal.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                ElzoFormsCloseConditionProModal();
                return;
            }

            if (e.key !== 'Tab') return;

            const focusable = Array.from(ElzoFormsConditionProModal.modal.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])'))
                .filter(element => !element.hidden && element.offsetParent !== null);
            if (!focusable.length) {
                e.preventDefault();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });
    }

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
            ElzoFormsToggleAddFieldPicker(e.target);
        }

        // Change field type
        const fieldTypeButton = e.target.closest ? e.target.closest('.elzo-forms-field-type-button') : null;
        if (fieldTypeButton) {
            ElzoFormsToggleFieldTypePicker(fieldTypeButton);
        }

        // Change conditional logic type through the shared picker behavior.
        const conditionTypeButton = e.target.closest ? e.target.closest('.elzo-forms-condition-type-button') : null;
        if (conditionTypeButton) {
            ElzoFormsToggleConditionTypePicker(conditionTypeButton);
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

            // cloneNode() does not reliably copy live select/checkbox state.
            ElzoFormsCopyFormControlState(field, clonedField);

            // Let the duplicated field generate a fresh key automatically. Do
            // this after copying live values so the source key is not restored.
            const clonedFieldKeyInput = clonedField.querySelector('.elzo-forms-field-key');
            if (clonedFieldKeyInput) {
                clonedFieldKeyInput.value = '';
                clonedFieldKeyInput.dataset.fieldKeyManual = '0';
            }

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

            // Preserve live condition types/operators and all other configured
            // controls before reindexing assigns fresh IDs to the cloned fields.
            ElzoFormsCopyFormControlState(step, clonedStep);

            step.insertAdjacentElement('afterend', clonedStep);

            // Update repeater
            ElzoFormsUpdateRepeaterFields(repeater);
        }
        // Add conditional logic group
        if (e.target.classList.contains('elzo-forms-field-logic-add-group-button')) {
            const wrapper = e.target.closest('.elzo-forms-field-logic-repeater-wrapper');
            const repeater = wrapper.querySelector('.elzo-forms-field-logic-repeater');
            const template = wrapper.querySelector('.elzo-forms-field-logic-group');
            const templateFieldLogicItem = template ? template.querySelector('.elzo-forms-field-logic-condition-item') : null;
            const conditionShape = ElzoFormsReadFieldLogicConditionShape(templateFieldLogicItem);
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
                if (newFieldLogicItem) ElzoFormsApplyFieldLogicConditionShape(newFieldLogicItem, conditionShape);
            }
        }

        // Add conditional logic item
        if (e.target.classList.contains('elzo-forms-field-logic-add-rule-button')) {
            const repeater = e.target.closest('.elzo-forms-field-logic-group-rules');
            const currentRule = e.target.closest('.elzo-forms-field-logic-group-rule');
            const template = currentRule || repeater.querySelector('.elzo-forms-field-logic-group-rule');
            const conditionShape = ElzoFormsReadFieldLogicConditionShape(currentRule);
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
                    ElzoFormsApplyFieldLogicConditionShape(newFieldLogicItem, conditionShape);
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
            const valueDisplays = submissionDataTable.querySelectorAll('.elzo-submission-line-value-display');
            const editing = this.getAttribute('aria-expanded') !== 'true';
            this.setAttribute('aria-expanded', String(editing));

            // Keep server-rendered links and previews intact between Edit/Close.
            valueFields.forEach(function(valueField) {
                valueField.style.display = editing ? 'block' : 'none';
            });
            valueDisplays.forEach(function(valueDisplay) {
                valueDisplay.style.display = editing ? 'none' : 'block';
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
    // Mirrors Conditional_Logic::get_supported_operators(). The fallback is
    // what runs when the localized list is missing, so it has to carry every
    // operator PHP knows or a condition silently evaluates to false here while
    // PHP still honours it.
    const SUPPORTED_LOGIC_OPERATORS = Array.isArray(window.ElzoFormsAjax?.logicOperators)
        ? window.ElzoFormsAjax.logicOperators
        : ['==', '!=', '>', '<', 'like', 'not_like', 'starts_with', 'ends_with', 'pattern',
           'count_eq', 'count_gt', 'count_lt'];
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
            case 'count_eq':
                return countSubmittedValues(values) === toLogicInt(expected);
            case 'count_gt':
                return countSubmittedValues(values) > toLogicInt(expected);
            case 'count_lt':
                return countSubmittedValues(values) < toLogicInt(expected);
            default:
                return false;
        }
    }

    /**
     * Count the values a field actually submitted.
     *
     * Mirrors Conditional_Logic::count_submitted_values(): an empty field
     * still contributes one empty string so the value operators have
     * something to compare, and counting that would report one value for a
     * field that submitted none.
     */
    function countSubmittedValues(values) {
        return values.filter(value => String(value ?? '') !== '').length;
    }

    // -------------------------------------------------------------------------
    // Condition item evaluators
    // -------------------------------------------------------------------------

    /**
     * Controls that carry a field's submitted value.
     *
     * A file field has no .elzo-forms-field control of its own: its value
     * lives in the hidden inputs of the upload list, one per uploaded file,
     * which is exactly what the browser posts and what PHP compares against.
     * The list scope matters -- the item template sits outside it and would
     * otherwise contribute a permanent empty value.
     */
    const LOGIC_VALUE_SELECTOR = '.elzo-forms-field, '
        + '.elzo-forms-file-drop-area-upload-list .elzo-forms-file-drop-area-upload-list-item-input';

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
            } else if (element.tagName === 'SELECT' && element.multiple) {
                // select.value is only the first selected option, so a
                // multi-select would otherwise hide every choice after the
                // first from a comparison PHP makes against all of them.
                Array.from(element.selectedOptions).forEach(function(option) {
                    if (!option.hasAttribute('data-placeholder')) values.push(option.value);
                });
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
        // Field rules are always local to their owning form. A document-level
        // fallback could bind a stale rule to the same logical field in another
        // instance of the form.
        return root.querySelector('[data-ef-field-id="' + escapeAttributeValue(fieldId) + '"]');
    }

    function evaluateFieldCondition(operator, settings, collectFields, root = document) {
        const fieldId = settings.field_id || '';
        if (!fieldId) return false;

        const fieldWrapper = findFieldWrapper(root, fieldId);
        const fields = fieldWrapper
            ? Array.from(fieldWrapper.querySelectorAll(LOGIC_VALUE_SELECTOR))
            : [];

        const values = collectComparableInputValues(fields, collectFields);

        return SUPPORTED_LOGIC_OPERATORS.includes(operator)
            ? compareLogicValues(values, operator, settings.value ?? '')
            : false;
    }

    /**
     * Resolve a field HTML ID of Elzo Forms 1.1 ("elzo-forms-field-{id}" or
     * "elzo-forms-field-wrapper-{id}"). Rendered IDs are now namespaced by the
     * form instance, so such an ID is matched through the stored field ID,
     * within the form being evaluated.
     */
    function findLegacyFieldWrapper(root, targetId) {
        const match = /^elzo-forms-field-(.+)$/.exec(targetId);
        if (!match) return null;

        const candidates = [match[1]];
        if (match[1].indexOf('wrapper-') === 0) {
            candidates.push(match[1].slice('wrapper-'.length));
        }

        for (const fieldId of candidates) {
            const wrapper = findFieldWrapper(root, fieldId);
            if (wrapper) return wrapper;
        }

        return null;
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
            const targetElement = findInRoot(root, '[id="' + escapeAttributeValue(targetId) + '"]')
                || findLegacyFieldWrapper(root, targetId);
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

    function collectConditionPostTypes(settings) {
        const rawPostTypes = settings?.post_types ?? settings?.post_type ?? settings?.value ?? [];
        const values = Array.isArray(rawPostTypes)
            ? rawPostTypes
            : String(rawPostTypes).split(/[,\s]+/);

        return [...new Set(values.map(sanitizeLogicKey).filter(Boolean))];
    }

    function evaluatePageCondition(operator, settings) {
        // The admin screens reuse this file without a page context; without it the
        // condition stays closed, matching PHP Conditional_Logic::evaluate_page_item().
        if (LOGIC_CONTEXT.currentPageId === undefined && LOGIC_CONTEXT.currentPostType === undefined) return false;

        const currentPageId   = parseInt(LOGIC_CONTEXT.currentPageId, 10) || 0;
        const currentPostType = sanitizeLogicKey(LOGIC_CONTEXT.currentPostType);

        const pageIds = collectConditionPageIds(settings);
        const postTypes = collectConditionPostTypes(settings);
        const postType = postTypes[0] || '';

        switch (operator) {
            case 'page_id_equals':       return !!pageIds[0] && currentPageId === pageIds[0];
            case 'page_id_not_equals':   return !!pageIds[0] && currentPageId !== pageIds[0];
            case 'page_id_in':           return pageIds.includes(currentPageId);
            case 'page_id_not_in':       return !pageIds.includes(currentPageId);
            case 'post_type_equals':     return postType !== '' && currentPostType === postType;
            case 'post_type_not_equals': return postType !== '' && currentPostType !== postType;
            case 'post_type_in':         return postTypes.length > 0 && postTypes.includes(currentPostType);
            case 'post_type_not_in':     return postTypes.length > 0 && !postTypes.includes(currentPostType);
            default:                     return false;
        }
    }
    /**
     * Evaluate a single condition item {type, operator, settings} — matches PHP
     * Conditional_Logic::evaluate_condition_item().
     *
     * A type this build does not ship fails closed, as it does in PHP: the FREE
     * build drops the PRO evaluators, so a saved PRO rule can never show a field
     * here whose value the server would then discard.
     */
    function evaluateConditionItem(item, collectFields, root) {
        const type     = item.type     || 'field';
        const operator = item.operator || '==';
        const settings = item.settings || {};

        switch (type) {
            case 'field':
                return evaluateFieldCondition(operator, settings, collectFields, root);
            case 'input':
                return evaluateInputCondition(operator, settings, collectFields, root);
            case 'auth':
                return evaluateAuthCondition(operator);
            case 'page':
                return evaluatePageCondition(operator, settings);
            default:
                return false;
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
