<?php

namespace App\Models\Uif;

use App\ApiAnita;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Localidad_Uif extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'codigopostal', 'provincia_uif_id', 'codigo'];
    protected $table = 'localidad_uif';

    /** Tabla Anita (base_admin) de la que se sincroniza. */
    protected $tableAnita = 'localidad';

    protected $keyFieldAnita = 'loc_localidad';

    public function provincias()
    {
        return $this->belongsTo(Provincia_Uif::class, 'provincia_uif_id');
    }

    /**
     * Próximo código Anita/ERP: max(Anita base_admin, MySQL numérico) + 1.
     */
    public function proximoCodigo(): int
    {
        $cast = SqlDialectSupport::castEntero('codigo');
        $maxLocal = (int) (self::query()->selectRaw("MAX({$cast}) as m")->value('m') ?? 0);

        $maxAnita = 0;
        try {
            $apiAnita = new ApiAnita();
            $raw = $apiAnita->apiCall([
                'acc' => 'list',
                'sistema' => 'base_admin',
                'tabla' => $this->tableAnita,
                'campos' => 'max('.$this->keyFieldAnita.') as max_codigo',
            ]);
            $fila = ApiAnita::primeraFilaLista(is_string($raw) ? $raw : null);
            $maxAnita = (int) ($fila->max_codigo ?? 0);
        } catch (\Throwable $e) {
            // Si Anita no responde, igual se puede sugerir desde MySQL.
        }

        return max($maxAnita, $maxLocal) + 1;
    }
}
