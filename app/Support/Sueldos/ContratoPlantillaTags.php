<?php

namespace App\Support\Sueldos;

/**
 * Tags de merge compatibles con Anita ($tag$) y modelos Crown (MOD12-ALE.docx).
 */
final class ContratoPlantillaTags
{
    /**
     * tag => descripción para la ayuda en pantalla.
     *
     * @var array<string, string>
     */
    public const CATALOGO = [
        'nombre_empleado' => 'Nombre del empleado',
        'documento' => 'Documento / DNI',
        'cuil' => 'CUIL',
        'direccion' => 'Domicilio',
        'localidad' => 'Localidad',
        'provincia' => 'Provincia',
        'nacionalidad' => 'Nacionalidad',
        'est_civil' => 'Estado civil',
        'categoria' => 'Categoría',
        'sueldo' => 'Sueldo / jornal (número)',
        'sueldo_en_letras' => 'Sueldo en letras',
        'mod_contrato' => 'Descripción de modalidad',
        'lugar' => 'Lugar de trabajo',
        'casino' => 'Lugar de trabajo (alias)',
        'dir_cas' => 'Dirección lugar de trabajo',
        'casino_direccion' => 'Dirección lugar de trabajo (alias)',
        'centro' => 'Centro de costo / sector',
        'comentario' => 'Comentario del contrato',
        'desdefecha' => 'Desde fecha (dd/mm/yyyy)',
        'hastafecha' => 'Hasta fecha (dd/mm/yyyy)',
        'dini' => 'Día inicio',
        'mini' => 'Mes inicio (nro)',
        'mini_txt' => 'Mes inicio (texto)',
        'aini' => 'Año inicio',
        'dfin' => 'Día fin',
        'mfin' => 'Mes fin (nro)',
        'mfin_txt' => 'Mes fin (texto)',
        'afin' => 'Año fin',
        'd_vig' => 'Días de vigencia',
        'dias_vigencia' => 'Días de vigencia (alias)',
        'nombre_empleado_reemplazado' => 'Empleado reemplazado',
        'dia' => 'Día emisión',
        'mes' => 'Mes emisión (nro)',
        'mes_txt' => 'Mes emisión (texto)',
        'anio' => 'Año emisión',
        'd' => 'Día emisión (cert laboral)',
        'm' => 'Mes emisión texto (cert laboral)',
        'a' => 'Año emisión (cert laboral)',
        'fechaing' => 'Fecha de ingreso',
        'fing' => 'Fecha de ingreso (alias)',
        'fechaegr' => 'Fecha de egreso',
        'fecha_entrega' => 'Fecha entrega documentación',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CATALOGO);
    }
}
