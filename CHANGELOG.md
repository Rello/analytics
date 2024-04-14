# Changelog

## 4.13.0 - 2024-04-14
### Added
- copy data loads

### Changed
- DataTables 2.0.3
- Code cleanup and refactoring

### Fixed
- console error when using timestamps for 1-column raw data #384
- css deprecation 
- CSP issues in NC28 with js scripts

## 4.12.0 - 2023-12-11
### Added
- Cron schedule "End of day" and "Start of day" #381

### Changed
- flow event listener for NC28
- css adjustment for NC28
- scss removal (<26)
- min version 26

### Fixed
- missing back button when no data sets exist yet
- Nextcloud Tables name with "-" #380
- js files loaded in an undefined sequence #378

## 4.11.1 - 2023-11-20
### Fixed
- save error for reports without table
- type parsing in report mapper
- report import not creating dataset
- js files loaded in an undefined sequence #378

## 4.11.0 - 2023-10-01
### Added
- Chart: stacked and stacked 100% options #370
- bigger input fields in data source options

### Fixed
- incorrect record count during data loads
- NC Flow - background color
- incorrect API error message #371
- custom colors in doughnut chart #372
- dashboard charts not generating
- csv import remove spaces
- chart generation optimization

## 4.10.0 - 2023-08-22
### Added
- translation integration for reports #358
- add custom columns (incl. text variables) in column picker #359
- list available values for input boxes (e.g. filters) #362
- csv data: select header row #360
- Report: drill down & aggregation also for external data sources #365
- Tables: fixed first column, horizontal scrolling #367
- Tables: save states like table length and sorting #367

### Changed
- DataTables 1.13.6

### Fixed
- 3digit comma values evaluated as 1000s #357
- show errors of external data sources
- error on public pages #363

## 4.9.4 - 2023-06-17
### Fixed
- NC 27 Uncaught (in promise) TypeError: OC.Apps is undefined #356
- Error in journal after upgrade Nextcloud to 24.0.12 #355
- Settings navigation not working

## 4.9.3 - 2023-05-27
### Fixed
- Sharing reports not possible any more #354

## 4.9.2 - 2023-05-26
### Fixed
- Sharing reports not possible any more #354

## 4.9.1 - 2023-05-17
### Fixed
- Whats-new popup

## 4.9.0 - 2023-05-15
### Added
- smart picker integration #343
- sharing of report groups #342
- Allow custom headers in JSON data source #351
- Grouping data source parameters into "more" section for better readability
- cleanup receiving shares when user is deleted

### Fixed
- thresholds of other user in shared report
- URL parsing replace &amp; in CSV datasource #348
- unified popups
- wizard ui enhancement

## 4.8.0 - 2023-03-16
### Added
- column picker for data sources #337
- number-only option field for data sources
- import reports directly from computer and not via NC files
- unified popups

### Fixed 
- report import not working #336
- hide export button for folder #335
- error handling when data source type is not available anymore
- automated data deletion not working #339
- cannot change color of data series #338
- chart legend not scrolling
- UI inconsistency (dataset maintenance <> advanced) #340
- new report wizard closing button hidden

## 4.7.2 - 2023-02-13
### Fixed
- php version dependencies (type declaration)

## 4.7.1 - 2023-02-12
### Fixed
- data deletion simulation not counting correct
- share edit setting not saved
- catching external datasource issues
- handling of null columns during loads

## 4.7.0 - 2023-02-10
### Added
- JSON: read array data which is not on the first level #326
- recognize dates in spreadsheet data source #311

### Changed
- removal of jQuery dependencies

### Fixed
- table search box gets hidden #329
- decimal number formatting in table

## 4.6.0 - 2022-12-26
### Added
- Notifications for failed data loads
- Schedule deletion jobs per dataset to clean up old data #316
- Filter variables to use same time format like chart #321

### Changed
- Sidebar UI optimizations
- Optimized share report template

### Fixed
- No update permission when sharing to group #324

## 4.5.2 - 2022-12-xx
### Added
- Notifications for failed data loads
- Schedule deletion jobs per dataset to clean up old data #316

### Changed
- Filter variables to use same time format like chart #321
- Sidebar UI optimizations
- Optimized share report template

### Fixed
- No update permission when sharing to group #324

## 4.5.1 - 2022-11-18
### Fixed
- Missing dataset id in example API call #317
- No data received on JSON #319

