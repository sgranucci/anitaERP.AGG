<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\ApiAnita;
use App\Models\Contable\Sicore_Config;
use App\Repositories\Compras\RetenciongananciaRepositoryInterface;
use App\Repositories\Compras\RetencionivaRepositoryInterface;
use App\Support\Contable\Sicore\SicoreCompraConcmovAnitaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;
use App\Support\Contable\Sicore\SicoreMayorComparableSupport;
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;

final class SicoreComprasDatosService
{
    /** @var list<string> */
    private const TIPOS_PAGO = ['OPP', 'AOP', 'OPA', 'OPV'];

    public function __construct(
        private readonly RetenciongananciaRepositoryInterface $retenciongananciaRepository,
        private readonly RetencionivaRepositoryInterface $retencionivaRepository,
        private readonly SicoreCompraConcmovAnitaSupport $compraConcmovSupport,
        private readonly SicoreProveedorErpSupport $proveedorSupport,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function generar(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        return match ($config->criterio) {
            'compras_iva' => $this->desdeRetimov($empresaId, $fechaDesde, $fechaHasta, $config),
            default => $this->desdeRetmov($empresaId, $fechaDesde, $fechaHasta, $config),
        };
    }

    /**
     * Ganancias: el período lo marca el subdiario (fecha contable del OPP/AOP).
     * retmov se lee por tipo/letra/sucursal/nro/empresa (sin filtrar por retv_fecha),
     * porque esa fecha a veces queda desfasada respecto del asiento.
     *
     * @return list<array<string, mixed>>
     */
    private function desdeRetmov(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $codigosCuenta = $this->codigosCuentaConfig($config, $empresaId);
        $pagosPeriodo = $this->listarPagosProveedorSubdiario(
            $empresaAnita,
            $fechaDesde,
            $fechaHasta,
            $codigosCuenta,
        );

        if ($pagosPeriodo === []) {
            return [];
        }

        $retmovPorClave = $this->indexarRetmovPorClave(
            $this->listarRetmovPorClaves($empresaAnita, array_values($pagosPeriodo)),
        );

        $this->proveedorSupport->precargar(array_map(
            static fn (array $pago) => (string) ($pago['emisor'] ?? ''),
            array_values($pagosPeriodo),
        ));
        $emisoresOk = $this->proveedorSupport->indicesExistentes(array_map(
            static fn (array $pago) => (string) ($pago['emisor'] ?? ''),
            array_values($pagosPeriodo),
        ));

        $regimenPorCodigo = $this->mapaRegimenGanancias();
        $out = [];
        $vistosRetmov = [];

        foreach ($pagosPeriodo as $pago) {
            $emisorNorm = SicoreMayorComparableSupport::normalizarEmisor((string) ($pago['emisor'] ?? ''));
            if ($emisorNorm === '' || ! isset($emisoresOk[$emisorNorm])) {
                continue;
            }

            $clave = $pago['clave'];
            $filasRet = $retmovPorClave[$clave] ?? [];
            if ($filasRet === []) {
                continue;
            }

            $fechaContable = (string) ($pago['fecha'] ?? '');

            foreach ($filasRet as $row) {
                $row = (array) $row;
                $dedupe = $clave.'|'.(int) ($row['retv_codigo_ret'] ?? 0).'|'.(int) ($row['retv_nro_retencion'] ?? 0);
                if (isset($vistosRetmov[$dedupe])) {
                    continue;
                }
                $vistosRetmov[$dedupe] = true;

                $codRet = (int) ($row['retv_codigo_ret'] ?? 0);
                $regimen = (int) ($config->codigo_regimen ?? ($regimenPorCodigo[$codRet] ?? 999));
                $signo = strncmp((string) ($row['retv_tipo'] ?? ''), 'AOP', 3) === 0 ? -1.0 : 1.0;
                $retencion = round((float) ($row['retv_retencion'] ?? 0) * $signo, 2);
                if (abs($retencion) < 0.001) {
                    continue;
                }

                $proveedor = $this->proveedorSupport->resolverDesdeFila(
                    $row,
                    'retv_proveedor',
                    'retv_nombre_prov',
                    'retv_cuit_prov',
                );

                $pagoActual = round((float) ($row['retv_pago_actual'] ?? 0) * $signo, 2);
                $base = $signo < 0 ? abs($retencion) : abs($pagoActual);
                $esDevolucion = strncmp((string) ($row['retv_tipo'] ?? ''), 'AOP', 3) === 0;

                $out[] = [
                    'origen' => 'compras_ganancias',
                    'sicore_config_id' => (int) $config->id,
                    'cod_regimen' => $regimen,
                    'cod_impuesto' => (int) $config->codigo_impuesto,
                    'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
                    // Devolución AOP: cod_comp=8 al inicio del registro; importes del .dat en positivo.
                    'cod_comp' => $esDevolucion
                        ? SicoreFormatoV8Support::COD_COMP_DEVOLUCION
                        : SicoreFormatoV8Support::COD_COMP_ORDEN_PAGO,
                    'fecha_comp' => $fechaContable,
                    'nro_comp' => (int) ($row['retv_nro'] ?? 0),
                    'importe_comp' => abs($pagoActual),
                    'base_calculo' => abs($base),
                    // Fecha del asiento (subdiario), no retv_fecha.
                    'fecha_retencion' => $fechaContable,
                    'cod_condicion' => $proveedor['cod_condicion'],
                    // Signo interno para conciliar con mayor; el archivo aplica abs().
                    'importe' => $retencion,
                    'porc_excl' => (float) ($row['retv_porc_excl'] ?? 0),
                    'fecha_boletin' => '',
                    'cod_documento' => 80,
                    'nro_documento' => SicoreFormatoV8Support::normalizarCuit($proveedor['cuit']),
                    'nro_cert' => (int) ($row['retv_nro_retencion'] ?? 0),
                    'codigo_proveedor' => $proveedor['codigo_proveedor'],
                    'razon_social' => substr($proveedor['nombre'], 0, 30),
                    'referencia' => 'Ret.GC '.$proveedor['codigo_proveedor'],
                ];
            }
        }

        return array_merge(
            $out,
            $this->desdeDevolucionesChequeSubdiario($empresaId, $fechaDesde, $fechaHasta, $config),
            $this->desdePagoproveedorErp($empresaId, $fechaDesde, $fechaHasta, $config, 'G'),
        );
    }

    /**
     * Retenciones de pagos ERP (pagoproveedor_retencion) — complementa retmov Anita.
     *
     * @return list<array<string, mixed>>
     */
    private function desdePagoproveedorErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Sicore_Config $config,
        string $tipoRetencion,
    ): array {
        if (! \Illuminate\Support\Facades\Schema::hasTable('pagoproveedor_retencion')) {
            return [];
        }

        $filas = \App\Models\Compras\Pagoproveedor_Retencion::query()
            ->where('tiporetencion', $tipoRetencion)
            ->where('importe', '!=', 0)
            ->whereHas('pagoproveedores', function ($q) use ($empresaId, $fechaDesde, $fechaHasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->where('estado', 'CONFIRMADA');
            })
            ->with(['pagoproveedores.proveedores'])
            ->get();

        if ($filas->isEmpty()) {
            return [];
        }

        $this->proveedorSupport->precargar($filas->map(function ($r) {
            return (string) ($r->pagoproveedores?->proveedores?->codigo ?? '');
        })->all());

        $regimenPorCodigo = $tipoRetencion === 'G' ? $this->mapaRegimenGanancias() : [];
        $out = [];

        foreach ($filas as $ret) {
            $pago = $ret->pagoproveedores;
            if ($pago === null) {
                continue;
            }
            $prov = $pago->proveedores;
            if ($prov === null) {
                continue;
            }
            $codRet = (int) ($ret->codigo_retencion ?: 0);
            $regimen = (int) ($config->codigo_regimen ?? ($regimenPorCodigo[$codRet] ?? (int) ($ret->codigo_regimen ?: 999)));
            $importe = round((float) $ret->importe, 2);
            if (abs($importe) < 0.001) {
                continue;
            }

            $cuit = SicoreFormatoV8Support::normalizarCuit((string) ($prov->nroinscripcion ?? ''));
            $codigoProv = (string) ($prov->codigo ?? '');

            $out[] = [
                'origen' => $tipoRetencion === 'I' ? 'compras_iva_erp' : 'compras_ganancias_erp',
                'sicore_config_id' => (int) $config->id,
                'cod_regimen' => $regimen,
                'cod_impuesto' => (int) $config->codigo_impuesto,
                'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
                'cod_comp' => 6,
                'fecha_comp' => $pago->fecha?->format('Y-m-d') ?? '',
                'nro_comp' => (int) $pago->numerotransaccion,
                'importe_comp' => abs((float) $pago->monto),
                'base_calculo' => abs((float) $ret->base_calculo),
                'fecha_retencion' => $pago->fecha?->format('Y-m-d') ?? '',
                'cod_condicion' => 1,
                'importe' => $importe,
                'porc_excl' => 0.0,
                'fecha_boletin' => '',
                'cod_documento' => 80,
                'nro_documento' => $cuit,
                'nro_cert' => (int) preg_replace('/\D+/', '', (string) ($ret->nro_certificado ?? '0')),
                'codigo_proveedor' => $codigoProv,
                'razon_social' => substr((string) ($prov->nombre ?? ''), 0, 30),
                'referencia' => 'Ret.ERP '.$codigoProv.' '.$pago->etiquetaComprobante(),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeRetimov(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        $filasRaw = $this->listarRetimov($empresaId, $fechaDesde, $fechaHasta);
        $this->proveedorSupport->precargar(array_map(
            static fn (array $row) => (string) ($row['retiv_proveedor'] ?? ''),
            array_map(static fn ($row) => (array) $row, $filasRaw),
        ));

        $regimenPorCodigo = $this->mapaRegimenIva();
        $out = [];

        foreach ($filasRaw as $row) {
            $row = (array) $row;
            $codRet = (int) ($row['retiv_codigo_ret'] ?? 0);
            $regimen = (int) ($config->codigo_regimen ?? ($regimenPorCodigo[$codRet] ?? 999));
            $retencion = round((float) ($row['retiv_retencion'] ?? 0), 2);
            if (abs($retencion) < 0.001) {
                continue;
            }

            $proveedor = $this->proveedorSupport->resolverDesdeFila(
                $row,
                'retiv_proveedor',
                'retiv_nombre_prov',
                'retiv_cuit_prov',
            );

            $fechaAnita = (int) ($row['retiv_fecha'] ?? 0);
            $fechaIso = $this->anitaAFechaIso($fechaAnita);
            $fechaCompAnita = (int) ($row['retiv_fecha_comp'] ?? $fechaAnita);
            $tipoComp = (string) ($row['retiv_tipo_comp'] ?? '01');
            $importesCompra = $this->compraConcmovSupport->importesDesdeCompraConcmov($row);

            $out[] = [
                'origen' => 'compras_iva',
                'sicore_config_id' => (int) $config->id,
                'cod_regimen' => $regimen,
                'cod_impuesto' => (int) $config->codigo_impuesto,
                'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
                'cod_comp' => SicoreFormatoV8Support::codigoComprobanteDesdeTipo($tipoComp),
                'fecha_comp' => $this->anitaAFechaIso($fechaCompAnita),
                'nro_comp' => (int) ($row['retiv_nro_comp'] ?? 0),
                'importe_comp' => $importesCompra['importe_comp'],
                'base_calculo' => $importesCompra['base_calculo'],
                'fecha_retencion' => $fechaIso,
                'cod_condicion' => $proveedor['cod_condicion'],
                'importe' => $retencion,
                'porc_excl' => (float) ($row['retiv_porc_excl'] ?? 0),
                'fecha_boletin' => '',
                'cod_documento' => 80,
                'nro_documento' => SicoreFormatoV8Support::normalizarCuit($proveedor['cuit']),
                'nro_cert' => (int) ($row['retiv_nro_ret'] ?? 0),
                'codigo_proveedor' => $proveedor['codigo_proveedor'],
                'razon_social' => substr($proveedor['nombre'], 0, 30),
                'referencia' => sprintf(
                    'Ret.IVA %s — FC %s',
                    $proveedor['codigo_proveedor'],
                    $row['retiv_nro_comp'] ?? '',
                ),
            ];
        }

        return array_merge(
            $out,
            $this->desdePagoproveedorErp($empresaId, $fechaDesde, $fechaHasta, $config, 'I'),
        );
    }

    /**
     * Devoluciones de retención de ganancias pagadas con cheque propio (CHP) que imputan
     * la cuenta configurada (ej. 214010013). No generan AOP en retmov; el mayor las ve
     * como Debe sobre el pasivo. En SICORE: cod_comp=8 e importes positivos en el .dat.
     *
     * @return list<array<string, mixed>>
     */
    private function desdeDevolucionesChequeSubdiario(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Sicore_Config $config,
    ): array {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $codigosCuenta = $this->codigosCuentaConfig($config, $empresaId);
        $cheques = $this->listarDevolucionesChequeSubdiario(
            $empresaAnita,
            $fechaDesde,
            $fechaHasta,
            $codigosCuenta,
        );

        if ($cheques === []) {
            return [];
        }

        $this->proveedorSupport->precargar(array_map(
            static fn (array $ch) => (string) ($ch['emisor'] ?? ''),
            $cheques,
        ));
        $emisoresOk = $this->proveedorSupport->indicesExistentes(array_map(
            static fn (array $ch) => (string) ($ch['emisor'] ?? ''),
            $cheques,
        ));

        $regimenPorCodigo = $this->mapaRegimenGanancias();
        $out = [];

        foreach ($cheques as $ch) {
            $emisorNorm = SicoreMayorComparableSupport::normalizarEmisor((string) ($ch['emisor'] ?? ''));
            if ($emisorNorm === '' || ! isset($emisoresOk[$emisorNorm])) {
                continue;
            }

            $importeAbs = round((float) ($ch['importe'] ?? 0), 2);
            if ($importeAbs < 0.001) {
                continue;
            }

            $fechaIso = (string) ($ch['fecha'] ?? '');
            $origenRet = $this->buscarRetmovOrigenDevolucion(
                $empresaAnita,
                $emisorNorm,
                $importeAbs,
                $fechaIso,
            );

            $codRet = (int) ($origenRet['retv_codigo_ret'] ?? 0);
            $regimen = (int) ($config->codigo_regimen ?? ($regimenPorCodigo[$codRet] ?? 999));
            $nroCert = (int) ($origenRet['retv_nro_retencion'] ?? 0);
            $pagoActual = abs((float) ($origenRet['retv_pago_actual'] ?? $importeAbs));

            $proveedor = $this->proveedorSupport->resolverDesdeFila(
                [
                    'retv_proveedor' => $emisorNorm,
                    'retv_nombre_prov' => '',
                    'retv_cuit_prov' => '',
                ],
                'retv_proveedor',
                'retv_nombre_prov',
                'retv_cuit_prov',
            );

            $out[] = [
                'origen' => 'compras_ganancias_dev_cheque',
                'sicore_config_id' => (int) $config->id,
                'cod_regimen' => $regimen,
                'cod_impuesto' => (int) $config->codigo_impuesto,
                'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
                'cod_comp' => SicoreFormatoV8Support::COD_COMP_DEVOLUCION,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($ch['nro'] ?? 0),
                'importe_comp' => $pagoActual > 0.001 ? $pagoActual : $importeAbs,
                'base_calculo' => $importeAbs,
                'fecha_retencion' => $fechaIso,
                'cod_condicion' => $proveedor['cod_condicion'],
                // Signo interno para conciliar con mayor; el archivo aplica abs() → positivo.
                'importe' => round(-$importeAbs, 2),
                'porc_excl' => (float) ($origenRet['retv_porc_excl'] ?? 0),
                'fecha_boletin' => '',
                'cod_documento' => 80,
                'nro_documento' => SicoreFormatoV8Support::normalizarCuit($proveedor['cuit']),
                'nro_cert' => $nroCert,
                'codigo_proveedor' => $proveedor['codigo_proveedor'],
                'razon_social' => substr($proveedor['nombre'], 0, 30),
                'referencia' => sprintf(
                    'Dev.GC CHP #%d %s',
                    (int) ($ch['nro'] ?? 0),
                    $proveedor['codigo_proveedor'],
                ),
            ];
        }

        return $out;
    }

    /**
     * CHP del período que tocan las cuentas de retención con efecto Debe (devolución).
     *
     * @param  list<int>  $codigosCuenta
     * @return list<array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int, emisor: string, fecha: string, importe: float}>
     */
    private function listarDevolucionesChequeSubdiario(
        int $empresaAnita,
        string $fechaDesde,
        string $fechaHasta,
        array $codigosCuenta,
    ): array {
        if ($empresaAnita <= 0 || $codigosCuenta === [] || $fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $cuentasSet = array_fill_keys(array_map('intval', $codigosCuenta), true);
        $cuentasOr = [];
        foreach ($codigosCuenta as $codigo) {
            $codigo = (int) $codigo;
            $cuentasOr[] = 'subd_cuenta='.$codigo;
            $cuentasOr[] = 'subd_contrapartida='.$codigo;
        }

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'subdiario',
            'campos' => 'subd_empresa,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,'
                .'subd_cuenta,subd_contrapartida,subd_importe,subd_tipo_mov',
            'whereArmado' => ' WHERE subd_empresa='.$empresaAnita
                .' AND subd_fecha BETWEEN '.$desdeAnita.' AND '.$hastaAnita
                .' AND subd_tipo="CHP"'
                .' AND ('.implode(' OR ', $cuentasOr).')',
            'orderBy' => 'subd_fecha, subd_nro',
        ]));

        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $tipo = strtoupper(trim((string) ($fila['subd_tipo'] ?? '')));
            if (! SicoreMayorComparableSupport::esTipoDevolucionCheque($tipo)) {
                continue;
            }

            $cuenta = (int) ($fila['subd_cuenta'] ?? 0);
            $contrapartida = (int) ($fila['subd_contrapartida'] ?? 0);
            $tipoMov = strtoupper(trim((string) ($fila['subd_tipo_mov'] ?? 'D')));
            if (! $this->subdiarioImputaDebeEnCuentas($cuenta, $contrapartida, $tipoMov, $cuentasSet)) {
                continue;
            }

            $nro = (int) ($fila['subd_nro'] ?? 0);
            $empresa = (int) ($fila['subd_empresa'] ?? $empresaAnita);
            if ($nro <= 0) {
                continue;
            }

            $clave = $tipo.'|'.$nro.'|'.$empresa;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $fechaIso = $this->anitaAFechaIso((int) ($fila['subd_fecha'] ?? 0));
            if ($fechaIso === '') {
                continue;
            }

            $out[] = [
                'tipo' => $tipo,
                'letra' => trim((string) ($fila['subd_letra'] ?? '')),
                'sucursal' => (int) ($fila['subd_sucursal'] ?? 0),
                'nro' => $nro,
                'empresa' => $empresa,
                'emisor' => trim((string) ($fila['subd_emisor'] ?? '')),
                'fecha' => $fechaIso,
                'importe' => abs((float) ($fila['subd_importe'] ?? 0)),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, true>  $cuentasSet
     */
    private function subdiarioImputaDebeEnCuentas(
        int $cuenta,
        int $contrapartida,
        string $tipoMov,
        array $cuentasSet,
    ): bool {
        $tipoMov = $tipoMov === 'H' ? 'H' : 'D';

        if (isset($cuentasSet[$cuenta]) && $tipoMov === 'D') {
            return true;
        }

        if (isset($cuentasSet[$contrapartida]) && $tipoMov === 'H') {
            return true;
        }

        return false;
    }

    /**
     * Retención OPP original (mismo proveedor e importe) para completar nro_cert / régimen.
     *
     * @return array<string, mixed>
     */
    private function buscarRetmovOrigenDevolucion(
        int $empresaAnita,
        string $emisorAnita,
        float $importeAbs,
        string $fechaIsoHasta,
    ): array {
        if ($empresaAnita <= 0 || $emisorAnita === '' || $importeAbs < 0.001 || $fechaIsoHasta === '') {
            return [];
        }

        $hastaAnita = (int) str_replace('-', '', $fechaIsoHasta);
        $emisorEsc = addslashes($emisorAnita);
        $emisorAlt = addslashes(ltrim($emisorAnita, '0'));
        if ($emisorAlt === '') {
            $emisorAlt = $emisorEsc;
        }

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retmov',
            'campos' => implode(', ', [
                'retv_proveedor', 'retv_tipo', 'retv_letra', 'retv_sucursal', 'retv_nro',
                'retv_fecha', 'retv_codigo_ret', 'retv_pago_actual', 'retv_retencion',
                'retv_nro_retencion', 'retv_porc_excl', 'retv_empresa',
            ]),
            'whereArmado' => ' WHERE retv_empresa='.$empresaAnita
                .' AND retv_fecha <= '.$hastaAnita
                .' AND retv_retencion <> 0'
                .' AND (retv_tipo="OPP" OR retv_tipo LIKE "OPP%")'
                .' AND (retv_proveedor="'.$emisorEsc.'" OR retv_proveedor="'.$emisorAlt.'")'
                .' AND ABS(retv_retencion-'.$importeAbs.') < 0.02',
            'orderBy' => 'retv_fecha DESC',
        ]));

        if ($filas === []) {
            return [];
        }

        return (array) $filas[0];
    }

