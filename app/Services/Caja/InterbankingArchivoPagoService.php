<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cuentacaja;
use App\Models\Compras\Pagoproveedor;
use App\Support\Caja\IngresoEgresoSolicitudpagoSupport;
use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use App\Support\Caja\InterbankingArchivoPagoFiltros;
use App\Support\Caja\InterbankingArchivoPagoFormatoSupport;
use App\Support\Compras\CbuSupport;
use App\Support\Compras\ProveedorCbuPagoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\DB;

/**
 * Genera archivo ASCII Interbanking combinando:
 * - Pagos ERP (pagoproveedor + OPP de Ingresos/Egresos) con CBU de proveedor_formapago
 * - Pagos solo Anita (pago/auxpag) como p-pagoxbanco.c, sin duplicar los ya tomados del ERP
 */
class InterbankingArchivoPagoService
{
    public function __construct(
        private readonly InterbankingArchivoPagoAnitaReader $anitaReader = new InterbankingArchivoPagoAnitaReader,
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
     *   archivo:string,
     *   observacion:string
     * }
     */
    public function generar(array $filtros): array
    {
        if (! InterbankingArchivoPagoFiltros::tieneCriteriosAplicados($filtros)) {
            return $this->vacio('Indique empresa y rango de fechas.');
        }

        $cbuOrigen = $this->cbuOrigenDesdeFiltros($filtros);
        $valCbu = CbuSupport::validarConMensaje($cbuOrigen);
        if (! $valCbu['ok']) {
            return $this->vacio('Seleccione una cuenta de caja con CBU válido. '.($valCbu['mensaje'] ?? ''));
        }
        $cbuOrigen = $valCbu['cbu'];

        $empresaId = (int) $filtros['empresa_id'];
        $fechaDesde = (string) $filtros['fecha_desde'];
        $fechaHasta = (string) $filtros['fecha_hasta'];
        $tipoOp = (string) ($filtros['tipo_op'] ?? 'OPP');
        $opDesde = (int) ($filtros['op_desde'] ?? 0);
        $opHasta = (int) ($filtros['op_hasta'] ?? 99999999);
        $tipoAp = (string) ($filtros['tipo_aplicacion'] ?? '');
        $incluirErp = ! empty($filtros['incluir_erp']);
        $incluirAnita = ! empty($filtros['incluir_anita']);

        $filas = [];
        $omitidas = [];
        $errores = [];
        /** @var array<string, true> OP ya cubierta por Anita con CBU en auxpag */
        $opsConCbuAnita = [];

        // Anita primero: si auxpag trae CBU de transferencia, manda sobre ERP (formapago único).
        if ($incluirAnita) {
            $filasAnita = $this->recolectarAnita(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $tipoOp,
                $opDesde,
                $opHasta,
                $tipoAp,
                $errores
            );
            foreach ($filasAnita as $f) {
                $clave = $this->claveOp((string) $f['tipo'], (int) $f['numero'], (string) $f['cbu']);
                if (isset($filas[$clave])) {
                    $filas[$clave]['importe'] = round(
                        (float) $filas[$clave]['importe'] + (float) $f['importe'],
                        2
                    );
                } else {
                    $filas[$clave] = $f;
                }
                if (! empty($f['cbu_desde_auxpag'])) {
                    $opsConCbuAnita[$this->claveOp((string) $f['tipo'], (int) $f['numero'], '')] = true;
                }
            }
        }

        if ($incluirErp) {
            [$filasErp, $omitErp] = $this->recolectarErp(
                $empresaId,
                $fechaDesde,
                $fechaHasta,
                $tipoOp,
                $opDesde,
                $opHasta
            );
            foreach ($filasErp as $f) {
                $claveSinCbu = $this->claveOp((string) $f['tipo'], (int) $f['numero'], '');
                // Si Anita ya aportó la OP con CBU de auxpag, no pisar con ERP.
                if (isset($opsConCbuAnita[$claveSinCbu])) {
                    continue;
                }
                // Si ya hay fila Anita (aunque sea por propago), no duplicar misma OP.
                $yaAnitaMismaOp = false;
                foreach ($filas as $existente) {
                    if ($this->claveOp((string) $existente['tipo'], (int) $existente['numero'], '') === $claveSinCbu
                        && str_starts_with((string) ($existente['origen'] ?? ''), 'Anita')
                    ) {
                        $yaAnitaMismaOp = true;
                        break;
                    }
                }
                if ($yaAnitaMismaOp) {
                    continue;
                }
                $clave = $this->claveOp((string) $f['tipo'], (int) $f['numero'], (string) $f['cbu']);
                $filas[$clave] = $f;
            }
            $omitidas = array_merge($omitidas, $omitErp);
        }

        $filas = array_values($filas);
        usort($filas, static function (array $a, array $b): int {
            $cmp = ((int) $a['numero']) <=> ((int) $b['numero']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcmp((string) $a['cbu'], (string) $b['cbu']);
        });

        $obs = sprintf(
            'Desde OP: %d hasta %d Desde fecha: %s hasta %s',
            $opDesde,
            $opHasta,
            date('d/m/Y', strtotime($fechaDesde)),
            date('d/m/Y', strtotime($fechaHasta))
        );

        $lineasArchivo = array_map(
            static fn (array $f) => ['cbu' => (string) $f['cbu'], 'importe' => (float) $f['importe']],
            $filas
        );

        $fechaSol = InterbankingArchivoPagoFormatoSupport::ymdDesdeFecha(
            (string) ($filtros['fecha_solicitud'] ?? date('Y-m-d'))
        );
        $secuencia = max(1, (int) ($filtros['secuencia'] ?? 1));
        $archivo = InterbankingArchivoPagoFormatoSupport::generarArchivo(
            $cbuOrigen,
            $fechaSol,
            $secuencia,
            $obs,
            $lineasArchivo
        );

        $total = round(array_sum(array_column($filas, 'importe')), 2);

        return [
            'ok' => true,
            'mensaje' => count($filas) > 0
                ? 'Listo: '.count($filas).' transferencia(s) por $'.number_format($total, 2, ',', '.')
                : 'Sin transferencias en el rango (revise CBU proveedor / tipo aplicación Anita).',
            'filas' => $filas,
            'omitidas' => $omitidas,
            'errores' => $errores,
            'total_importe' => $total,
            'cantidad' => count($filas),
            'archivo' => $archivo,
            'observacion' => $obs,
        ];
    }

    /**
     * CBU origen: siempre el de la cuentacaja elegida; si no hay id, el texto (compatibilidad).
     */
    public function cbuOrigenDesdeFiltros(array $filtros): string
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $cuentaId = (int) ($filtros['cuentacaja_id'] ?? 0);
        if ($cuentaId > 0) {
            $cuenta = $this->buscarCuentaOrigen($empresaId, $cuentaId);

            return $cuenta ? CbuSupport::normalizar((string) $cuenta->cbu) : '';
        }

        return CbuSupport::normalizar((string) ($filtros['cbu_origen'] ?? ''));
    }

