<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Models\Compras\Ordencompra;
use App\Services\Compras\OrdencompraAnitaSyncService;
use Illuminate\Support\Facades\Log;

/**
 * Resuelve IDs de orden de compra en ERP; sincroniza desde Anita bridge si falta.
 */
class MayorPlanoCuentaOrdencompraEnricher
{
    /** @var array<int, int> */
    private array $cachePorNumero = [];

    public function __construct(
        private readonly OrdencompraAnitaSyncService $syncService,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $numerosOc = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['nro_oc'] ?? 0),
            $filas,
        ), fn (int $n) => $n > 0)));

        if ($numerosOc === []) {
            return $filas;
        }

        $this->precargarExistentes($numerosOc);

        foreach ($numerosOc as $numeroOc) {
            if (isset($this->cachePorNumero[$numeroOc])) {
                continue;
            }

            try {
                $resultado = $this->syncService->traerRegistroDeAnita($numeroOc);
                if ($resultado === 'importado' || $resultado === 'omitido') {
                    $id = (int) Ordencompra::query()->where('numeroordencompra', $numeroOc)->value('id');
                    if ($id > 0) {
                        $this->cachePorNumero[$numeroOc] = $id;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('MayorPlanoCuenta: sync OC '.$numeroOc, ['error' => $e->getMessage()]);
            }
        }

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $nroOc = (int) ($fila['nro_oc'] ?? 0);
            $filas[$idx]['ordencompra_id'] = $nroOc > 0 ? (int) ($this->cachePorNumero[$nroOc] ?? 0) : 0;
        }

        return $filas;
    }

    /**
     * @param  list<int>  $numerosOc
     */
    private function precargarExistentes(array $numerosOc): void
    {
        $faltantes = array_values(array_filter($numerosOc, fn (int $n) => ! isset($this->cachePorNumero[$n])));
        if ($faltantes === []) {
            return;
        }

        $mapa = Ordencompra::query()
            ->whereIn('numeroordencompra', $faltantes)
            ->pluck('id', 'numeroordencompra')
            ->all();

        foreach ($mapa as $numero => $id) {
            $this->cachePorNumero[(int) $numero] = (int) $id;
        }
    }
}
