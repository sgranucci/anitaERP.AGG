<?php

namespace App\Support\Caja;

use App\Models\Caja\Flash\FlashCrown;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Informe comparativo de propinas por casino (Anita: l-propinaxcas.c).
 * Fuente ERP: flash_crown.caja_empleado (equivalente a resultado.caja_empleado).
 */
final class TesoreriaCrownPropinaCasinoSupport
{
    /**
     * @param  array{fecha_desde?: string, fecha_hasta?: string}  $filtros
     * @return array{
     *   casinos: list<array{id: int, codigo: string, nombre: string, etiqueta: string}>,
     *   filas: list<array<string, mixed>>,
     *   totales: array{total: float, por_casino: array<int, float>},
     *   total_dias: int,
     *   subtitulo: string
     * }
     */
    public static function generar(array $filtros): array
    {
        $desde = (string) ($filtros['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        if ($hasta < $desde) {
            $hasta = $desde;
        }

        $casinos = self::casinosColumnas();
        $salaIds = array_column($casinos, 'id');
        $porCasinoVacio = [];
        foreach ($salaIds as $salaId) {
            $porCasinoVacio[(int) $salaId] = 0.0;
        }

        if ($salaIds === []) {
            return [
                'casinos' => [],
                'filas' => [],
                'totales' => ['total' => 0.0, 'por_casino' => $porCasinoVacio],
                'total_dias' => 0,
                'subtitulo' => TesoreriaCrownPropinaCasinoFiltros::subtitulo($filtros),
            ];
        }

        /** @var Collection<int, object{fecha: mixed, sala_id: mixed, caja_empleado: mixed}> $movimientos */
        $movimientos = FlashCrown::query()
            ->whereBetween('fecha', [$desde, $hasta])
            ->whereIn('sala_id', $salaIds)
            ->select(['fecha', 'sala_id', 'caja_empleado'])
            ->orderBy('fecha')
            ->orderBy('sala_id')
            ->get();

        /** @var array<string, array{total: float, por_casino: array<int, float>}> $porFecha */
        $porFecha = [];
        foreach ($movimientos as $mov) {
            $fecha = $mov->fecha instanceof Carbon
                ? $mov->fecha->format('Y-m-d')
                : substr((string) $mov->fecha, 0, 10);
            if ($fecha === '') {
                continue;
            }
            if (! isset($porFecha[$fecha])) {
                $porFecha[$fecha] = [
                    'total' => 0.0,
                    'por_casino' => $porCasinoVacio,
                ];
            }
            $salaId = (int) $mov->sala_id;
            $monto = round((float) ($mov->caja_empleado ?? 0), 2);
            if (! array_key_exists($salaId, $porFecha[$fecha]['por_casino'])) {
                continue;
            }
            $porFecha[$fecha]['por_casino'][$salaId] = round(
                $porFecha[$fecha]['por_casino'][$salaId] + $monto,
                2
            );
            $porFecha[$fecha]['total'] = round($porFecha[$fecha]['total'] + $monto, 2);
        }

        ksort($porFecha);

        $filas = [];
        $totales = [
            'total' => 0.0,
            'por_casino' => $porCasinoVacio,
        ];

        foreach ($porFecha as $fechaYmd => $datos) {
            if (abs($datos['total']) < 0.00001) {
                $tieneAlguno = false;
                foreach ($datos['por_casino'] as $monto) {
                    if (abs((float) $monto) >= 0.00001) {
                        $tieneAlguno = true;
                        break;
                    }
                }
                if (! $tieneAlguno) {
                    continue;
                }
            }

            $carbon = Carbon::createFromFormat('Y-m-d', $fechaYmd)->startOfDay();
            $filas[] = [
                'es_total' => false,
                'fecha' => $fechaYmd,
                'fecha_fmt' => $carbon->format('d/m/Y'),
                'dia' => self::etiquetaDia($carbon),
                'total' => $datos['total'],
                'por_casino' => $datos['por_casino'],
            ];

            $totales['total'] = round($totales['total'] + $datos['total'], 2);
            foreach ($datos['por_casino'] as $salaId => $monto) {
                $totales['por_casino'][$salaId] = round(
                    $totales['por_casino'][$salaId] + (float) $monto,
                    2
                );
            }
        }

        return [
            'casinos' => $casinos,
            'filas' => $filas,
            'totales' => $totales,
            'total_dias' => count($filas),
            'subtitulo' => TesoreriaCrownPropinaCasinoFiltros::subtitulo($filtros),
        ];
    }

    /**
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    public static function casinosColumnas(): array
    {
        return TesoreriaCrownSalaSupport::salas()
            ->map(function ($sala): array {
                $nombre = trim((string) ($sala->nombre ?? ''));
                $codigo = strtoupper(trim((string) ($sala->codigo ?? '')));

                return [
                    'id' => (int) $sala->id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'etiqueta' => $nombre !== '' ? $nombre : $codigo,
                ];
            })
            ->values()
            ->all();
    }

    private static function etiquetaDia(Carbon $fecha): string
    {
        $es = mb_substr(mb_strtoupper($fecha->copy()->locale('es')->isoFormat('ddd')), 0, 3);
        $en = mb_substr(mb_strtoupper($fecha->copy()->locale('en')->isoFormat('ddd')), 0, 3);

        return $es.'/'.$en;
    }
}
