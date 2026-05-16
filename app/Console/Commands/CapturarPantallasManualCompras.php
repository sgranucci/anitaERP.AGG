<?php

namespace App\Console\Commands;

use HeadlessChromium\BrowserFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CapturarPantallasManualCompras extends Command
{
    protected $signature = 'manual:capturar-compras
                            {--usuario= : Usuario ERP para iniciar sesión}
                            {--password= : Contraseña (o variable MANUAL_CAPTURE_PASSWORD)}
                            {--base= : URL base, ej. http://10.20.30.210/anitaERP/public}';

    protected $description = 'Captura pantallas con Chrome/Chromium (alternativa: manual:capturar-compras-interno)';

  /** @var array<string, array{path: string, auth: bool}> */
    private array $pantallas = [
        'login' => ['path' => '/seguridad/login', 'auth' => false],
        'proveedor-listado' => ['path' => '/compras/proveedor', 'auth' => true],
        'requisicion-listado' => ['path' => '/compras/requisicion', 'auth' => true],
        'listaprecio-proveedor' => ['path' => '/compras/listaprecio_proveedor', 'auth' => true],
        'ordencompra-listado' => ['path' => '/compras/ordencompra', 'auth' => true],
        'tablas-maestras' => ['path' => '/compras/condicionpago', 'auth' => true],
    ];

    public function handle(): int
    {
        $base = rtrim($this->option('base') ?: (
            rtrim(config('app.url'), '/') . (env('APP_CARPETA', '') ?: '')
        ), '/');

        $usuario = $this->option('usuario') ?: env('MANUAL_CAPTURE_USER', 'admin');
        $password = $this->option('password') ?: env('MANUAL_CAPTURE_PASSWORD', '');

        if ($password === '') {
            $this->error('Indique contraseña con --password= o variable MANUAL_CAPTURE_PASSWORD en .env');

            return self::FAILURE;
        }

        $outDir = public_path('docs/manual-compras/img');
        File::ensureDirectoryExists($outDir);

        $this->info('Base URL: ' . $base);
        $this->info('Guardando en: ' . $outDir);

        try {
            $factory = new BrowserFactory();
            $browser = $factory->createBrowser(['headless' => true, 'noSandbox' => true]);
            $page = $browser->createPage();
            $page->setViewport(1280, 800);

            if (isset($this->pantallas['login'])) {
                $this->capturar($page, $base . $this->pantallas['login']['path'], $outDir . '/login.png');
            }

            $this->login($page, $base, $usuario, $password);

            foreach ($this->pantallas as $nombre => $cfg) {
                if ($nombre === 'login' || ! $cfg['auth']) {
                    continue;
                }
                $this->capturar($page, $base . $cfg['path'], $outDir . '/' . $nombre . '.png');
            }

            $browser->close();
        } catch (\Throwable $e) {
            $this->error('Error al capturar: ' . $e->getMessage());
            $this->line('Verifique que Chromium esté disponible (paquete chrome-php/chrome).');

            return self::FAILURE;
        }

        $this->info('Capturas generadas. Ejecute: php docs/manual-compras/generar.php');

        return self::SUCCESS;
    }

    private function login($page, string $base, string $usuario, string $password): void
    {
        $this->line('Iniciando sesión…');
        $page->navigate($base . '/seguridad/login')->waitForNavigation();
        $page->dom()->querySelector('input[name="usuario"]')->sendKeys($usuario);
        $page->dom()->querySelector('input[name="password"]')->sendKeys($password);
        $page->dom()->querySelector('button[type="submit"]')->click();
        $page->waitForNavigation();
        usleep(1500000);

        try {
            $rolBtn = $page->dom()->querySelector('#modal-seleccionar-rol .btn-primary, #modal-seleccionar-rol button[type="submit"]');
            if ($rolBtn !== null) {
                $rolBtn->click();
                $page->waitForNavigation();
                usleep(1000000);
            }
        } catch (\Throwable) {
            // Sin modal de rol
        }
    }

    private function capturar($page, string $url, string $file): void
    {
        $this->line('→ ' . basename($file));
        $page->navigate($url)->waitForNavigation();
        usleep(1200000);
        $page->screenshot(['format' => 'png'])->saveToFile($file);
    }
}
