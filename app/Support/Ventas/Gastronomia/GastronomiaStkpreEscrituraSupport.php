<?php

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Alta/actualización de precios en Anita (stkpre) para listas de costo mensual (5000 + mes).
 */
final class GastronomiaStkpreEscrituraSupport
{
    private const TABLA = 'stkpre';

    private const LONGITUD_CODIGO = 13;

    public function __construct(
        private readonly ApiAnita $apiAnita,
    ) {}

    public function codigoAnitaDesdeSku(string $sku): string
    {
        return str_pad(trim($sku), self::LONGITUD_CODIGO, '0', STR_PAD_LEFT);
    }

    /**
     * Inserta o actualiza stkp_precio para artículo + lista.
     */
    public function upsertPrecio(
        string $sku,
        string $codigoLista,
        float $precio,
        ?float $precioAnterior,
        int $monedaId,
        string $fechaVigenciaYmd,
        string $usuarioNombre,
    ): void {
        $codigoArticulo = $this->codigoAnitaDesdeSku($sku);
        $codigoLista = trim($codigoLista);
        $fechaAnita = str_replace('-', '', $fechaVigenciaYmd);
        $usuario = str_replace("'", "''", trim($usuarioNombre));
        $precioAnteriorSql = $precioAnterior !== null ? (string) $precioAnterior : '0';
        $monedaId = max(1, $monedaId);

        $existe = $this->existeFila($codigoArticulo, $codigoLista);

        if ($existe) {
            $payload = [
                'acc' => 'update',
                'tabla' => self::TABLA,
                'valores' => " stkp_precio = '{$precio}', stkp_precio_ant = '{$precioAnteriorSql}', "
                    ."stkp_cod_mon = '{$monedaId}', stkp_fe_ult_act = '{$fechaAnita}', "
                    ."stkp_usuario = '{$usuario}', stkp_terminal = 'www' ",
                'whereArmado' => " WHERE stkp_articulo = '{$codigoArticulo}' AND stkp_lista = '{$codigoLista}' ",
            ];
        } else {
            $payload = [
                'acc' => 'insert',
                'tabla' => self::TABLA,
                'campos' => ' stkp_articulo, stkp_lista, stkp_precio, stkp_precio_ant, stkp_cod_mon, stkp_fe_ult_act, stkp_usuario, stkp_terminal ',
                'valores' => " '{$codigoArticulo}', '{$codigoLista}', '{$precio}', '{$precioAnteriorSql}', "
                    ."'{$monedaId}', '{$fechaAnita}', '{$usuario}', 'www' ",
            ];
        }

        try {
            $respuesta = $this->apiAnita->apiCallEscritura($payload);
        } catch (\Throwable $e) {
            Log::warning('GastronomiaStkpreEscritura: error ApiAnita', [
                'sku' => $sku,
                'lista' => $codigoLista,
                'exception' => $e,
            ]);

            throw new \RuntimeException('No se pudo grabar costo en Anita (stkpre) para SKU '.$sku.'.', 0, $e);
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            throw new \RuntimeException(
                'Respuesta inválida al grabar stkpre para SKU '.$sku.': '.substr((string) $respuesta, 0, 200)
            );
        }
    }

    private function existeFila(string $codigoArticulo, string $codigoLista): bool
    {
        $articuloSql = str_replace("'", "''", $codigoArticulo);
        $listaSql = str_replace("'", "''", $codigoLista);

        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => 'stkp_articulo',
            'whereArmado' => " WHERE stkp_articulo = '{$articuloSql}' AND stkp_lista = '{$listaSql}' ",
        ];

        try {
            $respuesta = $this->apiAnita->apiCall($payload);
        } catch (\Throwable $e) {
            Log::warning('GastronomiaStkpreEscritura: error consulta existencia', ['exception' => $e]);

            return false;
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            return false;
        }

        $filas = json_decode((string) $respuesta, true);

        return is_array($filas) && count($filas) > 0;
    }

    public static function fechaVigenciaYmdDesdeCarbon(Carbon $fecha): string
    {
        return $fecha->toDateString();
    }
}
