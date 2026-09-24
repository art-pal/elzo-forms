/* Shared Variables editor. Persist only {{ paths }}, never visual token markup. */
(function () {
    'use strict';
    const config = window.ElzoFormsVariablesConfig || { items: [], texts: {} };
    const t = key => config.texts[key] || '';
    const editors = new Map();
    let sequence = 0;
    let active = null;
    let request = 0;
    const validPath = path => typeof path === 'string' && path.length <= 2048 && path.split('.').length <= 64 && /^[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*$/.test(path);
    const escape = value => String(value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const reference = path => '{{ ' + path + ' }}';

    function token(path) {
        return '<span class="elzo-forms-variable-token" contenteditable="false" data-elzo-variable="' + escape(path) + '" title="' + escape(reference(path)) + '">' + escape(path.replace(/^fields\.by_key\./, '')) + '</span>';
    }

    function markup(value) {
        let offset = 0;
        let html = '';
        const expression = /\{\{\s*([A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*)\s*\}\}/g;
        for (const match of value.matchAll(expression)) {
            if (match.index > 0 && value[match.index - 1] === '\\') continue;
            if (!validPath(match[1])) continue;
            html += escape(value.slice(offset, match.index)).replace(/\n/g, '<br>') + token(match[1]);
            offset = match.index + match[0].length;
        }
        return html + escape(value.slice(offset)).replace(/\n/g, '<br>');
    }

    function serialize(node) {
        if (node.nodeType === Node.TEXT_NODE) return node.nodeValue.replace(/\u00a0/g, ' ').replace(/\uFEFF/g, '');
        if (node.nodeType !== Node.ELEMENT_NODE && node.nodeType !== Node.DOCUMENT_FRAGMENT_NODE) return '';
        if (node.nodeType === Node.ELEMENT_NODE) {
            if (node.hasAttribute('data-mce-bogus')) return '';
            const path = node.getAttribute('data-elzo-variable');
            if (path && validPath(path)) return reference(path);
            if (node.tagName === 'BR') return '\n';
        }
        return Array.from(node.childNodes).map((child, index) => {
            const block = child.nodeType === Node.ELEMENT_NODE && ['P', 'DIV'].includes(child.tagName);
            return (block && index > 0 ? '\n' : '') + serialize(child);
        }).join('');
    }

    function changed(control) {
        control.dispatchEvent(new Event('input', { bubbles: true }));
        control.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function selectToken(state, node) {
        state.editor.getBody().querySelectorAll('.elzo-forms-variable-token.is-selected').forEach(token => token.classList.remove('is-selected'));
        state.selectedToken = node;
        if (node) node.classList.add('is-selected');
    }

    function trackTokenSelection(state) {
        const editor = state.editor;
        editor.on('click', event => {
            const node = event.target.closest?.('[data-elzo-variable]');
            selectToken(state, node && editor.getBody().contains(node) ? node : null);
            if (state.selectedToken) {
                editor.selection.select(state.selectedToken);
                state.bookmark = editor.selection.getBookmark(2, true);
            }
        });
        editor.on('keyup mouseup', () => {
            const range = editor.selection.getRng();
            // A keyboard selection must contain exactly one whole token.
            let node = !range.collapsed && range.startContainer === range.endContainer && range.endOffset === range.startOffset + 1
                ? range.startContainer.childNodes[range.startOffset] : null;
            const container = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
            if (!range.collapsed && container?.closest('[data-mce-bogus]')) node = editor.getBody().querySelector('[data-elzo-variable][data-mce-selected="1"]');
            selectToken(state, node?.nodeType === Node.ELEMENT_NODE && node.hasAttribute('data-elzo-variable') ? node : null);
        });
        editor.on('input SetContent undo redo', () => selectToken(state, null));
    }

    function visualInsert(state, path, exact) {
        const editor = state.editor;
        editor.focus();
        const selected = state.selectedToken;
        if (selected && editor.getBody().contains(selected)) editor.selection.select(selected);
        else if (state.bookmark) editor.selection.moveToBookmark(state.bookmark);
        selectToken(state, null);
        editor.undoManager.transact(() => {
            if (exact) editor.setContent(token(path)); else editor.insertContent(token(path));
        });
        state.bookmark = editor.selection.getBookmark(2, true);
    }

    function nativeInsert(control, path, selection, exact) {
        const value = reference(path);
        if (exact) control.value = value;
        else {
            const start = selection ? selection.start : (control.selectionStart || 0);
            const end = selection ? selection.end : (control.selectionEnd || start);
            control.value = control.value.slice(0, start) + value + control.value.slice(end);
            control.setSelectionRange(start + value.length, start + value.length);
        }
        changed(control);
        control.focus();
    }

    function initialize(control) {
        if (editors.has(control) || control.matches(':disabled') || control.readOnly) return;
        // Repeater clones can contain generated DOM; rebuild it from the canonical control.
        if (control.parentElement.classList.contains('elzo-forms-variables-shell')) {
            const clone = control.parentElement;
            clone.replaceWith(control);
        }
        control.hidden = false;
        if (['email', 'url'].includes(control.type)) control.type = 'text';
        const shell = document.createElement('div');
        shell.className = 'elzo-forms-variables-shell';
        const surface = document.createElement('div');
        surface.className = 'elzo-forms-variables-editor';
        surface.id = 'elzo-forms-variables-editor-' + (++sequence);
        surface.tabIndex = 0;
        surface.setAttribute('role', 'textbox');
        surface.setAttribute('aria-multiline', control.tagName === 'TEXTAREA' ? 'true' : 'false');
        surface.setAttribute('aria-label', control.labels && control.labels[0] ? control.labels[0].textContent.trim() : t('template'));
        surface.setAttribute('data-placeholder', control.placeholder || '');
        if (control.required) surface.setAttribute('aria-required', 'true');
        if (control.hasAttribute('aria-describedby')) surface.setAttribute('aria-describedby', control.getAttribute('aria-describedby'));
        const toolbar = document.createElement('div');
        toolbar.className = 'elzo-forms-variables-toolbar';
        const pick = document.createElement('button');
        pick.type = 'button'; pick.className = 'button button-small elzo-forms-variable-picker-trigger elzo-forms-variables-pick';
        pick.innerHTML = '<i class="elzo-icon elzo-icon-variable" aria-hidden="true"></i><span></span>';
        pick.querySelector('span').textContent = t('variables');
        const source = document.createElement('button');
        source.type = 'button'; source.className = 'button-link elzo-forms-variables-source'; source.textContent = t('editTemplate');
        source.setAttribute('aria-pressed', 'false');
        toolbar.append(pick, source);
        control.before(shell); shell.append(control, surface, toolbar);
        const state = { control, shell, surface, source, editor: null, bookmark: null, sourceMode: true, syncing: false };
        editors.set(control, state);
        surface.hidden = true;
        if (!window.tinymce) { source.hidden = true; return; }
        surface.hidden = false;
        surface.innerHTML = markup(control.value);
        window.tinymce.init({
            target: surface, inline: true, menubar: false, toolbar: false, statusbar: false,
            forced_root_block: false, entity_encoding: 'raw', branding: false, hidden_input: false,
            valid_elements: 'br,div,p,span[class|contenteditable|data-elzo-variable|title]',
            setup: editor => {
                state.editor = editor;
                trackTokenSelection(state);
                editor.on('init', () => { control.hidden = true; state.sourceMode = false; });
                editor.on('input change undo redo SetContent', () => sync(state));
                editor.on('blur keyup mouseup', () => {
                    sync(state);
                    state.bookmark = editor.selection.getBookmark(2, true);
                });
                editor.on('keydown', event => {
                    if (control.tagName !== 'TEXTAREA' && event.key === 'Enter') event.preventDefault();
                });
                editor.on('paste', event => {
                    if (!event.clipboardData) return;
                    event.preventDefault();
                    let text = event.clipboardData.getData('text/plain');
                    if (control.tagName !== 'TEXTAREA') text = text.replace(/[\r\n]+/g, ' ');
                    editor.undoManager.transact(() => editor.insertContent(markup(text)));
                    sync(state);
                });
                editor.on('copy cut', event => {
                    if (!event.clipboardData) return;
                    event.preventDefault();
                    const fragment = editor.selection.getRng().cloneContents();
                    event.clipboardData.setData('text/plain', serialize(fragment));
                    if (event.type === 'cut') {
                        editor.undoManager.transact(() => editor.selection.setContent(''));
                        sync(state);
                    }
                });
            }
        });
        source.addEventListener('click', () => {
            state.sourceMode = !state.sourceMode;
            source.setAttribute('aria-pressed', String(state.sourceMode));
            source.textContent = state.sourceMode ? t('visualEditor') : t('editTemplate');
            if (state.sourceMode) sync(state, true);
            else if (state.editor) state.editor.setContent(markup(control.value));
            control.hidden = !state.sourceMode;
            surface.hidden = state.sourceMode;
            if (state.sourceMode) control.focus(); else state.editor.focus();
        });
        control.addEventListener('input', () => {
            if (!state.syncing && !state.sourceMode && state.editor) state.editor.setContent(markup(control.value));
        });
        control.addEventListener('invalid', event => {
            event.preventDefault();
            if (!state.sourceMode) source.click();
            control.focus();
        });
        // Label clicks retain the canonical input's label association.
        if (control.labels) Array.from(control.labels).forEach(label => label.addEventListener('click', event => {
            if (!state.sourceMode && state.editor) { event.preventDefault(); state.editor.focus(); }
        }));
    }

    function sync(state, force) {
        if (!state.editor || (state.sourceMode && !force) || state.syncing) return;
        const value = serialize(state.editor.getBody());
        if (state.control.value === value) return;
        state.syncing = true;
        state.control.value = state.control.tagName === 'TEXTAREA' ? value : value.replace(/[\r\n]+/g, ' ');
        changed(state.control);
        state.syncing = false;
    }
    function mount(root) {
        let selector = '[data-elzo-variables="notification"]';
        root.querySelectorAll(selector).forEach(control => {
            initialize(control);
        });
        cleanup();
    }

    function cleanup() {
        for (const [control, state] of editors) {
            if (!control.isConnected) {
                if (state.editor) state.editor.remove();
                editors.delete(control);
            }
        }
    }

    function coreItems() {
        const items = [];
        const fields = Array.from(document.querySelectorAll('.elzo-forms-form-fields .elzo-forms-field')).map(field => ({
            id: field.dataset.id || '', key: field.querySelector('.elzo-forms-field-key')?.value || '',
            label: field.querySelector('[id^="elzo-forms-field-admin-label-"]')?.value || field.querySelector('[id^="elzo-forms-field-label-"]')?.value || '',
            type: field.querySelector('[id^="elzo-forms-field-type-"]')?.value || '',
            multiple: field.querySelector('[id^="elzo-forms-field-multiple-"]')?.checked || false
        }));
        const counts = Object.create(null);
        fields.forEach(field => { if (field.key) counts[field.key] = (counts[field.key] || 0) + 1; });
        fields.forEach(field => {
            if (['button', 'content'].includes(field.type.split(':')[0])) return;
            if (!/^[A-Za-z0-9_-]+$/.test(field.key) || counts[field.key] !== 1 || !validPath('fields.by_key.' + field.key)) return;
            const base = field.type.split(':')[0];
            const subtype = field.type.split(':')[1] || base;
            const type = ['checkbox', 'file'].includes(base) || (base === 'select' && field.multiple) ? 'array' : 'string';
            items.push({ path: 'fields.by_key.' + field.key, label: field.label || field.key, group: t('fields'), type, format: ['email', 'url', 'date', 'time'].includes(subtype) ? subtype : 'text' });
        });
        return items.concat(config.items);
    }

    const dialog = document.createElement('dialog');
    dialog.className = 'elzo-forms-variables-picker';
    dialog.setAttribute('aria-labelledby', 'elzo-forms-variables-picker-title');
    dialog.innerHTML = '<div class="elzo-forms-variables-picker-heading"><strong id="elzo-forms-variables-picker-title"><i class="elzo-icon elzo-icon-variable" aria-hidden="true"></i><span></span></strong><button type="button" class="button-link elzo-forms-variables-close"></button></div><input type="search" class="elzo-forms-variables-search" autocomplete="off"><p class="elzo-forms-variables-status" role="status"></p><div class="elzo-forms-variables-list"></div><details class="elzo-forms-variables-manual"><summary></summary><label><span></span><input type="text" class="elzo-forms-variables-path" autocomplete="off" spellcheck="false"></label><button type="button" class="button elzo-forms-variables-insert-path"></button></details>';
    const search = dialog.querySelector('.elzo-forms-variables-search');
    const status = dialog.querySelector('.elzo-forms-variables-status');
    const list = dialog.querySelector('.elzo-forms-variables-list');
    const manual = dialog.querySelector('.elzo-forms-variables-path');
    const insertPath = dialog.querySelector('.elzo-forms-variables-insert-path');

    function render() {
        list.replaceChildren();
        if (!active) return;
        const query = search.value.trim().toLowerCase();
        const groups = new Map();
        active.items.filter(item => item.selectable !== false && (!query || (item.label + ' ' + item.path).toLowerCase().includes(query))).forEach(item => {
            const group = item.group || item.provider || t('variables');
            if (!groups.has(group)) groups.set(group, []);
            groups.get(group).push(item);
        });
        for (const [name, items] of groups) {
            const heading = document.createElement('p'); heading.className = 'elzo-forms-variables-group'; heading.textContent = name; list.append(heading);
            items.forEach(item => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'elzo-forms-variables-option';
                const label = document.createElement('span'); label.textContent = item.label || item.path;
                const path = document.createElement('small'); path.textContent = item.path;
                button.append(label, path); button.addEventListener('click', () => insert(item.path)); list.append(button);
            });
        }
        status.textContent = groups.size ? '' : t('noVariables');
    }

    function insert(path) {
        if (!active || !validPath(path)) return;
        const context = active;
        context.restoreFocus = false;
        dialog.close();
        insertInto(context.control, path, context.selection, false);
    }

    function insertInto(control, path, selection, exact) {
        const state = editors.get(control);
        if (state && state.editor && !state.sourceMode) {
            visualInsert(state, path, exact);
            sync(state);
        } else nativeInsert(control, path, selection, exact);
    }

    async function open(button, control) {
        const state = editors.get(control);
        if (state && state.editor && !state.sourceMode) state.bookmark = state.editor.selection.getBookmark(2, true);
        active = { button, control, restoreFocus: true, items: [], selection: control && typeof control.selectionStart === 'number' ? { start: control.selectionStart, end: control.selectionEnd } : null };
        search.value = ''; manual.value = ''; insertPath.disabled = true;
        dialog.querySelector('details').open = false;
        dialog.querySelector('.elzo-forms-variables-context')?.remove();
        dialog.showModal(); search.focus();
        await loadCatalog();
    }

    async function loadCatalog() {
        const ticket = ++request;
        active.items = [];
        list.replaceChildren(); status.textContent = t('loading');
        try {
            let items = coreItems();
            if (active.control.dataset.elzoVariableDestination === 'email_recipient') items = items.filter(item => item.type === 'string' && item.format === 'email');
            if (ticket !== request || !dialog.open) return;
            active.items = items; render();
        } catch (error) {
            if (ticket === request && dialog.open) status.textContent = t('loadFailed');
        }
    }

    window.ElzoFormsVariables = {
        // Pure helpers are also used by the browser regression harness.
        markup, serialize, validPath
    };
    document.addEventListener('DOMContentLoaded', () => {
        document.body.append(dialog);
        dialog.querySelector('#elzo-forms-variables-picker-title span').textContent = t('variables');
        dialog.querySelector('.elzo-forms-variables-close').textContent = t('close');
        search.placeholder = t('search'); search.setAttribute('aria-label', t('search'));
        dialog.querySelector('summary').textContent = t('customPath');
        dialog.querySelector('label span').textContent = t('variablePath');
        insertPath.textContent = t('insert');
        mount(document);
        new MutationObserver(records => {
            const roots = new Set();
            let selector = '[data-elzo-variables="notification"]';
            records.forEach(record => record.addedNodes.forEach(node => {
                if (node.nodeType === Node.ELEMENT_NODE && (node.matches(selector) || node.querySelector(selector))) roots.add(node.parentElement || node);
            }));
            roots.forEach(mount);
            if (records.some(record => record.removedNodes.length)) cleanup();
        }).observe(document.body, { childList: true, subtree: true });
    });
    search.addEventListener('input', render);
    search.addEventListener('keydown', event => {
        if (event.key === 'ArrowDown') { event.preventDefault(); list.querySelector('button')?.focus(); }
        if (event.key === 'Enter' && list.querySelector('button')) { event.preventDefault(); list.querySelector('button').click(); }
    });
    list.addEventListener('keydown', event => {
        const buttons = Array.from(list.querySelectorAll('button'));
        const index = buttons.indexOf(document.activeElement);
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            buttons[Math.max(0, Math.min(buttons.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1)))]?.focus();
        }
    });
    manual.addEventListener('input', () => { insertPath.disabled = !validPath(manual.value.trim()); });
    insertPath.addEventListener('click', () => insert(manual.value.trim()));
    dialog.querySelector('.elzo-forms-variables-close').addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => { ++request; if (active?.restoreFocus && active.button.isConnected) active.button.focus(); });
    // Variable buttons insert into their explicitly supported destinations.
    document.addEventListener('click', event => {
        let button = event.target.closest('.elzo-forms-variables-pick');
        if (!button || button.disabled) return;
        let pickerButton = button;
        let control = button.closest('.elzo-forms-variables-shell')?.querySelector('input, textarea');
        if (!control) return;
        event.preventDefault(); event.stopPropagation();
        open(pickerButton, control);
    }, true);
    document.addEventListener('submit', () => {
        editors.forEach(state => sync(state));
    }, true);
})();
