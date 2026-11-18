# Security fixes

This file lists the security problems in Kohana 3.3.6 that this fork fixes,
and the problems that are still open. Each fix has a test that failed before
the fix (exceptions are marked).

Labels:

- **CVE**: a CVE identifier exists (source: NVD).
- **Reported**: a public report exists, but no CVE.
- **Hardening**: found in a code review of this fork. No earlier public report.

To report a new problem, see [README.md](README.md#report-a-security-problem).

## Fixed

| ID | Problem | Label | Severity | Commit |
|----|---------|-------|----------|--------|
| K3-01 | SQL injection through the `order_by()` direction ([CVE-2019-8979](https://nvd.nist.gov/vuln/detail/CVE-2019-8979)) | CVE | High | `c88abc4` |
| K3-02 | Raw SQL from the `where()`/`having()`/`on()` operator and the JOIN type. PDO did not quote identifiers | Hardening | Medium | `91f26d4` |
| K3-03 | XSS: `Security::strip_image_tags()` bypass ([CVE-2016-10510](https://nvd.nist.gov/vuln/detail/CVE-2016-10510), [kohana/kohana#107](https://github.com/kohana/kohana/issues/107)) | CVE | Medium | `d51747e` |
| K3-04 | Any file read through the userguide `guide-media` route (path traversal) | Reported (koseven#549) | High | `40b1c9b`, `386feed` |
| K3-05 | Reflected XSS and creation of any class in codebench | Hardening | Medium | `78e3dd5`, `386feed` |
| K3-06 | `Encrypt` had no MAC (malleable ciphertext) and needed mcrypt, which PHP 7.2 removed ([kohana/core#686](https://github.com/kohana/core/issues/686)) | Reported | High | `65cfbd6` |
| K3-07 | The error page showed source, trace, `$_SERVER` and `$_COOKIE` in production | Hardening | Medium | `02b07c7` |
| K3-08 | `Session_Cookie` unserialized client data with all classes, and old cookies stayed valid forever | Hardening | Medium | `6c62952` |
| K3-09 | Cookies had no HttpOnly and no SameSite attribute | Hardening | Medium | `2358232` |
| K3-10 | Auth hashed passwords with a fast unsalted HMAC and compared with `===` | Hardening | Medium | `7569ed0` |
| K3-11 | The autologin token was not rotated, was stored in plain text and came from `mt_rand()` | Hardening | Low | `93b2359` |
| K3-12 | `Text::random()`, `Security::token()` and `Session_Database` ids did not use a CSPRNG | Hardening | Low to medium | `4848e20`, `5f8d763` |
| K3-13 | Client IP spoofing through the left-most X-Forwarded-For entry and Client-IP | Reported (koseven#111) | Medium | `5c7caef` |
| K3-14 | `Valid::url()` accepted `javascript:`, `data:` and `file:` URLs | Hardening | Medium | `4808894` |
| K3-15 | SSRF and local file read through `Feed::parse()` and the request clients | Reported (koseven#547) | Low to medium | `c437ae4` |
| K3-16 | `Upload::save()` kept the client file name and extension (for example `.php`) | Hardening | Medium | `bbf0cd7` |
| K3-17 | ORM mass assignment: `values()` without a column list set every column | Hardening | Medium | `4765066` |
| K3-18 | World-writable cache and log files, and `unserialize()` of cache data with all classes | Hardening | Low to medium | `931955e` |
| K3-19 | Array input (`token[]=`) caused a TypeError in `Security::check()` and `Cookie::get()` | Hardening | Low | `e98c0c4` |
| K3-20 | `HTML::mailto()` did not escape the address | Hardening | Low | `8ace677` |
| K3-21 | `Response::send_file()` did not sanitize the file name in Content-Disposition | Hardening | Low | `d95f66b` |
| K3-22 | PDO MySQL set the charset with `SET NAMES`, not in the DSN | Hardening | Low | `aa459a8` |
| K3-24 | `index.php` ran `install.php` in production | Hardening | Low | `37d59b7` |
| K3-26 | `Kohana::find_file()` accepted names with `..` (path traversal through views and messages) | Hardening | Medium | `b28cff2` |

Notes:

- K3-03: NVD says "before 3.3.6", but 3.3.6 only marked the method as
  deprecated. The bad regular expression was still in 3.3.6.
- K3-01 and K3-03 have no upstream fix. The other fixes are new in this fork,
  except where a commit message names an upstream or Koseven commit.
- Tests that did not fail before the fix:
  - `Security::token()`: the old fallback does not run when OpenSSL is loaded.
  - K3-16 and K3-21: the tests failed before the fix only because the new
    methods did not exist.
- K3-24 has no automated test. It was checked by hand with the PHP built-in
  server.

## PHP 8 problems with a security effect

These are compatibility fixes, but without them a security function failed:

- `Kohana_Exception::handler()` accepted only `Exception`. A PHP 8 `Error`
  gave a second fatal error and no error page (`df50cfe`, `9591681`).
- `ORM::has()` threw a TypeError on PHP 8, so every Auth_ORM login failed
  (`39fe5a7`).
- The `MySQL` database driver used ext/mysql, which PHP 7.0 removed
  (`78153f8`).

## Open

| ID | Problem | Severity | Why it is open |
|----|---------|----------|----------------|
| K3-23 | The Imagick driver lets ImageMagick decode the file. `Image::factory()` first checks the file with `getimagesize()`, which rejects SVG and MVG. | Low | No test environment with Imagick. Harden the ImageMagick `policy.xml` on the server. |
| K3-25 | `HTTP::redirect()` follows absolute URLs. | Low | Only an open redirect when the application passes user input. Check the target in the application. |
| - | `Auth::complete_login()` stores the full user model in the session. With cookie sessions and `'encrypted' => FALSE`, the client can read it (base64). | Low | A change breaks code that reads the session user. Set `'encrypted' => TRUE` for cookie sessions. |
| - | `Upload::type()` checks only the extension, not the content. | Low | A content check changes what the rule means. |
| - | A login for an unknown user name returns faster than a login with a wrong password (user name enumeration by timing). | Low | Not changed from upstream. |
| - | The userguide module bundles jQuery 1.4.4, which has known CVEs. | Low | The module is a development tool. Its routes are off in production. |

## Checked, not a problem in 3.3.6

- CVE-2014-8684 (cookie signature timing): fixed upstream in 3.3.3.
- Host header injection: fixed upstream in 3.3.4 (`URL::is_trusted_host()`).
- `limit()` and `offset()`: the query builder casts them to integers.
- Session fixation: `Auth::complete_login()` and `logout()` regenerate the
  session id.
- Controller dispatch: only public `action_*` methods are reachable.
