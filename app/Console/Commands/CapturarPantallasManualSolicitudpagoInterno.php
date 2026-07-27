<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Solicitudpago\Solicitudpago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class CapturarPantallasManualSolicitudpagoInterno extends Command
{
    protected $signature = 'manual:capturar-solicitudpago-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional)}';

    protected $description = 'Captura pantallas reales del módulo Solicitudes de pago para el manual';

    public function handle(): int
    {
        $outDir = public_path('docs/manual-solicitudpago/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $madreId = (int) (Solicitudpago::query()
            ->whereHas('cuotas')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        $hijaId = (int) (Solicitudpago::query()
            ->whereNotNull('solicitudpago_madre_id')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        $formId = $madreId > 0 ? $madreId : (int) (Solicitudpago::query()->orderByDesc('id')->value('id') ?? 0);

        $pantallas = [
            'sp-listado' => '/solicitudpago/solicitudpago',
            'sp-filtros' => '/solicitudpago/solicitudpago?madre_hija=familia&estado=',
            'sp-formulario' => $formId > 0 ? '/solicitudpago/solicitudpago/'.$formId.'/editar' : '/solicitudpago/solicitudpago/crear',
            'sp-cuotas' => $madreId > 0
                ? '/solicitudpago/solicitudpago/'.$madreId.'/editar'
                : null,
            'sp-informe' => '/solicitudpago/informe-solicitudpago?consultar=1',
            'sp-modal-familia' => $madreId > 0
                ? '/solicitudpago/solicitudpago/'.$madreId.'/familia-vinculos'
                : null,
        ];

        $this->info('Capturando pantallas Solicitudpago → '.$outDir);

        foreach ($pantallas as $nombre => $path) {
            if ($path === null || $path === '') {
                $this->warn("  ✗ {$nombre}: sin ruta (faltan datos de ejemplo)");

                continue;
            }

            $this->autenticar($user);
            $html = $this->obtenerHtml($path);
            if ($html === null) {
                $this->warn("  ✗ {$nombre}: sin contenido");

                continue;
            }

            if ($nombre === 'sp-filtros') {
                $html = $this->forzarPanelFiltrosAbierto($html);
            }
            if ($nombre === 'sp-cuotas') {
                $html = $this->activarSolapaCuotas($html);
            }
            if ($nombre === 'sp-modal-familia') {
                $html = $this->envolverModalFamilia($html);
            }

            $destino = $outDir.'/'.$nombre.'.png';
            try {
                $this->htmlAPng($html, $destino);
                $this->info("  ✓ {$nombre}.png");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$nombre}: ".$e->getMessage());
            }
        }

        $this->info('Listo. Ejecute: php docs/manual-solicitudpago/generar.php');

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

    private function forzarPanelFiltrosAbierto(string $html): string
    {
        return preg_replace(
            '/id="panel-filtros-solicitudpago" class="collapse([^"]*)"/',
            'id="panel-filtros-solicitudpago" class="collapse show$1" style="display:block!important"',
            $html
        ) ?? $html;
    }

    private function activarSolapaCuotas(string $html): string
    {
        $html = preg_replace(
            '/id="tab-datos"[^>]*class="tab-pane fade show active"/',
            'id="tab-datos" class="tab-pane fade"',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/id="tab-cuotas"[^>]*class="tab-pane fade"/',
            'id="tab-cuotas" class="tab-pane fade show active"',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/id="tab-datos-link"[^>]*class="nav-link active"/',
            'id="tab-datos-link" class="nav-link"',
            $html
        ) ?? $html;
        $html = preg_replace(
            '/id="tab-cuotas-link"[^>]*class="nav-link"/',
            'id="tab-cuotas-link" class="nav-link active"',
            $html
        ) ?? $html;

        return $html;
    }

    private function envolverModalFamilia(string $fragmento): string
    {
        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            .'<title>Plan / cuotas</title>'
            .'<link rel="stylesheet" href="'.asset('assets/lte/dist/css/adminlte.min.css').'">'
            .'<style>body{background:#e9ecef;padding:24px;font-family:DejaVu Sans,sans-serif}'
            .'.modal-demo{background:#fff;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.15);max-width:920px;margin:0 auto}'
            .'.modal-demo-h{background:#17a2b8;color:#fff;padding:12px 16px;font-weight:600}'
            .'.modal-demo-b{padding:16px}</style></head><body>'
            .'<div class="modal-demo"><div class="modal-demo-h"><i class="fa fa-sitemap"></i> Plan / cuotas</div>'
            .'<div class="modal-demo-b">'.$fragmento.'</div></div></body></html>';
    }

    private function htmlAPng(string $html, string $pngPath): void
    {
        $html = $this->prepararHtmlParaCaptura($html);

        $tmpPdf = storage_path('app/tmp-manual-solicitudpago-captura.pdf');
        Pdf::loadHTML($html)
            ->setPaper([0, 0, 1280, 1024])
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
        if (str_contains($html, 'modal-demo')) {
            return $html;
        }

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
        $body = preg_replace('/<div class="modal fade[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/i', '', $body) ?? $body;

        $css = asset("assets/{$theme}/dist/css/adminlte.min.css");
        $cssCustom = asset('assets/css/custom.css');

        return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
            .'<base href="'.htmlspecialchars($base.'/', ENT_QUOTES).'">'
            .'<link rel="stylesheet" href="'.$css.'">'
            .'<link rel="stylesheet" href="'.$cssCustom.'">'
            .'<style>body{background:#f4f6f9;margin:0;padding:12px;font-family:DejaVu Sans,sans-serif;}'
            .'.content-wrapper{margin:0!important;}.modal{display:none!important;}'
            .'.main-sidebar,.main-header,.main-footer{display:none!important;}</style>'
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
