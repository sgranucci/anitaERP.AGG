<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Contable\Asiento;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Delta de deudores por ventas: mayor − deuda abierta de clientes, en pesos,
 * con el signo del mayor (negativo = débito).
 *
 * El aviso es la suma. Un cobro que acredita la cuenta y baja la factura no
 * mueve el conjunto. Entran las cuentas de rubro Activo asignadas al cliente,
 * excepto las que son medio de cobro (`cuentacaja`): esas reciben ventas de
 * clientes varios, no la CC del cliente espejo del medio (DAFITI, VISA, …).
 */
final class ClienteDeudaMayorDeltaSupport
{
    private const TABLA = 'cliente_deuda_mayor_delta';

    /** @var array<int, true>|null */
    private ?array $cuentasCliente = null;

    /**
     * Empresas con deuda abierta o mayor en una cuenta de clientes.
     *
     * @return list<int>
     */
    public function empresasConSaldo(): array
    {
        $ids = DB::table('cliente_cuentacorriente')
            ->whereNotNull('empresa_id')
            ->distinct()
            ->pluck('empresa_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $cuentas = array_keys($this->cuentasCliente());
        if ($cuentas !== []) {
            $delMayor = DB::table('asiento as a')
                ->join('asiento_movimiento as m', 'm.asiento_id', '=', 'a.id')
                ->whereIn('m.cuentacontable_id', $cuentas)
                ->whereNotNull('a.empresa_id')
                ->distinct()
                ->pluck('a.empresa_id');
            foreach ($delMayor as $id) {
                $ids[] = (int) $id;
            }
        }

        $ids = array_values(array_unique(array_filter(
            $ids,
            static fn (int $id): bool => $id > 0
        )));
        sort($ids);

        return $ids;
    }

    /**
     * @return array{
     *   fecha: string,
     *   fecha_anterior: ?string,
     *   fecha_anterior_fmt: ?string,
     *   hay_base_anterior: bool,
     *   tolerancia: float,
     *   variaciones: int,
     *   delta_clientes: float,
     *   delta_conjunto: float,
     *   delta_conjunto_anterior: ?float,
     *   variacion_conjunto: ?float,
     *   conjunto_movio: bool,
     *   cuentas_sin_base: int,
     *   cuentas: list<array<string, mixed>>,
     *   cuentas_total: int
     * }
     */
    public function controlar(int $empresaId, string $fecha, float $tolerancia): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $tolerancia = abs($tolerancia);
        $hoy = $this->saldosEmpresa($empresaId, $fecha);
        $fechaAnterior = $this->fechaAnterior($empresaId, $fecha);
        $anterior = $fechaAnterior === null ? [] : $this->filasGuardadas($empresaId, $fechaAnterior);

        $permitidas = $this->cuentasCliente();
        $ids = array_values(array_filter(
            array_unique(array_merge(array_keys($hoy), array_keys($anterior))),
            static fn ($cuentaId): bool => isset($permitidas[(int) $cuentaId])
        ));
        $nombres = $this->nombresCuenta($ids);
        $filas = [];
        $aGuardar = [];
        $variaciones = 0;

        foreach ($ids as $cuentaId) {
            $deuda = round((float) ($hoy[$cuentaId]['deuda'] ?? 0), 2);
            $mayor = round((float) ($hoy[$cuentaId]['mayor'] ?? 0), 2);
            $delta = round($mayor - $deuda, 2);
            $tenia = array_key_exists($cuentaId, $anterior);
            $deltaAnterior = $tenia ? round((float) $anterior[$cuentaId], 2) : null;
            $variacion = $deltaAnterior === null ? null : round($delta - $deltaAnterior, 2);
            $movio = $variacion !== null && abs($variacion) >= $tolerancia;
            if ($movio) {
                $variaciones++;
            }

            $nombre = $nombres[$cuentaId] ?? ['codigo' => '', 'nombre' => ''];
            $filas[] = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $nombre['codigo'],
                'nombre' => $nombre['nombre'],
                'deuda' => $deuda,
                'mayor' => $mayor,
                'delta' => $delta,
                'delta_anterior' => $deltaAnterior,
                'variacion' => $variacion,
                'movio' => $movio,
            ];

            $releva = abs($deuda) >= 0.01 || abs($mayor) >= 0.01 || abs($delta) >= 0.01 || $movio;
            if ($releva) {
                $aGuardar[] = [
                    'fecha' => $fecha,
                    'empresa_id' => $empresaId,
                    'cuentacontable_id' => $cuentaId,
                    'deuda_local' => $deuda,
                    'mayor_local' => $mayor,
                    'delta' => $delta,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        usort($filas, static function (array $a, array $b): int {
            $ma = ! empty($a['movio']);
            $mb = ! empty($b['movio']);
            if ($ma !== $mb) {
                return $mb <=> $ma;
            }

            return abs((float) $b['delta']) <=> abs((float) $a['delta']);
        });

        $conjunto = $this->deltaConjunto($filas, $fechaAnterior !== null, $tolerancia);
        $this->guardar($empresaId, $fecha, $aGuardar);

        return [
            'fecha' => $fecha,
            'fecha_anterior' => $fechaAnterior,
            'fecha_anterior_fmt' => $fechaAnterior === null ? null : Carbon::parse($fechaAnterior)->format('d/m/Y'),
            'hay_base_anterior' => $fechaAnterior !== null,
            'tolerancia' => $tolerancia,
            'variaciones' => $variaciones,
            'delta_clientes' => $conjunto['delta_conjunto'],
            'delta_conjunto' => $conjunto['delta_conjunto'],
            'delta_conjunto_anterior' => $conjunto['delta_conjunto_anterior'],
            'variacion_conjunto' => $conjunto['variacion_conjunto'],
            'conjunto_movio' => $conjunto['conjunto_movio'],
            'cuentas_sin_base' => $conjunto['cuentas_sin_base'],
            'cuentas' => $filas,
            'cuentas_total' => count($filas),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array{
     *   delta_conjunto: float,
     *   delta_conjunto_anterior: ?float,
     *   variacion_conjunto: ?float,
     *   conjunto_movio: bool,
     *   cuentas_sin_base: int
     * }
     */
    private function deltaConjunto(array $filas, bool $hayBase, float $tolerancia): array
    {
        $delta = round(array_sum(array_map(
            static fn (array $fila): float => (float) ($fila['delta'] ?? 0),
            $filas
        )), 2);
        $variacion = null;
        $anterior = null;
        $sinBase = 0;
        if ($hayBase) {
            $variacion = 0.0;
            $anterior = 0.0;
            foreach ($filas as $fila) {
                if ($fila['variacion'] === null) {
                    $sinBase++;

                    continue;
                }
                $variacion += (float) $fila['variacion'];
                $anterior += (float) $fila['delta_anterior'];
            }
            $variacion = round($variacion, 2);
            $anterior = round($anterior, 2);
        }

        return [
            'delta_conjunto' => $delta,
            'delta_conjunto_anterior' => $anterior,
            'variacion_conjunto' => $variacion,
            'conjunto_movio' => $variacion !== null && abs($variacion) >= $tolerancia,
            'cuentas_sin_base' => $sinBase,
        ];
    }

    /**
     * @return array<int, array{deuda: float, mayor: float}>
     */
    private function saldosEmpresa(int $empresaId, string $fecha): array
    {
        $cuentaIds = $this->cuentasCliente();
        $deuda = $this->deudaPorCuenta($empresaId, $fecha, $cuentaIds);
        $mayor = $this->mayorPorCuenta($empresaId, array_keys($cuentaIds));

        $out = [];
        foreach (array_unique(array_merge(array_keys($deuda), array_keys($mayor))) as $cuentaId) {
            $cuentaId = (int) $cuentaId;
            if ($cuentaId <= 0 || ! isset($cuentaIds[$cuentaId])) {
                continue;
            }
            $deudaLocal = round((float) ($deuda[$cuentaId] ?? 0), 2);
            $mayorLocal = round((float) ($mayor[$cuentaId] ?? 0), 2);
            if (abs($deudaLocal) < 0.01 && abs($mayorLocal) < 0.01) {
                continue;
            }
            $out[$cuentaId] = [
                'deuda' => $deudaLocal,
                'mayor' => $mayorLocal,
            ];
        }

        return $out;
    }

    /**
     * Cuentas de rubro Activo asignadas en el maestro de clientes.
     *
     * Se excluyen las que también son medio de cobro (`cuentacaja.cuentacontable_id`):
     * L-PAGO NUBE, L-MERCADOLIBRE, L-VISA, etc. Ahí asientan ventas de clientes
     * varios / contado; no son la cuenta corriente del cliente espejo del medio.
     *
     * @return array<int, true>
     */
    private function cuentasCliente(): array
    {
        if ($this->cuentasCliente !== null) {
            return $this->cuentasCliente;
        }

        $idsMedio = DB::table('cuentacaja')
            ->whereNotNull('cuentacontable_id')
            ->where('cuentacontable_id', '>', 0)
            ->distinct()
            ->pluck('cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->all();

        $ids = DB::table('cliente as cl')
            ->join('cuentacontable as c', 'c.id', '=', 'cl.cuentacontable_id')
            ->join('rubrocontable as r', 'r.id', '=', 'c.rubrocontable_id')
            ->whereNull('cl.deleted_at')
            ->whereRaw(SqlDialectSupport::lower('r.nombre').' = ?', ['activo'])
            ->when($idsMedio !== [], static fn ($q) => $q->whereNotIn('c.id', $idsMedio))
            ->distinct()
            ->pluck('c.id');

        $cuentas = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $cuentas[$id] = true;
            }
        }

        return $this->cuentasCliente = $cuentas;
    }

    /**
     * @param  array<int, true>  $cuentaIds
     * @return array<int, float>
     */
    private function deudaPorCuenta(int $empresaId, string $fecha, array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        $saldos = [];
        Cliente_Cuentacorriente::query()
            ->with([
                'clientes:id,cuentacontable_id',
                'ventas:id,codigo',
                'cobranzas:id,tipotransaccion_caja_id',
                'cobranzas.tipotransaccioncajas:id,abreviatura',
            ])
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', '<=', $fecha)
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->orderBy('id')
            ->chunkById(300, function ($filas) use (&$saldos, $cuentaIds, $fecha) {
                foreach ($filas as $cc) {
                    $abreviatura = strtoupper(trim((string) ($cc->cobranzas?->tipotransaccioncajas?->abreviatura ?? '')));
                    $esCoa = $abreviatura === 'COA';
                    if ((int) ($cc->cobranza_id ?? 0) > 0 && ! $esCoa) {
                        continue;
                    }
                    $codigo = $cc->ventas?->codigo;
                    if (! $esCoa && ! ClienteCuentacorrienteDeudaAlcanceSupport::esComprobanteDeuda(
                        $codigo !== null ? (string) $codigo : null,
                        null
                    )) {
                        continue;
                    }

                    $saldo = ClienteCuentacorrienteGrillaSupport::saldoPendiente(
                        (float) $cc->total,
                        (float) ($cc->aplicado ?? 0)
                    );
                    if (abs($saldo) < 0.01) {
                        continue;
                    }

                    $cuentaId = (int) ($cc->clientes?->cuentacontable_id ?? 0);
                    if ($cuentaId <= 0 || ! isset($cuentaIds[$cuentaId])) {
                        continue;
                    }

                    $local = $this->importeLocal(
                        $saldo,
                        (float) ($cc->cotizacion ?? 0),
                        (int) ($cc->moneda_id ?: 1),
                        $cc->fecha ? Carbon::parse($cc->fecha)->toDateString() : $fecha
                    );
                    if ($local === null) {
                        continue;
                    }
                    $saldos[$cuentaId] = ($saldos[$cuentaId] ?? 0) + $local;
                }
            });

        return $saldos;
    }

    /**
     * Débito (el cliente debe) queda negativo, igual que el mayor.
     * Moneda extranjera usa la cotización del documento; si viene en cero o en uno, la vigente.
     */
    private function importeLocal(float $saldo, float $cotizacion, int $monedaId, string $fecha): ?float
    {
        $monedaLocal = (int) config('cotizacion.ID_MONEDA_DEFAULT', CotizacionVigenteSupport::MONEDA_LOCAL_ID);
        $absoluto = round(abs($saldo), 2);
        if ($monedaId <= 0 || $monedaId === $monedaLocal) {
            $local = $absoluto;
        } else {
            $cot = $cotizacion > 1.0001 ? $cotizacion : 0.0;
            if ($cot <= 0) {
                $cot = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
            }
            if ($cot <= 0) {
                return null;
            }
            $local = round($absoluto * $cot, 2);
        }

        return $saldo >= 0 ? -$local : $local;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return array<int, float>
     */
    private function mayorPorCuenta(int $empresaId, array $cuentaIds): array
    {
        $cuentaIds = array_values(array_filter(array_map('intval', $cuentaIds), static fn (int $id): bool => $id > 0));
        if ($cuentaIds === [] || $empresaId <= 0) {
            return [];
        }

        $monedaLocalId = (int) config('cotizacion.ID_MONEDA_DEFAULT', CotizacionVigenteSupport::MONEDA_LOCAL_ID);
        $filas = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->selectRaw('m.cuentacontable_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1)'
                .' ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('m.cuentacontable_id')
            ->get();

        $saldos = [];
        foreach ($filas as $fila) {
            $saldos[(int) $fila->cuentacontable_id] = -1 * (float) $fila->saldo_local;
        }

        return $saldos;
    }

    private function fechaAnterior(int $empresaId, string $fecha): ?string
    {
        if (! DB::getSchemaBuilder()->hasTable(self::TABLA)) {
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
     * @return array<int, float>
     */
    private function filasGuardadas(int $empresaId, string $fecha): array
    {
        $filas = DB::table(self::TABLA)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha)
            ->get(['cuentacontable_id', 'delta']);
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->cuentacontable_id] = (float) $fila->delta;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{codigo: string, nombre: string}>
     */
    private function nombresCuenta(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $out = [];
        $filas = DB::table('cuentacontable')->whereIn('id', $ids)->get(['id', 'codigo', 'nombre']);
        foreach ($filas as $fila) {
            $out[(int) $fila->id] = [
                'codigo' => trim((string) $fila->codigo),
                'nombre' => trim((string) $fila->nombre),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function guardar(int $empresaId, string $fecha, array $filas): void
    {
        if (! DB::getSchemaBuilder()->hasTable(self::TABLA)) {
            return;
        }

        DB::transaction(function () use ($empresaId, $fecha, $filas) {
            $ids = array_map(static fn (array $fila) => (int) $fila['cuentacontable_id'], $filas);
            $borrar = DB::table(self::TABLA)
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', $fecha);
            if ($ids !== []) {
                $borrar->whereNotIn('cuentacontable_id', $ids);
            }
            $borrar->delete();

            if ($filas === []) {
                return;
            }

            DB::table(self::TABLA)->upsert(
                $filas,
                ['fecha', 'empresa_id', 'cuentacontable_id'],
                ['deuda_local', 'mayor_local', 'delta', 'updated_at']
            );
        });
    }
}
