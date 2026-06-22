<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\GastronomiaCierreJornadaProcesoSnapshot;
use App\Models\Ventas\TotemWaitryGastronomia;

/**
 * Concilia totales Sistema (cobros Waitry QR/MP/Posnet no cobrados en caja Anita real) vs Informe Z.
 *
 * La plantilla de carga es un bloque unificado por jornada (Z Waitry sin discriminar tótem físico),
 * con líneas por medio de cobro (QR kiosco, Posnet, MP, QR celular, etc.).
 */
final class WaitryInformeZConciliacionSupport
{
    /** Identificador de bloque único en plantilla / payload cuando el Z no discrimina tótem. */
    public const TOTEM_ID_PLANTILLA_UNIFICADA = 0;

    public static function toleranciaMonto(): float
    {
        return max(0.0, (float) config('gastronomia.cierre_totem_informe_z_tolerancia', 0.02));
    }

    /**
     * Conciliación unificada para PDF/pantalla a partir del cierre persistido.
     * Fusiona informe Z legacy (varios tótems) en un bloque por medio de pago.
     *
     * @return array{plantilla: list<array<string, mixed>>, conciliacion: array<string, mixed>}|null
     */
    public static function conciliacionPresentacionDesdeCierre(CierreTotemJornadaGastronomia $cierre): ?array
    {
        $informeZ = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        if ($informeZ === null || ! isset($informeZ['totems'])) {
            return null;
        }

        $detalle = is_array($cierre->detalle_json) ? $cierre->detalle_json : [];
        $empresaId = (int) $cierre->empresa_id;
        $resumen = self::resumenSistemaDesdeDetalleCierre(
            $detalle,
            $empresaId,
            (int) $cierre->jornada_gastronomia_id,
        );
        $plantilla = self::plantillaCarga($empresaId, $resumen);
        $plantilla = self::fusionarInformeZEnPlantilla($plantilla, $informeZ, $empresaId);
        $conciliacion = self::conciliar($plantilla);

        return [
            'plantilla' => $plantilla,
            'conciliacion' => $conciliacion,
        ];
    }

    /**
     * Filas del Informe Z para comprobante PDF (bloque unificado o legacy ya conciliado).
     *
     * @param  array<string, mixed>  $conciliacion
     * @return list<array<string, mixed>>
     */
    public static function bloquesInformeZConciliacionParaPdf(array $conciliacion): array
    {
        $totemsPdf = [];

        foreach ($conciliacion['totems'] ?? [] as $ct) {
            if (! is_array($ct)) {
                continue;
            }
            $totemId = (int) ($ct['totem_id'] ?? 0);
            $outLineas = [];
            foreach ($ct['lineas'] ?? [] as $ln) {
                if (! is_array($ln)) {
                    continue;
                }
                $outLineas[] = [
                    'etiqueta' => (string) ($ln['etiqueta'] ?? '—'),
                    'monto_sistema' => round((float) ($ln['monto_sistema'] ?? 0), 2),
                    'monto_informe_z' => round((float) ($ln['monto_informe_z'] ?? 0), 2),
                    'diferencia' => round((float) ($ln['diferencia'] ?? 0), 2),
                    'ok' => ! empty($ln['ok']),
                ];
            }

            $totemsPdf[] = [
                'totem_id' => $totemId,
                'plantilla_unificada' => $totemId === self::TOTEM_ID_PLANTILLA_UNIFICADA,
                'ubicacion_nombre' => (string) ($ct['ubicacion_nombre'] ?? 'Informe Z Waitry'),
                'detalle' => (string) ($ct['detalle'] ?? ''),
                'waitry_table_id' => $totemId === self::TOTEM_ID_PLANTILLA_UNIFICADA
                    ? null
                    : (isset($ct['waitry_table_id']) ? (int) $ct['waitry_table_id'] : null),
                'ok' => ! empty($ct['ok']),
                'lineas' => $outLineas,
                'total_sistema' => round((float) ($ct['total_sistema'] ?? 0), 2),
                'total_informe_z' => round((float) ($ct['total_informe_z'] ?? 0), 2),
                'diferencia' => round((float) ($ct['diferencia_total'] ?? 0), 2),
            ];
        }

        return $totemsPdf;
    }

