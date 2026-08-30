<?php

namespace App\Repositories\Configuracion;

interface ProvinciaRepositoryInterface extends RepositoryInterface
{

    public function all();

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection
     */
    public function leeProvincia($filtros, bool $paginar = false);

    public function sincronizarConAnita();
    public function resincronizarConAnita(): array;
    public function traerRegistroDeAnita($key);
	public function guardarAnita($request, $id);
	public function actualizarAnita($request, $id);
	public function eliminarAnita($id);
    public function findPorId($id);
    public function findPorCodigo($codigo);
    public function findPorJurisdiccion($jurisdiccion);

}

