<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\ApiAnita;
use App\Repositories\Caja\Flash\FlashCajaRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa la tabla Informix flash (schema flash.sql / a-flash.c) vía bridge Anita
 * hacia flash_caja. Mapeo por sala (no por flash_empresa):
 *   sala 21 → empresa_id 1
 *   sala 38 → empresa_id 2
 *   sala 43 → empresa_id 3
 *
 * Poker/EGA no se persisten (vending queda 0; show = flash_show).
 */
final class FlashCajaAnitaImportService
{
    /** @var array<int, int> sala Anita → empresa_id ERP */
    private const SALA_A_EMPRESA = [
        21 => 1,
        38 => 2,
        43 => 3,
    ];

    public function __construct(
        private readonly FlashCajaRepositoryInterface $repository,
    ) {}

    /**
     * @param  callable(string):void|null  $log
     * @return array{
     *   leidos: int,
     *   creados: int,
     *   actualizados: int,
     *   omitidos: int,
     *   salas_desconocidas: array<int, int>
     * }
     */
    public function importarRango(string $fechaDesde, string $fechaHasta, ?callable $log = null): array
    {
        $desde = Carbon::parse($fechaDesde)->startOfDay();
        $hasta = Carbon::parse($fechaHasta)->startOfDay();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $reportar = static function (string $mensaje) use ($log): void {
            if ($log !== null) {
                $log($mensaje);
            }
        };

        $sistema = (string) config('flash_caja_anita.sistema', 'caja');
        $tabla = (string) config('flash_caja_anita.tabla', 'flash');
        $salas = array_keys(self::SALA_A_EMPRESA);

        $leidos = 0;
        $creados = 0;
        $actualizados = 0;
        $omitidos = 0;
        /** @var array<int, int> $salasDesconocidas */
        $salasDesconocidas = [];

        $cursor = $desde->copy()->startOfMonth();
        while ($cursor->lte($hasta)) {
            $mesDesde = $cursor->copy()->startOfMonth();
            $mesHasta = $cursor->copy()->endOfMonth();
            if ($mesDesde->lt($desde)) {
                $mesDesde = $desde->copy();
            }
            if ($mesHasta->gt($hasta)) {
                $mesHasta = $hasta->copy();
            }

            $reportar(sprintf(
                'Leyendo Anita %s → %s (salas %s)...',
                $mesDesde->format('Y-m-d'),
                $mesHasta->format('Y-m-d'),
                implode(',', $salas),
            ));

            $filas = $this->leerMes($sistema, $tabla, $mesDesde, $mesHasta, $salas);
            $reportar('  Filas leídas: '.count($filas));

            foreach ($filas as $fila) {
                $leidos++;
                $sala = (int) preg_replace('/\D+/', '', (string) ($fila->flash_sala ?? ''));
                if (! isset(self::SALA_A_EMPRESA[$sala])) {
                    $salasDesconocidas[$sala] = ($salasDesconocidas[$sala] ?? 0) + 1;
                    $omitidos++;

                    continue;
                }

                $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->flash_fecha ?? ''));
                if ($fechaEntera < 10000101) {
                    $omitidos++;

                    continue;
                }
                $fecha = sprintf(
                    '%04d-%02d-%02d',
                    (int) substr((string) $fechaEntera, 0, 4),
                    (int) substr((string) $fechaEntera, 4, 2),
                    (int) substr((string) $fechaEntera, 6, 2),
                );

                $empresaId = self::SALA_A_EMPRESA[$sala];
                $payload = $this->mapearFila($fila, $empresaId, $fecha);

                $resultado = DB::transaction(function () use ($empresaId, $fecha, $payload) {
                    $existente = $this->repository->findPorEmpresaFecha($empresaId, $fecha);
                    if ($existente === null) {
                        $this->repository->create($payload);

                        return 'creado';
                    }
                    $this->repository->update($payload, $existente->id);

                    return 'actualizado';
                });

                if ($resultado === 'creado') {
                    $creados++;
                } else {
                    $actualizados++;
                }
            }

