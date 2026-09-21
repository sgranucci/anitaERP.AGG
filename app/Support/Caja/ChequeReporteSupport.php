<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta del reporte de cheques emitidos o recibidos.
 */
class ChequeReporteSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, Cheque>|Collection<int, Cheque>
     */
    public static function listar(array $filtros, EmpresaRepositoryInterface $empresas, bool $paginar)
    {
        $query = self::consulta($filtros, $empresas)
            ->select('cheque.*')
            ->with(['empresas', 'bancos', 'clientes', 'monedas', 'cuentacajas', 'proveedores']);

        self::aplicarOrden($query, $filtros);

        if ($paginar) {
            return $query->paginate(25);
        }

        return $query->get();
    }

    /**
     * Totales del filtro completo, agrupados por moneda.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object>
     */
    public static function totales(array $filtros, EmpresaRepositoryInterface $empresas): Collection
    {
        return self::consulta($filtros, $empresas)
            ->leftJoin('moneda', 'moneda.id', '=', 'cheque.moneda_id')
            ->selectRaw('moneda.abreviatura as moneda, COUNT(*) as cantidad, SUM(cheque.monto) as monto')
            ->groupBy('moneda.abreviatura')
            ->orderBy('moneda.abreviatura')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Cheque>
     */
    public static function consulta(array $filtros, EmpresaRepositoryInterface $empresas): Builder
    {
        $tipo = ($filtros['tipo'] ?? 'E') === 'R' ? 'R' : 'E';
        $query = Cheque::query()->where('cheque.origen', $tipo);

        $empresas->aplicarFiltroEmpresasAsignadas($query, 'cheque.empresa_id');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0 && $empresas->empresaIdPermitida($empresaId)) {
            $query->where('cheque.empresa_id', $empresaId);
        }

        self::aplicarRango($query, 'cheque.fechaemision', (string) ($filtros['fecha_doc_desde'] ?? ''), (string) ($filtros['fecha_doc_hasta'] ?? ''));
        self::aplicarRango($query, 'cheque.fechapago', (string) ($filtros['fecha_cheque_desde'] ?? ''), (string) ($filtros['fecha_cheque_hasta'] ?? ''));

        if ($tipo === 'E') {
            $estado = (string) ($filtros['estado'] ?? '');
            if ($estado !== '' && array_key_exists($estado, ChequeReporteFiltros::ESTADOS_EMITIDO)) {
                $query->where('cheque.estado', $estado);
            }
        } else {
            self::aplicarSituacionRecibido($query, (string) ($filtros['situacion'] ?? ''));
        }

        return $query;
    }

    /**
     * @param  Builder<Cheque>  $query
     */
    private static function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        $columnas = [
            'fechapago' => 'cheque.fechapago',
            'fechaemision' => 'cheque.fechaemision',
            'numerocheque' => 'cheque.numerocheque',
            'monto' => 'cheque.monto',
        ];
        $columna = $columnas[$orden] ?? 'cheque.fechapago';
        $dir = ($filtros['orden_dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $query->orderBy($columna, $dir)->orderBy('cheque.id', 'asc');
    }

    /**
     * @param  Builder<Cheque>  $query
     */
    private static function aplicarRango(Builder $query, string $columna, string $desde, string $hasta): void
    {
        if ($desde !== '') {
            $query->where($columna, '>=', $desde);
        }
        if ($hasta !== '') {
            $query->where($columna, '<=', $hasta);
        }
    }

    /**
     * @param  Builder<Cheque>  $query
     */
    private static function aplicarSituacionRecibido(Builder $query, string $situacion): void
    {
        if ($situacion === 'cartera') {
            $query->whereNull('cheque.pagoproveedor_id')
                ->whereNull('cheque.fecha_deposito')
                ->where(function ($q) {
                    $q->whereIn('cheque.estado', [' ', 'N'])
                        ->orWhereNull('cheque.estado')
                        ->orWhere('cheque.estado', '');
                });

            return;
        }
        if ($situacion === 'depositado') {
            $query->whereNotNull('cheque.fecha_deposito')
                ->whereNull('cheque.fecha_acreditacion');

            return;
        }
        if ($situacion === 'acreditado') {
            $query->whereNotNull('cheque.fecha_acreditacion');

            return;
        }
        if ($situacion === 'rechazado') {
            $query->where('cheque.estado', 'R');

            return;
        }
        if ($situacion === 'caucionado') {
            $query->whereNotNull('cheque.nro_caucion')
                ->where('cheque.nro_caucion', '!=', '')
                ->where('cheque.nro_caucion', '!=', '0');
        }
    }
}
