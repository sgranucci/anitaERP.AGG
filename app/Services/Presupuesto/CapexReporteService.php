<?php

namespace App\Services\Presupuesto;

use App\ApiAnita;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Contable\Cuentacontable;
use App\Models\Presupuesto\Capex;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Support\Collection;

class CapexReporteService
{
    /** Tipos de comprobante en aplicped que no son facturas (ej. COM = comprobante interno). */
    private const TIPOS_FACTURA_EXCLUIDOS = ['COM'];

    private const CHUNK_CODIGOS_CAPEX = 50;

    private const CHUNK_NUMEROS_OC = 80;

    private const CHUNK_FACTURAS = 40;

    private const CHUNK_SUBDIARIO = 15;

    /** tipoconcepto distinto de N/G/E → IVA, percepciones, retenciones, etc. */
    private const TIPOS_CONCEPTO_IVACOMPRA_EXCLUIDOS = ['I', 'P', 'B', 'M', 'T', 'S', 'A'];

    /** @var list<int>|null */
    private ?array $codigosCuentasImpuestosExcluidas = null;

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @return array{filas: list<array<string, mixed>>, total: int}
     */
    public function generar(array $filtros): array
    {
        $capexs = $this->consultarCapex($filtros);

        if ($capexs->isEmpty()) {
            return ['filas' => [], 'total' => 0];
        }

        $codigos = $capexs
            ->pluck('codigo')
            ->map(fn ($codigo) => (int) $codigo)
            ->filter(fn (int $codigo) => $codigo > 0)
            ->unique()
            ->values()
            ->all();

        $ordenesPorCodigo = $this->precargarOrdenesCompraPorCodigos($codigos);

        $numerosOc = [];
        foreach ($ordenesPorCodigo as $ordenes) {
            foreach ($ordenes as $orden) {
                $numerosOc[(int) $orden['numero']] = true;
            }
        }

        $facturasPorOc = $this->precargarFacturasPorOrdenes(array_keys($numerosOc));

        $facturasUnicas = [];
        foreach ($facturasPorOc as $facturas) {
            foreach ($facturas as $factura) {
                $clave = $this->claveComprobante(
                    $factura['tipo'],
                    $factura['letra'],
                    $factura['sucursal'],
                    $factura['numero'],
                    $factura['proveedor']
                );
                $facturasUnicas[$clave] = $factura;
            }
        }

        $datosPorFactura = $this->precargarDatosFacturas(array_values($facturasUnicas));
        $subdiarioPorFactura = $this->precargarSubdiarioFacturas(array_values($facturasUnicas), $datosPorFactura);
        $pagosPorFactura = $this->precargarPagosFacturas(array_values($facturasUnicas));

        $filas = [];

        foreach ($capexs as $capex) {
            $codigo = (int) $capex->codigo;
            $filas = array_merge(
                $filas,
                $this->armarFilasCapex(
                    $capex,
                    $ordenesPorCodigo[$codigo] ?? [],
                    $facturasPorOc,
                    $datosPorFactura,
                    $subdiarioPorFactura,
                    $pagosPorFactura
                )
            );
        }

        return [
            'filas' => $filas,
            'total' => count($filas),
        ];
    }

