<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableLayout;
use App\Models\Contable\ReporteContableLayoutColumna;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD / clonado de layouts de columnas por informe.
 */
class ReporteDefinibleLayoutSupport
{
    public function __construct(
        private readonly ReporteDefinibleLayoutResolver $resolver,
    ) {
    }

    public function clonarDesdeSistema(int $layoutOrigenId, int $reporteId, ?string $codigoNuevo = null): ReporteContableLayout
    {
        $src = ReporteContableLayout::query()
            ->with('columnas')
            ->findOrFail($layoutOrigenId);

        $codigo = $codigoNuevo !== null && trim($codigoNuevo) !== ''
            ? strtoupper(trim($codigoNuevo))
            : $src->codigo;

        if (ReporteContableLayout::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('codigo', $codigo)
            ->exists()) {
            $codigo = $codigo.'_'.substr((string) time(), -4);
        }

        return DB::transaction(function () use ($src, $reporteId, $codigo) {
            $dst = ReporteContableLayout::query()->create([
                'reporte_contable_id' => $reporteId,
                'codigo' => $codigo,
                'nombre' => $src->nombre.(str_contains((string) $src->nombre, '(copia)') ? '' : ' (copia)'),
                'es_default' => false,
                'activo' => true,
                'orden' => (int) $src->orden,
            ]);
            foreach ($src->columnas as $c) {
                $dst->columnas()->create([
                    'key' => (string) $c->key,
                    'label' => (string) $c->label,
                    'tipo' => (string) $c->tipo,
                    'orden' => (int) $c->orden,
                    'meta' => $c->meta,
                ]);
            }

            return $dst->load('columnas');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function crearLayoutInforme(int $reporteId, array $data): ReporteContableLayout
    {
        $codigo = strtoupper(trim((string) ($data['codigo'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($codigo === '' || $nombre === '') {
            throw ValidationException::withMessages(['codigo' => 'Código y nombre son obligatorios.']);
        }
        if (ReporteContableLayout::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('codigo', $codigo)
            ->exists()) {
            throw ValidationException::withMessages(['codigo' => 'Ya existe un layout con ese código en el informe.']);
        }

        $layout = ReporteContableLayout::query()->create([
            'reporte_contable_id' => $reporteId,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'es_default' => ! empty($data['es_default']),
            'activo' => true,
            'orden' => (int) ($data['orden'] ?? 100),
        ]);

        if (! empty($data['es_default'])) {
            $this->marcarDefault($reporteId, (int) $layout->id);
        }

        // Semilla mínima: una columna Actual
        $layout->columnas()->create([
            'key' => 'actual',
            'label' => 'Actual',
            'tipo' => ReporteDefinibleLayoutResolver::TIPO_ACTUAL,
            'orden' => 1,
        ]);

        return $layout->load('columnas');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizarLayout(ReporteContableLayout $layout, array $data): ReporteContableLayout
    {
        if ($layout->reporte_contable_id === null) {
            throw ValidationException::withMessages(['layout' => 'No se pueden editar presets de sistema; cloná primero.']);
        }
        if (array_key_exists('nombre', $data)) {
            $layout->nombre = trim((string) $data['nombre']);
        }
        if (array_key_exists('activo', $data)) {
            $layout->activo = (bool) $data['activo'];
        }
        if (array_key_exists('orden', $data)) {
            $layout->orden = (int) $data['orden'];
        }
        $layout->save();

        if (! empty($data['es_default']) && $layout->reporte_contable_id) {
            $this->marcarDefault((int) $layout->reporte_contable_id, (int) $layout->id);
        }

        return $layout->fresh('columnas');
    }

    public function eliminarLayout(ReporteContableLayout $layout): void
    {
        if ($layout->reporte_contable_id === null) {
            throw ValidationException::withMessages(['layout' => 'No se pueden borrar presets de sistema.']);
        }
        $reporteId = (int) $layout->reporte_contable_id;
        $layout->columnas()->delete();
        $layout->delete();

        $rep = ReporteContable::query()->find($reporteId);
        if ($rep && (int) $rep->layout_default_id === (int) $layout->id) {
            $rep->layout_default_id = null;
            $rep->save();
        }
    }

    public function marcarDefault(int $reporteId, int $layoutId): void
    {
        $layout = ReporteContableLayout::query()->findOrFail($layoutId);
        if ((int) $layout->reporte_contable_id !== $reporteId && $layout->reporte_contable_id !== null) {
            throw ValidationException::withMessages(['layout' => 'Layout no pertenece al informe.']);
        }
        ReporteContableLayout::query()
            ->where('reporte_contable_id', $reporteId)
            ->update(['es_default' => false]);
        if ($layout->reporte_contable_id !== null) {
            $layout->es_default = true;
            $layout->save();
        }
        ReporteContable::query()
            ->whereKey($reporteId)
            ->update(['layout_default_id' => $layoutId]);
    }

    /**
     * Copia layouts del informe origen al destino (no presets de sistema).
     *
     * @return array<int, int> mapa layoutOrigenId => layoutNuevoId
     */
    public function copiarLayoutsInforme(ReporteContable $origen, ReporteContable $destino): array
    {
        $map = [];
        $layouts = ReporteContableLayout::query()
            ->with('columnas')
            ->where('reporte_contable_id', $origen->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        foreach ($layouts as $src) {
            $dst = ReporteContableLayout::query()->create([
                'reporte_contable_id' => $destino->id,
                'codigo' => $src->codigo,
                'nombre' => $src->nombre,
                'es_default' => (bool) $src->es_default,
                'activo' => (bool) $src->activo,
                'orden' => (int) $src->orden,
            ]);
            foreach ($src->columnas as $c) {
                $dst->columnas()->create([
                    'key' => (string) $c->key,
                    'label' => (string) $c->label,
                    'tipo' => (string) $c->tipo,
                    'orden' => (int) $c->orden,
                    'meta' => $c->meta,
                ]);
            }
            $map[(int) $src->id] = (int) $dst->id;
        }

        if ($origen->layout_default_id && isset($map[(int) $origen->layout_default_id])) {
            $destino->layout_default_id = $map[(int) $origen->layout_default_id];
            $destino->save();
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function agregarColumna(ReporteContableLayout $layout, array $data): ReporteContableLayoutColumna
    {
        $this->assertEditable($layout);
        $tipos = ReporteDefinibleLayoutResolver::tiposColumna();
        $tipo = (string) ($data['tipo'] ?? '');
        if (! isset($tipos[$tipo])) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de columna inválido.']);
        }
        $key = trim((string) ($data['key'] ?? ''));
        if ($key === '') {
            $key = $tipo.'_'.((int) $layout->columnas()->max('orden') + 1);
        }
        $key = preg_replace('/[^a-z0-9_]/i', '_', strtolower($key)) ?: $tipo;
        $label = trim((string) ($data['label'] ?? $tipos[$tipo]));
        $orden = (int) ($data['orden'] ?? ((int) $layout->columnas()->max('orden') + 1));

        if ($layout->columnas()->where('key', $key)->exists()) {
            $key .= '_'.substr((string) time(), -3);
        }

        return $layout->columnas()->create([
            'key' => $key,
            'label' => $label !== '' ? $label : $tipos[$tipo],
            'tipo' => $tipo,
            'orden' => $orden,
            'meta' => $data['meta'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizarColumna(ReporteContableLayoutColumna $col, array $data): ReporteContableLayoutColumna
    {
        $layout = $col->layout;
        $this->assertEditable($layout);
        if (array_key_exists('label', $data)) {
            $col->label = trim((string) $data['label']);
        }
        if (array_key_exists('tipo', $data)) {
            $tipo = (string) $data['tipo'];
            if (! isset(ReporteDefinibleLayoutResolver::tiposColumna()[$tipo])) {
                throw ValidationException::withMessages(['tipo' => 'Tipo inválido.']);
            }
            $col->tipo = $tipo;
        }
        if (array_key_exists('orden', $data)) {
            $col->orden = (int) $data['orden'];
        }
        if (array_key_exists('key', $data) && trim((string) $data['key']) !== '') {
            $col->key = preg_replace('/[^a-z0-9_]/i', '_', strtolower(trim((string) $data['key']))) ?: $col->key;
        }
        if (array_key_exists('meta', $data)) {
            $meta = $data['meta'];
            if ($meta === null || $meta === '' || $meta === []) {
                $col->meta = null;
            } elseif (is_array($meta)) {
                $col->meta = $meta;
            }
        }
        $col->save();

        return $col;
    }

    public function eliminarColumna(ReporteContableLayoutColumna $col): void
    {
        $this->assertEditable($col->layout);
        if ($col->layout->columnas()->count() <= 1) {
            throw ValidationException::withMessages(['columna' => 'El layout debe tener al menos una columna.']);
        }
        $col->delete();
    }

    /**
     * @param  list<array{id: int, orden: int}>  $ordenes
     */
    public function reordenarColumnas(ReporteContableLayout $layout, array $ordenes): void
    {
        $this->assertEditable($layout);
        foreach ($ordenes as $item) {
            $id = (int) ($item['id'] ?? 0);
            $orden = (int) ($item['orden'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            ReporteContableLayoutColumna::query()
                ->where('reporte_contable_layout_id', $layout->id)
                ->whereKey($id)
                ->update(['orden' => $orden]);
        }
    }

    private function assertEditable(?ReporteContableLayout $layout): void
    {
        if (! $layout || $layout->reporte_contable_id === null) {
            throw ValidationException::withMessages(['layout' => 'Solo layouts del informe son editables. Cloná un preset.']);
        }
    }

    /**
     * @return array{sistema: list<array<string, mixed>>, informe: list<array<string, mixed>>, layout_default_id: int|null}
     */
    public function payloadUi(int $reporteId): array
    {
        $sistema = [];
        foreach (ReporteContableLayout::query()->sistema()->with('columnas')->orderBy('orden')->orderBy('codigo')->get() as $lay) {
            $sistema[] = $this->layoutToArray($lay, true);
        }
        $informe = [];
        foreach (ReporteContableLayout::query()
            ->where('reporte_contable_id', $reporteId)
            ->with('columnas')
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get() as $lay) {
            $informe[] = $this->layoutToArray($lay, false);
        }
        $defaultId = ReporteContable::query()->whereKey($reporteId)->value('layout_default_id');

        return [
            'sistema' => $sistema,
            'informe' => $informe,
            'layout_default_id' => $defaultId ? (int) $defaultId : null,
            'tipos_columna' => ReporteDefinibleLayoutResolver::tiposColumna(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutToArray(ReporteContableLayout $lay, bool $esSistema): array
    {
        $cols = [];
        foreach ($lay->columnas as $c) {
            $cols[] = [
                'id' => (int) $c->id,
                'key' => (string) $c->key,
                'label' => (string) $c->label,
                'tipo' => (string) $c->tipo,
                'orden' => (int) $c->orden,
            ];
        }

        return [
            'id' => (int) $lay->id,
            'codigo' => (string) $lay->codigo,
            'nombre' => (string) $lay->nombre,
            'es_default' => (bool) $lay->es_default,
            'activo' => (bool) $lay->activo,
            'orden' => (int) $lay->orden,
            'es_sistema' => $esSistema,
            'columnas' => $cols,
        ];
    }
}