    /**
     * @return list<int>
     */
    private function codigosCuentaConfig(Sicore_Config $config, int $empresaId): array
    {
        if (! $config->relationLoaded('cuentas')) {
            $config->load('cuentas.cuentacontable');
        }

        return $config->cuentas
            ->where('empresa_id', $empresaId)
            ->map(static fn ($c) => (int) preg_replace('/\D/', '', (string) ($c->cuentacontable?->codigo ?? '')))
            ->filter(static fn (int $cod) => $cod > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Pagos OPP/AOP del período que tocan las cuentas de retención configuradas.
     * Clave = tipo|letra|sucursal|nro|empresa.
     *
     * @param  list<int>  $codigosCuenta
     * @return array<string, array{clave: string, tipo: string, letra: string, sucursal: int, nro: int, empresa: int, emisor: string, fecha: string}>
     */
    private function listarPagosProveedorSubdiario(
        int $empresaAnita,
        string $fechaDesde,
        string $fechaHasta,
        array $codigosCuenta,
    ): array {
        if ($empresaAnita <= 0 || $codigosCuenta === [] || $fechaDesde === '' || $fechaHasta === '') {
            return [];
        }

        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $tiposSql = implode(',', array_map(
            static fn (string $t) => '"'.addslashes($t).'"',
            self::TIPOS_PAGO,
        ));
        $cuentasOr = [];
        foreach ($codigosCuenta as $codigo) {
            $codigo = (int) $codigo;
            $cuentasOr[] = 'subd_cuenta='.$codigo;
            $cuentasOr[] = 'subd_contrapartida='.$codigo;
        }

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => 'subdiario',
            'campos' => 'subd_empresa,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,subd_emisor,'
                .'subd_cuenta,subd_contrapartida',
            'whereArmado' => ' WHERE subd_empresa='.$empresaAnita
                .' AND subd_fecha BETWEEN '.$desdeAnita.' AND '.$hastaAnita
                .' AND subd_tipo IN ('.$tiposSql.')'
                .' AND ('.implode(' OR ', $cuentasOr).')',
            'orderBy' => 'subd_fecha, subd_tipo, subd_nro',
        ]));

