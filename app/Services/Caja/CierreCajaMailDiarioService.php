<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Exports\Caja\CierreCajaReporteExport;
use App\Mail\Caja\CierreCajaDiarioMail;
use App\Support\Reportes\DompdfListadoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Distribuye el cierre de caja diario por mail (Anita l-ciecaja -m).
 */
class CierreCajaMailDiarioService
{
    public function __construct(
        private readonly CierreCajaReporteService $reporteService,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     fecha: string,
     *     enviados: int,
     *     omitido: string|null,
     *     error: string|null
     * }
     */
    public function ejecutar(?string $fechaYmd = null, bool $enviarMail = true): array
    {
        $cfg = (array) config('caja.cierre_caja_mail', []);
        if (! ($cfg['habilitada'] ?? false)) {
            return [
                'ok' => true,
                'fecha' => '',
                'enviados' => 0,
                'omitido' => 'deshabilitado (caja.cierre_caja_mail.habilitada)',
                'error' => null,
            ];
        }

        $destinos = $this->destinatarios((string) ($cfg['email'] ?? ''));
        if ($enviarMail && $destinos === []) {
            return [
                'ok' => false,
                'fecha' => '',
                'enviados' => 0,
                'omitido' => null,
                'error' => 'Sin destinatarios (CAJA_CIERRE_MAIL_EMAIL).',
            ];
        }

        $fecha = $this->resolverFecha($fechaYmd, (string) ($cfg['fecha_relativa'] ?? 'hoy'));
        $empresaIds = array_values(array_filter(array_map(
            'intval',
            (array) ($cfg['empresas_ids'] ?? [1])
        )));
        if ($empresaIds === []) {
            return [
                'ok' => false,
                'fecha' => $fecha,
                'enviados' => 0,
                'omitido' => null,
                'error' => 'Sin empresas (CAJA_CIERRE_MAIL_EMPRESAS_IDS).',
            ];
        }

        $filtros = [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => true,
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'cuentacaja_id' => 0,
            'incluir_sin_movimiento' => ! empty($cfg['incluir_sin_movimiento']),
        ];

        $resultado = $this->reporteService->generar($filtros);
        $filas = $resultado['filas'] ?? [];
        $titulo = 'Cierre de caja';
        $subtitulo = (string) ($resultado['subtitulo'] ?? '');
        $formato = (string) ($cfg['formato'] ?? 'EXCEL');
        $fechaMostrar = Carbon::createFromFormat('Y-m-d', $fecha)->format('d/m/Y');

        try {
            [$contenido, $nombre, $mime] = $this->armarAdjunto(
                $formato,
                $filas,
                $titulo,
                $subtitulo,
                $resultado,
                $fecha
            );
        } catch (Throwable $e) {
            Log::error('caja.cierre_mail.adjunto_fallo', [
                'fecha' => $fecha,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'fecha' => $fecha,
                'enviados' => 0,
                'omitido' => null,
                'error' => 'No se pudo armar el adjunto: '.$e->getMessage(),
            ];
        }

        if (! $enviarMail) {
            return [
                'ok' => true,
                'fecha' => $fecha,
                'enviados' => 0,
                'omitido' => 'sin-mail',
                'error' => null,
            ];
        }

        try {
            Mail::to($destinos)->send(new CierreCajaDiarioMail(
                [
                    'fecha' => $fechaMostrar,
                    'subtitulo' => $subtitulo,
                    'total_registros' => (int) ($resultado['total_registros'] ?? 0),
                ],
                $nombre,
                $contenido,
                $mime
            ));
        } catch (Throwable $e) {
            Log::error('caja.cierre_mail.envio_fallo', [
                'fecha' => $fecha,
                'destinos' => $destinos,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'fecha' => $fecha,
                'enviados' => 0,
                'omitido' => null,
                'error' => 'Fallo SMTP: '.$e->getMessage(),
            ];
        }

        return [
            'ok' => true,
            'fecha' => $fecha,
            'enviados' => count($destinos),
            'omitido' => null,
            'error' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function destinatarios(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $out = [];
        foreach ($parts as $email) {
            $email = strtolower(trim($email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[$email] = $email;
            }
        }

        return array_values($out);
    }

    private function resolverFecha(?string $fechaYmd, string $relativa): string
    {
        $fechaYmd = trim((string) $fechaYmd);
        if ($fechaYmd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            return $fechaYmd;
        }

        return $relativa === 'ayer'
            ? Carbon::yesterday()->toDateString()
            : Carbon::today()->toDateString();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $resultado
     * @return array{0: string, 1: string, 2: string}
     */
    private function armarAdjunto(
        string $formato,
        array $filas,
        string $titulo,
        string $subtitulo,
        array $resultado,
        string $fechaYmd,
    ): array {
        if ($formato === 'PDF') {
            $html = view('caja.cierre_caja_reporte.listado', [
                'resultado' => $resultado,
                'filas' => $filas,
                'titulo' => $titulo,
                'subtitulo' => $subtitulo,
                'puede_ver_cuentacaja' => false,
                'puede_ver_cheque' => false,
            ])->render();

            $dir = storage_path('pdf/listados');
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $ruta = $dir.'/cierre_caja_mail_'.$fechaYmd.'_'.getmypid().'.pdf';
            DompdfListadoSupport::guardarLegalLandscape($html, $ruta, [
                'titulo_corto' => 'Cierre de caja',
            ]);
            $contenido = (string) file_get_contents($ruta);
            @unlink($ruta);

            return [
                $contenido,
                'cierre_caja_'.$fechaYmd.'.pdf',
                'application/pdf',
            ];
        }

        $binario = (string) Excel::raw(
            new CierreCajaReporteExport($filas, $titulo, $subtitulo, $resultado),
            ExcelWriter::XLSX
        );

        return [
            $binario,
            'cierre_caja_'.$fechaYmd.'.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
    }
}
