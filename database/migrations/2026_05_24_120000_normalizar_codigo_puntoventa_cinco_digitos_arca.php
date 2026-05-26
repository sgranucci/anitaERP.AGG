<?php

use App\Models\Ventas\Puntoventa;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formato ARCA: código de punto de venta con 5 dígitos (ceros a la izquierda).
     * Duplicados lógicos (ej. "30" y "00030"): conserva el id menor, reasigna FKs y elimina el resto.
     */
    public function up(): void
    {
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        $rows = DB::table('puntoventa')->orderBy('id')->get(['id', 'empresa_id', 'codigo']);
        $keeperPorClave = [];
        $duplicados = [];

        foreach ($rows as $row) {
            $normalizado = Puntoventa::normalizarCodigoArca((string) $row->codigo);
            if ($normalizado === null) {
                continue;
            }

            $clave = $row->empresa_id.'|'.$normalizado;
            if (! isset($keeperPorClave[$clave])) {
                $keeperPorClave[$clave] = (int) $row->id;

                continue;
            }

            $duplicados[] = [
                'id' => (int) $row->id,
                'keeper_id' => $keeperPorClave[$clave],
                'codigo' => $normalizado,
            ];
        }

        foreach ($duplicados as $dup) {
            $this->reasignarReferenciasPuntoventa($dup['id'], $dup['keeper_id']);
            Puntoventa::withTrashed()->where('id', $dup['id'])->forceDelete();
        }

        foreach ($rows as $row) {
            $normalizado = Puntoventa::normalizarCodigoArca((string) $row->codigo);
            if ($normalizado === null || (string) $row->codigo === $normalizado) {
                continue;
            }

            if (isset($keeperPorClave[$row->empresa_id.'|'.$normalizado])
                && $keeperPorClave[$row->empresa_id.'|'.$normalizado] !== (int) $row->id) {
                continue;
            }

            DB::table('puntoventa')->where('id', $row->id)->update(['codigo' => $normalizado]);
        }
    }

    private function reasignarReferenciasPuntoventa(int $desdeId, int $haciaId): void
    {
        if ($desdeId === $haciaId) {
            return;
        }

        $referencias = [
            ['configuracion_puntoventa_gastronomia', 'puntoventa_cae_id'],
            ['configuracion_puntoventa_gastronomia', 'puntoventa_caea_id'],
            ['venta', 'puntoventa_id'],
            ['venta', 'puntoventaremito_id'],
        ];

        if (Schema::hasTable('categoria') && Schema::hasColumn('categoria', 'puntoventa_id')) {
            $referencias[] = ['categoria', 'puntoventa_id'];
        }

        foreach ($referencias as [$tabla, $columna]) {
            if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
                continue;
            }
            DB::table($tabla)->where($columna, $desdeId)->update([$columna => $haciaId]);
        }
    }

    public function down(): void
    {
        // No revertir: los ceros a la izquierda son el formato canónico ARCA.
    }
};
