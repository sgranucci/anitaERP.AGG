<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita che_ban (pago / auxpag / propago / promae) al estilo p-pagoxbanco.c.
 */
final class InterbankingArchivoPagoAnitaReader
{
    private const PAGO_CAMPOS = 'pag_empresa,pag_fecha,pag_tipo,pag_rec,pag_sucursal,pag_pro,pag_leyenda';

    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_nro,axp_tipo_ap,axp_monto_ap,'
        .'axp_sucursal,axp_empresa,axp_cbu';

    private const PROPAGO_CAMPOS = 'prop_proveedor,prop_cbu,prop_forma_pago';

    private const PROMAE_CAMPOS = 'prom_proveedor,prom_nombre';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarPagos(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        string $tipoOp,
        int $opDesde,
        int $opHasta,
        array &$errores,
    ): array {
        $where = ' WHERE pag_empresa='.$empresaAnita
            .' AND pag_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd
            .' AND pag_rec BETWEEN '.$opDesde.' AND '.$opHasta;

        if ($tipoOp !== '' && $tipoOp !== '0') {
            $tipo = addslashes(substr($tipoOp, 0, 3));
            $where .= " AND pag_tipo = '".$tipo."'";
        } else {
            $where .= " AND pag_tipo LIKE 'OP%'";
        }

        return $this->listar('che_ban', 'pago', self::PAGO_CAMPOS, $where, $errores, 'pago-ib-archivo');
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    public function listarAuxpagPeriodo(
        int $empresaAnita,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        array &$errores,
    ): array {
        $where = ' WHERE axp_empresa='.$empresaAnita
            .' AND axp_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd;

        return $this->listar('che_ban', 'auxpag', self::AUXPAG_CAMPOS, $where, $errores, 'auxpag-ib-archivo');
    }

    /**
     * Último CBU no vacío de propago por código proveedor (6 dígitos Anita).
     *
     * @param  list<string>  $codigosProveedor
     * @param  list<string>  $errores
     * @return array<string, string> codigo_padded => cbu
     */
    public function mapaCbuPropago(array $codigosProveedor, array &$errores): array
    {
        $codigos = [];
        foreach ($codigosProveedor as $cod) {
            $p = self::padProveedor($cod);
            if ($p !== '') {
                $codigos[$p] = true;
            }
        }
        if ($codigos === []) {
            return [];
        }

        $mapa = [];
        foreach (array_keys($codigos) as $cod) {
            $where = " WHERE prop_proveedor = '".addslashes($cod)."'";
            $filas = $this->listar('compras', 'propago', self::PROPAGO_CAMPOS, $where, $errores, 'propago-'.$cod);
            $cbu = '';
            foreach ($filas as $fila) {
                $raw = preg_replace('/\D+/', '', (string) ($fila->prop_cbu ?? '')) ?? '';
                if ($raw !== '') {
                    $cbu = $raw;
                }
            }
            if ($cbu !== '') {
                $mapa[$cod] = $cbu;
            }
        }

        return $mapa;
    }

    /**
     * @param  list<string>  $codigosProveedor
     * @param  list<string>  $errores
     * @return array<string, string>
     */
    public function mapaNombresPromae(array $codigosProveedor, array &$errores): array
    {
        $mapa = [];
        foreach ($codigosProveedor as $cod) {
            $p = self::padProveedor($cod);
            if ($p === '' || isset($mapa[$p])) {
                continue;
            }
            $where = " WHERE prom_proveedor = '".addslashes($p)."'";
            $filas = $this->listar('compras', 'promae', self::PROMAE_CAMPOS, $where, $errores, 'promae-'.$p);
            $nombre = '';
            foreach ($filas as $fila) {
                $nombre = trim((string) ($fila->prom_nombre ?? ''));
            }
            $mapa[$p] = $nombre !== '' ? $nombre : $p;
        }

        return $mapa;
    }

    public static function padProveedor(string|int|null $codigo): string
    {
        $c = preg_replace('/\D+/', '', (string) $codigo) ?? '';
        if ($c === '') {
            return '';
        }

        return str_pad(substr($c, -6), 6, '0', STR_PAD_LEFT);
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
        $t0 = microtime(true);
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
            Log::warning('interbanking.archivo_pago.anita', [
                'etiqueta' => $etiqueta,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError($raw);
        if ($msg !== null) {
            $errores[] = $etiqueta.': '.$msg;
            Log::info('interbanking.archivo_pago.anita', [
                'etiqueta' => $etiqueta,
                'ms' => round((microtime(true) - $t0) * 1000, 1),
                'error' => $msg,
            ]);

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas($raw);
        Log::info('interbanking.archivo_pago.anita', [
            'etiqueta' => $etiqueta,
            'ms' => round((microtime(true) - $t0) * 1000, 1),
            'filas' => count($filas),
        ]);

        return $filas;
    }
}
