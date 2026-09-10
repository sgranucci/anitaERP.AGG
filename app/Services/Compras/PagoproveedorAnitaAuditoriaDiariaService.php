<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Mail\Compras\PagoproveedorAnitaAuditoriaDiaria;
use App\Models\Caja\Cheque;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Seguridad\Usuario;
use App\Support\Caja\ChequePropioCpromaeAnitaMapper;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Compras\AnitaSync\Pagoproveedor\PagoproveedorAnitaRetencionNumeracionSupport;
use App\Support\Compras\PagoproveedorAnitaAuditoriaCompareSupport as Compare;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditoría diaria OP ERP → Anita (pago, tesmov, auxpag, cpromae, ctamov, retenciones, asiento).
 * Por default solo diagnostica; --reparar reescribe tesorería Anita.
 */
final class PagoproveedorAnitaAuditoriaDiariaService
{
    public function __construct(
        private readonly PagoproveedorService $pagoproveedorService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
        ?bool $autoReparar = null,
        ?int $nro = null,
    ): array {
        $config = config('pagoproveedor.auditoria_diaria', []);
        $autoReparar ??= filter_var($config['auto_reparar'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $desde = $fechaDesde ?: Carbon::today()->subDays(max(1, (int) ($config['ventana_dias'] ?? 7)) - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();

        $this->autenticarUsuarioSistema($config);

        $informe = [
            'fecha_calendario' => $nro ? ('OP '.$nro) : ($desde.' → '.$hasta),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'nro' => $nro,
            'auto_reparar' => $autoReparar,
            'total' => 0,
            'ok' => 0,
            'reparadas' => 0,
            'discrepancias' => [],
            'filas_reparadas' => [],
            'errores' => [],
            'filas' => [],
            'requiere_alerta' => false,
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
        ];

        $q = Pagoproveedor::query()
            ->with([
                'proveedores',
                'empresas',
                'cheques.cuentacajas',
                'cheques.chequeras',
                'cheques.proveedores',
                'caja_movimientos.cheques.cuentacajas',
                'caja_movimientos.cheques.chequeras',
                'caja_movimientos.caja_movimiento_cuentacajas',
                'asientos.asiento_movimientos',
                'pagoproveedor_retenciones',
            ])
            ->whereIn('estado', ['CONFIRMADA', 'PAGADA', 'CONCILIADA']);

        if ($nro !== null && $nro > 0) {
            $q->where('numerotransaccion', $nro);
        } else {
            $q->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $hasta);
        }

        $pagos = $q->orderBy('id')->get();
        $informe['total'] = $pagos->count();

        foreach ($pagos as $pago) {
            try {
                $fila = $this->auditarUno($pago, $autoReparar);
            } catch (Throwable $e) {
                $informe['errores'][] = [
                    'id' => (int) $pago->id,
                    'etiqueta' => $this->etiqueta($pago),
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('PagoproveedorAnitaAuditoria: error', [
                    'id' => (int) $pago->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $informe['filas'][] = $fila;
            if (($fila['estado'] ?? '') === 'ok') {
                $informe['ok']++;
            } elseif (($fila['estado'] ?? '') === 'reparada') {
                $informe['reparadas']++;
                $informe['filas_reparadas'][] = $fila;
            } else {
                $informe['discrepancias'][] = $fila;
            }
        }

        $informe['requiere_alerta'] = $informe['discrepancias'] !== []
            || $informe['errores'] !== [];

        if ($enviarMail) {
            $this->enviarMailSiCorresponde($informe, $config);
        }

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarUno(Pagoproveedor $pago, bool $autoReparar): array
    {
        $fila = [
            'id' => (int) $pago->id,
            'etiqueta' => $this->etiqueta($pago),
            'nro' => (int) $pago->numerotransaccion,
            'problemas' => [],
            'acciones' => [],
            'estado' => 'ok',
            'diagnostico' => [],
        ];

        $fila['problemas'] = $this->diagnosticar($pago);
        $fila['diagnostico'] = ['problemas' => $fila['problemas']];

        if ($fila['problemas'] === []) {
            return $fila;
        }

        $fila['estado'] = 'discrepancia';

        if (! $autoReparar) {
            return $fila;
        }

        $anita = array_filter(
            $fila['problemas'],
            fn ($p) => ! str_starts_with((string) $p, 'Asiento')
                && ! str_contains(strtolower((string) $p), 'ctamov')
        );
        if ($anita === []) {
            return $fila;
        }

        try {
            $this->pagoproveedorService->sincronizarAnitaTesoreria($pago->fresh(), true);
            $fila['acciones'][] = 'Resincronizó tesorería Anita';
            $pago->refresh()->load([
                'proveedores',
                'cheques.cuentacajas',
                'cheques.chequeras',
                'cheques.proveedores',
                'caja_movimientos.cheques.cuentacajas',
                'caja_movimientos.cheques.chequeras',
                'caja_movimientos.caja_movimiento_cuentacajas',
                'asientos.asiento_movimientos',
                'pagoproveedor_retenciones',
            ]);
            $fila['problemas'] = $this->diagnosticar($pago);
            $fila['diagnostico'] = ['problemas' => $fila['problemas']];
            if ($fila['problemas'] === []) {
                $fila['estado'] = 'reparada';
            }
        } catch (Throwable $e) {
            $fila['acciones'][] = 'Reparación falló: '.$e->getMessage();
        }

        return $fila;
    }

    /**
     * @return list<string>
     */
    private function diagnosticar(Pagoproveedor $pago): array
    {
        $problemas = [];
        $nro = (int) $pago->numerotransaccion;
        if ($nro <= 0) {
            return ['Sin número de transacción'];
        }

        $tipo = strtoupper(substr(trim((string) ($pago->tipocomprobante ?: 'OPP')), 0, 3)) ?: 'OPP';
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $pago->empresa_id);
        if ($empresaAnita <= 0) {
            $empresaAnita = (int) $pago->empresa_id;
        }

        $this->auditarAsientoErp($pago, $problemas);
        $this->auditarCtamovAnita($pago, $tipo, $nro, $empresaAnita, $problemas);

        if (! IngresoEgresoAnitaTesmovSupport::estaHabilitada()) {
            $problemas[] = 'Escritura tesorería Anita deshabilitada (no se verifica pago/tesmov)';

            return $problemas;
        }

        $sistema = IngresoEgresoAnitaTesmovSupport::sistema();
        $whereOp = ' WHERE pag_tipo = '.$this->escSql($tipo)
            .' AND pag_rec = '.$nro
            .' AND pag_empresa = '.$empresaAnita;
        $pagosAnita = $this->listar($sistema, 'pago', 'pag_tipo,pag_rec,pag_empresa,pag_trec', $whereOp);
        if ($pagosAnita['error'] !== null) {
            $problemas[] = 'Lectura pago Anita: '.$pagosAnita['error'];
        } elseif ($pagosAnita['filas'] === []) {
            $problemas[] = 'Falta pago en Anita';
        }

        $whereTes = ' WHERE tesv_tipo = '.$this->escSql($tipo)
            .' AND tesv_nro = '.$nro
            .' AND tesv_empresa = '.$empresaAnita;
        $tesmov = $this->listar($sistema, 'tesmov', 'tesv_tipo,tesv_nro,tesv_empresa,tesv_importe', $whereTes);
        if ($tesmov['error'] !== null) {
            $problemas[] = 'Lectura tesmov Anita: '.$tesmov['error'];
        }

        $lineasCaja = $pago->caja_movimientos
            ->flatMap(fn ($m) => $m->caja_movimiento_cuentacajas)
            ->count();
        if ($lineasCaja > 0 && $tesmov['error'] === null && $tesmov['filas'] === []) {
            $problemas[] = 'Falta tesmov OPP en Anita';
        }

        $whereAux = ' WHERE axp_tipo = '.$this->escSql($tipo)
            .' AND axp_rec = '.$nro
            .' AND axp_empresa = '.$empresaAnita;
        $auxpag = $this->listar($sistema, 'auxpag', 'axp_tipo,axp_rec,axp_tipo_ap,axp_empresa', $whereAux);
        if ($auxpag['error'] !== null) {
            $problemas[] = 'Lectura auxpag Anita: '.$auxpag['error'];
        } elseif ($auxpag['filas'] === []) {
            $problemas[] = 'Falta auxpag en Anita';
        }

        foreach ($this->chequesEmitidos($pago) as $cheque) {
            $this->auditarChequePropio($pago, $cheque, $tipo, $nro, $empresaAnita, $sistema, $problemas);
        }

        $this->auditarRetenciones($pago, $tipo, $nro, $empresaAnita, $auxpag['filas'], $problemas);

        return $problemas;
    }

    /**
     * @param  list<string>  $problemas
     */
    private function auditarAsientoErp(Pagoproveedor $pago, array &$problemas): void
    {
        $asiento = $pago->asientos;
        if ($asiento === null && (int) ($pago->asiento_id ?? 0) > 0) {
            $asiento = \App\Models\Contable\Asiento::query()
                ->with('asiento_movimientos')
                ->find((int) $pago->asiento_id);
        }
        if ((int) ($pago->asiento_id ?? 0) <= 0 || $asiento === null) {
            $problemas[] = 'Asiento ERP ausente';

            return;
        }

        if (! $asiento->relationLoaded('asiento_movimientos')) {
            $asiento->load('asiento_movimientos');
        }

        $totales = Compare::balanceDesdeAsientoMovimientos($asiento->asiento_movimientos ?? []);
        if ($totales['lineas_con_importe'] < 2) {
            $problemas[] = 'Asiento ERP sin movimientos con importe';
        } elseif (! $totales['balanceado']) {
            $problemas[] = 'Asiento ERP desbalanceado Debe '.$totales['total_debe']
                .' vs Haber '.$totales['total_haber'];
        }
    }

    /**
     * @param  list<string>  $problemas
     */
    private function auditarCtamovAnita(
        Pagoproveedor $pago,
        string $tipo,
        int $nro,
        int $empresaAnita,
        array &$problemas,
    ): void {
        $asiento = $pago->asientos;
        if ($asiento === null && (int) ($pago->asiento_id ?? 0) > 0) {
            $asiento = \App\Models\Contable\Asiento::query()
                ->with('asiento_movimientos')
                ->find((int) $pago->asiento_id);
        }
        if ($asiento !== null && ! $asiento->relationLoaded('asiento_movimientos')) {
            $asiento->load('asiento_movimientos');
        }

        $nroAsiento = (int) ($asiento?->numeroasiento ?? 0);
        $erpTotales = $asiento !== null
            ? Compare::balanceDesdeAsientoMovimientos($asiento->asiento_movimientos ?? [])
            : ['total_debe' => 0.0, 'total_haber' => 0.0, 'lineas_con_importe' => 0, 'balanceado' => true];

        $sistema = 'contab';
        $campos = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_importe,ctav_tipo,ctav_nro';

        $filas = [];
        $error = null;
        if ($nroAsiento > 0) {
            $porAsiento = $this->listar(
                $sistema,
                'ctamov',
                $campos,
                ' WHERE ctav_empresa = '.$empresaAnita
                    .' AND ctav_nro_asiento = '.$nroAsiento
            );
            $error = $porAsiento['error'];
            $filas = $porAsiento['filas'];
        } elseif ($asiento !== null) {
            $problemas[] = 'Asiento ERP sin numeroasiento para verificar ctamov';
        }

        if ($error !== null) {
            $problemas[] = 'Lectura ctamov Anita: '.$error;

            return;
        }

        if ($filas === []) {
            $letra = (string) config('caja.ingresoegreso_anita_tesmov_letra', ' ');
            if ($letra === '') {
                $letra = ' ';
            }
            $sucursalCfg = config('caja.ingresoegreso_anita_tesmov_sucursal');
            $sucursal = $sucursalCfg === null || $sucursalCfg === ''
                ? $empresaAnita
                : (int) $sucursalCfg;
            $porComprobante = $this->listar(
                $sistema,
                'ctamov',
                $campos,
                ' WHERE ctav_empresa = '.$empresaAnita
                    .' AND ctav_tipo = '.$this->escSql($tipo)
                    .' AND ctav_letra = '.$this->escSql($letra)
                    .' AND ctav_sucursal = '.$sucursal
                    .' AND ctav_nro = '.$nro
            );
            if ($porComprobante['error'] !== null) {
                $problemas[] = 'Lectura ctamov Anita: '.$porComprobante['error'];

                return;
            }
            if ($porComprobante['filas'] !== []) {
                $filas = $porComprobante['filas'];
                if ($nroAsiento > 0) {
                    $problemas[] = 'ctamov de la OP no está bajo asiento '.$nroAsiento;
                }
            }
        }

        if ($filas === []) {
            if ($nroAsiento > 0 || $erpTotales['lineas_con_importe'] > 0) {
                $problemas[] = $nroAsiento > 0
                    ? 'Falta ctamov del asiento '.$nroAsiento
                    : 'Falta ctamov de la OP';
            }

            return;
        }

        if ($nroAsiento > 0) {
            foreach ($filas as $fila) {
                $row = is_array($fila) ? $fila : get_object_vars($fila);
                $tipoFila = strtoupper(substr(trim((string) ($row['ctav_tipo'] ?? '')), 0, 3));
                $nroFila = (int) ($row['ctav_nro'] ?? 0);
                if ($tipoFila !== '' && $tipoFila !== $tipo) {
                    $problemas[] = 'ctamov tipo '.$tipoFila.' ≠ '.$tipo;
                    break;
                }
                if ($nroFila > 0 && $nroFila !== $nro) {
                    $problemas[] = 'ctamov nro '.$nroFila.' ≠ OP '.$nro;
                    break;
                }
            }
        }

        foreach (Compare::discrepanciasCtamovVsErp($erpTotales, Compare::totalesDesdeCtamov($filas)) as $p) {
            $problemas[] = $p;
        }
    }

    /**
     * @param  list<string>  $problemas
     */
    private function auditarChequePropio(
        Pagoproveedor $pago,
        Cheque $cheque,
        string $tipo,
        int $nro,
        int $empresaAnita,
        string $sistema,
        array &$problemas,
    ): void {
        $cuenta = $cheque->cuentacajas;
        $codigoCuenta = $cuenta ? trim((string) $cuenta->codigo) : '';
        $nroCheque = (int) preg_replace('/\D/', '', (string) $cheque->numerocheque);
        $etiqueta = 'cheque '.$nroCheque;
        if ($codigoCuenta === '' || $nroCheque <= 0) {
            $problemas[] = $etiqueta.': sin cuenta o número';

            return;
        }

        $cuentaPad = str_pad(ltrim($codigoCuenta, '0') === '' ? '0' : ltrim($codigoCuenta, '0'), 8, '0', STR_PAD_LEFT);
        $esperado = ChequePropioCpromaeAnitaMapper::mapear([
            'cuenta' => $codigoCuenta,
            'nro' => $nroCheque,
            'fecha_emision' => (string) ($cheque->fechaemision ?: $pago->fecha),
            'fecha_pago' => (string) ($cheque->fechapago ?: $cheque->fechaemision ?: $pago->fecha),
            'importe' => round(abs((float) $cheque->monto), 2),
            'proveedor' => (string) ($cheque->proveedores?->codigo ?? $pago->proveedores?->codigo ?? '0'),
            'entregado' => (string) ($cheque->entregado ?: ($pago->proveedores?->nombre ?? '')),
            'anombrede' => (string) ($cheque->anombrede ?? ''),
            'nro_op' => $nro,
            'moneda_id' => (int) ($cheque->moneda_id ?: 1),
            'cotizacion' => (float) ($cheque->cotizacion ?? 0),
            'empresa' => $empresaAnita,
            'chequera_codigo' => (string) ($cheque->chequeras->codigo ?? '0'),
            'chequera_tipo' => (string) ($cheque->chequeras->tipochequera ?? 'F'),
            'para_dep' => (string) ($cheque->para_dep ?? ''),
            'negociable' => (string) ($cheque->negociable ?? ''),
            'nro_echeq' => (string) ($cheque->nro_echeq ?? ''),
            'fecha_entrega' => (string) ($cheque->fecha_entrega ?? ''),
            'estado_erp' => (string) ($cheque->estado ?? ''),
        ]);

        $whereCpro = ' WHERE cpro_cuenta = '.$this->escSql($cuentaPad)
            .' AND cpro_nro_cheque = '.$nroCheque;
        $cpro = $this->listar(
            $sistema,
            'cpromae',
            'cpro_cuenta,cpro_nro_cheque,cpro_fecha_cheque,cpro_fecha_emision,cpro_importe,'
            .'cpro_nro_op,cpro_cotizacion,cpro_para_dep,cpro_negociable,cpro_estado,cpro_nro_e_cheq',
            $whereCpro
        );
        if ($cpro['error'] !== null) {
            $problemas[] = $etiqueta.': lectura cpromae '.$cpro['error'];
        } elseif ($cpro['filas'] === []) {
            $problemas[] = $etiqueta.': falta cpromae';
        } else {
            foreach (Compare::discrepanciasCpromae($esperado, $cpro['filas'][0]) as $p) {
                $problemas[] = $etiqueta.': '.$p;
            }
        }

        $whereChp = " WHERE tesv_tipo = 'CHP' AND tesv_nro = ".$nroCheque
            .' AND tesv_cuenta = '.$this->escSql($cuentaPad)
            .' AND tesv_empresa = '.$empresaAnita;
        $tesChp = $this->listar($sistema, 'tesmov', 'tesv_tipo,tesv_nro,tesv_importe', $whereChp);
        if ($tesChp['error'] !== null) {
            $problemas[] = $etiqueta.': lectura tesmov CHP '.$tesChp['error'];
        } elseif ($tesChp['filas'] === []) {
            $problemas[] = $etiqueta.': falta tesmov CHP';
        }

        $whereAuxChp = ' WHERE axp_tipo = '.$this->escSql($tipo)
            .' AND axp_rec = '.$nro
            .' AND axp_tipo_ap = '.$this->escSql('CHP')
            .' AND axp_nro = '.$nroCheque
            .' AND axp_empresa = '.$empresaAnita;
        $auxChp = $this->listar(
            $sistema,
            'auxpag',
            'axp_tipo,axp_nro,axp_tipo_ap,axp_sucursal,axp_sucursal_cob,axp_empresa',
            $whereAuxChp
        );
        if ($auxChp['error'] !== null) {
            $problemas[] = $etiqueta.': lectura auxpag CHP '.$auxChp['error'];
        } elseif ($auxChp['filas'] === []) {
            $problemas[] = $etiqueta.': falta auxpag CHP';
        } else {
            foreach (\App\Support\Caja\ChequePropioAuxpagAnitaMapper::discrepancias(
                $nroCheque,
                $empresaAnita,
                $auxChp['filas'][0]
            ) as $p) {
                $problemas[] = $etiqueta.': '.$p;
            }
        }
    }

    /**
     * @param  list<object>  $auxpagFilas
     * @param  list<string>  $problemas
     */
    private function auditarRetenciones(
        Pagoproveedor $pago,
        string $tipo,
        int $nro,
        int $empresaAnita,
        array $auxpagFilas,
        array &$problemas,
    ): void {
        $sistema = (string) config('pagoproveedor.anita_sistema_retenciones', 'compras');
        $map = [
            Pagoproveedor_Retencion::TIPO_GANANCIAS => ['retmov', 'retv'],
            Pagoproveedor_Retencion::TIPO_IVA => ['retimov', 'retiv'],
            Pagoproveedor_Retencion::TIPO_SUSS => ['retsmov', 'retsv'],
            Pagoproveedor_Retencion::TIPO_IIBB => ['retibrmov', 'retibr'],
        ];

        $tiposApAuxpag = [];
        foreach ($auxpagFilas as $fila) {
            $t = strtoupper(substr(trim((string) ($fila->axp_tipo_ap ?? '')), 0, 3));
            if ($t !== '') {
                $tiposApAuxpag[$t] = true;
            }
        }

        $tiposApEsperados = [];
        foreach ($pago->pagoproveedor_retenciones as $ret) {
            if ((float) ($ret->importe ?? 0) <= 0.009) {
                continue;
            }
            $par = $map[(string) $ret->tiporetencion] ?? null;
            if ($par === null) {
                continue;
            }
            [$tabla, $pref] = $par;
            $where = ' WHERE '.$pref.'_tipo = '.$this->escSql($tipo)
                .' AND '.$pref.'_nro = '.$nro
                .' AND '.$pref.'_empresa = '.$empresaAnita;
            $lista = $this->listar($sistema, $tabla, $pref.'_tipo,'.$pref.'_nro,'.$pref.'_empresa', $where);
            if ($lista['error'] !== null) {
                $problemas[] = 'Lectura '.$tabla.': '.$lista['error'];
            } elseif ($lista['filas'] === []) {
                $problemas[] = 'Falta '.$tabla.' de retención '.$ret->tiporetencion;
            }

            $tipoAp = PagoproveedorAnitaRetencionNumeracionSupport::tipoApAuxpag((string) $ret->tiporetencion);
            if ($tipoAp !== null) {
                $tiposApEsperados[$tipoAp] = true;
            }
        }

        foreach (array_keys($tiposApEsperados) as $tipoAp) {
            if (! isset($tiposApAuxpag[$tipoAp])) {
                $problemas[] = 'Falta auxpag '.$tipoAp;
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, Cheque>
     */
    private function chequesEmitidos(Pagoproveedor $pago)
    {
        $propios = $pago->cheques->where('origen', 'E')->values();
        if ($propios->isNotEmpty()) {
            return $propios;
        }

        return $pago->caja_movimientos
            ->flatMap(fn ($m) => $m->cheques)
            ->where('origen', 'E')
            ->unique('id')
            ->values();
    }

    /**
     * @return array{filas: list<object>, error: ?string}
     */
    private function listar(string $sistema, string $tabla, string $campos, string $whereArmado): array
    {
        $raw = (new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);

        return [
            'filas' => $parsed['filas'],
            'error' => $parsed['error_lectura'],
        ];
    }

    private function escSql(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }

    private function etiqueta(Pagoproveedor $pago): string
    {
        $prov = (string) ($pago->proveedores->nombre ?? '');

        return trim($pago->etiquetaComprobante().($prov !== '' ? ' · '.$prov : ''));
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     */
    private function enviarMailSiCorresponde(array &$informe, array $config): void
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return;
        }

        $debe = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || (
                filter_var($config['mail_si_reparo'] ?? true, FILTER_VALIDATE_BOOLEAN)
                && (int) ($informe['reparadas'] ?? 0) > 0
            );

        if (! $debe) {
            return;
        }

        try {
            Mail::to($destino)->send(new PagoproveedorAnitaAuditoriaDiaria($informe));
            $informe['mail_enviado'] = true;
            $informe['mail_destino'] = $destino;
        } catch (Throwable $e) {
            $informe['mail_error'] = $e->getMessage();
            Log::error('PagoproveedorAnitaAuditoria: mail falló', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0 || Auth::id()) {
            return;
        }

        $usuario = Usuario::query()->find($usuarioId);
        if ($usuario !== null) {
            Auth::login($usuario);
        }
    }
}
