<?php

namespace App\Support\Sueldos;

/**
 * Tablas de códigos del F572 Web (SiRADIG - ARCA), manual del desarrollador v1.24.
 *
 * Punto único para resolver descripciones a partir de los códigos que vienen en el
 * XML (deducciones, parentesco, provincias, tarjetas, etc.). Evita crear tablas de
 * catálogo en BD: los códigos se persisten y la descripción se resuelve acá.
 */
class SiradigTablas
{
    public const GRUPO_DEDUCCION = 'D';

    public const GRUPO_RETENCION = 'R';

    public const GRUPO_AJUSTE = 'J';

    /** Tabla 1: Provincias (código ARCA => descripción) */
    public const PROVINCIAS = [
        0 => 'Ciudad Autónoma de Buenos Aires',
        1 => 'Buenos Aires',
        2 => 'Catamarca',
        3 => 'Córdoba',
        4 => 'Corrientes',
        5 => 'Entre Ríos',
        6 => 'Jujuy',
        7 => 'Mendoza',
        8 => 'La Rioja',
        9 => 'Salta',
        10 => 'San Juan',
        11 => 'San Luis',
        12 => 'Santa Fe',
        13 => 'Santiago del Estero',
        14 => 'Tucumán',
        16 => 'Chaco',
        17 => 'Chubut',
        18 => 'Formosa',
        19 => 'Misiones',
        20 => 'Neuquén',
        21 => 'La Pampa',
        22 => 'Río Negro',
        23 => 'Santa Cruz',
        24 => 'Tierra del Fuego',
    ];

    /** Tabla 2: Tipo de Documento */
    public const TIPOS_DOCUMENTO = [
        80 => 'CUIT',
        86 => 'CUIL',
        87 => 'CDI',
        96 => 'DNI',
        89 => 'LC',
        90 => 'LE',
        92 => 'En Trámite',
    ];

    /** Tabla 3: Parentesco (período 2022 en adelante + históricos) */
    public const PARENTESCO = [
        1 => 'Cónyuge',
        3 => 'Hijo/a Menor de 18 Años',
        30 => 'Hijastro/a Menor de 18 Años',
        31 => 'Hijo/a Incapacitado para el Trabajo',
        32 => 'Hijastro/a Incapacitado para el Trabajo',
        33 => 'Padre',
        34 => 'Madre',
        35 => 'Nieto/a Menor de 24 Años',
        36 => 'Nieto/a Incapacitado para el Trabajo',
        37 => 'Bisnieto/a Menor de 24 Años',
        38 => 'Bisnieto/a Incapacitado para el Trabajo',
        39 => 'Abuelo/a',
        40 => 'Bisabuelo/a',
        41 => 'Padrastro/Madrastra',
        42 => 'Hermano/a Menor de 24 Años',
        43 => 'Hermano/a Incapacitado para el Trabajo',
        44 => 'Suegro/a',
        45 => 'Yerno/Nuera Menor de 24 Años',
        46 => 'Yerno/Nuera Incapacitado para el Trabajo',
        51 => 'Unión Convivencial',
        103 => 'Hijo/a Mayor de 18 y Hasta 24 años',
    ];

    /** Tabla 4: Deducciones (atributo tipo del elemento <deduccion>) */
    public const DEDUCCIONES = [
        1 => 'Cuotas Médico-Asistenciales',
        2 => 'Primas de Seguro para el caso de muerte / riesgo de muerte',
        3 => 'Donaciones',
        4 => 'Intereses Préstamo Hipotecario',
        5 => 'Gastos de Sepelio',
        7 => 'Gastos Médicos y Paramédicos',
        8 => 'Deducción del Personal Doméstico',
        9 => 'Aporte a Sociedades de Garantía Recíproca',
        10 => 'Vehículos de Corredores y Viajantes de Comercio',
        11 => 'Gastos de Representación e Intereses de Corredores y Viajantes de Comercio',
        21 => 'Gastos de Adquisición de Indumentaria y Equipamiento para uso Exclusivo en el Lugar de Trabajo',
        22 => 'Alquiler de Inmuebles casa habitación – Locatarios (Inquilinos) - 40%',
        23 => 'Primas de Ahorro correspondientes a Seguros Mixtos',
        24 => 'Aportes correspondientes a Planes de Seguro de Retiro Privados',
        25 => 'Adquisición de Cuotapartes de Fondos Comunes de Inversión con fines de retiro',
        32 => 'Gastos de Educación',
        33 => 'Alquiler de Inmuebles casa habitación – Locatarios (Inquilinos) – 10%',
        34 => 'Alquiler de Inmuebles casa habitación – Locadores (Propietarios)',
        99 => 'Otras Deducciones',
    ];

