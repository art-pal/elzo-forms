/**
 * Elzo Forms reCAPTCHA v3 Integration
 *
 * Automatically executes reCAPTCHA v3 before Elzo Forms sends a valid submission.
 */
(function() {
    'use strict';

    if (!window.wp || !window.wp.hooks || typeof window.wp.hooks.addAction !== 'function') {
        console.error('Elzo Forms reCAPTCHA: WordPress wp.hooks not found');
        return;
    }

    // Track forms currently processing reCAPTCHA
    const processingForms = new WeakSet();
    let recaptchaReadyPromise = null;

    function waitForRecaptcha() {
        if (recaptchaReadyPromise) {
            return recaptchaReadyPromise;
        }

        recaptchaReadyPromise = new Promise(function(resolve, reject) {
            const startedAt = Date.now();
            const timeout = 15000;

            function check() {
                if (window.grecaptcha && typeof window.grecaptcha.ready === 'function') {
                    window.grecaptcha.ready(resolve);
                    return;
                }

                if (Date.now() - startedAt >= timeout) {
                    reject(new Error('reCAPTCHA script did not become ready in time'));
                    return;
                }

                setTimeout(check, 100);
            }

            check();
        });

        recaptchaReadyPromise.catch(function() {
            recaptchaReadyPromise = null;
        });

        return recaptchaReadyPromise;
    }

    /**
     * Execute reCAPTCHA and add token to form
     */
    async function executeRecaptcha(form, siteKey, action) {
        await waitForRecaptcha();

        return new Promise(function(resolve, reject) {
            window.grecaptcha.execute(siteKey, { action: action })
                .then(function(token) {
                    // Add token to form
                    let tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
                    if (!tokenInput) {
                        tokenInput = document.createElement('input');
                        tokenInput.type = 'hidden';
                        tokenInput.name = 'g-recaptcha-response';
                        form.appendChild(tokenInput);
                    }
                    tokenInput.value = token;
                    resolve(token);
                })
                .catch(reject);
        });
    }

    // Initialize reCAPTCHA for forms
    function initRecaptcha() {
        // Elzo Forms waits for promises pushed into this action's second argument
        // before it builds FormData and sends the AJAX request.
        window.wp.hooks.addAction('elzoForms.form.submit.beforeSend', 'elzoForms/recaptcha', function(form, beforeSendPromises, submitContext) {
            // Check if form has reCAPTCHA configured (by presence of site key)
            if (!form) {
                return;
            }

            if (!Array.isArray(beforeSendPromises)) {
                return;
            }

            const siteKey = form.getAttribute('data-recaptcha-site-key');
            if (!siteKey) {
                // No reCAPTCHA configured for this form
                return;
            }

            // Get action from attribute, or generate from form ID
            let action = form.getAttribute('data-recaptcha-action');
            if (!action) {
                // Extract form ID from hidden input and generate form-specific action
                const formId = form.querySelector('input[name="elzo_form_id"]')?.value;
                action = formId ? 'form_' + formId : 'submit';
            }

            // Check if already processing this form
            if (processingForms.has(form)) {
                return;
            }

            if (submitContext && typeof submitContext === 'object') {
                submitContext.recaptchaAction = action;
            }

            processingForms.add(form);

            beforeSendPromises.push(
                executeRecaptcha(form, siteKey, action)
                    .then(function(token) {
                        if (submitContext && typeof submitContext === 'object') {
                            submitContext.recaptchaToken = token;
                        }
                    })
                    .finally(function() {
                        processingForms.delete(form);
                    })
            );
        });
    }

    initRecaptcha();

})();
