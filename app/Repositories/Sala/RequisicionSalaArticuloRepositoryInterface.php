<?php

namespace App\Repositories\Sala;

interface RequisicionSalaArticuloRepositoryInterface extends RepositoryInterface
{
    public function syncFromRequest(array $data, int $requisicion_sala_id): void;
}
