<?php

declare(strict_types=1);

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquinavendingCaja;
use App\Models\Configuracion\Empresa;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Reporte Contable: ventas vending por jornada y punto de venta (máquina),
 * con apertura por medio de pago desde los movimientos de caja de la rendición.
 */
final class MaquinavendingDiarioPuntoventaReporteSupport
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

        $rendiciones = $this->cargarRendiciones($empresaId, $desde, $hasta, $puntoventaId);

        /** @var array<string, array<int, list<RendicionMaquinavendingCaja>>> $porDiaPv */
        $porDiaPv = [];
        foreach ($rendiciones as $r) {
            $fecha = $r->maquinavendingRendicion?->fecha_jornada?->format('Y-m-d')
                ?? $r->fecharendicion?->format('Y-m-d');
            if ($fecha === null || $fecha === '' || $fecha < $desde || $fecha > $hasta) {
                continue;
            }
            $pv = $r->puntoventaCae ?? $r->puntoventaCaea;
            $pvId = (int) ($pv->id ?? $r->puntoventa_cae_id ?? 0);
            if ($pvId <= 0) {
                $pvId = -1;
            }
            $porDiaPv[$fecha][$pvId][] = $r;
        }

        ksort($porDiaPv);

        $dias = [];
        $resumen = $this->totalesBloqueVacio();
        /** @var array<int, array<string, mixed>> $mediosGlobal */
        $mediosGlobal = [];

        foreach ($porDiaPv as $fecha => $porPv) {
            $puntoventas = [];
            $totDia = $this->totalesBloqueVacio();

            ksort($porPv);
            foreach ($porPv as $grupo) {
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
     * @return Collection<int, RendicionMaquinavendingCaja>
     */
    private function cargarRendiciones(int $empresaId, string $desde, string $hasta, ?int $puntoventaId): Collection
    {
        return RendicionMaquinavendingCaja::query()
            ->with([
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'maquinavendingRendicion:id,fecha_jornada',
                'movimientos.cuentacaja:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId)
            ->when($puntoventaId !== null && $puntoventaId > 0, function ($q) use ($puntoventaId) {
                $q->where('puntoventa_cae_id', $puntoventaId);
            })
            ->where(function ($w) use ($desde, $hasta) {
                $w->whereHas('maquinavendingRendicion', function ($mr) use ($desde, $hasta) {
                    $mr->whereDate('fecha_jornada', '>=', $desde)
                        ->whereDate('fecha_jornada', '<=', $hasta);
                })->orWhere(function ($q) use ($desde, $hasta) {
                    $q->whereDoesntHave('maquinavendingRendicion')
                        ->whereDate('fecharendicion', '>=', $desde)
                        ->whereDate('fecharendicion', '<=', $hasta);
                });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<RendicionMaquinavendingCaja>  $rendiciones
     * @return array<string, mixed>
     */
    private function armarFilaPuntoventa(array $rendiciones): array
    {
        $ventaBruta = 0.0;
        $totalNc = 0.0;
        $ventaNeta = 0.0;
        $totalCobrado = 0.0;
        $totalInvitaciones = 0.0;
        $cantRend = 0;
        /** @var array<int, array<string, mixed>> $medios */
        $medios = [];
        $pvCodigo = '';
        $pvNombre = '';
        $pvId = 0;

        foreach ($rendiciones as $r) {
            $pv = $r->puntoventaCae ?? $r->puntoventaCaea;
            $pvId = (int) ($pv->id ?? $r->puntoventa_cae_id ?? 0);
            $pvCodigo = trim((string) ($pv->codigo ?? $pvCodigo));
            $pvNombre = trim((string) ($pv->nombre ?? $pvNombre));

            $factura = round((float) ($r->totalfactura ?? 0), 2);
            $nc = round((float) ($r->totalnotacredito ?? 0), 2);
            $cobrado = round((float) ($r->totalcobrado ?? 0), 2);
            $invitacion = round((float) ($r->totalinvitacion ?? 0), 2);

            $ventaBruta += $factura + $nc;
            $totalNc += $nc;
            $ventaNeta += $factura;
            $totalCobrado += $cobrado;
            $totalInvitaciones += $invitacion;
            $cantRend++;

            foreach ($r->movimientos ?? [] as $mov) {
                $ccId = (int) ($mov->cuentacaja_id ?? 0);
                if ($ccId <= 0) {
                    continue;
                }
                $cc = $mov->cuentacaja;
                if (! isset($medios[$ccId])) {
                    $medios[$ccId] = [
                        'cuentacaja_id' => $ccId,
                        'codigo' => (string) ($cc->codigo ?? ''),
                        'nombre' => trim((string) ($cc->nombre ?? '')),
                        'cobros' => 0.0,
                        'devoluciones_nc' => 0.0,
                        'neto' => 0.0,
                    ];
                }
                $montoMedio = round((float) ($mov->monto ?? 0), 2);
                if ($montoMedio < 0) {
                    $medios[$ccId]['devoluciones_nc'] += abs($montoMedio);
                } else {
                    $medios[$ccId]['cobros'] += $montoMedio;
                }
                $medios[$ccId]['neto'] += $montoMedio;
            }
        }

        $ventaBruta = round($ventaBruta, 2);
        $totalNc = round($totalNc, 2);
        $ventaNeta = round($ventaNeta, 2);
        $totalCobrado = round($totalCobrado, 2);
        $totalInvitaciones = round($totalInvitaciones, 2);
        $cobrable = round($ventaNeta - $totalInvitaciones, 2);
        $diferencia = round($totalCobrado - $cobrable, 2);

        return [
            'puntoventa_id' => $pvId,
            'pv_codigo' => $pvCodigo !== '' ? $pvCodigo : '—',
            'pv_nombre' => $pvNombre !== '' ? $pvNombre : '—',
            'cantidad_rendiciones' => $cantRend,
            'venta_bruta' => $ventaBruta,
            'cantidad_nc' => 0,
            'total_nc' => $totalNc,
            'venta_neta' => $ventaNeta,
            'total_cobrado' => $totalCobrado,
            'total_invitaciones' => $totalInvitaciones,
            'diferencia_cobranza' => $diferencia,
            'cuadre_ok' => abs($diferencia) <= self::TOLERANCIA,
            'medios' => $this->normalizarMedios($medios),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    private function totalesBloqueVacio(): array
    {
        return [
            'cantidad_rendiciones' => 0,
            'venta_bruta' => 0.0,
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
        $acum['cantidad_rendiciones'] = (int) $acum['cantidad_rendiciones'] + (int) ($fila['cantidad_rendiciones'] ?? 0);
        $acum['venta_bruta'] = (float) $acum['venta_bruta'] + (float) ($fila['venta_bruta'] ?? 0);
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
}
