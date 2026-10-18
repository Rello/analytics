/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

OCA.Analytics.JsonPicker = {
    // The legacy grammar has no escaping for separators or trimmed field names.
    selectable: parts => parts.every(key => key !== '' && key.trim() === key && !/[/{},]/.test(key)),
    isContainer: value => value !== null && typeof value === 'object',

    decode: function (text) {
        try {
            return JSON.parse(text);
        } catch (error) {
            // Match the datasource's support for line-delimited JSON, but do not
            // silently hide malformed lines in the browser.
            const lines = text.trim().split(/\r?\n/);
            if (lines.length < 2) throw error;
            return lines.map(line => JSON.parse(line));
        }
    },

    open: async function (form, datasource, input) {
        const picker = OCA.Analytics.JsonPicker;
        const options = {};
        for (const field of form.querySelectorAll('input, select')) {
            if (field.id !== 'dataSourceType') options[field.id] = field.value;
        }
        const controller = new AbortController();
        let previewController;
        let revision = 0;
        let selected = [];
        let rowPath = null;
        const content = document.importNode(document.getElementById('templateJsonPicker').content, true);
        const root = content.querySelector('.jsonPicker');
        const tree = root.querySelector('[data-role="tree"]');
        const pathDisplay = root.querySelector('#jsonPickerPath');
        let extractionPath = input.value;
        const status = root.querySelector('[data-role="status"]');
        const preview = root.querySelector('[data-role="preview"]');
        const previewButton = root.querySelector('[data-action="preview"]');
        const back = root.querySelector('[data-action="root"]');
        pathDisplay.textContent = extractionPath;
        OCA.Analytics.Notification.htmlDialogInitiate(t('analytics', 'Select JSON data'), () => {
            if (!extractionPath) return;
            input.value = extractionPath;
            input.dispatchEvent(new Event('input', {bubbles: true}));
            input.dispatchEvent(new Event('change', {bubbles: true}));
            OCA.Analytics.Notification.dialogClose();
        });
        const dialog = document.getElementById('analyticsDialogContainer');
        OCA.Analytics.Notification.htmlDialogUpdate(content, '');
        dialog._analyticsDialogCleanup = () => {
            controller.abort();
            previewController?.abort();
        };
        const apply = dialog.querySelector('#analyticsDialogBtnGo');
        apply.textContent = t('analytics', 'Apply selection');
        apply.disabled = true;
        previewButton.disabled = true;
        const active = () => document.getElementById('analyticsDialogContainer') === dialog;
        const update = () => {
            revision++;
            previewController?.abort();
            extractionPath = rowPath === null ? selected.join(',')
                : (selected.length ? rowPath + '{' + selected.join(',') + '}' : '');
            pathDisplay.textContent = extractionPath;
            apply.disabled = !extractionPath;
            previewButton.disabled = !extractionPath;
            preview.replaceChildren();
            status.textContent = '';
        };
        const request = async (url, payload, signal) => {
            const response = await fetch(OC.generateUrl(url), {
                method: 'POST', headers: OCA.Analytics.headers(), signal,
                body: JSON.stringify(payload)
            });
            let result;
            try { result = await response.json(); }
            catch (error) { throw new Error(t('analytics', 'The datasource request failed (HTTP {status}).', {status: response.status})); }
            if (!response.ok || (result.error && String(result.error) !== '0')) {
                throw new Error(result.error || t('analytics', 'Could not load JSON data. Check the datasource settings.'));
            }
            return result;
        };
        previewButton.addEventListener('click', async () => {
            previewController?.abort();
            previewController = new AbortController();
            const current = ++revision;
            previewButton.disabled = true;
            status.textContent = t('analytics', 'Loading');
            preview.replaceChildren();
            try {
                const result = await request('apps/analytics/data', {
                    type: Number(datasource), options: JSON.stringify({...options, path: extractionPath}), limit: 20
                }, previewController.signal);
                if (!active() || current !== revision) return;
                const table = document.createElement('table');
                // Preserve the actual legacy result, including padded columns.
                const rows = result.data || [];
                const width = Math.max(result.header?.length || 0, ...rows.map(row => row.length));
                const heading = table.createTHead().insertRow();
                for (let i = 0; i < width; i++) {
                    const cell = document.createElement('th');
                    cell.scope = 'col';
                    cell.textContent = result.header?.[i] ?? t('analytics', 'Column {number}', {number: i + 1});
                    heading.appendChild(cell);
                }
                const body = table.createTBody();
                rows.forEach(row => {
                    const tr = body.insertRow();
                    row.forEach(value => {
                        tr.insertCell().textContent = typeof value === 'string' ? value : JSON.stringify(value);
                    });
                });
                preview.appendChild(table);
                status.textContent = rows.length ? t('analytics', 'Preview of the first 20 rows.') : t('analytics', 'No sample rows available.');
            } catch (error) {
                if (active() && current === revision && error.name !== 'AbortError') status.textContent = error.message;
            } finally {
                if (active() && current === revision) {
                    previewButton.disabled = !extractionPath;
                    if (document.activeElement === document.body) previewButton.focus();
                }
            }
        });

        const action = (label, callback, enabled = true) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button analyticsSecondary';
            button.textContent = label;
            button.disabled = !enabled;
            button.addEventListener('click', callback);
            return button;
        };
        const choose = (key, parts, value, parent) => {
            const label = document.createElement('label');
            label.className = 'jsonPickerValue';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.disabled = !picker.selectable(parts);
            const path = parts.join('/');
            checkbox.checked = selected.includes(path);
            checkbox.addEventListener('change', () => {
                selected = checkbox.checked ? [...selected, path] : selected.filter(item => item !== path);
                update();
            });
            const text = document.createElement('span');
            text.textContent = key + ': ' + JSON.stringify(value)?.slice(0, 120);
            label.append(checkbox, text);
            parent.appendChild(label);
        };
        // Render at most 50 entries at once, and only expand objects on demand.
        const children = (samples, parts, parent, fields = false) => {
            const entries = new Map();
            samples.forEach(sample => {
                if (!picker.isContainer(sample)) return;
                Object.entries(sample).forEach(([key, value]) => {
                    if (!entries.has(key)) entries.set(key, []);
                    entries.get(key).push(value);
                });
            });
            const items = [...entries];
            let offset = 0;
            const more = action(t('analytics', 'Show more'), () => render());
            const render = () => {
                more.remove();
                items.slice(offset, offset + 50).forEach(([key, values]) => {
                    const path = [...parts, key];
                    const value = values.find(picker.isContainer) ?? values[0];
                    if (!picker.isContainer(value)) {
                        choose(key, path, value, parent);
                        return;
                    }
                    const details = document.createElement('details');
                    const summary = document.createElement('summary');
                    const isArray = Array.isArray(value);
                    const name = document.createElement('span');
                    name.textContent = key + ' (' + Object.keys(value).length + ')';
                    summary.appendChild(name);
                    details.appendChild(summary);
                    details.addEventListener('toggle', () => {
                        if (!details.open || details.dataset.loaded) return;
                        details.dataset.loaded = 'true';
                        if (!fields && isArray && value.some(item => picker.isContainer(item))) {
                            details.appendChild(action(t('analytics', 'Use all {name} and choose the relevant fields', {name: key}), () => showFields(value, path), picker.selectable(path)));
                        } else if (!fields && Object.values(value).every(item => !picker.isContainer(item))) {
                            details.appendChild(action(t('analytics', 'Use entries as rows'), () => {
                                rowPath = null;
                                selected = [path.join('/')];
                                update();
                                tree.querySelectorAll('input[type="checkbox"]').forEach(box => { box.checked = false; });
                            }, picker.selectable(path)));
                        }
                        if (Object.keys(value).length === 0) {
                            const empty = document.createElement('p');
                            empty.className = 'userGuidance';
                            empty.textContent = t('analytics', 'No sample rows available.');
                            details.appendChild(empty);
                        } else if (parts.length < 20) {
                            children(fields ? values : [value], path, details, fields);
                        } else {
                            const hint = document.createElement('p');
                            hint.className = 'userGuidance';
                            hint.textContent = t('analytics', 'This nesting is too deep to browse. Enter the Object path manually.');
                            details.appendChild(hint);
                        }
                    });
                    parent.appendChild(details);
                });
                offset += 50;
                if (offset < items.length) parent.appendChild(more);
            };
            render();
        };
        const showFields = (records, parts, initialFields = []) => {
            rowPath = parts.join('/');
            selected = initialFields;
            update();
            tree.replaceChildren();
            back.hidden = false;
            children(records.slice(0, 50), [], tree, true);
            back.focus();
        };
        status.textContent = t('analytics', 'Loading');
        try {
            const result = await request('apps/analytics/datasource/json/content', {
                type: Number(datasource), options: JSON.stringify(options)
            }, controller.signal);
            if (!active()) return;
            let json;
            try { json = picker.decode(result.content); }
            catch (error) { throw new Error(t('analytics', 'The datasource did not return valid JSON.')); }
            const browse = (initial = false) => {
                rowPath = null;
                selected = initial && !/[{}]/.test(extractionPath) ? extractionPath.split(',').filter(Boolean) : [];
                tree.replaceChildren();
                back.hidden = true;
                if (Array.isArray(json) && json.some(picker.isContainer)) {
                    tree.appendChild(action(t('analytics', 'Use all entries and choose the relevant fields'), () => showFields(json, [])));
                }
                children([json], [], tree);
                if (!picker.isContainer(json) || Object.keys(json).length === 0) {
                    const hint = document.createElement('p');
                    hint.className = 'userGuidance';
                    hint.textContent = t('analytics', 'No selectable fields found. You can still enter the Object path manually.');
                    tree.appendChild(hint);
                }
            };
            browse(true);
            const savedArray = extractionPath.match(/^([^{}]*)\{([^{}]+)\}$/);
            if (savedArray) {
                const parts = savedArray[1] ? savedArray[1].split('/') : [];
                const records = parts.reduce((value, key) => picker.isContainer(value) && Object.hasOwn(value, key) ? value[key] : undefined, json);
                if (Array.isArray(records)) showFields(records, parts, savedArray[2].split(',').map(field => field.trim()));
            }
            back.addEventListener('click', () => {
                browse();
                update();
                (tree.querySelector('button, summary, input') || previewButton).focus();
            });
            status.textContent = '';
            apply.disabled = !extractionPath;
            previewButton.disabled = !extractionPath;
        } catch (error) {
            if (active() && error.name !== 'AbortError') {
                status.textContent = error.message;
                status.setAttribute('role', 'alert');
            }
        }
    }
};
