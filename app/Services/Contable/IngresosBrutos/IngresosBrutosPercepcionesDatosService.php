<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\ApiAnita;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Venta;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Compras\Retencion\AnitaRetencionEsquemaSupport;
use App\Support\Configuracion\CotizacionVigenteSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasCuitSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreVentaImpuestoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Percepciones IIBB — Anita venibr (opción 8) + ERP venta_impuesto (Perc. Buenos Aires…).
 */
final class IngresosBrutosPercepcionesDatosService
{
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
        $anita = $this->desdeVenibrAnita($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        $erp = $this->desdeVentaImpuestoErp($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        $bancos = $this->desdeGastoBancarioErp($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);

        // Preferir Anita si hay datos; ERP complementa cuando no hay venibr (ventas solo ERP).
        // Los gastos bancarios (ICO) no están en venibr: se suman siempre, con el CUIT del banco.
        $base = $anita !== [] ? $anita : $erp;

        return $this->fusionarSinDuplicar($base, $bancos);
    }

    /**
     * Percepciones de IIBB que el banco practica en el gasto bancario (ICO/IDO).
     * El CUIT del archivo es el del banco, que es el agente.
     *
     * @return list<array<string, mixed>>
     */
    private function desdeGastoBancarioErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        if ($empresaId <= 0
            || ! Schema::hasTable('comprobante_proveedor')
            || ! Schema::hasTable('comprobante_proveedor_concepto')
            || ! Schema::hasTable('concepto_ivacompra')
            || ! Schema::hasColumn('concepto_ivacompra', 'provincia_id')
            || ! Schema::hasColumn('concepto_ivacompra', 'tipoconcepto')) {
            return [];
        }

        $provinciaId = (int) $provincia->id;
        if ($provinciaId <= 0) {
            return [];
        }

        $filas = DB::table('comprobante_proveedor as cp')
            ->join('comprobante_proveedor_concepto as cpc', 'cpc.comprobante_proveedor_id', '=', 'cp.id')
            ->join('concepto_ivacompra as ci', 'ci.id', '=', 'cpc.concepto_ivacompra_id')
            ->leftJoin('tipotransaccion_compra as tt', 'tt.id', '=', 'cp.tipotransaccion_compra_id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->where('cp.empresa_id', $empresaId)
            ->whereBetween('cp.fechacomprobante', [$fechaDesde, $fechaHasta])
            ->where(function ($q): void {
                $q->whereNull('cp.estado')
                    ->orWhere('cp.estado', '!=', ComprobanteProveedorEstados::ANULADO);
            })
            ->where('ci.tipoconcepto', ComprobanteProveedorConceptoIvaTipos::PERCEPCION_IIBB)
            ->where('ci.provincia_id', $provinciaId)
            ->where(function ($q): void {
                $q->where('cp.tipo_tesoreria', ComprobanteProveedorTipoTesoreria::GASTO_BANCO)
                    ->orWhereIn('tt.abreviatura', ['ICO', 'IDO']);
            })
            ->orderBy('cp.fechacomprobante')
            ->orderBy('cp.numerocomprobante')
            ->get([
                'cp.id',
                'cp.fechacomprobante',
                'cp.letra',
                'cp.sucursal',
                'cp.numerocomprobante',
                'cp.moneda_id',
                'cp.cotizacion',
                'cp.proveedor_id',
                'cp.proveedor_nombre_eventual',
                'cp.proveedor_documento_eventual',
                'cp.identificacion_proveedor_cuit',
                'tt.abreviatura',
                'tt.signo',
                'p.nombre as proveedor_nombre',
                'p.codigo as proveedor_codigo',
                'p.nroinscripcion',
                'ci.nombre as concepto_nombre',
                'cpc.monto',
            ]);

        $out = [];
        foreach ($filas as $fila) {
            $tipo = strtoupper(substr(trim((string) ($fila->abreviatura ?? 'ICO')), 0, 3));
            $vendedor = LibroIvaDigitalComprasCuitSupport::cuitYNombreVendedorErp(
                (string) ($fila->nroinscripcion ?? ''),
                (string) ($fila->identificacion_proveedor_cuit ?? ''),
                (string) ($fila->proveedor_documento_eventual ?? ''),
                (string) ($fila->proveedor_nombre ?? ''),
                (string) ($fila->proveedor_nombre_eventual ?? ''),
                $tipo !== '' ? $tipo : 'ICO',
            );
            if (! LibroIvaDigitalComprasCuitSupport::esCuitValido($vendedor['cuit'])) {
                continue;
            }

            $signo = (float) ($fila->signo ?? 1);
            if (abs($signo) < 0.0001) {
                $signo = 1.0;
            }
            $coef = $this->coefMonedaComprobante($fila);
            if ($coef <= 0) {
                continue;
            }
            $importe = round((float) ($fila->monto ?? 0) * $signo * $coef, 2);
            if (abs($importe) < 0.001) {
                continue;
            }

            $fecha = substr((string) ($fila->fechacomprobante ?? ''), 0, 10);
            $nro = (int) ($fila->numerocomprobante ?? 0);
            $out[] = [
                'origen' => 'gasto_bancario',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'percepciones',
                'fecha_retencion' => $fecha,
                'fecha_comp' => $fecha,
                'nro_comp' => $nro,
                'nro_cert' => 0,
                'sucursal' => (int) ($fila->sucursal ?? 0),
                'letra' => substr(trim((string) ($fila->letra ?? 'A')).' ', 0, 1),
                'tipo_documento' => $importe < 0 ? 'C' : 'F',
                'base_calculo' => abs($importe),
                'importe' => $importe,
                'alicuota' => 0.0,
                'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit($vendedor['cuit']),
                'codigo_proveedor' => trim((string) ($fila->proveedor_codigo ?? '')),
                'razon_social' => substr($vendedor['nombre'], 0, 30),
                'referencia' => sprintf(
                    'Perc.IIBB gasto bancario %s %s %s-%08d',
                    $tipo !== '' ? $tipo : 'ICO',
                    (string) ($fila->letra ?? 'A'),
                    str_pad((string) ((int) ($fila->sucursal ?? 0)), 4, '0', STR_PAD_LEFT),
                    $nro,
                ),
                'comprobante_proveedor_id' => (int) $fila->id,
            ];
        }

        return $out;
    }

    private function coefMonedaComprobante(object $fila): float
    {
        $monedaId = (int) ($fila->moneda_id ?? 1);
        if ($monedaId <= 1) {
            return 1.0;
        }

        $cotizacion = (float) ($fila->cotizacion ?? 0);
        if ($cotizacion > 1.0001) {
            return $cotizacion;
        }

        $fecha = substr((string) ($fila->fechacomprobante ?? ''), 0, 10);
        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaId);

        return $vigente > 0 ? $vigente : 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  list<array<string, mixed>>  $extra
     * @return list<array<string, mixed>>
     */
    private function fusionarSinDuplicar(array $base, array $extra): array
    {
        if ($extra === []) {
            return $base;
        }

        $vistos = [];
        foreach ($base as $reg) {
            $vistos[$this->clavePercepcion($reg)] = true;
        }
        foreach ($extra as $reg) {
            $clave = $this->clavePercepcion($reg);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $base[] = $reg;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    private function clavePercepcion(array $reg): string
    {
        return substr((string) ($reg['fecha_retencion'] ?? ''), 0, 10)
            .'|'.(int) ($reg['nro_comp'] ?? 0)
            .'|'.IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($reg['nro_documento'] ?? ''))
            .'|'.number_format((float) ($reg['importe'] ?? 0), 2, '.', '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeVenibrAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $codigosProv = IngresosBrutosProvinciaAnitaSupport::codigosAnita($provincia);
        if ($codigosProv === []) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $provIn = implode(',', array_map('intval', $codigosProv));
        $filtraEmpresa = AnitaRetencionEsquemaSupport::ventaTieneColumnaEmpresa();

        $campos = [
            'ven_fecha', 'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
            'ven_cliente', 'ven_gravado', 'ven_gravado_ot',
            'ven_cod_mon', 'ven_cotizacion',
            'clim_nombre', 'clim_cuit',
            'veni_provincia', 'veni_porcentaje', 'veni_importe',
            'ven_cuit_cli', 'ven_nombre_cliente',
        ];
        if ($filtraEmpresa) {
            $campos[] = 'ven_empresa';
        }
        $where = ' WHERE ven_cliente=clim_cliente'
            .' AND veni_tipo=ven_tipo AND veni_letra=ven_letra'
            .' AND veni_sucursal=ven_sucursal AND veni_nro=ven_nro'
            .' AND ven_fecha >= '.$desdeAnita
            .' AND ven_fecha <= '.$hastaAnita
            .' AND veni_provincia IN ('.$provIn.')'
            .' AND veni_importe <> 0';
        if ($filtraEmpresa) {
            $where .= ' AND ven_empresa = '.$empresaAnita;
        }

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta, outer climae, venibr',
            'campos' => implode(', ', $campos),
            'whereArmado' => $where,
            'orderBy' => 'ven_fecha, ven_sucursal, ven_nro',
        ]));

        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $clave = trim((string) ($fila['ven_tipo'] ?? '')).'|'
                .trim((string) ($fila['ven_letra'] ?? '')).'|'
                .(int) ($fila['ven_sucursal'] ?? 0).'|'
                .(int) ($fila['ven_nro'] ?? 0);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $importe = round((float) ($fila['veni_importe'] ?? 0), 2);
            if (abs($importe) < 0.001) {
                continue;
            }

            $tipo = strtoupper(trim((string) ($fila['ven_tipo'] ?? '')));
            $signo = (str_starts_with($tipo, 'NC') || str_starts_with($tipo, 'C')) ? -1.0 : 1.0;
            $cot = (float) ($fila['ven_cotizacion'] ?? 1);
            $coef = $cot > 0 ? $cot : 1.0;
            $base = round(((float) ($fila['ven_gravado'] ?? 0) + (float) ($fila['ven_gravado_ot'] ?? 0)) * $signo * $coef, 2);
            $importe = round($importe * $signo * $coef, 2);
            $fechaIso = IngresosBrutosFormatoArbaSupport::fechaIsoDesdeAnita((int) ($fila['ven_fecha'] ?? 0));
            $tipoDoc = $this->tipoDocumentoArba($tipo);

            $out[] = [
                'origen' => 'venibr',
                'iibb_config_id' => (int) $config->id,
                'tipo' => 'percepciones',
                'fecha_retencion' => $fechaIso,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($fila['ven_nro'] ?? 0),
                'nro_cert' => 0,
                'sucursal' => (int) ($fila['ven_sucursal'] ?? 0),
                'letra' => substr(trim((string) ($fila['ven_letra'] ?? ' ')).' ', 0, 1),
                'tipo_documento' => $tipoDoc,
                'base_calculo' => abs($base),
                'importe' => $importe,
                'alicuota' => round((float) ($fila['veni_porcentaje'] ?? 0), 2),
                'nro_documento' => $this->cuitInformado(
                    (string) ($fila['clim_cuit'] ?? ''),
                    (string) ($fila['ven_cuit_cli'] ?? ''),
                ),
                'codigo_proveedor' => trim((string) ($fila['ven_cliente'] ?? '')),
                'razon_social' => $this->razonSocialInformada(
                    (string) ($fila['clim_nombre'] ?? ''),
                    (string) ($fila['ven_nombre_cliente'] ?? ''),
                ),
                'referencia' => sprintf(
                    'Perc.IIBB Anita %s %s %s-%08d',
                    $tipo,
                    (string) ($fila['ven_letra'] ?? ''),
                    str_pad((string) ((int) ($fila['ven_sucursal'] ?? 0)), 4, '0', STR_PAD_LEFT),
                    (int) ($fila['ven_nro'] ?? 0),
                ),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeVentaImpuestoErp(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Iibb_Presentacion_Config $config,
        Provincia $provincia,
    ): array {
        $nombreProv = trim((string) $provincia->nombre);
        $fragmentos = array_values(array_filter([
            $nombreProv,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'Buenos Aires' : null,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'BAI' : null,
            IngresosBrutosProvinciaAnitaSupport::esBuenosAires($provincia) ? 'ARBA' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'CABA' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'Capital Federal' : null,
            IngresosBrutosProvinciaAnitaSupport::esCaba($provincia) ? 'AGIP' : null,
        ]));

        $ventas = Venta::query()
            ->select('venta.*')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$fechaDesde, $fechaHasta])
            ->with(['venta_impuestos', 'clientes', 'tipotransacciones', 'puntoventas'])
            ->orderBy('venta.fecha')
            ->orderBy('venta.id')
            ->get();

        $out = [];
        foreach ($ventas as $venta) {
            $signo = SicoreVentaImpuestoSupport::signoVenta($venta);
            $coef = SicoreVentaImpuestoSupport::coefMoneda($venta);
            foreach ($venta->venta_impuestos as $imp) {
                $concepto = trim((string) $imp->concepto);
                if (! $this->esPercepcionProvincia($concepto, $fragmentos)) {
                    continue;
                }
                $importe = round((float) $imp->importe * $signo * $coef, 2);
                if (abs($importe) < 0.001) {
                    continue;
                }
                $cliente = $venta->clientes;
                $tipoComp = (string) ($venta->tipotransacciones?->codigo ?? '01');
                $tipoDoc = match ($tipoComp) {
                    '02' => 'D',
                    '03' => 'C',
                    default => 'F',
                };
                $alicuota = 0.0;
                if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/', $concepto, $m)) {
                    $alicuota = (float) str_replace(',', '.', $m[1]);
                }

                $out[] = [
                    'origen' => 'venta_impuesto',
                    'iibb_config_id' => (int) $config->id,
                    'tipo' => 'percepciones',
                    'fecha_retencion' => (string) $venta->fecha,
                    'fecha_comp' => (string) $venta->fecha,
                    'nro_comp' => (int) ($venta->numerocomprobante ?? 0),
                    'nro_cert' => 0,
                    'sucursal' => (int) ($venta->puntoventas?->codigo ?? 0),
                    'letra' => 'A',
                    'tipo_documento' => $tipoDoc,
                    'base_calculo' => round(abs((float) ($venta->total ?? 0)) * $coef, 2),
                    'importe' => $importe,
                    'alicuota' => $alicuota,
                    'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit(
                        (string) ($cliente?->numerodocumento ?? $venta->nroinscripcion ?? '')
                    ),
                    'codigo_proveedor' => (string) ($cliente?->codigo ?? ''),
                    'razon_social' => substr(trim((string) ($cliente?->nombre ?? $venta->nombre ?? '')), 0, 30),
                    'referencia' => sprintf('Perc.IIBB ERP %s', $concepto),
                    'venta_id' => (int) $venta->id,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $fragmentos
     */
    private function esPercepcionProvincia(string $concepto, array $fragmentos): bool
    {
        if (stripos($concepto, 'Perc') === false && stripos($concepto, 'Percep') === false) {
            return false;
        }
        if (stripos($concepto, 'IVA') !== false) {
            return false;
        }
        foreach ($fragmentos as $frag) {
            if ($frag !== '' && stripos($concepto, $frag) !== false) {
                return true;
            }
        }

        return stripos($concepto, 'IIBB') !== false || stripos($concepto, 'Ing. Bruto') !== false;
    }

    /**
     * El maestro (climae) manda. Si la factura no tiene cliente en el ABM,
     * el CUIT queda en la cabecera (ven_cuit_cli).
     */
    private function cuitInformado(string $maestro, string $comprobante): string
    {
        if ($this->cuitUtil($maestro)) {
            return IngresosBrutosFormatoArbaSupport::normalizarCuit($maestro);
        }
        if ($this->cuitUtil($comprobante)) {
            return IngresosBrutosFormatoArbaSupport::normalizarCuit($comprobante);
        }

        return IngresosBrutosFormatoArbaSupport::normalizarCuit($maestro);
    }

    private function cuitUtil(string $cuit): bool
    {
        $digits = preg_replace('/\D/', '', $cuit) ?? '';

        return strlen($digits) === 11 && $digits !== '00000000000';
    }

    /**
     * Misma prioridad que el CUIT: ficha del cliente y, si no hay, el nombre de la factura.
     */
    private function razonSocialInformada(string $maestro, string $comprobante): string
    {
        $maestro = trim($maestro);
        $nombre = $maestro !== '' ? $maestro : trim($comprobante);

        return substr($nombre, 0, 30);
    }

    private function tipoDocumentoArba(string $tipoCompAnita): string
    {
        $t = strtoupper(trim($tipoCompAnita));
        if (str_starts_with($t, 'ND') || $t === '02') {
            return 'D';
        }
        if (str_starts_with($t, 'NC') || $t === '03') {
            return 'C';
        }

        return 'F';
    }
}
