<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorIvaLineasDesdeOcSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Repara IVA mal imputado a 114010-002 (u otro Indirecto) cuando la OC exige
 * reubicación (FNB→411) o prorrateo multi-CC (FPB/FPS). Solo asientos de octubre.
 *
 * No descontabiliza: los comprobantes pueden tener pagos. Ajusta conceptos ERP,
 * movimientos del asiento y re-sincroniza ctamov Anita.
 */
final class ComprobanteProveedorIvaProrrateoReclasificarService
{
    private const DESDE = '2026-10-01';

    private const HASTA = '2026-10-31';

    public function __construct(
        private PrecargaProveedorIvaLineasDesdeOcSupport $ivaLineasDesdeOc,
        private ComprobanteProveedorAsientoService $asientoService,
        private AsientoRepositoryInterface $asientoRepository,
    ) {}

    /**
     * @param  list<int>|null  $comprobanteIds  null = detectar todos los de octubre afectados
     * @return list<array<string, mixed>>
     */
    public function previsualizar(?array $comprobanteIds = null): array
    {
        $out = [];
        foreach ($this->candidatos($comprobanteIds) as $plan) {
            $out[] = $this->resumenPlan($plan);
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $comprobanteIds
     * @return array{procesados: int, omitidos: int, errores: list<string>, detalle: list<array<string, mixed>>}
     */
    public function aplicar(?array $comprobanteIds = null): array
    {
        $procesados = 0;
        $omitidos = 0;
        $errores = [];
        $detalle = [];

        foreach ($this->candidatos($comprobanteIds) as $plan) {
            $resumen = $this->resumenPlan($plan);
            try {
                $this->aplicarPlan($plan);
                $procesados++;
                $resumen['estado'] = 'aplicado';
                $detalle[] = $resumen;
            } catch (Throwable $e) {
                $omitidos++;
                $msg = 'CP '.$plan['comprobante']->id.': '.$e->getMessage();
                $errores[] = $msg;
                $resumen['estado'] = 'error';
                $resumen['error'] = $e->getMessage();
                $detalle[] = $resumen;
                Log::error('compras.iva_prorrateo_reclasificar.fallo', [
                    'comprobante_id' => $plan['comprobante']->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'procesados' => $procesados,
            'omitidos' => $omitidos,
            'errores' => $errores,
            'detalle' => $detalle,
        ];
    }

    /**
     * @param  list<int>|null  $comprobanteIds
     * @return list<array{
     *   comprobante: Comprobante_Proveedor,
     *   asiento: Asiento,
     *   lineas_actuales: list<array<string, mixed>>,
     *   lineas_objetivo: list<array<string, mixed>>,
     *   iva_actual: list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>,
     *   iva_objetivo: list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>,
     *   modo: string,
     *   avisos: list<string>
     * }>
     */
    private function candidatos(?array $comprobanteIds): array
    {
        $q = Comprobante_Proveedor::query()
            ->with([
                'tipotransaccion_compras:id,abreviatura',
                'ordencompras.ordencompra_articulos.centrocostos_destino:id,codigo,tipoiva',
                'ordencompras.centrocostos:id,codigo,tipoiva',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'asientos.asiento_movimientos.cuentacontables',
                'proveedores:id,nombre',
            ])
            ->where('estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->whereNotNull('asiento_id')
            ->whereDate('fechaiva', '>=', self::DESDE)
            ->whereDate('fechaiva', '<=', self::HASTA);

        if ($comprobanteIds !== null && $comprobanteIds !== []) {
            $q->whereIn('id', array_map('intval', $comprobanteIds));
        }

        $planes = [];
        foreach ($q->orderBy('id')->get() as $cp) {
            $asiento = $cp->asientos;
            if (! $asiento instanceof Asiento) {
                continue;
            }
            $fechaAsiento = $asiento->fecha instanceof \DateTimeInterface
                ? $asiento->fecha->format('Y-m-d')
                : substr((string) $asiento->fecha, 0, 10);
            if ($fechaAsiento < self::DESDE || $fechaAsiento > self::HASTA) {
                continue;
            }

            $lineasActuales = [];
            foreach ($cp->comprobante_proveedor_conceptos as $c) {
                $ci = $c->concepto_ivacompras;
                if (! $ci) {
                    continue;
                }
                $lineasActuales[] = [
                    'concepto_ivacompra_id' => (int) $ci->id,
                    'codigo_concepto_anita' => (int) $ci->codigo,
                    'monto' => round((float) $c->monto, 2),
                    'orden' => (int) ($c->orden ?? 0),
                    'centrocosto_id' => ((int) ($c->centrocosto_id ?? 0)) ?: null,
                    'tipoconcepto' => strtoupper(trim((string) ($ci->tipoconcepto ?? ''))),
                ];
            }

            $abrev = strtoupper(trim((string) ($cp->tipotransaccion_compras?->abreviatura ?? '')));
            $corr = $this->ivaLineasDesdeOc->corregir($lineasActuales, $cp->ordencompras, $abrev);
            if (! $corr['cambio']) {
                continue;
            }

            $empresaId = (int) ($cp->empresa_id ?? 0);
            $ivaActual = $this->resumenIva($lineasActuales, $empresaId);
            $ivaObjetivo = $this->resumenIva($corr['lineas'], $empresaId);
            if ($ivaActual === [] || $this->mismaFirmaIva($ivaActual, $ivaObjetivo)) {
                continue;
            }

            // Solo si el dinero cambia de cuenta, y el tipo del CP cuadra con la OC
            // (evita 311→321 misma cuenta, o FNS tipado mal sobre OC Indirecta).
            if (! $this->pareceMalImputado($ivaActual, $ivaObjetivo, $corr['modo'], $cp, $abrev)) {
                continue;
            }

            $planes[] = [
                'comprobante' => $cp,
                'asiento' => $asiento,
                'lineas_actuales' => $lineasActuales,
                'lineas_objetivo' => $corr['lineas'],
                'iva_actual' => $ivaActual,
                'iva_objetivo' => $ivaObjetivo,
                'modo' => $corr['modo'],
                'avisos' => $corr['avisos'],
            ];
        }

        return $planes;
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    private function resumenPlan(array $plan): array
    {
        /** @var Comprobante_Proveedor $cp */
        $cp = $plan['comprobante'];
        $abrev = (string) ($cp->tipotransaccion_compras?->abreviatura ?? '');

        return [
            'comprobante_id' => (int) $cp->id,
            'asiento_id' => (int) $cp->asiento_id,
            'fechaiva' => substr((string) $cp->fechaiva, 0, 10),
            'tipo' => $abrev,
            'comprobante' => sprintf(
                '%s %s-%s-%s',
                $abrev,
                (string) $cp->letra,
                (int) $cp->sucursal,
                (int) $cp->numerocomprobante
            ),
            'oc' => (string) ($cp->ordencompras?->numeroordencompra ?? ''),
            'proveedor' => (string) ($cp->proveedores?->nombre ?? ''),
            'modo' => $plan['modo'],
            'iva_actual' => array_map(
                static fn (array $r): string => $r['codigo'].':'.$r['cuenta'].'='.number_format($r['monto'], 2, '.', ''),
                $plan['iva_actual']
            ),
            'iva_objetivo' => array_map(
                static fn (array $r): string => $r['codigo'].':'.$r['cuenta'].'='.number_format($r['monto'], 2, '.', ''),
                $plan['iva_objetivo']
            ),
            'avisos' => $plan['avisos'],
        ];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function aplicarPlan(array $plan): void
    {
        /** @var Comprobante_Proveedor $cp */
        $cp = $plan['comprobante'];
        /** @var Asiento $asiento */
        $asiento = $plan['asiento'];
        $empresaId = (int) $cp->empresa_id;

        DB::transaction(function () use ($plan, $cp, $asiento, $empresaId) {
            $this->reemplazarConceptosIva($cp, $plan['lineas_actuales'], $plan['lineas_objetivo'], $empresaId);
            $this->reemplazarMovimientosIva($asiento, $plan['iva_actual'], $plan['iva_objetivo']);
        });

        $asientoFresh = Asiento::query()->with('asiento_movimientos')->find((int) $asiento->id);
        if ($asientoFresh === null) {
            throw new RuntimeException('Asiento '.$asiento->id.' no encontrado tras reclasificar.');
        }
        $payload = $this->asientoRepository->armarPayloadAnitaDesdeModelo($asientoFresh);
        $this->asientoService->sincronizarCtamovAnita($cp->fresh(['tipotransaccion_compras']), $payload);

        Log::info('compras.iva_prorrateo_reclasificar.ok', [
            'comprobante_id' => $cp->id,
            'asiento_id' => $asiento->id,
            'modo' => $plan['modo'],
            'iva_objetivo' => $plan['iva_objetivo'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lineasActuales
     * @param  list<array<string, mixed>>  $lineasObjetivo
     */
    private function reemplazarConceptosIva(
        Comprobante_Proveedor $cp,
        array $lineasActuales,
        array $lineasObjetivo,
        int $empresaId,
    ): void {
        $idsIActuales = [];
        foreach ($lineasActuales as $l) {
            if (($l['tipoconcepto'] ?? '') === 'I') {
                $idsIActuales[] = (int) $l['concepto_ivacompra_id'];
            }
        }
        $idsIActuales = array_values(array_unique(array_filter($idsIActuales)));

        EloquentAuditDeleteSupport::each(
            Comprobante_Proveedor_Concepto::query()
                ->where('comprobante_proveedor_id', $cp->id)
                ->whereIn('concepto_ivacompra_id', $idsIActuales !== [] ? $idsIActuales : [0])
        );

        $maxOrden = (int) Comprobante_Proveedor_Concepto::query()
            ->where('comprobante_proveedor_id', $cp->id)
            ->max('orden');

        foreach ($lineasObjetivo as $l) {
            $conceptoId = (int) ($l['concepto_ivacompra_id'] ?? 0);
            $concepto = Concepto_Ivacompra::query()->find($conceptoId);
            if (! $concepto || strtoupper(trim((string) $concepto->tipoconcepto)) !== 'I') {
                continue;
            }
            $monto = round((float) ($l['monto'] ?? 0), 2);
            if (abs($monto) < 0.0001) {
                continue;
            }
            $maxOrden++;
            Comprobante_Proveedor_Concepto::query()->create([
                'comprobante_proveedor_id' => $cp->id,
                'concepto_ivacompra_id' => $conceptoId,
                'orden' => $maxOrden,
                'monto' => $monto,
                'cuentacontabledebe_id' => $concepto->cuentacontableDebeIdParaEmpresa($empresaId) ?: null,
                'centrocosto_id' => ((int) ($l['centrocosto_id'] ?? 0)) ?: null,
            ]);
        }
    }

    /**
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $ivaActual
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $ivaObjetivo
     */
    private function reemplazarMovimientosIva(Asiento $asiento, array $ivaActual, array $ivaObjetivo): void
    {
        $asiento->loadMissing('asiento_movimientos');
        $movimientos = $asiento->asiento_movimientos;
        $usados = [];
        /** @var list<Asiento_Movimiento> $aBorrar */
        $aBorrar = [];

        foreach ($ivaActual as $fila) {
            $mov = $movimientos->first(function (Asiento_Movimiento $m) use ($fila, $usados) {
                if (isset($usados[$m->id])) {
                    return false;
                }
                if ((int) $m->cuentacontable_id !== (int) $fila['cuenta_id']) {
                    return false;
                }

                return abs(round((float) $m->monto, 2) - round((float) $fila['monto'], 2)) < 0.015;
            });
            if (! $mov) {
                throw new RuntimeException(
                    'No se encontró movimiento asiento cuenta '.$fila['cuenta']
                    .' monto '.number_format($fila['monto'], 2, '.', '')
                    .' (asiento '.$asiento->id.').'
                );
            }
            $usados[$mov->id] = true;
            $aBorrar[] = $mov;
        }

        $plantilla = $aBorrar[0] ?? null;
        if ($plantilla === null) {
            throw new RuntimeException('Sin movimientos IVA a reemplazar en asiento '.$asiento->id);
        }

        $sumaAntes = round((float) $movimientos->sum('monto'), 2);
        $sumaIvaAntes = round(array_sum(array_map(static fn (array $r) => $r['monto'], $ivaActual)), 2);
        $sumaIvaDespues = round(array_sum(array_map(static fn (array $r) => $r['monto'], $ivaObjetivo)), 2);
        if (abs($sumaIvaAntes - $sumaIvaDespues) > 0.02) {
            throw new RuntimeException(
                'El IVA objetivo ('.$sumaIvaDespues.') no cuadra con el actual ('.$sumaIvaAntes.').'
            );
        }

        foreach ($aBorrar as $mov) {
            EloquentAuditDeleteSupport::each(
                Asiento_Movimiento::query()->whereKey($mov->id)
            );
        }

        foreach ($ivaObjetivo as $fila) {
            Asiento_Movimiento::query()->create([
                'asiento_id' => $asiento->id,
                'cuentacontable_id' => $fila['cuenta_id'],
                'centrocosto_id' => $plantilla->centrocosto_id,
                'monto' => round((float) $fila['monto'], 2),
                'moneda_id' => $plantilla->moneda_id,
                'cotizacion' => $plantilla->cotizacion,
                'observacion' => $plantilla->observacion,
                'comprobante_proveedor_id' => $plantilla->comprobante_proveedor_id,
                'comprobante_proveedor_concepto_id' => null,
                'concepto_ivacompra_id' => null,
                'anita_tipo' => $plantilla->anita_tipo,
                'anita_letra' => $plantilla->anita_letra,
                'anita_sucursal' => $plantilla->anita_sucursal,
                'anita_nro' => $plantilla->anita_nro,
                'nro_ordencompra' => $plantilla->nro_ordencompra,
            ]);
        }

        $sumaDespues = round((float) Asiento_Movimiento::query()->where('asiento_id', $asiento->id)->sum('monto'), 2);
        if (abs($sumaAntes - $sumaDespues) > 0.02) {
            throw new RuntimeException(
                'El asiento '.$asiento->id.' desbalanceó al reclasificar IVA (antes '.$sumaAntes.', después '.$sumaDespues.').'
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>
     */
    private function resumenIva(array $lineas, int $empresaId): array
    {
        $ids = [];
        foreach ($lineas as $l) {
            $id = (int) ($l['concepto_ivacompra_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $conceptos = Concepto_Ivacompra::query()
            ->with(['concepto_ivacompra_empresas'])
            ->whereIn('id', array_keys($ids))
            ->get()
            ->keyBy('id');

        $agrupado = [];
        foreach ($lineas as $l) {
            $id = (int) ($l['concepto_ivacompra_id'] ?? 0);
            $c = $conceptos->get($id);
            if (! $c || strtoupper(trim((string) $c->tipoconcepto)) !== 'I') {
                continue;
            }
            $monto = round((float) ($l['monto'] ?? 0), 2);
            if (abs($monto) < 0.0001) {
                continue;
            }
            $cuentaId = $c->cuentacontableDebeIdParaEmpresa($empresaId);
            $codigoCta = (string) (DB::table('cuentacontable')->where('id', $cuentaId)->value('codigo') ?? '');
            $key = $id.'|'.$cuentaId;
            if (! isset($agrupado[$key])) {
                $agrupado[$key] = [
                    'codigo' => (int) $c->codigo,
                    'monto' => 0.0,
                    'cuenta_id' => $cuentaId,
                    'cuenta' => $codigoCta,
                ];
            }
            $agrupado[$key]['monto'] = round($agrupado[$key]['monto'] + $monto, 2);
        }

        $out = array_values($agrupado);
        usort($out, static fn (array $a, array $b): int => $a['codigo'] <=> $b['codigo']);

        return $out;
    }

    /**
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $a
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $b
     */
    private function mismaFirmaIva(array $a, array $b): bool
    {
        $norm = static function (array $rows): array {
            $out = [];
            foreach ($rows as $r) {
                $out[] = $r['codigo'].':'.$r['cuenta_id'].':'.number_format($r['monto'], 2, '.', '');
            }
            sort($out);

            return $out;
        };

        return $norm($a) === $norm($b);
    }

    /**
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $actual
     * @param  list<array{codigo:int, monto:float, cuenta_id:int, cuenta:string}>  $objetivo
     */
    private function pareceMalImputado(
        array $actual,
        array $objetivo,
        string $modo,
        Comprobante_Proveedor $cp,
        string $abrevCp,
    ): bool {
        $normCta = static fn (string $c): string => (string) preg_replace('/\D+/', '', $c);

        $cuentasActual = [];
        $cuentasObj = [];
        $montoPorCtaActual = [];
        $montoPorCtaObj = [];
        foreach ($actual as $r) {
            $k = $normCta($r['cuenta']);
            $cuentasActual[$k] = true;
            $montoPorCtaActual[$k] = round(($montoPorCtaActual[$k] ?? 0) + $r['monto'], 2);
        }
        foreach ($objetivo as $r) {
            $k = $normCta($r['cuenta']);
            $cuentasObj[$k] = true;
            $montoPorCtaObj[$k] = round(($montoPorCtaObj[$k] ?? 0) + $r['monto'], 2);
        }

        // Sin movimiento de plata entre cuentas (ej. 311→321 en 114010-002): no tocar.
        ksort($montoPorCtaActual);
        ksort($montoPorCtaObj);
        if ($montoPorCtaActual === $montoPorCtaObj) {
            return false;
        }

        if ($modo === 'prorrateo') {
            return isset($cuentasActual['114010002'])
                && (isset($cuentasObj['521130001']) || isset($cuentasObj['114010010']));
        }

        // Reubicación: el tipo del CP debe coincidir con el que resuelve la OC.
        $oc = $cp->ordencompras;
        if ($oc === null) {
            return false;
        }
        $familia = match ($abrevCp[0] ?? '') {
            'C' => 'NC',
            'D' => 'ND',
            default => 'FC',
        };
        $abrevOc = PrecargaProveedorAbreviaturaTipoSupport::abreviaturaDesdeOrdencompra($oc, $familia);
        if ($abrevOc === null || strtoupper($abrevOc) !== $abrevCp) {
            return false;
        }

        return isset($cuentasActual['114010002'])
            && (isset($cuentasObj['521130001']) || isset($cuentasObj['114010010']));
    }
}
