<?php

namespace App\Repositories\Contable;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableCuenta;
use App\Models\Contable\ReporteContableRubro;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleAclSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleJerarquiaSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleLayoutSupport;
use App\Support\Contable\ReporteDefinible\ReporteDefinibleSupport;
use App\Support\Contable\ReporteDefinibleListadoFiltros;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReporteContableRepository
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public function leeReportes($filtros = null, bool $flPaginando = true)
    {
        $query = ReporteContable::query()
            ->withCount('rubros')
            ->orderBy('codigo');

        if (is_array($filtros)) {
            ReporteDefinibleListadoFiltros::aplicar($query, $filtros);
        } elseif (is_string($filtros) && $filtros !== '') {
            $query->where(function ($q) use ($filtros) {
                $q->where('nombre', 'like', '%'.$filtros.'%')
                    ->orWhere('codigo', 'like', '%'.$filtros.'%');
            });
        }

        $usuarioId = (int) (Auth::id() ?? 0);
        if ($usuarioId > 0) {
            app(ReporteDefinibleAclSupport::class)->filtrarQuery($query, $usuarioId);
        }

        return $flPaginando ? $query->paginate(10) : $query->get();
    }

    public function find(int $id): ?ReporteContable
    {
        return ReporteContable::query()->find($id);
    }

    public function findConEstructura(int $id): ?ReporteContable
    {
        return ReporteContable::query()
            ->with([
                'rubros' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'rubros.cuentas' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
                'rubros.cuentas.cuentacontable:id,codigo,nombre',
                'rubros.cuentas.empresa:id,nombre,codigo',
                'rubros.cuentas.ccostos',
            ])
            ->withCount('rubros')
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ReporteContable
    {
        if (empty($data['codigo'])) {
            $data['codigo'] = ((int) ReporteContable::query()->max('codigo')) + 1;
        }

        return ReporteContable::query()->create([
            'codigo' => (int) $data['codigo'],
            'nombre' => trim((string) $data['nombre']),
            'titulo1' => trim((string) ($data['titulo1'] ?? '')) ?: null,
            'titulo2' => trim((string) ($data['titulo2'] ?? '')) ?: null,
            'tipo' => $data['tipo'] ?? ReporteDefinibleSupport::TIPO_REPORTE_OTRO,
            'origen' => $data['origen'] ?? 'manual',
            'anita_informe' => $data['anita_informe'] ?? null,
            'activo' => (bool) ($data['activo'] ?? true),
            'observaciones' => trim((string) ($data['observaciones'] ?? '')) ?: null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data, int $id): ReporteContable
    {
        $reporte = ReporteContable::query()->findOrFail($id);
        $reporte->fill([
            'nombre' => trim((string) ($data['nombre'] ?? $reporte->nombre)),
            'titulo1' => array_key_exists('titulo1', $data)
                ? (trim((string) $data['titulo1']) ?: null)
                : $reporte->titulo1,
            'titulo2' => array_key_exists('titulo2', $data)
                ? (trim((string) $data['titulo2']) ?: null)
                : $reporte->titulo2,
            'tipo' => $data['tipo'] ?? $reporte->tipo,
            'activo' => array_key_exists('activo', $data)
                ? (bool) $data['activo']
                : $reporte->activo,
            'observaciones' => array_key_exists('observaciones', $data)
                ? (trim((string) $data['observaciones']) ?: null)
                : $reporte->observaciones,
            'valido_desde' => array_key_exists('valido_desde', $data)
                ? ($data['valido_desde'] ?: null)
                : $reporte->valido_desde,
            'valido_hasta' => array_key_exists('valido_hasta', $data)
                ? ($data['valido_hasta'] ?: null)
                : $reporte->valido_hasta,
            'estado_publicacion' => array_key_exists('estado_publicacion', $data)
                ? (string) $data['estado_publicacion']
                : $reporte->estado_publicacion,
        ]);
        if (isset($data['codigo'])) {
            $reporte->codigo = (int) $data['codigo'];
        }
        $reporte->save();

        return $reporte;
    }

    public function delete(int $id): void
    {
        ReporteContable::query()->whereKey($id)->delete();
    }

    public function copiar(int $id): ReporteContable
    {
        $origen = $this->findConEstructura($id);
        if (! $origen) {
            throw new \RuntimeException('Informe no encontrado');
        }

        return DB::transaction(function () use ($origen) {
            $nuevoCodigo = ((int) ReporteContable::query()->max('codigo')) + 1;
            $nuevo = ReporteContable::query()->create([
                'codigo' => $nuevoCodigo,
                'nombre' => $origen->nombre.' (copia)',
                'titulo1' => $origen->titulo1,
                'titulo2' => $origen->titulo2,
                'tipo' => $origen->tipo,
                'origen' => 'manual',
                'anita_informe' => null,
                'activo' => true,
                'observaciones' => $origen->observaciones,
            ]);

            /** @var array<int, int> */
            $map = [];
            foreach ($origen->rubros->sortBy(['orden', 'id']) as $rubro) {
                $nuevoRubro = ReporteContableRubro::query()->create([
                    'reporte_contable_id' => $nuevo->id,
                    'parent_id' => $rubro->parent_id ? ($map[(int) $rubro->parent_id] ?? null) : null,
                    'codigo_linea' => $rubro->codigo_linea,
                    'nombre' => $rubro->nombre,
                    'nivel' => $rubro->nivel,
                    'orden' => $rubro->orden,
                    'tipo' => $rubro->tipo,
                    'formula' => $rubro->formula,
                    'estilo_negrita' => $rubro->estilo_negrita,
                    'estilo_subrayado' => $rubro->estilo_subrayado,
                    'mostrar_total' => $rubro->mostrar_total,
                    'anita_rubro' => null,
                ]);
                $map[(int) $rubro->id] = (int) $nuevoRubro->id;

                foreach ($rubro->cuentas as $cta) {
                    $nuevaCta = ReporteContableCuenta::query()->create([
                        'reporte_contable_rubro_id' => $nuevoRubro->id,
                        'empresa_id' => $cta->empresa_id,
                        'cuentacontable_id' => $cta->cuentacontable_id,
                        'codigo_cuenta' => $cta->codigo_cuenta,
                        'origen' => $cta->origen,
                        'signo' => $cta->signo,
                        'carga_ccosto' => $cta->carga_ccosto,
                        'sucursal' => $cta->sucursal,
                        'orden' => $cta->orden,
                    ]);
                    foreach ($cta->ccostos as $cc) {
                        $nuevaCta->ccostos()->create([
                            'ccosto_desde' => $cc->ccosto_desde,
                            'ccosto_hasta' => $cc->ccosto_hasta,
                            'centrocosto_id' => $cc->centrocosto_id,
                        ]);
                    }
                }
            }

            app(ReporteDefinibleLayoutSupport::class)->copiarLayoutsInforme($origen, $nuevo);

            return $nuevo->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function crearRubro(int $reporteId, array $data): ReporteContableRubro
    {
        $parentId = isset($data['parent_id']) && (int) $data['parent_id'] > 0
            ? (int) $data['parent_id']
            : null;
        $nivel = 1;
        if ($parentId) {
            $parent = ReporteContableRubro::query()
                ->where('reporte_contable_id', $reporteId)
                ->whereKey($parentId)
                ->firstOrFail();
            $nivel = (int) $parent->nivel + 1;
        }

        $orden = (int) (ReporteContableRubro::query()
            ->where('reporte_contable_id', $reporteId)
            ->max('orden') ?? -1) + 1;

        $codigo = trim((string) ($data['codigo_linea'] ?? ''));
        if ($codigo === '') {
            $codigo = 'R'.str_pad((string) ($orden + 1), 3, '0', STR_PAD_LEFT);
        }

        return ReporteContableRubro::query()->create([
            'reporte_contable_id' => $reporteId,
            'parent_id' => $parentId,
            'codigo_linea' => $codigo,
            'nombre' => trim((string) $data['nombre']),
            'nivel' => $nivel,
            'orden' => $orden,
            'tipo' => $data['tipo'] ?? ReporteDefinibleSupport::RUBRO_CUENTAS,
            'formula' => trim((string) ($data['formula'] ?? '')) ?: null,
            'estilo_negrita' => (bool) ($data['estilo_negrita'] ?? ($nivel <= 1)),
            'estilo_subrayado' => (bool) ($data['estilo_subrayado'] ?? false),
            'mostrar_total' => (bool) ($data['mostrar_total'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizarRubro(int $rubroId, array $data): ReporteContableRubro
    {
        $rubro = ReporteContableRubro::query()->findOrFail($rubroId);
        $rubro->fill([
            'nombre' => trim((string) ($data['nombre'] ?? $rubro->nombre)),
            'codigo_linea' => array_key_exists('codigo_linea', $data)
                ? (trim((string) $data['codigo_linea']) ?: $rubro->codigo_linea)
                : $rubro->codigo_linea,
            'tipo' => $data['tipo'] ?? $rubro->tipo,
            'formula' => array_key_exists('formula', $data)
                ? (trim((string) $data['formula']) ?: null)
                : $rubro->formula,
            'estilo_negrita' => array_key_exists('estilo_negrita', $data)
                ? (bool) $data['estilo_negrita']
                : $rubro->estilo_negrita,
            'estilo_subrayado' => array_key_exists('estilo_subrayado', $data)
                ? (bool) $data['estilo_subrayado']
                : $rubro->estilo_subrayado,
            'mostrar_total' => array_key_exists('mostrar_total', $data)
                ? (bool) $data['mostrar_total']
                : $rubro->mostrar_total,
            'conjunto_id' => array_key_exists('conjunto_id', $data)
                ? (((int) $data['conjunto_id']) > 0 ? (int) $data['conjunto_id'] : null)
                : $rubro->conjunto_id,
            'lado_presentacion' => array_key_exists('lado_presentacion', $data)
                ? (in_array(strtoupper((string) ($data['lado_presentacion'] ?? '')), ['D', 'H'], true)
                    ? strtoupper((string) $data['lado_presentacion'])
                    : null)
                : $rubro->lado_presentacion,
            'ocultar_si_cero' => array_key_exists('ocultar_si_cero', $data)
                ? (bool) $data['ocultar_si_cero']
                : $rubro->ocultar_si_cero,
        ]);
        $rubro->save();

        return $rubro;
    }

    public function eliminarRubro(int $rubroId): void
    {
        $rubro = ReporteContableRubro::query()->findOrFail($rubroId);
        // Reparent hijos al padre del eliminado
        ReporteContableRubro::query()
            ->where('parent_id', $rubro->id)
            ->update([
                'parent_id' => $rubro->parent_id,
                'nivel' => max(1, (int) $rubro->nivel),
            ]);
        $rubro->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function agregarCuenta(int $rubroId, array $data): ReporteContableCuenta
    {
        $rubro = ReporteContableRubro::query()->findOrFail($rubroId);
        $orden = (int) (ReporteContableCuenta::query()
            ->where('reporte_contable_rubro_id', $rubroId)
            ->max('orden') ?? -1) + 1;

        return ReporteContableCuenta::query()->create([
            'reporte_contable_rubro_id' => $rubro->id,
            'empresa_id' => isset($data['empresa_id']) && (int) $data['empresa_id'] > 0
                ? (int) $data['empresa_id']
                : null,
            'cuentacontable_id' => isset($data['cuentacontable_id']) && (int) $data['cuentacontable_id'] > 0
                ? (int) $data['cuentacontable_id']
                : null,
            'codigo_cuenta' => (int) $data['codigo_cuenta'],
            'codigo_hasta' => isset($data['codigo_hasta']) && (int) $data['codigo_hasta'] > 0
                ? (int) $data['codigo_hasta']
                : null,
            'origen' => ReporteDefinibleSupport::normalizarOrigen((string) ($data['origen'] ?? 'R')),
            'signo' => ((int) ($data['signo'] ?? 1)) >= 0 ? 1 : -1,
            'carga_ccosto' => ReporteDefinibleSupport::normalizarCargaCcosto($data['carga_ccosto'] ?? 'S'),
            'sucursal' => isset($data['sucursal']) ? (int) $data['sucursal'] : null,
            'orden' => $orden,
        ]);
    }

    public function eliminarCuenta(int $cuentaId): void
    {
        ReporteContableCuenta::query()->whereKey($cuentaId)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function estructuraUi(int $reporteId): array
    {
        $reporte = $this->findConEstructura($reporteId);
        if (! $reporte) {
            return [];
        }

        return ReporteDefinibleJerarquiaSupport::aplanarParaUi($reporte->rubros);
    }
}
