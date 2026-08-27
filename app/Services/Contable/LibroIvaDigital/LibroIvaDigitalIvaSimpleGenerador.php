<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaBridgeReader;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaBridgeReader;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use App\Support\Contable\CierreRendicionMaquinaConfigSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Totales abiertos por actividad ARCA para IVA Simple (DJ).
 * Débito: actividad de venta.actividad_arca_id o, si falta, puntoventa.actividad_arca_id.
 * Crédito: compras ERP + Anita (misma fuente que Libro IVA Digital compras).
 */
class LibroIvaDigitalIvaSimpleGenerador
{
    public function __construct(
        private readonly LibroIvaDigitalComprasAnitaBridgeReader $comprasAnitaBridgeReader,
        private readonly LibroIvaDigitalVentasFslAnitaBridgeReader $fslAnitaBridgeReader,
    ) {
    }

    /**
     * @param  array{
     *     por_fecha_jornada?: bool,
     *     prorrateo_cf_global?: bool,
     *     completar_compras_anita?: bool,
     *     completar_fsl_anita?: bool,
     *     ventas_registros?: list<array<string, mixed>>,
     *     compras_registros?: list<array<string, mixed>>
     * }  $opciones
     * @return array{
     *     debito_fiscal: string,
     *     credito_fiscal: string,
     *     restitucion_debito: string,
     *     restitucion_credito: string,
     *     detalle_debito: list<array<string, mixed>>,
     *     detalle_restitucion_debito: list<array<string, mixed>>,
     *     detalle_credito: list<array<string, mixed>>,
     *     detalle_restitucion_credito: list<array<string, mixed>>,
     *     resumen_por_actividad: list<array<string, mixed>>,
     *     resumen_por_concepto: list<array<string, mixed>>,
     *     resumen: array<string, int|float>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));
        $porFechaJornada = (bool) ($opciones['por_fecha_jornada'] ?? false);
        $completarAnita = (bool) ($opciones['completar_compras_anita'] ?? true);
        $completarFslAnita = (bool) ($opciones['completar_fsl_anita'] ?? true);
        $prorrateoGlobal = (bool) ($opciones['prorrateo_cf_global'] ?? false);

        $ventasRegistros = $opciones['ventas_registros'] ?? null;
        $comprasRegistros = $opciones['compras_registros'] ?? null;

        if (is_array($ventasRegistros)) {
            $desdeLibro = LibroIvaDigitalIvaSimpleSupport::debitoDesdeRegistrosLibro($ventasRegistros);
            $ventasDebito = [
                'lineas' => array_map(
                    static fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila),
                    $desdeLibro['detalle'],
                ),
                'detalle' => $desdeLibro['detalle'],
            ];
            $ventasRestitucion = [
                'lineas' => array_map(
                    static fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila, true),
                    $desdeLibro['detalle_restitucion'],
                ),
                'detalle' => $desdeLibro['detalle_restitucion'],
            ];
        } else {
            $ventasDebito = $this->ventasDebitoFiscal($empresaId, $desde, $hasta, false, $porFechaJornada, $completarFslAnita);
            $ventasRestitucion = $this->ventasDebitoFiscal($empresaId, $desde, $hasta, true, $porFechaJornada, false);
        }

        if (is_array($comprasRegistros)) {
            $compras = LibroIvaDigitalIvaSimpleSupport::creditoDesdeRegistrosLibro(
                $comprasRegistros,
                $prorrateoGlobal,
            );
        } else {
            $compras = $this->comprasCreditoFiscal(
                $empresaId,
                $desde,
                $hasta,
                $completarAnita,
                $prorrateoGlobal,
            );
        }

        $ajustes = $this->agregarAjustesManuales($empresaId, $anio, $mes);

        $detalleDebito = array_merge($ventasDebito['detalle'], $ajustes['detalle_debito']);
        $detalleRestitucionDebito = array_merge($ventasRestitucion['detalle'], $ajustes['detalle_restitucion_debito']);

        $filasDebito = array_merge(
            $ventasDebito['lineas'],
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila), $ajustes['detalle_debito']),
        );
        $filasRestitucionDebito = array_merge(
            $ventasRestitucion['lineas'],
            array_map(fn (array $fila) => LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila, true), $ajustes['detalle_restitucion_debito']),
        );

        $acumCredito = [];
        $acumRestitucionCredito = [];
        foreach ($compras['detalle'] as $fila) {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumCredito, $fila, $prorrateoGlobal, false);
        }
        foreach ($ajustes['detalle_credito'] as $fila) {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumCredito, $fila, $prorrateoGlobal, false);
        }
        foreach ($compras['detalle_restitucion'] as $fila) {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumRestitucionCredito, $fila, $prorrateoGlobal, true);
        }
        foreach ($ajustes['detalle_restitucion_credito'] as $fila) {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumRestitucionCredito, $fila, $prorrateoGlobal, true);
        }

        $credito = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acumCredito, false);
        $restitucionCredito = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acumRestitucionCredito, true);

        $resumenPorActividad = LibroIvaDigitalIvaSimpleSupport::resumenPorActividad(
            $detalleDebito,
            $detalleRestitucionDebito,
        );
        $resumenPorConcepto = LibroIvaDigitalIvaSimpleSupport::resumenPorConcepto(
            $credito['detalle'],
            $restitucionCredito['detalle'],
        );

        $totalIvaDebito = $this->sumarIvaDesdeLineasDebito($filasDebito);
        $sinActividad = $this->contarSinActividad($filasDebito, $filasRestitucionDebito);

        return [
            'debito_fiscal' => implode("\r\n", $filasDebito),
            'credito_fiscal' => implode("\r\n", $credito['lineas']),
            'restitucion_debito' => implode("\r\n", $filasRestitucionDebito),
            'restitucion_credito' => implode("\r\n", $restitucionCredito['lineas']),
            'detalle_debito' => $detalleDebito,
            'detalle_restitucion_debito' => $detalleRestitucionDebito,
            'detalle_credito' => $credito['detalle'],
            'detalle_restitucion_credito' => $restitucionCredito['detalle'],
            'resumen_por_actividad' => $resumenPorActividad,
            'resumen_por_concepto' => $resumenPorConcepto,
            'resumen' => [
                'renglones_debito' => count($filasDebito),
                'renglones_credito' => count($credito['lineas']),
                'renglones_restitucion_debito' => count($filasRestitucionDebito),
                'renglones_restitucion_credito' => count($restitucionCredito['lineas']),
                'total_iva_debito' => round($totalIvaDebito, 2),
                'total_iva_credito' => round(array_sum(array_column($credito['detalle'], 'iva')), 2),
                'total_iva_restitucion_credito' => round(array_sum(array_column($restitucionCredito['detalle'], 'iva')), 2),
                'total_exento_compras' => round((float) ($compras['total_exento'] ?? 0), 2),
                'total_no_integra_compras' => round((float) ($compras['total_no_integra'] ?? 0), 2),
                'total_monotributo_compras' => round((float) ($compras['total_monotributo'] ?? 0), 2),
                'sin_actividad_arca' => $sinActividad,
                'actividades' => count($resumenPorActividad),
            ],
        ];
    }

    /**
     * @return array{lineas: list<string>, detalle: list<array<string, mixed>>}
     */
    private function ventasDebitoFiscal(
        int $empresaId,
        string $desde,
        string $hasta,
        bool $soloNotasCredito,
        bool $porFechaJornada,
        bool $completarFslAnita = false,
    ): array {
        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->join('venta_impuesto as vi_neto', function ($join): void {
                $join->on('vi_neto.venta_id', '=', 'venta.id')
                    ->where('vi_neto.concepto', 'like', 'Gravado al%');
            })
            ->join('venta_impuesto as vi_iva', function ($join): void {
                $join->on('vi_iva.venta_id', '=', 'venta.id')
                    ->where(function ($q): void {
                        $q->where('vi_iva.concepto', 'like', 'Iva %')
                            ->orWhere('vi_iva.concepto', 'like', 'IVA%');
                    })
                    ->whereColumn('vi_iva.tasa', 'vi_neto.tasa');
            })
            ->leftJoin('actividad_arca', 'actividad_arca.id', '=', 'venta.actividad_arca_id')
            ->leftJoin('actividad_arca as aa_pv', 'aa_pv.id', '=', 'puntoventa.actividad_arca_id')
            ->leftJoin('condicioniva', 'condicioniva.id', '=', 'venta.condicioniva_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('tt.abreviatura', '<>', 'IZV');

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query, 'venta', 'tt');

        if ($soloNotasCredito) {
            $query->where('tt.signo', '<', 0);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            });
        }

        $rows = $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(actividad_arca.codigoarca), ''), NULLIF(TRIM(aa_pv.codigoarca), ''), '000000') as actividad,
                COALESCE(NULLIF(TRIM(actividad_arca.nombre), ''), NULLIF(TRIM(aa_pv.nombre), ''), '') as actividad_nombre,
                condicioniva.codigoexterno as cond_iva,
                vi_neto.tasa as tasa,
                SUM(ABS(vi_neto.importe)) as neto,
                SUM(ABS(vi_iva.importe)) as iva
            ")
            ->groupBy('actividad', 'actividad_nombre', 'cond_iva', 'tasa')
            ->havingRaw('SUM(ABS(vi_neto.importe)) > 0')
            ->get();

        $lineas = [];
        $detalle = [];
        foreach ($rows as $row) {
            $actividad = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad((string) $row->actividad);
            $tipoSujeto = LibroIvaDigitalMapeosSupport::tipoSujetoCompradorIvaSimple((string) $row->cond_iva);
            $codAlicuota = LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple((float) $row->tasa);
            $neto = (float) $row->neto;
            $iva = (float) $row->iva;

            $fila = [
                'actividad_codigo' => $actividad,
                'actividad_nombre' => (string) $row->actividad_nombre,
                'tipo_operacion' => '1',
                'tipo_sujeto' => $tipoSujeto,
                'alicuota_codigo' => $codAlicuota,
                'tasa' => (float) $row->tasa,
                'neto' => $neto,
                'iva' => $iva,
                'iva_computable' => $iva,
                'exento' => 0.0,
                'restitucion' => $soloNotasCredito,
            ];

            $detalle[] = $fila;
            $lineas[] = LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila, $soloNotasCredito);
        }

        if (! $soloNotasCredito) {
            $exentas = $this->ventasExentasDebito($empresaId, $desde, $hasta, $porFechaJornada, $completarFslAnita);
            $lineas = array_merge($lineas, $exentas['lineas']);
            $detalle = array_merge($detalle, $exentas['detalle']);
        }

        return ['lineas' => $lineas, 'detalle' => $detalle];
    }

    /**
     * @return array{lineas: list<string>, detalle: list<array<string, mixed>>}
     */
    private function ventasExentasDebito(
        int $empresaId,
        string $desde,
        string $hasta,
        bool $porFechaJornada,
        bool $completarFslAnita,
    ): array {
        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->join('venta_impuesto as vi', function ($join): void {
                $join->on('vi.venta_id', '=', 'venta.id')
                    ->where(function ($q): void {
                        $q->where('vi.concepto', 'like', '%Exento%')
                            ->orWhere('vi.concepto', 'like', '%No Gravado%');
                    });
            })
            ->leftJoin('actividad_arca', 'actividad_arca.id', '=', 'venta.actividad_arca_id')
            ->leftJoin('actividad_arca as aa_pv', 'aa_pv.id', '=', 'puntoventa.actividad_arca_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('tt.abreviatura', '<>', 'IZV')
            ->where(function ($q): void {
                $q->whereNull('tt.signo')->orWhere('tt.signo', '>=', 0);
            });

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query, 'venta', 'tt');

        $exentos = $query
            ->selectRaw("
                COALESCE(NULLIF(TRIM(actividad_arca.codigoarca), ''), NULLIF(TRIM(aa_pv.codigoarca), ''), '000000') as actividad,
                COALESCE(NULLIF(TRIM(actividad_arca.nombre), ''), NULLIF(TRIM(aa_pv.nombre), ''), '') as actividad_nombre,
                SUM(ABS(vi.importe)) as exento
            ")
            ->groupBy('actividad', 'actividad_nombre')
            ->havingRaw('SUM(ABS(vi.importe)) > 0')
            ->get();

        /** @var array<string, array{actividad_codigo: string, actividad_nombre: string, exento: float}> $acum */
        $acum = [];
        foreach ($exentos as $row) {
            $actividad = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad((string) $row->actividad);
            $clave = $actividad;
            if (! isset($acum[$clave])) {
                $acum[$clave] = [
                    'actividad_codigo' => $actividad,
                    'actividad_nombre' => (string) $row->actividad_nombre,
                    'exento' => 0.0,
                ];
            }
            $acum[$clave]['exento'] += (float) $row->exento;
        }

        if ($completarFslAnita) {
            $pvFslDefault = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);
            $clavesErpFsl = $this->clavesFslErp($empresaId, $desde, $hasta, $porFechaJornada);
            foreach ($this->fslAnitaBridgeReader->listarPeriodo($empresaId, $desde, $hasta, $porFechaJornada) as $filaAnita) {
                $claveNat = LibroIvaDigitalVentasFslAnitaArmadoSupport::claveDesdeFilaAnita($filaAnita, $pvFslDefault);
                if (isset($clavesErpFsl[$claveNat])) {
                    continue;
                }
                $fila = LibroIvaDigitalVentasFslAnitaArmadoSupport::filaIvaSimpleExento(
                    $filaAnita,
                    $porFechaJornada,
                    $pvFslDefault,
                );
                if ($fila === null) {
                    continue;
                }
                $actividad = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad(
                    (string) $fila['actividad_codigo'],
                );
                if (! isset($acum[$actividad])) {
                    $acum[$actividad] = [
                        'actividad_codigo' => $actividad,
                        'actividad_nombre' => (string) $fila['actividad_nombre'],
                        'exento' => 0.0,
                    ];
                }
                $acum[$actividad]['exento'] += (float) $fila['exento'];
            }
        }

        $lineas = [];
        $detalle = [];
        foreach ($acum as $row) {
            $exento = (float) $row['exento'];
            if ($exento <= 0.0001) {
                continue;
            }
            $fila = [
                'actividad_codigo' => $row['actividad_codigo'],
                'actividad_nombre' => $row['actividad_nombre'],
                'tipo_operacion' => '3',
                'tipo_sujeto' => null,
                'alicuota_codigo' => null,
                'tasa' => null,
                'neto' => 0.0,
                'iva' => 0.0,
                'iva_computable' => 0.0,
                'exento' => $exento,
                'restitucion' => false,
            ];
            $detalle[] = $fila;
            $lineas[] = LibroIvaDigitalIvaSimpleSupport::lineaDebitoFiscal($fila);
        }

        return ['lineas' => $lineas, 'detalle' => $detalle];
    }

    /**
     * @return array<string, true>
     */
    private function clavesFslErp(
        int $empresaId,
        string $desde,
        string $hasta,
        bool $porFechaJornada,
    ): array {
        $query = DB::table('venta')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->join('tipotransaccion as tt', 'tt.id', '=', 'venta.tipotransaccion_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where('tt.abreviatura', 'FSL');

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);

        $claves = [];
        foreach ($query->select('puntoventa.codigo', 'venta.numerocomprobante')->get() as $row) {
            $pv = (int) preg_replace('/\D+/', '', (string) $row->codigo);
            $claves[LibroIvaDigitalVentasFslAnitaArmadoSupport::claveNatural(
                $pv,
                (int) $row->numerocomprobante,
            )] = true;
        }

        return $claves;
    }

    /**
     * @return array{
     *     detalle: list<array<string, mixed>>,
     *     detalle_restitucion: list<array<string, mixed>>
     * }
     */
    private function comprasCreditoFiscal(
        int $empresaId,
        string $desde,
        string $hasta,
        bool $completarAnita,
        bool $prorrateoGlobal,
    ): array {
        $acumCredito = [];
        $acumRestitucion = [];
        /** @var array<string, true> $clavesErp */
        $clavesErp = [];
        /** @var array<int, true> $nrosInternosErp */
        $nrosInternosErp = [];

        Comprobante_Proveedor::query()
            ->where('comprobante_proveedor.empresa_id', $empresaId)
            ->whereBetween('comprobante_proveedor.fechaiva', [$desde, $hasta])
            ->where('comprobante_proveedor.estado', '<>', ComprobanteProveedorEstados::ANULADO)
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'comprobante_proveedor_conceptos.concepto_ivacompras.impuestos',
            ])
            ->orderBy('comprobante_proveedor.fechaiva')
            ->lazy(100)
            ->each(function (Comprobante_Proveedor $cp) use (
                &$acumCredito,
                &$acumRestitucion,
                &$clavesErp,
                &$nrosInternosErp,
                $prorrateoGlobal,
            ): void {
                $clave = $this->claveErp($cp);
                if ($clave !== '') {
                    $clavesErp[$clave] = true;
                }
                $nroInterno = (int) ($cp->anita_nro_interno ?? 0);
                if ($nroInterno > 0) {
                    $nrosInternosErp[$nroInterno] = true;
                }

                $letra = strtoupper((string) ($cp->letra ?: 'A'));
                $totales = LibroIvaDigitalConceptoIvacompraSupport::desglosarComprobante(
                    $cp->comprobante_proveedor_conceptos,
                    $letra,
                );
                $esNc = LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoTipo($cp->tipotransaccion_compras);
                foreach ($totales['alicuotas'] as $row) {
                    $this->acumularFilaCredito($acumCredito, $acumRestitucion, $row, $prorrateoGlobal, $esNc);
                }
            });

        if ($completarAnita) {
            $filasAnita = $this->comprasAnitaBridgeReader->listarPeriodo($empresaId, $desde, $hasta);
            foreach ($filasAnita as $fila) {
                $compra = $fila['compra'];
                $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
                if ($nroInterno > 0 && isset($nrosInternosErp[$nroInterno])) {
                    continue;
                }

                $clave = LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural(
                    (string) ($compra['com_proveedor'] ?? ''),
                    (string) ($compra['com_tipo'] ?? ''),
                    (string) ($compra['com_letra'] ?? ''),
                    (int) ($compra['com_sucursal'] ?? 0),
                    (int) ($compra['com_nro'] ?? 0),
                );
                if (isset($clavesErp[$clave])) {
                    continue;
                }

                $registro = LibroIvaDigitalComprasAnitaArmadoSupport::armarRegistro(
                    $compra,
                    $fila['conceptos'],
                    $prorrateoGlobal,
                );
                if ($registro === null) {
                    continue;
                }

                $letra = strtoupper(substr(trim((string) ($compra['com_letra'] ?? 'A')), 0, 1));
                $tipoAbrev = strtoupper(substr(trim((string) ($compra['com_tipo'] ?? '')), 0, 3));
                $tipo = LibroIvaDigitalComprasAnitaArmadoSupport::tipoPorAbreviatura($tipoAbrev);
                $esNc = LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoTipo($tipo);
                $alicuotas = LibroIvaDigitalComprasAnitaArmadoSupport::alicuotasIvaSimple(
                    $fila['conceptos'],
                    $letra,
                );
                foreach ($alicuotas as $row) {
                    $this->acumularFilaCredito($acumCredito, $acumRestitucion, $row, $prorrateoGlobal, $esNc);
                }
            }
        }

        $credito = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acumCredito, false);
        $restitucion = LibroIvaDigitalIvaSimpleSupport::lineasDesdeAcumuladoCredito($acumRestitucion, true);

        return [
            'detalle' => $credito['detalle'],
            'detalle_restitucion' => $restitucion['detalle'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $acumCredito
     * @param  array<string, array<string, mixed>>  $acumRestitucion
     * @param  array<string, mixed>  $row
     */
    private function acumularFilaCredito(
        array &$acumCredito,
        array &$acumRestitucion,
        array $row,
        bool $prorrateoGlobal,
        bool $esNc,
    ): void {
        if ($esNc) {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumRestitucion, $row, $prorrateoGlobal, true);
        } else {
            LibroIvaDigitalIvaSimpleSupport::acumularCredito($acumCredito, $row, $prorrateoGlobal, false);
        }
    }

    private function claveErp(Comprobante_Proveedor $cp): string
    {
        $proveedor = str_pad(trim((string) ($cp->proveedores->codigo ?? '')), 6, '0', STR_PAD_LEFT);
        $tipo = strtoupper(substr(trim((string) ($cp->tipotransaccion_compras->abreviatura ?? '')), 0, 3));
        $letra = strtoupper(substr(trim((string) ($cp->letra ?: 'A')), 0, 1));

        if ($proveedor === '000000' || $tipo === '') {
            return '';
        }

        return LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural(
            $proveedor,
            $tipo,
            $letra,
            (int) $cp->sucursal,
            (int) $cp->numerocomprobante,
        );
    }

    /**
     * @return array{
     *     detalle_debito: list<array<string, mixed>>,
     *     detalle_credito: list<array<string, mixed>>,
     *     detalle_restitucion_debito: list<array<string, mixed>>,
     *     detalle_restitucion_credito: list<array<string, mixed>>
     * }
     */
    private function agregarAjustesManuales(int $empresaId, int $anio, int $mes): array
    {
        $resultado = [
            'detalle_debito' => [],
            'detalle_credito' => [],
            'detalle_restitucion_debito' => [],
            'detalle_restitucion_credito' => [],
        ];

        if (! Schema::hasTable('libro_iva_ajuste_dj')) {
            return $resultado;
        }

        $ajustes = DB::table('libro_iva_ajuste_dj')
            ->where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->get();

        foreach ($ajustes as $row) {
            $params = $this->parseObservacionAjuste((string) ($row->observacion ?? ''));
            $tipo = strtoupper(trim((string) $row->tipo));
            $neto = (float) ($row->neto_gravado ?? 0);
            $iva = (float) ($row->importe ?? 0);
            $computable = (float) ($row->importe_computable ?? $iva);

            switch ($tipo) {
                case 'DEBITO_FISCAL':
                case 'DEBITO':
                    $resultado['detalle_debito'][] = [
                        'actividad_codigo' => $params['actividad'] ?? '000000',
                        'actividad_nombre' => '',
                        'tipo_operacion' => $params['tipo_operacion'] ?? '1',
                        'tipo_sujeto' => (int) ($params['tipo_sujeto'] ?? 3),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $computable,
                        'exento' => 0.0,
                        'restitucion' => false,
                    ];
                    break;
                case 'CREDITO_FISCAL':
                case 'CREDITO':
                    $resultado['detalle_credito'][] = [
                        'concepto' => (int) ($params['concepto'] ?? 1),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $computable,
                    ];
                    break;
                case 'RESTITUCION_DEBITO':
                    $resultado['detalle_restitucion_debito'][] = [
                        'actividad_codigo' => $params['actividad'] ?? '000000',
                        'actividad_nombre' => '',
                        'tipo_operacion' => $params['tipo_operacion'] ?? '1',
                        'tipo_sujeto' => (int) ($params['tipo_sujeto'] ?? 3),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $iva,
                        'exento' => 0.0,
                        'restitucion' => true,
                    ];
                    break;
                case 'RESTITUCION_CREDITO':
                    $resultado['detalle_restitucion_credito'][] = [
                        'concepto' => (int) ($params['concepto'] ?? 1),
                        'alicuota_codigo' => (int) ($params['alicuota'] ?? 5),
                        'neto' => $neto,
                        'iva' => $iva,
                        'iva_computable' => $iva,
                    ];
                    break;
            }
        }

        return $resultado;
    }

    /**
     * @return array<string, string>
     */
    private function parseObservacionAjuste(string $observacion): array
    {
        $params = [];
        foreach (explode(';', $observacion) as $parte) {
            if (! str_contains($parte, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $parte, 2));
            $params[$clave] = $valor;
        }

        return $params;
    }

    /**
     * @param  list<string>  $lineas
     */
    private function sumarIvaDesdeLineasDebito(array $lineas): float
    {
        $total = 0.0;
        foreach ($lineas as $linea) {
            $partes = explode(';', rtrim($linea, ';'));
            if (($partes[1] ?? '') !== '1') {
                continue;
            }
            $iva = isset($partes[5]) ? (float) str_replace(',', '.', $partes[5]) : 0.0;
            $total += $iva;
        }

        return $total;
    }

    /**
     * @param  list<string>  $debito
     * @param  list<string>  $restitucion
     */
    private function contarSinActividad(array $debito, array $restitucion): int
    {
        $count = 0;
        foreach (array_merge($debito, $restitucion) as $linea) {
            $actividad = explode(';', $linea)[0] ?? '';
            if ($actividad === '000000') {
                $count++;
            }
        }

        return $count;
    }
}
