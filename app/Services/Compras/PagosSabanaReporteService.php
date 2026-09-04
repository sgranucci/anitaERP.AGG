<?php

namespace App\Services\Compras;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use App\Support\Compras\ComprobanteProveedorCentrocostoSupport;
use App\Support\Compras\PagosSabanaAnitaArmadoSupport;
use App\Support\Compras\PagosSabanaAnitaBridgeReader;
use App\Support\Compras\PagosSabanaColumnasSupport;
use App\Support\Compras\PagosSabanaReporteFiltros;
use App\Support\Compras\PagoproveedorListadoFila;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as PaginatorImpl;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Informe de pagos tipo sábana: OPP/OPA de pagoproveedor + IE (SP y otros conceptos).
 * Fuente ERP por defecto; opcionalmente Anita (pago/auxpag) vía flag temporal.
 */
class PagosSabanaReporteService
{
    private const ESTADOS_PP = ['CONFIRMADA', 'PAGADA', 'CONCILIADA'];

    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private PagosSabanaAnitaBridgeReader $anitaReader = new PagosSabanaAnitaBridgeReader,
    ) {}

    public static function anitaHabilitadaEnConfig(): bool
    {
        return (bool) config('compras.pagos_sabana_anita_habilitada', false);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function usarAnita(array $filtros): bool
    {
        return self::anitaHabilitadaEnConfig() && ! empty($filtros['incluir_anita']);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   columnas: list<array<string, mixed>>,
     *   totales: array<string, mixed>,
     *   secciones: list<array<string, mixed>>,
     *   fuente: string,
     *   anita_errores: list<string>
     * }
     */
    public function generar(array $filtros): array
    {
        if (self::usarAnita($filtros)) {
            return $this->generarDesdeAnita($filtros);
        }

        $resultado = $this->generarDesdeErp($filtros);
        $resultado['fuente'] = 'erp';
        $resultado['anita_errores'] = [];

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarDesdeAnita(array $filtros): array
    {
        $errores = [];
        $desdeYmd = (int) str_replace('-', '', (string) ($filtros['fecha_desde'] ?? ''));
        $hastaYmd = (int) str_replace('-', '', (string) ($filtros['fecha_hasta'] ?? ''));

        $empresasPorAnita = [];
        $empresasAnita = [];
        foreach ($filtros['empresa_ids'] ?? [] as $empresaId) {
            $empresaId = (int) $empresaId;
            if ($empresaId <= 0) {
                continue;
            }
            $codigoAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
            if ($codigoAnita <= 0) {
                continue;
            }
            $empresasAnita[] = $codigoAnita;
            $emp = DB::table('empresa')->where('id', $empresaId)->first(['id', 'codigo', 'nombre']);
            $empresasPorAnita[$codigoAnita] = [
                'id' => $empresaId,
                'codigo' => (string) ($emp->codigo ?? $codigoAnita),
                'nombre' => (string) ($emp->nombre ?? ''),
            ];
        }

        if ($empresasAnita === [] || $desdeYmd <= 0 || $hastaYmd <= 0) {
            return [
                'filas' => [],
                'columnas' => PagosSabanaColumnasSupport::resolverVisibles([]),
                'totales' => $this->totalesVacios(),
                'secciones' => [],
                'fuente' => 'anita',
                'anita_errores' => ['Sin empresas o fechas válidas para Anita.'],
            ];
        }

        $pagos = $this->anitaReader->listarPagos($empresasAnita, $desdeYmd, $hastaYmd, $errores);
        $auxpags = $this->anitaReader->listarAuxpag($empresasAnita, $desdeYmd, $hastaYmd, $errores);

        $cuentasBanco = [];
        foreach ($auxpags as $axp) {
            $c = strtoupper(trim((string) ($axp->axp_banco ?? '')));
            if ($c !== '') {
                $cuentasBanco[$c] = true;
            }
        }
        $tesmae = $this->anitaReader->mapaTesmae(array_keys($cuentasBanco), $errores);

        $codigosProv = [];
        foreach ($pagos as $pago) {
            $p = InterbankingArchivoPagoAnitaReader::padProveedor($pago->pag_pro ?? '');
            if ($p !== '') {
                $codigosProv[$p] = true;
            }
        }
        $proveedoresPorCodigo = $this->mapaProveedoresErp(array_keys($codigosProv));

        $filasDetalle = PagosSabanaAnitaArmadoSupport::armarFilas(
            $pagos,
            $auxpags,
            $tesmae,
            $empresasPorAnita,
            $proveedoresPorCodigo,
        );
        $filasDetalle = $this->enriquecerFilasAnitaConErp($filasDetalle, $filtros);
        $filasDetalle = $this->enriquecerFilasAnitaDesdeComprobantes($filasDetalle);
        $filasDetalle = $this->enriquecerFilasAnitaDesdeAplicped($filasDetalle, $errores);

        $columnas = PagosSabanaColumnasSupport::resolverVisibles($filasDetalle);
        $totales = $this->calcularTotales($filasDetalle, $columnas);

        return $this->aplicarSeccionesSiCorresponde($filtros, $filasDetalle, $columnas, $totales, [
            'fuente' => 'anita',
            'anita_errores' => $errores,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function generarDesdeErp(array $filtros): array
    {
        $cabeceras = $this->cargarCabeceras($filtros);
        if ($cabeceras->isEmpty()) {
            return [
                'filas' => [],
                'columnas' => PagosSabanaColumnasSupport::resolverVisibles([]),
                'totales' => $this->totalesVacios(),
                'secciones' => [],
            ];
        }

        $ppIds = $cabeceras
            ->where('origen', PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR)
            ->pluck('pk_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $ieIds = $cabeceras
            ->where('origen', PagoproveedorListadoFila::ORIGEN_IE_OPP)
            ->pluck('pk_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $spIds = $cabeceras
            ->pluck('solicitudpago_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $mediosPp = $this->cargarMediosCajaPorPagoproveedor($ppIds);
        $mediosIe = $this->cargarMediosCajaPorMovimiento($ieIds);
        $chequesPp = $this->cargarChequesPorPagoproveedor($ppIds);
        $chequesIe = $this->cargarChequesPorMovimiento($ieIds);
        $retenciones = $this->cargarRetenciones($ppIds);
        $aplicaciones = $this->cargarAplicaciones($ppIds);
        $spCentros = $this->cargarCentrosCostoSp($spIds);
        $formapagos = $this->cargarFormapagos($ppIds, $ieIds);

        $filasDetalle = [];
        foreach ($cabeceras as $cab) {
            $filasDetalle[] = $this->armarFila(
                $cab,
                $mediosPp,
                $mediosIe,
                $chequesPp,
                $chequesIe,
                $retenciones,
                $aplicaciones,
                $spCentros,
                $formapagos,
            );
        }

        $columnas = PagosSabanaColumnasSupport::resolverVisibles($filasDetalle);
        $totales = $this->calcularTotales($filasDetalle, $columnas);

        return $this->aplicarSeccionesSiCorresponde($filtros, $filasDetalle, $columnas, $totales);
    }

    /**
     * @param  list<array<string, mixed>>  $filasDetalle
     * @param  list<array<string, mixed>>  $columnas
     * @param  array<string, mixed>  $totales
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function aplicarSeccionesSiCorresponde(
        array $filtros,
        array $filasDetalle,
        array $columnas,
        array $totales,
        array $extra = [],
    ): array {
        $base = array_merge([
            'filas' => $filasDetalle,
            'columnas' => $columnas,
            'totales' => $totales,
            'secciones' => [],
        ], $extra);

        $consolidar = ! empty($filtros['consolidar_empresas']);
        if ($consolidar || count($filtros['empresa_ids'] ?? []) <= 1) {
            return $base;
        }

        $secciones = [];
        $filasConHeaders = [];
        foreach ($filasDetalle as $fila) {
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            if (! isset($secciones[$empresaId])) {
                $secciones[$empresaId] = [
                    'empresa_id' => $empresaId,
                    'empresa_nombre' => (string) ($fila['empresa_nombre'] ?? ''),
                    'filas' => [],
                ];
            }
            $secciones[$empresaId]['filas'][] = $fila;
        }

        $seccionesList = [];
        foreach ($secciones as $seccion) {
            $seccion['totales'] = $this->calcularTotales($seccion['filas'], $columnas);
            $seccionesList[] = $seccion;
            $filasConHeaders[] = [
                'tipo_fila' => 'header_empresa',
                'empresa_id' => $seccion['empresa_id'],
                'empresa_nombre' => $seccion['empresa_nombre'],
                'nombreempresa' => $seccion['empresa_nombre'],
            ];
            foreach ($seccion['filas'] as $fila) {
                $filasConHeaders[] = $fila;
            }
        }

        $base['filas'] = $filasConHeaders;
        $base['secciones'] = $seccionesList;

        return $base;
    }

    /**
     * @param  list<string>  $codigosPadded
     * @return array<string, array{id: int, codigo: string, nombre: string}>
     */
    private function mapaProveedoresErp(array $codigosPadded): array
    {
        if ($codigosPadded === []) {
            return [];
        }

        $variantes = [];
        foreach ($codigosPadded as $cod) {
            $variantes[] = $cod;
            $variantes[] = ltrim($cod, '0') ?: '0';
        }
        $variantes = array_values(array_unique($variantes));

        $rows = DB::table('proveedor')
            ->whereIn('codigo', $variantes)
            ->get(['id', 'codigo', 'nombre']);

        $out = [];
        foreach ($rows as $row) {
            $pad = InterbankingArchivoPagoAnitaReader::padProveedor($row->codigo);
            $out[$pad] = [
                'id' => (int) $row->id,
                'codigo' => (string) $row->codigo,
                'nombre' => (string) $row->nombre,
            ];
        }

        return $out;
    }

    /**
     * Completa CC / OC / links ERP cuando la OP ya existe en anitaERP.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function enriquecerFilasAnitaConErp(array $filas, array $filtros): array
    {
        if ($filas === []) {
            return $filas;
        }

        $erp = $this->generarDesdeErp($filtros);
        $porClave = [];
        foreach ($erp['filas'] as $filaErp) {
            if (($filaErp['tipo_fila'] ?? '') === 'header_empresa') {
                continue;
            }
            $empAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($filaErp['empresa_id'] ?? 0));
            $clave = PagosSabanaAnitaArmadoSupport::clavePago(
                $empAnita,
                (string) ($filaErp['tip'] ?? ''),
                (int) ($filaErp['numero_op'] ?? 0),
            );
            $porClave[$clave] = $filaErp;
        }

        foreach ($filas as &$fila) {
            $clave = (string) ($fila['anita_clave'] ?? '');
            $erpFila = $porClave[$clave] ?? null;
            if ($erpFila === null) {
                continue;
            }
            $fila['pagoproveedor_id'] = $erpFila['pagoproveedor_id'] ?? null;
            $fila['caja_movimiento_id'] = $erpFila['caja_movimiento_id'] ?? null;
            $fila['solicitudpago_id'] = $erpFila['solicitudpago_id'] ?? null;
            $fila['pk_id'] = $erpFila['pk_id'] ?? 0;
            $fila['origen'] = $erpFila['origen'] ?? $fila['origen'];
            if (($fila['centros_costo'] ?? '') === '' && ($erpFila['centros_costo'] ?? '') !== '') {
                $fila['centros_costo'] = $erpFila['centros_costo'];
            }
            if (($fila['ordenes_compra'] ?? '') === '' && ($erpFila['ordenes_compra'] ?? '') !== '') {
                $fila['ordenes_compra'] = $erpFila['ordenes_compra'];
                $fila['ordenes_compra_links'] = $erpFila['ordenes_compra_links'] ?? [];
            }
            if (($fila['comprobantes'] ?? '') === '' && ($erpFila['comprobantes'] ?? '') !== '') {
                $fila['comprobantes'] = $erpFila['comprobantes'];
                $fila['comprobantes_links'] = $erpFila['comprobantes_links'] ?? [];
            } elseif (! empty($erpFila['comprobantes_links'])) {
                $fila['comprobantes_links'] = $erpFila['comprobantes_links'];
            }
            if (empty($fila['proveedor_id']) && ! empty($erpFila['proveedor_id'])) {
                $fila['proveedor_id'] = $erpFila['proveedor_id'];
            }
        }
        unset($fila);

        return $filas;
    }

    /**
     * OP solo en Anita: resuelve CC/OC/links desde facturas aplicadas (FIB 1118, FIS 15599, …)
     * ya existentes en comprobante_proveedor del ERP.
     *
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function enriquecerFilasAnitaDesdeComprobantes(array $filas): array
    {
        $refs = [];
        foreach ($filas as $fila) {
            if (($fila['centros_costo'] ?? '') !== '' && ($fila['ordenes_compra'] ?? '') !== '') {
                continue;
            }
            foreach ($fila['comprobantes_refs'] ?? [] as $ref) {
                $tipo = strtoupper(trim((string) ($ref['tipo'] ?? '')));
                $numero = preg_replace('/\D+/', '', (string) ($ref['numero'] ?? '')) ?? '';
                if ($tipo === '' || $numero === '') {
                    continue;
                }
                $refs[$tipo.'|'.$numero] = ['tipo' => $tipo, 'numero' => $numero];
            }
            // Fallback: parsear texto "FIB 1118 | FIS 15599"
            if (($fila['comprobantes_refs'] ?? []) === [] && trim((string) ($fila['comprobantes'] ?? '')) !== '') {
                foreach (preg_split('/\s*\|\s*/', (string) $fila['comprobantes']) ?: [] as $parte) {
                    if (preg_match('/^([A-Z]{2,4})\s+(\d+)$/i', trim($parte), $m) !== 1) {
                        continue;
                    }
                    $tipo = strtoupper($m[1]);
                    $numero = $m[2];
                    $refs[$tipo.'|'.$numero] = ['tipo' => $tipo, 'numero' => $numero];
                }
            }
        }

        if ($refs === []) {
            return $filas;
        }

        $tipos = array_values(array_unique(array_column($refs, 'tipo')));
        $numeros = array_values(array_unique(array_column($refs, 'numero')));
        $internos = [];
        foreach ($filas as $fila) {
            foreach ($fila['comprobantes_refs'] ?? [] as $ref) {
                $ni = (int) ($ref['nro_interno'] ?? 0);
                if ($ni > 0) {
                    $internos[$ni] = true;
                }
            }
        }

        $candidatosQuery = DB::table('comprobante_proveedor as cp')
            ->join('tipotransaccion_compra as ttc', 'ttc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->where(function ($q) use ($tipos, $numeros, $internos) {
                $q->where(function ($q2) use ($tipos, $numeros) {
                    $q2->whereIn(DB::raw('UPPER(TRIM(ttc.abreviatura))'), $tipos)
                        ->whereIn('cp.numerocomprobante', $numeros);
                });
                if ($internos !== []) {
                    $q->orWhereIn('cp.anita_nro_interno', array_keys($internos));
                }
            })
            ->select([
                'cp.id',
                'cp.empresa_id',
                'cp.proveedor_id',
                'cp.ordencompra_id',
                'cp.numerocomprobante',
                'cp.anita_nro_interno',
                DB::raw('UPPER(TRIM(ttc.abreviatura)) as tipo_abrev'),
                'oc.numeroordencompra',
            ]);

        $candidatos = $candidatosQuery->get();

        if ($candidatos->isEmpty()) {
            // Match numérico por si el nro en ERP tiene ceros a la izquierda distintos
            $candidatos = DB::table('comprobante_proveedor as cp')
                ->join('tipotransaccion_compra as ttc', 'ttc.id', '=', 'cp.tipotransaccion_compra_id')
                ->leftJoin('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
                ->whereIn(DB::raw('UPPER(TRIM(ttc.abreviatura))'), $tipos)
                ->whereIn(DB::raw('CAST(cp.numerocomprobante AS UNSIGNED)'), array_map('intval', $numeros))
                ->select([
                    'cp.id',
                    'cp.empresa_id',
                    'cp.proveedor_id',
                    'cp.ordencompra_id',
                    'cp.numerocomprobante',
                    'cp.anita_nro_interno',
                    DB::raw('UPPER(TRIM(ttc.abreviatura)) as tipo_abrev'),
                    'oc.numeroordencompra',
                ])
                ->get();
        }

        if ($candidatos->isEmpty()) {
            return $filas;
        }

        $ids = $candidatos->pluck('id')->map(fn ($id) => (int) $id)->unique()->all();
        $modelos = \App\Models\Compras\Comprobante_Proveedor::query()
            ->with([
                'ordencompras.ordencompra_articulos:id,ordencompra_id,centrocostodestino_id',
                'proveedores:id,centrocostocompra_id',
            ])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $ccIds = [];
        foreach ($modelos as $cp) {
            $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($cp);
            if ($ccId > 0) {
                $ccIds[$ccId] = $ccId;
            }
        }
        $centrosMap = $ccIds === []
            ? collect()
            : DB::table('centrocosto')->whereIn('id', array_values($ccIds))->get(['id', 'codigo', 'nombre'])->keyBy('id');

        /** @var array<string, list<object>> */
        $porTipoNro = [];
        /** @var array<int, list<object>> */
        $porInterno = [];
        foreach ($candidatos as $row) {
            $nroNorm = preg_replace('/\D+/', '', (string) $row->numerocomprobante) ?: (string) $row->numerocomprobante;
            $clave = strtoupper(trim((string) $row->tipo_abrev)).'|'.$nroNorm;
            $porTipoNro[$clave][] = $row;
            $claveInt = strtoupper(trim((string) $row->tipo_abrev)).'|'.(string) ((int) $nroNorm);
            $porTipoNro[$claveInt][] = $row;
            $ni = (int) ($row->anita_nro_interno ?? 0);
            if ($ni > 0) {
                $porInterno[$ni][] = $row;
            }
        }

        foreach ($filas as &$fila) {
            if (($fila['centros_costo'] ?? '') !== '' && ($fila['ordenes_compra'] ?? '') !== ''
                && ! empty($fila['comprobantes_links'])) {
                continue;
            }

            $refsFila = $fila['comprobantes_refs'] ?? [];
            if ($refsFila === [] && trim((string) ($fila['comprobantes'] ?? '')) !== '') {
                foreach (preg_split('/\s*\|\s*/', (string) $fila['comprobantes']) ?: [] as $parte) {
                    if (preg_match('/^([A-Z]{2,4})\s+(\d+)$/i', trim($parte), $m) !== 1) {
                        continue;
                    }
                    $refsFila[] = [
                        'tipo' => strtoupper($m[1]),
                        'numero' => $m[2],
                        'etiqueta' => strtoupper($m[1]).' '.$m[2],
                    ];
                }
            }

            $centros = [];
            $ocs = [];
            $links = $fila['comprobantes_links'] ?? [];
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $proveedorId = (int) ($fila['proveedor_id'] ?? 0);

            foreach ($refsFila as $ref) {
                $tipo = strtoupper(trim((string) ($ref['tipo'] ?? '')));
                $numero = preg_replace('/\D+/', '', (string) ($ref['numero'] ?? '')) ?? '';
                $nroInterno = (int) ($ref['nro_interno'] ?? 0);
                $candidatosRef = $porTipoNro[$tipo.'|'.$numero]
                    ?? $porTipoNro[$tipo.'|'.(string) ((int) $numero)]
                    ?? [];
                if ($candidatosRef === [] && $nroInterno > 0) {
                    $candidatosRef = $porInterno[$nroInterno] ?? [];
                }

                $elegido = null;
                foreach ($candidatosRef as $cand) {
                    if ($empresaId > 0 && (int) $cand->empresa_id === $empresaId
                        && ($proveedorId <= 0 || (int) $cand->proveedor_id === $proveedorId)) {
                        $elegido = $cand;
                        break;
                    }
                }
                if ($elegido === null && $candidatosRef !== []) {
                    foreach ($candidatosRef as $cand) {
                        if ($proveedorId > 0 && (int) $cand->proveedor_id === $proveedorId) {
                            $elegido = $cand;
                            break;
                        }
                    }
                }
                if ($elegido === null && $candidatosRef !== []) {
                    $elegido = $candidatosRef[0];
                }
                if ($elegido === null) {
                    continue;
                }

                $cpId = (int) $elegido->id;
                $links[] = [
                    'id' => $cpId,
                    'etiqueta' => (string) ($ref['etiqueta'] ?? ($tipo.' '.$numero)),
                ];

                if (isset($modelos[$cpId])) {
                    $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($modelos[$cpId]);
                    $cc = $centrosMap->get($ccId);
                    if ($cc) {
                        $label = trim(($cc->codigo ? $cc->codigo.' ' : '').($cc->nombre ?? ''));
                        if ($label !== '') {
                            $centros[$label] = $label;
                        }
                    }
                }

                $ocId = (int) ($elegido->ordencompra_id ?? 0);
                $ocNro = trim((string) ($elegido->numeroordencompra ?? ''));
                if ($ocId > 0 && $ocNro !== '') {
                    $ocs[$ocId] = ['id' => $ocId, 'numero' => $ocNro];
                }
            }

            if (($fila['centros_costo'] ?? '') === '' && $centros !== []) {
                $fila['centros_costo'] = implode(' | ', array_values($centros));
            }
            if (($fila['ordenes_compra'] ?? '') === '' && $ocs !== []) {
                $fila['ordenes_compra'] = implode(' | ', array_column(array_values($ocs), 'numero'));
                $fila['ordenes_compra_links'] = array_values($ocs);
            }
            if ($links !== []) {
                // Unique by id
                $uniq = [];
                foreach ($links as $link) {
                    $id = (int) ($link['id'] ?? 0);
                    if ($id > 0) {
                        $uniq[$id] = $link;
                    }
                }
                $fila['comprobantes_links'] = array_values($uniq);
            }
        }
        unset($fila);

        return $filas;
    }

    /**
     * OP Anita sin factura en ERP: aplicped (factura→PEP) + ordencompra local para CC/OC.
     * Una sola lectura bridge de aplicped; match por proveedor+tipo(+letra/sucursal)+nro.
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    private function enriquecerFilasAnitaDesdeAplicped(array $filas, array &$errores): array
    {
        $tipos = [];
        $numeros = [];
        $necesita = false;
        foreach ($filas as $fila) {
            if (($fila['centros_costo'] ?? '') !== '' && ($fila['ordenes_compra'] ?? '') !== '') {
                continue;
            }
            foreach ($fila['comprobantes_refs'] ?? [] as $ref) {
                $tipo = strtoupper(trim((string) ($ref['tipo'] ?? '')));
                $numero = preg_replace('/\D+/', '', (string) ($ref['numero'] ?? '')) ?? '';
                if ($tipo === '' || $numero === '') {
                    continue;
                }
                $tipos[$tipo] = true;
                $numeros[(int) $numero] = true;
                $necesita = true;
            }
        }
        if (! $necesita || $tipos === [] || $numeros === []) {
            return $filas;
        }

        $aplicped = $this->anitaReader->listarAplicpedFacturas(
            array_keys($numeros),
            array_keys($tipos),
            $errores,
        );
        if ($aplicped === []) {
            return $filas;
        }

        /** @var array<string, list<object>> $porClave */
        $porClave = [];
        $pepNros = [];
        foreach ($aplicped as $apl) {
            $provPad = InterbankingArchivoPagoAnitaReader::padProveedor($apl->aplp_proveedor ?? '');
            $tipo = strtoupper(trim((string) ($apl->aplp_tipo ?? '')));
            $letra = strtoupper(trim((string) ($apl->aplp_letra ?? 'A'))) ?: 'A';
            $suc = (int) ($apl->aplp_sucursal ?? 0);
            $nro = (int) preg_replace('/\D+/', '', (string) ($apl->aplp_nro ?? ''));
            $pep = (int) ($apl->aplp_ref_nro ?? 0);
            if ($provPad === '' || $tipo === '' || $nro <= 0 || $pep <= 0) {
                continue;
            }
            $pepNros[$pep] = true;
            $porClave[$provPad.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro][] = $apl;
            $porClave[$provPad.'|'.$tipo.'|'.$nro][] = $apl;
        }

        if ($pepNros === []) {
            return $filas;
        }

        $ocs = \App\Models\Compras\Ordencompra::query()
            ->with(['ordencompra_articulos:id,ordencompra_id,centrocostodestino_id'])
            ->whereIn('numeroordencompra', array_keys($pepNros))
            ->get();

        /** @var array<int, \App\Models\Compras\Ordencompra> $ocPorNro */
        $ocPorNro = [];
        $ccIds = [];
        foreach ($ocs as $oc) {
            $nroOc = (int) ($oc->numeroordencompra ?? 0);
            if ($nroOc <= 0) {
                continue;
            }
            $ocPorNro[$nroOc] = $oc;
            $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc);
            if ($ccId > 0) {
                $ccIds[$ccId] = $ccId;
            }
        }
        $centrosMap = $ccIds === []
            ? collect()
            : DB::table('centrocosto')->whereIn('id', array_values($ccIds))->get(['id', 'codigo', 'nombre'])->keyBy('id');

        foreach ($filas as &$fila) {
            if (($fila['centros_costo'] ?? '') !== '' && ($fila['ordenes_compra'] ?? '') !== '') {
                continue;
            }

            $provPad = InterbankingArchivoPagoAnitaReader::padProveedor($fila['proveedor_codigo'] ?? '');
            if ($provPad === '') {
                continue;
            }

            $centros = [];
            $ocsFila = [];
            foreach ($fila['comprobantes_refs'] ?? [] as $ref) {
                $tipo = strtoupper(trim((string) ($ref['tipo'] ?? '')));
                $numero = (int) preg_replace('/\D+/', '', (string) ($ref['numero'] ?? ''));
                if ($tipo === '' || $numero <= 0) {
                    continue;
                }
                $letra = strtoupper(trim((string) ($ref['letra'] ?? 'A'))) ?: 'A';
                $suc = (int) ($ref['sucursal'] ?? 0);
                $apls = $porClave[$provPad.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$numero]
                    ?? $porClave[$provPad.'|'.$tipo.'|'.$numero]
                    ?? [];

                foreach ($apls as $apl) {
                    $pep = (int) ($apl->aplp_ref_nro ?? 0);
                    $oc = $ocPorNro[$pep] ?? null;
                    if ($oc === null) {
                        continue;
                    }
                    $ocId = (int) $oc->id;
                    $ocNro = trim((string) ($oc->numeroordencompra ?? ''));
                    if ($ocId > 0 && $ocNro !== '') {
                        $ocsFila[$ocId] = ['id' => $ocId, 'numero' => $ocNro];
                    }
                    $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc);
                    $cc = $centrosMap->get($ccId);
                    if ($cc) {
                        $label = trim(($cc->codigo ? $cc->codigo.' ' : '').($cc->nombre ?? ''));
                        if ($label !== '') {
                            $centros[$label] = $label;
                        }
                    }
                }
            }

            if (($fila['centros_costo'] ?? '') === '' && $centros !== []) {
                $fila['centros_costo'] = implode(' | ', array_values($centros));
            }
            if (($fila['ordenes_compra'] ?? '') === '' && $ocsFila !== []) {
                $fila['ordenes_compra'] = implode(' | ', array_column(array_values($ocsFila), 'numero'));
                $fila['ordenes_compra_links'] = array_values($ocsFila);
            }
        }
        unset($fila);

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage, int $page): LengthAwarePaginator
    {
        $coleccion = collect($filas);
        $total = $coleccion->count();
        $items = $coleccion->forPage($page, $perPage)->values()->all();

        return new PaginatorImpl($items, $total, $perPage, $page, [
            'path' => PaginatorImpl::resolveCurrentPath(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  \Illuminate\Support\Collection<int, mixed>  $empresaQuery
     */
    public function subtituloFiltros(array $filtros, $empresaQuery): string
    {
        $partes = [];
        $desde = PagosSabanaReporteFiltros::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = PagosSabanaReporteFiltros::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);
        if ($desde !== '' || $hasta !== '') {
            $partes[] = 'Desde '.$desde.' hasta '.$hasta;
        }

        $ids = $filtros['empresa_ids'] ?? [];
        if ($ids !== []) {
            $nombres = $empresaQuery->whereIn('id', $ids)->pluck('nombre')->all();
            $partes[] = 'Empresas: '.implode(', ', $nombres);
        }

        if (empty($filtros['consolidar_empresas']) && count($ids) > 1) {
            $partes[] = 'Sin consolidar';
        }

        $partes[] = 'Expresado en: Pesos';

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function cargarCabeceras(array $filtros): Collection
    {
        $pp = $this->queryPagoproveedor($filtros);
        $ie = $this->queryIeOppOpa($filtros);

        return DB::query()
            ->fromSub($pp->unionAll($ie), 'pagos_sabana')
            ->orderBy('fecha')
            ->orderBy('numerotransaccion')
            ->orderBy('pk_id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryPagoproveedor(array $filtros)
    {
        $query = DB::table('pagoproveedor as pp')
            ->leftJoin('empresa', 'empresa.id', '=', 'pp.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'pp.proveedor_id')
            ->whereIn('pp.tipocomprobante', ['OPP', 'OPA'])
            ->whereIn('pp.estado', self::ESTADOS_PP)
            ->whereBetween('pp.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->select([
                DB::raw("'".PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR."' as origen"),
                'pp.id as pk_id',
                'pp.fecha',
                'pp.tipocomprobante',
                'pp.numerotransaccion',
                'pp.empresa_id',
                'empresa.codigo as empresa_codigo',
                'empresa.nombre as empresa_nombre',
                'pp.proveedor_id',
                'proveedor.codigo as proveedor_codigo',
                'proveedor.nombre as proveedor_nombre',
                'pp.monto',
                'pp.detalle',
                'pp.proveedor_formapago_id',
                'pp.caja_movimiento_id',
                DB::raw('(SELECT cm.solicitudpago_id FROM caja_movimiento cm
                    WHERE cm.solicitudpago_id IS NOT NULL
                      AND (cm.pagoproveedor_id = pp.id
                           OR (pp.caja_movimiento_id IS NOT NULL AND cm.id = pp.caja_movimiento_id))
                    ORDER BY cm.id
                    LIMIT 1) as solicitudpago_id'),
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'pp.empresa_id');
        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query->whereIn('pp.empresa_id', $filtros['empresa_ids']);
        }

        return $query;
    }

    /**
     * OPP/OPA de ingresos y egresos sin fila en pagoproveedor (SP y otros conceptos).
     *
     * @param  array<string, mixed>  $filtros
     */
    private function queryIeOppOpa(array $filtros)
    {
        $montoSub = DB::table('caja_movimiento_cuentacaja as cmc')
            ->select('cmc.caja_movimiento_id')
            ->selectRaw(
                'COALESCE(SUM(ABS(cmc.monto * CASE WHEN COALESCE(cmc.moneda_id, 1) > 1 THEN COALESCE(cmc.cotizacion, 1) ELSE 1 END)), 0) as monto_mn'
            )
            ->groupBy('cmc.caja_movimiento_id');

        $query = DB::table('caja_movimiento as cm')
            ->join('tipotransaccion_caja as ttc', 'ttc.id', '=', 'cm.tipotransaccion_caja_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'cm.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'cm.proveedor_id')
            ->leftJoinSub($montoSub, 'monto_agg', function ($join) {
                $join->on('monto_agg.caja_movimiento_id', '=', 'cm.id');
            })
            ->whereNull('cm.pagoproveedor_id')
            ->whereNull('cm.caja_movimiento_origen_id')
            ->whereNull('cm.caja_movimiento_revertido_por_id')
            ->whereNull('ttc.deleted_at')
            ->whereRaw('UPPER(TRIM(ttc.abreviatura)) IN (?, ?)', ['OPP', 'OPA'])
            ->whereBetween('cm.fecha', [$filtros['fecha_desde'], $filtros['fecha_hasta']])
            ->select([
                DB::raw("'".PagoproveedorListadoFila::ORIGEN_IE_OPP."' as origen"),
                'cm.id as pk_id',
                'cm.fecha',
                DB::raw('UPPER(TRIM(ttc.abreviatura)) as tipocomprobante'),
                'cm.numerotransaccion',
                'cm.empresa_id',
                'empresa.codigo as empresa_codigo',
                'empresa.nombre as empresa_nombre',
                'cm.proveedor_id',
                'proveedor.codigo as proveedor_codigo',
                'proveedor.nombre as proveedor_nombre',
                DB::raw('COALESCE(monto_agg.monto_mn, 0) as monto'),
                'cm.detalle',
                'cm.proveedor_formapago_id',
                DB::raw('cm.id as caja_movimiento_id'),
                'cm.solicitudpago_id',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cm.empresa_id');
        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query->whereIn('cm.empresa_id', $filtros['empresa_ids']);
        }

        return $query;
    }

    /**
     * @param  list<int>  $ppIds
     * @return array<int, list<object>>
     */
    private function cargarMediosCajaPorPagoproveedor(array $ppIds): array
    {
        if ($ppIds === []) {
            return [];
        }

        $rows = DB::table('caja_movimiento_cuentacaja as cmc')
            ->join('caja_movimiento as cm', 'cm.id', '=', 'cmc.caja_movimiento_id')
            ->join('cuentacaja as cc', 'cc.id', '=', 'cmc.cuentacaja_id')
            ->leftJoin('banco as b', 'b.id', '=', 'cc.banco_id')
            ->where(function ($q) use ($ppIds) {
                $q->whereIn('cm.pagoproveedor_id', $ppIds)
                    ->orWhereIn('cm.id', function ($sub) use ($ppIds) {
                        $sub->select('caja_movimiento_id')
                            ->from('pagoproveedor')
                            ->whereIn('id', $ppIds)
                            ->whereNotNull('caja_movimiento_id');
                    });
            })
            ->select([
                'cm.pagoproveedor_id',
                'cm.id as caja_movimiento_id',
                'cmc.monto',
                'cmc.moneda_id',
                'cmc.cotizacion',
                'cc.codigo',
                'cc.nombre',
                'cc.banco_id',
                'cc.tipocuenta',
                'b.nombre as banco_nombre',
            ])
            ->get();

        $ppPorMov = DB::table('pagoproveedor')
            ->whereIn('id', $ppIds)
            ->whereNotNull('caja_movimiento_id')
            ->pluck('id', 'caja_movimiento_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $ppId = (int) ($row->pagoproveedor_id ?? 0);
            if ($ppId <= 0) {
                $ppId = (int) ($ppPorMov[(int) $row->caja_movimiento_id] ?? 0);
            }
            if ($ppId <= 0) {
                continue;
            }
            $out[$ppId][] = $row;
        }

        return $out;
    }

    /**
     * @param  list<int>  $cmIds
     * @return array<int, list<object>>
     */
    private function cargarMediosCajaPorMovimiento(array $cmIds): array
    {
        if ($cmIds === []) {
            return [];
        }

        $rows = DB::table('caja_movimiento_cuentacaja as cmc')
            ->join('cuentacaja as cc', 'cc.id', '=', 'cmc.cuentacaja_id')
            ->leftJoin('banco as b', 'b.id', '=', 'cc.banco_id')
            ->whereIn('cmc.caja_movimiento_id', $cmIds)
            ->select([
                'cmc.caja_movimiento_id',
                'cmc.monto',
                'cmc.moneda_id',
                'cmc.cotizacion',
                'cc.codigo',
                'cc.nombre',
                'cc.banco_id',
                'cc.tipocuenta',
                'b.nombre as banco_nombre',
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->caja_movimiento_id][] = $row;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ppIds
     * @return array<int, list<object>>
     */
    private function cargarChequesPorPagoproveedor(array $ppIds): array
    {
        if ($ppIds === []) {
            return [];
        }

        $rows = DB::table('cheque as c')
            ->leftJoin('banco as b', 'b.id', '=', 'c.banco_id')
            ->whereIn('c.pagoproveedor_id', $ppIds)
            ->where(function ($q) {
                $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'A');
            })
            ->select(['c.pagoproveedor_id', 'c.origen', 'c.numerocheque', 'c.monto', 'c.cotizacion', 'c.moneda_id', 'b.nombre as banco_nombre'])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->pagoproveedor_id][] = $row;
        }

        return $out;
    }

    /**
     * @param  list<int>  $cmIds
     * @return array<int, list<object>>
     */
    private function cargarChequesPorMovimiento(array $cmIds): array
    {
        if ($cmIds === []) {
            return [];
        }

        $rows = DB::table('cheque as c')
            ->leftJoin('banco as b', 'b.id', '=', 'c.banco_id')
            ->whereIn('c.caja_movimiento_id', $cmIds)
            ->whereNull('c.pagoproveedor_id')
            ->where(function ($q) {
                $q->whereNull('c.estado')->orWhere('c.estado', '<>', 'A');
            })
            ->select(['c.caja_movimiento_id', 'c.origen', 'c.numerocheque', 'c.monto', 'c.cotizacion', 'c.moneda_id', 'b.nombre as banco_nombre'])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->caja_movimiento_id][] = $row;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ppIds
     * @return array<int, array{I: float, G: float, B: float, S: float}>
     */
    private function cargarRetenciones(array $ppIds): array
    {
        if ($ppIds === []) {
            return [];
        }

        $rows = DB::table('pagoproveedor_retencion')
            ->whereIn('pagoproveedor_id', $ppIds)
            ->select('pagoproveedor_id', 'tiporetencion', DB::raw('SUM(importe) as total'))
            ->groupBy('pagoproveedor_id', 'tiporetencion')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $ppId = (int) $row->pagoproveedor_id;
            if (! isset($out[$ppId])) {
                $out[$ppId] = ['I' => 0.0, 'G' => 0.0, 'B' => 0.0, 'S' => 0.0];
            }
            $tipo = strtoupper(trim((string) $row->tiporetencion));
            if (isset($out[$ppId][$tipo])) {
                $out[$ppId][$tipo] = round((float) $row->total, 2);
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $ppIds
     * @return array<int, array{comprobantes: list<array<string, mixed>>, centros: list<string>, ocs: list<array<string, mixed>>}>
     */
    private function cargarAplicaciones(array $ppIds): array
    {
        if ($ppIds === []) {
            return [];
        }

        $rows = DB::table('pagoproveedor_comprobante as ppc')
            ->join('proveedor_cuentacorriente as pcc', 'pcc.id', '=', 'ppc.proveedor_cuentacorriente_id')
            ->leftJoin('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
            ->leftJoin('tipotransaccion_compra as ttc', 'ttc.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('ordencompra as oc', 'oc.id', '=', 'cp.ordencompra_id')
            ->whereIn('ppc.pagoproveedor_id', $ppIds)
            ->select([
                'ppc.pagoproveedor_id',
                'cp.id as comprobante_id',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'ttc.abreviatura as tipo_abrev',
                'oc.id as ordencompra_id',
                'oc.numeroordencompra',
                'cp.ordencompra_id as cp_ordencompra_id',
            ])
            ->get();

        $comprobanteIds = $rows->pluck('comprobante_id')->filter()->map(fn ($id) => (int) $id)->unique()->all();
        $comprobantes = [];
        if ($comprobanteIds !== []) {
            $comprobantes = \App\Models\Compras\Comprobante_Proveedor::query()
                ->with([
                    'ordencompras.ordencompra_articulos:id,ordencompra_id,centrocostodestino_id',
                    'proveedores:id,centrocostocompra_id',
                ])
                ->whereIn('id', $comprobanteIds)
                ->get()
                ->keyBy('id');
        }

        $centrocostoIds = [];
        foreach ($comprobantes as $cp) {
            $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($cp);
            if ($ccId > 0) {
                $centrocostoIds[$ccId] = $ccId;
            }
        }
        $centrosMap = $centrocostoIds === []
            ? collect()
            : DB::table('centrocosto')->whereIn('id', array_values($centrocostoIds))->get(['id', 'codigo', 'nombre'])->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $ppId = (int) $row->pagoproveedor_id;
            if (! isset($out[$ppId])) {
                $out[$ppId] = ['comprobantes' => [], 'centros' => [], 'ocs' => []];
            }

            $tipo = (string) ($row->tipo_abrev ?: 'FAC');
            $nro = trim((string) ($row->numerocomprobante ?? ''));
            if ($nro !== '') {
                $etiqueta = sprintf('%s %s-%04d-%s', $tipo, (string) ($row->letra ?? 'A'), (int) ($row->sucursal ?? 0), $nro);
                $out[$ppId]['comprobantes'][] = [
                    'id' => (int) ($row->comprobante_id ?? 0),
                    'etiqueta' => $etiqueta,
                ];
            }

            $cpId = (int) ($row->comprobante_id ?? 0);
            if ($cpId > 0 && isset($comprobantes[$cpId])) {
                $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($comprobantes[$cpId]);
                $cc = $centrosMap->get($ccId);
                if ($cc) {
                    $label = trim(($cc->codigo ? $cc->codigo.' ' : '').($cc->nombre ?? ''));
                    if ($label !== '') {
                        $out[$ppId]['centros'][$label] = $label;
                    }
                }
            }

            $ocId = (int) ($row->ordencompra_id ?? 0);
            $ocNro = trim((string) ($row->numeroordencompra ?? ''));
            if ($ocId > 0 && $ocNro !== '') {
                $out[$ppId]['ocs'][$ocId] = [
                    'id' => $ocId,
                    'numero' => $ocNro,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $spIds
     * @return array<int, list<string>>
     */
    private function cargarCentrosCostoSp(array $spIds): array
    {
        if ($spIds === []) {
            return [];
        }

        $rows = DB::table('solicitudpago_cuenta as spc')
            ->join('centrocosto as cc', 'cc.id', '=', 'spc.centrocosto_id')
            ->whereIn('spc.solicitudpago_id', $spIds)
            ->whereNotNull('spc.centrocosto_id')
            ->select(['spc.solicitudpago_id', 'cc.codigo', 'cc.nombre'])
            ->distinct()
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $label = trim(($row->codigo ? $row->codigo.' ' : '').($row->nombre ?? ''));
            if ($label === '') {
                continue;
            }
            $out[(int) $row->solicitudpago_id][$label] = $label;
        }

        return array_map(static fn (array $mapa) => array_values($mapa), $out);
    }

    /**
     * @param  list<int>  $ppIds
     * @param  list<int>  $ieIds
     * @return array{pp: array<int, string>, ie: array<int, string>}
     */
    private function cargarFormapagos(array $ppIds, array $ieIds): array
    {
        $out = ['pp' => [], 'ie' => []];

        if ($ppIds !== []) {
            $rows = DB::table('pagoproveedor as pp')
                ->leftJoin('proveedor_formapago as pf', 'pf.id', '=', 'pp.proveedor_formapago_id')
                ->leftJoin('formapago as f', 'f.id', '=', 'pf.formapago_id')
                ->whereIn('pp.id', $ppIds)
                ->select(['pp.id', 'f.abreviatura'])
                ->get();
            foreach ($rows as $row) {
                $out['pp'][(int) $row->id] = strtoupper(trim((string) ($row->abreviatura ?? '')));
            }
        }

        if ($ieIds !== []) {
            $rows = DB::table('caja_movimiento as cm')
                ->leftJoin('proveedor_formapago as pf', 'pf.id', '=', 'cm.proveedor_formapago_id')
                ->leftJoin('formapago as f', 'f.id', '=', 'pf.formapago_id')
                ->whereIn('cm.id', $ieIds)
                ->select(['cm.id', 'f.abreviatura'])
                ->get();
            foreach ($rows as $row) {
                $out['ie'][(int) $row->id] = strtoupper(trim((string) ($row->abreviatura ?? '')));
            }
        }

        return $out;
    }

    /**
     * @param  object  $cab
     * @param  array<int, list<object>>  $mediosPp
     * @param  array<int, list<object>>  $mediosIe
     * @param  array<int, list<object>>  $chequesPp
     * @param  array<int, list<object>>  $chequesIe
     * @param  array<int, array{I: float, G: float, B: float, S: float}>  $retenciones
     * @param  array<int, array{comprobantes: list<array<string, mixed>>, centros: list<string>|array, ocs: list<array<string, mixed>>|array}>  $aplicaciones
     * @param  array<int, list<string>>  $spCentros
     * @param  array{pp: array<int, string>, ie: array<int, string>}  $formapagos
     * @return array<string, mixed>
     */
    private function armarFila(
        object $cab,
        array $mediosPp,
        array $mediosIe,
        array $chequesPp,
        array $chequesIe,
        array $retenciones,
        array $aplicaciones,
        array $spCentros,
        array $formapagos,
    ): array {
        $origen = (string) $cab->origen;
        $pkId = (int) $cab->pk_id;
        $esPp = $origen === PagoproveedorListadoFila::ORIGEN_PAGOPROVEEDOR;

        $lineasCaja = $esPp ? ($mediosPp[$pkId] ?? []) : ($mediosIe[$pkId] ?? []);
        $cheques = $esPp ? ($chequesPp[$pkId] ?? []) : ($chequesIe[$pkId] ?? []);
        $rets = $esPp ? ($retenciones[$pkId] ?? ['I' => 0.0, 'G' => 0.0, 'B' => 0.0, 'S' => 0.0]) : ['I' => 0.0, 'G' => 0.0, 'B' => 0.0, 'S' => 0.0];
        $apps = $esPp ? ($aplicaciones[$pkId] ?? ['comprobantes' => [], 'centros' => [], 'ocs' => []]) : ['comprobantes' => [], 'centros' => [], 'ocs' => []];

        $desglose = $this->desglosarMedios($lineasCaja, $cheques);
        $desglose['retencion_iva'] = round((float) ($rets['I'] ?? 0), 2);
        $desglose['retencion_gan'] = round((float) ($rets['G'] ?? 0), 2);
        $desglose['retencion_ibr'] = round((float) ($rets['B'] ?? 0), 2);
        $desglose['retencion_suss'] = round((float) ($rets['S'] ?? 0), 2);

        $totalMedios = $desglose['efectivo'] + $desglose['transferencia'] + $desglose['ch_propios']
            + $desglose['ch_terceros'] + $desglose['doc_propios'] + $desglose['doc_terceros']
            + $desglose['varios'] + $desglose['intercompany'] + $desglose['creditos'] + $desglose['adelantos']
            + $desglose['retencion_iva'] + $desglose['retencion_gan'] + $desglose['retencion_ibr']
            + $desglose['retencion_suss'];

        $totalCabecera = round((float) ($cab->monto ?? 0), 2);
        $totalPago = $totalMedios > 0.005 ? round($totalMedios, 2) : $totalCabecera;

        $abrevFp = $esPp ? ($formapagos['pp'][$pkId] ?? '') : ($formapagos['ie'][$pkId] ?? '');
        $tipoMedio = $this->resolverTipoMedio($abrevFp, $desglose);

        $centrosMap = [];
        if ($esPp) {
            foreach (array_values(is_array($apps['centros'] ?? null) ? $apps['centros'] : []) as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $centrosMap[$label] = $label;
                }
            }
        }
        $spId = (int) ($cab->solicitudpago_id ?? 0);
        if ($spId > 0 && isset($spCentros[$spId])) {
            foreach ($spCentros[$spId] as $label) {
                $label = trim((string) $label);
                if ($label !== '') {
                    $centrosMap[$label] = $label;
                }
            }
        }
        $centros = array_values($centrosMap);

        $comps = $apps['comprobantes'] ?? [];
        $ocs = array_values($apps['ocs'] ?? []);

        $fecha = '';
        if (! empty($cab->fecha)) {
            try {
                $fecha = \Carbon\Carbon::parse((string) $cab->fecha)->format('Y-m-d');
            } catch (\Throwable) {
                $fecha = (string) $cab->fecha;
            }
        }

        return array_merge([
            'tipo_fila' => 'detalle',
            'origen' => $origen,
            'pk_id' => $pkId,
            'pagoproveedor_id' => $esPp ? $pkId : null,
            'caja_movimiento_id' => $esPp ? null : $pkId,
            'solicitudpago_id' => $spId > 0 ? $spId : null,
            'proveedor_id' => (int) ($cab->proveedor_id ?? 0) ?: null,
            'proveedor_codigo' => (string) ($cab->proveedor_codigo ?? ''),
            'proveedor_nombre' => (string) ($cab->proveedor_nombre ?? ''),
            'tip' => (string) ($cab->tipocomprobante ?? 'OPP'),
            'numero_op' => (string) ($cab->numerotransaccion ?? ''),
            'fecha' => $fecha,
            'tipo_medio' => $tipoMedio,
            'total_pago' => $totalPago,
            'comprobantes' => $this->unirEtiquetas(array_column($comps, 'etiqueta')),
            'comprobantes_links' => $comps,
            'ch_prop_emi' => $desglose['ch_prop_emi'],
            'banco' => $desglose['banco'],
            'ch_terc_ent' => $desglose['ch_terc_ent'],
            'doc_prop_emit' => '',
            'doc_terc_entr' => '',
            'centros_costo' => implode(' | ', $centros),
            'ordenes_compra' => $this->unirEtiquetas(array_column($ocs, 'numero')),
            'ordenes_compra_links' => $ocs,
            'detalle' => (string) ($cab->detalle ?? ''),
            'empresa_id' => (int) ($cab->empresa_id ?? 0),
            'empresa' => (string) ($cab->empresa_codigo ?? $cab->empresa_id ?? ''),
            'empresa_nombre' => (string) ($cab->empresa_nombre ?? ''),
            'nombreempresa' => (string) ($cab->empresa_nombre ?? ''),
        ], [
            'efectivo' => $desglose['efectivo'],
            'transferencia' => $desglose['transferencia'],
            'ch_propios' => $desglose['ch_propios'],
            'ch_terceros' => $desglose['ch_terceros'],
            'doc_propios' => $desglose['doc_propios'],
            'doc_terceros' => $desglose['doc_terceros'],
            'retencion_iva' => $desglose['retencion_iva'],
            'retencion_gan' => $desglose['retencion_gan'],
            'retencion_ibr' => $desglose['retencion_ibr'],
            'retencion_suss' => $desglose['retencion_suss'],
            'creditos' => $desglose['creditos'],
            'adelantos' => $desglose['adelantos'],
            'varios' => $desglose['varios'],
            'intercompany' => $desglose['intercompany'],
        ]);
    }

    /**
     * @param  list<object>  $lineasCaja
     * @param  list<object>  $cheques
     * @return array<string, mixed>
     */
    private function desglosarMedios(array $lineasCaja, array $cheques): array
    {
        $out = [
            'efectivo' => 0.0,
            'transferencia' => 0.0,
            'ch_propios' => 0.0,
            'ch_terceros' => 0.0,
            'doc_propios' => 0.0,
            'doc_terceros' => 0.0,
            'creditos' => 0.0,
            'adelantos' => 0.0,
            'varios' => 0.0,
            'intercompany' => 0.0,
            'ch_prop_emi' => '',
            'ch_terc_ent' => '',
            'banco' => '',
        ];

        $bancos = [];
        $chPropEmi = [];
        $chTercEnt = [];

        foreach ($lineasCaja as $linea) {
            $importe = $this->importeMn($linea->monto ?? 0, $linea->moneda_id ?? 1, $linea->cotizacion ?? 1);
            if ($importe < 0.005) {
                continue;
            }
            $codigo = strtoupper(trim((string) ($linea->codigo ?? '')));
            $nombre = strtoupper(trim((string) ($linea->nombre ?? '')));
            $bancoId = (int) ($linea->banco_id ?? 0);

            if (str_starts_with($codigo, 'INT') || str_contains($nombre, 'INTERCOMP')) {
                $out['intercompany'] += $importe;
                continue;
            }

            if ($bancoId > 0) {
                $out['transferencia'] += $importe;
                $bancoLabel = $this->etiquetaBancoCuenta((string) ($linea->nombre ?? ''), (string) ($linea->banco_nombre ?? ''));
                if ($bancoLabel !== '') {
                    $bancos[$bancoLabel] = $bancoLabel;
                }
                continue;
            }

            if (preg_match('/CAJA|FONDO\\s*FIJO|EFECTIVO/', $nombre) === 1) {
                $out['efectivo'] += $importe;
                continue;
            }

            $out['varios'] += $importe;
        }

        foreach ($cheques as $cheque) {
            $importe = $this->importeMn($cheque->monto ?? 0, $cheque->moneda_id ?? 1, $cheque->cotizacion ?? 1);
            $origen = strtoupper(trim((string) ($cheque->origen ?? '')));
            $nro = trim((string) ($cheque->numerocheque ?? ''));
            $bancoCh = trim((string) ($cheque->banco_nombre ?? ''));
            $texto = $nro !== '' ? ($nro.($bancoCh !== '' ? '/'.mb_substr($bancoCh, 0, 4).'.' : '')) : '';

            if ($origen === 'E') {
                $out['ch_propios'] += $importe;
                if ($texto !== '') {
                    $chPropEmi[$texto] = $texto;
                }
                if ($bancoCh !== '') {
                    $bancos[$bancoCh] = $bancoCh;
                }
            } elseif ($origen === 'R') {
                $out['ch_terceros'] += $importe;
                if ($texto !== '') {
                    $chTercEnt[$texto] = $texto;
                }
            }
        }

        foreach (['efectivo', 'transferencia', 'ch_propios', 'ch_terceros', 'varios', 'intercompany'] as $k) {
            $out[$k] = round($out[$k], 2);
        }
        $out['ch_prop_emi'] = implode(' ', array_values($chPropEmi));
        $out['ch_terc_ent'] = implode(' ', array_values($chTercEnt));
        $out['banco'] = implode(' | ', array_values($bancos));

        return $out;
    }

    private function importeMn($monto, $monedaId, $cotizacion): float
    {
        $base = abs((float) $monto);
        $monedaId = (int) $monedaId;
        $cot = (float) $cotizacion;
        if ($monedaId > 1 && $cot > 0) {
            return round($base * $cot, 2);
        }

        return round($base, 2);
    }

    private function etiquetaBancoCuenta(string $nombreCuenta, string $bancoNombre): string
    {
        $nombreCuenta = trim($nombreCuenta);
        if ($nombreCuenta !== '') {
            $antes = explode('$', $nombreCuenta, 2)[0];

            return trim($antes) !== '' ? trim($antes) : $nombreCuenta;
        }

        return trim($bancoNombre);
    }

    /**
     * @param  array<string, mixed>  $desglose
     */
    private function resolverTipoMedio(string $abrevFp, array $desglose): string
    {
        return match ($abrevFp) {
            'T' => 'TRF',
            'C', 'V' => 'CHQ',
            'E' => 'EFE',
            default => match (true) {
                abs((float) ($desglose['ch_propios'] ?? 0)) >= 0.005
                    || abs((float) ($desglose['ch_terceros'] ?? 0)) >= 0.005 => 'CHQ',
                abs((float) ($desglose['efectivo'] ?? 0)) >= 0.005
                    && abs((float) ($desglose['transferencia'] ?? 0)) < 0.005 => 'EFE',
                default => 'TRF',
            },
        };
    }

    /**
     * @param  list<string>  $etiquetas
     */
    private function unirEtiquetas(array $etiquetas): string
    {
        $unicas = [];
        foreach ($etiquetas as $etiqueta) {
            $etiqueta = trim((string) $etiqueta);
            if ($etiqueta === '') {
                continue;
            }
            $unicas[$etiqueta] = $etiqueta;
        }

        return implode(' | ', array_values($unicas));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  list<array<string, mixed>>  $columnas
     * @return array<string, mixed>
     */
    private function calcularTotales(array $filas, array $columnas): array
    {
        $detalle = array_values(array_filter(
            $filas,
            static fn (array $f) => ($f['tipo_fila'] ?? '') !== 'header_empresa'
        ));

        $importes = [];
        foreach ($columnas as $col) {
            if ($col['tipo'] !== PagosSabanaColumnasSupport::TIPO_IMPORTE) {
                continue;
            }
            $clave = $col['clave'];
            $importes[$clave] = round(array_sum(array_map(
                static fn (array $f) => (float) ($f[$clave] ?? 0),
                $detalle
            )), 2);
        }

        return [
            'cantidad' => count($detalle),
            'importes' => $importes,
            'total_pago' => $importes['total_pago'] ?? 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function totalesVacios(): array
    {
        return ['cantidad' => 0, 'importes' => [], 'total_pago' => 0.0];
    }
}
