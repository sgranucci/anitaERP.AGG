<?php

namespace App\Repositories\Solicitudpago;

interface SolicitudpagoRepositoryInterface extends RepositoryInterface
{
    public function all();

    public function leeSolicitudpago(array|string|null $filtros, bool $paginar = true);

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesSolicitudpago(array $filtros): array;

    public function sincronizarConAnita(): array;

    public function findPorCodigo(int $codigo);

    public function guardarCompleto(array $data, ?int $id = null);

    public function cambiarEstado(int $id, string $nuevoEstado, string $leyenda = '');
}
