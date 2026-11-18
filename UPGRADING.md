# Upgrade from Kohana 3.3.6

This file lists the changes that can affect an existing application. Most
security fixes use a secure default and have an opt-out. Read each section
before you deploy, and run your own tests.

## Requirements

- PHP 8.1 or later. The `mbstring` and `openssl` extensions are necessary.
- ext/mysql and ext/mcrypt are not necessary any more.

## Environment

- Set `KOHANA_ENV=PRODUCTION` (server variable or environment variable) on
  production servers. The example bootstrap reads both now.
- In PRODUCTION:
  - The error page shows only the HTTP status. The log keeps the details.
    `Kohana_Exception::$show_details = TRUE` shows the full page again.
  - The userguide and codebench modules add no routes. Set
    `'production' => TRUE` in `config/userguide.php` or `config/codebench.php`
    to keep them.
  - `index.php` does not run `install.php`.

## Encryption (Encrypt)

- `Encrypt` uses AES-256-CBC with HMAC-SHA256. New values start with `v2:`.
- Values that the old mcrypt version wrote do not decode by default. To read
  them during a migration, set `'legacy' => TRUE` in the `encrypt`
  configuration group, with the old `'cipher'` and `'mode'`. Only
  `rijndael-128` is supported. Re-encrypt the data, then remove `'legacy'`.
- Encrypted sessions (`'encrypted' => TRUE`) written by the old version do not
  decode. Users get a new, empty session one time, unless `'legacy'` is on.
- `MCRYPT_*` constants in configuration files still work: the class defines
  the ones it needs.

## Database

- `order_by()` accepts only `ASC`, `DESC` or nothing as the direction. Use
  `DB::expr()` for other SQL, for example `DB::expr('DESC NULLS LAST')`.
- `where()`, `having()` and `on()` accept only known operators. Add an
  operator with `Database_Query_Builder::$operators[] = '#>>';` or pass a
  `DB::expr()`. `join()` accepts only known JOIN types
  (`Database_Query_Builder_Join::$types`).
- PDO connections now quote identifiers. The quote character comes from the
  DSN driver (a backtick for MySQL, `"` for PostgreSQL and SQLite). A column
  name with SQL in it (for example `select('COUNT(id)')`) must use
  `DB::expr()`. Set `'identifier' => ''` in the database group for the old
  behavior.
- PDO MySQL: the `'charset'` value goes into the DSN. A charset in the DSN has
  priority.
- The `MySQL` type uses MySQLi. `'persistent' => TRUE` becomes the MySQLi
  `p:` host prefix.

## ORM

- `$model->values($data)` without the list of expected columns throws a
  `Kohana_Exception`. Give the list: `$model->values($data, array('title',
  'body'))`. Opt-out: `protected $_values_without_expected = TRUE;` in a model,
  or `ORM::$values_without_expected = TRUE;` for all models.
- `Model_Auth_User::create_user()` and `update_user()` without a column list
  set only `username`, `password` and `email`.

## Auth

- New passwords use `password_hash()` (`PASSWORD_DEFAULT`). Old HMAC hashes
  still log in when `'hash_key'` is set, and Auth_ORM then saves a new hash.
  Keep `'hash_key'` until all users have logged in one time.
- `Auth::hash()` output has a random salt. Code that compares
  `hash($password)` with a stored value must call
  `Auth::instance()->verify($password, $stored)`.
- Opt-out: `'password_algo' => FALSE` keeps the old HMAC hashes.
- Auth_File cannot save a new hash. Replace the hashes in the `users`
  configuration with `Auth::instance()->hash($password)` values.
- Widen the password column for longer hashes:
  - MySQL: `ALTER TABLE users MODIFY password VARCHAR(255) NOT NULL;`
  - PostgreSQL: `ALTER TABLE users ALTER COLUMN password TYPE varchar(255);`
- The autologin token table stores a hash of the token, and each auto-login
  replaces the token. Users with an old "remember me" cookie must log in one
  time. Code that read `$token->token` as the cookie value must call
  `$token->plain_token()`. PostgreSQL:
  `ALTER TABLE user_tokens ALTER COLUMN token TYPE varchar(40);`

## Sessions and cookies

- `Cookie::$httponly` is `TRUE` and the new `Cookie::$samesite` is `'Lax'`.
  Cross-site POST returns (payment callbacks, single sign-on) need
  `Cookie::$samesite = 'None'` and `Cookie::$secure = TRUE`. The native session
  cookie uses the same flags.
- Cookie sessions restore only `Model_User` objects. Other objects become
  `__PHP_Incomplete_Class`. Set `'allowed_classes'` in the `cookie` session
  group (`TRUE`, `FALSE` or a list of classes).
- A cookie session that is idle for longer than its lifetime (one day for a
  lifetime of 0) starts empty. Set `'max_age'` in seconds, or 0 to disable.
- `Session_Database` ids have 24 hex characters, as the documented column.
- `Security::check()` and `Cookie::get()` return `FALSE` or the default for
  array input.

## Requests, URLs and files

- `Request::$client_ip` comes from the right of X-Forwarded-For, and only when
  `REMOTE_ADDR` is a trusted proxy. `Request::$trusted_proxies` accepts CIDR
  ranges. Add your load balancers to the list.
- `Valid::url()` accepts only http and https. Pass `NULL` as the second
  argument for any scheme: `->rule('link', 'url', array(':value', NULL))`.
- `Feed::parse()` reads a local file only with the third argument `TRUE`.
- The curl and stream request clients fetch only http and https URLs. Set
  `CURLOPT_PROTOCOLS` in the client options for other protocols.
- `Upload::save()` without a file name uses a random name and drops blocked
  extensions such as `php` (`Upload::$blocked_extensions`). An explicit name
  with `/`, `\`, `..` or NUL throws.
- `Kohana::find_file()` returns `FALSE` for names with a `..` segment or NUL.
- `HTML::mailto()` escapes the address. `Security::strip_image_tags()` removes
  all image tags.
- `Text::random()` and `Security::token()` use `random_int()` and
  `random_bytes()`. The output formats do not change.

## Cache and log files

- New directories get mode 0755 and new files 0644 (`Kohana::$cache_dir_mode`,
  `Kohana::$cache_file_mode`, `Log_File::$dir_mode`, `Log_File::$file_mode`,
  and `'dir_mode'` in the file cache group). Before, they were world-writable.
  On existing installs, run `chmod -R o-w` on the cache and log directories one
  time. If the web server and the CLI write as different users, set
  group-writable modes such as 0775 and 0664.
- `Kohana::cache()` has a fourth parameter, `$allowed_classes`. An application
  class that overrides `Kohana::cache()` must add it.

## PHP 8 changes in the framework

- `Date::months()` uses `IntlDateFormatter` (or English names without intl) in
  place of `strftime()`.
- `ORM` implements `__serialize()` and `__unserialize()`. Old serialized models
  still load.
- `Kohana_Exception::handler()` and the internal request client handle PHP 8
  `Error` objects.
- Methods of `ArrayAccess`, `Iterator` and `Countable` classes have
  `#[\ReturnTypeWillChange]`, so subclasses keep their signatures.
