<?php

namespace App\Repositories\Caja;

interface ChequeraRepositoryInterface extends RepositoryInterface
{

    public function all();
    /**
     * @return array{en_anita:int,creadas:int,actualizadas:int,omitidas:int,errores:list<string>}
     */
    public function sincronizarConAnita(): array;
    public function sincronizarCuentaDesdeAnita(int $cuentacajaId): void;
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    public function findPorCodigo($codigo);

}

