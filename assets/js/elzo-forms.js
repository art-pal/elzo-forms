// Track focus to restore after modal closes
let ElzoFormsLastFocusedElement = null;
let ElzoFormsModalCounter = 0;

function ElzoFormsCreateModalId() {
    ElzoFormsModalCounter++;

    return 'elzo-forms-modal-' + Date.now() + '-' + ElzoFormsModalCounter;
}

function ElzoFormsGetModalBackdrop(modal, fallbackIndex = null) {
    if (!modal) {
        return null;
    }

    const modalId = modal.getAttribute('data-elzo-forms-modal-id');
    const backdrops = Array.from(document.querySelectorAll('.elzo-forms-modal-backdrop'));

    if (modalId) {
        const matchedBackdrop = backdrops.find(function(backdrop) {
            return backdrop.getAttribute('data-elzo-forms-modal-id') === modalId;
        });

        if (matchedBackdrop) {
            return matchedBackdrop;
        }
    }

    return Number.isInteger(fallbackIndex) ? (backdrops[fallbackIndex] || null) : null;
}

function ElzoFormsGetBackdropModal(backdrop) {
    if (!backdrop) {
        return null;
    }

    const modalId = backdrop.getAttribute('data-elzo-forms-modal-id');
    const modals = Array.from(document.querySelectorAll('.elzo-forms-modal'));

    if (modalId) {
        const matchedModal = modals.find(function(modal) {
            return modal.getAttribute('data-elzo-forms-modal-id') === modalId;
        });

        if (matchedModal) {
            return matchedModal;
        }
    }

    const backdrops = Array.from(document.querySelectorAll('.elzo-forms-modal-backdrop'));
    const backdropIndex = backdrops.indexOf(backdrop);

    return backdropIndex >= 0 ? (modals[backdropIndex] || null) : null;
}

function ElzoFormsRestoreModalFocus() {
    if (ElzoFormsLastFocusedElement && typeof ElzoFormsLastFocusedElement.focus === 'function') {
        ElzoFormsLastFocusedElement.focus({ preventScroll: true });
    }
}

function ElzoFormsCloseCustomDropdowns(){
    const dropdowns = document.querySelectorAll('.elzo-forms-custom-dropdown.active');
    
    dropdowns.forEach(function(dropdown) {
        const searchInput = dropdown.querySelector('.elzo-forms-custom-dropdown-search-input');
        
        if (searchInput) {
            searchInput.blur();
        }

        dropdown.classList.remove('active');
    });
}

function ElzoFormsOpenCustomDropdown(dropdown){
    dropdown.classList.add('active');
}

function ElzoFormsCloseBackdrop(backdrop) {
    if (!backdrop || backdrop.getAttribute('data-elzo-forms-closing') === '1') {
        return;
    }

    backdrop.setAttribute('data-elzo-forms-closing', '1');

    setTimeout(function(){
        backdrop.classList.remove('active');
    }, 200);

    setTimeout(function(){
        backdrop.remove();
    }, 700);
}

function ElzoFormsCloseModal(modal, options = {}){
    if (!modal || modal.getAttribute('data-elzo-forms-closing') === '1') {
        return;
    }

    const backdrop = options.backdrop || ElzoFormsGetModalBackdrop(modal);
    const context = {
        modalId: modal.getAttribute('data-elzo-forms-modal-id') || '',
        reason: options.reason || 'programmatic',
        restoreFocus: options.restoreFocus !== undefined ? options.restoreFocus : true,
        trigger: options.trigger || null,
        modal: modal,
        backdrop: backdrop
    };

    modal.setAttribute('data-elzo-forms-closing', '1');

    if (backdrop) {
        backdrop.setAttribute('data-elzo-forms-closing', '1');
    }
    
    window.wp.hooks.doAction('elzoForms.modal.close', modal, backdrop, context);
    
    modal.classList.remove('active');

    setTimeout(function(){
        if (backdrop) {
            backdrop.classList.remove('active');
        }
    }, 200);

    setTimeout(function(){
        modal.remove();

        if (backdrop) {
            backdrop.remove();
        }
        
        window.wp.hooks.doAction('elzoForms.modal.closed', modal, backdrop, context);

        if (context.restoreFocus) {
            ElzoFormsRestoreModalFocus();
        }
    }, 700);
}

function ElzoFormsCloseModals(options = {}){
    const modals = Array.from(document.querySelectorAll('.elzo-forms-modal')).filter(function(modal) {
        return modal.getAttribute('data-elzo-forms-closing') !== '1';
    });
    const backdrops = Array.from(document.querySelectorAll('.elzo-forms-modal-backdrop')).filter(function(backdrop) {
        return backdrop.getAttribute('data-elzo-forms-closing') !== '1';
    });

    if (modals.length === 0 && backdrops.length === 0) {
        return;
    }

    const context = {
        reason: options.reason || 'programmatic',
        restoreFocus: options.restoreFocus !== undefined ? options.restoreFocus : true,
        trigger: options.trigger || null,
        modals: modals,
        backdrops: backdrops
    };

    window.wp.hooks.doAction('elzoForms.modal.closeAll', modals, backdrops, context);

    const pairedBackdrops = new Set();

    modals.forEach(function(modal, index) {
        const backdrop = ElzoFormsGetModalBackdrop(modal, index);

        if (backdrop) {
            pairedBackdrops.add(backdrop);
        }

        ElzoFormsCloseModal(modal, {
            reason: context.reason,
            restoreFocus: false,
            trigger: context.trigger,
            backdrop: backdrop
        });
    });

    backdrops.forEach(function(backdrop) {
        if (!pairedBackdrops.has(backdrop)) {
            ElzoFormsCloseBackdrop(backdrop);
        }
    });

    setTimeout(function(){
        window.wp.hooks.doAction('elzoForms.modal.closedAll', modals, backdrops, context);

        if (context.restoreFocus) {
            ElzoFormsRestoreModalFocus();
        }
    }, 700);
}

