<?php

namespace App\Repositories\Crm;

use App\Services\Crm\SuitecrmConfigService;
use Illuminate\Support\Collection;

class SuitecrmNotaRepository
{
    public function __construct(
        private readonly SuitecrmConfigService $config,
        private readonly SuitecrmAccountRepository $accountRepository
    ) {}

    /**
     * @return array<int, string>
     */
    public function findAccountIdsByCodigoCuit(string $codigo, string $cuit): array
    {
        return $this->accountRepository->findAccountIdsByCodigoCuit($codigo, $cuit);
    }

    /**
     * @param  array<int, string>  $accountIds
     * @param  array<int, string>  $excluirCreatedByUserIds  Ocultar notas creadas por estos usuarios SuiteCRM (rol Supervisor).
     */
    public function listByAccountIds(array $accountIds, array $excluirCreatedByUserIds = []): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        $query = $this->config->connection()
            ->table('notes as n')
            ->join('accounts as a', function ($join) {
                $join->on('n.parent_id', '=', 'a.id')
                    ->where('n.parent_type', '=', 'Accounts');
            })
            ->whereIn('n.parent_id', $accountIds)
            ->where('n.deleted', 0)
            ->where('a.deleted', 0);

        if ($excluirCreatedByUserIds !== []) {
            $query->where(function ($q) use ($excluirCreatedByUserIds) {
                $q->whereNull('n.created_by')
                    ->orWhereNotIn('n.created_by', $excluirCreatedByUserIds);
            });
        }

        return $query
            ->orderByDesc('n.date_entered')
            ->select([
                'n.id',
                'n.name',
                'n.description',
                'n.date_entered',
                'n.date_modified',
                'n.parent_id',
                'n.created_by',
                'n.modified_user_id',
            ])
            ->get();
    }

    public function findById(string $notaId): ?object
    {
        return $this->config->connection()
            ->table('notes')
            ->where('id', $notaId)
            ->where('deleted', 0)
            ->first();
    }

    public function insert(array $data): void
    {
        $this->config->connection()->table('notes')->insert($data);
    }

    public function update(string $notaId, array $data): int
    {
        return $this->config->connection()
            ->table('notes')
            ->where('id', $notaId)
            ->where('deleted', 0)
            ->update($data);
    }

    public function softDelete(string $notaId, string $userId): int
    {
        $now = now()->format('Y-m-d H:i:s');

        return $this->config->connection()
            ->table('notes')
            ->where('id', $notaId)
            ->where('deleted', 0)
            ->update([
                'deleted' => 1,
                'date_modified' => $now,
                'modified_user_id' => $userId,
            ]);
    }
}
