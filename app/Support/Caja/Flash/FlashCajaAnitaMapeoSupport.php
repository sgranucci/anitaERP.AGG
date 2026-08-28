<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Models\Caja\Flash\FlashCaja;
use App\Models\Configuracion\Empresa;

/**
 * Mapeo ERP ↔ Anita (tabla flash): sala 21/38/43 → empresas 1/2/3.
 *
 * Informix no tiene columna de vending: flash_ayb = AyB ERP + vending ERP.
 */
final class FlashCajaAnitaMapeoSupport
{
    /** @var array<int, int> sala Anita → empresa_id ERP */
    public const SALA_A_EMPRESA = [
        21 => 1,
        38 => 2,
        43 => 3,
    ];

    public static function empresaIdDesdeSala(int $sala): ?int
    {
        return self::SALA_A_EMPRESA[$sala] ?? null;
    }

    public static function salaDesdeEmpresaId(int $empresaId): ?int
    {
        $sala = array_search($empresaId, self::SALA_A_EMPRESA, true);

        return $sala === false ? null : (int) $sala;
    }

    /**
     * @return list<int>
     */
    public static function salas(): array
    {
        return array_keys(self::SALA_A_EMPRESA);
    }

    public static function flashEmpresaDesdeEmpresaId(int $empresaId): int
    {
        $codigo = (int) (Empresa::query()->whereKey($empresaId)->value('codigo') ?? 0);

        return $codigo > 0 ? $codigo : $empresaId;
    }

    public static function fechaEntera(string $fechaIso): int
    {
        return (int) preg_replace('/\D+/', '', $fechaIso);
    }

    /**
     * @return list<string>
     */
    public static function camposClaveAnita(): array
    {
        return ['flash_empresa', 'flash_sala', 'flash_fecha'];
    }

    /**
     * @return list<string>
     */
    public static function camposUpdateAnita(): array
    {
        return array_values(array_filter(
            self::camposInsertAnita(),
            static fn (string $campo): bool => ! in_array($campo, self::camposClaveAnita(), true)
        ));
    }

    public static function whereClave(int $flashEmpresa, int $sala, int $fechaEntera): string
    {
        return ' WHERE flash_empresa = '.$flashEmpresa
            .' AND flash_sala = '.$sala
            .' AND flash_fecha = '.$fechaEntera;
    }

    /**
     * @return list<string>
     */
    public static function camposInsertAnita(): array
    {
        return [
            'flash_empresa', 'flash_sala', 'flash_fecha', 'flash_att', 'flash_ayb',
            'flash_slot_d', 'flash_slot_r', 'flash_slot_coin_in', 'flash_soft_count', 'flash_hard_count',
            'flash_cant_slots',
            'flash_rul_d', 'flash_rul_r', 'flash_rul_coin_in', 'flash_soft_rul', 'flash_hard_rul',
            'flash_cant_rul',
            'flash_cotizacion', 'flash_comentario',
            'flash_bingo_carton', 'flash_bingo_venta', 'flash_bingo_result',
            'flash_pos_online',
            'flash_poker_d', 'flash_poker_r', 'flash_poker_cin', 'flash_poker_soft_c', 'flash_poker_hard_c',
            'flash_cant_poker',
            'flash_win_ol_slot', 'flash_win_ol_rul', 'flash_win_ol_poker',
            'flash_estac', 'flash_cant_vehic', 'flash_simulador', 'flash_play', 'flash_arcade',
            'flash_cant_item', 'flash_show',
        ];
    }

