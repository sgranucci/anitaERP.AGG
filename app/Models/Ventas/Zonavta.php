<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\ApiAnita;

class Zonavta extends Model
{
    protected $fillable = ['nombre', 'codigo'];
    protected $table = 'zonavta';
    protected $keyField = 'zonv_codigo';

    /**
     * Código que Anita espera en ven_zonavta / stkv_zona_vta (zonv_codigo), no el id ERP.
     */
    public static function codigoAnitaDesdeId(?int $zonavtaId): int
    {
        if ($zonavtaId === null || $zonavtaId <= 0) {
            return 0;
        }

        $codigo = trim((string) (self::query()->whereKey($zonavtaId)->value('codigo') ?? ''));
        if ($codigo === '' || ! ctype_digit($codigo)) {
            return (int) $codigo ?: 0;
        }

        return (int) $codigo;
    }

    public function destino(): HasOne
    {
        return $this->hasOne(Destino::class, 'zonavta_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 
                        'sistema' => 'ventas',
						'tabla' => $this->table, 
						'orderBy' => 'zonv_codigo' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $codigosLocales = Zonavta::query()->pluck('codigo')->map(fn ($c) => (string) $c)->all();
        
        if (is_array($dataAnita)) {
            foreach ($dataAnita as $value) {
                $codigo = (string) $value->{$this->keyField};
                if (! in_array($codigo, $codigosLocales, true)) {
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
            'campos' => 'zonv_codigo, zonv_desc',
            'sistema' => 'ventas',
            'tabla' => $this->table,
            'orderBy' => 'zonv_codigo',
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
            'acc' => 'list', 'tabla' => $this->table, 
            'sistema' => 'ventas',
            'campos' => 'zonv_codigo, zonv_desc',
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
        $key = $key ?? (string) ($data->zonv_codigo ?? '');
        if ($key === '') {
            return null;
        }

        $payload = [
            'nombre' => $data->zonv_desc,
            'codigo' => $data->zonv_codigo,
        ];

        $existente = Zonavta::query()
            ->where('codigo', (string) $data->zonv_codigo)
            ->orWhere('id', $key)
            ->first();

        if ($existente) {
            $existente->update($payload);

            return 'actualizado';
        }

        Zonavta::create(array_merge(['id' => $key], $payload));

        return 'insertado';
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        $data = array( 'tabla' => 'zonavta', 'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => ' zonv_codigo, zonv_desc ',
            'valores' => " '".$id."', 
						   '".$request->nombre."' "
        );
        $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => 'zonavta', 
                    'sistema' => 'ventas',
					'valores' => " 
								zonv_desc = '".  $request->nombre."' ",
					'whereArmado' => " WHERE zonv_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'zonavta', 
						'whereArmado' => " WHERE zonv_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}
    
}
