<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Color extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'color';
    protected $keyField = 'col_color';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->table );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Color::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyField});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'campos' => '
                col_color,
                col_desc
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Color::create([
				"nombre" => $data->col_desc,
				"codigo" => $data->col_color
            ]);
        }
    }

    /**
     * Sync idempotente por código (col_color): importa los colores de Anita que falten.
     *
     * @return int cantidad importada
     */
    public function sincronizarCatalogoAnita(): int
    {
        $apiAnita = new ApiAnita();
        $data = ['acc' => 'list', 'campos' => 'col_color, col_desc', 'tabla' => $this->table, 'sistema' => 'sueldos'];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $existentes = self::query()->pluck('codigo')->filter()->map(fn ($c) => (int) $c)->all();

        $importados = 0;
        foreach ($dataAnita as $value) {
            $codigo = (int) ($value->col_color ?? 0);
            if ($codigo === 0 || in_array($codigo, $existentes, true)) {
                continue;
            }
            self::create([
                'nombre' => trim((string) ($value->col_desc ?? '')),
                'codigo' => $codigo,
            ]);
            $existentes[] = $codigo;
            $importados++;
        }

        return $importados;
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'color', 'acc' => 'insert',
            'campos' => ' col_color, col_desc ',
            'valores' => " '".$request->codigo."', '".$request->nombre."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'color', 
				'valores' => " col_desc = '".$request->nombre."'",
				'whereArmado' => " WHERE col_color = '".$request->codigo."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => 'color', 'whereArmado' => " WHERE col_color = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
}
