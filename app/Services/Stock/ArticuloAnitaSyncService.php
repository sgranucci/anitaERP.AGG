<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Stock\ArticuloStkmaeAnitaBridgeSupport;
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

    /**
     * Actualiza solo articulo.vencimientoendia desde stkmae.stkm_vto_en_dias.
     * Sin --sku: todos los artículos ERP que existan en Anita.
     *
     * @return array{en_anita:int, actualizados:int, sin_cambio:int, no_encontrados_erp:int, advertencias:list<string>}
     */
    public function sincronizarVencimientoEnDiasDesdeAnita(?string $sku = null, ?int $empresaId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $advertencias = [];
        $sku = is_string($sku) ? trim($sku) : '';

        if ($sku !== '') {
            $codigo = $this->resolverCodigoAnitaPorSku($sku, $empresaId);
            if ($codigo === null) {
                throw new \RuntimeException("Artículo «{$sku}» no encontrado en Anita (stkmae).");
            }
            $filas = ArticuloStkmaeAnitaBridgeSupport::listarDetallePorCodigos([$codigo], $empresaId);

            return $this->aplicarVencimientoEnDiasDesdeFilasAnita($filas, $advertencias);
        }

        $apiAnita = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => 'stkmae',
            'campos' => 'stkm_articulo, stkm_vto_en_dias',
        ];
        if ($empresaId !== null && $empresaId > 0) {
            $payload = StockAnitaBridgeSupport::mergePayload($payload, $empresaId);
        }
        $respuesta = $apiAnita->apiCall($payload);
        $filas = ApiAnita::decodificarListaFilas(is_string($respuesta) ? $respuesta : null);
        if ($filas === []) {
            $decoded = json_decode((string) $respuesta);
            $filas = is_array($decoded) ? $decoded : [];
        }
        if ($filas === []) {
            return [
                'en_anita' => 0,
                'actualizados' => 0,
                'sin_cambio' => 0,
                'no_encontrados_erp' => 0,
                'advertencias' => ['Anita no devolvió filas de stkmae (stkm_vto_en_dias).'],
            ];
        }

        return $this->aplicarVencimientoEnDiasDesdeFilasAnita($filas, $advertencias);
    }

    /**
     * @param  list<object>  $filas
     * @param  list<string>  $advertencias
     * @return array{en_anita:int, actualizados:int, sin_cambio:int, no_encontrados_erp:int, advertencias:list<string>}
     */
    private function aplicarVencimientoEnDiasDesdeFilasAnita(array $filas, array $advertencias): array
    {
        $actualizados = 0;
        $sinCambio = 0;
        $noEncontrados = 0;

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->stkm_articulo ?? ''));
            if ($codigo === '') {
                continue;
            }
            $skuLocal = ltrim($codigo, '0');
            if ($skuLocal === '') {
                continue;
            }

            $dias = (int) ($fila->stkm_vto_en_dias ?? 0);
            if ($dias < 0) {
                $dias = 0;
            }

            $articulo = ArticuloSkuMatchSupport::resolverCanonico($skuLocal);
            if ($articulo === null) {
                $noEncontrados++;

                continue;
            }

            $actual = (int) ($articulo->vencimientoendia ?? 0);
            if ($actual === $dias) {
                $sinCambio++;

                continue;
            }

            $articulo->update(['vencimientoendia' => $dias]);
            $actualizados++;
        }

        return [
            'en_anita' => count($filas),
            'actualizados' => $actualizados,
            'sin_cambio' => $sinCambio,
            'no_encontrados_erp' => $noEncontrados,
            'advertencias' => $advertencias,
        ];
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
