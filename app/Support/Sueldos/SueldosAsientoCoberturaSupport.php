<?php

namespace App\Support\Sueldos;

use App\Models\Contable\Cuentacontable;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Liquidacion_Detalle_Sueldos;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use Illuminate\Support\Facades\Schema;

/**
 * Informe de cobertura del mapeo (fase 0): patas fijas, fallback por tipo
 * y conceptos usados en corridas cerradas sin cuenta.
 */
final class SueldosAsientoCoberturaSupport
{
    /**
     * @return array{
     *   empresa_id: int,
     *   patas_fijas: list<array<string, mixed>>,
     *   fallback_tipo: list<array<string, mixed>>,
     *   conceptos_sin_cuenta: list<array<string, mixed>>,
     *   ok_minimo: bool,
     *   cantidad_faltantes: int
     * }
     */
    public static function informe(int $empresaId): array
    {
        $patas = [];
        $catalogo = CuentaAutomaticaClaves::catalogo();
        foreach (SueldosAsientoMapeoSupport::clavesAutomaticasFase0() as $clave) {
            $cuentaId = CuentaAutomaticaResolver::resolverId($empresaId, $clave);
            $cuenta = self::cuentaResumen($cuentaId);
            $ok = $cuenta !== null;
            $patas[] = [
                'clave' => $clave,
                'descripcion' => $catalogo[$clave]['descripcion'] ?? $clave,
                'cuenta_id' => $cuentaId,
                'codigo' => $cuenta['codigo'] ?? null,
                'nombre' => $cuenta['nombre'] ?? null,
                'ok' => $ok,
            ];
        }

        $tipos = [];
        foreach (SueldosAsientoMapeoSupport::tiposImputables() as $tipo) {
            $virtual = new Concepto_Sueldos([
                'tipo' => $tipo,
                'rubro_costo_laboral' => null,
            ]);
            $resuelto = SueldosAsientoMapeoSupport::resolver($empresaId, $virtual);
            $ok = SueldosAsientoMapeoSupport::estaResuelto($resuelto, $tipo);
            $debe = self::cuentaResumen($resuelto['cuenta_debe_id'] ?? null);
            $haber = self::cuentaResumen($resuelto['cuenta_haber_id'] ?? null);
            $tipos[] = [
                'tipo' => $tipo,
                'label' => ConceptoTipo::etiquetaTipo($tipo),
                'origen' => $resuelto['origen'] ?? null,
                'debe_codigo' => $debe['codigo'] ?? null,
                'haber_codigo' => $haber['codigo'] ?? null,
                'ok' => $ok,
            ];
        }

        $sinCuenta = self::conceptosCerradosSinCuenta($empresaId);

        $faltantes = 0;
        foreach ($patas as $p) {
            if (! $p['ok']) {
                $faltantes++;
            }
        }
        foreach ($tipos as $t) {
            if (! $t['ok']) {
                $faltantes++;
            }
        }
        $faltantes += count($sinCuenta);

        $okMinimo = true;
        foreach ($patas as $p) {
            if (! $p['ok']) {
                $okMinimo = false;
                break;
            }
        }
        if ($okMinimo) {
            foreach ($tipos as $t) {
                if (! $t['ok']) {
                    $okMinimo = false;
                    break;
                }
            }
        }

        return [
            'empresa_id' => $empresaId,
            'patas_fijas' => $patas,
            'fallback_tipo' => $tipos,
            'conceptos_sin_cuenta' => $sinCuenta,
            'ok_minimo' => $okMinimo,
            'cantidad_faltantes' => $faltantes,
        ];
    }

    /**
     * @return list<array{concepto_id: int, codigo: int, descripcion: string, tipo: string, origen: ?string}>
     */
    private static function conceptosCerradosSinCuenta(int $empresaId): array
    {
        if (! Schema::hasTable('liquidacion_detalle_sueldos') || ! Schema::hasTable('liquidacion_sueldos')) {
            return [];
        }

        $ids = Liquidacion_Detalle_Sueldos::query()
            ->select('liquidacion_detalle_sueldos.concepto_id')
            ->join('liquidacion_sueldos', 'liquidacion_sueldos.id', '=', 'liquidacion_detalle_sueldos.liquidacion_id')
            ->where('liquidacion_sueldos.empresa_id', $empresaId)
            ->whereIn('liquidacion_sueldos.estado', ['cerrada', 'contabilizada', 'pagada'])
            ->whereNotNull('liquidacion_detalle_sueldos.concepto_id')
            ->distinct()
            ->pluck('concepto_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $conceptos = Concepto_Sueldos::query()
            ->whereIn('id', $ids)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'descripcion', 'tipo', 'rubro_costo_laboral']);

        $out = [];
        foreach ($conceptos as $concepto) {
            $resuelto = SueldosAsientoMapeoSupport::resolver($empresaId, $concepto);
            if (SueldosAsientoMapeoSupport::estaResuelto($resuelto, $concepto->tipo)) {
                continue;
            }
            $out[] = [
                'concepto_id' => (int) $concepto->id,
                'codigo' => (int) $concepto->codigo,
                'descripcion' => (string) $concepto->descripcion,
                'tipo' => (string) $concepto->tipo,
                'origen' => $resuelto['origen'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * AS de esta corrida con override pero sin cuenta (gate de cierre).
     *
     * @param  list<array<string, mixed>>  $informeConceptos
     * @return list<array<string, mixed>>
     */
    public static function asUsadosSinCuenta(array $informeConceptos): array
    {
        $out = [];
        foreach ($informeConceptos as $fila) {
            $sinCuenta = ($fila['cuenta_debe_codigo'] ?? '') === ''
                && ($fila['cuenta_haber_codigo'] ?? '') === '';
            if (! empty($fila['en_asiento']) || ($fila['origen'] ?? '') !== 'concepto' || ! $sinCuenta) {
                continue;
            }
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @return array{id: int, codigo: string, nombre: string}|null
     */
    private static function cuentaResumen(mixed $cuentaId): ?array
    {
        $id = (int) $cuentaId;
        if ($id <= 0) {
            return null;
        }

        $cuenta = Cuentacontable::query()->whereKey($id)->first(['id', 'codigo', 'nombre']);
        if ($cuenta === null) {
            return null;
        }

        return [
            'id' => (int) $cuenta->id,
            'codigo' => (string) $cuenta->codigo,
            'nombre' => (string) $cuenta->nombre,
        ];
    }
}