function ElzoFormsOpenModal(message, button, options = {}){
    let modalArgs = {
        message: message,
        button: button || 'Ok'
    };

    const context = {
        modalId: ElzoFormsCreateModalId(),
        reason: options.reason || 'programmatic',
        trigger: options.trigger || null,
        previousActiveElement: null,
        modal: null,
        backdrop: null
    };

    modalArgs = window.wp.hooks.applyFilters('elzoForms.modal.args', modalArgs, context);

    if (!modalArgs || typeof modalArgs !== 'object') {
        modalArgs = {
            message: message,
            button: button || 'Ok'
        };
    }

    message = Object.prototype.hasOwnProperty.call(modalArgs, 'message') ? modalArgs.message : message;
    button = modalArgs.button || 'Ok';

    const shouldOpen = window.wp.hooks.applyFilters('elzoForms.modal.shouldOpen', true, message, button, context);

    if (!shouldOpen) {
        return;
    }

    window.wp.hooks.doAction('elzoForms.modal.open', message, button, context);

    ElzoFormsLastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    context.previousActiveElement = ElzoFormsLastFocusedElement;

    // Close the other opened modals without restoring focus (to avoid focus bounce)
    ElzoFormsCloseModals({
        reason: 'replace',
        restoreFocus: false,
        trigger: options.trigger || null
    });

    const backdrop = document.createElement('div');
    backdrop.classList.add('elzo-forms-modal-backdrop');
    backdrop.setAttribute('data-elzo-forms-modal-id', context.modalId);
    
    document.body.appendChild(backdrop);

    setTimeout(function(){
        if (!backdrop.isConnected || backdrop.getAttribute('data-elzo-forms-closing') === '1') {
            return;
        }

        backdrop.classList.add('active');
    }, 100);

    // Build modal content via DOM API for reliability
    const content = document.createElement('div');
    content.classList.add('elzo-forms-modal');
    content.setAttribute('role', 'dialog');
    content.setAttribute('aria-modal', 'true');
    content.setAttribute('tabindex', '-1');
    content.setAttribute('data-elzo-forms-modal-id', context.modalId);

    // Basic accessible labeling fallback
    const plainText = (typeof message === 'string') ? message.replace(/<[^>]*>/g, '') : '';
    if (plainText) {
        content.setAttribute('aria-label', plainText.substring(0, 120));
    }

    const modalContent = document.createElement('div');
    modalContent.classList.add('elzo-forms-modal-content');

    if (typeof message === 'string') {
        modalContent.innerHTML = message;
    } else if (message instanceof Node) {
        modalContent.appendChild(message);
    }

    const buttonsWrapper = document.createElement('div');
    buttonsWrapper.classList.add('elzo-forms-modal-buttons');

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'elzo-forms-button elzo-forms-w-100 elzo-forms-modal-close';
    closeBtn.setAttribute('aria-label', (window.ElzoFormsAjax && window.ElzoFormsAjax.texts && window.ElzoFormsAjax.texts.closeDialog) || 'Close dialog');
    closeBtn.textContent = button;

    buttonsWrapper.appendChild(closeBtn);
    modalContent.appendChild(buttonsWrapper);
    content.appendChild(modalContent);

    document.body.appendChild(content);
    context.modal = content;
    context.backdrop = backdrop;

    // Ensure the close button works even if other scripts stop bubbling
    closeBtn.addEventListener('click', function(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        ElzoFormsCloseModal(content, {
            reason: 'button',
            trigger: closeBtn,
            backdrop: backdrop
        });
    });

    setTimeout(function(){
        if (!content.isConnected || content.getAttribute('data-elzo-forms-closing') === '1') {
            return;
        }

        content.classList.add('active');
        
        window.wp.hooks.doAction('elzoForms.modal.opened', content, backdrop, message, button, context);

        // Focus the close button for keyboard users
        const closeBtn = content.querySelector('.elzo-forms-modal-close');
        if (closeBtn && typeof closeBtn.focus === 'function') {
            closeBtn.focus({ preventScroll: true });
        } else if (typeof content.focus === 'function') {
            content.focus({ preventScroll: true });
        }
    }, 300);
}

function ElzoFormsRemoveAlert(alert, options = {}){
    if (!alert) {
        return;
    }

    const context = {
        reason: options.reason || 'programmatic',
        trigger: options.trigger || null,
        container: alert.parentElement,
        type: alert.getAttribute('data-elzo-forms-alert-type') || '',
        message: alert.getAttribute('data-elzo-forms-alert-message') || ''
    };

    window.wp.hooks.doAction('elzoForms.alert.close', alert, context);
    
    alert.remove();
    
    window.wp.hooks.doAction('elzoForms.alert.closed', alert, context);
}

function ElzoFormsShowAlert(message, container, type = 'error', options = {}){
    let alertArgs = {
        message: message,
        container: container,
        type: type
    };

    const context = {
        reason: options.reason || 'programmatic',
        trigger: options.trigger || null,
        container: container,
        type: type,
        alert: null
    };

    alertArgs = window.wp.hooks.applyFilters('elzoForms.alert.args', alertArgs, context);

    if (!alertArgs || typeof alertArgs !== 'object') {
        alertArgs = {
            message: message,
            container: container,
            type: type
        };
    }

    message = Object.prototype.hasOwnProperty.call(alertArgs, 'message') ? alertArgs.message : message;
    container = alertArgs.container || container;
    type = alertArgs.type || type;

    context.container = container;
    context.type = type;

    if (!container) {
        return;
    }

    const shouldShow = window.wp.hooks.applyFilters('elzoForms.alert.shouldShow', true, message, container, type, context);

    if (!shouldShow) {
        return;
    }

    window.wp.hooks.doAction('elzoForms.alert.show', message, container, type, context);
    
    const alert = document.createElement('div');
    alert.className = `elzo-forms-alert elzo-forms-alert-${type}`;
    alert.setAttribute('data-elzo-forms-alert-type', type);
    alert.setAttribute('data-elzo-forms-alert-message', typeof message === 'string' ? message : '');
    alert.innerHTML = message + '<button type="button" class="elzo-forms-alert-close"></button>';

    container.prepend(alert);
    context.alert = alert;
    
    window.wp.hooks.doAction('elzoForms.alert.shown', alert, message, container, type, context);
}

