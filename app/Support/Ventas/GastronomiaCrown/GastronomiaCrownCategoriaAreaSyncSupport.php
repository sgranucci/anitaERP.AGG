<?php

namespace App\Support\Ventas\GastronomiaCrown;

use App\ApiAnita;
use App\Models\Stock\Categoria;
use App\Models\Ventas\AreaComandaGastronomia;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\CategoriaAnitaBridgeSupport;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sincroniza área de comanda Crown desde Anita `stkagr.stka_area`
 * hacia `categoria_area_comanda` (misma lógica que AGG subcategoría ↔ área).
 */
final class GastronomiaCrownCategoriaAreaSyncSupport
{
    /**
     * @return array{
     *   ok: bool,
     *   mensaje: string,
     *   creadas: int,
     *   actualizadas: int,
     *   omitidas: int,
     *   sin_categoria: int,
     *   sin_area: int,
     *   detalle: list<array{codigo:string,nombre:string,area_anita:int,accion:string}>
     * }
     */
    public static function sincronizar(bool $ejecutar = false, ?int $empresaId = null): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new InvalidArgumentException('Solo aplica en entorno Crown.');
        }

        $empresaId ??= (int) (DB::table('empresa')->orderBy('id')->value('id') ?: 1);
        $areasPorCodigo = AreaComandaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->get(['id', 'codigo'])
            ->keyBy(fn (AreaComandaGastronomia $a) => (string) $a->codigo);

        $api = new ApiAnita;
        $payload = [
            'acc' => 'list',
            'tabla' => CategoriaAnitaBridgeSupport::tabla(),
            'campos' => 'stka_agrupacion,stka_desc,stka_area',
            'whereArmado' => ' WHERE stka_area > 0 ',
            'orderBy' => 'stka_agrupacion',
        ];
        self::aplicarServidorAnita($payload);

        $filas = ApiAnita::decodificarListaFilas($api->apiCallHttp($payload));
        $detalle = [];
        $creadas = 0;
        $actualizadas = 0;
        $omitidas = 0;
        $sinCategoria = 0;
        $sinArea = 0;

        foreach ($filas as $fila) {
            $codigoAnita = trim((string) ($fila->stka_agrupacion ?? ''));
            $nombre = trim((string) ($fila->stka_desc ?? ''));
            $areaAnita = (int) ($fila->stka_area ?? 0);
            if ($codigoAnita === '' || $areaAnita <= 0) {
                $omitidas++;

                continue;
            }

            $categoria = self::buscarCategoria($codigoAnita);
            if ($categoria === null) {
                $sinCategoria++;
                $detalle[] = [
                    'codigo' => $codigoAnita,
                    'nombre' => $nombre,
                    'area_anita' => $areaAnita,
                    'accion' => 'sin_categoria',
                ];

                continue;
            }

            $area = $areasPorCodigo->get((string) $areaAnita);
            if ($area === null) {
                $sinArea++;
                $detalle[] = [
                    'codigo' => $codigoAnita,
                    'nombre' => $nombre,
                    'area_anita' => $areaAnita,
                    'accion' => 'sin_area_erp',
                ];

                continue;
            }

            $areaId = (int) $area->id;
            $actuales = DB::table('categoria_area_comanda')
                ->where('categoria_id', $categoria->id)
                ->pluck('area_comanda_gastronomia_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (in_array($areaId, $actuales, true) && count($actuales) === 1) {
                $omitidas++;
                $detalle[] = [
                    'codigo' => (string) $categoria->codigo,
                    'nombre' => (string) $categoria->nombre,
                    'area_anita' => $areaAnita,
                    'accion' => 'igual',
                ];

                continue;
            }

            $accion = $actuales === [] ? 'crear' : 'actualizar';
            if ($ejecutar) {
                DB::table('categoria_area_comanda')->where('categoria_id', $categoria->id)->delete();
                DB::table('categoria_area_comanda')->insert([
                    'categoria_id' => $categoria->id,
                    'area_comanda_gastronomia_id' => $areaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($accion === 'crear') {
                $creadas++;
            } else {
                $actualizadas++;
            }
            $detalle[] = [
                'codigo' => (string) $categoria->codigo,
                'nombre' => (string) $categoria->nombre,
                'area_anita' => $areaAnita,
                'accion' => $accion,
            ];
        }

        $mensaje = sprintf(
            'Crown categorías↔área: crear %d, actualizar %d, igual %d, sin categoría %d, sin área ERP %d%s',
            $creadas,
            $actualizadas,
            $omitidas,
            $sinCategoria,
            $sinArea,
            $ejecutar ? '' : ' (dry-run)'
        );

        return [
            'ok' => true,
            'mensaje' => $mensaje,
            'creadas' => $creadas,
            'actualizadas' => $actualizadas,
            'omitidas' => $omitidas,
            'sin_categoria' => $sinCategoria,
            'sin_area' => $sinArea,
            'detalle' => $detalle,
        ];
    }

    private static function buscarCategoria(string $codigoAnita): ?Categoria
    {
        $norm = CategoriaAnitaBridgeSupport::normalizarCodigo($codigoAnita);
        $candidatos = array_values(array_unique(array_filter([
            $codigoAnita,
            $norm,
            ltrim($codigoAnita, '0'),
            str_pad($norm, 4, '0', STR_PAD_LEFT),
        ], fn ($c) => $c !== '')));

        foreach ($candidatos as $codigo) {
            $cat = Categoria::query()->where('codigo', $codigo)->first();
            if ($cat) {
                return $cat;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function aplicarServidorAnita(array &$payload): void
    {
        $ifx = trim((string) config('stock.categoria_anita_ifx_server', ''));
        if ($ifx === '') {
            $ifx = trim((string) config('stock.articulo_anita_ifx_server', ''));
        }
        if ($ifx === '') {
            $ifx = 'cncadmin';
        }
        $payload['ifx_server'] = $ifx;
        $payload['servidor'] = 'ANITA_IP';
        $payload['path_sistema'] = '/usr2/casino';
        $payload['sistema'] = 'ventas';
    }
}
