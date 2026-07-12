<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo_ParteUnica;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

final class NpuBajaConsultaSupport
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
    ) {}

    /**
     * @return Builder<Articulo_ParteUnica>
     */
    public function queryActivos(string $consulta = '', int $empresaId = 0): Builder
    {
        $query = Articulo_ParteUnica::query()
            ->select('articulo_parte_unica.*')
            ->with(['articulos:id,sku,descripcion,numeroparte'])
            ->join('articulo as a', 'a.id', '=', 'articulo_parte_unica.articulo_id')
            ->where('articulo_parte_unica.estado', ArticuloParteUnicaEstados::ACTIVO)
            ->where('a.numeroparte', '1');

        if ($empresaId > 0) {
            $query->where(function (Builder $q) use ($empresaId) {
                $q->where('a.empresa_id', $empresaId)
                    ->orWhereNull('a.empresa_id');
            });
        } else {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'a.empresa_id', true);
        }

        $consulta = trim($consulta);
        if ($consulta !== '') {
            $like = '%'.addcslashes($consulta, '%_\\').'%';
            $npu = filter_var($consulta, FILTER_VALIDATE_INT);

            $query->where(function (Builder $q) use ($like, $npu, $consulta) {
                if ($npu !== false && (int) $npu > 0) {
                    $q->orWhere('articulo_parte_unica.numeroparte', (int) $npu);
                }
                $q->orWhere('a.sku', 'like', $like)
                    ->orWhere('a.descripcion', 'like', $like);

                if (ctype_digit($consulta)) {
                    $q->orWhere('articulo_parte_unica.numeroparte', 'like', $consulta.'%');
                }
            });
        }

        return $query->orderByDesc('articulo_parte_unica.numeroparte');
    }
}
