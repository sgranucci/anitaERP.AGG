<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonEntidades;

use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta pasivo 215010-003 (contribución hospital / entidad de bien público).
 */
final class CanonEntidadesCuentaSupport
{
    /**
     * @return array<string, mixed>
     */
    public static function resolver(int $empresaId): array
    {
        $ids = [];
        foreach ([
            CuentaAutomaticaClaves::CIERRE_MAQUINA_CONT_CANON_HOSPITAL,
            CuentaAutomaticaClaves::CIERRE_BINGO_CONT_HOSPITAL,
        ] as $clave) {
            $id = CuentaAutomaticaResolver::resolverId($empresaId, $clave);
            if ($id !== null && $id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));
        $porCodigo = self::buscarPorCodigo($empresaId);
        foreach ($porCodigo['ids'] as $id) {
            if (! in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        $detalle = self::detalleCuentas($ids);
        if ($detalle === [] && $porCodigo['detalle'] !== []) {
            $detalle = $porCodigo['detalle'];
            $ids = $porCodigo['ids'];
        }

        $codigosAnita = [];
        foreach ($detalle as $cuenta) {
            $codigo = (int) preg_replace('/\D/', '', (string) ($cuenta['codigo'] ?? ''));
            if ($codigo > 0) {
                $codigosAnita[] = $codigo;
            }
        }
        if ($codigosAnita === []) {
            $codigosAnita[] = (int) CanonEntidadesReglasSupport::CUENTA_CODIGO;
        }

        return [
            'ids' => $ids,
            'codigos_anita' => array_values(array_unique($codigosAnita)),
            'cuentas' => $detalle !== [] ? $detalle : [[
                'id' => 0,
                'codigo' => CanonEntidadesReglasSupport::CUENTA_ETIQUETA,
                'nombre' => 'Canon entidad de bien público',
                'tipocuenta' => '2',
            ]],
        ];
    }

    /**
     * @return array{ids: list<int>, detalle: list<array<string, mixed>>}
     */
    private static function buscarPorCodigo(int $empresaId): array
    {
        if ($empresaId <= 0 || ! Schema::hasTable('cuentacontable')) {
            return ['ids' => [], 'detalle' => []];
        }

        $filas = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where(function ($q) {
                $q->where('codigo', CanonEntidadesReglasSupport::CUENTA_CODIGO)
                    ->orWhere('codigo', CanonEntidadesReglasSupport::CUENTA_ETIQUETA)
                    ->orWhereRaw("REPLACE(codigo, '-', '') = ?", [CanonEntidadesReglasSupport::CUENTA_CODIGO]);
            })
            ->get(['id', 'codigo', 'nombre', 'tipocuenta']);

        $ids = [];
        $detalle = [];
        foreach ($filas as $fila) {
            $id = (int) $fila->id;
            if ($id <= 0) {
                continue;
            }
            $ids[] = $id;
            $detalle[] = [
                'id' => $id,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'tipocuenta' => $fila->tipocuenta,
            ];
        }

        return ['ids' => $ids, 'detalle' => $detalle];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private static function detalleCuentas(array $ids): array
    {
        if ($ids === [] || ! Schema::hasTable('cuentacontable')) {
            return [];
        }

        return DB::table('cuentacontable')
            ->whereIn('id', $ids)
            ->get(['id', 'codigo', 'nombre', 'tipocuenta'])
            ->map(static fn ($fila) => [
                'id' => (int) $fila->id,
                'codigo' => (string) $fila->codigo,
                'nombre' => (string) $fila->nombre,
                'tipocuenta' => $fila->tipocuenta,
            ])
            ->values()
            ->all();
    }
}
