<?php

namespace App\Repositories\Contable;

interface BienUsoRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Contable\BienUso>
     */
    public function leeBienUso($filtros, ?bool $flPaginando = null);
}
