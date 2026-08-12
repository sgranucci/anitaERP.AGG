<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Documento_Fiscal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

interface Proveedor_Documento_FiscalRepositoryInterface
{
    public function listarPorProveedor(int $proveedorId): Collection;

    /**
     * @return array{CUIT: ?Proveedor_Documento_Fiscal, CM05: ?Proveedor_Documento_Fiscal}
     */
    public function vigentesPorTipo(int $proveedorId): array;

    /**
     * @return list<array{tipo: string, etiqueta: string, estado: string, documento: ?Proveedor_Documento_Fiscal, mensaje: string}>
     */
    public function avisosPortal(int $proveedorId): array;

    public function crearDesdeUpload(
        int $proveedorId,
        string $tipo,
        UploadedFile $archivo,
        ?string $fechaVencimiento,
        ?int $anioEjercicio,
        string $origen,
    ): Proveedor_Documento_Fiscal;

    public function findDelProveedor(int $id, int $proveedorId): Proveedor_Documento_Fiscal;

    public function eliminar(int $id, int $proveedorId): void;

    public function sincronizarDesdeRequest(int $proveedorId, $request): void;
}
