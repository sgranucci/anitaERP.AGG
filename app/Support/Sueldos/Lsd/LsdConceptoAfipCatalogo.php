<?php

namespace App\Support\Sueldos\Lsd;

use App\Models\Sueldos\Lsd_Concepto_Afip_Sueldos;
use Illuminate\Support\Facades\Schema;

class LsdConceptoAfipCatalogo
{
    public const TIPO_REMUNERATIVO = 'remunerativo';

    public const TIPO_NO_REMUNERATIVO = 'no_remunerativo';

    public const TIPO_DESCUENTO = 'descuento';

    public static function tipoDesdeCodigo(?string $codigo): ?string
    {
        $n = (int) preg_replace('/\D+/', '', (string) $codigo);
        if ($n >= 110000 && $n <= 499999) {
            return self::TIPO_REMUNERATIVO;
        }
        if ($n >= 510000 && $n <= 799999) {
            return self::TIPO_NO_REMUNERATIVO;
        }
        if ($n >= 810000 && $n <= 829999) {
            return self::TIPO_DESCUENTO;
        }

        return null;
    }

    public static function codigoValido(?string $codigo): bool
    {
        $c = self::normalizarCodigo($codigo);

        return $c !== null && self::tipoDesdeCodigo($c) !== null;
    }

    public static function normalizarCodigo(?string $codigo): ?string
    {
        $c = preg_replace('/\D+/', '', (string) $codigo) ?? '';
        if ($c === '') {
            return null;
        }

        return str_pad(substr($c, -6), 6, '0', STR_PAD_LEFT);
    }

    public static function pideCantidad(?string $codigo): bool
    {
        $c = self::normalizarCodigo($codigo);
        if ($c === null) {
            return false;
        }
        $n = (int) $c;
        if (in_array($c, ['120003', '150000'], true)) {
            return true;
        }

        return $n >= 130000 && $n <= 130003;
    }

    public static function fila(?string $codigo): ?Lsd_Concepto_Afip_Sueldos
    {
        $c = self::normalizarCodigo($codigo);
        if ($c === null) {
            return null;
        }

        return Lsd_Concepto_Afip_Sueldos::query()->where('codigo', $c)->first();
    }

    /** @return list<array{codigo: string, tipo: string, descripcion: string, pide_cantidad: bool}> */
    public static function paraSelector(): array
    {
        if (! Schema::hasTable('lsd_concepto_afip_sueldos')) {
            return [];
        }

        return Lsd_Concepto_Afip_Sueldos::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['codigo', 'tipo', 'descripcion', 'pide_cantidad'])
            ->map(fn ($r) => [
                'codigo' => (string) $r->codigo,
                'tipo' => (string) $r->tipo,
                'descripcion' => (string) $r->descripcion,
                'pide_cantidad' => (bool) $r->pide_cantidad,
            ])
            ->all();
    }

    public static function codigoEmpleadorDesdeInterno(int|string|null $codigoInterno): string
    {
        $n = (int) $codigoInterno;
        if ($n <= 0) {
            return str_repeat('0', 10);
        }

        return str_pad((string) $n, 10, '0', STR_PAD_LEFT);
    }
}
