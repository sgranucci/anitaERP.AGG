<?php

namespace App\Repositories\Ventas;

interface VentaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePaginando($busqueda);
    public function leeSinPaginar($busqueda);
    public function totalesIndexPorReparto($filtros);
    public function idsIndexPorReparto($filtros, int $transporteId): array;
    public function findOrFail($id);
    public function find($id);
    public function delete($id);
    public function update(array $data, $id);
    public function create(array $data);
    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id, ?int $empresa_id = null);
    public function maxNumeroComprobanteAnitaBridge(string $tipo, string $letra, string $sucursal, $path_sistema = null): int;
    public function numeroComprobanteAnitaDelDia(
        string $tipo,
        string $letra,
        string $sucursal,
        string $fechaYmd,
        ?int $empresaAnita = null,
        $path_sistema = null,
    ): int;
}

