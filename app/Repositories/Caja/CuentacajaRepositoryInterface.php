<?php

namespace App\Repositories\Caja;

interface CuentacajaRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita(?string $codigo = null, bool $sincronizarCbu = true): array;
    public function sincronizarCbuConAnita(?string $codigo = null): array;
    public function traerRegistroDeAnita($key): string;
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    public function findPorCodigo($codigo);

}

