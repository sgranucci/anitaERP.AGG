<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Delta entre el mayor de las cuentas de proveedores y la deuda abierta, en pesos.
 * El signo es el del mayor (negativo = débito). Delta = mayor − deuda.
 * La corrida guarda el número del día y la siguiente avisa si se movió.
 *
 * Si la empresa tiene cuenta de anticipos (`pago.anticipo_proveedor`), esa cuenta
 * entra al control. La deuda abierta de las OP adelantadas imputadas ahí se compara
 * con el mayor de anticipos, no con el de proveedores. Sin esa cuenta el anticipo
 * sigue en proveedores, que es donde se contabiliza.
 */
final class ProveedorDeudaMayorDeltaSupport
{
    private const TABLA = 'proveedor_deuda_mayor_delta';

    /** @var array<string, array<int, array<int, array{deuda: float, mayor: float}>>> */
    private array $saldosPorFecha = [];

    /** @var array<int, int>|null empresa_id => cuentacontable_id de anticipos */
    private ?array $anticipoPorEmpresa = null;

    /** @var array<int, true>|null pagoproveedor_id con Debe en la cuenta de anticipos */
    private ?array $pagosConAnticipo = null;

    /**
     * Empresas con deuda abierta o mayor en una cuenta de proveedores al corte.
     *
     * @return list<int>
     */
    public function empresasConSaldo(string $fecha): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();

