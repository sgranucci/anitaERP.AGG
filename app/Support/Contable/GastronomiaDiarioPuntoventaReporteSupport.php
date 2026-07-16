<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionService;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte Contable: ventas gastronomía por jornada y punto de venta,
 * con apertura por medio de pago (cobros de facturas vs devoluciones NC).
 */
final class GastronomiaDiarioPuntoventaReporteSupport
{
    private const TOLERANCIA = 0.02;

    /**
     * @return array{
     *   empresa_id: int,
     *   empresa_nombre: string,
     *   fecha_desde: string,
     *   fecha_hasta: string,
     *   dias: list<array<string, mixed>>,
     *   resumen: array<string, mixed>,
     *   medios_global: list<array<string, mixed>>
     * }
     */
    public function generar(int $empresaId, string $fechaDesde, string $fechaHasta, ?int $puntoventaId = null): array
    {
        $empresa = Empresa::query()->findOrFail($empresaId);
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $emisiones = $this->cargarEmisiones($empresaId, $desde, $hasta, $puntoventaId);
        /** @var array<string, array<int, list<VentaGastronomiaEmision>>> $porDiaPv */
        $porDiaPv = [];

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if ($venta === null) {
                continue;
            }
            $fechaRaw = $venta->fechajornada ?? $venta->fecha ?? null;
            if ($fechaRaw === null || $fechaRaw === '') {
                continue;
            }
            try {
                $fecha = Carbon::parse($fechaRaw)->toDateString();
            } catch (\Throwable) {
                continue;
            }
            if ($fecha < $desde || $fecha > $hasta) {
                continue;
            }
            $pvId = (int) ($venta->puntoventa_id ?? 0);
            if ($pvId <= 0) {
                continue;
            }
            $porDiaPv[$fecha][$pvId][] = $em;
        }

        ksort($porDiaPv);

        $dias = [];
        $resumen = $this->resumenVacio();
        /** @var array<int, array<string, mixed>> $mediosGlobal */
        $mediosGlobal = [];

