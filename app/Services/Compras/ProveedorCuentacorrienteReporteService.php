<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Configuracion\Empresa;
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
     *   proveedores_resueltos: list<array{id:int,codigo:string,nombre:string}>,
     *   secciones: list<array<string, mixed>>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaIds = ProveedorCuentacorrienteReporteFiltros::empresaIds($filtros);
        if (ProveedorCuentacorrienteReporteFiltros::consolidarEmpresas($filtros) || $empresaIds === []) {
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
                'columnas_saldo' => [],
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
        $compactoDeuda = $soloTotales && $modo !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;
        $forzarDia = ($filtros['cotizacion_modo'] ?? '') === ProveedorCuentacorrienteReporteFiltros::COTIZACION_DIA;
        $conteoCcPorComprobante = $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA
            ? []
            : $this->conteoMovimientosPorComprobante($movimientos);

        $porProveedor = $movimientos->groupBy(fn ($m) => (int) $m->proveedor_id);
        $filas = [];
        $totalDebe = 0.0;
        $totalHaber = 0.0;
        $totalPendiente = 0.0;
        $totalesSaldosPorMoneda = [];
        $columnasSaldo = [];
        $movimientosCount = 0;
        $aplicacionesCount = 0;
        $cotizacionesDiaUsadas = 0;
        $esFicha = $modo === ProveedorCuentacorrienteReporteFiltros::MODO_FICHA;

        foreach ($porProveedor as $proveedorId => $movsProveedor) {
            /** @var Collection<int, Proveedor_Cuentacorriente> $movsProveedor */
            $primero = $movsProveedor->first();
            $proveedorCodigo = trim((string) ($primero->proveedores->codigo ?? ''));
            $proveedorNombre = (string) ($primero->proveedores->nombre ?? '');
            $nombreEmpresa = $this->nombreEmpresaUnicaGrupo($movsProveedor);

            if (! $compactoDeuda) {
                $filas[] = [
                    'tipo' => 'header_proveedor',
                    'proveedor_id' => (int) $proveedorId,
                    'proveedor_codigo' => $proveedorCodigo,
                    'proveedor_nombre' => $proveedorNombre,
                    'nombreempresa' => $nombreEmpresa,
                    'empresa_id' => (int) ($primero->empresa_id ?? 0),
                ];
            }

            $saldoCorrido = 0.0;
            $saldoCorridoPesos = 0.0;
            $subDebe = 0.0;
            $subHaber = 0.0;
            $subPendiente = 0.0;
            $subImporte = 0.0;
            $subAplicado = 0.0;
            $saldoParcial = 0.0;

            if ($modo !== ProveedorCuentacorrienteReporteFiltros::MODO_FICHA) {
                $movsProveedor = $this->ordenarPorFechaComprobante($movsProveedor);
            }

            $saldosMoneda = [];
            if ($esFicha) {
                $saldoAnterior = $this->saldoAnteriorProveedor(
                    (int) $proveedorId,
                    $filtros,
                    $enPesos,
                    $forzarDia
                );
                $saldoCorrido = $saldoAnterior['origen'];
                $saldoCorridoPesos = $saldoAnterior['pesos'];
                $saldosMoneda = $saldoAnterior['por_moneda'];
                foreach ($saldosMoneda as $monedaIdAnterior => $montoAnterior) {
                    if (abs((float) $montoAnterior) > 0.0001) {
                        $this->registrarColumnaSaldo(
                            $columnasSaldo,
                            (int) $monedaIdAnterior,
                            (string) ($saldoAnterior['abreviaturas'][$monedaIdAnterior] ?? '')
                        );
                    }
                }
                if (! $soloTotales && $saldosMoneda !== [] && (abs($saldoCorrido) > 0.0001 || abs($saldoCorridoPesos) > 0.0001)) {
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
                        'saldos_por_moneda' => $saldosMoneda,
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

                if ($esFicha) {
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
                    $monedaNativaId = (int) $conv['moneda_id'];
                    $saldosMoneda[$monedaNativaId] = round(($saldosMoneda[$monedaNativaId] ?? 0) + $totalOrigen, 2);
                    $this->registrarColumnaSaldo(
                        $columnasSaldo,
                        $monedaNativaId,
                        CuentacorrienteSaldosPorMoneda::abreviaturaDe($mov)
                    );
                } else {
                    [$importeMostrar, $aplicadoMostrar] = $this->columnasImporteAplicadoDeuda(
                        $mov,
                        $conteoCcPorComprobante,
                        $totalOrigen,
                        $pendienteOrigen,
                        $importeMostrar,
                        $aplicadoMostrar,
                    );
                    $subImporte += abs($importeMostrar);
                    $subAplicado += abs($aplicadoMostrar);
                    $subPendiente += $pendienteMostrar;
                    $totalPendiente += $pendientePesos;
                    $saldoParcial = round($saldoParcial + $pendienteMostrar, 2);
                }

                if ($soloTotales) {
                    continue;
                }

                $nombreEmpresaMov = (string) ($mov->empresas->nombre ?? '');
                $filaMov = [
                    'tipo' => 'movimiento',
                    'id' => (int) $mov->id,
                    'proveedor_id' => (int) $proveedorId,
                    'proveedor_codigo' => $proveedorCodigo,
                    'proveedor_nombre' => $proveedorNombre,
                    'nombreempresa' => $nombreEmpresaMov,
                    'empresa_id' => (int) ($mov->empresa_id ?? 0),
                    'fecha' => $this->fmtFecha(ProveedorCuentacorrienteGrillaSupport::fechaComprobante($mov)),
                    'fechavencimiento' => $this->fmtFecha(ProveedorCuentacorrienteGrillaSupport::fechaVencimiento($mov)),
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
                    'saldo_parcial' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA
                        ? $saldoParcial
                        : null,
                    'saldo' => $enPesos ? $saldoCorridoPesos : $saldoCorrido,
                    'saldo_pesos' => $saldoCorridoPesos,
                    'saldos_por_moneda' => $esFicha ? $saldosMoneda : null,
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
                        'nombreempresa' => $nombreEmpresaMov,
                        'fecha' => $this->fmtFecha(
                            ProveedorCuentacorrienteGrillaSupport::fechaComprobanteAplicacion($apl)
                        ),
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
                        'saldo_parcial' => null,
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
                'importe' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA ? $subImporte : null,
                'aplicado' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA && $subAplicado > 0.0001
                    ? $subAplicado
                    : null,
                'saldo_pendiente' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA ? $subPendiente : null,
                'saldo_parcial' => $modo === ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA ? $saldoParcial : null,
                'saldo' => $esFicha
                    ? ($enPesos ? $saldoCorridoPesos : $saldoCorrido)
                    : null,
                'saldo_pesos' => $saldoCorridoPesos,
                'saldos_por_moneda' => $esFicha ? $saldosMoneda : null,
                'abreviatura' => $enPesos ? CuentacorrienteSaldosPorMoneda::abreviaturaLocal() : '',
            ];

            if ($esFicha) {
                foreach ($saldosMoneda as $monedaIdTotal => $montoTotal) {
                    $totalesSaldosPorMoneda[$monedaIdTotal] = round(
                        ($totalesSaldosPorMoneda[$monedaIdTotal] ?? 0) + (float) $montoTotal,
                        2
                    );
                }
            }
        }

        if ($esFicha) {
            $this->registrarColumnaSaldo(
                $columnasSaldo,
                CuentacorrienteSaldosPorMoneda::monedaLocalId(),
                CuentacorrienteSaldosPorMoneda::abreviaturaLocal()
            );
        }
        $columnasSaldoLista = $esFicha
            ? CuentacorrienteSaldosPorMoneda::columnasSaldoFicha(array_values($columnasSaldo))
            : [];

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
                'saldos_por_moneda' => $totalesSaldosPorMoneda,
                'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
                'modo' => $modo,
            ],
            'columnas_saldo' => $columnasSaldoLista,
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
     * Cuenta corriente de Anita a veces guarda solo el saldo (sin filas de aplicación).
     * Con un único movimiento del documento, Importe es el total del comprobante y
     * Aplicado es lo ya cancelado. El saldo pendiente no se toca.
     *
     * @param  array<int, int>  $conteoCcPorComprobante
     * @return array{0: float, 1: float}
     */
    private function columnasImporteAplicadoDeuda(
        Proveedor_Cuentacorriente $mov,
        array $conteoCcPorComprobante,
        float $totalOrigen,
        float $pendienteOrigen,
        float $importeMostrar,
        float $aplicadoMostrar,
    ): array {
        $comprobante = $mov->comprobante_proveedores;
        $comprobanteId = (int) ($mov->comprobante_proveedor_id ?? 0);
        if ($comprobante === null || $comprobanteId <= 0 || (int) ($conteoCcPorComprobante[$comprobanteId] ?? 0) !== 1) {
            return [$importeMostrar, $aplicadoMostrar];
        }

        $monedaComprobante = (int) ($comprobante->moneda_id ?? 0);
        $monedaMovimiento = (int) ($mov->moneda_id ?? 0);
        if ($monedaComprobante > 0 && $monedaMovimiento > 0 && $monedaComprobante !== $monedaMovimiento) {
            return [$importeMostrar, $aplicadoMostrar];
        }

        $monto = abs((float) $comprobante->total);
        $pendienteAbs = abs($pendienteOrigen);
        if ($monto < 0.02 || $monto + 0.02 < $pendienteAbs) {
            return [$importeMostrar, $aplicadoMostrar];
        }

        $coef = 1.0;
        if (abs($totalOrigen) > 0.0001) {
            $coef = abs($importeMostrar) / abs($totalOrigen);
        }

        $importe = round($monto * $coef, 2);
        $aplicadoDocumento = round($monto - $pendienteAbs, 2);
        $aplicado = $aplicadoDocumento > 0.02 ? round($aplicadoDocumento * $coef, 2) : 0.0;

        return [$importe, $aplicado];
    }

    /**
     * @param  Collection<int, Proveedor_Cuentacorriente>  $movimientos
     * @return array<int, int>
     */
    private function conteoMovimientosPorComprobante(Collection $movimientos): array
    {
        $ids = $movimientos
            ->pluck('comprobante_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($ids === []) {
            return [];
        }

        $out = [];
        $rows = Proveedor_Cuentacorriente::query()
            ->whereIn('comprobante_proveedor_id', $ids)
            ->selectRaw('comprobante_proveedor_id, COUNT(*) as n')
            ->groupBy('comprobante_proveedor_id')
            ->get();
        foreach ($rows as $row) {
            $out[(int) $row->comprobante_proveedor_id] = (int) $row->n;
        }

        return $out;
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
                'comprobante_proveedores.comprobante_proveedor_cuotas',
                'comprobante_proveedor_cuotas',
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
                'comprobante_proveedores.comprobante_proveedor_cuotas',
                'comprobante_proveedor_cuotas',
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
        $empresaIds = ProveedorCuentacorrienteReporteFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('proveedor_cuentacorriente.empresa_id', $empresaIds);
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
     * Dentro del proveedor, la deuda se lista por la fecha del comprobante
     * (la misma que muestra la columna Fecha), no por la fecha de carga de la cuenta corriente.
     * En el mismo día, por número de factura de más vieja a más reciente
     * (letra, punto de venta y número). El vencimiento desempata cuotas del mismo comprobante.
     *
     * @param  Collection<int, Proveedor_Cuentacorriente>  $movimientos
     * @return Collection<int, Proveedor_Cuentacorriente>
     */
    private function ordenarPorFechaComprobante(Collection $movimientos): Collection
    {
        return $movimientos->sortBy(function (Proveedor_Cuentacorriente $mov): string {
            $fecha = $this->ymdOrden(ProveedorCuentacorrienteGrillaSupport::fechaComprobante($mov));
            $vencimiento = $this->ymdOrden(ProveedorCuentacorrienteGrillaSupport::fechaVencimiento($mov));

            return $fecha.'|'.$this->claveNumeroComprobante($mov).'|'.$vencimiento
                .'|'.str_pad((string) (int) $mov->id, 12, '0', STR_PAD_LEFT);
        })->values();
    }

    /**
     * Clave de orden del comprobante visible (A3-25181 antes que A3-25190).
     * Sin factura, usa el número del pago. Sin ninguno, queda al final del día.
     */
    private function claveNumeroComprobante(Proveedor_Cuentacorriente $mov): string
    {
        $comp = $mov->comprobante_proveedores;
        if ((int) ($mov->comprobante_proveedor_id ?? 0) > 0 && $comp !== null) {
            return sprintf(
                '%-4s|%08d|%012d',
                strtoupper(trim((string) ($comp->letra ?? ''))),
                (int) ($comp->sucursal ?? 0),
                (int) ($comp->numerocomprobante ?? 0)
            );
        }

        $pago = $mov->pagoproveedores;
        if ((int) ($mov->pagoproveedor_id ?? 0) > 0 && $pago !== null) {
            return sprintf(
                '%-4s|%08d|%012d',
                strtoupper(trim((string) ($pago->letra ?: ($pago->tipocomprobante ?? '')))),
                (int) ($pago->sucursal ?? 0),
                (int) ($pago->numerotransaccion ?? 0)
            );
        }

        return sprintf('ZZZZ|99999999|%012d', (int) $mov->id);
    }

    private function ymdOrden(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $fecha);
        if ($texto === '') {
            return '9999-12-31';
        }

        $ts = strtotime($texto);

        return $ts ? date('Y-m-d', $ts) : '9999-12-31';
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
            ->with([
                'monedas:id,abreviatura',
                'pagoproveedores:id,fecha',
                'comprobante_proveedor_aplicados:id,fechacomprobante',
                'proveedor_cuentacorriente_aplicados.pagoproveedores:id,fecha',
                'proveedor_cuentacorriente_aplicados.comprobante_proveedores:id,fechacomprobante',
            ])
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
     * @return array{origen: float, pesos: float, por_moneda: array<int, float>, abreviaturas: array<int, string>}
     */
    private function saldoAnteriorProveedor(int $proveedorId, array $filtros, bool $enPesos, bool $forzarDia): array
    {
        $vacio = ['origen' => 0.0, 'pesos' => 0.0, 'por_moneda' => [], 'abreviaturas' => []];
        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($fechaDesde === '') {
            return $vacio;
        }

        $query = Proveedor_Cuentacorriente::query()
            ->with('monedas:id,abreviatura')
            ->where('proveedor_id', $proveedorId)
            ->whereDate('fecha', '<', $fechaDesde);

        $empresaIds = ProveedorCuentacorrienteReporteFiltros::empresaIds($filtros);
        if ($empresaIds !== []) {
            $query->whereIn('empresa_id', $empresaIds);
        }

        $origen = 0.0;
        $pesos = 0.0;
        $porMoneda = [];
        $abreviaturas = [];
        foreach ($query->get(['id', 'total', 'moneda_id', 'cotizacion', 'fecha']) as $mov) {
            $monedaId = CuentacorrienteSaldosPorMoneda::monedaIdDe($mov);
            $total = (float) $mov->total;
            $origen += $total;
            $porMoneda[$monedaId] = round(($porMoneda[$monedaId] ?? 0) + $total, 2);
            $abreviatura = CuentacorrienteSaldosPorMoneda::abreviaturaDe($mov);
            if ($abreviatura !== '') {
                $abreviaturas[$monedaId] = $abreviatura;
            }
            $conv = $this->convertirMovimiento($mov, true, $forzarDia);
            $pesos += $conv['importe_firmado_pesos'];
        }

        return [
            'origen' => $origen,
            'pesos' => $pesos,
            'por_moneda' => $porMoneda,
            'abreviaturas' => $abreviaturas,
        ];
    }

    /**
     * @param  array<int, array{moneda_id: int, abreviatura: string, es_local: bool}>  $columnas
     */
    private function registrarColumnaSaldo(array &$columnas, int $monedaId, string $abreviatura): void
    {
        if ($monedaId <= 0) {
            return;
        }

        $abreviatura = trim($abreviatura);
        $localId = CuentacorrienteSaldosPorMoneda::monedaLocalId();
        if (! isset($columnas[$monedaId])) {
            $columnas[$monedaId] = [
                'moneda_id' => $monedaId,
                'abreviatura' => $abreviatura !== ''
                    ? $abreviatura
                    : ($monedaId === $localId ? CuentacorrienteSaldosPorMoneda::abreviaturaLocal() : 'ME'),
                'es_local' => $monedaId === $localId,
            ];

            return;
        }

        if ($columnas[$monedaId]['abreviatura'] === '' && $abreviatura !== '') {
            $columnas[$monedaId]['abreviatura'] = $abreviatura;
        }
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
     * @return array{debe: float, haber: float, pendiente: float, saldos_por_moneda: array<int, float>, abreviatura: string, modo: string}
     */
    private function totalesVacios(): array
    {
        return [
            'debe' => 0.0,
            'haber' => 0.0,
            'pendiente' => 0.0,
            'saldos_por_moneda' => [],
            'abreviatura' => CuentacorrienteSaldosPorMoneda::abreviaturaLocal(),
            'modo' => ProveedorCuentacorrienteReporteFiltros::MODO_DEUDA,
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
        $proveedoresResueltos = [];
        $stats = ['proveedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0];
        $totales = $this->totalesVacios();
        $columnasSaldo = [];
        $huboDatos = false;

        foreach ($empresaIds as $empresaId) {
            $res = $this->generarInterno(array_merge($filtros, [
                'empresa_ids' => [$empresaId],
                'empresa_id' => $empresaId,
                'consolidar_empresas' => true,
            ]));
            $advertencias = array_merge($advertencias, $res['advertencias'] ?? []);
            if ($proveedoresResueltos === [] && ! empty($res['proveedores_resueltos'])) {
                $proveedoresResueltos = $res['proveedores_resueltos'];
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
                $filas[] = $fila;
            }
            $stats['proveedores'] += (int) ($res['stats']['proveedores'] ?? 0);
            $stats['movimientos'] += (int) ($res['stats']['movimientos'] ?? 0);
            $stats['aplicaciones'] += (int) ($res['stats']['aplicaciones'] ?? 0);
            $totales['debe'] += (float) ($res['totales']['debe'] ?? 0);
            $totales['haber'] += (float) ($res['totales']['haber'] ?? 0);
            $totales['pendiente'] += (float) ($res['totales']['pendiente'] ?? 0);
            foreach ($res['totales']['saldos_por_moneda'] ?? [] as $monedaIdTotal => $montoTotal) {
                $totales['saldos_por_moneda'][(int) $monedaIdTotal] = round(
                    ($totales['saldos_por_moneda'][(int) $monedaIdTotal] ?? 0) + (float) $montoTotal,
                    2
                );
            }
            foreach ($res['columnas_saldo'] ?? [] as $columnaSaldo) {
                $this->registrarColumnaSaldo(
                    $columnasSaldo,
                    (int) ($columnaSaldo['moneda_id'] ?? 0),
                    (string) ($columnaSaldo['abreviatura'] ?? '')
                );
            }
            $totales['modo'] = (string) ($res['totales']['modo'] ?? $totales['modo']);
            $totales['abreviatura'] = (string) ($res['totales']['abreviatura'] ?? $totales['abreviatura']);
        }

        $advertencias = array_values(array_unique($advertencias));
        if (! $huboDatos) {
            return [
                'filas' => [],
                'totales' => $this->totalesVacios(),
                'columnas_saldo' => [],
                'advertencias' => $advertencias !== []
                    ? $advertencias
                    : ['Sin movimientos para los filtros indicados.'],
                'stats' => ['proveedores' => 0, 'movimientos' => 0, 'aplicaciones' => 0],
                'proveedores_resueltos' => $proveedoresResueltos,
                'secciones' => [],
            ];
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'columnas_saldo' => CuentacorrienteSaldosPorMoneda::columnasSaldoFicha(array_values($columnasSaldo)),
            'advertencias' => $advertencias,
            'stats' => $stats,
            'proveedores_resueltos' => $proveedoresResueltos,
            'secciones' => $secciones,
        ];
    }

    /**
     * @param  Collection<int, Proveedor_Cuentacorriente>  $movimientos
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
