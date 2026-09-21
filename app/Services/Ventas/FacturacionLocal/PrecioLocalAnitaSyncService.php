<?php

namespace App\Services\Ventas\FacturacionLocal;

use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Tiponumeracion;
use App\Models\Ventas\LocalVenta;
use App\Support\Stock\ArticuloSkuMatchSupport;
use App\Support\Stock\PrecioAnitaFechaSupport;
use App\Support\Stock\PrecioConservarVigenteSupport;
use App\Support\Ventas\FacturacionLocal\PrecioListaLocalMapeoSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Importa stkpre del Anita Local → precio ERP, remapeando listas (5→11, 6→12, 50→13).
 */
final class PrecioLocalAnitaSyncService
{
    private const TABLA = 'stkpre';

    public function __construct(
        private readonly ApiAnita $apiAnita,
        private readonly PrecioConservarVigenteSupport $conservarVigente,
    ) {}

    /**
     * @return array{
     *     dry_run: bool,
     *     servidor: string,
     *     ifx_server: string,
     *     fecha_desde_anita: int,
     *     mapeo: array<string, string>,
     *     filas_anita: int,
     *     filas_unicas_sku_lista: int,
     *     insertados: int,
     *     actualizados: int,
     *     a_insertar: int,
     *     a_actualizar: int,
     *     omitidos_sin_articulo: int,
     *     omitidos_sin_lista: int,
     *     omitidos_precio_invalido: int,
     *     omitidos_lista_sin_mapeo: int,
     *     listas_creadas: list<string>,
     *     listas_ya_existentes: list<string>,
     *     obsoletos_eliminados: int,
     *     pares_con_duplicado: int,
     *     errores: list<string>,
     *     error?: string
     * }
     */
    public function sincronizar(
        ?LocalVenta $local = null,
        ?int $fechaDesdeAnita = null,
        bool $ejecutar = false,
        bool $conservarSoloVigente = true,
        ?int $usuarioId = null,
    ): array {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $servidor = $local?->anitaServidor()
            ?: (string) config('facturacion_local.anita_servidor_default', 'LOCAL_IP');
        $ifx = $local?->anitaIfxServer()
            ?: (string) config('facturacion_local.anita_ifx_server_default', 'IFX_SERVER_LOCAL');

        $fechaDesdeAnita = $fechaDesdeAnita ?? $this->fechaDesdeConfig();
        $usuarioId = max(1, (int) ($usuarioId ?? (Usuario::query()->orderBy('id')->value('id') ?? 1)));
        $mapeo = PrecioListaLocalMapeoSupport::mapa();

        $ret = [
            'dry_run' => ! $ejecutar,
            'servidor' => $servidor,
            'ifx_server' => $ifx,
            'fecha_desde_anita' => $fechaDesdeAnita,
            'mapeo' => $mapeo,
            'filas_anita' => 0,
            'filas_unicas_sku_lista' => 0,
            'insertados' => 0,
            'actualizados' => 0,
            'a_insertar' => 0,
            'a_actualizar' => 0,
            'omitidos_sin_articulo' => 0,
            'omitidos_sin_lista' => 0,
            'omitidos_precio_invalido' => 0,
            'omitidos_lista_sin_mapeo' => 0,
            'listas_creadas' => [],
            'listas_ya_existentes' => [],
            'obsoletos_eliminados' => 0,
            'pares_con_duplicado' => 0,
            'errores' => [],
        ];

        if ($mapeo === []) {
            $ret['error'] = 'Sin mapeo de listas local → ERP (config facturacion_local.lista_precio_mapeo).';

            return $ret;
        }

        if ($ejecutar && ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId))) {
            $ret['error'] = 'Usuario inválido para importar precios.';

            return $ret;
        }

        $listasErp = $this->asegurarListasErp($usuarioId, $ejecutar);
        $ret['listas_creadas'] = $listasErp['creadas'];
        $ret['listas_ya_existentes'] = $listasErp['ya_existentes'];
        if (($listasErp['error'] ?? null) !== null) {
            $ret['error'] = $listasErp['error'];

            return $ret;
        }

        $mapListasErp = $listasErp['mapa_id'];
        $codigosAnita = array_keys($mapeo);

        try {
            $filasAnita = $this->listarStkpreLocal($servidor, $ifx, $fechaDesdeAnita, $codigosAnita);
        } catch (\Throwable $e) {
            $ret['error'] = $e->getMessage();

            return $ret;
        }

        $ret['filas_anita'] = count($filasAnita);
        $filasUnicas = $this->agruparPorSkuListaMasReciente($filasAnita);
        $ret['filas_unicas_sku_lista'] = count($filasUnicas);

        $mapArticulos = $this->mapaArticulosPorSku();
        $paresTocados = [];

        foreach ($filasUnicas as $fila) {
            $sku = ltrim(trim((string) ($fila['stkp_articulo'] ?? '')), '0');
            if ($sku === '') {
                continue;
            }

            $codigoAnita = PrecioListaLocalMapeoSupport::normalizarCodigo($fila['stkp_lista'] ?? '');
            $codigoErp = $mapeo[$codigoAnita] ?? null;
            if ($codigoErp === null) {
                $ret['omitidos_lista_sin_mapeo']++;

                continue;
            }

            $listaprecioId = $mapListasErp[$codigoErp] ?? null;
            if ($listaprecioId === null) {
                $ret['omitidos_sin_lista']++;

                continue;
            }

            $articuloId = $mapArticulos[$sku] ?? null;
            if ($articuloId === null) {
                $canonico = ArticuloSkuMatchSupport::resolverCanonico($sku);
                $articuloId = $canonico ? (int) $canonico->id : null;
                if ($articuloId !== null) {
                    $mapArticulos[$sku] = $articuloId;
                }
            }
            if ($articuloId === null) {
                $ret['omitidos_sin_articulo']++;

                continue;
            }

            $precio = $fila['stkp_precio'] ?? null;
            if ($precio === null || $precio === '' || ! is_numeric($precio)) {
                $ret['omitidos_precio_invalido']++;

                continue;
            }

            $fechavigencia = PrecioAnitaFechaSupport::fechavigenciaDesdeAnita($fila['stkp_fe_ult_act'] ?? null);
            $precioAnterior = $fila['stkp_precio_ant'] ?? null;
            $precioAnterior = ($precioAnterior !== null && $precioAnterior !== '' && is_numeric($precioAnterior))
                ? (float) $precioAnterior
                : null;
            $monedaId = (int) ($fila['stkp_cod_mon'] ?? 1);
            if ($monedaId < 1) {
                $monedaId = 1;
            }

            if ($listaprecioId <= 0) {
                // Dry-run sin cabecera ERP aún: se creará la lista al --ejecutar.
                $ret['a_insertar']++;

                continue;
            }

            $existente = Precio::query()
                ->where('articulo_id', $articuloId)
                ->where('listaprecio_id', $listaprecioId)
                ->whereDate('fechavigencia', $fechavigencia)
                ->orderByDesc('id')
                ->first();

            if ($existente) {
                $ret['a_actualizar']++;
            } else {
                $ret['a_insertar']++;
            }

            if (! $ejecutar) {
                continue;
            }

            try {
                $payload = [
                    'precio' => (float) $precio,
                    'precioanterior' => $precioAnterior,
                    'moneda_id' => $monedaId,
                    'usuarioultcambio_id' => $usuarioId,
                ];

                if ($existente) {
                    $existente->update($payload);
                    $ret['actualizados']++;
                } else {
                    Precio::create(array_merge($payload, [
                        'articulo_id' => $articuloId,
                        'listaprecio_id' => $listaprecioId,
                        'fechavigencia' => $fechavigencia,
                    ]));
                    $ret['insertados']++;
                }

                $paresTocados[] = [
                    'articulo_id' => $articuloId,
                    'listaprecio_id' => $listaprecioId,
                ];
            } catch (\Throwable $e) {
                $msg = "SKU {$sku} lista Anita {$codigoAnita}→ERP {$codigoErp}: ".$e->getMessage();
                if (count($ret['errores']) < 50) {
                    $ret['errores'][] = $msg;
                }
                Log::warning('PrecioLocalAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        if ($ejecutar && $conservarSoloVigente && $paresTocados !== []) {
            $limp = $this->conservarVigente->conservarSoloVigente($this->uniquePares($paresTocados));
            $ret['obsoletos_eliminados'] = $limp['eliminados'];
            $ret['pares_con_duplicado'] = $limp['pares_con_duplicado'];
        }

        return $ret;
    }

    private function fechaDesdeConfig(): int
    {
        $raw = trim((string) config('facturacion_local.precio_anita_sync_desde', '20250101'));
        $n = (int) preg_replace('/\D/', '', $raw);

        return $n >= 19000000 ? $n : PrecioAnitaFechaSupport::fechaDesdeConfig();
    }

    /**
     * @return array{
     *     mapa_id: array<string, int>,
     *     creadas: list<string>,
     *     ya_existentes: list<string>,
     *     error?: string
     * }
     */
    private function asegurarListasErp(int $usuarioId, bool $ejecutar): array
    {
        $codigosErp = PrecioListaLocalMapeoSupport::codigosErp();
        $nombres = PrecioListaLocalMapeoSupport::nombresErpPorDefecto();
        $mapaId = [];
        $creadas = [];
        $ya = [];

        foreach ($codigosErp as $codigo) {
            $lista = Listaprecio::query()
                ->where('codigo', $codigo)
                ->orWhere('codigo', (int) $codigo)
                ->first();

            if ($lista) {
                $mapaId[$codigo] = (int) $lista->id;
                $ya[] = $codigo;

                continue;
            }

            if (! $ejecutar) {
                $ya[] = $codigo.' (se creará)';

                continue;
            }

            $tiponum = Tiponumeracion::query()->orderBy('id')->value('id');
            $lista = Listaprecio::create([
                'nombre' => $nombres[$codigo] ?? ('Lista '.$codigo),
                'formula' => '0',
                'incluyeimpuesto' => '1', // listas locales = precio final (IVA incluido)
                'codigo' => (int) $codigo,
                'tiponumeracion_id' => $tiponum,
                'usuarioultcambio_id' => $usuarioId,
            ]);
            $mapaId[$codigo] = (int) $lista->id;
            $creadas[] = $codigo;
        }

        if ($ejecutar) {
            foreach ($codigosErp as $codigo) {
                if (! isset($mapaId[$codigo])) {
                    return [
                        'mapa_id' => $mapaId,
                        'creadas' => $creadas,
                        'ya_existentes' => $ya,
                        'error' => "No se pudo asegurar listaprecio ERP código {$codigo}.",
                    ];
                }
            }
        } else {
            // Dry-run: resolver IDs existentes; faltantes se marcarán omitidos_sin_lista al persistir.
            foreach ($codigosErp as $codigo) {
                if (isset($mapaId[$codigo])) {
                    continue;
                }
                // Placeholder negativo solo para contar a_insertar/a_actualizar en dry-run sin lista.
                $mapaId[$codigo] = -1 * max(1, (int) $codigo);
            }
        }

        return [
            'mapa_id' => $mapaId,
            'creadas' => $creadas,
            'ya_existentes' => $ya,
        ];
    }

    /**
     * @param  list<string>  $codigosAnita
     * @return list<array<string, mixed>>
     */
    private function listarStkpreLocal(string $servidor, string $ifx, int $fechaDesdeAnita, array $codigosAnita): array
    {
        $inParts = [];
        foreach ($codigosAnita as $codigo) {
            $esc = addslashes($codigo);
            $inParts[] = "'{$esc}'";
            if (ctype_digit($codigo)) {
                $inParts[] = (string) ((int) $codigo);
            }
        }
        $inParts = array_values(array_unique($inParts));
        if ($inParts === []) {
            return [];
        }

        // Incluir stkp_fe_ult_act = 0 / inválida: en Anita Local muchos precios
        // de listas WEB/OFERTA/LUGANO quedan con fecha 0 y el filtro solo por
        // >= desde los omitía (ej. SKU 63091523 lista 50 → ERP 13).
        $where = ' WHERE (stkp_fe_ult_act >= '.(int) $fechaDesdeAnita
            .' OR stkp_fe_ult_act IS NULL OR stkp_fe_ult_act < 19000000)'
            .' AND stkp_lista IN ('.implode(',', $inParts).') ';

        $payload = [
            'acc' => 'list',
            'tabla' => self::TABLA,
            'campos' => implode(',', [
                'stkp_articulo',
                'stkp_lista',
                'stkp_precio',
                'stkp_precio_ant',
                'stkp_cod_mon',
                'stkp_fe_ult_act',
            ]),
            'whereArmado' => $where,
            'orderBy' => 'stkp_fe_ult_act desc',
            'servidor' => $servidor,
            'ifx_server' => $ifx,
        ];

        $raw = $this->apiAnita->apiCall($payload);
        $rawStr = is_string($raw) ? $raw : json_encode($raw);
        $error = ApiAnita::extraerMensajeError($rawStr);
        if ($error !== null) {
            throw new \RuntimeException('Anita Local stkpre: '.$error);
        }

        $filas = ApiAnita::decodificarListaFilas($rawStr);
        $out = [];
        foreach ($filas as $fila) {
            $out[] = is_object($fila) ? get_object_vars($fila) : (array) $fila;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function agruparPorSkuListaMasReciente(array $filas): array
    {
        $mejor = [];
        foreach ($filas as $fila) {
            $articulo = trim((string) ($fila['stkp_articulo'] ?? ''));
            $lista = PrecioListaLocalMapeoSupport::normalizarCodigo($fila['stkp_lista'] ?? '');
            if ($articulo === '' || $lista === '') {
                continue;
            }
            $clave = $articulo.'|'.$lista;
            $fecha = (int) ($fila['stkp_fe_ult_act'] ?? 0);
            if (! isset($mejor[$clave]) || $fecha > (int) ($mejor[$clave]['stkp_fe_ult_act'] ?? 0)) {
                $mejor[$clave] = $fila;
            }
        }

        return array_values($mejor);
    }

    /**
     * @return array<string, int>
     */
    private function mapaArticulosPorSku(): array
    {
        $map = [];
        foreach (Articulo::query()->select(['id', 'sku'])->cursor() as $row) {
            $sku = trim((string) $row->sku);
            if ($sku !== '') {
                $map[$sku] = (int) $row->id;
                $sinCeros = ltrim($sku, '0');
                if ($sinCeros !== '' && ! isset($map[$sinCeros])) {
                    $map[$sinCeros] = (int) $row->id;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<array{articulo_id: int, listaprecio_id: int}>  $pares
     * @return list<array{articulo_id: int, listaprecio_id: int}>
     */
    private function uniquePares(array $pares): array
    {
        $out = [];
        foreach ($pares as $par) {
            $aid = (int) $par['articulo_id'];
            $lid = (int) $par['listaprecio_id'];
            if ($aid <= 0 || $lid <= 0) {
                continue;
            }
            $out[$aid.'|'.$lid] = [
                'articulo_id' => $aid,
                'listaprecio_id' => $lid,
            ];
        }

        return array_values($out);
    }
}
