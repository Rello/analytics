/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: OC */
/** global: t */
'use strict';

OCA.Analytics = OCA.Analytics || {};

/**
 * Shared client-side contract helpers for flexible datasets.
 *
 * Stable column references are deliberately kept separate from matrix indexes:
 * references are persisted and sent to the server, while indexes are resolved
 * from each response's columnRefs array only at the rendering boundary.
 */
OCA.Analytics.Flexible = {
    STORAGE_MODE: 'flexible_shared',
    schemaVersion: 1,

    isFlexible: function (value) {
        return value?.storageMode === this.STORAGE_MODE || value?.storage_mode === this.STORAGE_MODE;
    },

    columns: function (value) {
        return Array.isArray(value?.columns)
            ? [...value.columns].sort((a, b) => Number(a.position) - Number(b.position))
            : [];
    },

    dimensions: function (value) {
        return this.columns(value).filter(column => column.role === 'dimension');
    },

    measures: function (value) {
        return this.columns(value).filter(column => column.role === 'measure');
    },

    column: function (value, reference) {
        return this.columns(value).find(column => column.ref === reference) || null;
    },

    indexForReference: function (response, reference) {
        if (!Array.isArray(response?.columnRefs)) {
            return -1;
        }
        return response.columnRefs.indexOf(reference);
    },

    referenceForIndex: function (response, index) {
        return Array.isArray(response?.columnRefs) ? response.columnRefs[Number(index)] ?? null : null;
    },

    parseDataOptions: function (value) {
        let parsed = value;
        if (typeof parsed === 'string') {
            try {
                parsed = JSON.parse(parsed || '[]');
            } catch (error) {
                return [];
            }
        }
        return parsed && typeof parsed === 'object' ? parsed : [];
    },

    seriesOptions: function (value) {
        const parsed = this.parseDataOptions(value);
        if (Array.isArray(parsed)) {
            return parsed;
        }
        return Array.isArray(parsed.series) ? parsed.series : [];
    },

    withSeriesOptions: function (value, series) {
        const parsed = this.parseDataOptions(value);
        if (Array.isArray(parsed)) {
            return series;
        }
        const result = {...parsed};
        if (series.length) {
            result.series = series;
        } else {
            delete result.series;
        }
        return result;
    },

    resolveLayout: function (response, layout) {
        if (!layout || typeof layout !== 'object') return layout;
        return Object.fromEntries(Object.entries(layout).map(([section, items]) => [section,
            Array.isArray(items) ? items.map(item => {
                if (typeof item === 'string' && /^c_[1-9][0-9]*$/.test(item)) {
                    return this.indexForReference(response, item);
                }
                if (typeof item === 'number' && Number.isInteger(item)) {
                    return item;
                }
                if (typeof item === 'string' && /^[0-9]+$/.test(item)) {
                    return Number(item);
                }
                return -1;
            }).filter(index => Number.isInteger(index) && index >= 0) : []
        ]));
    },

    errorMessage: function (payload, fallback) {
        if (typeof payload === 'string' && payload !== '') {
            return payload;
        }
        if (typeof payload?.error === 'string' && payload.error !== '') {
            return payload.error;
        }
        if (typeof payload?.error?.message === 'string' && payload.error.message !== '') {
            return payload.error.message;
        }
        if (typeof payload?.message === 'string' && payload.message !== '') {
            return payload.message;
        }
        return fallback;
    },

    request: async function (url, options, fallback) {
        const response = await fetch(url, options);
        const payload = await response.json().catch(() => null);
        if (!response.ok) {
            const error = new Error(this.errorMessage(payload, fallback));
            error.code = payload?.error?.code || null;
            error.details = payload?.error?.details || {};
            error.status = response.status;
            throw error;
        }
        return payload;
    },

    inputType: function (column) {
        if (column.type === 'date') {
            return 'date';
        }
        if (column.type === 'datetime') {
            return 'datetime-local';
        }
        if (column.type === 'boolean') {
            return 'checkbox';
        }
        return 'text';
    },

    valueFromInput: function (input, column) {
        if (column.type === 'boolean') {
            if (input.tagName === 'SELECT') {
                return input.value === '' ? null : input.value === 'true';
            }
            return input.checked;
        }
        return input.value === '' && column.nullable ? null : input.value;
    },

    setInputValue: function (input, column, value) {
        if (column.type === 'boolean') {
            const booleanValue = value === true || value === 1 || value === '1' || value === 'true';
            if (input.tagName === 'SELECT') {
                input.value = value === null || value === undefined || value === '' ? '' : String(booleanValue);
            } else {
                input.checked = booleanValue;
            }
        } else if (column.type === 'datetime' && typeof value === 'string') {
            input.value = value.replace(/Z$/, '').substring(0, 16);
        } else {
            input.value = value ?? '';
        }
    },

    sourceHeader: function (value, delimiter) {
        return String(value || '') === ''
            ? []
            : String(value).split(delimiter).map(item => item.trim());
    },

    buildMapping: function (dataset, sourceHeader, container) {
        const entries = [];
        container.querySelectorAll('[data-flexible-column-ref]').forEach(select => {
            if (select.value !== '') {
                entries.push({column: select.dataset.flexibleColumnRef, sourceIndex: Number(select.value)});
            }
        });
        return {
            schemaVersion: this.schemaVersion,
            sourceHeader: sourceHeader.map(String),
            columns: entries,
        };
    },

    parseMapping: function (value) {
        if (value && typeof value === 'object') {
            return value;
        }
        try {
            return JSON.parse(value || 'null');
        } catch (error) {
            return null;
        }
    },

    suggestMapping: function (dataset, sourceHeader, existingMapping = null) {
        const existing = this.parseMapping(existingMapping);
        const result = new Map();
        if (existing && Array.isArray(existing.columns)
            && JSON.stringify(existing.sourceHeader || []) === JSON.stringify(sourceHeader)) {
            existing.columns.forEach(item => result.set(item.column, Number(item.sourceIndex)));
            return result;
        }
        const used = new Set();
        this.columns(dataset).forEach((column, position) => {
            let index = sourceHeader.findIndex((header, candidate) => !used.has(candidate)
                && String(header).trim().toLocaleLowerCase() === String(column.name).trim().toLocaleLowerCase());
            if (index < 0 && position < sourceHeader.length && !used.has(position)) {
                index = position;
            }
            if (index >= 0) {
                result.set(column.ref, index);
                used.add(index);
            }
        });
        return result;
    },

    renderMappingEditor: function (container, dataset, sourceHeader, existingMapping = null, previewRow = []) {
        container.replaceChildren();
        const heading = document.createElement('h4');
        heading.textContent = t('analytics', 'Source mapping');
        container.appendChild(heading);
        if (!sourceHeader.length) {
            const message = document.createElement('p');
            message.className = 'userGuidance';
            message.textContent = t('analytics', 'Run a test load or enter a source header to configure the mapping.');
            container.appendChild(message);
            return;
        }
        const suggestions = this.suggestMapping(dataset, sourceHeader, existingMapping);
        this.columns(dataset).forEach(column => {
            const row = document.createElement('label');
            row.className = 'flexibleMappingRow';
            const name = document.createElement('span');
            name.textContent = column.name + (column.nullable ? '' : ' *');
            const select = document.createElement('select');
            select.className = 'sidebarInput';
            select.dataset.flexibleColumnRef = column.ref;
            select.add(new Option(column.nullable ? t('analytics', 'Not mapped') : t('analytics', 'Please select'), ''));
            sourceHeader.forEach((header, index) => {
                const preview = previewRow[index];
                const label = preview === undefined || preview === null || preview === ''
                    ? String(header)
                    : String(header) + ' — ' + String(preview);
                select.add(new Option(label, String(index)));
            });
            if (suggestions.has(column.ref)) {
                select.value = String(suggestions.get(column.ref));
            }
            row.append(name, select);
            container.appendChild(row);
        });
        const ignored = document.createElement('p');
        ignored.className = 'userGuidance flexibleIgnoredColumns';
        container.appendChild(ignored);
        const refreshIgnored = () => {
            const used = new Set([...container.querySelectorAll('[data-flexible-column-ref]')]
                .map(select => select.value).filter(value => value !== '').map(Number));
            const names = sourceHeader.filter((header, index) => !used.has(index));
            ignored.textContent = names.length
                ? t('analytics', 'Ignored source columns') + ': ' + names.join(', ')
                : '';
        };
        container.querySelectorAll('select').forEach(select => select.addEventListener('change', refreshIgnored));
        refreshIgnored();
    },
};
