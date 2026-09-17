<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
use App\Support\Compras\ProveedorCuentacorrienteReporteFiltros;
use App\Support\Compras\ProveedorCuentacorrienteReporteProveedorSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;

class ProveedorCuentacorrienteReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, mixed>,
     *   advertencias: list<string>,
     *   stats: array{proveedores: int, movimientos: int, aplicaciones: int},
     *   proveedores_resueltos: list<array{id:int,codigo:string,nombre:string}>
     * }
     */
    public function generar(array $filtros): array
    {
        $resProveedores = ProveedorCuentacorrienteReporteProveedorSupport::resolver($filtros);
        $advertencias = [];
        $proveedorIdsFiltro = $resProveedores['ids'];

        if (($filtros['alcance_proveedores'] ?? '') === ProveedorCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES
            && $proveedorIdsFiltro === []) {
            $advertencias[] = 'Elija al menos un proveedor en la lista.';
        }
        if (($filtros['alcance_proveedores'] ?? '') === ProveedorCuentacorrienteReporteFiltros::ALCANCE_RANGO
            && $proveedorIdsFiltro === []) {
            $advertencias[] = 'No hay proveedores en el rango de códigos indicado.';
        }
        foreach ($resProveedores['faltantes_rango'] as $faltante) {
            $advertencias[] = 'Sin proveedores para el rango: '.$faltante;
        }

        $modo = (string) ($filtros['modo'] ?? ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA);
        $movimientos = $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA
            ? $this->cargarFicha($filtros, $proveedorIdsFiltro)
            : $this->cargarDeuda($filtros, $proveedorIdsFiltro);

        if ($movimientos->isEmpty()) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'advertencias' => array_merge($advertencias, ['Sin movimientos para los filtros indicados.']),
                'stats' => ['proveedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'proveedores_resueltos' => $resProveedores['iniciales'],
            ];
        }

        $aplicacionesPorCc = [];
        if ($modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA
            && ! empty($filtros['incluir_aplicaciones'])
            && empty($filtros['solo_totales'])) {
            $aplicacionesPorCc = $this->cargarAplicaciones($movimientos->pluck('id')->all());
        }

        $enPesos = ($filtros['expresion'] ?? '') === ProveedorCuentacorrienteReporteFiltros::EXPRESION_PESOS;
        $soloTotales = ! empty($filtros['solo_totales']);
        $forzarDia = ($filtros['cotizacion_modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::COTIZACION_DIA;

        $porProveedor = $movimientos->groupBy(fn ($m) => (int) $m->proveedor_id);
        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalPendiente = 0.0;
        $movimientosCount = 0;
        $aplicacionesCount = 0;
        $cotizacionesDiaUsadas = 0;

        foreach ($porProveedor as $proveedorId => $movsProveedor) {
            /** @var Collection<int, Proveedor_Cuentacorriente> $movsProveedor */
            $primero = $movsProveedor->first();
            $proveedorCodigo = trim((string) ($primero->proveedores->codigo ?? ''));
            $proveedorNombre = (string) ($primero->proveedores->nombre ?? '');
            $nombreEmpresa = (string) ($primero->empresas->nombre ?? '');

            $filas[] = [
                'tipo' => 'header_proveedor',
                'proveedor_id' => (int) $proveedorId,
                'proveedor_codigo' => $proveedorCodigo,
                'proveedor_nombre' => $proveedorNombre,
                'nombreempresa' => $nombreEmpresa,
            ];

            $saldoCorrido = 0.0;
            $saldoCorridoPesos = 0.0;
            $subDebe = 0.0;
            $subHaber = 0.0;
            $subPendiente = 0.0;

            if ($modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA) {
                $saldoAnterior = $this->saldoAnteriorProveedor(
                    (int) $proveedorId,
                    $filtros,
                    $enPesos,
                    $forzarDia
                );
                $saldoCorrido = $saldoAnterior['origen'];
                $saldoCorridoPesos = $saldoAnterior['pesos'];
                if (! $soloTotales && (abs($saldoCorrido) > 0.0001 || abs($saldoCorridoPesos) > 0.0001)) {
                    $filas[] = [
                        'tipo' => 'saldo_anterior',
                        'proveedor_id' => (int) $proveedorId,
                        'proveedor_codigo' => $proveedorCodigo,
                        'proveedor_nombre' => $proveedorNombre,
                        'nombreempresa' => $nombreEmpresa,
                        'comprobante' => 'Saldo anterior',
                        'saldo' => $enPesos ? $saldoCorridoPesos : $saldoCorrido,
                        'saldo_origen' => $saldoCorrido,
                        'saldo_pesos' => $saldoCorridoPesos,
                        'abreviatura' => $enPesos
                            ? CuentacorrienteSaldosPorMoneda::abreviaturaLocal()
                            : '',
                    ];
                }
            }

            foreach ($movsProveedor as $mov) {
                $movimientosCount++;
                $conv = $this->convertirMovimiento($mov, $enPesos, $forzarDia);
                if ($conv['cotizacion_origen'] === 'dia') {
                    $cotizacionesDiaUsadas++;
                }

                $totalOrigen = (float) $mov->total;
                $aplicadoOrigen = (float) ($mov->aplicado ?? 0);
                $pendienteOrigen = ProveedorCuentacorrienteGrillaSupport::saldoPendiente($totalOrigen, $aplicadoOrigen);

                $importeMostrar = $conv['importe'];
                $aplicadoMostrar = $conv['aplicado'];
                $pendienteMostrar = $conv['pendiente'];
                $pendientePesos = $enPesos
                    ? $pendienteMostrar
                    : $this->convertirMovimiento($mov, true, $forzarDia)['pendiente'];
                $importeFirmadoPesos = $conv['importe_firmado_pesos'];
                if (! $enPesos) {
                    $importeFirmadoPesos = $this->convertirMovimiento($mov, true, $forzarDia)['importe_firmado_pesos'];
                }

                $dhMov = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal($totalOrigen, abs($importeMostrar));
                $dhMovPesos = ProveedorCuentacorrienteGrillaSupport::debeHaberDesdeTotal($totalOrigen, abs($importeFirmadoPesos));

                if ($modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA) {
                    if ($dhMov['debe'] !== null) {
                        $subDebe += $dhMov['debe'];
                        $totalDebe += (float) ($dhMovPesos['debe'] ?? 0);
                    }
                    if ($dhMov['haber'] !== null) {
                        $subHaber += $dhMov['haber'];
                        $totalHaber += (float) ($dhMovPesos['haber'] ?? 0);
                    }
                    $saldoCorrido += $totalOrigen;
                    $saldoCorridoPesos += $importeFirmadoPesos;
                } else {
                    $subPendiente += $pendienteMostrar;
                    $totalPendiente += $pendientePesos;
                }

                if ($soloTotales) {
                    continue;
                }

                $filaMov = [
                    'tipo' => 'movimiento',
                    'id' => (int) $mov->id,
                    'proveedor_id' => (int) $proveedorId,
                    'proveedor_codigo' => $proveedorCodigo,
                    'proveedor_nombre' => $proveedorNombre,
                    'nombreempresa' => $nombreEmpresa,
                    'empresa_id' => (int) ($mov->empresa_id ?? 0),
                    'fecha' => $this->fmtFecha($mov->fecha),
                    'fechavencimiento' => $this->fmtFecha($mov->fechavencimiento),
                    'comprobante' => ProveedorCuentacorrienteGrillaSupport::etiquetaComprobante($mov),
                    'comprobante_proveedor_id' => (int) ($mov->comprobante_proveedor_id ?? 0),
                    'pagoproveedor_id' => (int) ($mov->pagoproveedor_id ?? 0),
                    'moneda_id' => $conv['moneda_id'],
                    'abreviatura' => $conv['abreviatura'],
                    'etiqueta_moneda' => $conv['etiqueta_moneda'],
                    'cotizacion' => $conv['cotizacion_usada'],
                    'cotizacion_origen' => $conv['cotizacion_origen'],
                    'debe' => $dhMov['debe'],
                    'haber' => $dhMov['haber'],
                    'importe' => abs($importeMostrar),
                    'aplicado' => abs($aplicadoMostrar) > 0.0001 ? abs($aplicadoMostrar) : null,
                    'saldo_pendiente' => $pendienteMostrar,
                    'saldo' => $enPesos ? $saldoCorridoPesos : $saldoCorrido,
                    'saldo_pesos' => $saldoCorridoPesos,
                ];
                $filas[] = $filaMov;

                foreach ($aplicacionesPorCc[(int) $mov->id] ?? [] as $apl) {
                    $aplicacionesCount++;
                    $convApl = $this->convertirAplicacion($apl, $enPesos, $forzarDia);
                    // Espejo de clientes (aplicación en Haber): en proveedores cancela la deuda del Haber → Debe.
                    $filas[] = [
                        'tipo' => 'aplicacion',
                        'id' => (int) $apl->id,
                        'parent_id' => (int) $mov->id,
                        'proveedor_id' => (int) $proveedorId,
                        'proveedor_codigo' => $proveedorCodigo,
                        'proveedor_nombre' => $proveedorNombre,
                        'nombreempresa' => $nombreEmpresa,
                        'fecha' => $this->fmtFecha($apl->fecha ?? null),
                        'fechavencimiento' => '',
                        'comprobante' => '↳ Aplicación: '.(string) ($apl->comprobanteaplicado ?? ('#'.$apl->id)),
                        'comprobante_proveedor_id' => (int) ($apl->comprobante_proveedor_aplicado_id ?? 0),
                        'pagoproveedor_id' => (int) ($apl->pagoproveedor_id ?? 0),
                        'moneda_id' => $convApl['moneda_id'],
                        'abreviatura' => $convApl['abreviatura'],
                        'etiqueta_moneda' => $convApl['etiqueta_moneda'],
                        'cotizacion' => $convApl['cotizacion_usada'],
                        'cotizacion_origen' => $convApl['cotizacion_origen'],
                        'debe' => abs($convApl['importe']),
                        'haber' => null,
                        'importe' => abs($convApl['importe']),
                        'aplicado' => abs($convApl['importe']),
                        'saldo_pendiente' => null,
                        'saldo' => null,
                        'saldo_pesos' => null,
                    ];
                }
            }

            $filas[] = [
                'tipo' => 'total_proveedor',
                'proveedor_id' => (int) $proveedorId,
                'proveedor_codigo' => $proveedorCodigo,
                'proveedor_nombre' => $proveedorNombre,
                'nombreempresa' => $nombreEmpresa,
                'comprobante' => 'Total proveedor',
                'debe' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA ? $subDebe : null,
                'haber' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA ? $subHaber : null,
                'importe' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA ? $subPendiente : null,
                'saldo_pendiente' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA ? $subPendiente : null,
                'saldo' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA
                    ? ($enPesos ? $saldoCorridoPesos : $saldoCorrido)
                    : null,
                'saldo_pesos' => $saldoCorridoPesos,
                'abreviatura' => $enPesos ? CuentacorrienteSaldosPorMoneda::abreviaturaLocal() : '',
            ];
        }

        if ($cotizacionesDiaUsadas > 0 && $enPesos) {
            $advertencias[] = 'Se usó cotización vigente del día en '.$cotizacionesDiaUsadas
                .' movimiento(s) sin cotización cargada en el comprobante.';
        }

        return [
            'filas' => $filas,
            'totales' => [
                'debe' => $totalDebe,
                'haber' => $totalHaber,
                'pendiente' => $totalPendiente,
                'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
                'modo' => $modo,
            ],
            'advertencias' => $advertencias,
            'stats' => [
                'proveedores' => $porProveedor->count(),
                'movimientos' => $movimientosCount,
                'aplicaciones' => $aplicacionesCount,
            ],
            'proveedores_resueltos' => $resProveedores['iniciales'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page = 1): LengthAwarePaginator
    {
        $perPage = max(10, min(500, $perPage));
        $page = max(1, $page);
        $total = count($filas);
        $offset = ($page - 1) * $perPage;

        return new PaginatorImpl(
            array_slice($filas, $offset, $perPage),
            $total,
            $perPage,
            $page,
            ['path' => PaginatorImpl::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $proveedorIds
     * @return Collection<int, Proveedor_Cuentacorriente>
     */
    private function cargarDeuda(array $filtros, array $proveedorIds): Collection
    {
        $query = Proveedor_Cuentacorriente::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'comprobante_proveedores.tipotransaccion_compras',
                'pagoproveedores',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->whereRaw(SqlDialectSupport::sqlAlcanceDeudaAbiertaProveedorCc())
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteProveedorCc());

        $this->aplicarFiltrosComunes($query, $filtros, $proveedorIds);

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('proveedor.codigo'))
            ->orderBy('proveedor_cuentacorriente.fecha')
            ->orderBy('proveedor_cuentacorriente.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $proveedorIds
     * @return Collection<int, Proveedor_Cuentacorriente>
     */
    private function cargarFicha(array $filtros, array $proveedorIds): Collection
    {
        $query = Proveedor_Cuentacorriente::query()
            ->with([
                'proveedores:id,codigo,nombre',
                'comprobante_proveedores.tipotransaccion_compras',
                'pagoproveedores',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->select('proveedor_cuentacorriente.*');

        $this->aplicarFiltrosComunes($query, $filtros, $proveedorIds);

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('proveedor.codigo'))
            ->orderBy('proveedor_cuentacorriente.fecha')
            ->orderBy('proveedor_cuentacorriente.id')
            ->get();
    }

    /**
     * @param  Builder<\App\Models\Compras\Proveedor_Cuentacorriente>  $query
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $proveedorIds
     */
    private function aplicarFiltrosComunes(Builder $query, array $filtros, array $proveedorIds): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('proveedor_cuentacorriente.empresa_id', $empresaId);
        }

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaDesde !== '') {
            $query->whereDate('proveedor_cuentacorriente.fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $query->whereDate('proveedor_cuentacorriente.fecha', '<=', $fechaHasta);
        }

        $query->join('proveedor', 'proveedor.id', '=', 'proveedor_cuentacorriente.proveedor_id')
            ->whereNull('proveedor.deleted_at');

        if ($proveedorIds !== []) {
            $query->whereIn('proveedor_cuentacorriente.proveedor_id', $proveedorIds);
        }
    }

    /**
     * @param  list<int>  $ccIds
     * @return array<int, list<object>>
     */
    private function cargarAplicaciones(array $ccIds): array
    {
        if ($ccIds === []) {
            return [];
        }

        $rows = Proveedor_Cuentacorriente_Aplicacion::query()
            ->with(['monedas:id,abreviatura'])
            ->whereIn('proveedor_cuentacorriente_id', $ccIds)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $porCc = [];
        foreach ($rows as $row) {
            $porCc[(int) $row->proveedor_cuentacorriente_id][] = $row;
        }

        return $porCc;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{origen: float, pesos: float}
     */
    private function saldoAnteriorProveedor(int $proveedorId, array $filtros, bool $enPesos, bool $forzarDia): array
    {
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($fechaDesde === '') {
            return ['origen' => 0.0, 'pesos' => 0.0];
        }

        $query = Proveedor_Cuentacorriente::query()
            ->where('proveedor_id', $proveedorId)
            ->whereDate('fecha', '<', $fechaDesde);

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $origen = 0.0;
        $pesos = 0.0;
        foreach ($query->get(['total', 'moneda_id', 'cotizacion', 'fecha']) as $mov) {
            $origen += (float) $mov->total;
            $conv = $this->convertirMovimiento($mov, true, $forzarDia);
            $pesos += $conv['importe_firmado_pesos'];
        }

        return ['origen' => $origen, 'pesos' => $pesos];
    }

    /**
     * @return array{
     *   importe: float,
     *   aplicado: float,
     *   pendiente: float,
     *   importe_firmado_pesos: float,
     *   moneda_id: int,
     *   abreviatura: string,
     *   etiqueta_moneda: string,
     *   cotizacion_usada: float,
     *   cotizacion_origen: string
     * }
     */
    private function convertirMovimiento(object $mov, bool $enPesos, bool $forzarDia): array
    {
        $total = (float) ($mov->total ?? 0);
        $aplicado = (float) ($mov->aplicado ?? 0);
        $pendiente = ProveedorCuentacorrienteGrillaSupport::saldoPendiente($total, $aplicado);
        $monedaId = CuentacorrienteSaldosPorMoneda::monedaIdDe($mov);
        $abrev = CuentacorrienteSaldosPorMoneda::abreviaturaDe($mov);
        $fecha = (string) ($mov->fecha ?? date('Y-m-d'));
        [$cotizacion, $origen] = $this->resolverCotizacion($mov, $fecha, $monedaId, $forzarDia);

        if (! $enPesos || $monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return [
                'importe' => $total,
                'aplicado' => $aplicado,
                'pendiente' => $pendiente,
                'importe_firmado_pesos' => $total,
                'moneda_id' => $monedaId,
                'abreviatura' => $abrev,
                'etiqueta_moneda' => $abrev,
                'cotizacion_usada' => $cotizacion,
                'cotizacion_origen' => $origen,
            ];
        }

        $coef = (float) calculaCoeficienteMoneda(
            CuentacorrienteSaldosPorMoneda::monedaLocalId(),
            $monedaId,
            $cotizacion
        );
        $local = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();

        return [
            'importe' => round($total * $coef, 2),
            'aplicado' => round($aplicado * $coef, 2),
            'pendiente' => round($pendiente * $coef, 2),
            'importe_firmado_pesos' => round($total * $coef, 2),
            'moneda_id' => $monedaId,
            'abreviatura' => $local,
            'etiqueta_moneda' => ($abrev !== '' ? $abrev : 'ME').' → '.$local.' · TC '.number_format($cotizacion, 4, ',', '.'),
            'cotizacion_usada' => $cotizacion,
            'cotizacion_origen' => $origen,
        ];
    }

    /**
     * @return array{
     *   importe: float,
     *   moneda_id: int,
     *   abreviatura: string,
     *   etiqueta_moneda: string,
     *   cotizacion_usada: float,
     *   cotizacion_origen: string
     * }
     */
    private function convertirAplicacion(object $apl, bool $enPesos, bool $forzarDia): array
    {
        $total = (float) ($apl->total ?? 0);
        $monedaId = CuentacorrienteSaldosPorMoneda::monedaIdDe($apl);
        $abrev = CuentacorrienteSaldosPorMoneda::abreviaturaDe($apl);
        $fecha = (string) ($apl->fecha ?? date('Y-m-d'));
        [$cotizacion, $origen] = $this->resolverCotizacion($apl, $fecha, $monedaId, $forzarDia);

        if (! $enPesos || $monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return [
                'importe' => $total,
                'moneda_id' => $monedaId,
                'abreviatura' => $abrev,
                'etiqueta_moneda' => $abrev,
                'cotizacion_usada' => $cotizacion,
                'cotizacion_origen' => $origen,
            ];
        }

        $coef = (float) calculaCoeficienteMoneda(
            CuentacorrienteSaldosPorMoneda::monedaLocalId(),
            $monedaId,
            $cotizacion
        );
        $local = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();

        return [
            'importe' => round($total * $coef, 2),
            'moneda_id' => $monedaId,
            'abreviatura' => $local,
            'etiqueta_moneda' => ($abrev !== '' ? $abrev : 'ME').' → '.$local,
            'cotizacion_usada' => $cotizacion,
            'cotizacion_origen' => $origen,
        ];
    }

    /**
     * @return array{0: float, 1: string}
     */
    private function resolverCotizacion(object $fila, string $fecha, int $monedaId, bool $forzarDia): array
    {
        if ($monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return [1.0, 'local'];
        }

        $cotDoc = (float) ($fila->cotizacion ?? 0);
        if (! $forzarDia && $cotDoc > 0) {
            return [$cotDoc, 'comprobante'];
        }

        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);
        if ($vigente > 0) {
            return [$vigente, 'dia'];
        }

        if ($cotDoc > 0) {
            return [$cotDoc, 'comprobante'];
        }

        return [1.0, 'fallback'];
    }

    private function fmtFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        $ts = strtotime((string) $fecha);

        return $ts ? date('d/m/Y', $ts) : (string) $fecha;
    }

    /**
     * @return array{debe: float, haber: float, pendiente: float, abreviatura: string, modo: string}
     */
    private function totalesVacios(): array
    {
        return [
            'debe' => 0.0,
            'haber' => 0.0,
            'pendiente' => 0.0,
            'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
            'modo' => ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA,
        ];
    }
}
