<?php

namespace App\Models\Caja\Estacionamiento;

use App\Models\Ventas\Cliente;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DescuentoEstacionamiento extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_PORCENTAJE = 'P';

    public const TIPO_IMPORTE = 'I';

    public const TIPO_APLICA = 'A';

    protected $fillable = ['nombre', 'codigo', 'tipovalor', 'valor', 'cliente_id'];

    protected $table = 'descuento_estacionamiento';

    /**
     * @return array<string, string>
     */
    public static function tiposValor(): array
    {
        return [
            self::TIPO_PORCENTAJE => 'Porcentaje',
            self::TIPO_IMPORTE => 'Importe',
            self::TIPO_APLICA => 'Aplica',
        ];
    }

    public function etiquetaTipoValor(): string
    {
        return self::tiposValor()[$this->tipovalor] ?? $this->tipovalor;
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
