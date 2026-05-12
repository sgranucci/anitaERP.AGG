<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Capex;

interface CapexRepositoryInterface extends RepositoryInterface
{

    public function createDesdeAnita(array $data);

    /**
     * Proyectos CAPEX del último presupuesto para empresa (modal requisición u otros).
     * Si se indica centrocostodestino_id, solo registros con ese centro de costo.
     *
     * @return array{data: string}
     */
    public function consultaCapex($consulta, $empresa_id, $centrocostodestino_id = null);

    /**
     * CAPEX exacto por código (último presupuesto, empresa, ACTIVO y opcionalmente CC destino).
     */
    public function resolverPorCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): ?Capex;

    /**
     * @return array{ok: bool, row: ?Capex, mensaje: ?string}
     */
    public function diagnosticarCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): array;
}

