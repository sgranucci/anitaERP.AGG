<?php

namespace App\Repositories\Ventas;

interface PedidoRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();
	public function ultimoCodigoAnita($tipo, $letra, $sucursal, &$nro);
	public function guardarAnita($request);
	public function all();

}

