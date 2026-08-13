<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Servicio;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Proveedor_ServicioRepository implements Proveedor_ServicioRepositoryInterface
{
    protected $model;

    public function __construct(Proveedor_Servicio $proveedor_servicio)
    {
        $this->model = $proveedor_servicio;
    }

    public function create(array $data, $id)
    {
        return $this->guardarProveedorServicio($data, 'create', $id);
    }

    public function update(array $data, $id)
    {
        return $this->guardarProveedorServicio($data, 'update', $id);
    }

    public function delete($proveedor_id, $codigo)
    {
        return $this->model->where('proveedor_id', $proveedor_id)->delete();
    }

    public function find($id)
    {
        if (null == $proveedor_servicio = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_servicio;
    }

    public function leeProveedorServicio($proveedor_id)
    {
        return $this->model->where('proveedor_id', $proveedor_id)->orderBy('cliente')->get();
    }

    public function findOrFail($id)
    {
        if (null == $proveedor_servicio = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $proveedor_servicio;
    }

    private function guardarProveedorServicio($data, $funcion, $id = null)
    {
        if ($funcion === 'update' || ! isset($data['servicios_clientes'])) {
            $this->model->where('proveedor_id', $id)->delete();
        }

        if (! isset($data['servicios_clientes'])) {
            return;
        }

        $filas = $this->normalizarFilasServicio($data, (int) $id);

        foreach ($filas as $fila) {
            $this->model->create(array_merge($fila, ['proveedor_id' => $id]));
        }
    }

    /**
     * @return list<array{empresa_id: ?int, cliente: string, detalle: ?string}>
     */
    private function normalizarFilasServicio(array $data, int $proveedorId): array
    {
        $clientes = (array) ($data['servicios_clientes'] ?? []);
        $detalles = (array) ($data['servicios_detalles'] ?? []);
        $empresaIds = (array) ($data['servicios_empresa_ids'] ?? []);

        $empresaDefault = $this->empresaIdDefaultProveedor($proveedorId, $data);

        $max = max(count($clientes), count($detalles), count($empresaIds));
        $filas = [];
        $vistos = [];

        for ($i = 0; $i < $max; $i++) {
            $cliente = trim((string) ($clientes[$i] ?? ''));
            $detalle = trim((string) ($detalles[$i] ?? ''));
            $empresaId = $this->idEnteroONull($empresaIds[$i] ?? null) ?? $empresaDefault;

            if ($cliente === '' && $detalle === '') {
                continue;
            }

            if ($cliente === '') {
                continue;
            }

            $clave = ($empresaId ?? 0).'|'.mb_strtolower($cliente);
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;

            $filas[] = [
                'empresa_id' => $empresaId,
                'cliente' => mb_substr($cliente, 0, 255),
                'detalle' => $detalle === '' ? null : mb_substr($detalle, 0, 255),
            ];
        }

        return $filas;
    }

    private function empresaIdDefaultProveedor(int $proveedorId, array $data): ?int
    {
        $desdeRequest = $this->idEnteroONull($data['empresa_id'] ?? null);
        if ($desdeRequest !== null) {
            return $desdeRequest;
        }

        if ($proveedorId <= 0) {
            return null;
        }

        $empresaId = Proveedor::query()->whereKey($proveedorId)->value('empresa_id');

        return $empresaId !== null ? (int) $empresaId : null;
    }

    private function idEnteroONull($valor): ?int
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }
}
