<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\CierreTotemJornadaGastronomia;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación por MEDIO DE COBRO de la jornada de gastronomía Waitry:
 *
 *  - Contabilizado por medio: DEBE − HABER de las cuentas de disponibilidades/valores
 *    (prefijos 111/112/113) en los asientos del cierre.
 *  - Esperado / «Z» por medio (híbrido, comparable 1:1 con contabilizado):
 *      · Informe Z (tótem/GMEP) → Mercado Pago / QR kiosco
 *      · Cobranzas ERP del asiento 2 (excl. TOTEM) → efectivo, tarjeta, etc.
 *      · Compensación proceso → fondo fijo máquinas
 *
 * El cruce es por CÓDIGO de cuenta contable (hay códigos duplicados con distinto id, p.ej. 113010001).
 */
final class GastronomiaConciliacionMedioPagoSupport
{
    /** Prefijos de cuenta contable considerados «medio de cobro» (disponibilidades + valores a cobrar). */
    private const PREFIJOS_MEDIO_COBRO = ['111', '112', '113'];

    /**
     * Conciliación por medio para una jornada.
     *
     * @return array{
     *   jornada_id: int|null,
     *   fecha_jornada: string,
     *   medios: list<array<string, mixed>>,
     *   total_z: float,
     *   total_contabilizado_z: float,
     *   total_contabilizado: float,
     *   diff_total: float,
     *   estado: string
     * }
     */
    public function conciliarJornada(
        int $empresaId,
        string $fechaJornada,
        float $tolerancia = 0.02,
        ?CierreTotemJornadaGastronomia $cierre = null,
    ): array {
        $cierre ??= CierreTotemJornadaGastronomia::query()
            ->whereHas('jornada', function ($q) use ($empresaId, $fechaJornada) {
                $q->where('empresa_id', $empresaId)->whereDate('fecha_jornada', $fechaJornada);
            })
            ->orderByDesc('id')
            ->first();

        $z = $this->esperadoPorMedio($empresaId, $fechaJornada, $cierre);
        $contab = $this->contabilizadoPorMedio($empresaId, $fechaJornada);

        $codigos = array_values(array_unique(array_merge(array_keys($z), array_keys($contab))));
        sort($codigos);

        $medios = [];
        $totalZ = 0.0;
        $totalContab = 0.0;
        $hayDif = false;
        foreach ($codigos as $codigo) {
            $zItem = $z[$codigo] ?? null;
            $cItem = $contab[$codigo] ?? null;
            $zMonto = round((float) ($zItem['total'] ?? 0), 2);
            $cMonto = round((float) ($cItem['total'] ?? 0), 2);
            $tieneEsperado = $zItem !== null && abs($zMonto) > $tolerancia;
            $diff = round($zMonto - $cMonto, 2);
            $totalZ = round($totalZ + $zMonto, 2);
            $totalContab = round($totalContab + $cMonto, 2);

            if ($tieneEsperado || abs($cMonto) > $tolerancia) {
                $estado = abs($diff) <= $tolerancia ? 'OK' : 'DIF';
                if ($estado === 'DIF') {
                    $hayDif = true;
                }
            } else {
                $estado = 'OK';
            }

            $medios[] = [
                'cuenta_codigo' => $codigo,
                'cuenta_nombre' => (string) ($zItem['cuenta_nombre'] ?? $cItem['cuenta_nombre'] ?? ''),
                'medio_clave' => (string) ($zItem['medio_clave'] ?? $cItem['medio_clave'] ?? ''),
                'fuente_z' => (string) ($zItem['fuente'] ?? ''),
                'z' => $zMonto,
                'contabilizado' => $cMonto,
                'diff' => $diff,
                'estado' => $estado,
            ];
        }

        $diffTotal = round($totalZ - $totalContab, 2);

        return [
            'jornada_id' => $cierre?->jornada_gastronomia_id !== null ? (int) $cierre->jornada_gastronomia_id : null,
            'fecha_jornada' => $fechaJornada,
            'medios' => $medios,
            'total_z' => $totalZ,
            'total_contabilizado_z' => $totalContab,
            'total_contabilizado' => $totalContab,
            'diff_total' => $diffTotal,
            'estado' => (! $hayDif && abs($diffTotal) <= $tolerancia) ? 'OK' : 'DIF',
        ];
    }

    /**
     * Esperado («Z») por medio: Informe Z + cobranzas salón + fondo fijo proceso.
     *
     * @return array<string, array{cuenta_codigo:string,cuenta_nombre:string,medio_clave:string,total:float,fuente:string}>
     */
    public function esperadoPorMedio(
        int $empresaId,
        string $fechaJornada,
        ?CierreTotemJornadaGastronomia $cierre = null,
    ): array {
        $out = [];

        foreach ($this->zPorMedio($cierre) as $cod => $item) {
            $out[$cod] = $item + ['fuente' => 'informe_z'];
        }

        foreach ($this->esperadoSalonDesdeCobranzasErp($empresaId, $fechaJornada) as $cod => $item) {
            // Si el Informe Z ya cubre la cuenta (p.ej. MP), prevalece el Z físico.
            if (isset($out[$cod]) && abs((float) $out[$cod]['total']) > 0.005) {
                continue;
            }
            $out[$cod] = $item + ['fuente' => 'cobranza_erp'];
        }

        foreach ($this->esperadoFondoFijoDesdeProceso($empresaId, $fechaJornada) as $cod => $item) {
            if (isset($out[$cod]) && abs((float) $out[$cod]['total']) > 0.005) {
                continue;
            }
            $out[$cod] = $item + ['fuente' => 'fondo_fijo'];
        }

        return $out;
    }

