<?php

namespace App\Http\Controllers;

use App\Capellada;
use App\Capeart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\BO_Permission;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasPermissions;

class CapelladaController extends Controller
{
    use HasPermissions;

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function list()
    {
        return view('capellada.list');
    }

    public function listJsonResponse(){
        $capellada = new Capellada();
        $capelladas = array('data' => $capellada->all());
        echo json_encode($capelladas);
    }

    public function create()
    {
        return view('capellada.create');
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'marm_marca' => ['required', 'string', 'max:8'],
            'marm_desc' => ['required', 'string', 'max:30'],
            'marm_formula' => ['required', 'string', 'max:60'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                             ->withErrors($validator)
                             ->withInput();
        }

        $data = $request->all();
        
        $capellada = Capellada::create([
            'marm_marca' => $data['marm_marca'],
            'marm_desc' => $data['marm_desc'],
            'marm_formula' => $data['marm_formula']
        ]);

        return redirect()->route('capellada.list')->with('status', 'Capellada creada');
    }

    public function show(Capellada $capellada)
    {
        //
    }

    public function edit($id)
    {
        $capellada = Capellada::where('marm_marca', $id)->get()->first();
        $capeart = Capeart::where('capea_marm_marca',$id)->get()->first();
        return view('capellada.edit',compact('capellada','id','capeart'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Capellada  $capellada
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'marm_marca' => ['sometimes','required', 'string', 'max:8'],
            'marm_desc' => ['sometimes','required', 'string', 'max:30'],
            'marm_formula' => ['sometimes','required', 'string', 'max:60'],
            'capea_material' => ['sometimes','required','string','max:8'],
            'capea_color' => ['sometimes','required','string','max:8'],
            'capea_piezas' => ['sometimes','string','max:8','nullable'],
            'capea_consumo1' => ['sometimes','string','max:1','nullable'],
            'capea_consumo2' => ['sometimes','string','max:1','nullable'],
            'capea_consumo3' => ['sometimes','string','max:1','nullable'],
            'capea_consumo4' => ['sometimes','string','max:1','nullable'],
            'capea_tipo' => ['sometimes','integer','nullable'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                             ->withErrors($validator)
                             ->withInput();
        }

        $data = $request->all();
        
        if(auth()->user()->can('datos-diseno')){
            $capellada = Capellada::where('marm_marca', $data['id']);

            $capellada->update([
                'marm_marca' => $data['marm_marca'],
                'marm_desc' => $data['marm_desc'],
                'marm_formula' => $data['marm_formula']
            ]);
        }
        
        if(auth()->user()->can('datos-tecnicos')){
            $capeart = Capeart::where('capea_marm_marca', $data['id']);
            if($capeart->count() > 0 ){
                $capeart->update([
                    'capea_material' => $data['capea_material'],
                    'capea_color' => $data['capea_color'],
                    'capea_piezas' => $data['capea_piezas'],
                    'capea_consumo1' => $data['capea_consumo1'],
                    'capea_consumo2' => $data['capea_consumo2'],
                    'capea_consumo3' => $data['capea_consumo3'],
                    'capea_consumo4' => $data['capea_consumo4'],
                    'capea_tipo' => $data['capea_tipo']
                ]); 
            }else{

                $capeart = Capeart::create([
                    'capea_marm_marca' => $data['id'],
                    'capea_material' => $data['capea_material'],
                    'capea_color' => $data['capea_color'],
                    'capea_piezas' => $data['capea_piezas'],
                    'capea_consumo1' => $data['capea_consumo1'],
                    'capea_consumo2' => $data['capea_consumo2'],
                    'capea_consumo3' => $data['capea_consumo3'],
                    'capea_consumo4' => $data['capea_consumo4'],
                    'capea_tipo' => $data['capea_tipo']
                ]);
            }
        }

        return redirect()->route('capellada.list')->with('status', 'Capellada editada');   
    }

    public function delete($id)
    {
        $capellada = Capellada::where('marm_marca', $id);
        $capellada->delete();
        return redirect()->route('capellada.list')->with('status', 'Datos eliminados con exito'); 
    }
}
