<?php

namespace App\Repositories\Caja;

interface Voucher_FormapagoRepositoryInterface 
{

    public function create(array $data, $id);
    public function update(array $data, $id);
    public function find($id);
    public function findOrFail($id);
    public function delete($voucher_id, $codigo);
    public function leeVoucherFormapago($voucher_id);
}

