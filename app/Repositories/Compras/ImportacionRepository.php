<?php

declare(strict_types=1);

namespace App\Repositories\Compras;

use App\Models\Compras\Importacion;
use App\Models\Compras\Importacion_Archivo;
use App\Models\Compras\Importacion_Embarque;
use App\Models\Compras\Importacion_Gasto;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Compras\ImportacionChecklistService;
use App\Services\Compras\ImportacionLandedCostService;
use App\Support\Compras\ImportacionEstados;
use App\Support\Compras\ImportacionListadoColumnas;
use App\Support\Compras\ImportacionListadoFiltros;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportacionRepository implements ImportacionRepositoryInterface
{
    public function __construct(
        private readonly Importacion $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly ImportacionLandedCostService $landedCostService,
        private readonly ImportacionChecklistService $checklistService,
    ) {}

    public function leeImportacion($filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $filtros = $this->normalizarFiltros($filtros);
        $query = $this->queryListado($filtros)
            ->select('importacion.*')
            ->addSelect([
                'empresa.nombre as nombreempresa',
                'proveedor.nombre as nombreproveedor',
                'moneda.abreviatura as abreviaturamoneda',
            ])
            ->with(['empresa', 'proveedor', 'moneda']);

        ImportacionListadoFiltros::aplicarOrden($query, $filtros);

        if (! $paginar) {
            return $query->get();
        }

        $perPage = max(1, min(50, (int) ($filtros['_per_page'] ?? 10)));

        return $query->paginate($perPage);
    }

    /**
     * Pack C: cortes por agrupación sobre el universo filtrado (conteo + suma de importes).
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return array<string, mixed>
     */
    public function cortesImportacion($filtros): array
    {
        $filtros = $this->normalizarFiltros($filtros);
        $campos = ImportacionListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            ImportacionListadoColumnas::RECURSO,
            ImportacionListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            $this->queryListado($filtros),
            $agrupar,
            $campos,
            'importacion.id',
            static fn (object $row, string $key): string => ImportacionListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => ImportacionListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            ImportacionListadoColumnas::medidasParaCortes($etiquetas)
        );
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltros($filtros): array
    {
        if (is_string($filtros)) {
            return array_merge(ImportacionListadoFiltros::filtrosVacios(), [
                'valor' => trim($filtros),
                'busqueda' => trim($filtros),
            ]);
        }

        return is_array($filtros) ? $filtros : ImportacionListadoFiltros::filtrosVacios();
    }

    /**
     * Base del listado: joins, alcance de empresa y filtros (chips + QBE). Sin ORDER ni SELECT.
     *
     * @param  array<string, mixed>  $filtros
     * @return \Illuminate\Database\Eloquent\Builder<Importacion>
     */
    private function queryListado(array $filtros)
    {
        $query = $this->model->newQuery()
            ->leftJoin('empresa', 'empresa.id', '=', 'importacion.empresa_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'importacion.proveedor_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'importacion.moneda_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'importacion.empresa_id');
        ImportacionListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    public function create(array $data): Importacion
    {
        return DB::transaction(function () use ($data) {
            $empresaId = (int) ($data['empresa_id'] ?? 0);
            $codigo = trim((string) ($data['codigo'] ?? ''));
            if ($codigo === '') {
                $codigo = $this->siguienteCodigo($empresaId);
            }

            $importacion = $this->model->create([
                'codigo' => $codigo,
                'empresa_id' => $empresaId,
                'proveedor_id' => $data['proveedor_id'] ?? null,
                'moneda_id' => $data['moneda_id'] ?? null,
                'cotizacion' => $data['cotizacion'] ?? null,
                'incoterm_id' => $data['incoterm_id'] ?? null,
                'estado' => $data['estado'] ?? ImportacionEstados::BORRADOR,
                'fecha_apertura' => $data['fecha_apertura'] ?? now()->toDateString(),
                'fecha_etd' => $data['fecha_etd'] ?? null,
                'fecha_eta' => $data['fecha_eta'] ?? null,
                'fob_estimado' => $data['fob_estimado'] ?? 0,
                'observacion' => $data['observacion'] ?? null,
                'exige_stock_para_cerrar' => (bool) ($data['exige_stock_para_cerrar'] ?? true),
                'usuario_id' => Auth::id(),
            ]);

            $this->sincronizarHijas($importacion, $data);
            $this->checklistService->asegurarDefaults($importacion);
            $this->landedCostService->refrescarTotalesCabecera($importacion->fresh());

            return $importacion->fresh();
        });
    }

    public function update(array $data, int $id): Importacion
    {
        return DB::transaction(function () use ($data, $id) {
            $importacion = $this->findOrFail($id);
            if (! ImportacionEstados::esEditable((string) $importacion->estado)) {
                throw new \RuntimeException('La carpeta no es editable en estado '.$importacion->estado.'.');
            }

            $nuevoEstado = (string) ($data['estado'] ?? $importacion->estado);
            if ($nuevoEstado !== (string) $importacion->estado
                && ! ImportacionEstados::puedeTransicionar((string) $importacion->estado, $nuevoEstado)) {
                throw new \RuntimeException('Transición de estado no permitida.');
            }

            $importacion->fill([
                'proveedor_id' => $data['proveedor_id'] ?? $importacion->proveedor_id,
                'moneda_id' => $data['moneda_id'] ?? $importacion->moneda_id,
                'cotizacion' => $data['cotizacion'] ?? $importacion->cotizacion,
                'incoterm_id' => $data['incoterm_id'] ?? $importacion->incoterm_id,
                'estado' => $nuevoEstado,
                'fecha_apertura' => $data['fecha_apertura'] ?? $importacion->fecha_apertura,
                'fecha_etd' => $data['fecha_etd'] ?? $importacion->fecha_etd,
                'fecha_eta' => $data['fecha_eta'] ?? $importacion->fecha_eta,
                'fob_estimado' => $data['fob_estimado'] ?? $importacion->fob_estimado,
                'observacion' => $data['observacion'] ?? $importacion->observacion,
                'exige_stock_para_cerrar' => array_key_exists('exige_stock_para_cerrar', $data)
                    ? (bool) $data['exige_stock_para_cerrar']
                    : $importacion->exige_stock_para_cerrar,
            ]);
            $importacion->save();

            $this->sincronizarHijas($importacion, $data);
            $this->landedCostService->refrescarTotalesCabecera($importacion->fresh());

            return $importacion->fresh();
        });
    }

    public function delete(int $id): bool
    {
        $importacion = $this->findOrFail($id);
        if (! in_array((string) $importacion->estado, [ImportacionEstados::BORRADOR, ImportacionEstados::ANULADA], true)) {
            throw new \RuntimeException('Solo se pueden eliminar carpetas en borrador o anuladas.');
        }

        return (bool) $importacion->delete();
    }

    public function findOrFail(int $id): Importacion
    {
        $imp = $this->model->findOrFail($id);
        if (! $this->empresaRepository->empresaIdPermitida((int) $imp->empresa_id)) {
            abort(403, 'No tiene acceso a la empresa de esta importación.');
        }

        return $imp;
    }

    public function findConRelaciones(int $id): Importacion
    {
        return $this->findOrFail($id)->load([
            'empresa',
            'proveedor',
            'moneda',
            'incoterm',
            'ordenescompra.proveedores:id,nombre',
            'embarques',
            'gastos.tipo',
            'gastos.proveedor',
            'gastos.comprobanteProveedor.tipotransaccion_compras:id,abreviatura',
            'gastos.comprobanteProveedor.proveedores:id,nombre',
            'lotes',
            'archivos',
            'checklist',
            'prorrateos.lineas',
            'recepciones',
        ]);
    }

    public function siguienteCodigo(int $empresaId): string
    {
        $anio = date('Y');
        $prefijo = 'IMP-'.$anio.'-';
        $ultimo = $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('codigo', 'like', $prefijo.'%')
            ->orderByDesc('id')
            ->value('codigo');

        $seq = 1;
        if (is_string($ultimo) && preg_match('/(\d+)$/', $ultimo, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefijo.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $data */
    private function sincronizarHijas(Importacion $importacion, array $data): void
    {
        if (array_key_exists('ordencompra_ids', $data)) {
            $ids = collect($data['ordencompra_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $importacion->ordenescompra()->sync($ids);
        }

        if (array_key_exists('embarques', $data) && is_array($data['embarques'])) {
            $conservar = [];
            foreach ($data['embarques'] as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $id = (int) ($fila['id'] ?? 0);
                $payload = [
                    'importacion_id' => $importacion->id,
                    'tipo_documento' => $fila['tipo_documento'] ?? 'BL',
                    'numero_documento' => $fila['numero_documento'] ?? null,
                    'contenedor' => $fila['contenedor'] ?? null,
                    'buque_vuelo' => $fila['buque_vuelo'] ?? null,
                    'puerto_origen' => $fila['puerto_origen'] ?? null,
                    'puerto_destino' => $fila['puerto_destino'] ?? null,
                    'fecha_embarque' => $fila['fecha_embarque'] ?: null,
                    'fecha_arribo' => $fila['fecha_arribo'] ?: null,
                    'observacion' => $fila['observacion'] ?? null,
                ];
                if ($id > 0) {
                    $emb = Importacion_Embarque::query()
                        ->where('importacion_id', $importacion->id)
                        ->whereKey($id)
                        ->first();
                    if ($emb) {
                        $emb->update($payload);
                        $conservar[] = $emb->id;
                        continue;
                    }
                }
                $nuevo = Importacion_Embarque::query()->create($payload);
                $conservar[] = $nuevo->id;
            }
            EloquentAuditDeleteSupport::each(
                Importacion_Embarque::query()
                    ->where('importacion_id', $importacion->id)
                    ->when($conservar !== [], fn ($q) => $q->whereNotIn('id', $conservar))
            );
        }

        if (array_key_exists('gastos', $data) && is_array($data['gastos'])) {
            $conservar = [];
            foreach ($data['gastos'] as $fila) {
                if (! is_array($fila) || empty($fila['importacion_gasto_tipo_id'])) {
                    continue;
                }
                $id = (int) ($fila['id'] ?? 0);
                $importe = (float) ($fila['importe'] ?? 0);
                $cot = (float) ($fila['cotizacion'] ?? 0);
                $monedaId = (int) ($fila['moneda_id'] ?? $importacion->moneda_id ?? 1);
                $importeLocal = (float) ($fila['importe_moneda_local'] ?? 0);
                if ($importeLocal <= 0) {
                    $importeLocal = $monedaId <= 1 ? $importe : ($cot > 0 ? $importe * $cot : $importe);
                }
                $cpId = isset($fila['comprobante_proveedor_id']) ? (int) $fila['comprobante_proveedor_id'] : 0;
                $naturaleza = (string) ($fila['naturaleza'] ?? 'estimado');
                if ($cpId > 0) {
                    // Factura asociada = gasto real (el operador puede haber dejado estimado por error).
                    $naturaleza = 'real';
                }
                $payload = [
                    'importacion_id' => $importacion->id,
                    'importacion_gasto_tipo_id' => (int) $fila['importacion_gasto_tipo_id'],
                    'naturaleza' => $naturaleza,
                    'proveedor_id' => $fila['proveedor_id'] ?? null,
                    'comprobante_proveedor_id' => $cpId > 0 ? $cpId : null,
                    'moneda_id' => $monedaId > 0 ? $monedaId : null,
                    'cotizacion' => $cot ?: null,
                    'importe' => $importe,
                    'importe_moneda_local' => round($importeLocal, 4),
                    'base_prorrateo' => $fila['base_prorrateo'] ?? null,
                    'fecha' => $fila['fecha'] ?: null,
                    'referencia' => $fila['referencia'] ?? null,
                    'observacion' => $fila['observacion'] ?? null,
                ];
                if ($id > 0) {
                    $gasto = Importacion_Gasto::query()
                        ->where('importacion_id', $importacion->id)
                        ->whereKey($id)
                        ->first();
                    if ($gasto) {
                        $gasto->update($payload);
                        $conservar[] = $gasto->id;
                        continue;
                    }
                }
                $nuevo = Importacion_Gasto::query()->create($payload);
                $conservar[] = $nuevo->id;
            }
            EloquentAuditDeleteSupport::each(
                Importacion_Gasto::query()
                    ->where('importacion_id', $importacion->id)
                    ->when($conservar !== [], fn ($q) => $q->whereNotIn('id', $conservar))
            );
        }

        if (array_key_exists('lote_ids', $data)) {
            $ids = collect($data['lote_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            $importacion->lotes()->sync($ids);
        }

        if (! empty($data['archivos_quitar']) && is_array($data['archivos_quitar'])) {
            $quitar = collect($data['archivos_quitar'])->map(fn ($id) => (int) $id)->filter()->all();
            $archivos = Importacion_Archivo::query()
                ->where('importacion_id', $importacion->id)
                ->whereIn('id', $quitar)
                ->get();
            foreach ($archivos as $archivo) {
                if ($archivo->ruta && Storage::disk('public')->exists($archivo->ruta)) {
                    Storage::disk('public')->delete($archivo->ruta);
                }
                $archivo->delete();
            }
        }
    }
}
