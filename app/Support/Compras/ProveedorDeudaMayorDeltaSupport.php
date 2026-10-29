<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Database\SqlDialectSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dos controles separados, en pesos, con el signo del mayor (negativo = débito).
 *
 * Proveedores: delta = mayor − deuda abierta, solo cuentas de rubro Pasivo que
 * además estén en el plan (MN, ME y no corrientes). Avisa si ese delta cambia.
 * Una factura anticipada acredita proveedores y suma la misma deuda abierta:
 * el delta de proveedores no se mueve. Un banco, un gasto u otra cuenta cargada
 * en el proveedor no entra.
 *
 * Anticipos: control aparte, solo si la empresa tiene `pago.anticipo_proveedor`.
 * Lo explicado es la OP adelantada imputada ahí más el neto de la factura
 * anticipada que la recepción todavía no cerró. Sin explicar = mayor − explicado.
 * Alta de factura y cierre por recepción mueven el mayor y lo explicado por el
 * mismo importe, así que este control no avisa. Avisa si el sin explicar cambia.
 * Sin cuenta de anticipos configurada, el anticipo sigue en proveedores.
 */
final class ProveedorDeudaMayorDeltaSupport
{
    private const TABLA = 'proveedor_deuda_mayor_delta';

    /** @var array<string, array<int, array<int, array{deuda: float, mayor: float, opa: float, facturas: float}>>> */
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
                'facturas' => round((float) ($hoy[$cuentaId]['facturas'] ?? 0), 2),
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

        $this->guardar($empresaId, $fecha, $aGuardar);

