# Panorama Filter Proposal

## Goal
Enable filtering across all reports within a panorama. The filter modal should
list all unique dimensions gathered from the underlying reports. When a filter is
applied, every report that contains the selected dimension receives the filter
and updates accordingly.

## Overview of Changes
1. **UI Trigger**
   - Add a filter button to the panorama header next to existing edit controls.
   - The button opens the existing filter modal from `OCA.Analytics.Filter`.

2. **Dimension Collection**
   - While loading a panorama, collect each report's dimension list and build a
     unique dimension set.
   - Provide this list to the filter dialog so users can pick any dimension
     available across the dashboard.

3. **Filter Storage**
   - Store selected filters on the panorama object, e.g. under
     `OCA.Analytics.Panorama.currentPanorama.filters`.
   - When saving a panorama, persist these filters similarly to existing report
     filter options.

4. **Filter Application**
   - When retrieving report data (`getReportData`) include matching panorama
     filters in the request if the report supports the dimension.
   - After each update, call `OCA.Analytics.Filter.refreshFilterVisualisation` to
     show active filters in the header.

5. **Compatibility**
   - Reuse the existing modal and processing logic from `filter.js`. Only the
     dimension source and propagation mechanism differ from single reports.

## Implementation Steps
1. **Collect dimensions**
   - Extend `OCA.Analytics.Panorama.getReportData` to push each report's
     `dimensions` property into a global set.
2. **Open dialog**
   - Implement `OCA.Analytics.Panorama.openFilterDialog` that populates the
     dimension dropdown with the collected set and invokes
     `OCA.Analytics.Filter.openFilterDialog` for value selection.
3. **Apply filter**
   - Update `OCA.Analytics.Panorama.getReportData` so that panorama filters are
     appended to the request for reports containing the chosen dimension.
4. **Visual feedback**
   - Show applied filters in the panorama header using the same DOM elements as
     single reports.

This approach mirrors the filtering workflow of individual reports while
allowing a panorama-wide view. All reports continue to honour their own filters;
the panorama filter simply adds additional criteria where applicable.
