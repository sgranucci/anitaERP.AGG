<?php

namespace App\Support\Stock;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Alta/actualización de precios de venta en Anita (stkpre).
 */
final class StkpreEscrituraSupport
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
        if ($codigoLista === '') {
            throw new \InvalidArgumentException('Código de lista Anita vacío.');
        }

        $fechaAnita = str_replace('-', '', $fechaVigenciaYmd);
        $usuario = str_replace("'", "''", trim($usuarioNombre));
        if ($usuario === '') {
            $usuario = 'www';
        }
        $precioAnteriorSql = $precioAnterior !== null ? (string) $precioAnterior : '0';
        $monedaId = max(1, $monedaId);
        $articuloSql = str_replace("'", "''", $codigoArticulo);
        $listaSql = str_replace("'", "''", $codigoLista);

        $existe = $this->existeFila($codigoArticulo, $codigoLista);

        if ($existe) {
            $payload = [
                'acc' => 'update',
                'tabla' => self::TABLA,
                'valores' => " stkp_precio = '{$precio}', stkp_precio_ant = '{$precioAnteriorSql}', "
                    ."stkp_cod_mon = '{$monedaId}', stkp_fe_ult_act = '{$fechaAnita}', "
                    ."stkp_usuario = '{$usuario}', stkp_terminal = 'www' ",
                'whereArmado' => " WHERE stkp_articulo = '{$articuloSql}' AND stkp_lista = '{$listaSql}' ",
            ];
        } else {
            $payload = [
                'acc' => 'insert',
                'tabla' => self::TABLA,
                'campos' => ' stkp_articulo, stkp_lista, stkp_precio, stkp_precio_ant, stkp_cod_mon, stkp_fe_ult_act, stkp_usuario, stkp_terminal ',
                'valores' => " '{$articuloSql}', '{$listaSql}', '{$precio}', '{$precioAnteriorSql}', "
                    ."'{$monedaId}', '{$fechaAnita}', '{$usuario}', 'www' ",
            ];
        }

        $payload = $this->mergeBridgeOpcional($payload);

        try {
            $respuesta = $this->apiAnita->apiCallEscritura($payload);
        } catch (\Throwable $e) {
            Log::warning('StkpreEscritura: error ApiAnita', [
                'sku' => $sku,
                'lista' => $codigoLista,
                'exception' => $e,
            ]);

            throw new \RuntimeException('No se pudo grabar precio en Anita (stkpre) para SKU '.$sku.'.', 0, $e);
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            throw new \RuntimeException(
                'Respuesta inválida al grabar stkpre para SKU '.$sku.': '.substr((string) $respuesta, 0, 200)
            );
        }
    }

    /**
     * @param  list<array{sku: string, precio: float, precio_anterior?: float|null}>  $items
     * @return array{ok: int, errores: list<string>}
     */
    public function upsertLote(
        array $items,
        string $codigoLista,
        int $monedaId,
        string $fechaVigenciaYmd,
        string $usuarioNombre,
        bool $cortarEnError = false,
    ): array {
        $ok = 0;
        $errores = [];

        foreach ($items as $item) {
            $sku = trim((string) ($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            try {
                $this->upsertPrecio(
                    $sku,
                    $codigoLista,
                    (float) $item['precio'],
                    array_key_exists('precio_anterior', $item) ? $item['precio_anterior'] : null,
                    $monedaId,
                    $fechaVigenciaYmd,
                    $usuarioNombre,
                );
                $ok++;
            } catch (\Throwable $e) {
                $errores[] = $sku.': '.$e->getMessage();
                if ($cortarEnError) {
                    break;
                }
            }
        }

        return ['ok' => $ok, 'errores' => $errores];
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
        $payload = $this->mergeBridgeOpcional($payload);

        try {
            $respuesta = $this->apiAnita->apiCall($payload);
        } catch (\Throwable $e) {
            Log::warning('StkpreEscritura: error consulta existencia', ['exception' => $e]);

            return false;
        }

        if ($respuesta === false || $respuesta === '' || str_contains((string) $respuesta, 'Error')) {
            return false;
        }

        $filas = json_decode((string) $respuesta, true);

        return is_array($filas) && count($filas) > 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergeBridgeOpcional(array $payload): array
    {
        $ifx = trim((string) config('stock.precio_anita_ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('stock.precio_anita_path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $sistema = trim((string) config('stock.precio_anita_sistema', ''));
        if ($sistema !== '') {
            $payload['sistema'] = $sistema;
        }

        return $payload;
    }
}