    /**
     * Cuenta de caja origen: por id, por CBU (preferencia vieja / config Anita) o primera con CBU válido.
     */
    public function resolverCuentaOrigen(int $empresaId, int $cuentacajaId = 0, string $cbuHint = ''): ?Cuentacaja
    {
        if ($cuentacajaId > 0) {
            $porId = $this->buscarCuentaOrigen($empresaId, $cuentacajaId);
            if ($porId !== null) {
                return $porId;
            }
        }

        $hints = [];
        $hintNorm = CbuSupport::normalizar($cbuHint);
        if ($hintNorm !== '') {
            $hints[] = $hintNorm;
        }
        $config = CbuSupport::normalizar((string) config('interbanking.archivo_pago_cbu_origen', ''));
        if ($config !== '' && ! in_array($config, $hints, true)) {
            $hints[] = $config;
        }

        if ($empresaId <= 0) {
            return null;
        }

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereNotNull('cbu')
            ->where('cbu', '!=', '')
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'cbu', 'empresa_id']);

        foreach ($hints as $hint) {
            foreach ($cuentas as $cta) {
                $val = CbuSupport::validarConMensaje((string) $cta->cbu);
                if ($val['ok'] && $val['cbu'] === $hint) {
                    return $cta;
                }
            }
        }

        foreach ($cuentas as $cta) {
            $val = CbuSupport::validarConMensaje((string) $cta->cbu);
            if ($val['ok']) {
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
     * @deprecated Usar resolverCuentaOrigen(); se mantiene por compatibilidad.
     */
    public function sugerirCbuOrigen(int $empresaId = 0): string
    {
        $cta = $this->resolverCuentaOrigen($empresaId);

        return $cta ? CbuSupport::normalizar((string) $cta->cbu) : '';
    }

    /**
     * @return array{0:list<array<string,mixed>>,1:list<array<string,mixed>>}
     */
    private function recolectarErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
    ): array {
        $filas = [];
        $omitidas = [];

        $ops = Pagoproveedor::query()
            ->with(['proveedores', 'pagoproveedor_retenciones'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->whereBetween('numerotransaccion', [$opDesde, $opHasta])
            ->whereNotIn('estado', ['BAJA', 'REVERTIDA'])
            ->where(function ($q) use ($tipoOp) {
                if ($tipoOp !== '' && $tipoOp !== '0') {
                    $q->whereRaw('UPPER(TRIM(tipocomprobante)) = ?', [strtoupper($tipoOp)]);
                } else {
                    $q->whereRaw("UPPER(TRIM(tipocomprobante)) LIKE 'OP%'");
                }
            })
            ->orderBy('numerotransaccion')
            ->get();

        foreach ($ops as $op) {
            $fila = $this->filaDesdePagoproveedor($op);
            if ($fila === null) {
                $omitidas[] = [
                    'origen' => 'ERP',
                    'tipo' => (string) $op->tipocomprobante,
                    'numero' => (int) $op->numerotransaccion,
                    'proveedor' => (string) ($op->proveedores->nombre ?? ''),
                    'motivo' => 'Sin CBU válido en proveedor_formapago / detalle',
                ];
                continue;
            }
            $filas[] = $fila;
        }

        $tipoOppId = IngresoEgresoSolicitudpagoSupport::tipotransaccionCajaIdPorConfig();
        $ieQuery = Caja_Movimiento::query()
            ->with(['proveedores'])
            ->where('empresa_id', $empresaId)
            ->whereNull('pagoproveedor_id')
            ->whereNull('caja_movimiento_revertido_por_id')
            ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
            ->whereBetween('numerotransaccion', [$opDesde, $opHasta]);

        if ($tipoOppId > 0) {
            $ieQuery->where('tipotransaccion_caja_id', $tipoOppId);
        } else {
            $ieQuery->whereHas('tipotransaccioncajas', function ($q) {
                $q->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP'])->whereNull('deleted_at');
            });
        }

        if ($tipoOp !== '' && $tipoOp !== '0' && strtoupper($tipoOp) !== 'OPP') {
            // IE unificado solo emite OPP; otros tipos se omiten
            $ieQuery->whereRaw('1 = 0');
        }

        foreach ($ieQuery->orderBy('numerotransaccion')->get() as $mov) {
            $fila = $this->filaDesdeIeOpp($mov);
            if ($fila === null) {
                $omitidas[] = [
                    'origen' => 'ERP-IE',
                    'tipo' => 'OPP',
                    'numero' => (int) $mov->numerotransaccion,
                    'proveedor' => (string) ($mov->proveedores->nombre ?? ''),
                    'motivo' => 'Sin CBU válido o monto cero',
                ];
                continue;
            }
            $filas[] = $fila;
        }

        return [$filas, $omitidas];
    }

    private function filaDesdePagoproveedor(Pagoproveedor $op): ?array
    {
        $op->loadMissing(['proveedores', 'pagoproveedor_retenciones']);
        $cbu = ProveedorCbuPagoSupport::cbuDesdeDocumento(
            (int) ($op->proveedor_formapago_id ?? 0) ?: null,
            (string) ($op->cbu_pago ?? ''),
            (int) $op->proveedor_id,
            (string) ($op->detalle ?? '')
        );
        $val = CbuSupport::validarConMensaje($cbu);
        if (! $val['ok']) {
            return null;
        }

        $bruto = (float) $op->monto;
        $ret = (float) $op->pagoproveedor_retenciones->sum('monto');
        $neto = round(max(0, $bruto - $ret), 2);
        if ($neto < 0.005) {
            return null;
        }

        $prov = $op->proveedores;
        $codigo = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($prov->codigo ?? ''));

        return [
            'origen' => 'ERP',
            'proveedor_codigo' => $codigo,
            'proveedor_nombre' => (string) ($prov->nombre ?? ''),
            'tipo' => strtoupper(substr(trim((string) $op->tipocomprobante), 0, 3)),
            'sucursal' => (int) $op->sucursal,
            'numero' => (int) $op->numerotransaccion,
            'fecha' => self::fechaYmd($op->fecha),
            'cbu' => $val['cbu'],
            'importe' => $neto,
            'referencia' => $op->etiquetaComprobante(),
        ];
    }

    private function filaDesdeIeOpp(Caja_Movimiento $mov): ?array
    {
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

        return [
            'origen' => 'ERP-IE',
            'proveedor_codigo' => $codigo,
            'proveedor_nombre' => (string) ($prov->nombre ?? ''),
            'tipo' => 'OPP',
            'sucursal' => SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $mov->empresa_id),
            'numero' => (int) $mov->numerotransaccion,
            'fecha' => self::fechaYmd($mov->fecha),
            'cbu' => $val['cbu'],
            'importe' => $monto,
            'referencia' => 'OPP-'.str_pad((string) $mov->numerotransaccion, 8, '0', STR_PAD_LEFT),
            'solicitudpago_id' => (int) ($mov->solicitudpago_id ?? 0) ?: null,
        ];
    }

    /**
     * Caja_Movimiento.fecha es string (sin cast date); Pagoproveedor sí es Carbon.
     * optional($fecha)->format() sobre string devuelve null.
     */
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
     * Tipos de auxpag que no son transferencia bancaria (retenciones / espejo / cheques).
     *
     * @var list<string>
     */
    private const TIPOS_AP_EXCLUIDOS = ['CHP', 'FIN', 'FIS', 'RTP', 'RGP', 'IBP', 'GAN', 'IVA', 'SUSS', 'SIR'];

    /**
     * @param  list<string>  $errores
     * @return list<array<string,mixed>>
     */
    private function recolectarAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
        string $tipoAp,
        array &$errores,
    ): array {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
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
            return [];
        }

        $auxpag = $this->anitaReader->listarAuxpagPeriodo(
            $empresaAnita,
            $desdeYmd,
            $hastaYmd,
            $errores
        );

        // Match pago↔auxpag por empresa + tipo + nro OP (no por sucursal:
        // TMB suele ir con axp_sucursal=0 y pago.pag_sucursal = sucursal operativa).
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
        $mapaCbu = $this->anitaReader->mapaCbuPropago($codigos, $errores);
        $mapaNombres = $this->anitaReader->mapaNombresPromae($codigos, $errores);

        $filas = [];
        foreach ($pagos as $pag) {
            $tipo = strtoupper(substr(trim((string) ($pag->pag_tipo ?? '')), 0, 3));
            if (! str_starts_with($tipo, 'OP')) {
                continue;
            }
            $rec = (int) ($pag->pag_rec ?? 0);
            $empPag = (int) ($pag->pag_empresa ?? 0);
            if ($empPag <= 0) {
                $empPag = $empresaAnita;
            }
            $suc = (int) ($pag->pag_sucursal ?? 0);
            if ($rec < $opDesde || $rec > $opHasta) {
                continue;
            }
            $lineas = $this->filtrarLineasAuxpagTransferencia(
                $auxPorOp[$empPag.'|'.$tipo.'|'.$rec] ?? [],
                $tipoAp
            );
            if ($lineas === []) {
                continue;
            }

            $proCod = InterbankingArchivoPagoAnitaReader::padProveedor((string) ($pag->pag_pro ?? ''));
            $cbuPropago = $mapaCbu[$proCod] ?? '';
            $porCbu = [];

            foreach ($lineas as $axp) {
                $cbuAux = CbuSupport::normalizar((string) ($axp->axp_cbu ?? ''));
                $desdeAuxpag = $cbuAux !== '';
                $cbu = $desdeAuxpag ? $cbuAux : CbuSupport::normalizar($cbuPropago);
                $val = CbuSupport::validarConMensaje($cbu);
                if (! $val['ok']) {
                    continue;
                }
                $imp = round(abs((float) ($axp->axp_monto_ap ?? 0)), 2);
                if ($imp < 0.005) {
                    continue;
                }
                $cbuOk = $val['cbu'];
                if (! isset($porCbu[$cbuOk])) {
                    $porCbu[$cbuOk] = ['importe' => 0.0, 'cbu_desde_auxpag' => false];
                }
                $porCbu[$cbuOk]['importe'] = round($porCbu[$cbuOk]['importe'] + $imp, 2);
                if ($desdeAuxpag) {
                    $porCbu[$cbuOk]['cbu_desde_auxpag'] = true;
                }
            }

            $fechaPag = (string) ($pag->pag_fecha ?? '');
            if (strlen($fechaPag) === 8 && ctype_digit($fechaPag)) {
                $fechaPag = substr($fechaPag, 0, 4).'-'.substr($fechaPag, 4, 2).'-'.substr($fechaPag, 6, 2);
            }

            foreach ($porCbu as $cbu => $info) {
                $filas[] = [
                    'origen' => 'Anita',
                    'proveedor_codigo' => $proCod,
                    'proveedor_nombre' => $mapaNombres[$proCod] ?? $proCod,
                    'tipo' => $tipo,
                    'sucursal' => $suc,
                    'numero' => $rec,
                    'fecha' => $fechaPag,
                    'cbu' => $cbu,
                    'importe' => $info['importe'],
                    'cbu_desde_auxpag' => $info['cbu_desde_auxpag'],
                    'referencia' => sprintf('%s-%04d-%08d', $tipo, $suc, $rec),
                ];
            }
        }

        return $filas;
    }

    /**
     * Como p-pagoxbanco con tipo aplicación: solo líneas de transferencia bancaria.
     * Sin filtro: prioriza las que traen CBU; si no hay, tipos bancarios; nunca FIN/retenciones.
     *
     * @param  list<object>  $lineas
     * @return list<object>
     */
    private function filtrarLineasAuxpagTransferencia(array $lineas, string $tipoAp): array
    {
        if ($tipoAp !== '') {
            return array_values(array_filter(
                $lineas,
                static function ($axp) use ($tipoAp) {
                    $t = strtoupper(substr(trim((string) ($axp->axp_tipo_ap ?? '')), 0, 3));

                    return $t === $tipoAp;
                }
            ));
        }

        $conCbu = [];
        $bancarias = [];
        foreach ($lineas as $axp) {
            $t = strtoupper(substr(trim((string) ($axp->axp_tipo_ap ?? '')), 0, 3));
            if ($t === '' || in_array($t, self::TIPOS_AP_EXCLUIDOS, true)) {
                continue;
            }
            $cbu = trim((string) ($axp->axp_cbu ?? ''));
            if ($cbu !== '' && $cbu !== ' ') {
                $conCbu[] = $axp;
            } else {
                $bancarias[] = $axp;
            }
        }

        return $conCbu !== [] ? $conCbu : $bancarias;
    }

    private function claveOp(string $tipo, int $numero, string $cbu): string
    {
        return strtoupper(substr(trim($tipo), 0, 3)).'|'.$numero.'|'.$cbu;
    }

    /**
     * @return array{
     *   ok:bool,mensaje:string,filas:list,omitidas:list,errores:list,
     *   total_importe:float,cantidad:int,archivo:string,observacion:string
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
            'archivo' => '',
            'observacion' => '',
        ];
    }
}
