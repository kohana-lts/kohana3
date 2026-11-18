# Configuration

The default configuration file is located in `MODPATH/auth/config/auth.php`. You should copy this file to `APPPATH/config/auth.php` and make changes there, in keeping with the [cascading filesystem](../kohana/files).

[Config merging](../kohana/config#config-merging) allows these default configuration settings to apply if you don't overwrite them in your application configuration file.

Name | Type | Default | Description
-----|------|---------|------------
driver | `string` | file | The name of the auth driver to use.
password_algo | `mixed` | PASSWORD_DEFAULT | The algorithm for `password_hash()`. `FALSE` uses the legacy HMAC hash.
password_options | `array` | array() | The options for `password_hash()`, for example `array('cost' => 12)`.
hash_method | `string` | sha256 | The HMAC function of legacy password hashes.
hash_key | `string` | NULL | The HMAC key of legacy password hashes. Without it, legacy hashes do not verify.

New passwords get a `password_hash()` hash. A login with a legacy HMAC hash still works when `hash_key` is set. The ORM driver then saves a new hash. The file driver cannot save, so replace legacy hashes in its `users` list with `Auth::instance()->hash($password)` values.
session_type | `string` | [Session::$default] | The type of session to use when storing the auth user.
session_key | `string` | auth_user | The name of the session variable used to save the user.
