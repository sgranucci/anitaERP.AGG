<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorAuditorIvaTipoMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorAuditorIvaTipoSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor ERP-only Contaduría P3: conceptos IVA ajenos al tipo de comprobante.
 */
final class ComprobanteProveedorAuditorIvaTipoService
{
    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('comprobante_proveedor_auditoria_erp.iva_fuera_tipo', []);
        $campoFecha = (string) ($config['campo_fecha'] ?? 'fechaiva');
        if (! in_array($campoFecha, ['fechaiva', 'fechacomprobante'], true)) {
            $campoFecha = 'fechaiva';
        }
        $soloI = filter_var($config['solo_impuesto_iva'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $maxFilas = max(10, (int) ($config['max_filas_mail'] ?? 80));

        [$desde, $hasta] = $this->resolverPeriodoMes($fechaDesde, $fechaHasta);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'campo_fecha' => $campoFecha,
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

        $comprobantes = Comprobante_Proveedor::query()
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'empresas',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
            ])
            ->whereIn('estado', [
                ComprobanteProveedorEstados::CONTABILIZADO,
                ComprobanteProveedorEstados::APROBADO,
                ComprobanteProveedorEstados::BORRADOR,
                ComprobanteProveedorEstados::PENDIENTE_REVISION,
                ComprobanteProveedorEstados::PENDIENTE_APROBACION,
                ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
            ])
            ->where(function ($q) {
                $q->whereNull('origen_entrada')
                    ->orWhere('origen_entrada', '!=', ComprobanteProveedorOrigenEntrada::ANITA_IMPORT);
            })
            ->whereDate($campoFecha, '>=', $desde)
            ->whereDate($campoFecha, '<=', $hasta)
            ->orderBy('id')
            ->get();

        $informe['total'] = $comprobantes->count();

        foreach ($comprobantes as $cp) {
            try {
                $ids = [];
                foreach ($cp->comprobante_proveedor_conceptos as $linea) {
                    if (abs((float) ($linea->monto ?? 0)) < 0.0001) {
                        continue;
                    }
                    $id = (int) ($linea->concepto_ivacompra_id ?? 0);
                    if ($id > 0) {
                        $ids[] = $id;
                    }
                }

                $eval = ComprobanteProveedorAuditorIvaTipoSupport::evaluar(
                    (int) ($cp->tipotransaccion_compra_id ?? 0),
                    $ids,
                    $soloI,
                );

                $base = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $this->etiqueta($cp),
                    'empresa' => (string) ($cp->empresas->nombre ?? ''),
                    'proveedor' => (string) ($cp->proveedores->nombre ?? ''),
                    'estado_cp' => (string) ($cp->estado ?? ''),
                    'abreviatura' => $eval['abreviatura'],
                    'fechaiva' => substr((string) $cp->fechaiva, 0, 10),
                    'fechacomprobante' => substr((string) $cp->fechacomprobante, 0, 10),
                    'conceptos_fuera' => $eval['fuera']->map(
                        static fn ($c) => trim((string) $c->codigo.' '.$c->nombre)
                    )->values()->all(),
                ];

                if ($eval['omitido']) {
                    $informe['omitidos']++;

                    continue;
                }

                if ($eval['ok']) {
                    $informe['ok']++;

                    continue;
                }

                $informe['desvios'][] = $base + [
                    'estado' => 'desvio',
                    'motivo' => $eval['motivo'],
                ];
            } catch (Throwable $e) {
                $informe['errores'][] = [
                    'id' => (int) $cp->id,
                    'etiqueta' => $this->etiqueta($cp),
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('auditor_iva_tipo.error', [
                    'comprobante_id' => (int) $cp->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $informe['requiere_alerta'] = $informe['desvios'] !== [] || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMail($informe, $config, $maxFilas);
        }

        return $informe;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolverPeriodoMes(?string $desde, ?string $hasta): array
    {
        if ($desde && $hasta) {
            return [$desde, $hasta];
        }

        return [
            $desde ?: Carbon::today()->startOfMonth()->toDateString(),
            $hasta ?: Carbon::today()->toDateString(),
        ];
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
            Mail::to($destino)->send(new ComprobanteProveedorAuditorIvaTipoMail($informeMail));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('auditor_iva_tipo.mail', ['error' => $e->getMessage()]);
        }
    }
}
