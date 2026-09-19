<?php

namespace App\Services\Compras;

use App\Mail\Compras\PagoproveedorImputacionApDiaria;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Compras\ComprobanteProveedorImputacionApCuentasSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApCtamovSupport;
use App\Support\Compras\ComprobanteProveedorImputacionApSupport;
use App\Support\Compras\PagoproveedorImputacionApPromovSupport;
use App\Support\Compras\PagoproveedorImputacionApSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Informe diario OP a OP: CC ERP ↔ asiento ERP ↔ promov Anita ↔ ctamov Anita.
 */
final class PagoproveedorImputacionApDiariaService
{
    public function __construct(
        private readonly ComprobanteProveedorImputacionApCtamovSupport $ctamov,
        private readonly PagoproveedorImputacionApPromovSupport $promov,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
    ): array {
        $config = config('pagoproveedor.imputacion_ap_diaria', []);
        $ventana = max(1, (int) ($config['ventana_dias'] ?? 7));
        $desde = $fechaDesde ?: Carbon::today()->subDays($ventana - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();
        $tolerancia = (float) ($config['tolerancia'] ?? PagoproveedorImputacionApSupport::TOLERANCIA);
        $maxFilasMail = max(10, (int) ($config['max_filas_mail'] ?? 80));

        $empresaIds = array_values(array_filter(array_map(
            'intval',
            (array) ($config['empresas_ids'] ?? [])
        ), static fn (int $id) => $id > 0));
        if ($empresaIds === []) {
            $empresaIds = Empresa::query()->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        $pagos = Pagoproveedor::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'empresas:id,codigo,nombre',
                'monedas:id,abreviatura,nombre',
                'tipotransaccion_cajas:id,abreviatura',
                'caja_movimientos.tipotransaccioncajas:id,abreviatura',
            ])
            ->whereIn('empresa_id', $empresaIds)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->whereNotIn('estado', PagoproveedorImputacionApSupport::ESTADOS_ANULADOS)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get()
            ->filter(function (Pagoproveedor $pago): bool {
                $abrev = (string) ($pago->tipotransaccion_cajas?->abreviatura ?? '');
                if (PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(0, $abrev)) {
                    return false;
                }
                foreach ($pago->caja_movimientos ?? [] as $cm) {
                    if (PagoproveedorImputacionApSupport::esOrigenIngresoEgreso(
                        (int) ($cm->solicitudpago_id ?? 0),
                        (string) ($cm->tipotransaccioncajas?->abreviatura ?? $abrev)
                    )) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $filas = $this->armarFilas($pagos, $tolerancia);
        $partes = PagoproveedorImputacionApSupport::particionarControlDiario($filas);
        $desvios = $partes['desvios'];
        $borradores = $partes['borradores'];

        $totales = [
            'total_filas' => count($filas),
            'ok' => count($partes['ok']),
            'con_desvio' => count($desvios),
            'en_borrador' => count($borradores),
            'sin_cc' => 0,
            'sin_asiento' => 0,
            'sin_promov' => 0,
            'sin_ctamov' => 0,
            'cc_ars' => 0.0,
            'asiento_ars' => 0.0,
            'promov_ars' => 0.0,
            'ctamov_ars' => 0.0,
        ];
        foreach ($desvios as $fila) {
            $alertas = $fila['alertas'] ?? [];
            if (in_array('Sin CC', $alertas, true)) {
                $totales['sin_cc']++;
            }
            if (in_array('Sin asiento', $alertas, true)) {
                $totales['sin_asiento']++;
            }
            if (in_array('Sin promov Anita', $alertas, true)) {
                $totales['sin_promov']++;
            }
            if (in_array('Sin ctamov Anita', $alertas, true)) {
                $totales['sin_ctamov']++;
            }
        }
        foreach ($filas as $fila) {
            $totales['cc_ars'] += (float) ($fila['cc_ars'] ?? 0);
            $totales['asiento_ars'] += (float) ($fila['asiento_ars'] ?? 0);
            $totales['promov_ars'] += (float) ($fila['promov_ars'] ?? 0);
            $totales['ctamov_ars'] += (float) ($fila['ctamov_ars'] ?? 0);
        }
        foreach (['cc_ars', 'asiento_ars', 'promov_ars', 'ctamov_ars'] as $k) {
            $totales[$k] = round((float) $totales[$k], 2);
        }

        $errores = [];
        if ($totales['total_filas'] === 0) {
            $errores[] = 'Sin OP en el período: no hay control para marcar OK.';
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
                'Cada OP compara la CC ERP (valor libro de las facturas aplicadas) vs el trío AP/anticipo del asiento vs ctamov Anita.',
                'Promov Anita se controla contra el total de la OP (cabecera), no contra el AP: en cruzada ME la DC va a P&L.',
                'Solo OP de Compras del período. Excluye REVERTIDA/BAJA y las OP nacidas en Ingreso/Egreso (SP / ING / EGR / TRA).',
                'OPP/OPA son crédito (Haber−Debe negativo). AOP invierte el signo.',
                'El residual a anticipo entra al trío. Se controla aparte vs ctamov.',
                'Importes en $: CC al TC de la factura; promov al TC del pago. Haber suma, Debe resta.',
            ],
        ];

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config);
        }

        return $informe;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Pagoproveedor>  $pagos
     * @return list<array<string, mixed>>
     */
    private function armarFilas($pagos, float $tolerancia): array
    {
        if ($pagos->isEmpty()) {
            return [];
        }

        $pagoIds = $pagos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ccPorPago = Proveedor_Cuentacorriente::query()
            ->whereIn('pagoproveedor_id', $pagoIds)
            ->get([
                'id',
                'pagoproveedor_id',
                'total',
                'moneda_id',
                'cotizacion',
                'fecha',
            ])
            ->groupBy('pagoproveedor_id');

        $ccIds = $ccPorPago->flatten()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cotLibroPorCcPago = $ccIds === []
            ? collect()
            : Proveedor_Cuentacorriente_Aplicacion::query()
                ->whereIn('pagoproveedor_id', $pagoIds)
                ->whereIn('proveedor_cuentacorriente_aplicado_id', $ccIds)
                ->get(['proveedor_cuentacorriente_aplicado_id', 'cotizacion'])
                ->groupBy('proveedor_cuentacorriente_aplicado_id')
                ->map(static fn ($filas) => (float) ($filas->first()->cotizacion ?? 0));

        $asientoIds = $pagos->pluck('asiento_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $asientos = $asientoIds === []
            ? collect()
            : Asiento::query()
                ->with(['asiento_movimientos.cuentacontables', 'asiento_movimientos.monedas'])
                ->whereIn('id', $asientoIds)
                ->get()
                ->keyBy('id');

        $empresaIds = $pagos->pluck('empresa_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $catalogo = ComprobanteProveedorImputacionApCuentasSupport::armar($empresaIds);

        $clavesCtamov = [];
        $clavesPromov = [];
        foreach ($pagos as $pago) {
            $fecha = $this->fechaYmd($pago->fecha);
            $empresaAnita = PagoproveedorImputacionApPromovSupport::empresaAnitaDePago((int) $pago->empresa_id);
            $tipo = PagoproveedorImputacionApSupport::tipoDesdeComprobante((string) $pago->tipocomprobante);
            $nro = (int) $pago->numerotransaccion;
            $asiento = $asientos->get((int) ($pago->asiento_id ?? 0));
            $nroAsiento = (int) ($asiento?->numeroasiento ?? 0);
            if ($nroAsiento > 0 && $empresaAnita > 0) {
                $clavesCtamov[] = [
                    'empresa_anita' => $empresaAnita,
                    'numeroasiento' => $nroAsiento,
                    'fecha' => $fecha,
                ];
            }
            if ($nro > 0 && $empresaAnita > 0) {
                $clavesPromov[] = [
                    'empresa_anita' => $empresaAnita,
                    'tipo' => $tipo,
                    'numero' => $nro,
                    'sucursal' => (int) ($pago->sucursal ?? 1),
                    'fecha' => $fecha,
                    'signo' => PagoproveedorImputacionApSupport::signoHaberNeto($tipo),
                    'moneda_id' => (int) ($pago->moneda_id ?: 1),
                    'cotizacion' => (int) ($pago->moneda_id ?: 1) <= 1 ? 1 : ($pago->cotizacion ?? 1),
                ];
            }
        }

        $ctamovPorAsiento = $this->ctamov->sumarTrioPorAsiento($clavesCtamov, $catalogo);
        $promovPorOp = $this->promov->sumarPorOp($clavesPromov);

        $out = [];
        foreach ($pagos as $pago) {
            $fecha = $this->fechaYmd($pago->fecha);
            $tipo = PagoproveedorImputacionApSupport::tipoDesdeComprobante((string) $pago->tipocomprobante);
            $empresaAnita = PagoproveedorImputacionApPromovSupport::empresaAnitaDePago((int) $pago->empresa_id);
            $lineasCc = $ccPorPago->get($pago->id, collect());
            $ccArs = 0.0;
            $tieneCc = $lineasCc->isNotEmpty();
            foreach ($lineasCc as $cc) {
                $monedaId = (int) ($cc->moneda_id ?: ($pago->moneda_id ?? 1));
                $cotLibro = (float) ($cotLibroPorCcPago->get((int) $cc->id) ?? 0);
                if ($cotLibro <= 0) {
                    $cotLibro = (float) ($cc->cotizacion ?? ($pago->cotizacion ?? 1));
                }
                if ($monedaId <= 1) {
                    $cotLibro = 1;
                }
                $ccArs += ComprobanteProveedorImputacionApSupport::aPesosTolerante(
                    (float) ($cc->total ?? 0),
                    $monedaId,
                    $cotLibro,
                    $fecha !== '' ? $fecha : ($cc->fecha ?? null),
                    'CC OP #'.$pago->id
                );
            }
            $ccArs = round($ccArs, 2);

            $asientoId = (int) ($pago->asiento_id ?? 0);
            $asiento = $asientos->get($asientoId);
            $tieneAsiento = $asientoId > 0 && $asiento !== null && trim((string) ($asiento->numeroasiento ?? '')) !== '';
            $imputado = ComprobanteProveedorImputacionApSupport::imputacionTrio(
                $this->movimientosDeAsiento($asiento, $fecha),
                $catalogo,
                'asiento OP #'.$pago->id
            );
            $asientoArs = round((float) $imputado['trio'], 2);
            $asientoAnticipoArs = round((float) $imputado['anticipo'], 2);

            $nroAsiento = (int) ($asiento?->numeroasiento ?? 0);
            $ctamov = $ctamovPorAsiento[ComprobanteProveedorImputacionApCtamovSupport::clave($empresaAnita, $nroAsiento)]
                ?? ['trio' => 0.0, 'ap' => 0.0, 'anticipo' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $ctamovArs = round((float) ($ctamov['trio'] ?? 0), 2);
            $ctamovAnticipoArs = round((float) ($ctamov['anticipo'] ?? 0), 2);
            $tieneCtamov = ! empty($ctamov['encontrado']);

            $nro = (int) $pago->numerotransaccion;
            $promov = $promovPorOp[PagoproveedorImputacionApPromovSupport::clave($empresaAnita, $tipo, $nro)]
                ?? ['ars' => 0.0, 'monto' => 0.0, 'lineas' => 0, 'encontrado' => false];
            $promovArs = round((float) ($promov['ars'] ?? 0), 2);
            $tienePromov = ! empty($promov['encontrado']);

            $monedaPagoId = (int) ($pago->moneda_id ?: 1);
            $esperadoOpArs = ComprobanteProveedorImputacionApSupport::aPesosTolerante(
                PagoproveedorImputacionApSupport::signoHaberNeto($tipo) * abs((float) ($pago->monto ?? 0)),
                $monedaPagoId,
                $monedaPagoId <= 1 ? 1 : ($pago->cotizacion ?? 1),
                $fecha !== '' ? $fecha : null,
                'OP #'.$pago->id
            );

            $eval = PagoproveedorImputacionApSupport::evaluarCuatroPatas(
                $ccArs,
                $asientoArs,
                $promovArs,
                $ctamovArs,
                $tieneCc,
                $tieneAsiento,
                $tienePromov,
                $tieneCtamov,
                $tolerancia,
                $asientoAnticipoArs,
                $ctamovAnticipoArs,
                $esperadoOpArs,
            );

            $out[] = [
                'id' => (int) $pago->id,
                'tipo' => $tipo,
                'tipo_etiqueta' => PagoproveedorImputacionApSupport::etiquetaTipo($tipo),
                'fecha' => $fecha,
                'empresa_id' => (int) $pago->empresa_id,
                'nombreempresa' => (string) ($pago->empresas?->nombre ?? ''),
                'proveedor_id' => (int) $pago->proveedor_id,
                'codigo_proveedor' => (string) ($pago->proveedores?->codigo ?? ''),
                'nombre_proveedor' => (string) ($pago->proveedores?->nombre ?? ''),
                'pagoproveedor_id' => (int) $pago->id,
                'asiento_id' => $asientoId,
                'numeroasiento' => (string) ($asiento?->numeroasiento ?? ''),
                'etiqueta' => $pago->etiquetaComprobante(),
                'estado' => (string) ($pago->estado ?? ''),
                'moneda_id' => (int) ($pago->moneda_id ?: 1),
                'moneda' => (string) ($pago->monedas?->abreviatura ?? ''),
                'cotizacion' => (float) ($pago->cotizacion ?: 0),
                'total_origen' => round((float) ($pago->monto ?? 0), 2),
                'esperado_ars' => $esperadoOpArs,
                'cc_ars' => $ccArs,
                'asiento_ars' => $asientoArs,
                'promov_ars' => $promovArs,
                'ctamov_ars' => $ctamovArs,
                'anticipo_ars' => $asientoAnticipoArs,
                'ctamov_anticipo_ars' => $ctamovAnticipoArs,
                'ctamov_lineas' => (int) ($ctamov['lineas'] ?? 0),
                'promov_lineas' => (int) ($promov['lineas'] ?? 0),
                'diff_cc_asiento' => $eval['diff_cc_asiento'],
                'diff_asiento_ctamov' => $eval['diff_asiento_ctamov'],
                'diff_cc_ctamov' => $eval['diff_cc_ctamov'],
                'diff_cc_promov' => $eval['diff_cc_promov'],
                'diff_cc_op' => $eval['diff_cc_op'],
                'ok' => $eval['ok'],
                'alertas' => $eval['alertas'],
                'alertas_texto' => implode(' · ', $eval['alertas']),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{cuentacontable_id:int, monto:float, moneda_id:int, cotizacion:mixed, fecha:?string}>
     */
    private function movimientosDeAsiento(?Asiento $asiento, string $fechaDocumento): array
    {
        if ($asiento === null) {
            return [];
        }

        $fechaAsiento = $this->fechaYmd($asiento->fecha) ?: $fechaDocumento;
        $out = [];
        foreach ($asiento->asiento_movimientos ?? [] as $mov) {
            $out[] = [
                'cuentacontable_id' => (int) ($mov->cuentacontable_id ?? 0),
                'monto' => (float) ($mov->monto ?? 0),
                'moneda_id' => (int) ($mov->moneda_id ?: 1),
                'cotizacion' => $mov->cotizacion,
                'fecha' => $fechaAsiento,
            ];
        }

        return $out;
    }

    private function fechaYmd(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $texto = trim((string) $fecha);

        return $texto !== '' ? substr($texto, 0, 10) : '';
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
            Mail::to($destino)->send(new PagoproveedorImputacionApDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('PagoproveedorImputacionApDiaria: mail falló', ['error' => $e->getMessage()]);
        }
    }
}
