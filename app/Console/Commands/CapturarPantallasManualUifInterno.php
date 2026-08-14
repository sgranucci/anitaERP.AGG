<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class CapturarPantallasManualUifInterno extends Command
{
    protected $signature = 'manual:capturar-uif-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional)}
                            {--empresa= : ID de empresa (opcional)}';

    protected $description = 'Captura pantallas reales del módulo UIF para el manual';

    public function handle(): int
    {
        $outDir = public_path('docs/manual-uif/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $empresaId = (int) ($this->option('empresa') ?: 0);
        if ($empresaId <= 0) {
            $empresaId = (int) (Empresa::query()->orderBy('id')->value('id') ?? 0);
        }

        $periodo = date('Y-m');
        $limite = (string) config('uif.LIMITE_INFORME_UIF', 0);
        $clienteId = (int) (\App\Models\Uif\Cliente_Uif::query()->orderByDesc('id')->value('id') ?? 0);

        $pantallas = [
            'clientes-listado' => '/uif/cliente_uif',
            'premios-listado' => '/uif/premio_uif',
            'informe-consulta' => '/uif/crearexportaoperacion',
            'congelados-listado' => '/uif/cliente_congelado_uif',
            'conciliacion-wigos' => '/uif/conciliacion-wigos',
        ];

        if ($empresaId > 0) {
            $pantallas['informe-resultado'] = '/uif/exportaoperacion/'.$periodo.'/'.$limite.'/'.$empresaId;
        }

        if ($clienteId > 0) {
            $pantallas['clientes-alta'] = '/uif/cliente_uif/'.$clienteId.'/editar';
        }

        $this->info('Capturando pantallas UIF → '.$outDir);

        foreach ($pantallas as $nombre => $path) {
            $this->autenticar($user);
            $html = $this->obtenerHtml($path);
            if ($html === null) {
                $this->warn("  ✗ {$nombre}: sin contenido");

                continue;
            }

            $destino = $outDir.'/'.$nombre.'.png';
            try {
                $this->htmlAPng($html, $destino);
                $this->info("  ✓ {$nombre}.png");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$nombre}: ".$e->getMessage());
            }
        }

        $this->info('Listo. Ejecute: php docs/manual-uif/generar.php');

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

        return is_string($html) && strlen($html) > 200 ? $html : null;
    }

    private function forzarModalVisible(string $html, string $modalId): string
    {
        $html = preg_replace(
            '/id="'.preg_quote($modalId, '/').'"([^>]*)class="modal fade([^"]*)"/',
            'id="'.$modalId.'"$1class="modal fade show$2" style="display:block!important"',
            $html
        ) ?? $html;

        return $html;
    }

    private function htmlAPng(string $html, string $pngPath): void
    {
        $html = $this->prepararHtmlParaCaptura($html);

        $tmpPdf = storage_path('app/tmp-manual-uif-captura.pdf');
        Pdf::loadHTML($html)
            ->setPaper([0, 0, 1280, 1200])
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
        $base = rtrim(config('app.url'), '/').(env('APP_CARPETA', '') ?: '/');
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

        // Conservar el modal forzado visible; ocultar el resto
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
            try {
                $img = new \Imagick();
                $img->setResolution(110, 110);
                $img->readImage($pdfPath.'[0]');
                $img->setImageFormat('png');
                $img->writeImage($pngPath);
                $img->clear();
                $img->destroy();

                return;
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    'No se pudo convertir PDF a PNG (pdftoppm: '.implode(' ', $out).'; Imagick: '.$e->getMessage().')'
                );
            }
        }

        throw new \RuntimeException('No se pudo convertir PDF a PNG: '.implode(' ', $out));
    }
}
