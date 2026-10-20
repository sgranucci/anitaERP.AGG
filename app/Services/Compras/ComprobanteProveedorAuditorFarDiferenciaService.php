<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorAuditorFarDiferenciaMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorAuditorFarDiferenciaSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor ERP-only Contaduría P1: dif. FC vs COM en FAR en vez de cuentas OC.
 */
final class ComprobanteProveedorAuditorFarDiferenciaService
{
    public function __construct(
        private readonly ComprobanteProveedorAsientoService $asientoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('comprobante_proveedor_auditoria_erp.far_diferencia', []);
        $tol = (float) ($config['tolerancia'] ?? 0.05);
        $campoFecha = (string) ($config['campo_fecha'] ?? 'fechaiva');
        if (! in_array($campoFecha, ['fechaiva', 'fechacomprobante'], true)) {
            $campoFecha = 'fechaiva';
        }
        $codigoFar = (string) ($config['codigo_far'] ?? '211010004');
        $maxFilas = max(10, (int) ($config['max_filas_mail'] ?? 80));

        [$desde, $hasta] = $this->resolverPeriodoMes($fechaDesde, $fechaHasta);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'campo_fecha' => $campoFecha,
            'tolerancia' => $tol,
            'codigo_far' => $codigoFar,
            'total' => 0,
            'ok' => 0,
            'omitidos' => 0,
            'desvios' => [],
            'errores' => [],
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        $query = Comprobante_Proveedor::query()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'ordencompras',
                'empresas',
                'asientos',
                'comprobante_proveedor_recepciones',
            ])
            ->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->whereNotNull('asiento_id')
            ->where('modo_carga', ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
            ->where(function ($q) {
                $q->whereNull('origen_entrada')
                    ->orWhere('origen_entrada', '!=', ComprobanteProveedorOrigenEntrada::ANITA_IMPORT);
            })
            ->whereHas('comprobante_proveedor_recepciones')
            ->whereDate($campoFecha, '>=', $desde)
            ->whereDate($campoFecha, '<=', $hasta)
            ->orderBy('id');

        $comprobantes = $query->get();
        $informe['total'] = $comprobantes->count();

        foreach ($comprobantes as $cp) {
            try {
                $fila = $this->auditarUno($cp, $codigoFar, $tol);
            } catch (Throwable $e) {
                $informe['errores'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $this->etiqueta($cp),
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('auditor_far_diferencia.error', [
                    'comprobante_id' => (int) $cp->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (($fila['estado'] ?? '') === 'ok') {
                $informe['ok']++;
            } elseif (($fila['estado'] ?? '') === 'omitido') {
                $informe['omitidos']++;
            } else {
                $informe['desvios'][] = $fila;
            }
        }

        $informe['requiere_alerta'] = $informe['desvios'] !== [] || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMail($informe, $config, $maxFilas);
        }

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarUno(Comprobante_Proveedor $cp, string $codigoFar, float $tol): array
    {
        $farAsiento = $this->sumarFarAsiento((int) $cp->asiento_id, $codigoFar);
        $preview = $this->asientoService->armarPreview($cp);
        $lineas = ComprobanteProveedorAuditorFarDiferenciaSupport::lineasDesdePayloadPreview(
            $preview['payload_asiento'] ?? []
        );
        $eval = ComprobanteProveedorAuditorFarDiferenciaSupport::evaluar($lineas, $farAsiento, $tol);

        $base = [
            'id' => (int) $cp->id,
            'etiqueta' => $this->etiqueta($cp),
            'empresa' => (string) ($cp->empresas->nombre ?? ''),
            'proveedor' => (string) ($cp->proveedores->nombre ?? ''),
            'oc' => (string) ($cp->ordencompras->numeroordencompra ?? ''),
            'fechaiva' => substr((string) $cp->fechaiva, 0, 10),
            'fechacomprobante' => substr((string) $cp->fechacomprobante, 0, 10),
            'far_asiento' => $eval['far_asiento'],
            'far_esperado' => $eval['far_esperado'],
            'far_diferencia_esperada' => $eval['far_diferencia_esperada'],
            'desvio_far' => $eval['desvio_far'],
        ];

        if ($eval['ok']) {
            return $base + ['estado' => 'ok', 'motivo' => null];
        }

        return $base + [
            'estado' => 'desvio',
            'motivo' => $eval['motivo'],
        ];
    }

    private function sumarFarAsiento(int $asientoId, string $codigoFar): float
    {
        if ($asientoId <= 0) {
            return 0.0;
        }

        $sum = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->where('am.asiento_id', $asientoId)
            ->where('cc.codigo', $codigoFar)
            ->sum('am.monto');

        return round((float) $sum, 2);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverPeriodoMes(?string $desde, ?string $hasta): array
    {
        if ($desde && $hasta) {
            return [$desde, $hasta];
        }

        $inicio = Carbon::today()->startOfMonth()->toDateString();
        $fin = Carbon::today()->toDateString();

        return [$desde ?: $inicio, $hasta ?: $fin];
    }

    private function etiqueta(Comprobante_Proveedor $cp): string
    {
        $abrev = (string) ($cp->tipotransaccion_compras->abreviatura ?? 'FC');

        return sprintf(
            '%s %s %04d-%d (#%d)',
            $abrev,
            (string) ($cp->letra ?? ''),
            (int) ($cp->sucursal ?? 0),
            (int) ($cp->numerocomprobante ?? 0),
            (int) $cp->id,
        );
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMail(array &$informe, array $config, int $maxFilas): void
    {
        $destino = trim((string) (
            $config['email']
            ?? config('comprobante_proveedor_auditoria_erp.email')
            ?? ''
        ));
        if ($destino === '') {
            $destino = trim((string) config('comprobante_proveedor_auditoria_erp.email', ''));
        }
        if ($destino === '') {
            return;
        }

        $mailSiempre = filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $mailSiempre && ! ($informe['requiere_alerta'] ?? false)) {
            return;
        }

        $informeMail = $informe;
        $informeMail['desvios'] = array_slice($informe['desvios'], 0, $maxFilas);
        $informeMail['desvios_truncados'] = count($informe['desvios']) > $maxFilas;

        try {
            Mail::to($destino)->send(new ComprobanteProveedorAuditorFarDiferenciaMail($informeMail));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('auditor_far_diferencia.mail', ['error' => $e->getMessage()]);
        }
    }
}
