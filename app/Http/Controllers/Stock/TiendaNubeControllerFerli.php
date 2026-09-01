<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Imports\Stock\TiendaNubeImport;
use Maatwebsite\Excel\Facades\Excel;

class TiendaNubeControllerFerli extends Controller
{
    private $tienda;

    public function crearImportacion()
    {
        can('importar-tiendanube');

        $tipoimportacion_enum = [
            'STOCKPRECIO' => 'Stock y precios',
            'STOCK' => 'Solo Stock',
            'PRECIO' => 'Solo Precios',
        ];

        $tienda_enum = [
            'FERLI' => 'Tienda Ferli',
            'BOAONDA' => 'Tienda Boaonda',
        ];

        return view('stock.tiendanube_ferli.crearimportacion', compact('tipoimportacion_enum', 'tienda_enum'));
    }

    public function importar(Request $request)
    {
        set_time_limit(0);

        $collection = Excel::toCollection(new TiendaNubeImport, request('file'));

        $tipoImportacion = $request->tipoimportacion;
        $this->tienda = $request->tienda;

        $anterSku = '';
        $idArticulo = 0;
        $variant = [];
        $respuesta = [];
        foreach ($collection[0] as $item) {
            if (isset($item[2])) {
                $datos = $item;
            } else {
                $datos = explode(';', $item[0]);
            }

            $sku = explode('-', $datos[0]);
            $skuRaiz = $sku[0];
            if ($skuRaiz != $anterSku) {
                if ($anterSku != '' && $idArticulo != 0) {
                    Self::cierraArticulo($idArticulo, $variant, $anterSku, $respuesta);
                }

                $data = Self::leeTiendaNube($datos[0]);
                if (isset($data->id)) {
                    $idArticulo = $data->id;
                    $anterSku = $skuRaiz;
                } else {
                    $idArticulo = 0;
                }
                $variant = [];
            }

            if (!isset($data->variants)) {
                $data = new \stdClass();
                $data->variants = [];
            }

            foreach ($data->variants as $variante) {
                if ($datos[0] == $variante->sku) {
                    $id = $variante->id;
                    $stock = (float) $datos[4];
                    $price = (float) $datos[2];
                    if ($datos[2] != $datos[3]) {
                        $promotionalPrice = (float) $datos[3];
                    } else {
                        $promotionalPrice = (float) 0;
                    }

                    $inventario = [];
                    $inventario[] = [
                        'stock' => (float) $stock,
                    ];

                    switch ($tipoImportacion) {
                        case 'STOCKPRECIO':
                            $variant[] = [
                                'id' => $id,
                                'price' => $price,
                                'compare_at_price' => $price,
                                'promotional_price' => $promotionalPrice,
                                'inventory_levels' => $inventario,
                            ];
                            break;
                        case 'PRECIO':
                            $variant[] = [
                                'id' => $id,
                                'price' => $price,
                                'compare_at_price' => $price,
                                'promotional_price' => $promotionalPrice,
                            ];
                            break;
                        case 'STOCK':
                            $variant[] = [
                                'id' => $id,
                                'price' => $price,
                                'compare_at_price' => $price,
                                'inventory_levels' => $inventario,
                            ];
                            break;
                    }
                }
            }
        }
        if ($anterSku != '') {
            Self::cierraArticulo($idArticulo, $variant, $anterSku, $respuesta);
        }

        return view('stock.tiendanube_ferli.index', compact('respuesta'));
    }

    private function credencialesTienda()
    {
        if ($this->tienda == 'FERLI') {
            return [
                'store' => '3796054',
                'token' => '0dbf46228d998e16568037d613c3236d357423c9',
            ];
        }

        return [
            'store' => '6250382',
            'token' => 'f720a3872d8110e83857824216581471271ee3e5',
        ];
    }

    private function leeTiendaNube($sku)
    {
        $credenciales = $this->credencialesTienda();
        $url = 'https://api.tiendanube.com/v1/'.$credenciales['store'].'/products/sku/'.$sku;

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Authentication: bearer '.$credenciales['token'],
                'User-Agent: Interface inventario (sergiogranucci@gmail.com)',
            ],
        ]);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            echo curl_error($curl);
        }
        curl_close($curl);

        return json_decode($response);
    }

    private function grabaTiendaNubeVariante($idArticulo, $datos)
    {
        $credenciales = $this->credencialesTienda();
        $url = 'https://api.tiendanube.com/v1/'.$credenciales['store'].'/products/'.$idArticulo.'/variants';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PATCH',
            CURLOPT_HTTPHEADER => [
                'Authentication: bearer '.$credenciales['token'],
                'Content-Type: application/json',
                'User-Agent: Interface inventario (sergiogranucci@gmail.com)',
            ],
            CURLOPT_POSTFIELDS => $datos,
        ]);
        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            echo curl_error($curl);
        }
        curl_close($curl);

        return json_decode($response);
    }

    private function cierraArticulo($idArticulo, $variant, $anterSku, &$respuesta)
    {
        $salidaJson = json_encode($variant);
        $response = Self::grabaTiendaNubeVariante($idArticulo, $salidaJson);

        if (!is_iterable($response)) {
            return;
        }

        foreach ($response as $variante) {
            if (isset($variante->sku)) {
                $estado = isset($variante->id) ? 'ok' : $variante;
                $respuesta[] = [
                    'sku' => $anterSku,
                    'variante' => $variante->sku,
                    'estado' => $estado,
                ];
            }
        }
    }
}
