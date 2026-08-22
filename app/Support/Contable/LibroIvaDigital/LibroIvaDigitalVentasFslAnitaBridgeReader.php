<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Ventas\MaquinaFslTipoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita venta tipo FSL del período (máquinas aún viven en Informix).
 * Fecha: ven_fecha o ven_fecha_vto según opción de jornada (paridad p-rg3685 / gastro cache).
 */
final class LibroIvaDigitalVentasFslAnitaBridgeReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listarPeriodo(
        int $empresaId,
        string $desdeYmd,
        string $hastaYmd,
        bool $porFechaJornada = false,
    ): array {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return [];
        }

        $desde = (int) str_replace('-', '', $desdeYmd);
        $hasta = (int) str_replace('-', '', $hastaYmd);
        if ($desde <= 0 || $hasta <= 0 || $hasta < $desde) {
            return [];
        }

        $campoFecha = $porFechaJornada ? 'ven_fecha_vto' : 'ven_fecha';
        // Descripción / textos al final: evita corrimiento por | en el bridge.
        $campos = implode(',', [
            'ven_empresa',
            'ven_tipo',
            'ven_letra',
            'ven_sucursal',
            'ven_nro',
            'ven_fecha',
            'ven_fecha_vto',
            'ven_monto',
            'ven_gravado',
            'ven_exento',
            'ven_impuesto1',
            'ven_cliente',
            'ven_nombre_cliente',
        ]);

        $tipo = MaquinaFslTipoSupport::ABREVIATURA;
        $where = ' WHERE ven_tipo = \''.$tipo.'\''
            .' AND ven_empresa = '.$empresaAnita
            .' AND '.$campoFecha.' >= '.$desde
            .' AND '.$campoFecha.' <= '.$hasta;

        try {
            $api = new ApiAnita();
            $filas = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'venta',
                'campos' => $campos,
                'whereArmado' => $where,
                'orderBy' => $campoFecha.', ven_sucursal, ven_nro',
            ]));
        } catch (\Throwable $e) {
            Log::warning('libro_iva_digital.fsl_anita_bridge', [
                'empresa_id' => $empresaId,
                'empresa_anita' => $empresaAnita,
                'desde' => $desde,
                'hasta' => $hasta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $resultado = [];
        foreach ($filas as $fila) {
            $resultado[] = (array) $fila;
        }

        return $resultado;
    }
}
