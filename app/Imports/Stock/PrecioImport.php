<?php

namespace App\Imports\Stock;

use App\Models\Stock\Precio;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Articulo;
use App\Models\Stock\Talle;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;
use Auth;
use Carbon\Carbon;
use DB;

class PrecioImport implements OnEachRow, WithHeadingRow
{
    private $fechavigencia = null, $moneda_id = null, $heading;

    public function  __construct($fechavigencia, $moneda_id, $heading)
    {
        $this->fechavigencia = $fechavigencia;
        $this->moneda_id = $moneda_id;
        $this->heading = $heading;
    }

    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function onRow(Row $row)
    {
        $row = $row->toArray();

        // Lee el articulo
        $articulo = Articulo::select('id')->where('sku', $row['articulo'])->first();

        $arrayPrecios = [];
        $fechavigencia = Carbon::createFromFormat('d-m-Y', $this->fechavigencia);

        if ($articulo)
        {
            // Verifica que lista de precio tiene que cambiar 
            foreach ($this->heading as $lineaEncabezado)
            {
                foreach ($lineaEncabezado[0] as $nombreColumna)
                {
                    $nombreInicial = substr($nombreColumna, 0, 2);

                    if ($nombreInicial == 'L_' || $nombreInicial == 'l_')
                    {
                        $codigoLista = str_replace($nombreInicial, '', $nombreColumna);

                        // Busca la lista de precios
                        $listaprecio = Listaprecio::select('id')->where('codigo',$codigoLista)->first();

                        if ($listaprecio)
                        {
                            // Lee la lista de precios
                            $precio = Precio::where('articulo_id', $articulo->id)
                                            ->where('listaprecio_id', $listaprecio->id)
                                            ->whereDate('fechavigencia', $fechavigencia)->first();

                            if ($row[$nombreColumna] != 0)
                            {
                                // Si el precio ya existe lo borra primero
                                if ($precio)
                                {
                                    $operacion = 'UPDATE';
                                    $id = $precio->id;
                                }
                                else    
                                {
                                    $operacion = 'CREATE';
                                    $id = 0;
                                }

                                $arrayPrecios[] = [
                                    'articulo_id' => $articulo->id,
                                    'listaprecio_id' => $listaprecio->id,
                                    'fechavigencia' => $fechavigencia,
                                    'moneda_id' => $this->moneda_id,
                                    'precio' => $row[$nombreColumna],
                                    'precioanterior' => 0,
                                    'usuarioultcambio_id' => Auth::id(),
                                    'operacion' => $operacion,
                                    'id' => $id
                                ];
                            }
                        }
                    }
                }
            }
            // Graba los precios de la fila del excel
            foreach ($arrayPrecios as $precio)
            {
                try 
                {
                    if ($precio['operacion'] == 'CREATE')
                        Precio::create($precio);
                    else
                        Precio::findOrFail($precio['id'])->update($precio);

                    // Lee la lista de precios
                    $listaprecio = ListaPrecio::find($precio['listaprecio_id']);

                    // Busca talles
                    $desdetalle = $hastatalle = '';
                    if ($listaprecio)
                    {
                        $desdetalle = Talle::select('id')->where('nombre', $listaprecio->desdetalle)->first();
                        $hastatalle = Talle::select('id')->where('nombre', $listaprecio->hastatalle)->first();
                    }
                    // Actualiza los pedidos con ese articulo
                    DB::table('pedido_combinacion')->where('articulo_id', $precio['articulo_id'])
                                                    ->update(['precio' => $precio['precio']]);

                    DB::table('pedido_combinacion_talle')->join('pedido_combinacion', 'pedido_combinacion_talle.pedido_combinacion_id', 'pedido_combinacion.id')
                                                    ->where('pedido_combinacion.articulo_id', $precio['articulo_id'])
                                                    ->whereBetween('pedido_combinacion_talle.talle_id', [$desdetalle->id, $hastatalle->id])
                                                    ->update(['pedido_combinacion_talle.precio' => $precio['precio']]);                                                

                    // Actualiza los movimientos de stock con ese articulo
                    DB::table('articulo_movimiento')->where('articulo_id', $precio['articulo_id'])
                                                    ->update(['precio' => $precio['precio']]);

                    DB::table('articulo_movimiento_talle')->join('articulo_movimiento', 'articulo_movimiento_talle.articulo_movimiento_id', 'articulo_movimiento.id')
                                                    ->where('articulo_movimiento.articulo_id', $precio['articulo_id'])
                                                    ->whereBetween('articulo_movimiento_talle.talle_id', [$desdetalle->id, $hastatalle->id])
                                                    ->update(['articulo_movimiento_talle.precio' => $precio['precio']]);                
                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollback();
                    dd($e->getMessage());
                    return $e->getMessage();
                }
            }
                
        }
    }

    public function headingRow(): int
    {
        return 1;
    }
}
