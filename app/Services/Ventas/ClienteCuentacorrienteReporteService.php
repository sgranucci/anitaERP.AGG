<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteClienteSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;

class ClienteCuentacorrienteReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   totales: array<string, mixed>,
     *   advertencias: list<string>,
     *   stats: array{clientes: int, movimientos: int, aplicaciones: int},
     *   clientes_resueltos: list<array{id:int,codigo:string,nombre:string}>
     * }
     */
    public function generar(array $filtros): array
    {
        $resClientes = ClienteCuentacorrienteReporteClienteSupport::resolver($filtros);
        $advertencias = [];
        $clienteIdsFiltro = $resClientes['ids'];

        if (($filtros['alcance_clientes'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES
            && $clienteIdsFiltro === []) {
            $advertencias[] = 'Elija al menos un cliente en la lista.';
        }
        if (($filtros['alcance_clientes'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_RANGO
            && $clienteIdsFiltro === []) {
            $advertencias[] = 'No hay clientes en el rango de códigos indicado.';
        }
        foreach ($resClientes['faltantes_rango'] as $faltante) {
            $advertencias[] = 'Sin clientes para el rango: '.$faltante;
        }

        $modo = (string) ($filtros['modo'] ?? ClienteCuentacorrienteReporteFiltros::MODO_DEUDA);
        $movimientos = $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA
            ? $this->cargarFicha($filtros, $clienteIdsFiltro)
            : $this->cargarDeuda($filtros, $clienteIdsFiltro);

        if ($movimientos->isEmpty()) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'advertencias' => array_merge($advertencias, ['Sin movimientos para los filtros indicados.']),
                'stats' => ['clientes' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'clientes_resueltos' => $resClientes['iniciales'],
            ];
        }

        $aplicacionesPorCc = [];
        if ($modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA
            && ! empty($filtros['incluir_aplicaciones'])
            && empty($filtros['solo_totales'])) {
            $aplicacionesPorCc = $this->cargarAplicaciones($movimientos->pluck('id')->all());
        }

        $enPesos = ($filtros['expresion'] ?? '') === ClienteCuentacorrienteReporteFiltros::EXPRESION_PESOS;
        $soloTotales = ! empty($filtros['solo_totales']);
        $forzarDia = ($filtros['cotizacion_modo'] ?? '') === ClienteCuentacorrienteReporteFiltros::COTIZACION_DIA;

        $porCliente = $movimientos->groupBy(fn ($m) => (int) $m->cliente_id);
        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalPendiente = 0.0;
        $movimientosCount = 0;
        $aplicacionesCount = 0;
        $cotizacionesDiaUsadas = 0;

        foreach ($porCliente as $clienteId => $movsCliente) {
            /** @var Collection<int, Cliente_Cuentacorriente> $movsCliente */
            $primero = $movsCliente->first();
            $clienteCodigo = trim((string) ($primero->clientes->codigo ?? $primero->codigocliente ?? ''));
            $clienteNombre = (string) ($primero->clientes->nombre ?? $primero->nombrecliente ?? '');
            $nombreEmpresa = (string) ($primero->empresas->nombre ?? '');

            $filas[] = [
                'tipo' => 'header_cliente',
                'cliente_id' => (int) $clienteId,
                'cliente_codigo' => $clienteCodigo,
                'cliente_nombre' => $clienteNombre,
                'nombreempresa' => $nombreEmpresa,
            ];

            $saldoCorrido = 0.0;
            $saldoCorridoPesos = 0.0;
            $subDebe = 0.0;
            $subHaber = 0.0;
            $subPendiente = 0.0;

            if ($modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA) {
                $saldoAnterior = $this->saldoAnteriorCliente(
                    (int) $clienteId,
                    $filtros,
                    $enPesos,
                    $forzarDia
                );
                $saldoCorrido = $saldoAnterior['origen'];
                $saldoCorridoPesos = $saldoAnterior['pesos'];
                if (! $soloTotales && (abs($saldoCorrido) > 0.0001 || abs($saldoCorridoPesos) > 0.0001)) {
                    $filas[] = [
                        'tipo' => 'saldo_anterior',
                        'cliente_id' => (int) $clienteId,
                        'cliente_codigo' => $clienteCodigo,
                        'cliente_nombre' => $clienteNombre,
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

            foreach ($movsCliente as $mov) {
                $movimientosCount++;
                $conv = $this->convertirMovimiento($mov, $enPesos, $forzarDia);
                if ($conv['cotizacion_origen'] === 'dia') {
                    $cotizacionesDiaUsadas++;
                }

                $totalOrigen = (float) $mov->total;
                $aplicadoOrigen = (float) ($mov->aplicado ?? 0);
                $pendienteOrigen = ClienteCuentacorrienteGrillaSupport::saldoPendienteAbsoluto($totalOrigen, $aplicadoOrigen);

                $importeMostrar = $conv['importe'];
                $aplicadoMostrar = $conv['aplicado'];
                $pendienteMostrar = $conv['pendiente'];
                $pendientePesos = $enPesos
                    ? $pendienteMostrar
                    : abs($this->convertirMovimiento($mov, true, $forzarDia)['pendiente']);
                $importeFirmadoPesos = $conv['importe_firmado_pesos'];
                if (! $enPesos) {
                    $importeFirmadoPesos = $this->convertirMovimiento($mov, true, $forzarDia)['importe_firmado_pesos'];
                }

                if ($modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA) {
                    if ($totalOrigen >= 0) {
                        $subDebe += abs($importeMostrar);
                        $totalDebe += abs($importeFirmadoPesos);
                    } else {
                        $subHaber += abs($importeMostrar);
                        $totalHaber += abs($importeFirmadoPesos);
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
                    'cliente_id' => (int) $clienteId,
                    'cliente_codigo' => $clienteCodigo,
                    'cliente_nombre' => $clienteNombre,
                    'nombreempresa' => $nombreEmpresa,
                    'empresa_id' => (int) ($mov->empresa_id ?? 0),
                    'fecha' => $this->fmtFecha($mov->fecha),
                    'fechavencimiento' => $this->fmtFecha($mov->fechavencimiento),
                    'comprobante' => ClienteCuentacorrienteGrillaSupport::etiquetaComprobante($mov),
                    'venta_id' => (int) ($mov->venta_id ?? 0),
                    'cobranza_id' => (int) ($mov->cobranza_id ?? 0),
                    'moneda_id' => $conv['moneda_id'],
                    'abreviatura' => $conv['abreviatura'],
                    'etiqueta_moneda' => $conv['etiqueta_moneda'],
                    'cotizacion' => $conv['cotizacion_usada'],
                    'cotizacion_origen' => $conv['cotizacion_origen'],
                    'debe' => $totalOrigen >= 0 ? abs($importeMostrar) : null,
                    'haber' => $totalOrigen < 0 ? abs($importeMostrar) : null,
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
                    $filas[] = [
                        'tipo' => 'aplicacion',
                        'id' => (int) $apl->id,
                        'parent_id' => (int) $mov->id,
                        'cliente_id' => (int) $clienteId,
                        'cliente_codigo' => $clienteCodigo,
                        'cliente_nombre' => $clienteNombre,
                        'nombreempresa' => $nombreEmpresa,
                        'fecha' => $this->fmtFecha($apl->fecha ?? $apl->fechaaplicacion ?? null),
                        'fechavencimiento' => '',
                        'comprobante' => '↳ Aplicación: '.(string) ($apl->comprobanteaplicado ?? $apl->comprobante ?? ('#'.$apl->id)),
                        'venta_id' => (int) ($apl->ventaaplicado_id ?? 0),
                        'cobranza_id' => (int) ($apl->cobranza_id ?? 0),
                        'moneda_id' => $convApl['moneda_id'],
                        'abreviatura' => $convApl['abreviatura'],
                        'etiqueta_moneda' => $convApl['etiqueta_moneda'],
                        'cotizacion' => $convApl['cotizacion_usada'],
                        'cotizacion_origen' => $convApl['cotizacion_origen'],
                        'debe' => null,
                        'haber' => abs($convApl['importe']),
                        'importe' => abs($convApl['importe']),
                        'aplicado' => abs($convApl['importe']),
                        'saldo_pendiente' => null,
                        'saldo' => null,
                        'saldo_pesos' => null,
                    ];
                }
            }

            $filas[] = [
                'tipo' => 'total_cliente',
                'cliente_id' => (int) $clienteId,
                'cliente_codigo' => $clienteCodigo,
                'cliente_nombre' => $clienteNombre,
                'nombreempresa' => $nombreEmpresa,
                'comprobante' => 'Total cliente',
                'debe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $subDebe : null,
                'haber' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $subHaber : null,
                'importe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $subPendiente : null,
                'saldo_pendiente' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $subPendiente : null,
                'saldo' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA
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
                'clientes' => $porCliente->count(),
                'movimientos' => $movimientosCount,
                'aplicaciones' => $aplicacionesCount,
            ],
            'clientes_resueltos' => $resClientes['iniciales'],
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
     * @param  list<int>  $clienteIds
     * @return Collection<int, Cliente_Cuentacorriente>
     */
    private function cargarDeuda(array $filtros, array $clienteIds): Collection
    {
        $query = Cliente_Cuentacorriente::query()
            ->with(['clientes:id,codigo,nombre', 'ventas:id,codigo,lugarentrega', 'monedas:id,abreviatura', 'empresas:id,nombre'])
            ->select('cliente_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->whereNotNull('cliente_cuentacorriente.venta_id')
            ->whereRaw(SqlDialectSupport::sqlSinCobranzaClienteCc())
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc());

        $this->aplicarFiltrosComunes($query, $filtros, $clienteIds);

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('cliente.codigo'))
            ->orderBy('cliente_cuentacorriente.fecha')
            ->orderBy('cliente_cuentacorriente.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $clienteIds
     * @return Collection<int, Cliente_Cuentacorriente>
     */
    private function cargarFicha(array $filtros, array $clienteIds): Collection
    {
        $query = Cliente_Cuentacorriente::query()
            ->with([
                'clientes:id,codigo,nombre',
                'ventas:id,codigo,lugarentrega',
                'cobranzas:id,detalle',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->select('cliente_cuentacorriente.*');

        $this->aplicarFiltrosComunes($query, $filtros, $clienteIds);

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('cliente.codigo'))
            ->orderBy('cliente_cuentacorriente.fecha')
            ->orderBy('cliente_cuentacorriente.id')
            ->get();
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente_Cuentacorriente>  $query
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $clienteIds
     */
    private function aplicarFiltrosComunes(Builder $query, array $filtros, array $clienteIds): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('cliente_cuentacorriente.empresa_id', $empresaId);
        }

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaDesde !== '') {
            $query->whereDate('cliente_cuentacorriente.fecha', '>=', $fechaDesde);
        }
        if ($fechaHasta !== '') {
            $query->whereDate('cliente_cuentacorriente.fecha', '<=', $fechaHasta);
        }

        $query->join('cliente', 'cliente.id', '=', 'cliente_cuentacorriente.cliente_id')
            ->whereNull('cliente.deleted_at');

        if ($clienteIds !== []) {
            $query->whereIn('cliente_cuentacorriente.cliente_id', $clienteIds);
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

        $rows = Cliente_Cuentacorriente_Aplicacion::query()
            ->with(['monedas:id,abreviatura'])
            ->whereIn('cliente_cuentacorriente_id', $ccIds)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $porCc = [];
        foreach ($rows as $row) {
            $porCc[(int) $row->cliente_cuentacorriente_id][] = $row;
        }

        return $porCc;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{origen: float, pesos: float}
     */
    private function saldoAnteriorCliente(int $clienteId, array $filtros, bool $enPesos, bool $forzarDia): array
    {
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($fechaDesde === '') {
            return ['origen' => 0.0, 'pesos' => 0.0];
        }

        $query = Cliente_Cuentacorriente::query()
            ->where('cliente_id', $clienteId)
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
        $pendiente = ClienteCuentacorrienteGrillaSupport::saldoPendiente($total, $aplicado);
        $monedaId = CuentacorrienteSaldosPorMoneda::monedaIdDe($mov);
        $abrev = CuentacorrienteSaldosPorMoneda::abreviaturaDe($mov);
        $fecha = (string) ($mov->fecha ?? date('Y-m-d'));
        [$cotizacion, $origen] = $this->resolverCotizacion($mov, $fecha, $monedaId, $forzarDia);

        if (! $enPesos || $monedaId <= CotizacionVigenteSupport::MONEDA_LOCAL_ID) {
            return [
                'importe' => $total,
                'aplicado' => $aplicado,
                'pendiente' => abs($pendiente),
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
            'pendiente' => round(abs($pendiente) * $coef, 2),
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
        $fecha = (string) ($apl->fecha ?? $apl->fechaaplicacion ?? date('Y-m-d'));
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
            'modo' => ClienteCuentacorrienteReporteFiltros::MODO_DEUDA,
        ];
    }
}
