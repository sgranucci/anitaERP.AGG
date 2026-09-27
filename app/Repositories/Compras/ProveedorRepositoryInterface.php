<?php

namespace App\Repositories\Compras;

interface ProveedorRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita(?int $empresaId = null, ?string $pathSistema = null);

    public function resincronizarDesdeAnita(
        bool $dryRun = false,
        ?int $empresaId = null,
        ?string $pathSistema = null,
    ): array;

    public function traerRegistroDeAnita(
        string $codigoAnita,
        ?bool $flCreaRegistro = null,
        ?int $empresaId = null,
        ?string $pathSistema = null,
    ): ?string;

    public function existeProveedorPorCodigo(string $codigo, ?int $empresaId = null): bool;

    public function previewSincronizacionDesdeAnita(
        string $codigoAnita,
        ?string $pathSistema = null,
        ?int $empresaId = null,
    ): ?array;

    /**
     * Reenvía proveedor ERP → Anita (inserta promae si falta).
     *
     * @return 'insertado'|'actualizado'
     */
    public function sincronizarAnitaDesdeErp(int $proveedorId): string;

    /**
     * Pack C: cortes por agrupación (universo filtrado).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesProveedor(array $filtros): array;

}
