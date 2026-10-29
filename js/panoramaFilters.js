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
            requests: new Map(), metadata: new Map(), choices: new Map(), choiceLoads: new Set(), errors: new Set(), renders: new Map(),
        };
    },
    stop: function () {
        if (!this.state) return;
        this.state.requests.forEach(request => request.controller.abort());
        this.state.requests.clear();
        this.state.choiceLoads.clear();
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
        this.loadQuickFilterValues();
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
    pruneRemovedReports: function () {
        const reports = this.reports();
        let changed = false;
        const definitions = this.definitions().flatMap(filter => {
            const mappings = filter.mappings.filter(mapping => reports.has(Number(mapping.reportId)));
            if (mappings.length === filter.mappings.length) return [filter];
            changed = true;
            return mappings.length ? [{...filter, mappings}] : [];
        });
        if (!changed) return;
        OCA.Analytics.currentPanorama.filters = definitions;
        const state = this.ensureState();
        state.definitions = structuredClone(definitions);
        const ids = new Set(definitions.map(filter => filter.id));
        Object.keys(state.values).forEach(id => {
            if (!ids.has(id)) delete state.values[id];
        });
        this.updateButtons();
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
        const request = {controller, reportId: Number(reportId), discovery};
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
                const stableIndex = OCA.Analytics.Flexible.isFlexible(data)
                    ? OCA.Analytics.Flexible.indexForReference(data, key)
                    : -1;
                const index = stableIndex >= 0 ? stableIndex : (data.header || []).indexOf(label);
                const unambiguous = stableIndex >= 0 || (index >= 0 && data.header.lastIndexOf(label) === index);
                return [key, unambiguous ? [...new Set((data.data || []).map(row => row[index]).filter(value => value != null))] : []];
            }));
            if (!filtered) state.choices.set(Number(reportId), suggestions);
            state.metadata.set(Number(reportId), {name: data.options.name, dimensions: data.dimensions || {}, suggestions});
            this.refreshQuickFilters();
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
            if (changed.has(request.reportId) && !request.discovery) { request.controller.abort(); state.requests.delete(key); }
        });
        state.panorama.pages.forEach((page, pageIndex) => (page.reports || []).forEach((report, index) => {
            if (Number(report?.type) === 0 && changed.has(Number(report.value))) this.render(report.value, `${pageIndex}-${index}`);
        }));
        this.refreshFilterVisualisation();
    },
    refreshFilterVisualisation: function () {
        const state = this.ensureState();
        this.refreshQuickFilters();
        const container = document.getElementById('filterVisualisation');
        if (!container) return;
        container.replaceChildren();
        const fragment = document.createDocumentFragment();

        state.definitions.filter(filter => filter.enabled).forEach(filter => {
            const value = state.values[filter.id];
            const conditions = Array.isArray(value) ? value : value ? [{option: filter.operator, value}] : [];
            if (filter.showInHeader !== false && conditions.length === 1 && conditions[0].option === 'EQ') return;
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
    quickDefinitions: function () {
        return this.ensureState().definitions.filter(filter => filter.enabled && filter.showInHeader !== false);
    },
    loadQuickFilterValues: function () {
        if (OCA.Analytics.editMode) return;
        const state = this.ensureState();
        const reportIds = new Set(this.quickDefinitions().flatMap(filter => filter.mappings.map(mapping => mapping.reportId)));
        reportIds.forEach(id => {
            if (state.choices.has(id) || state.choiceLoads.has(id)) return;
            state.choiceLoads.add(id);
            this.request(id, true).catch(() => {
                // Report tiles and the existing filter dialog retain their error handling.
            }).finally(() => {
                if (this.state === state) this.refreshQuickFilters();
            });
        });
    },
    refreshQuickFilters: function () {
        const container = document.getElementById('panoramaQuickFilters');
        if (!container) return;
        const state = this.ensureState();
        const definitions = this.quickDefinitions();
        const configure = document.getElementById('panoramaConfigureFilters');
        container.hidden = configure.hidden && (OCA.Analytics.editMode || !definitions.length);
        container.querySelectorAll('.panoramaQuickFilterField, .panoramaQuickFilterReset').forEach(control => {
            control.hidden = OCA.Analytics.editMode;
        });
        if (OCA.Analytics.editMode) return;
        if (container.hidden) return;
        // Reuse controls while data arrives so keyboard focus and open selectors survive.
        const existing = new Map([...container.querySelectorAll('select')].map(select => [select.dataset.filterId, select]));
        definitions.forEach(filter => {
            let select = existing.get(filter.id);
            if (!select) {
                select = document.createElement('select');
                select.className = 'panoramaQuickFilterSelect';
                select.dataset.filterId = filter.id;
                const label = document.createElement('label');
                label.className = 'panoramaFilterField panoramaQuickFilterField';
                label.append(document.createElement('span'), select);
                container.append(label);
                select.addEventListener('change', () => {
                    const values = {...this.ensureState().values};
                    if (select.value === '') delete values[filter.id];
                    else values[filter.id] = [{option: 'EQ', value: select.value}];
                    this.apply(values);
                });
            }
            existing.delete(filter.id);
            select.previousElementSibling.textContent = filter.label;
            const value = state.values[filter.id];
            const conditions = Array.isArray(value) ? value : value ? [{option: filter.operator, value}] : [];
            const simple = !conditions.length || (conditions.length === 1 && conditions[0].option === 'EQ');
            const selected = conditions.length ? conditions[0].value : '';
            const choices = [...new Set(filter.mappings.flatMap(mapping =>
                state.choices.get(mapping.reportId)?.[mapping.dimension] || []).map(String))]
                .filter(value => value !== '').sort((a, b) => a.localeCompare(b, undefined, {numeric: true}));
            if (simple && selected && !choices.includes(selected)) choices.unshift(selected);
            // TRANSLATORS: All values of this panorama filter; no drilldown selection.
            const allValues = t('analytics', 'All');
            const options = [[allValues, ''], ...choices.map(value => [value, value])];
            if (!simple) options.push([t('analytics', 'Custom filter'), '']);
            const signature = JSON.stringify(options);
            if (select.dataset.options !== signature) {
                select.replaceChildren(...options.map(([label, value]) => new Option(label, value)));
                if (!simple) select.lastElementChild.disabled = true;
                select.dataset.options = signature;
            }
            if (simple) select.value = selected;
            else select.selectedIndex = select.options.length - 1;
        });
        existing.forEach(select => select.parentElement.remove());
        let reset = container.querySelector('.panoramaQuickFilterReset');
        if (!reset) {
            reset = document.createElement('button');
            reset.type = 'button';
            reset.className = 'panoramaQuickFilterReset';
            reset.textContent = t('analytics', 'Reset');
            reset.addEventListener('click', () => {
                const values = {...this.ensureState().values};
                this.quickDefinitions().forEach(filter => {
                    if (filter.defaultValue) values[filter.id] = filter.defaultValue;
                    else delete values[filter.id];
                });
                this.apply(values);
            });
        }
        container.append(reset);
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
            const summary = document.createElement('button');
            summary.type = 'button';
            summary.className = 'analyticsSecondary panoramaFilterSummary';
            summary.setAttribute('aria-expanded', 'false');
            summary.textContent = filter.label || t('analytics', 'New variable');
            header.append(summary);
            const enabled = this.field(header, t('analytics', 'Enabled'), this.input('', 'checkbox'));
            enabled.parentElement.classList.add('panoramaFilterEnable');
            enabled.checked = filter.enabled;
            const mappingCount = document.createElement('span');
            mappingCount.className = 'panoramaFilterMappingCount';
            header.append(mappingCount);
            const remove = document.createElement('a');
            remove.href = '#';
            remove.setAttribute('role', 'button');
            remove.className = 'icon-delete panoramaFilterRemove';
            remove.title = t('analytics', 'Remove');
            remove.setAttribute('aria-label', remove.title);
            header.append(remove);
            const settings = document.createElement('div');
            settings.className = 'panoramaFilterSettings';
            settings.id = `panoramaFilterSettings-${this.newId()}`;
            settings.hidden = true;
            summary.setAttribute('aria-controls', settings.id);
            summary.addEventListener('click', () => {
                settings.hidden = !settings.hidden;
                summary.setAttribute('aria-expanded', String(!settings.hidden));
            });
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
            const showInHeader = this.field(settings, t('analytics', 'Show in panorama header'), this.input('', 'checkbox'));
            showInHeader.checked = filter.showInHeader !== false;
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
                // TRANSLATORS: Noun: a data column used to identify or group records, such as region or date.
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
                // A failed report request cannot establish whether its dimensions changed.
                // Keep its saved mappings so unrelated presentation changes remain editable.
                const stale = available.has(reportId) && currentLabel !== mapping.dimensionLabel;
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
            const entry = {filter, row, enabled, label, operator, defaultValue, showInHeader, mappings};
            remove.addEventListener('click', event => {
                event.preventDefault();
                row.remove();
                rows.splice(rows.indexOf(entry), 1);
            });
            remove.addEventListener('keydown', event => {
                if (event.key === ' ') {
                    event.preventDefault();
                    remove.click();
                }
            });
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
                    || selected.some(m => (entry.enabled.checked && m.stale) || !reports.has(m.mapping.reportId))) {
                    errors.textContent = t('analytics', 'Enter a name and repair missing dimensions. Enabled variables need a report dimension.');
                    errors.scrollIntoView({block: 'nearest'});
                    return;
                }
                for (const {mapping} of selected) {
                    const key = `${mapping.reportId}:${mapping.dimension}`;
                    if (entry.enabled.checked && targets.has(key)) {
                        errors.textContent = t('analytics', 'A dimension can only belong to one enabled variable');
                        errors.scrollIntoView({block: 'nearest'});
                        return;
                    }
                    if (entry.enabled.checked) targets.add(key);
                }
                next.push({...entry.filter, label: entry.label.value.trim(), enabled: entry.enabled.checked,
                    showInHeader: entry.showInHeader.checked,
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
