<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Liquidacion_Recibo_Sueldos;
use Carbon\Carbon;

/**
 * Catálogo Anita help_4 (campo empleado) + resolución de valor para listgen.
 */
final class ReporteSueldosDefinibleCampoEmpleadoSupport
{
    /**
     * @return array<int, string>
     */
    public static function catalogo(): array
    {
        return [
            1 => 'Categoría',
            2 => 'Fecha ingreso',
            3 => 'Fecha egreso',
            4 => 'CUIL',
            5 => 'Centro de costo',
            6 => 'Sindicato',
            7 => 'Modalidad SIJP',
            8 => 'Lugar de trabajo',
            9 => 'Fecha nacimiento',
            10 => 'Obra social',
            11 => 'Cód. obra social',
            12 => 'Mano de obra',
            13 => 'Cód. c. costo',
            14 => 'Cta. bancaria',
            15 => 'Domicilio',
            16 => 'Nacionalidad',
            17 => 'Estado civil',
            18 => 'Localidad',
            19 => 'Teléfono',
            20 => 'Sexo',
            21 => 'Motivo baja',
            22 => 'Tipo de liq.',
            23 => 'Leyenda 1',
            24 => 'Leyenda 2',
            25 => 'Agrupamiento',
            26 => 'C.costo anterior',
            27 => 'CBU',
            28 => 'Banco',
            29 => 'Tipo emp. FC',
            30 => 'CAT (Fecha ingreso)',
            31 => 'Cuenta empresa',
        ];
    }

    public static function etiqueta(?int $codigo): string
    {
        if ($codigo === null || $codigo <= 0) {
            return '';
        }

        return self::catalogo()[$codigo] ?? ('Campo '.$codigo);
    }

    /**
     * @param  array<string, mixed>  $contexto  Relaciones precargadas opcionales
     */
    public static function resolver(
        int $campo,
        ?Liquidacion_Recibo_Sueldos $recibo,
        ?Empleado_Sueldos $empleado,
        array $contexto = [],
        ?int $largo = null
    ): string {
        $emp = $empleado;
        $valor = match ($campo) {
            1 => (string) ($recibo->categoria_desc ?? $contexto['categoria_desc'] ?? $emp?->categoria?->descripcion ?? ''),
            2 => self::fmtFecha($recibo?->fecha_ingreso ?? $emp?->fecha_ingreso),
            3 => self::fmtFecha($emp?->fecha_egreso),
            4 => (string) ($recibo->cuil ?? $emp?->cuil ?? ''),
            5 => (string) ($contexto['centrocosto_nombre'] ?? $emp?->centrocosto?->nombre ?? ''),
            6 => (string) ($contexto['sindicato_nombre'] ?? $emp?->sindicato?->descripcion ?? ''),
            7 => (string) ($emp?->modalidad_sijp ?? ''),
            8 => (string) ($contexto['lugartrabajo_nombre'] ?? $emp?->lugartrabajo?->nombre ?? ''),
            9 => self::fmtFecha($emp?->fecha_nacimiento),
            10 => (string) ($contexto['obrasocial_nombre'] ?? $emp?->obrasocial?->descripcion ?? ''),
            11 => (string) ($emp?->obrasocial?->codigo ?? $contexto['obrasocial_codigo'] ?? ''),
            12 => (string) ($emp?->mano_obra ?? ''),
            13 => (string) ($emp?->centrocosto?->codigo ?? $contexto['centrocosto_codigo'] ?? ''),
            14 => (string) ($emp?->cuenta_bancaria ?? ''),
            15 => (string) ($emp?->domicilio ?? ''),
            16 => (string) ($emp?->nacionalidad ?? ''),
            17 => (string) ($emp?->estado_civil ?? ''),
            18 => (string) ($emp?->localidad ?? ''),
            19 => (string) ($emp?->telefono ?? ''),
            20 => (string) ($emp?->sexo ?? ''),
            21 => (string) ($contexto['motivoegreso_nombre'] ?? $emp?->motivoegreso?->descripcion ?? ''),
            22 => (string) ($emp?->codigo_liquidacion ?? ''),
            23 => (string) ($emp?->leyendas?->get(0)?->leyenda ?? ''),
            24 => (string) ($emp?->leyendas?->get(1)?->leyenda ?? ''),
            25 => (string) ($contexto['agrupamiento_nombre'] ?? $emp?->agrupamiento?->descripcion ?? ''),
            26 => (string) ($contexto['centrocosto_anterior_nombre'] ?? $emp?->getAttribute('centrocosto_anterior_nombre') ?? ''),
            27 => (string) ($emp?->cbu ?? ''),
            28 => (string) ($emp?->banco_codigo ?? ''),
            29 => (string) ($emp?->tipo_empresa_sijp ?? ''),
            30 => self::fmtFecha($recibo?->fecha_ingreso ?? $emp?->fecha_ingreso),
            31 => (string) ($emp?->empresa_id ?? ''),
            default => '',
        };

        if ($largo !== null && $largo > 0) {
            return mb_substr($valor, 0, $largo);
        }

        return $valor;
    }

    private static function fmtFecha(mixed $fecha): string
    {
        if ($fecha === null || $fecha === '') {
            return '';
        }
        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $fecha;
        }
    }
}
