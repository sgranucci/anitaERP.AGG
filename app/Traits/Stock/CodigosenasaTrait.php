<?php

namespace App\Traits\Stock;

trait CodigosenasaTrait {

    public static $enumLlevaFrio = [
		'S' => 'Lleva Frio',
		'N' => 'No Lleva Frio',
		];

    /**
     * Normaliza el valor de ABM/Anita a S (frío) / N (sin frío).
     * En BD se guarda la etiqueta («Lleva Frio» / «No Lleva Frio»), no la letra.
     */
    public static function codigoFrio(?string $valor): string
    {
        $v = mb_strtoupper(trim((string) $valor));
        if ($v === '' || $v === 'N' || $v === 'NO' || str_starts_with($v, 'NO ')) {
            return 'N';
        }
        if ($v === 'S' || $v === 'SI' || $v === 'SÍ' || str_contains($v, 'LLEVA')) {
            return 'S';
        }

        return 'N';
    }
}
