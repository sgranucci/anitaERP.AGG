<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\ContaduriaInventarioRecuentoSupport;
use App\Support\Stock\ContaduriaInventarioRubroSupport;

final class ContaduriaInventarioReporteService
{
    public function __construct(
        private readonly ExistenciasDepositoReporteService $existencias,
    ) {}

    /**
     * Una lectura de existencias a la fecha de cierre, partida por depósito y rubro.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{
     *     error:?string,
     *     fecha:string,
     *     origen_etiqueta:string,
     *     depositos:list<array{id:int,codigo:string,nombre:string}>,
     *     subtotal:list<array{clave:string,titulo:string,saldos:array<int,float>,total:float}>,
     *     detalles:list<array{deposito_id:int,deposito_codigo:string,deposito_nombre:string,rubro:string,titulo_hoja:string,filas:list<array<string,mixed>>,total:float}>
     * }
     */
    public function armar(array $filtros): array
    {
        $fecha = (string) ($filtros['fecha_hasta'] ?? '');
        $origen = ($filtros['origen'] ?? 'recuento') === 'saldo' ? 'saldo' : 'recuento';
        $cargado = $origen === 'recuento'
            ? $this->filasDesdeUltimoRecuento($filtros, $fecha)
            : $this->filasDesdeSaldo($filtros, $fecha);

        if ($cargado['error'] !== null) {
            return [
                'error' => $cargado['error'],
                'fecha' => $fecha,
                'origen_etiqueta' => '',
                'depositos' => [],
                'subtotal' => [],
                'detalles' => [],
            ];
        }

        $depositos = $cargado['depositos'];
        $filas = $cargado['filas'];

        $acumulado = [];
        $detalles = [];
        foreach (ContaduriaInventarioRubroSupport::rubros() as $rubro) {
            $acumulado[$rubro['clave']] = [
                'clave' => $rubro['clave'],
                'titulo' => $rubro['titulo'],
                'saldos' => [],
                'valores' => [],
                'total' => 0.0,
                'total_valor' => 0.0,
            ];
            foreach ($depositos as $deposito) {
                $acumulado[$rubro['clave']]['saldos'][$deposito['id']] = 0.0;
                $acumulado[$rubro['clave']]['valores'][$deposito['id']] = 0.0;
                $detalles[$deposito['id'].'|'.$rubro['clave']] = [
                    'deposito_id' => $deposito['id'],
                    'deposito_codigo' => $deposito['codigo'],
                    'deposito_nombre' => $deposito['nombre'],
                    'rubro' => $rubro['clave'],
                    'titulo_hoja' => $this->tituloHoja($deposito['codigo'], $rubro['titulo']),
                    'filas' => [],
                    'total' => 0.0,
                    'total_valor' => 0.0,
                ];
            }
        }

        $precios = $this->preciosUltimaCompra($filas);

        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $articuloId = (int) ($fila['articulo_id'] ?? 0);
            $precio = round((float) ($precios[$articuloId] ?? 0), 4);
            $clave = ContaduriaInventarioRubroSupport::clave($fila);
            foreach ($depositos as $deposito) {
                $saldo = round((float) ($fila['saldos'][$deposito['id']] ?? 0), 4);
                if (abs($saldo) <= 0.0001) {
                    continue;
                }
                $valor = round($saldo * $precio, 2);
                $acumulado[$clave]['saldos'][$deposito['id']] = round(
                    $acumulado[$clave]['saldos'][$deposito['id']] + $saldo,
                    4,
                );
                $acumulado[$clave]['valores'][$deposito['id']] = round(
                    $acumulado[$clave]['valores'][$deposito['id']] + $valor,
                    2,
                );
                $acumulado[$clave]['total'] = round($acumulado[$clave]['total'] + $saldo, 4);
                $acumulado[$clave]['total_valor'] = round($acumulado[$clave]['total_valor'] + $valor, 2);
                $detalles[$deposito['id'].'|'.$clave]['filas'][] = [
                    'sku' => (string) ($fila['sku'] ?? ''),
                    'descripcion' => (string) ($fila['descripcion'] ?? ''),
                    'categoria' => (string) ($fila['categoria'] ?? ''),
                    'uso' => (string) ($fila['uso'] ?? ''),
                    'tipo' => (string) ($fila['tipo'] ?? ''),
                    'saldo' => $saldo,
                    'precio' => $precio,
                    'valor' => $valor,
                ];
                $detalles[$deposito['id'].'|'.$clave]['total'] = round(
                    $detalles[$deposito['id'].'|'.$clave]['total'] + $saldo,
                    4,
                );
                $detalles[$deposito['id'].'|'.$clave]['total_valor'] = round(
                    $detalles[$deposito['id'].'|'.$clave]['total_valor'] + $valor,
                    2,
                );
            }
        }

