<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use App\Traits\Stock\CodigosenasaTrait;

class Codigosenasa extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use CodigosenasaTrait;
    protected $fillable = ['nombre', 'registro', 'envasesenasa_id', 'llevafrio', 'prefijo', 'codigo'];
    protected $table = 'codigosenasa';

    public function envasesenasas()
    {
        return $this->belongsTo(Envasesenasa::class, 'envasesenasa_id');
    }

    /**
     * @return array{id: int, codigo: string, nombre: string, registro: string, prefijo: string, llevafrio: string}
     */
    public function aConsultaArray(): array
    {
        return [
            'id' => (int) $this->id,
            'codigo' => (string) $this->codigo,
            'nombre' => trim((string) $this->nombre),
            'registro' => trim((string) $this->registro),
            'prefijo' => trim((string) $this->prefijo),
            'llevafrio' => (string) $this->llevafrio,
        ];
    }

}
