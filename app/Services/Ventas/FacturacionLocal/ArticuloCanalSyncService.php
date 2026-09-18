<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Estado;
use App\Models\Stock\Categoria;
use App\Models\Stock\Linea;
use App\Models\Stock\Material;
use App\Models\Stock\Unidadmedida;
use App\Models\Ventas\Canal;
use App\Models\Ventas\LocalVenta;
use App\Support\Stock\ArticuloCuentacontableEmpresasSupport;
use App\Support\Stock\ArticuloImpuestoAnitaSupport;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Ventas\FacturacionLocal\ArticuloCanalSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync stkmae del Anita Local → crea artículos ERP faltantes y asigna canal LOCAL.
 */
final class ArticuloCanalSyncService
{
    private const CAMPOS_DETALLE = 'stkm_articulo,stkm_desc,stkm_unidad_medida,stkm_cod_umd,stkm_agrupacion,stkm_marca,stkm_linea,stkm_fl_no_factura,stkm_cod_impuesto,stkm_cta_contable,stkm_cta_contablec,stkm_unidad_xenv,stkm_peso_aprox,stkm_ppp,stkm_articulo_prod,stkm_nombre_foto,stkm_cod_umd_alter';

    /**
     * @return array{
     *   dry_run:bool,
     *   skus_anita:int,
     *   encontrados_erp:int,
     *   ya_asignados:int,
     *   a_asignar:int,
     *   asignados:int,
     *   a_crear:int,
     *   creados:int,
     *   errores_alta:int,
     *   sin_match:int,
     *   skus_a_asignar:list<string>,
     *   skus_a_crear:list<string>,
     *   skus_sin_match:list<string>,
     *   errores_muestra:list<string>,
     *   error?:string
     * }
     */
    public function sincronizar(?LocalVenta $local = null, bool $ejecutar = false): array
    {
        $servidor = $local?->anitaServidor() ?: (string) config('facturacion_local.anita_servidor_default', 'LOCAL_IP');
        $ifx = $local?->anitaIfxServer() ?: (string) config('facturacion_local.anita_ifx_server_default', 'IFX_SERVER_LOCAL');

        $canal = Canal::local();
        if (! $canal) {
            return $this->resultadoVacio(! $ejecutar, 'No existe el canal LOCAL activo. Corra la migración de tablas.');
        }

        $filasAnita = $this->listarDetalleStkmaeLocal($servidor, $ifx);
        if (isset($filasAnita['error'])) {
            return $this->resultadoVacio(! $ejecutar, $filasAnita['error']);
        }

        /** @var list<object> $filas */
        $filas = $filasAnita['filas'];

        $mapaUm = $this->mapaUnidadmedida();
        $mapaCat = $this->mapaCodigoId(Categoria::query()->get(['id', 'codigo']));
        $mapaLinea = $this->mapaCodigoId(Linea::query()->get(['id', 'codigo']));
        $mapaMaterial = $this->mapaCodigoId(Material::query()->get(['id', 'codigo']));
        $umDefaultId = (int) (Unidadmedida::query()->where('nombre', 'Unidades')->value('id')
            ?: Unidadmedida::query()->orderBy('id')->value('id')
            ?: 0);

        $usuarioId = $this->asegurarUsuarioOperativo();

        $aAsignar = [];
        $aCrear = [];
        $yaAsignados = 0;
        $encontrados = 0;

        foreach ($filas as $fila) {
            $codigoAnita = trim((string) ($fila->stkm_articulo ?? ''));
            $skuErp = $this->skuErpDesdeAnita($codigoAnita);
            if ($skuErp === '') {
                continue;
            }

            $articulo = $this->buscarArticuloErp($skuErp, $codigoAnita);
            if ($articulo) {
                $encontrados++;
                if (ArticuloCanalSupport::articuloTieneCanal((int) $articulo->id, $canal->codigo)) {
                    $yaAsignados++;

                    continue;
                }
                $aAsignar[] = ['articulo_id' => (int) $articulo->id, 'sku' => (string) $articulo->sku];

                continue;
            }

            $aCrear[] = $fila;
        }

        $asignados = 0;
        $creados = 0;
        $erroresAlta = 0;
        $erroresMuestra = [];
        $skusCreados = [];

        if ($ejecutar) {
            foreach ($aCrear as $fila) {
                try {
                    $articulo = DB::transaction(function () use (
                        $fila,
                        $mapaUm,
                        $mapaCat,
                        $mapaLinea,
                        $mapaMaterial,
                        $umDefaultId,
                        $usuarioId,
                        $canal
                    ) {
                        $articulo = $this->crearArticuloDesdeFilaLocal(
                            $fila,
                            $mapaUm,
                            $mapaCat,
                            $mapaLinea,
                            $mapaMaterial,
                            $umDefaultId,
                            $usuarioId
                        );
                        ArticuloCanalSupport::asignarCanal((int) $articulo->id, (int) $canal->id);

                        return $articulo;
                    });
                    $creados++;
                    $skusCreados[] = (string) $articulo->sku;
                } catch (\Throwable $e) {
                    $erroresAlta++;
                    $sku = $this->skuErpDesdeAnita((string) ($fila->stkm_articulo ?? ''));
                    if (count($erroresMuestra) < 30) {
                        $erroresMuestra[] = $sku.': '.$e->getMessage();
                    }
                    Log::warning('facturacion_local.sync_canal.alta_error', [
                        'sku' => $sku,
                        'msg' => $e->getMessage(),
                    ]);
                }
            }

            foreach ($aAsignar as $row) {
                if (ArticuloCanalSupport::asignarCanal($row['articulo_id'], (int) $canal->id)) {
                    $asignados++;
                }
            }
        }

        return [
            'dry_run' => ! $ejecutar,
            'skus_anita' => count($filas),
            'encontrados_erp' => $encontrados,
            'ya_asignados' => $yaAsignados,
            'a_asignar' => count($aAsignar),
            'asignados' => $asignados,
            'a_crear' => count($aCrear),
            'creados' => $creados,
            'errores_alta' => $erroresAlta,
            'sin_match' => $ejecutar ? max(0, count($aCrear) - $creados) : count($aCrear),
            'skus_a_asignar' => array_column($aAsignar, 'sku'),
            'skus_a_crear' => array_map(
                fn ($f) => $this->skuErpDesdeAnita((string) ($f->stkm_articulo ?? '')),
                array_slice($aCrear, 0, 100)
            ),
            'skus_sin_match' => $ejecutar
                ? []
                : array_map(
                    fn ($f) => $this->skuErpDesdeAnita((string) ($f->stkm_articulo ?? '')),
                    array_slice($aCrear, 0, 100)
                ),
            'errores_muestra' => $erroresMuestra,
        ];
    }

