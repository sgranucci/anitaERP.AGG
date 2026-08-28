<?php

namespace App\Services\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Linea;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Talle;
use DB;

class PrecioServiceFerli
{
    public function asignaListaPrecio($talle, $tiponumeracion_id)
    {
        $listaprecio = Listaprecio::all();

        $lista_id = 0;
        foreach ($listaprecio as $lista) {
            if ($talle >= $lista->desdetalle && $talle <= $lista->hastatalle &&
                $tiponumeracion_id == $lista->tiponumeracion_id) {
                $lista_id = $lista->id;
                break;
            }
        }

        return $lista_id;
    }

    public function asignaPrecio($articulo_id, $combinacion_id, $talle_id, $fechavigencia)
    {
        $talle_id = preg_replace('([^A-Za-z0-9,])', '', $talle_id);
        $array_talle = explode(',', $talle_id);
        $array_precio = [];
        if ($talle_id) {
            $talle = Talle::select('nombre', 'id')->whereIn('id', $array_talle)->get();

            $articulo = Articulo::select('linea_id')->where('id', $articulo_id)->first();

            $tiponumeracion_id = 0;
            if ($articulo) {
                $linea = Linea::find($articulo->linea_id);

                if ($linea) {
                    $tiponumeracion_id = $linea->tiponumeracion_id;
                }
            }
            $fecha = $this->normalizaFechaVigencia($fechavigencia);
            foreach ($talle as $value) {
                $lista = $this->asignaListaPrecio($value->nombre, $tiponumeracion_id);
                $precio = $this->resuelvePrecio($articulo_id, $lista, $combinacion_id, $fecha);
                $array_precio[] = $this->datosPrecio($precio);
            }
        }

        return $array_precio;
    }

    public function asignaPrecioPorTipoNumeracion($articulo_id, $tiponumeracion_id, $fechavigencia, $combinacion_id = null)
    {
        $listaprecio = Listaprecio::all();
        $fecha = $this->normalizaFechaVigencia($fechavigencia);

        $array_precio = [];
        foreach ($listaprecio as $lista) {
            if ($tiponumeracion_id == $lista->tiponumeracion_id) {
                $precio = $this->resuelvePrecio($articulo_id, $lista->id, $combinacion_id, $fecha);
                $array_precio[] = $this->datosPrecio($precio);
            }
        }

        return $array_precio;
    }

    private function normalizaFechaVigencia($fechavigencia)
    {
        if (gettype($fechavigencia) == 'string') {
            return $fechavigencia;
        }

        return date('Y-m-d', strtotime($fechavigencia));
    }

    /**
     * Resuelve precio por vigencia (lógica Ferli L8, 13/8/2026).
     * Preferencia: precio de la combinación; si no hay, el genérico (combinacion NULL).
     * Un genérico con vigencia estrictamente posterior al de la combinación sí pisa a todas.
     * Si genérico y combinación comparten la misma fecha, gana la combinación.
     */
    private function resuelvePrecio($articulo_id, $listaprecio_id, $combinacion_id, $fecha)
    {
        if (! $listaprecio_id) {
            return null;
        }

        if ($combinacion_id === null || $combinacion_id === '' || (int) $combinacion_id === 0) {
            $combinacion_id = null;
        }

        $precioGenerico = Precio::with('listaprecios')
            ->where('articulo_id', $articulo_id)
            ->where('listaprecio_id', $listaprecio_id)
            ->whereNull('combinacion_id')
            ->where('fechavigencia', '<=', $fecha)
            ->orderBy('fechavigencia', 'desc')
            ->first();

        if (! $combinacion_id) {
            return $precioGenerico;
        }

        $precioCombinacion = Precio::with('listaprecios')
            ->where('articulo_id', $articulo_id)
            ->where('listaprecio_id', $listaprecio_id)
            ->where('combinacion_id', $combinacion_id)
            ->where('fechavigencia', '<=', $fecha)
            ->orderBy('fechavigencia', 'desc')
            ->first();

        if ($precioGenerico && (! $precioCombinacion || $precioGenerico->fechavigencia > $precioCombinacion->fechavigencia)) {
            return $precioGenerico;
        }

        return $precioCombinacion ?: $precioGenerico;
    }

