<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto;

interface PartidagastoRepositoryInterface extends RepositoryInterface
{

    public function createDesdeAnita(array $data);

    /**
     * Partidas del último presupuesto para empresa (modal requisición).
     * Si se indica centrocostodestino_id, solo partidas con ese centro de costo.
     *
     * @return array{data: string}
     */
    public function consultaPartidagasto($consulta, $empresa_id, $centrocostodestino_id = null);

    /**
     * Partida exacta por código (último presupuesto, empresa y opcionalmente CC destino).
     */
    public function resolverPorCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): ?Partidagasto;

    /**
     * @return array{ok: bool, row: ?Partidagasto, mensaje: ?string}
     */
    public function diagnosticarCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): array;
}

