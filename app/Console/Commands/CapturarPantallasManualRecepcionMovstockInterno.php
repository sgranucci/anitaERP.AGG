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

class CapturarPantallasManualRecepcionMovstockInterno extends Command
{
    protected $signature = 'manual:capturar-recepcion-movstock-interno
                            {--usuario=admin : Usuario ERP}
                            {--rol= : ID de rol (opcional)}';

    protected $description = 'Captura pantallas reales: Recepción proveedores, Movimientos stock y Transferencias';

    /** @var array<string, array{path: string, auth: bool, post?: string, modal_oc?: bool}> */
    private array $pantallas = [
        'recepcion-listado' => ['path' => '/stock/recepcion-proveedor', 'auth' => true],
        'recepcion-form' => ['path' => '', 'auth' => true],
        'recepcion-modal-oc' => ['path' => '/stock/recepcion-proveedor/crear', 'auth' => true, 'post' => 'modal_oc', 'modal_oc' => true],
        'recepcion-devolucion' => ['path' => '', 'auth' => true],
        'movimientos-listado' => ['path' => '/stock/movimientostock', 'auth' => true],
        'movimientos-form' => ['path' => '', 'auth' => true],
        'transferencia-pantalla' => ['path' => '/stock/transferencia-mercaderia', 'auth' => true],
        'transferencia-pendientes' => ['path' => '/stock/transferencia-mercaderia/pendientes', 'auth' => true],
    ];

    public function handle(): int
    {
        $this->resolverRutasDinamicas();

        $this->pantallas = array_filter(
            $this->pantallas,
            static fn (array $cfg): bool => ! $cfg['auth'] || ($cfg['path'] ?? '') !== ''
        );

        $outDir = public_path('docs/manual-recepcion-movstock/img');
        File::ensureDirectoryExists($outDir);

        $usuarioLogin = $this->option('usuario');
        $user = Usuario::where('usuario', $usuarioLogin)->first();
        if (! $user) {
            $this->error("Usuario no encontrado: {$usuarioLogin}");

            return self::FAILURE;
        }

        $this->info('Capturando pantallas Recepción/Movimientos → '.$outDir);

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
                $this->htmlAPng($html, $destino, ! empty($cfg['modal_oc']));
                $this->info("  ✓ {$nombre}.png");
            } catch (\Throwable $e) {
                $this->warn("  ✗ {$nombre}: ".$e->getMessage());
            }
        }

        $this->info('Listo. Ejecute: php docs/manual-recepcion-movstock/generar.php');

        return self::SUCCESS;
    }

    private function resolverRutasDinamicas(): void
    {
        $tablaRecepcion = $this->tablaRecepcionCabecera();

        $recepcionBorrador = (int) (DB::table($tablaRecepcion)
            ->where('estado', 'BORRADOR')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        $recepcionConfirmada = (int) (DB::table($tablaRecepcion)
            ->where('estado', 'CONFIRMADA')
            ->where('tipo', 'RECEPCION')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($recepcionBorrador > 0) {
            $this->pantallas['recepcion-form']['path'] = '/stock/recepcion-proveedores/'.$recepcionBorrador.'/editar';
        } elseif ($recepcionConfirmada > 0) {
            $this->pantallas['recepcion-form']['path'] = '/stock/recepcion-proveedores/'.$recepcionConfirmada.'/editar';
        }

        if ($recepcionConfirmada > 0) {
            $this->pantallas['recepcion-devolucion']['path'] = '/stock/recepcion-proveedores/'.$recepcionConfirmada.'/devolucion';
        }

        $movimientoId = (int) (DB::table('movimientostock')
            ->where('codigo', 'not like', 'TR-%')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        if ($movimientoId <= 0) {
            $movimientoId = (int) (DB::table('movimientostock')->orderByDesc('id')->value('id') ?? 0);
        }

        if ($movimientoId > 0) {
            $this->pantallas['movimientos-form']['path'] = '/stock/movimientostock/'.$movimientoId.'/editar';
        } else {
            $this->pantallas['movimientos-form']['path'] = '/stock/movimientostock/crear';
        }
    }

    private function tablaRecepcionCabecera(): string
    {
        foreach (DB::select("SHOW TABLES LIKE 'recepcion_prove%'") as $row) {
            $nombre = array_values((array) $row)[0];
            if (! str_contains($nombre, '_articulo')
                && ! str_contains($nombre, '_archivo')
                && ! str_contains($nombre, '_estado')
                && ! str_contains($nombre, '_parte')) {
                return $nombre;
            }
        }

        throw new \RuntimeException('No se encontró la tabla cabecera recepcion_proveedores.');
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

    private function htmlAPng(string $html, string $pngPath, bool $preservarModalOc = false): void
    {
        $html = $this->prepararHtmlParaCaptura($html, $preservarModalOc);

        $tmpPdf = storage_path('app/tmp-manual-recepcion-movstock-captura.pdf');
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

        if ($tipo === 'modal_oc') {
            $filaEjemplo = '<tr>'
                .'<td><strong>882145</strong></td>'
                .'<td>10/06/2026</td>'
                .'<td>Proveedor ejemplo S.A.</td>'
                .'<td>Empresa demo</td>'
                .'<td><span class="badge badge-warning">Parcial</span></td>'
                .'<td class="text-right">120</td>'
                .'<td><span class="btn btn-warning btn-sm">Elegir</span></td>'
                .'<td><span class="btn btn-info btn-sm">Consultar</span></td>'
                .'</tr>';

            $html = preg_replace(
                '/<tbody id="datosocrecepcion"><\/tbody>/',
                '<tbody id="datosocrecepcion">'.$filaEjemplo.'</tbody>',
                $html
            ) ?? $html;

            $html = preg_replace(
                '/id="consultaocrecepcionModal"[^>]*>/',
                'id="consultaocrecepcionModal" class="modal show" style="display:block!important;position:relative;opacity:1;padding:12px;">',
                $html
            ) ?? $html;

            $html = preg_replace(
                '/class="modal-backdrop fade[^"]*"/',
                'class="modal-backdrop d-none"',
                $html
            ) ?? $html;
        }

        return $html;
    }

    private function prepararHtmlParaCaptura(string $html, bool $preservarModalOc = false): string
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

        if ($preservarModalOc) {
            $body = preg_replace(
                '/<div class="modal fade(?! show)[\s\S]*?id="consultaocrecepcionModal"[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/i',
                '',
                $body
            ) ?? $body;
            $body = preg_replace(
                '/<div class="modal fade[\s\S]*?<\/div>\s*<\/div>\s*<\/div>\s*<\/div>/i',
                '',
                $body
            ) ?? $body;
        } else {
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
            .($preservarModalOc ? '.modal-backdrop{display:none!important;}' : '.modal{display:none!important;}')
            .'</style></head><body>'
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
