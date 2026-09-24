<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Proveedor;
use App\Support\Caja\IngresoEgresoCanjeChequeSupport;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use App\Support\Caja\Macro\MacroArchivoPagoAnitaReader;
use App\Support\Caja\Macro\MacroArchivoPagoFiltros;
use App\Support\Caja\Macro\MacroArchivoPagoFormatoSupport;
use App\Support\Caja\Macro\MacroArchivoPagoRetencionTextoSupport;
use App\Support\Caja\Macro\MacroPagoCanalSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\ProveedorCbuPagoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Exportación pagos Banco Macro (diskette).
 *
 * Filtra OP como p-enviamacro.c:
 * - Anita: auxpag con axp_banco = cuenta elegida y tipo TMR/TMK/TMB (transf) o CHP/CPC (cheque)
 * - ERP: movimientos/cheques de la cuentacaja Macro seleccionada
 *   (incluye canje/reemplazo CANJE como IEV: el cheque nuevo con cheque_reemplaza_id)
 * - Excluye revertidas/anuladas: AOP en Anita (mismo nro), cpro_fecha_anula, estado ERP
 *
 * Canal: config macro.canal (archivo hoy; webservice después).
 */
class MacroArchivoPagoService
{
    public function __construct(
        private readonly MacroArchivoPagoAnitaReader $anitaReader = new MacroArchivoPagoAnitaReader,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   ok:bool,
     *   mensaje:string,
     *   filas:list<array<string,mixed>>,
     *   omitidas:list<array<string,mixed>>,
     *   errores:list<string>,
     *   total_importe:float,
     *   cantidad:int,
     *   canal:string,
     *   export:array<string,mixed>,
     *   archivos:array<string,string>
     * }
     */
    public function generar(array $filtros): array
    {
        if (! MacroArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return $this->vacio('Indique empresa y rango de fechas.');
        }

        $empresaId = (int) $filtros['empresa_id'];
        $cuenta = $this->resolverCuentaOrigen(
            $empresaId,
            (int) ($filtros['cuentacaja_id'] ?? 0),
            (string) ($filtros['cuenta_anita'] ?? '')
        );
        if ($cuenta === null) {
            return $this->vacio('Seleccione la cuenta de caja Macro (origen del débito).');
        }

        $cuentaAnita = MacroArchivoPagoFiltros::padCuentaAnita(
            (string) ($filtros['cuenta_anita'] ?: $cuenta->codigo)
        );
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $cuentaDebito = (string) ($filtros['cuenta_debito'] ?? '');
        if ($cuentaDebito === '') {
            $cuentaDebito = MacroArchivoPagoFormatoSupport::cuentaDebitoEmpresa($empresaAnita);
        }
        if ($cuentaDebito === '') {
            return $this->vacio(
                'Configure la cuenta débito Macro (config macro.cuentas_debito o campo cuenta_debito).'
            );
        }

        $fechaDesde = (string) $filtros['fecha_desde'];
        $fechaHasta = (string) $filtros['fecha_hasta'];
        $tipoOp = (string) ($filtros['tipo_op'] ?? 'OPP');
        $opDesde = (int) ($filtros['op_desde'] ?? 0);
        $opHasta = (int) ($filtros['op_hasta'] ?? 99999999);
        $tipoAp = (string) ($filtros['tipo_aplicacion'] ?? '');
        $sucursalBanco = (int) ($filtros['sucursal_banco'] ?? 0);
        $usuarioRet = (string) ($filtros['usuario_retencion'] ?? '');
        $incluirErp = ! empty($filtros['incluir_erp']);
        $incluirAnita = ! empty($filtros['incluir_anita']);
        $incluirCheques = ! empty($filtros['incluir_cheques']);
        $incluirTransf = ! empty($filtros['incluir_transferencias']);

        $filas = [];
        $beneficiarios = [];
        $retenciones = [];
        $omitidas = [];
        $errores = [];
        /** @var array<string, true> */
        $opsAnita = [];

        if ($incluirAnita) {
            [$filasAnita, $benefAnita, $retAnita] = $this->recolectarAnita(
                $empresaId,
                $empresaAnita,
                $cuentaAnita,
                $cuentaDebito,
                $fechaDesde,
                $fechaHasta,
                $tipoOp,
                $opDesde,
                $opHasta,
                $tipoAp,
                $sucursalBanco,
                $usuarioRet,
                $incluirCheques,
                $incluirTransf,
                $errores
            );
            foreach ($filasAnita as $f) {
                $clave = $this->claveFila($f);
                $filas[$clave] = $f;
                $opsAnita[$this->claveOp((string) $f['tipo'], (int) $f['numero'])] = true;
            }
            foreach ($benefAnita as $cuit => $b) {
                $beneficiarios[$cuit] = $b;
            }
            $retenciones = array_merge($retenciones, $retAnita);
        }

        if ($incluirErp) {
            [$filasErp, $benefErp, $retErp, $omitErp] = $this->recolectarErp(
                $empresaId,
                $empresaAnita,
                (int) $cuenta->id,
                $cuentaDebito,
                $fechaDesde,
                $fechaHasta,
                $tipoOp,
                $opDesde,
                $opHasta,
                $sucursalBanco,
                $usuarioRet,
                $incluirCheques,
                $incluirTransf,
                $opsAnita
            );
            foreach ($filasErp as $f) {
                $clave = $this->claveFila($f);
                if (! isset($filas[$clave])) {
                    $filas[$clave] = $f;
                }
            }
            foreach ($benefErp as $cuit => $b) {
                if (! isset($beneficiarios[$cuit])) {
                    $beneficiarios[$cuit] = $b;
                }
            }
            $retenciones = array_merge($retenciones, $retErp);
            $omitidas = array_merge($omitidas, $omitErp);
        }

        $filas = array_values($filas);
        usort($filas, static function (array $a, array $b): int {
            $cmp = ((int) $a['numero']) <=> ((int) $b['numero']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) ($a['orden_pago'] ?? ''), (string) ($b['orden_pago'] ?? ''));
        });

