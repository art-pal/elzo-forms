/**
 * Elzo Forms Import / Export screen.
 *
 * Every control on the screen works without this script; it only makes the
 * choices clearer and lets a file be dropped onto the upload field.
 */
(function () {
    'use strict';

    var settings = window.ElzoFormsImportExport || {};

    function each(selector, callback, root) {
        Array.prototype.forEach.call((root || document).querySelectorAll(selector), callback);
    }

    function onChange(elements, callback) {
        Array.prototype.forEach.call(elements, function (element) {
            element.addEventListener('change', callback);
        });
        callback();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var screen = document.querySelector('.elzo-forms-import-export');
        if (!screen) {
            return;
        }

        screen.classList.add('elzo-forms-ie-enhanced');

        // Form pickers: "Select all" follows the form checkboxes, and the search
        // field narrows the list. While a search is active, "Select all" ticks or
        // clears the forms shown; it stands for all forms only once every form
        // is ticked.
        each('[data-elzo-ie-picker]', function (picker) {
            var toggle = picker.querySelector('[data-elzo-ie-toggle-all]');
            var choices = picker.querySelectorAll('[data-elzo-ie-choice]');
            var search = picker.querySelector('[data-elzo-ie-choice-search]');
            var input = search ? search.querySelector('input[type="search"]') : null;
            var clear = search ? search.querySelector('button') : null;
            var empty = picker.querySelector('[data-elzo-ie-choice-empty]');
            if (!toggle || !choices.length) {
                return;
            }

            function row(choice) {
                return choice.closest('label');
            }

            function sync() {
                var ticked = Array.prototype.filter.call(choices, function (choice) {
                    return choice.checked;
                }).length;

                toggle.checked = ticked === choices.length;
                toggle.indeterminate = ticked > 0 && ticked < choices.length;
            }

            toggle.addEventListener('change', function () {
                Array.prototype.forEach.call(choices, function (choice) {
                    if (!row(choice).hidden) {
                        choice.checked = toggle.checked;
                    }
                });
                sync();
            });

            onChange(choices, sync);

            if (!input) {
                return;
            }

            function filter() {
                var term = input.value.trim().toLowerCase();
                var shown = 0;

                Array.prototype.forEach.call(choices, function (choice) {
                    var matches = term === '' || row(choice).textContent.toLowerCase().indexOf(term) !== -1;
                    row(choice).hidden = !matches;
                    if (matches) {
                        shown++;
                    }
                });

                if (clear) {
                    clear.style.display = term === '' ? 'none' : '';
                }
                if (empty) {
                    empty.hidden = shown > 0;
                }
            }

            search.hidden = false;
            input.addEventListener('input', filter);

            // Enter in the search field must not submit the export form.
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }
            });

            if (clear) {
                clear.addEventListener('click', function () {
                    input.value = '';
                    filter();
                    input.focus();
                });
            }
        }, screen);

        // Selected submissions are exported as chosen, so the filters are
        // hidden and left out of the request while the selection applies.
        each('[data-elzo-ie-use-selection]', function (toggle) {
            var form = toggle.closest('form');
            var filters = form ? form.querySelector('[data-elzo-ie-filters]') : null;
            if (!filters) {
                return;
            }

            onChange([toggle], function () {
                filters.hidden = toggle.checked;
                each('input, select', function (input) {
                    input.disabled = toggle.checked;
                }, filters);
            });
        }, screen);

        // Options that only apply to one format.
        each('form', function (form) {
            var formats = form.querySelectorAll('[data-elzo-ie-format]');
            if (!formats.length) {
                return;
            }

            onChange(formats, function () {
                var checked = form.querySelector('[data-elzo-ie-format]:checked');
                var isJson = !!checked && checked.value === 'json';

                each('[data-elzo-ie-json-only]', function (input) {
                    input.disabled = !isJson;
                }, form);
                each('[data-elzo-ie-json-only-group]', function (group) {
                    group.classList.toggle('is-disabled', !isJson);
                }, form);
                each('[data-elzo-ie-csv-only]', function (input) {
                    input.disabled = isJson;
                }, form);
                each('[data-elzo-ie-csv-only-group]', function (group) {
                    group.classList.toggle('is-disabled', isJson);
                }, form);
            });
        }, screen);

        // Explain Replace while it is selected.
        each('[data-elzo-ie-action]', function (select) {
            var row = select.closest('[data-elzo-ie-row]');
            if (!row) {
                return;
            }

            onChange([select], function () {
                row.classList.toggle('is-replace', select.value === 'replace');
            });
        }, screen);

        // Drop a file onto the upload field.
        each('[data-elzo-ie-dropzone]', function (zone) {
            var input = zone.querySelector('[data-elzo-ie-file]');
            var name = zone.querySelector('[data-elzo-ie-file-name]');
            if (!input) {
                return;
            }

            function showName() {
                if (!name) {
                    return;
                }

                name.textContent = input.files && input.files[0]
                    ? String(settings.selectedFile || '%s').replace('%s', input.files[0].name)
                    : '';
            }

            input.addEventListener('change', showName);

            ['dragenter', 'dragover'].forEach(function (type) {
                zone.addEventListener(type, function (event) {
                    event.preventDefault();
                    zone.classList.add('is-dragover');
                });
            });

            ['dragleave', 'dragend', 'drop'].forEach(function (type) {
                zone.addEventListener(type, function () {
                    zone.classList.remove('is-dragover');
                });
            });

            zone.addEventListener('drop', function (event) {
                event.preventDefault();

                if (!event.dataTransfer || !event.dataTransfer.files || !event.dataTransfer.files.length) {
                    return;
                }

                try {
                    input.files = event.dataTransfer.files;
                } catch (error) {
                    return;
                }

                showName();
            });
        }, screen);
    });
})();
