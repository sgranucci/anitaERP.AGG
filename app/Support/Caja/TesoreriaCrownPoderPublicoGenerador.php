<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownPoderPublico;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Genera y persiste el poder público del día (fichas + slot) en el ERP.
 *
 * Fichas: mismo criterio que l-stockfichm / TesoreriaCrownPoderPublicoCalculo.
 * Slot: subtotal_slot − slot_resultado − voucher_mesa (l-anexo1.c).
 * Mes y acumulado se encadenan desde la fila ERP del día anterior; el 1 del mes el mes arranca en 0.
 * Si falta subtotal_slot queda en 0 con aviso para cargarlo en resultado online.
 * Corte Anita: sin ppublico / efemesa en vivo.
 */
final class TesoreriaCrownPoderPublicoGenerador
{
    /**
     * @return array{
     *     ficha_dia: float,
     *     ficha_mes: float,
     *     ficha_anio: float,
     *     slot_dia: float,
     *     slot_mes: float,
     *     slot_anio: float,
     *     origen: string,
     *     avisos: list<string>,
     *     modelo: TesoreriaCrownPoderPublico
     * }
     */
    public static function obtenerOGenerar(int $salaId, string $fechaYmd, bool $regenerar = false): array
    {
        $existente = TesoreriaCrownPoderPublico::deSalaFecha($salaId, $fechaYmd);
        if ($existente && ! $regenerar) {
            return self::presentar($existente, []);
        }

        $modelo = self::generar($salaId, $fechaYmd);

        return self::presentar($modelo, (array) ($modelo->detalle['avisos'] ?? []));
    }

    public static function generar(int $salaId, string $fechaYmd): TesoreriaCrownPoderPublico
    {
        $avisos = [];
        $origenes = [];

        $ficha = self::calcularFichas($fechaYmd, $avisos, $origenes);
        $slotPiezas = self::piezasSlot($salaId, $fechaYmd, $avisos, $origenes);
        $slotDia = self::slotDiaDesdePiezas($slotPiezas);
        $fichaDia = (float) $ficha['dia'];
        $ayer = date('Y-m-d', strtotime($fechaYmd.' -1 day'));

        // Mes/acum se encadenan desde el ERP del día anterior.
        $anterior = self::anteriorParaCadena($salaId, $ayer, $avisos);
        $esPrimero = substr(str_replace('-', '', $fechaYmd), 6, 2) === '01';
        $fichaMes = ($esPrimero ? 0.0 : (float) ($anterior['ficha_mes'] ?? 0)) + $fichaDia;
        $fichaAcum = (float) ($anterior['ficha_acum'] ?? 0) + $fichaDia;
        $slotMes = ($esPrimero ? 0.0 : (float) ($anterior['slot_mes'] ?? 0)) + $slotDia;
        $slotAcum = (float) ($anterior['slot_acum'] ?? 0) + $slotDia;

        $origen = self::origenDe($origenes);
        $detalle = [
            'avisos' => $avisos,
            'ficha' => $ficha,
            'slot' => $slotPiezas,
            'anterior_fecha' => $ayer,
        ];

        $modelo = TesoreriaCrownPoderPublico::deSalaFecha($salaId, $fechaYmd)
            ?? new TesoreriaCrownPoderPublico(['sala_id' => $salaId, 'fecha' => $fechaYmd]);
        $modelo->fill([
            'ficha_dia' => round($fichaDia, 2),
            'ficha_mes' => round($fichaMes, 2),
            'ficha_acum' => round($fichaAcum, 2),
            'slot_dia' => round($slotDia, 2),
            'slot_mes' => round($slotMes, 2),
            'slot_acum' => round($slotAcum, 2),
            'voucher_mesa' => round((float) $slotPiezas['voucher_mesa'], 2),
            'slot_resultado' => round((float) $slotPiezas['slot_resultado'], 2),
            'subtotal_slot' => $slotPiezas['subtotal_slot'] === null
                ? null
                : round((float) $slotPiezas['subtotal_slot'], 2),
            'origen' => $origen,
            'detalle' => $detalle,
            'generado_at' => now(),
        ]);
        $modelo->save();

        return $modelo->fresh();
    }

    /**
     * @param  list<string>  $avisos
     * @return array{ficha_dia: float, ficha_mes: float, ficha_anio: float, slot_dia: float, slot_mes: float, slot_anio: float, origen: string, avisos: list<string>, modelo: TesoreriaCrownPoderPublico}
     */
    private static function presentar(TesoreriaCrownPoderPublico $modelo, array $avisos): array
    {
        return [
            'ficha_dia' => (float) $modelo->ficha_dia,
            'ficha_mes' => (float) $modelo->ficha_mes,
            'ficha_anio' => (float) $modelo->ficha_acum,
            'slot_dia' => (float) $modelo->slot_dia,
            'slot_mes' => (float) $modelo->slot_mes,
            'slot_anio' => (float) $modelo->slot_acum,
            'origen' => (string) $modelo->origen,
            'avisos' => $avisos !== [] ? $avisos : (array) ($modelo->detalle['avisos'] ?? []),
            'modelo' => $modelo,
        ];
    }

