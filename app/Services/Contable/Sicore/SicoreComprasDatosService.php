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
use App\Support\Contable\Sicore\SicoreProveedorErpSupport;

final class SicoreComprasDatosService
{
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
     * @return list<array<string, mixed>>
     */
    private function desdeRetmov(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        $filasRaw = $this->listarRetmov($empresaId, $fechaDesde, $fechaHasta);
        $this->proveedorSupport->precargar(array_map(
            static fn (array $row) => (string) ($row['retv_proveedor'] ?? ''),
            array_map(static fn ($row) => (array) $row, $filasRaw),
        ));

        $regimenPorCodigo = $this->mapaRegimenGanancias();
        $out = [];

        foreach ($filasRaw as $row) {
            $row = (array) $row;
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

            $fechaAnita = (int) ($row['retv_fecha'] ?? 0);
            $fechaIso = $this->anitaAFechaIso($fechaAnita);
            $pagoActual = round((float) ($row['retv_pago_actual'] ?? 0) * $signo, 2);
            $base = $signo < 0 ? abs($retencion) : abs($pagoActual);

            $out[] = [
                'origen' => 'compras_ganancias',
                'sicore_config_id' => (int) $config->id,
                'cod_regimen' => $regimen,
                'cod_impuesto' => (int) $config->codigo_impuesto,
                'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
                'cod_comp' => strncmp((string) ($row['retv_tipo'] ?? ''), 'AOP', 3) === 0 ? 3 : 6,
                'fecha_comp' => $fechaIso,
                'nro_comp' => (int) ($row['retv_nro'] ?? 0),
                'importe_comp' => abs($pagoActual),
                'base_calculo' => abs($base),
                'fecha_retencion' => $fechaIso,
                'cod_condicion' => $proveedor['cod_condicion'],
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

        return $out;
    }

    /**
     * @return list<object|array<string, mixed>>
     */
    private function listarRetmov(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);

        $api = new ApiAnita();

        return ApiAnita::decodificarListaFilas($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'retmov',
            'campos' => implode(', ', [
                'retv_proveedor', 'retv_tipo', 'retv_letra', 'retv_sucursal', 'retv_nro',
                'retv_fecha', 'retv_codigo_ret', 'retv_pago_actual', 'retv_retencion',
                'retv_nro_retencion', 'retv_nombre_prov', 'retv_cuit_prov', 'retv_porc_excl',
                'retv_empresa',
            ]),
            'whereArmado' => ' WHERE retv_fecha >= '.$desdeAnita
                .' AND retv_fecha <= '.$hastaAnita
                .' AND retv_empresa = '.$empresaAnita
                .' AND retv_retencion <> 0',
            'orderBy' => 'retv_fecha, retv_proveedor, retv_nro_retencion',
        ]));
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
