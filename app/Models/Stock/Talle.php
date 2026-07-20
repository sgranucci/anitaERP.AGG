<?php

namespace App\Models\Stock;

use App\ApiAnita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Talle extends Model
{
    protected $table = "talle";
    protected $fillable = ['nombre', 'codigo'];
    protected $keyField = 'tall_talle';

    protected $casts = [
        'codigo' => 'integer',
    ];

	public function modulos()
	{
		return $this->belongsToMany(Modulo::class);
	}

    /**
     * Trae desde Anita (tabla talle) los talles que falten localmente, por código.
     *
     * @return int cantidad importada
     */
    public function sincronizarConAnita(): int
    {
        $apiAnita = new ApiAnita();
        $data = ['acc' => 'list', 'campos' => 'tall_talle, tall_desc', 'tabla' => $this->table, 'sistema' => 'sueldos'];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $existentes = self::query()->pluck('codigo')->filter()->map(fn ($c) => (int) $c)->all();

        $importados = 0;
        foreach ($dataAnita as $value) {
            $codigo = (int) ($value->tall_talle ?? 0);
            if ($codigo === 0 || in_array($codigo, $existentes, true)) {
                continue;
            }
            self::create([
                'nombre' => trim((string) ($value->tall_desc ?? '')),
                'codigo' => $codigo,
            ]);
            $existentes[] = $codigo;
            $importados++;
        }

        return $importados;
    }
}
