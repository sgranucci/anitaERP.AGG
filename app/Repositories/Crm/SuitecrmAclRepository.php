<?php

namespace App\Repositories\Crm;

use App\Services\Crm\SuitecrmConfigService;
use Illuminate\Support\Facades\Cache;

class SuitecrmAclRepository
{
    public function __construct(
        private readonly SuitecrmConfigService $config
    ) {}

    /**
     * UUID de usuarios SuiteCRM con el rol indicado (ej. Supervisor → mgomez).
     *
     * @return array<int, string>
     */
    public function findUserIdsByRolNombre(string $rolNombre): array
    {
        $rolNombre = trim($rolNombre);
        if ($rolNombre === '') {
            return [];
        }

        $cacheKey = 'suitecrm.user_ids_rol.'.md5(mb_strtolower($rolNombre));

        return Cache::remember($cacheKey, 300, function () use ($rolNombre) {
            return $this->config->connection()
                ->table('acl_roles_users as aru')
                ->join('acl_roles as ar', function ($join) {
                    $join->on('aru.role_id', '=', 'ar.id')
                        ->where('ar.deleted', 0);
                })
                ->where('aru.deleted', 0)
                ->where('ar.name', $rolNombre)
                ->whereNotNull('aru.user_id')
                ->distinct()
                ->pluck('aru.user_id')
                ->map(fn ($id) => (string) $id)
                ->filter(fn ($id) => $id !== '')
                ->values()
                ->all();
        });
    }
}
