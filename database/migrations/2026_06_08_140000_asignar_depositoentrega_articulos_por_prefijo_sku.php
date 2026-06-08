<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Asigna depósito de entrega (articulo.depositoentrega_id) según prefijo de SKU:
 * LIM → depmae 1000, LIB → 1001, FAR → 1002, DES → 1003.
 */
return new class extends Migration
{
    /** @var array<string, string> prefijo SKU => código depmae */
    private const PREFIJO_A_CODIGO_DEPOSITO = [
        'LIM' => '1000',
        'LIB' => '1001',
        'FAR' => '1002',
        'DES' => '1003',
    ];

    public function up(): void
    {
        $depositosPorPrefijo = $this->resolverDepositosPorPrefijo();

        foreach (self::PREFIJO_A_CODIGO_DEPOSITO as $prefijo => $codigoDeposito) {
            $depositoId = $depositosPorPrefijo[$prefijo] ?? null;
            if ($depositoId === null) {
                throw new \RuntimeException(
                    'Migración depósito por SKU: no existe depmae con código '.$codigoDeposito.' para prefijo '.$prefijo.'.'
                );
            }

            DB::table('articulo')
                ->where('sku', 'like', $prefijo.'%')
                ->update([
                    'depositoentrega_id' => $depositoId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No reversible: no se conservan los depositoentrega_id anteriores.
    }

    /**
     * @return array<string, int> prefijo => depmae.id
     */
    private function resolverDepositosPorPrefijo(): array
    {
        $codigos = array_values(self::PREFIJO_A_CODIGO_DEPOSITO);

        $filas = DB::table('depmae')
            ->whereIn('codigo', $codigos)
            ->get(['id', 'codigo']);

        $idPorCodigo = [];
        foreach ($filas as $fila) {
            $idPorCodigo[(string) $fila->codigo] = (int) $fila->id;
        }

        $resultado = [];
        foreach (self::PREFIJO_A_CODIGO_DEPOSITO as $prefijo => $codigo) {
            if (! isset($idPorCodigo[$codigo])) {
                continue;
            }
            $resultado[$prefijo] = $idPorCodigo[$codigo];
        }

        return $resultado;
    }
};
