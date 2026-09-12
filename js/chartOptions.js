/**
 * Analytics
 *
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: cloner */

'use strict';

OCA.Analytics = OCA.Analytics || {};
OCA.Analytics.ChartOptions = OCA.Analytics.ChartOptions || {};
Object.assign(OCA.Analytics.ChartOptions, {
    GUI_NAMESPACE: '__analytics_gui',
    GUI_VERSION: 4,
    MANAGED_PATHS: ['analyticsModel', 'scales.secondary'],

    safeParse: function (raw, fallback = {}) {
        if (raw === '' || raw === null || raw === undefined || raw === 'null') {
            return fallback;
        }

        if (typeof raw === 'string') {
            try {
                return JSON.parse(raw);
            } catch (e) {
                return fallback;
            }
        }

        if (typeof raw === 'object') {
            return raw;
        }

        return fallback;
    },

    safeStringify: function (value, fallback = '{}') {
        try {
            return JSON.stringify(value);
        } catch (e) {
            return fallback;
        }
    },

    _normalizeParsedOptions: function (value) {
        const parsed = this.safeParse(value, {});
        const options = this._isPlainObject(parsed) ? this._clone(parsed) : {};
        return this._normalizeLegacyAxes(options);
    },

    _getGuiStateFromOptions: function (options) {
        const gui = options[this.GUI_NAMESPACE];

        const state = this._isPlainObject(gui) ? this._clone(gui) : {};
        if (!state.model && typeof options.analyticsModel === 'string') {
            state.model = options.analyticsModel;
        }

        return this._ensureGuiState(state);
    },

    _setGuiStateOnOptions: function (options, guiState) {
        const normalizedOptions = this._isPlainObject(options) ? this._clone(options) : {};
        const state = this._ensureGuiState(guiState);

        normalizedOptions[this.GUI_NAMESPACE] = state;
        delete normalizedOptions.analyticsModel;

        return normalizedOptions;
    },

    parseAndNormalize: function (raw) {
        const chartOptions = this._normalizeParsedOptions(raw);
        const guiState = this._getGuiStateFromOptions(chartOptions);
        return this._setGuiStateOnOptions(chartOptions, guiState);
    },

    getGuiState: function (chartOptions) {
        const options = this._normalizeParsedOptions(chartOptions);
        return this._getGuiStateFromOptions(options);
    },

    setGuiState: function (chartOptions, guiState) {
        const options = this._normalizeParsedOptions(chartOptions);
        const existingState = this._getGuiStateFromOptions(options);
        return this._setGuiStateOnOptions(options, {...existingState, ...guiState});
    },

    compose: function (defaultOptions, chartOptions, dataOptions) {
        const defaults = this._isPlainObject(defaultOptions) ? this._clone(defaultOptions) : {};
        const normalizedChartOptions = this.parseAndNormalize(chartOptions);
        const normalizedDataOptions = this._normalizeDataOptions(dataOptions);
        const guiState = this._getGuiStateFromOptions(normalizedChartOptions);

        let customOptions = this._removeGuiNamespace(normalizedChartOptions);
        customOptions = this._stripManagedPaths(customOptions);

        const guiPatch = this._buildGuiPatch(guiState, normalizedDataOptions);

        let composed = this._deepMerge(this._clone(defaults), customOptions);
        composed = this._deepMerge(composed, guiPatch);

        return composed;
    },

    toSidebarEditorValue: function (chartOptions) {
        const normalizedChartOptions = this.parseAndNormalize(chartOptions);
        let customOptions = this._removeGuiNamespace(normalizedChartOptions);
        customOptions = this._stripManagedPaths(customOptions);

        if (this._isEmptyObject(customOptions)) {
            return '';
        }

        return this.safeStringify(customOptions, '');
    },

    fromSidebarEditorValue: function (editorText, existingChartOptions) {
        const normalizedExistingChartOptions = this.parseAndNormalize(existingChartOptions);
        const guiState = this.getGuiState(normalizedExistingChartOptions);

        let customOptions = {};
        if (typeof editorText === 'string' && editorText.trim() !== '') {
            customOptions = this.safeParse(editorText, null);
            if (!this._isPlainObject(customOptions)) {
                throw new Error('Incorrect chart options');
            }
            customOptions = this._normalizeLegacyAxes(customOptions);
        }

        customOptions = this._removeGuiNamespace(customOptions);
        customOptions = this._stripManagedPaths(customOptions);

        return this.setGuiState(customOptions, guiState);
    },

    _isPlainObject: function (value) {
        return value !== null
            && typeof value === 'object'
            && !Array.isArray(value);
    },

    _clone: function (value) {
        if (value === null || value === undefined) {
            return value;
        }

        if (typeof value === 'function') {
            return value;
        }

        if (Array.isArray(value)) {
            return value.map((entry) => this._clone(entry));
        }

        if (this._isPlainObject(value)) {
            const cloned = {};
            Object.keys(value).forEach((key) => {
                cloned[key] = this._clone(value[key]);
            });
            return cloned;
        }

        return value;
    },

    _deepMerge: function (target, source) {
        if (typeof cloner !== 'undefined' && cloner?.deep?.merge) {
            return cloner.deep.merge(target, source);
        }

        if (!this._isPlainObject(source)) {
            return target;
        }

        Object.keys(source).forEach((key) => {
            const sourceValue = source[key];
            const targetValue = target[key];

            if (this._isPlainObject(sourceValue) && this._isPlainObject(targetValue)) {
                target[key] = this._deepMerge(targetValue, sourceValue);
            } else if (this._isPlainObject(sourceValue)) {
                target[key] = this._deepMerge({}, sourceValue);
            } else if (Array.isArray(sourceValue)) {
                target[key] = this._clone(sourceValue);
            } else {
                target[key] = sourceValue;
            }
        });

        return target;
    },

    _normalizeLegacyAxes: function (chartOptions) {
        return this._renameKeyRecursively(chartOptions, 'xAxes', 'x');
    },

    _renameKeyRecursively: function (value, fromKey, toKey) {
        if (Array.isArray(value)) {
            return value.map((item) => this._renameKeyRecursively(item, fromKey, toKey));
        }

        if (!this._isPlainObject(value)) {
            return value;
        }

        const renamed = {};
        Object.keys(value).forEach((key) => {
            const newKey = key === fromKey ? toKey : key;
            renamed[newKey] = this._renameKeyRecursively(value[key], fromKey, toKey);
        });

        return renamed;
    },

    _setByPath: function (obj, path, value) {
        const segments = path.split('.');
        let current = obj;
        for (let i = 0; i < segments.length - 1; i++) {
            const segment = segments[i];
            if (!this._isPlainObject(current[segment])) {
                current[segment] = {};
            }
            current = current[segment];
        }
        current[segments[segments.length - 1]] = value;
    },

    _unsetByPath: function (obj, path) {
        const segments = path.split('.');
        let current = obj;
        for (let i = 0; i < segments.length - 1; i++) {
            const segment = segments[i];
            if (!this._isPlainObject(current[segment])) {
                return;
            }
            current = current[segment];
        }
        delete current[segments[segments.length - 1]];
    },

    _pruneEmptyObjects: function (value) {
        if (Array.isArray(value)) {
            return value.map((entry) => this._pruneEmptyObjects(entry));
        }

        if (!this._isPlainObject(value)) {
            return value;
        }

        const pruned = {};
        Object.keys(value).forEach((key) => {
            const cleaned = this._pruneEmptyObjects(value[key]);
            if (this._isPlainObject(cleaned) && this._isEmptyObject(cleaned)) {
                return;
            }
            pruned[key] = cleaned;
        });

        return pruned;
    },

    _stripManagedPaths: function (chartOptions) {
        const stripped = this._isPlainObject(chartOptions) ? this._clone(chartOptions) : {};
        this.MANAGED_PATHS.forEach((path) => this._unsetByPath(stripped, path));
        return this._pruneEmptyObjects(stripped);
    },

    _removeGuiNamespace: function (chartOptions) {
        const normalized = this._isPlainObject(chartOptions) ? this._clone(chartOptions) : {};
        delete normalized[this.GUI_NAMESPACE];
        return normalized;
    },

    _normalizeModel: function (model) {
        const validModels = ['kpiModel', 'accountModel', 'timeSeriesModel'];
        return validModels.includes(model) ? model : 'kpiModel';
    },

    _normalizeDoughnutLabelStyle: function (style) {
        const validStyles = ['percentage', 'absolute'];
        return validStyles.includes(style) ? style : 'percentage';
    },

    _ensureGuiState: function (guiState) {
        const state = this._isPlainObject(guiState) ? this._clone(guiState) : {};
        state.version = this.GUI_VERSION;
        state.model = this._normalizeModel(state.model);
        state.doughnutLabelStyle = this._normalizeDoughnutLabelStyle(state.doughnutLabelStyle);
        state.aggregationFunctions = this._normalizeAggregationFunctions(state.aggregationFunctions);
        const columnMapping = this._normalizeColumnMapping(state.columnMapping ?? state.flexibleFields);
        if (columnMapping) {
            state.columnMapping = columnMapping;
        } else {
            delete state.columnMapping;
        }
        delete state.flexibleFields;
        return state;
    },

    _normalizeColumnId: function (value) {
        if (Number.isInteger(value) && value >= 0) {
            return value;
        }
        if (typeof value === 'string' && /^c_[1-9][0-9]*$/.test(value)) {
            return value;
        }
        return null;
    },

    _normalizeColumnMapping: function (fields) {
        if (!this._isPlainObject(fields)) {
            return null;
        }

        const category = this._normalizeColumnId(fields.category);
        if (category === null) {
            return null;
        }
        const columns = (value) => Array.isArray(value)
            ? [...new Set(value.map(column => this._normalizeColumnId(column))
                .filter(column => column !== null))]
            : [];
        const series = columns(fields.series).filter(column => column !== category);
        const measures = columns(fields.measures).filter(column => column !== category);
        if (measures.length === 0) {
            return null;
        }

        return {category, series, measures};
    },

    columnDescriptors: function (reportData) {
        const header = Array.isArray(reportData?.header) ? reportData.header : [];
        const references = Array.isArray(reportData?.columnRefs) ? reportData.columnRefs : [];
        return header.map((label, index) => ({
            id: typeof references[index] === 'string' && /^c_[1-9][0-9]*$/.test(references[index])
                ? references[index]
                : index,
            index,
            label: String(label ?? ''),
        }));
    },

    encodeColumnId: function (columnId) {
        return JSON.stringify(columnId);
    },

    decodeColumnId: function (value) {
        try {
            return this._normalizeColumnId(JSON.parse(value));
        } catch (error) {
            return null;
        }
    },

    resolveColumnIndex: function (reportData, columnId) {
        const normalized = this._normalizeColumnId(columnId);
        if (normalized === null) {
            return -1;
        }
        if (Number.isInteger(normalized)) {
            return normalized < (reportData?.header?.length || 0) ? normalized : -1;
        }
        return Array.isArray(reportData?.columnRefs) ? reportData.columnRefs.indexOf(normalized) : -1;
    },

    /** Older row-based reports use column positions, even when dimensions are numeric. */
    usesPositionalColumnMapping: function (reportData, model = 'kpiModel') {
        if (model !== 'kpiModel') return false;
        const columns = this.columnDescriptors(reportData);
        const roles = new Map((Array.isArray(reportData?.columns) ? reportData.columns : [])
            .map(column => [column.ref, column.role]));
        return !columns.length || !columns.every(column => ['dimension', 'measure'].includes(roles.get(column.id)));
    },

    defaultColumnMapping: function (reportData, model = 'kpiModel') {
        const columns = this.columnDescriptors(reportData);
        if (columns.length < 2) {
            return null;
        }
        if (model === 'accountModel' || model === 'timeSeriesModel') {
            return {
                category: columns[0].id,
                series: [],
                measures: columns.slice(1).map(column => column.id),
            };
        }
        if (this.usesPositionalColumnMapping(reportData, model)) {
            // Match the original row.slice(-3) interpretation for all untyped
            // sources. Numeric years/IDs must not become additional measures.
            return {
                category: columns[columns.length - 2].id,
                series: columns.length >= 3 ? [columns[columns.length - 3].id] : [],
                measures: [columns[columns.length - 1].id],
            };
        }
        const roles = new Map(reportData.columns.map(column => [column.ref, column.role]));
        const rows = Array.isArray(reportData?.data) ? reportData.data : [];
        const valuesFor = (column) => rows.map(row => row?.[column.index])
            .filter(value => value !== null && value !== undefined && value !== '');
        const measureColumns = columns.filter(column => roles.get(column.id) === 'measure');
        const measureIds = new Set(measureColumns.map(column => column.id));
        const dimensionColumns = columns.filter(column => !measureIds.has(column.id));
        const dateColumn = dimensionColumns.find(column => {
            const values = valuesFor(column);
            return values.length > 0 && values.every(value => typeof value === 'string'
                && !Number.isFinite(Number(value)) && Number.isFinite(Date.parse(value)));
        });
        const categoryColumn = dateColumn || dimensionColumns[dimensionColumns.length - 1] || columns[0];
        return {
            category: categoryColumn.id,
            series: dimensionColumns.filter(column => column.id !== categoryColumn.id).map(column => column.id),
            measures: measureColumns.filter(column => column.id !== categoryColumn.id).map(column => column.id),
        };
    },

    /** Suggest field roles without changing the defaults of existing reports. */
    suggestColumnMapping: function (reportData, model = 'kpiModel') {
        const fallback = this.defaultColumnMapping(reportData, model);
        if (!fallback || model !== 'kpiModel') return fallback;
        const columns = this.columnDescriptors(reportData);
        const metadata = new Map((Array.isArray(reportData.columns) ? reportData.columns : []).map(column => [column.ref, column]));
        const rows = (reportData.data || []).slice(0, 100);
        const valuesFor = column => rows.map(row => row?.[column.index])
            .filter(value => value !== null && value !== undefined && value !== '');
        const measures = columns.filter(column => {
            const role = metadata.get(column.id)?.role;
            if (role) return role === 'measure';
            // A numeric year or identifier is normally a grouping field, not a quantity.
            if (/(?:^|[\s_-])(year|jahr|année|año|id)(?:$|[\s_-])/iu.test(column.label)) return false;
            const values = valuesFor(column);
            return values.length && values.every(value => Number.isFinite(Number(value)));
        });
        if (!measures.length) return fallback;
        const dimensions = columns.filter(column => !measures.includes(column));
        if (!dimensions.length) return fallback;
        const date = dimensions.find(column => ['date', 'datetime'].includes(metadata.get(column.id)?.type)
            || (valuesFor(column).length && valuesFor(column).every(value => typeof value === 'string'
                && !Number.isFinite(Number(value)) && Number.isFinite(Date.parse(value)))));
        const category = date || dimensions.find(column => !/(year|jahr|année|año)/iu.test(column.label)) || dimensions[0];
        return {
            category: category.id,
            series: dimensions.filter(column => column !== category).map(column => column.id),
            measures: measures.map(column => column.id),
        };
    },

    columnMapping: function (reportData, model = 'kpiModel', configured = null) {
        const fallback = this.defaultColumnMapping(reportData, model);
        if (!fallback) {
            return null;
        }
        const normalized = this._normalizeColumnMapping(configured);
        if (!normalized) {
            return fallback;
        }
        const available = new Set(this.columnDescriptors(reportData).map(column => column.id));
        const category = available.has(normalized.category) ? normalized.category : fallback.category;
        const series = normalized.series.filter(column => available.has(column) && column !== category);
        const measures = normalized.measures.filter(column => available.has(column) && column !== category);
        return {
            category,
            series: model === 'kpiModel' ? series : [],
            measures: measures.length ? measures : fallback.measures,
        };
    },

    _normalizeAggregationFunctions: function (functions) {
        if (!Array.isArray(functions)) {
            return [];
        }

        const seen = new Set();
        return functions.reduce((normalized, selection) => {
            if (!this._isPlainObject(selection)
                || !['aggregate', 'disaggregate'].includes(selection.mode)
                || !Number.isInteger(selection.sourceIndex)
                || selection.sourceIndex < 0) {
                return normalized;
            }

            const key = selection.mode + ':' + selection.sourceIndex;
            if (seen.has(key)) {
                return normalized;
            }
            seen.add(key);

            const value = {mode: selection.mode, sourceIndex: selection.sourceIndex};
            if (selection.hidden === true) {
                value.hidden = true;
            }
            normalized.push(value);
            return normalized;
        }, []);
    },

    _isSecondaryAxisRequired: function (dataOptions, guiState) {
        if (this._normalizeAggregationFunctions(guiState?.aggregationFunctions).length > 0) {
            return true;
        }

        if (!Array.isArray(dataOptions)) {
            return false;
        }

        return dataOptions.some((option) =>
            this._isPlainObject(option) && option.yAxisID === 'secondary'
        );
    },

    _buildGuiPatch: function (guiState, dataOptions) {
        const patch = {};
        const model = this._normalizeModel(guiState.model);

        if (model !== 'kpiModel') {
            patch.analyticsModel = model;
        }

        this._setByPath(patch, 'scales.secondary.display', this._isSecondaryAxisRequired(dataOptions, guiState));
        return patch;
    },

    _normalizeDataOptions: function (dataOptions) {
        if (Array.isArray(dataOptions)) {
            return dataOptions;
        }

        const parsed = this.safeParse(dataOptions, []);
        return Array.isArray(parsed) ? parsed : [];
    },

    _isEmptyObject: function (value) {
        return this._isPlainObject(value) && Object.keys(value).length === 0;
    },
});