        $ordenes = array_map(static function (array $f): array {
            return [
                'cuit' => (string) ($f['cuit'] ?? ''),
                'sucursal_banco' => (int) ($f['sucursal_banco'] ?? 0),
                'orden_pago' => (string) ($f['orden_pago'] ?? ''),
                'nombre' => (string) ($f['proveedor_nombre'] ?? ''),
                'importe' => (float) ($f['importe'] ?? 0),
                'cuenta_debito' => (string) ($f['cuenta_debito'] ?? ''),
                'referencia_cbu_o_cheque' => (string) ($f['referencia_cbu_o_cheque'] ?? ''),
                'modalidad' => (int) ($f['modalidad'] ?? 4),
                'flag_entrega' => (int) ($f['flag_entrega'] ?? 0),
                'fecha_pago' => (string) ($f['fecha'] ?? ''),
                'fecha_cheque' => (string) ($f['fecha_cheque'] ?? ''),
            ];
        }, $filas);

        $lote = [
            'beneficiarios' => array_values($beneficiarios),
            'ordenes' => $ordenes,
            'retenciones' => $retenciones,
            'meta' => [
                'empresa_id' => $empresaId,
                'cuentacaja_id' => (int) $cuenta->id,
                'cuenta_anita' => $cuentaAnita,
                'cuenta_debito' => $cuentaDebito,
            ],
        ];

        $canal = MacroPagoCanalSupport::canalActivo();
        $export = $canal->exportar($lote);
        $total = round(array_sum(array_column($filas, 'importe')), 2);

