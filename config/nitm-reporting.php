<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Nitm Reporting will be accessible from. If the
    | setting is null, Nitm Reporting will reside under the same domain as the
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('NITM_REPORTING_DOMAIN', null),

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Standalone Viewer Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Nitm Reporting will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('NITM_REPORTING_PATH', 'nitm/reporting'),

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting API route config
    |--------------------------------------------------------------------------
    |
    | This is the configuration parameter for configuring the api routes for retrieving reports
    |
    */

    'api-route' => [
        'domain' => env('NITM_REPORTING_DOMAIN', null),
        'name' => env('NITM_REPORTING_API_ROUTE_NAME', 'reporting'),
        'controller' => env('NITM_REPORTING_API_ROUTE_CONTROLLER', 'Api\ReportingController'),
        'group' => [
            'prefix' => env('NITM_REPORTING_API_PREFIX', 'api/reporting'),
            'namespace' => env('NITM_REPORTING_API_NAMESPACE', 'App\Http\Controllers')
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the storage driver that will
    | be used to store Nitm Reporting's data. In addition, you may set any
    | custom options as needed by the particular driver you choose.
    |
    */

    'driver' => env('NITM_REPORTING_DRIVER', 'database'),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mongodb'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Master Switch
    |--------------------------------------------------------------------------
    |
    | This option may be used to disable all Nitm Reporting watchers regardless
    | of their individual configuration, which simply provides a single
    | and convenient way to enable or disable Nitm Reporting data storage.
    |
    */

    'enabled' => env('NITM_REPORTING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Nitm Reporting route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'middleware' => [
        'web'
    ],

    /*
    |--------------------------------------------------------------------------
    | Nitm Reporting Api Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Nitm Reporting route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can simply stick with this list.
    |
    */

    'api-middleware' => [
        'api'
    ]
];