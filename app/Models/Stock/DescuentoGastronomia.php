<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DescuentoGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_PORCENTAJE = 'P';

    public const TIPO_IMPORTE = 'I';

    public const TIPO_APLICA = 'A';

    protected $fillable = ['nombre', 'codigo', 'tipovalor', 'valor'];

    protected $table = 'descuento_gastronomia';

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
}
