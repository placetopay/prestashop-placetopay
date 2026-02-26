# Prestashop Gateway to PlacetoPay

[PlacetoPay](https://www.placetopay.com) Plugin Payment for [Prestashop](https://www.prestashop.com)

For more information about the component and the functionalities it offers, visit the following link **[Prestashop-Placetopay](https://placetopay.dev/plugins/prestashop)**.

## Prerequisites

- `prestashop` >= 1.7.x _recommended: >= 8.x (supports 9.x)
- `php` >= 7.2.5 _recommended: >= 8.1_
- `ext-curl`
- `ext-json`
- `ext-mbstring`

## Compatibility Version

Supported PrestaShop versions (legacy 1.6 and below are no longer supported):

| PrestaShop | Plugin | PHP      | Comments                  |
|------------|--------|----------|---------------------------|
| 1.7.x      | 3.*    | >= 7.2.5 | Supported                 |
| 8.x.x      | 4.*    | >= 7.4   | Supported                 |
| 9.x.x      | 6.*    | >= 8.1   | Supported (recommended)   |

## Releases

Last releases from: [https://github.com/placetopay/prestashop-placetopay/releases/](https://github.com/placetopay/prestashop-placetopay/releases/) and [see installation process in Prestashop](https://addons.prestashop.com/en/content/21-how-to)

### Error Codes

| Code | Description                                    | Status      |
|------|------------------------------------------------|-------------|
| 1    | Create payments table failed                   |             |
| 2    | Add email column failed                        |             |
| 3    | Add id_request column failed                   |             |
| 4    | Add reference column failed                    |             |
| 5    | Update ipaddres column failed                  |             |
| 6    | Login and TranKey is not set                   |             |
| 7    | Payment is not allowed by pending transactions |             |
| 8    | Payment process failed                         |             |
| 9    | Reference (encrypt) was not found              |             |
| 10   | Reference (decrypt) was not found              |             |
| 11   | Id Request (decrypt) was not found             |             |
| 12   | Try to change payment without status PENDING   |             |
| 13   | PlacetoPay connection failed                   | @deprecated |
| 14   | Order related with payment not found           | @deprecated |
| 15   | Get payment in payment table failed            |             |
| 16   | Command not available in this context          |             |
| 17   | Access not allowed                             |             |
| 18   | Cart empty or already used                     |             |
| 19   | Update translation status failed               |             |
| 20   | Add installments (and others) column failed    |             |
| 99   | Un-known error, module not installed?          |             |
| 100  | Install process failed                         |             |
| 201  | Order id was not found                         |             |
| 202  | Order was not loaded                           |             |
| 301  | Customer was not loaded                        | @deprecated |
| 302  | Address was not loaded                         | @deprecated |
| 303  | Currency was not loaded                        | @deprecated |
| 401  | Create payment PlacetoPay failed               | @deprecated |
| 501  | Payload notification PlacetoPay was not valid  | @deprecated |
| 601  | Update status payment PlacetoPay fail          | @deprecated |
| 801  | Get order by id failed                         | @deprecated |
| 901  | Get last pending transaction failed            | @deprecated |
| 999  | Un-know error, details in: Database Logs       |             |

### SMTP Email

```mysql
USE prestashop;

SELECT name, value FROM ps_161_configuration WHERE name IN ('PS_MAIL_METHOD', 'PS_MAIL_SERVER', 'PS_MAIL_USER', 'PS_MAIL_PASSWD', 'PS_MAIL_SMTP_ENCRYPTION', 'PS_MAIL_SMTP_PORT')

UPDATE ps_configuration SET value='2' where name = 'PS_MAIL_METHOD';
UPDATE ps_configuration SET value='smtp.mailtrap.io' where name = 'PS_MAIL_SERVER';
UPDATE ps_configuration SET value='user' where name = 'PS_MAIL_USER';
UPDATE ps_configuration SET value='password' where name = 'PS_MAIL_PASSWD';
UPDATE ps_configuration SET value='off' where name = 'PS_MAIL_SMTP_ENCRYPTION';
UPDATE ps_configuration SET value='2525' where name = 'PS_MAIL_SMTP_PORT';
```

## Quality

During package development I try as best as possible to embrace good design and development practices, to help ensure that this package is as good as it can
be. My checklist for package development includes:

- Be fully [PSR1](https://www.php-fig.org/psr/psr-1/), [PSR2](https://www.php-fig.org/psr/psr-2/), and [PSR4](https://www.php-fig.org/psr/psr-4/) compliant.
- Include comprehensive documentation in README.md.
- Provide an up-to-date CHANGELOG.md which adheres to the format outlined at [keepachangelog](https://keepachangelog.com).
- Have no [phpcs](https://pear.php.net/package/PHP_CodeSniffer) warnings throughout all code, use `composer test` command.