    /** Tabla 5: Motivos (Otras Deducciones, tipo 99, detalle "motivo") */
    public const MOTIVOS_OTRAS_DEDUCCIONES = [
        1 => 'Aportes para fondos de Jubilación, Retiros, Pensiones o Subsidios destinados al ANSES',
        2 => 'Cajas Provinciales o Municipales',
        3 => 'Impuesto sobre los Créditos y Débitos en Cuenta Bancaria sin CBU',
        4 => 'Beneficios de Regímenes con tratamientos Preferenciales que se Efectivicen Mediante Deducciones',
        5 => 'Beneficios de Regímenes con tratamientos Preferenciales que No se Efectivicen Mediante Deducciones',
        6 => 'Actores - Retribuciones Abonadas a Representantes - R.G. N° 2442/08',
        7 => 'Cajas Complementarias de Previsión',
        8 => 'Fondos Compensadores de Previsión',
        9 => 'Otros',
    ];

    /** Tabla 6: Ajustes (atributo tipo del elemento <ajuste>) */
    public const AJUSTES = [
        1 => 'Montos Retroactivos',
        2 => 'Reintegros de Soc. de Garantía Recíproca Art. 79 Párrafo 2 y Párrafo 3',
    ];

    /** Tabla 7: Tipos de Tarjeta */
    public const TIPOS_TARJETA = [
        0 => 'Totalizado / No Aplica',
        1 => 'Tarjeta de Crédito / Compra',
        2 => 'Tarjeta de Débito',
    ];

    /** Tabla 8: Id de Tarjetas */
    public const ID_TARJETAS = [
        1 => 'MasterCard',
        2 => 'Visa',
        3 => 'American Express',
        4 => 'Cabal',
        5 => 'Italcred',
        6 => 'Naranja',
        7 => 'Nativa',
        8 => 'Diners Club',
        99 => 'Otra',
    ];

