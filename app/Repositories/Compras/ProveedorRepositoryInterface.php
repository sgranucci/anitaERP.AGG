<?php

namespace App\Repositories\Compras;

interface ProveedorRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();

    public function resincronizarDesdeAnita(bool $dryRun = false): array;

    public function traerRegistroDeAnita(string $codigoAnita, ?bool $flCreaRegistro = null): ?string;

    public function existeProveedorPorCodigo(string $codigo): bool;

    public function previewSincronizacionDesdeAnita(string $codigoAnita): ?array;

}

