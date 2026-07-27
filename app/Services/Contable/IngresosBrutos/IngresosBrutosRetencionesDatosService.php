<?php

declare(strict_types=1);

namespace App\Services\Contable\IngresosBrutos;

use App\ApiAnita;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Contable\Iibb_Presentacion_Config;
use App\Models\Configuracion\Provincia;
use App\Support\Contable\IngresosBrutos\IngresosBrutosFormatoArbaSupport;
use App\Support\Contable\IngresosBrutos\IngresosBrutosProvinciaAnitaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones IIBB — Anita retibrmov (opción 7) + ERP pagoproveedor_retencion tipo B.
 */
final class IngresosBrutosRetencionesDatosService
{
    public function __construct(
        private readonly SicoreProveedorErpSupport $proveedorSupport = new SicoreProveedorErpSupport(),
    ) {
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
        $anita = $this->desdeRetibrmovAnita($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
        if ($anita !== []) {
            return $anita;
        }

        return $this->desdePagoproveedorErp($empresaId, $fechaDesde, $fechaHasta, $config, $provincia);
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
        $codigosProv = IngresosBrutosProvinciaAnitaSupport::codigosAnita($provincia);
        if ($codigosProv === []) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);
        $provIn = implode(',', array_map('intval', $codigosProv));

        // Columnas reales Informix (retibrmov.sql): retibr_nro_ret / retibr_porc_ret
        // (el C usa alias retibr_nro_retencion / retibr_porc_retencion vía .def).
        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retibrmov',
            'campos' => implode(', ', [
                'retibr_proveedor', 'retibr_tipo', 'retibr_letra', 'retibr_sucursal', 'retibr_nro',
                'retibr_fecha', 'retibr_sujeto', 'retibr_retencion', 'retibr_porc_ret',
                'retibr_nro_ret', 'retibr_provincia', 'retibr_empresa',
            ]),
            'whereArmado' => ' WHERE retibr_fecha >= '.$desdeAnita
                .' AND retibr_fecha <= '.$hastaAnita
                .' AND retibr_empresa = '.$empresaAnita
                .' AND retibr_retencion <> 0'
                .' AND retibr_provincia IN ('.$provIn.')',
            'orderBy' => 'retibr_fecha, retibr_nro_ret, retibr_proveedor',
        ]));

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
            $agrupados[$clave]['sujeto'] += (float) ($fila['retibr_sujeto'] ?? 0);
            $agrupados[$clave]['retencion'] += (float) ($fila['retibr_retencion'] ?? 0);
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

        return $out;
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
                'base_calculo' => round(abs((float) $ret->base_calculo), 2),
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
}
