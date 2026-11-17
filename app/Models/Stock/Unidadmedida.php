<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use OwenIt\Auditing\Contracts\Auditable;

class Unidadmedida extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['id', 'nombre', 'abreviatura', 'codigo'];
    protected $table = 'unidadmedida';
    protected $tableAnita = 'stkumd';
    protected $keyField = 'stkum_umd';

    public function articulos()
    {
        return $this->hasMany(Articulo::class);
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocalArray = Unidadmedida::query()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($dataAnita as $value) {
            $idAnita = (int) ($value->{$this->keyField} ?? 0);
            if ($idAnita <= 0 || in_array($idAnita, $datosLocalArray, true)) {
                continue;
            }
            $this->traerRegistroDeAnita($value->{$this->keyField});
            $datosLocalArray[] = $idAnita;
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                stkum_umd,
				stkum_desc,
				stkum_abreviatura ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
            Unidadmedida::create([
                'id' => (int) $key,
				"nombre" => $data->stkum_desc,
				"abreviatura" => $data->stkum_abreviatura
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'campos' => ' stkum_umd, stkum_desc, stkum_abreviatura ',
            'valores' => " '".$id."', '".$request->nombre."' , '".$request->abreviatura."'"
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita,
				'valores' => " stkum_desc = '".$request->nombre.
					"' , stkum_abreviatura = '".$request->abreviatura."'", 
				'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE stkum_umd = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

    /**
     * Selector de UM sin abreviaturas duplicadas (ids legacy ERP + sync Anita).
     * Prefiere el id más alto (códigos Anita 9xxx, los que usan los artículos).
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function listadoParaSelectorSinDuplicados()
    {
        return static::query()
            ->orderByDesc('id')
            ->get()
            ->unique(static function (self $um): string {
                $abr = strtoupper(trim((string) ($um->abreviatura ?? '')));

                return $abr !== '' ? $abr : strtoupper(trim((string) ($um->nombre ?? '')).'#'.$um->id);
            })
            ->sortBy(static function (self $um): string {
                return strtoupper(trim((string) ($um->abreviatura ?: $um->nombre ?: '')));
            }, SORT_NATURAL)
            ->values();
    }
}