    /**
     * @param  array<string, int>  $mapaUm
     * @param  array<string, int>  $mapaCat
     * @param  array<string, int>  $mapaLinea
     * @param  array<string, int>  $mapaMaterial
     */
    private function crearArticuloDesdeFilaLocal(
        object $fila,
        array $mapaUm,
        array $mapaCat,
        array $mapaLinea,
        array $mapaMaterial,
        int $umDefaultId,
        int $usuarioId
    ): Articulo {
        $codigoAnita = trim((string) ($fila->stkm_articulo ?? ''));
        $sku = $this->skuErpDesdeAnita($codigoAnita);
        if ($sku === '') {
            throw new \RuntimeException('SKU vacío');
        }

        if (ArticuloSkuMatchSupport::existe($sku)) {
            throw new \RuntimeException('Ya existe en ERP (condición de carrera)');
        }

        $desc = trim((string) ($fila->stkm_desc ?? ''));
        if ($desc === '') {
            $desc = $sku;
        }

        $umId = $this->resolverUnidadmedidaId(
            (string) ($fila->stkm_unidad_medida ?? ''),
            (int) ($fila->stkm_cod_umd ?? 0),
            $mapaUm,
            $umDefaultId
        );

        $categoriaId = $this->resolverPorCodigo((string) ($fila->stkm_agrupacion ?? ''), $mapaCat);
        $lineaId = $this->resolverPorCodigo((string) ($fila->stkm_linea ?? ''), $mapaLinea);
        $materialId = $this->resolverPorCodigo((string) ($fila->stkm_marca ?? ''), $mapaMaterial);
        $impuestoId = ArticuloImpuestoAnitaSupport::impuestoIdDesdeCodigoAnita(
            (string) ($fila->stkm_cod_impuesto ?? '')
        );

        $ctaVentaId = $this->resolverCuentaId((string) ($fila->stkm_cta_contable ?? ''));
        $ctaCompraId = $this->resolverCuentaId((string) ($fila->stkm_cta_contablec ?? ''));

        $noFactura = trim((string) ($fila->stkm_fl_no_factura ?? '0'));
        $estado = in_array($noFactura, ['I'], true) ? 'INACTIVO' : 'ACTIVO';
        $nofacturaFlag = in_array($noFactura, ['1', 'N', 'I'], true) ? 1 : 0;

        $articulo = Articulo::create([
            'sku' => $sku,
            'descripcion' => mb_substr($desc, 0, 100),
            'detalle' => $desc,
            'categoria_id' => $categoriaId,
            'linea_id' => $lineaId,
            'material_id' => $materialId,
            'unidadmedida_id' => $umId > 0 ? $umId : null,
            'unidadmedidaalternativa_id' => $umId > 0 ? $umId : null,
            'usoarticulo_id' => 1,
            'impuesto_id' => $impuestoId,
            'cuentacontableventa_id' => $ctaVentaId,
            'cuentacontablecompra_id' => $ctaCompraId,
            'nofactura' => $nofacturaFlag,
            'estado' => $estado,
            'peso' => (float) ($fila->stkm_peso_aprox ?? 0),
            'ppp' => (float) ($fila->stkm_ppp ?? 0),
            'unidadesxenvase' => (float) ($fila->stkm_unidad_xenv ?? 0),
            'skualternativo' => trim((string) ($fila->stkm_articulo_prod ?? '')) ?: null,
            'foto' => trim((string) ($fila->stkm_nombre_foto ?? '')) ?: null,
            'maneja_stock_color_talle' => true,
            'fl_precio_promedio_transferencia' => 0,
            'usuario_id' => $usuarioId,
        ]);

        Articulo_Estado::create([
            'articulo_id' => $articulo->id,
            'fecha' => now(),
            'estado' => $estado,
            'usuario_id' => $usuarioId,
            'observacion' => 'Alta desde Anita Local (sync canal LOCAL)',
        ]);

        if ($ctaVentaId) {
            ArticuloCuentacontableEmpresasSupport::asegurarDesdeCuentaOrigen(
                (int) $articulo->id,
                'VENTAS',
                (int) $ctaVentaId,
                $usuarioId
            );
        }
        if ($ctaCompraId) {
            ArticuloCuentacontableEmpresasSupport::asegurarDesdeCuentaOrigen(
                (int) $articulo->id,
                'COMPRAS',
                (int) $ctaCompraId,
                $usuarioId
            );
        }

        return $articulo;
    }