        $out = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $tipo = strtoupper(trim((string) ($fila['subd_tipo'] ?? '')));
            if (! in_array($tipo, self::TIPOS_PAGO, true)) {
                continue;
            }

            $letra = trim((string) ($fila['subd_letra'] ?? ''));
            $sucursal = (int) ($fila['subd_sucursal'] ?? 0);
            $nro = (int) ($fila['subd_nro'] ?? 0);
            $empresa = (int) ($fila['subd_empresa'] ?? $empresaAnita);
            if ($nro <= 0) {
                continue;
            }

            $clave = $this->clavePago($tipo, $letra, $sucursal, $nro, $empresa);
            $fechaIso = $this->anitaAFechaIso((int) ($fila['subd_fecha'] ?? 0));
            if ($fechaIso === '') {
                continue;
            }

            // Si la misma OP aparece más de una vez, conservar la fecha contable primera.
            if (isset($out[$clave])) {
                continue;
            }

            $out[$clave] = [
                'clave' => $clave,
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'nro' => $nro,
                'empresa' => $empresa,
                'emisor' => trim((string) ($fila['subd_emisor'] ?? '')),
                'fecha' => $fechaIso,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{tipo: string, letra: string, sucursal: int, nro: int, empresa: int}>  $claves
     * @return list<object|array<string, mixed>>
     */
    private function listarRetmovPorClaves(int $empresaAnita, array $claves): array
    {
        if ($claves === [] || $empresaAnita <= 0) {
            return [];
        }

        $api = new ApiAnita();
        $campos = implode(', ', [
            'retv_proveedor', 'retv_tipo', 'retv_letra', 'retv_sucursal', 'retv_nro',
            'retv_fecha', 'retv_codigo_ret', 'retv_pago_actual', 'retv_retencion',
            'retv_nro_retencion', 'retv_nombre_prov', 'retv_cuit_prov', 'retv_porc_excl',
            'retv_empresa',
        ]);

        $out = [];
        foreach (array_chunk($claves, 40) as $lote) {
            $ors = [];
            foreach ($lote as $pago) {
                $tipo = addslashes((string) ($pago['tipo'] ?? ''));
                $letra = addslashes((string) ($pago['letra'] ?? ''));
                $suc = (int) ($pago['sucursal'] ?? 0);
                $nro = (int) ($pago['nro'] ?? 0);
                $emp = (int) ($pago['empresa'] ?? $empresaAnita);
                if ($tipo === '' || $nro <= 0) {
                    continue;
                }
                $ors[] = '(retv_tipo="'.$tipo.'"'
                    .' AND retv_letra="'.$letra.'"'
                    .' AND retv_sucursal='.$suc
                    .' AND retv_nro='.$nro
                    .' AND retv_empresa='.$emp.')';
            }
            if ($ors === []) {
                continue;
            }

            $filas = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'retmov',
                'campos' => $campos,
                'whereArmado' => ' WHERE retv_retencion <> 0 AND ('.implode(' OR ', $ors).')',
                'orderBy' => 'retv_tipo, retv_nro, retv_nro_retencion',
            ]));
            foreach ($filas as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  list<object|array<string, mixed>>  $filas
     * @return array<string, list<array<string, mixed>>>
     */
    private function indexarRetmovPorClave(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $clave = $this->clavePago(
                strtoupper(trim((string) ($fila['retv_tipo'] ?? ''))),
                trim((string) ($fila['retv_letra'] ?? '')),
                (int) ($fila['retv_sucursal'] ?? 0),
                (int) ($fila['retv_nro'] ?? 0),
                (int) ($fila['retv_empresa'] ?? 0),
            );
            $out[$clave][] = $fila;
        }

        return $out;
    }

