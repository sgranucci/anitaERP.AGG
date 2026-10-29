<?php

declare(strict_types=1);

namespace App\Support\Caja\Frances;

use App\ApiAnita;
use App\Support\Caja\InterbankingArchivoPagoAnitaReader;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImportEmpresaSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita de pago / auxpag / promae / propago para p-enviafrances.
 * Crown no tiene axp_empresa: el filtro de empresa no se manda en auxpag.
 */
final class FrancesArchivoPagoAnitaReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarPagos(int $fechaDesdeYmd, int $fechaHastaYmd, array &$errores): array
    {
        $empresas = PagoproveedorAnitaImportEmpresaSupport::empresasAnitaImport();
        $where = ' WHERE pag_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd;
        if ($empresas !== []) {
            $where .= ' AND pag_empresa IN ('.implode(',', $empresas).')';
        }

        return $this->listar(
            'che_ban',
            'pago',
            'pag_empresa,pag_fecha,pag_tipo,pag_rec,pag_sucursal,pag_pro,pag_letra,pag_mov_ext',
            $where,
            $errores,
            'frances-pago'
        );
    }

    /**
     * Medio de tesorería (axp_tipo_ap) de la cuenta de caja.
     * En Crown el código de la cuenta es tctes_imputacion. Se usa el tipo sin chequera (número 000):
     * en 487451/9 eso es BFM y no el de cheques (CFS).
     *
     * @param  list<string>  $errores
     */
    public function tipoAplicacionPorCodigo(string $codigoCuenta, array &$errores): string
    {
        $imputacion = trim($codigoCuenta);
        if ($imputacion === '') {
            return '';
        }

        $filas = $this->listar(
            'che_ban',
            'tctes',
            'tctes_clave,tctes_imputacion,tctes_numero,tctes_desc',
            " WHERE tctes_imputacion = '".str_replace("'", "''", $imputacion)."'",
            $errores,
            'frances-tctes'
        );

        $elegida = null;
        foreach ($filas as $fila) {
            $numero = str_pad(trim((string) ($fila->tctes_numero ?? '')), 3, '0', STR_PAD_LEFT);
            if ($numero === '000') {
                $elegida = $fila;
                break;
            }
        }

        return strtoupper(substr(trim((string) ($elegida->tctes_clave ?? '')), 0, 3));
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarAuxpag(int $fechaDesdeYmd, int $fechaHastaYmd, string $tipoAplicacion, array &$errores): array
    {
        $tipo = strtoupper(substr(trim($tipoAplicacion), 0, 3));
        $tieneEmpresa = PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa();
        $where = ' WHERE axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            ." AND axp_tipo_ap = '".addslashes($tipo)."'";
        if ($tieneEmpresa) {
            $empresas = PagoproveedorAnitaImportEmpresaSupport::empresasAnitaImport();
            if ($empresas !== []) {
                $where .= ' AND axp_empresa IN ('.implode(',', $empresas).')';
            }
        }

        $campos = $tieneEmpresa
            ? 'axp_empresa,axp_fecha,axp_tipo,axp_rec,axp_pro,axp_tipo_ap,axp_monto_ap,axp_sucursal,axp_letra_comp'
            : 'axp_fecha,axp_tipo,axp_rec,axp_pro,axp_tipo_ap,axp_monto_ap,axp_sucursal,axp_letra_comp';

        return $this->listar('che_ban', 'auxpag', $campos, $where, $errores, 'frances-auxpag');
    }

    /**
     * @param  list<string>  $codigos
     * @param  list<string>  $errores
     * @return array<string, array{nombre:string, cuit:string, cbu:string}>
     */
    public function mapaProveedores(array $codigos, array &$errores): array
    {
        $mapa = [];
        foreach ($codigos as $codigo) {
            $clave = InterbankingArchivoPagoAnitaReader::padProveedor($codigo);
            if ($clave === '' || isset($mapa[$clave])) {
                continue;
            }
            $where = " WHERE prom_proveedor = '".addslashes($clave)."'";
            $filas = $this->listar(
                'compras',
                'promae',
                'prom_proveedor,prom_nombre,prom_cuit',
                $where,
                $errores,
                'frances-promae-'.$clave
            );
            $nombre = '';
            $cuit = '';
            foreach ($filas as $fila) {
                $nombre = trim((string) ($fila->prom_nombre ?? ''));
                $cuit = (string) ($fila->prom_cuit ?? '');
            }

            $cbu = '';
            $propago = $this->listar(
                'compras',
                'propago',
                'prop_proveedor,prop_cbu',
                " WHERE prop_proveedor = '".addslashes($clave)."'",
                $errores,
                'frances-propago-'.$clave
            );
            foreach ($propago as $fila) {
                $raw = preg_replace('/\D+/', '', (string) ($fila->prop_cbu ?? '')) ?? '';
                if ($raw !== '') {
                    $cbu = $raw;
                }
            }

            $mapa[$clave] = [
                'nombre' => $nombre,
                'cuit' => $cuit,
                'cbu' => $cbu,
            ];
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        array &$errores,
        string $etiqueta,
    ): array {
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
            ]);
        } catch (\Throwable $e) {
            $errores[] = $etiqueta.': '.$e->getMessage();
            Log::warning('frances.archivo_pago.anita', ['etiqueta' => $etiqueta, 'error' => $e->getMessage()]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;

            return [];
        }

        return ApiAnita::decodificarListaFilas($raw);
    }
}
