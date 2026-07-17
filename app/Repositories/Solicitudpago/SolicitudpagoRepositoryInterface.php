<?php

namespace App\Repositories\Solicitudpago;

interface SolicitudpagoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeSolicitudpago(array|string|null $filtros, bool $paginar = true);

    public function sincronizarConAnita(): array;

    public function findPorCodigo(int $codigo);

    public function guardarCompleto(array $data, ?int $id = null);

    public function cambiarEstado(int $id, string $nuevoEstado, string $leyenda = '');
}
