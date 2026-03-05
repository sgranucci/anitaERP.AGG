<?php

namespace App\Repositories\Contable;

interface Usuario_CuentacontableRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorUsuario($usuario_id);
    public function leePorUsuarioCuenta($usuario_id, $cuentacontable_id);
    public function deletePorUsuario($usuario_id);
}

