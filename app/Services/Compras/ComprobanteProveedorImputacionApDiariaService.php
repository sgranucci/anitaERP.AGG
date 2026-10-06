<?php

namespace App\Services\Compras;

use App\Mail\Compras\ComprobanteProveedorImputacionApDiaria;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ComprobanteProveedorImputacionApCuentasSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApCtamovSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informe diario factura a factura: CC ERP ↔ haber AP asiento ↔ haber AP ctamov.
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
            // Solo origen ERP: la importación histórica Anita no pasa por el circuito de asiento ERP.
            'excluir_origenes' => [ComprobanteProveedorOrigenEntrada::ANITA_IMPORT],
        ];

        $generado = $this->reporte->generar($filtros);
        $filas = $this->enriquecerConCcYCtamov($generado['filas'] ?? [], $tolerancia);

        $partes = ComprobanteProveedorImputacionApSupport::particionarControlDiario($filas);
        $desvios = $partes['desvios'];
        $borradores = $partes['borradores'];

        $totales = [
            'total_filas' => count($filas),
            'ok' => count($partes['ok']),
            'con_desvio' => count($desvios),
            'en_borrador' => count($borradores),
            'sin_cc' => 0,
            'sin_asiento' => 0,
            'sin_ctamov' => 0,
            'ie' => 0,
            'ie_ok' => 0,
            'ie_desvio' => 0,
            'cc_ars' => 0.0,
            'asiento_ars' => 0.0,
            'ctamov_ars' => 0.0,
        ];
        foreach ($desvios as $fila) {
            if (in_array('Sin CC', $fila['alertas'] ?? [], true)) {
                $totales['sin_cc']++;
            }
            if (in_array('Sin asiento', $fila['alertas'] ?? [], true)
                || in_array('Sin asiento del I/E', $fila['alertas'] ?? [], true)) {
                $totales['sin_asiento']++;
            }
            if (in_array('Sin ctamov Anita', $fila['alertas'] ?? [], true)) {
                $totales['sin_ctamov']++;
            }
        }
        foreach ($filas as $fila) {
            if (! empty($fila['es_ingreso_egreso'])) {
                if (ComprobanteProveedorImputacionApSupport::esBorrador((string) ($fila['estado'] ?? ''))) {
                    continue;
                }
                $totales['ie']++;
                if (! empty($fila['ok'])) {
                    $totales['ie_ok']++;
                } else {
                    $totales['ie_desvio']++;
                }

                continue;
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
            'borradores' => $borradores,
            'borradores_mail' => array_slice($borradores, 0, $maxFilasMail),
            'borradores_omitidos' => max(0, count($borradores) - $maxFilasMail),
            'errores' => $errores,
            'requiere_alerta' => $errores !== [] || $totales['con_desvio'] > 0,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'notas' => [
                'Cada factura compara la CC (cuotas) vs el haber a cuenta de proveedores del asiento vs el mismo en ctamov Anita.',
                'Solo suma líneas de proveedores MN/ME (códigos de config) y anticipo; ignora gastos/IVA del asiento.',
                'No usa el saldo neto de CC: ignora aplicaciones/OPP posteriores; exige existencia e importe de la factura en CC.',
                'Solo facturas de origen ERP (excluye importación desde Anita).',
                'Los comprobantes de ingresos y egresos no tienen cuenta corriente: se comparan con el debe del asiento del movimiento. Su ctamov lo controla el mail de I/E.',
                'Los comprobantes en BORRADOR se listan aparte: todavía no se contabilizaron, no son un desvío de cuadre.',
                'El debe a anticipo de una factura anticipada no se netea contra la CC; se controla aparte vs ctamov.',
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

        // Solo CC de la factura (cuotas), no aplicaciones/OPP que netean el saldo.
        $ccPorComp = $compIds === []
            ? collect()
            : Proveedor_Cuentacorriente::query()
                ->whereIn('comprobante_proveedor_id', $compIds)
                ->whereNotNull('comprobante_proveedor_cuota_id')
                ->where(function ($q) {
                    $q->whereNull('pagoproveedor_id')->orWhere('pagoproveedor_id', '<=', 0);
                })
                ->get([
                    'comprobante_proveedor_id',
                    'comprobante_proveedor_cuota_id',
                    'pagoproveedor_id',
                    'total',
                    'moneda_id',
                    'cotizacion',
                    'fecha',
                ])
                ->filter(static fn ($cc) => ComprobanteProveedorImputacionApSupport::esLineaCcDeudaFactura($cc))
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
        $ingresoEgreso = $this->contextoIngresoEgreso($filas);

        $out = [];
        foreach ($filas as $fila) {
            $compId = (int) ($fila['comprobante_id'] ?? 0);
            $esIngresoEgreso = (string) ($fila['origen_entrada'] ?? '') === ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO;
            $fila['es_ingreso_egreso'] = $esIngresoEgreso;
            if ($esIngresoEgreso) {
                $cajaId = (int) ($fila['caja_movimiento_id'] ?? 0);
                $asientoIe = $ingresoEgreso['asiento'][$cajaId] ?? null;
                $tieneAsientoIe = $asientoIe !== null;
                $lineasIe = $ingresoEgreso['lineas'][$cajaId] ?? [];
                $facturaIe = ComprobanteProveedorImputacionApSupport::facturaIngresoEgresoEnPesos(
                    (float) ($fila['total_origen'] ?? 0),
                    (float) ($fila['total_ars'] ?? 0),
                    (int) ($fila['moneda_id'] ?? 1),
                    $lineasIe,
                    $compId,
                );
                $asientoIeArs = $tieneAsientoIe
                    ? ComprobanteProveedorImputacionApSupport::importeDebeIngresoEgreso(
                        $compId,
                        $lineasIe,
                        $ingresoEgreso['conceptos'][$compId] ?? [],
                    )
                    : 0.0;
                $evalIe = ComprobanteProveedorImputacionApSupport::evaluarIngresoEgreso(
                    $facturaIe,
                    $asientoIeArs,
                    $tieneAsientoIe,
                    $tolerancia,
                );
                $fila['cc_ars'] = 0.0;
                $fila['asiento_ars'] = $asientoIeArs;
                $fila['ctamov_ars'] = 0.0;
                $fila['factura_ie_ars'] = $facturaIe;
                $fila['asiento_id'] = (int) ($asientoIe['id'] ?? 0);
                $fila['numeroasiento'] = (string) ($asientoIe['numero'] ?? '');
                $fila['ctamov_lineas'] = 0;
                $fila['diff_cc_asiento'] = $evalIe['diff_cc_asiento'];
                $fila['diff_asiento_ctamov'] = $evalIe['diff_asiento_ctamov'];
                $fila['diff_cc_ctamov'] = $evalIe['diff_cc_ctamov'];
                $fila['diff_cc_factura'] = $evalIe['diff_cc_factura'];
                $fila['ok'] = $evalIe['ok'];
                $fila['alertas'] = $evalIe['alertas'];
                $fila['alertas_texto'] = implode(' · ', $evalIe['alertas']);
                $out[] = $fila;

                continue;
            }
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

            $asientoArs = ComprobanteProveedorImputacionApSupport::haberAp([
                'ap_mn' => (float) ($fila['ap_mn_ars'] ?? 0),
                'ap_me' => (float) ($fila['ap_me_ars'] ?? 0),
            ]);
            $asientoAnticipoArs = round((float) ($fila['anticipo_ars'] ?? 0), 2);
            $tieneAsiento = (int) ($fila['asiento_id'] ?? 0) > 0
                && trim((string) ($fila['numeroasiento'] ?? '')) !== '';

            $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($fila['empresa_id'] ?? 0));
            $nroAsiento = (int) ($fila['numeroasiento'] ?? 0);
            $ctamov = $ctamovPorAsiento[ComprobanteProveedorImputacionApCtamovSupport::clave($empresaAnita, $nroAsiento)]
                ?? ['trio' => 0.0, 'ap' => 0.0, 'anticipo' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $ctamovArs = round((float) ($ctamov['ap'] ?? 0), 2);
            $ctamovAnticipoArs = round((float) ($ctamov['anticipo'] ?? 0), 2);
            $tieneCtamov = ! empty($ctamov['encontrado']);

            $eval = ComprobanteProveedorImputacionApSupport::evaluarTresPatas(
                $ccArs,
                $asientoArs,
                $ctamovArs,
                $tieneCc,
                $tieneAsiento,
                $tieneCtamov,
                $tolerancia,
                $asientoAnticipoArs,
                $ctamovAnticipoArs,
                isset($fila['esperado_ars']) ? (float) $fila['esperado_ars'] : null,
            );

            $fila['cc_ars'] = $ccArs;
            $fila['asiento_ars'] = $asientoArs;
            $fila['ctamov_ars'] = $ctamovArs;
            $fila['ctamov_lineas'] = (int) ($ctamov['lineas'] ?? 0);
            $fila['diff_cc_asiento'] = $eval['diff_cc_asiento'];
            $fila['diff_asiento_ctamov'] = $eval['diff_asiento_ctamov'];
            $fila['diff_cc_ctamov'] = $eval['diff_cc_ctamov'];
            $fila['diff_cc_factura'] = $eval['diff_cc_factura'];
            $fila['ok'] = $eval['ok'];
            $fila['alertas'] = $eval['alertas'];
            $fila['alertas_texto'] = implode(' · ', $eval['alertas']);
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * Asiento del movimiento de caja y conceptos del comprobante, para cruzar
     * el total del I/E sin usar la cuenta corriente.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *     lineas: array<int, list<array{monto: float, codigo: string, comprobante_proveedor_id: int, moneda_id: int, cotizacion: mixed, fecha: mixed}>>,
     *     conceptos: array<int, list<array{monto: float, codigo: string}>>,
     *     asiento: array<int, array{id: int, numero: string}>
     * }
     */
    private function contextoIngresoEgreso(array $filas): array
    {
        $vacio = ['lineas' => [], 'conceptos' => [], 'asiento' => []];
        $ie = array_values(array_filter(
            $filas,
            static fn (array $fila): bool => (string) ($fila['origen_entrada'] ?? '') === ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO
        ));
        if ($ie === []) {
            return $vacio;
        }

        $cajaIds = [];
        $compIds = [];
        $empresaPorComp = [];
        foreach ($ie as $fila) {
            $cajaId = (int) ($fila['caja_movimiento_id'] ?? 0);
            $compId = (int) ($fila['comprobante_id'] ?? 0);
            if ($cajaId > 0) {
                $cajaIds[$cajaId] = $cajaId;
            }
            if ($compId > 0) {
                $compIds[$compId] = $compId;
                $empresaPorComp[$compId] = (int) ($fila['empresa_id'] ?? 0);
            }
        }

        $lineas = [];
        $asiento = [];
        if ($cajaIds !== []) {
            $asientos = Asiento::query()
                ->whereIn('caja_movimiento_id', array_values($cajaIds))
                ->with(['asiento_movimientos.cuentacontables:id,codigo'])
                ->orderBy('id')
                ->get();

            foreach ($asientos as $row) {
                $cajaId = (int) $row->caja_movimiento_id;
                $asiento[$cajaId] = [
                    'id' => (int) $row->id,
                    'numero' => (string) ($row->numeroasiento ?? ''),
                ];
                $lineas[$cajaId] = [];
                foreach ($row->asiento_movimientos as $mov) {
                    $monto = round((float) $mov->monto, 2);
                    if ($monto <= 0) {
                        continue;
                    }
                    $lineas[$cajaId][] = [
                        'monto' => $monto,
                        'codigo' => trim((string) ($mov->cuentacontables?->codigo ?? '')),
                        'comprobante_proveedor_id' => (int) ($mov->comprobante_proveedor_id ?? 0),
                        'moneda_id' => (int) ($mov->moneda_id ?: 1),
                        'cotizacion' => $mov->cotizacion,
                        'fecha' => $row->fecha,
                    ];
                }
            }
        }

        return [
            'lineas' => $lineas,
            'conceptos' => $this->conceptosIngresoEgreso(array_values($compIds), $empresaPorComp),
            'asiento' => $asiento,
        ];
    }

    /**
     * @param  list<int>  $compIds
     * @param  array<int, int>  $empresaPorComp
     * @return array<int, list<array{monto: float, codigo: string}>>
     */
    private function conceptosIngresoEgreso(array $compIds, array $empresaPorComp): array
    {
        if ($compIds === []) {
            return [];
        }

        $filas = Comprobante_Proveedor_Concepto::query()
            ->whereIn('comprobante_proveedor_id', $compIds)
            ->with(['concepto_ivacompras.concepto_ivacompra_empresas'])
            ->get();

        $cuentaIds = [];
        $armados = [];
        foreach ($filas as $linea) {
            $compId = (int) $linea->comprobante_proveedor_id;
            $empresaId = (int) ($empresaPorComp[$compId] ?? 0);
            $cuentaId = (int) ($linea->cuentacontabledebe_id
                ?: $linea->concepto_ivacompras?->cuentacontableDebeIdParaEmpresa($empresaId > 0 ? $empresaId : null));
            if ($cuentaId <= 0) {
                continue;
            }
            $cuentaIds[$cuentaId] = $cuentaId;
            $armados[] = [
                'comp' => $compId,
                'cuenta' => $cuentaId,
                'monto' => round(abs((float) $linea->monto), 2),
            ];
        }

        $codigos = $cuentaIds === []
            ? collect()
            : Cuentacontable::query()->whereIn('id', array_values($cuentaIds))->pluck('codigo', 'id');

        $out = [];
        foreach ($armados as $armado) {
            $out[$armado['comp']][] = [
                'monto' => $armado['monto'],
                'codigo' => trim((string) ($codigos[$armado['cuenta']] ?? '')),
            ];
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
