<?php

namespace App\Repositories\Compras;

interface Listaprecio_ProveedorRepositoryInterface
{
    public function create(array $data);

    public function update(array $data, $id);

    public function find($id);

    public function delete($id);

    public function sincronizarConAnita();

    /**
     * Graba en Anita (listapmae + listapmov) el estado actual del ERP.
     * Debe llamarse cuando la cabecera y los ítems ya están persistidos localmente.
     */
    public function persistirEnAnita(int $listaprecio_proveedor_id): void;

    /**
     * Importa una lista desde Anita (tablas listapmae / listapmov) por número de lista.
     */
    public function importarDesdeAnita(int $lispm_nro): void;
}
