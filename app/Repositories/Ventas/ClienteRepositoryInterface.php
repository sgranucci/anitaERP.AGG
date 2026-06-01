<?php

namespace App\Repositories\Ventas;

interface ClienteRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
    public function existeClientePorCodigo(string $codigo): bool;
    public function replicarClienteEnAnitaPorCodigo(string $codigo): string;

    public function sincronizarAnitaDespuesDeGrabado(int $clienteId): void;
    public function previewInsertClimaeSqlPorCodigo(string $codigo): string;
    public function updateEmiteNc($id);
    public function findPorCodigo($codigo);
    public function actualizaPadronMipyme($modo);
    public function actualizaPadronMipymePorCuit($cuit, $modo);

}

