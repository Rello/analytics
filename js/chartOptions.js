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
    GUI_VERSION: 2,
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

    parseAndNormalize: function (raw) {
        let chartOptions = this.safeParse(raw, {});
        chartOptions = this._isPlainObject(chartOptions) ? this._clone(chartOptions) : {};
        chartOptions = this._normalizeLegacyAxes(chartOptions);

        const guiState = this.getGuiState(chartOptions);
        delete chartOptions.analyticsModel;
        chartOptions = this.setGuiState(chartOptions, guiState);

        return chartOptions;
    },

    getGuiState: function (chartOptions) {
        const parsed = this.safeParse(chartOptions, {});
        const options = this._isPlainObject(parsed) ? parsed : {};
        const gui = options[this.GUI_NAMESPACE];

        const state = this._isPlainObject(gui) ? this._clone(gui) : {};
        if (!state.model && typeof options.analyticsModel === 'string') {
            state.model = options.analyticsModel;
        }

        return this._ensureGuiState(state);
    },

    setGuiState: function (chartOptions, guiState) {
        const parsed = this.safeParse(chartOptions, {});
        const options = this._isPlainObject(parsed) ? this._clone(parsed) : {};
        const state = this._ensureGuiState(guiState);

        options[this.GUI_NAMESPACE] = state;
        delete options.analyticsModel;

        return options;
    },

    compose: function (defaultOptions, chartOptions, dataOptions) {
        const defaults = this._isPlainObject(defaultOptions) ? this._clone(defaultOptions) : {};
        const normalizedChartOptions = this.parseAndNormalize(chartOptions);
        const normalizedDataOptions = this._normalizeDataOptions(dataOptions);
        const guiState = this.getGuiState(normalizedChartOptions);

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
        return state;
    },

    _isSecondaryAxisRequired: function (dataOptions) {
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

        this._setByPath(patch, 'scales.secondary.display', this._isSecondaryAxisRequired(dataOptions));
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
