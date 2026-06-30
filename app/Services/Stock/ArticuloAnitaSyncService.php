<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Stock\StockAnitaBridgeSupport;

/**
 * Altas y actualizaciones de artículos en el ERP desde Anita (stkmae) vía {@see ApiAnita}.
 *
 * @see Articulo::sincronizarConAnita()
 * @see Articulo::traerRegistroDeAnita()
 */
class ArticuloAnitaSyncService
{
    /**
     * @return array{en_anita:int, importados:int, omitidos_ya_en_erp:int, advertencias:list<string>}
     */
    public function sincronizarDesdeAnita(): array
    {
        return (new Articulo)->sincronizarConAnita();
    }

    /**
     * @return array{en_anita:int, importados:int, actualizados:int, errores:int, advertencias:list<string>}
     */
    public function resincronizarDesdeAnita(): array
    {
        return (new Articulo)->resincronizarDesdeAnita();
    }

    /**
     * Importa o actualiza un artículo por SKU ERP (ej. V0421) desde Anita.
     *
     * @param  int|null  $empresaId  Bridge por empresa (2=Kandiko, 3=Rebisco); null = bridge central Biyemas.
     * @return array{sku:string, codigo_anita:string, accion:'importado'|'actualizado', advertencias:list<string>}
     */
    public function sincronizarSkuDesdeAnita(string $sku, ?int $empresaId = null): array
    {
        $sku = trim($sku);
        if ($sku === '') {
            throw new \InvalidArgumentException('Debe indicar el SKU del artículo.');
        }

        $codigoAnita = $this->resolverCodigoAnitaPorSku($sku, $empresaId);
        if ($codigoAnita === null) {
            $ctx = ($empresaId !== null && $empresaId > 0) ? " (bridge empresa {$empresaId})" : '';
            throw new \RuntimeException("Artículo «{$sku}» no encontrado en Anita (stkmae){$ctx}.");
        }

        $skuLocal = ltrim($codigoAnita, '0');
        $existe = ArticuloSkuMatchSupport::existe($skuLocal);
        $accion = $existe ? 'actualizado' : 'importado';

        (new Articulo)->traerRegistroDeAnita($codigoAnita, ! $existe, $empresaId);

        $canonico = ArticuloSkuMatchSupport::resolverCanonico($skuLocal);
        if ($canonico === null) {
            throw new \RuntimeException("No se pudo importar/actualizar «{$sku}» desde Anita.");
        }

        $inactivados = ArticuloSkuMatchSupport::inactivarDuplicados($skuLocal, (int) $canonico->id);
        $advertencias = [];
        if ($inactivados !== []) {
            $advertencias[] = 'Duplicados por SKU inactivados (ids: '.implode(', ', $inactivados).').';
        }

        return [
            'sku' => (string) $canonico->fresh()->sku,
            'codigo_anita' => $codigoAnita,
            'accion' => $accion,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Resuelve stkm_articulo en Anita (13 caracteres con ceros) a partir del SKU ERP.
     */
    public function resolverCodigoAnitaPorSku(string $sku, ?int $empresaId = null): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        $candidatos = array_values(array_unique(array_filter([
            str_pad($sku, 13, '0', STR_PAD_LEFT),
            str_pad(strtoupper($sku), 13, '0', STR_PAD_LEFT),
            str_pad(strtolower($sku), 13, '0', STR_PAD_LEFT),
        ])));

        $apiAnita = new ApiAnita;
        foreach ($candidatos as $codigo) {
            if ($this->existeEnStkmae($apiAnita, $codigo, $empresaId)) {
                return $codigo;
            }
        }

        $skuEsc = addslashes($sku);
        $data = [
            'acc' => 'list',
            'tabla' => 'stkmae',
            'campos' => 'stkm_articulo',
            'whereArmado' => " WHERE stkm_articulo LIKE '%{$skuEsc}' ",
        ];
        if ($empresaId !== null && $empresaId > 0) {
            $data = StockAnitaBridgeSupport::mergePayload($data, $empresaId);
        }
        $res = json_decode($apiAnita->apiCall($data));
        if (! is_array($res) || $res === []) {
            return null;
        }

        $skuNorm = strtoupper($sku);
        foreach ($res as $row) {
            $codigo = trim((string) ($row->stkm_articulo ?? ''));
            if ($codigo !== '' && strtoupper(ltrim($codigo, '0')) === $skuNorm) {
                return $codigo;
            }
        }

        return trim((string) ($res[0]->stkm_articulo ?? '')) ?: null;
    }

    private function existeEnStkmae(ApiAnita $apiAnita, string $codigo, ?int $empresaId = null): bool
    {
        $data = [
            'acc' => 'list',
            'tabla' => 'stkmae',
            'campos' => 'stkm_articulo',
            'whereArmado' => " WHERE stkm_articulo = '".addslashes($codigo)."' ",
        ];
        if ($empresaId !== null && $empresaId > 0) {
            $data = StockAnitaBridgeSupport::mergePayload($data, $empresaId);
        }
        $res = json_decode($apiAnita->apiCall($data));

        return is_array($res) && count($res) > 0;
    }
}
