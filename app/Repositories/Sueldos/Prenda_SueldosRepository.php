<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Stock\Articulo;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Support\Sueldos\PrendaSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Prenda_SueldosRepository implements Prenda_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'prenda';

    protected string $tableAnitaVariantes = 'prendart';

    public function __construct(Prenda_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->newQuery()->orderBy('orden')->orderBy('codigo')->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leePrenda($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PrendaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PrendaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('prenda_sueldos.*')
            ->withCount('variantes');

        if (PrendaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PrendaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('prenda_sueldos.orden')->orderBy('prenda_sueldos.codigo');

        if (isset($flPaginando)) {
            return $flPaginando ? $query->paginate(15) : $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $prenda = $this->model->create($this->normalizar($data, null));
            $this->sincronizarVariantes($prenda, $data['variantes'] ?? []);

            return $prenda->fresh('variantes');
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $prenda = $this->model->findOrFail($id);
            $prenda->update($this->normalizar($data, $prenda));
            $this->sincronizarVariantes($prenda, $data['variantes'] ?? []);

            return $prenda->fresh('variantes');
        });
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->with(['variantes.color', 'variantes.talle', 'variantes.articulo'])->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->with(['variantes.color', 'variantes.talle', 'variantes.articulo'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data, ?Prenda_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $porc = $data['porcentaje_pedido'] ?? null;
        $vidaUtil = $data['vida_util_meses'] ?? null;

        return [
            'codigo' => $codigo,
            'descripcion' => mb_substr(trim((string) ($data['descripcion'] ?? '')), 0, 60),
            'marca' => ! empty($data['marca']) ? mb_substr(trim((string) $data['marca']), 0, 30) : null,
            'es_seguridad' => (bool) ($data['es_seguridad'] ?? false),
            'vida_util_meses' => ($vidaUtil === '' || $vidaUtil === null) ? null : max(0, (int) $vidaUtil),
            'requiere_certificacion' => (bool) ($data['requiere_certificacion'] ?? false),
            'norma' => ! empty($data['norma']) ? mb_substr(trim((string) $data['norma']), 0, 80) : null,
            'porcentaje_pedido' => ($porc === '' || $porc === null) ? null : (float) $porc,
            'activo' => (bool) ($data['activo'] ?? true),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
    }

    /**
     * Reemplaza la matriz de variantes (color × talle → artículo) de la prenda.
     *
     * @param  array<int, array<string, mixed>>  $variantes
     */
    private function sincronizarVariantes(Prenda_Sueldos $prenda, array $variantes): void
    {
        Prenda_Articulo_Sueldos::query()->where('prenda_id', $prenda->id)->delete();

        $vistos = [];
        foreach ($variantes as $v) {
            $colorId = (int) ($v['color_id'] ?? 0);
            $talleId = (int) ($v['talle_id'] ?? 0);
            if ($colorId === 0 || $talleId === 0) {
                continue;
            }
            $clave = $colorId.'-'.$talleId;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $sku = trim((string) ($v['sku'] ?? ''));
            $articuloId = $this->resolverArticuloId($sku);

            Prenda_Articulo_Sueldos::create([
                'prenda_id' => $prenda->id,
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'sku' => $sku !== '' ? $sku : null,
                'articulo_id' => $articuloId,
            ]);
        }
    }

    private function resolverArticuloId(string $sku): ?int
    {
        if ($sku === '') {
            return null;
        }
        $skuSinCeros = ltrim($sku, '0');
        $articulo = Articulo::query()
            ->where('sku', $sku)
            ->orWhereRaw("TRIM(LEADING '0' FROM sku) = ?", [$skuSinCeros])
            ->first(['id']);

        return $articulo?->id;
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    /**
     * Sync pull unilateral desde Anita (bridge, base sueldos): trae `prenda` y su matriz
     * `prendart`. Inserta faltantes por código; no actualiza ni borra ni escribe hacia Anita.
     *
     * @return array{en_anita: int, importados: int, omitidos: int, variantes: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '600');

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'variantes' => 0, 'errores' => []];

        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'pren_prenda, pren_desc, pren_porc_pedido, pren_marca, pren_seguridad',
            'orderBy' => 'pren_prenda',
        ]));

        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = 'prenda: '.(string) $parsed['error_lectura'];

            return $resultado;
        }

        $mapaPrendaCodigoId = [];
        foreach ($parsed['filas'] as $row) {
            $resultado['en_anita']++;
            $codigo = (int) ($row->pren_prenda ?? 0);
            if ($codigo <= 0) {
                $resultado['omitidos']++;
                continue;
            }

            $existente = $this->findPorCodigo($codigo);
            if ($existente) {
                $mapaPrendaCodigoId[$codigo] = (int) $existente->id;
                $resultado['omitidos']++;
                continue;
            }

            $desc = mb_substr(trim((string) ($row->pren_desc ?? '')), 0, 60);
            if ($desc === '') {
                $desc = (string) $codigo;
            }
            $marca = trim((string) ($row->pren_marca ?? ''));
            $porc = $row->pren_porc_pedido ?? null;

            $prenda = $this->model->create([
                'codigo' => $codigo,
                'descripcion' => $desc,
                'marca' => $marca !== '' ? mb_substr($marca, 0, 30) : null,
                'es_seguridad' => $this->esSeguridadAnita($row->pren_seguridad ?? null),
                'porcentaje_pedido' => ($porc === '' || $porc === null) ? null : (float) $porc,
                'activo' => true,
                'orden' => 0,
            ]);
            $mapaPrendaCodigoId[$codigo] = (int) $prenda->id;
            $resultado['importados']++;
        }

        $resultado['variantes'] = $this->sincronizarVariantesAnita($mapaPrendaCodigoId, $resultado['errores']);

        return $resultado;
    }

    /**
     * Trae `prendart` y crea variantes color×talle→artículo para las prendas ya presentes.
     *
     * @param  array<int, int>  $mapaPrendaCodigoId
     * @param  list<string>  $errores
     */
    private function sincronizarVariantesAnita(array $mapaPrendaCodigoId, array &$errores): int
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnitaVariantes,
            'campos' => 'part_prenda, part_color, part_talle, part_articulo',
            'orderBy' => 'part_prenda, part_color, part_talle',
        ]));

        if (! empty($parsed['error_lectura'])) {
            $errores[] = 'prendart: '.(string) $parsed['error_lectura'];

            return 0;
        }

        $mapaColor = Color::query()->whereNotNull('codigo')->pluck('id', 'codigo')->all();
        $mapaTalle = Talle::query()->whereNotNull('codigo')->pluck('id', 'codigo')->all();

        $creadas = 0;
        foreach ($parsed['filas'] as $row) {
            $codPrenda = (int) ($row->part_prenda ?? 0);
            $prendaId = $mapaPrendaCodigoId[$codPrenda] ?? (int) (optional($this->findPorCodigo($codPrenda))->id ?? 0);
            if ($prendaId === 0) {
                continue;
            }
            $colorId = (int) ($mapaColor[(int) ($row->part_color ?? 0)] ?? 0);
            $talleId = (int) ($mapaTalle[(int) ($row->part_talle ?? 0)] ?? 0);
            if ($colorId === 0 || $talleId === 0) {
                continue;
            }

            $existe = Prenda_Articulo_Sueldos::query()
                ->where('prenda_id', $prendaId)
                ->where('color_id', $colorId)
                ->where('talle_id', $talleId)
                ->exists();
            if ($existe) {
                continue;
            }

            $sku = trim((string) ($row->part_articulo ?? ''));
            Prenda_Articulo_Sueldos::create([
                'prenda_id' => $prendaId,
                'color_id' => $colorId,
                'talle_id' => $talleId,
                'sku' => $sku !== '' ? $sku : null,
                'articulo_id' => $this->resolverArticuloId($sku),
            ]);
            $creadas++;
        }

        return $creadas;
    }

    private function esSeguridadAnita($valor): bool
    {
        $v = strtoupper(trim((string) $valor));

        return in_array($v, ['S', '1', 'SI', 'Y', 'T'], true);
    }
}
