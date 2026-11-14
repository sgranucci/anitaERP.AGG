<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaSubtipo;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownValor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Kardex unificado de valores (relevamiento JL / l-efeboveda):
 * movimientos confirmados entre bóveda, pre-depósito y caja chica.
 */
final class TesoreriaCrownKardexValoresSupport
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function consulta(int $salaId, array $filtros): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('tesoreria_crown_boveda_linea as l')
            ->join('tesoreria_crown_boveda as b', 'b.id', '=', 'l.boveda_id')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_jornada as j', 'j.id', '=', 'b.jornada_id')
            ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'b.deposito_id')
            ->join('tesoreria_crown_tipo_deposito as td', 'td.id', '=', 'd.tipo_deposito_id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->join('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->leftJoin('tesoreria_crown_boveda_subtipo as st', 'st.id', '=', 'b.subtipo_id')
            ->where('b.sala_id', $salaId)
            ->where('b.estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereIn('td.codigo', TesoreriaCrownBovedaSupport::LUGARES);

        TesoreriaCrownKardexValoresListadoFiltros::aplicarExternos($q, $filtros);
        TesoreriaCrownKardexValoresListadoFiltros::aplicarQbe($q, (array) ($filtros['qbe'] ?? []));

        $orden = (array) ($filtros['sort'] ?? []);
        if ($orden !== []) {
            foreach ($orden as $criterio) {
                $campo = (string) ($criterio['campo'] ?? '');
                $dir = strtolower((string) ($criterio['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
                $col = TesoreriaCrownKardexValoresListadoFiltros::camposOrdenables()[$campo]['column'] ?? null;
                if ($col) {
                    $q->orderBy($col, $dir);
                }
            }
        } else {
            $q->orderBy('j.fecha')->orderBy('b.numero')->orderBy('l.id');
        }

        return $q->select([
            'b.id as boveda_id',
            'b.numero',
            'b.hora',
            'b.comentario',
            'b.subtipo_texto',
            'b.confirmado_at',
            'j.fecha',
            't.codigo as tipo_codigo',
            't.abreviatura',
            't.nombre as tipo_nombre',
            't.signo',
            't.operacion',
            'st.codigo as subtipo_codigo',
            'st.nombre as subtipo_nombre',
            'st.mueve_billetaje',
            'st.signo as subtipo_signo',
            'd.id as deposito_id',
            'd.codigo as deposito_codigo',
            'd.nombre as deposito_nombre',
            'td.codigo as deposito_tipo',
            'l.valor_id',
            'l.cantidad',
            'v.denominacion',
            'tv.codigo as moneda',
            'tv.nombre as moneda_nombre',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator|Collection
     */
    public static function listar(int $salaId, array $filtros, bool $paginar = true)
    {
        $q = self::consulta($salaId, $filtros);

        if ($paginar) {
            return $q->paginate(25);
        }

        return $q->get();
    }

    /**
     * @param  Collection<int, object>|LengthAwarePaginator  $filas
     * @return list<object>
     */
    public static function enriquecer($filas): array
    {
        $out = [];
        $saldos = [];
        foreach ($filas as $fila) {
            $efecto = TesoreriaCrownBovedaSupport::efectoConSubtipo(
                (string) $fila->signo,
                $fila->mueve_billetaje ?? null,
                $fila->subtipo_signo ?? null
            );
            $cantidad = (float) $fila->cantidad;
            $entrada = $efecto > 0 ? $cantidad : ($efecto === 0 ? null : null);
            $salida = $efecto < 0 ? $cantidad : null;
            if ($efecto === 0) {
                // Asentado sin mover: se muestra en ingreso informativo
                $entrada = $cantidad;
            }
            $monto = round($cantidad * (float) $fila->denominacion, 2);
            $fila->entrada = $entrada;
            $fila->salida = $salida;
            $fila->monto = $efecto === 0 ? $monto : ($efecto * $monto);
            $fila->efecto_texto = TesoreriaCrownBovedaSupport::efectoTextoConSubtipo(
                (string) $fila->signo,
                $fila->mueve_billetaje ?? null,
                $fila->subtipo_signo ?? null
            );
            $fila->numero_visible = TesoreriaCrownNumeradorSupport::visible((int) $fila->numero);
            $fila->subtipo_etiqueta = trim(
                (($fila->subtipo_codigo ?? '') !== '' ? $fila->subtipo_codigo.' — ' : '')
                .($fila->subtipo_nombre ?? '')
                .(($fila->subtipo_texto ?? '') !== '' ? ' ('.$fila->subtipo_texto.')' : '')
            );
            // Saldo acumulado por valor×depósito (patrón kardex artículo). En página
            // paginada corre sobre el lote visible; el export completo es el saldo del filtro.
            $clave = ((int) $fila->valor_id).'|'.((int) $fila->deposito_id);
            $delta = $efecto === 0 ? 0.0 : ($efecto * $cantidad);
            $saldos[$clave] = round(($saldos[$clave] ?? 0.0) + $delta, 4);
            $fila->saldo_acum = $saldos[$clave];
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * @return list<array{codigo: string, nombre: string}>
     */
    public static function operaciones(): array
    {
        return [
            ['codigo' => '0', 'nombre' => 'Ingreso / egreso'],
            ['codigo' => '1', 'nombre' => 'Propina'],
            ['codigo' => '2', 'nombre' => 'Moneda extranjera'],
            ['codigo' => '3', 'nombre' => 'Fallo de bóveda'],
            ['codigo' => '4', 'nombre' => 'A cta. terceros'],
            ['codigo' => '5', 'nombre' => 'Pago fondo fijo'],
            ['codigo' => '6', 'nombre' => 'Depósito cliente'],
            ['codigo' => '7', 'nombre' => 'Crédito caja'],
        ];
    }

    public static function tipos(int $salaId, null|string|\Carbon\CarbonInterface $fecha = null): Collection
    {
        return TesoreriaCrownBovedaSupport::tiposActivos($salaId, $fecha);
    }

    public static function subtiposDeTipo(
        int $tipoId,
        null|string|\Carbon\CarbonInterface $fecha = null
    ): Collection {
        $fechaYmd = TesoreriaCrownBovedaVigenciaSupport::normalizarFecha($fecha);
        $q = TesoreriaCrownBovedaSubtipo::query()
            ->with('tipo')
            ->where('tipo_id', $tipoId)
            ->where('activo', true);
        TesoreriaCrownBovedaVigenciaSupport::aplicarVigenteEn($q, $fechaYmd);

        return $q->orderBy('codigo')->get();
    }

    public static function depositos(int $salaId): Collection
    {
        return TesoreriaCrownBovedaSupport::lugares($salaId);
    }

    public static function valores(int $salaId): Collection
    {
        return TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('tipo_valor_id')
            ->orderByDesc('denominacion')
            ->get();
    }
}
