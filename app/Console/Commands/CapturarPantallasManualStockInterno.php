<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;

class CapturarPantallasManualStockInterno extends Command
{
    protected $signature = 'manual:capturar-stock-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional)}';

    protected $description = 'Captura pantallas reales del módulo Recuento de inventario';

    /** @var array<string, array{path: string, auth: bool, post?: string}> */
    private array $pantallas = [
        'recuento-listado' => ['path' => '/stock/recuento', 'auth' => true],
        'recuento-crear' => ['path' => '/stock/recuento/crear', 'auth' => true],
        'recuento-editar' => ['path' => '', 'auth' => true],
        'recuento-ver' => ['path' => '', 'auth' => true],
        'recuento-opciones-cierre' => ['path' => '', 'auth' => true, 'post' => 'opciones_cierre'],
        'recuento-movimientos' => ['path' => '', 'auth' => true],
        'recuento-importar' => ['path' => '', 'auth' => true],
    ];

    public function handle(): int
    {
        $recuentoId = (int) (DB::table('recuento')->orderByDesc('id')->value('id') ?? 0);
        if ($recuentoId > 0) {
            $this->pantallas['recuento-editar']['path'] = '/stock/recuento/'.$recuentoId.'/editar';
            $this->pantallas['recuento-ver']['path'] = '/stock/recuento/'.$recuentoId.'/ver';
            $this->pantallas['recuento-opciones-cierre']['path'] = '/stock/recuento/'.$recuentoId.'/ver';
            $this->pantallas['recuento-importar']['path'] = '/stock/recuento/'.$recuentoId.'/importar';

            $item = DB::table('recuento_item')->where('recuento_id', $recuentoId)->orderByDesc('id')->first();
            if ($item) {
                $depositoId = (int) (DB::table('recuento')->where('id', $recuentoId)->value('deposito_id') ?? 0);
                $this->pantallas['recuento-movimientos']['path'] = sprintf(
                    '/stock/recuento/movimientos-articulo?articulo_id=%d&deposito_id=%d',
                    (int) $item->articulo_id,
                    $depositoId
                );
            }
        }

        $this->pantallas = array_filter(
            $this->pantallas,
            static fn (array $cfg): bool => ! $cfg['auth'] || ($cfg['path'] ?? '') !== ''
        );

        $outDir = public_path('docs/manual-stock/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $this->info('Capturando pantallas Recuento → '.$outDir);

        foreach ($this->pantallas as $nombre => $cfg) {
            $this->autenticar($user);

            $html = $this->obtenerHtml($cfg['path']);
            if (! empty($cfg['post'])) {
                $html = $this->aplicarPostProceso($html, (string) $cfg['post']);
            }
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

        $this->info('Listo. Ejecute: php docs/manual-stock/generar.php');

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

        $tmpPdf = storage_path('app/tmp-manual-stock-captura.pdf');
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

    private function aplicarPostProceso(?string $html, string $tipo): ?string
    {
        if ($html === null) {
            return null;
        }

        if ($tipo === 'opciones_cierre') {
            $html = preg_replace(
                '/id="panel-opciones-cierre-recuento"/',
                'id="panel-opciones-cierre-recuento" style="box-shadow:0 0 0 3px #007bff;"',
                $html
            ) ?? $html;
        }

        return $html;
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

        // Modales, scripts y <template> (filas TR sueltas) rompen DomPDF.
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
            .'.content-wrapper{margin:0!important;}.modal{display:none!important;}</style></head><body>'
            .$body.'</body></html>';
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
            throw new \RuntimeException('Ghostscript falló: '.implode("\n", $output));
        }
    }
}
