<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use App\Models\Configuracion\Empresa;
use App\Models\Ventas\Cliente_Cuentacorriente;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\ClienteCuentacorrienteDeudaAlcanceSupport;
use App\Support\Ventas\ClienteCuentacorrienteGrillaSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteClienteSupport;
use App\Support\Ventas\ClienteCuentacorrienteReporteFiltros;
use App\Support\Ventas\ClienteCuentacorrienteReporteVendedorSupport;
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
     *   stats: array{clientes: int, vendedores: int, movimientos: int, aplicaciones: int},
     *   clientes_resueltos: list<array{id:int,codigo:string,nombre:string}>,
     *   vendedores_resueltos: list<array{id:int,codigo:string,nombre:string}>,
     *   secciones: list<array<string, mixed>>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = ClienteCuentacorrienteReporteFiltros::empresaIds($filtros);
        if (ClienteCuentacorrienteReporteFiltros::consolidarEmpresas($filtros) || $empresaIds === []) {
            $resultado = $this->generarInterno($filtros);
            $resultado['secciones'] = [];

            return $resultado;
        }

        return $this->generarPorEmpresa($filtros, $empresaIds);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarInterno(array $filtros): array
    {
        $resClientes = ClienteCuentacorrienteReporteClienteSupport::resolver($filtros);
        $resVendedores = ClienteCuentacorrienteReporteVendedorSupport::resolver($filtros);
        $advertencias = [];
        $clienteIdsFiltro = $resClientes['ids'];
        $vendedorIdsFiltro = $resVendedores['ids'];

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

        if (($filtros['alcance_vendedores'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES
            && $vendedorIdsFiltro === []) {
            $advertencias[] = 'Elija al menos un vendedor en la lista.';
        }
        if (($filtros['alcance_vendedores'] ?? '') === ClienteCuentacorrienteReporteFiltros::ALCANCE_RANGO
            && $vendedorIdsFiltro === []) {
            $advertencias[] = 'No hay vendedores en el rango de códigos indicado.';
        }
        foreach ($resVendedores['faltantes_rango'] as $faltante) {
            $advertencias[] = 'Sin vendedores para el rango: '.$faltante;
        }

        $alcanceClientes = (string) ($filtros['alcance_clientes'] ?? ClienteCuentacorrienteReporteFiltros::ALCANCE_TODOS);
        $alcanceVendedores = (string) ($filtros['alcance_vendedores'] ?? ClienteCuentacorrienteReporteFiltros::ALCANCE_TODOS);
        $filtroClientesActivo = in_array($alcanceClientes, [
            ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES,
            ClienteCuentacorrienteReporteFiltros::ALCANCE_RANGO,
        ], true);
        $filtroVendedoresActivo = in_array($alcanceVendedores, [
            ClienteCuentacorrienteReporteFiltros::ALCANCE_PUNTUALES,
            ClienteCuentacorrienteReporteFiltros::ALCANCE_RANGO,
        ], true);

        if (($filtroClientesActivo && $clienteIdsFiltro === [])
            || ($filtroVendedoresActivo && $vendedorIdsFiltro === [])) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'advertencias' => array_merge($advertencias, ['Sin movimientos para los filtros indicados.']),
                'stats' => ['clientes' => 0, 'vendedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'clientes_resueltos' => $resClientes['iniciales'],
                'vendedores_resueltos' => $resVendedores['iniciales'],
            ];
        }

        $modo = (string) ($filtros['modo'] ?? ClienteCuentacorrienteReporteFiltros::MODO_DEUDA);
        $movimientos = $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA
            ? $this->cargarFicha($filtros, $clienteIdsFiltro, $vendedorIdsFiltro)
            : $this->cargarDeuda($filtros, $clienteIdsFiltro, $vendedorIdsFiltro);

        if ($movimientos->isEmpty()) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'advertencias' => array_merge($advertencias, ['Sin movimientos para los filtros indicados.']),
                'stats' => ['clientes' => 0, 'vendedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'clientes_resueltos' => $resClientes['iniciales'],
                'vendedores_resueltos' => $resVendedores['iniciales'],
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

        $movimientos = $movimientos
            ->sortBy(static function ($m) {
                $vendCodigo = trim((string) ($m->clientes->vendedores->codigo ?? ''));
                $vendId = (int) ($m->clientes->vendedor_id ?? 0);
                $cliCodigo = trim((string) ($m->clientes->codigo ?? $m->codigocliente ?? ''));

                return sprintf(
                    '%s|%010d|%s|%s|%010d',
                    str_pad($vendCodigo !== '' ? $vendCodigo : 'ZZZZ', 20, '0', STR_PAD_LEFT),
                    $vendId,
                    str_pad($cliCodigo, 20, '0', STR_PAD_LEFT),
                    (string) ($m->fecha ?? ''),
                    (int) ($m->id ?? 0)
                );
            })
            ->values();

        $porCliente = $movimientos->groupBy(fn ($m) => (int) $m->cliente_id);

        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalPendiente = 0.0;
        $movimientosCount = 0;
        $aplicacionesCount = 0;
        $cotizacionesDiaUsadas = 0;
        $clientesUnicos = [];
        $vendedoresUnicos = [];

        $vendedorActualId = null;
        $metaVendedor = [
            'vendedor_id' => 0,
            'vendedor_codigo' => '',
            'vendedor_nombre' => '',
        ];
        $subVendDebe = 0.0;
        $subVendHaber = 0.0;
        $subVendPendiente = 0.0;

        $flushTotalVendedor = static function () use (
            &$filas,
            &$vendedorActualId,
            &$metaVendedor,
            &$subVendDebe,
            &$subVendHaber,
            &$subVendPendiente,
            $modo
        ): void {
            if ($vendedorActualId === null) {
                return;
            }
            $filas[] = [
                'tipo' => 'total_vendedor',
                'vendedor_id' => (int) $metaVendedor['vendedor_id'],
                'vendedor_codigo' => (string) $metaVendedor['vendedor_codigo'],
                'vendedor_nombre' => (string) $metaVendedor['vendedor_nombre'],
                'comprobante' => 'Total vendedor',
                'debe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $subVendDebe : null,
                'haber' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $subVendHaber : null,
                'importe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $subVendPendiente : null,
                'saldo_pendiente' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $subVendPendiente : null,
                'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
            ];
            $subVendDebe = 0.0;
            $subVendHaber = 0.0;
            $subVendPendiente = 0.0;
        };

        foreach ($porCliente as $clienteId => $movsCliente) {
            /** @var Collection<int, Cliente_Cuentacorriente> $movsCliente */
            $clientesUnicos[(int) $clienteId] = true;
            $primero = $movsCliente->first();
            $vendedorModelo = $primero->clientes->vendedores ?? null;
            $vendedorId = (int) ($primero->clientes->vendedor_id ?? 0);
            $vendedoresUnicos[$vendedorId] = true;
            $vendedorCodigo = $vendedorModelo
                ? trim((string) $vendedorModelo->codigo)
                : '';
            $vendedorNombre = $vendedorModelo
                ? (string) $vendedorModelo->nombre
                : ($vendedorId > 0 ? 'Vendedor #'.$vendedorId : 'Sin vendedor');
            $clienteCodigo = trim((string) ($primero->clientes->codigo ?? $primero->codigocliente ?? ''));
            $clienteNombre = (string) ($primero->clientes->nombre ?? $primero->nombrecliente ?? '');
            $nombreEmpresa = $this->nombreEmpresaUnicaGrupo($movsCliente);

            if ($vendedorActualId !== $vendedorId) {
                $flushTotalVendedor();
                $vendedorActualId = $vendedorId;
                $metaVendedor = [
                    'vendedor_id' => $vendedorId,
                    'vendedor_codigo' => $vendedorCodigo,
                    'vendedor_nombre' => $vendedorNombre,
                ];
                $filas[] = [
                    'tipo' => 'header_vendedor',
                    'vendedor_id' => $vendedorId,
                    'vendedor_codigo' => $vendedorCodigo,
                    'vendedor_nombre' => $vendedorNombre,
                ];
            }

            $filas[] = [
                'tipo' => 'header_cliente',
                'cliente_id' => (int) $clienteId,
                'cliente_codigo' => $clienteCodigo,
                'cliente_nombre' => $clienteNombre,
                'nombreempresa' => $nombreEmpresa,
                'empresa_id' => (int) ($primero->empresa_id ?? 0),
                'vendedor_id' => $vendedorId,
                'vendedor_codigo' => $vendedorCodigo,
                'vendedor_nombre' => $vendedorNombre,
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

                $nombreEmpresaMov = (string) ($mov->empresas->nombre ?? '');
                $filaMov = [
                    'tipo' => 'movimiento',
                    'id' => (int) $mov->id,
                    'cliente_id' => (int) $clienteId,
                    'cliente_codigo' => $clienteCodigo,
                    'cliente_nombre' => $clienteNombre,
                    'nombreempresa' => $nombreEmpresaMov,
                    'empresa_id' => (int) ($mov->empresa_id ?? 0),
                    'vendedor_id' => $vendedorId,
                    'vendedor_codigo' => $vendedorCodigo,
                    'vendedor_nombre' => $vendedorNombre,
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
                    'importe' => $importeMostrar,
                    'aplicado' => abs($aplicadoMostrar) > 0.0001 ? $aplicadoMostrar : null,
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
                        'nombreempresa' => $nombreEmpresaMov,
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
                'vendedor_id' => $vendedorId,
                'vendedor_codigo' => $vendedorCodigo,
                'vendedor_nombre' => $vendedorNombre,
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

            $subVendDebe += $subDebe;
            $subVendHaber += $subHaber;
            $subVendPendiente += $subPendiente;
        }

        $flushTotalVendedor();

        if ($filas !== []) {
            $filas[] = [
                'tipo' => 'total_general',
                'comprobante' => 'Total a cobrar',
                'cliente_codigo' => '',
                'cliente_nombre' => 'Total cuentas a cobrar',
                'debe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $totalDebe : null,
                'haber' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $totalHaber : null,
                'importe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $totalPendiente : null,
                'saldo_pendiente' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $totalPendiente : null,
                'saldo' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA
                    ? ($totalDebe - $totalHaber)
                    : null,
                'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
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
                'clientes' => count($clientesUnicos),
                'vendedores' => count($vendedoresUnicos),
                'movimientos' => $movimientosCount,
                'aplicaciones' => $aplicacionesCount,
            ],
            'clientes_resueltos' => $resClientes['iniciales'],
            'vendedores_resueltos' => $resVendedores['iniciales'],
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
     * @param  list<int>  $vendedorIds
     * @return Collection<int, Cliente_Cuentacorriente>
     */
    private function cargarDeuda(array $filtros, array $clienteIds, array $vendedorIds = []): Collection
    {
        $query = Cliente_Cuentacorriente::query()
            ->with([
                'clientes:id,codigo,nombre,vendedor_id',
                'clientes.vendedores:id,codigo,nombre',
                'ventas:id,codigo,lugarentrega',
                'cobranzas:id,detalle',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->select('cliente_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id'),
            ])
            ->whereRaw(SqlDialectSupport::sqlSaldoPendienteClienteCc());

        ClienteCuentacorrienteDeudaAlcanceSupport::aplicar($query);

        $this->aplicarFiltrosComunes($query, $filtros, $clienteIds, $vendedorIds);

        return $query
            ->orderByRaw(SqlDialectSupport::ordenCodigoAsc('cliente.codigo'))
            ->orderBy('cliente_cuentacorriente.fecha')
            ->orderBy('cliente_cuentacorriente.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $clienteIds
     * @param  list<int>  $vendedorIds
     * @return Collection<int, Cliente_Cuentacorriente>
     */
    private function cargarFicha(array $filtros, array $clienteIds, array $vendedorIds = []): Collection
    {
        $query = Cliente_Cuentacorriente::query()
            ->with([
                'clientes:id,codigo,nombre,vendedor_id',
                'clientes.vendedores:id,codigo,nombre',
                'ventas:id,codigo,lugarentrega',
                'cobranzas:id,detalle',
                'monedas:id,abreviatura',
                'empresas:id,nombre',
            ])
            ->select('cliente_cuentacorriente.*');

        $this->aplicarFiltrosComunes($query, $filtros, $clienteIds, $vendedorIds);

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
     * @param  list<int>  $vendedorIds
     */
    private function aplicarFiltrosComunes(Builder $query, array $filtros, array $clienteIds, array $vendedorIds = []): void
    {
        $empresaIds = ClienteCuentacorrienteReporteFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('cliente_cuentacorriente.empresa_id', $empresaIds);
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

        if ($vendedorIds !== []) {
            $query->whereIn('cliente.vendedor_id', $vendedorIds);
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

        $empresaIds = ClienteCuentacorrienteReporteFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
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

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<int>  $empresaIds
     * @return array<string, mixed>
     */
    private function generarPorEmpresa(array $filtros, array $empresaIds): array
    {
        $filas = [];
        $secciones = [];
        $advertencias = [];
        $clientesResueltos = [];
        $vendedoresResueltos = [];
        $stats = ['clientes' => 0, 'vendedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0];
        $totales = $this->totalesVacios();
        $huboDatos = false;

        foreach ($empresaIds as $empresaId) {
            $res = $this->generarInterno(array_merge($filtros, [
                'empresa_ids' => [$empresaId],
                'empresa_id' => $empresaId,
                'consolidar_empresas' => true,
            ]));
            $advertencias = array_merge($advertencias, $res['advertencias'] ?? []);
            if ($clientesResueltos === [] && ! empty($res['clientes_resueltos'])) {
                $clientesResueltos = $res['clientes_resueltos'];
            }
            if ($vendedoresResueltos === [] && ! empty($res['vendedores_resueltos'])) {
                $vendedoresResueltos = $res['vendedores_resueltos'];
            }
            if (($res['filas'] ?? []) === []) {
                continue;
            }

            $huboDatos = true;
            $nombre = $this->nombreEmpresaPorId($empresaId);
            $secciones[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombre,
                'filas' => $res['filas'],
                'totales' => $res['totales'],
                'stats' => $res['stats'] ?? [],
            ];
            $filas[] = [
                'tipo' => 'header_empresa',
                'empresa_id' => $empresaId,
                'nombreempresa' => $nombre,
                'empresa_nombre' => $nombre,
                'comprobante' => 'Empresa',
            ];
            foreach ($res['filas'] as $fila) {
                if (($fila['tipo'] ?? '') === 'total_general') {
                    continue;
                }
                $filas[] = $fila;
            }
            $stats['clientes'] += (int) ($res['stats']['clientes'] ?? 0);
            $stats['vendedores'] += (int) ($res['stats']['vendedores'] ?? 0);
            $stats['movimientos'] += (int) ($res['stats']['movimientos'] ?? 0);
            $stats['aplicaciones'] += (int) ($res['stats']['aplicaciones'] ?? 0);
            $totales['debe'] += (float) ($res['totales']['debe'] ?? 0);
            $totales['haber'] += (float) ($res['totales']['haber'] ?? 0);
            $totales['pendiente'] += (float) ($res['totales']['pendiente'] ?? 0);
            $totales['modo'] = (string) ($res['totales']['modo'] ?? $totales['modo']);
            $totales['abreviatura'] = (string) ($res['totales']['abreviatura'] ?? $totales['abreviatura']);
        }

        $advertencias = array_values(array_unique($advertencias));
        if (! $huboDatos) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'advertencias' => $advertencias !== []
                    ? $advertencias
                    : ['Sin movimientos para los filtros indicados.'],
                'stats' => ['clientes' => 0, 'vendedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'clientes_resueltos' => $clientesResueltos,
                'vendedores_resueltos' => $vendedoresResueltos,
                'secciones' => [],
            ];
        }

        $modo = (string) ($totales['modo'] ?? ClienteCuentacorrienteReporteFiltros::MODO_DEUDA);
        $filas[] = [
            'tipo' => 'total_general',
            'comprobante' => 'Total a cobrar',
            'cliente_codigo' => '',
            'cliente_nombre' => 'Total cuentas a cobrar',
            'debe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $totales['debe'] : null,
            'haber' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA ? $totales['haber'] : null,
            'importe' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $totales['pendiente'] : null,
            'saldo_pendiente' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_DEUDA ? $totales['pendiente'] : null,
            'saldo' => $modo === ClienteCuentacorrienteReporteFiltros::MODO_FICHA
                ? ($totales['debe'] - $totales['haber'])
                : null,
            'abreviatura' => (string) ($totales['abreviatura'] ?? CuentacorrienteSaldosPorMoneda::abreviaturaLocal()),
        ];

        return [
            'filas' => $filas,
            'totales' => $totales,
            'advertencias' => $advertencias,
            'stats' => $stats,
            'clientes_resueltos' => $clientesResueltos,
            'vendedores_resueltos' => $vendedoresResueltos,
            'secciones' => $secciones,
        ];
    }

    /**
     * @param  Collection<int, Cliente_Cuentacorriente>  $movimientos
     */
    private function nombreEmpresaUnicaGrupo(Collection $movimientos): string
    {
        $nombres = $movimientos
            ->map(static fn ($m) => (string) ($m->empresas->nombre ?? ''))
            ->filter(static fn (string $n) => $n !== '')
            ->unique()
            ->values();

        return $nombres->count() === 1 ? (string) $nombres->first() : '';
    }

    private function nombreEmpresaPorId(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        return (string) (Empresa::query()->whereKey($empresaId)->value('nombre') ?? '');
    }
}
