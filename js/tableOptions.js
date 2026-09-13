/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
'use strict';

/** Draft-only table editor. The preview never joins the report's table registry. */
OCA.Analytics.TableOptions = {
    active: null,

    mount: function () {
        const dialog = document.getElementById('analyticsDialogContainer');
        const report = OCA.Analytics.currentReportData;
        const state = this.active = {
            dialog, report, draft: structuredClone(report.options.tableoptions || {}),
            preview: null, timer: null, selected: null, columns: [], closed: false,
        };
        const field = id => dialog.querySelector('#' + id);
        state.field = field;
        dialog.classList.add('analyticsDialog--tableOptions', 'analyticsDialog--visualizationOptions');
        dialog.querySelector('.analyticsEnhancedDialogLayout').append(field('tableOptionsPreview'));
        document.getElementById('analyticsDialogBtnGo').textContent = t('analytics', 'Apply');
        document.getElementById('analyticsDialogBtnLeading').textContent = t('analytics', 'Reset to defaults');
        const cleanup = dialog._analyticsDialogCleanup;
        dialog._analyticsDialogCleanup = () => {
            state.closed = true;
            clearTimeout(state.timer);
            state.observer.disconnect();
            state.preview?.destroy();
            cleanup?.();
            if (this.active === state) this.active = null;
        };
        const layout = state.draft.layout;
        state.mode = layout?.columns?.length || layout?.measures?.length ? 'pivot' : 'table';
        field(state.mode === 'pivot' ? 'tableModePivot' : 'tableModeList').checked = true;
        field('tableDensity').value = state.draft.density || (state.draft.compactDisplay ? 'compact' : 'comfortable');
        field('compactDisplayOption').closest('.tableOptionsSettingsRow').hidden = true;
        field('tableShowHeader').checked = state.draft.showHeader ?? !state.draft.compactDisplay;
        field('tableStriped').checked = state.draft.striped !== false;
        const length = state.draft.length || 10;
        if (![...field('tablePageLength').options].some(option => Number(option.value) === length)) {
            field('tablePageLength').add(new Option(String(length), String(length)));
        }
        field('tablePageLength').value = length;

        dialog.querySelectorAll('[name="tableMode"]').forEach(input => input.addEventListener('change', () => {
            state.mode = input.value;
            if (state.mode === 'table') {
                for (const role of ['columns', 'measures']) {
                    field(role).querySelectorAll('.draggable').forEach(item => field('rows').append(item));
                }
            } else if (!field('columns').querySelector('.draggable') && !field('measures').querySelector('.draggable')) {
                const items = [...field('rows').querySelectorAll('.draggable')];
                if (items.length >= 3) {
                    field('columns').append(items[1]);
                    field('measures').append(items.at(-1));
                    items.slice(2, -1).forEach(item => field('notRequired').append(item));
                }
            }
            this.layoutChanged();
        }));
        state.observer = new MutationObserver(() => this.layoutChanged());
        ['rows', 'columns', 'measures', 'notRequired'].forEach(role => state.observer.observe(field(role), {childList: true}));
        dialog.addEventListener('change', event => {
            if (event.target.id === 'compactDisplayOption') {
                field('tableShowHeader').checked = !event.target.checked;
            }
            this.schedule();
        });
        const bindings = {
            tableColumnTitle: 'title', tableColumnFormat: 'format', tableColumnCurrency: 'currency',
            tableColumnDecimals: 'decimals', tableColumnAlign: 'align', tableColumnWidth: 'width',
            tableColumnWrap: 'wrap', tableHighlightBelow: 'highlightBelow',
        };
        Object.entries(bindings).forEach(([id, key]) => field(id).addEventListener('input', () => {
            if (!state.selected) return;
            const formats = state.draft.columnFormats ||= [];
            let format = formats.find(item => item.reference === state.selected);
            if (!format) formats.push(format = {reference: state.selected});
            format[key] = key === 'wrap' ? field(id).checked : field(id).value;
            if (key === 'currency') format.currency = format.currency.toUpperCase();
            this.updateFormatVisibility();
            this.schedule();
        }));
        ['tableColumnSelect', 'tableHighlightColumn'].forEach(id => field(id).addEventListener('change', () => this.selectColumn(field(id).value)));
        field('tableColumnReset').addEventListener('click', () => {
            state.draft.columnFormats = (state.draft.columnFormats || []).filter(item => item.reference !== state.selected);
            this.selectColumn(state.selected);
            this.schedule();
        });
        this.layoutChanged();
    },

    schedule: function () {
        const state = this.active;
        if (!state || state.closed) return;
        clearTimeout(state.timer);
        state.timer = setTimeout(() => this.renderPreview(), 100);
    },

    layoutChanged: function () {
        const state = this.active;
        if (!state || state.closed) return;
        const {field} = state;
        OCA.Analytics.Filter.Drag.managePlaceholders();
        state.dialog.classList.toggle('analyticsTableModeList', state.mode === 'table');
        field('tableOptionsLayoutRows').querySelector('p').textContent = state.mode === 'table' ? t('analytics', 'Visible columns') : t('analytics', 'Rows');
        state.dialog.querySelectorAll('.columnSection .draggable').forEach(item => {
            let actions = item.querySelector('.tableOptionsFieldActions');
            if (!actions) {
                actions = document.createElement('div');
                actions.className = 'tableOptionsFieldActions chartColumnSelectionActions';
                for (const [direction, label] of [[-1, t('analytics', 'Move up')], [1, t('analytics', 'Move down')]]) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = direction === -1 ? '↑' : '↓';
                    button.setAttribute('aria-label', label);
                    button.addEventListener('click', () => {
                        const items = [...item.parentElement.querySelectorAll('.draggable')];
                        const neighbor = items[items.indexOf(item) + direction];
                        if (neighbor) item.parentElement.insertBefore(direction === -1 ? item : neighbor, direction === -1 ? neighbor : item);
                    });
                    actions.append(button);
                }
                item.append(actions);
            }
            const siblings = [...item.parentElement.querySelectorAll('.draggable')];
            const buttons = actions.querySelectorAll('button');
            buttons[0].disabled = siblings[0] === item;
            buttons[1].disabled = siblings.at(-1) === item;
        });
        OCA.Analytics.Filter.refreshCalculatedColumnSourcesFromLayout();
        this.schedule();
    },

    calculationsChanged: function (deletedIndex) {
        const state = this.active;
        if (!state) return;
        if (deletedIndex !== null && deletedIndex !== undefined) {
            state.draft.columnFormats = (state.draft.columnFormats || []).filter(format => format.reference !== 'calculation:' + deletedIndex)
                .map(format => {
                    const match = /^calculation:(\d+)$/.exec(format.reference);
                    return match && Number(match[1]) > deletedIndex ? {...format, reference: 'calculation:' + (Number(match[1]) - 1)} : format;
                });
        }
        this.schedule();
    },

    read: function () {
        const state = this.active;
        if (!state) return null;
        const {field} = state;
        const count = role => field(role).querySelectorAll('.draggable').length;
        let error = '';
        if (state.mode === 'table' && count('rows') === 0) error = t('analytics', 'Select at least one visible column.');
        if (state.mode === 'pivot' && ['rows', 'columns', 'measures'].some(role => count(role) !== 1)) {
            error = t('analytics', 'Select exactly one field each in Rows, Columns, and Values.');
        }
        // The current renderer pivots one value per cell; never silently discard duplicate cells.
        if (!error && state.mode === 'pivot') {
            const index = role => Number(field(role).querySelector('.draggable').id.replace('column-', ''));
            const seen = new Set();
            for (const row of state.report.data || []) {
                const key = JSON.stringify([String(row[index('rows')]), String(row[index('columns')])]);
                if (seen.has(key)) { error = t('analytics', 'Several report rows map to the same pivot cell. Aggregate or filter the report first.'); break; }
                seen.add(key);
            }
        }
        field('tableOptionsLayoutError').textContent = error;
        field('tableOptionsLayoutError').hidden = !error;
        if (error) return null;
        const options = OCA.Analytics.Filter.readTableOptionsDialog(state.draft);
        if (!options) return null;
        options.density = field('tableDensity').value;
        delete options.compactDisplay;
        options.showHeader = field('tableShowHeader').checked;
        options.striped = field('tableStriped').checked;
        options.length = Number(field('tablePageLength').value);
        return options;
    },

    renderPreview: function () {
        const state = this.active;
        if (!state || state.closed) return;
        const {field} = state;
        const options = this.read();
        const message = field('tableOptionsPreviewError');
        state.preview?.destroy();
        state.preview = null;
        field('tableOptionsPreviewTable').replaceChildren();
        if (!options) {
            message.textContent = field('tableOptionsLayoutError').textContent;
            message.hidden = false;
            field('tableOptionsPreviewStatus').textContent = '';
            return;
        }
        const invalidField = [...state.dialog.querySelectorAll('input[type="number"], input[pattern]')]
            .find(input => input.offsetParent !== null && !input.validity.valid);
        if (invalidField) {
            message.textContent = t('analytics', 'Check the column settings.');
            message.hidden = false;
            return;
        }
        try {
            state.preview = OCA.Analytics.Visualization.buildDataTable(field('tableOptionsPreviewTable'), {
                ...state.report, options: {...state.report.options, tableoptions: options},
            }, true, undefined, {preview: true});
            state.columns = state.preview.settings()[0].aoColumns;
            this.updateColumnChoices();
            state.preview.columns().every(function () {
                const column = state.columns[this.index()];
                const th = this.header();
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'tableOptionsPreviewColumn';
                button.dataset.columnRef = column.analyticsReference;
                button.textContent = OCA.Analytics.Visualization.unescapeHtml(column.sTitle || column.title || '');
                button.addEventListener('click', event => {
                    event.stopPropagation();
                    OCA.Analytics.TableOptions.selectColumn(column.analyticsReference, true);
                });
                th.replaceChildren(button);
            });
            this.updatePreviewSelection();
            const info = state.preview.page.info();
            field('tableOptionsPreviewStatus').textContent = t('analytics', 'Preview: {shown} of {total} table rows. Current report filters apply.', {
                shown: info.end.toLocaleString(), total: info.recordsTotal.toLocaleString(),
            });
            message.textContent = state.report.data?.length ? '' : t('analytics', 'No data to preview with the current filters.');
            message.hidden = !!state.report.data?.length;
        } catch (error) {
            state.preview?.destroy();
            state.preview = null;
            message.textContent = t('analytics', 'This table cannot be previewed. Check the layout and column settings.');
            message.hidden = false;
            console.error('Table preview failed', error);
        }
    },

    updatePreviewSelection: function () {
        const state = this.active;
        if (!state) return;
        state.field('tableOptionsPreviewTable').querySelectorAll('th').forEach(th => {
            const button = th.querySelector('.tableOptionsPreviewColumn');
            const selected = button?.dataset.columnRef === state.selected;
            th.classList.toggle('tableOptionsSelectedColumn', selected);
            button?.setAttribute('aria-pressed', String(selected));
        });
    },

    updateColumnChoices: function () {
        const state = this.active;
        const {field} = state;
        const options = () => state.columns.map(column => new Option(column.analyticsLabel || OCA.Analytics.Visualization.unescapeHtml(column.sTitle), column.analyticsReference));
        for (const id of ['tableColumnSelect', 'tableHighlightColumn']) field(id).replaceChildren(...options());
        if (!state.columns.some(column => column.analyticsReference === state.selected)) {
            this.selectColumn(state.columns[0]?.analyticsReference);
        } else {
            field('tableColumnSelect').value = state.selected;
            field('tableHighlightColumn').value = state.selected;
        }
    },

    selectColumn: function (reference, navigate = false) {
        const state = this.active;
        if (!state) return;
        const column = state.columns.find(column => column.analyticsReference === reference);
        if (!column) return;
        state.selected = reference;
        const format = OCA.Analytics.Visualization.getTableColumnFormat(state.draft, reference) || {};
        const values = {
            tableColumnSelect: reference, tableHighlightColumn: reference,
            tableColumnTitle: format.title ?? column.analyticsLabel ?? '',
            tableColumnFormat: format.format || 'auto', tableColumnCurrency: format.currency || 'EUR',
            tableColumnDecimals: format.decimals ?? '', tableColumnAlign: format.align || 'auto',
            tableColumnWidth: format.width || '', tableHighlightBelow: format.highlightBelow ?? '',
        };
        Object.entries(values).forEach(([id, value]) => { state.field(id).value = value; });
        state.field('tableColumnWrap').checked = format.wrap === true;
        this.updateFormatVisibility();
        this.updatePreviewSelection();
        if (navigate) {
            state.dialog.querySelector('[data-target="tableColumnSection"]')?.click();
            state.field('tableColumnTitle').focus({preventScroll: true});
        }
    },

    updateFormatVisibility: function () {
        const state = this.active;
        const format = state.field('tableColumnFormat').value;
        state.field('tableColumnCurrency').closest('.tableOptionsSettingsRow').hidden = format !== 'currency';
        state.field('tableColumnDecimals').closest('.tableOptionsSettingsRow').hidden = format === 'text';
        state.field('tableColumnFormatHint').textContent = format === 'percent'
            ? t('analytics', 'Fractional values are displayed as percentages: 0.25 becomes 25%. Calculated percentages keep their existing scale.') : '';
    },

    reset: function () {
        const state = this.active;
        if (!state) return;
        state.draft = {};
        state.mode = 'table';
        state.field('tableModeList').checked = true;
        state.field('tablePageLength').value = '10';
        state.field('tableDensity').value = 'comfortable';
        for (const id of ['totalOption', 'compactDisplayOption']) state.field(id).checked = false;
        state.field('tableStriped').checked = true;
        for (const id of ['formatLocalesOption', 'tableShowHeader']) state.field(id).checked = true;
        [...state.dialog.querySelectorAll('.columnSection .draggable')]
            .sort((a, b) => Number(a.id.replace('column-', '')) - Number(b.id.replace('column-', '')))
            .forEach(item => state.field('rows').append(item));
        state.field('tableOptionsCalculatedColumns').value = '';
        OCA.Analytics.Filter.initializeCalculatedColumnsDialog();
        this.selectColumn(state.selected);
        this.layoutChanged();
    },

    apply: function () {
        const state = this.active;
        if (!state) return;
        const options = this.read();
        if (!options) return;
        for (const input of state.dialog.querySelectorAll('input[type="number"], input[pattern]')) {
            if (input.offsetParent !== null && !input.reportValidity()) return;
        }
        state.report.options.tableoptions = options;
        OCA.Analytics.unsavedChanges = true;
        OCA.Analytics.Notification.dialogClose();
        OCA.Analytics.Report.Backend.getData();
    },
};
