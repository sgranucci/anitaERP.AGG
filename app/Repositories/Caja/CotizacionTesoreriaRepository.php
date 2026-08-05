<?php

namespace App\Repositories\Caja;

use App\Models\Caja\CotizacionTesoreria;
use App\Support\Caja\CotizacionTesoreriaListadoFiltros;
use App\Support\Caja\CotizacionTesoreriaMonedasSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CotizacionTesoreriaRepository implements CotizacionTesoreriaRepositoryInterface
{
    public function __construct(protected CotizacionTesoreria $model) {}

    public function create(array $data): CotizacionTesoreria
    {
        return $this->model->create($this->prepararDatos($data));
    }

    public function update(array $data, $id): bool
    {
        $row = $this->model->findOrFail($id);

        return $row->update($this->prepararDatos($data, false));
    }

    public function delete($id): bool
    {
        return (bool) $this->model->destroy($id);
    }

    public function find($id): ?CotizacionTesoreria
    {
        return $this->model->with(['usuarios', 'empresas'])->find($id);
    }

    public function findOrFail($id): CotizacionTesoreria
    {
        return $this->model->with(['usuarios', 'empresas'])->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator<CotizacionTesoreria>|Collection<int, CotizacionTesoreria>
     */
    public function leeCotizacionTesoreria($filtros = null, bool $flPaginando = true)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = CotizacionTesoreriaListadoFiltros::filtrosVacios();
            $filtros['valor'] = $texto;
            $filtros['busqueda'] = $texto;
            $filtros['empresa_scope'] = 'todas';
        } elseif (! is_array($filtros)) {
            $filtros = CotizacionTesoreriaListadoFiltros::filtrosVacios();
            $filtros['empresa_scope'] = 'todas';
        }

        $query = $this->model->newQuery()
            ->from('cotizacion_tesoreria')
            ->leftJoin('empresa', 'empresa.id', '=', 'cotizacion_tesoreria.empresa_id')
            ->select('cotizacion_tesoreria.*', 'empresa.nombre as nombreempresa')
            ->orderByDesc('cotizacion_tesoreria.fecha')
            ->orderBy('cotizacion_tesoreria.empresa_id')
            ->orderByDesc('cotizacion_tesoreria.id');

        // Siempre aplica (empresa externa + panel / búsqueda).
        CotizacionTesoreriaListadoFiltros::aplicar($query, $filtros);

        return $flPaginando
            ? $query->paginate(10)->appends(CotizacionTesoreriaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepararDatos(array $data, bool $esAlta = true): array
    {
        $fecha = Carbon::parse($data['fecha'])->startOfDay();
        $tasas = CotizacionTesoreriaMonedasSupport::tasasDesdeRequest($data);

        $out = array_merge($tasas, [
            'empresa_id' => (int) ($data['empresa_id'] ?? 1),
            'fecha' => $fecha->format('Y-m-d'),
            'fecha_anita' => (int) $fecha->format('Ymd'),
            'fecha_alfa' => $fecha->format('d/m/Y'),
        ]);

        if ($esAlta || ! array_key_exists('usuario_id', $data)) {
            $out['usuario_id'] = Auth::id();
        } elseif ($data['usuario_id'] !== null && $data['usuario_id'] !== '') {
            $out['usuario_id'] = (int) $data['usuario_id'];
        }

        return $out;
    }
}
