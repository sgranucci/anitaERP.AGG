<?php

namespace App\Repositories\Compras;

interface ProveedorRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita(?int $empresaId = null);

    public function resincronizarDesdeAnita(bool $dryRun = false, ?int $empresaId = null): array;

    public function traerRegistroDeAnita(string $codigoAnita, ?bool $flCreaRegistro = null, ?int $empresaId = null): ?string;

    public function existeProveedorPorCodigo(string $codigo): bool;

    public function previewSincronizacionDesdeAnita(string $codigoAnita): ?array;

}
