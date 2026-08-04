<?php

namespace App\Repositories\Contable;

interface CuentacontableRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function sincronizarConAnita(?array $empresasCodigo = null): array;
    /**
     * Actualiza cuentacontable.conceptogasto_id desde Anita ctaconc (ctaco_concepto).
     *
     * @param  list<string>|null  $empresasCodigo
     * @return array{en_anita:int,actualizados:int,iguales:int,sin_cuenta:int,sin_concepto:int,errores:list<string>}
     */
    public function sincronizarConceptosDesdeAnita(bool $dryRun = false, ?array $empresasCodigo = null): array;
    public function traerRegistroDeAnita($empresa, $key);
	public function guardarAnita($request);
	public function actualizarAnita($request, $codigo);
	public function eliminarAnita($empresa, $id);
    public function findPorId($id);
    public function findPorCodigo($empresa_id, $codigo);

}

