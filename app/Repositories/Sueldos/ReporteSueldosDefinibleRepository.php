<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\ReporteSueldosDefinible;
use App\Models\Sueldos\ReporteSueldosDefinibleColumna;
use App\Models\Sueldos\ReporteSueldosDefinibleConcepto;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleAclSupport;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleListadoFiltros;
use App\Support\Sueldos\ReporteDefinible\ReporteSueldosDefinibleSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteSueldosDefinibleRepository
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeReportes($filtros = null, bool $flPaginando = true)
    {
        $query = ReporteSueldosDefinible::query()
            ->withCount('columnas')
            ->orderBy('codigo');

        if (is_array($filtros)) {
            ReporteSueldosDefinibleListadoFiltros::aplicar($query, $filtros);
        } elseif (is_string($filtros) && $filtros !== '') {
            $query->where(function ($q) use ($filtros) {
                $q->where('titulo', 'like', '%'.$filtros.'%')
                    ->orWhere('codigo', 'like', '%'.$filtros.'%');
            });
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            app(ReporteSueldosDefinibleAclSupport::class)->filtrarQuery($query, $usuarioId);
        }

        return $flPaginando ? $query->paginate(10) : $query->get();
    }

    public function find(int $id): ?ReporteSueldosDefinible
    {
        return ReporteSueldosDefinible::query()->find($id);
    }

    public function findConEstructura(int $id): ?ReporteSueldosDefinible
    {
        return ReporteSueldosDefinible::query()
            ->with(['columnas.conceptos.concepto:id,codigo,descripcion,activo', 'owner:id,nombre'])
            ->withCount('columnas')
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ReporteSueldosDefinible
    {
        if (empty($data['codigo'])) {
            $data['codigo'] = ((int) ReporteSueldosDefinible::query()->max('codigo')) + 1;
        }

        return ReporteSueldosDefinible::query()->create([
            'codigo' => (int) $data['codigo'],
            'titulo' => trim((string) $data['titulo']),
            'tipo' => $data['tipo'] ?? ReporteSueldosDefinibleSupport::TIPO_GENERICO,
            'asociado_codigo' => $data['asociado_codigo'] ?? null,
            'empresa_id' => $data['empresa_id'] ?? null,
            'owner_id' => $data['owner_id'] ?? Auth::id(),
            'origen' => $data['origen'] ?? 'manual',
            'anita_listado' => $data['anita_listado'] ?? null,
            'activo' => (bool) ($data['activo'] ?? true),
            'observaciones' => $data['observaciones'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): ?ReporteSueldosDefinible
    {
        $reporte = $this->find($id);
        if (! $reporte) {
            return null;
        }
        $reporte->update([
            'titulo' => trim((string) ($data['titulo'] ?? $reporte->titulo)),
            'tipo' => $data['tipo'] ?? $reporte->tipo,
            'asociado_codigo' => array_key_exists('asociado_codigo', $data) ? $data['asociado_codigo'] : $reporte->asociado_codigo,
            'empresa_id' => array_key_exists('empresa_id', $data) ? $data['empresa_id'] : $reporte->empresa_id,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : $reporte->activo,
            'observaciones' => array_key_exists('observaciones', $data) ? $data['observaciones'] : $reporte->observaciones,
            'owner_id' => $reporte->owner_id ?: (Auth::id() ?: $reporte->owner_id),
        ]);

        return $reporte->fresh();
    }

    public function delete(int $id): bool
    {
        $reporte = $this->find($id);
        if (! $reporte) {
            return false;
        }

        return (bool) $reporte->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardarColumna(int $reporteId, array $data, ?int $columnaId = null): ReporteSueldosDefinibleColumna
    {
        $payload = [
            'reporte_sueldos_definible_id' => $reporteId,
            'nro_columna' => (int) ($data['nro_columna'] ?? 1),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'contenido' => (string) ($data['contenido'] ?? ReporteSueldosDefinibleSupport::CONTENIDO_IMPORTE),
            'campo_empleado' => ($data['campo_empleado'] ?? null) ?: null,
            'largo' => ($data['largo'] ?? null) ?: null,
            'formula' => ($data['formula'] ?? null) ?: null,
            'orden' => (int) ($data['orden'] ?? $data['nro_columna'] ?? 0),
        ];

        if ($columnaId) {
            $col = ReporteSueldosDefinibleColumna::query()
                ->where('reporte_sueldos_definible_id', $reporteId)
                ->where('id', $columnaId)
                ->firstOrFail();
            $col->update($payload);

            return $col->fresh();
        }

        return ReporteSueldosDefinibleColumna::query()->create($payload);
    }

    public function eliminarColumna(int $reporteId, int $columnaId): void
    {
        $col = ReporteSueldosDefinibleColumna::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->where('id', $columnaId)
            ->first();
        if ($col) {
            $col->conceptos()->delete();
            $col->delete();
        }
    }

    /**
     * @param  list<array{concepto_codigo:int,signo?:string,orden?:int}>  $conceptos
     */
    public function sincronizarConceptos(int $columnaId, array $conceptos): void
    {
        DB::transaction(function () use ($columnaId, $conceptos) {
            ReporteSueldosDefinibleConcepto::query()->where('columna_id', $columnaId)->delete();
            $orden = 0;
            foreach ($conceptos as $c) {
                $cod = (int) ($c['concepto_codigo'] ?? 0);
                if ($cod <= 0) {
                    continue;
                }
                $orden++;
                ReporteSueldosDefinibleConcepto::query()->create([
                    'columna_id' => $columnaId,
                    'concepto_codigo' => $cod,
                    'orden' => (int) ($c['orden'] ?? $orden),
                    'signo' => (($c['signo'] ?? '+') === '-') ? '-' : '+',
                ]);
            }
        });
    }

    public function copiar(int $id): ?ReporteSueldosDefinible
    {
        $origen = $this->findConEstructura($id);
        if (! $origen) {
            return null;
        }

        return DB::transaction(function () use ($origen) {
            $nuevo = $this->create([
                'codigo' => ((int) ReporteSueldosDefinible::query()->max('codigo')) + 1,
                'titulo' => $origen->titulo.' (copia)',
                'tipo' => $origen->tipo,
                'asociado_codigo' => $origen->asociado_codigo,
                'empresa_id' => $origen->empresa_id,
                'origen' => 'manual',
                'activo' => true,
                'observaciones' => $origen->observaciones,
            ]);
            foreach ($origen->columnas as $col) {
                $nuevaCol = ReporteSueldosDefinibleColumna::query()->create([
                    'reporte_sueldos_definible_id' => $nuevo->id,
                    'nro_columna' => $col->nro_columna,
                    'descripcion' => $col->descripcion,
                    'contenido' => $col->contenido,
                    'campo_empleado' => $col->campo_empleado,
                    'largo' => $col->largo,
                    'formula' => $col->formula,
                    'orden' => $col->orden,
                ]);
                foreach ($col->conceptos as $con) {
                    ReporteSueldosDefinibleConcepto::query()->create([
                        'columna_id' => $nuevaCol->id,
                        'concepto_codigo' => $con->concepto_codigo,
                        'orden' => $con->orden,
                        'signo' => $con->signo,
                    ]);
                }
            }

            return $nuevo;
        });
    }

    public function crearDesdePlantilla(int $plantillaId): ?ReporteSueldosDefinible
    {
        $plantilla = ReporteSueldosDefinible::query()->where('id', $plantillaId)->where('origen', 'plantilla')->first();
        if (! $plantilla) {
            return null;
        }
        $copia = $this->copiar($plantilla->id);
        if ($copia) {
            $copia->update(['titulo' => $plantilla->titulo, 'origen' => 'manual']);
        }

        return $copia;
    }
}