        $detallesConDatos = [];
        foreach ($depositos as $deposito) {
            foreach (ContaduriaInventarioRubroSupport::rubros() as $rubro) {
                $detalle = $detalles[$deposito['id'].'|'.$rubro['clave']];
                if ($detalle['filas'] === [] && $rubro['clave'] === ContaduriaInventarioRubroSupport::OTROS) {
                    continue;
                }
                if ($rubro['clave'] === ContaduriaInventarioRubroSupport::OTROS
                    && abs((float) $detalle['total']) <= 0.0001
                    && abs((float) $detalle['total_valor']) <= 0.0001) {
                    continue;
                }
                if ($detalle['filas'] === []) {
                    continue;
                }
                usort($detalle['filas'], static fn (array $a, array $b) => strcmp($a['sku'], $b['sku']));
                $detallesConDatos[] = $detalle;
            }
        }

        return [
            'error' => null,
            'fecha' => $fecha,
            'origen_etiqueta' => $cargado['origen_etiqueta'],
            'depositos' => $depositos,
            'subtotal' => array_values($acumulado),
            'detalles' => $detallesConDatos,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{error:?string,origen_etiqueta:string,depositos:list<array{id:int,codigo:string,nombre:string}>,filas:iterable<mixed>}
     */
    private function filasDesdeSaldo(array $filtros, string $fecha): array
    {
        $consulta = $this->existencias->consultar($filtros, false);
        $depositos = [];
        foreach ($consulta['depositos'] as $deposito) {
            $depositos[] = [
                'id' => (int) $deposito->id,
                'codigo' => trim((string) $deposito->codigo),
                'nombre' => trim((string) $deposito->nombre),
            ];
        }

        if ($depositos === []) {
            return [
                'error' => 'No hay depósitos con existencia para los códigos indicados en la empresa.',
                'origen_etiqueta' => '',
                'depositos' => [],
                'filas' => [],
            ];
        }

        $filas = $consulta['filas'];
        if ($filas instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $filas = $filas->getCollection();
        }

        $etiqueta = $fecha !== '' && $fecha < date('Y-m-d')
            ? 'Origen: saldo al cierre (suma de movimientos hasta la fecha)'
            : 'Origen: saldo vigente';

        return [
            'error' => null,
            'origen_etiqueta' => $etiqueta,
            'depositos' => $depositos,
            'filas' => $filas,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{error:?string,origen_etiqueta:string,depositos:list<array{id:int,codigo:string,nombre:string}>,filas:list<array<string,mixed>>}
     */
    private function filasDesdeUltimoRecuento(array $filtros, string $fecha): array
    {
        $modelos = $this->existencias->depositosAutorizadosParaFiltros($filtros);
        $depositos = [];
        foreach ($modelos as $deposito) {
            $depositos[] = [
                'id' => (int) $deposito->id,
                'codigo' => trim((string) $deposito->codigo),
                'nombre' => trim((string) $deposito->nombre),
            ];
        }

        if ($depositos === []) {
            return [
                'error' => 'No hay depósitos para los códigos indicados en la empresa.',
                'origen_etiqueta' => '',
                'depositos' => [],
                'filas' => [],
            ];
        }

        $resuelto = ContaduriaInventarioRecuentoSupport::resolver(
            array_column($depositos, 'id'),
            $fecha,
        );
        if ($resuelto['faltantes'] !== []) {
            $nombres = [];
            foreach ($depositos as $deposito) {
                if (in_array($deposito['id'], $resuelto['faltantes'], true)) {
                    $nombres[] = 'depósito '.$deposito['codigo'].' '.$deposito['nombre'];
                }
            }

            return [
                'error' => 'No hay recuento cerrado al '.($fecha !== '' ? date('d/m/Y', strtotime($fecha)) : 'cierre')
                    .' para '.implode(', ', $nombres).'.',
                'origen_etiqueta' => '',
                'depositos' => [],
                'filas' => [],
            ];
        }

        return [
            'error' => null,
            'origen_etiqueta' => ContaduriaInventarioRecuentoSupport::etiqueta($depositos, $resuelto['por_deposito']),
            'depositos' => $depositos,
            'filas' => $this->filasDeCantidades($depositos, $resuelto['por_deposito']),
        ];
    }

    /**
     * @param  list<array{id:int,codigo:string,nombre:string}>  $depositos
     * @param  array<int, array{cantidades:array<int,float>}>  $porDeposito
     * @return list<array<string, mixed>>
     */
    private function filasDeCantidades(array $depositos, array $porDeposito): array
    {
        $ids = [];
        foreach ($porDeposito as $info) {
            foreach (array_keys($info['cantidades'] ?? []) as $articuloId) {
                $articuloId = (int) $articuloId;
                if ($articuloId > 0) {
                    $ids[$articuloId] = $articuloId;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        $articulos = Articulo::query()
            ->select([
                'articulo.id',
                'articulo.sku',
                'articulo.descripcion',
                'articulo.categoria_id',
                'articulo.usoarticulo_id',
                'articulo.tipoarticulo_id',
            ])
            ->with([
                'categorias:id,nombre',
                'usoarticulos:id,nombre',
                'tipoarticulos:id,nombre',
            ])
            ->whereIn('articulo.id', array_values($ids))
            ->orderBy('articulo.sku')
            ->get();

        $filas = [];
        foreach ($articulos as $articulo) {
            $articuloId = (int) $articulo->id;
            $saldos = [];
            $tiene = false;
            foreach ($depositos as $deposito) {
                $cantidad = round((float) ($porDeposito[$deposito['id']]['cantidades'][$articuloId] ?? 0), 4);
                $saldos[$deposito['id']] = $cantidad;
                if (abs($cantidad) > 0.0001) {
                    $tiene = true;
                }
            }
            if (! $tiene) {
                continue;
            }
            $filas[] = [
                'articulo_id' => $articuloId,
                'sku' => (string) ($articulo->sku ?? ''),
                'descripcion' => (string) ($articulo->descripcion ?? ''),
                'categoria' => (string) ($articulo->categorias?->nombre ?? ''),
                'uso' => (string) ($articulo->usoarticulos?->nombre ?? ''),
                'tipo' => (string) ($articulo->tipoarticulos?->nombre ?? ''),
                'saldos' => $saldos,
            ];
        }

        return $filas;
    }

    /**
     * @param  iterable<mixed>  $filas
     * @return array<int, float>
     */
    private function preciosUltimaCompra(iterable $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $id = (int) ($fila['articulo_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        if ($ids === []) {
            return [];
        }

        $resueltos = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos(array_values($ids));
        $precios = [];
        foreach ($resueltos as $id => $dato) {
            $precio = $dato['precio'] ?? null;
            $precios[(int) $id] = $precio !== null ? (float) $precio : 0.0;
        }

        return $precios;
    }

    private function tituloHoja(string $codigoDeposito, string $rubro): string
    {
        $titulo = trim($codigoDeposito.' '.$rubro);
        $titulo = preg_replace('/[\\\\\\/*\\[\\]:?]/', ' ', $titulo) ?? 'Hoja';
        $titulo = trim($titulo);

        return mb_substr($titulo !== '' ? $titulo : 'Hoja', 0, 31);
    }
}
