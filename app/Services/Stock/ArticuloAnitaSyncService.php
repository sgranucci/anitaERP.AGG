<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Unidadmedida;
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
     * Actualiza solo unidadmedida_id / unidadmedidaalternativa_id desde stkmae.
     * Primero asegura el catálogo stkumd (solo altas). No toca peso ni envase.
     *
     * @return array{
     *   dry_run: bool,
     *   en_anita: int,
     *   actualizados: int,
     *   sin_cambio: int,
     *   no_encontrados_erp: int,
     *   sin_um_anita: int,
     *   um_anita_sin_catalogo: int,
     *   catalogo_antes: int,
     *   catalogo_despues: int,
     *   cambios: list<array{sku: string, de: string, a: string, de_alt: string, a_alt: string}>,
     *   advertencias: list<string>
     * }
     */
    public function sincronizarUnidadMedidaDesdeAnita(bool $dryRun = true, ?string $sku = null, ?int $empresaId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $catalogoAntes = (int) Unidadmedida::query()->count();
        if (! $dryRun) {
            (new Unidadmedida())->sincronizarConAnita();
        }
        $catalogoDespues = (int) Unidadmedida::query()->count();

        $advertencias = [];
        $sku = is_string($sku) ? trim($sku) : '';

        if ($sku !== '') {
            $codigo = $this->resolverCodigoAnitaPorSku($sku, $empresaId);
            if ($codigo === null) {
                throw new \RuntimeException("Artículo «{$sku}» no encontrado en Anita (stkmae).");
            }
            $filas = ArticuloStkmaeAnitaBridgeSupport::listarDetallePorCodigos([$codigo], $empresaId);
        } else {
            $apiAnita = new ApiAnita;
            $payload = [
                'acc' => 'list',
                'tabla' => 'stkmae',
                'campos' => 'stkm_articulo,stkm_cod_umd,stkm_cod_umd_alter,stkm_unidad_medida',
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
        }

        if ($filas === []) {
            return [
                'dry_run' => $dryRun,
                'en_anita' => 0,
                'actualizados' => 0,
                'sin_cambio' => 0,
                'no_encontrados_erp' => 0,
                'sin_um_anita' => 0,
                'um_anita_sin_catalogo' => 0,
                'catalogo_antes' => $catalogoAntes,
                'catalogo_despues' => $catalogoDespues,
                'cambios' => [],
                'advertencias' => ['Anita no devolvió filas de stkmae (unidad de medida).'],
            ];
        }

        return $this->aplicarUnidadMedidaDesdeFilasAnita($filas, $dryRun, $advertencias, $catalogoAntes, $catalogoDespues);
    }

    /**
     * @param  list<object>  $filas
     * @param  list<string>  $advertencias
     * @return array{
     *   dry_run: bool,
     *   en_anita: int,
     *   actualizados: int,
     *   sin_cambio: int,
     *   no_encontrados_erp: int,
     *   sin_um_anita: int,
     *   um_anita_sin_catalogo: int,
     *   catalogo_antes: int,
     *   catalogo_despues: int,
     *   cambios: list<array{sku: string, de: string, a: string, de_alt: string, a_alt: string}>,
     *   advertencias: list<string>
     * }
     */
    private function aplicarUnidadMedidaDesdeFilasAnita(
        array $filas,
        bool $dryRun,
        array $advertencias,
        int $catalogoAntes,
        int $catalogoDespues
    ): array {
        $umPorId = [];
        $umPorAbr = [];
        foreach (Unidadmedida::query()->get(['id', 'abreviatura']) as $um) {
            $umPorId[(int) $um->id] = $um;
            $abr = strtoupper(trim((string) $um->abreviatura));
            if ($abr !== '') {
                $umPorAbr[$abr] = $um;
            }
        }

        $articulos = Articulo::query()->get(['id', 'sku', 'unidadmedida_id', 'unidadmedidaalternativa_id']);
        $porSku = [];
        foreach ($articulos as $articulo) {
            $clave = strtoupper(ltrim(trim((string) $articulo->sku), '0'));
            if ($clave === '') {
                continue;
            }
            if (! isset($porSku[$clave])) {
                $porSku[$clave] = $articulo;
            }
        }

        $actualizados = 0;
        $sinCambio = 0;
        $noEncontrados = 0;
        $sinUmAnita = 0;
        $umSinCatalogo = 0;
        $cambios = [];

        foreach ($filas as $fila) {
            $codigo = trim((string) ($fila->stkm_articulo ?? ''));
            if ($codigo === '' || ltrim($codigo, '0') === '') {
                continue;
            }
            $skuLocal = strtoupper(ltrim($codigo, '0'));
            $articulo = $porSku[$skuLocal] ?? ArticuloSkuMatchSupport::resolverCanonico(ltrim($codigo, '0'));
            if ($articulo === null) {
                $noEncontrados++;

                continue;
            }

            $umIdAnita = (int) ($fila->stkm_cod_umd ?? 0);
            $umAltAnita = (int) ($fila->stkm_cod_umd_alter ?? 0);
            $abrAnita = $this->normalizarAbreviaturaUm((string) ($fila->stkm_unidad_medida ?? ''));

            // El texto de Anita (KG/UN/CAJ) manda: stkm_cod_umd suele estar en 0 o no coincide.
            $umDestino = $abrAnita !== '' ? ($umPorAbr[$abrAnita] ?? null) : null;
            if ($umDestino === null && $umIdAnita > 0) {
                $umDestino = $umPorId[$umIdAnita] ?? null;
            }
            if ($umDestino === null && ($umIdAnita > 0 || $abrAnita !== '' || trim((string) ($fila->stkm_unidad_medida ?? '')) !== '')) {
                $umSinCatalogo++;
                if (count($advertencias) < 20) {
                    $crudo = trim((string) ($fila->stkm_unidad_medida ?? ''));
                    $advertencias[] = "SKU {$skuLocal}: Anita UM {$umIdAnita}/{$crudo} no está en el catálogo ERP.";
                }
            }
            if ($umDestino === null && $umIdAnita <= 0 && $abrAnita === '') {
                $sinUmAnita++;
            }

            $umAltDestino = $umAltAnita > 0 ? ($umPorId[$umAltAnita] ?? null) : null;
            $nuevoId = $umDestino !== null ? (int) $umDestino->id : null;
            $nuevoAltId = $umAltDestino !== null ? (int) $umAltDestino->id : null;
            $actualId = (int) ($articulo->unidadmedida_id ?? 0) ?: null;
            $actualAltId = (int) ($articulo->unidadmedidaalternativa_id ?? 0) ?: null;

            if ($actualId === $nuevoId && $actualAltId === $nuevoAltId) {
                $sinCambio++;

                continue;
            }

            $cambios[] = [
                'sku' => (string) $articulo->sku,
                'de' => $this->etiquetaUm($actualId, $umPorId),
                'a' => $this->etiquetaUm($nuevoId, $umPorId),
                'de_alt' => $this->etiquetaUm($actualAltId, $umPorId),
                'a_alt' => $this->etiquetaUm($nuevoAltId, $umPorId),
            ];

            if (! $dryRun) {
                $articulo->update([
                    'unidadmedida_id' => $nuevoId,
                    'unidadmedidaalternativa_id' => $nuevoAltId,
                ]);
            }
            $actualizados++;
        }

        return [
            'dry_run' => $dryRun,
            'en_anita' => count($filas),
            'actualizados' => $actualizados,
            'sin_cambio' => $sinCambio,
            'no_encontrados_erp' => $noEncontrados,
            'sin_um_anita' => $sinUmAnita,
            'um_anita_sin_catalogo' => $umSinCatalogo,
            'catalogo_antes' => $catalogoAntes,
            'catalogo_despues' => $catalogoDespues,
            'cambios' => $cambios,
            'advertencias' => $advertencias,
        ];
    }

    private function normalizarAbreviaturaUm(string $abr): string
    {
        $s = strtoupper(trim($abr));
        $s = rtrim($s, '.');
        $s = preg_replace('/\s+/', '', $s) ?? $s;
        if ($s === '') {
            return '';
        }

        $alias = [
            'KG' => 'KG',
            'KGS' => 'KG',
            'KILO' => 'KG',
            'KILOGRAMO' => 'KG',
            '1' => 'KG',
            'UN' => 'UN',
            'UNI' => 'UN',
            'UND' => 'UN',
            'UNC' => 'UN',
            'UNID' => 'UN',
            'UNIDAD' => 'UN',
            'CAJ' => 'CAJ',
            'CAJA' => 'CAJ',
            'LT' => 'LT',
            'LTS' => 'LT',
            'L' => 'LT',
            'LIT' => 'LT',
            'LITRO' => 'LT',
        ];

        return $alias[$s] ?? $s;
    }

    /**
     * @param  array<int, Unidadmedida>  $umPorId
     */
    private function etiquetaUm(?int $id, array $umPorId): string
    {
        if ($id === null || $id <= 0) {
            return '(vacío)';
        }
        $um = $umPorId[$id] ?? null;

        return $um !== null ? trim((string) $um->abreviatura).' #'.$id : '#'.$id;
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
