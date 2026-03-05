<?php

namespace App\Repositories\Caja;

interface Voucher_ReservaRepositoryInterface 
{

    public function create(array $data, $id);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($voucher_id, $codigo);
    public function leeVoucherReserva($voucher_id);
}

