<?php

namespace App\Repositories\Caja;

use App\Models\Caja\CotizacionTesoreria;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CotizacionTesoreriaRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return LengthAwarePaginator<CotizacionTesoreria>|Collection<int, CotizacionTesoreria>
     */
    public function leeCotizacionTesoreria($filtros = null, bool $flPaginando = true);
}
