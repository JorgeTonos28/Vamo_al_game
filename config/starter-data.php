<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Starter Data
    |--------------------------------------------------------------------------
    |
    | DatabaseSeeder now stays safe for upgrades: it only seeds structural
    | defaults unless starter data is explicitly enabled. This lets production
    | run migrate + seed without injecting demo users or sample leagues.
    |
    | Local environments can keep demos on by default, or run:
    | php artisan db:seed --class=LocalStarterDataSeeder
    |
    */
    'enabled' => env('APP_ENABLE_STARTER_DATA', env('APP_ENV', 'production') === 'local'),
];
