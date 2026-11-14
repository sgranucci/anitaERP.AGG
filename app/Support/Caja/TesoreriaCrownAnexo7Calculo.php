<?php

namespace App\Support\Caja;

use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownJornada;
use Illuminate\Support\Facades\DB;

/**
 * Anexo VII / l-anexo7.c: fallos, anticipos y excedentes de cajeros.
 *
 * Fallo = diferencia negativa del cierre; excedente = positiva.
 * Concepto (como l-anexo7.c): "Fallo", "Excedente", "Excedente/Fallo"
 * o "Anticipo de sueldo" — no el tipo de tarea/caja.
 *
 * Bóveda a cuenta de terceros (lee_fallo_boveda): adelanto (4), fallo de caja (15)
 * y faltante de bóveda (16) suman a fallos; excedente (13) y sobrante (17) a excedentes.
 * Anita los suma en la columna de fallos, también el adelanto.
 *
 * Anticipos de sueldo: fuera de alcance. No hay maestro/proceso en Tesorería
 * Crown ni lectura viva de Anita; la columna queda siempre en 0 a propósito.
 */
final class TesoreriaCrownAnexo7Calculo
{
    /** @deprecated Usar TesoreriaCrownBovedaAnexoMapaSupport::anexo7Codigos() */
    public const CODIGOS_BOVEDA_FALLO = [4, 15, 16];

    /** @deprecated Usar TesoreriaCrownBovedaAnexoMapaSupport::anexo7Codigos() */
    public const CODIGOS_BOVEDA_EXCEDENTE = [13, 17];

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array{fallos: float, anticipos: float, excedentes: float},
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $base = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $filas = [];
        $totales = ['fallos' => 0.0, 'anticipos' => 0.0, 'excedentes' => 0.0];
        $agregar = function (array $fila, float $fallos, float $excedentes) use (&$filas, &$totales): void {
            $anticipos = 0.0;
            if ($fallos < 0.001 && $excedentes < 0.001 && $anticipos < 0.001) {
                return;
            }
            $filas[] = [
                'caja' => $fila['caja'],
                'cajero' => $fila['cajero'],
                'legajo' => $fila['legajo'],
                'concepto' => self::concepto($fallos, $excedentes, $anticipos),
                'fallos' => $fallos,
                'anticipos' => $anticipos,
                'excedentes' => $excedentes,
            ];
            $totales['fallos'] = round($totales['fallos'] + $fallos, 2);
            $totales['anticipos'] = round($totales['anticipos'] + $anticipos, 2);
            $totales['excedentes'] = round($totales['excedentes'] + $excedentes, 2);
        };

        foreach ($base['filas'] as $fila) {
            $dif = (float) $fila['diferencia'];
            $falloMozo = (float) ($fila['fallo_mozo'] ?? 0);
            $fallos = 0.0;
            $excedentes = 0.0;
            if ($dif < -0.001) {
                $fallos = abs($dif);
            } elseif ($dif > 0.001) {
                $excedentes = $dif;
            }
            if ($falloMozo > 0.001) {
                $fallos = round($fallos + $falloMozo, 2);
            }
            $agregar($fila, $fallos, $excedentes);
        }

        $mapa = TesoreriaCrownBovedaAnexoMapaSupport::anexo7Codigos(
            $jornada->fecha?->format('Y-m-d')
        );
        foreach (self::movimientosBovedaTerceros($jornada, $mapa) as $mov) {
            $esFallo = in_array((int) $mov->codigo, $mapa['fallos'], true);
            $monto = round(abs((float) $mov->monto), 2);
            $agregar([
                'caja' => 'Bóveda '.trim((string) $mov->abreviatura).' '.(int) $mov->numero,
                'cajero' => self::nombreLegajo((int) $mov->legajo, (string) $mov->comentario),
                'legajo' => (int) $mov->legajo,
            ], $esFallo ? $monto : 0.0, $esFallo ? 0.0 : $monto);
        }

        $avisos = [];
        if ($filas === []) {
            $avisos[] = 'No hay fallos ni excedentes en esta jornada.';
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array{fallos: list<int>, excedentes: list<int>}  $mapa
     * @return list<object>
     */
    private static function movimientosBovedaTerceros(TesoreriaCrownJornada $jornada, array $mapa): array
    {
        $codigos = array_merge($mapa['fallos'], $mapa['excedentes']);

        return DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->leftJoin('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->where('b.jornada_id', $jornada->id)
            ->where('b.estado', 'C')
            ->whereNull('b.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_boveda as r')
                    ->whereColumn('r.revierte_id', 'b.id');
            })
            ->whereIn('t.codigo', $codigos)
            ->where(function ($q) {
                $q->whereNull('tv.codigo')->orWhereNotIn('tv.codigo', ['USD', 'DOL', 'EUR', 'EURO']);
            })
            ->groupBy('b.id', 'b.numero', 'b.legajo', 'b.comentario', 't.codigo', 't.abreviatura')
            ->orderBy('b.numero')
            ->get([
                'b.numero', 'b.legajo', 'b.comentario', 't.codigo', 't.abreviatura',
                DB::raw('SUM(l.cantidad * v.denominacion) as monto'),
            ])
            ->all();
    }

    private static function nombreLegajo(int $legajo, string $comentario): string
    {
        $nombre = $legajo > 0
            ? (string) (CajeroCrown::query()->where('legajo', $legajo)->value('nombre') ?? '')
            : '';

        return trim($nombre) !== '' ? trim($nombre) : trim($comentario);
    }

    /**
     * Etiqueta de concepto de l-anexo7.c (FORM1 / Anticipo de sueldo).
     */
    private static function concepto(float $fallos, float $excedentes, float $anticipos): string
    {
        $hayFallo = $fallos > 0.001;
        $hayExcedente = $excedentes > 0.001;
        if ($hayFallo && $hayExcedente) {
            return 'Excedente/Fallo';
        }
        if ($hayExcedente) {
            return 'Excedente';
        }
        if ($hayFallo) {
            return 'Fallo';
        }
        if ($anticipos > 0.001 || $anticipos < -0.001) {
            return 'Anticipo de sueldo';
        }

        return '';
    }
}
