<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Lsd_Presentacion_Sueldos;
use App\Models\Sueldos\Parametro_Sueldos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class CapturarPantallasManualLsdInterno extends Command
{
    protected $signature = 'manual:capturar-lsd-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional)}';

    protected $description = 'Captura pantallas reales del LSD para el manual';

    public function handle(): int
    {
        $outDir = public_path('docs/manual-lsd-sueldos/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $c110 = (int) (Concepto_Sueldos::query()->where('codigo', 110)->value('id') ?? 0);
        $c1002 = (int) (Concepto_Sueldos::query()->where('codigo', 1002)->value('id') ?? 0);
        $pDetr = (int) (Parametro_Sueldos::query()->where('codigo', 'DETRACCION_LEY_27430')->value('id') ?? 0);
        $pTope = (int) (Parametro_Sueldos::query()->where('codigo', 'TOPE_SIPA')->value('id') ?? 0);
        $presId = (int) (Lsd_Presentacion_Sueldos::query()->orderByDesc('id')->value('id') ?? 0);

        $pantallas = [
            'lsd-workbench' => '/sueldos/libro-sueldos-digital',
            'lsd-cobertura' => '/sueldos/libro-sueldos-digital/cobertura',
        ];
        if ($c110 > 0) {
            $pantallas['concepto-sueldo'] = '/sueldos/concepto/'.$c110.'/editar';
        }
        if ($c1002 > 0) {
            $pantallas['concepto-1002'] = '/sueldos/concepto/'.$c1002.'/editar';
        }
        if ($pDetr > 0) {
            $pantallas['parametro-detraccion'] = '/sueldos/parametro/'.$pDetr.'/editar';
        }
        if ($pTope > 0) {
            $pantallas['parametro-tope'] = '/sueldos/parametro/'.$pTope.'/editar';
        }
        if ($presId > 0) {
            $pantallas['lsd-ver'] = '/sueldos/libro-sueldos-digital/'.$presId;
        }

        $this->info('Capturando pantallas LSD → '.$outDir);

        foreach ($pantallas as $nombre => $path) {
            $this->autenticar($user);
            $html = $this->obtenerHtml($path);
            if ($html === null) {
                $this->warn("  ✗ {$nombre}: sin contenido ({$path})");

                continue;
            }

            $destino = $outDir.'/'.$nombre.'.png';
            try {
                $alto = str_starts_with($nombre, 'concepto-') ? 2200 : 1400;
                $this->htmlAPng($html, $destino, $alto);
                $this->info("  ✓ {$nombre}.png");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$nombre}: ".$e->getMessage());
            }
        }

        $this->info('Listo. Ejecute: php docs/manual-lsd-sueldos/generar.php');

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

        $tmpPdf = storage_path('app/tmp-manual-lsd-captura.pdf');
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
