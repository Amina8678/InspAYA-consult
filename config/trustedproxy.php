<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies
|--------------------------------------------------------------------------
|
| Read by Laravel's TrustProxies middleware at request time, as a fallback to
| anything set in code. This must live here and not in bootstrap/app.php:
| that file runs before .env is loaded, so env() there only ever sees real
| process environment variables, never the .env file.
|
| Blank means "trust no proxy". See docs/admin-auth.md "Trusted proxies".
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
