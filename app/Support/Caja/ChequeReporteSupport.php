<?php

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Consulta del reporte de cheques emitidos o recibidos.
 *
 * Columnas alineadas al listado Anita («LISTADO A SERGIO») y pedidos de Adriana:
 * cliente, destino (proveedor), totales por día, orden cronológico, importe numérico en Excel.
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
            ->with([
                'empresas',
                'bancos',
                'clientes:id,codigo,nombre',
                'monedas',
                'cuentacajas',
                'proveedores:id,codigo,nombre',
                'cobranzas:id,numerotransaccion',
                'pagoproveedores:id,numerotransaccion',
            ]);

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
     * Suma de cheques por día (fecha del cheque), orden cronológico.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, object{fecha: ?string, cantidad: int, monto: float}>
     */
    public static function totalesPorDia(array $filtros, EmpresaRepositoryInterface $empresas): Collection
    {
        return self::consulta($filtros, $empresas)
            ->selectRaw('cheque.fechapago as fecha, COUNT(*) as cantidad, SUM(cheque.monto) as monto')
            ->groupBy('cheque.fechapago')
            ->orderBy('cheque.fechapago', 'asc')
            ->get()
            ->map(static function ($row) {
                $row->cantidad = (int) $row->cantidad;
                $row->monto = (float) $row->monto;

                return $row;
            });
    }

    /**
     * Filas para PDF/Excel: cheques + subtotal «Total dia» + total general.
     * Los subtotales diarios se insertan cuando el orden es por fecha de cheque (default Anita).
     *
     * @param  Collection<int, Cheque>  $datas
     * @return list<array{tipo: string, cheque?: Cheque, fecha?: ?string, cantidad?: int, monto?: float, etiqueta?: string}>
     */
    public static function filasConSubtotalesDiarios(Collection $datas, array $filtros): array
    {
        $orden = (string) ($filtros['orden'] ?? 'fechapago');
        $filas = [];
        $totalCantidad = 0;
        $totalMonto = 0.0;

        if ($orden !== 'fechapago' || $datas->isEmpty()) {
            foreach ($datas as $cheque) {
                $filas[] = ['tipo' => 'cheque', 'cheque' => $cheque];
                $totalCantidad++;
                $totalMonto += (float) $cheque->monto;
            }
            if ($totalCantidad > 0) {
                $filas[] = [
                    'tipo' => 'total_general',
                    'cantidad' => $totalCantidad,
                    'monto' => $totalMonto,
                    'etiqueta' => 'Total general',
                ];
            }

            return $filas;
        }

        $diaActual = null;
        $cantDia = 0;
        $montoDia = 0.0;

        $flushDia = static function () use (&$filas, &$diaActual, &$cantDia, &$montoDia): void {
            if ($diaActual === null || $cantDia === 0) {
                return;
            }
            $filas[] = [
                'tipo' => 'total_dia',
                'fecha' => $diaActual,
                'cantidad' => $cantDia,
                'monto' => $montoDia,
                'etiqueta' => 'Total dia '.ChequeDepositoComprobanteSupport::fechaDmy($diaActual),
            ];
            $cantDia = 0;
            $montoDia = 0.0;
        };

        foreach ($datas as $cheque) {
            $fecha = self::fechaYmd($cheque->fechapago);
            if ($diaActual !== null && $fecha !== $diaActual) {
                $flushDia();
            }
            $diaActual = $fecha;
            $filas[] = ['tipo' => 'cheque', 'cheque' => $cheque];
            $cantDia++;
            $montoDia += (float) $cheque->monto;
            $totalCantidad++;
            $totalMonto += (float) $cheque->monto;
        }
        $flushDia();

        if ($totalCantidad > 0) {
            $filas[] = [
                'tipo' => 'total_general',
                'cantidad' => $totalCantidad,
                'monto' => $totalMonto,
                'etiqueta' => 'Total general',
            ];
        }

        return $filas;
    }

    public static function codigoCliente(Cheque $cheque): string
    {
        $codigo = $cheque->clientes->codigo ?? null;
        if ($codigo === null || $codigo === '') {
            return '';
        }

        return (string) $codigo;
    }

    public static function nombreCliente(Cheque $cheque): string
    {
        return trim((string) ($cheque->clientes->nombre ?? ''));
    }

    /**
     * Destino del valor: proveedor endosado / pago, o a quién se entregó (anombrede).
     * No usa `entregado` en recibidos: en sync Anita suele caer el banco de emisión.
     */
    public static function destino(Cheque $cheque): string
    {
        $proveedor = $cheque->proveedores;
        if ($proveedor) {
            $codigo = trim((string) ($proveedor->codigo ?? ''));
            $nombre = trim((string) ($proveedor->nombre ?? ''));
            if ($codigo !== '' && $nombre !== '') {
                return $codigo.' — '.$nombre;
            }

            return $nombre !== '' ? $nombre : $codigo;
        }

        $aNombre = trim((string) ($cheque->anombrede ?? ''));
        if ($aNombre !== '') {
            return $aNombre;
        }

        if (($cheque->origen ?? '') === 'E') {
            return trim((string) ($cheque->entregado ?? ''));
        }

        return '';
    }

    public static function beneficiario(Cheque $cheque): string
    {
        return trim((string) ($cheque->anombrede ?? $cheque->entregado ?? ''));
    }

    public static function bancoOCuenta(Cheque $cheque): string
    {
        if (($cheque->origen ?? '') === 'E') {
            return trim((string) ($cheque->cuentacajas->nombre ?? ''));
        }

        return trim((string) ($cheque->bancos->nombre ?? ''));
    }

    /**
     * N.rec. (cobranza) o N.OP (pago proveedor).
     */
    public static function nroDocumentoOrigen(Cheque $cheque): string
    {
        $nroCob = $cheque->cobranzas->numerotransaccion ?? null;
        if ($nroCob !== null && $nroCob !== '' && (int) $nroCob !== 0) {
            return (string) $nroCob;
        }
        $nroOp = $cheque->pagoproveedores->numerotransaccion ?? null;
        if ($nroOp !== null && $nroOp !== '' && (int) $nroOp !== 0) {
            return (string) $nroOp;
        }

        return '';
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

    private static function fechaYmd(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s, $m)) {
            return substr($s, 0, 10);
        }

        return $s;
    }
}
