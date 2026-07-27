<?php

namespace App\Repositories\Sala;

interface RequisicionSalaArticuloRepositoryInterface extends RepositoryInterface
{
    public function syncFromRequest(array $data, int $requisicion_sala_id): void;

    /** Actualiza solo leyenda/UID/NPU de líneas existentes (edición menor post-aprobación). */
    public function syncDatosMenoresFromRequest(array $data, int $requisicion_sala_id): void;
}
