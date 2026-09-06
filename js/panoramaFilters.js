/**
 * SPDX-FileCopyrightText: 2026 Marcel Scherello
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

'use strict';

OCA.Analytics.PanoramaFilters = {
    state: null,
    newId: function () {
        // getRandomValues also works on HTTP Nextcloud installations.
        return 'f_' + Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    },
    definitions: function () {
        const filters = OCA.Analytics.currentPanorama?.filters;
        if (Array.isArray(filters)) return filters;
        try { return JSON.parse(filters || '[]') || []; } catch (error) { return []; }
    },
    reset: function () {
        this.stop();
        const definitions = this.definitions();
        OCA.Analytics.currentPanorama.filters = definitions;
        this.state = {
            panorama: OCA.Analytics.currentPanorama,
            definitions: structuredClone(definitions),
            values: Object.fromEntries(definitions.filter(f => f.enabled && f.defaultValue).map(f => [f.id, f.defaultValue])),
            requests: new Map(), metadata: new Map(), errors: new Set(), renders: new Map(),
        };
    },
    stop: function () {
        if (!this.state) return;
        this.state.requests.forEach(request => request.controller.abort());
        this.state.requests.clear();
        this.state.renders.clear();
    },
    ensureState: function () {
        if (this.state?.panorama !== OCA.Analytics.currentPanorama) this.reset();
        return this.state;
    },
    updateButtons: function () {
        this.ensureState();
        const hasEnabledFilters = this.state.definitions.some(filter => filter.enabled);
        document.getElementById('panoramaConfigureFilters').hidden = !(OCA.Analytics.editMode
            && Number(OCA.Analytics.currentPanorama.permissions) === OCA.Analytics.SHARE_PERMISSION_UPDATE);
        const filterIcon = document.getElementById('addFilterIcon');
        const filterVisualisation = document.getElementById('filterVisualisation');
        filterIcon.hidden = OCA.Analytics.editMode || !hasEnabledFilters;
        filterVisualisation.hidden = OCA.Analytics.editMode || !hasEnabledFilters;
        this.refreshFilterVisualisation();
    },
    reports: function () {
        const reports = new Map();
        OCA.Analytics.currentPanorama.pages.forEach((page, index) => {
            (page.reports || []).forEach(report => {
                if (Number(report?.type) !== OCA.Analytics.PANORAMA_CONTENT_TYPE_REPORT) return;
                const id = Number(report.value);
                if (!reports.has(id)) reports.set(id, []);
                reports.get(id).push({index, name: page.name});
            });
        });
        return reports;
    },
    valuesFor: function (reportId, values = this.state.values) {
        return Object.fromEntries(this.state.definitions.filter(f => f.enabled && Object.hasOwn(values, f.id)
            && values[f.id] !== '' && values[f.id] !== null && f.mappings.some(m => m.reportId === Number(reportId)))
            .map(f => [f.id, values[f.id]]));
    },
    request: function (reportId, discovery = false) {
        const state = this.ensureState();
        const values = discovery ? {} : this.valuesFor(reportId);
        const filtered = Object.keys(values).length > 0;
        const key = JSON.stringify([Number(reportId), values]);
        if (state.requests.has(key)) return state.requests.get(key).promise;
        const controller = new AbortController();
        const request = {controller, reportId: Number(reportId)};
        request.promise = (async () => {
            const storage = filtered ? null : OCA.Analytics.getLocalStorage();
            const cacheKey = `analytics-report-${reportId}`;
            let cached = null;
            try { cached = JSON.parse(storage?.getItem(cacheKey) || 'null'); } catch (error) { /* Ignore invalid cache. */ }
            if (!cached?.data?.options || !cached.version) cached = null;
            const headers = OCA.Analytics.headers();
            if (cached?.version) headers.set('If-None-Match', cached.version);
            const params = new URLSearchParams();
            // Unsaved edit selections can include new reports; discovery uses the existing owner endpoint.
            if (!OCA.Analytics.editMode || filtered) params.set('panoramaId', state.panorama.id);
            if (filtered) params.set('variables', JSON.stringify(values));
            const response = await fetch(OC.generateUrl('apps/analytics/data/pa/' + reportId) + '?' + params, {
                headers, signal: controller.signal,
            });
            let data;
            if (response.status === 304 && cached?.data) {
                data = cached.data;
            } else {
                data = await response.json().catch(() => null);
                if (!response.ok || !data || Number(data.error) !== 0) throw new Error(typeof data?.error === 'string'
                    ? data.error : t('analytics', 'Request could not be processed'));
                if (storage && response.headers.get('X-Analytics-Cacheable') === 'true' && response.headers.get('ETag')) {
                    try { storage.setItem(cacheKey, JSON.stringify({data, version: response.headers.get('ETag')})); } catch (error) { /* Storage is optional. */ }
                }
            }
            if (controller.signal.aborted || this.state !== state) throw new DOMException('Aborted', 'AbortError');
            const suggestions = Object.fromEntries(Object.entries(data.dimensions || {}).map(([key, label]) => {
                const index = (data.header || []).indexOf(label);
                const unambiguous = index >= 0 && data.header.lastIndexOf(label) === index;
                return [key, unambiguous ? [...new Set((data.data || []).map(row => row[index]).filter(value => value != null))] : []];
            }));
            state.metadata.set(Number(reportId), {name: data.options.name, dimensions: data.dimensions || {}, suggestions});
            return data;
        })().finally(() => {
            if (state.requests.get(key) === request) state.requests.delete(key);
        });
        state.requests.set(key, request);
        return request.promise;
    },
    replaceWidget: function (itemId, text) {
        const old = document.getElementById('myWidget' + itemId);
        if (!old) return;
        if (old.tagName === 'CANVAS') Chart.getChart(old)?.destroy();
        const tableKey = parseInt(itemId.replace(/[^0-9]+/g, ''), 10);
        const table = OCA.Analytics.tableObject?.[tableKey];
        if (old.tagName === 'TABLE' && typeof table?.destroy === 'function' && table.table().node() === old) {
            table.destroy();
            delete OCA.Analytics.tableObject[tableKey];
        }
        const replacement = document.createElement('div');
        replacement.id = old.id;
        replacement.textContent = text;
        replacement.setAttribute('role', 'status');
        old.replaceWith(replacement);
    },
    render: async function (reportId, itemId) {
        const state = this.ensureState();
        const token = {};
        state.renders.set(itemId, token);
        state.errors.delete(itemId);
        this.replaceWidget(itemId, t('analytics', 'Loading'));
        const current = () => this.state === state && state.renders.get(itemId) === token
            && state.panorama === OCA.Analytics.currentPanorama && document.getElementById('myWidget' + itemId);
        try {
            const data = await this.request(reportId);
            if (!current()) return;
            const processed = OCA.Analytics.Report.Backend.processReceivedData(structuredClone(data));
            if (!processed.data?.length) {
                this.replaceWidget(itemId, t('analytics', 'No data'));
                document.getElementById('analyticsWidgetReport' + itemId).textContent = data.options.name;
            } else {
                OCA.Analytics.Panorama.setWidgetTypeReportContent(processed, itemId);
            }
        } catch (error) {
            if (error.name === 'AbortError' || !current()) return;
            state.errors.add(itemId);
            state.metadata.delete(Number(reportId));
            this.replaceWidget(itemId, error.message);
        } finally {
            if (state.renders.get(itemId) === token) state.renders.delete(itemId);
        }
    },
    apply: function (values) {
        const state = this.ensureState();
        const changed = new Set([...this.reports().keys()].filter(id =>
            JSON.stringify(this.valuesFor(id)) !== JSON.stringify(this.valuesFor(id, values))));
        state.values = values;
        state.requests.forEach((request, key) => {
            if (changed.has(request.reportId)) { request.controller.abort(); state.requests.delete(key); }
        });
        state.panorama.pages.forEach((page, pageIndex) => (page.reports || []).forEach((report, index) => {
            if (Number(report?.type) === 0 && changed.has(Number(report.value))) this.render(report.value, `${pageIndex}-${index}`);
        }));
        this.refreshFilterVisualisation();
    },
    refreshFilterVisualisation: function () {
        const state = this.ensureState();
        const container = document.getElementById('filterVisualisation');
        if (!container) return;
        container.replaceChildren();
        const fragment = document.createDocumentFragment();

        state.definitions.filter(filter => filter.enabled).forEach(filter => {
            const value = state.values[filter.id];
            const conditions = Array.isArray(value) ? value : value ? [{option: filter.operator, value}] : [];
            conditions.forEach((condition, index) => {
                if (condition.value === '') return;
                const item = document.createElement('span');
                item.className = 'filterVisualizationItem';
                const remove = document.createElement('span');
                remove.className = 'filterVisualizationRemove icon-close';
                remove.title = t('analytics', 'Remove filter');
                remove.addEventListener('click', () => this.removeCondition(filter.id, index));
                const label = document.createElement('span');
                let option = OCA.Analytics.Filter.optionTextsArray[condition.option] || '';
                let valueText = condition.value;
                if ((valueText.match(/%/g) || []).length === 2) {
                    option = '';
                    valueText = valueText.replace(/%/g, '').replace(/\(.*?\)/g, '');
                }
                label.textContent = `${filter.label} ${option} ${valueText}`.trim();
                item.append(remove, label);
                fragment.append(item);
            });
        });
        container.append(fragment);
    },
    removeCondition: function (filterId, conditionIndex) {
        const values = Object.assign(Object.create(null), this.ensureState().values);
        const conditions = Array.isArray(values[filterId]) ? [...values[filterId]] : [];
        conditions.splice(conditionIndex, 1);
        if (conditions.length) {
            values[filterId] = conditions;
        } else {
            delete values[filterId];
        }
        this.apply(values);
    },
    field: function (parent, text, input) {
        const label = document.createElement('label');
        label.className = 'panoramaFilterField';
        const caption = document.createElement('span');
        caption.textContent = text;
        label.append(caption, input);
        parent.append(label);
        return input;
    },
    input: function (value = '', type = 'text') {
        const input = document.createElement('input');
        input.type = type;
        input.value = value ?? '';
        return input;
    },
    reportLabel: function (reportId) {
        return this.state.metadata.get(reportId)?.name || t('analytics', 'Report') + ' ' + reportId;
    },
    openViewer: function () {
        const state = this.ensureState();
        const definitions = state.definitions.filter(filter => filter.enabled);
        const filters = definitions.flatMap(filter => {
            const value = state.values[filter.id];
            const conditions = Array.isArray(value) ? value : value ? [{option: filter.operator, value}] : [];
            return conditions.map(condition => ({dimension: filter.id, ...condition}));
        });
        OCA.Analytics.Filter.openFilterDialog({
            dimensions: Object.fromEntries(definitions.map(filter => [filter.id, filter.label])),
            defaultOperators: Object.fromEntries(definitions.map(filter => [filter.id, filter.operator])),
            descriptions: Object.fromEntries(definitions.map(filter => [
                filter.id,
                [...new Set(filter.mappings.map(mapping => this.reportLabel(mapping.reportId)))],
            ])),
            filters,
            valuesFor: id => [...new Set((definitions.find(filter => filter.id === id)?.mappings || [])
                .flatMap(mapping => state.metadata.get(mapping.reportId)?.suggestions?.[mapping.dimension] || []))],
            dialogOptions: {leadingAction: {label: t('analytics', 'Reset'), onClick: () => {
                this.apply({}); OCA.Analytics.Notification.dialogClose();
            }}},
            onApply: rows => {
                const values = Object.create(null);
                rows.filter(row => row.value !== '').forEach(row => {
                    (values[row.dimension] ||= []).push({option: row.option, value: row.value});
                });
                this.apply(values);
                OCA.Analytics.Notification.dialogClose();
            },
        });
    },
    suggestions: function (definitions, metadata) {
        const assigned = new Set(definitions.flatMap(f => f.mappings.map(m => `${m.reportId}:${m.dimension}`)));
        const groups = new Map();
        metadata.forEach((report, reportId) => {
            const names = Object.values(report.dimensions).map(name => String(name).trim().toLowerCase());
            Object.entries(report.dimensions).forEach(([dimension, label]) => {
                const name = String(label).trim().toLowerCase();
                if (!name || assigned.has(`${reportId}:${dimension}`) || names.filter(n => n === name).length !== 1) return;
                if (!groups.has(name)) groups.set(name, {id: this.newId(), label: String(label).trim(), enabled: false,
                    operator: 'EQ', defaultValue: null, mappings: []});
                groups.get(name).mappings.push({reportId, dimension, dimensionLabel: label});
            });
        });
        return [...groups.values()];
    },
    openEditor: async function () {
        if (!OCA.Analytics.editMode || Number(OCA.Analytics.currentPanorama.permissions) !== OCA.Analytics.SHARE_PERMISSION_UPDATE) return;
        const state = this.ensureState();
        OCA.Analytics.Notification.htmlDialogInitiate(t('analytics', 'Configure filters'), () => {});
        const dialog = document.getElementById('analyticsDialogContainer');
        document.getElementById('analyticsDialogBtnGo').hidden = true;
        const reports = this.reports();
        const failures = [];
        await Promise.all([...reports.keys()].map(async id => {
            if (state.metadata.has(id)) return;
            try { await this.request(id, true); } catch (error) { if (error.name !== 'AbortError') failures.push(id); }
        }));
        if (this.state !== state || document.getElementById('analyticsDialogContainer') !== dialog) return;
        const available = new Map([...reports.keys()].filter(id => state.metadata.has(id)).map(id => [id, state.metadata.get(id)]));
        const definitions = structuredClone(this.definitions());
        definitions.push(...this.suggestions(definitions, available));
        const content = document.createElement('div');
        content.className = 'panoramaFilters';
        const errors = document.createElement('p');
        errors.setAttribute('role', 'alert');
        if (failures.length) errors.textContent = t('analytics', 'Some report dimensions could not be loaded. Reopen this dialog to retry.');
        content.append(errors);
        const rows = [];
        const addRow = filter => {
            const row = document.createElement('fieldset');
            row.className = 'panoramaFilterDefinition';
            const header = document.createElement('div');
            header.className = 'panoramaFilterCardHeader';
            row.append(header);
            const enabled = this.field(header, t('analytics', 'Enabled'), this.input('', 'checkbox'));
            enabled.parentElement.classList.add('panoramaFilterEnable');
            enabled.checked = filter.enabled;
            const summary = document.createElement('strong');
            summary.className = 'panoramaFilterSummary';
            summary.textContent = filter.label || t('analytics', 'New variable');
            header.append(summary);
            const mappingCount = document.createElement('span');
            mappingCount.className = 'panoramaFilterMappingCount';
            header.append(mappingCount);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'analyticsSecondary panoramaFilterRemove';
            remove.textContent = t('analytics', 'Remove');
            header.append(remove);
            const settings = document.createElement('div');
            settings.className = 'panoramaFilterSettings';
            row.append(settings);
            const controls = document.createElement('div');
            controls.className = 'panoramaFilterDefinitionControls';
            settings.append(controls);
            const labelInput = this.input(filter.label);
            labelInput.className = 'sidebarInput';
            const label = this.field(controls, t('analytics', 'Variable name'), labelInput);
            label.addEventListener('input', () => { summary.textContent = label.value.trim() || t('analytics', 'New variable'); });
            const operator = document.createElement('select');
            operator.className = 'sidebarInput';
            Object.entries(OCA.Analytics.Filter.optionTextsArray).forEach(([key, text]) => operator.add(new Option(text, key)));
            operator.value = filter.operator;
            this.field(controls, t('analytics', 'Operator'), operator);
            const defaultValueInput = this.input(filter.defaultValue);
            defaultValueInput.className = 'sidebarInput';
            const defaultValue = this.field(controls, t('analytics', 'Default value'), defaultValueInput);
            const mappingTitle = document.createElement('div');
            mappingTitle.className = 'panoramaFilterMappingsTitle';
            mappingTitle.textContent = t('analytics', 'Applies to');
            settings.append(mappingTitle);
            const mappingList = document.createElement('div');
            mappingList.className = 'panoramaFilterMappings';
            settings.append(mappingList);
            const mappingHeader = document.createElement('div');
            mappingHeader.className = 'panoramaFilterMappingHeader';
            mappingHeader.append(Object.assign(document.createElement('span'), {textContent: t('analytics', 'Report')}),
                Object.assign(document.createElement('span'), {textContent: t('analytics', 'Dimension')}), document.createElement('span'));
            mappingList.append(mappingHeader);
            const candidates = new Map();
            available.forEach((report, reportId) => Object.entries(report.dimensions).forEach(([dimension, dimensionLabel]) => {
                candidates.set(`${reportId}:${dimension}`, {reportId, dimension, dimensionLabel});
            }));
            // Retain stale mappings visibly until the owner removes or repairs them.
            filter.mappings.forEach(m => candidates.set(`${m.reportId}:${m.dimension}`, m));
            const mappings = [];
            const groupedCandidates = new Map();
            candidates.forEach(mapping => {
                if (!groupedCandidates.has(mapping.reportId)) groupedCandidates.set(mapping.reportId, []);
                groupedCandidates.get(mapping.reportId).push(mapping);
            });
            groupedCandidates.forEach((reportMappings, reportId) => reportMappings.forEach((mapping, index) => {
                const original = filter.mappings.some(m => m.reportId === mapping.reportId && m.dimension === mapping.dimension);
                const currentLabel = available.get(mapping.reportId)?.dimensions[mapping.dimension];
                const stale = currentLabel !== mapping.dimensionLabel;
                const addMapping = (candidate, selected, invalid) => {
                    const mappingRow = document.createElement('div');
                    mappingRow.className = 'panoramaFilterMappingRow';
                    if (index === 0) mappingRow.classList.add('is-report-start');
                    const report = document.createElement('span');
                    report.textContent = index === 0 ? this.reportLabel(reportId) : '';
                    const dimension = document.createElement('span');
                    dimension.textContent = candidate.dimensionLabel + (invalid ? ' — ' + t('analytics', 'Missing or changed dimension') : '');
                    const check = this.input('', 'checkbox');
                    check.checked = selected;
                    check.setAttribute('aria-label', `${this.reportLabel(reportId)}: ${candidate.dimensionLabel}`);
                    mappingRow.append(report, dimension, check);
                    mappingList.append(mappingRow);
                    mappings.push({check, mapping: candidate, stale: invalid});
                    check.addEventListener('change', updateMappingCount);
                };
                addMapping(mapping, original, stale);
                if (stale && currentLabel !== undefined) addMapping({...mapping, dimensionLabel: currentLabel}, false, false);
            }));
            function updateMappingCount() {
                const count = mappings.filter(mapping => mapping.check.checked).length;
                mappingCount.textContent = `${t('analytics', 'Selected')}: ${count}`;
            }
            const setEnabled = () => {
                settings.classList.toggle('is-disabled', !enabled.checked);
                settings.querySelectorAll('input, select').forEach(control => { control.disabled = !enabled.checked; });
            };
            enabled.addEventListener('change', setEnabled);
            setEnabled();
            updateMappingCount();
            const entry = {filter, row, enabled, label, operator, defaultValue, mappings};
            remove.addEventListener('click', () => { row.remove(); rows.splice(rows.indexOf(entry), 1); });
            content.append(row);
            rows.push(entry);
        };
        definitions.forEach(addRow);
        const add = document.createElement('button');
        add.type = 'button';
        add.className = 'analyticsSecondary panoramaFilterAdd';
        add.textContent = t('analytics', 'Add variable');
        add.addEventListener('click', () => addRow({id: this.newId(), label: '', enabled: false, operator: 'EQ', defaultValue: null, mappings: []}));
        content.append(add);
        // Replace the placeholder callback after discovery finishes.
        const oldGo = document.getElementById('analyticsDialogBtnGo');
        const go = oldGo.cloneNode(true);
        oldGo.replaceWith(go);
        go.hidden = false;
        go.textContent = t('analytics', 'Apply');
        go.addEventListener('click', () => {
            const targets = new Set();
            const next = [];
            for (const entry of rows) {
                const selected = entry.mappings.filter(m => m.check.checked);
                if (!entry.label.value.trim() || (entry.enabled.checked && !selected.length)
                    || selected.some(m => m.stale || !reports.has(m.mapping.reportId))) {
                    errors.textContent = t('analytics', 'Enter a name and repair missing dimensions. Enabled variables need a report dimension.');
                    return;
                }
                for (const {mapping} of selected) {
                    const key = `${mapping.reportId}:${mapping.dimension}`;
                    if (entry.enabled.checked && targets.has(key)) {
                        errors.textContent = t('analytics', 'A dimension can only belong to one enabled variable');
                        return;
                    }
                    if (entry.enabled.checked) targets.add(key);
                }
                next.push({...entry.filter, label: entry.label.value.trim(), enabled: entry.enabled.checked,
                    operator: entry.operator.value, defaultValue: entry.defaultValue.value || null,
                    mappings: selected.map(m => m.mapping)});
            }
            OCA.Analytics.currentPanorama.filters = next;
            OCA.Analytics.unsavedChanges = true;
            OCA.Analytics.Filter.toggleSaveButtonDisplay();
            OCA.Analytics.Notification.dialogClose();
        });
        OCA.Analytics.Notification.htmlDialogUpdate(content,
            t('analytics', 'Enable the variables viewers may use. Match dimensions below, then save the panorama. Date expressions such as %last2months% are supported.'));
    },
};

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('panoramaConfigureFilters').addEventListener('click', () => OCA.Analytics.PanoramaFilters.openEditor());
});
