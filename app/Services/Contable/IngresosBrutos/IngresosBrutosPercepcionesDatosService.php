<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\ApiAnita;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Venta;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreVentaImpuestoSupport;

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

        // Preferir Anita si hay datos; ERP complementa cuando no hay venibr (ventas solo ERP).
        if ($anita !== []) {
            return $anita;
        }

        return $erp;
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

        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => 'venta, climae, venibr',
            'campos' => implode(', ', [
                'ven_fecha', 'ven_tipo', 'ven_letra', 'ven_sucursal', 'ven_nro',
                'ven_cliente', 'ven_empresa', 'ven_gravado', 'ven_gravado_ot',
                'ven_cod_mon', 'ven_cotizacion',
                'clim_nombre', 'clim_cuit',
                'veni_provincia', 'veni_porcentaje', 'veni_importe',
            ]),
            'whereArmado' => ' WHERE ven_cliente=clim_cliente'
                .' AND veni_tipo=ven_tipo AND veni_letra=ven_letra'
                .' AND veni_sucursal=ven_sucursal AND veni_nro=ven_nro'
                .' AND ven_fecha >= '.$desdeAnita
                .' AND ven_fecha <= '.$hastaAnita
                .' AND ven_empresa = '.$empresaAnita
                .' AND veni_provincia IN ('.$provIn.')'
                .' AND veni_importe <> 0',
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
                'nro_documento' => IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($fila['clim_cuit'] ?? '')),
                'codigo_proveedor' => trim((string) ($fila['ven_cliente'] ?? '')),
                'razon_social' => substr(trim((string) ($fila['clim_nombre'] ?? '')), 0, 30),
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
        ]));

        $ventas = Venta::query()
            ->select('venta.*')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->whereBetween('venta.fecha', [$fechaDesde, $fechaHasta])
            ->whereNull('venta.deleted_at')
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
