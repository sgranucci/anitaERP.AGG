<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorImputacionApDiaria;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\ComprobanteProveedorImputacionApCuentasSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApCtamovSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informe diario factura a factura: CC ERP ↔ asiento ERP ↔ ctamov Anita.
 */
final class ComprobanteProveedorImputacionApDiariaService
{
    public function __construct(
        private readonly ComprobanteProveedorImputacionApReporteService $reporte,
        private readonly ComprobanteProveedorImputacionApCtamovSupport $ctamov,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('comprobante_proveedor_anita.imputacion_ap_diaria', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 7));
        $desde = $fechaDesde ?: Carbon::today()->subDays($ventana - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? ComprobanteProveedorImputacionApSupport::TOLERANCIA);
        $maxFilasMail = max(10, (int) ($config['max_filas_mail'] ?? 80));

        $empresaIds = array_values(array_filter(array_map(
            'intval',
            (array) ($config['empresas_ids'] ?? [])
        ), static fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            $empresaIds = Empresa::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $filtros = [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => true,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'proveedores' => '',
            'solo_diferencias' => false,
            'incluir_comprobantes' => true,
            'incluir_opa' => false,
            'incluir_aplicaciones' => false,
            'tolerancia' => $tolerancia,
        ];

        $generado = $this->reporte->generar($filtros);
        $filas = $this->enriquecerConCcYCtamov($generado['filas'] ?? [], $tolerancia);

        $totales = [
            'total_filas' => count($filas),
            'ok' => 0,
            'con_desvio' => 0,
            'sin_cc' => 0,
            'sin_asiento' => 0,
            'sin_ctamov' => 0,
            'cc_ars' => 0.0,
            'asiento_ars' => 0.0,
            'ctamov_ars' => 0.0,
        ];
        $desvios = [];
        foreach ($filas as $fila) {
            if (! empty($fila['ok'])) {
                $totales['ok']++;
            } else {
                $totales['con_desvio']++;
                $desvios[] = $fila;
            }
            if (in_array('Sin CC', $fila['alertas'] ?? [], true)) {
                $totales['sin_cc']++;
            }
            if (in_array('Sin asiento', $fila['alertas'] ?? [], true)) {
                $totales['sin_asiento']++;
            }
            if (in_array('Sin ctamov Anita', $fila['alertas'] ?? [], true)) {
                $totales['sin_ctamov']++;
            }
            $totales['cc_ars'] += (float) ($fila['cc_ars'] ?? 0);
            $totales['asiento_ars'] += (float) ($fila['asiento_ars'] ?? 0);
            $totales['ctamov_ars'] += (float) ($fila['ctamov_ars'] ?? 0);
        }
        foreach (['cc_ars', 'asiento_ars', 'ctamov_ars'] as $k) {
            $totales[$k] = round((float) $totales[$k], 2);
        }

        $errores = [];
        if ($totales['total_filas'] === 0) {
            $errores[] = 'Sin facturas en el período: no hay control para marcar OK.';
        }

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_ids' => $empresaIds,
            'tolerancia' => $tolerancia,
            'totales' => $totales,
            'desvios' => $desvios,
            'desvios_mail' => array_slice($desvios, 0, $maxFilasMail),
            'desvios_omitidos' => max(0, count($desvios) - $maxFilasMail),
            'errores' => $errores,
            'requiere_alerta' => $errores !== [] || $totales['con_desvio'] > 0,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'notas' => [
                'Cada factura compara CC ERP, asiento ERP (AP MN + AP ME + anticipo) y ctamov Anita.',
                'Importes en $ con la cotización de la operación. Haber suma, Debe resta.',
                'No incluye OPA ni aplicaciones: solo comprobantes de proveedor.',
            ],
        ];

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config);
        }

        return $informe;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function enriquecerConCcYCtamov(array $filas, float $tolerancia): array
    {
        if ($filas === []) {
            return [];
        }

        $compIds = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['comprobante_id'] ?? 0);
            if ($id > 0) {
                $compIds[] = $id;
            }
        }
        $compIds = array_values(array_unique($compIds));

        $ccPorComp = $compIds === []
            ? collect()
            : Proveedor_Cuentacorriente::query()
                ->whereIn('comprobante_proveedor_id', $compIds)
                ->get(['comprobante_proveedor_id', 'total', 'moneda_id', 'cotizacion', 'fecha'])
                ->groupBy('comprobante_proveedor_id');

        $empresaIds = array_values(array_unique(array_map(
            static fn (array $f) => (int) ($f['empresa_id'] ?? 0),
            $filas
        )));
        $catalogo = ComprobanteProveedorImputacionApCuentasSupport::armar($empresaIds);

        $clavesCtamov = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila['numeroasiento'] ?? 0);
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if ($nro <= 0 || $empresaId <= 0) {
                continue;
            }
            $clavesCtamov[] = [
                'empresa_anita' => SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId),
                'numeroasiento' => $nro,
                'fecha' => (string) ($fila['fecha'] ?? ''),
            ];
        }
        $ctamovPorAsiento = $this->ctamov->sumarTrioPorAsiento($clavesCtamov, $catalogo);

        $out = [];
        foreach ($filas as $fila) {
            $compId = (int) ($fila['comprobante_id'] ?? 0);
            $lineasCc = $ccPorComp->get($compId, collect());
            $ccArs = 0.0;
            $tieneCc = $lineasCc->isNotEmpty();
            foreach ($lineasCc as $cc) {
                $ccArs += ComprobanteProveedorImputacionApSupport::aPesosTolerante(
                    (float) ($cc->total ?? 0),
                    (int) ($cc->moneda_id ?: ($fila['moneda_id'] ?? 1)),
                    $cc->cotizacion ?? ($fila['cotizacion'] ?? 1),
                    $fila['fecha'] ?? $cc->fecha,
                    'CC comprobante #'.$compId
                );
            }
            $ccArs = round($ccArs, 2);

            $asientoArs = round((float) ($fila['imputado_ars'] ?? 0), 2);
            $tieneAsiento = (int) ($fila['asiento_id'] ?? 0) > 0
                && trim((string) ($fila['numeroasiento'] ?? '')) !== '';

            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($fila['empresa_id'] ?? 0));
            $nroAsiento = (int) ($fila['numeroasiento'] ?? 0);
            $ctamov = $ctamovPorAsiento[ComprobanteProveedorImputacionApCtamovSupport::clave($empresaAnita, $nroAsiento)]
                ?? ['trio' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $ctamovArs = round((float) $ctamov['trio'], 2);
            $tieneCtamov = ! empty($ctamov['encontrado']);

            $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
                $ccArs,
                $asientoArs,
                $ctamovArs,
                $tieneCc,
                $tieneAsiento,
                $tieneCtamov,
                $tolerancia
            );

            $fila['cc_ars'] = $ccArs;
            $fila['asiento_ars'] = $asientoArs;
            $fila['ctamov_ars'] = $ctamovArs;
            $fila['ctamov_lineas'] = (int) ($ctamov['lineas'] ?? 0);
            $fila['diff_cc_asiento'] = $eval['diff_cc_asiento'];
            $fila['diff_asiento_ctamov'] = $eval['diff_asiento_ctamov'];
            $fila['diff_cc_ctamov'] = $eval['diff_cc_ctamov'];
            $fila['ok'] = $eval['ok'];
            $fila['alertas'] = $eval['alertas'];
            $fila['alertas_texto'] = implode(' · ', $eval['alertas']);
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMailSiCorresponde(array &$informe, array $config): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }

        $debe = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if (! $debe) {
            return;
        }

        try {
            Mail::to($destino)->send(new ComprobanteProveedorImputacionApDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('ComprobanteProveedorImputacionApDiaria: mail falló', ['error' => $e->getMessage()]);
        }
    }
}