    /**
     * @return Collection<int, Capex>
     */
    public function consultarCapex(array $filtros): Collection
    {
        $empresasAsignadas = $this->empresaRepository->traeEmpresasAsignadas();

        $query = Capex::query()
            ->select([
                'capex.*',
                'empresa.nombre as nombreempresa',
                'presupuesto.nombre as nombrepresupuesto',
                'presupuesto.anio as aniopresupuesto',
                'centrocosto.nombre as nombrecentrocosto',
            ])
            ->join('empresa', 'empresa.id', '=', 'capex.empresa_id')
            ->join('presupuesto', 'presupuesto.id', '=', 'capex.presupuesto_id')
            ->join('centrocosto', 'centrocosto.id', '=', 'capex.centrocosto_id')
            ->whereIn('capex.empresa_id', $empresasAsignadas)
            ->with([
                'capex_partidas.capex_partida_montos',
                'capex_partidas.monedas',
            ])
            ->orderBy('capex.id');

        if (! empty($filtros['empresa_id'])) {
            $query->where('capex.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! empty($filtros['presupuesto_id'])) {
            $query->where('capex.presupuesto_id', (int) $filtros['presupuesto_id']);
        }
        if (! empty($filtros['centrocosto_id'])) {
            $query->where('capex.centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (! empty($filtros['capex_id'])) {
            $query->where('capex.id', (int) $filtros['capex_id']);
        }

        return $query->get();
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, list<array{tipo:string, letra:string, sucursal:int, numero:int, mes:string, moneda_id:string, total:float}>>
     */
    private function precargarOrdenesCompraPorCodigos(array $codigos): array
    {
        $porCodigo = [];

        foreach (array_chunk($codigos, self::CHUNK_CODIGOS_CAPEX) as $chunk) {
            $lista = implode(',', array_map('intval', $chunk));
            $apiAnita = new ApiAnita();
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'movpresup,pendmaep,promae,stkmae',
                'campos' => '
                    movp_proyecto,
                    movp_fecha as fechaordencompra,
                    movp_tipo,
                    movp_nro,
                    prom_nombre as nombreproveedor,
                    penmp_cod_mon as moneda_id,
                    movp_cotizacion as cotizacion,
                    movp_importe as total,
                    movp_mes as mes,
                    movp_articulo as articulo,
                    stkm_desc
                ',
                'whereArmado' => " WHERE
                    movp_proyecto IN ({$lista}) and
                    movp_tipo=penmp_tipo and
                    movp_nro=penmp_nro and
                    penmp_proveedor=prom_proveedor and
                    movp_articulo=stkm_articulo",
            ];

            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if (! is_array($dataAnita)) {
                continue;
            }

            foreach ($dataAnita as $row) {
                $codigo = (int) ($row->movp_proyecto ?? 0);
                if ($codigo <= 0) {
                    continue;
                }

                if (! isset($porCodigo[$codigo])) {
                    $porCodigo[$codigo] = [];
                }

                $porCodigo[$codigo][] = $row;
            }
        }

        foreach ($porCodigo as $codigo => $raw) {
            $porCodigo[$codigo] = $this->agruparOrdenesCompra($raw);
        }

        return $porCodigo;
    }

    /**
     * @param  list<int>  $numerosOc
     * @return array<int, list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>>
     */
    private function precargarFacturasPorOrdenes(array $numerosOc): array
    {
        $porOc = [];
        $numerosOc = array_values(array_unique(array_filter(array_map('intval', $numerosOc), fn (int $n) => $n > 0)));

        foreach (array_chunk($numerosOc, self::CHUNK_NUMEROS_OC) as $chunk) {
            $lista = implode(',', $chunk);
            $apiAnita = new ApiAnita();
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'aplicped',
                'campos' => '
                    aplp_ref_nro,
                    aplp_proveedor,
                    aplp_tipo,
                    aplp_letra,
                    aplp_sucursal,
                    aplp_nro
                ',
                'whereArmado' => " WHERE
                    aplp_ref_tipo='PEP' and
                    aplp_ref_letra='X' and
                    aplp_ref_sucursal=0 and
                    aplp_ref_nro IN ({$lista}) and
                    aplp_tipo<>'COM'",
            ];

            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if (! is_array($dataAnita)) {
                continue;
            }

            foreach ($dataAnita as $row) {
                $numeroOc = (int) ($row->aplp_ref_nro ?? 0);
                $proveedor = trim((string) ($row->aplp_proveedor ?? ''));
                $tipo = trim((string) ($row->aplp_tipo ?? ''));
                $letra = trim((string) ($row->aplp_letra ?? ''));
                $sucursal = (int) ($row->aplp_sucursal ?? 0);
                $numero = (int) ($row->aplp_nro ?? 0);

                if ($numeroOc <= 0 || $proveedor === '' || $tipo === '' || $numero <= 0 || $this->esTipoFacturaExcluido($tipo)) {
                    continue;
                }

                if (! isset($porOc[$numeroOc])) {
                    $porOc[$numeroOc] = [];
                }

                $clave = $proveedor.'|'.$tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;
                $porOc[$numeroOc][$clave] = [
                    'proveedor' => $proveedor,
                    'tipo' => $tipo,
                    'letra' => $letra,
                    'sucursal' => $sucursal,
                    'numero' => $numero,
                ];
            }
        }

        foreach ($porOc as $numeroOc => $facturas) {
            $porOc[$numeroOc] = array_values($facturas);
        }

        return $porOc;
    }

    /**
     * @param  list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>  $facturas
     * @return array<string, array{monto: float, nro_interno: int, empresa: int}>
     */
    private function precargarDatosFacturas(array $facturas): array
    {
        $datos = [];

        foreach (array_chunk($facturas, self::CHUNK_FACTURAS) as $chunk) {
            $condiciones = [];

            foreach ($chunk as $factura) {
                $condiciones[] = '(
                    prov_proveedor=\''.$this->proveedorAnita($factura['proveedor']).'\' and
                    prov_tipo=\''.trim($factura['tipo']).'\' and
                    prov_letra=\''.trim($factura['letra']).'\' and
                    prov_sucursal='.(int) $factura['sucursal'].' and
                    prov_nro='.(int) $factura['numero'].'
                )';
            }

            if ($condiciones === []) {
                continue;
            }

            $apiAnita = new ApiAnita();
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'promov',
                'campos' => '
                    prov_proveedor,
                    prov_tipo,
                    prov_letra,
                    prov_sucursal,
                    prov_nro,
                    prov_monto,
                    prov_nro_interno,
                    prov_empresa
                ',
                'whereArmado' => ' WHERE '.implode(' OR ', $condiciones),
            ];

            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if (! is_array($dataAnita)) {
                continue;
            }

            foreach ($dataAnita as $row) {
                $clave = $this->claveComprobante(
                    (string) ($row->prov_tipo ?? ''),
                    (string) ($row->prov_letra ?? ''),
                    (int) ($row->prov_sucursal ?? 0),
                    (int) ($row->prov_nro ?? 0),
                    trim((string) ($row->prov_proveedor ?? ''))
                );

                if (! isset($datos[$clave])) {
                    $datos[$clave] = [
                        'monto' => 0.0,
                        'nro_interno' => (int) ($row->prov_nro_interno ?? 0),
                        'empresa' => (int) ($row->prov_empresa ?? 0),
                    ];
                }

                $datos[$clave]['monto'] += (float) ($row->prov_monto ?? 0);

                if ($datos[$clave]['nro_interno'] <= 0) {
                    $datos[$clave]['nro_interno'] = (int) ($row->prov_nro_interno ?? 0);
                }

                if ($datos[$clave]['empresa'] <= 0) {
                    $datos[$clave]['empresa'] = (int) ($row->prov_empresa ?? 0);
                }
            }
        }

