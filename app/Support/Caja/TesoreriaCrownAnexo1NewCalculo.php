<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBingo;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anexo I formato tesorería (debe/haber): ENTRO / SALIO.
 *
 * Resumen operativo del día para controlar diferencias (pedido Mauro).
 * No es el Anexo I contable (día/mes/anual, l-anexo1.c).
 *
 * Columnas: Concepto | Fecha | Débito (ENTRO) | Crédito (SALIO) | Saldo corrido.
 */
final class TesoreriaCrownAnexo1NewCalculo
{
    private const SUBTIPO_VALORES_ENCONTRADOS = 'ENC';

    /**
     * @return array{
     *     filas: list<array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>,
     *     variante: string
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        TesoreriaCrownRecalculoAdelanteSupport::asegurar($jornada);
        $avisos = [];
        $importes = self::importesDia($jornada, $avisos);
        $fechaVisible = $jornada->fecha->format('d/m/Y');

        $filas = [];
        $saldo = 0.0;

        $filas[] = self::seccion('ENTRO');
        foreach (self::lineasEntro() as $clave => $concepto) {
            $monto = round((float) ($importes[$clave] ?? 0), 2);
            $saldo = round($saldo + $monto, 2);
            $filas[] = self::movimiento($concepto, $fechaVisible, $monto, null, $saldo);
        }
        $totalEntro = round((float) ($importes['total_entro'] ?? 0), 2);
        $filas[] = self::total('Total ENTRO', $fechaVisible, $totalEntro, null, $saldo);

        $filas[] = self::seccion('SALIO');
        foreach (self::lineasSalio() as $clave => $concepto) {
            $monto = round((float) ($importes[$clave] ?? 0), 2);
            $saldo = round($saldo - $monto, 2);
            $filas[] = self::movimiento($concepto, $fechaVisible, null, $monto, $saldo);
        }
        $totalSalio = round((float) ($importes['total_salio'] ?? 0), 2);
        $filas[] = self::total('Total SALIO', $fechaVisible, null, $totalSalio, $saldo);

        $variacion = round($totalEntro - $totalSalio, 2);
        $filas[] = self::total('Variación del día (ENTRO − SALIO)', $fechaVisible, $variacion >= 0 ? $variacion : null, $variacion < 0 ? abs($variacion) : null, $saldo);

        return [
            'filas' => $filas,
            'cerrado' => ! $jornada->estaAbierta(),
            'avisos' => array_values(array_unique($avisos)),
            'variante' => 'nuevo',
        ];
    }

    /**
     * @return array{
     *     filas: list<array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>,
     *     variante: string
     * }
     */
    public static function delDia(string $fechaYmd): array
    {
        $jornada = TesoreriaCrownJornada::query()
            ->whereDate('fecha', $fechaYmd)
            ->orderBy('id')
            ->first();
        if (! $jornada instanceof TesoreriaCrownJornada) {
            return [
                'filas' => [],
                'cerrado' => false,
                'avisos' => ['No hay jornada de tesorería para el '.$fechaYmd.'.'],
                'variante' => 'nuevo',
            ];
        }

        return self::deJornada($jornada);
    }

