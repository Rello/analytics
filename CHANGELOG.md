# Changelog

## 3.0.1 - 2020-10-04
### Fixed
- share token not generated

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
- translations: fr [#67](https://github.com/rello/analytics/issues/67) @[simmstein](https://github.com/simmstein)
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