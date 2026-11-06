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
 * Delta conjunto de proveedores + anticipos, en pesos, signo del mayor
 * (negativo = débito). Delta de cada cuenta = mayor − deuda.
 *
 * El aviso es la suma. Una aplicación debita una cuenta y acredita la otra por
 * el mismo importe, y baja la OPA y la factura: el delta conjunto no se mueve.
 *
 * Proveedores: facturas impagas, notas de crédito sin aplicar y OPA sin aplicar
 * que no están en anticipos. Entran las cuentas de rubro Pasivo del proveedor
 * (MN y ME) y las del plan configurado. Un banco o un gasto no entra.
 *
 * Anticipos: solo deuda de OPA (y el sobrante de OPP) imputadas a
 * `pago.anticipo_proveedor`. El mayor de esa cuenta excluye facturas anticipadas
 * y recepciones: eso va al auditor aparte de factura anticipada vs recepción.
 * Sin cuenta de anticipos, la OPA sigue en proveedores.
 */
final class ProveedorDeudaMayorDeltaSupport
{
    private const TABLA = 'proveedor_deuda_mayor_delta';

    /** @var array<string, array<int, array<int, array{deuda: float, mayor: float, opa: float}>>> */
    private array $saldosPorFecha = [];

    /** @var array<int, int>|null empresa_id => cuentacontable_id de anticipos */
    private ?array $anticipoPorEmpresa = null;

    /** @var array<int, true>|null pagoproveedor_id con Debe en la cuenta de anticipos */
    private ?array $pagosConAnticipo = null;

    /** @var array<int, true>|null cuentas de pasivo de proveedores, más anticipos */
    private ?array $cuentasProveedorCache = null;

