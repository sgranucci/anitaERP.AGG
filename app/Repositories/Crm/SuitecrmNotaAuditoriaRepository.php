<?php

namespace App\Repositories\Crm;

use App\Services\Crm\SuitecrmConfigService;
use Illuminate\Support\Collection;

class SuitecrmNotaAuditoriaRepository
{
    public function __construct(
        private readonly SuitecrmConfigService $config
    ) {}

    /**
     * Notas SuiteCRM con parent (cuenta / lead / contacto) y campos custom denormalizados.
     *
     * @param  array{
     *     vendedor_crm_id:?string,
     *     fecha_desde:?string,
     *     fecha_hasta:?string,
     *     parent_type:string,
     *     texto:string,
     *     solo_vinculo_erp?:bool
     * }  $filtros
     * @param  array<int, string>  $excluirCreatedByUserIds
     */
    public function listar(array $filtros, array $excluirCreatedByUserIds = []): Collection
    {
        $query = $this->config->connection()
            ->table('notes as n')
            ->leftJoin('notes_cstm as nc', 'nc.id_c', '=', 'n.id')
            ->leftJoin('users as u', 'u.id', '=', 'n.assigned_user_id')
            ->leftJoin('accounts as a', function ($join) {
                $join->on('a.id', '=', 'n.parent_id')
                    ->where('n.parent_type', '=', 'Accounts')
                    ->where('a.deleted', '=', 0);
            })
            ->leftJoin('accounts_cstm as ac', 'ac.id_c', '=', 'a.id')
            ->leftJoin('leads as l', function ($join) {
                $join->on('l.id', '=', 'n.parent_id')
                    ->where('n.parent_type', '=', 'Leads')
                    ->where('l.deleted', '=', 0);
            })
            ->leftJoin('contacts as c', function ($join) {
                $join->on('c.id', '=', 'n.parent_id')
                    ->where('n.parent_type', '=', 'Contacts')
                    ->where('c.deleted', '=', 0);
            })
            ->where('n.deleted', 0);

        if (($filtros['vendedor_crm_id'] ?? null) !== null && $filtros['vendedor_crm_id'] !== '') {
            $query->where('n.assigned_user_id', $filtros['vendedor_crm_id']);
        }

        if (($filtros['fecha_desde'] ?? null) !== null) {
            $query->where('n.date_entered', '>=', $filtros['fecha_desde'].' 00:00:00');
        }

        if (($filtros['fecha_hasta'] ?? null) !== null) {
            $query->where('n.date_entered', '<=', $filtros['fecha_hasta'].' 23:59:59');
        }

        $parentType = (string) ($filtros['parent_type'] ?? '');
        if ($parentType !== '') {
            $query->where('n.parent_type', $parentType);
        }

        $texto = trim((string) ($filtros['texto'] ?? ''));
        if ($texto !== '') {
            $like = '%'.$texto.'%';
            $query->where(function ($q) use ($like) {
                $q->where('n.name', 'like', $like)
                    ->orWhere('n.description', 'like', $like)
                    ->orWhere('a.name', 'like', $like)
                    ->orWhere('nc.cuenta_relacionada_c', 'like', $like)
                    ->orWhere('nc.cli_potencial_relacionado_c', 'like', $like)
                    ->orWhere('l.first_name', 'like', $like)
                    ->orWhere('l.last_name', 'like', $like)
                    ->orWhere('l.account_name', 'like', $like)
                    ->orWhere('c.first_name', 'like', $like)
                    ->orWhere('c.last_name', 'like', $like);
            });
        }

        if ($excluirCreatedByUserIds !== []) {
            $query->where(function ($q) use ($excluirCreatedByUserIds) {
                $q->whereNull('n.created_by')
                    ->orWhereNotIn('n.created_by', $excluirCreatedByUserIds);
            });
        }

        return $query
            ->orderBy('n.date_entered')
            ->orderBy('n.id')
            ->select([
                'n.id',
                'n.name',
                'n.description',
                'n.parent_type',
                'n.parent_id',
                'n.date_entered',
                'n.date_modified',
                'n.created_by',
                'n.assigned_user_id',
                'u.user_name as vendedor_user_name',
                'u.first_name as vendedor_first_name',
                'u.last_name as vendedor_last_name',
                'a.name as account_name',
                'ac.codigo_c as account_codigo',
                'ac.cuit_c as account_cuit',
                'l.first_name as lead_first_name',
                'l.last_name as lead_last_name',
                'l.account_name as lead_account_name',
                'l.status as lead_status',
                'l.converted as lead_converted',
                'c.first_name as contact_first_name',
                'c.last_name as contact_last_name',
                'nc.cuenta_relacionada_c',
                'nc.cli_potencial_relacionado_c',
            ])
            ->get();
    }

    /**
     * Usuarios CRM con al menos una nota asignada (o activos con notas históricas).
     *
     * @return Collection<int, object{id:string,user_name:string,first_name:string,last_name:string,notas:int}>
     */
    public function listarVendedoresConNotas(): Collection
    {
        return $this->config->connection()
            ->table('users as u')
            ->join('notes as n', function ($join) {
                $join->on('n.assigned_user_id', '=', 'u.id')
                    ->where('n.deleted', '=', 0);
            })
            ->where('u.deleted', 0)
            ->groupBy('u.id', 'u.user_name', 'u.first_name', 'u.last_name')
            ->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->selectRaw('u.id, u.user_name, u.first_name, u.last_name, count(n.id) as notas')
            ->get();
    }
}