    /**
     * Resumen para plantilla Informe Z: preferir {@see resumen_informe_z} persistido al cierre.
     *
     * @param  array<string, mixed>  $detalle  detalle_json del cierre tótem
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function resumenSistemaDesdeDetalleCierre(array $detalle, int $empresaId = 0, ?int $jornadaId = null): array
    {
        // Tras el cierre de jornada el Informe Z es histórico: no recalcular Sistema desde
        // snapshot/proceso posterior (facturas lote post-cierre, relecturas Waitry, etc.).
        $informeZ = $detalle['resumen_informe_z'] ?? null;
        if (is_array($informeZ) && is_array($informeZ['por_totem'] ?? null)) {
            return self::reconstruirResumenInformeZConDesglose($informeZ, $empresaId);
        }

        if ($jornadaId !== null && $jornadaId > 0 && $empresaId > 0) {
            $desdeProceso = self::resumenInformeZDesdeProcesoSnapshot($jornadaId, $empresaId);
            if ($desdeProceso !== null && is_array($desdeProceso['por_totem'] ?? null)) {
                return self::reconstruirResumenInformeZConDesglose($desdeProceso, $empresaId);
            }
        }

        $filtrado = self::filtrarResumenSoloCreditCardPosnet(
            is_array($detalle['resumen_totems'] ?? null) ? $detalle['resumen_totems'] : ['por_totem' => [], 'total_general' => []],
        );

        return self::reconstruirResumenInformeZConDesglose($filtrado, $empresaId);
    }

    /**
     * Repara resumen legacy: total_general fusionado (p. ej. todo en Mercado Pago) → categorías QR / Posnet / MP.
     *
     * @param  array{por_totem?:list<array<string,mixed>>,total_general?:array<string,mixed>}  $resumen
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function reconstruirResumenInformeZConDesglose(array $resumen, int $empresaId = 0): array
    {
        $porTotem = [];
        $globalMedios = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;

        foreach ($resumen['por_totem'] ?? [] as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }

            $mediosPorClave = [];
            $totalBloque = 0.0;
            $cantBloque = 0;

            foreach ($bloque['por_medio_pago'] ?? [] as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $categoria = WaitryMedioPagoCuentacajaSupport::categoriaInformeZDesglose(
                    $m['categoria'] ?? $m['tipo'] ?? null,
                    $m['gateway'] ?? null,
                );
                if ($categoria === null || ! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($categoria)) {
                    continue;
                }

                $total = round((float) ($m['total'] ?? 0), 2);
                $cant = (int) ($m['cantidad'] ?? 0);
                if ($total <= 0.0001 && $cant <= 0) {
                    continue;
                }

                $clave = 'cat:'.$categoria;
                if (! isset($mediosPorClave[$clave])) {
                    $mediosPorClave[$clave] = [
                        'tipo' => $categoria,
                        'categoria' => $categoria,
                        'etiqueta' => WaitryMedioPagoCuentacajaSupport::etiquetaCategoriaInformeZ($categoria),
                        'cantidad' => $cant,
                        'total' => $total,
                        'cuentacaja_label' => $m['cuentacaja_label'] ?? null,
                    ];
                } else {
                    $mediosPorClave[$clave]['cantidad'] += $cant;
                    $mediosPorClave[$clave]['total'] = round($mediosPorClave[$clave]['total'] + $total, 2);
                }

                $totalBloque = round($totalBloque + $total, 2);
                $cantBloque += $cant;
            }

            if ($mediosPorClave === []) {
                continue;
            }

            $mediosOrdenados = array_values($mediosPorClave);
            usort($mediosOrdenados, fn (array $a, array $b) => strcmp((string) ($a['etiqueta'] ?? ''), (string) ($b['etiqueta'] ?? '')));

            $porTotem[] = [
                'totem_id' => $bloque['totem_id'] ?? null,
                'ubicacion_nombre' => $bloque['ubicacion_nombre'] ?? '',
                'detalle' => $bloque['detalle'] ?? '',
                'waitry_layout_id' => $bloque['waitry_layout_id'] ?? null,
                'waitry_table_id' => $bloque['waitry_table_id'] ?? null,
                'cantidad_ordenes' => $cantBloque > 0 ? $cantBloque : (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => $totalBloque > 0.0001 ? $totalBloque : round((float) ($bloque['total_ingreso'] ?? 0), 2),
                'por_medio_pago' => $mediosOrdenados,
                'por_table_id' => self::filtrarPorTableIdInformeZ($bloque['por_table_id'] ?? []),
            ];

            $globalCantidad += (int) ($porTotem[array_key_last($porTotem)]['cantidad_ordenes'] ?? 0);
            $globalIngreso = round($globalIngreso + (float) ($porTotem[array_key_last($porTotem)]['total_ingreso'] ?? 0), 2);

            foreach ($mediosOrdenados as $medio) {
                $globalKey = 'cat:'.($medio['categoria'] ?? $medio['tipo'] ?? '');
                if ($globalKey === 'cat:') {
                    continue;
                }
                if (! isset($globalMedios[$globalKey])) {
                    $globalMedios[$globalKey] = $medio;
                } else {
                    $globalMedios[$globalKey]['cantidad'] += (int) ($medio['cantidad'] ?? 0);
                    $globalMedios[$globalKey]['total'] = round(
                        (float) ($globalMedios[$globalKey]['total'] ?? 0) + (float) ($medio['total'] ?? 0),
                        2,
                    );
                }
            }
        }

        $mediosGlobal = array_values($globalMedios);
        usort($mediosGlobal, fn (array $a, array $b) => strcmp((string) ($a['etiqueta'] ?? ''), (string) ($b['etiqueta'] ?? '')));

        return [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => $mediosGlobal,
            ],
        ];
    }

    /**
     * Resumen Informe Z desde snapshot del proceso Caja (cuadro «Waitry pagado sin facturar»).
     * Solo como fallback en {@see resumenSistemaDesdeDetalleCierre()} si aún no hay
     * {@code detalle_json.resumen_informe_z} persistido al cierre de jornada.
     *
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}|null
     */
    public static function resumenInformeZDesdeProcesoSnapshot(int $jornadaId, int $empresaId): ?array
    {
        if ($jornadaId <= 0 || $empresaId <= 0) {
            return null;
        }

        $snapshot = GastronomiaCierreJornadaProcesoSnapshot::query()
            ->where('jornada_gastronomia_id', $jornadaId)
            ->first();

        if ($snapshot === null || $snapshot->lineas() === []) {
            return null;
        }

        $totems = TotemWaitryGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->get()
            ->filter(static fn (TotemWaitryGastronomia $t) => $t->participaInformeZ());

        $lineas = [];
        foreach ($snapshot->lineas() as $ln) {
            if (! is_array($ln)) {
                continue;
            }
            if (! empty($ln['discrepancia_gap'])) {
                continue;
            }
            if (WaitryOrdenEstadoSupport::esAnuladaPorDescuentoTotalLinea($ln)) {
                continue;
            }
            $lineas[] = $ln;
        }

        if ($lineas === []) {
            return null;
        }

        return WaitryTotemJornadaResumenSupport::armarParaInformeZ($totems, $lineas, $empresaId);
    }

