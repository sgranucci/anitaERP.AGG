<?php

namespace App\Models\Ventas;

use App\Models\Ventas\Cliente;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DescuentoGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const TIPO_PORCENTAJE = 'P';

    public const TIPO_IMPORTE = 'I';

    public const TIPO_APLICA = 'A';

    public const TIPO_CONSUMO_STAFF = 'staff';

    public const TIPO_CONSUMO_INVITACION = 'invitacion';

    protected $fillable = ['nombre', 'codigo', 'tipovalor', 'valor', 'tipo_consumo', 'cliente_id'];

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

    /**
     * @return array<string, string>
     */
    public static function tiposConsumo(): array
    {
        return [
            self::TIPO_CONSUMO_STAFF => 'Staff',
            self::TIPO_CONSUMO_INVITACION => 'Invitación',
        ];
    }

    public function etiquetaTipoConsumo(): string
    {
        return self::tiposConsumo()[$this->tipo_consumo] ?? (string) $this->tipo_consumo;
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