    private function buscarArticuloErp(string $skuErp, string $codigoAnita): ?Articulo
    {
        $candidatos = array_values(array_unique(array_filter([
            $skuErp,
            $codigoAnita,
            ltrim($codigoAnita, '0'),
            str_pad($skuErp, 13, '0', STR_PAD_LEFT),
        ])));

        foreach ($candidatos as $cand) {
            $art = ArticuloSkuMatchSupport::resolverCanonico($cand);
            if ($art) {
                return $art;
            }
        }

        return null;
    }

    private function skuErpDesdeAnita(string $codigoAnita): string
    {
        $codigoAnita = trim($codigoAnita);
        if ($codigoAnita === '') {
            return '';
        }

        $sku = ltrim($codigoAnita, '0');

        return $sku === '' ? '' : $sku;
    }

    /**
     * @param  array<string, int>  $mapaUm
     */
    private function resolverUnidadmedidaId(string $abrev, int $codUmd, array $mapaUm, int $defaultId): int
    {
        $abrev = strtoupper(trim($abrev));
        if ($abrev !== '') {
            if (isset($mapaUm[$abrev])) {
                return $mapaUm[$abrev];
            }
            if (in_array($abrev, ['PAR', 'PARES'], true) && isset($mapaUm['PAR'])) {
                return $mapaUm['PAR'];
            }
            if (in_array($abrev, ['U', 'UNI', 'UN', 'UNIDADES'], true)) {
                return $mapaUm['UNI'] ?? $mapaUm['U'] ?? $defaultId;
            }
        }

        if ($codUmd > 0 && Unidadmedida::query()->whereKey($codUmd)->exists()) {
            return $codUmd;
        }

        return $defaultId;
    }

    /**
     * @param  array<string, int>  $mapa
     */
    private function resolverPorCodigo(string $codigoAnita, array $mapa): ?int
    {
        $codigo = ltrim(trim($codigoAnita), '0');
        if ($codigo === '') {
            return null;
        }

        return $mapa[strtoupper($codigo)] ?? $mapa[$codigo] ?? null;
    }

