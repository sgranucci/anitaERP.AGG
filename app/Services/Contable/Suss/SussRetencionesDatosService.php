<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\ApiAnita;
use App\Models\Compras\Pagoproveedor_Retencion;
use App\Models\Compras\Retencionsuss;
use App\Models\Contable\Suss_Presentacion_Config;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;
use App\Support\Contable\Suss\SussFormatoF2004Support;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones SUSS — Anita retsmov (p-retsmov.c) + ERP pagoproveedor_retencion tipo S.
 */
final class SussRetencionesDatosService
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
        Suss_Presentacion_Config $config,
    ): array {
        $anita = $this->desdeRetsmovAnita($empresaId, $fechaDesde, $fechaHasta, $config);
        if ($anita !== []) {
            return $anita;
        }

        return $this->desdePagoproveedorErp($empresaId, $fechaDesde, $fechaHasta, $config);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desdeRetsmovAnita(
        int $empresaId,
        string $fechaDesde,
        string $fechaHasta,
        Suss_Presentacion_Config $config,
    ): array {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);

        // Columnas Informix (análogo retibrmov): nro_ret / porc_ret cortos.
        // El C (p-retsmov) usa alias retsv_nro_retencion vía .def.
        $api = new ApiAnita();
        $filas = ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retsmov',
            'campos' => implode(', ', [
                'retsv_proveedor', 'retsv_tipo', 'retsv_letra', 'retsv_sucursal', 'retsv_nro',
                'retsv_fecha', 'retsv_gravado', 'retsv_retencion', 'retsv_nro_ret',
                'retsv_codigo_ret', 'retsv_empresa',
            ]),
            'whereArmado' => ' WHERE retsv_fecha >= '.$desdeAnita
                .' AND retsv_fecha <= '.$hastaAnita
                .' AND retsv_empresa = '.$empresaAnita
                .' AND retsv_retencion <> 0',
            'orderBy' => 'retsv_fecha, retsv_nro_ret, retsv_proveedor',
        ]));

        if ($filas === []) {
            return [];
        }

        $regimenPorCodigo = $this->mapaRegimenErp();
        $regimenDefault = (int) ($config->codigo_regimen ?? 0);

        $agrupados = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $nroRet = (int) ($fila['retsv_nro_ret'] ?? $fila['retsv_nro_retencion'] ?? 0);
            $clave = $nroRet.'|'.trim((string) ($fila['retsv_proveedor'] ?? ''));
            if (! isset($agrupados[$clave])) {
                $codRet = trim((string) ($fila['retsv_codigo_ret'] ?? ''));
                $agrupados[$clave] = [
                    'proveedor' => trim((string) ($fila['retsv_proveedor'] ?? '')),
                    'fecha' => (int) ($fila['retsv_fecha'] ?? 0),
                    'nro_ret' => $nroRet,
                    'nro_comp' => (int) ($fila['retsv_nro'] ?? 0),
                    'codigo_ret' => $codRet,
                    'gravado' => 0.0,
                    'retencion' => 0.0,
                ];
            }
            $agrupados[$clave]['gravado'] += (float) ($fila['retsv_gravado'] ?? 0);
            $agrupados[$clave]['retencion'] += (float) ($fila['retsv_retencion'] ?? 0);
        }

        $this->proveedorSupport->precargar(array_column($agrupados, 'proveedor'));

        $out = [];
        foreach ($agrupados as $grupo) {
            if (abs($grupo['retencion']) < 0.001) {
                continue;
            }
            $proveedor = $this->proveedorSupport->resolverDesdeFila(
                ['retsv_proveedor' => $grupo['proveedor']],
                'retsv_proveedor',
                '',
                '',
            );
            $fechaIso = SussFormatoF2004Support::fechaIsoDesdeAnita((int) $grupo['fecha']);
            $regimen = $regimenPorCodigo[$grupo['codigo_ret']] ?? $regimenDefault;
            $importe = round((float) $grupo['retencion'], 2);
            $base = round((float) $grupo['gravado'], 2);
            if ($base < abs($importe)) {
                $base = abs($importe);
            }

            $out[] = [
                'origen' => 'retsmov',
                'suss_config_id' => (int) $config->id,
                'fecha_retencion' => $fechaIso,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($grupo['nro_comp'] ?: $grupo['nro_ret']),
                'nro_comprobante' => (string) ($grupo['nro_comp'] ?: $grupo['nro_ret']),
                'nro_cert' => (int) $grupo['nro_ret'],
                'tipo_comprobante' => SussFormatoF2004Support::TIPO_COMPROBANTE_ORDEN_PAGO,
                'codigo_regimen' => (int) $regimen,
                'base_calculo' => $base,
                'importe_comprobante' => $base,
                'importe' => $importe,
                'nro_documento' => SussFormatoF2004Support::normalizarCuit((string) ($proveedor['cuit'] ?? '')),
                'codigo_proveedor' => (string) ($proveedor['codigo_proveedor'] ?? $grupo['proveedor']),
                'razon_social' => substr((string) ($proveedor['nombre'] ?? ''), 0, 30),
                'referencia' => 'Ret.SUSS '.$grupo['proveedor'],
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
        Suss_Presentacion_Config $config,
    ): array {
        if (! Schema::hasTable('pagoproveedor_retencion')) {
            return [];
        }

        $filas = Pagoproveedor_Retencion::query()
            ->where('tiporetencion', Pagoproveedor_Retencion::TIPO_SUSS)
            ->where('importe', '!=', 0)
            ->whereHas('pagoproveedores', function ($q) use ($empresaId, $fechaDesde, $fechaHasta) {
                $q->where('empresa_id', $empresaId)
                    ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                    ->where('estado', 'CONFIRMADA');
            })
            ->with(['pagoproveedores.proveedores', 'retencionsusss'])
            ->get();

        $regimenDefault = (int) ($config->codigo_regimen ?? 0);
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
            $base = round(abs((float) $ret->base_calculo), 2);
            if ($base < abs($importe)) {
                $base = abs($importe);
            }
            $regimen = (int) preg_replace('/\D+/', '', (string) ($ret->codigo_regimen ?: ($ret->retencionsusss?->regimen ?? '')));
            if ($regimen <= 0) {
                $regimen = $regimenDefault;
            }
            $nroOp = (int) ($pago->numerotransaccion ?? 0);

            $out[] = [
                'origen' => 'pagoproveedor_erp',
                'suss_config_id' => (int) $config->id,
                'fecha_retencion' => $fecha,
                'fecha_comp' => $fecha,
                'nro_comp' => $nroOp,
                'nro_comprobante' => (string) $nroOp,
                'nro_cert' => $nroCert,
                'tipo_comprobante' => SussFormatoF2004Support::TIPO_COMPROBANTE_ORDEN_PAGO,
                'codigo_regimen' => $regimen,
                'base_calculo' => $base,
                'importe_comprobante' => $base,
                'importe' => $importe,
                'nro_documento' => SussFormatoF2004Support::normalizarCuit((string) ($prov->nroinscripcion ?? '')),
                'codigo_proveedor' => (string) ($prov->codigo ?? ''),
                'razon_social' => substr((string) ($prov->nombre ?? ''), 0, 30),
                'referencia' => 'Ret.SUSS ERP '.($prov->codigo ?? '').' '.$pago->etiquetaComprobante(),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int> codigo retencionsuss → régimen AFIP
     */
    private function mapaRegimenErp(): array
    {
        if (! Schema::hasTable('retencionsuss')) {
            return [];
        }

        $map = [];
        foreach (Retencionsuss::query()->get(['codigo', 'regimen']) as $row) {
            $cod = trim((string) $row->codigo);
            $reg = (int) preg_replace('/\D+/', '', (string) ($row->regimen ?? ''));
            if ($cod !== '' && $reg > 0) {
                $map[$cod] = $reg;
                $map[ltrim($cod, '0') ?: '0'] = $reg;
            }
        }

        return $map;
    }
}
