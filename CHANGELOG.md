# Changelog
- All notable changes to this project will be documented in this file.
- See [changelog structure](https://keepachangelog.com/en/0.3.0/) for more information of how to write perfect changelog.

## Release note
- Make sure what version is required for the client. Is it production or testing
- Make sure why developing, set DISABLE_CACHE to true in order for dependency injection loaded containers would change.
  Otherwise, they are in immutable state.
- When providing the zip , make sure there are no .git or var folder
- Install vendors using composer install --no-dev --optimize-autoloader
- Use existing vendor if exists in repository for compatability issues


## [3.1.1] - 2021-01-11

### Changed
- prestashop 1.7.7 controllers created for printing service
- order view page compatibility update, parameters changes, new hooks instantiated
- order add page new parameters added, logic changed for version compliance
- javascript changes for prestashop 1.7.7
- bootstrap update to version 4 in backoffice pages

## [3.1.2] - 2021-01-22

### Changed
- Show prestashop version in shipment reference

## [3.1.3] - 2021-01-29

### Changed
- Validations added for back office address form
- Functionality added, show API error messages at front end
- Shipment data validator bug fix 

## [3.1.4] - 2021-02-25

### Changed
- Set parcels from different countries functionality added
- Bugfix when no idcart in order page to select parcel

## [3.1.5] - 2021-03-17
### Changed
- Added shipping cost to dpd shipment
- Translations added for carriers on webservice change
- Map sequence loading improvements when prestashop js cache enabled

## [3.1.6] - 2021-04-20
### Changed
- Ajax request to save phone number on order opc carrier, number validation added
- Validations for empty phone number and invalid number
- Validations when no pudo selected
- Reload map on address country change
- Bugfix for missing pudo points added
- Fix for parcel update added, update all points
- Pudo service weights changed, Portugal and other countries added
- Show parcel terminal even tough delivery city is not correct
- Carrier translation update
- Vendor updated in zip folder, remove ports
- Map bug fix when off map script loaded
## [3.1.7] - 2021-06-07
### Changed
- Import parcels function changed on cron parcel update, take countries only from zone range functionality added.