    private function resolverCuentaId(string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $codigo === '0') {
            return null;
        }

        $id = Cuentacontable::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : null;
    }

    /**
     * @return array<string, int>
     */
    private function mapaUnidadmedida(): array
    {
        $mapa = [];
        foreach (Unidadmedida::query()->get(['id', 'nombre', 'abreviatura']) as $um) {
            foreach ([(string) $um->abreviatura, (string) $um->nombre] as $key) {
                $key = strtoupper(trim($key));
                if ($key !== '') {
                    $mapa[$key] = (int) $um->id;
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array<string, int>
     */
    private function mapaCodigoId($rows): array
    {
        $mapa = [];
        foreach ($rows as $row) {
            $codigo = ltrim(trim((string) ($row->codigo ?? '')), '0');
            if ($codigo === '') {
                continue;
            }
            $mapa[strtoupper($codigo)] = (int) $row->id;
            $mapa[$codigo] = (int) $row->id;
        }

        return $mapa;
    }

    private function asegurarUsuarioOperativo(): int
    {
        if (Auth::id()) {
            return (int) Auth::id();
        }

        $usuario = Usuario::query()->whereKey(1)->first()
            ?: Usuario::query()->orderBy('id')->first();

        if (! $usuario) {
            throw new \RuntimeException('No hay usuario para registrar altas de artículo.');
        }

        Auth::login($usuario);

        return (int) $usuario->id;
    }

    /**
     * @return array{filas:list<object>}|array{error:string}
     */
    private function listarDetalleStkmaeLocal(string $servidor, string $ifx): array
    {
        try {
            $data = [
                'acc' => 'list',
                'tabla' => 'stkmae',
                'campos' => self::CAMPOS_DETALLE,
                'orderBy' => 'stkm_articulo',
                'servidor' => $servidor,
                'ifx_server' => $ifx,
            ];
            $api = new ApiAnita;
            $raw = $api->apiCall($data);
            $rawStr = is_string($raw) ? $raw : json_encode($raw);
            $decoded = json_decode($rawStr, true);
            if (is_array($decoded) && isset($decoded['Error']) && is_string($decoded['Error']) && $decoded['Error'] !== '') {
                return ['error' => $decoded['Error'].' (servidor='.$servidor.', ifx='.$ifx.', url='.ApiAnita::urlBridge(ApiAnita::resolverHost($servidor)).')'];
            }

            $filas = ApiAnita::decodificarListaFilas($rawStr);
            $out = [];
            $vistos = [];
            foreach ($filas as $fila) {
                $obj = is_object($fila) ? $fila : (object) $fila;
                $sku = $this->skuErpDesdeAnita((string) ($obj->stkm_articulo ?? ''));
                if ($sku === '' || isset($vistos[$sku])) {
                    continue;
                }
                $vistos[$sku] = true;
                $out[] = $obj;
            }

            return ['filas' => $out];
        } catch (\Throwable $e) {
            Log::warning('facturacion_local.sync_canal.anita_error', [
                'msg' => $e->getMessage(),
                'servidor' => $servidor,
                'ifx' => $ifx,
            ]);

            return ['error' => 'Error leyendo stkmae Local: '.$e->getMessage()];
        }
    }

    /**
     * @return array{
     *   dry_run:bool,
     *   skus_anita:int,
     *   encontrados_erp:int,
     *   ya_asignados:int,
     *   a_asignar:int,
     *   asignados:int,
     *   a_crear:int,
     *   creados:int,
     *   errores_alta:int,
     *   sin_match:int,
     *   skus_a_asignar:list<string>,
     *   skus_a_crear:list<string>,
     *   skus_sin_match:list<string>,
     *   errores_muestra:list<string>,
     *   error:string
     * }
     */
    private function resultadoVacio(bool $dryRun, string $error): array
    {
        return [
            'dry_run' => $dryRun,
            'skus_anita' => 0,
            'encontrados_erp' => 0,
            'ya_asignados' => 0,
            'a_asignar' => 0,
            'asignados' => 0,
            'a_crear' => 0,
            'creados' => 0,
            'errores_alta' => 0,
            'sin_match' => 0,
            'skus_a_asignar' => [],
            'skus_a_crear' => [],
            'skus_sin_match' => [],
            'errores_muestra' => [],
            'error' => $error,
        ];
    }
}