    private function clavePago(string $tipo, string $letra, int $sucursal, int $nro, int $empresa): string
    {
        return strtoupper(trim($tipo)).'|'
            .trim($letra).'|'
            .$sucursal.'|'
            .$nro.'|'
            .$empresa;
    }

    /**
     * @return list<object|array<string, mixed>>
     */
    private function listarRetimov(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);

        $api = new ApiAnita();

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retimov',
            'campos' => implode(', ', [
                'retiv_proveedor', 'retiv_fecha', 'retiv_codigo_ret', 'retiv_retencion',
                'retiv_nro_ret', 'retiv_tipo_comp', 'retiv_letra_comp', 'retiv_suc_comp',
                'retiv_nro_comp', 'retiv_fecha_comp', 'retiv_nro_interno',
                'retiv_nombre_prov', 'retiv_cuit_prov',
                'retiv_porc_excl', 'retiv_empresa',
            ]),
            'whereArmado' => ' WHERE retiv_fecha >= '.$desdeAnita
                .' AND retiv_fecha <= '.$hastaAnita
                .' AND retiv_empresa = '.$empresaAnita
                .' AND retiv_retencion <> 0',
            'orderBy' => 'retiv_fecha, retiv_proveedor, retiv_nro_ret',
        ]));
    }

    /**
     * @return array<int, int>
     */
    private function mapaRegimenGanancias(): array
    {
        $map = [];
        foreach ($this->retenciongananciaRepository->all() as $ret) {
            $cod = (int) $ret->codigo;
            if ($cod > 0) {
                $map[$cod] = (int) $ret->regimen;
            }
        }

        return $map;
    }

    /**
     * @return array<int, int>
     */
    private function mapaRegimenIva(): array
    {
        $map = [];
        foreach ($this->retencionivaRepository->all() as $ret) {
            $cod = (int) $ret->codigo;
            if ($cod > 0) {
                $map[$cod] = (int) $ret->regimen;
            }
        }

        return $map;
    }

    private function anitaAFechaIso(int $fechaAnita): string
    {
        if ($fechaAnita <= 0) {
            return '';
        }

        $s = str_pad((string) $fechaAnita, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
