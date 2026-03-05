<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\Stock\TiendaNubeImport;
use Illuminate\Support\Facades\Storage;
use App\Models\Seguridad\Usuario;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Carbon\Carbon;
use DB;
use Auth;

class TiendaNubeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
	public function crearImportacion()
    {
        can('importar-tiendanube');
		
        return view('stock.tiendanube.crearimportacion');
    }

	public function importar(Request $request)
    {
        //$this->validate(request(), [
            //'file' => 'required|mimetypes::'.
                //'application/vnd.ms-office,'.
                //'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,'.
                //'application/vnd.ms-excel',
        //]);

        set_time_limit(0);

        $collection = Excel::toCollection(new TiendaNubeImport, request("file"));

        $anterSku = '';
        $respuesta = [];
        foreach($collection[0] as $item)
        {
            // Extrae linea del csv por si viene el texto o separado en un array
			if (isset($item[2]))
				$datos = $item;
			else
            	$datos = explode(";", $item[0]);

            // Extrae raiz del sku
            $sku = explode("-", $datos[0]);
            $skuRaiz = $sku[0];
            if ($skuRaiz != $anterSku)
            {
                // Envia anterior sku
                if ($anterSku != '' && $idArticulo != 0)
                    Self::cierraArticulo($idArticulo, $variant, $anterSku, $respuesta);

                // Lee el articulo con todas sus variantes
                $data = Self::leeTiendaNube($datos[0]);

                if (isset($data->id))
				{
                    $idArticulo = $data->id;
                	$anterSku = $skuRaiz;
				}
                else    
                    $idArticulo = 0;
                $salida = [];
                $variant = [];
            }

            if (!isset($data->variants))
            {
                $data = new \stdClass();
                $data->variants = [];
            }

            // Procesa cada variante
            foreach($data->variants as $variante)
            {
                // Si coincide con la linea que esta leyendo del csv la agrega a los datos a enviar
                if ($datos[0] == $variante->sku)
                {
                    $id = $variante->id;
                    $stock = (float) $datos[4];
                    $price = (float) $datos[2];
                    if ($datos[2] != $datos[3])
                        $promotionalPrice = (float) $datos[3];
                    else
                        $promotionalPrice = (float) 0;
    
                    $inventario = [];

                    $inventario[] = [
                        "stock" => (float) $stock
                    ];

                    $variant[] = [
                        "id" => $id,
                        "price" => $price,
                        "compare_at_price" => $price,
                        "promotional_price" => $promotionalPrice,   
                        "inventory_levels" => $inventario                 
                    ];
                }
            }
        }
        // Envia ultimo SKU
        if ($anterSku != '')
            Self::cierraArticulo($idArticulo, $variant, $anterSku, $respuesta);

        // Retorna estado de la transferencia
        return view('stock.tiendanube.index', compact('respuesta'));
    }

    // Lee un producto de API de tienda nube
    private function leeTiendaNube($sku)
    {
        // Lee el articulo con todas sus variantes
        $url = "https://api.tiendanube.com/v1/3796054/products/sku/".$sku;

        $curl = curl_init(); 
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array( 
                'Authentication: bearer 0dbf46228d998e16568037d613c3236d357423c9 ', 
                'User-Agent: Interface inventario (sergiogranucci@gmail.com)' )
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {     
            $error_msg = curl_error($curl); 
            echo $error_msg; 
            } 
        curl_close($curl);

        return json_decode($response);
    }

    // Graba informacion de precios y stock de un articulo en tienda nube
    private function grabaTiendaNube($datos)
    {
        // Genera llamada para actualizar stock y precios
        $url = "https://api.tiendanube.com/v1/3796054/products/stock-price";
        $curl = curl_init(); 
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_HTTPHEADER => array( 
                'Authentication: bearer 0dbf46228d998e16568037d613c3236d357423c9 ', 
                'Content-Type: application/json',
                'User-Agent: Interface inventario (sergiogranucci@gmail.com)' ),
            CURLOPT_POSTFIELDS => $datos
        ));
        $response = curl_exec($curl);
        dd($datos);
        if (curl_errno($curl)) {     
            $error_msg = curl_error($curl); 
            echo $error_msg; 
        } 
        curl_close($curl);

        return json_decode($response);
    }

    // Graba informacion de precios y stock de un articulo y variante en tienda nube
    private function grabaTiendaNubeVariante($idArticulo, $datos)
    {
        // Genera llamada para actualizar stock y precios
        $url = "https://api.tiendanube.com/v1/3796054/products/".$idArticulo."/variants";
        $curl = curl_init(); 
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url, 
            CURLOPT_RETURNTRANSFER => true, 
            CURLOPT_CUSTOMREQUEST => "PATCH",
            CURLOPT_HTTPHEADER => array( 
                'Authentication: bearer 0dbf46228d998e16568037d613c3236d357423c9 ', 
                'Content-Type: application/json',
                'User-Agent: Interface inventario (sergiogranucci@gmail.com)' ),
            CURLOPT_POSTFIELDS => $datos
        ));
        $response = curl_exec($curl);
        if (curl_errno($curl)) {     
            $error_msg = curl_error($curl); 
            echo $error_msg; 
        } 
        curl_close($curl);

        return json_decode($response);
    }
    
    private function cierraArticulo($idArticulo, $variant, $anterSku, &$respuesta)
    {
        $salidaJson = json_encode($variant);

        // Genera llamada para actualizar stock y precios
        $response = Self::grabaTiendaNubeVariante($idArticulo, $salidaJson);

        // Saca status de envio
        $numeroVariante = 0;
        foreach($response as $variant)
        {
            $numeroVariante++;
            if (isset($variant->id))
                $estado = "ok";
            else    
                $estado = $variant;

            // Agrega respuesta
            $respuesta[] = [
                "sku" => $anterSku,
                "variante" => $variant->sku,
                "estado" => $estado
            ];
        }
    }
}