    private function datosPrecio($precio)
    {
        if ($precio) {
            return [
                'precio' => $precio->precio,
                'listaprecio_id' => $precio->listaprecio_id,
                'moneda_id' => $precio->moneda_id,
                'incluyeimpuesto' => $precio->listaprecios->incluyeimpuesto,
            ];
        }

        return [
            'precio' => 0,
            'listaprecio_id' => 0,
            'moneda_id' => 1,
            'incluyeimpuesto' => 1,
        ];
    }

    public static function asignaPrecioPorLista($articulo_id, $listaprecio_id, $fechavigencia)
    {
        $precios = Precio::select('articulo_id', 'listaprecio_id', 'fechavigencia', 'precio')
            ->where('articulo_id', '=', $articulo_id)
            ->where('listaprecio_id', '=', $listaprecio_id)
            ->orderBy('fechavigencia')
            ->get();

        $precioRet = 0;
        foreach ($precios as $precio) {
            if ($precio->fechavigencia <= $fechavigencia) {
                $precioRet = $precio->precio;
            }
        }

        return $precioRet;
    }

    public function generaDatosRepListaPrecio($estado, $mventa_id,
        $desdearticulo_id, $hastaarticulo_id,
        $desdecategoria_id, $hastacategoria_id, $listasprecio,
        $nofactura)
    {
        $listasPrecio_id = explode(',', $listasprecio);

        $precios = Precio::select('precio.articulo_id as articulo_id',
            'articulo.sku as sku',
            'articulo.descripcion as descripcion',
            'articulo.categoria_id as categoria_id',
            'articulo.mventa_id as mventa_id',
            'articulo.nofactura as nofactura',
            'mventa.nombre as marca',
            'categoria.nombre as categoria',
            'precio.listaprecio_id as listaprecio_id',
            'precio.fechavigencia as fechavigencia',
            'precio.precio')
            ->join('articulo', 'articulo.id', 'precio.articulo_id')
            ->join('mventa', 'mventa.id', 'articulo.mventa_id')
            ->join('categoria', 'categoria.id', 'articulo.categoria_id')
            ->whereBetween('articulo_id', [$desdearticulo_id, $hastaarticulo_id]);

        if ($nofactura != '2') {
            $precios = $precios->where('nofactura', $nofactura);
        }

        if ($mventa_id > 0) {
            $precios = $precios->where('articulo.mventa_id', $mventa_id);
        }

        $precios = $precios->whereBetween('articulo.categoria_id', [$desdecategoria_id, $hastacategoria_id])
            ->whereIn('precio.listaprecio_id', $listasPrecio_id)
            ->orderBy('articulo.descripcion')
            ->orderBy('articulo.sku')
            ->orderBy('listaprecio_id')
            ->orderBy('fechavigencia', 'desc');

        if ($estado != 'TODAS') {
            $query = $precios->whereExists(function ($query) use ($estado) {
                $query->select(DB::raw(1))
                    ->from('combinacion')
                    ->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='".substr($estado, 0, 1)."'");
            });

            $query = $query->get();
        } else {
            $query = $precios->get();
        }

        $data = [];
        $anterSku = '';
        foreach ($query as $articulo) {
            if ($articulo['sku'] != $anterSku) {
                if ($anterSku != '') {
                    $data[] = [
                        'articulo_id' => $articulo_id,
                        'sku' => $sku,
                        'descripcion' => $descripcion,
                        'marca' => $marca,
                        'categoria' => $categoria,
                        'precios' => $precios,
                    ];
                }
                $anterSku = $articulo['sku'];
                $precios = [];
            }

            $articulo_id = $articulo['articulo_id'];
            $sku = $articulo['sku'];
            $descripcion = $articulo['descripcion'];
            $marca = $articulo['marca'];
            $categoria = $articulo['categoria'];

            for ($i = 0, $flEncontro = false; $i < count($precios); $i++) {
                if ($articulo['listaprecio_id'] == $precios[$i]['listaprecio_id']) {
                    $flEncontro = true;
                }
            }
            if (! $flEncontro) {
                $precios[] = ['listaprecio_id' => $articulo['listaprecio_id'],
                    'precio' => $articulo['precio'],
                    'fechavigencia' => $articulo['fechavigencia'],
                ];
            }
        }
        if ($anterSku != '') {
            $data[] = [
                'articulo_id' => $articulo_id,
                'sku' => $sku,
                'descripcion' => $descripcion,
                'marca' => $marca,
                'categoria' => $categoria,
                'precios' => $precios,
            ];
        }

        return $data;
    }
}
