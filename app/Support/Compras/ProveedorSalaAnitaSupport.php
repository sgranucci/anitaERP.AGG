<?php

namespace App\Support\Compras;

use App\Models\Configuracion\Sala;
use Illuminate\Support\Collection;

/**
 * Mapeo Anita `compras.casino` / `procasino` ↔ ERP `sala` (solo Crown).
 *
 * Códigos Anita (casi_casino) verificados en /usr2/casino 17/oct/2026.
 */
final class ProveedorSalaAnitaSupport
{
    /**
     * casino Anita → código de sala ERP.
     *
     * @var array<int, string>
     */
    public const CASINO_A_CODIGO_SALA = [
        1 => 'CIP', // Cipoletti
        2 => 'GRO', // Roca / Gral. Roca
        3 => 'CHO', // Choele Choel
        4 => 'VIE', // Viedma
        5 => 'EFA', // El Faro
        6 => 'LGR', // Las Grutas
        7 => 'CAT', // Catriel
    ];

    public static function codigoSalaDesdeCasinoAnita(int $casinoAnita): ?string
    {
        return self::CASINO_A_CODIGO_SALA[$casinoAnita] ?? null;
    }

    public static function casinoAnitaDesdeSala(Sala $sala): ?int
    {
        $codigo = strtoupper(trim((string) ($sala->codigo ?? '')));
        if ($codigo === '') {
            return null;
        }

        $flip = array_flip(self::CASINO_A_CODIGO_SALA);

        return $flip[$codigo] ?? null;
    }

    public static function salaIdDesdeCasinoAnita(int $casinoAnita): ?int
    {
        $codigo = self::codigoSalaDesdeCasinoAnita($casinoAnita);
        if ($codigo === null) {
            return null;
        }

        $id = Sala::query()->where('codigo', $codigo)->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  list<object|array<string, mixed>>  $filasProcasino
     * @return list<int> sala_id
     */
    public static function salaIdsDesdeFilasProcasino(array $filasProcasino): array
    {
        $ids = [];
        foreach ($filasProcasino as $fila) {
            $a = is_array($fila) ? $fila : (array) $fila;
            $casino = (int) ($a['proca_casino'] ?? 0);
            if ($casino <= 0) {
                continue;
            }
            $salaId = self::salaIdDesdeCasinoAnita($casino);
            if ($salaId !== null) {
                $ids[] = $salaId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Salas del ABM Crown (todas las de la empresa, ordenadas).
     *
     * @return Collection<int, Sala>
     */
    public static function salasParaSelector(): Collection
    {
        return Sala::query()
            ->orderBy('codigo')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'empresa_id']);
    }
}
