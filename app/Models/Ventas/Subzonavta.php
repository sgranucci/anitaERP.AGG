<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Subzonavta extends Model
{
    protected $fillable = ['nombre'];
    protected $table = 'subzonavta';
    protected $tableAnita = 'subzona';
    protected $keyField = 'subz_codigo';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 
                        'sistema' => 'ventas',
						'tabla' => $this->tableAnita,
						'orderBy' => 'subz_codigo' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $idsLocales = Subzonavta::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        
        if (is_array($dataAnita)) {
            foreach ($dataAnita as $value) {
                $codigo = (string) $value->{$this->keyField};
                if (! in_array($codigo, $idsLocales, true)) {
                    $this->traerRegistroDeAnita($codigo);
                }
            }
        }
    }

    /**
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public function resincronizarConAnita(): array
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'campos' => 'subz_codigo, subz_desc',
            'sistema' => 'ventas',
            'tabla' => $this->tableAnita,
            'orderBy' => 'subz_codigo',
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $stats = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        if (! is_array($dataAnita)) {
            return $stats;
        }

        foreach ($dataAnita as $value) {
            $resultado = $this->upsertDesdeFilaAnita($value);
            if ($resultado === 'insertado') {
                $stats['insertados']++;
            } elseif ($resultado === 'actualizado') {
                $stats['actualizados']++;
            } else {
                $stats['omitidos']++;
            }
        }

        return $stats;
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'ventas',
            'campos' => 'subz_codigo, subz_desc',
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return null;
        }

        return $this->upsertDesdeFilaAnita($dataAnita[0], $key);
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    private function upsertDesdeFilaAnita(object $data, ?string $key = null): ?string
    {
        $key = $key ?? (string) ($data->subz_codigo ?? '');
        if ($key === '') {
            return null;
        }

        $payload = ['nombre' => $data->subz_desc];

        $existente = Subzonavta::query()->whereKey($key)->first();
        if ($existente) {
            $existente->update($payload);

            return 'actualizado';
        }

        Subzonavta::create(array_merge(['id' => $key], $payload));

        return 'insertado';
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => ' subz_codigo, subz_desc ',
            'valores' => " '".$id."', 
						   '".$request->nombre."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 
					'tabla' => $this->tableAnita, 
                    'sistema' => 'ventas',
					'valores' => " 
						subz_desc = '".  $request->nombre."' ",
					'whereArmado' => " WHERE subz_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'subzonavta', 
						'whereArmado' => " WHERE subz_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
}

