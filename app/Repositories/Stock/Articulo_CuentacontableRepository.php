<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Cuentacontable;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Articulo_CuentacontableRepository implements Articulo_CuentacontableRepositoryInterface
{
    protected $model;

    public function __construct(Articulo_Cuentacontable $articulo_cuentacontable)
    {
        $this->model = $articulo_cuentacontable;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function leePorArticulo($articulo_id, $empresa_id = null)
    {
        if ($empresa_id) {
            return $this->model
                ->where('empresa_id', $empresa_id)
                ->where('articulo_id', $articulo_id)
                ->get();
        }

        return $this->model->where('articulo_id', $articulo_id)->get();
    }

    public function create(array $data, $id)
    {
        return $this->guardarArticulo_Cuentacontable($data, 'create', $id);
    }

    public function createUnique(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->guardarArticulo_Cuentacontable($data, 'update', $id);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function deletePorArticulo($articulo_id)
    {
        return $this->model->where('articulo_id', $articulo_id)->delete();
    }

    public function find($id)
    {
        if (null == $articulo_cuentacontable = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $articulo_cuentacontable;
    }

    public function findOrFail($id)
    {
        if (null == $articulo_cuentacontable = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $articulo_cuentacontable;
    }

    /**
     * Sincroniza cuentas del artículo.
     * Solo toca empresas asignadas al usuario; el resto (ej. Villafranca si el usuario
     * solo tiene Bierzo) se conserva aunque no se muestren ni envíen en el form.
     * Sin empresas en sesión = acceso total (todas).
     */
    private function guardarArticulo_Cuentacontable($data, $funcion, $id = null)
    {
        $empresaRepo = app(EmpresaRepository::class);
        $asignadas = array_values(array_filter(array_map(
            'intval',
            $empresaRepo->traeEmpresasAsignadas()
        )));
        $alcanceTotal = $asignadas === [];

        $filas = $this->normalizarFilasDesdeRequest($data, $asignadas, $alcanceTotal);

        if ($funcion === 'update') {
            $this->borrarAlcanceUsuario((int) $id, $asignadas, $alcanceTotal);
        }

        $ultima = null;
        foreach ($filas as $fila) {
            $ultima = $this->model->create([
                'articulo_id' => (int) $id,
                'empresa_id' => $fila['empresa_id'],
                'cuentacontable_id' => $fila['cuentacontable_id'],
                'tipoimputacion' => $fila['tipoimputacion'],
                'creousuario_id' => $fila['creousuario_id'],
            ]);
        }

        return $ultima;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $asignadas
     * @return list<array{empresa_id: int, cuentacontable_id: int, tipoimputacion: string, creousuario_id: int}>
     */
    private function normalizarFilasDesdeRequest(array $data, array $asignadas, bool $alcanceTotal): array
    {
        if (! isset($data['empresa_ids']) || ! is_array($data['empresa_ids'])) {
            return [];
        }

        $empresaIds = $data['empresa_ids'];
        $cuentaIds = $data['cuentacontable_ids'] ?? [];
        $tipos = $data['tipoimputaciones'] ?? [];
        $creadores = $data['creousuario_cuentacontable_ids'] ?? [];
        $usuarioId = (int) (auth()->id() ?? 0);
        $filas = [];
        $vistos = [];

        foreach ($empresaIds as $i => $empresaIdRaw) {
            $empresaId = (int) ($empresaIdRaw ?? 0);
            $cuentaId = (int) ($cuentaIds[$i] ?? 0);
            $tipo = trim((string) ($tipos[$i] ?? ''));
            if ($empresaId <= 0 || $cuentaId <= 0 || $tipo === '') {
                continue;
            }
            if (! $alcanceTotal && ! in_array($empresaId, $asignadas, true)) {
                continue;
            }
            $clave = $empresaId.'|'.$tipo;
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $creador = (int) ($creadores[$i] ?? 0);
            $filas[] = [
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'tipoimputacion' => $tipo,
                'creousuario_id' => $creador > 0 ? $creador : max(1, $usuarioId),
            ];
        }

        return $filas;
    }

    /**
     * @param  list<int>  $asignadas
     */
    private function borrarAlcanceUsuario(int $articuloId, array $asignadas, bool $alcanceTotal): void
    {
        $query = $this->model->newQuery()->where('articulo_id', $articuloId);
        if (! $alcanceTotal) {
            $query->whereIn('empresa_id', $asignadas);
        }

        EloquentAuditDeleteSupport::each($query);
    }
}