    /**
     * @return array<string, string>
     */
    private static function lineasEntro(): array
    {
        return [
            'ingreso_efectivo' => 'Ingreso de efectivo',
            'ventas_ab' => 'Ventas A&B',
            'ventas_hotel' => 'Ventas Hotel',
            'propina' => 'Propina',
            'bingo' => 'Bingo',
            'valores_encontrados' => 'Valores encontrados',
            'ingreso_fondo' => 'Ingreso de Fondo',
            'otros_ingresos' => 'Otros ingresos',
            'excedente_caja' => 'Excedente de caja',
            'recargo_financiero' => 'Recargo Financiero',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function lineasSalio(): array
    {
        return [
            'egreso_fondo' => 'Egreso de Fondo',
            'pagos_por_caja' => 'Pagos por caja',
            'fondo_fijo' => 'Fondo Fijo',
            'fallos_caja' => 'Fallos de caja',
            'deposito_efectivo' => 'Deposito de efectivo',
            'tarjetas_credito' => 'Tarjetas de crédito',
            'tarjetas_debito' => 'Tarjetas de débito',
            'total_qr' => 'Total QR',
            'deposito_dolares' => 'Deposito dolares',
            'deposito_euros' => 'Deposito euros',
            'cta_cte_hotel' => 'cta cte hotel',
            'transferencias_hotel' => 'Transferencias hotel',
            'otros_egreso' => 'Otros Egreso',
        ];
    }

    /**
     * @param  list<string>  $avisos
     * @return array<string, float>
     */
    private static function importesDia(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $out = array_fill_keys(
            array_merge(array_keys(self::lineasEntro()), array_keys(self::lineasSalio()), ['total_entro', 'total_salio']),
            0.0
        );

        $resumen = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $porTipo = [];
        foreach ($resumen['grupos'] as $grupo) {
            $porTipo[(string) $grupo['tipo']] = $grupo['totales'];
        }
        $ventaContado = static function (string $tipo) use ($porTipo): float {
            $tot = $porTipo[$tipo] ?? null;
            if (! is_array($tot)) {
                return 0.0;
            }

            return round((float) ($tot['resultado'] ?? 0) - (float) ($tot['diferencia'] ?? 0), 2);
        };

        // Ventas A&B / Hotel = Xs gastro / hotel (resultado sin la diferencia de arqueo).
        $out['ventas_ab'] = $ventaContado('G');
        $out['ventas_hotel'] = $ventaContado('H');

        $ventasXCaja = 0.0;
        $pagosXCaja = 0.0;
        $excedente = 0.0;
        $fallos = 0.0;
        $recargo = 0.0;
        $credito = 0.0;
        $debito = 0.0;
        $qr = 0.0;
        $ctacteHotel = 0.0;
        $transferHotel = 0.0;

        foreach ($resumen['filas'] as $fila) {
            if (! empty($fila['propina'])) {
                continue;
            }
            $tipoCaja = (string) ($fila['tipo'] ?? '');
            $dif = (float) ($fila['diferencia'] ?? 0);
            if ($dif > 0.001) {
                $excedente += $dif;
            } elseif ($dif < -0.001) {
                $fallos += abs($dif);
            }

            $credito += (float) ($fila['tarjeta_credito'] ?? 0);
            $debito += (float) ($fila['tarjeta_debito'] ?? 0);
            $qr += (float) ($fila['total_qr'] ?? 0);

            if (in_array($tipoCaja, ['K', 'L', 'T'], true)) {
                $ventasXCaja += (float) ($fila['vta_cashless'] ?? 0);
                $pagosXCaja += (float) ($fila['pago_cashless'] ?? 0);
            }
            if ($tipoCaja === 'E') {
                $recargo += (float) ($fila['recargo_fin'] ?? 0);
            }
            if ($tipoCaja === 'H') {
                $transfer = (float) ($fila['transferencia_hotel'] ?? 0);
                $ctacteHotel += (float) ($fila['ctacte'] ?? 0) - $transfer;
                $transferHotel += $transfer;
            }
        }

        $boveda = self::bovedaDetalle($jornada);
        $sumarCod = static function (int ...$codigos) use ($boveda): float {
            $total = 0.0;
            foreach ($codigos as $codigo) {
                $total += (float) ($boveda['por_codigo'][$codigo] ?? 0);
            }

            return round($total, 2);
        };

        $cashbox = self::recuentoCashbox($jornada);
        $out['ingreso_efectivo'] = round($ventasXCaja + $cashbox, 2);
        $out['valores_encontrados'] = round((float) ($boveda['valores_encontrados'] ?? 0), 2);
        $out['ingreso_fondo'] = $sumarCod(25);
        $out['otros_ingresos'] = round((float) ($boveda['otros_ingresos'] ?? 0), 2);
        $out['excedente_caja'] = round($excedente + $sumarCod(17), 2);
        $out['recargo_financiero'] = round($recargo, 2);
        $out['propina'] = self::propinaDia($jornada);

        $bingo = TesoreriaCrownBingo::deSalaFecha(
            (int) $jornada->sala_id,
            (string) $jornada->fecha?->format('Y-m-d')
        );
        $out['bingo'] = $bingo ? round((float) $bingo->resultado, 2) : 0.0;

        $propinaMesas = self::propinaMesas($jornada);
        $out['egreso_fondo'] = $sumarCod(30);
        $out['pagos_por_caja'] = round($sumarCod(39) + $pagosXCaja + $propinaMesas, 2);
        $out['fondo_fijo'] = $sumarCod(2);
        $out['fallos_caja'] = round($fallos + $sumarCod(16), 2);
        $out['deposito_efectivo'] = $sumarCod(22);
        $out['tarjetas_credito'] = round($credito, 2);
        $out['tarjetas_debito'] = round($debito, 2);
        $out['total_qr'] = round($qr, 2);
        $out['deposito_dolares'] = TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::totalJornada(
            $jornada,
            TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::CODIGO_DOLARES,
            $avisos
        );
        $out['deposito_euros'] = TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::totalJornada(
            $jornada,
            TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::CODIGO_EUROS,
            $avisos
        );
        $out['cta_cte_hotel'] = round($ctacteHotel, 2);
        $out['transferencias_hotel'] = round($transferHotel, 2);
        $out['otros_egreso'] = $sumarCod(14);

        $totalEntro = 0.0;
        foreach (array_keys(self::lineasEntro()) as $clave) {
            $totalEntro += (float) $out[$clave];
        }
        $totalSalio = 0.0;
        foreach (array_keys(self::lineasSalio()) as $clave) {
            $totalSalio += (float) $out[$clave];
        }
        $out['total_entro'] = round($totalEntro, 2);
        $out['total_salio'] = round($totalSalio, 2);

        foreach ($out as $clave => $importe) {
            $out[$clave] = round((float) $importe, 2);
        }

        return $out;
    }

    /**
     * Bóveda pesos por código + desglose INGR (tipo 1) por subtipo ENC vs resto.
     * CashBox (comentario o vínculo slot) no entra en valores encontrados ni en otros ingresos.
     *
     * @return array{por_codigo: array<int, float>, valores_encontrados: float, otros_ingresos: float}
     */
    private static function bovedaDetalle(TesoreriaCrownJornada $jornada): array
    {
        $cashboxIds = TesoreriaCrownSlot::query()
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownSlot::RECIBIDO)
            ->where('tipo', '!=', TesoreriaCrownSlot::DROP)
            ->whereNotNull('boveda_id')
            ->pluck('boveda_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->all();

        $filas = DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->leftJoin('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->leftJoin('tesoreria_crown_boveda_subtipo as st', 'st.id', '=', 'b.subtipo_id')
            ->where('b.jornada_id', $jornada->id)
            ->where('b.estado', 'C')
            ->whereNull('b.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_boveda as r')
                    ->whereColumn('r.revierte_id', 'b.id');
            })
            ->groupBy('b.id', 't.codigo', 'st.codigo', 'tv.codigo', 'b.comentario')
            ->get([
                'b.id as boveda_id',
                't.codigo as codigo',
                'st.codigo as subtipo',
                'tv.codigo as moneda',
                'b.comentario',
                DB::raw('SUM(l.cantidad * v.denominacion) as monto'),
            ]);

        $porCodigo = [];
        $valoresEncontrados = 0.0;
        $otrosIngresos = 0.0;

        foreach ($filas as $fila) {
            $moneda = strtoupper(trim((string) $fila->moneda));
            if (in_array($moneda, ['USD', 'DOL', 'EUR', 'EURO'], true)) {
                continue;
            }
            $monto = round((float) $fila->monto, 2);
            $codigo = (int) $fila->codigo;
            $porCodigo[$codigo] = round(($porCodigo[$codigo] ?? 0) + $monto, 2);

            if ($codigo !== 1) {
                continue;
            }

            $bovedaId = (int) $fila->boveda_id;
            $esCashbox = in_array($bovedaId, $cashboxIds, true)
                || str_starts_with(mb_strtoupper(trim((string) $fila->comentario)), 'CASHBOX');
            if ($esCashbox) {
                continue;
            }

            $subtipo = strtoupper(trim((string) ($fila->subtipo ?? '')));
            if ($subtipo === self::SUBTIPO_VALORES_ENCONTRADOS) {
                $valoresEncontrados = round($valoresEncontrados + $monto, 2);
            } else {
                $otrosIngresos = round($otrosIngresos + $monto, 2);
            }
        }

        return [
            'por_codigo' => $porCodigo,
            'valores_encontrados' => $valoresEncontrados,
            'otros_ingresos' => $otrosIngresos,
        ];
    }

    /** Recuento CashBox (slots recibidos, no drop) en pesos. */
    private static function recuentoCashbox(TesoreriaCrownJornada $jornada): float
    {
        return round((float) TesoreriaCrownSlot::query()
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownSlot::RECIBIDO)
            ->where('tipo', '!=', TesoreriaCrownSlot::DROP)
            ->sum('total'), 2);
    }