        foreach ($datos as $clave => $item) {
            $datos[$clave]['monto'] = round($item['monto'], 2);
        }

        return $datos;
    }

    /**
     * @param  list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>  $facturas
     * @param  array<string, array{monto: float, nro_interno: int, empresa: int}>  $datosPorFactura
     * @return array<string, array{cotizacion: ?float, cuenta_contable: string}>
     */
    private function precargarSubdiarioFacturas(array $facturas, array $datosPorFactura): array
    {
        $facturasConInterno = [];

        foreach ($facturas as $factura) {
            $clave = $this->claveComprobante(
                $factura['tipo'],
                $factura['letra'],
                $factura['sucursal'],
                $factura['numero'],
                $factura['proveedor'],
            );
            $datos = $datosPorFactura[$clave] ?? null;
            $nroInterno = (int) ($datos['nro_interno'] ?? 0);

            if ($nroInterno <= 0) {
                continue;
            }

            $facturasConInterno[] = [
                'clave' => $clave,
                'tipo' => $factura['tipo'],
                'letra' => $factura['letra'],
                'sucursal' => (int) $factura['sucursal'],
                'numero' => (int) $factura['numero'],
                'nro_interno' => $nroInterno,
                'empresa' => (int) ($datos['empresa'] ?? 0),
            ];
        }

        if ($facturasConInterno === []) {
            return [];
        }

        $lineasPorClave = [];
        $codigosExcluidos = $this->codigosCuentasImpuestosExcluidas();

        foreach (array_chunk($facturasConInterno, self::CHUNK_SUBDIARIO) as $chunk) {
            $condiciones = [];

            foreach ($chunk as $factura) {
                $condicion = '(
                    subd_tipo=\''.trim($factura['tipo']).'\' and
                    subd_letra=\''.trim($factura['letra']).'\' and
                    subd_sucursal='.(int) $factura['sucursal'].' and
                    subd_nro='.(int) $factura['numero'].' and
                    subd_nro_interno='.(int) $factura['nro_interno'];

                if ((int) $factura['empresa'] > 0) {
                    $condicion .= ' and subd_empresa='.(int) $factura['empresa'];
                }

                $condicion .= ')';
                $condiciones[] = $condicion;
            }

            if ($condiciones === []) {
                continue;
            }

            $apiAnita = new ApiAnita();
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'contab',
                'tabla' => 'subdiario, ctamae',
                'campos' => '
                    subd_tipo,
                    subd_letra,
                    subd_sucursal,
                    subd_nro,
                    subd_nro_interno,
                    subd_cotizacion,
                    subd_cuenta,
                    subd_importe,
                    subd_tipo_mov,
                    ctam_desc
                ',
                'whereArmado' => ' WHERE
                    subd_sistema=\'C\' and
                    subd_cuenta=ctam_cuenta and
                    subd_empresa=ctam_empresa and
                    ('.implode(' OR ', $condiciones).')',
            ];

            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if (! is_array($dataAnita)) {
                continue;
            }

            foreach ($dataAnita as $row) {
                $claveSubdiario = $this->claveSubdiario(
                    (string) ($row->subd_tipo ?? ''),
                    (string) ($row->subd_letra ?? ''),
                    (int) ($row->subd_sucursal ?? 0),
                    (int) ($row->subd_nro ?? 0),
                    (int) ($row->subd_nro_interno ?? 0),
                );

                if (! isset($lineasPorClave[$claveSubdiario])) {
                    $lineasPorClave[$claveSubdiario] = [
                        'cotizacion' => null,
                        'lineas' => [],
                    ];
                }

                $cotizacion = (float) ($row->subd_cotizacion ?? 0);
                if ($cotizacion > 0 && $lineasPorClave[$claveSubdiario]['cotizacion'] === null) {
                    $lineasPorClave[$claveSubdiario]['cotizacion'] = round($cotizacion, 4);
                }

                $lineasPorClave[$claveSubdiario]['lineas'][] = $row;
            }
        }

        $resultado = [];

        foreach ($facturasConInterno as $factura) {
            $claveSubdiario = $this->claveSubdiario(
                $factura['tipo'],
                $factura['letra'],
                $factura['sucursal'],
                $factura['numero'],
                $factura['nro_interno'],
            );

            $bloque = $lineasPorClave[$claveSubdiario] ?? null;

            $resultado[$factura['clave']] = [
                'cotizacion' => is_array($bloque) ? ($bloque['cotizacion'] ?? null) : null,
                'cuenta_contable' => $this->resolverCuentaGastoSubdiario(
                    is_array($bloque) ? ($bloque['lineas'] ?? []) : [],
                    $codigosExcluidos,
                ),
            ];
        }

        return $resultado;
    }

    /**
     * @param  list<object>  $lineas
     * @param  list<int>  $codigosExcluidos
     */
    private function resolverCuentaGastoSubdiario(array $lineas, array $codigosExcluidos): string
    {
        $mejor = null;

        foreach ($lineas as $linea) {
            if (trim((string) ($linea->subd_tipo_mov ?? '')) !== 'D') {
                continue;
            }

            $codigo = (int) ($linea->subd_cuenta ?? 0);
            if ($codigo <= 0 || in_array($codigo, $codigosExcluidos, true)) {
                continue;
            }

            $importe = abs((float) ($linea->subd_importe ?? 0));
            if ($importe <= 0) {
                continue;
            }

            if ($mejor === null || $importe > $mejor['importe']) {
                $mejor = [
                    'codigo' => $codigo,
                    'nombre' => trim((string) ($linea->ctam_desc ?? '')),
                    'importe' => $importe,
                ];
            }
        }

        if ($mejor === null) {
            return '';
        }

        $nombreLocal = Cuentacontable::query()
            ->where('codigo', $mejor['codigo'])
            ->value('nombre');

        $nombre = $nombreLocal ? trim((string) $nombreLocal) : $mejor['nombre'];

        return trim($mejor['codigo'].' '.$nombre);
    }

    /**
     * @return list<int>
     */
    private function codigosCuentasImpuestosExcluidas(): array
    {
        if ($this->codigosCuentasImpuestosExcluidas !== null) {
            return $this->codigosCuentasImpuestosExcluidas;
        }

        $conceptos = Concepto_Ivacompra::query()
            ->whereIn('tipoconcepto', self::TIPOS_CONCEPTO_IVACOMPRA_EXCLUIDOS)
            ->with(['cuentacontablesdebe', 'cuentacontableshaber'])
            ->get(['id', 'cuentacontabledebe_id', 'cuentacontablehaber_id']);

        $codigos = [];

        foreach ($conceptos as $concepto) {
            foreach (['cuentacontablesdebe', 'cuentacontableshaber'] as $relacion) {
                $cuenta = $concepto->{$relacion};
                if ($cuenta && (int) $cuenta->codigo > 0) {
                    $codigos[(int) $cuenta->codigo] = true;
                }
            }
        }

        $this->codigosCuentasImpuestosExcluidas = array_keys($codigos);

        return $this->codigosCuentasImpuestosExcluidas;
    }

    /**
     * @param  list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>  $facturas
     * @return array<string, list<object>>
     */
    private function precargarPagosFacturas(array $facturas): array
    {
        $pagos = [];

        foreach (array_chunk($facturas, self::CHUNK_FACTURAS) as $chunk) {
            $condiciones = [];

            foreach ($chunk as $factura) {
                $condiciones[] = '(
                    aplvp_proveedor=\''.$this->proveedorAnita($factura['proveedor']).'\' and
                    aplvp_tipo=\''.trim($factura['tipo']).'\' and
                    aplvp_letra=\''.trim($factura['letra']).'\' and
                    aplvp_sucursal='.(int) $factura['sucursal'].' and
                    aplvp_nro='.(int) $factura['numero'].'
                )';
            }

            if ($condiciones === []) {
                continue;
            }

            $apiAnita = new ApiAnita();
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'aplmovp',
                'campos' => '
                    aplvp_proveedor,
                    aplvp_tipo,
                    aplvp_letra,
                    aplvp_sucursal,
                    aplvp_nro,
                    aplvp_fecha,
                    aplvp_monto,
                    aplvp_tipo_cob,
                    aplvp_letra_cob,
                    aplvp_sucursal_cob,
                    aplvp_nro_cob
                ',
                'whereArmado' => ' WHERE '.implode(' OR ', $condiciones),
            ];

            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if (! is_array($dataAnita)) {
                continue;
            }

            foreach ($dataAnita as $row) {
                $clave = $this->claveComprobante(
                    (string) ($row->aplvp_tipo ?? ''),
                    (string) ($row->aplvp_letra ?? ''),
                    (int) ($row->aplvp_sucursal ?? 0),
                    (int) ($row->aplvp_nro ?? 0),
                    trim((string) ($row->aplvp_proveedor ?? ''))
                );

                if (! isset($pagos[$clave])) {
                    $pagos[$clave] = [];
                }

                $pagos[$clave][] = $row;
            }
        }

        return $pagos;
    }

    /**
     * @param  list<array{tipo:string, letra:string, sucursal:int, numero:int, mes:string, moneda_id:string, total:float}>  $ordenes
     * @param  array<int, list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>>  $facturasPorOc
     * @param  array<string, array{monto: float, nro_interno: int, empresa: int}>  $datosPorFactura
     * @param  array<string, array{cotizacion: ?float, cuenta_contable: string}>  $subdiarioPorFactura
     * @param  array<string, list<object>>  $pagosPorFactura
     * @return list<array<string, mixed>>
     */
    private function armarFilasCapex(
        Capex $capex,
        array $ordenes,
        array $facturasPorOc,
        array $datosPorFactura,
        array $subdiarioPorFactura,
        array $pagosPorFactura,
    ): array {
        $montoCapex = $this->calcularMontoCapex($capex);

        $base = [
            'id' => $capex->id,
            'empresa_id' => $capex->empresa_id,
            'presupuesto_id' => $capex->presupuesto_id,
            'centrocosto_id' => $capex->centrocosto_id,
            'nombreempresa' => $capex->nombreempresa ?? '',
            'empresa' => $capex->nombreempresa ?? '',
            'presupuesto' => $capex->nombrepresupuesto ?? '',
            'centrocosto' => $capex->nombrecentrocosto ?? '',
            'nombre' => $capex->nombre ?? '',
            'detalle' => $capex->detalle ?? '',
            'codigoproyecto' => $capex->codigoproyecto ?? '',
            'anio' => $this->resolverAnio($capex),
            'nro_proyecto' => $capex->codigo ?? '',
            'estado' => $capex->estado ?? '',
            'partidas' => $this->formatearPartidas($capex),
        ];

        if ($ordenes === []) {
            return [$this->filaDetalle($base, [
                'monto_capex' => $montoCapex,
            ])];
        }

        $filas = [];
        $ocImporteAsignado = [];
        $montoCapexAsignado = false;

        foreach ($ordenes as $orden) {
            $ocClave = $this->claveComprobante(
                $orden['tipo'],
                $orden['letra'],
                $orden['sucursal'],
                $orden['numero']
            );
            $ocTexto = $this->formatearComprobante(
                $orden['tipo'],
                $orden['letra'],
                $orden['sucursal'],
                $orden['numero']
            );
            $importeOc = round((float) ($orden['total'] ?? 0), 2);
            $facturas = $facturasPorOc[(int) $orden['numero']] ?? [];
            $fcImporteAsignado = [];

            if ($facturas === []) {
                $filas[] = $this->filaDetalle($base, [
                    'mes' => $orden['mes'] ?? '',
                    'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                    'monto_capex' => $this->asignarMontoCapex($montoCapexAsignado, $montoCapex),
                    'importe_oc' => $this->asignarImporteUnico($ocImporteAsignado, $ocClave, $importeOc),
                    'oc' => $ocTexto,
                ]);

                continue;
            }

            foreach ($facturas as $factura) {
                $fcClave = $this->claveComprobante(
                    $factura['tipo'],
                    $factura['letra'],
                    $factura['sucursal'],
                    $factura['numero'],
                    $factura['proveedor']
                );
                $fcTexto = $this->formatearComprobante(
                    $factura['tipo'],
                    $factura['letra'],
                    $factura['sucursal'],
                    $factura['numero']
                );
                $importeFc = $datosPorFactura[$fcClave]['monto'] ?? null;
                $subdiario = $subdiarioPorFactura[$fcClave] ?? null;
                $pagos = $pagosPorFactura[$fcClave] ?? [];

                if ($pagos === []) {
                    $filas[] = $this->filaDetalle($base, [
                        'mes' => $orden['mes'] ?? '',
                        'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                        'monto_capex' => $this->asignarMontoCapex($montoCapexAsignado, $montoCapex),
                        'importe_oc' => $this->asignarImporteUnico($ocImporteAsignado, $ocClave, $importeOc),
                        'importe_fc' => $this->asignarImporteUnico($fcImporteAsignado, $fcClave, $importeFc),
                        'cotizacion_fc' => $this->asignarCotizacionUnica($fcImporteAsignado, $fcClave, $subdiario['cotizacion'] ?? null),
                        'cuenta_contable' => $this->asignarTextoUnico($fcImporteAsignado, $fcClave, $subdiario['cuenta_contable'] ?? ''),
                        'oc' => $ocTexto,
                        'fc' => $fcTexto,
                    ]);

                    continue;
                }

                foreach ($pagos as $pago) {
                    $filas[] = $this->filaDetalle($base, [
                        'mes' => $orden['mes'] ?? '',
                        'moneda' => $this->nombreMoneda($orden['moneda_id'] ?? null),
                        'monto_capex' => $this->asignarMontoCapex($montoCapexAsignado, $montoCapex),
                        'importe_oc' => $this->asignarImporteUnico($ocImporteAsignado, $ocClave, $importeOc),
                        'importe_fc' => $this->asignarImporteUnico($fcImporteAsignado, $fcClave, $importeFc),
                        'cotizacion_fc' => $this->asignarCotizacionUnica($fcImporteAsignado, $fcClave, $subdiario['cotizacion'] ?? null),
                        'cuenta_contable' => $this->asignarTextoUnico($fcImporteAsignado, $fcClave, $subdiario['cuenta_contable'] ?? ''),
                        'importe_pago' => round((float) ($pago->aplvp_monto ?? 0), 2),
                        'oc' => $ocTexto,
                        'fc' => $fcTexto,
                        'pago' => $this->formatearPago($pago),
                    ]);
                }
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $detalle
     * @return array<string, mixed>
     */
    private function filaDetalle(array $base, array $detalle = []): array
    {
        return array_merge($base, [
            'mes' => '',
            'moneda' => '',
            'monto_capex' => null,
            'importe_oc' => null,
            'importe_fc' => null,
            'cotizacion_fc' => null,
            'cuenta_contable' => '',
            'importe_pago' => null,
            'oc' => '',
            'fc' => '',
            'pago' => '',
        ], $detalle);
    }

    private function asignarMontoCapex(bool &$asignado, ?float $monto): ?float
    {
        if ($asignado || $monto === null) {
            return null;
        }

        $asignado = true;

        return $monto;
    }

    /**
     * @param  array<string, true>  $asignados
     */
    private function asignarImporteUnico(array &$asignados, string $clave, ?float $importe): ?float
    {
        if ($importe === null) {
            return null;
        }

        if (isset($asignados[$clave])) {
            return null;
        }

        $asignados[$clave] = true;

        return $importe;
    }

    /**
     * @param  array<string, true>  $asignados
     */
    private function asignarCotizacionUnica(array &$asignados, string $clave, ?float $cotizacion): ?float
    {
        if ($cotizacion === null || isset($asignados[$clave.'|cotizacion'])) {
            return null;
        }

        $asignados[$clave.'|cotizacion'] = true;

        return $cotizacion;
    }

    /**
     * @param  array<string, true>  $asignados
     */
    private function asignarTextoUnico(array &$asignados, string $clave, string $texto): string
    {
        if ($texto === '' || isset($asignados[$clave.'|texto'])) {
            return '';
        }

        $asignados[$clave.'|texto'] = true;

        return $texto;
    }

    private function claveComprobante(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        string $proveedor = '',
    ): string {
        return trim($proveedor).'|'.trim($tipo).'|'.trim($letra).'|'.$sucursal.'|'.$numero;
    }

    private function claveSubdiario(
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
        int $nroInterno,
    ): string {
        return trim($tipo).'|'.trim($letra).'|'.$sucursal.'|'.$numero.'|'.$nroInterno;
    }

    private function proveedorAnita(string $proveedor): string
    {
        return str_pad(trim($proveedor), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Total grabado en partidas (misma lógica que el listado CAPEX).
     */
    private function calcularMontoCapex(Capex $capex): ?float
    {
        $total = 0.0;
        $tieneMontos = false;

        foreach ($capex->capex_partidas as $partida) {
            foreach ($partida->capex_partida_montos as $monto) {
                $total += (float) $monto->monto;
                $tieneMontos = true;
            }
        }

        return $tieneMontos ? round($total, 2) : null;
    }

    /**
     * @param  mixed  $raw
     * @return list<array{tipo:string, letra:string, sucursal:int, numero:int, mes:string, moneda_id:string, total:float}>
     */
    private function agruparOrdenesCompra($raw): array
    {
        if (! is_array($raw) && ! ($raw instanceof \Traversable)) {
            return [];
        }

        $ordenes = [];

        foreach ($raw as $row) {
            $tipo = trim((string) ($row->movp_tipo ?? 'PEP'));
            $letra = 'X';
            $sucursal = 0;
            $numero = (int) ($row->movp_nro ?? 0);

            if ($numero <= 0) {
                continue;
            }

            $clave = $tipo.'|'.$letra.'|'.$sucursal.'|'.$numero;

            if (isset($ordenes[$clave])) {
                $ordenes[$clave]['total'] += (float) ($row->total ?? 0);

                continue;
            }

            $ordenes[$clave] = [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'numero' => $numero,
                'mes' => (string) ($row->mes ?? ''),
                'moneda_id' => (string) ($row->moneda_id ?? ''),
                'total' => (float) ($row->total ?? 0),
            ];
        }

        return array_values($ordenes);
    }

    /**
     * @return list<array{proveedor:string, tipo:string, letra:string, sucursal:int, numero:int}>
     */
    public function leeFacturasPorOrdenCompra(int $numeroOc, string $tipoRef = 'PEP', string $letraRef = 'X', int $sucursalRef = 0): array
    {
        return $this->precargarFacturasPorOrdenes([$numeroOc])[$numeroOc] ?? [];
    }

    /**
     * @return list<object>
     */
    public function leePagosPorFactura(string $proveedor, string $tipo, string $letra, int $sucursal, int $numero): array
    {
        $clave = $this->claveComprobante($tipo, $letra, $sucursal, $numero, $proveedor);

        return $this->precargarPagosFacturas([[
            'proveedor' => $proveedor,
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
        ]])[$clave] ?? [];
    }

    public function leeMontoFactura(string $proveedor, string $tipo, string $letra, int $sucursal, int $numero): ?float
    {
        $clave = $this->claveComprobante($tipo, $letra, $sucursal, $numero, $proveedor);

        return $this->precargarDatosFacturas([[
            'proveedor' => $proveedor,
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numero' => $numero,
        ]])[$clave]['monto'] ?? null;
    }

    private function formatearPartidas(Capex $capex): string
    {
        $lineas = [];

        foreach ($capex->capex_partidas as $partida) {
            $montoTotal = $partida->capex_partida_montos->sum('monto');
            $moneda = $partida->monedas->abreviatura ?? '';
            $lineas[] = 'Nro.'.$partida->codigo.' '.$partida->nombre.' '.$moneda.' '.number_format((float) $montoTotal, 2, '.', ',');
        }

        return implode("\n", $lineas);
    }

    private function resolverAnio(Capex $capex): string
    {
        $codigoProyecto = (string) ($capex->codigoproyecto ?? '');

        if (str_contains($codigoProyecto, '/')) {
            return trim(substr($codigoProyecto, strrpos($codigoProyecto, '/') + 1));
        }

        if (! empty($capex->aniopresupuesto)) {
            return (string) $capex->aniopresupuesto;
        }

        return '';
    }

    private function formatearComprobante(string $tipo, string $letra, int $sucursal, int $numero): string
    {
        return trim($tipo).' '.trim($letra).' '.$sucursal.'-'.$numero;
    }

    private function formatearFechaAnita($fecha): string
    {
        $fecha = preg_replace('/\D/', '', (string) $fecha);

        if (strlen($fecha) < 8) {
            return (string) $fecha;
        }

        return substr($fecha, 6, 2).'/'.substr($fecha, 4, 2).'/'.substr($fecha, 0, 4);
    }

    private function formatearPago(object $pago): string
    {
        $fecha = $this->formatearFechaAnita($pago->aplvp_fecha ?? '');
        $monto = number_format((float) ($pago->aplvp_monto ?? 0), 2, '.', ',');
        $ordenPago = $this->formatearComprobante(
            (string) ($pago->aplvp_tipo_cob ?? ''),
            (string) ($pago->aplvp_letra_cob ?? ''),
            (int) ($pago->aplvp_sucursal_cob ?? 0),
            (int) ($pago->aplvp_nro_cob ?? 0)
        );

        return $fecha.' '.$monto.' OP '.$ordenPago;
    }

    private function nombreMoneda($monedaId): string
    {
        return match ((string) $monedaId) {
            '1' => 'PESOS',
            '2' => 'DOLARES',
            '3' => 'EUROS',
            default => '',
        };
    }

    private function esTipoFacturaExcluido(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), self::TIPOS_FACTURA_EXCLUIDOS, true);
    }
}