function ElzoFormsTooltip(element, tooltipText) {
    if (!element) return;

    ElzoFormsRemoveConfirmationElements();

    const tooltip = document.createElement('div');
    tooltip.className = 'elzo-forms-tooltip';
    tooltip.textContent = tooltipText;
    document.body.appendChild(tooltip);

    function positionTooltip() {
        const rect = element.getBoundingClientRect();
        tooltip.style.left = `${rect.left + window.scrollX + rect.width / 2 - tooltip.offsetWidth / 2}px`;
        tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight - 5}px`;
    }

    tooltip.style.display = 'block';
    positionTooltip();

    window.addEventListener('scroll', positionTooltip);
    window.addEventListener('resize', positionTooltip);
}

function ElzoFormsRemoveConfirmationElements() {
    const confirmationTooltips = document.querySelectorAll('.elzo-forms-tooltip');
    confirmationTooltips.forEach(tooltip => tooltip.remove());

    const waitingConfirmationButtons = document.querySelectorAll('.elzo-forms-waiting-confirmation');
    waitingConfirmationButtons.forEach(button => button.classList.remove('elzo-forms-waiting-confirmation'));
}

function ElzoFormsFadeOut(element, duration = 300) {
    element.style.transition = `opacity ${duration}ms`;
    element.style.opacity = 0;

    setTimeout(() => {
        element.style.display = 'none';
    }, duration);
}

// Escape HTML for safe attribute storage
function ElzoFormsEscapeHTML(html) {
    return html
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Unescape HTML from attribute storage
function ElzoFormsUnescapeHTML(escapedHTML) {
    return escapedHTML
        .replace(/&gt;/g, '>')
        .replace(/&lt;/g, '<')
        .replace(/&#39;/g, "'")
        .replace(/&quot;/g, '"')
        .replace(/&amp;/g, '&');
}

// Find a select option by value without building a CSS selector from it.
// Option values come from the form author, so a quote inside one both breaks
// the selector and throws before the field can update.
/**
 * Ask conditional logic to rebuild itself.
 *
 * The upload list creates and removes the inputs that hold a file field's
 * value, so logic cannot reach them through the change event it listens to:
 * the elements it registered at load time are not the ones that exist now.
 */
function ElzoFormsRefreshConditionalLogic() {
    document.dispatchEvent(new CustomEvent('elzo-forms-refresh-logic-elements'));
}

/**
 * Uploads owned by an item of an upload list, keyed by the item.
 *
 * Removing an item discards its temporary file on the server right away
 * instead of leaving it to the scheduled cleanup.
 */
const ElzoFormsFileUploads = new WeakMap();

// The upload ID is what the server accepts chunks and removals on, so it must
// not be guessable. randomUUID() needs a secure context; getRandomValues() does not.
function ElzoFormsCreateUploadId() {
    const cryptoApi = window.crypto;

    if (cryptoApi && typeof cryptoApi.randomUUID === 'function') {
        return cryptoApi.randomUUID();
    }

    if (cryptoApi && typeof cryptoApi.getRandomValues === 'function') {
        return Array.from(cryptoApi.getRandomValues(new Uint8Array(16)), function(byte) {
            return byte.toString(16).padStart(2, '0');
        }).join('');
    }

    return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
}

function ElzoFormsDiscardUpload(item) {
    const upload = item ? ElzoFormsFileUploads.get(item) : null;

    if (!upload) {
        return;
    }

    ElzoFormsFileUploads.delete(item);
    upload.cancelled = true;

    // Let the chunk already in flight land first, so the session it creates
    // exists, and a final chunk has handed back the token removal requires.
    Promise.resolve(upload.pending).then(function() {
        const body = new FormData();

        body.set('action', 'elzo_forms_remove_upload');
        body.set('nonce', window.ElzoFormsAjax ? window.ElzoFormsAjax.uploadNonce : '');
        body.set('form_id', upload.formId);
        body.set('field_id', upload.fieldId);
        body.set('upload_id', upload.uploadId);
        body.set('file_url', upload.fileUrl);

        // Best effort: whatever this misses, the scheduled cleanup still removes.
        return fetch(upload.url, {
            method: 'POST',
            body: body
        });
    }).catch(function() {});
}

/*
 * Mark a control valid or invalid for assistive technology as well as for
 * styling. An invalid control gets aria-invalid and an aria-describedby
 * reference to the region that shows its step's validation message; the
 * references it already had, such as its help text, are kept.
 */
function ElzoFormsSetValidationState(control, invalid, errorClasses, messageId) {
    if (!control) {
        return;
    }

    if (errorClasses && errorClasses.length) {
        if (invalid) {
            control.classList.add(...errorClasses);
        } else {
            control.classList.remove(...errorClasses);
        }
    }

    if (invalid) {
        control.setAttribute('aria-invalid', 'true');
    } else {
        control.removeAttribute('aria-invalid');
    }

    if (!messageId) {
        return;
    }

    const references = String(control.getAttribute('aria-describedby') || '')
        .split(/\s+/)
        .filter(function(reference) {
            return reference !== '' && reference !== messageId;
        });

    if (invalid) {
        references.push(messageId);
    }

    if (references.length) {
        control.setAttribute('aria-describedby', references.join(' '));
    } else {
        control.removeAttribute('aria-describedby');
    }
}

function ElzoFormsStepMessageId(element) {
    const step = element ? element.closest('.elzo-forms-step') : null;
    const region = step ? step.querySelector('.elzo-forms-step-alert-wrapper') : null;

    return region && region.id ? region.id : '';
}

function ElzoFormsFindOptionByValue(select, value) {
    return Array.from(select.options).find(function(option) {
        return option.value === value;
    }) || null;
}

document.addEventListener('DOMContentLoaded', function() {
    const ElzoFormsTexts = (window.ElzoFormsAjax && window.ElzoFormsAjax.texts)
        ? window.ElzoFormsAjax.texts
        : {};

    function ElzoFormsSettings(form) {
        const defaults = {
            form_field_error_class: 'invalid',
        }

        const settings_field = form.querySelector('.elzo-forms-settings');
        const settings = JSON.parse(settings_field.value);

        return Object.assign({}, defaults, settings);
    }

    function ElzoFormsParsePositiveInt(value, fallback = 0) {
        const parsed = parseInt(value, 10);

        if (Number.isNaN(parsed) || parsed < 1) {
            return fallback;
        }

        return parsed;
    }

    /*
     * Conditional logic hides a field by setting display:none on its own wrapper
     * (or on the surrounding column), and the server skips hidden fields entirely.
     * The walk stops at the step so an inactive step, which is hidden the same way,
     * still has its fields validated.
     */
    function ElzoFormsIsHiddenWithinStep(element, step) {
        let node = element;

        while (node && node !== step) {
            if (node.style && node.style.display === 'none') {
                return true;
            }

            node = node.parentElement;
        }

        return false;
    }

    function ElzoFormsValidateStep(step, options = {}) {
        const form = step.closest('.elzo-forms-form');
        const settings = ElzoFormsSettings(form);
        const errorClass = ['invalid'];

        if (settings.form_field_error_class) {
            const customErrorClasses = String(settings.form_field_error_class)
                .split(/\s+/)
                .map(className => className.trim())
                .filter(Boolean);

            errorClass.push(...customErrorClasses);
        }
        
        const inputs = step.querySelectorAll('input, textarea, select');
        const messageId = ElzoFormsStepMessageId(step);
        const checkboxGroups = step.querySelectorAll('.elzo-forms-checkbox-list-wrapper');
        const fileDropAreas = step.querySelectorAll('.elzo-forms-file-drop-area');
        const context = {
            settings: settings,
            errorClass: errorClass,
            inputs: inputs,
            checkboxGroups: checkboxGroups,
            fileDropAreas: fileDropAreas,
            invalidFields: [],
            invalidGroups: [],
            invalidFileDropAreas: [],
            formContext: options.formContext || null
        };

        if (context.formContext && Array.isArray(context.formContext.stepContexts)) {
            context.formContext.stepContexts.push(context);
        }

        window.wp.hooks.doAction('elzoForms.validation.step.start', step, form, context);

        let valid = true;

        if(inputs.length > 0){
            inputs.forEach(function(input) {
                if (input.required && !input.value) {
                    valid = false;
                    context.invalidFields.push(input);

                    ElzoFormsSetValidationState(input, true, errorClass, messageId);
                } else {
                    ElzoFormsSetValidationState(input, false, errorClass, messageId);
                }
            });
        }

        if(checkboxGroups.length > 0){
            checkboxGroups.forEach(function(checkboxGroup) {
                if (ElzoFormsIsHiddenWithinStep(checkboxGroup, step)) {
                    return;
                }

                const checkboxes = checkboxGroup.querySelectorAll('input[type="checkbox"]');

                // Mirrors Field_Checkbox::validate(): min/max constrain a selection
                // that has been started, only "required" forbids an empty one.
                const isRequired = checkboxGroup.classList.contains('elzo-forms-checkbox-list-required');
                const minSelections = ElzoFormsParsePositiveInt(checkboxGroup.dataset.minSelections, 0);
                let maxSelections = ElzoFormsParsePositiveInt(checkboxGroup.dataset.maxSelections, 0);

                if (maxSelections > 0 && minSelections > maxSelections) {
                    maxSelections = minSelections;
                }

                let checked = 0;

                if (!checkboxes.length) {
                    return;
                }

                checkboxes.forEach(function(checkbox) {
                    if (checkbox.checked) {
                        checked++;
                    }
                });

                const tooFew = (isRequired && checked < 1)
                    || (minSelections > 0 && checked > 0 && checked < minSelections);
                const tooMany = maxSelections > 0 && checked > maxSelections;

                if (tooFew || tooMany) {
                    valid = false;
                    context.invalidGroups.push(checkboxGroup);

                    checkboxes.forEach(function(checkbox) {
                        if(tooFew && !checkbox.checked || tooMany && checkbox.checked) {
                            context.invalidFields.push(checkbox);
                            ElzoFormsSetValidationState(checkbox, true, errorClass, messageId);
                        }
                    });
                } else {
                    checkboxes.forEach(function(checkbox) {
                        ElzoFormsSetValidationState(checkbox, false, errorClass, messageId);
                    });
                }
            });
        }

        if(fileDropAreas.length > 0){
            fileDropAreas.forEach(function(dropArea) {
                if (ElzoFormsIsHiddenWithinStep(dropArea, step)) {
                    return;
                }

                const list = dropArea.querySelector('.elzo-forms-file-drop-area-upload-list');

                const input = dropArea.querySelector('.elzo-forms-file-upload-input');

                if (!list || !input) {
                    return;
                }

                let files = list.querySelectorAll('.elzo-forms-file-drop-area-upload-list-item-input');

                const uniqueFileValues = new Set();
                const uniqueFiles = [];

                files.forEach(function(file) {
                    if (file.value && !uniqueFileValues.has(file.value)) {
                        uniqueFileValues.add(file.value);
                        uniqueFiles.push(file);
                    }
                });

                files = uniqueFiles;

                // Mirrors Field_File::validate(): min/max constrain an upload that
                // has been started, only "required" forbids an empty one.
                const isRequired = dropArea.classList.contains('elzo-forms-field-required');
                const minFiles = input.multiple ? ElzoFormsParsePositiveInt(dropArea.dataset.minFiles, 0) : 0;
                let maxFiles = input.multiple
                    ? ElzoFormsParsePositiveInt(dropArea.dataset.maxFiles, 0)
                    : 1;

                if (maxFiles > 0 && minFiles > maxFiles) {
                    maxFiles = minFiles;
                }

                const tooFewFiles = (isRequired && files.length < 1)
                    || (minFiles > 0 && files.length > 0 && files.length < minFiles);
                const tooManyFiles = maxFiles > 0 && files.length > maxFiles;

                if (tooFewFiles || tooManyFiles) {
                    valid = false;
                    context.invalidFileDropAreas.push(dropArea);

                    dropArea.classList.add(...errorClass);
                    ElzoFormsSetValidationState(input, true, [], messageId);
                } else {
                    dropArea.classList.remove(...errorClass);
                    ElzoFormsSetValidationState(input, false, [], messageId);
                }
            });
        }

        valid = !!window.wp.hooks.applyFilters('elzoForms.validation.step.isValid', valid, step, form, context);
        window.wp.hooks.doAction('elzoForms.validation.step.validated', step, form, valid, context);

        return valid;
    }

    function ElzoFormsValidateForm(form) {
        const settings = ElzoFormsSettings(form);
        const formSteps = Array.from(form.querySelectorAll('.elzo-forms-step'));
        const context = {
            settings: settings,
            steps: formSteps,
            invalidSteps: [],
            stepContexts: []
        };
        let valid = true;

        window.wp.hooks.doAction('elzoForms.validation.form.start', form, context);

        formSteps.forEach(function(step) {
            if (!ElzoFormsValidateStep(step, { formContext: context })) {
                valid = false;
                context.invalidSteps.push(step);
            }
        });

        valid = !!window.wp.hooks.applyFilters('elzoForms.validation.form.isValid', valid, form, context);
        window.wp.hooks.doAction('elzoForms.validation.form.validated', form, valid, context);

        return valid;
    }

    function ElzoFormsChangeStep(form, nextStep, direction, trigger = null) {
        const currentStep = form.querySelector('.elzo-forms-step.active');

        if (!currentStep || !nextStep || currentStep === nextStep) {
            return false;
        }

        const steps = Array.from(form.querySelectorAll('.elzo-forms-step'));
        const context = {
            settings: ElzoFormsSettings(form),
            trigger: trigger,
            steps: steps,
            currentIndex: steps.indexOf(currentStep),
            nextIndex: steps.indexOf(nextStep)
        };

        const shouldChange = window.wp.hooks.applyFilters('elzoForms.step.change.shouldChange', true, form, currentStep, nextStep, direction, context);

        if (!shouldChange) {
            return false;
        }

        window.wp.hooks.doAction('elzoForms.step.change.before', form, currentStep, nextStep, direction, context);

        currentStep.classList.remove('active');
        nextStep.classList.add('active');

        currentStep.style.display = 'none';
        nextStep.style.display = 'block';

        window.wp.hooks.doAction('elzoForms.step.change.after', form, currentStep, nextStep, direction, context);

        return true;
    }

    /**
     * Restore a form to the state it was rendered in.
     *
     * The native reset is what defines "cleared" for every control: it puts
     * back the value the field was rendered with instead of blanking it, which
     * is what a hidden or prefilled field needs, and it keeps working for
     * field types added later without listing them here. What it cannot reach
     * is the markup the plugin renders on top of a control, so the widgets
     * that mirror one are resynced from it afterwards.
     *
     * A multi-step form goes back to its first step unless `keepStep` is set,
     * which the caller uses when something on the active step must stay in view.
     */
    function ElzoFormsResetForm(form, options = {}) {
        if (!form || typeof form.reset !== 'function') {
            return;
        }

        form.reset();

        // An uploaded file is tracked by list markup the plugin builds, not by
        // the file input, so the reset above leaves it standing. Its hidden
        // inputs still hold upload URLs whose sessions the submission consumed.
        form.querySelectorAll('.elzo-forms-file-drop-area').forEach(function(dropArea) {
            dropArea
                .querySelectorAll('.elzo-forms-file-drop-area-upload-list .elzo-forms-file-drop-area-upload-list-item')
                .forEach(function(item) {
                    item.remove();
                });

            ElzoFormsUpdateDropArea(dropArea);
        });

        ElzoFormsRefreshConditionalLogic();

        form.querySelectorAll('.invalid').forEach(function(element) {
            element.classList.remove('invalid');
        });

        form.querySelectorAll('[aria-invalid]').forEach(function(element) {
            ElzoFormsSetValidationState(element, false, [], ElzoFormsStepMessageId(element));
        });

        // Custom select facades, range sliders and conditional logic all
        // rebuild themselves from a change event, which a native reset never
        // fires. File inputs are left out: they drive the upload pipeline.
        form.querySelectorAll('input:not([type="file"]), select, textarea').forEach(function(control) {
            control.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // A dropdown search box filters its items on input, so the reset value
        // only takes effect once the same event replays it.
        form.querySelectorAll('.elzo-forms-custom-dropdown-search-input').forEach(function(searchInput) {
            searchInput.dispatchEvent(new Event('input', { bubbles: true }));
        });

        const steps = form.querySelectorAll('.elzo-forms-step');
        if (steps.length > 1 && !options.keepStep) {
            ElzoFormsChangeStep(form, steps[0], 'reset', null);
        }
    }

    function elzo_forms_update_range_slider(rangeSlider){
        const minRange = rangeSlider.querySelector('.elzo-forms-range-slider-min');
        const maxRange = rangeSlider.querySelector('.elzo-forms-range-slider-max');
        const rangeSliderProgress = rangeSlider.querySelector('.elzo-forms-range-slider-progress');
        const minValue = rangeSlider.querySelector('.elzo-forms-range-slider-min-input');
        const maxValue = rangeSlider.querySelector('.elzo-forms-range-slider-max-input');
        const realInput = rangeSlider.querySelector('.elzo-forms-range-slider-real-input');
        let minRangeValue = 0;
        let maxRangeValue = 100;

        if(minRange && parseInt(minRange.value) >= parseInt(maxRange.value)) {
            minRangeValue = parseInt(maxRange.value);
            maxRangeValue = parseInt(minRange.value);
        } else {
            minRangeValue = minRange ? parseInt(minRange.value) : maxRange.min;
            maxRangeValue = parseInt(maxRange.value);
        }

        if(minValue) minValue.value = minRangeValue;
        if(maxValue) maxValue.value = maxRangeValue;

        const minPercent = minRange ? (((minRangeValue - minRange.min) / (minRange.max - minRange.min)) * 100) : 0;
        const maxPercent = ((maxRangeValue - maxRange.min) / (maxRange.max - maxRange.min)) * 100;

        if(rangeSliderProgress){
            rangeSliderProgress.style.left = minPercent + '%';
            rangeSliderProgress.style.right = (100 - maxPercent) + '%';
        }

        if(realInput) realInput.value = minRange ? `${minRangeValue} - ${maxRangeValue}` : maxRangeValue;
    }
    
    document.addEventListener('click', function(e) {
        if(!e.target.classList.contains('elzo-forms-waiting-confirmation')){
            ElzoFormsRemoveConfirmationElements();
        }
        
        // Next step button
        if (e.target.classList.contains('elzo-forms-next-step-button')) {
            const form = e.target.closest('.elzo-forms-form');
            const settings = ElzoFormsSettings(form);
            const currentStep = form.querySelector('.elzo-forms-step.active');
            const stepAlertWrapper = currentStep.querySelector('.elzo-forms-step-alert-wrapper');
            const nextStep = currentStep.nextElementSibling;

            if (!ElzoFormsValidateStep(currentStep)) {
                const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.fillInRequiredFields + '.';

                if(settings.form_alert_type == 'modal'){
                    ElzoFormsOpenModal(errorText, null, {
                        reason: 'validation',
                        trigger: e.target
                    });
                } else {
                    ElzoFormsShowAlert(errorText, stepAlertWrapper, 'error', {
                        reason: 'validation',
                        trigger: e.target
                    });
                }

                return;
            }

            if (nextStep) {
                ElzoFormsChangeStep(form, nextStep, 'next', e.target);
            }
        }

        // Previous step button
        if (e.target.classList.contains('elzo-forms-previous-step-button')) {
            const form = e.target.closest('.elzo-forms-form');
            const currentStep = form.querySelector('.elzo-forms-step.active');
            const previousStep = currentStep.previousElementSibling;

            if (previousStep) {
                ElzoFormsChangeStep(form, previousStep, 'previous', e.target);
            }
        }

        // Custom dropdown button
        if (e.target.classList.contains('elzo-forms-custom-dropdown-item')) {
            const wrapper = e.target.closest('.elzo-forms-field-wrapper');
            const select = wrapper.querySelector('.elzo-forms-field-select');
            const searchInput = wrapper.querySelector('.elzo-forms-custom-dropdown-search-input');
            const value = e.target.getAttribute('data-value');

            if (select.multiple) {
                const selectedValues = Array.from(select.selectedOptions).map(option => option.value);

                if (selectedValues.includes(value)) {
                    const index = selectedValues.indexOf(value);
                    selectedValues.splice(index, 1);
                } else {
                    selectedValues.push(value);
                }

                Array.from(select.options).forEach(option => {
                    option.selected = selectedValues.includes(option.value);
                });
            } else {
                select.value = value;
            }

            select.dispatchEvent(new Event('change', { bubbles: true }));

            if (searchInput) {
                searchInput.value = '';

                searchInput.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }

        // Custom select facade item
        if (e.target.classList.contains('elzo-forms-custom-select-facade-item')) {
            const wrapper = e.target.closest('.elzo-forms-custom-select-wrapper');
            const select = wrapper.querySelector('.elzo-forms-field-select');
            const value = e.target.getAttribute('data-value');
            const option = ElzoFormsFindOptionByValue(select, value);

            if (option) {
                option.selected = false;
            }

            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        // Custom dropdown toggler
        if (e.target.classList.contains('elzo-forms-custom-dropdown-toggler')) {
            ElzoFormsOpenCustomDropdown(e.target.closest('.elzo-forms-custom-dropdown'));
        }

        // Close custom dropdowns
        if (!e.target.closest('.elzo-forms-custom-select-wrapper')) {
            ElzoFormsCloseCustomDropdowns();
        }

        // Close modals (support clicks on children inside the close button)
        const modalBackdrop = e.target.closest('.elzo-forms-modal-backdrop');
        const modalCloseBtn = e.target.closest('.elzo-forms-modal-close');
        if (modalCloseBtn) {
            const modal = modalCloseBtn.closest('.elzo-forms-modal');

            if (modal) {
                ElzoFormsCloseModal(modal, {
                    reason: 'button',
                    trigger: modalCloseBtn
                });
            }
        } else if (modalBackdrop) {
            const modal = ElzoFormsGetBackdropModal(modalBackdrop);

            if (modal) {
                ElzoFormsCloseModal(modal, {
                    reason: 'backdrop',
                    trigger: modalBackdrop,
                    backdrop: modalBackdrop
                });
            } else {
                ElzoFormsCloseModals({
                    reason: 'backdrop',
                    trigger: modalBackdrop
                });
            }
        }

        // Remove file
        if (e.target.classList.contains('elzo-forms-file-drop-area-upload-list-item-remove')) {
            if(e.target.classList.contains('elzo-forms-waiting-confirmation')){
                ElzoFormsRemoveConfirmationElements();

                ElzoFormsDiscardUpload(e.target.closest('.elzo-forms-file-drop-area-upload-list-item'));

                ElzoFormsFadeOut(e.target.closest('.elzo-forms-file-drop-area-upload-list-item'));

                // Remove the file after the animation
                setTimeout(() => {
                    const dropArea = e.target.closest('.elzo-forms-file-drop-area');

                    e.target.closest('.elzo-forms-file-drop-area-upload-list-item').remove();

                    ElzoFormsUpdateDropArea(dropArea);
                    ElzoFormsRefreshConditionalLogic();
                }, 400);
            } else {
                ElzoFormsTooltip(e.target, ElzoFormsTexts.confirmRemoveFile || 'Are you sure you want to remove this file?');

                e.target.classList.add('elzo-forms-waiting-confirmation');
            }
        }

        // Close alert
        if (e.target.classList.contains('elzo-forms-alert-close')) {
            ElzoFormsRemoveAlert(e.target.closest('.elzo-forms-alert'), {
                reason: 'button',
                trigger: e.target
            });
        }
    });

    document.addEventListener('change', function(e) {
        // Custom dropdown select
        if (e.target.classList.contains('select-w-custom-dropdown')) {
            const value = e.target.value;
            const valueOption = value ? ElzoFormsFindOptionByValue(e.target, value) : null;
            const valueLabel = valueOption ? valueOption.textContent : '';
            const wrapper = e.target.closest('.elzo-forms-field-wrapper');
            const multiple = e.target.multiple;
            const facade = multiple ? wrapper.querySelector('.elzo-forms-custom-select-facade') : null;
            const facadeInput = wrapper.querySelector('.elzo-forms-custom-select-facade-input');
            const dropdown = wrapper.querySelector('.elzo-forms-custom-dropdown');
            const items = dropdown.querySelectorAll('.elzo-forms-custom-dropdown-item');
            const selectedOptions = Array.from(e.target.selectedOptions)
                .filter(option => !option.hasAttribute('data-placeholder'));
            const selectedValues = selectedOptions.map(option => option.value);

            // Set facade min height to facadeInput height to avoid layout shift
            if (facade && facadeInput && facadeInput.offsetParent !== null) {
                facade.style.minHeight = facadeInput.offsetHeight + 'px';
            }

            items.forEach(function(item) {
                const value = item.getAttribute('data-value');

                if (selectedValues.includes(value)) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });  
            
            if (multiple) {
                const facadeItems = [];

                if(selectedValues.length !== 0) {
                    facadeInput.style.display = 'none';

                    facade.style.display = 'flex';

                    // Build the items as nodes. Option values and labels are
                    // author-controlled, so re-parsing them as HTML here would
                    // turn a stored value into markup.
                    selectedOptions.forEach(function(option) {
                        const facadeItem = document.createElement('button');

                        facadeItem.type = 'button';
                        facadeItem.className = 'elzo-forms-custom-select-facade-item';
                        facadeItem.setAttribute('data-value', option.value);
                        facadeItem.textContent = option.textContent + ' \u00d7';

                        facadeItems.push(facadeItem);
                    });
                } else {
                    facade.style.display = 'none';

                    facadeInput.style.display = 'block';
                }

                facade.textContent = '';

                facadeItems.forEach(function(facadeItem) {
                    facade.appendChild(facadeItem);
                });
            } else {
                dropdown.classList.remove('active');
            }

            if(facadeInput) {
                facadeInput.value = value ? (valueLabel ? valueLabel : value) : '';
            }
        }

        // Range slider
        if(e.target.classList.contains('elzo-forms-range-slider-min-input') || e.target.classList.contains('elzo-forms-range-slider-max-input')) {
            const rangeSlider = e.target.closest('.elzo-forms-range-slider-wrapper');
            const minRange = rangeSlider.querySelector('.elzo-forms-range-slider-min');
            const maxRange = rangeSlider.querySelector('.elzo-forms-range-slider-max');
            const minValue = rangeSlider.querySelector('.elzo-forms-range-slider-min-input');
            const maxValue = rangeSlider.querySelector('.elzo-forms-range-slider-max-input');

            if(minRange) minRange.value = minValue.value;
            maxRange.value = maxValue.value;

            elzo_forms_update_range_slider(rangeSlider);
        }
    });

    function ElzoFormsSetFormSubmitting(form, formSubmitButtons, isSubmitting) {
        if (isSubmitting) {
            form.classList.add('loading');

            formSubmitButtons.forEach(button => {
                if (button && !button.hasAttribute('data-original-text')) {
                    button.setAttribute('data-original-text', ElzoFormsEscapeHTML(button.innerHTML));
                }

                button.innerHTML = ElzoFormsTexts.submitting + '...';
            });

            return;
        }

        form.classList.remove('loading');

        formSubmitButtons.forEach(button => {
            const escapedHTML = button.getAttribute('data-original-text');

            if (escapedHTML !== null) {
                button.innerHTML = ElzoFormsUnescapeHTML(escapedHTML);
            }
        });
    }

    function ElzoFormsAppendPageContext(formData) {
        const logicContext = window.ElzoFormsAjax?.logicContext || {};

        // Page and URL conditions are evaluated in the browser; admin-ajax.php has
        // no queried object, so the server can only mirror that verdict when it
        // receives the page the form was rendered on.
        if (logicContext.currentPageId === undefined) return;

        formData.append('elzo_form_page_id', String(parseInt(logicContext.currentPageId, 10) || 0));
        formData.append('elzo_form_page_url', window.location.href);
    }

    function ElzoFormsShowSubmissionMessage(message, settings, container, type = 'error', options = {}) {
        if (settings.form_alert_type == 'modal') {
            ElzoFormsOpenModal(message, null, options);
        } else {
            ElzoFormsShowAlert(message, container, type, options);
        }
    }

    document.addEventListener('submit', async function(e) {
        if (e.target.classList.contains('elzo-forms-form')) {
            e.preventDefault();

            const form = e.target;

            if (form.classList.contains('loading')) {
                return;
            }

            // Prepare the form context
            const settings = ElzoFormsSettings(form);
            const currentStep = form.querySelector('.elzo-forms-step.active');
            const stepAlertWrapper = currentStep ? currentStep.querySelector('.elzo-forms-step-alert-wrapper') : form;
            const alertContainer = stepAlertWrapper || form;
            const formAction = form.getAttribute('action');
            const formMethod = form.getAttribute('method') || 'POST';
            const formSubmitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
            let formData = null;
            let fetchResponse = null;
            let response = null;
            let ajaxStarted = false;
            const submitContext = {
                event: e,
                settings: settings,
                currentStep: currentStep,
                stepAlertWrapper: stepAlertWrapper,
                alertContainer: alertContainer,
                formAction: formAction,
                formMethod: formMethod,
                submitButtons: formSubmitButtons,
                formData: null,
                fetchResponse: null,
                response: null,
                data: null,
                message: '',
                error: null,
                failedPhase: '',
                phase: 'before'
            };

            window.wp.hooks.doAction('elzoForms.form.submit.before', form, submitContext);

            const shouldSubmit = window.wp.hooks.applyFilters('elzoForms.form.submit.shouldSubmit', true, form, submitContext);

            if (!shouldSubmit) {
                submitContext.phase = 'cancelled';
                window.wp.hooks.doAction('elzoForms.form.submit.cancelled', form, 'shouldSubmit', submitContext);
                return;
            }

            submitContext.phase = 'validation';
            if (!ElzoFormsValidateForm(form)) {
                submitContext.phase = 'validationFailed';
                window.wp.hooks.doAction('elzoForms.form.submit.validationFailed', form, submitContext);

                const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.fillInRequiredFields + '.';
                ElzoFormsShowSubmissionMessage(errorText, settings, alertContainer, 'error', {
                    reason: 'validation',
                    trigger: null
                });

                return;
            }

            ElzoFormsSetFormSubmitting(form, formSubmitButtons, true);

            try {
                const beforeSendPromises = [];
                submitContext.phase = 'beforeSend';
                window.wp.hooks.doAction('elzoForms.form.submit.beforeSend', form, beforeSendPromises, submitContext);
                await Promise.all(beforeSendPromises);

                submitContext.phase = 'formData';
                formData = new FormData(form);
                ElzoFormsAppendPageContext(formData);
                submitContext.formData = formData;
                const filteredFormData = window.wp.hooks.applyFilters('elzoForms.form.submit.formData', formData, form, submitContext);
                formData = filteredFormData instanceof FormData ? filteredFormData : formData;
                submitContext.formData = formData;

                const shouldSend = window.wp.hooks.applyFilters('elzoForms.form.submit.shouldSend', true, form, formData, submitContext);

                if (!shouldSend) {
                    submitContext.phase = 'cancelled';
                    window.wp.hooks.doAction('elzoForms.form.submit.cancelled', form, 'shouldSend', submitContext);
                    ElzoFormsSetFormSubmitting(form, formSubmitButtons, false);
                    return;
                }

                submitContext.phase = 'submit';
                window.wp.hooks.doAction('elzoForms.form.submit', form, formData, submitContext);

                submitContext.phase = 'ajax';
                window.wp.hooks.doAction('elzoForms.form.ajax.start', form, formData, formAction, formMethod, submitContext);
                ajaxStarted = true;

                fetchResponse = await fetch(formAction, {
                    method: formMethod,
                    body: formData
                });
                submitContext.fetchResponse = fetchResponse;

                response = await fetchResponse.json();
                submitContext.response = response;

                window.wp.hooks.doAction('elzoForms.form.ajax.end', form, response, fetchResponse, formData, submitContext);
                ElzoFormsSetFormSubmitting(form, formSubmitButtons, false);

                // Safely normalize response structure
                const isObject = response && typeof response === 'object' && response !== null;
                const isSuccess = isObject && response.success === true;
                const data = isObject && response.data && typeof response.data === 'object' && response.data !== null ? response.data : {};
                const message = typeof data.message === 'string' ? data.message : '';
                submitContext.data = data;
                submitContext.message = message;

                if (!isSuccess) {
                    const defaultErrorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.tryAgain + '.';

                    submitContext.phase = 'error';
                    window.wp.hooks.doAction('elzoForms.form.error', form, response, message, data, formData, submitContext);

                    ElzoFormsShowSubmissionMessage((message ? message : defaultErrorText), settings, alertContainer, 'error', {
                        reason: 'submissionError',
                        trigger: null
                    });
                } else {
                    submitContext.phase = 'success';
                    window.wp.hooks.doAction('elzoForms.form.success', form, response, data, formData, submitContext);

                    const redirectUrl = data.redirect_url ? data.redirect_url : '';

                    const redirectDelay = data.redirect_delay ? parseFloat(data.redirect_delay) : 0;

                    const showMessage = !!message && (!redirectUrl || redirectDelay > 0);

                    if (settings.clear_form_after_submission == 'yes') {
                        // An inline message goes into the active step, so a
                        // multi-step form must not leave that step to reset.
                        ElzoFormsResetForm(form, {
                            keepStep: showMessage && settings.form_alert_type != 'modal'
                        });
                    }

                    if (settings.hide_form_after_submission == 'yes') {
                        form.style.display = 'none';
                    }

                    if(showMessage){
                        ElzoFormsShowSubmissionMessage(message, settings, alertContainer, 'success', {
                            reason: 'submissionSuccess',
                            trigger: null
                        });
                    }

                    if (redirectUrl) {
                        setTimeout(function() {
                            window.location.href = redirectUrl;
                        }, redirectDelay * 1000);
                    }
                }
            } catch(error) {
                console.error('Elzo Forms submission error:', error);

                ElzoFormsSetFormSubmitting(form, formSubmitButtons, false);
                submitContext.error = error;
                submitContext.failedPhase = submitContext.phase;
                submitContext.phase = 'error';
                submitContext.formData = formData;
                submitContext.fetchResponse = fetchResponse;
                submitContext.response = response;

                window.wp.hooks.doAction('elzoForms.form.submit.error', form, error, formData, submitContext);

                if (ajaxStarted) {
                    window.wp.hooks.doAction('elzoForms.form.ajax.error', form, error, formData, submitContext);
                }

                const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.tryAgain + '.';
                ElzoFormsShowSubmissionMessage(errorText, settings, alertContainer, 'error', {
                    reason: 'submitError',
                    trigger: null
                });
            }
        }
    });

    document.addEventListener('focusin', function(e) {
        if(!e.target.closest('.elzo-forms-custom-dropdown.active')){
            ElzoFormsCloseCustomDropdowns();
        }

        // Custom dropdown search input
        if (e.target.classList.contains('elzo-forms-custom-dropdown-search-input')) {
            ElzoFormsOpenCustomDropdown(e.target.closest('.elzo-forms-custom-dropdown'));
        }

        // Custom dropdown toggler
        if (e.target.classList.contains('elzo-forms-custom-dropdown-toggler')) {
            ElzoFormsOpenCustomDropdown(e.target.closest('.elzo-forms-custom-dropdown'));
        }

        // Custom select facade input
        if(e.target.classList.contains('elzo-forms-custom-select-facade-input')) {
            const wrapper = e.target.closest('.elzo-forms-custom-select-wrapper');
            const dropdown = wrapper.querySelector('.elzo-forms-custom-dropdown');

            ElzoFormsOpenCustomDropdown(dropdown);
        }
    });

    document.addEventListener('input', function(e) {
        // Custom dropdown search input
        if (e.target.classList.contains('elzo-forms-custom-dropdown-search-input')) {
            const dropdown = e.target.closest('.elzo-forms-custom-dropdown');
            const search = e.target.value.toLowerCase();
            const items = dropdown.querySelectorAll('.elzo-forms-custom-dropdown-item');
            let found = 0;

            items.forEach(function(item) {
                const text = item.textContent.toLowerCase();

                if (text.includes(search)) {
                    item.style.display = 'block';
                    found++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (found) {
                dropdown.querySelector('.nothing-found').style.display = 'none';
            } else {
                dropdown.querySelector('.nothing-found').style.display = 'block';
            }
        }

        // Range slider
        if(e.target.classList.contains('elzo-forms-range-slider-min') || e.target.classList.contains('elzo-forms-range-slider-max')) {
            elzo_forms_update_range_slider(e.target.closest('.elzo-forms-range-slider-wrapper'));
        }
    });

    function ElzoFormsUpdateDropArea(dropArea) {
        if (!dropArea) return;
        
        const text = dropArea.querySelector('.elzo-forms-file-drop-area-text');
        const button = dropArea.querySelector('.elzo-forms-file-drop-area-button');
        const input = dropArea.querySelector('.elzo-forms-file-upload-input');
        const list = dropArea.querySelector('.elzo-forms-file-drop-area-upload-list');

        if (!button || !input || !list) {
            return false;
        }

        const items = list.querySelectorAll('.elzo-forms-file-drop-area-upload-list-item');
        let maxFiles = input.multiple
            ? ElzoFormsParsePositiveInt(dropArea.dataset.maxFiles, 0)
            : 1;
        const minFiles = input.multiple
            ? ElzoFormsParsePositiveInt(dropArea.dataset.minFiles, 0)
            : 0;

        if (maxFiles > 0 && minFiles > maxFiles) {
            maxFiles = minFiles;
        }
        
        if (text) {
            text.style.display = items.length ? 'none' : 'block';
        }

        if (maxFiles && items.length >= maxFiles) {
            button.style.display = 'none';
            
            return maxFiles;
        } else {
            button.style.display = 'inline-block';
            
            return false;
        }
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            ElzoFormsCloseModals({
                reason: 'escape',
                trigger: null
            });
            ElzoFormsCloseCustomDropdowns();
        }
    });

    // File Upload

    document.querySelectorAll('.elzo-forms-file-drop-area').forEach(dropArea => {
        const fileInput = dropArea.querySelector('.elzo-forms-file-upload-input');
        const settings = ElzoFormsSettings(dropArea.closest('.elzo-forms-form'));
        const configuredChunkSize = parseFloat(fileInput.dataset.maxChunk || '0');
        const chunkSize = Number.isFinite(configuredChunkSize) && configuredChunkSize > 0 ? configuredChunkSize * 1024 * 1024 : 1024 * 1024;
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('highlight'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('highlight'), false);
        });

        dropArea.addEventListener('drop', handleDrop, false);

        dropArea.querySelector('.elzo-forms-file-upload-input').addEventListener('change', function() {
            handleFiles(this.files, dropArea);
        });

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files, dropArea);
        }

        function handleFiles(files, dropArea) {
            for (let i = 0; i < files.length; i++) {
                const maxFiles = ElzoFormsUpdateDropArea(dropArea);

                if (maxFiles) {
                    const context = {
                        form: dropArea.closest('.elzo-forms-form'),
                        dropArea: dropArea,
                        fileInput: fileInput,
                        settings: settings,
                        files: files,
                        maxFiles: maxFiles,
                        reason: 'maxFiles'
                    };
                    const errorText = '<strong>'+ElzoFormsTexts.notice + '.</strong> ' + ElzoFormsTexts.maximumFilesReached.replace('%s', maxFiles) + '.';

                    window.wp.hooks.doAction('elzoForms.file.upload.rejected', files[i] || null, dropArea, 'maxFiles', context);

                    if(settings.form_alert_type == 'modal'){
                        ElzoFormsOpenModal(errorText, null, {
                            reason: 'fileUploadRejected',
                            trigger: null
                        });
                    } else {
                        ElzoFormsShowAlert(errorText, dropArea, 'error', {
                            reason: 'fileUploadRejected',
                            trigger: null
                        });
                    }
    
                    return;
                }

                uploadFile(files[i], dropArea);

                ElzoFormsUpdateDropArea(dropArea);
            }
        }

        function uploadFile(file, dropArea) {
            const fileInput = dropArea.querySelector('.elzo-forms-file-upload-input');
            const form = dropArea.closest('.elzo-forms-form');
            const maxFileSizeMb = parseFloat(fileInput.dataset.maxFileSize || '0');
            const maxFileSize = Number.isFinite(maxFileSizeMb) && maxFileSizeMb > 0 ? maxFileSizeMb * 1024 * 1024 : 0;
            const uploadId = ElzoFormsCreateUploadId();
            const chunksTotal = Math.max(1, Math.ceil(file.size / chunkSize));
            const url = window.ElzoFormsAjax && window.ElzoFormsAjax.ajaxUrl ? window.ElzoFormsAjax.ajaxUrl : '/wp-admin/admin-ajax.php';
            const uploadContext = {
                form: form,
                dropArea: dropArea,
                fileInput: fileInput,
                settings: settings,
                uploadId: uploadId,
                chunkSize: chunkSize,
                chunksTotal: chunksTotal,
                maxFileSize: maxFileSize,
                url: url,
                item: null,
                formData: null,
                progressBar: null,
                progressPercentage: null,
                response: null,
                error: null,
                fileUrl: '',
                chunkIndex: 0,
                loaded: 0,
                total: file.size,
                progress: 0
            };

            const shouldUpload = window.wp.hooks.applyFilters('elzoForms.file.upload.shouldUpload', true, file, dropArea, uploadContext);

            if (!shouldUpload) {
                window.wp.hooks.doAction('elzoForms.file.upload.cancelled', file, dropArea, uploadContext);
                return;
            }

            if (maxFileSize > 0 && file.size > maxFileSize) {
                uploadContext.reason = 'fileSize';
                const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.fileSizeExceeded.replace('%s', (maxFileSize / 1024 / 1024).toFixed(2)) + '.';

                window.wp.hooks.doAction('elzoForms.file.upload.rejected', file, dropArea, 'fileSize', uploadContext);

                if(settings.form_alert_type == 'modal'){
                    ElzoFormsOpenModal(errorText, null, {
                        reason: 'fileUploadRejected',
                        trigger: null
                    });
                } else {
                    ElzoFormsShowAlert(errorText, dropArea, 'error', {
                        reason: 'fileUploadRejected',
                        trigger: null
                    });
                }

                return;
            }

            let fileName = file.name;

            if (fileName.length > 15) {
                const extension = fileName.split('.').pop();
                const croppedName = fileName.substring(0, 15 - extension.length - 3) + '...' + extension;
                fileName = croppedName;
            }
            
            let formData = new FormData();
            const template = dropArea.querySelector('.elzo-forms-file-drop-area-upload-list-item-template').cloneNode(true);
            template.style.display = 'flex';
            template.classList.remove('elzo-forms-file-drop-area-upload-list-item-template');
            const nameBox = template.querySelector('.elzo-forms-file-drop-area-upload-list-item-name-box');
            const fileNameElement = document.createElement('strong');
            const fileSizeElement = document.createElement('span');
            fileNameElement.textContent = fileName;
            fileSizeElement.textContent = (file.size / 1024 / 1024).toFixed(2)+' MB';
            nameBox.textContent = '';
            nameBox.appendChild(fileNameElement);
            nameBox.appendChild(document.createTextNode(', '));
            nameBox.appendChild(fileSizeElement);
            dropArea.querySelector('.elzo-forms-file-drop-area-upload-list').appendChild(template);
            const templateInput = template.querySelector('.elzo-forms-file-drop-area-upload-list-item-input');

            const progressBar = template.querySelector('.elzo-forms-file-drop-area-upload-list-item-progress-bar');
            const progressPercentage = template.querySelector('.elzo-forms-file-drop-area-upload-list-item-progress-percentage');
            const progressTrack = template.querySelector('.elzo-forms-file-drop-area-upload-list-item-progress');

            if (progressTrack) {
                // Set here as well: a theme's copy of the template may predate these attributes.
                progressTrack.setAttribute('role', 'progressbar');
                progressTrack.setAttribute('aria-valuemin', '0');
                progressTrack.setAttribute('aria-valuemax', '100');
                progressTrack.setAttribute('aria-valuenow', '0');

                if (ElzoFormsTexts.uploadProgress) {
                    progressTrack.setAttribute('aria-label', ElzoFormsTexts.uploadProgress.replace('%s', file.name));
                }
            }

            function renderProgress(progress, label) {
                progressBar.style.width = progress + '%';
                progressPercentage.textContent = label;

                if (progressTrack) {
                    progressTrack.setAttribute('aria-valuenow', String(Math.floor(progress)));
                }
            }

            uploadContext.item = template;
            uploadContext.formData = formData;
            uploadContext.progressBar = progressBar;
            uploadContext.progressPercentage = progressPercentage;

            // Create file thumbnail dynamically if it's an image
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
            
                reader.onload = function(e) {
                    const img = new Image();
                    img.src = e.target.result;
            
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
            
                        canvas.width = 160;
                        canvas.height = 160;
            
                        // Calculate the cropping area
                        const cropSize = Math.min(img.width, img.height);
                        const cropX = (img.width - cropSize) / 2;
                        const cropY = (img.height - cropSize) / 2;
            
                        ctx.drawImage(img, cropX, cropY, cropSize, cropSize, 0, 0, 160, 160);
            
                        const thumbnail = document.createElement('img');
                        thumbnail.width = 40;
                        thumbnail.height = 40;
                        thumbnail.src = canvas.toDataURL('image/png');
                        thumbnail.alt = file.name;
                        thumbnail.classList.add('elzo-forms-file-drop-area-upload-list-item-thumbnail-image');
                        template.querySelector('.elzo-forms-file-drop-area-upload-list-item-thumbnail').innerHTML = thumbnail.outerHTML;
                    }
                }
            
                reader.readAsDataURL(file);
            }

            formData.set('action', 'elzo_forms_upload_file');
            formData.set('form_id', fileInput.dataset.formId);
            formData.set('field_id', fileInput.dataset.fieldId);
            formData.set('nonce', window.ElzoFormsAjax ? window.ElzoFormsAjax.uploadNonce : '');
            formData.set('upload_id', uploadId);
            formData.set('chunks_total', chunksTotal);
            const filteredUploadFormData = window.wp.hooks.applyFilters('elzoForms.file.upload.formData', formData, file, dropArea, uploadContext);
            formData = filteredUploadFormData instanceof FormData ? filteredUploadFormData : formData;
            formData.set('upload_id', uploadId);
            formData.set('chunks_total', chunksTotal);
            uploadContext.formData = formData;

            const upload = {
                uploadId: uploadId,
                formId: formData.get('form_id') || '',
                fieldId: formData.get('field_id') || '',
                url: url,
                fileUrl: '',
                pending: null,
                cancelled: false
            };
            ElzoFormsFileUploads.set(template, upload);

            window.wp.hooks.doAction('elzoForms.file.upload.before', file, dropArea, uploadContext);

            let start = 0;
            let end = chunkSize;
            let chunkIndex = 0;

            function uploadChunk() {
                const chunk = file.slice(start, end);
                uploadContext.chunkIndex = chunkIndex;
                uploadContext.loaded = start;
                uploadContext.progress = file.size > 0 ? (start / file.size) * 100 : 0;
                formData.set('file', chunk);
                formData.set('file_name', file.name);
                formData.set('chunk_index', chunkIndex);
                formData.set('chunks_total', chunksTotal);

                upload.pending = fetch(url, {
                    method: 'POST',
                    body: formData
                }).then(response => response.json()).then(answer => {
                    if (answer && answer.success && answer.data && answer.data.file_url) {
                        upload.fileUrl = answer.data.file_url;
                    }

                    // The visitor removed the file; ElzoFormsDiscardUpload() takes it from here.
                    if (upload.cancelled) {
                        return;
                    }

                    uploadContext.response = answer;

                    if (answer.success) {
                        start = end;
                        end = start + chunkSize;
                        chunkIndex++;
                        const percentage = file.size > 0 ? (start / file.size) * 100 : 100;
                        uploadContext.loaded = Math.min(start, file.size);
                        uploadContext.progress = Math.min(percentage, 100);
                        uploadContext.chunkIndex = chunkIndex;
                        renderProgress(uploadContext.progress, uploadContext.progress.toFixed(2) + '%');

                        window.wp.hooks.doAction('elzoForms.file.upload.progress', file, dropArea, uploadContext.progress, uploadContext);

                        if (start < file.size) {
                            uploadChunk();
                        } else {
                            renderProgress(100, '100%');

                            templateInput.value = answer.data.file_url;
                            uploadContext.fileUrl = answer.data.file_url;
                            uploadContext.progress = 100;

                            ElzoFormsRefreshConditionalLogic();

                            window.wp.hooks.doAction('elzoForms.file.upload.success', file, dropArea, answer, uploadContext);
                        }
                    } else {
                        const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + answer.data.message + '.';
                        uploadContext.error = answer;

                        window.wp.hooks.doAction('elzoForms.file.upload.error', file, dropArea, answer, uploadContext);

                        if(settings.form_alert_type == 'modal'){
                            ElzoFormsOpenModal(errorText, null, {
                                reason: 'fileUploadError',
                                trigger: null
                            });
                        } else {
                            ElzoFormsShowAlert(errorText, dropArea, 'error', {
                                reason: 'fileUploadError',
                                trigger: null
                            });
                        }

                        template.remove();

                        ElzoFormsUpdateDropArea(dropArea);
                    }
                }).catch(error => {
                    if (upload.cancelled) {
                        return;
                    }

                    console.error('Error:', error);
                    uploadContext.error = error;

                    window.wp.hooks.doAction('elzoForms.file.upload.error', file, dropArea, error, uploadContext);

                    const errorText = '<strong>'+ElzoFormsTexts.errorOccurred + '.</strong> ' + ElzoFormsTexts.tryAgain + '.';

                    if(settings.form_alert_type == 'modal'){
                        ElzoFormsOpenModal(errorText, null, {
                            reason: 'fileUploadError',
                            trigger: null
                        });
                    } else {
                        ElzoFormsShowAlert(errorText, dropArea, 'error', {
                            reason: 'fileUploadError',
                            trigger: null
                        });
                    }

                    // The request failed in transit, so the server may still hold a partial file.
                    ElzoFormsDiscardUpload(template);
                    template.remove();
                    ElzoFormsUpdateDropArea(dropArea);
                });
            }

            uploadChunk();
        }
    }); 
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
