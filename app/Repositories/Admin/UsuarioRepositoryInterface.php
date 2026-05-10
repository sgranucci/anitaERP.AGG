<?php

namespace App\Repositories\Admin;

interface UsuarioRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorUsuarioId($id);

    /**
     * Busca por id numérico o por código de login (columna usuario).
     */
    public function findPorIdOCodigo(string $valor, $empresa_id = null);
}

