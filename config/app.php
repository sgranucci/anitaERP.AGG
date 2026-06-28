<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Anita ERP'),
    'empresa' => env('EMPRESA', 'AGG'),
    'empresa_link' => env('EMPRESA_LINK', '/anitaERP/public'),
    'app_carpeta' => env('APP_CARPETA', '/anitaERP/public'),

    /*
    | When true, opening the stock article list runs Anita sync in the same HTTP request, which can
    | exceed reverse-proxy limits and return 504 Gateway Timeout.
    */
    'anita_sync_articulo_index' => (bool) env('ANITA_SYNC_ARTICULO_INDEX', false),

    /*
    | When true, the stock "fórmulas de artículos" index shows the button to sync formulas from Anita (ApiAnita).
    */
    'anita_sync_formula_articulo_index' => (bool) env('ANITA_SYNC_FORMULA_ARTICULO_INDEX', true),

    /*
    | When true, the stock "mesas gastronomía" index shows the button to sync from Anita (ApiAnita).
    */
    'anita_sync_mesa_gastronomia_index' => (bool) env('ANITA_SYNC_MESA_GASTRONOMIA_INDEX', true),

    /*
    | When true, the stock "descuentos gastronomía" index shows the button to sync from Anita (ApiAnita).
    */
    'anita_sync_descuento_gastronomia_index' => (bool) env('ANITA_SYNC_DESCUENTO_GASTRONOMIA_INDEX', true),

    /*
     * Máquinas vending: import masivo desde Anita (maqvmae / ubimvending) al abrir index vacío.
     * Solo import ERP ← Anita; create/update/delete en ERP no replican a Anita.
     */
    'anita_sync_maquinavending_gastronomia_index' => (bool) env('ANITA_SYNC_MAQUINAVENDING_GASTRONOMIA_INDEX', true),

    /*
    | When true, the stock "mozos gastronomía" index shows the button to sync from Anita (ApiAnita).
    */
    'anita_sync_mozo_gastronomia_index' => (bool) env('ANITA_SYNC_MOZO_GASTRONOMIA_INDEX', true),

    /*
    | When true, the ventas "categorías fidelidad gastronomía" index shows the button to sync from Anita (ApiAnita).
    */
    'anita_sync_categoria_fidelidad_gastronomia_index' => (bool) env('ANITA_SYNC_CATEGORIA_FIDELIDAD_GASTRONOMIA_INDEX', true),

    /*
    | When true, the ventas "clientes VIP canjes gastronomía" index syncs from Anita on first load / shows sync button.
    */
    'anita_sync_cliente_vip_gastronomia_index' => (bool) env('ANITA_SYNC_CLIENTE_VIP_GASTRONOMIA_INDEX', true),

    /*
    | When true, create/update/delete in clientes VIP canjes replicates changes to Anita (base_admin.clivipg).
    */
    'anita_sync_cliente_vip_gastronomia_write' => (bool) env('ANITA_SYNC_CLIENTE_VIP_GASTRONOMIA_WRITE', true),

    /*
    | When true, opening ventas/puntoventa with lista vacía dispara sync con Anita en la misma petición HTTP.
    */
    'anita_sync_puntoventa_index' => (bool) env('ANITA_SYNC_PUNTOVENTA_INDEX', false),

    /*
    | When true, create/update/delete in ventas/puntoventa replicates changes to Anita (tabla sucursal).
    */
    'anita_sync_puntoventa_write' => (bool) env('ANITA_SYNC_PUNTOVENTA_WRITE', false),

    /*
    | When true, create/update/delete in ventas/vendedor replicates changes to Anita (tabla vendedor).
    */
    'anita_sync_vendedor_write' => (bool) env('ANITA_SYNC_VENDEDOR_WRITE', true),

    /*
    | When true, create/update/delete in ventas/cliente replicates changes to Anita (tabla climae).
    */
    'anita_sync_cliente_write' => (bool) env('ANITA_SYNC_CLIENTE_WRITE', false),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => 'America/Argentina/Buenos_Aires',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'es',

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
    ])->toArray(),

];