        return array_keys($this->saldos($fecha));
    }

    /**
     * @return array{
     *   fecha: string,
     *   fecha_anterior: ?string,
     *   fecha_anterior_fmt: ?string,
     *   hay_base_anterior: bool,
     *   tolerancia: float,
     *   variaciones: int,
     *   cuentas: list<array<string, mixed>>,
     *   cuentas_total: int
     * }
     */
    public function controlar(int $empresaId, string $fecha, float $tolerancia): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $tolerancia = abs($tolerancia);
        $hoy = $this->saldos($fecha)[$empresaId] ?? [];
        $fechaAnterior = $this->fechaAnterior($empresaId, $fecha);
        $anterior = $fechaAnterior === null ? [] : $this->filasGuardadas($empresaId, $fechaAnterior);

        $ids = array_values(array_unique(array_merge(array_keys($hoy), array_keys($anterior))));
        $cuentas = $this->nombresCuenta($ids);
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

            $nombre = $cuentas[$cuentaId] ?? ['codigo' => '', 'nombre' => ''];
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

        $this->guardar($empresaId, $fecha, $aGuardar);

        return [
            'fecha' => $fecha,
            'fecha_anterior' => $fechaAnterior,
            'fecha_anterior_fmt' => $fechaAnterior === null ? null : Carbon::parse($fechaAnterior)->format('d/m/Y'),
            'hay_base_anterior' => $fechaAnterior !== null,
            'tolerancia' => $tolerancia,
            'variaciones' => $variaciones,
            'cuentas' => $filas,
            'cuentas_total' => count($filas),
        ];
    }

    /**
     * @return array<int, array<int, array{deuda: float, mayor: float}>>
     */
    private function saldos(string $fecha): array
    {
        if (isset($this->saldosPorFecha[$fecha])) {
            return $this->saldosPorFecha[$fecha];
        }

        $cuentaIds = $this->cuentasProveedor();
        $deuda = $this->deudaPorCuenta($fecha);
        $mayor = $this->mayorPorCuenta($cuentaIds);

        $porEmpresa = [];
        $keys = array_unique(array_merge(array_keys($deuda), array_keys($mayor)));
        foreach ($keys as $key) {
            [$empresaId, $cuentaId] = array_map('intval', explode('|', (string) $key));
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            $deudaLocal = round((float) ($deuda[$key] ?? 0), 2);
            $mayorLocal = round((float) ($mayor[$key] ?? 0), 2);
            if (abs($deudaLocal) < 0.01 && abs($mayorLocal) < 0.01) {
                continue;
            }
            $porEmpresa[$empresaId][$cuentaId] = [
                'deuda' => $deudaLocal,
                'mayor' => $mayorLocal,
            ];
        }

        $this->saldosPorFecha[$fecha] = $porEmpresa;

        return $porEmpresa;
    }

    /**
     * @return array<int, true>
     */
    private function cuentasProveedor(): array
    {
        $cuentas = [];
        Proveedor::query()
            ->select(['id', 'cuentacontable_id', 'cuentacontableme_id'])
            ->orderBy('id')
            ->chunkById(200, function ($proveedores) use (&$cuentas) {
                foreach ($proveedores as $proveedor) {
                    foreach ([(int) $proveedor->cuentacontable_id, (int) $proveedor->cuentacontableme_id] as $id) {
                        if ($id > 0) {
                            $cuentas[$id] = true;
                        }
                    }
                }
            });

        $empresaIds = Empresa::query()->orderBy('id')->pluck('id')->all();
        foreach (array_keys($cuentas) as $cuentaId) {
            foreach ($empresaIds as $empresaId) {
                $homologada = ProveedorCuentaContableMonedaSupport::cuentaEnPlanDeEmpresa(
                    (int) $cuentaId,
                    (int) $empresaId
                );
                if ($homologada > 0) {
                    $cuentas[$homologada] = true;
                }
            }
        }

        foreach ($this->cuentasAnticipoPorEmpresa() as $cuentaId) {
            $cuentas[$cuentaId] = true;
        }

        return $cuentas;
    }

    /**
     * Solo las empresas que tienen configurada `pago.anticipo_proveedor`.
     *
     * @return array<int, int>
     */
    private function cuentasAnticipoPorEmpresa(): array
    {
        if ($this->anticipoPorEmpresa !== null) {
            return $this->anticipoPorEmpresa;
        }

        $out = [];
        foreach (Empresa::query()->orderBy('id')->pluck('id') as $empresaId) {
            $cuentaId = (int) (ProveedorAnticipoCuentaContableSupport::cuentaAnticipoId((int) $empresaId) ?? 0);
            if ($cuentaId > 0) {
                $out[(int) $empresaId] = $cuentaId;
            }
        }

        return $this->anticipoPorEmpresa = $out;
    }

    /**
     * Pagos cuyo asiento debitó la cuenta de anticipos. Esas OP abiertas no viven
     * en el mayor de proveedores.
     *
     * @return array<int, true>
     */
    private function pagosConDebeEnAnticipo(): array
    {
        if ($this->pagosConAnticipo !== null) {
            return $this->pagosConAnticipo;
        }

        $cuentaIds = array_values(array_unique($this->cuentasAnticipoPorEmpresa()));
        if ($cuentaIds === []) {
            return $this->pagosConAnticipo = [];
        }

        $ids = DB::table('asiento as a')
            ->join('asiento_movimiento as m', 'm.asiento_id', '=', 'a.id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where('m.monto', '>', 0)
            ->whereNotNull('a.pagoproveedor_id')
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->distinct()
            ->pluck('a.pagoproveedor_id');

        $set = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $set[$id] = true;
            }
        }

        return $this->pagosConAnticipo = $set;
    }

    /**
     * @return array<string, float>
     */
    private function deudaPorCuenta(string $fecha): array
    {
        $cuadre = app(ProveedorCuentacorrienteDeudaFichaCuadreSupport::class);
        $anticipoPorEmpresa = $this->cuentasAnticipoPorEmpresa();
        $pagosAnticipo = $this->pagosConDebeEnAnticipo();
        $saldos = [];
        Proveedor_Cuentacorriente::query()
            ->with([
                'comprobante_proveedores.ordencompras.ordencompra_articulos',
                'proveedores',
                'pagoproveedores:id,tipocomprobante',
            ])
            ->whereDate('fecha', '<=', $fecha)
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->orderBy('id')
            ->chunkById(300, function ($filas) use (&$saldos, $cuadre, $anticipoPorEmpresa, $pagosAnticipo) {
                foreach ($filas as $cc) {
                    $fila = (object) [
                        'total' => (float) $cc->total,
                        'aplicado' => (float) ($cc->aplicado ?? 0),
                        'comprobante_proveedor_id' => (int) ($cc->comprobante_proveedor_id ?? 0),
                        'pagoproveedor_id' => (int) ($cc->pagoproveedor_id ?? 0),
                        'tipocomprobante' => (string) ($cc->pagoproveedores?->tipocomprobante ?? ''),
                    ];
                    if (! $cuadre->esDeudaAbierta($fila)) {
                        continue;
                    }
                    $saldo = ProveedorCuentacorrienteGrillaSupport::saldoPendiente(
                        (float) $fila->total,
                        (float) $fila->aplicado
                    );
                    if (abs($saldo) < 0.01) {
                        continue;
                    }
                    $proveedor = $cc->proveedores;
                    $empresaId = (int) $cc->empresa_id;
                    $cuentaAnticipoId = $anticipoPorEmpresa[$empresaId] ?? 0;
                    $esAnticipo = $cuentaAnticipoId > 0
                        && ProveedorAnticipoCuentaContableSupport::esCreditoAnticipo($cc)
                        && isset($pagosAnticipo[(int) $cc->pagoproveedor_id]);
                    $cuentaId = 0;
                    if ($esAnticipo) {
                        $cuentaId = $cuentaAnticipoId;
                    } elseif ($cc->comprobante_proveedores) {
                        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorDesdeComprobante(
                            $cc->comprobante_proveedores,
                            $proveedor
                        );
                    }
                    if ($cuentaId <= 0) {
                        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
                            $proveedor,
                            (int) ($cc->moneda_id ?: 1),
                            $empresaId
                        );
                    }
                    if ($cuentaId <= 0) {
                        continue;
                    }
                    if (! $esAnticipo) {
                        $cuentaId = ProveedorCuentaContableMonedaSupport::cuentaEnPlanDeEmpresa(
                            $cuentaId,
                            $empresaId
                        );
                    }
                    if ($cuentaId <= 0) {
                        continue;
                    }
                    $local = ProveedorCuentacorrienteAplicacionLiquidacionSupport::valorLocal(
                        $saldo,
                        (float) ($cc->cotizacion ?? 1),
                        (int) ($cc->moneda_id ?: 1)
                    );
                    if ($saldo < 0) {
                        $local = -$local;
                    }
                    $key = $empresaId.'|'.$cuentaId;
                    $saldos[$key] = ($saldos[$key] ?? 0) + $local;
                }
            });

        return $saldos;
    }

    /**
     * Mismo universo que el control de mayor: no corta por fecha, así un asiento
     * con fecha futura no hace saltar el delta el día en que esa fecha llega.
     *
     * @param  array<int, true>  $cuentaIds
     * @return array<string, float>
     */
    private function mayorPorCuenta(array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $filas = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->whereIn('m.cuentacontable_id', array_keys($cuentaIds))
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->selectRaw('a.empresa_id, m.cuentacontable_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1)'
                .' ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('a.empresa_id', 'm.cuentacontable_id')
            ->get();

        $saldos = [];
        foreach ($filas as $fila) {
            $key = ((int) $fila->empresa_id).'|'.((int) $fila->cuentacontable_id);
            $saldos[$key] = -1 * (float) $fila->saldo_local;
        }

        return $saldos;
    }

    private function fechaAnterior(int $empresaId, string $fecha): ?string
    {
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
