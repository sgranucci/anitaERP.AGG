<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Empresa;
use App\Models\Contable\Asiento;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\Anita\AnitaMayorAnaliticoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

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
 *
 * Ingresos/egresos (`asiento.caja_movimiento_id`): no entran en el mayor del
 * delta. Si tocan una cuenta controlada se listan aparte para avisar a
 * administración (no mueven el conjunto).
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
     *   anticipos: list<array<string, mixed>>,
     *   mayor_anita_incluido: bool,
     *   mayor_anita_error: ?string,
     *   diferencias_erp_anita: int,
     *   ie_cuentas_controladas: list<array<string, mixed>>,
     *   ie_cuentas_controladas_total: int,
     *   ie_alerta: bool,
     *   ie_suma_efecto_mayor: float
     * }
     */
    public function controlar(int $empresaId, string $fecha, float $tolerancia): array
    {
        $fecha = Carbon::parse($fecha)->toDateString();
        $tolerancia = abs($tolerancia);
        $hoy = $this->saldos($fecha)[$empresaId] ?? [];
        $fechaAnterior = $this->fechaAnterior($empresaId, $fecha);
        $anterior = $fechaAnterior === null ? [] : $this->filasGuardadas($empresaId, $fechaAnterior);
        $deudaAnterior = $fechaAnterior === null ? [] : $this->deudasGuardadas($empresaId, $fechaAnterior);
        $snapAnteriorAt = $fechaAnterior === null ? null : $this->momentoSnapshot($empresaId, $fechaAnterior);
        $cuentaAnticipoId = (int) ($this->cuentasAnticipoPorEmpresa()[$empresaId] ?? 0);

        $permitidas = $this->cuentasProveedor();
        $ids = array_values(array_filter(
            array_unique(array_merge(array_keys($hoy), array_keys($anterior))),
            static fn ($cuentaId): bool => isset($permitidas[(int) $cuentaId])
        ));
        $cuentas = $this->nombresCuenta($ids);
        $noIeNuevos = $snapAnteriorAt === null
            ? []
            : $this->efectoMayorPorCuentaEmpresa(
                $empresaId,
                $ids,
                soloOpaAnticipo: null,
                soloCajaMovimiento: false,
                creadoDesde: $snapAnteriorAt,
            );
        $ieAlAnterior = $snapAnteriorAt === null
            ? []
            : $this->efectoMayorPorCuentaEmpresa(
                $empresaId,
                $ids,
                soloOpaAnticipo: null,
                soloCajaMovimiento: true,
                creadoHasta: $snapAnteriorAt,
            );
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
            // Mayor del control sin I/E. La base anterior se reconstruye con la misma
            // fórmula (mayor hoy − no-IE nuevos − deuda guardada) para no saltar el
            // día en que se dejó de incluir ingresos/egresos en el mayor.
            $deltaAnterior = null;
            if ($tenia) {
                $mayorAlAnterior = round($mayor - (float) ($noIeNuevos[$cuentaId] ?? 0), 2);
                $deudaAnt = array_key_exists($cuentaId, $deudaAnterior)
                    ? round((float) $deudaAnterior[$cuentaId], 2)
                    : round($mayorAlAnterior - (float) $anterior[$cuentaId] + (float) ($ieAlAnterior[$cuentaId] ?? 0), 2);
                $deltaAnterior = round($mayorAlAnterior - $deudaAnt, 2);
            }
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
        $anita = $this->enriquecerConMayorAnita($empresaId, $fecha, $tolerancia, $filasProveedor, $filasAnticipo);
        $ie = $this->listarIngresosEgresosCuentasControladas(
            $empresaId,
            $ids,
            $snapAnteriorAt,
            $fecha,
            $tolerancia,
        );

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
            'mayor_anita_incluido' => $anita['incluido'],
            'mayor_anita_error' => $anita['error'],
            'diferencias_erp_anita' => $anita['diferencias'],
            'ie_cuentas_controladas' => $ie['filas'],
            'ie_cuentas_controladas_total' => $ie['total'],
            'ie_alerta' => $ie['alerta'],
            'ie_suma_efecto_mayor' => $ie['suma_efecto_mayor'],
        ];
    }

    /**
     * Suma neto_haber Anita (subdiario+ctamov) por cuenta y lo compara con el mayor ERP.
     *
     * @param  list<array<string, mixed>>  $filasProveedor
     * @param  list<array<string, mixed>>  $filasAnticipo
     * @return array{incluido: bool, error: ?string, diferencias: int}
     */
    private function enriquecerConMayorAnita(
        int $empresaId,
        string $fecha,
        float $tolerancia,
        array &$filasProveedor,
        array &$filasAnticipo,
    ): array {
        $meta = ['incluido' => false, 'error' => null, 'diferencias' => 0];
        // Solo El Bierzo: en AGG/Ferli/Crown/etc. el mayor contable es el del ERP.
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return $meta;
        }
        if (! filter_var(config('compras.deuda_ficha_auditor.incluir_mayor_anita', false), FILTER_VALIDATE_BOOLEAN)) {
            return $meta;
        }

        $codigoACuentaId = [];
        foreach (array_merge($filasProveedor, $filasAnticipo) as $fila) {
            $codigo = (int) preg_replace('/\D+/', '', (string) ($fila['codigo'] ?? ''));
            $cuentaId = (int) ($fila['cuentacontable_id'] ?? 0);
            if ($codigo > 0 && $cuentaId > 0) {
                $codigoACuentaId[$codigo] = $cuentaId;
            }
        }
        if ($codigoACuentaId === []) {
            return $meta;
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            $meta['error'] = 'Sin código empresa Anita.';

            return $meta;
        }

        $desde = Carbon::parse((string) config(
            'compras.deuda_ficha_auditor.mayor_anita_desde',
            '2000-01-01'
        ))->toDateString();
        $ymdDesde = (int) str_replace('-', '', $desde);
        $ymdHasta = (int) str_replace('-', '', $fecha);

        try {
            $movs = app(AnitaMayorAnaliticoSupport::class)->listarMovimientosPeriodo(
                $empresaAnita,
                $ymdDesde,
                $ymdHasta,
                array_keys($codigoACuentaId),
            );
        } catch (Throwable $e) {
            $meta['error'] = $e->getMessage();
            Log::warning('deuda_ficha_auditor.mayor_anita_fallo', [
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return $meta;
        }

        $porCuentaId = [];
        foreach ($movs as $mov) {
            $codigo = (int) ($mov['cuenta_codigo'] ?? 0);
            $cuentaId = $codigoACuentaId[$codigo] ?? 0;
            if ($cuentaId <= 0) {
                continue;
            }
            $porCuentaId[$cuentaId] = ($porCuentaId[$cuentaId] ?? 0.0) + (float) ($mov['neto_haber'] ?? 0);
        }

        $diferencias = 0;
        $aplicar = static function (array &$filas) use (&$diferencias, $porCuentaId, $tolerancia): void {
            foreach ($filas as &$fila) {
                $cuentaId = (int) ($fila['cuentacontable_id'] ?? 0);
                $mayorAnita = round((float) ($porCuentaId[$cuentaId] ?? 0), 2);
                $deuda = (float) ($fila['deuda'] ?? 0);
                $mayorErp = (float) ($fila['mayor'] ?? 0);
                $deltaAnita = round($mayorAnita - $deuda, 2);
                $diffErpAnita = round($mayorErp - $mayorAnita, 2);
                $difiere = abs($diffErpAnita) >= $tolerancia;
                if ($difiere) {
                    $diferencias++;
                }
                $fila['mayor_anita'] = $mayorAnita;
                $fila['delta_anita'] = $deltaAnita;
                $fila['diff_erp_anita'] = $diffErpAnita;
                $fila['erp_anita_difiere'] = $difiere;
            }
            unset($fila);
        };
        $aplicar($filasProveedor);
        $aplicar($filasAnticipo);

        $meta['incluido'] = true;
        $meta['diferencias'] = $diferencias;

        return $meta;
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
     * Los asientos de ingresos/egresos (`caja_movimiento_id`) no entran: se
     * informan aparte si tocan una cuenta controlada.
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

        return $this->sumarMayor($pasivoIds, false, false)
            + $this->sumarMayor($anticipoIds, true, false);
    }

    /**
     * @param  list<int>  $cuentaIds
     * @param  bool|null  $soloCajaMovimiento  null = todos; true = solo I/E; false = sin I/E
     * @return array<string, float>
     */
    private function sumarMayor(array $cuentaIds, bool $soloOpaAnticipo, ?bool $soloCajaMovimiento = false): array
    {
        return $this->sumarMayorFiltrado($cuentaIds, $soloOpaAnticipo, $soloCajaMovimiento);
    }

    /**
     * Efecto en el mayor (signo del mayor) por cuenta, acotado a una empresa.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, float> cuentacontable_id => efecto
     */
    private function efectoMayorPorCuentaEmpresa(
        int $empresaId,
        array $cuentaIds,
        ?bool $soloOpaAnticipo,
        ?bool $soloCajaMovimiento,
        ?string $creadoDesde = null,
        ?string $creadoHasta = null,
    ): array {
        $cuentaIds = array_values(array_filter(array_map('intval', $cuentaIds), static fn (int $id): bool => $id > 0));
        if ($cuentaIds === [] || $empresaId <= 0) {
            return [];
        }

        $anticipoId = (int) ($this->cuentasAnticipoPorEmpresa()[$empresaId] ?? 0);
        $out = [];
        foreach ($cuentaIds as $cuentaId) {
            $esAnticipo = $anticipoId > 0 && $cuentaId === $anticipoId;
            $filtroOpa = $soloOpaAnticipo;
            if ($filtroOpa === null) {
                $filtroOpa = $esAnticipo;
            }
            $parcial = $this->sumarMayorFiltrado(
                [$cuentaId],
                (bool) $filtroOpa,
                $soloCajaMovimiento,
                $empresaId,
                $creadoDesde,
                $creadoHasta,
            );
            $key = $empresaId.'|'.$cuentaId;
            if (isset($parcial[$key])) {
                $out[$cuentaId] = round((float) $parcial[$key], 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @return array<string, float>
     */
    private function sumarMayorFiltrado(
        array $cuentaIds,
        bool $soloOpaAnticipo,
        ?bool $soloCajaMovimiento,
        ?int $empresaId = null,
        ?string $creadoDesde = null,
        ?string $creadoHasta = null,
    ): array {
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
        if ($empresaId !== null && $empresaId > 0) {
            $query->where('a.empresa_id', $empresaId);
        }
        if ($soloOpaAnticipo) {
            $query->whereNull('a.comprobante_proveedor_id')
                ->whereNull('a.recepcionproveedor_id');
        }
        if ($soloCajaMovimiento === true) {
            $query->whereNotNull('a.caja_movimiento_id');
        } elseif ($soloCajaMovimiento === false) {
            $query->whereNull('a.caja_movimiento_id');
        }
        if ($creadoDesde !== null && $creadoDesde !== '') {
            $query->where('a.created_at', '>', $creadoDesde);
        }
        if ($creadoHasta !== null && $creadoHasta !== '') {
            $query->where('a.created_at', '<=', $creadoHasta);
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

    /**
     * I/E que tocaron cuentas controladas desde el snapshot anterior (o el día
     * del corte si es la primera medición). No entran al delta; solo aviso.
     *
     * @param  list<int>  $cuentaIds
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total: int,
     *   alerta: bool,
     *   suma_efecto_mayor: float
     * }
     */
    private function listarIngresosEgresosCuentasControladas(
        int $empresaId,
        array $cuentaIds,
        ?string $desdeCreatedAt,
        string $fechaCorte,
        float $tolerancia,
    ): array {
        $cuentaIds = array_values(array_filter(array_map('intval', $cuentaIds), static fn (int $id): bool => $id > 0));
        if ($cuentaIds === [] || $empresaId <= 0) {
            return ['filas' => [], 'total' => 0, 'alerta' => false, 'suma_efecto_mayor' => 0.0];
        }

        $monedaLocalId = ProveedorCuentacorrienteAplicacionLiquidacionSupport::monedaLocalId();
        $query = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('caja_movimiento as cm', 'cm.id', '=', 'a.caja_movimiento_id')
            ->leftJoin('tipotransaccion_caja as t', 't.id', '=', 'cm.tipotransaccion_caja_id')
            ->leftJoin('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cm.proveedor_id')
            ->leftJoin('usuario as u', 'u.id', '=', 'cm.usuario_id')
            ->where('a.empresa_id', $empresaId)
            ->whereIn('m.cuentacontable_id', $cuentaIds)
            ->whereNotNull('a.caja_movimiento_id')
            ->where(function ($q) {
                $q->whereNull('a.estado_aprobacion')
                    ->orWhere('a.estado_aprobacion', '!=', Asiento::ESTADO_APROBACION_RECHAZADO);
            });
        if ($desdeCreatedAt !== null && $desdeCreatedAt !== '') {
            $query->where('a.created_at', '>', $desdeCreatedAt);
        } else {
            $query->whereDate('a.created_at', $fechaCorte);
        }

        $filasRaw = $query
            ->orderBy('a.created_at')
            ->orderBy('a.id')
            ->get([
                'a.id as asiento_id',
                'a.fecha as asiento_fecha',
                'a.numeroasiento',
                'a.created_at as asiento_created_at',
                'a.observacion as asiento_obs',
                'a.anita_tipo',
                'a.anita_nro',
                'cm.id as caja_movimiento_id',
                'cm.numerotransaccion',
                'cm.fecha as caja_fecha',
                'cm.detalle',
                't.abreviatura as tipo_abreviatura',
                't.nombre as tipo_nombre',
                'm.cuentacontable_id',
                'c.codigo as cuenta_codigo',
                'c.nombre as cuenta_nombre',
                'm.monto',
                'm.moneda_id',
                'm.cotizacion',
                'p.codigo as proveedor_codigo',
                'p.nombre as proveedor_nombre',
                'u.usuario as usuario',
            ]);

        $filas = [];
        $suma = 0.0;
        foreach ($filasRaw as $fila) {
            $cot = (float) ($fila->cotizacion ?? 0);
            if ($cot == 0.0) {
                $cot = 1.0;
            }
            $monedaId = (int) ($fila->moneda_id ?: $monedaLocalId);
            $local = $monedaId > $monedaLocalId
                ? ((float) $fila->monto * $cot)
                : (float) $fila->monto;
            $efecto = round(-1 * $local, 2);
            if (abs($efecto) < 0.01) {
                continue;
            }
            $suma += $efecto;
            $filas[] = [
                'asiento_id' => (int) $fila->asiento_id,
                'caja_movimiento_id' => (int) $fila->caja_movimiento_id,
                'numerotransaccion' => (string) ($fila->numerotransaccion ?? ''),
                'fecha' => Carbon::parse((string) ($fila->caja_fecha ?? $fila->asiento_fecha))->toDateString(),
                'fecha_fmt' => Carbon::parse((string) ($fila->caja_fecha ?? $fila->asiento_fecha))->format('d/m/Y'),
                'tipo' => trim((string) ($fila->tipo_abreviatura ?: $fila->anita_tipo ?: '')),
                'tipo_nombre' => trim((string) ($fila->tipo_nombre ?? '')),
                'cuenta_codigo' => trim((string) ($fila->cuenta_codigo ?? '')),
                'cuenta_nombre' => trim((string) ($fila->cuenta_nombre ?? '')),
                'proveedor_codigo' => trim((string) ($fila->proveedor_codigo ?? '')),
                'proveedor_nombre' => trim((string) ($fila->proveedor_nombre ?? '')),
                'usuario' => trim((string) ($fila->usuario ?? '')),
                'detalle' => trim((string) ($fila->detalle ?: $fila->asiento_obs ?: '')),
                'monto_local' => round($local, 2),
                'efecto_mayor' => $efecto,
                'anita_nro' => $fila->anita_nro !== null ? (string) $fila->anita_nro : '',
                'created_at' => (string) $fila->asiento_created_at,
            ];
        }

        $suma = round($suma, 2);

        return [
            'filas' => $filas,
            'total' => count($filas),
            'alerta' => $filas !== [] && abs($suma) >= $tolerancia,
            'suma_efecto_mayor' => $suma,
        ];
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
     * @return array<int, float>
     */
    private function deudasGuardadas(int $empresaId, string $fecha): array
    {
        $filas = DB::table(self::TABLA)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha)
            ->get(['cuentacontable_id', 'deuda_local']);
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->cuentacontable_id] = (float) $fila->deuda_local;
        }

        return $out;
    }

    private function momentoSnapshot(int $empresaId, string $fecha): ?string
    {
        $valor = DB::table(self::TABLA)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fecha)
            ->max('updated_at');
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse((string) $valor)->format('Y-m-d H:i:s');
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
