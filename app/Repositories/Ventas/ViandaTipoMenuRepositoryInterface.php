<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\ViandaTipoMenu;

interface ViandaTipoMenuRepositoryInterface
{
    public function all(?int $empresaId = null);

    public function existeRegistro(): bool;

    public function create(array $data);

    public function update(array $data, $id);

    public function delete($id);

    public function find($id);

    public function findOrFail($id);

    /**
     * Copia artículos (y cabecera nombre/estado) del menú origen al de la empresa destino.
     * Si ya existe menú con el mismo código Anita (o mismo nombre sin código), lo pisa.
     */
    public function replicarAEmpresa(int $origenId, int $empresaDestinoId): ViandaTipoMenu;
}
