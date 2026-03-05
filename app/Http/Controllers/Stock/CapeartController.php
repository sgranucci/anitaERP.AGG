<?php

namespace App\Http\Controllers\Stock;

use App\Models\Stock\Capeart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\BO_Permission;

class CapeartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        
    }

    public function listJsonResponse($id)
    {
        $capeart = DB::table('capeart')
                        ->select('articulo_id as capea_articulo',
								  'id as capea_orden',
								  'material_id as capea_material',
								  'color_id as capea_color',
								  'piezas as capea_piezas',
								  'consumo1 as capea_consumo1',
								  'consumo2 as capea_consumo2',
								  'consumo3 as capea_consumo3',
								  'consumo4 as capea_consumo4',
								  'combinacion_id as capea_combinacion',
								  'tipo as capea_tipo')
                        ->where('combinacion_id','=',$id);
        
        $capeartTmp = array();

        $color = array(
            1 => 'azul',
            2 => 'rojo'
        );
        
        $material = array(
            1 => "Material Uno",
            2 => "Material Dos",
        );

        $tipo = array(
           1 => 'Tipo uno',
           2 => 'Tipo dos'
        );

        foreach( $capeart->get() as $key => $value){
            
            foreach( $material as $k => $v ){
                if( $k == $value->capea_material ){
                    $value->capea_material = $v;
                }
            }

            foreach( $color as $k => $v ){
                if( $k == $value->capea_color ){
                    $value->capea_color = $v;
                }
            }

            foreach( $tipo as $k => $v ){
                if( $k == $value->capea_tipo ){
                    $value->capea_tipo = $v;
                }
            }

            $capeartTmp[] = $value;
        }
        
        $capearts = array('data' => $capeartTmp);

        echo json_encode($capearts);
    }

    public function listJsonResponsenuevo($id)
    {
        $capeart = Capeart::with('articulos')->with('colores')->with('materiales')
                        ->where('combinacion_id','=',$id)->get();
        $capearts = array('data' => $capeart);

        echo json_encode($capearts);
    }

    public function create()
    {
        return view('capeart.create');
    }

    public function save(Request $request)
    {   
        $except = $request->except('_token');
        $capeartCheck = Capeart::where('combinacion_id',$except["combinacion_id"]);

		// Graba anita
		$Capeart = new Capeart();
        $Capeart->guardarAnita($request);

        $capeart = Capeart::create($except); 
        return "Capellada creada";
    }

    public function findJsonResponse(Request $request){
        $request = $request->all();
        $combinacion = $request["combinacion_id"];
        $articulo = $request["articulo_id"];
		$orden = $request["id"];

        $capeart = Capeart::where('combinacion_id', $combinacion)
                            ->where('articulo_id', $articulo)
                            ->where('id',$orden)->get()->first();

        return json_encode($capeart);
    }

    public function show(Capeart $capeart)
    {
        //
    }

    public function edit($id)
    {
                   
    }

    public function update(Request $request)
    {
        $newRequest = $request->except(['_token', 'combinacion_id','orden','articulo_id']);
        $request = $request->all();
        $capeart = Capeart::where('combinacion_id', $request["combinacion_id"])
                            ->where('articulo_id', $request["articulo_id"])
                            ->where('id', $request["id"]);
        $capeart->update($newRequest);

		// Graba anita
		$Capeart = new Capeart();
        $Capeart->guardarAnita($request);

        echo json_encode("Capellada editada");
    }

    public function delete(Request $request)
    {   
        $request = $request->all();
        $capeart = Capeart::where("combinacion_id",$request["combinacion_id"])
                            ->where("articulo_id",$request["articulo_id"])
                            ->where("id",$request["id"]);

		// Graba anita
		$Capeart = new Capeart();
        $Capeart->eliminarAnita($capeart->articulo_id, $capeart->combinacion_id);

        $capeart->delete();
        echo json_encode($capeart);
    }
}
