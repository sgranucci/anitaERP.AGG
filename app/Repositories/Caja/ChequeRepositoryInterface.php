<?php

namespace App\Repositories\Caja;

interface ChequeRepositoryInterface extends RepositoryInterface
{

    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Caja\Cheque>
     */
    public function leeCheque($filtros, bool $flPaginando = true);

    public function sincronizarConAnita();
    public function sincronizarCpromaeConAnita(): void;
    public function sincronizarCtermaeConAnita(bool $soloCartera = false): void;
    public function traerRegistroDeAnita($key1, $key2, $key3);
	public function guardarAnita($request);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($origen, $cuenta, $numeroCheque);
    public function findPorNumeroCheque($codigo);
    public function findPorNroInternoAnita(int $nroInterno): ?\App\Models\Caja\Cheque;
    public function vincularNroInternoAnita(int $chequeId, int $nroInterno): void;

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardarChequeIngresoEgreso(array $data, string $funcion, int $cajaMovimientoId);

}

