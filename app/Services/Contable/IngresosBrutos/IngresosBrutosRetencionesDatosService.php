<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\ApiAnita;
use App\Models\Caja\Caja_Movimiento;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Asiento;
use App\Models\Contable\Asiento_Movimiento;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Configuracion\Provincia;
use App\Support\Compras\Retencion\AnitaRetencionEsquemaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosRetencionSignoSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreErpComplementoSupport;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones IIBB — Anita retibrmov (opción 7) + ERP pagoproveedor_retencion tipo B.
 */
final class IngresosBrutosRetencionesDatosService
{
    private ?string $ultimaAdvertencia = null;

    /** @var array<string, true>|null */
    private ?array $notasCreditoCompra = null;

    public function __construct(
        private readonly SicoreProveedorErpSupport $proveedorSupport = new SicoreProveedorErpSupport(),
    ) {
    }

    public function ultimaAdvertencia(): ?string
    {
        return $this->ultimaAdvertencia;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function generar(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $this->ultimaAdvertencia = null;

        $anita = $this->desdeRetibrmovAnita($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        $erp = $this->desdePagoproveedorErp($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);

        // Anita puede traer la OPP original y faltar la devolución solo-ERP (DEV RET).
        // Se mergea como en SICORE: Anita primero, ERP solo lo que no está.
        if ($anita === []) {
            $base = $erp;
        } elseif ($erp === []) {
            $base = $anita;
        } else {
            $base = array_merge(
                $anita,
                SicoreErpComplementoSupport::soloNuevos($erp, $anita),
            );
        }

        // OPP/CHP de caja (I/E) con Debe en la cuenta IIBB y proveedor: devolución
        // que no generó retibrmov ni pagoproveedor_retencion (ej. DEVOLUCION DE IIBB).
        $devs = $this->desdeDevolucionesErpAsiento(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $config,
            $provincia,
            $base,
        );

        if ($devs === []) {
            return $base;
        }

        return array_merge($base, SicoreErpComplementoSupport::soloNuevos($devs, $base));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeRetibrmovAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $tieneProvincia = AnitaRetencionEsquemaSupport::retibrmovTieneProvincia();
        $codigosProv = IngresosBrutosProvinciaAnitaSupport::codigosAnita($provincia);
        if ($tieneProvincia && $codigosProv === []) {
            $this->ultimaAdvertencia = 'La provincia seleccionada no tiene códigos Anita (codigoexterno/jurisdicción) para filtrar retibrmov.';

            return [];
        }
        if (! $tieneProvincia && ! IngresosBrutosProvinciaAnitaSupport::esCaba($provincia)) {
            $this->ultimaAdvertencia = 'En esta instalación retibrmov no distingue provincia. Solo se listan las retenciones de CABA.';

            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $provIn = implode(',', array_map('intval', $codigosProv));
        $tieneEmpresa = AnitaRetencionEsquemaSupport::movimientosTienenColumnaEmpresa();

        // Columnas reales Informix (retibrmov.sql): retibr_nro_ret / retibr_porc_ret
        // (el C usa alias retibr_nro_retencion / retibr_porc_retencion vía .def).
        // Alias largos hacen que el bridge responda [] sin mensaje de error.
        // Ferli no tiene retibr_empresa ni retibr_provincia.
        $campos = [
            'retibr_proveedor', 'retibr_tipo', 'retibr_letra', 'retibr_sucursal', 'retibr_nro',
            'retibr_fecha', 'retibr_sujeto', 'retibr_retencion', 'retibr_porc_ret',
            'retibr_nro_ret',
        ];
        $where = ' WHERE retibr_fecha >= '.$desdeAnita
            .' AND retibr_fecha <= '.$hastaAnita
            .' AND retibr_retencion <> 0';
        if ($tieneProvincia) {
            $campos[] = 'retibr_provincia';
            $where .= ' AND retibr_provincia IN ('.$provIn.')';
        }
        if ($tieneEmpresa) {
            $campos[] = 'retibr_empresa';
            $where .= ' AND retibr_empresa = '.$empresaAnita;
        }

        $payload = [
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retibrmov',
            'campos' => implode(', ', $campos),
            'whereArmado' => $where,
            'orderBy' => 'retibr_fecha, retibr_nro_ret, retibr_proveedor',
        ];

        $parsed = $this->leerRetibrmovConReintento($payload, $empresaId, $fechaDesde, $fechaHasta);
        $filas = $parsed['filas'];
        $errorLectura = $parsed['error_lectura'];

        if ($errorLectura !== null) {
            $this->ultimaAdvertencia = 'No se pudo leer retenciones IIBB desde Anita (retibrmov): '.$errorLectura
                .' Reintente la consulta. Si persiste, revise el bridge Anita.';
            Log::warning('IIBB retibrmov: error de lectura Anita', [
                'empresa_id' => $empresaId,
                'empresa_anita' => $empresaAnita,
                'desde' => $fechaDesde,
                'hasta' => $fechaHasta,
                'error' => $errorLectura,
            ]);

            return [];
        }

        if ($filas === []) {
            return [];
        }

        $agrupados = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $nroRet = (int) ($fila['retibr_nro_ret'] ?? 0);
            $clave = $nroRet.'|'.trim((string) ($fila['retibr_proveedor'] ?? ''));
            if (! isset($agrupados[$clave])) {
                $agrupados[$clave] = [
                    'proveedor' => trim((string) ($fila['retibr_proveedor'] ?? '')),
                    'fecha' => (int) ($fila['retibr_fecha'] ?? 0),
                    'nro_ret' => $nroRet,
                    // retibr_nro = comprobante/OP asociado (aparece en mayor como #N).
                    'nro_comp' => (int) ($fila['retibr_nro'] ?? 0),
                    'sujeto' => 0.0,
                    'retencion' => 0.0,
                    'alicuota' => (float) ($fila['retibr_porc_ret'] ?? 0),
                ];
            }
            // NC y anulación (AOP) se graban en positivo con el mismo nro de certificado
            // que la OPP. Hay que restarlas: si se suman, el certificado queda duplicado.
            // La NC aplicada (CIB, NCP, …) ya viene negativa en retibrmov y se deja así.
            $tipoMov = strtoupper(trim((string) ($fila['retibr_tipo'] ?? '')));
            $tipoComp = strtoupper(trim((string) ($fila['retibr_tipo_comp'] ?? '')));
            $sujeto = (float) ($fila['retibr_sujeto'] ?? 0);
            $retencion = (float) ($fila['retibr_retencion'] ?? 0);
            if (IngresosBrutosRetencionSignoSupport::movimientoResta($tipoMov)
                || ($retencion > 0 && $this->esNotaCreditoCompra($tipoComp))) {
                $sujeto = -abs($sujeto);
                $retencion = -abs($retencion);
            }
            $agrupados[$clave]['sujeto'] += $sujeto;
            $agrupados[$clave]['retencion'] += $retencion;
            $agrupados[$clave]['alicuota'] = (float) ($fila['retibr_porc_ret'] ?? $agrupados[$clave]['alicuota']);
        }

        $this->proveedorSupport->precargar(array_column($agrupados, 'proveedor'));

        $out = [];
        foreach ($agrupados as $grupo) {
            if (abs($grupo['sujeto']) < 0.001 || abs($grupo['retencion']) < 0.001) {
                continue;
            }
            $proveedor = $this->proveedorSupport->resolverDesdeFila(
                ['retibr_proveedor' => $grupo['proveedor']],
                'retibr_proveedor',
                '',
                '',
            );
            $fechaIso = IngresosBrutosFormatoArbaSupport::fechaIsoDesdeAnita((int) $grupo['fecha']);

            $out[] = [
                'origen' => 'retibrmov',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'retenciones',
                'fecha_retencion' => $fechaIso,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($grupo['nro_comp'] ?: $grupo['nro_ret']),
                'nro_cert' => (int) $grupo['nro_ret'],
                'sucursal' => 1,
                'base_calculo' => round((float) $grupo['sujeto'], 2),
                'importe' => round((float) $grupo['retencion'], 2),
                'alicuota' => round((float) $grupo['alicuota'], 2),
                'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($proveedor['cuit'] ?? '')),
                'codigo_proveedor' => (string) ($proveedor['codigo_proveedor'] ?? $grupo['proveedor']),
                'razon_social' => substr((string) ($proveedor['nombre'] ?? ''), 0, 30),
                'referencia' => 'Ret.IIBB '.$grupo['proveedor'],
            ];
        }

        if ($filas !== [] && $out === []) {
            $this->ultimaAdvertencia = 'Anita devolvió filas de retibrmov pero quedaron en cero al agrupar (sujeto/retención). Revise columnas retibr_nro_ret / retibr_sujeto / retibr_retencion.';
            Log::warning('IIBB retibrmov: filas Anita descartadas al agrupar', [
                'empresa_id' => $empresaId,
                'filas_anita' => count($filas),
            ]);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{filas: list<object>, error_lectura: ?string}
     */
    private function leerRetibrmovConReintento(
        array $payload,
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
    ): array {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall($payload));

        // Fallo puntual del bridge (timeout / body vacío / pico): un reintento suele recuperar.
        if ($parsed['filas'] === [] || $parsed['error_lectura'] !== null) {
            usleep(250_000);
            $parsed2 = ApiAnita::parsearRespuestaLista($api->apiCall($payload));
            if ($parsed2['filas'] !== [] || ($parsed2['error_lectura'] === null && $parsed['error_lectura'] !== null)) {
                if ($parsed['error_lectura'] !== null || $parsed['filas'] === []) {
                    Log::info('IIBB retibrmov: recuperado en reintento', [
                        'empresa_id' => $empresaId,
                        'desde' => $fechaDesde,
                        'hasta' => $fechaHasta,
                        'error_previo' => $parsed['error_lectura'],
                        'filas' => count($parsed2['filas']),
                    ]);
                }

                return $parsed2;
            }
        }

        return $parsed;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdePagoproveedorErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        if (! Schema::hasTable('pagoproveedor_retencion')) {
            return [];
        }

        $filas = Pagoproveedor_Retencion::query()
            ->where('tiporetencion', Pagoproveedor_Retencion::TIPO_IIBB)
            ->where('provincia_id', (int) $provincia->id)
            ->where('importe', '!=', 0)
            ->whereHas('pagoproveedores', function ($q) use ($empresaId, $fechaDesde, $fechaHasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->where('estado', 'CONFIRMADA');
            })
            ->with(['pagoproveedores.proveedores'])
            ->get();

        $out = [];
        foreach ($filas as $ret) {
            $pago = $ret->pagoproveedores;
            $prov = $pago?->proveedores;
            if ($pago === null || $prov === null) {
                continue;
            }
            $importe = round((float) $ret->importe, 2);
            if (abs($importe) < 0.001) {
                continue;
            }
            $tipoPago = strtoupper(substr(trim((string) ($pago->tipocomprobante ?? '')), 0, 3));
            $base = (float) $ret->base_calculo;
            // Importe ya negativo (DEV RET) o AOP/NC / detalle DEV RET → resta en archivo.
            if ($importe < 0
                || IngresosBrutosRetencionSignoSupport::esDevolucionRetencionPago($pago)
                || IngresosBrutosRetencionSignoSupport::movimientoResta($tipoPago)
                || ($importe > 0 && $this->esNotaCreditoCompra($tipoPago))) {
                $importe = -abs($importe);
                $base = -abs($base);
            }
            $fecha = $pago->fecha?->format('Y-m-d') ?? '';
            $nroCert = (int) preg_replace('/\D+/', '', (string) ($ret->nro_certificado ?? '0'));

            $out[] = [
                'origen' => 'pagoproveedor_erp',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'retenciones',
                'fecha_retencion' => $fecha,
                'fecha_comp' => $fecha,
                'nro_comp' => (int) ($pago->numerotransaccion ?? 0),
                'nro_cert' => $nroCert,
                'sucursal' => 1,
                'base_calculo' => round($base, 2),
                'importe' => $importe,
                'alicuota' => round((float) $ret->alicuota, 2),
                'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($prov->nroinscripcion ?? '')),
                'codigo_proveedor' => (string) ($prov->codigo ?? ''),
                'razon_social' => substr((string) ($prov->nombre ?? ''), 0, 30),
                'referencia' => 'Ret.IIBB ERP '.($prov->codigo ?? '').' '.$pago->etiquetaComprobante(),
            ];
        }

        return $out;
    }

    /**
     * Nota de crédito de compras (CIB, NCP, …). No incluye OPP/OPA: también
     * tienen signo negativo, pero son el pago, no el comprobante aplicado.
     */
    private function esNotaCreditoCompra(string $abreviatura): bool
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if ($abreviatura === '') {
            return false;
        }

        return isset($this->notasCreditoCompra()[$abreviatura]);
    }

    /**
     * @return array<string, true>
     */
    private function notasCreditoCompra(): array
    {
        if ($this->notasCreditoCompra !== null) {
            return $this->notasCreditoCompra;
        }

        $this->notasCreditoCompra = [];
        if (! Schema::hasTable('tipotransaccion_compra') || ! Schema::hasColumn('tipotransaccion_compra', 'signo')) {
            return $this->notasCreditoCompra;
        }

        foreach (DB::table('tipotransaccion_compra')->get(['abreviatura', 'signo']) as $tipo) {
            $abreviatura = strtoupper(trim((string) ($tipo->abreviatura ?? '')));
            if ($abreviatura === '' || (int) $tipo->signo >= 0) {
                continue;
            }
            if (str_starts_with($abreviatura, 'NC') || str_starts_with($abreviatura, 'C')) {
                $this->notasCreditoCompra[$abreviatura] = true;
            }
        }

        return $this->notasCreditoCompra;
    }

    /**
     * Devoluciones de IIBB hechas por I/E (OPP/CHP) contra la cuenta configurada.
     * Contablemente son Debe del pasivo (monto > 0); no generan retibrmov ni
     * pagoproveedor_retencion. Se excluyen pagos de DDJJ ARBA.
     *
     * @param  list<array<string, mixed>>  $yaPresentes
     * @return list<array<string, mixed>>
     */
    private function desdeDevolucionesErpAsiento(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
        array $yaPresentes,
    ): array {
        if (! Schema::hasTable('asiento') || ! Schema::hasTable('asiento_movimiento')) {
            return [];
        }

        $cuentaIds = $config->cuentas
            ->where('empresa_id', $empresaId)
            ->pluck('cuentacontable_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        if ($cuentaIds === []) {
            return [];
        }

        $movimientos = Asiento_Movimiento::query()
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->where('monto', '>', 0)
            ->whereHas('asientos', function ($q) use ($empresaId, $fechaDesde, $fechaHasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->where(function ($tipo) {
                        $tipo->whereIn('anita_tipo', ['OPP', 'CHP'])
                            ->orWhereNull('anita_tipo')
                            ->orWhere('anita_tipo', '');
                    });
            })
            ->with(['asientos'])
            ->get();

        if ($movimientos->isEmpty()) {
            return [];
        }

        $cajaIds = $movimientos
            ->map(static fn (Asiento_Movimiento $m) => (int) ($m->asientos?->caja_movimiento_id ?? 0))
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $cajas = $cajaIds === []
            ? collect()
            : Caja_Movimiento::query()
                ->whereIn('id', $cajaIds)
                ->with('proveedores')
                ->get()
                ->keyBy('id');

        $clavesYa = SicoreErpComplementoSupport::claves($yaPresentes);
        $out = [];
        $vistos = [];

        foreach ($movimientos as $mov) {
            $asiento = $mov->asientos;
            if (! $asiento instanceof Asiento) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($asiento->anita_tipo ?? '')));
            if ($tipo === '') {
                $tipo = 'OPP';
            }
            if (! SicoreMayorComparableSupport::esTipoDevolucionRetencion($tipo)) {
                continue;
            }

            $detalle = trim((string) ($mov->observacion ?? $asiento->observacion ?? ''));
            $caja = $cajas->get((int) ($asiento->caja_movimiento_id ?? 0));
            if ($caja instanceof Caja_Movimiento && trim((string) $caja->detalle) !== '') {
                $detalle = trim((string) $caja->detalle);
            }

            // Pago de liquidación ARBA / DDJJ: no es devolución a proveedor.
            if (SicoreMayorComparableSupport::motivoExclusion($detalle) !== null) {
                continue;
            }

            $proveedor = $caja?->proveedores;
            if ($proveedor === null) {
                continue;
            }

            // Solo devoluciones explícitas (DEV RET / DEVOLUCION IIBB) o detalle vacío
            // con proveedor (casos DEV RET sin texto). Evita ANULA u otros Debe sueltos.
            if ($detalle !== '' && ! IngresosBrutosRetencionSignoSupport::esDevolucionRetencionDetalle($detalle)) {
                continue;
            }

            $importeAbs = round(abs((float) $mov->monto), 2);
            if ($importeAbs < 0.001) {
                continue;
            }

            $nroComp = (int) ($asiento->anita_nro ?: $caja?->numerotransaccion ?: 0);
            if ($nroComp <= 0) {
                continue;
            }

            $claveVista = $tipo.'|'.$nroComp.'|'.$importeAbs;
            if (isset($vistos[$claveVista])) {
                continue;
            }
            $vistos[$claveVista] = true;

            $probe = [
                'nro_comp' => $nroComp,
                'nro_cert' => 0,
                'importe' => $importeAbs,
            ];
            if (SicoreErpComplementoSupport::estaEnClaves($probe, $clavesYa)
                || SicoreErpComplementoSupport::estaEnClaves(
                    ['nro_comp' => $nroComp, 'nro_cert' => 0, 'importe' => -$importeAbs],
                    $clavesYa,
                )) {
                continue;
            }

            $fecha = $asiento->fecha
                ? (is_string($asiento->fecha) ? substr($asiento->fecha, 0, 10) : $asiento->fecha->format('Y-m-d'))
                : ($caja?->fecha ? substr((string) $caja->fecha, 0, 10) : '');
            if ($fecha === '') {
                continue;
            }

            $origen = $this->buscarRetencionIibbOrigen(
                (int) $proveedor->id,
                (int) $provincia->id,
                $importeAbs,
                $fecha,
            );

            $base = abs((float) ($origen['base_calculo'] ?? 0));
            if ($base < 0.001) {
                $ali = (float) ($origen['alicuota'] ?? 0);
                $base = $ali > 0.0001 ? round($importeAbs * 100.0 / $ali, 2) : $importeAbs;
            }
            $alicuota = round((float) ($origen['alicuota'] ?? 0), 2);
            $nroCert = (int) ($origen['nro_certificado'] ?? 0);

            $out[] = [
                'origen' => 'asiento_erp_devolucion',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'retenciones',
                'fecha_retencion' => $fecha,
                'fecha_comp' => $fecha,
                'nro_comp' => $nroComp,
                'nro_cert' => $nroCert,
                'sucursal' => 1,
                'base_calculo' => round(-$base, 2),
                'importe' => round(-$importeAbs, 2),
                'alicuota' => $alicuota,
                'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($proveedor->nroinscripcion ?? '')),
                'codigo_proveedor' => (string) ($proveedor->codigo ?? ''),
                'razon_social' => substr((string) ($proveedor->nombre ?? ''), 0, 30),
                'referencia' => sprintf(
                    'Dev.IIBB %s #%d %s',
                    $tipo,
                    $nroComp,
                    (string) ($proveedor->codigo ?? ''),
                ),
            ];
        }

        return $out;
    }

