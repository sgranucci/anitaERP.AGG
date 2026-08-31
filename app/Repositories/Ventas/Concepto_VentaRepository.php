<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Concepto_Venta_Cuentacontable;
use App\Models\Ventas\Concepto_Venta_Precio;
use App\Models\Ventas\Concepto_Venta_Tag;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\ConceptoVentaListadoFiltros;
use App\Support\Ventas\ConceptoVentaTagSupport;
use App\Support\Ventas\ConceptoVentaUsoSupport;
use App\Support\Ventas\GtinEan13Support;
use RuntimeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

class Concepto_VentaRepository implements Concepto_VentaRepositoryInterface
{
    public function __construct(
        protected Concepto_Venta $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, Concepto_Venta>
     */
    public function leeConceptoVenta($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ConceptoVentaListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ConceptoVentaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('concepto_venta.*')
            ->with([
                'impuesto:id,nombre,valor',
                'unidadmedida:id,nombre,abreviatura,codigo',
                'cuentas' => fn ($q) => $this->restringirCuentasEmpresasAsignadas($q),
                'cuentas.cuentacontables:id,codigo,nombre,empresa_id',
                'cuentas.empresas:id,nombre,codigo',
                'cuentas.tipotransaccion:id,abreviatura,nombre',
                'cuentas.centrocosto:id,codigo,nombre',
                'precios',
                'tags',
            ]);

        if (ConceptoVentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ConceptoVentaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('concepto_venta.codigo');

        return $paginar
            ? $query->paginate(10)->appends(ConceptoVentaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function all()
    {
        return $this->model->orderBy('codigo')->get();
    }

    public function create(array $data)
    {
        $this->quitarCamposHijos($data);
        $data['codigo'] = strtoupper(trim((string) ($data['codigo'] ?? '')));
        $data['activo'] = (bool) ($data['activo'] ?? true);
        $data['unidades_mtx'] = max(1, (int) ($data['unidades_mtx'] ?? 1));
        $data['codigo_gtin'] = $this->normalizarGtin($data['codigo_gtin'] ?? null);
        $data['codigo_anita'] = $this->normalizarCodigoAnita($data['codigo_anita'] ?? null);
        $data['descripcion'] = trim((string) ($data['descripcion'] ?? $data['nombre'] ?? ''));

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $concepto = $this->model->findOrFail($id);
        $this->quitarCamposHijos($data);
        if (isset($data['codigo'])) {
            $data['codigo'] = strtoupper(trim((string) $data['codigo']));
        }
        if (array_key_exists('activo', $data)) {
            $data['activo'] = (bool) $data['activo'];
            if ($concepto->activo && ! $data['activo']) {
                $bloqueo = ConceptoVentaUsoSupport::mensajeBloqueo((int) $concepto->id, 'inactivar');
                if ($bloqueo !== null) {
                    throw new RuntimeException($bloqueo);
                }
            }
        }
        if (isset($data['unidades_mtx'])) {
            $data['unidades_mtx'] = max(1, (int) $data['unidades_mtx']);
        }
        if (array_key_exists('codigo_gtin', $data)) {
            $data['codigo_gtin'] = $this->normalizarGtin($data['codigo_gtin']);
        }
        if (array_key_exists('codigo_anita', $data)) {
            $data['codigo_anita'] = $this->normalizarCodigoAnita($data['codigo_anita']);
        }
        $concepto->update($data);

        return $concepto;
    }

    public function delete($id)
    {
        $concepto = $this->model->find($id);
        if ($concepto === null) {
            return false;
        }

        $bloqueo = ConceptoVentaUsoSupport::mensajeBloqueo((int) $concepto->id, 'borrar');
        if ($bloqueo !== null) {
            throw new RuntimeException($bloqueo);
        }

        EloquentAuditDeleteSupport::each(
            Concepto_Venta_Tag::query()->where('concepto_venta_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            Concepto_Venta_Precio::query()->where('concepto_venta_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            Concepto_Venta_Cuentacontable::query()->where('concepto_venta_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('id', (int) $id)
        );

        return true;
    }

    public function find($id)
    {
        $concepto = $this->model->with([
            'cuentas' => fn ($q) => $this->restringirCuentasEmpresasAsignadas($q),
            'cuentas.cuentacontables',
            'cuentas.empresas',
            'cuentas.tipotransaccion',
            'cuentas.centrocosto',
            'precios',
            'tags',
            'impuesto',
            'unidadmedida',
        ])->find($id);
        if ($concepto === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $concepto;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function findPorCodigo(string $codigo): ?Concepto_Venta
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        return $this->model->newQuery()
            ->with(['cuentas', 'precios', 'tags', 'impuesto', 'unidadmedida'])
            ->where('codigo', $codigo)
            ->first();
    }

    public function findPorCodigoAnita(int $codigoAnita): ?Concepto_Venta
    {
        if ($codigoAnita <= 0) {
            return null;
        }

        return $this->model->newQuery()->where('codigo_anita', $codigoAnita)->first();
    }

    public function listadoActivosParaConsulta(string $texto)
    {
        $query = $this->model->newQuery()
            ->activos()
            ->with(['tags' => fn ($q) => $q->orderBy('orden')->orderBy('id')])
            ->orderBy('codigo');
        $texto = trim($texto);
        if ($texto !== '') {
            $like = '%'.addcslashes($texto, '%_\\').'%';
            $query->where(function ($q) use ($texto, $like) {
                $id = filter_var($texto, FILTER_VALIDATE_INT);
                if ($id !== false) {
                    $q->orWhere('id', (int) $id)->orWhere('codigo_anita', (int) $id);
                }
                $q->orWhere('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like)
                    ->orWhere('descripcion', 'like', $like)
                    ->orWhere('codigo_gtin', 'like', $like);
            });
        }

        return $query->limit(80)->get();
    }

    /**
     * Reemplaza solo las cuentas de empresas asignadas al usuario.
     * Las de otras empresas (p. ej. Villafranca) se conservan.
     *
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarCuentas(int $conceptoId, array $filas): void
    {
        $queryBorrar = Concepto_Venta_Cuentacontable::query()
            ->where('concepto_venta_id', $conceptoId);
        $this->restringirCuentasEmpresasAsignadas($queryBorrar);
        EloquentAuditDeleteSupport::each($queryBorrar);

        $vistos = [];
        foreach ($filas as $fila) {
            $empresaId = (int) ($fila['empresa_id'] ?? 0);
            $cuentaId = (int) ($fila['cuentacontable_id'] ?? 0);
            if ($empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }
            if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
                continue;
            }
            $tipoId = (int) ($fila['tipotransaccion_id'] ?? 0);
            $desde = $this->normalizarFecha($fila['vigencia_desde'] ?? null);
            $hasta = $this->normalizarFecha($fila['vigencia_hasta'] ?? null);
            $clave = $empresaId.'|'.$tipoId.'|'.($desde ?? '').'|'.($hasta ?? '').'|'.$cuentaId;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $usuarioId = (int) ($fila['creousuario_id'] ?? 0);
            if ($usuarioId <= 0) {
                $usuarioId = (int) (auth()->id() ?? 0);
            }
            if ($usuarioId <= 0) {
                continue;
            }
            $ccId = (int) ($fila['centrocosto_id'] ?? 0);
            Concepto_Venta_Cuentacontable::query()->create([
                'concepto_venta_id' => $conceptoId,
                'empresa_id' => $empresaId,
                'tipotransaccion_id' => $tipoId > 0 ? $tipoId : null,
                'cuentacontable_id' => $cuentaId,
                'vigencia_desde' => $desde,
                'vigencia_hasta' => $hasta,
                'centrocosto_id' => $ccId > 0 ? $ccId : null,
                'creousuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarPrecios(int $conceptoId, array $filas): void
    {
        EloquentAuditDeleteSupport::each(
            Concepto_Venta_Precio::query()->where('concepto_venta_id', $conceptoId)
        );

        foreach ($filas as $fila) {
            $precio = (float) str_replace(',', '.', (string) ($fila['precio'] ?? 0));
            if ($precio <= 0) {
                continue;
            }
            $usuarioId = (int) ($fila['creousuario_id'] ?? auth()->id() ?? 0);
            if ($usuarioId <= 0) {
                continue;
            }
            Concepto_Venta_Precio::query()->create([
                'concepto_venta_id' => $conceptoId,
                'precio' => $precio,
                'vigencia_desde' => $this->normalizarFecha($fila['vigencia_desde'] ?? null),
                'vigencia_hasta' => $this->normalizarFecha($fila['vigencia_hasta'] ?? null),
                'creousuario_id' => $usuarioId,
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarTags(int $conceptoId, array $filas): void
    {
        EloquentAuditDeleteSupport::each(
            Concepto_Venta_Tag::query()->where('concepto_venta_id', $conceptoId)
        );

        $normalizadas = ConceptoVentaTagSupport::normalizarFilasFormulario($filas);
        foreach ($normalizadas as $fila) {
            Concepto_Venta_Tag::query()->create([
                'concepto_venta_id' => $conceptoId,
                'clave' => $fila['clave'],
                'etiqueta' => $fila['etiqueta'],
                'tipo' => $fila['tipo'],
                'origen' => $fila['origen'] ?? 'pedible',
                'obligatorio' => $fila['obligatorio'],
                'orden' => $fila['orden'],
                'largo_max' => $fila['largo_max'],
                'opciones' => $fila['opciones'] ?? null,
            ]);
        }
    }

    /**
     * Sin empresas asignadas (acceso total) no filtra.
     *
     * @param  Builder<\App\Models\Ventas\Concepto_Venta_Cuentacontable>|Relation  $query
     */
    private function restringirCuentasEmpresasAsignadas(Builder|Relation $query): void
    {
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');
    }

    private function normalizarGtin(mixed $valor): ?string
    {
        return GtinEan13Support::normalizar($valor);
    }

    private function normalizarCodigoAnita(mixed $valor): ?int
    {
        $n = (int) $valor;

        return $n > 0 ? $n : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function quitarCamposHijos(array &$data): void
    {
        unset(
            $data['empresa_ids'],
            $data['cuentacontable_ids'],
            $data['creousuario_cuentacontable_ids'],
            $data['tipotransaccion_ids'],
            $data['vigencia_desde'],
            $data['vigencia_hasta'],
            $data['centrocosto_ids'],
            $data['precios'],
            $data['precio_vigencia_desde'],
            $data['precio_vigencia_hasta'],
            $data['creousuario_precio_ids'],
            $data['tag_claves'],
            $data['tag_etiquetas'],
            $data['tag_tipos'],
            $data['tag_origenes'],
            $data['tag_obligatorios'],
            $data['tag_ordenes'],
            $data['tag_largo_max'],
            $data['tag_opciones'],
        );
    }

    private function normalizarFecha(mixed $valor): ?string
    {
        $txt = substr(trim((string) $valor), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $txt) === 1 ? $txt : null;
    }
}
