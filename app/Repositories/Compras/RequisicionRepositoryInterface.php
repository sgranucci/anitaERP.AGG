<?php

namespace App\Repositories\Compras;

interface RequisicionRepositoryInterface extends RepositoryInterface
{
    /** Renumera provisorio si el número ya existe en ERP/Anita (numerador único global). */
    public function renumerarProvisorioSiColisionaGlobal(int $id): int;
}

