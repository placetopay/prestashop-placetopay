# Prestashop Gateway to PlacetoPay

[PlacetoPay][1] Plugin Payment for [Prestashop][2]

## Prerequisites

- `prestashop` >= 1.6.1 _recommended: >= 1.7.8_
- `php` >= 7.1 _recommended: >= 7.4_
- `ext-curl`
- `ext-soap`
- `ext-json`
- `ext-mbstring`

## Compatibility Version

| PrestaShop | Plugin  | PHP          | End of Life                                                                               | Comments                          |
|------------|---------|--------------|-------------------------------------------------------------------------------------------|-----------------------------------|
| 1.5.x      | ~2.6.4  | ~5.6         | [July, 2016](https://www.prestashop.com/en/blog/prestashop-security-release)              | `@unmanteined`                    |
| 1.6.0      | >=2.6.4 | ~5.6         | [June, 2019](https://www.prestashop.com/en/blog/maintenance-extension-prestashop-1-6)     | 1.6.0.5 / 1.6.0.14 `@unmanteined` |
| 1.6.1      | >=2.6.4 | >=5.6 <= 7.1 | [June, 2019](https://www.prestashop.com/en/blog/maintenance-extension-prestashop-1-6)     | 1.6.1.0 / 1.6.1.24 `@deprecated`  |
| 1.7.0      | 3.*     | ~7.1         | [November, 2018](https://build.prestashop.com/news/announcing-our-2017-release-schedule/) | 1.7.0.0 / 1.7.0.6  `@deprecated`  |
| 1.7.1      | 3.*     | ~7.1         | [April, 2019](https://build.prestashop.com/howtos/misc/2017-release-schedule/)            | 1.7.1.0 / 1.7.1.2  `@deprecated`  |
| 1.7.2      | 3.*     | ~7.1         | [July, 2019](https://build.prestashop.com/howtos/misc/2017-release-schedule/)             | 1.7.2.0 / 1.7.2.5  `@deprecated`  |
| 1.7.3      | 3.*     | ~7.1         | [February, 2020](https://build.prestashop.com/howtos/misc/2017-release-schedule/)         | 1.7.3.0 / 1.7.3.4  `@deprecated`  |
| 1.7.4      | 3.*     | ~7.1         | [July, 2020][4]                                                                           | 1.7.4.0 / 1.7.4.4  `@deprecated`  |
| 1.7.5      | 3.*     | >=7.1 <= 7.2 | [December, 2020][4]                                                                       | 1.7.5.0 / 1.7.5.2  `@deprecated`  |
| 1.7.6      | 3.*     | >=7.1 <= 7.2 | [July, 2021][4]                                                                           | 1.7.6.0 / 1.7.6.9  `@deprecated`  |
| 1.7.7      | 3.*     | >=7.1 <= 7.3 | December, 2022                                                                            | 1.7.7.0 / 1.7.7.8  `@deprecated`  |
| 1.7.8      | 3.*     | >=7.1 <= 7.4 | September, 2023                                                                           | 1.7.8.0 / 1.7.8.8  `@deprecated`  |
| 8.x.x      | 4.*     | >>= 7.4      | March, 2024                                                                               | 8.0.x / 8.1.x      `@manteined`   |

> More information: [Prestashop End Support for obsolete PHP versions][4]

View releases [here][3]

## Installation in Production

### Without CLI

Get module .zip from [https://dev.placetopay.com/web/plugins/](https://dev.placetopay.com/web/plugins/) and [see process in Prestashop](https://addons.prestashop.com/en/content/21-how-to)

### With CLI and composer

Create `placetopaypayment` folder (this is required, with this name)

```bash
mkdir /var/www/html/modules/placetopaypayment
```

Clone Project in modules

```bash
git clone https://github.com/placetopay/prestashop-gateway.git /var/www/html/modules/placetopaypayment
```

Set permissions and install dependencies with composer

```bash
cd /var/www/html/modules/placetopaypayment \
    && sudo setfacl -dR -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \
    && sudo setfacl -R -m u:www-data:rwX -m u:`whoami`:rwX `pwd` \
    && composer install --no-dev
```
> Don't install dev dependencies

## Error Codes

| Code | Description                                    |
|------|------------------------------------------------|
| 1    | Create payments table failed                   |
| 2    | Add email column failed                        |
| 3    | Add id_request column failed                   |
| 4    | Add reference column failed                    |
| 5    | Update ipaddres column failed                  |
| 6    | Login and TranKey is not set                   |
| 7    | Payment is not allowed by pending transactions |
| 8    | Payment process failed                         |
| 9    | Reference (encrypt) was not found              |
| 10   | Reference (decrypt) was not found              |
| 11   | Id Request (decrypt) was not found             |
| 12   | Try to change payment without status PENDING   |
| 13   | PlacetoPay connection failed                   |
| 14   | Order related with payment not found           |
| 15   | Get payment in payment table failed            |
| 16   | Command not available in this context          |
| 17   | Access not allowed                             |
| 18   | Cart empty or already used                     |
| 99   | Un-known error, module not installed?          |
| 100  | Install process failed                         |
| 201  | Order id was not found                         |
| 202  | Order was not loaded                           |
| 301  | Customer was not loaded                        |
| 302  | Address was not loaded                         |
| 303  | Currency was not loaded                        |
| 401  | Create payment PlacetoPay failed               |
| 501  | Payload notification PlacetoPay was not valid  |
| 601  | Update status payment PlacetoPay fail          |
| 801  | Get order by id failed                         |
| 901  | Get last pending transaction failed            |
| 999  | Un-know error, details in: Database Logs       |

## Installation in Development

If you are a developer, please continue reading, else, that is all.

### With CLI and composer

```bash
cd /var/www/html/modules/placetopaypayment \
    && composer install --no-dev
```

### With Docker

Install PrestaShop 1.6 (latest in 1.6 branch) with PHP 5.6 (and MySQL 5.7). In folder of project;

```bash
cd /var/www/html/modules/placetopaypayment
make install
```

Then... (Please wait few minutes, while install ALL and load Apache :D to continue), you can go to

- [store](http://localhost:8787)
- [admin](http://localhost:8787/adminstore)

> If server return error code (400, 404, 500) you can status in docker logs until that installation process end, use:

```bash
make logs-prestashop
```

__Preshtashop Admin Access__

- email: demo@prestashop.com
- password: prestashop_demo

__MySQL Access__

- user: root
- password: admin
- database: prestashop

See details in `docker-compose.yml` file or run `make config` command

#### Customize docker installation

Default versions

- PrestaShop: 1.6
- PHP: 5.6
- MySQL: 5.7

Others installation options are [here][5], You can change versions in `.env` file

```bash
# PrestaShop 1.7 with PHP 7.0
PS_VERSION=1.7-7.0

# PrestaShop 1.6.1.1 with PHP 5.6
PS_VERSION=1.6.1.1

# PrestaShop latest with PHP 5.6 and MySQL 5.5
PS_VERSION=latest
MYSQL_VERSION=5.5
```

#### Binding ports

Ports by default in this installation are

- Web Server (`WEB_PORT`): 8787 => 80
- Database (`MYSQL_PORT`): 33060 => 3306

> You can change versions in `.env` file

#### Used another database in docker

You can override setup in docker, rename `docker-compose.override.example.yml` to `docker-compose.override.yml` and [customize](https://store.docker.com/community/images/prestashop/prestashop) your installation, by example

```yaml
version: "3.2"

services:
  # This service is shutdown
  database:
    entrypoint: "echo true"

  prestashop:
    environment:
      # IP Address or name from database to use
      DB_SERVER: my_db
```

### Setup Module

Install and setup you `login` and `trankey` in your [store](http://localhost:8787/adminstore)!

Maybe you need to setup on shipping carriers.

Enjoy development and testing!

### SMTP Email

Change email configuration to use [mailtrap.io][6] in development

```mysql
USE prestashop;

UPDATE ps_configuration SET value='2' where name = 'PS_MAIL_METHOD';
UPDATE ps_configuration SET value='smtp.mailtrap.io' where name = 'PS_MAIL_SERVER';
UPDATE ps_configuration SET value='user' where name = 'PS_MAIL_USER';
UPDATE ps_configuration SET value='password' where name = 'PS_MAIL_PASSWD';
UPDATE ps_configuration SET value='off' where name = 'PS_MAIL_SMTP_ENCRYPTION';
UPDATE ps_configuration SET value='2525' where name = 'PS_MAIL_SMTP_PORT';
```

### Troubleshooting

If shop is not auto-installed, then rename folder `xinstall` in container and installed from [wizard](http://localhost:8787/install)

```bash
make bash-prestashop
mv xinstall install
```

> This apply to last versions from PrestaShop (>= 1.7)

### Compress Plugin As Zip File

In terminal run

```bash
make compile
```

Or adding version number in filename to use

```bash
make compile PLUGIN_VERSION=_X.Y.Z
```

## Quality

During package development I try as best as possible to embrace good design and development practices, to help ensure that this package is as good as it can
be. My checklist for package development includes:

- Be fully [PSR1][7], [PSR2][8], and [PSR4][7] compliant.
- Include comprehensive documentation in README.md.
- Provide an up-to-date CHANGELOG.md which adheres to the format outlined
    at [keepachangelog][10].
- Have no [phpcs][11] warnings throughout all code, use `composer test` command.

[1]: https://www.placetopay.com
[2]: https://www.prestashop.com
[3]: https://github.com/placetopay/prestashop-gateway/releases
[4]: https://build.prestashop.com/news/announcing-end-of-support-for-obsolete-php-versions/
[5]: https://store.docker.com/community/images/prestashop/prestashop/tags
[6]: https://mailtrap.io/
[7]: https://www.php-fig.org/psr/psr-1/
[8]: https://www.php-fig.org/psr/psr-2/
[9]: https://www.php-fig.org/psr/psr-4/
[10]: https://keepachangelog.com
[11]: https://pear.php.net/package/PHP_CodeSniffer
