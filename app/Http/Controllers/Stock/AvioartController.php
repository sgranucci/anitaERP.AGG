<?php

namespace App\Http\Controllers\Stock;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Stock\Avioart;
use App\Models\Stock\Material;
use App\Models\Stock\Color;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionAvioart;

class AvioartController extends Controller
{
    use HasPermissions;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function list()
    {
        return view('stock.avioart.list');
    }

    public function listJsonResponse(){
        $avioart = Avioart::orderBy('id');
        $avioarts = array('data' => $avioart->get());
        echo json_encode($avioarts);
    }

    public function listJsonResponseId($id){
	  	$avioarts = Avioart::with('articulos:id,nombre')->with('combinacion:id,nombre')->with('material:id,nombre')->
							with('color:id,nombre')->width('usuarios:id,nombre');

		$color = Color::all();
		$material = Material::all();

        $avioart = array('data' => $avioarts->get());
        echo json_encode($avioart);
    }

    public function create()
    {
        return view('stock.avioart.create');
    }

    public function save(ValidacionAvioart $request)
    {
        $avio = Avioart::create($request->all());

		// Graba anita
		$Avio = new Avioart();
        $Avio->guardarAnita($request);

        return redirect()->route('stock.avioart.list')->with('status', 'Avio creado');

    }

    public function saveJsonResponse(Request $request)
    {
        $except = $request->except('_token');
        $aviosCheck = Avioart::where('combinacion_id',$except["combinacion_id"]);
        $avios = Avioart::create($except);
		//
		// Graba anita
		$Avio = new Avioart();
        $Avio->guardarAnita($request);

        return "Avio creado";
    }

    public function show(Avioart $avioart)
    {
        //
    }

    public function edit($id)
    {
        $avios = Avioart::where('combinacion_id',$id)->get()->first();
        return view('avioart.edit',compact('avios','id'));
    }

    public function update(Request $request)
    {
        $data = $request->all();

        $avio = Avioart::where('id',$data['id']);

        $avio->update([
            'material_id' => $data['material_id'],
            'color_id' => $data['color_id'],
            'consumo1' => $data['consumo1'],
            'consumo2' => $data['consumo2'],
            'consumo3' => $data['consumo3'],
            'consumo4' => $data['consumo4'],
            'tipo' => $data['tipo'] 
        ]);

        return redirect()->route('stock.avioart.list')->with('status', 'Avio editado');   
    }

    public function updateJsonResponse(Request $request){
        $newRequest = $request->except(['_token','combinacion_id','id','articulo_id']);
        $request = $request->all();
        $avios = Avioart::where('combinacion_id', $request["combinacion_id"])
                        ->where('articulo_id', $request["articulo_id"])
                        ->where('id', $request["id"]);
        $avios->update($newRequest);
        echo json_encode("Avio editada");
    }

    public function delete($id)
    {
        $avio = Avioart::where('id',$id);

		// Borra anita
		$Avio = new Avioart();
		$Avio->eliminarAnita($avio->articulo_id, $avio->combinacion_id);

        $avio->delete();

        return redirect()->route('stock.avioart.list')->with('status', 'Datos eliminados con exito'); 
    }

    public function deleteJsonResponse(Request $request)
    {
        $request = $request->all();
        $avios = Avioart::where("combinacion_id",$request["combinacion_id"])
                        ->where("articulo_id",$request["articulo_id"])
                        ->where("id",$request["id"]);

		// Borra anita
		$Avio = new Avioart();
		$Avio->eliminarAnita($avios->articulo_id, $avios->combinacion_id);

        $avios->delete();
        echo json_encode($avios);
    }

    public function findJsonResponse(Request $request){
        $request = $request->all();
        $combinacion = $request["id"];
        $orden = $request["id"];
        $articulo = $request["articulo_id"];
        $avios = Avioart::where('combinacion_id', $request["id"])
                        ->where('articulo_id', $articulo)
                        ->where('id', $orden)->get()->first();
        return json_encode($avios);
    }
}
