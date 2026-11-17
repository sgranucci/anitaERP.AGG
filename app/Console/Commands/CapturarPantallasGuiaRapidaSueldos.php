<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Liquidacion_Sueldos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

/**
 * Bajadas de pantallas para la guía rápida de liquidación (HTML autenticado → PNG).
 */
class CapturarPantallasGuiaRapidaSueldos extends Command
{
    protected $signature = 'manual:capturar-guia-rapida-sueldos
                            {--usuario=sergio : Usuario ERP}
                            {--rol= : ID de rol (opcional)}
                            {--solo= : Claves separadas por coma}';

    protected $description = 'Captura pantallas de la guía rápida de sueldos (empleados, novedades, corridas)';

    public function handle(): int
    {
        $outDir = public_path('docs/guia-rapida-sueldos/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = (string) $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $liqId = (int) (Liquidacion_Sueldos::query()->orderByDesc('id')->value('id') ?? 0);

        $pantallas = [
            'empleado-listado' => '/sueldos/empleado',
            'novedad-listado' => '/sueldos/novedad',
            'novedad-crear' => '/sueldos/novedad/crear',
            'novedad-importar' => '/sueldos/novedad/importar',
            'liquidacion-listado' => '/sueldos/liquidacion',
            'liquidacion-crear' => '/sueldos/liquidacion/crear',
            'liquidacion-resultado' => $liqId > 0 ? '/sueldos/liquidacion/'.$liqId.'/resultado' : null,
        ];

        $solo = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('solo')))));
        if ($solo !== []) {
            $pantallas = array_intersect_key($pantallas, array_flip($solo));
        }
        $pantallas = array_filter($pantallas, static fn ($path) => is_string($path) && $path !== '');

        $this->info('Capturando guía rápida sueldos → '.$outDir);
        if ($liqId > 0) {
            $this->comment("Resultado: liquidación #{$liqId}");
        }

        foreach ($pantallas as $nombre => $path) {
            $this->autenticar($user);
            $html = $this->obtenerHtml($path);
            if ($html === null) {
                $this->warn("  ✗ {$nombre}: sin contenido ({$path})");

                continue;
            }

            $destino = $outDir.'/'.$nombre.'.png';
            try {
                $alto = str_contains($nombre, 'crear') || str_contains($nombre, 'importar') ? 1800 : 1400;
                $this->htmlAPng($html, $destino, $alto);
                $this->info('  ✓ '.$nombre.'.png ('.filesize($destino).' bytes)');
            } catch (\Throwable $e) {
                $this->warn('  ✗ '.$nombre.': '.$e->getMessage());
            }
        }

        $this->info('Listo. Regenerar: php docs/guia-rapida-sueldos/generar.php');

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
            if ($rol) {
                Session::put(['rol_id' => $rol->id, 'rol_nombre' => $rol->nombre]);
            }
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

        return is_string($html) && strlen($html) > 200 ? $html : null;
    }

    private function htmlAPng(string $html, string $pngPath, int $alto = 1400): void
    {
        $html = $this->prepararHtmlParaCaptura($html);

        $tmpPdf = storage_path('app/tmp-guia-rapida-sueldos-captura.pdf');
        Pdf::loadHTML($html)
            ->setPaper([0, 0, 1280, $alto])
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
        $base = rtrim((string) config('app.url'), '/').(env('APP_CARPETA', '') ?: '/');
        $theme = 'lte';

        if (preg_match('/<div class="content-wrapper">[\s\S]*?<\/div>\s*<!--Inicio Footer -->/i', $html, $m)) {
            $body = $m[0];
        } elseif (preg_match('/<body[^>]*>([\s\S]*)<\/body>/i', $html, $m)) {
            $body = $m[1];
        } else {
            $body = $html;
        }

        $body = preg_replace('/<script[\s\S]*?<\/script>/i', '', $body) ?? $body;
        $body = preg_replace('/<template[\s\S]*?<\/template>/i', '', $body) ?? $body;
        if (! str_contains($body, 'modal fade show')) {
            $body = preg_replace('/<div class="modal fade[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/i', '', $body) ?? $body;
        }

        $css = asset("assets/{$theme}/dist/css/adminlte.min.css");
        $cssCustom = asset('assets/css/custom.css');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            .'<base href="'.htmlspecialchars($base.'/', ENT_QUOTES).'">'
            .'<link rel="stylesheet" href="'.$css.'">'
            .'<link rel="stylesheet" href="'.$cssCustom.'">'
            .'<style>body{background:#f4f6f9;margin:0;padding:12px;font-family:DejaVu Sans,sans-serif;}'
            .'.content-wrapper{margin:0!important;}'
            .'.main-sidebar,.main-header,.main-footer{display:none!important;}'
            .'.modal-backdrop{display:none!important;}</style>'
            .'</head><body>'.$body.'</body></html>';
    }

    private function pdfPrimeraPaginaAPng(string $pdfPath, string $pngPath): void
    {
        $base = preg_replace('/\.png$/i', '', $pngPath);
        $cmd = 'pdftoppm -png -f 1 -l 1 -r 110 '.escapeshellarg($pdfPath).' '.escapeshellarg($base);
        exec($cmd.' 2>&1', $out, $code);
        $generado = $base.'-1.png';
        if ($code === 0 && is_file($generado)) {
            rename($generado, $pngPath);

            return;
        }

        if (class_exists(\Imagick::class)) {
            $img = new \Imagick();
            $img->setResolution(110, 110);
            $img->readImage($pdfPath.'[0]');
            $img->setImageFormat('png');
            $img->writeImage($pngPath);
            $img->clear();
            $img->destroy();

            return;
        }

        throw new \RuntimeException('No se pudo convertir PDF a PNG: '.implode(' ', $out));
    }
}
