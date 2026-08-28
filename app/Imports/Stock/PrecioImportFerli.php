<?php

namespace App\Imports\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Precio;
use App\Models\Stock\Talle;
use Auth;
use Carbon\Carbon;
use DB;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class PrecioImportFerli implements OnEachRow, WithHeadingRow
{
    private $fechavigencia = null;

    private $moneda_id = null;

    private $heading;

    /**
     * Combinaciones cargadas como excepción en esta importación.
     * Evita que una fila con combinación 0 pise un precio especial
     * que ya se importó (sin importar el orden de las filas).
     */
    private $excepcionesImportadas = [];

    public function __construct($fechavigencia, $moneda_id, $heading)
    {
        $this->fechavigencia = $fechavigencia;
        $this->moneda_id = $moneda_id;
        $this->heading = $heading;
    }

    public function onRow(Row $row)
    {
        $row = $row->toArray();

        if (isset($row['articulo']) || isset($row['sku'])) {
            if (isset($row['articulo'])) {
                $sku = $row['articulo'];
            } else {
                $sku = $row['sku'];
            }

            $articulo = Articulo::select('id')->where('sku', $sku)->first();

            $arrayPrecios = [];
            $fechavigencia = Carbon::createFromFormat('d-m-Y', $this->fechavigencia);

            if ($articulo) {
                foreach ($this->heading as $lineaEncabezado) {
                    foreach ($lineaEncabezado[0] as $nombreColumna) {
                        $nombreInicial = substr($nombreColumna, 0, 2);

                        if ($nombreInicial == 'L_' || $nombreInicial == 'l_') {
                            $codigoLista = str_replace($nombreInicial, '', $nombreColumna);

                            $listaprecio = Listaprecio::select('id')->where('codigo', $codigoLista)->first();

                            if ($listaprecio) {
                                if ($row[$nombreColumna] != 0) {
                                    foreach ($this->combinacionesAAplicar($row, $articulo->id, $listaprecio->id) as $destino) {
                                        $item = $this->armaItemPrecio(
                                            $articulo->id,
                                            $destino['combinacion_id'],
                                            $listaprecio->id,
                                            $fechavigencia,
                                            $row[$nombreColumna],
                                            $destino['aplica_pedidos']
                                        );
                                        if ($item) {
                                            $arrayPrecios[] = $item;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($arrayPrecios as $precio) {
                    try {
                        if ($precio['operacion'] == 'CREATE') {
                            Precio::create($precio);
                        } else {
                            Precio::findOrFail($precio['id'])->update($precio);
                        }

                        $listaprecio = Listaprecio::find($precio['listaprecio_id']);

                        $desdetalle = $hastatalle = null;
                        if ($listaprecio) {
                            $desdetalle = Talle::select('id')->where('nombre', $listaprecio->desdetalle)->first();
                            $hastatalle = Talle::select('id')->where('nombre', $listaprecio->hastatalle)->first();
                        }

                        if (! empty($precio['aplica_pedidos'])) {
                            $pedidos = DB::table('pedido_combinacion')->where('articulo_id', $precio['articulo_id']);
                            if ($precio['combinacion_id'] != null) {
                                $pedidos->where('combinacion_id', $precio['combinacion_id']);
                            }
                            $pedidos->update(['precio' => $precio['precio']]);

                            if ($desdetalle && $hastatalle) {
                                $tallesPedido = DB::table('pedido_combinacion_talle')
                                    ->join('pedido_combinacion', 'pedido_combinacion_talle.pedido_combinacion_id', 'pedido_combinacion.id')
                                    ->where('pedido_combinacion.articulo_id', $precio['articulo_id'])
                                    ->whereBetween('pedido_combinacion_talle.talle_id', [$desdetalle->id, $hastatalle->id]);
                                if ($precio['combinacion_id'] != null) {
                                    $tallesPedido->where('pedido_combinacion.combinacion_id', $precio['combinacion_id']);
                                }
                                $tallesPedido->update(['pedido_combinacion_talle.precio' => $precio['precio']]);
                            }
                        }

                        $movimientos = DB::table('articulo_movimiento')->where('articulo_id', $precio['articulo_id']);
                        if ($precio['combinacion_id'] != null) {
                            $movimientos->where('combinacion_id', $precio['combinacion_id']);
                        } elseif (empty($precio['aplica_pedidos'])) {
                            $movimientos = null;
                        }

                        if ($movimientos) {
                            $movimientos->update(['precio' => $precio['precio']]);
                        }

                        if ($movimientos && $desdetalle && $hastatalle) {
                            $tallesMov = DB::table('articulo_movimiento_talle')
                                ->join('articulo_movimiento', 'articulo_movimiento_talle.articulo_movimiento_id', 'articulo_movimiento.id')
                                ->where('articulo_movimiento.articulo_id', $precio['articulo_id'])
                                ->whereBetween('articulo_movimiento_talle.talle_id', [$desdetalle->id, $hastatalle->id]);
                            if ($precio['combinacion_id'] != null) {
                                $tallesMov->where('articulo_movimiento.combinacion_id', $precio['combinacion_id']);
                            }
                            $tallesMov->update(['articulo_movimiento_talle.precio' => $precio['precio']]);
                        }

                        DB::commit();
                    } catch (\Exception $e) {
                        DB::rollback();
                        throw $e;
                    }
                }
            }
        }
    }

    public function headingRow(): int
    {
        return 1;
    }

    /**
     * Combinación 0 / vacía = precio normal de todos los colores.
     * Un número de combinación = excepción (precio especial de ese color).
     */
    private function combinacionesAAplicar(array $row, $articulo_id, $listaprecio_id)
    {
        $codigo = $this->codigoCombinacionDeFila($row);

        if ($this->esPrecioGenerico($codigo)) {
            $destinos = [[
                'combinacion_id' => null,
                'aplica_pedidos' => false,
            ]];

            $combinaciones = Combinacion::select('id')
                ->where('articulo_id', $articulo_id)
                ->get();

            foreach ($combinaciones as $combinacion) {
                if (isset($this->excepcionesImportadas[$this->claveExcepcion($articulo_id, $combinacion->id, $listaprecio_id)])) {
                    continue;
                }

                $destinos[] = [
                    'combinacion_id' => $combinacion->id,
                    'aplica_pedidos' => true,
                ];
            }

            return $destinos;
        }

        $combinacion = Combinacion::where('articulo_id', $articulo_id)
            ->where('codigo', $codigo)
            ->first();

        if (! $combinacion) {
            return [];
        }

        $this->excepcionesImportadas[$this->claveExcepcion($articulo_id, $combinacion->id, $listaprecio_id)] = true;

        return [[
            'combinacion_id' => $combinacion->id,
            'aplica_pedidos' => true,
        ]];
    }

    private function armaItemPrecio($articulo_id, $combinacion_id, $listaprecio_id, $fechavigencia, $importe, $aplica_pedidos)
    {
        $query = Precio::where('articulo_id', $articulo_id)
            ->where('listaprecio_id', $listaprecio_id)
            ->whereDate('fechavigencia', $fechavigencia);

        if ($combinacion_id != null) {
            $query->where('combinacion_id', $combinacion_id);
        } else {
            $query->whereNull('combinacion_id');
        }

        $precio = $query->first();

        return [
            'articulo_id' => $articulo_id,
            'combinacion_id' => $combinacion_id,
            'listaprecio_id' => $listaprecio_id,
            'fechavigencia' => $fechavigencia,
            'moneda_id' => $this->moneda_id,
            'precio' => $importe,
            'precioanterior' => 0,
            'usuarioultcambio_id' => Auth::id(),
            'operacion' => $precio ? 'UPDATE' : 'CREATE',
            'id' => $precio ? $precio->id : 0,
            'aplica_pedidos' => $aplica_pedidos,
        ];
    }

    private function codigoCombinacionDeFila(array $row)
    {
        foreach (['combinacion', 'nro_combinacion', 'nrocombinacion'] as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }

        return null;
    }

    private function esPrecioGenerico($codigo)
    {
        if ($codigo === null || $codigo === '') {
            return true;
        }

        return (int) $codigo === 0;
    }

    private function claveExcepcion($articulo_id, $combinacion_id, $listaprecio_id)
    {
        return $articulo_id.'-'.$combinacion_id.'-'.$listaprecio_id;
    }
}
