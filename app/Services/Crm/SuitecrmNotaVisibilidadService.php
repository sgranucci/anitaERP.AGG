<?php

namespace App\Services\Crm;

use App\Repositories\Crm\SuitecrmAclRepository;
use App\Support\SuitecrmPermiso;

class SuitecrmNotaVisibilidadService
{
    /** @var array<int, string>|null */
    private ?array $userIdsSupervisor = null;

    public function __construct(
        private readonly SuitecrmAclRepository $aclRepository
    ) {}

    public function puedeVerNotasSupervisor(): bool
    {
        return SuitecrmPermiso::puedeVerNotasSupervisor();
    }

    public function nombreRolSupervisorSuitecrm(): string
    {
        return (string) config('suitecrm.supervisor_rol_nombre', 'Supervisor');
    }

    /**
     * @return array<int, string>
     */
    public function userIdsSupervisorSuitecrm(): array
    {
        if ($this->userIdsSupervisor === null) {
            $this->userIdsSupervisor = $this->aclRepository->findUserIdsByRolNombre(
                $this->nombreRolSupervisorSuitecrm()
            );
        }

        return $this->userIdsSupervisor;
    }

    public function esNotaDeSupervisor(?string $createdBy): bool
    {
        $createdBy = trim((string) $createdBy);
        if ($createdBy === '') {
            return false;
        }

        return in_array($createdBy, $this->userIdsSupervisorSuitecrm(), true);
    }

    public function puedeVerNota(object $nota): bool
    {
        if ($this->puedeVerNotasSupervisor()) {
            return true;
        }

        return ! $this->esNotaDeSupervisor($nota->created_by ?? null);
    }

    public function mensajeSinPermiso(): string
    {
        return 'No tiene permiso para ver notas creadas por supervisores en SuiteCRM.';
    }
}
