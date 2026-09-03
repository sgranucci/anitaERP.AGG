<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Models\Compras\Ordencompra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Resuelve IDs de orden de compra solo en ERP (ya no se importa desde Anita).
 */
class MayorPlanoCuentaOrdencompraEnricher
{
    /** @var array<int, int> */
    private array $cachePorNumero = [];

    /** @var array<int, int> */
    private array $cachePorComprobante = [];

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $numerosOc = [];
        $cpIds = [];

        foreach ($filas as $fila) {
            $nro = (int) ($fila['nro_oc'] ?? 0);
            if ($nro > 0) {
                $numerosOc[$nro] = $nro;
            }
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
            if ($cpId > 0) {
                $cpIds[$cpId] = $cpId;
            }
        }

        $this->precargarExistentes(array_values($numerosOc));
        $this->precargarDesdeComprobantes(array_values($cpIds));

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $ocIdExistente = (int) ($fila['ordencompra_id'] ?? 0);
            if ($ocIdExistente > 0) {
                continue;
            }

            $nroOc = (int) ($fila['nro_oc'] ?? 0);
            $cpId = (int) ($fila['comprobante_proveedor_id'] ?? 0);
            $ocId = $nroOc > 0 ? (int) ($this->cachePorNumero[$nroOc] ?? 0) : 0;
            if ($ocId <= 0 && $cpId > 0) {
                $ocId = (int) ($this->cachePorComprobante[$cpId] ?? 0);
            }
            $filas[$idx]['ordencompra_id'] = $ocId;
        }

        return $filas;
    }

    /**
     * @param  list<int>  $numerosOc
     */
    private function precargarExistentes(array $numerosOc): void
    {
        $faltantes = array_values(array_filter($numerosOc, fn (int $n) => $n > 0 && ! isset($this->cachePorNumero[$n])));
        if ($faltantes === []) {
            return;
        }

        $mapa = Ordencompra::query()
            ->whereIn('numeroordencompra', $faltantes)
            ->orderBy('id')
            ->pluck('id', 'numeroordencompra')
            ->all();

        foreach ($mapa as $numero => $id) {
            $this->cachePorNumero[(int) $numero] = (int) $id;
        }
    }

    /**
     * @param  list<int>  $cpIds
     */
    private function precargarDesdeComprobantes(array $cpIds): void
    {
        if ($cpIds === [] || ! Schema::hasTable('comprobante_proveedor')) {
            return;
        }

        $query = DB::table('comprobante_proveedor')
            ->whereIn('id', $cpIds)
            ->where('ordencompra_id', '>', 0)
            ->select(['id', 'ordencompra_id']);

        foreach ($query->get() as $row) {
            $cpId = (int) $row->id;
            $ocId = (int) $row->ordencompra_id;
            if ($cpId > 0 && $ocId > 0) {
                $this->cachePorComprobante[$cpId] = $ocId;
            }
        }
    }
}