    /**
     * Propina Pública + Híbrida (bóveda PROP) + mesas (estados finales F).
     * Misma lógica que el informe de propinas por casino, acotada a la jornada.
     */
    private static function propinaDia(TesoreriaCrownJornada $jornada): float
    {
        $propBoveda = 0.0;
        $tieneFlag = Schema::hasColumn('tesoreria_crown_boveda_tipo', 'para_reporte_propina');
        if ($tieneFlag) {
            $importeLineas = DB::table('tesoreria_crown_boveda_linea as l')
                ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
                ->groupBy('l.boveda_id')
                ->select('l.boveda_id')
                ->selectRaw('SUM(l.cantidad * v.denominacion) as importe_lineas');

            $query = DB::table('tesoreria_crown_boveda as b')
                ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
                ->leftJoinSub($importeLineas, 'il', 'il.boveda_id', '=', 'b.id')
                ->where('b.jornada_id', $jornada->id)
                ->where('b.estado', 'C')
                ->whereNull('b.revierte_id')
                ->where('t.para_reporte_propina', true)
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('tesoreria_crown_boveda as r')
                        ->whereColumn('r.revierte_id', 'b.id');
                });
            if (Schema::hasColumn('tesoreria_crown_mesa', 'boveda_id')) {
                $query->whereNotExists(function ($q) {
                    $q->selectRaw('1')
                        ->from('tesoreria_crown_mesa as m')
                        ->whereColumn('m.boveda_id', 'b.id');
                });
            }
            $propBoveda = round((float) $query->selectRaw(
                'COALESCE(SUM(CASE'
                .' WHEN il.importe_lineas IS NOT NULL AND ABS(il.importe_lineas) >= 0.00001'
                .' THEN il.importe_lineas'
                .' ELSE COALESCE(b.importe_pesos, 0)'
                .' END), 0) as total'
            )->value('total'), 2);
        } else {
            // Fallback: PROP con subtipos PUB/HIB (sin MES, que se toma de mesa F).
            $propBoveda = round((float) DB::table('tesoreria_crown_boveda as b')
                ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
                ->leftJoin('tesoreria_crown_boveda_subtipo as st', 'st.id', '=', 'b.subtipo_id')
                ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
                ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
                ->where('b.jornada_id', $jornada->id)
                ->where('b.estado', 'C')
                ->whereNull('b.revierte_id')
                ->where('t.abreviatura', 'PROP')
                ->whereIn('st.codigo', ['PUB', 'HIB'])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('tesoreria_crown_boveda as r')
                        ->whereColumn('r.revierte_id', 'b.id');
                })
                ->sum(DB::raw('l.cantidad * v.denominacion')), 2);
        }

        return round($propBoveda + self::propinaMesas($jornada), 2);
    }

    private static function propinaMesas(TesoreriaCrownJornada $jornada): float
    {
        return round((float) TesoreriaCrownMesa::query()
            ->where('jornada_id', $jornada->id)
            ->where('tipo', 'F')
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_mesa as r')
                    ->whereColumn('r.revierte_id', 'tesoreria_crown_mesa.id');
            })
            ->sum('propina'), 2);
    }

    /**
     * @return array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}
     */
    private static function seccion(string $concepto): array
    {
        return [
            'concepto' => $concepto,
            'fecha' => null,
            'debito' => null,
            'credito' => null,
            'saldo' => null,
            'total' => false,
            'seccion' => true,
        ];
    }

    /**
     * @return array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}
     */
    private static function movimiento(string $concepto, string $fecha, ?float $debito, ?float $credito, float $saldo): array
    {
        return [
            'concepto' => $concepto,
            'fecha' => $fecha,
            'debito' => $debito,
            'credito' => $credito,
            'saldo' => $saldo,
            'total' => false,
            'seccion' => false,
        ];
    }

    /**
     * @return array{concepto: string, fecha: ?string, debito: ?float, credito: ?float, saldo: ?float, total: bool, seccion: bool}
     */
    private static function total(string $concepto, string $fecha, ?float $debito, ?float $credito, float $saldo): array
    {
        return [
            'concepto' => $concepto,
            'fecha' => $fecha,
            'debito' => $debito,
            'credito' => $credito,
            'saldo' => $saldo,
            'total' => true,
            'seccion' => false,
        ];
    }
}
