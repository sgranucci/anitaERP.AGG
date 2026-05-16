<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class CapturarPantallasManualInterno extends Command
{
    protected $signature = 'manual:capturar-compras-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional, usa el primero si no se indica)}';

    protected $description = 'Captura pantallas reales del ERP (render interno, sin Chrome)';

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
        $reqId = \Illuminate\Support\Facades\DB::table('requisicion')->orderByDesc('id')->value('id');
        if ($reqId) {
            $this->pantallas['presupuestos-tab'] = [
                'path' => '/compras/requisicion/' . $reqId . '/editar',
                'auth' => true,
            ];
        }

        $outDir = public_path('docs/manual-compras/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $this->info('Capturando pantallas reales → ' . $outDir);

        foreach ($this->pantallas as $nombre => $cfg) {
            if ($cfg['auth']) {
                $this->autenticar($user);
            } else {
                Auth::logout();
                Session::flush();
            }

            $html = $this->obtenerHtml($cfg['path']);
            if ($html === null) {
                $this->warn("  ✗ {$nombre}: sin contenido");

                continue;
            }

            $destino = $outDir . '/' . $nombre . '.png';
            try {
                $this->htmlAPng($html, $destino);
                $this->info("  ✓ {$nombre}.png");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$nombre}: " . $e->getMessage());
            }
        }

        $this->info('Listo. Ejecute: php docs/manual-compras/generar.php');

        return self::SUCCESS;
    }

    private function autenticar(Usuario $user): void
    {
        Auth::logout();
        Session::flush();

        $user->loadMissing(['centrocostos', 'sectorLegajocompra']);
        Auth::login($user);

        $roles = $user->roles()->get();
        $empresas = $user->usuario_empresas()->get();
        $user->setSession($roles->toArray(), $empresas->toArray());

        $rolId = $this->option('rol') ?: $roles->first()?->id;
        if ($rolId) {
            $rol = $roles->firstWhere('id', (int) $rolId) ?? $roles->first();
            Session::put(['rol_id' => $rol->id, 'rol_nombre' => $rol->nombre]);
        }
    }

    private function obtenerHtml(string $path): ?string
    {
        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        $request = Request::create($path, 'GET');
        $response = $kernel->handle($request);
        $html = $response->getContent();
        $kernel->terminate($request, $response);

        return is_string($html) && strlen($html) > 500 ? $html : null;
    }

    private function htmlAPng(string $html, string $pngPath): void
    {
        $html = $this->prepararHtmlParaCaptura($html);

        $tmpPdf = storage_path('app/tmp-manual-captura.pdf');
        Pdf::loadHTML($html)
            ->setPaper([0, 0, 1280, 800])
            ->setOption([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ])
            ->save($tmpPdf);

        $this->pdfPrimeraPaginaAPng($tmpPdf, $pngPath);
        @unlink($tmpPdf);
    }

    private function prepararHtmlParaCaptura(string $html): string
    {
        $base = rtrim(config('app.url'), '/') . (env('APP_CARPETA', '') ?: '/');
        $theme = 'lte';

        if (preg_match('/<div class="content-wrapper">[\s\S]*?<\/div>\s*<!--Inicio Footer -->/i', $html, $m)) {
            $body = $m[0];
        } elseif (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $m)) {
            $body = $m[1];
        } else {
            $body = $html;
        }

        $css = asset("assets/{$theme}/dist/css/adminlte.min.css");
        $cssCustom = asset('assets/css/custom.css');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            . '<base href="' . htmlspecialchars($base . '/', ENT_QUOTES) . '">'
            . '<link rel="stylesheet" href="' . $css . '">'
            . '<link rel="stylesheet" href="' . $cssCustom . '">'
            . '<style>body{background:#f4f6f9;margin:0;padding:12px;font-family:DejaVu Sans,sans-serif;}'
            . '.content-wrapper{margin:0!important;}</style></head><body>'
            . $body . '</body></html>';
    }

    private function pdfPrimeraPaginaAPng(string $pdfPath, string $pngPath): void
    {
        $gs = trim((string) shell_exec('command -v gs'));
        if ($gs === '') {
            throw new \RuntimeException('Ghostscript (gs) no está instalado.');
        }

        $cmd = sprintf(
            '%s -dSAFER -dBATCH -dNOPAUSE -sDEVICE=png16m -r144 -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s 2>&1',
            escapeshellarg($gs),
            escapeshellarg($pngPath),
            escapeshellarg($pdfPath)
        );

        exec($cmd, $output, $code);
        if ($code !== 0 || ! is_file($pngPath)) {
            throw new \RuntimeException('Ghostscript falló: ' . implode("\n", $output));
        }
    }
}
