<?php

namespace App\Repositories\Seguridad;

use App\Models\Seguridad\IngresoProveedor;
use App\Models\Seguridad\IngresoProveedorArchivo;
use App\Models\Seguridad\IngresoProveedorPersona;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Seguridad\IngresoProveedorControlSupport;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorListadoFiltros;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Seguridad\IngresoProveedorVisibilidadSupport;
use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IngresoProveedorRepository implements IngresoProveedorRepositoryInterface
{
    public function __construct(protected IngresoProveedor $model)
    {
    }

    public function leeIngresoProveedor(array $filtros, bool $flPaginando = true): LengthAwarePaginator|Collection
    {
        $query = $this->model->newQuery()
            ->from('ingreso_proveedor')
            ->select('ingreso_proveedor.*')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ingreso_proveedor.proveedor_id')
            ->leftJoin('ingreso_proveedor_motivo', 'ingreso_proveedor_motivo.id', '=', 'ingreso_proveedor.motivo_id')
            ->leftJoin('ingreso_proveedor_punto', 'ingreso_proveedor_punto.id', '=', 'ingreso_proveedor.punto_id')
            ->leftJoin('ingreso_proveedor_sector', 'ingreso_proveedor_sector.id', '=', 'ingreso_proveedor.sector_id')
            ->leftJoin('ingreso_proveedor_area', 'ingreso_proveedor_area.id', '=', 'ingreso_proveedor.area_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ingreso_proveedor.usuario_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'ingreso_proveedor.empresa_id')
            ->with([
                'proveedores:id,codigo,nombre',
                'motivos:id,nombre',
                'puntos:id,nombre',
                'sectores:id,nombre',
                'areas:id,nombre',
                'usuarios:id,nombre',
                'empresas:id,nombre',
                'ordencompras:id,numeroordencompra,fecha,estadoordencompra,es_contrato,contrato_exige_ingresos',
            ]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ingreso_proveedor.empresa_id');
        IngresoProveedorListadoFiltros::aplicarEmpresa($query, $filtros);
        IngresoProveedorListadoFiltros::aplicarEstructurados($query, $filtros);
        if (empty($filtros['omitir_alcance'])) {
            IngresoProveedorVisibilidadSupport::aplicarFiltroAlcance($query);
        }

        if (IngresoProveedorListadoFiltros::tieneCriteriosTexto($filtros)) {
            IngresoProveedorListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('ingreso_proveedor.fecha')->orderByDesc('ingreso_proveedor.id');

        return $flPaginando ? $query->paginate(10) : $query->get();
    }

    public function create(array $data): IngresoProveedor
    {
        $ticket = DB::transaction(function () use ($data) {
            $personas = $this->extraerPersonas($data);
            $archivos = $data['nombrearchivos'] ?? [];
            unset($data['nombrearchivos'], $data['nombresanteriores'], $data['persona_nombres'], $data['persona_documentos']);

            $data = IngresoProveedorVisitanteSupport::normalizarAlGuardar($data);
            $data['estado'] = IngresoProveedorEstados::PENDIENTE;
            $data['usuario_id'] = Auth::id();
            $data['fecha'] = now()->toDateString();
            $data['hashvisualizar'] = $data['hashvisualizar'] ?? Str::lower(Str::random(48));
            $data['fecha_ingreso'] = null;
            $data['hora_ingreso'] = null;
            $data['fecha_egreso'] = null;
            $data['hora_egreso'] = null;
            $data['minutos_en_planta'] = null;

            $ticket = $this->model->create($this->soloFillable($data));
            $this->sincronizarPersonas($ticket, $personas);
            $this->guardarArchivosNuevos($ticket, $archivos);

            return $ticket->fresh(['personas', 'archivos', 'proveedores']);
        });

        app(ModuloAvisoService::class)->enviar(
            'seguridad',
            'ingreso_proveedor_creado',
            (int) $ticket->id
        );

        return $ticket;
    }

    public function update(array $data, int $id): IngresoProveedor
    {
        return DB::transaction(function () use ($data, $id) {
            $ticket = $this->findOrFail($id);
            $personas = $this->extraerPersonas($data);
            $archivosNuevos = $data['nombrearchivos'] ?? [];
            $conservar = $data['nombresanteriores'] ?? null;
            unset($data['nombrearchivos'], $data['nombresanteriores'], $data['persona_nombres'], $data['persona_documentos']);
            $data = IngresoProveedorVisitanteSupport::normalizarAlGuardar($data);

            unset($data['estado'], $data['usuario_id'], $data['fecha'], $data['fecha_ingreso'], $data['hora_ingreso'], $data['fecha_egreso'], $data['hora_egreso'], $data['minutos_en_planta']);

            $ticket->update($this->soloFillable($data));
            $this->sincronizarPersonas($ticket, $personas);
            if (is_array($conservar)) {
                $this->sincronizarArchivosConservados($ticket, $conservar);
            }
            $this->guardarArchivosNuevos($ticket, $archivosNuevos);

            return $ticket->fresh(['personas', 'archivos', 'proveedores']);
        });
    }

    public function delete(int $id): void
    {
        $ticket = $this->findOrFail($id);
        foreach ($ticket->archivos as $archivo) {
            Storage::disk(IngresoProveedorArchivo::DISCO)->delete($archivo->rutaRelativa());
            $archivo->delete();
        }
        EloquentAuditDeleteSupport::each(
            IngresoProveedorPersona::query()->where('ingreso_proveedor_id', $ticket->id)
        );
        $ticket->delete();
    }

    public function findOrFail(int $id): IngresoProveedor
    {
        return $this->model->with([
            'personas.usuarioIngreso:id,nombre',
            'personas.usuarioEgreso:id,nombre',
            'archivos',
            'proveedores' => static fn ($q) => $q->withTrashed(),
            'ordencompras:id,numeroordencompra,fecha,estadoordencompra,es_contrato,contrato_exige_ingresos',
            'empresas',
            'usuarioAutorizo:id,nombre',
        ])->findOrFail($id);
    }

    /**
     * @return list<array{nombre: string, documento: string}>
     */
    private function extraerPersonas(array &$data): array
    {
        $nombres = $data['persona_nombres'] ?? [];
        $documentos = $data['persona_documentos'] ?? [];
        $out = [];
        foreach ((array) $nombres as $i => $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre === '') {
                continue;
            }
            $out[] = [
                'nombre' => $nombre,
                'documento' => trim((string) ($documentos[$i] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{nombre: string, documento: string}>  $personas
     */
    private function sincronizarPersonas(IngresoProveedor $ticket, array $personas): void
    {
        EloquentAuditDeleteSupport::each(
            IngresoProveedorPersona::query()->where('ingreso_proveedor_id', $ticket->id)
        );
        $orden = 1;
        foreach ($personas as $persona) {
            IngresoProveedorPersona::create([
                'ingreso_proveedor_id' => $ticket->id,
                'orden' => $orden++,
                'nombre' => $persona['nombre'],
                'documento' => $persona['documento'] !== '' ? $persona['documento'] : null,
                'documento_norm' => IngresoProveedorControlSupport::normalizarDni($persona['documento']),
            ]);
        }
    }

    /**
     * @param  list<mixed>  $archivos
     */
    private function guardarArchivosNuevos(IngresoProveedor $ticket, array $archivos): void
    {
        foreach ($archivos as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $nombre = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
            $file->storeAs(IngresoProveedorArchivo::CARPETA, $nombre, IngresoProveedorArchivo::DISCO);
            IngresoProveedorArchivo::create([
                'ingreso_proveedor_id' => $ticket->id,
                'nombre_original' => $file->getClientOriginalName(),
                'nombre_archivo' => $nombre,
                'mime' => $file->getClientMimeType(),
                'tamanio' => $file->getSize(),
            ]);
        }
    }

    /**
     * @param  list<string|int>  $conservar
     */
    private function sincronizarArchivosConservados(IngresoProveedor $ticket, array $conservar): void
    {
        $ids = array_values(array_filter(array_map('intval', $conservar)));
        $query = IngresoProveedorArchivo::query()->where('ingreso_proveedor_id', $ticket->id);
        if ($ids !== []) {
            $query->whereNotIn('id', $ids);
        }
        foreach ($query->get() as $archivo) {
            Storage::disk(IngresoProveedorArchivo::DISCO)->delete($archivo->rutaRelativa());
            $archivo->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function soloFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->model->getFillable()));
    }
}
