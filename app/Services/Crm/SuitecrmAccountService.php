<?php

namespace App\Services\Crm;

use App\Models\Ventas\Cliente;
use App\Repositories\Crm\SuitecrmAccountRepository;
use Illuminate\Support\Str;

class SuitecrmAccountService
{
    public function __construct(
        private readonly SuitecrmConfigService $config,
        private readonly SuitecrmAccountRepository $repository
    ) {}

    public function isHabilitado(): bool
    {
        return $this->config->isHabilitado();
    }

    /**
     * @return array{ok: bool, enlazada: bool, cuentas: array, mensaje?: string}
     */
    public function estadoParaCliente(Cliente $cliente): array
    {
        $codigo = trim((string) $cliente->codigo);
        $cuit = trim((string) $cliente->numerodocumento);

        if ($codigo === '' || $cuit === '') {
            return [
                'ok' => false,
                'enlazada' => false,
                'cuentas' => [],
                'mensaje' => 'El cliente debe tener código y CUIT/documento para enlazar con SuiteCRM.',
            ];
        }

        $ids = $this->repository->findAccountIdsByCodigoCuit($codigo, $cuit);
        $cuentas = $this->repository->listCuentasByIds($ids)
            ->map(fn ($row) => $this->mapCuenta($row))
            ->values()
            ->all();

        return [
            'ok' => true,
            'enlazada' => $cuentas !== [],
            'cuentas' => $cuentas,
            'mensaje' => $cuentas === []
                ? 'No existe cuenta en SuiteCRM para este código y CUIT. Podés crearla con «Sincronizar cuenta».'
                : null,
        ];
    }

    /**
     * Crea o actualiza account + accounts_cstm según datos del cliente Anita.
     *
     * @return array{ok: bool, accion?: string, account_id?: string, cuentas?: array, mensaje?: string}
     */
    public function sincronizar(Cliente $cliente): array
    {
        $cliente->loadMissing(['localidades', 'provincias', 'paises']);

        $codigo = trim((string) $cliente->codigo);
        $cuit = trim((string) $cliente->numerodocumento);

        if ($codigo === '' || $cuit === '') {
            return [
                'ok' => false,
                'mensaje' => 'Complete código y CUIT/documento antes de sincronizar con SuiteCRM.',
            ];
        }

        $accountIds = $this->repository->findAccountIdsByCodigoCuit($codigo, $cuit);
        $userId = $this->config->defaultUserId();
        $now = now()->format('Y-m-d H:i:s');
        [$accountRow, $cstmRow] = $this->buildRows($cliente, $userId, $now);

        if ($accountIds === []) {
            $accountId = (string) Str::uuid();
            $accountRow['id'] = $accountId;
            $accountRow['date_entered'] = $now;
            $accountRow['created_by'] = $userId;
            $cstmRow['id_c'] = $accountId;

            $this->repository->insertAccount($accountRow, $cstmRow);

            return [
                'ok' => true,
                'accion' => 'creada',
                'account_id' => $accountId,
                'mensaje' => 'Cuenta creada en SuiteCRM.',
                'cuentas' => [$this->mapCuenta((object) array_merge($accountRow, $cstmRow))],
            ];
        }

        foreach ($accountIds as $accountId) {
            $this->repository->updateAccount($accountId, $accountRow, $cstmRow);
        }

        $cuentas = $this->repository->listCuentasByIds($accountIds)
            ->map(fn ($row) => $this->mapCuenta($row))
            ->values()
            ->all();

        $mensaje = count($accountIds) === 1
            ? 'Cuenta actualizada en SuiteCRM.'
            : count($accountIds).' cuentas actualizadas en SuiteCRM.';

        return [
            'ok' => true,
            'accion' => 'actualizada',
            'account_id' => $accountIds[0],
            'mensaje' => $mensaje,
            'cuentas' => $cuentas,
        ];
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function buildRows(Cliente $cliente, string $userId, string $now): array
    {
        $nombre = mb_substr(trim((string) $cliente->nombre), 0, 150);
        $domicilio = mb_substr(trim((string) $cliente->domicilio), 0, 150);
        $localidad = mb_substr((string) ($cliente->localidades->nombre ?? ''), 0, 100);
        $provincia = mb_substr((string) ($cliente->provincias->nombre ?? ''), 0, 100);
        $pais = mb_substr((string) ($cliente->paises->nombre ?? ''), 0, 255);
        $telefono = mb_substr(trim((string) $cliente->telefono), 0, 100);
        $website = mb_substr(trim((string) $cliente->urlweb), 0, 255);
        $leyenda = trim((string) $cliente->leyenda);
        $contacto = mb_substr(trim((string) $cliente->contacto), 0, 255);

        $account = [
            'name' => $nombre !== '' ? $nombre : 'Sin nombre',
            'date_modified' => $now,
            'modified_user_id' => $userId,
            'deleted' => 0,
            'phone_office' => $telefono !== '' ? $telefono : null,
            'website' => $website !== '' ? $website : null,
            'billing_address_street' => $domicilio !== '' ? $domicilio : null,
            'billing_address_city' => $localidad !== '' ? $localidad : null,
            'billing_address_state' => $provincia !== '' ? $provincia : null,
            'billing_address_postalcode' => $cliente->codigopostal ? mb_substr((string) $cliente->codigopostal, 0, 20) : null,
            'billing_address_country' => $pais !== '' ? $pais : null,
            'description' => $leyenda !== '' ? $leyenda : null,
            'assigned_user_id' => $userId,
        ];

        $cstm = [
            'codigo_c' => trim((string) $cliente->codigo),
            'cuit_c' => trim((string) $cliente->numerodocumento),
            'notas_especiales_c' => $contacto !== '' ? $contacto : null,
        ];

        return [$account, $cstm];
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    private function mapCuenta(object $row): array
    {
        return [
            'id' => $row->id ?? null,
            'name' => $row->name ?? '',
            'phone_office' => $row->phone_office ?? '',
            'website' => $row->website ?? '',
            'billing_address_street' => $row->billing_address_street ?? '',
            'billing_address_city' => $row->billing_address_city ?? '',
            'billing_address_state' => $row->billing_address_state ?? '',
            'billing_address_postalcode' => $row->billing_address_postalcode ?? '',
            'description' => $row->description ?? '',
            'codigo_c' => $row->codigo_c ?? '',
            'cuit_c' => $row->cuit_c ?? '',
            'notas_especiales_c' => $row->notas_especiales_c ?? '',
            'date_entered' => $row->date_entered ?? null,
            'date_modified' => $row->date_modified ?? null,
        ];
    }
}