        return [
            'ok' => ! empty($export['ok']) && count($filas) > 0,
            'mensaje' => count($filas) > 0
                ? 'Listo: '.count($filas).' pago(s) Macro por $'.number_format($total, 2, ',', '.')
                    .' (canal '.$canal->codigo().')'
                : 'Sin pagos Macro en el rango (revise cuenta Anita / CBU / cheques).',
            'filas' => $filas,
            'omitidas' => $omitidas,
            'errores' => $errores,
            'total_importe' => $total,
            'cantidad' => count($filas),
            'canal' => $canal->codigo(),
            'export' => $export,
            'archivos' => $export['archivos'] ?? [],
        ];
    }

    public function resolverCuentaOrigen(int $empresaId, int $cuentacajaId = 0, string $codigoHint = ''): ?Cuentacaja
    {
        if ($cuentacajaId > 0) {
            $porId = $this->buscarCuentaOrigen($empresaId, $cuentacajaId);
            if ($porId !== null) {
                return $porId;
            }
        }

        $hint = MacroArchivoPagoFiltros::padCuentaAnita($codigoHint);
        if ($empresaId <= 0) {
            return null;
        }

        $q = Cuentacaja::query()->paraEmpresa($empresaId)->orderBy('codigo');
        if ($hint !== '') {
            $porCodigo = (clone $q)->where('codigo', $hint)->first();
            if ($porCodigo === null) {
                $porCodigo = (clone $q)->where('codigo', ltrim($hint, '0'))->first();
            }
            if ($porCodigo === null) {
                // Match últimos dígitos del código (Anita 8 vs ERP corto)
                foreach ((clone $q)->get(['id', 'codigo', 'nombre', 'cbu', 'empresa_id']) as $cta) {
                    if (MacroArchivoPagoFiltros::padCuentaAnita((string) $cta->codigo) === $hint) {
                        $porCodigo = $cta;
                        break;
                    }
                }
            }
            if ($porCodigo !== null) {
                return $porCodigo;
            }
        }

        // Preferir cuentas cuyo CBU empiece con código Macro
        $macro = (string) config('macro.codigo_banco', 285);
        foreach ($q->whereNotNull('cbu')->where('cbu', '!=', '')->get() as $cta) {
            $cbu = CbuSupport::normalizar((string) $cta->cbu);
            if (str_starts_with($cbu, $macro)) {
                return $cta;
            }
        }

        return null;
    }

    public function buscarCuentaOrigen(int $empresaId, int $cuentacajaId): ?Cuentacaja
    {
        if ($cuentacajaId <= 0) {
            return null;
        }
        $q = Cuentacaja::query()->whereKey($cuentacajaId);
        if ($empresaId > 0) {
            $q->paraEmpresa($empresaId);
        }

        return $q->first();
    }

    /**
     * @param  list<string>  $errores
     * @return array{0:list<array<string,mixed>>,1:array<string,array<string,mixed>>,2:list<array<string,mixed>>}
     */
    private function recolectarAnita(
        int $empresaId,
        int $empresaAnita,
        string $cuentaAnita,
        string $cuentaDebito,
        string $fechaDesde,
        string $fechaHasta,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
        string $tipoAp,
        int $sucursalBanco,
        string $usuarioRet,
        bool $incluirCheques,
        bool $incluirTransf,
        array &$errores,
    ): array {
        $desdeYmd = (int) str_replace('-', '', $fechaDesde);
        $hastaYmd = (int) str_replace('-', '', $fechaHasta);

        $pagos = $this->anitaReader->listarPagos(
            $empresaAnita,
            $desdeYmd,
            $hastaYmd,
            $tipoOp,
            $opDesde,
            $opHasta,
            $errores
        );
        if ($pagos === []) {
            return [[], [], []];
        }

        $aopsPorRec = $this->anitaReader->mapaRecsAnuladosPorAop(
            $empresaAnita,
            $opDesde,
            $opHasta,
            $errores
        );
        $opsAnuladasErp = $this->mapaOpsAnuladasErp($empresaId, $opDesde, $opHasta);

        $auxpag = $this->anitaReader->listarAuxpagPeriodo($empresaAnita, $desdeYmd, $hastaYmd, $errores);
        $auxPorOp = [];
        foreach ($auxpag as $axp) {
            $tipo = strtoupper(substr(trim((string) ($axp->axp_tipo ?? '')), 0, 3));
            $rec = (int) ($axp->axp_rec ?? 0);
            $emp = (int) ($axp->axp_empresa ?? 0);
            if ($tipo === '' || $rec <= 0) {
                continue;
            }
            $auxPorOp[$emp.'|'.$tipo.'|'.$rec][] = $axp;
        }

        $codigos = [];
        foreach ($pagos as $pag) {
            $codigos[] = (string) ($pag->pag_pro ?? '');
        }
        $mapaProp = $this->anitaReader->mapaPropago($codigos, $errores);
        $mapaProm = $this->anitaReader->mapaPromae($codigos, $errores);

        $tiposTransf = array_map('strtoupper', (array) config('macro.tipos_ap_transferencia', ['TMR', 'TMK', 'TMB']));
        $tiposCheque = array_map('strtoupper', (array) config('macro.tipos_ap_cheque', ['CHP', 'CPC']));

        $filas = [];
        $beneficiarios = [];
        $retenciones = [];

        foreach ($pagos as $pag) {
            $tipo = strtoupper(substr(trim((string) ($pag->pag_tipo ?? '')), 0, 3));
            $tiposOk = MacroArchivoPagoFormatoSupport::tiposComprobanteFiltro($tipoOp);
            if ($tiposOk === null) {
                if (! str_starts_with($tipo, 'OP') && ! in_array($tipo, array_map('strtoupper', (array) config('macro.tipos_op_extra_con_opp', ['IEV'])), true)) {
                    continue;
                }
            } elseif (! in_array($tipo, $tiposOk, true)) {
                continue;
            }
            $rec = (int) ($pag->pag_rec ?? 0);
            if ($rec < $opDesde || $rec > $opHasta) {
                continue;
            }
            $empPag = (int) ($pag->pag_empresa ?? 0) ?: $empresaAnita;
            if (isset($aopsPorRec[$empPag.'|'.$rec]) || isset($opsAnuladasErp[$this->claveOp($tipo, $rec)])) {
                continue;
            }
            $suc = (int) ($pag->pag_sucursal ?? 0);
            $proCod = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($pag->pag_pro ?? ''));
            $prom = $mapaProm[$proCod] ?? null;
            $prop = $mapaProp[$proCod] ?? null;

            $lineas = $auxPorOp[$empPag.'|'.$tipo.'|'.$rec] ?? [];
            $lineasMacro = $this->filtrarLineasAuxpagMacro(
                $lineas,
                $cuentaAnita,
                $tipoAp,
                $tiposTransf,
                $tiposCheque,
                $incluirCheques,
                $incluirTransf
            );
            if ($lineasMacro === []) {
                continue;
            }

            $fechaPag = self::fechaYmd($pag->pag_fecha ?? null);
            $cuit = MacroArchivoPagoFormatoSupport::cuit11((string) ($prom?->prom_cuit ?? $prop?->prop_cuit ?? ''));
            $nombre = trim((string) ($prom?->prom_nombre ?? $proCod));
            $envioOp = false;
            $ordenPagoBase = MacroArchivoPagoFormatoSupport::ordenPagoTransferencia($tipo, $suc, $rec);
            // Como p-enviamacro: RTN usa el último orden_pago grabado en OPG (cheque incluye nro).
            $ordenPagoRtn = $ordenPagoBase;
            $comps = [];

            foreach ($lineas as $axp) {
                if ((int) ($axp->axp_nro_interno ?? 0) !== 0) {
                    $comps[] = [
                        'tipo_apli' => strtoupper(substr(trim((string) ($axp->axp_tipo_ap ?? '')), 0, 3)),
                        'letra' => (string) ($axp->axp_letra_comp ?? ' '),
                        'sucursal' => (int) ($axp->axp_sucursal ?? 0),
                        'nro' => (int) ($axp->axp_nro ?? 0),
                        'monto_ap' => (float) ($axp->axp_monto_ap ?? 0),
                    ];
                }
            }

            foreach ($lineasMacro as $axp) {
                $tAp = strtoupper(substr(trim((string) ($axp->axp_tipo_ap ?? '')), 0, 3));
                $imp = round(abs((float) ($axp->axp_monto_ap ?? 0)), 2);
                if ($imp < 0.005) {
                    continue;
                }

                if (in_array($tAp, $tiposCheque, true)) {
                    $nroCh = (int) ($axp->axp_nro ?? 0);
                    $fechaCh = (int) ($axp->axp_fecha_co ?? 0);
                    $cheque = $this->anitaReader->leerCheque($cuentaAnita, $nroCh, $fechaCh, $errores);
                    if (MacroArchivoPagoAnitaReader::chequeAnuladoEnCpromae($cheque)) {
                        continue;
                    }
                    if ($cheque === null) {
                        // Sin cpromae: igual exporta con datos de auxpag (importe/fecha)
                        $impCh = $imp;
                        $emi = (string) $fechaCh;
                        $fechChStr = (string) $fechaCh;
                        $paraDep = ' ';
                    } else {
                        $impCh = round(abs((float) ($cheque->cpro_importe ?? $imp)), 2);
                        $emi = (string) ($cheque->cpro_fecha_emision ?? $fechaCh);
                        $fechChStr = (string) ($cheque->cpro_fecha_cheque ?? $fechaCh);
                        $paraDep = strtoupper(substr(trim((string) ($cheque->cpro_para_dep ?? '')), 0, 1));
                    }
                    $modalidad = MacroArchivoPagoFormatoSupport::modalidadCheque($emi, $fechChStr);
                    $orden = MacroArchivoPagoFormatoSupport::ordenPagoCheque($tipo, $suc, $rec, $nroCh);
                    $ordenPagoRtn = $orden;
                    $filas[] = [
                        'origen' => 'Anita',
                        'medio' => 'cheque',
                        'proveedor_codigo' => $proCod,
                        'proveedor_nombre' => $nombre,
                        'cuit' => $cuit,
                        'tipo' => $tipo,
                        'sucursal' => $suc,
                        'numero' => $rec,
                        'fecha' => $fechaPag,
                        'fecha_cheque' => self::fechaYmd($fechChStr),
                        'cbu' => '',
                        'importe' => $impCh,
                        'orden_pago' => $orden,
                        'cuenta_debito' => $cuentaDebito,
                        'referencia_cbu_o_cheque' => (string) $nroCh,
                        'modalidad' => $modalidad,
                        'flag_entrega' => $paraDep === 'E' ? 1 : 2,
                        'sucursal_banco' => $sucursalBanco,
                    ];
                    $envioOp = true;
                } else {
                    $cbuAux = CbuSupport::normalizar((string) ($axp->axp_cbu ?? ''));
                    $cbuProp = CbuSupport::normalizar((string) ($prop?->prop_cbu ?? ''));
                    $cbu = $cbuAux !== '' ? $cbuAux : $cbuProp;
                    $val = CbuSupport::validarConMensaje($cbu);
                    if (! $val['ok']) {
                        continue;
                    }
                    $codBanco = (int) ($prop?->prop_cod_banco ?? 0);
                    $modalidad = MacroArchivoPagoFormatoSupport::modalidadTransferencia($val['cbu'], $codBanco ?: null);
                    $orden = $ordenPagoBase;
                    $ordenPagoRtn = $orden;
                    $filas[] = [
                        'origen' => 'Anita',
                        'medio' => 'transferencia',
                        'proveedor_codigo' => $proCod,
                        'proveedor_nombre' => $nombre,
                        'cuit' => $cuit,
                        'tipo' => $tipo,
                        'sucursal' => $suc,
                        'numero' => $rec,
                        'fecha' => $fechaPag,
                        'fecha_cheque' => '',
                        'cbu' => $val['cbu'],
                        'importe' => $imp,
                        'orden_pago' => $orden,
                        'cuenta_debito' => $cuentaDebito,
                        'referencia_cbu_o_cheque' => $val['cbu'],
                        'modalidad' => $modalidad,
                        'flag_entrega' => 0,
                        'sucursal_banco' => $sucursalBanco,
                    ];
                    $envioOp = true;
                }
            }

            if ($envioOp && $cuit !== '') {
                $beneficiarios[$cuit] = $this->beneficiarioDesdePromae($prom, $prop, $proCod, $cuit, $nombre);
                if ($comps !== []) {
                    array_push(
                        $retenciones,
                        ...MacroArchivoPagoRetencionTextoSupport::desdeComprobantes(
                            $ordenPagoRtn,
                            $comps,
                            $usuarioRet
                        )
                    );
                }
                $letraPag = ' ';
                $bloquesRet = $this->anitaReader->listarRetencionesOp(
                    $proCod,
                    $tipo,
                    $letraPag,
                    $suc,
                    $rec,
                    $empPag,
                    $errores
                );
                array_push(
                    $retenciones,
                    ...MacroArchivoPagoRetencionTextoSupport::desdeAnitaBloques(
                        $ordenPagoRtn,
                        $bloquesRet,
                        $usuarioRet,
                        $nombre,
                        $cuit
                    )
                );
            }
        }

        return [$filas, $beneficiarios, $retenciones];
    }

    /**
     * @param  list<object>  $lineas
     * @param  list<string>  $tiposTransf
     * @param  list<string>  $tiposCheque
     * @return list<object>
     */
    private function filtrarLineasAuxpagMacro(
        array $lineas,
        string $cuentaAnita,
        string $tipoAp,
        array $tiposTransf,
        array $tiposCheque,
        bool $incluirCheques,
        bool $incluirTransf,
    ): array {
        $out = [];
        foreach ($lineas as $axp) {
            $banco = MacroArchivoPagoFiltros::padCuentaAnita((string) ($axp->axp_banco ?? ''));
            if ($cuentaAnita !== '' && $banco !== $cuentaAnita) {
                continue;
            }
            $t = strtoupper(substr(trim((string) ($axp->axp_tipo_ap ?? '')), 0, 3));
            if ($tipoAp !== '') {
                if ($t !== $tipoAp) {
                    continue;
                }
            } else {
                $esTransf = in_array($t, $tiposTransf, true);
                $esCheque = in_array($t, $tiposCheque, true);
                if ($esTransf && ! $incluirTransf) {
                    continue;
                }
                if ($esCheque && ! $incluirCheques) {
                    continue;
                }
                if (! $esTransf && ! $esCheque) {
                    continue;
                }
            }
            $out[] = $axp;
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $opsAnita
     * @return array{0:list<array>,1:array<string,array>,2:list<array>,3:list<array>}
     */
    private function recolectarErp(
        int $empresaId,
        int $empresaAnita,
        int $cuentacajaId,
        string $cuentaDebito,
        string $fechaDesde,
        string $fechaHasta,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
        int $sucursalBanco,
        string $usuarioRet,
        bool $incluirCheques,
        bool $incluirTransf,
        array $opsAnita,
    ): array {
        $filas = [];
        $beneficiarios = [];
        $retenciones = [];
        $omitidas = [];

        $ops = Pagoproveedor::query()
            ->with([
                'proveedores',
                'pagoproveedor_retenciones',
                'cheques',
                'caja_movimientos.caja_movimiento_cuentacajas',
            ])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->whereBetween('numerotransaccion', [$opDesde, $opHasta])
            ->whereNotIn('estado', ['BAJA', 'REVERTIDA', 'PAGADA', 'CONCILIADA'])
            ->where(function ($q) {
                $q->whereNull('bloqueado_banco')->orWhere('bloqueado_banco', false);
            })
            ->where(function ($q) use ($tipoOp) {
                $tipos = MacroArchivoPagoFormatoSupport::tiposComprobanteFiltro($tipoOp);
                if ($tipos === null) {
                    $extras = array_values(array_filter(array_map(
                        static fn ($t) => strtoupper(substr(trim((string) $t), 0, 3)),
                        (array) config('macro.tipos_op_extra_con_opp', ['IEV'])
                    )));
                    $q->where(function ($q2) use ($extras) {
                        $q2->whereRaw("UPPER(TRIM(tipocomprobante)) LIKE 'OP%'");
                        if ($extras !== []) {
                            $ph = implode(',', array_fill(0, count($extras), '?'));
                            $q2->orWhereRaw('UPPER(TRIM(tipocomprobante)) IN ('.$ph.')', $extras);
                        }
                    });
                } else {
                    $ph = implode(',', array_fill(0, count($tipos), '?'));
                    $q->whereRaw('UPPER(TRIM(tipocomprobante)) IN ('.$ph.')', $tipos);
                }
            })
            ->where(function ($q) use ($cuentacajaId) {
                $q->whereHas('caja_movimientos.caja_movimiento_cuentacajas', function ($q2) use ($cuentacajaId) {
                    $q2->where('cuentacaja_id', $cuentacajaId);
                })->orWhereHas('cheques', function ($q2) use ($cuentacajaId) {
                    $q2->where('cuentacaja_id', $cuentacajaId);
                });
            })
            ->orderBy('numerotransaccion')
            ->get();

        foreach ($ops as $op) {
            $claveOp = $this->claveOp((string) $op->tipocomprobante, (int) $op->numerotransaccion);
            if (isset($opsAnita[$claveOp])) {
                continue;
            }

            $prov = $op->proveedores;
            $cuit = MacroArchivoPagoFormatoSupport::cuit11((string) ($prov->nroinscripcion ?? ''));
            $nombre = (string) ($prov->nombre ?? '');
            $codigo = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($prov->codigo ?? ''));
            $tipo = strtoupper(substr(trim((string) $op->tipocomprobante), 0, 3));
            $suc = (int) $op->sucursal;
            $rec = (int) $op->numerotransaccion;
            $fecha = self::fechaYmd($op->fecha);
            $agrego = false;
            $ordenPagoRtn = MacroArchivoPagoFormatoSupport::ordenPagoTransferencia($tipo, $suc, $rec);

            if ($incluirCheques) {
                foreach ($op->cheques as $ch) {
                    if ((int) ($ch->cuentacaja_id ?? 0) !== $cuentacajaId) {
                        continue;
                    }
                    if (in_array(strtoupper((string) ($ch->estado ?? '')), ['ANULADO', 'BAJA'], true)) {
                        continue;
                    }
                    $nroCh = (int) ($ch->numerocheque ?? 0);
                    $imp = round(abs((float) ($ch->monto ?? 0)), 2);
                    if ($nroCh <= 0 || $imp < 0.005) {
                        continue;
                    }
                    $modalidad = MacroArchivoPagoFormatoSupport::modalidadCheque(
                        self::fechaYmd($ch->fechaemision) ?? '',
                        self::fechaYmd($ch->fechapago) ?? ''
                    );
                    $paraDep = strtoupper(substr(trim((string) ($ch->para_dep ?? '')), 0, 1));
                    $orden = MacroArchivoPagoFormatoSupport::ordenPagoCheque($tipo, $suc, $rec, $nroCh);
                    $ordenPagoRtn = $orden;
                    $filas[] = [
                        'origen' => 'ERP',
                        'medio' => 'cheque',
                        'proveedor_codigo' => $codigo,
                        'proveedor_nombre' => $nombre,
                        'cuit' => $cuit,
                        'tipo' => $tipo,
                        'sucursal' => $suc,
                        'numero' => $rec,
                        'fecha' => $fecha,
                        'fecha_cheque' => self::fechaYmd($ch->fechapago),
                        'cbu' => '',
                        'importe' => $imp,
                        'orden_pago' => $orden,
                        'cuenta_debito' => $cuentaDebito,
                        'referencia_cbu_o_cheque' => (string) $nroCh,
                        'modalidad' => $modalidad,
                        'flag_entrega' => $paraDep === 'E' ? 1 : 2,
                        'sucursal_banco' => $sucursalBanco,
                    ];
                    $agrego = true;
                }
            }

            if ($incluirTransf) {
                $usaCuenta = false;
                foreach ($op->caja_movimientos as $mov) {
                    foreach ($mov->caja_movimiento_cuentacajas as $cmc) {
                        if ((int) $cmc->cuentacaja_id === $cuentacajaId) {
                            $usaCuenta = true;
                            break 2;
                        }
                    }
                }
                if ($usaCuenta) {
                    $cbu = ProveedorCbuPagoSupport::cbuDesdeDocumento(
                        (int) ($op->proveedor_formapago_id ?? 0) ?: null,
                        (string) ($op->cbu_pago ?? ''),
                        (int) $op->proveedor_id,
                        (string) ($op->detalle ?? '')
                    );
                    $val = CbuSupport::validarConMensaje($cbu);
                    $bruto = (float) $op->monto;
                    $ret = $op->totalRetenciones();
                    $neto = $op->netoAPagar();
                    if (! $val['ok'] || $neto < 0.005) {
                        if (! $agrego) {
                            $omitidas[] = [
                                'origen' => 'ERP',
                                'tipo' => $tipo,
                                'numero' => $rec,
                                'proveedor' => $nombre,
                                'motivo' => ! $val['ok']
                                    ? 'Sin CBU válido'
                                    : 'Monto neto cero',
                            ];
                        }
                    } else {
                        $modalidad = MacroArchivoPagoFormatoSupport::modalidadTransferencia($val['cbu']);
                        $orden = MacroArchivoPagoFormatoSupport::ordenPagoTransferencia($tipo, $suc, $rec);
                        $ordenPagoRtn = $orden;
                        $filas[] = [
                            'origen' => 'ERP',
                            'medio' => 'transferencia',
                            'proveedor_codigo' => $codigo,
                            'proveedor_nombre' => $nombre,
                            'cuit' => $cuit,
                            'tipo' => $tipo,
                            'sucursal' => $suc,
                            'numero' => $rec,
                            'fecha' => $fecha,
                            'fecha_cheque' => '',
                            'cbu' => $val['cbu'],
                            'importe' => $neto,
                            'orden_pago' => $orden,
                            'cuenta_debito' => $cuentaDebito,
                            'referencia_cbu_o_cheque' => $val['cbu'],
                            'modalidad' => $modalidad,
                            'flag_entrega' => 0,
                            'sucursal_banco' => $sucursalBanco,
                        ];
                        $agrego = true;
                    }
                }
            }

            if ($agrego && $cuit !== '') {
                $beneficiarios[$cuit] = $this->beneficiarioDesdeProveedorErp($prov, $cuit, $codigo, $nombre);
                $compsErp = $this->comprobantesDesdePagoproveedor($op);
                if ($compsErp !== []) {
                    array_push(
                        $retenciones,
                        ...MacroArchivoPagoRetencionTextoSupport::desdeComprobantes(
                            $ordenPagoRtn,
                            $compsErp,
                            $usuarioRet
                        )
                    );
                }
                array_push(
                    $retenciones,
                    ...MacroArchivoPagoRetencionTextoSupport::desdePagoproveedor(
                        $ordenPagoRtn,
                        $op,
                        $usuarioRet
                    )
                );
            }
        }

        // IE OPP sin pagoproveedor, misma cuenta
        if ($incluirTransf) {
            $tipoOppId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig();
            $ieQuery = Caja_Movimiento::query()
                ->with(['proveedores', 'caja_movimiento_cuentacajas'])
                ->where('empresa_id', $empresaId)
                ->whereNull('pagoproveedor_id')
                ->whereNull('caja_movimiento_revertido_por_id')
                ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                ->whereBetween('numerotransaccion', [$opDesde, $opHasta])
                ->whereHas('caja_movimiento_cuentacajas', function ($q) use ($cuentacajaId) {
                    $q->where('cuentacaja_id', $cuentacajaId);
                });

            if ($tipoOppId > 0) {
                $ieQuery->where('tipotransaccion_caja_id', $tipoOppId);
            } else {
                $ieQuery->whereHas('tipotransaccioncajas', function ($q) {
                    $q->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP'])->whereNull('deleted_at');
                });
            }
            $tiposFiltroIe = MacroArchivoPagoFormatoSupport::tiposComprobanteFiltro($tipoOp);
            if ($tiposFiltroIe !== null && ! in_array('OPP', $tiposFiltroIe, true)) {
                $ieQuery->whereRaw('1 = 0');
            }

            foreach ($ieQuery->orderBy('numerotransaccion')->get() as $mov) {
                $claveOp = $this->claveOp('OPP', (int) $mov->numerotransaccion);
                if (isset($opsAnita[$claveOp])) {
                    continue;
                }
                $fila = $this->filaIeOppMacro($mov, $cuentaDebito, $sucursalBanco, $empresaAnita);
                if ($fila === null) {
                    $omitidas[] = [
                        'origen' => 'ERP-IE',
                        'tipo' => 'OPP',
                        'numero' => (int) $mov->numerotransaccion,
                        'proveedor' => (string) ($mov->proveedores->nombre ?? ''),
                        'motivo' => 'Sin CBU / monto',
                    ];

                    continue;
                }
                $filas[] = $fila;
                if ($fila['cuit'] !== '') {
                    $beneficiarios[$fila['cuit']] = $this->beneficiarioDesdeProveedorErp(
                        $mov->proveedores,
                        $fila['cuit'],
                        $fila['proveedor_codigo'],
                        $fila['proveedor_nombre']
                    );
                }
            }
        }

        // Canje/reemplazo ERP: no hay IEV en Anita (CANJE no escribe tesmov).
        // Como p-enviamacro con IEV: incluir el cheque nuevo (cheque_reemplaza_id).
        if ($incluirCheques) {
            [$filasCanje, $benefCanje, $omitCanje] = $this->recolectarCanjeChequesErp(
                $empresaId,
                $empresaAnita,
                $cuentacajaId,
                $cuentaDebito,
                $fechaDesde,
                $fechaHasta,
                $tipoOp,
                $sucursalBanco,
                $opsAnita,
                $filas
            );
            array_push($filas, ...$filasCanje);
            foreach ($benefCanje as $cuit => $ben) {
                $beneficiarios[$cuit] = $ben;
            }
            array_push($omitidas, ...$omitCanje);
        }

        return [$filas, $beneficiarios, $retenciones, $omitidas];
    }

    /**
     * Cheques emitidos por canje (IE tipo CANJE) de la cuentacaja Macro.
     * Se filtra por fecha (no por nro OP: el canje numera aparte, p.ej. 1, 2…).
     *
     * @param  array<string, true>  $opsAnita
     * @param  list<array<string,mixed>>  $filasYa
     * @return array{0:list<array<string,mixed>>,1:array<string,array<string,mixed>>,2:list<array<string,mixed>>}
     */
    private function recolectarCanjeChequesErp(
        int $empresaId,
        int $empresaAnita,
        int $cuentacajaId,
        string $cuentaDebito,
        string $fechaDesde,
        string $fechaHasta,
        string $tipoOp,
        int $sucursalBanco,
        array $opsAnita,
        array $filasYa,
    ): array {
        $tiposFiltro = MacroArchivoPagoFormatoSupport::tiposComprobanteFiltro($tipoOp);
        $extras = array_map(
            'strtoupper',
            (array) config('macro.tipos_op_extra_con_opp', ['IEV'])
        );
        if ($tiposFiltro !== null
            && ! in_array('IEV', $tiposFiltro, true)
            && ! in_array('OPP', $tiposFiltro, true)
            && count(array_intersect($tiposFiltro, $extras)) === 0
        ) {
            return [[], [], []];
        }

        $yaCheques = [];
        foreach ($filasYa as $f) {
            if (($f['medio'] ?? '') !== 'cheque') {
                continue;
            }
            $n = (int) ($f['referencia_cbu_o_cheque'] ?? 0);
            if ($n > 0) {
                $yaCheques[$n] = true;
            }
        }

        $tipoCanjeId = (int) (DB::table('tipotransaccion_caja')
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [IngresoEgresoCanjeChequeSupport::ABREV_CANJE])
            ->whereNull('deleted_at')
            ->value('id') ?? 0);

        $query = Cheque::query()
            ->with(['proveedores', 'cuentacajas', 'caja_movimientos'])
            ->where('origen', 'E')
            ->where('cuentacaja_id', $cuentacajaId)
            ->whereNotNull('cheque_reemplaza_id')
            ->where('cheque_reemplaza_id', '>', 0)
            ->whereNotIn(DB::raw('UPPER(TRIM(estado))'), ['A', 'ANULADO', 'BAJA'])
            ->whereHas('caja_movimientos', function ($q) use ($empresaId, $fechaDesde, $fechaHasta, $tipoCanjeId) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->whereNull('caja_movimiento_revertido_por_id');
                if ($tipoCanjeId > 0) {
                    $q->where('tipotransaccion_caja_id', $tipoCanjeId);
                } else {
                    $q->whereHas('tipotransaccioncajas', function ($q2) {
                        $q2->whereRaw('UPPER(TRIM(abreviatura)) = ?', [IngresoEgresoCanjeChequeSupport::ABREV_CANJE])
                            ->whereNull('deleted_at');
                    });
                }
            });

        $filas = [];
        $beneficiarios = [];
        $omitidas = [];
        $tipoOpg = $extras[0] ?? 'IEV';

        foreach ($query->orderBy('numerocheque')->get() as $ch) {
            $nroCh = (int) ($ch->numerocheque ?? 0);
            $imp = round(abs((float) ($ch->monto ?? 0)), 2);
            if ($nroCh <= 0 || $imp < 0.005) {
                continue;
            }
            if (isset($yaCheques[$nroCh])) {
                continue;
            }

            $mov = $ch->caja_movimientos;
            $rec = (int) ($mov->numerotransaccion ?? 0);
            $claveOp = $this->claveOp($tipoOpg, $rec);
            if ($rec > 0 && isset($opsAnita[$claveOp])) {
                continue;
            }

            $prov = $ch->proveedores;
            $cuit = MacroArchivoPagoFormatoSupport::cuit11((string) ($prov->nroinscripcion ?? ''));
            $nombre = trim((string) ($prov->nombre ?? ''));
            if ($nombre === '') {
                $nombre = trim((string) ($ch->anombrede ?? ''));
            }
            if ($cuit === '' && $nombre === '') {
                $omitidas[] = [
                    'origen' => 'ERP-CANJE',
                    'tipo' => $tipoOpg,
                    'numero' => $rec,
                    'proveedor' => (string) ($ch->anombrede ?? ''),
                    'motivo' => 'Cheque canje '.$nroCh.' sin proveedor/CUIT',
                ];

                continue;
            }

            $codigo = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($prov->codigo ?? ''));
            $suc = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) ($mov->empresa_id ?? $empresaId))
                ?: $empresaAnita;
            $fecha = self::fechaYmd($mov->fecha ?? $ch->fechaemision);
            $modalidad = MacroArchivoPagoFormatoSupport::modalidadCheque(
                self::fechaYmd($ch->fechaemision) ?? '',
                self::fechaYmd($ch->fechapago) ?? ''
            );
            $paraDep = strtoupper(substr(trim((string) ($ch->para_dep ?? '')), 0, 1));
            $orden = MacroArchivoPagoFormatoSupport::ordenPagoCheque($tipoOpg, $suc, max($rec, 0), $nroCh);

            $filas[] = [
                'origen' => 'ERP-CANJE',
                'medio' => 'cheque',
                'proveedor_codigo' => $codigo,
                'proveedor_nombre' => $nombre !== '' ? $nombre : 'SIN NOMBRE',
                'cuit' => $cuit,
                'tipo' => $tipoOpg,
                'sucursal' => $suc,
                'numero' => $rec,
                'fecha' => $fecha,
                'fecha_cheque' => self::fechaYmd($ch->fechapago),
                'cbu' => '',
                'importe' => $imp,
                'orden_pago' => $orden,
                'cuenta_debito' => $cuentaDebito,
                'referencia_cbu_o_cheque' => (string) $nroCh,
                'modalidad' => $modalidad,
                'flag_entrega' => $paraDep === 'E' ? 1 : 2,
                'sucursal_banco' => $sucursalBanco,
            ];
            $yaCheques[$nroCh] = true;

            if ($cuit !== '' && $prov) {
                $beneficiarios[$cuit] = $this->beneficiarioDesdeProveedorErp(
                    $prov,
                    $cuit,
                    $codigo,
                    $nombre !== '' ? $nombre : (string) ($prov->nombre ?? '')
                );
            }
        }

        return [$filas, $beneficiarios, $omitidas];
    }

    private function filaIeOppMacro(
        Caja_Movimiento $mov,
        string $cuentaDebito,
        int $sucursalBanco,
        int $empresaAnita,
    ): ?array {
        $mov->loadMissing(['proveedores']);
        $proveedorId = (int) ($mov->proveedor_id ?? 0);
        if ($proveedorId <= 0) {
            return null;
        }
        $cbu = ProveedorCbuPagoSupport::cbuDesdeDocumento(
            (int) ($mov->proveedor_formapago_id ?? 0) ?: null,
            (string) ($mov->cbu_pago ?? ''),
            $proveedorId,
            (string) ($mov->detalle ?? '')
        );
        $val = CbuSupport::validarConMensaje($cbu);
        if (! $val['ok']) {
            return null;
        }
        $monto = (float) DB::table('caja_movimiento_cuentacaja as cmc')
            ->where('cmc.caja_movimiento_id', $mov->id)
            ->selectRaw(
                'COALESCE(SUM(ABS(cmc.monto * CASE WHEN COALESCE(cmc.moneda_id, 1) > 1 THEN COALESCE(cmc.cotizacion, 1) ELSE 1 END)), 0) as monto_mn'
            )
            ->value('monto_mn');
        $monto = round(abs($monto), 2);
        if ($monto < 0.005) {
            return null;
        }
        $prov = $mov->proveedores;
        $codigo = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($prov->codigo ?? ''));
        $cuit = MacroArchivoPagoFormatoSupport::cuit11((string) ($prov->nroinscripcion ?? ''));
        $suc = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $mov->empresa_id) ?: $empresaAnita;
        $rec = (int) $mov->numerotransaccion;
        $orden = MacroArchivoPagoFormatoSupport::ordenPagoTransferencia('OPP', $suc, $rec);

        return [
            'origen' => 'ERP-IE',
            'medio' => 'transferencia',
            'proveedor_codigo' => $codigo,
            'proveedor_nombre' => (string) ($prov->nombre ?? ''),
            'cuit' => $cuit,
            'tipo' => 'OPP',
            'sucursal' => $suc,
            'numero' => $rec,
            'fecha' => self::fechaYmd($mov->fecha),
            'fecha_cheque' => '',
            'cbu' => $val['cbu'],
            'importe' => $monto,
            'orden_pago' => $orden,
            'cuenta_debito' => $cuentaDebito,
            'referencia_cbu_o_cheque' => $val['cbu'],
            'modalidad' => MacroArchivoPagoFormatoSupport::modalidadTransferencia($val['cbu']),
            'flag_entrega' => 0,
            'sucursal_banco' => $sucursalBanco,
        ];
    }

    private function beneficiarioDesdePromae(?object $prom, ?object $prop, string $proCod, string $cuit, string $nombre): array
    {
        $retIbr = trim((string) ($prom?->prom_ret_ibr ?? ''));
        $condIva = trim((string) ($prom?->prom_cond_iva ?? ''));
        $condGan = trim((string) ($prom?->prom_cond_gan ?? ''));
        $fiscal = MacroArchivoPagoFormatoSupport::condicionesFiscales($retIbr, $condIva, $condGan);
        $email = trim((string) ($prop?->prop_e_mail_conf ?? $prom?->prom_e_mail ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            $email = 'proveedores@grupoagg.com';
        }

        return [
            'cuit' => $cuit,
            'ing_bruto' => $fiscal['ing_bruto'],
            'ganancia' => $fiscal['ganancia'],
            'iva' => $fiscal['iva'],
            'nombre' => $nombre,
            'proveedor_codigo' => $proCod,
            'domicilio' => 'NO INFORMADA',
            'cod_postal' => trim((string) ($prom?->prom_cod_postal ?? '')),
            'email' => $email,
        ];
    }

    private function beneficiarioDesdeProveedorErp(?Proveedor $prov, string $cuit, string $codigo, string $nombre): array
    {
        $email = trim((string) ($prov->email ?? ''));
        if ($email === '' || ! str_contains($email, '@')) {
            $email = 'proveedores@grupoagg.com';
        }

        return [
            'cuit' => $cuit,
            'ing_bruto' => 1,
            'ganancia' => 1,
            'iva' => 1,
            'nombre' => $nombre,
            'proveedor_codigo' => $codigo,
            'domicilio' => 'NO INFORMADA',
            'cod_postal' => '',
            'email' => $email,
        ];
    }

    /**
     * @param  list<array{tipo_apli:string,letra:string,sucursal:int,nro:int,monto_ap:float}>  $comps
     * @return list<array<string,mixed>>
     *
     * @deprecated usar MacroArchivoPagoRetencionTextoSupport::desdeComprobantes
     */
    private function retencionesComprobantes(string $ordenPago, array $comps, string $usuario): array
    {
        return MacroArchivoPagoRetencionTextoSupport::desdeComprobantes($ordenPago, $comps, $usuario);
    }

    /**
     * @return list<array{tipo_apli:string,letra:string,sucursal:int,nro:int,monto_ap:float}>
     */
    private function comprobantesDesdePagoproveedor(Pagoproveedor $op): array
    {
        $op->loadMissing(['pagoproveedor_comprobantes.proveedor_cuentacorrientes.comprobante_proveedores']);
        $comps = [];
        foreach ($op->pagoproveedor_comprobantes as $pc) {
            $cc = $pc->proveedor_cuentacorrientes;
            $cp = $cc?->comprobante_proveedores;
            if ($cp === null) {
                continue;
            }
            $comps[] = [
                'tipo_apli' => strtoupper(substr(trim((string) ($cp->tipocomprobante ?? 'FAC')), 0, 3)),
                'letra' => (string) ($cp->letra ?? ' '),
                'sucursal' => (int) ($cp->sucursal ?? 0),
                'nro' => (int) ($cp->numero ?? 0),
                'monto_ap' => (float) ($pc->montoaplicado ?? 0),
            ];
        }

        return $comps;
    }

    private function claveFila(array $f): string
    {
        return strtoupper((string) ($f['orden_pago'] ?? ''))
            .'|'.(string) ($f['medio'] ?? '')
            .'|'.(string) ($f['referencia_cbu_o_cheque'] ?? '');
    }

    /**
     * OP originales en ERP ya dadas de baja o revertidas (no compensatorios AOP).
     *
     * @return array<string, true> clave tipo|numero
     */
    private function mapaOpsAnuladasErp(int $empresaId, int $opDesde, int $opHasta): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        $filas = Pagoproveedor::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('numerotransaccion', [$opDesde, $opHasta])
            ->whereIn('estado', ['BAJA', 'REVERTIDA'])
            ->where(function ($q) {
                $q->whereNull('pagoproveedor_origen_id')
                    ->orWhere('pagoproveedor_origen_id', 0);
            })
            ->get(['tipocomprobante', 'numerotransaccion']);

        $mapa = [];
        foreach ($filas as $fila) {
            $mapa[$this->claveOp((string) $fila->tipocomprobante, (int) $fila->numerotransaccion)] = true;
        }

        return $mapa;
    }

    private function claveOp(string $tipo, int $numero): string
    {
        return strtoupper(substr(trim($tipo), 0, 3)).'|'.$numero;
    }

    private static function fechaYmd(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{8}$/', $s) === 1) {
            return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
        }
        $ts = strtotime($s);

        return $ts ? date('Y-m-d', $ts) : null;
    }

    /**
     * @return array{
     *   ok:bool,mensaje:string,filas:list,omitidas:list,errores:list,
     *   total_importe:float,cantidad:int,canal:string,export:array,archivos:array
     * }
     */
    private function vacio(string $mensaje): array
    {
        return [
            'ok' => false,
            'mensaje' => $mensaje,
            'filas' => [],
            'omitidas' => [],
            'errores' => [],
            'total_importe' => 0.0,
            'cantidad' => 0,
            'canal' => (string) config('macro.canal', 'archivo'),
            'export' => [],
            'archivos' => [],
        ];
    }
}
