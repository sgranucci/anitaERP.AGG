<?php

namespace App\Repositories\Crm;

use App\Services\Crm\SuitecrmConfigService;
use Illuminate\Support\Collection;

class SuitecrmAccountRepository
{
    public function __construct(
        private readonly SuitecrmConfigService $config
    ) {}

    /**
     * @return array<int, string>
     */
    public function findAccountIdsByCodigoCuit(string $codigo, string $cuit): array
    {
        $rows = $this->config->connection()
            ->table('accounts as a')
            ->join('accounts_cstm as ac', 'a.id', '=', 'ac.id_c')
            ->where('a.deleted', 0)
            ->where('ac.codigo_c', $codigo)
            ->where('ac.cuit_c', $cuit)
            ->pluck('a.id');

        return $rows->unique()->values()->all();
    }

    public function findCuentaByCodigoCuit(string $codigo, string $cuit): ?object
    {
        return $this->config->connection()
            ->table('accounts as a')
            ->join('accounts_cstm as ac', 'a.id', '=', 'ac.id_c')
            ->where('a.deleted', 0)
            ->where('ac.codigo_c', $codigo)
            ->where('ac.cuit_c', $cuit)
            ->orderByDesc('a.date_modified')
            ->select([
                'a.id',
                'a.name',
                'a.phone_office',
                'a.website',
                'a.billing_address_street',
                'a.billing_address_city',
                'a.billing_address_state',
                'a.billing_address_postalcode',
                'a.description',
                'a.date_entered',
                'a.date_modified',
                'ac.codigo_c',
                'ac.cuit_c',
                'ac.notas_especiales_c',
            ])
            ->first();
    }

    /**
     * @param  array<int, string>  $accountIds
     */
    public function listCuentasByIds(array $accountIds): Collection
    {
        if ($accountIds === []) {
            return collect();
        }

        return $this->config->connection()
            ->table('accounts as a')
            ->join('accounts_cstm as ac', 'a.id', '=', 'ac.id_c')
            ->whereIn('a.id', $accountIds)
            ->where('a.deleted', 0)
            ->orderByDesc('a.date_modified')
            ->select([
                'a.id',
                'a.name',
                'a.phone_office',
                'a.website',
                'a.billing_address_street',
                'a.billing_address_city',
                'a.billing_address_state',
                'a.billing_address_postalcode',
                'a.description',
                'a.date_entered',
                'a.date_modified',
                'ac.codigo_c',
                'ac.cuit_c',
                'ac.notas_especiales_c',
            ])
            ->get();
    }

    public function insertAccount(array $accountData, array $cstmData): string
    {
        $conn = $this->config->connection();

        return $conn->transaction(function () use ($conn, $accountData, $cstmData) {
            $conn->table('accounts')->insert($accountData);
            $conn->table('accounts_cstm')->insert($cstmData);

            return $accountData['id'];
        });
    }

    public function updateAccount(string $accountId, array $accountData, array $cstmData): void
    {
        $conn = $this->config->connection();

        $conn->transaction(function () use ($conn, $accountId, $accountData, $cstmData) {
            $conn->table('accounts')
                ->where('id', $accountId)
                ->where('deleted', 0)
                ->update($accountData);

            $exists = $conn->table('accounts_cstm')->where('id_c', $accountId)->exists();
            if ($exists) {
                $conn->table('accounts_cstm')->where('id_c', $accountId)->update($cstmData);
            } else {
                $cstmData['id_c'] = $accountId;
                $conn->table('accounts_cstm')->insert($cstmData);
            }
        });
    }
}