        foreach ($porDiaPv as $fecha => $porPv) {
            $puntoventas = [];
            $totDia = $this->totalesBloqueVacio();

            ksort($porPv);
            foreach ($porPv as $pvId => $grupo) {
                $fila = $this->armarFilaPuntoventa($grupo);
                $puntoventas[] = $fila;
                $this->acumularBloque($totDia, $fila);
                $this->acumularBloque($resumen, $fila);
                foreach ($fila['medios'] as $medio) {
                    $this->acumularMedio($mediosGlobal, $medio);
                }
            }

            usort($puntoventas, static fn (array $a, array $b): int => strcmp(
                (string) ($a['pv_codigo'] ?? ''),
                (string) ($b['pv_codigo'] ?? ''),
            ));

            $dias[] = [
                'fecha_jornada' => $fecha,
                'fecha_jornada_fmt' => Carbon::parse($fecha)->format('d/m/Y'),
                'puntoventas' => $puntoventas,
                'totales' => $this->redondearBloque($totDia),
                'cantidad_pv' => count($puntoventas),
            ];
        }

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'dias' => $dias,
            'resumen' => $this->redondearBloque($resumen) + [
                'cantidad_dias' => count($dias),
                'cantidad_filas_pv' => array_sum(array_map(
                    static fn (array $d): int => count($d['puntoventas'] ?? []),
                    $dias,
                )),
            ],
            'medios_global' => $this->normalizarMedios($mediosGlobal),
        ];
    }

    /**
     * @return Collection<int, VentaGastronomiaEmision>
     */
    private function cargarEmisiones(int $empresaId, string $desde, string $hasta, ?int $puntoventaId): Collection
    {
        $codigosExcluir = config('rendicion_gastronomia_anita.auditoria_diaria.puntoventa_codigos_solo_anita', []);

        return VentaGastronomiaEmision::query()
            ->whereHas('venta', function ($v) use ($empresaId, $desde, $hasta, $puntoventaId, $codigosExcluir) {
                $v->where(function ($fecha) use ($desde, $hasta) {
                    $fecha->where(function ($j) use ($desde, $hasta) {
                        $j->whereDate('fechajornada', '>=', $desde)
                            ->whereDate('fechajornada', '<=', $hasta);
                    })->orWhere(function ($legacy) use ($desde, $hasta) {
                        $legacy->whereNull('fechajornada')
                            ->whereDate('fecha', '>=', $desde)
                            ->whereDate('fecha', '<=', $hasta);
                    });
                })
                    ->whereHas('puntoventas', function ($pv) use ($empresaId, $puntoventaId, $codigosExcluir) {
                        $pv->where('empresa_id', $empresaId);
                        if ($puntoventaId !== null && $puntoventaId > 0) {
                            $pv->where('puntoventa.id', $puntoventaId);
                        }
                        if ($codigosExcluir !== []) {
                            $pv->whereNotIn('puntoventa.codigo', $codigosExcluir);
                        }
                        $pv->whereRaw('LOWER(puntoventa.nombre) NOT LIKE ?', ['%estacionamiento%'])
                            ->whereRaw('LOWER(puntoventa.nombre) NOT LIKE ?', ['%estac.%']);
                    });
            })
            ->whereDoesntHave('venta.estacionamientoEmision')
            ->with([
                'venta.puntoventas:id,codigo,nombre,empresa_id',
                'venta.venta_impuestos',
                'venta.tipotransacciones',
                'venta.cobranzasDirectas',
                'venta.caja_movimientos.cobranzas',
            ])
            ->get();
    }

    /**
     * @param  list<VentaGastronomiaEmision>  $emisiones
     * @return array<string, mixed>
     */
    private function armarFilaPuntoventa(array $emisiones): array
    {
        $importeMinimo = GastronomiaFacturacionService::IMPORTE_MINIMO_FACTURA;
        $ventaBruta = 0.0;
        $ventaNeto = 0.0;
        $ventaIva = 0.0;
        $totalNc = 0.0;
        $ventaNeta = 0.0;
        $totalCobrado = 0.0;
        $totalInvitaciones = 0.0;
        $cantFacturas = 0;
        $cantNc = 0;
        $cantInvitaciones = 0;
        /** @var array<int, array<string, mixed>> $medios */
        $medios = [];
        $pcs = [];
        $pvCodigo = '';
        $pvNombre = '';
        $pvId = 0;

        foreach ($emisiones as $em) {
            $venta = $em->venta;
            if ($venta === null) {
                continue;
            }

            $pv = $venta->puntoventas;
            $pvId = (int) ($pv->id ?? $venta->puntoventa_id ?? 0);
            $pvCodigo = trim((string) ($pv->codigo ?? $pvCodigo));
            $pvNombre = trim((string) ($pv->nombre ?? $pvNombre));
            $pc = trim((string) ($em->identificador_pc ?? ''));
            if ($pc !== '') {
                $pcs[$pc] = true;
            }

            $desglose = $this->desgloseNetoIva($venta);
            $monto = $desglose['total'];
            $esNc = ($em->venta_factura_origen_id ?? null) !== null;
            $cobranzas = GastronomiaVentaDetalleSupport::cobranzasDeVenta($venta);
            $lineasMedios = GastronomiaVentaDetalleSupport::mediosPagoPorCobranza($cobranzas);
            $cobradoVenta = 0.0;
            foreach ($lineasMedios as $lineas) {
                foreach ($lineas as $linea) {
                    $cobradoVenta += (float) ($linea->monto ?? 0);
                }
            }
            $cobradoVenta = round($cobradoVenta, 2);
            $totalCobrado += $cobradoVenta;
            $ventaNeta += $monto;

            if ($esNc) {
                $totalNc += abs($monto);
                $cantNc++;
            } else {
                $ventaBruta += $monto;
                $ventaNeto += $desglose['neto'];
                $ventaIva += $desglose['iva'];
                $cantFacturas++;
                if ($this->esInvitacion($monto, $cobradoVenta, $importeMinimo)) {
                    $totalInvitaciones += $monto;
                    $cantInvitaciones++;
                }
            }

            foreach ($lineasMedios as $lineas) {
                foreach ($lineas as $linea) {
                    $ccId = (int) ($linea->cuentacaja_id ?? 0);
                    if ($ccId <= 0) {
                        continue;
                    }
                    if (! isset($medios[$ccId])) {
                        $medios[$ccId] = [
                            'cuentacaja_id' => $ccId,
                            'codigo' => (string) ($linea->codigo ?? ''),
                            'nombre' => trim((string) ($linea->nombre ?? $linea->cuenta ?? '')),
                            'cobros' => 0.0,
                            'devoluciones_nc' => 0.0,
                            'neto' => 0.0,
                        ];
                    }
                    $montoMedio = round((float) ($linea->monto ?? 0), 2);
                    if ($esNc) {
                        // NC: cobranza negativa → devolución en valor absoluto para la columna.
                        $medios[$ccId]['devoluciones_nc'] += abs($montoMedio);
                    } else {
                        $medios[$ccId]['cobros'] += $montoMedio;
                    }
                    $medios[$ccId]['neto'] += $montoMedio;
                }
            }
        }

        $ventaBruta = round($ventaBruta, 2);
        $ventaNeto = round($ventaNeto, 2);
        $ventaIva = round($ventaIva, 2);
        $totalNc = round($totalNc, 2);
        $ventaNeta = round($ventaNeta, 2);
        $totalCobrado = round($totalCobrado, 2);
        $totalInvitaciones = round($totalInvitaciones, 2);
        $cobrable = round($ventaNeta - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $cobrable, 2);

        $pcsLista = array_keys($pcs);
        sort($pcsLista);

        return [
            'puntoventa_id' => $pvId,
            'pv_codigo' => $pvCodigo !== '' ? $pvCodigo : '—',
            'pv_nombre' => $pvNombre !== '' ? $pvNombre : '—',
            'identificadores_pc' => $pcsLista,
            'cantidad_facturas' => $cantFacturas,
            'venta_bruta' => $ventaBruta,
            'venta_neto' => $ventaNeto,
            'venta_iva' => $ventaIva,
            'cantidad_nc' => $cantNc,
            'total_nc' => $totalNc,
            'venta_neta' => $ventaNeta,
            'total_cobrado' => $totalCobrado,
            'total_invitaciones' => $totalInvitaciones,
            'cantidad_invitaciones' => $cantInvitaciones,
            'diferencia_cobranza' => $diferencia,
            'cuadre_ok' => abs($diferencia) <= self::TOLERANCIA,
            'medios' => $this->normalizarMedios($medios),
        ];
    }

    /**
     * @return array{neto: float, iva: float, total: float}
     */
    private function desgloseNetoIva(Venta $venta): array
    {
        $desglose = IvaVentasDesgloseSupport::columnasDesdeVenta($venta);
        $iva = abs((float) ($desglose['iva'] ?? 0));
        $total = abs((float) ($desglose['total'] ?? 0));
        if ($total <= 0) {
            $total = abs((float) ($venta->total ?? 0));
        }

        return [
            'neto' => round($total - $iva, 2),
            'iva' => round($iva, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function resumenVacio(): array
    {
        return $this->totalesBloqueVacio();
    }

    /**
     * @return array<string, float|int>
     */
    private function totalesBloqueVacio(): array
    {
        return [
            'cantidad_facturas' => 0,
            'venta_bruta' => 0.0,
            'venta_neto' => 0.0,
            'venta_iva' => 0.0,
            'cantidad_nc' => 0,
            'total_nc' => 0.0,
            'venta_neta' => 0.0,
            'total_cobrado' => 0.0,
            'total_invitaciones' => 0.0,
            'diferencia_cobranza' => 0.0,
        ];
    }

    /**
     * @param  array<string, float|int>  $acum
     * @param  array<string, mixed>  $fila
     */
    private function acumularBloque(array &$acum, array $fila): void
    {
        $acum['cantidad_facturas'] = (int) $acum['cantidad_facturas'] + (int) ($fila['cantidad_facturas'] ?? 0);
        $acum['venta_bruta'] = (float) $acum['venta_bruta'] + (float) ($fila['venta_bruta'] ?? 0);
        $acum['venta_neto'] = (float) $acum['venta_neto'] + (float) ($fila['venta_neto'] ?? 0);
        $acum['venta_iva'] = (float) $acum['venta_iva'] + (float) ($fila['venta_iva'] ?? 0);
        $acum['cantidad_nc'] = (int) $acum['cantidad_nc'] + (int) ($fila['cantidad_nc'] ?? 0);
        $acum['total_nc'] = (float) $acum['total_nc'] + (float) ($fila['total_nc'] ?? 0);
        $acum['venta_neta'] = (float) $acum['venta_neta'] + (float) ($fila['venta_neta'] ?? 0);
        $acum['total_cobrado'] = (float) $acum['total_cobrado'] + (float) ($fila['total_cobrado'] ?? 0);
        $acum['total_invitaciones'] = (float) $acum['total_invitaciones'] + (float) ($fila['total_invitaciones'] ?? 0);
        $acum['diferencia_cobranza'] = (float) $acum['diferencia_cobranza'] + (float) ($fila['diferencia_cobranza'] ?? 0);
    }

    /**
     * @param  array<string, float|int>  $bloque
     * @return array<string, float|int>
     */
    private function redondearBloque(array $bloque): array
    {
        $bloque['venta_bruta'] = round((float) $bloque['venta_bruta'], 2);
        $bloque['venta_neto'] = round((float) $bloque['venta_neto'], 2);
        $bloque['venta_iva'] = round((float) $bloque['venta_iva'], 2);
        $bloque['total_nc'] = round((float) $bloque['total_nc'], 2);
        $bloque['venta_neta'] = round((float) $bloque['venta_neta'], 2);
        $bloque['total_cobrado'] = round((float) $bloque['total_cobrado'], 2);
        $bloque['total_invitaciones'] = round((float) $bloque['total_invitaciones'], 2);
        $bloque['diferencia_cobranza'] = round((float) $bloque['diferencia_cobranza'], 2);

        return $bloque;
    }

    /**
     * @param  array<int, array<string, mixed>>  $acum
     * @param  array<string, mixed>  $medio
     */
    private function acumularMedio(array &$acum, array $medio): void
    {
        $ccId = (int) ($medio['cuentacaja_id'] ?? 0);
        if ($ccId <= 0) {
            return;
        }
        if (! isset($acum[$ccId])) {
            $acum[$ccId] = [
                'cuentacaja_id' => $ccId,
                'codigo' => (string) ($medio['codigo'] ?? ''),
                'nombre' => (string) ($medio['nombre'] ?? ''),
                'cobros' => 0.0,
                'devoluciones_nc' => 0.0,
                'neto' => 0.0,
            ];
        }
        $acum[$ccId]['cobros'] += (float) ($medio['cobros'] ?? 0);
        $acum[$ccId]['devoluciones_nc'] += (float) ($medio['devoluciones_nc'] ?? 0);
        $acum[$ccId]['neto'] += (float) ($medio['neto'] ?? 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $mapa
     * @return list<array<string, mixed>>
     */
    private function normalizarMedios(array $mapa): array
    {
        foreach ($mapa as &$m) {
            $m['cobros'] = round((float) ($m['cobros'] ?? 0), 2);
            $m['devoluciones_nc'] = round((float) ($m['devoluciones_nc'] ?? 0), 2);
            $m['neto'] = round((float) ($m['neto'] ?? 0), 2);
        }
        unset($m);
        $lista = array_values($mapa);
        usort($lista, static fn (array $a, array $b): int => strcmp(
            (string) ($a['nombre'] ?? ''),
            (string) ($b['nombre'] ?? ''),
        ));

        return $lista;
    }

    private function esInvitacion(float $montoVenta, float $totalCobrado, float $importeMinimo): bool
    {
        return abs($montoVenta - $importeMinimo) < 0.001 && abs($totalCobrado) < 0.001;
    }
}
