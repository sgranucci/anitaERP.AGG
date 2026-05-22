<?php

namespace App\Services\Crm;

use App\Models\Ventas\Cliente;
use App\Repositories\Crm\SuitecrmAccountRepository;
use App\Repositories\Crm\SuitecrmNotaRepository;
use Illuminate\Support\Str;

class SuitecrmNotaService
{
    public function __construct(
        private readonly SuitecrmConfigService $config,
        private readonly SuitecrmNotaRepository $repository,
        private readonly SuitecrmAccountRepository $accountRepository,
        private readonly SuitecrmNotaVisibilidadService $visibilidad
    ) {}

    public function isHabilitado(): bool
    {
        return $this->config->isHabilitado();
    }

    /**
     * @return array{enlazada: bool, account_ids: array<int, string>, mensaje?: string}
     */
    public function resolverCuenta(Cliente $cliente): array
    {
        $codigo = trim((string) $cliente->codigo);
        $cuit = trim((string) $cliente->numerodocumento);

        if ($codigo === '' || $cuit === '') {
            return [
                'enlazada' => false,
                'account_ids' => [],
                'mensaje' => 'El cliente debe tener código y CUIT/documento para enlazar con SuiteCRM.',
            ];
        }

        $accountIds = $this->accountRepository->findAccountIdsByCodigoCuit($codigo, $cuit);

        if ($accountIds === []) {
            return [
                'enlazada' => false,
                'account_ids' => [],
                'mensaje' => "No se encontró cuenta en SuiteCRM para código {$codigo} y CUIT {$cuit}.",
            ];
        }

        return [
            'enlazada' => true,
            'account_ids' => $accountIds,
        ];
    }

    /**
     * @return array{ok: bool, cuenta: array, notas: array<int, array>, mensaje?: string}
     */
    public function listarParaCliente(Cliente $cliente): array
    {
        $cuenta = $this->resolverCuenta($cliente);
        if (! $cuenta['enlazada']) {
            return [
                'ok' => false,
                'cuenta' => $cuenta,
                'notas' => [],
                'mensaje' => $cuenta['mensaje'] ?? 'Cuenta no enlazada.',
            ];
        }

        $excluirSupervisor = $this->visibilidad->puedeVerNotasSupervisor()
            ? []
            : $this->visibilidad->userIdsSupervisorSuitecrm();

        $notas = $this->repository
            ->listByAccountIds($cuenta['account_ids'], $excluirSupervisor)
            ->map(fn ($n) => $this->mapNota($n))
            ->values()
            ->all();

        return [
            'ok' => true,
            'cuenta' => $cuenta,
            'notas' => $notas,
        ];
    }

    /**
     * @return array{ok: bool, nota?: array, mensaje?: string}
     */
    public function crear(Cliente $cliente, string $nombre, string $descripcion): array
    {
        $cuenta = $this->resolverCuenta($cliente);
        if (! $cuenta['enlazada']) {
            return ['ok' => false, 'mensaje' => $cuenta['mensaje'] ?? 'Cuenta no enlazada.'];
        }

        $parentId = $cuenta['account_ids'][0];
        $userId = $this->config->defaultUserId();
        $now = now()->format('Y-m-d H:i:s');
        $id = (string) Str::uuid();

        $this->repository->insert([
            'id' => $id,
            'name' => $nombre,
            'description' => $descripcion,
            'parent_type' => 'Accounts',
            'parent_id' => $parentId,
            'date_entered' => $now,
            'date_modified' => $now,
            'created_by' => $userId,
            'modified_user_id' => $userId,
            'deleted' => 0,
            'embed_flag' => 0,
        ]);

        $row = $this->repository->findById($id);

        return [
            'ok' => true,
            'nota' => $row ? $this->mapNota($row) : null,
        ];
    }

    /**
     * @return array{ok: bool, nota?: array, mensaje?: string}
     */
    public function actualizar(Cliente $cliente, string $notaId, string $nombre, string $descripcion): array
    {
        $cuenta = $this->resolverCuenta($cliente);
        if (! $cuenta['enlazada']) {
            return ['ok' => false, 'mensaje' => $cuenta['mensaje'] ?? 'Cuenta no enlazada.'];
        }

        $nota = $this->repository->findById($notaId);
        if (! $nota || ! in_array($nota->parent_id, $cuenta['account_ids'], true)) {
            return ['ok' => false, 'mensaje' => 'La nota no pertenece a la cuenta CRM de este cliente.'];
        }
        if (! $this->visibilidad->puedeVerNota($nota)) {
            return ['ok' => false, 'mensaje' => $this->visibilidad->mensajeSinPermiso()];
        }

        $userId = $this->config->defaultUserId();
        $now = now()->format('Y-m-d H:i:s');

        $this->repository->update($notaId, [
            'name' => $nombre,
            'description' => $descripcion,
            'date_modified' => $now,
            'modified_user_id' => $userId,
        ]);

        $row = $this->repository->findById($notaId);

        return [
            'ok' => true,
            'nota' => $row ? $this->mapNota($row) : null,
        ];
    }

    /**
     * @return array{ok: bool, mensaje?: string}
     */
    public function eliminar(Cliente $cliente, string $notaId): array
    {
        $cuenta = $this->resolverCuenta($cliente);
        if (! $cuenta['enlazada']) {
            return ['ok' => false, 'mensaje' => $cuenta['mensaje'] ?? 'Cuenta no enlazada.'];
        }

        $nota = $this->repository->findById($notaId);
        if (! $nota || ! in_array($nota->parent_id, $cuenta['account_ids'], true)) {
            return ['ok' => false, 'mensaje' => 'La nota no pertenece a la cuenta CRM de este cliente.'];
        }
        if (! $this->visibilidad->puedeVerNota($nota)) {
            return ['ok' => false, 'mensaje' => $this->visibilidad->mensajeSinPermiso()];
        }

        $this->repository->softDelete($notaId, $this->config->defaultUserId());

        return ['ok' => true];
    }

    /**
     * @param  object  $row
     * @return array<string, mixed>
     */
    private function mapNota(object $row): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name ?? '',
            'description' => $row->description ?? '',
            'date_entered' => $row->date_entered ?? null,
            'date_modified' => $row->date_modified ?? null,
            'parent_id' => $row->parent_id ?? null,
        ];
    }
}