    /** @var array<int, true>|null ids de cuentas de proveedores MN/ME, todos los planes */
    private ?array $cuentasProveedorValidas = null;

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
     *   variaciones_anticipos: int,
     *   delta_proveedores: float,
     *   delta_anticipos: float,
     *   delta_conjunto: float,
     *   delta_conjunto_anterior: ?float,
     *   variacion_conjunto: ?float,
     *   conjunto_movio: bool,
     *   cuentas_sin_base: int,
     *   cuentas: list<array<string, mixed>>,
     *   cuentas_total: int,
     *   anticipos: list<array<string, mixed>>
     * }
     */
    public function controlar(int $empresaId, string $fecha, float $tolerancia): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $tolerancia = abs($tolerancia);
        $hoy = $this->saldos($fecha)[$empresaId] ?? [];
        $fechaAnterior = $this->fechaAnterior($empresaId, $fecha);
        $anterior = $fechaAnterior === null ? [] : $this->filasGuardadas($empresaId, $fechaAnterior);
        $cuentaAnticipoId = (int) ($this->cuentasAnticipoPorEmpresa()[$empresaId] ?? 0);

        $permitidas = $this->cuentasProveedor();
        $ids = array_values(array_filter(
            array_unique(array_merge(array_keys($hoy), array_keys($anterior))),
            static fn ($cuentaId): bool => isset($permitidas[(int) $cuentaId])
        ));
        $cuentas = $this->nombresCuenta($ids);
        $filasProveedor = [];
        $filasAnticipo = [];
        $aGuardar = [];
        $variaciones = 0;
        $variacionesAnticipo = 0;

        foreach ($ids as $cuentaId) {
            $deuda = round((float) ($hoy[$cuentaId]['deuda'] ?? 0), 2);
            $mayor = round((float) ($hoy[$cuentaId]['mayor'] ?? 0), 2);
            $delta = round($mayor - $deuda, 2);
            $tenia = array_key_exists($cuentaId, $anterior);
            $deltaAnterior = $tenia ? round((float) $anterior[$cuentaId], 2) : null;
            $variacion = $deltaAnterior === null ? null : round($delta - $deltaAnterior, 2);
            $movio = $variacion !== null && abs($variacion) >= $tolerancia;
            $esAnticipo = $cuentaAnticipoId > 0 && $cuentaId === $cuentaAnticipoId;
            if ($movio) {
                if ($esAnticipo) {
                    $variacionesAnticipo++;
                } else {
                    $variaciones++;
                }
            }

            $nombre = $cuentas[$cuentaId] ?? ['codigo' => '', 'nombre' => ''];
            $fila = [
                'cuentacontable_id' => $cuentaId,
                'codigo' => $nombre['codigo'],
                'nombre' => $nombre['nombre'],
                'deuda' => $deuda,
                'opa' => round((float) ($hoy[$cuentaId]['opa'] ?? 0), 2),
                'mayor' => $mayor,
                'delta' => $delta,
                'delta_anterior' => $deltaAnterior,
                'variacion' => $variacion,
                'movio' => $movio,
                'es_anticipo' => $esAnticipo,
            ];
            if ($esAnticipo) {
                $filasAnticipo[] = $fila;
            } else {
                $filasProveedor[] = $fila;
            }

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

        $filasProveedor = $this->ordenarPorVariacion($filasProveedor);
        $filasAnticipo = $this->ordenarPorVariacion($filasAnticipo);
        $conjunto = $this->deltaConjunto($filasProveedor, $filasAnticipo, $fechaAnterior !== null, $tolerancia);

        $this->guardar($empresaId, $fecha, $aGuardar);

        return [
            'fecha' => $fecha,
            'fecha_anterior' => $fechaAnterior,
            'fecha_anterior_fmt' => $fechaAnterior === null ? null : Carbon::parse($fechaAnterior)->format('d/m/Y'),
            'hay_base_anterior' => $fechaAnterior !== null,
            'tolerancia' => $tolerancia,
            'variaciones' => $variaciones,
            'variaciones_anticipos' => $variacionesAnticipo,
            'delta_proveedores' => $conjunto['delta_proveedores'],
            'delta_anticipos' => $conjunto['delta_anticipos'],
            'delta_conjunto' => $conjunto['delta_conjunto'],
            'delta_conjunto_anterior' => $conjunto['delta_conjunto_anterior'],
            'variacion_conjunto' => $conjunto['variacion_conjunto'],
            'conjunto_movio' => $conjunto['conjunto_movio'],
            'cuentas_sin_base' => $conjunto['cuentas_sin_base'],
            'cuentas' => $filasProveedor,
            'cuentas_total' => count($filasProveedor),
            'anticipos' => $filasAnticipo,
        ];
    }

    /**
     * La variación del conjunto suma solo las cuentas que ya tenían base.
     * Una cuenta que entra hoy no dispara el aviso: queda como medición inicial.
     *
     * @param  list<array<string, mixed>>  $filasProveedor
     * @param  list<array<string, mixed>>  $filasAnticipo
     * @return array{
     *   delta_proveedores: float,
     *   delta_anticipos: float,
     *   delta_conjunto: float,
     *   delta_conjunto_anterior: ?float,
     *   variacion_conjunto: ?float,
     *   conjunto_movio: bool,
     *   cuentas_sin_base: int
     * }
     */
    private function deltaConjunto(array $filasProveedor, array $filasAnticipo, bool $hayBase, float $tolerancia): array
    {
        $deltaProveedores = round(array_sum(array_map(
            static fn (array $fila): float => (float) ($fila['delta'] ?? 0),
            $filasProveedor
        )), 2);
        $deltaAnticipos = round(array_sum(array_map(
            static fn (array $fila): float => (float) ($fila['delta'] ?? 0),
            $filasAnticipo
        )), 2);
        $variacion = null;
        $anterior = null;
        $sinBase = 0;
        if ($hayBase) {
            $variacion = 0.0;
            $anterior = 0.0;
            foreach (array_merge($filasProveedor, $filasAnticipo) as $fila) {
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
            'delta_proveedores' => $deltaProveedores,
            'delta_anticipos' => $deltaAnticipos,
            'delta_conjunto' => round($deltaProveedores + $deltaAnticipos, 2),
            'delta_conjunto_anterior' => $anterior,
            'variacion_conjunto' => $variacion,
            'conjunto_movio' => $variacion !== null && abs($variacion) >= $tolerancia,
            'cuentas_sin_base' => $sinBase,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function ordenarPorVariacion(array $filas): array
    {
        usort($filas, static function (array $a, array $b): int {
            $ma = ! empty($a['movio']);
            $mb = ! empty($b['movio']);
            if ($ma !== $mb) {
                return $mb <=> $ma;
            }

            return abs((float) $b['delta']) <=> abs((float) $a['delta']);
        });

        return $filas;
    }

    /**
     * @return array<int, array<int, array{deuda: float, mayor: float, opa: float}>>
     */
    private function saldos(string $fecha): array
    {
        if (isset($this->saldosPorFecha[$fecha])) {
            return $this->saldosPorFecha[$fecha];
        }

        $cuentaIds = $this->cuentasProveedor();
        $deudaCc = $this->deudaPorCuenta($fecha);
        $mayor = $this->mayorPorCuenta($cuentaIds);

        $porEmpresa = [];
        $keys = array_unique(array_merge(array_keys($deudaCc), array_keys($mayor)));
        foreach ($keys as $key) {
            [$empresaId, $cuentaId] = array_map('intval', explode('|', (string) $key));
            if ($empresaId <= 0 || $cuentaId <= 0 || ! isset($cuentaIds[$cuentaId])) {
                continue;
            }
            $opa = round((float) ($deudaCc[$key] ?? 0), 2);
            $mayorLocal = round((float) ($mayor[$key] ?? 0), 2);
            if (abs($opa) < 0.01 && abs($mayorLocal) < 0.01) {
                continue;
            }
            $porEmpresa[$empresaId][$cuentaId] = [
                'deuda' => $opa,
                'mayor' => $mayorLocal,
                'opa' => $opa,
            ];
        }

        $this->saldosPorFecha[$fecha] = $porEmpresa;

        return $porEmpresa;
    }

    /**
     * Cuentas de rubro Pasivo del proveedor (MN/ME) y del plan configurado,
     * homologadas al plan de cada empresa, más la cuenta de anticipos.
     *
     * @return array<int, true>
     */
    private function cuentasProveedor(): array
    {
        if ($this->cuentasProveedorCache !== null) {
            return $this->cuentasProveedorCache;
        }

        $validas = $this->idsCuentasProveedorValidas();
        $cuentas = [];
        Proveedor::query()
            ->select(['id', 'cuentacontable_id', 'cuentacontableme_id'])
            ->orderBy('id')
            ->chunkById(200, function ($proveedores) use (&$cuentas, $validas) {
                foreach ($proveedores as $proveedor) {
                    foreach ([(int) $proveedor->cuentacontable_id, (int) $proveedor->cuentacontableme_id] as $id) {
                        if ($id > 0 && isset($validas[$id])) {
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
                if ($homologada > 0 && isset($validas[$homologada])) {
                    $cuentas[$homologada] = true;
                }
            }
        }

        $cuentas = $this->soloRubroPasivo($cuentas);

        foreach ($this->cuentasAnticipoPorEmpresa() as $cuentaId) {
            if ($cuentaId > 0) {
                $cuentas[$cuentaId] = true;
            }
        }

        return $this->cuentasProveedorCache = $cuentas;
    }

    /**
     * @param  array<int, true>  $cuentas
     * @return array<int, true>
     */
    private function soloRubroPasivo(array $cuentas): array
    {
        if ($cuentas === []) {
            return [];
        }

        $ids = DB::table('cuentacontable as c')
            ->join('rubrocontable as r', 'r.id', '=', 'c.rubrocontable_id')
            ->whereIn('c.id', array_keys($cuentas))
            ->where('r.nombre', 'Pasivo')
            ->pluck('c.id');

        $pasivo = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $pasivo[$id] = true;
            }
        }

        return $pasivo;
    }

    /**
     * Cuentas de proveedores de rubro Pasivo: las del maestro (MN y ME) y las
     * del plan configurado cuando ese código existe en el plan de la empresa.
     *
     * @return array<int, true>
     */
    private function idsCuentasProveedorValidas(): array
    {
        if ($this->cuentasProveedorValidas !== null) {
            return $this->cuentasProveedorValidas;
        }

        $catalogo = ComprobanteProveedorImputacionApCuentasSupport::armar();
        $cuentas = $catalogo['mn'] + $catalogo['me'];
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

        return $this->cuentasProveedorValidas = $this->soloRubroPasivo($cuentas);
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
     * Pagos cuyo asiento debitó anticipos, y la OPA de sobrante de esa OPP.
     * El debe está en el asiento de la OPP; el crédito abierto está en la OPA hija,
     * que no tiene asiento propio. Esas OPA cuadran con anticipos, no con proveedores.
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

        if ($set !== []) {
            $hijas = DB::table('pagoproveedor')
                ->where('tipocomprobante', 'OPA')
                ->whereIn('pagoproveedor_origen_id', array_keys($set))
                ->pluck('id');
            foreach ($hijas as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $set[$id] = true;
                }
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
                    if (! $esAnticipo && ! isset($this->idsCuentasProveedorValidas()[$cuentaId])) {
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
     * No corta por fecha: un asiento con fecha futura no hace saltar el delta el
     * día en que esa fecha llega. En cuentas de anticipos se excluyen facturas
     * anticipadas y recepciones (van al auditor de FA vs recepción).
     *
     * @param  array<int, true>  $cuentaIds
     * @return array<string, float>
     */
    private function mayorPorCuenta(array $cuentaIds): array
    {
        if ($cuentaIds === []) {
            return [];
        }

        $anticipoIds = array_values(array_unique(array_filter(
            array_map('intval', $this->cuentasAnticipoPorEmpresa()),
            static fn (int $id): bool => $id > 0
        )));
        $anticipoSet = array_fill_keys($anticipoIds, true);
        $pasivoIds = [];
        foreach (array_keys($cuentaIds) as $cuentaId) {
            $cuentaId = (int) $cuentaId;
            if ($cuentaId > 0 && ! isset($anticipoSet[$cuentaId])) {
                $pasivoIds[] = $cuentaId;
            }
        }

        return $this->sumarMayor($pasivoIds, false)
            + $this->sumarMayor($anticipoIds, true);
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return array<string, float>
     */
    private function sumarMayor(array $cuentaIds, bool $soloOpaAnticipo): array
    {
        $cuentaIds = array_values(array_filter(array_map('intval', $cuentaIds), static fn (int $id): bool => $id > 0));
        if ($cuentaIds === []) {
            return [];
        }

        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $query = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            });
        if ($soloOpaAnticipo) {
            // Solo OPP/OPA y aplicaciones: sin Debe de factura anticipada ni Haber de recepción.
            $query->whereNull('a.comprobante_proveedor_id')
                ->whereNull('a.recepcionproveedor_id');
        }
        $filas = $query
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
