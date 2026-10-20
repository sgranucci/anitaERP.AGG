<?php

namespace App\Support\Stock;

use App\Models\Stock\Recuento;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Último recuento cerrado de cada depósito para el inventario de contaduría.
 * Si en la fecha más reciente hay varios recuentos cerrados, entran todos.
 */
final class ContaduriaInventarioRecuentoSupport
{
    /**
     * @param  list<int>  $depositoIds
     * @return array{
     *     faltantes:list<int>,
     *     por_deposito:array<int, array{
     *         fecha:string,
     *         recuentos:list<array{id:int,codigo:string,estado:string}>,
     *         cantidades:array<int,float>
     *     }>
     * }
     */
    public static function resolver(array $depositoIds, string $fechaHasta): array
    {
        $depositoIds = array_values(array_unique(array_filter(array_map('intval', $depositoIds))));
        if ($depositoIds === [] || $fechaHasta === '') {
            return ['faltantes' => $depositoIds, 'por_deposito' => []];
        }

        $recuentos = Recuento::query()
            ->whereIn('deposito_id', $depositoIds)
            ->where('fecha', '<=', $fechaHasta)
            ->whereIn('estado', [Recuento::ESTADO_CERRADO_PARCIAL, Recuento::ESTADO_CERRADO_TOTAL])
            ->orderBy('deposito_id')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'codigo', 'fecha', 'deposito_id', 'estado']);

        $fechaPorDeposito = [];
        $elegidos = [];
        foreach ($recuentos as $recuento) {
            $depositoId = (int) $recuento->deposito_id;
            $fecha = $recuento->fecha instanceof Carbon
                ? $recuento->fecha->format('Y-m-d')
                : substr((string) $recuento->fecha, 0, 10);
            if (! isset($fechaPorDeposito[$depositoId])) {
                $fechaPorDeposito[$depositoId] = $fecha;
            }
            if ($fecha !== $fechaPorDeposito[$depositoId]) {
                continue;
            }
            $elegidos[$depositoId][] = [
                'id' => (int) $recuento->id,
                'codigo' => trim((string) $recuento->codigo),
                'estado' => (string) $recuento->estado,
            ];
        }

        $faltantes = [];
        foreach ($depositoIds as $depositoId) {
            if (! isset($elegidos[$depositoId])) {
                $faltantes[] = $depositoId;
            }
        }

        $ids = [];
        foreach ($elegidos as $lista) {
            foreach ($lista as $recuento) {
                $ids[] = $recuento['id'];
            }
        }

        $cantidades = self::cantidadesPorDeposito($elegidos, $ids);

        $porDeposito = [];
        foreach ($elegidos as $depositoId => $lista) {
            usort($lista, static fn (array $a, array $b) => $a['id'] <=> $b['id']);
            $porDeposito[$depositoId] = [
                'fecha' => $fechaPorDeposito[$depositoId],
                'recuentos' => $lista,
                'cantidades' => $cantidades[$depositoId] ?? [],
            ];
        }

        return [
            'faltantes' => $faltantes,
            'por_deposito' => $porDeposito,
        ];
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string}>  $depositos
     * @param  array<int, array{fecha:string,recuentos:list<array{id:int,codigo:string,estado:string}>,cantidades:array<int,float>}>  $porDeposito
     */
    public static function etiqueta(array $depositos, array $porDeposito): string
    {
        $partes = [];
        foreach ($depositos as $deposito) {
            $info = $porDeposito[(int) $deposito['id']] ?? null;
            if ($info === null) {
                continue;
            }
            $codigos = array_map(static fn (array $recuento) => $recuento['codigo'], $info['recuentos']);
            $fecha = Carbon::parse($info['fecha'])->format('d/m/Y');
            $estados = array_values(array_unique(array_column($info['recuentos'], 'estado')));
            $estado = count($estados) === 1
                ? mb_strtolower(Recuento::etiquetaEstado($estados[0]))
                : 'cerrado parcial y total';
            $partes[] = 'Dep. '.$deposito['codigo'].' '.$deposito['nombre'].': '
                .self::unir($codigos).' del '.$fecha.' ('.$estado.')';
        }

        return 'Origen: último recuento de cada depósito · '.implode(' · ', $partes);
    }

    /**
     * @param  array<int, list<array{id:int,codigo:string,estado:string}>>  $elegidos
     * @param  list<int>  $recuentoIds
     * @return array<int, array<int, float>>
     */
    private static function cantidadesPorDeposito(array $elegidos, array $recuentoIds): array
    {
        if ($recuentoIds === []) {
            return [];
        }

        $depositoPorRecuento = [];
        foreach ($elegidos as $depositoId => $lista) {
            foreach ($lista as $recuento) {
                $depositoPorRecuento[$recuento['id']] = $depositoId;
            }
        }

        $sumas = [];
        $items = DB::table('recuento_item')
            ->whereIn('recuento_id', $recuentoIds)
            ->orderBy('recuento_id')
            ->get(['recuento_id', 'articulo_id', 'cantidad_contada']);

        foreach ($items as $item) {
            $recuentoId = (int) $item->recuento_id;
            $articuloId = (int) $item->articulo_id;
            if ($articuloId <= 0 || ! isset($depositoPorRecuento[$recuentoId])) {
                continue;
            }
            $sumas[$recuentoId][$articuloId] = ($sumas[$recuentoId][$articuloId] ?? 0) + (float) $item->cantidad_contada;
        }

        $cantidades = [];
        foreach ($elegidos as $depositoId => $lista) {
            $ordenados = $lista;
            usort($ordenados, static fn (array $a, array $b) => $a['id'] <=> $b['id']);
            foreach ($ordenados as $recuento) {
                foreach ($sumas[$recuento['id']] ?? [] as $articuloId => $cantidad) {
                    $cantidades[$depositoId][(int) $articuloId] = (float) $cantidad;
                }
            }
        }

        return $cantidades;
    }

    /**
     * @param  list<string>  $codigos
     */
    private static function unir(array $codigos): string
    {
        $codigos = array_values(array_filter($codigos, static fn (string $codigo) => $codigo !== ''));
        $cantidad = count($codigos);
        if ($cantidad <= 1) {
            return $codigos[0] ?? '';
        }
        if ($cantidad === 2) {
            return $codigos[0].' y '.$codigos[1];
        }

        $ultimo = array_pop($codigos);

        return implode(', ', $codigos).' y '.$ultimo;
    }
}
