<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Estado;
use App\Support\Stock\FormulaArticuloSku;
use App\Models\Stock\Formula_Articulo_Hijo;
use App\Repositories\Stock\Formula_Articulo_ArchivoRepositoryInterface;
use App\Repositories\Stock\Formula_Articulo_EstadoRepositoryInterface;
use App\Repositories\Stock\Formula_Articulo_HijoRepositoryInterface;
use App\Repositories\Stock\Formula_ArticuloRepositoryInterface;
use Auth;
use Carbon\Carbon;
use DB;

class FormulaArticuloService
{
    public function __construct(
        private Formula_ArticuloRepositoryInterface $formulaArticuloRepository,
        private Formula_Articulo_EstadoRepositoryInterface $formulaArticuloEstadoRepository,
        private Formula_Articulo_HijoRepositoryInterface $formulaArticuloHijoRepository,
        private Formula_Articulo_ArchivoRepositoryInterface $formulaArticuloArchivoRepository,
    ) {}

    public function guardar($request): array
    {
        $data = $request->all();
        $estadoInicial = $data['estado'] ?? Formula_Articulo_Estado::$enumEstado[0]['nombre'];

        $validacion = $this->validaLineasYSubformulas(null, $data);
        if ($validacion !== null) {
            return ['mensaje' => 'error', 'errores' => $validacion];
        }

        $articuloCabeceraId = isset($data['articulo_id']) && $data['articulo_id'] !== '' && $data['articulo_id'] !== null
            ? (int) $data['articulo_id']
            : null;

        $codigo = isset($data['codigo']) ? trim((string) $data['codigo']) : '';
        $cabecera = [
            'articulo_id' => $articuloCabeceraId,
            'codigo' => $codigo !== '' ? mb_substr($codigo, 0, 50) : null,
            'detalle' => $data['detalle'] ?? null,
            'cantidadunidad' => (float) ($data['cantidadunidad'] ?? 0),
            'estado' => $estadoInicial,
            'creousuario_id' => Auth::user()->id,
        ];

        DB::beginTransaction();
        try {
            $formula = $this->formulaArticuloRepository->create($cabecera);

            $this->formulaArticuloEstadoRepository->creaEstado(
                (int) $formula->id,
                Carbon::now()->toDateTimeString(),
                $estadoInicial,
                (int) Auth::user()->id,
                'Alta de fórmula'
            );

            $this->formulaArticuloHijoRepository->syncFromRequest($data, (int) $formula->id);
            $this->formulaArticuloArchivoRepository->create($request, (int) $formula->id);

            if ($articuloCabeceraId !== null) {
                Articulo::where('id', $articuloCabeceraId)->update(['formula' => $formula->id]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok', 'id' => $formula->id];
    }

    public function actualizar($request, int $id): array
    {
        $existente = $this->formulaArticuloRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Fórmula no encontrada.'];
        }

        $data = $request->all();
        $validacion = $this->validaLineasYSubformulas($id, $data);
        if ($validacion !== null) {
            return ['mensaje' => 'error', 'errores' => $validacion];
        }

        $estadoNuevo = $data['estado'] ?? $existente->estado;
        $articuloCabeceraId = isset($data['articulo_id']) && $data['articulo_id'] !== '' && $data['articulo_id'] !== null
            ? (int) $data['articulo_id']
            : null;

        $codigo = isset($data['codigo']) ? trim((string) $data['codigo']) : '';
        $cabecera = [
            'articulo_id' => $articuloCabeceraId,
            'codigo' => $codigo !== '' ? mb_substr($codigo, 0, 50) : null,
            'detalle' => $data['detalle'] ?? null,
            'cantidadunidad' => (float) ($data['cantidadunidad'] ?? 0),
            'estado' => $estadoNuevo,
        ];

        DB::beginTransaction();
        try {
            if (($existente->estado ?? '') !== $estadoNuevo) {
                $this->formulaArticuloEstadoRepository->creaEstado(
                    $id,
                    Carbon::now()->toDateTimeString(),
                    (string) $estadoNuevo,
                    (int) Auth::user()->id,
                    trim((string) ($data['observacion_estado'] ?? ''))
                );
            }

            $this->formulaArticuloRepository->update($cabecera, $id);
            $this->formulaArticuloHijoRepository->syncFromRequest($data, $id);
            $this->formulaArticuloArchivoRepository->update($request, $id);

            Articulo::where('formula', $id)->update(['formula' => 0]);
            if ($articuloCabeceraId !== null) {
                Articulo::where('id', $articuloCabeceraId)->update(['formula' => $id]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function eliminar(int $id): array
    {
        DB::beginTransaction();
        try {
            Articulo::where('formula', $id)->update(['formula' => 0]);
            $this->formulaArticuloRepository->delete($id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validaLineasYSubformulas(?int $formulaId, array $data): ?string
    {
        $articulo_ids = $data['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            return null;
        }

        $hijas = [];
        for ($i = 0; $i < count($articulo_ids); $i++) {
            $aid = $articulo_ids[$i] ?? null;
            $fid = $data['formula_hija_ids'][$i] ?? null;
            $aid = ($aid === '' || $aid === null) ? null : (int) $aid;
            $fid = ($fid === '' || $fid === null) ? null : (int) $fid;
            if ($aid === null && $fid === null) {
                continue;
            }
            if ($fid !== null) {
                if ($formulaId !== null && $fid === (int) $formulaId) {
                    return 'Un ítem no puede referenciar la misma fórmula como subfórmula.';

                }
                $hijas[] = $fid;
            }
        }

        $raiz = $formulaId ?? 0;
        if ($raiz > 0 && in_array($raiz, $hijas, true)) {
            return 'Referencia circular en subfórmulas.';
        }

        foreach (array_unique($hijas) as $hijaId) {
            if ($this->formulaContieneAncestro($hijaId, $raiz)) {
                return 'La subfórmula seleccionada genera un ciclo con esta fórmula.';

            }
        }

        return null;
    }

    /**
     * ¿Desde $formulaId se alcanza $ancestroId siguiendo solo vínculos formula_hija_id en la base?
     */
    private function formulaContieneAncestro(int $formulaId, int $ancestroId): bool
    {
        if ($ancestroId <= 0) {
            return false;
        }
        if ($formulaId === $ancestroId) {
            return true;
        }

        $visitados = [];
        $frontera = [$formulaId];

        while ($frontera !== []) {
            $actual = array_pop($frontera);
            if (isset($visitados[$actual])) {
                continue;
            }
            $visitados[$actual] = true;

            $hijas = Formula_Articulo_Hijo::query()
                ->where('formula_articulo_id', $actual)
                ->whereNotNull('formula_hija_id')
                ->pluck('formula_hija_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->all();

            foreach ($hijas as $h) {
                if ($h === $ancestroId) {
                    return true;
                }
                if (! isset($visitados[$h])) {
                    $frontera[] = $h;
                }
            }
        }

        return false;
    }

    /**
     * Resuelve el id de formula_articulo (ERP) para un artículo.
     * Prioriza vínculo por formula_articulo.articulo_id; evita usar articulo.formula si apunta a otra cabecera.
     *
     * @return array{formula_id: int|null, origen: string|null, mensaje: string|null}
     */
    public function resolverIdParaArticulo(int $articuloId): array
    {
        $articulo = Articulo::query()->select('id', 'sku', 'descripcion', 'formula')->find($articuloId);
        if (! $articulo) {
            return [
                'formula_id' => null,
                'origen' => null,
                'mensaje' => 'Artículo no encontrado.',
            ];
        }

        $activa = $this->nombreEstadoActiva();

        $porArticuloId = Formula_Articulo::query()
            ->where('articulo_id', $articuloId)
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [$activa])
            ->orderByDesc('id')
            ->first();

        if ($porArticuloId) {
            return [
                'formula_id' => (int) $porArticuloId->id,
                'origen' => 'articulo_id',
                'mensaje' => null,
            ];
        }

        $codigoDesdeSku = FormulaArticuloSku::codigoDesdeSku((string) $articulo->sku);
        if ($codigoDesdeSku !== null) {
            $porCodigoSku = Formula_Articulo::query()
                ->where(function ($q) use ($codigoDesdeSku) {
                    $q->where('anita_stkcm_formula', $codigoDesdeSku)
                        ->orWhere('codigo', (string) $codigoDesdeSku);
                })
                ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [$activa])
                ->orderByDesc('id')
                ->first();

            if ($porCodigoSku) {
                return [
                    'formula_id' => (int) $porCodigoSku->id,
                    'origen' => 'codigo_sku',
                    'mensaje' => null,
                ];
            }
        }

        $legacy = (int) ($articulo->formula ?? 0);
        if ($legacy <= 0) {
            return [
                'formula_id' => null,
                'origen' => null,
                'mensaje' => 'No hay fórmula vinculada a este artículo. Cree o sincronice una fórmula con articulo_id = '.$articuloId.' (SKU '.$articulo->sku.').',
            ];
        }

        $porAnita = Formula_Articulo::query()
            ->where('anita_stkcm_formula', $legacy)
            ->orderByRaw('CASE WHEN estado = ? THEN 0 ELSE 1 END', [$activa])
            ->orderByDesc('id')
            ->first();

        if ($porAnita) {
            return [
                'formula_id' => (int) $porAnita->id,
                'origen' => 'anita_stkcm_formula',
                'mensaje' => null,
            ];
        }

        $porId = Formula_Articulo::query()->find($legacy);
        if ($porId && ($porId->articulo_id === null || (int) $porId->articulo_id === $articuloId)) {
            return [
                'formula_id' => (int) $porId->id,
                'origen' => 'id',
                'mensaje' => null,
            ];
        }

        return [
            'formula_id' => null,
            'origen' => null,
            'mensaje' => 'El campo Fórmula (id) del artículo ('.$legacy.') no corresponde a una fórmula de este SKU. Vincule la fórmula en el CRUD de fórmulas con articulo_id = '.$articuloId.' o sincronice desde Anita.',
        ];
    }

    private function nombreEstadoActiva(): string
    {
        foreach (Formula_Articulo_Estado::$enumEstado as $e) {
            if (($e['valor'] ?? '') === 'A') {
                return (string) $e['nombre'];
            }
        }

        return 'ACTIVA';
    }
}
