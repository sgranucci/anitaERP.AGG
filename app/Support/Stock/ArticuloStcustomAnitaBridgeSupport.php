<?php

namespace App\Support\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use Illuminate\Support\Facades\Log;

/**
 * Replica stcustom.desc_det (Anita bridge) desde articulo.detalle en anitaERP.
 */
final class ArticuloStcustomAnitaBridgeSupport
{
    public const CAMPO_DESC_DET = 'desc_det';

    /**
     * @return array{ok: bool, sku: string, clave: string, valor: string, errores: list<string>}
     */
    public static function replicarDescDet(Articulo $articulo, ?ApiAnita $apiAnita = null): array
    {
        $apiAnita ??= new ApiAnita;
        $clave = self::claveAnita($articulo->sku);
        $valor = trim((string) ($articulo->detalle ?? ''));
        $error = self::grabaCampo($apiAnita, $clave, self::CAMPO_DESC_DET, $valor);

        return [
            'ok' => $error === null,
            'sku' => (string) $articulo->sku,
            'clave' => $clave,
            'valor' => $valor,
            'errores' => $error === null ? [] : [$error],
        ];
    }

    /**
     * @return array{total: int, ok: int, fallos: int, detalle_fallos: list<string>}
     */
    public static function replicarDescDetPorPrefijoSku(string $prefijoSku): array
    {
        $prefijo = strtoupper(trim($prefijoSku));
        if ($prefijo === '') {
            throw new \InvalidArgumentException('El prefijo SKU no puede estar vacío.');
        }

        $apiAnita = new ApiAnita;
        $articulos = Articulo::query()
            ->where('sku', 'like', $prefijo.'%')
            ->orderBy('sku')
            ->get(['id', 'sku', 'detalle']);

        $ok = 0;
        $fallos = 0;
        $detalleFallos = [];

        foreach ($articulos as $articulo) {
            $resultado = self::replicarDescDet($articulo, $apiAnita);
            if ($resultado['ok']) {
                $ok++;
                continue;
            }

            $fallos++;
            $detalleFallos[] = $resultado['sku'].': '.implode('; ', $resultado['errores']);
            Log::warning('articulo.stcustom_anita.desc_det_fallo', $resultado);
        }

        return [
            'total' => $articulos->count(),
            'ok' => $ok,
            'fallos' => $fallos,
            'detalle_fallos' => $detalleFallos,
        ];
    }

    /**
     * @param  array<string, string>  $campos  idcampo => valor
     * @return array{total: int, ok: int, fallos: int, detalle_fallos: list<string>}
     */
    public static function actualizarCamposPorPrefijoSku(string $prefijoSku, array $campos): array
    {
        $prefijo = strtoupper(trim($prefijoSku));
        if ($prefijo === '') {
            throw new \InvalidArgumentException('El prefijo SKU no puede estar vacío.');
        }

        $apiAnita = new ApiAnita;
        $skus = Articulo::query()
            ->where('sku', 'like', $prefijo.'%')
            ->orderBy('sku')
            ->pluck('sku');

        $ok = 0;
        $fallos = 0;
        $detalleFallos = [];

        foreach ($skus as $sku) {
            $clave = self::claveAnita((string) $sku);
            $errores = [];
            foreach ($campos as $idcampo => $valor) {
                $error = self::grabaCampo($apiAnita, $clave, (string) $idcampo, (string) $valor);
                if ($error !== null) {
                    $errores[] = $idcampo.': '.$error;
                }
            }

            if ($errores === []) {
                $ok++;
                continue;
            }

            $fallos++;
            $detalleFallos[] = $sku.': '.implode('; ', $errores);
            Log::warning('articulo.stcustom_anita.campos_fallo', [
                'sku' => $sku,
                'campos' => $campos,
                'errores' => $errores,
            ]);
        }

        return [
            'total' => $skus->count(),
            'ok' => $ok,
            'fallos' => $fallos,
            'detalle_fallos' => $detalleFallos,
        ];
    }

    public static function claveAnita(string $sku): string
    {
        return str_pad(trim($sku), 13, '0', STR_PAD_LEFT);
    }

    private static function grabaCampo(ApiAnita $apiAnita, string $clave, string $idcampo, string $valor): ?string
    {
        $valorSql = str_replace("'", "''", $valor);

        $data = [
            'acc' => 'list',
            'tabla' => 'stcustom',
            'sistema' => 'ventas',
            'campos' => 'clave,idcampo',
            'whereArmado' => " WHERE clave='".$clave."' AND idcampo='".$idcampo."' ",
        ];
        $existente = json_decode($apiAnita->apiCall($data), true);
        if (! is_array($existente)) {
            return 'respuesta list inválida';
        }

        if (count($existente) > 0) {
            $payload = [
                'acc' => 'update',
                'tabla' => 'stcustom',
                'sistema' => 'ventas',
                'valores' => " valor = '".$valorSql."' ",
                'whereArmado' => " WHERE clave='".$clave."' AND idcampo='".$idcampo."' ",
            ];
        } else {
            $payload = [
                'tabla' => 'stcustom',
                'acc' => 'insert',
                'sistema' => 'ventas',
                'campos' => 'clave,idcampo,valor',
                'valores' => "'".$clave."','".$idcampo."','".$valorSql."'",
            ];
        }

        try {
            $apiAnita->apiCallEscritura($payload, 'stcustom '.$idcampo, 'articulo.stcustom_anita.fallo');
        } catch (\Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }
}
