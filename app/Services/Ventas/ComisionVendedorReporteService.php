<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Vendedor;
use App\Support\Ventas\ComisionVendedorListadoFiltros;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ComisionVendedorReporteService
{
    /**
     * Detalle: vendedor → factura (sin marca), con gravado / % / comisión.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>}
     */
    public function generarDetalle(array $filtros): array
    {
        $movimientos = $this->cargarMovimientos($filtros);
        $filas = $this->aplanarDetalle($movimientos);
        $totales = $this->totalesDesdeMovimientos($movimientos);

        return ['filas' => $filas, 'totales' => $totales];
    }

    /**
     * Resumen: vendedor → cliente (neto + comisión), estilo Anita “2 - VENDEDOR”.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{filas: list<array<string, mixed>>, totales: array<string, float|int>}
     */
    public function generarResumen(array $filtros): array
    {
        $movimientos = $this->cargarMovimientos($filtros);
        $filas = $this->aplanarResumen($movimientos);
        $totales = $this->totalesDesdeMovimientos($movimientos);

        return ['filas' => $filas, 'totales' => $totales];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $total = count($filas);
        $offset = max(0, ($page - 1) * $perPage);
        $slice = array_slice($filas, $offset, $perPage);

        return new LengthAwarePaginator($slice, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function armarSubtitulo(array $filtros, string $empresaTexto, string $vendedorTexto, string $tipoTexto): string
    {
        $partes = [];
        if ($empresaTexto !== '') {
            $partes[] = 'Empresa: '.$empresaTexto;
        }
        $periodo = ComisionVendedorListadoFiltros::formatearPeriodoTexto($filtros);
        if ($periodo !== '') {
            $partes[] = 'Período: '.$periodo;
        }
        $partes[] = 'Vendedor: '.$vendedorTexto;
        $partes[] = 'Tipo: '.$tipoTexto;

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function cargarMovimientos(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        $vendedorId = (int) ($filtros['vendedor_id'] ?? 0);
        $tipoId = (int) ($filtros['tipotransaccion_id'] ?? 0);

        $query = Venta::query()
            ->with(['venta_impuestos', 'tipotransacciones', 'clientes', 'puntoventas.empresas'])
            ->whereNotNull('vendedor_id')
            ->where('vendedor_id', '>', 0)
            ->whereHas('puntoventas', static function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId);
            })
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->where(function ($q) {
                $q->whereNull('nombre')
                    ->orWhereRaw("UPPER(TRIM(nombre)) NOT LIKE 'ANULADA%'");
            })
            ->whereHas('tipotransacciones', static function ($q) {
                $q->whereRaw("UPPER(TRIM(COALESCE(abreviatura, ''))) <> 'PRE'");
            })
            ->orderBy('vendedor_id')
            ->orderBy('fecha')
            ->orderBy('id');

        if ($vendedorId > 0) {
            $query->where('vendedor_id', $vendedorId);
        }
        if ($tipoId > 0) {
            $query->where('tipotransaccion_id', $tipoId);
        }

        /** @var Collection<int, Venta> $ventas */
        $ventas = $query->get();
        if ($ventas->isEmpty()) {
            return [];
        }

        $vendedores = Vendedor::query()
            ->whereIn('id', $ventas->pluck('vendedor_id')->unique()->filter()->all())
            ->get()
            ->keyBy('id');

        $movimientos = [];
        foreach ($ventas as $venta) {
            if (IvaVentasDesgloseSupport::esAnulada($venta)) {
                continue;
            }

            $vendedor = $vendedores->get((int) $venta->vendedor_id);
            if ($vendedor === null) {
                continue;
            }

            $montos = IvaVentasDesgloseSupport::columnasDesdeVenta($venta);
            $porcentaje = (float) ($vendedor->comisionventa ?? 0);
            $aplicaSobre = trim((string) ($vendedor->aplicasobre ?? 'Sobre Neto'));
            $base = $this->baseComision($montos, $aplicaSobre);
            $comision = round($base * $porcentaje / 100.0, 2);

            $cliente = $venta->clientes;
            $cuit = trim((string) ($venta->nroinscripcion ?? ''));
            if ($cuit === '') {
                $cuit = trim((string) ($cliente->numerodocumento ?? ''));
            }

            [$fechaVista, $fechaYmd] = $this->formatearFechaVenta($venta->fecha ?? null);

            $movimientos[] = [
                'venta_id' => (int) $venta->id,
                'fecha' => $fechaVista,
                'fecha_ymd' => $fechaYmd,
                'vendedor_id' => (int) $vendedor->id,
                'vendedor_codigo' => trim((string) ($vendedor->codigo ?? '')),
                'vendedor_nombre' => trim((string) ($vendedor->nombre ?? '')),
                'porcentaje' => $porcentaje,
                'aplica_sobre' => $aplicaSobre,
                'cliente_id' => (int) ($venta->cliente_id ?? 0),
                'cliente_codigo' => trim((string) ($cliente->codigo ?? '')),
                'cliente_nombre' => trim((string) ($cliente->nombre ?? $venta->nombre ?? '')),
                'tipo' => strtoupper(trim((string) ($venta->tipotransacciones?->abreviatura ?? ''))),
                'comprobante' => $this->textoComprobante($venta),
                'gravado' => (float) ($montos['neto_gravado'] ?? 0),
                'total' => (float) ($montos['total'] ?? 0),
                'base_comision' => $base,
                'comision' => $comision,
                'cuit' => $cuit,
                'nombreempresa' => trim((string) ($venta->puntoventas?->empresas?->nombre ?? '')),
            ];
        }

        usort($movimientos, static function (array $a, array $b): int {
            $cmp = strnatcasecmp(
                (string) ($a['vendedor_codigo'] ?? ''),
                (string) ($b['vendedor_codigo'] ?? ''),
            );
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmpFecha = strcmp((string) ($a['fecha_ymd'] ?? ''), (string) ($b['fecha_ymd'] ?? ''));
            if ($cmpFecha !== 0) {
                return $cmpFecha;
            }

            return strcmp((string) ($a['comprobante'] ?? ''), (string) ($b['comprobante'] ?? ''));
        });

        return $movimientos;
    }

    /**
     * @param  array<string, float>  $montos
     */
    private function baseComision(array $montos, string $aplicaSobre): float
    {
        $aplica = mb_strtoupper(trim($aplicaSobre));
        if ($aplica === 'B' || str_contains($aplica, 'BRUTO')) {
            return (float) ($montos['total'] ?? 0);
        }

        // Sobre Neto (default): neto gravado + no gravado + exento (base imponible sin IVA).
        return (float) ($montos['neto_gravado'] ?? 0)
            + (float) ($montos['no_gravado'] ?? 0)
            + (float) ($montos['exento'] ?? 0);
    }

    private function textoComprobante(Venta $venta): string
    {
        $codigo = trim((string) ($venta->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        $abrev = strtoupper(trim((string) ($venta->tipotransacciones?->abreviatura ?? '')));
        $pv = str_pad((string) ($venta->puntoventas?->codigo ?? '0'), 4, '0', STR_PAD_LEFT);
        $nro = str_pad((string) ($venta->numerocomprobante ?? '0'), 8, '0', STR_PAD_LEFT);

        return trim($abrev.' '.$pv.'-'.$nro);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function formatearFechaVenta(mixed $fecha): array
    {
        if ($fecha instanceof \Carbon\CarbonInterface) {
            return [$fecha->format('d/m/Y'), $fecha->format('Y-m-d')];
        }

        $raw = trim((string) $fecha);
        if ($raw === '') {
            return ['', ''];
        }

        try {
            $c = \Carbon\Carbon::parse($raw);

            return [$c->format('d/m/Y'), $c->format('Y-m-d')];
        } catch (\Throwable) {
            return [$raw, $raw];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function aplanarDetalle(array $movimientos): array
    {
        $filas = [];
        $vendedorActual = null;
        $acumGravado = 0.0;
        $acumComision = 0.0;
        $acumTotal = 0.0;
        $cantVend = 0;
        $metaVend = [];

        $flush = function () use (&$filas, &$vendedorActual, &$acumGravado, &$acumComision, &$acumTotal, &$cantVend, &$metaVend): void {
            if ($vendedorActual === null) {
                return;
            }
            $filas[] = [
                'tipo_fila' => 'total_vendedor',
                'descripcion' => 'Totales de: '.$metaVend['vendedor_codigo'].' '.$metaVend['vendedor_nombre'],
                'vendedor_id' => $metaVend['vendedor_id'],
                'vendedor_codigo' => $metaVend['vendedor_codigo'],
                'vendedor_nombre' => $metaVend['vendedor_nombre'],
                'porcentaje' => $metaVend['porcentaje'],
                'aplica_sobre' => $metaVend['aplica_sobre'],
                'gravado' => round($acumGravado, 2),
                'total' => round($acumTotal, 2),
                'comision' => round($acumComision, 2),
                'cantidad' => $cantVend,
                'nombreempresa' => $metaVend['nombreempresa'],
            ];
            $vendedorActual = null;
            $acumGravado = 0.0;
            $acumComision = 0.0;
            $acumTotal = 0.0;
            $cantVend = 0;
            $metaVend = [];
        };

        foreach ($movimientos as $mov) {
            $clave = (int) $mov['vendedor_id'];
            if ($vendedorActual !== $clave) {
                $flush();
                $vendedorActual = $clave;
                $metaVend = [
                    'vendedor_id' => $clave,
                    'vendedor_codigo' => $mov['vendedor_codigo'],
                    'vendedor_nombre' => $mov['vendedor_nombre'],
                    'porcentaje' => $mov['porcentaje'],
                    'aplica_sobre' => $mov['aplica_sobre'],
                    'nombreempresa' => $mov['nombreempresa'],
                ];
                $filas[] = [
                    'tipo_fila' => 'header_vendedor',
                    'descripcion' => 'Vendedor: '.$mov['vendedor_codigo'].' '.$mov['vendedor_nombre'],
                    'vendedor_id' => $clave,
                    'vendedor_codigo' => $mov['vendedor_codigo'],
                    'vendedor_nombre' => $mov['vendedor_nombre'],
                    'porcentaje' => $mov['porcentaje'],
                    'aplica_sobre' => $mov['aplica_sobre'],
                    'nombreempresa' => $mov['nombreempresa'],
                ];
            }

            $filas[] = array_merge($mov, ['tipo_fila' => 'detalle']);
            $acumGravado += (float) $mov['gravado'];
            $acumComision += (float) $mov['comision'];
            $acumTotal += (float) $mov['total'];
            $cantVend++;
        }
        $flush();

        if ($filas !== []) {
            $tot = $this->totalesDesdeMovimientos($movimientos);
            $filas[] = [
                'tipo_fila' => 'total_final',
                'descripcion' => 'TOTAL GENERAL',
                'gravado' => $tot['gravado'],
                'total' => $tot['total'],
                'comision' => $tot['comision'],
                'cantidad' => $tot['cantidad_comprobantes'],
                'nombreempresa' => $movimientos[0]['nombreempresa'] ?? '',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    private function aplanarResumen(array $movimientos): array
    {
        /** @var array<string, array<string, mixed>> $porVendedorCliente */
        $porVendedorCliente = [];
        foreach ($movimientos as $mov) {
            $clave = ((int) $mov['vendedor_id']).'|'.((int) $mov['cliente_id']);
            if (! isset($porVendedorCliente[$clave])) {
                $porVendedorCliente[$clave] = [
                    'vendedor_id' => (int) $mov['vendedor_id'],
                    'vendedor_codigo' => $mov['vendedor_codigo'],
                    'vendedor_nombre' => $mov['vendedor_nombre'],
                    'porcentaje' => $mov['porcentaje'],
                    'aplica_sobre' => $mov['aplica_sobre'],
                    'cliente_id' => (int) $mov['cliente_id'],
                    'cliente_codigo' => $mov['cliente_codigo'],
                    'cliente_nombre' => $mov['cliente_nombre'],
                    'cuit' => $mov['cuit'],
                    'gravado' => 0.0,
                    'total' => 0.0,
                    'comision' => 0.0,
                    'cantidad' => 0,
                    'nombreempresa' => $mov['nombreempresa'],
                ];
            }
            $porVendedorCliente[$clave]['gravado'] += (float) $mov['gravado'];
            $porVendedorCliente[$clave]['total'] += (float) $mov['total'];
            $porVendedorCliente[$clave]['comision'] += (float) $mov['comision'];
            $porVendedorCliente[$clave]['cantidad']++;
            if ($porVendedorCliente[$clave]['cuit'] === '' && $mov['cuit'] !== '') {
                $porVendedorCliente[$clave]['cuit'] = $mov['cuit'];
            }
        }

        $clientes = array_values($porVendedorCliente);
        usort($clientes, static function (array $a, array $b): int {
            $cmp = strnatcasecmp((string) $a['vendedor_codigo'], (string) $b['vendedor_codigo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strnatcasecmp((string) $a['cliente_codigo'], (string) $b['cliente_codigo']);
        });

        $filas = [];
        $vendedorActual = null;
        $acumGravado = 0.0;
        $acumComision = 0.0;
        $acumTotal = 0.0;
        $cantVend = 0;
        $metaVend = [];

        $flush = function () use (&$filas, &$vendedorActual, &$acumGravado, &$acumComision, &$acumTotal, &$cantVend, &$metaVend): void {
            if ($vendedorActual === null) {
                return;
            }
            $filas[] = [
                'tipo_fila' => 'total_vendedor',
                'descripcion' => 'Totales de: '.$metaVend['vendedor_codigo'].' '.$metaVend['vendedor_nombre'],
                'vendedor_id' => $metaVend['vendedor_id'],
                'vendedor_codigo' => $metaVend['vendedor_codigo'],
                'vendedor_nombre' => $metaVend['vendedor_nombre'],
                'porcentaje' => $metaVend['porcentaje'],
                'aplica_sobre' => $metaVend['aplica_sobre'],
                'gravado' => round($acumGravado, 2),
                'total' => round($acumTotal, 2),
                'comision' => round($acumComision, 2),
                'cantidad' => $cantVend,
                'nombreempresa' => $metaVend['nombreempresa'],
            ];
            $vendedorActual = null;
            $acumGravado = 0.0;
            $acumComision = 0.0;
            $acumTotal = 0.0;
            $cantVend = 0;
            $metaVend = [];
        };

        foreach ($clientes as $cli) {
            $clave = (int) $cli['vendedor_id'];
            if ($vendedorActual !== $clave) {
                $flush();
                $vendedorActual = $clave;
                $metaVend = [
                    'vendedor_id' => $clave,
                    'vendedor_codigo' => $cli['vendedor_codigo'],
                    'vendedor_nombre' => $cli['vendedor_nombre'],
                    'porcentaje' => $cli['porcentaje'],
                    'aplica_sobre' => $cli['aplica_sobre'],
                    'nombreempresa' => $cli['nombreempresa'],
                ];
                $filas[] = [
                    'tipo_fila' => 'header_vendedor',
                    'descripcion' => 'Vendedor: '.$cli['vendedor_codigo'].' '.$cli['vendedor_nombre'],
                    'vendedor_id' => $clave,
                    'vendedor_codigo' => $cli['vendedor_codigo'],
                    'vendedor_nombre' => $cli['vendedor_nombre'],
                    'porcentaje' => $cli['porcentaje'],
                    'aplica_sobre' => $cli['aplica_sobre'],
                    'nombreempresa' => $cli['nombreempresa'],
                ];
            }

            $filas[] = array_merge($cli, [
                'tipo_fila' => 'cliente',
                'gravado' => round((float) $cli['gravado'], 2),
                'total' => round((float) $cli['total'], 2),
                'comision' => round((float) $cli['comision'], 2),
            ]);
            $acumGravado += (float) $cli['gravado'];
            $acumComision += (float) $cli['comision'];
            $acumTotal += (float) $cli['total'];
            $cantVend += (int) $cli['cantidad'];
        }
        $flush();

        if ($filas !== []) {
            $tot = $this->totalesDesdeMovimientos($movimientos);
            $filas[] = [
                'tipo_fila' => 'total_final',
                'descripcion' => 'TOTAL GENERAL',
                'gravado' => $tot['gravado'],
                'total' => $tot['total'],
                'comision' => $tot['comision'],
                'cantidad' => $tot['cantidad_comprobantes'],
                'nombreempresa' => $movimientos[0]['nombreempresa'] ?? '',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, float|int>
     */
    private function totalesDesdeMovimientos(array $movimientos): array
    {
        $gravado = 0.0;
        $total = 0.0;
        $comision = 0.0;
        $vendedores = [];
        foreach ($movimientos as $mov) {
            $gravado += (float) $mov['gravado'];
            $total += (float) $mov['total'];
            $comision += (float) $mov['comision'];
            $vendedores[(int) $mov['vendedor_id']] = true;
        }

        return [
            'gravado' => round($gravado, 2),
            'total' => round($total, 2),
            'comision' => round($comision, 2),
            'cantidad_comprobantes' => count($movimientos),
            'cantidad_vendedores' => count($vendedores),
        ];
    }
}
