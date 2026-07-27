<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Precarga_Comprobante_Proveedor;

interface Precarga_Comprobante_ProveedorRepositoryInterface extends RepositoryInterface
{

    public function all();

    public function listarPortalProveedor(int $proveedorId, bool $paginar = true);

    public function findDuplicadoPrecarga(
        int $empresaId,
        int $proveedorId,
        int $tipotransaccionCompraId,
        string $letra,
        $sucursal,
        $numerocomprobante,
        ?int $excluirId = null
    );

    public function mensajeFacturaDuplicada(
        Precarga_Comprobante_Proveedor $existente,
        string $tipoAbreviatura
    ): string;

}

