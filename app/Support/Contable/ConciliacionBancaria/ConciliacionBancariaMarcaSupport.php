<?php

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaMarca;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marcas de conciliado por movimiento. Un movimiento con marca está conciliado (como con fecha de
 * conciliación en Anita); sin marca ni cruce, está pendiente. Las pantallas, los reportes y la corrida
 * automática leen todo desde ConciliacionBancariaService::hashesConciliadosCuenta, que suma estas marcas.
 */
final class ConciliacionBancariaMarcaSupport
{
    public const LADO_ERP = ConciliacionBancariaMarca::LADO_ERP;

    public const LADO_BANCO = ConciliacionBancariaMarca::LADO_BANCO;

    public const ORIGEN_ANITA = 'anita';

    private static ?bool $tabla = null;

    /** @var array{marcas: array{C: array<string, true>, B: array<string, true>}, pares: array<int, true>, grupos: array<int, true>}|null */
    private static ?array $simulacion = null;

    public static function existeTabla(): bool
    {
        return self::$tabla ??= Schema::hasTable('conciliacion_bancaria_marca');
    }

    /**
     * @return array{C: array<string, true>, B: array<string, true>}
     */
    public static function hashes(int $empresaId, int $cuentacajaId): array
    {
        $out = [self::LADO_ERP => [], self::LADO_BANCO => []];
        if (self::existeTabla()) {
            DB::table('conciliacion_bancaria_marca')
                ->where('empresa_id', $empresaId)
                ->where('cuentacaja_id', $cuentacajaId)
                ->orderBy('id')
                ->chunkById(2000, function ($chunk) use (&$out): void {
                    foreach ($chunk as $m) {
                        if (isset($out[$m->lado])) {
                            $out[$m->lado][(string) $m->hash] = true;
                        }
                    }
                });
        }
        if (self::$simulacion !== null) {
            $out[self::LADO_ERP] += self::$simulacion['marcas'][self::LADO_ERP];
            $out[self::LADO_BANCO] += self::$simulacion['marcas'][self::LADO_BANCO];
        }

        return $out;
    }

    /**
     * Dry-run: calcula pantallas y reportes como si estas marcas existieran y esos cruces no, sin escribir nada.
     *
     * @param  array{C: array<string, true>, B: array<string, true>}  $marcas
     * @param  list<int>  $paresIgnorados
     * @param  list<int>  $gruposIgnorados
     */
    public static function simular(array $marcas, array $paresIgnorados = [], array $gruposIgnorados = []): void
    {
        self::$simulacion = [
            'marcas' => [
                self::LADO_ERP => $marcas[self::LADO_ERP] ?? [],
                self::LADO_BANCO => $marcas[self::LADO_BANCO] ?? [],
            ],
            'pares' => array_fill_keys($paresIgnorados, true),
            'grupos' => array_fill_keys($gruposIgnorados, true),
        ];
    }

    public static function dejarDeSimular(): void
    {
        self::$simulacion = null;
    }

    public static function parIgnorado(int $parId): bool
    {
        return self::$simulacion !== null && isset(self::$simulacion['pares'][$parId]);
    }

    public static function grupoIgnorado(int $grupoId): bool
    {
        return self::$simulacion !== null && isset(self::$simulacion['grupos'][$grupoId]);
    }

    /** Hash de un cheque o cobranza que viene del stock de cheques y no del mayor. */
    public static function hashChequeStock(string $tip, string $numero): string
    {
        return 'CHEQ|'.strtoupper(trim($tip)).'|'.(ltrim(trim($numero), '0') ?: '0');
    }

    /** Quita la marca de un movimiento (vuelve a figurar como pendiente). */
    public static function quitar(int $empresaId, int $cuentacajaId, string $lado, string $hash): int
    {
        if (! self::existeTabla() || $hash === '') {
            return 0;
        }
        $n = 0;
        ConciliacionBancariaMarca::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('lado', $lado)
            ->where('hash', $hash)
            ->get()
            ->each(function (ConciliacionBancariaMarca $m) use (&$n): void {
                $m->delete();
                $n++;
            });

        return $n;
    }
}
