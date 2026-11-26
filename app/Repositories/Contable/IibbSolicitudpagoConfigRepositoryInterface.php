<?php

declare(strict_types=1);

namespace App\Repositories\Contable;

use App\Models\Contable\IibbSolicitudpagoConfig;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

interface IibbSolicitudpagoConfigRepositoryInterface
{
    public function all(): Collection;

    /**
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): IibbSolicitudpagoConfig;

    public function findPorEmpresa(int $empresaId): ?IibbSolicitudpagoConfig;

    /** Configuración activa de la empresa, con relaciones para liquidación y solicitud. */
    public function vigentePorEmpresa(int $empresaId): ?IibbSolicitudpagoConfig;

    public function create(array $data): IibbSolicitudpagoConfig;

    public function update(array $data, int $id): bool;

    public function delete(int $id): bool;
}
