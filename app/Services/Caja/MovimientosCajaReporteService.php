<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\MovimientosCajaReporteFiltros;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Informe de movimientos de cobros/pagos/IEV (Anita l-movim.c) sobre caja_movimiento ERP.
 */
class MovimientosCajaReporteService
{
    /** @var list<string> */
    private const ABREV_COBRO = ['COB'];

    /** @var list<string> */
    private const ABREV_PAGO = ['OPP'];

    /** @var list<string> */
    private const ABREV_IEV = ['ING', 'EGR', 'DEV'];

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total_registros: int,
     *   total_ingreso: float,
     *   total_egreso: float,
     *   total_neto: float,
     *   subtitulo: string,
     *   formato: string,
     *   filtros: array<string, mixed>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
        $desde = (string) ($filtros['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        $formato = (string) ($filtros['formato'] ?? MovimientosCajaReporteFiltros::FORMATO_RESUMIDO);
        $completo = $formato === MovimientosCajaReporteFiltros::FORMATO_COMPLETO;

        $nombresEmpresa = $empresaIds === []
            ? []
            : Empresa::query()->whereIn('id', $empresaIds)->orderBy('nombre')->pluck('nombre')->all();

        $query = Caja_Movimiento::query()
            ->with([
                'caja_movimiento_cuentacajas.cuentacajas',
                'caja_movimiento_cuentacajas.monedas',
                'caja_movimiento_estados',
                'tipotransaccioncajas',
                'empresas',
                'clientes',
                'proveedores',
                'conceptogastos',
            ])
            ->whereBetween('fecha', [$desde, $hasta]);

        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        $this->aplicarFiltroTipo($query, (string) ($filtros['tipo_listado'] ?? MovimientosCajaReporteFiltros::TIPO_TODOS));
        $this->aplicarFiltroCuenta($query, (int) ($filtros['cuentacaja_id'] ?? 0));
        $this->aplicarOrden($query, (string) ($filtros['orden'] ?? MovimientosCajaReporteFiltros::ORDEN_FECHA));

        /** @var \Illuminate\Support\Collection<int, Caja_Movimiento> $movimientos */
        $movimientos = $query->get();

        $estadoFiltro = (string) ($filtros['estado'] ?? MovimientosCajaReporteFiltros::ESTADO_ACTIVOS);
        $cuentaFiltro = (int) ($filtros['cuentacaja_id'] ?? 0);
        $consolidar = ! empty($filtros['consolidar_empresas']);
        $multiEmpresa = count($empresaIds) > 1;

        $detalles = [];
        foreach ($movimientos as $mov) {
            $estado = $this->estadoActual($mov);
            if (! $this->estadoCoincide($estado, $estadoFiltro)) {
                continue;
            }

            $lineas = $this->lineasMovimiento($mov);
            if ($lineas === []) {
                $lineas = [[
                    'cuenta_id' => 0,
                    'cuenta_codigo' => '',
                    'cuenta_nombre' => 'Sin cuenta de caja',
                    'moneda_id' => 1,
                    'cotizacion' => 1.0,
                    'monto' => 0.0,
                    'monto_mn' => 0.0,
                    'ingreso' => 0.0,
                    'egreso' => 0.0,
                ]];
            }

            foreach ($lineas as $linea) {
                if ($cuentaFiltro > 0 && (int) $linea['cuenta_id'] !== $cuentaFiltro) {
                    continue;
                }

                $detalles[] = [
                    'tipo_fila' => 'dato',
                    'id' => (int) $mov->id,
                    'nombreempresa' => (string) ($mov->empresas->nombre ?? ''),
                    'empresa_id' => (int) $mov->empresa_id,
                    'fecha' => $this->fechaDmy($mov->fecha),
                    'fecha_ymd' => $this->fechaYmd($mov->fecha),
                    'numero' => (string) ($mov->numerotransaccion ?? ''),
                    'tipo_abrev' => (string) ($mov->tipotransaccioncajas->abreviatura ?? ''),
                    'tipo_nombre' => (string) ($mov->tipotransaccioncajas->nombre ?? ''),
                    'clipro_codigo' => $this->cliproCodigo($mov),
                    'clipro_nombre' => $this->cliproNombre($mov),
                    'concepto' => (string) ($mov->conceptogastos->nombre ?? ''),
                    'detalle' => (string) ($mov->detalle ?? ''),
                    'estado' => $estado,
                    'estado_nombre' => $this->estadoNombre($estado),
                    'cuenta_id' => (int) $linea['cuenta_id'],
                    'cuenta_codigo' => (string) $linea['cuenta_codigo'],
                    'cuenta_nombre' => (string) $linea['cuenta_nombre'],
                    'cuenta_etiqueta' => $this->etiquetaCuenta(
                        (string) $linea['cuenta_codigo'],
                        (string) $linea['cuenta_nombre']
                    ),
                    'ingreso' => (float) $linea['ingreso'],
                    'egreso' => (float) $linea['egreso'],
                    'total' => round((float) $linea['ingreso'] - (float) $linea['egreso'], 2),
                    'monto_mn' => (float) $linea['monto_mn'],
                    'cobranza_id' => (int) ($mov->cobranza_id ?? 0),
                    'pagoproveedor_id' => (int) ($mov->pagoproveedor_id ?? 0),
                    'link_tipo' => $this->linkTipo($mov),
                    'observacion_linea' => $completo ? (string) ($linea['observacion'] ?? '') : '',
                ];
            }
        }

        usort($detalles, function (array $a, array $b) use ($filtros): int {
            $cmpCuenta = strcmp((string) $a['cuenta_codigo'], (string) $b['cuenta_codigo']);
            if ($cmpCuenta !== 0) {
                return $cmpCuenta;
            }
            $cmpNombre = strcmp((string) $a['cuenta_nombre'], (string) $b['cuenta_nombre']);
            if ($cmpNombre !== 0) {
                return $cmpNombre;
            }
            $orden = (string) ($filtros['orden'] ?? MovimientosCajaReporteFiltros::ORDEN_FECHA);
            if ($orden === MovimientosCajaReporteFiltros::ORDEN_NUMERO) {
                $c = strcmp((string) $a['numero'], (string) $b['numero']);
                if ($c !== 0) {
                    return $c;
                }
            } elseif ($orden === MovimientosCajaReporteFiltros::ORDEN_TIPO) {
                $c = strcmp((string) $a['tipo_abrev'], (string) $b['tipo_abrev']);
                if ($c !== 0) {
                    return $c;
                }
            }
            $c = strcmp((string) $a['fecha_ymd'], (string) $b['fecha_ymd']);
            if ($c !== 0) {
                return $c;
            }

            return ((int) $a['id']) <=> ((int) $b['id']);
        });

        $filas = $this->aplanarConCortePorCuenta($detalles, $consolidar, $multiEmpresa);
        $datos = array_values(array_filter(
            $filas,
            static fn (array $f) => ($f['tipo_fila'] ?? '') === 'dato'
        ));
        $totalIngreso = round(array_sum(array_map(static fn (array $f) => (float) ($f['ingreso'] ?? 0), $datos)), 2);
        $totalEgreso = round(array_sum(array_map(static fn (array $f) => (float) ($f['egreso'] ?? 0), $datos)), 2);

        return [
            'filas' => $filas,
            'total_registros' => count($datos),
            'total_ingreso' => $totalIngreso,
            'total_egreso' => $totalEgreso,
            'total_neto' => round($totalIngreso - $totalEgreso, 2),
            'subtitulo' => MovimientosCajaReporteFiltros::subtitulo($filtros, $nombresEmpresa),
            'formato' => $formato,
            'filtros' => $filtros,
        ];
    }

    /**
     * Corte de control por cuenta de caja (cabecera + detalle + subtotal), patrón Anita / remesa.
     *
     * @param  list<array<string, mixed>>  $detalles
     * @return list<array<string, mixed>>
     */
    private function aplanarConCortePorCuenta(array $detalles, bool $consolidar, bool $multiEmpresa): array
    {
        $filas = [];
        $totalIngreso = 0.0;
        $totalEgreso = 0.0;
        $grupo = null;
        $grupoEtiqueta = '';
        $acumIngreso = 0.0;
        $acumEgreso = 0.0;
        $nombreEmpresaGrupo = '';

        $flushGrupo = function () use (&$filas, &$grupo, &$grupoEtiqueta, &$acumIngreso, &$acumEgreso, &$nombreEmpresaGrupo): void {
            if ($grupo === null) {
                return;
            }
            $filas[] = [
                'tipo_fila' => 'total_cuenta',
                'cuenta_etiqueta' => 'Total '.$grupoEtiqueta,
                'clipro_nombre' => 'Total '.$grupoEtiqueta,
                'ingreso' => round($acumIngreso, 2),
                'egreso' => round($acumEgreso, 2),
                'total' => round($acumIngreso - $acumEgreso, 2),
                'nombreempresa' => $nombreEmpresaGrupo,
            ];
        };

        foreach ($detalles as $mov) {
            $clave = ($multiEmpresa || ! $consolidar ? ((int) $mov['empresa_id']).'|' : '')
                .(string) $mov['cuenta_codigo'].'|'.(string) $mov['cuenta_nombre'];
            if ($grupo !== $clave) {
                $flushGrupo();
                $grupo = $clave;
                $grupoEtiqueta = (string) ($mov['cuenta_etiqueta'] ?? $this->etiquetaCuenta(
                    (string) $mov['cuenta_codigo'],
                    (string) $mov['cuenta_nombre']
                ));
                if (($multiEmpresa || ! $consolidar) && trim((string) ($mov['nombreempresa'] ?? '')) !== '') {
                    $grupoEtiqueta = trim((string) $mov['nombreempresa']).' — '.$grupoEtiqueta;
                }
                $acumIngreso = 0.0;
                $acumEgreso = 0.0;
                $nombreEmpresaGrupo = (string) ($mov['nombreempresa'] ?? '');
                $filas[] = [
                    'tipo_fila' => 'grupo',
                    'cuenta_etiqueta' => $grupoEtiqueta,
                    'cuenta_id' => (int) ($mov['cuenta_id'] ?? 0),
                    'cuenta_codigo' => (string) ($mov['cuenta_codigo'] ?? ''),
                    'nombreempresa' => $nombreEmpresaGrupo,
                ];
            }

            $filas[] = $mov;
            $acumIngreso = round($acumIngreso + (float) $mov['ingreso'], 2);
            $acumEgreso = round($acumEgreso + (float) $mov['egreso'], 2);
            $totalIngreso = round($totalIngreso + (float) $mov['ingreso'], 2);
            $totalEgreso = round($totalEgreso + (float) $mov['egreso'], 2);
        }
        $flushGrupo();

        if ($detalles !== []) {
            $filas[] = [
                'tipo_fila' => 'total_general',
                'cuenta_etiqueta' => 'Total general',
                'clipro_nombre' => 'Total general',
                'ingreso' => $totalIngreso,
                'egreso' => $totalEgreso,
                'total' => round($totalIngreso - $totalEgreso, 2),
                'nombreempresa' => $detalles[0]['nombreempresa'] ?? '',
            ];
        }

        return $filas;
    }

    private function etiquetaCuenta(string $codigo, string $nombre): string
    {
        $codigo = trim($codigo);
        $nombre = trim($nombre);
        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' — '.$nombre;
        }

        return $codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : 'Sin cuenta');
    }

    /**
     * @param  list<int>  $empresaIds
     * @return \Illuminate\Support\Collection<int, Cuentacaja>
     */
    public function cuentasFiltro(array $empresaIds)
    {
        $q = Cuentacaja::query()->orderBy('codigo');
        if ($empresaIds !== []) {
            $q->where(function ($w) use ($empresaIds) {
                $w->whereNull('empresa_id')->orWhereIn('empresa_id', $empresaIds);
            });
        }

        return $q->get(['id', 'codigo', 'nombre']);
    }

    private function aplicarFiltroTipo(Builder $query, string $tipo): void
    {
        $abrevs = match ($tipo) {
            MovimientosCajaReporteFiltros::TIPO_COBRO => self::ABREV_COBRO,
            MovimientosCajaReporteFiltros::TIPO_PAGO => self::ABREV_PAGO,
            MovimientosCajaReporteFiltros::TIPO_IEV => self::ABREV_IEV,
            default => [],
        };
        if ($abrevs === []) {
            return;
        }
        $query->whereHas('tipotransaccioncajas', static function ($q) use ($abrevs) {
            $q->whereIn('abreviatura', $abrevs);
        });
    }

    private function aplicarFiltroCuenta(Builder $query, int $cuentacajaId): void
    {
        if ($cuentacajaId <= 0) {
            return;
        }
        $query->whereHas('caja_movimiento_cuentacajas', static function ($q) use ($cuentacajaId) {
            $q->where('cuentacaja_id', $cuentacajaId);
        });
    }

    private function aplicarOrden(Builder $query, string $orden): void
    {
        match ($orden) {
            MovimientosCajaReporteFiltros::ORDEN_NUMERO => $query
                ->orderBy('numerotransaccion')
                ->orderBy('fecha')
                ->orderBy('id'),
            MovimientosCajaReporteFiltros::ORDEN_TIPO => $query
                ->orderBy(
                    DB::raw('(select abreviatura from tipotransaccion_caja where tipotransaccion_caja.id = caja_movimiento.tipotransaccion_caja_id)')
                )
                ->orderBy('fecha')
                ->orderBy('id'),
            default => $query->orderBy('fecha')->orderBy('id'),
        };
    }

    private function estadoActual(Caja_Movimiento $mov): string
    {
        $estados = $mov->caja_movimiento_estados;
        if ($estados === null || $estados->isEmpty()) {
            return 'A';
        }
        $ultimo = $estados->sortByDesc(static function ($e) {
            $f = self::fechaYmdEstatica($e->fecha ?? null);

            return $f.'|'.str_pad((string) $e->id, 12, '0', STR_PAD_LEFT);
        })->first();

        return (string) ($ultimo->estado ?? 'A');
    }

    private function fechaDmy(mixed $fecha): string
    {
        $ymd = self::fechaYmdEstatica($fecha);
        if ($ymd === '' || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) {
            return '';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }

    private function fechaYmd(mixed $fecha): string
    {
        return self::fechaYmdEstatica($fecha);
    }

    private static function fechaYmdEstatica(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $s, $m)) {
            return $m[1];
        }

        return $s;
    }

    private function estadoCoincide(string $estado, string $filtro): bool
    {
        return match ($filtro) {
            MovimientosCajaReporteFiltros::ESTADO_ACTIVOS => $estado === 'A',
            MovimientosCajaReporteFiltros::ESTADO_ANULADOS => in_array($estado, ['R', 'B', 'S'], true),
            default => true,
        };
    }

    private function estadoNombre(string $estado): string
    {
        return match ($estado) {
            'A' => 'Activo',
            'R' => 'Revertido',
            'S' => 'Suspendido',
            'B' => 'Baja',
            default => $estado,
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineasMovimiento(Caja_Movimiento $mov): array
    {
        $lineas = [];

        foreach ($mov->caja_movimiento_cuentacajas as $linea) {
            $monto = (float) ($linea->monto ?? 0);
            $monedaId = (int) ($linea->moneda_id ?? 1);
            $cotizacion = (float) ($linea->cotizacion ?? 1);
            $coef = $monedaId > 1 ? ($cotizacion > 0 ? $cotizacion : 1.0) : 1.0;
            $mn = round($monto * $coef, 2);
            $ingreso = $mn > 0 ? $mn : 0.0;
            $egreso = $mn < 0 ? abs($mn) : 0.0;
            $cuenta = $linea->cuentacajas;
            $lineas[] = [
                'cuenta_id' => (int) ($linea->cuentacaja_id ?? 0),
                'cuenta_codigo' => (string) ($cuenta->codigo ?? ''),
                'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
                'moneda_id' => $monedaId,
                'cotizacion' => $cotizacion,
                'monto' => $monto,
                'monto_mn' => $mn,
                'ingreso' => $ingreso,
                'egreso' => $egreso,
                'observacion' => (string) ($linea->observacion ?? ''),
            ];
        }

        return $lineas;
    }

    private function cliproCodigo(Caja_Movimiento $mov): string
    {
        if ($mov->cliente_id) {
            return (string) ($mov->clientes->codigo ?? '');
        }
        if ($mov->proveedor_id) {
            return (string) ($mov->proveedores->codigo ?? '');
        }

        return '';
    }

    private function cliproNombre(Caja_Movimiento $mov): string
    {
        if ($mov->cliente_id) {
            return (string) ($mov->clientes->nombre ?? '');
        }
        if ($mov->proveedor_id) {
            return (string) ($mov->proveedores->nombre ?? '');
        }

        return '';
    }

    private function linkTipo(Caja_Movimiento $mov): string
    {
        if ((int) ($mov->cobranza_id ?? 0) > 0) {
            return 'cobranza';
        }
        if ((int) ($mov->pagoproveedor_id ?? 0) > 0) {
            return 'pagoproveedor';
        }

        return 'ingresoegreso';
    }
}
