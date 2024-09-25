# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com)
and this project adheres to [Semantic Versioning](http://semver.org).

## [Unreleased]

### [4.0.5 (2024-09-26)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.4...4.0.5)

### Added
- Add brand image in payment option

## [4.0.4 (2024-06-20)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.3...4.0.4)

## Added

- Add information in the readme.

## [4.0.4 (2024-06-05)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.3...4.0.4)

## Fixed

- Fix method reset for uninstall and install the component.

## [4.0.3 (2024-01-08)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.2...4.0.3)

## Removed

- Remove docker installation options (and update readme).

## Updated

- Change method to get installments of the payment.
- Change message of approved payment and remove default messages in the payment detail.

## Fixed

- Fix Order details view in show backend administration.

## [4.0.2 (2023-12-05)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.1...4.0.2)

### Fixed

- Resolve endpoint by Client.

## [4.0.1 (2023-11-30)](https://github.com/placetopay/prestashop-placetopay/compare/4.0.0...4.0.1)

### Updated

- Hide lightbox option for CL.

## [4.0.0 (2023-11-01)](https://github.com/placetopay/prestashop-placetopay/compare/3.8.2...4.0.0)

### Added

- Enable support for Prestashop >= 8.0.

### Removed

- Drop support for Prestashop <= 1.6.

## [3.8.2 (2023-10-27)](https://github.com/placetopay/prestashop-placetopay/compare/3.8.1...3.8.2)

### Added

- Hide default payment approved message when it is not.

## [3.8.1 (2023-08-23)](https://github.com/placetopay/prestashop-placetopay/compare/3.8.0...3.8.1)

### Added

- Add lightbox to process payments.

## [3.8.0 (2023-07-05)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.6...3.8.0)

### Added

- Add branding by client country (it's defined in PS).
- Add modifiers support: UY.

### Removed

- Remove currency validation, now it depends from response of WC.
- Remove payments methods enable, now it depends from configurations in WC.
- Remove connection type configuration, only supports REST messages (using JSON).

## [3.7.6 (2023-05-30)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.5...3.7.6)

### Fixed

- Fix total amount and base amount of the payment.

## [3.7.5 (2023-04-24)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.4...3.7.5)

### Fixed

- Fix amount base of the payment. (code overwrite in commit)

## [3.7.4 (2023-03-30)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.3...3.7.4)

### Added

- Add UY country as option in country list.

## [3.7.3 (2023-03-10)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.2...3.7.3)

### Fixed

- Fix messages showed after declined  or canceled payments, always it showed success message

## [3.7.2 (2022-10-28)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.1...3.7.2)

### Fixed

- Fix amount base of the payment. Now the base has the value of the shipping and the amount of the products.
- Fix locale sent to request in WC

## [3.7.1 (2022-09-15)](https://github.com/placetopay/prestashop-placetopay/compare/3.7.0...3.7.1)

### Added
- Add countries HN, BZ and PR as option in country list

### Fixed
- Fix sonda resolution when order status new after payment approved has shipped flag enable

## [3.7.0 (2022-07-07)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.5...3.7.0)

### Added
- Add new countries in Settings: HN and BZ
- Add header for platform source

### Updated
- Update defaults values by country setup after first installation

### Fixed
- Fix headers used in gateways requests and queries
- Fix installments error when acquirer don't use them (CL)
- Fix logger file in debug mode enable
- Fix sonda process to support (old) PrestaShop 1.6.1.*

## [3.6.5 (2022-05-05)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.4...3.6.5)

### Added
- Headers when processing pending transactions
- Override dnetix package due to conflicts with guzzle (v5 ~ v7)

## [3.6.4 (2022-05-05)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.3...3.6.4)

### Added
- Reference to order note

## [3.6.3 (2022-04-22)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.2...3.6.3)

### Updated
- Update image url on return payment view (details)

## [3.6.2 (2022-04-19)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.1...3.6.2)

### Updated
- Update translations for chile

## [3.6.1 (2022-04-18)](https://github.com/placetopay/prestashop-placetopay/compare/3.6.0...3.6.1)

### Updated
- Order note for payment method name

## [3.6.0 (2022-04-12)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.9...3.6.0)

### Added
- Hook to display payment details on the order admin

## [3.5.9 (2022-04-08)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.8...3.5.9)

### Updated
- Payment button image on prestashop 1.6

## [3.5.8 (2022-03-03)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.7...3.5.8)

### Fixed
- Error with base tax total

## [3.5.7 (2022-03-03)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.6...3.5.7)

### Updated
- Ecuador test endpoint.

## [3.5.6 (2022-02-08)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.5...3.5.6)

### Updated
- Restricted countries filter is removed.

## [3.5.5 (2021-08-31)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.4...3.5.5)

### Added
- Panama and Puerto Rico country codes.

## [3.5.4 (2021-08-31)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.3...3.5.4)

### Updated
- Branding name

## [3.5.3 (2021-08-20)](https://github.com/placetopay/prestashop-placetopay/compare/3.5.0...3.5.3)

### Updated
- Chile endpoints
- dnetix/redirection package

## [3.5.0 (2021-05-06)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.5...3.5.0)

### Added
- Support to Chile country
- Custom payment button image

## [3.4.6 (2021-02-02)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.5...3.4.6)

### Updated
- dnetix/redirection package

## [3.4.5 (2020-09-04)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.4...v3.4.5)

### Updated
- Brand name

### Fixed
- Error when there is a declined transaction
- Error when returning from web checkout to eCommerce

## [3.4.4 (2020-09-04)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.3...v3.4.4)

### Updated
- Update Country list
- Update placetopay production endpoint
- Update logo

## [3.4.3 (2018-09-12)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.2...v3.4.3)

### Added
- Compliancy message

### Updated
- Update translations

### Fixed
- Fix bug versions compliancy

## [3.4.2 (2018-08-23)](https://github.com/placetopay/prestashop-placetopay/compare/v3.4.0...v3.4.2)

### Updated
- Update dependencies guzzle/guzzle from 5.3.2 => 5.3.3
- Update README file with [mailtrap.io](https://mailtrap.io/)
- Update format name logfile, it is: \[dev|prod\]_YYYYMMDD_placetopayment.log
- Update commands in Makefile
- Update max version's PrestaShop supported: PS 1.7.4.2

### Fixed
- Fix translations in: `es` and `gb` locales
- Fix bug getting order by cart id, fail if order not exist

### Removed
- Stock re-inject option setup in PS 1.6, deprecated in PS 1.5

## [3.4.0 (2018-07-25)](https://github.com/placetopay/prestashop-placetopay/compare/v3.3.0...v3.4.0)

### Added
- Add payment method selector to restrict in redirection page
- Add currency validator before request PlacetoPay

### Updated
- Change alerts, now are show in top of page
- Save errorCode in log database as objectId and updated error codes
- Improve logs when connection to service failed
- Show more configuration in sonda request

## [3.3.0 (2018-07-19)](https://github.com/placetopay/prestashop-placetopay/compare/v3.2.7...v3.3.0)

### Added
- Exception and any others errors are visible from PS back-office System Logs
- Added object type in errors save in logs prestashop

### Updated
- Minimum version support now is PS 1.6.0.5
- Change PaymentLogger::log function
- Update error code, catalog table create

## [3.2.7 (2018-07-17)](https://github.com/placetopay/prestashop-placetopay/compare/v3.2.6...v3.2.7)

### Updated
- Simple fix path applied, improve support
- Not overwrite default country in docker installation
- Allow installation in default country (gb)

### Fixed
- Fix cs
- Fix log path in PS >= 1.7.4.0
- Fix guzzle in PS >= 1.7.4.0, downgrade from 6.3.3 to 5.3.2

## [3.2.6 (2018-07-13)](https://github.com/placetopay/prestashop-placetopay/compare/v3.2.5...v3.2.6)

### Fixed
- Fix message error (in database) on failed transaction, before it is not was updated
- Fix translations, index error in files

### Updated
- Update dependencies dnetix/redirection from 0.4.3 => 0.4.5 (Add extra currencies)
- Added code sniffer validations

## [3.2.5 (2018-05-15)](https://github.com/placetopay/prestashop-placetopay/compare/v3.2.4...v3.2.5)

### Fixed
- Fix return page, now it depends of status payment

## [3.2.4 (2018-04-27)](https://github.com/placetopay/prestashop-placetopay/compare/v3.1.0...v3.2.4)

### Added
- Allowed set a Custom Connection URL to connect to payment service in PlacetoPay

### Fixed
- Fix bug in Windows System with Apache Server installed (Separator)
- Fix bug in English translations files
- Fix bug on update status, add validation to request object

### Updated
- Update message trace on development and improve code type hint and vars name
- Remove translations not used
- Update dependencies to stable versions, thus:
    psr/http-message (1.0.1)
    guzzlehttp/psr7 (1.4.2)
    guzzlehttp/promises (v1.3.1)
    guzzlehttp/guzzle (6.3.3)
    dnetix/redirection (0.4.3)

## [3.1.0 (2018-03-11)](https://github.com/placetopay/prestashop-placetopay/compare/v3.0.2...v3.1.0)

### Added
- Add makefile with docker
- Add validation in notification to signature
- Add extra security, to show setup is necesary send last 5 characteres of login to show data
- Add skipResult setup to skip last screen in payment process on payment
- Add PlacetoPay brand in PS >= 1.7 in payment options form
- Add validation to execute sonda process, from browser not is available, only CLI

### Fixed
- Fix bug in way to get URL base
- Fix bug when transaction not is approved not update reason and reaso…
- Fix bug updating description in payments rejected (error in bd)
- Fix bug in value assigned of stock reinject on update
- Fix errors when module is re-install, catch error generate by rename columns
- Fix error when module is executed but it is not installed yet (from sonda process)
- Fix bug in installation on PS 1.7.2.5, logo.png was change path
- Fix Skip class when some are not found in PS 1.7 loader

### Updated
- Update dependency redirection from 0.4.1 to 0.4.2
- Update dependencies guzzle from 6.2 to 6.3
- Update message trace on development
- Update translation changing Place to Pay -> PlacetoPay

### Created
- Create CONTRIBUTING.md
- Create LICENSE

## [3.0.2 (2018-01-10)](https://github.com/placetopay/prestashop-placetopay/compare/v3.0.1...v3.0.2)

### Fixed
- Fix bug in notification process, column name error

## [3.0.1 (2017-12-13)](https://github.com/placetopay/prestashop-placetopay/compare/v3.0.0...v3.0.1)

### Fixed
- Fix bug in utf8 translations in spanish in some installations in Prestashop < 1.7

## [3.0.0 (2017-12-06)](https://github.com/placetopay/prestashop-placetopay/compare/v2.6.4...v3.0.0)

### Added
- Add compatibility with Prestashop >= 1.7

## [2.6.4 (2017-12-01)](https://github.com/placetopay/prestashop-placetopay/releases/tag/v2.6.4)

### Fixed
- Fixed bug in Windows Server Systems