    /**
     * Z por medio: agrupa los cobros del informe_z_json por cuenta contable de la cuentacaja del medio.
     *
     * @return array<string, array{cuenta_codigo:string,cuenta_nombre:string,medio_clave:string,total:float}>
     */
    public function zPorMedio(?CierreTotemJornadaGastronomia $cierre): array
    {
        if ($cierre === null) {
            return [];
        }

        $iz = is_array($cierre->informe_z_json) ? $cierre->informe_z_json : null;
        if ($iz === null) {
            return [];
        }

        $ccIds = [];
        foreach ($iz['totems'] ?? [] as $t) {
            foreach ($t['lineas'] ?? [] as $ln) {
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                if ($ccId > 0) {
                    $ccIds[$ccId] = true;
                }
            }
        }
        $mapaCc = $this->cuentaContablePorCuentacajaId(array_keys($ccIds));

        $out = [];
        foreach ($iz['totems'] ?? [] as $t) {
            foreach ($t['lineas'] ?? [] as $ln) {
                $monto = round((float) ($ln['monto'] ?? $ln['monto_informe_z'] ?? 0), 2);
                if (abs($monto) < 0.005) {
                    continue;
                }
                $ccId = (int) ($ln['cuentacaja_id'] ?? 0);
                $cuenta = $mapaCc[$ccId] ?? null;
                $codigo = $cuenta['codigo'] ?? ('CC:'.($ln['cuentacaja_codigo'] ?? $ccId));
                if (! isset($out[$codigo])) {
                    $out[$codigo] = [
                        'cuenta_codigo' => (string) $codigo,
                        'cuenta_nombre' => (string) ($cuenta['nombre'] ?? $ln['cuentacaja_nombre'] ?? ''),
                        'medio_clave' => (string) ($cuenta['medio_clave'] ?? $ln['cuentacaja_codigo'] ?? ''),
                        'total' => 0.0,
                    ];
                }
                $out[$codigo]['total'] = round($out[$codigo]['total'] + $monto, 2);
            }
        }

        return $out;
    }

    /**
     * Esperado salón: DEBE de cobranzas ERP (asiento 2 excl. TOTEM) agrupado por cuenta contable 111/112/113.
     *
     * @return array<string, array{cuenta_codigo:string,cuenta_nombre:string,medio_clave:string,total:float}>
     */
    public function esperadoSalonDesdeCobranzasErp(int $empresaId, string $fechaJornada): array
    {
        $datos = CierreJornadaFacturadoAnitaSupport::datosAsientoVentasJornadaExclTotem($empresaId, $fechaJornada);
        $lineas = $datos['debe_por_cuenta'] ?? [];
        if ($lineas === []) {
            return [];
        }

        $ccIds = [];
        foreach ($lineas as $ln) {
            $id = (int) ($ln['cuenta_id'] ?? 0);
            if ($id > 0) {
                $ccIds[$id] = true;
            }
        }
        $mapa = $this->cuentaContablePorCuentacajaId(array_keys($ccIds));

        $out = [];
        foreach ($lineas as $ln) {
            $ccId = (int) ($ln['cuenta_id'] ?? 0);
            $cuenta = $mapa[$ccId] ?? null;
            $codigo = (string) ($cuenta['codigo'] ?? '');
            if ($codigo === '' || ! $this->esCuentaMedioCobro($codigo)) {
                continue;
            }
            $monto = round((float) ($ln['debe'] ?? 0), 2);
            if (abs($monto) < 0.005) {
                continue;
            }
            if (! isset($out[$codigo])) {
                $out[$codigo] = [
                    'cuenta_codigo' => $codigo,
                    'cuenta_nombre' => (string) ($cuenta['nombre'] ?? ''),
                    'medio_clave' => (string) ($cuenta['medio_clave'] ?? ''),
                    'total' => 0.0,
                ];
            }
            $out[$codigo]['total'] = round($out[$codigo]['total'] + $monto, 2);
        }

        return $out;
    }