    /**
     * @param  array{por_totem:list<array<string,mixed>>,total_general?:array<string,mixed>}  $resumen
     * @return array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}
     */
    public static function filtrarResumenSoloCreditCardPosnet(array $resumen): array
    {
        $porTotem = [];
        $globalCantidad = 0;
        $globalIngreso = 0.0;
        $globalMedios = [];

        foreach ($resumen['por_totem'] ?? [] as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }
            $mediosFiltrados = [];
            $totalBloque = 0.0;
            $cantBloque = 0;

            foreach ($bloque['por_medio_pago'] ?? [] as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $tipoOCategoria = $m['categoria'] ?? $m['tipo'] ?? null;
                if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                    continue;
                }
                $mediosFiltrados[] = $m;
                $totalBloque = round($totalBloque + (float) ($m['total'] ?? 0), 2);
                $cantBloque += (int) ($m['cantidad'] ?? 0);
            }

            if ($mediosFiltrados === [] && (float) ($bloque['total_ingreso'] ?? 0) <= 0.0001) {
                continue;
            }

            $porTotem[] = [
                ...$bloque,
                'cantidad_ordenes' => $cantBloque > 0 ? $cantBloque : (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => $totalBloque > 0.0001 ? $totalBloque : round((float) ($bloque['total_ingreso'] ?? 0), 2),
                'por_medio_pago' => $mediosFiltrados,
                'por_table_id' => self::filtrarPorTableIdInformeZ($bloque['por_table_id'] ?? []),
            ];

            $globalCantidad += (int) ($porTotem[array_key_last($porTotem)]['cantidad_ordenes'] ?? 0);
            $globalIngreso = round($globalIngreso + (float) ($porTotem[array_key_last($porTotem)]['total_ingreso'] ?? 0), 2);

            foreach ($mediosFiltrados as $medio) {
                $tipoOCategoria = $medio['categoria'] ?? $medio['tipo'] ?? null;
                if ($tipoOCategoria === null) {
                    continue;
                }
                $globalKey = WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipoOCategoria)
                    ? 'cat:'.$tipoOCategoria
                    : WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipoOCategoria, 0);
                if ($globalKey === '__excl__') {
                    continue;
                }
                if (! isset($globalMedios[$globalKey])) {
                    $globalMedios[$globalKey] = $medio;
                } else {
                    $globalMedios[$globalKey]['cantidad'] += (int) ($medio['cantidad'] ?? 0);
                    $globalMedios[$globalKey]['total'] = round(
                        (float) ($globalMedios[$globalKey]['total'] ?? 0) + (float) ($medio['total'] ?? 0),
                        2,
                    );
                }
            }
        }

        return [
            'por_totem' => $porTotem,
            'total_general' => [
                'cantidad_ordenes' => $globalCantidad,
                'total_ingreso' => $globalIngreso,
                'por_medio_pago' => array_values($globalMedios),
            ],
        ];
    }

    /**
     * Plantilla de carga: un bloque unificado por jornada, líneas por medio de cobro.
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenSistema
     * @return list<array<string, mixed>>
     */
    public static function plantillaCarga(int $empresaId, array $resumenSistema): array
    {
        $tieneTotemsInformeZ = TotemWaitryGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->where('informe_z_habilitado', true)
            ->exists();

        if (! $tieneTotemsInformeZ) {
            return self::plantillaCargaFallbackPorTotem($empresaId, $resumenSistema);
        }

        $mediosSistema = self::mediosSistemaDesdeTotalGeneral($resumenSistema, $empresaId);
        $lineas = self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema);
        if ($lineas === [] && $mediosSistema === []) {
            $lineas = self::lineasPlantillaDesdeTiposCatalogo($empresaId);
        }

        $totalSistema = self::totalIngresoSistemaDesdeMedios($mediosSistema);
        if ($totalSistema <= 0.0001) {
            $totalSistema = round((float) ($resumenSistema['total_general']['total_ingreso'] ?? 0), 2);
        }

        return [[
            'totem_id' => self::TOTEM_ID_PLANTILLA_UNIFICADA,
            'plantilla_unificada' => true,
            'ubicacion_nombre' => 'Informe Z Waitry',
            'detalle' => 'Salón / jornada',
            'waitry_table_id' => null,
            'total_ingreso_sistema' => $totalSistema,
            'lineas' => $lineas,
            'aviso_sin_table_id' => false,
        ]];
    }

    /**
     * @param  array{por_totem?:list<array<string,mixed>>,total_general?:array<string,mixed>}  $resumenSistema
     * @return array<string, array<string, mixed>>
     */
    public static function mediosSistemaDesdeTotalGeneral(array $resumenSistema, int $empresaId): array
    {
        $mediosSistema = [];
        foreach ($resumenSistema['total_general']['por_medio_pago'] ?? [] as $m) {
            if (! is_array($m)) {
                continue;
            }
            $tipoOCategoria = $m['categoria'] ?? $m['tipo'] ?? null;
            if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                continue;
            }
            $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipoOCategoria, $empresaId);
            if ($claveMedio === '__excl__') {
                continue;
            }
            if (! isset($mediosSistema[$claveMedio])) {
                $mediosSistema[$claveMedio] = $m;
            } else {
                $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                    + (int) ($m['cantidad'] ?? 0);
                $mediosSistema[$claveMedio]['total'] = round(
                    (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                    2,
                );
            }
        }

        return $mediosSistema;
    }

    /**
     * Suma montos Z de payload legacy (varios tótems) o unificado (totem_id 0) por cuenta y tipo.
     *
     * @param  list<array<string, mixed>>  $totemsPayload
     * @return array{cuentas: array<int, float>, tipos: array<string, float>}
     */
    public static function acumularMontosInformeZDesdePayload(array $totemsPayload, int $empresaId): array
    {
        $cuentas = [];
        $tipos = [];

        foreach ($totemsPayload as $t) {
            if (! is_array($t)) {
                continue;
            }
            foreach ($t['lineas'] ?? [] as $ln) {
                if (! is_array($ln)) {
                    continue;
                }
                $monto = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                if ($monto <= 0.0001) {
                    continue;
                }
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0 && ! WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
                    $cuentas[$ccId] = round(($cuentas[$ccId] ?? 0) + $monto, 2);
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                if ($tipo !== null) {
                    $tipos[$tipo] = round(($tipos[$tipo] ?? 0) + $monto, 2);
                }
            }
        }

        return ['cuentas' => $cuentas, 'tipos' => $tipos];
    }

    /**
     * @param  list<array<string, mixed>>  $plantilla
     */
    public static function plantillaEsUnificada(array $plantilla): bool
    {
        return count($plantilla) === 1 && ! empty($plantilla[0]['plantilla_unificada']);
    }

    /**
     * Precarga Informe Z = Sistema (conciliación en cero al cerrar desde Gastronomía).
     *
     * @param  list<array<string, mixed>>  $plantilla
     * @return list<array<string, mixed>>
     */
    public static function precargarMontosInformeZDesdeSistema(array $plantilla): array
    {
        foreach ($plantilla as $bi => $bloque) {
            $lineas = is_array($bloque['lineas'] ?? null) ? $bloque['lineas'] : [];
            foreach ($lineas as $li => $ln) {
                if (! is_array($ln)) {
                    continue;
                }
                $lineas[$li]['monto_informe_z'] = round((float) ($ln['monto_sistema'] ?? 0), 2);
            }
            $plantilla[$bi]['lineas'] = $lineas;
        }

        return $plantilla;
    }

    /**
     * Fallback si no hay tótems con Informe Z habilitado: un bloque por entrada en resumen.
     *
     * @param  array{por_totem:list<array<string,mixed>>,total_general:array<string,mixed>}  $resumenSistema
     * @return list<array<string, mixed>>
     */
    private static function plantillaCargaFallbackPorTotem(int $empresaId, array $resumenSistema): array
    {
        $bloques = [];
        foreach ($resumenSistema['por_totem'] ?? [] as $sistema) {
            if (! is_array($sistema)) {
                continue;
            }
            $mediosSistema = [];
            foreach ($sistema['por_medio_pago'] ?? [] as $m) {
                $tipoOCategoria = $m['categoria'] ?? $m['tipo'] ?? null;
                if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                    continue;
                }
                $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipoOCategoria, $empresaId);
                if ($claveMedio === '__excl__') {
                    continue;
                }
                if (! isset($mediosSistema[$claveMedio])) {
                    $mediosSistema[$claveMedio] = $m;
                } else {
                    $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                        + (int) ($m['cantidad'] ?? 0);
                    $mediosSistema[$claveMedio]['total'] = round(
                        (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                        2,
                    );
                }
            }

            if ($mediosSistema === [] && (float) ($sistema['total_ingreso'] ?? 0) <= 0.0001) {
                continue;
            }

            $bloques[] = [
                'totem_id' => $sistema['totem_id'] ?? null,
                'ubicacion_nombre' => $sistema['ubicacion_nombre'] ?? 'Tótem',
                'detalle' => $sistema['detalle'] ?? '',
                'waitry_table_id' => $sistema['waitry_table_id'] ?? null,
                'total_ingreso_sistema' => self::totalIngresoSistemaDesdeMedios($mediosSistema),
                'lineas' => self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema),
            ];
        }

        return $bloques;
    }

    /**
     * @param  list<array<string, mixed>>  $plantillaTotems  Salida de plantillaCarga con montos informe_z completados
     * @return array{ok:bool,tolerancia:float,totems:list<array<string,mixed>>}
     */
    public static function conciliar(array $plantillaTotems): array
    {
        $tol = self::toleranciaMonto();
        $resultadoTotems = [];
        $okGlobal = true;

        foreach ($plantillaTotems as $bloque) {
            $lineasConc = [];
            $okTotem = true;
            $totalSistema = 0.0;
            $totalZ = 0.0;

            foreach ($bloque['lineas'] ?? [] as $ln) {
                $mSistema = round((float) ($ln['monto_sistema'] ?? 0), 2);
                $mZ = round((float) ($ln['monto_informe_z'] ?? 0), 2);
                $diff = round($mZ - $mSistema, 2);
                $okLinea = abs($diff) <= $tol
                    || ($mSistema <= $tol && $mZ <= $tol);

                if (! $okLinea) {
                    $okTotem = false;
                    $okGlobal = false;
                }

                $totalSistema = round($totalSistema + $mSistema, 2);
                $totalZ = round($totalZ + $mZ, 2);

                $lineasConc[] = [
                    'tipo_waitry' => $ln['tipo_waitry'] ?? null,
                    'etiqueta' => $ln['etiqueta'] ?? '—',
                    'monto_sistema' => $mSistema,
                    'cantidad_sistema' => (int) ($ln['cantidad_sistema'] ?? 0),
                    'monto_informe_z' => $mZ,
                    'diferencia' => $diff,
                    'ok' => $okLinea,
                ];
            }

            $diffTotal = round($totalZ - $totalSistema, 2);
            if (abs($diffTotal) > $tol) {
                $okTotem = false;
                $okGlobal = false;
            }

            $resultadoTotems[] = [
                'totem_id' => $bloque['totem_id'] ?? null,
                'ubicacion_nombre' => $bloque['ubicacion_nombre'] ?? '',
                'detalle' => $bloque['detalle'] ?? '',
                'waitry_table_id' => $bloque['waitry_table_id'] ?? null,
                'ok' => $okTotem,
                'total_sistema' => $totalSistema,
                'total_informe_z' => $totalZ,
                'diferencia_total' => $diffTotal,
                'lineas' => $lineasConc,
            ];
        }

        return [
            'ok' => $okGlobal,
            'tolerancia' => $tol,
            'totems' => $resultadoTotems,
        ];
    }

    /**
     * Aplica montos guardados en informe_z_json sobre la plantilla.
     *
     * @param  list<array<string, mixed>>  $plantilla
     * @param  array<string, mixed>|null  $informeZ
     * @return list<array<string, mixed>>
     */
    public static function fusionarInformeZEnPlantilla(array $plantilla, ?array $informeZ, int $empresaId = 0): array
    {
        if ($informeZ === null || ($informeZ['totems'] ?? []) === []) {
            return $plantilla;
        }

        if (self::plantillaEsUnificada($plantilla)) {
            $acum = self::acumularMontosInformeZDesdePayload($informeZ['totems'] ?? [], $empresaId);
            foreach ($plantilla as &$bloque) {
                foreach ($bloque['lineas'] as &$ln) {
                    $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                    if ($tipo !== null && array_key_exists($tipo, $acum['tipos'])) {
                        $ln['monto_informe_z'] = $acum['tipos'][$tipo];

                        continue;
                    }
                    // Varios medios (Posnet, QR, MP) comparten GMEP: no usar suma por cuenta en otra categoría.
                    if (WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($ln['tipo_waitry'] ?? null)) {
                        continue;
                    }
                    $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                    if ($ccId > 0 && array_key_exists($ccId, $acum['cuentas'])) {
                        $ln['monto_informe_z'] = $acum['cuentas'][$ccId];
                    }
                }
                unset($ln);
            }
            unset($bloque);

            return $plantilla;
        }

        $mapZPorCuenta = [];
        $mapZPorTipo = [];
        $mapZPorTotemLegacy = [];
        foreach ($informeZ['totems'] ?? [] as $t) {
            $tid = (int) ($t['totem_id'] ?? 0);
            if ($tid <= 0) {
                continue;
            }
            $tableId = (int) ($t['waitry_table_id'] ?? 0);
            $claveBloque = self::claveBloqueInformeZ($tid, $tableId);
            $mapZPorCuenta[$claveBloque] = [];
            $mapZPorTipo[$claveBloque] = [];
            if ($tableId <= 0) {
                $mapZPorTotemLegacy[$tid] = ['cuentas' => [], 'tipos' => []];
            }
            foreach ($t['lineas'] ?? [] as $ln) {
                $monto = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0 && ! WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
                    $mapZPorCuenta[$claveBloque][$ccId] = $monto;
                    if ($tableId <= 0) {
                        $mapZPorTotemLegacy[$tid]['cuentas'][$ccId] = $monto;
                    }
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                if ($tipo !== null) {
                    $mapZPorTipo[$claveBloque][$tipo] = $monto;
                    if ($tableId <= 0) {
                        $mapZPorTotemLegacy[$tid]['tipos'][$tipo] = $monto;
                    }
                }
            }
        }

        foreach ($plantilla as &$bloque) {
            $tid = (int) ($bloque['totem_id'] ?? 0);
            $tableId = (int) ($bloque['waitry_table_id'] ?? 0);
            $claveBloque = self::claveBloqueInformeZ($tid, $tableId);
            $zCuentas = $mapZPorCuenta[$claveBloque] ?? [];
            $zTipos = $mapZPorTipo[$claveBloque] ?? [];
            if ($zCuentas === [] && $zTipos === [] && isset($mapZPorTotemLegacy[$tid])) {
                $zCuentas = $mapZPorTotemLegacy[$tid]['cuentas'];
                $zTipos = $mapZPorTotemLegacy[$tid]['tipos'];
            }
            foreach ($bloque['lineas'] as &$ln) {
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0 && array_key_exists($ccId, $zCuentas)) {
                    $ln['monto_informe_z'] = $zCuentas[$ccId];

                    continue;
                }
                $tipo = WaitryMedioPagoCuentacajaSupport::tipoParaClaveMapaInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                if ($tipo !== null && array_key_exists($tipo, $zTipos)) {
                    $ln['monto_informe_z'] = $zTipos[$tipo];
                }
            }
            unset($ln);
        }
        unset($bloque);

        return $plantilla;
    }

    /**
     * @param  list<array<string, mixed>>  $porTableId
     * @return list<array<string, mixed>>
     */
    private static function filtrarPorTableIdInformeZ(array $porTableId): array
    {
        $out = [];
        foreach ($porTableId as $bloque) {
            if (! is_array($bloque)) {
                continue;
            }
            $mediosFiltrados = [];
            $totalBloque = 0.0;
            $cantBloque = 0;
            foreach ($bloque['por_medio_pago'] ?? [] as $m) {
                if (! is_array($m)) {
                    continue;
                }
                $tipoOCategoria = $m['categoria'] ?? $m['tipo'] ?? null;
                if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                    continue;
                }
                $mediosFiltrados[] = $m;
                $totalBloque = round($totalBloque + (float) ($m['total'] ?? 0), 2);
                $cantBloque += (int) ($m['cantidad'] ?? 0);
            }
            if ($mediosFiltrados === [] && (float) ($bloque['total_ingreso'] ?? 0) <= 0.0001) {
                continue;
            }
            $out[] = [
                ...$bloque,
                'cantidad_ordenes' => $cantBloque > 0 ? $cantBloque : (int) ($bloque['cantidad_ordenes'] ?? 0),
                'total_ingreso' => $totalBloque > 0.0001 ? $totalBloque : round((float) ($bloque['total_ingreso'] ?? 0), 2),
                'por_medio_pago' => $mediosFiltrados,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $sistemaBloque
     * @return list<array<string, mixed>>
     */
    private static function expandirBloquePlantillaPorTableIds(
        int $empresaId,
        TotemWaitryGastronomia $totem,
        array $bloqueAgregado,
        ?array $sistemaBloque,
    ): array {
        $porTable = [];
        foreach ($sistemaBloque['por_table_id'] ?? [] as $tbl) {
            if (! is_array($tbl)) {
                continue;
            }
            $tableId = (int) ($tbl['waitry_table_id'] ?? 0);
            if ($tableId > 0) {
                $porTable[$tableId] = $tbl;
            }
        }

        $bloques = [];
        foreach ($totem->waitryTableIds() as $tableId) {
            $tbl = $porTable[$tableId] ?? null;
            $mediosSistema = self::mediosInformeZDesdePorTable($empresaId, $tbl);
            $lineas = self::lineasPlantillaDesdeMediosSistema($empresaId, $mediosSistema);
            if ($lineas === [] && $mediosSistema === []) {
                $lineas = self::lineasPlantillaDesdeTiposCatalogo($empresaId);
            }

            $bloques[] = [
                'totem_id' => (int) $totem->id,
                'ubicacion_nombre' => (string) ($bloqueAgregado['ubicacion_nombre'] ?? $totem->ubicacion?->nombre ?? '—'),
                'detalle' => trim((string) ($bloqueAgregado['detalle'] ?? $totem->detalle ?? '')),
                'waitry_table_id' => $tableId,
                'waitry_table_name' => $tbl['waitry_table_name'] ?? null,
                'total_ingreso_sistema' => self::totalIngresoSistemaDesdeMedios($mediosSistema),
                'lineas' => $lineas,
                'aviso_sin_table_id' => (bool) ($bloqueAgregado['aviso_sin_table_id'] ?? false),
            ];
        }

        return $bloques !== [] ? $bloques : [$bloqueAgregado];
    }

    /**
     * @param  array<string, mixed>|null  $tbl
     * @return array<string, array<string, mixed>>
     */
    private static function mediosInformeZDesdePorTable(int $empresaId, ?array $tbl): array
    {
        if ($tbl === null) {
            return [];
        }

        $mediosSistema = [];
        foreach ($tbl['por_medio_pago'] ?? [] as $m) {
            if (! is_array($m)) {
                continue;
            }
            $tipoOCategoria = $m['categoria'] ?? $m['tipo'] ?? null;
            if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                continue;
            }
            $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipoOCategoria, $empresaId);
            if ($claveMedio === '__excl__') {
                continue;
            }
            if (! isset($mediosSistema[$claveMedio])) {
                $mediosSistema[$claveMedio] = $m;
            } else {
                $mediosSistema[$claveMedio]['cantidad'] = (int) ($mediosSistema[$claveMedio]['cantidad'] ?? 0)
                    + (int) ($m['cantidad'] ?? 0);
                $mediosSistema[$claveMedio]['total'] = round(
                    (float) ($mediosSistema[$claveMedio]['total'] ?? 0) + (float) ($m['total'] ?? 0),
                    2,
                );
            }
        }

        return $mediosSistema;
    }

    private static function claveBloqueInformeZ(int $totemId, int $tableId): string
    {
        return $totemId.':'.$tableId;
    }

    /**
     * @param  array<string, array<string, mixed>>  $mediosSistema
     */
    private static function totalIngresoSistemaDesdeMedios(array $mediosSistema): float
    {
        $total = 0.0;
        foreach ($mediosSistema as $m) {
            $total = round($total + (float) ($m['total'] ?? 0), 2);
        }

        return $total;
    }

    private static function lineaPlantillaDesdeTipo(int $empresaId, string $tipoOCategoria, ?array $medioSistema): array
    {
        $categoria = WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipoOCategoria)
            ? $tipoOCategoria
            : (WaitryMedioPagoCuentacajaSupport::categoriaInformeZDesglose($tipoOCategoria) ?? $tipoOCategoria);
        $tipoCuenta = WaitryMedioPagoCuentacajaSupport::tipoWaitryDesdeCategoriaInformeZ($categoria);
        $cuenta = WaitryMedioPagoCuentacajaSupport::cuentaParaTipoInformeZ($tipoCuenta, $empresaId);
        $etiquetaMedio = WaitryMedioPagoCuentacajaSupport::etiquetaCategoriaInformeZ($categoria);
        $etiquetaCuenta = $cuenta !== null
            ? trim(($cuenta['codigo'] ?? '').' — '.($cuenta['nombre'] ?? ''))
            : $etiquetaMedio;

        return [
            'tipo_waitry' => $categoria,
            'categoria_informe_z' => $categoria,
            'etiqueta' => (string) ($medioSistema['etiqueta'] ?? $etiquetaMedio),
            'cuentacaja_id' => $cuenta['id'] ?? null,
            'cuentacaja_codigo' => $cuenta['codigo'] ?? '',
            'cuentacaja_nombre' => $cuenta['nombre'] ?? $etiquetaMedio,
            'moneda_abreviatura' => $cuenta['moneda_abreviatura'] ?? 'ARS',
            'monto_sistema' => round((float) ($medioSistema['total'] ?? 0), 2),
            'cantidad_sistema' => (int) ($medioSistema['cantidad'] ?? 0),
            'monto_informe_z' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function lineaInformeZValida(array $linea, int $empresaId): bool
    {
        $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
        if ($ccId > 0 && WaitryMedioPagoCuentacajaSupport::esCuentacajaTotem($ccId, $empresaId)) {
            return false;
        }

        $tipo = $linea['tipo_waitry'] ?? null;
        if (WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipo)) {
            return true;
        }

        $tipoNorm = WaitryMedioPagoCuentacajaSupport::normalizarTipo($tipo);

        return $tipoNorm !== null
            && ! WaitryMedioPagoCuentacajaSupport::esTipoExcluidoInformeZ($tipoNorm)
            && WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipoNorm);
    }

    /**
     * @param  array<string, array<string, mixed>>  $mediosSistema  claveMedioInformeZ => medio resumen
     * @return list<array<string, mixed>>
     */
    private static function lineasPlantillaDesdeMediosSistema(int $empresaId, array $mediosSistema): array
    {
        $lineasPorClave = [];

        foreach ($mediosSistema as $m) {
            $categoria = $m['categoria'] ?? $m['tipo'] ?? null;
            if ($categoria === null) {
                continue;
            }
            if (! WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($categoria)) {
                $tipoRaw = WaitryMedioPagoCuentacajaSupport::normalizarTipo($categoria);
                if ($tipoRaw === null || ! WaitryMedioPagoCuentacajaSupport::esTipoPagoInformeZSistema($tipoRaw)) {
                    continue;
                }
            }
            $linea = self::lineaPlantillaDesdeTipo($empresaId, (string) $categoria, $m);
            if (! self::lineaInformeZValida($linea, $empresaId)) {
                continue;
            }
            $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $linea, $empresaId);
        }

        $lineas = array_values($lineasPorClave);
        usort($lineas, fn (array $a, array $b) => strcmp((string) ($a['etiqueta'] ?? ''), (string) ($b['etiqueta'] ?? '')));

        return $lineas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function lineasPlantillaDesdeTiposCatalogo(int $empresaId): array
    {
        $lineasPorClave = [];

        foreach (self::tiposRepresentativosCatalogoInformeZ($empresaId) as $tipo) {
            $linea = self::lineaPlantillaDesdeTipo($empresaId, $tipo, null);
            if (! self::lineaInformeZValida($linea, $empresaId)) {
                continue;
            }
            $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $linea, $empresaId);
        }

        return array_values($lineasPorClave);
    }

    /**
     * Categorías por defecto en plantilla vacía (desglose QR / Posnet / MP).
     *
     * @param  array<string, string>  $tiposCatalogo
     * @return list<string>
     */
    private static function tiposRepresentativosCatalogo(int $empresaId, array $tiposCatalogo): array
    {
        $categorias = [
            WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_KIOSCO,
            WaitryMedioPagoCuentacajaSupport::CATEGORIA_POSNET_KIOSCO,
            WaitryMedioPagoCuentacajaSupport::CATEGORIA_MERCADOPAGO,
            WaitryMedioPagoCuentacajaSupport::CATEGORIA_QR_CELULAR,
            WaitryMedioPagoCuentacajaSupport::CATEGORIA_MP_CELULAR,
        ];

        foreach (array_keys($tiposCatalogo) as $tipoRaw) {
            $cat = WaitryMedioPagoCuentacajaSupport::categoriaInformeZDesglose($tipoRaw);
            if ($cat !== null && ! in_array($cat, $categorias, true)) {
                $categorias[] = $cat;
            }
        }

        sort($categorias);

        return $categorias;
    }

    /**
     * @param  array<string, array<string, mixed>>  $lineasPorClave
     * @return array<string, array<string, mixed>>
     */
    private static function fusionarLineaEnMapa(array $lineasPorClave, array $linea, int $empresaId): array
    {
        $categoria = $linea['categoria_informe_z']
            ?? (WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($linea['tipo_waitry'] ?? null)
                ? $linea['tipo_waitry']
                : null);
        if ($categoria !== null) {
            $clave = 'cat:'.$categoria;
        } else {
            $ccId = (int) ($linea['cuentacaja_id'] ?? 0);
            $clave = $ccId > 0
                ? 'cc:'.$ccId
                : WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($linea['tipo_waitry'] ?? null, $empresaId);
        }
        if ($clave === '__excl__') {
            return $lineasPorClave;
        }

        if (! isset($lineasPorClave[$clave])) {
            $lineasPorClave[$clave] = $linea;

            return $lineasPorClave;
        }

        $lineasPorClave[$clave]['monto_sistema'] = round(
            (float) ($lineasPorClave[$clave]['monto_sistema'] ?? 0) + (float) ($linea['monto_sistema'] ?? 0),
            2,
        );
        $lineasPorClave[$clave]['cantidad_sistema'] = (int) ($lineasPorClave[$clave]['cantidad_sistema'] ?? 0)
            + (int) ($linea['cantidad_sistema'] ?? 0);

        return $lineasPorClave;
    }

    /**
     * Reparte ingreso huérfano (totem_id null) entre bloques de plantilla cuando aún quedó sin asignar.
     *
     * @param  list<array<string, mixed>>  $bloques
     * @param  array<string, mixed>  $huerfano
     * @return list<array<string, mixed>>
     */
    private static function fusionarBloqueHuerfanoEnPlantilla(array $bloques, array $huerfano, int $empresaId): array
    {
        $n = count($bloques);
        if ($n <= 0) {
            return $bloques;
        }

        foreach ($huerfano['por_medio_pago'] ?? [] as $medio) {
            $tipoOCategoria = $medio['categoria'] ?? $medio['tipo'] ?? null;
            if (! WaitryMedioPagoCuentacajaSupport::medioInformeZValidoEnResumen($tipoOCategoria)) {
                continue;
            }
            $claveMedio = WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($tipoOCategoria, $empresaId);
            if ($claveMedio === '__excl__') {
                continue;
            }
            $tipoCanon = WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipoOCategoria)
                ? $tipoOCategoria
                : (WaitryMedioPagoCuentacajaSupport::tipoRepresentativoInformeZ($tipoOCategoria) ?? $tipoOCategoria);
            $total = round((float) ($medio['total'] ?? 0), 2);
            $cant = (int) ($medio['cantidad'] ?? 0);
            $parte = round($total / $n, 2);
            $parteCant = (int) floor($cant / $n);

            foreach ($bloques as $i => &$bloque) {
                $lineasPorClave = [];
                foreach ($bloque['lineas'] ?? [] as $ln) {
                    $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                    $clave = $ccId > 0
                        ? 'cc:'.$ccId
                        : WaitryMedioPagoCuentacajaSupport::claveMedioInformeZ($ln['tipo_waitry'] ?? null, $empresaId);
                    if ($clave !== '__excl__') {
                        $lineasPorClave[$clave] = $ln;
                    }
                }

                $montoLinea = $parte;
                if ($i === $n - 1) {
                    $montoLinea = round($total - $parte * ($n - 1), 2);
                }
                $cantLinea = $parteCant;
                if ($i === $n - 1) {
                    $cantLinea = $cant - $parteCant * ($n - 1);
                }

                $etiqueta = $medio['etiqueta'] ?? (WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipoCanon)
                    ? WaitryMedioPagoCuentacajaSupport::etiquetaCategoriaInformeZ($tipoCanon)
                    : WaitryMedioPagoCuentacajaSupport::etiquetaTipo($tipoCanon));
                $medioParcial = [
                    'tipo' => $tipoCanon,
                    'categoria' => WaitryMedioPagoCuentacajaSupport::esCategoriaInformeZDesglose($tipoCanon) ? $tipoCanon : null,
                    'etiqueta' => $etiqueta,
                    'cantidad' => $cantLinea,
                    'total' => $montoLinea,
                ];
                $nueva = self::lineaPlantillaDesdeTipo($empresaId, $tipoCanon, $medioParcial);
                $lineasPorClave = self::fusionarLineaEnMapa($lineasPorClave, $nueva, $empresaId);
                $bloque['lineas'] = array_values($lineasPorClave);
                $bloque['total_ingreso_sistema'] = round(
                    (float) ($bloque['total_ingreso_sistema'] ?? 0) + $montoLinea,
                    2,
                );
            }
            unset($bloque);
        }

        return $bloques;
    }

    /**
     * Un tipo por cuenta de caja para filas vacías del Informe Z (QR + MP + Posnet fusionados si comparten cuenta).
     *
     * @return list<string>
     */
    private static function tiposRepresentativosCatalogoInformeZ(int $empresaId): array
    {
        return self::tiposRepresentativosCatalogo($empresaId, WaitryMedioPagoCuentacajaSupport::mapaTipoCuentacaja());
    }
}