    /**
     * @param  list<string>  $avisos
     * @param  list<string>  $origenes
     * @return array{dia: float, filas?: list<array<string, mixed>>, anita_dia?: int|null}
     */
    private static function calcularFichas(string $fechaYmd, array &$avisos, array &$origenes): array
    {
        try {
            $armado = TesoreriaCrownPoderPublicoCalculo::delDia($fechaYmd);
            $origenFichas = (string) ($armado['origen'] ?? 'erp');
            $origenes[] = TesoreriaCrownPoderPublico::ORIGEN_ERP;
            foreach ($armado['avisos'] as $aviso) {
                $avisos[] = $aviso;
            }

            return [
                'dia' => (float) $armado['diario_valorizado'],
                'filas' => $armado['filas'],
                'anita_dia' => $armado['anita_dia'],
                'origen' => $origenFichas,
            ];
        } catch (Throwable $e) {
            $avisos[] = 'No se pudo calcular el poder público de fichas: '.$e->getMessage();
            $origenes[] = TesoreriaCrownPoderPublico::ORIGEN_ERP;

            return ['dia' => 0.0];
        }
    }

    /**
     * @param  list<string>  $avisos
     * @param  list<string>  $origenes
     * @return array{subtotal_slot: ?float, slot_resultado: float, voucher_mesa: float, fuente_subtotal: string}
     */
    private static function piezasSlot(int $salaId, string $fechaYmd, array &$avisos, array &$origenes): array
    {
        $central = TesoreriaCrownResultadoOnline::deSalaFecha($salaId, $fechaYmd);
        $slotResultado = $central ? (float) $central->slot_resultado : 0.0;
        $subtotal = $central && $central->subtotal_slot !== null
            ? (float) $central->subtotal_slot
            : null;
        $voucher = self::voucherMesa($salaId, $fechaYmd, $avisos);
        $fuente = 'erp';

        if ($subtotal === null) {
            $avisos[] = 'Falta el subtotal de slots (soft count). Cargalo en resultado online; sin eso el poder público de slot queda en −resultado − voucher.';
            $subtotal = 0.0;
            $fuente = 'cero';
            $origenes[] = TesoreriaCrownPoderPublico::ORIGEN_ERP;
        } else {
            $origenes[] = TesoreriaCrownPoderPublico::ORIGEN_ERP;
        }

        if ($central && abs($slotResultado) < 0.001) {
            $avisos[] = 'Resultado de slots online en cero para el día.';
        }

        return [
            'subtotal_slot' => $subtotal,
            'slot_resultado' => $slotResultado,
            'voucher_mesa' => $voucher,
            'fuente_subtotal' => $fuente,
        ];
    }

    /**
     * @param  array{subtotal_slot: ?float, slot_resultado: float, voucher_mesa: float}  $piezas
     */
    private static function slotDiaDesdePiezas(array $piezas): float
    {
        $subtotal = (float) ($piezas['subtotal_slot'] ?? 0);
        $slotR = (float) ($piezas['slot_resultado'] ?? 0);
        $voucher = (float) ($piezas['voucher_mesa'] ?? 0);

        return round($subtotal - $slotR - $voucher, 2);
    }

    /**
     * @param  list<string>  $avisos
     */
    public static function voucherMesa(int $salaId, string $fechaYmd, array &$avisos = []): float
    {
        $jornada = TesoreriaCrownJornada::query()
            ->where('sala_id', $salaId)
            ->whereDate('fecha', $fechaYmd)
            ->first();
        if ($jornada) {
            $sumaColumna = 0.0;
            if (Schema::hasColumn('tesoreria_crown_mesa', 'voucher')) {
                $sumaColumna = (float) TesoreriaCrownMesa::query()
                    ->where('jornada_id', $jornada->id)
                    ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
                    ->whereNull('revierte_id')
                    ->whereDoesntHave('reversa')
                    ->sum('voucher');
            }
            if (abs($sumaColumna) > 0.001) {
                return round($sumaColumna, 2);
            }
            $sumaParse = 0.0;
            $comentarios = TesoreriaCrownMesa::query()
                ->where('jornada_id', $jornada->id)
                ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
                ->whereNull('revierte_id')
                ->where('comentario', 'like', '%voucher%')
                ->pluck('comentario');
            foreach ($comentarios as $comentario) {
                $sumaParse += self::parseVoucherComentario((string) $comentario);
            }
            if (abs($sumaParse) > 0.001) {
                return round($sumaParse, 2);
            }
        }

        return 0.0;
    }

    public static function parseVoucherComentario(string $comentario): float
    {
        if (! preg_match('/voucher\s+([0-9.]+,[0-9]{2})/iu', $comentario, $m)) {
            return 0.0;
        }

        return round((float) str_replace(['.', ','], ['', '.'], $m[1]), 2);
    }

    /**
     * @param  list<string>  $avisos
     * @return array{ficha_mes: float, ficha_acum: float, slot_mes: float, slot_acum: float}|null
     */
    private static function anteriorParaCadena(int $salaId, string $ayerYmd, array &$avisos): ?array
    {
        $erp = TesoreriaCrownPoderPublico::deSalaFecha($salaId, $ayerYmd);
        if ($erp) {
            return [
                'ficha_mes' => (float) $erp->ficha_mes,
                'ficha_acum' => (float) $erp->ficha_acum,
                'slot_mes' => (float) $erp->slot_mes,
                'slot_acum' => (float) $erp->slot_acum,
            ];
        }

        $avisos[] = 'Sin poder público ERP del día anterior: mes y acumulado arrancan solo con el día.';

        return null;
    }

    /**
     * @param  list<string>  $origenes
     */
    private static function origenDe(array $origenes): string
    {
        $unicos = array_values(array_unique($origenes));
        if ($unicos === [TesoreriaCrownPoderPublico::ORIGEN_ERP]) {
            return TesoreriaCrownPoderPublico::ORIGEN_ERP;
        }
        if ($unicos === [TesoreriaCrownPoderPublico::ORIGEN_ANITA]) {
            return TesoreriaCrownPoderPublico::ORIGEN_ANITA;
        }

        return TesoreriaCrownPoderPublico::ORIGEN_MIXTO;
    }

}
