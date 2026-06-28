<?php

namespace App\Services\Contable;

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alta del catálogo contabilidad_cuenta_automatica al activar una empresa (asignación usuario_empresa).
 * No sobrescribe cuentas ya configuradas manualmente.
 */
class ContabilidadCuentaAutomaticaSeedService
{
    /** @var array<string, string> clave => código contable sugerido por empresa */
    private const CODIGO_SUGERIDO_POR_CLAVE = [
        CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS => '211010013',
        CuentaAutomaticaClaves::CAJA_VALORES_A_DEPOSITAR => '111040000',
    ];

    /**
     * @param  list<int>  $empresaIds
     */
    public function asegurarCatalogoEmpresas(array $empresaIds): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }
            $this->asegurarCatalogoEmpresa($empresaId);
        }
    }

    public function asegurarCatalogoEmpresa(int $empresaId): void
    {
        if ($empresaId <= 0 || ! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
            $existente = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', $clave)
                ->first();

            if ($existente === null) {
                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $this->resolverCuentaInicial($empresaId, $clave, $meta),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            if ($existente->cuentacontable_id !== null && (int) $existente->cuentacontable_id > 0) {
                continue;
            }

            $cuentaId = $this->resolverCuentaInicial($empresaId, $clave, $meta);
            if ($cuentaId === null) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica')
                ->where('id', $existente->id)
                ->update([
                    'cuentacontable_id' => $cuentaId,
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Empresas con al menos un usuario asignado (operativas en AGG).
     *
     * @return list<int>
     */
    public function empresaIdsConUsuariosAsignados(): array
    {
        if (! Schema::hasTable('usuario_empresa')) {
            return [];
        }

        return DB::table('usuario_empresa')
            ->distinct()
            ->orderBy('empresa_id')
            ->pluck('empresa_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    public function asegurarCatalogoEmpresasConUsuariosAsignados(): int
    {
        $ids = $this->empresaIdsConUsuariosAsignados();
        $this->asegurarCatalogoEmpresas($ids);

        return count($ids);
    }

    /**
     * @param  array{modulo_tabla: ?string, modulo_columna: ?string, env_config: ?string}  $meta
     */
    private function resolverCuentaInicial(int $empresaId, string $clave, array $meta): ?int
    {
        if ($meta['modulo_tabla'] !== null
            && $meta['modulo_columna'] !== null
            && Schema::hasTable($meta['modulo_tabla'])
            && Schema::hasColumn($meta['modulo_tabla'], $meta['modulo_columna'])) {
            $moduloValor = DB::table($meta['modulo_tabla'])
                ->where('empresa_id', $empresaId)
                ->value($meta['modulo_columna']);
            $id = $this->intOrNull($moduloValor);
            if ($id !== null) {
                return $id;
            }
        }

        if ($meta['env_config'] !== null && $meta['env_config'] !== '') {
            $id = $this->intOrNull(config($meta['env_config']));
            if ($id !== null) {
                return $this->cuentaPerteneceEmpresa($id, $empresaId) ? $id : null;
            }
        }

        if ($clave === CuentaAutomaticaClaves::CAJA_VALORES_A_DEPOSITAR) {
            $mapaLegacy = config('cobranza.VALORES_A_DEPOSITAR');
            if (is_array($mapaLegacy) && isset($mapaLegacy[(string) $empresaId])) {
                $id = $this->resolverCuentaPorCodigo($empresaId, (string) $mapaLegacy[(string) $empresaId]);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        $codigo = $this->codigoSugeridoParaClave($clave);
        if ($codigo !== null) {
            return $this->resolverCuentaPorCodigo($empresaId, $codigo);
        }

        return null;
    }

    private function codigoSugeridoParaClave(string $clave): ?string
    {
        if (isset(self::CODIGO_SUGERIDO_POR_CLAVE[$clave])) {
            return self::CODIGO_SUGERIDO_POR_CLAVE[$clave];
        }

        return match ($clave) {
            CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS => (string) config('caja.cheques_diferidos_cuenta_codigo', '211010013'),
            CuentaAutomaticaClaves::CAJA_VALORES_A_DEPOSITAR => (string) config('caja.valores_a_depositar_cuenta_codigo', '111040000'),
            default => null,
        };
    }

    private function resolverCuentaPorCodigo(int $empresaId, string $codigo): ?int
    {
        $id = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');

        return $this->intOrNull($id);
    }

    private function cuentaPerteneceEmpresa(int $cuentacontableId, int $empresaId): bool
    {
        return DB::table('cuentacontable')
            ->where('id', $cuentacontableId)
            ->where('empresa_id', $empresaId)
            ->exists();
    }

    private function intOrNull(mixed $valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
