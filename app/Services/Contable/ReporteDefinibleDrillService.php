<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Models\Contable\Cuentacontable;
use App\Repositories\Contable\ReporteContableRepository;
use App\Support\Contable\AsientoOrigenProcesoSupport;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleProcesador;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSaldoReader;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\DB;

/**
 * Drill de una celda del informe: rubro → cuentas → asientos → comprobante que lo originó.
 *
 * Siempre lee asientos (no el snapshot mensual) porque el objetivo es mostrar el
 * documento real detrás del número, no reproducir el agregado.
 */
class ReporteDefinibleDrillService
{
    /** Tope de asientos listados en el drill de una cuenta. */
    public const LIMITE_ASIENTOS = 300;

    public function __construct(
        private readonly ReporteContableRepository $repository,
        private readonly ReporteDefinibleProcesador $procesador,
        private readonly ReporteDefinibleSaldoReader $saldoReader,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cuentasDeRubro(int $reporteId, int $rubroId, array $filtros): array
    {
        $reporte = $this->repository->findConEstructura($reporteId);
        if ($reporte === null) {
            return ['cuentas' => [], 'total' => 0.0, 'ventana' => null, 'error' => 'Informe inexistente.'];
        }

        $ventana = $this->ventana($filtros);
        if ($ventana === null) {
            return ['cuentas' => [], 'total' => 0.0, 'ventana' => null, 'error' => 'Período inválido.'];
        }

        $codigos = $this->procesador->codigosRealesDeRubro($reporte, $rubroId);
        if ($codigos === []) {
            return [
                'cuentas' => [],
                'total' => 0.0,
                'ventana' => $ventana,
                'error' => 'El rubro no tiene cuentas reales asignadas (puede ser total, fórmula o texto).',
            ];
        }

        $movimientos = $this->saldoReader->listarMovimientos(
            $ventana['empresa_ids'],
            $ventana['fecha_desde'],
            $ventana['fecha_hasta'],
            $codigos,
            $ventana['modo_asientos'],
            $ventana['moneda_id'],
            $ventana['solo_moneda_origen'],
        );

        $detalle = $this->procesador->detalleCuentasDeRubro($reporte, $rubroId, $movimientos);
        $nombres = $this->nombresCuenta(array_keys($detalle['cuentas']));

        $cuentas = [];
        foreach ($detalle['cuentas'] as $codigo => $valor) {
            if (abs($valor) < 0.005) {
                continue;
            }
            $cuentas[] = [
                'codigo' => (int) $codigo,
                'codigo_fmt' => $this->formatearCodigo((int) $codigo),
                'nombre' => (string) ($nombres[(int) $codigo] ?? ''),
                'valor' => $valor,
            ];
        }
        usort($cuentas, fn ($a, $b) => abs($b['valor']) <=> abs($a['valor']));

        $rubro = $reporte->rubros->firstWhere('id', $rubroId);

        return [
            'rubro' => [
                'id' => $rubroId,
                'codigo' => (string) ($rubro->codigo_linea ?? ''),
                'nombre' => (string) ($rubro->nombre ?? ''),
            ],
            'cuentas' => $cuentas,
            'total' => $detalle['total'],
            'ventana' => $ventana,
            'movimientos' => count($movimientos),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function asientosDeCuenta(int $codigoCuenta, array $filtros): array
    {
        $ventana = $this->ventana($filtros);
        if ($ventana === null) {
            return ['asientos' => [], 'error' => 'Período inválido.'];
        }

        $cuenta = Cuentacontable::query()->where('codigo', (string) $codigoCuenta)->first(['id', 'codigo', 'nombre']);
        if ($cuenta === null) {
            return ['asientos' => [], 'error' => 'La cuenta '.$codigoCuenta.' no existe en el plan del ERP.'];
        }

        $filas = DB::table('asiento_movimiento as am')
            ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
            ->leftJoin('tipoasiento as t', 't.id', '=', 'a.tipoasiento_id')
            ->leftJoin('empresa as e', 'e.id', '=', 'a.empresa_id')
            ->where('am.cuentacontable_id', (int) $cuenta->id)
            ->whereIn('a.empresa_id', $ventana['empresa_ids'])
            ->whereBetween('a.fecha', [$ventana['fecha_desde'], $ventana['fecha_hasta']])
            ->orderBy('a.fecha')
            ->orderBy('a.id')
            ->limit(self::LIMITE_ASIENTOS + 1)
            ->get([
                'a.id as asiento_id', 'a.fecha', 'a.numeroasiento', 'a.observacion', 'a.empresa_id',
                'e.nombre as empresa', 't.abreviatura as tipo', 'am.monto', 'am.moneda_id', 'am.observacion as detalle',
                'a.ordencompra_id', 'a.venta_id', 'a.comprobante_proveedor_id', 'a.recepcionproveedor_id',
                'a.movimientostock_id', 'a.cobranza_id', 'a.pagoproveedor_id',
                'a.remesa_id', 'a.solicitudpago_id', 'a.caja_movimiento_id',
            ]);

        $truncado = $filas->count() > self::LIMITE_ASIENTOS;
        $filas = $filas->take(self::LIMITE_ASIENTOS);

        $asientos = [];
        $total = 0.0;
        foreach ($filas as $f) {
            $total += (float) $f->monto;
            $asientos[] = [
                'asiento_id' => (int) $f->asiento_id,
                'fecha' => (string) $f->fecha,
                'numeroasiento' => (string) $f->numeroasiento,
                'tipo' => (string) ($f->tipo ?? ''),
                'empresa' => (string) ($f->empresa ?? ''),
                'monto' => round((float) $f->monto, 2),
                'observacion' => trim((string) ($f->detalle ?: $f->observacion)),
                'origen' => $this->origenDocumento($f),
            ];
        }

        return [
            'cuenta' => [
                'codigo' => (int) $codigoCuenta,
                'codigo_fmt' => $this->formatearCodigo((int) $codigoCuenta),
                'nombre' => (string) $cuenta->nombre,
            ],
            'asientos' => $asientos,
            'total' => round($total, 2),
            'truncado' => $truncado,
            'limite' => self::LIMITE_ASIENTOS,
            'ventana' => $ventana,
        ];
    }

    /**
     * Documento de origen del asiento: el ERP guarda una FK por tipo de comprobante.
     *
     * @return array{tipo: string, id: int, url: string|null}|null
     */
    private function origenDocumento(object $fila): ?array
    {
        foreach (AsientoOrigenProcesoSupport::FKS as $campo => $meta) {
            $valor = (int) ($fila->{$campo} ?? 0);
            if ($valor <= 0) {
                continue;
            }

            return [
                'tipo' => (string) ($meta['label'] ?? $campo),
                'id' => $valor,
                'url' => $this->urlOrigenConsulta((string) ($meta['route'] ?? ''), $valor, $meta['permiso'] ?? []),
            ];
        }

        return null;
    }

    /**
     * @param  list<string>  $permisos
     */
    private function urlOrigenConsulta(string $routeName, int $id, array $permisos): ?string
    {
        if ($routeName === '' || $id <= 0) {
            return null;
        }
        if ($permisos !== []) {
            $ok = false;
            foreach ($permisos as $permiso) {
                if (can($permiso, false)) {
                    $ok = true;
                    break;
                }
            }
            if (! $ok) {
                return null;
            }
        }
        try {
            $ruta = app('router')->getRoutes()->getByName($routeName);
            if ($ruta === null || ! str_contains($ruta->uri(), '{id}')) {
                return null;
            }

            return ModoConsultaUrlSupport::route($routeName, ['id' => $id]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{empresa_ids: list<int>, fecha_desde: string, fecha_hasta: string, modo_asientos: string,
     *               moneda_id: int, solo_moneda_origen: bool}|null
     */
    private function ventana(array $filtros): ?array
    {
        $empresaIds = array_values(array_filter(array_map('intval', (array) ($filtros['empresa_ids'] ?? []))));
        if ($empresaIds === []) {
            return null;
        }

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde === '' || $hasta === '') {
            $pd = (int) ($filtros['periodo_desde'] ?? 0);
            $ph = (int) ($filtros['periodo_hasta'] ?? 0);
            if ($pd < 190001 || $ph < $pd) {
                return null;
            }
            [$desde, $hasta] = ReporteDefinibleSaldoReader::fechasDesdePeriodos($pd, $ph);
        }
        if ($desde === '' || $hasta === '') {
            return null;
        }

        return [
            'empresa_ids' => $empresaIds,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'modo_asientos' => (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion'),
            'moneda_id' => (int) ($filtros['moneda_id'] ?? CuentacontableSaldoMesSupport::monedaLocalId()),
            'solo_moneda_origen' => (bool) ($filtros['solo_moneda_origen'] ?? false),
        ];
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, string>
     */
    private function nombresCuenta(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $out = [];
        foreach (Cuentacontable::query()->whereIn('codigo', $codigos)->get(['codigo', 'nombre']) as $cuenta) {
            $out[(int) $cuenta->codigo] = (string) $cuenta->nombre;
        }

        return $out;
    }

    private function formatearCodigo(int $codigo): string
    {
        $texto = str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return substr($texto, 0, 6).'-'.substr($texto, 6);
    }
}
