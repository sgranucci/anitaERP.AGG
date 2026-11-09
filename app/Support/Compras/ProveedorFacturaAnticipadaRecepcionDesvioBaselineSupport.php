<?php

declare(strict_types=1);

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historia diaria de desvíos FA vs recepción por OC.
 * Alerta solo si aparece un desvío nuevo o cambia el exceso respecto de la base.
 * Sin base anterior, la corrida queda como medición inicial (sin alerta).
 */
final class ProveedorFacturaAnticipadaRecepcionDesvioBaselineSupport
{
    private const TABLA = 'proveedor_factura_anticipada_recepcion_desvio';

    /**
     * @param  list<array<string, mixed>>  $desvios
     * @return array{
     *   fecha: string,
     *   fecha_anterior: ?string,
     *   fecha_anterior_fmt: ?string,
     *   hay_base_anterior: bool,
     *   desvios_nuevos: list<array<string, mixed>>,
     *   desvios_informados: list<array<string, mixed>>,
     *   desvios_nuevos_total: int,
     *   desvios_informados_total: int,
     *   requiere_alerta: bool
     * }
     */
    public function clasificarYGuardar(
        int $empresaId,
        string $fecha,
        array $desvios,
        float $tolerancia,
    ): array {
        $fecha = Carbon::parse($fecha)->toDateString();
        $tolerancia = abs($tolerancia);
        $fechaAnterior = $this->fechaAnterior($empresaId, $fecha);
        $anterior = $fechaAnterior === null
            ? []
            : $this->filasGuardadas($empresaId, $fechaAnterior);

        $nuevos = [];
        $informados = [];

        if ($fechaAnterior === null) {
            // Primera medición: se acepta como base, sin alerta.
            foreach ($desvios as $fila) {
                $informados[] = $this->enriquecer($fila, null, true);
            }
        } else {
            foreach ($desvios as $fila) {
                $ocId = (int) ($fila['ordencompra_id'] ?? 0);
                $exceso = round((float) ($fila['exceso_recepcion'] ?? 0), 2);
                $tenia = array_key_exists($ocId, $anterior);
                $excesoAnterior = $tenia ? round((float) $anterior[$ocId], 2) : null;
                $variacion = $excesoAnterior === null
                    ? null
                    : round($exceso - $excesoAnterior, 2);
                $esNuevo = ! $tenia || ($variacion !== null && abs($variacion) >= $tolerancia);
                $filaEnriquecida = $this->enriquecer($fila, $excesoAnterior, ! $esNuevo);
                if ($esNuevo) {
                    $nuevos[] = $filaEnriquecida;
                } else {
                    $informados[] = $filaEnriquecida;
                }
            }
        }

        usort($nuevos, static fn (array $a, array $b): int => $b['exceso_recepcion'] <=> $a['exceso_recepcion']);
        usort($informados, static fn (array $a, array $b): int => $b['exceso_recepcion'] <=> $a['exceso_recepcion']);

        $this->guardar($empresaId, $fecha, $desvios);

        return [
            'fecha' => $fecha,
            'fecha_anterior' => $fechaAnterior,
            'fecha_anterior_fmt' => $fechaAnterior === null
                ? null
                : Carbon::parse($fechaAnterior)->format('d/m/Y'),
            'hay_base_anterior' => $fechaAnterior !== null,
            'desvios_nuevos' => $nuevos,
            'desvios_informados' => $informados,
            'desvios_nuevos_total' => count($nuevos),
            'desvios_informados_total' => count($informados),
            'requiere_alerta' => $nuevos !== [],
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function enriquecer(array $fila, ?float $excesoAnterior, bool $yaInformado): array
    {
        $exceso = round((float) ($fila['exceso_recepcion'] ?? 0), 2);
        $fila['exceso_anterior'] = $excesoAnterior;
        $fila['variacion_exceso'] = $excesoAnterior === null
            ? null
            : round($exceso - $excesoAnterior, 2);
        $fila['ya_informado'] = $yaInformado;

        return $fila;
    }

    private function fechaAnterior(int $empresaId, string $fecha): ?string
    {
        if (! $this->tablaExiste()) {
            return null;
        }

        $valor = DB::table(self::TABLA)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '<', $fecha)
            ->max('fecha');
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse((string) $valor)->toDateString();
    }

    /**
     * @return array<int, float> ordencompra_id => exceso_recepcion
     */
    private function filasGuardadas(int $empresaId, string $fecha): array
    {
        if (! $this->tablaExiste()) {
            return [];
        }

        $filas = DB::table(self::TABLA)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha)
            ->get(['ordencompra_id', 'exceso_recepcion']);
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->ordencompra_id] = (float) $fila->exceso_recepcion;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $desvios
     */
    private function guardar(int $empresaId, string $fecha, array $desvios): void
    {
        if (! $this->tablaExiste()) {
            return;
        }

        $filas = [];
        $ahora = now();
        foreach ($desvios as $fila) {
            $ocId = (int) ($fila['ordencompra_id'] ?? 0);
            if ($ocId <= 0) {
                continue;
            }
            $filas[] = [
                'fecha' => $fecha,
                'empresa_id' => $empresaId,
                'ordencompra_id' => $ocId,
                'cuentacontable_id' => ((int) ($fila['cuentacontable_id'] ?? 0)) ?: null,
                'numeroordencompra' => trim((string) ($fila['numeroordencompra'] ?? '')),
                'factura' => round((float) ($fila['factura'] ?? 0), 2),
                'recepcion' => round((float) ($fila['recepcion'] ?? 0), 2),
                'exceso_recepcion' => round((float) ($fila['exceso_recepcion'] ?? 0), 2),
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::transaction(function () use ($empresaId, $fecha, $filas) {
            $ids = array_map(static fn (array $fila) => (int) $fila['ordencompra_id'], $filas);
            $borrar = DB::table(self::TABLA)
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $fecha);
            if ($ids !== []) {
                $borrar->whereNotIn('ordencompra_id', $ids);
            }
            $borrar->delete();

            if ($filas === []) {
                return;
            }

            DB::table(self::TABLA)->upsert(
                $filas,
                ['fecha', 'empresa_id', 'ordencompra_id'],
                [
                    'cuentacontable_id',
                    'numeroordencompra',
                    'factura',
                    'recepcion',
                    'exceso_recepcion',
                    'updated_at',
                ]
            );
        });
    }

    private function tablaExiste(): bool
    {
        return Schema::hasTable(self::TABLA);
    }
}
