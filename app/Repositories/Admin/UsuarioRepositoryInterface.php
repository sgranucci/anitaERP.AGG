<?php

namespace App\Repositories\Admin;

interface UsuarioRepositoryInterface extends RepositoryInterface
{

    public function all();
    public function leePorUsuarioId($id);
}

