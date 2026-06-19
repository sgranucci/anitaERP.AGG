<?php

namespace App\Repositories\Contable;

interface AsientoRepositoryInterface extends RepositoryInterface
{

    public function sincronizarConAnita();

    /**
     * Reemplaza líneas ctamov en Anita (delete + insert) sin tocar el registro asiento en MySQL.
     *
     * @param  array<string, mixed>  $data  Debe incluir numeroasiento, empresa_id, fecha, cuentacontable_ids, debes, haberes, etc.
     */
    public function sincronizarCtamovAnita(array $data): void;

    public function leeAsientoPorClave($id, $clave);
}

