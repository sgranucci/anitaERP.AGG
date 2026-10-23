<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Models\Stock\Recuento;
use App\Support\Stock\ArticuloPrecioUltimaCompraSupport;
use App\Support\Stock\ContaduriaInventarioRecuentoSupport;
use App\Support\Stock\ContaduriaInventarioRubroSupport;
use Carbon\Carbon;

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
     *     depositos:list<array{id:int,codigo:string,nombre:string,fecha_origen:string,recuentos:list<array<string,mixed>>}>,
     *     subtotal:list<array{clave:string,titulo:string,saldos:array<int,float>,valores:array<int,float>,total:float,total_valor:float}>,
     *     detalles:list<array{deposito_id:int,deposito_codigo:string,deposito_nombre:string,rubro:string,titulo_hoja:string,filas:list<array<string,mixed>>,total:float,total_valor:float,encabezado_titulo:string,encabezado_datos:string,encabezado_pie:string}>
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
                    'titulo_hoja' => $this->tituloHoja($rubro['titulo']),
                    'filas' => [],
                    'total' => 0.0,
                    'total_valor' => 0.0,
                ];
            }
        }

        $precios = $this->preciosUltimaCompra($filas);
        $ums = $this->unidadesPorArticulo($filas);
        $depositoPorId = [];
        foreach ($depositos as $deposito) {
            $depositoPorId[$deposito['id']] = $deposito;
        }

        foreach ($filas as $fila) {
            $fila = (array) $fila;
            $articuloId = (int) ($fila['articulo_id'] ?? 0);
            $precio = round((float) ($precios[$articuloId] ?? 0), 4);
            foreach ($depositos as $deposito) {
                $saldo = round((float) ($fila['saldos'][$deposito['id']] ?? 0), 4);
                if (abs($saldo) <= 0.0001) {
                    continue;
                }
                $recuentoId = (int) ($fila['recuento_por_deposito'][$deposito['id']] ?? 0);
                $recuento = $deposito['recuentos_por_id'][$recuentoId] ?? null;
                $clave = ContaduriaInventarioRubroSupport::claveDesdeRecuento(
                    is_array($recuento) ? (string) ($recuento['comentario'] ?? '') : '',
                    $fila,
                );
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
                    'um' => (string) ($ums[$articuloId] ?? ''),
                    'tipo' => (string) ($fila['tipo'] ?? ''),
                    'saldo' => $saldo,
                    'precio' => $precio,
                    'valor' => $valor,
                    'recuento_id' => $recuentoId,
                    'recuento_codigo' => is_array($recuento) ? (string) ($recuento['codigo'] ?? '') : '',
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
                if ($detalle['filas'] === []) {
                    continue;
                }
                usort($detalle['filas'], static function (array $a, array $b): int {
                    $tipo = strcmp((string) ($a['tipo'] ?? ''), (string) ($b['tipo'] ?? ''));

                    return $tipo !== 0 ? $tipo : strcmp((string) ($a['sku'] ?? ''), (string) ($b['sku'] ?? ''));
                });
                $encabezado = $this->encabezadoHoja($depositoPorId[$deposito['id']], $detalle['filas'], $origen);
                $detalle['encabezado_titulo'] = $encabezado['titulo'];
                $detalle['encabezado_datos'] = $encabezado['datos'];
                $detalle['encabezado_pie'] = $encabezado['pie'];
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
                'fecha_origen' => $fecha,
                'recuentos' => [],
                'recuentos_por_id' => [],
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
        $omitirSinRecuento = ! empty($filtros['omitir_sin_recuento']);
        if ($resuelto['faltantes'] !== [] && ! $omitirSinRecuento) {
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

        if ($omitirSinRecuento && $resuelto['faltantes'] !== []) {
            $faltan = array_flip($resuelto['faltantes']);
            $depositos = array_values(array_filter(
                $depositos,
                static fn (array $deposito) => ! isset($faltan[$deposito['id']]),
            ));
        }

        if ($depositos === []) {
            return [
                'error' => 'No hay recuento cerrado al '.($fecha !== '' ? date('d/m/Y', strtotime($fecha)) : 'cierre').' para los depósitos de la empresa.',
                'origen_etiqueta' => '',
                'depositos' => [],
                'filas' => [],
            ];
        }

        foreach ($depositos as $indice => $deposito) {
            $info = $resuelto['por_deposito'][$deposito['id']] ?? null;
            $porId = [];
            foreach ($info['recuentos'] ?? [] as $recuento) {
                $porId[(int) $recuento['id']] = $recuento;
            }
            $depositos[$indice]['fecha_origen'] = (string) ($info['fecha'] ?? '');
            $depositos[$indice]['recuentos'] = array_values($porId);
            $depositos[$indice]['recuentos_por_id'] = $porId;
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
            $recuentoPorDeposito = [];
            $tiene = false;
            foreach ($depositos as $deposito) {
                $cantidad = round((float) ($porDeposito[$deposito['id']]['cantidades'][$articuloId] ?? 0), 4);
                $saldos[$deposito['id']] = $cantidad;
                $recuentoPorDeposito[$deposito['id']] = (int) ($porDeposito[$deposito['id']]['recuento_por_articulo'][$articuloId] ?? 0);
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
                'recuento_por_deposito' => $recuentoPorDeposito,
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

    /**
     * @param  iterable<mixed>  $filas
     * @return array<int, string>
     */
    private function unidadesPorArticulo(iterable $filas): array
    {
        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) (((array) $fila)['articulo_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $map = [];
        $articulos = Articulo::query()
            ->select(['id', 'unidadmedida_id'])
            ->with('unidadesdemedidas:id,abreviatura')
            ->whereIn('id', array_values($ids))
            ->get();
        foreach ($articulos as $articulo) {
            $map[(int) $articulo->id] = trim((string) ($articulo->unidadesdemedidas?->abreviatura ?? ''));
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $deposito
     * @param  list<array<string, mixed>>  $filas
     * @return array{titulo:string,datos:string,pie:string}
     */
    private function encabezadoHoja(array $deposito, array $filas, string $origen): array
    {
        $depositoTxt = Depmae::etiquetaDesdePartes(
            (string) ($deposito['codigo'] ?? ''),
            (string) ($deposito['nombre'] ?? ''),
        );
        $pie = 'Ordenado por tipo de artículo y SKU. Costo u/c: última compra (Anita → ERP COM → PPP artículo). Líneas: '.count($filas);
        $fecha = (string) ($deposito['fecha_origen'] ?? '');
        $fechaTxt = $fecha !== '' ? Carbon::parse($fecha)->format('d/m/Y') : '';

        $ids = [];
        foreach ($filas as $fila) {
            $id = (int) ($fila['recuento_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $recuentos = [];
        foreach ($ids as $id) {
            $meta = $deposito['recuentos_por_id'][$id] ?? null;
            if (is_array($meta)) {
                $recuentos[] = $meta;
            }
        }

        if ($origen !== 'recuento' || $recuentos === []) {
            return [
                'titulo' => 'Saldo al cierre',
                'datos' => 'Fecha: '.$fechaTxt.' | Depósito: '.$depositoTxt.' | Generado: '.date('d/m/Y H:i'),
                'pie' => $pie,
            ];
        }

        $codigos = [];
        $estados = [];
        $usuarios = [];
        $comentarios = [];
        foreach ($recuentos as $recuento) {
            $codigo = trim((string) ($recuento['codigo'] ?? ''));
            if ($codigo !== '') {
                $codigos[] = $codigo;
            }
            $estado = trim((string) ($recuento['estado'] ?? ''));
            if ($estado !== '') {
                $estados[$estado] = $estado;
            }
            $usuario = trim((string) ($recuento['usuario'] ?? ''));
            if ($usuario !== '') {
                $usuarios[$usuario] = $usuario;
            }
            $comentario = trim((string) ($recuento['comentario'] ?? ''));
            if ($comentario !== '') {
                $comentarios[$comentario] = $comentario;
            }
        }

        $titulo = count($codigos) === 1
            ? 'Recuento '.$codigos[0]
            : 'Recuentos '.ContaduriaInventarioRecuentoSupport::enumerarCodigos($codigos);
        $estadoTxt = count($estados) === 1
            ? Recuento::etiquetaEstado(array_values($estados)[0])
            : 'Cerrado parcial y total';
        $datos = 'Fecha: '.$fechaTxt.' | Estado: '.$estadoTxt.' | Depósito: '.$depositoTxt;
        if ($usuarios !== []) {
            $datos .= ' | Usuario: '.implode(', ', array_values($usuarios));
        }
        $datos .= ' | Generado: '.date('d/m/Y H:i');
        if ($comentarios !== []) {
            $pie = 'Comentario: '.implode(' | ', array_values($comentarios)).' | '.$pie;
        }

        return [
            'titulo' => $titulo,
            'datos' => $datos,
            'pie' => $pie,
        ];
    }

    private function tituloHoja(string $rubro): string
    {
        $titulo = preg_replace('/[\\\\\\/*\\[\\]:?]/', ' ', $rubro) ?? 'Hoja';
        $titulo = trim($titulo);

        return mb_substr($titulo !== '' ? $titulo : 'Hoja', 0, 31);
    }
}
