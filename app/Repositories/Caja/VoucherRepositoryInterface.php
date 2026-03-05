<?php

namespace App\Repositories\Caja;

interface VoucherRepositoryInterface extends RepositoryInterface
{

    public function all();
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
	public function leeVoucher($busqueda, $flPaginando = null);
	public function leeVoucherPorGuiaOrdenservicio($guia_id, $ordenservicio_id);

}