        return [
            'fecha' => $fecha,
            'fecha_anterior' => $fechaAnterior,
            'fecha_anterior_fmt' => $fechaAnterior === null ? null : Carbon::parse($fechaAnterior)->format('d/m/Y'),
            'hay_base_anterior' => $fechaAnterior !== null,
            'tolerancia' => $tolerancia,
            'variaciones' => $variaciones,
            'variaciones_anticipos' => $variacionesAnticipo,
            'cuentas' => $filasProveedor,
            'cuentas_total' => count($filasProveedor),
            'anticipos' => $filasAnticipo,
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
     * @return array<int, array<int, array{deuda: float, mayor: float, opa: float, facturas: float}>>
     */
    private function saldos(string $fecha): array
    {
        if (isset($this->saldosPorFecha[$fecha])) {
            return $this->saldosPorFecha[$fecha];
        }

        $cuentaIds = $this->cuentasProveedor();
        $deudaCc = $this->deudaPorCuenta($fecha);
        $deudaFa = $this->saldoFacturaAnticipadaEnAnticipo();
        $mayor = $this->mayorPorCuenta($cuentaIds);

        $porEmpresa = [];
        $keys = array_unique(array_merge(array_keys($deudaCc), array_keys($deudaFa), array_keys($mayor)));
        foreach ($keys as $key) {
            [$empresaId, $cuentaId] = array_map('intval', explode('|', (string) $key));
            if ($empresaId <= 0 || $cuentaId <= 0 || ! isset($cuentaIds[$cuentaId])) {
                continue;
            }
            $opa = round((float) ($deudaCc[$key] ?? 0), 2);
            $facturas = round((float) ($deudaFa[$key] ?? 0), 2);
            $deudaLocal = round($opa + $facturas, 2);
            $mayorLocal = round((float) ($mayor[$key] ?? 0), 2);
            if (abs($deudaLocal) < 0.01 && abs($mayorLocal) < 0.01) {
                continue;
            }
            $porEmpresa[$empresaId][$cuentaId] = [
                'deuda' => $deudaLocal,
                'mayor' => $mayorLocal,
                'opa' => $opa,
                'facturas' => $facturas,
            ];
        }

        $this->saldosPorFecha[$fecha] = $porEmpresa;

        return $porEmpresa;
    }

    /**
     * Cuentas del plan de proveedores (MN/ME) y de rubro Pasivo, homologadas al plan
     * de cada empresa, más la cuenta de anticipos configurada.
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
     * Cuentas de proveedores MN/ME (y no corrientes) de la config, en cada plan.
     *
     * @return array<int, true>
     */
    private function idsCuentasProveedorValidas(): array
    {
        if ($this->cuentasProveedorValidas !== null) {
            return $this->cuentasProveedorValidas;
        }

        $catalogo = ComprobanteProveedorImputacionApCuentasSupport::armar();

        return $this->cuentasProveedorValidas = $catalogo['mn'] + $catalogo['me'];
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
     * Neto de factura anticipada todavía en la cuenta de anticipos, con el signo
     * del mayor. Incluye el débito del comprobante y el crédito de la recepción
     * de esa orden, sin pasar el crédito por encima de lo facturado en el ERP.
     *
     * @return array<string, float> clave empresa|cuenta
     */
    private function saldoFacturaAnticipadaEnAnticipo(): array
    {
        $anticipoPorEmpresa = $this->cuentasAnticipoPorEmpresa();
        $cuentaIds = array_values(array_unique($anticipoPorEmpresa));
        if ($cuentaIds === []) {
            return [];
        }

        $facturado = $this->montoFacturaAnticipadaPorOrden($cuentaIds);
        $cerrado = $this->montoCierreRecepcionPorOrden($cuentaIds, array_keys($facturado));
        $saldos = [];
        foreach ($facturado as $key => $invoice) {
            $recepcion = (float) ($cerrado[$key] ?? 0);
            $credito = min(0.0, $recepcion);
            $tope = min(0.0, -$invoice);
            $explicado = round($invoice + max($credito, $tope), 2);
            if (abs($explicado) < 0.005) {
                continue;
            }
            $partes = explode('|', $key);
            $empresaId = (int) ($partes[0] ?? 0);
            $cuentaId = (int) ($partes[1] ?? 0);
            if (($anticipoPorEmpresa[$empresaId] ?? 0) !== $cuentaId) {
                continue;
            }
            $grupo = $empresaId.'|'.$cuentaId;
            $saldos[$grupo] = round(($saldos[$grupo] ?? 0) + (-1 * $explicado), 2);
        }

        return $saldos;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return array<string, float> empresa|cuenta|ordencompra => suma de monto en pesos
     */
    private function montoFacturaAnticipadaPorOrden(array $cuentaIds): array
    {
        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $filas = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'a.comprobante_proveedor_id')
            ->join('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->where('cp.modo_carga', '!=', ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)
            ->whereRaw(SqlDialectSupport::lower('oc.tratamiento').' in (?, ?, ?)', ['anticipada', '2', 's'])
            ->where(function ($q) {
                $q->whereNull('oc.es_contrato')->orWhere('oc.es_contrato', 0);
            })
            ->selectRaw('a.empresa_id, m.cuentacontable_id, oc.id as ordencompra_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('a.empresa_id', 'm.cuentacontable_id', 'oc.id')
            ->get();

        return $this->indexarMontoPorOrden($filas);
    }

    /**
     * Crédito de la recepción sobre las mismas órdenes. El tope lo aplica el caller:
     * un cierre mayor que la factura del ERP no se toma como explicado.
     *
     * @param  list<int>  $cuentaIds
     * @param  list<string>  $clavesFacturado
     * @return array<string, float>
     */
    private function montoCierreRecepcionPorOrden(array $cuentaIds, array $clavesFacturado): array
    {
        $ocIds = [];
        foreach ($clavesFacturado as $clave) {
            $partes = explode('|', (string) $clave);
            $ocId = (int) ($partes[2] ?? 0);
            if ($ocId > 0) {
                $ocIds[$ocId] = true;
            }
        }
        if ($ocIds === []) {
            return [];
        }

        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $filas = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('recepcion_proveedor as r', 'r.id', '=', 'a.recepcionproveedor_id')
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->whereIn('r.ordencompra_id', array_keys($ocIds))
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            })
            ->selectRaw('a.empresa_id, m.cuentacontable_id, r.ordencompra_id as ordencompra_id')
            ->selectRaw(
                'SUM(CASE WHEN COALESCE(m.moneda_id, ?) > ? THEN m.monto * COALESCE(NULLIF(m.cotizacion, 0), 1) ELSE m.monto END) as saldo_local',
                [$monedaLocalId, $monedaLocalId]
            )
            ->groupBy('a.empresa_id', 'm.cuentacontable_id', 'r.ordencompra_id')
            ->get();

        return $this->indexarMontoPorOrden($filas);
    }

    /**
     * @param  iterable<int, object>  $filas
     * @return array<string, float>
     */
    private function indexarMontoPorOrden(iterable $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $key = ((int) $fila->empresa_id).'|'.((int) $fila->cuentacontable_id).'|'.((int) $fila->ordencompra_id);
            $out[$key] = round((float) $fila->saldo_local, 2);
        }

        return $out;
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