## 4.5.0 - 2022-11-05
### Added
- Text variables for API #314

### Changed
- DataTables 1.12.1
- Chart.js v3.9.1
- chartjs-plugin-datalabels v2.1.0
- Simplify "New record" notification maintenance #303

### Fixed
- handle thousand separator comma
- regex datasource wrong html escaping
- issue in dataload: URL parsing replace &amp; #278
- Sharing of groups should not be possible #313
- various NC25 css fixes
- Print layout #302

## 4.4.0 - 2022-09-15
### Added
- Navigation drag & drop #292
- Text variables for thresholds #299
- New threshold notification type: "new record" #297
- NC25 support

### Fixed
- Usernames shown as IDs instead of readable names #286
- Faster report creation #293
- Notification issue for occ dataload #294
- Share Link doesn't include port number #298
- Display series are persistently listed in the legend even if not present #288
- html-grabber not working due to html-escaping

## 4.3.1 - 2022-06-25
### Fixed
- Icons for share users too big #282
- Shared report not shown #283
- wrong image url #284

## 4.3.0 - 2022-06-13
### Added
- new application start page #276
- persist legend selections (hidden/visible) #120
- remove data when user is deleted #280
- Chart.js v3.8
- chartjs-plugin-zoom v1.2.1

### Fixed
- issue when datasets exist without reports #273
- unhandled error in data load #274
- issue in data load: URL parsing replace &amp; #278

## 4.2.1 - 2022-03-27
### Fixed
- Database error during fresh install

## 4.2.0 - 2022-03-26
### Added
- Public API to get plain chart for external website inclusion #259  => [wiki](https://github.com/Rello/analytics/wiki/Sharing) for details

