<?php

declare(strict_types=1);

namespace App\Repositories\Ventas;

use App\Models\Ventas\Contrato_Venta;
use App\Models\Ventas\Contrato_Venta_Dato;
use App\Models\Ventas\Contrato_Venta_Periodo;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Ventas\ConceptoVentaPlantillaMotor;
use App\Support\Ventas\ContratoVentaListadoFiltros;
use App\Support\Ventas\ContratoVentaSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class Contrato_VentaRepository implements Contrato_VentaRepositoryInterface
{
    public function __construct(
        protected Contrato_Venta $model,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection<int, Contrato_Venta>
     */
    public function leeContratoVenta($filtros, bool $paginar = false)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ContratoVentaListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ContratoVentaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('contrato_venta.*')
            ->leftJoin('cliente', 'cliente.id', '=', 'contrato_venta.cliente_id')
            ->leftJoin('concepto_venta', 'concepto_venta.id', '=', 'contrato_venta.concepto_venta_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'contrato_venta.empresa_id')
            ->with([
                'cliente:id,nombre,codigo,numerodocumento',
                'conceptoVenta.tags',
                'conceptoVenta.impuesto:id,nombre,valor',
                'datos',
                'empresa:id,nombre,codigo',
                'periodos',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'contrato_venta.empresa_id');

        if (ContratoVentaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ContratoVentaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('contrato_venta.codigo');

        return $paginar
            ? $query->paginate(10)->appends(ContratoVentaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        $this->quitarCamposHijos($data);
        $data = $this->normalizarCabecera($data);
        if (! isset($data['creousuario_id']) || (int) $data['creousuario_id'] <= 0) {
            $data['creousuario_id'] = (int) (auth()->id() ?? 0) ?: null;
        }

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $contrato = $this->model->findOrFail($id);
        $this->quitarCamposHijos($data);
        $data = $this->normalizarCabecera($data, false);
        $contrato->update($data);

        return $contrato;
    }

    public function delete($id)
    {
        $contrato = $this->model->find($id);
        if ($contrato === null) {
            return false;
        }

        EloquentAuditDeleteSupport::each(
            Contrato_Venta_Dato::query()->where('contrato_venta_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            Contrato_Venta_Periodo::query()->where('contrato_venta_id', (int) $id)
        );
        EloquentAuditDeleteSupport::each(
            $this->model->newQuery()->where('id', (int) $id)
        );

        return true;
    }

    public function find($id)
    {
        $contrato = $this->model->newQuery()
            ->with([
                'cliente:id,nombre,codigo,numerodocumento',
                'conceptoVenta.tags',
                'conceptoVenta.impuesto:id,nombre,valor',
                'datos',
                'empresa:id,nombre,codigo',
                'periodos',
            ])
            ->find($id);

        if ($contrato === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        $this->assertEmpresaPermitida((int) $contrato->empresa_id);

        return $contrato;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    public function findPorCodigo(string $codigo, ?int $empresaId = null): ?Contrato_Venta
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return null;
        }

        $query = $this->model->newQuery()
            ->with([
                'cliente:id,nombre,codigo,numerodocumento',
                'conceptoVenta.tags',
                'conceptoVenta.impuesto:id,nombre,valor',
                'datos',
                'empresa:id,nombre,codigo',
                'periodos',
            ])
            ->where('codigo', $codigo);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empresa_id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->first();
    }

    /**
     * @param  list<array{clave?: string, valor?: string|null}>  $filas
     */
    public function sincronizarDatos(int $contratoId, array $filas): void
    {
        EloquentAuditDeleteSupport::each(
            Contrato_Venta_Dato::query()->where('contrato_venta_id', $contratoId)
        );

        $vistos = [];
        foreach ($filas as $fila) {
            $clave = ConceptoVentaPlantillaMotor::normalizarClave((string) ($fila['clave'] ?? ''));
            if ($clave === '' || ! ConceptoVentaPlantillaMotor::esClaveValida($clave) || isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $valor = trim((string) ($fila['valor'] ?? ''));
            Contrato_Venta_Dato::query()->create([
                'contrato_venta_id' => $contratoId,
                'clave' => $clave,
                'valor' => $valor !== '' ? mb_substr($valor, 0, 255) : null,
            ]);
        }
    }

    public function listadoActivosParaConsulta(string $texto, ?int $empresaId = null)
    {
        $query = $this->model->newQuery()
            ->select('contrato_venta.*')
            ->leftJoin('cliente', 'cliente.id', '=', 'contrato_venta.cliente_id')
            ->leftJoin('concepto_venta', 'concepto_venta.id', '=', 'contrato_venta.concepto_venta_id')
            ->with([
                'cliente:id,nombre,codigo,numerodocumento',
                'conceptoVenta:id,codigo,nombre,descripcion,impuesto_id',
                'empresa:id,nombre,codigo',
                'datos',
            ])
            ->where('contrato_venta.estado', ContratoVentaSupport::ESTADO_ACTIVO)
            ->orderBy('contrato_venta.codigo');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'contrato_venta.empresa_id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('contrato_venta.empresa_id', $empresaId);
        }

        $texto = trim($texto);
        if ($texto !== '') {
            $like = '%'.addcslashes($texto, '%_\\').'%';
            $query->where(function ($q) use ($texto, $like) {
                $id = filter_var($texto, FILTER_VALIDATE_INT);
                if ($id !== false) {
                    $q->orWhere('contrato_venta.id', (int) $id);
                }
                $q->orWhere('contrato_venta.codigo', 'like', $like)
                    ->orWhere('cliente.nombre', 'like', $like)
                    ->orWhere('cliente.codigo', 'like', $like)
                    ->orWhere('concepto_venta.codigo', 'like', $like)
                    ->orWhere('concepto_venta.nombre', 'like', $like);
            });
        }

        return $query->limit(80)->get();
    }

    public function listadoColaFacturacion(
        ?int $empresaId,
        ?int $clienteId,
        string $fechaYmd,
        bool $soloPendientes = true
    ) {
        $fecha = substr(trim($fechaYmd), 0, 10);
        if ($fecha === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            $fecha = date('Y-m-d');
        }

        $query = $this->model->newQuery()
            ->select('contrato_venta.*')
            ->with([
                'cliente:id,nombre,codigo,numerodocumento',
                'conceptoVenta.tags',
                'conceptoVenta.impuesto:id,nombre,valor',
                'datos',
                'empresa:id,nombre,codigo',
                'periodos',
            ])
            ->where('contrato_venta.estado', ContratoVentaSupport::ESTADO_ACTIVO)
            ->whereDate('contrato_venta.vigencia_desde', '<=', $fecha)
            ->where(function ($q) use ($fecha) {
                $q->whereNull('contrato_venta.vigencia_hasta')
                    ->orWhereDate('contrato_venta.vigencia_hasta', '>=', $fecha);
            })
            ->orderBy('contrato_venta.codigo');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'contrato_venta.empresa_id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('contrato_venta.empresa_id', $empresaId);
        }
        if ($clienteId !== null && $clienteId > 0) {
            $query->where('contrato_venta.cliente_id', $clienteId);
        }

        /** @var Collection<int, Contrato_Venta> $coleccion */
        $coleccion = $query->get();

        if (! $soloPendientes) {
            return $coleccion;
        }

        return $coleccion->filter(function (Contrato_Venta $contrato) use ($fecha) {
            $periodo = ContratoVentaSupport::periodoParaFecha(
                $fecha,
                (string) ($contrato->periodicidad ?? ContratoVentaSupport::PERIODICIDAD_MENSUAL)
            );

            return ! ContratoVentaSupport::periodoYaFacturado(
                (int) $contrato->id,
                $periodo['desde'],
                $periodo['hasta']
            );
        })->values();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarCabecera(array $data, bool $esAlta = true): array
    {
        if (isset($data['codigo']) || $esAlta) {
            $data['codigo'] = strtoupper(trim((string) ($data['codigo'] ?? '')));
        }
        if (isset($data['estado']) || $esAlta) {
            $data['estado'] = ContratoVentaSupport::normalizarEstado((string) ($data['estado'] ?? ContratoVentaSupport::ESTADO_ACTIVO));
        }
        if (isset($data['periodicidad']) || $esAlta) {
            $data['periodicidad'] = ContratoVentaSupport::normalizarPeriodicidad(
                (string) ($data['periodicidad'] ?? ContratoVentaSupport::PERIODICIDAD_MENSUAL)
            );
        }
        if (array_key_exists('dia_facturacion', $data) || $esAlta) {
            $dia = (int) ($data['dia_facturacion'] ?? 1);
            $data['dia_facturacion'] = max(1, min(28, $dia));
        }
        if (array_key_exists('precio', $data)) {
            $precioRaw = $data['precio'];
            if ($precioRaw === null || $precioRaw === '') {
                $data['precio'] = null;
            } else {
                $data['precio'] = (float) str_replace(',', '.', (string) $precioRaw);
            }
        }
        if (array_key_exists('observacion', $data)) {
            $obs = trim((string) ($data['observacion'] ?? ''));
            $data['observacion'] = $obs !== '' ? mb_substr($obs, 0, 255) : null;
        }
        foreach (['empresa_id', 'cliente_id', 'concepto_venta_id', 'moneda_id', 'condicionventa_id'] as $fk) {
            if (array_key_exists($fk, $data)) {
                $n = (int) ($data[$fk] ?? 0);
                $data[$fk] = $n > 0 ? $n : null;
            }
        }
        if (isset($data['empresa_id'])) {
            $this->assertEmpresaPermitida((int) $data['empresa_id']);
        }

        return $data;
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if ($empresaId > 0 && ! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            throw new ModelNotFoundException('Empresa no permitida para el usuario');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function quitarCamposHijos(array &$data): void
    {
        unset(
            $data['dato_claves'],
            $data['dato_valores'],
            $data['codigocliente'],
            $data['nombrecliente'],
            $data['concepto_codigo'],
            $data['concepto_descripcion'],
        );
    }
}