    /**
     * Anita no discrimina vending: un solo campo flash_ayb.
     */
    public static function flashAybParaAnita(FlashCaja $flash): float
    {
        return round((float) ($flash->ayb ?? 0) + (float) ($flash->vending ?? 0), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public static function valoresDesdeFlash(FlashCaja $flash, int $sala, int $flashEmpresa): array
    {
        $comentario = mb_substr(trim((string) ($flash->comentario ?? '')), 0, 30);

        return [
            'flash_empresa' => $flashEmpresa,
            'flash_sala' => $sala,
            'flash_fecha' => self::fechaEntera($flash->fecha?->format('Y-m-d') ?? ''),
            'flash_att' => (int) ($flash->att ?? 0),
            'flash_ayb' => self::flashAybParaAnita($flash),
            'flash_slot_d' => (float) ($flash->slot_d ?? 0),
            'flash_slot_r' => (float) ($flash->slot_r ?? 0),
            'flash_slot_coin_in' => (float) ($flash->slot_coin_in ?? 0),
            'flash_soft_count' => (float) ($flash->soft_count ?? 0),
            'flash_hard_count' => (float) ($flash->hard_count ?? 0),
            'flash_cant_slots' => (int) ($flash->cant_slots ?? 0),
            'flash_rul_d' => (float) ($flash->rul_d ?? 0),
            'flash_rul_r' => (float) ($flash->rul_r ?? 0),
            'flash_rul_coin_in' => (float) ($flash->rul_coin_in ?? 0),
            'flash_soft_rul' => (float) ($flash->soft_rul ?? 0),
            'flash_hard_rul' => (float) ($flash->hard_rul ?? 0),
            'flash_cant_rul' => (int) ($flash->cant_rul ?? 0),
            'flash_cotizacion' => (float) ($flash->cotizacion ?? 0),
            'flash_comentario' => $comentario,
            'flash_bingo_carton' => (int) ($flash->bingo_cant_carton ?? 0),
            'flash_bingo_venta' => (float) ($flash->bingo_total_venta ?? 0),
            'flash_bingo_result' => (float) ($flash->bingo_resultado ?? 0),
            'flash_pos_online' => (int) ($flash->pos_online ?? 0),
            'flash_poker_d' => 0.0,
            'flash_poker_r' => 0.0,
            'flash_poker_cin' => 0.0,
            'flash_poker_soft_c' => 0.0,
            'flash_poker_hard_c' => 0.0,
            'flash_cant_poker' => 0,
            'flash_win_ol_slot' => (float) ($flash->win_ol_slot ?? 0),
            'flash_win_ol_rul' => (float) ($flash->win_ol_rul ?? 0),
            'flash_win_ol_poker' => 0.0,
            'flash_estac' => (float) ($flash->estac ?? 0),
            'flash_cant_vehic' => (int) ($flash->cant_vehic ?? 0),
            'flash_simulador' => 0.0,
            'flash_play' => 0.0,
            'flash_arcade' => 0.0,
            'flash_cant_item' => 0,
            'flash_show' => (float) ($flash->show ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public static function valoresSql(array $valores): string
    {
        $enteros = [
            'flash_empresa', 'flash_sala', 'flash_fecha', 'flash_att', 'flash_cant_slots',
            'flash_cant_rul', 'flash_bingo_carton', 'flash_pos_online', 'flash_cant_poker',
            'flash_cant_vehic', 'flash_cant_item',
        ];
        $partes = [];
        foreach (self::camposInsertAnita() as $campo) {
            $partes[] = self::valorSqlCampo($campo, $valores[$campo] ?? 0, $enteros);
        }

        return implode(', ', $partes);
    }

    /**
     * @param  array<string, mixed>  $valores
     */
    public static function valoresUpdateSql(array $valores): string
    {
        $enteros = [
            'flash_empresa', 'flash_sala', 'flash_fecha', 'flash_att', 'flash_cant_slots',
            'flash_cant_rul', 'flash_bingo_carton', 'flash_pos_online', 'flash_cant_poker',
            'flash_cant_vehic', 'flash_cant_item',
        ];
        $partes = [];
        foreach (self::camposUpdateAnita() as $campo) {
            $partes[] = $campo.' = '.self::valorSqlCampo($campo, $valores[$campo] ?? 0, $enteros);
        }

        return implode(', ', $partes);
    }

    /**
     * @param  list<string>  $enteros
     */
    private static function valorSqlCampo(string $campo, mixed $valor, array $enteros): string
    {
        if ($campo === 'flash_comentario') {
            return "'".str_replace("'", "''", (string) $valor)."'";
        }
        if (in_array($campo, $enteros, true)) {
            return (string) (int) $valor;
        }

        return number_format((float) $valor, 4, '.', '');
    }
}
