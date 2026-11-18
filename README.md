# Kohana 3.3, maintained fork with security fixes and PHP 8 support

This repository is an unofficial, community-maintained fork of the
[Kohana PHP framework](https://github.com/kohana/kohana) 3.3. Upstream
development has stopped. The last release is 3.3.6 (July 2016). This fork
keeps 3.3.6 applications running on current PHP versions and fixes the
security problems that people found after the last release.

The fork is not a new framework version. It changes as little as possible.
Read [UPGRADING.md](UPGRADING.md) before you replace the files of an existing
application: some security fixes change default behavior.

## Status

| Item | Value |
|------|-------|
| Base | Kohana 3.3.6, all official modules |
| PHP | 8.1 to 8.5. Tested locally on 8.5 (with MySQL 8.4) and in Docker on 8.1 to 8.4 (without the MySQL tests). The CI workflow covers all five. |
| Security fixes | See [SECURITY.md](SECURITY.md) |
| Behavior changes | See [UPGRADING.md](UPGRADING.md) |

## What is in this repository

The upstream project used Git submodules. This repository contains all of
them in one tree, so one checkout gives a complete framework:

| Directory | Upstream repository | Upstream tag |
|-----------|---------------------|--------------|
| `/` (application, index.php) | kohana/kohana | v3.3.6 |
| `system/` | kohana/core | v3.3.6 |
| `modules/auth/` | kohana/auth | v3.3.6 |
| `modules/cache/` | kohana/cache | v3.3.6 |
| `modules/codebench/` | kohana/codebench | v3.3.6 |
| `modules/database/` | kohana/database | v3.3.6 |
| `modules/image/` | kohana/image | v3.3.6 |
| `modules/minion/` | kohana/minion | v3.3.6 |
| `modules/orm/` | kohana/orm | v3.3.6 |
| `modules/unittest/` | kohana/unittest | v3.3.6 |
| `modules/userguide/` | kohana/userguide | v3.3.6 |

The first commit is the unchanged upstream code (tag `upstream-3.3.6`). Its
message lists the upstream commit of each part. To see every change of this
fork, run:

    git diff upstream-3.3.6 main

Note: the import commit missed `modules/userguide/vendor/markdown`, because a
`.gitignore` file of the module ignores `vendor/`. The second commit adds these
files unchanged. A diff against `upstream-3.3.6` thus shows them as new files.

## Requirements

- PHP 8.1 or later
- The `mbstring` and `openssl` extensions
- `mysqli` or `pdo_mysql` for MySQL, `pdo_sqlite` for the SQLite tests
- `intl` is optional. Without it, `Date::months()` gives English month names.

The `mysql` extension does not exist since PHP 7.0. The `MySQL` database type
now uses MySQLi, so existing configurations keep working.

## Use it in an application

Replace the `system/` and `modules/` directories of your application with the
directories of this repository. Then read [UPGRADING.md](UPGRADING.md) and
update your configuration.

Set `Kohana::$environment` to `Kohana::PRODUCTION` on production servers. The
bootstrap reads it from the `KOHANA_ENV` server variable or environment
variable. Several protections depend on it (error pages, development modules,
`install.php`).

## Run the tests

    composer install
    vendor/bin/phpunit

The MySQL driver tests run only when you set these environment variables:
`KOHANA_TEST_MYSQL_HOST`, `KOHANA_TEST_MYSQL_PORT`, `KOHANA_TEST_MYSQL_USER`,
`KOHANA_TEST_MYSQL_PASSWORD` and `KOHANA_TEST_MYSQL_DATABASE`. For example:

    docker run -d --name kohana-mysql -p 127.0.0.1:3306:3306 \
        -e MYSQL_ROOT_PASSWORD=test -e MYSQL_DATABASE=kohana_test mysql:8.4

The upstream tests used PHPUnit 3.7. This fork runs them on PHPUnit 9.6.
`Unittest_TestCase` keeps the old assertions that the tests use as small
compatibility methods.

## Report a security problem

Do not open a public issue for a security problem. Send a private report to
the maintainers of this repository (for example a GitHub security advisory).

## License

Kohana is released under the BSD license. See [LICENSE.md](LICENSE.md). The
copyright of the original code stays with the Kohana Team.
