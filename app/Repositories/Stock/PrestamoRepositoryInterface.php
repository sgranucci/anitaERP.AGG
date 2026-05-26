<?php

namespace App\Repositories\Stock;

interface PrestamoRepositoryInterface
{
    public function all();

    public function find(int $id);

    public function findConRelaciones(int $id);

    public function create(array $data);

    public function update(int $id, array $data);

    public function delete(int $id): bool;

    /**
     * Préstamos en estado vencido o próximos a vencer pendientes de
     * devolución para enviar recordatorios automáticos.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\Stock\Prestamo>
     */
    public function pendientesParaRecordar();
}