            $cursor->addMonthNoOverflow()->startOfMonth();
        }

        return [
            'leidos' => $leidos,
            'creados' => $creados,
            'actualizados' => $actualizados,
            'omitidos' => $omitidos,
            'salas_desconocidas' => $salasDesconocidas,
        ];
    }

    /**
     * @param  list<int>  $salas
     * @return list<object>
     */
    private function leerMes(string $sistema, string $tabla, Carbon $desde, Carbon $hasta, array $salas): array
    {
        $desdeEntera = (int) $desde->format('Ymd');
        $hastaEntera = (int) $hasta->format('Ymd');
        $salasSql = implode(',', array_map('intval', $salas));

        $campos = implode(', ', [
            'flash_empresa', 'flash_sala', 'flash_fecha', 'flash_att', 'flash_ayb',
            'flash_slot_d', 'flash_slot_r', 'flash_slot_coin_in', 'flash_soft_count', 'flash_hard_count',
            'flash_cant_slots',
            'flash_rul_d', 'flash_rul_r', 'flash_rul_coin_in', 'flash_soft_rul', 'flash_hard_rul',
            'flash_cant_rul',
            'flash_cotizacion', 'flash_comentario',
            'flash_bingo_carton', 'flash_bingo_venta', 'flash_bingo_result',
            'flash_pos_online',
            'flash_win_ol_slot', 'flash_win_ol_rul',
            'flash_estac', 'flash_cant_vehic', 'flash_show',
        ]);

        $where = ' WHERE flash_fecha >= \''.$desdeEntera.'\''
            .' AND flash_fecha <= \''.$hastaEntera.'\''
            .' AND flash_sala IN ('.$salasSql.')';

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => 'flash_fecha, flash_sala',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar flash Anita: '.$parsed['error_lectura']);
        }

        return $parsed['filas'];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearFila(object $fila, int $empresaId, string $fecha): array
    {
        $comentario = trim((string) ($fila->flash_comentario ?? ''));
        if ($comentario !== '') {
            $comentario = mb_substr($comentario, 0, 30);
        } else {
            $comentario = null;
        }

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'att' => (int) ($fila->flash_att ?? 0),
            'ayb' => round((float) ($fila->flash_ayb ?? 0), 2),
            'slot_coin_in' => round((float) ($fila->flash_slot_coin_in ?? 0), 2),
            'slot_d' => round((float) ($fila->flash_slot_d ?? 0), 2),
            'slot_r' => round((float) ($fila->flash_slot_r ?? 0), 2),
            'soft_count' => round((float) ($fila->flash_soft_count ?? 0), 2),
            'hard_count' => round((float) ($fila->flash_hard_count ?? 0), 2),
            'cant_slots' => (int) ($fila->flash_cant_slots ?? 0),
            'rul_coin_in' => round((float) ($fila->flash_rul_coin_in ?? 0), 2),
            'rul_d' => round((float) ($fila->flash_rul_d ?? 0), 2),
            'rul_r' => round((float) ($fila->flash_rul_r ?? 0), 2),
            'soft_rul' => round((float) ($fila->flash_soft_rul ?? 0), 2),
            'hard_rul' => round((float) ($fila->flash_hard_rul ?? 0), 2),
            'cant_rul' => (int) ($fila->flash_cant_rul ?? 0),
            'cotizacion' => $this->nullableFloat($fila->flash_cotizacion ?? null),
            'comentario' => $comentario,
            'bingo_cant_carton' => (int) ($fila->flash_bingo_carton ?? 0),
            'bingo_total_venta' => round((float) ($fila->flash_bingo_venta ?? 0), 2),
            'bingo_resultado' => round((float) ($fila->flash_bingo_result ?? 0), 2),
            'pos_online' => (int) ($fila->flash_pos_online ?? 0),
            'win_ol_slot' => round((float) ($fila->flash_win_ol_slot ?? 0), 2),
            'win_ol_rul' => round((float) ($fila->flash_win_ol_rul ?? 0), 2),
            'estac' => round((float) ($fila->flash_estac ?? 0), 2),
            'vending' => 0.0,
            'cant_vehic' => (int) ($fila->flash_cant_vehic ?? 0),
            'show' => round((float) ($fila->flash_show ?? 0), 2),
            'calculado_en' => null,
        ];
    }

    private function nullableFloat(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return round((float) $valor, 4);
    }
}