    /**
     * Retención IIBB original (mismo proveedor e importe) para completar certificado / base.
     *
     * @return array{base_calculo: float, alicuota: float, nro_certificado: int}
     */
    private function buscarRetencionIibbOrigen(
        int $proveedorId,
        int $provinciaId,
        float $importeAbs,
        string $fechaIsoHasta,
    ): array {
        $vacio = ['base_calculo' => 0.0, 'alicuota' => 0.0, 'nro_certificado' => 0];
        if ($proveedorId <= 0 || $importeAbs < 0.001 || $fechaIsoHasta === '') {
            return $vacio;
        }

        $fila = Pagoproveedor_Retencion::query()
            ->where('tiporetencion', Pagoproveedor_Retencion::TIPO_IIBB)
            ->where('provincia_id', $provinciaId)
            ->where('importe', '>', 0)
            ->whereRaw('ABS(importe - ?) < 0.02', [$importeAbs])
            ->whereHas('pagoproveedores', function ($q) use ($proveedorId, $fechaIsoHasta) {
                $q->where('proveedor_id', $proveedorId)
                    ->where('fecha', '<=', $fechaIsoHasta)
                    ->where('estado', 'CONFIRMADA');
            })
            ->with(['pagoproveedores' => static fn ($q) => $q->orderByDesc('fecha')])
            ->get()
            ->sortByDesc(static fn (Pagoproveedor_Retencion $r) => (string) ($r->pagoproveedores?->fecha ?? ''))
            ->first();

        if ($fila === null) {
            return $vacio;
        }

        return [
            'base_calculo' => (float) $fila->base_calculo,
            'alicuota' => (float) $fila->alicuota,
            'nro_certificado' => (int) preg_replace('/\D+/', '', (string) ($fila->nro_certificado ?? '0')),
        ];
    }
}