    /**
     * Esperado fondo fijo: −resumen_debe del asiento de compensación grabado en el snapshot del proceso.
     *
     * @return array<string, array{cuenta_codigo:string,cuenta_nombre:string,medio_clave:string,total:float}>
     */
    public function esperadoFondoFijoDesdeProceso(int $empresaId, string $fechaJornada): array
    {
        $snap = DB::table('gastronomia_cierre_jornada_proceso_snapshot')
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->orderByDesc('id')
            ->value('payload');
        if ($snap === null || $snap === '') {
            return [];
        }
        $payload = is_string($snap) ? json_decode($snap, true) : (is_array($snap) ? $snap : null);
        if (! is_array($payload)) {
            return [];
        }

        $monto = 0.0;
        foreach ($payload['asientos_proceso_grabacion']['asientos'] ?? [] as $asiento) {
            if ((string) ($asiento['codigo'] ?? '') !== 'compensacion_efectivo_no_facturado') {
                continue;
            }
            $monto = round((float) ($asiento['resumen_debe'] ?? 0), 2);
            break;
        }
        if (abs($monto) < 0.005) {
            return [];
        }

        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $ffId = (int) ($cfg['cuenta_fondo_fijo_maquinas_id'] ?? 0);
        if ($ffId <= 0) {
            return [];
        }
        $cc = DB::table('cuentacontable')->where('id', $ffId)->first(['codigo', 'nombre']);
        if ($cc === null) {
            return [];
        }
        $codigo = (string) ($cc->codigo ?? '');
        if ($codigo === '' || ! $this->esCuentaMedioCobro($codigo)) {
            return [];
        }

        return [
            $codigo => [
                'cuenta_codigo' => $codigo,
                'cuenta_nombre' => (string) ($cc->nombre ?? ''),
                'medio_clave' => 'fondo_fijo',
                'total' => round(-1 * $monto, 2),
            ],
        ];
    }

    /**
     * Contabilizado por medio: DEBE − HABER de las cuentas de cobro (111/112/113) en los asientos del cierre.
     *
     * @return array<string, array{cuenta_codigo:string,cuenta_nombre:string,medio_clave:string,total:float}>
     */
    public function contabilizadoPorMedio(int $empresaId, string $fechaJornada): array
    {
        $ids = $this->asientoIdsCierreJornada($empresaId, $fechaJornada);
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('asiento_movimiento as am')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->whereIn('am.asiento_id', $ids)
            ->groupBy('cc.codigo', 'cc.nombre')
            ->select(
                'cc.codigo',
                'cc.nombre',
                DB::raw('SUM(CASE WHEN am.monto > 0 THEN am.monto ELSE 0 END) as debe'),
                DB::raw('SUM(CASE WHEN am.monto < 0 THEN -am.monto ELSE 0 END) as haber'),
            )
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $codigo = (string) ($row->codigo ?? '');
            if (! $this->esCuentaMedioCobro($codigo)) {
                continue;
            }
            $neto = round((float) $row->debe - (float) $row->haber, 2);
            if (abs($neto) < 0.005) {
                continue;
            }
            $out[$codigo] = [
                'cuenta_codigo' => $codigo,
                'cuenta_nombre' => (string) ($row->nombre ?? ''),
                'medio_clave' => '',
                'total' => $neto,
            ];
        }

        return $out;
    }

    /**
     * IDs de asientos del cierre Waitry de la jornada (snapshot grabado o fallback por observación).
     *
     * @return list<int>
     */
    public function asientoIdsCierreJornada(int $empresaId, string $fechaJornada): array
    {
        $mapaGrabados = CierreJornadaProcesoAsientosGrabacionSupport::mapaAsientosGrabadosPorEmpresaJornada(
            $empresaId,
            $fechaJornada,
        );
        $ids = array_map('intval', array_keys($mapaGrabados));
        if ($ids !== []) {
            return $ids;
        }

        $prefijo = 'Cierre Waitry jornada '.$fechaJornada.' — ';

        return DB::table('asiento')
            ->where('empresa_id', $empresaId)
            ->where('observacion', 'like', $prefijo.'%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $cuentacajaIds
     * @return array<int, array{codigo:string,nombre:string,medio_clave:string}>
     */
    public function cuentaContablePorCuentacajaId(array $cuentacajaIds): array
    {
        $cuentacajaIds = array_values(array_filter(array_map('intval', $cuentacajaIds), fn ($id) => $id > 0));
        if ($cuentacajaIds === []) {
            return [];
        }

        $rows = DB::table('cuentacaja as cj')
            ->leftJoin('cuentacontable as cc', 'cc.id', '=', 'cj.cuentacontable_id')
            ->whereIn('cj.id', $cuentacajaIds)
            ->get(['cj.id as cuentacaja_id', 'cj.codigo as cj_codigo', 'cc.codigo as cc_codigo', 'cc.nombre as cc_nombre']);

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->cuentacaja_id] = [
                'codigo' => (string) ($row->cc_codigo ?? ''),
                'nombre' => (string) ($row->cc_nombre ?? ''),
                'medio_clave' => (string) ($row->cj_codigo ?? ''),
            ];
        }

        return $out;
    }

    private function esCuentaMedioCobro(string $codigo): bool
    {
        foreach (self::PREFIJOS_MEDIO_COBRO as $prefijo) {
            if (str_starts_with($codigo, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