    /** Tabla 9: Retenciones, Percepciones y Pagos a Cuenta (atributo tipo de <retPerPago>) */
    public const RETENCIONES = [
        6 => 'Impuestos sobre Créditos y Débitos en cuenta Bancaria',
        12 => 'Retenciones y Percepciones Aduaneras',
        13 => 'Pago a Cuenta - Compras en el Exterior',
        14 => 'Impuesto sobre los Movimientos de Fondos Propios o de Terceros',
        15 => 'Pago a Cuenta - Compra de Paquetes Turísticos',
        16 => 'Pago a Cuenta - Compra de Pasajes',
        17 => 'Pago a Cuenta - Compra de Moneda Extranjera para Turismo / Transf. al Exterior',
        18 => 'Pago a Cuenta - Adquisición de moneda extranjera para tenencia de billetes extranjeros en el país',
        19 => 'Pago a Cuenta - Compra de Paquetes Turísticos en efectivo',
        20 => 'Pago a Cuenta - Compra de Pasajes en efectivo',
        27 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. a)',
        28 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. b)',
        29 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. c)',
        30 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. d)',
        31 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. e)',
        35 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. a) [2023] (RG 5450/2023)',
        36 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. b) [2023] (RG 5450/2023)',
        37 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. c) [2023] (RG 5450/2023)',
        38 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. d) [2023] (RG 5450/2023)',
        39 => 'Pago a Cuenta - RG 4815 - Ley 27541 - Art. 35 inc. e) [2023] (RG 5450/2023)',
        40 => 'Autorretenciones - RG 5683/2025',
    ];

    /** Tabla 10: Tipos de Norma */
    public const TIPOS_NORMA = [
        0 => 'Ley',
        1 => 'Decreto',
        2 => 'RG',
    ];

    /** Tabla 11: Datos Adicionales (atributo nombre de <datoAdicional>) */
    public const DATOS_ADICIONALES = [
        'exencionGan2016SAC1' => 'Exención impuesto a las ganancias 1ra cuota SAC 2016 (Ley 27.260 art. 63)',
        'trabRegPatagonica' => 'Trabajó en zona patagónica',
        'jubPensRegPatagonica' => 'Jubilado/pensionado/retirado vivió en zona patagónica',
        'jubPensOtrosIngresos' => 'Jubilado/pensionado/retirado percibió otros ingresos',
        'jubPensTribBienes' => 'Jubilado/pensionado/retirado tributó Bienes Personales',
        'jubPensTribOtrosBienes' => 'Jubilado/pensionado/retirado tiene otros bienes por los que tributó Bienes Personales',
    ];

    /** Tabla 12: Tipo de Gasto (detalle de deducción tipo 32 - Educación) */
    public const TIPOS_GASTO = [
        1 => 'Servicios con fines educativos',
        2 => 'Herramientas educativas',
    ];

    /** Régimen del elemento <ingAp> (>= 2024) */
    public const REGIMENES = [
        'G' => 'General',
        'C' => 'Cedular',
    ];

    /** Etiquetas de los importes mensuales de otros empleadores (columna => label). */
    public const INGRESO_APORTE_LABELS = [
        'gan_brut' => 'Ganancia bruta',
        'ret_gan' => 'Retención Ganancias',
        'obra_soc' => 'Aporte Obra Social',
        'seg_soc' => 'Aporte Seguridad Social',
        'seg_soc_anses' => 'Seg. Social - ANSES',
        'seg_soc_cajas' => 'Seg. Social - Cajas Prev.',
        'sind' => 'Aportes Sindicales',
        'sac' => 'SAC',
        'retrib_no_hab' => 'Retribuciones no habituales',
        'ajuste' => 'Ajustes',
        'ajuste_rem_gravadas' => 'Ajustes rem. gravadas',
        'ajuste_rem_exe_no_alcanzadas' => 'Ajustes rem. exentas/no alcanzadas',
        'exe_no_alc' => 'Exentos / No alcanzados',
        'asign_fam' => 'Asignaciones Familiares',
        'int_prest_emp' => 'Intereses préstamos al empleador',
        'remun_judiciales' => 'Remuneraciones judiciales',
        'indem_ley4003' => 'Indemnizaciones RG 4003/2017',
        'remun_ley19640' => 'Remun. Ley 19.640 (T. del Fuego)',
        'remun_cct_petro' => 'Remun. CCT 396/2004 (Petroleros)',
        'cursos_semin' => 'Cursos y Seminarios',
        'indum_equip_emp' => 'Indumentaria/Equipamiento',
        'horas_ext_gr' => 'Horas extras gravadas',
        'horas_ext_ex' => 'Horas extras exentas',
        'mat_did' => 'Material didáctico',
        'movilidad' => 'Movilidad',
        'viaticos' => 'Viáticos',
        'otros_con_an' => 'Otros conceptos análogos',
        'bonos_prod' => 'Bonos de productividad',
        'fallos_caja' => 'Fallos de caja',
        'con_sim_nat' => 'Conceptos de similar naturaleza',
        'remun_exenta_ley27549' => 'Remun. exenta Ley 27.549/27.718',
        'suplem_partic_ley19101' => 'Suplementos art. 57 Ley 19.101',
        'teletrabajo_exento' => 'Comp. gastos teletrabajo (exento)',
        'no_ret_med_caut' => 'Importe no retenido - Medidas cautelares',
    ];

    public static function provincia(?int $codigo): string
    {
        return self::etiqueta(self::PROVINCIAS, $codigo);
    }

    public static function tipoDocumento(?int $codigo): string
    {
        return self::etiqueta(self::TIPOS_DOCUMENTO, $codigo);
    }

    public static function parentesco(?int $codigo): string
    {
        return self::etiqueta(self::PARENTESCO, $codigo);
    }

    public static function deduccion(?int $codigo): string
    {
        return self::etiqueta(self::DEDUCCIONES, $codigo);
    }

    public static function retencion(?int $codigo): string
    {
        return self::etiqueta(self::RETENCIONES, $codigo);
    }

    public static function ajuste(?int $codigo): string
    {
        return self::etiqueta(self::AJUSTES, $codigo);
    }

    public static function motivoOtraDeduccion(?int $codigo): string
    {
        return self::etiqueta(self::MOTIVOS_OTRAS_DEDUCCIONES, $codigo);
    }

    public static function tipoTarjeta(?int $codigo): string
    {
        return self::etiqueta(self::TIPOS_TARJETA, $codigo);
    }

    public static function idTarjeta(?int $codigo): string
    {
        return self::etiqueta(self::ID_TARJETAS, $codigo);
    }

    public static function tipoNorma(?int $codigo): string
    {
        return self::etiqueta(self::TIPOS_NORMA, $codigo);
    }

    public static function tipoGasto(?int $codigo): string
    {
        return self::etiqueta(self::TIPOS_GASTO, $codigo);
    }

    public static function datoAdicional(?string $nombre): string
    {
        $nombre = trim((string) $nombre);

        return self::DATOS_ADICIONALES[$nombre] ?? $nombre;
    }

    public static function regimen(?string $codigo): string
    {
        $codigo = strtoupper(trim((string) $codigo));

        return self::REGIMENES[$codigo] ?? $codigo;
    }

    /**
     * Descripción del concepto según su grupo (D/R/J).
     */
    public static function concepto(string $grupo, ?int $codigo): string
    {
        return match ($grupo) {
            self::GRUPO_DEDUCCION => self::deduccion($codigo),
            self::GRUPO_RETENCION => self::retencion($codigo),
            self::GRUPO_AJUSTE => self::ajuste($codigo),
            default => '',
        };
    }

    /**
     * @param  array<int, string>  $tabla
     */
    private static function etiqueta(array $tabla, ?int $codigo): string
    {
        if ($codigo === null) {
            return '';
        }

        return $tabla[$codigo] ?? '';
    }
}