### Fixed
- XSS protection (credit to: Jafar Abo Nada; https://twitter.com/jafar_abo_nada; UpdateLap)
- notification threshold not working
- Quick start in data maintenance #267
- Quick start closure #264

## 4.1.0 - 2022-03-06
### Added
- Dynamic text variables for filter #186 => [wiki](https://github.com/Rello/analytics/wiki/Filter,-chart-options-&-drilldown#filter) for details
- Zoom in charts #246
- Analysis functions (beta): Aggregate / disaggregate values #258
- JSON data source: POST body; reading arrays #233 => [wiki](https://github.com/Rello/analytics/wiki/Datasource:-JSON) for details

### Changed
- Cancel report load when another report is selected #249
- Chart.js v3.7

### Fixed
- l10n: Untranslated messages #255
- l10n: Text strings in demo #254
- l10n: First Run Wizard #253
- open report from url #248

## 4.0.3 - 2022-01-21
### Fixed
- file picker not working #239
- Fix donut chart selection in wizard #242 @[connium](https://github.com/connium)
- Cannot set "can navigate" for other than the very first user in the list #238
- Datasource Undefined array key \"user_id\" #240
- Make dataload faster #236

## 4.0.2 - 2021-12-03
### Fixed
- Search error #234

## 4.0.1 - 2021-11-20
### Fixed
- php7.3 incompatibility #209
- text variable issue
- No default text in dropdown #226
- No text string to translate for "copy" #228
- Color of closure icon #225
- Filter does not work on the graph shared by link #224
- ru translations @[AleksovAnry](https://github.com/AleksovAnry)
- make wizard text selectable
- Data maintenance in advanced mode

## 4.0.0 - 2021-11-15
### Added
- Multiple reports per dataset #193
- GET/POST for json datasource
- Error texts for datasources #196
- "New report" & "New Dataset" Wizard #197
- HU translation #199
- enable Transifex #202

### Changed
- Dashboard Size/Format of displayed numbers #187
- Chart.js 3.6
- Data with only day/month #184

### Fixed
- Navigation layout issue #190
- Ads a 0 in front of 2 digit month #191
- Report shared by link no chart is displayed #192
- Chart axis assignment not working #189

## 3.6.0 - 2021-07-28

### Added
- Report: text variables(e.g. %lastUpdate%) [#145](https://github.com/rello/analytics/issues/145)
- Charts: customize colors #119
- Charts: datalabels in doughnuts
- automatic refresh of report #182
- Enable receiver of share to unshare [#171](https://github.com/rello/analytics/issues/171)
- sorting of external/unstructured data [#175](https://github.com/rello/analytics/issues/175)
- various loading indicators #157

### Changed

- Chart.js 3.5
- Update ru language @[sibergad](https://github.com/sibergad)
- Trend only for visible data series [#172](https://github.com/rello/analytics/issues/172)
- Dashboard subheader #180
- move to \Psr\Log\LoggerInterface

### Fixed
- DatasetService removes first favorite #185
- dataset options not merged [#178](https://github.com/rello/analytics/issues/178)
- dataset options not merged on dashboard [#179](https://github.com/rello/analytics/issues/179)

## 3.5.1 - 2021-05-22

### Fixed

- threshold colors wrong in dashboard [#159](https://github.com/rello/analytics/issues/159)
- Issues with bulk data upload [#136](https://github.com/rello/analytics/issues/136)
- Database issue on NC21 installation [#113](https://github.com/rello/analytics/issues/113)
- ru translations @[AleksovAnry](https://github.com/AleksovAnry)

## 3.5.0 - 2021-05-18

### Added

- UI cleanups [#138](https://github.com/rello/analytics/issues/138)
- Analysis functions: trend [#144](https://github.com/rello/analytics/issues/144)
- Report: Save logic also for chart options [#123](https://github.com/rello/analytics/issues/123)
- Report: Same filter logic for all data sources [#139](https://github.com/rello/analytics/issues/139)
- Report: Download chart as image [#143](https://github.com/rello/analytics/issues/143)
- Datasource: overwrite column with custom text [#131](https://github.com/rello/analytics/issues/131)
- REST API V3: get report list, details & data [#151](https://github.com/Rello/analytics/pull/151)
  @[ochorocho](https://github.com/ochorocho)

### Changed
- Migration to Chart.js V.3 [#140](https://github.com/rello/analytics/issues/140)
- Update ru language @[sibergad](https://github.com/sibergad) @[AleksovAnry](https://github.com/AleksovAnry)
- New key in l10n [#129](https://github.com/rello/analytics/issues/129)

### Fixed
- workflow not working
- Report: handle spaces in filters
- Issues with bulk data upload [#136](https://github.com/rello/analytics/issues/136)
- occ command error [#130](https://github.com/rello/analytics/issues/130)
- Error when sharing xlsx report [#133](https://github.com/rello/analytics/issues/133)
- handle db migration issues [#148](https://github.com/rello/analytics/issues/148)
- document from group folder not working [#146](https://github.com/rello/analytics/issues/146)

## 3.4.1 - 2021-03-14

### Fixed

- First start wizard showing every time #132

## 3.4.0 - 2021-03-13

### Added

- Datasource: Spreadsheet (xls, xlsx, ods) [#115](https://github.com/rello/analytics/issues/115)

### Changed
- Displayed date & time in UTC [#54](https://github.com/rello/analytics/issues/54)
- Improve robustness of data load (empty data) [#112](https://github.com/rello/analytics/issues/112)
- Usability when creating new reports
- UI improvements
- Share link to clipboard and not new window
- Filter usability enhancements [#122](https://github.com/rello/analytics/issues/122)

### Fixed
- Sharing link not working [#121](https://github.com/rello/analytics/issues/121)
- "Limit to groups" not working [#73](https://github.com/rello/analytics/issues/73)
- Dashboard icon missing
- Float numbers in graph tooltips [#117](https://github.com/rello/analytics/issues/117)
- High CPU load during dataloads [#118](https://github.com/rello/analytics/issues/118)
- sharing "can modify" does not show the current status. [#125](https://github.com/rello/analytics/issues/125)

## 3.3.3 - 2021-02-24

### Fixed

- Database issue on NC21 installation [#113](https://github.com/rello/analytics/issues/113)

## 3.3.2 - 2021-02-17

### Fixed

- Dataload issue with "External file" [#110](https://github.com/rello/analytics/issues/110)

## 3.3.0 - 2021-02-13
### Added
- First Start Wizard [#103](https://github.com/rello/analytics/issues/103)
- Export / import reports (incl data) [#100](https://github.com/rello/analytics/issues/100)
- Parameter to skip header rows in csv and file datasource [#97](https://github.com/rello/analytics/issues/97)
- Allow multiple paths in one JSON load [wiki](https://github.com/Rello/analytics/wiki/Datasource:-JSON)
- Favorite also for shared reports [#107](https://github.com/rello/analytics/issues/107)
- Various UI cleanups and performance enhancements

### Fixed
- SQL error for shared reports [#98](https://github.com/rello/analytics/issues/98)
- Datasource options field in DB too short [#78](https://github.com/rello/analytics/issues/78)
- JSON source not adding timestamp [#106](https://github.com/rello/analytics/issues/106)
- Error in occ- and scheduled load [#109](https://github.com/rello/analytics/issues/109)

## 3.2.0 - 2021-01-02
### Added
- Allow filter-permissions on shared reports [#77](https://github.com/rello/analytics/issues/77)
- NC21
- delete option in report menu

### Changed
- Filter Changed are not persisted automatically [#94](https://github.com/rello/analytics/issues/94)
- Disable dataload for non-internal reports [#74](https://github.com/rello/analytics/issues/74)
- more flexible clipboard import
- don´t load dashboard when accessing report directly

### Fixed
- Favorites dashboard not showing in app startscreen
- Missing language keys [#86](https://github.com/rello/analytics/issues/86)
  @[AleksovAnry](https://github.com/AleksovAnry)
- fr translations [#92](https://github.com/rello/analytics/issues/92)
  @[simmstein](https://github.com/simmstein)
- wording corrections
- shared reports showing multiple times
- image path for alternative app folder
- Advanced: Dataload tab does not load [#79](https://github.com/rello/analytics/issues/79)

## 3.1.0 - 2020-12-02
### Added
- event to register external datasources [#71](https://github.com/rello/analytics/issues/71)
- better datasource config using dropdowns

### Fixed
- share token not generated
- Chart: tooltips not showing
- an error occurred while using NC search [#70](https://github.com/rello/analytics/issues/70)
- database issue for shared reports [#69](https://github.com/rello/analytics/issues/69)
- dashboard error when widget not enabled

## 3.0.0 - 2020-10-01
### Added
- NC20 
- NC20 Dashboard Widget [#61](https://github.com/rello/analytics/issues/61)
- NC20 Search integration [#66](https://github.com/rello/analytics/issues/66)
- Sharing: user + groups [#60](https://github.com/rello/analytics/issues/60)
- API: use display names for upload [#58](https://github.com/rello/analytics/issues/58)
- API: accepting arrays [#55](https://github.com/rello/analytics/issues/55)
- API: delete data [#56](https://github.com/rello/analytics/issues/56)
- Report: mark reports as favorite [#63](https://github.com/rello/analytics/issues/63)
- translation: ru, ru_RU @[AleksovAnry](https://github.com/AleksovAnry)
- translations: fr [#67](https://github.com/rello/analytics/issues/67) @[simmstein](https://github.com/simmstein)
- headers for RegEx datasource @[AleksovAnry](https://github.com/AleksovAnry)
- Dataload: Purge Dataset [#47](https://github.com/rello/analytics/issues/47)

### Changed
- Enhanced datamodel [#59](https://github.com/rello/analytics/issues/59)
- new app icon

### Fixed
- Floating point numbers rounded off in Rest API calls [#57](https://github.com/rello/analytics/issues/57)

## 2.5.0 - 2020-10-01
### Added
- Sharing: user + groups [#60](https://github.com/rello/analytics/issues/60)
- API: use display names for upload [#58](https://github.com/rello/analytics/issues/58)
- API: accepting arrays [#55](https://github.com/rello/analytics/issues/55)
- API: delete data [#56](https://github.com/rello/analytics/issues/56)
- Report: mark reports as favorite [#63](https://github.com/rello/analytics/issues/63)
- translation: ru, ru_RU @[AleksovAnry](https://github.com/AleksovAnry)
- translation: fr [#67](https://github.com/rello/analytics/issues/67) @[simmstein](https://github.com/simmstein)
- headers for RegEx datasource @[AleksovAnry](https://github.com/AleksovAnry)
- Dataload: Purge Dataset [#47](https://github.com/rello/analytics/issues/47)

### Changed
- Enhanced datamodel [#59](https://github.com/rello/analytics/issues/59)
- new app icon

### Fixed
- Floating point numbers rounded off in Rest API calls [#57](https://github.com/rello/analytics/issues/57)

## 2.4.1 - 2020-07-10
### Fixed
- DB index too long

## 2.4.0 - 2020-07-09
### Added
- WhatsNew popup [#43](https://github.com/rello/analytics/issues/43)
- Chart: customisation per data-series [#44](https://github.com/rello/analytics/issues/44)
- Chart: combined charts [#44](https://github.com/rello/analytics/issues/44)
- Chart: secondary axis [#36](https://github.com/rello/analytics/issues/36)
- Report: persist filters [#36](https://github.com/rello/analytics/issues/36)

### Changed
- migration to QueryBuilder
- migration from database.xml to /Migration
- cleanup notification messages

## 2.3.1 - 2020-05-07
### Fixed
- various css improvements

## 2.3.0 - 2020-05-06
### Added
- Enable filters in reports [#41](https://github.com/rello/analytics/issues/41)

### Changed
- avoid multiple notifications for same threshold by replacing old ones
- shorten app name to 'Analytics'

### Fixed
- Thresholds not working in table [#39](https://github.com/rello/analytics/issues/39)
- Thresholds not accepting commas

## 2.2.3 - 2020-04-27
### Fixed
- appstore certificate issue

## 2.2.2 - 2020-04-27
### Fixed
- appstore certificate issue

## 2.2.1 - 2020-04-12
### Fixed
- advanced settings not showing

### Added
- NC19

## 2.2.0 - 2020-04-05
### Added
- chart type: doughnut
- advanced chart options [#30](https://github.com/rello/analytics/issues/30)
- print report layout [#34](https://github.com/rello/analytics/issues/34)

### Fixed
- handle German date format in input form
- regex not working - options field too short [#31](https://github.com/rello/analytics/issues/31)
- frontend doesn't respect the users timezone [#17](https://github.com/rello/analytics/issues/17)
- donate button [#35](https://github.com/rello/analytics/issues/35)

## 2.1.1 - 2020-03-12
### Fixed
- shorten notification text to fit iOS push banner
- double category labels [#28](https://github.com/rello/analytics/issues/28)
- column mismatch in dataload for git datasource
- data deletion simulation display bug

## 2.1.0 - 2020-03-11
### Added
- Datasource: JSON [#21](https://github.com/rello/analytics/issues/21) (e.g. [NC monitoring](https://github.com/Rello/analytics/wiki/Datasource:-JSON))
- occ command for executing dataloads [#16](https://github.com/rello/analytics/issues/16)
- Data deletion with wildcards
- Compatible with dark theme [#11](https://github.com/rello/analytics/issues/11)

### Changed
- Exchange Highcharts with Charts.js [#23](https://github.com/rello/analytics/issues/23)

### Fixed
- Removed incorrect error logs in API
- Notification parsed subject

## 2.0.0 - 2020-01-17
### Added
- NC 18 Flow integration [#10](https://github.com/rello/analytics/issues/10)
- Enhanced dataloads with scheduling [#13](https://github.com/rello/analytics/issues/13)
- Advanced configuration page for more options than sidebar [#12](https://github.com/rello/analytics/issues/12)
- Datasource: website grabber [#14](https://github.com/rello/analytics/issues/14)
- Thresholds for all datasource types (notifications just for database)
- Compatibility dark mode [#11](https://github.com/rello/analytics/issues/11)
- [Wiki](https://github.com/Rello/analytics/wiki)

### Changed
- link report in activity message
- redesign of backend (controllers; mappers)

### Fixed
- XSS risk in innerHTML

### Removed
- NC16

## 1.2.2 - 2019-12-15
### Fixed
- Notification missing setParsedSubject

## 1.2.1 - 2019-12-15
### Fixed
- Activity not always reported
- Notification pushed to wrong user
- Thresholds not read for external report types [#9](https://github.com/rello/analytics/issues/9)
- Notifications icon path

## 1.2.0 - 2019-12-14
### Added
- Thresholds: NC Notification and color coding [#5](https://github.com/rello/analytics/issues/5)

## 1.1.0 - 2019-12-05
### Added
- 'de' language files
- currency/unit formatting in datatable column
- CSV import format validation [#7](https://github.com/rello/analytics/issues/7)
- Assets blocked by uBlock origin [#8](https://github.com/rello/analytics/issues/8)
- favicon
- direct url to report
- chart type: area stacked

## 1.0.0 - 2019-12-01
### Added
- Initial version of Data Analytics
