<?php

namespace App\Support\Sueldos\Ganancias;

/**
 * Tablas oficiales AFIP ene-dic 2026 (Art. 94 y Art. 30) tomadas de los PDF
 * de escalas / deducciones personales. Los valores son acumulados al mes de pago.
 */
class GananciasTablas2026Seed
{
    /**
     * Tramos Art. 94: [mes => [[desde, hasta|null, fijo, alicuota, excedente], ...]]
     *
     * @return array<int, list<array{0: float, 1: ?float, 2: float, 3: float, 4: float}>>
     */
    public static function tramosArt94(): array
    {
        return [
            1 => [
                [0, 166669.17, 0, 5, 0],
                [166669.17, 333338.35, 8333.46, 9, 166669.17],
                [333338.35, 500007.52, 23333.68, 12, 333338.35],
                [500007.52, 750011.28, 43333.99, 15, 500007.52],
                [750011.28, 1500022.57, 80834.55, 19, 750011.28],
                [1500022.57, 2250033.85, 223336.69, 23, 1500022.57],
                [2250033.85, 3375050.77, 395839.29, 27, 2250033.85],
                [3375050.77, 5062576.16, 699593.86, 31, 3375050.77],
                [5062576.16, null, 1222726.73, 35, 5062576.16],
            ],
            2 => [
                [0, 333338.35, 0, 5, 0],
                [333338.35, 666676.69, 16666.92, 9, 333338.35],
                [666676.69, 1000015.04, 46667.37, 12, 666676.69],
                [1000015.04, 1500022.57, 86667.97, 15, 1000015.04],
                [1500022.57, 3000045.13, 161669.10, 19, 1500022.57],
                [3000045.13, 4500067.70, 446673.39, 23, 3000045.13],
                [4500067.70, 6750101.55, 791678.58, 27, 4500067.70],
                [6750101.55, 10125152.33, 1399187.72, 31, 6750101.55],
                [10125152.33, null, 2445453.46, 35, 10125152.33],
            ],
            3 => [
                [0, 500007.52, 0, 5, 0],
                [500007.52, 1000015.04, 25000.38, 9, 500007.52],
                [1000015.04, 1500022.56, 70001.05, 12, 1000015.04],
                [1500022.56, 2250033.85, 130001.96, 15, 1500022.56],
                [2250033.85, 4500067.70, 242503.65, 19, 2250033.85],
                [4500067.70, 6750101.55, 670010.08, 23, 4500067.70],
                [6750101.55, 10125152.32, 1187517.87, 27, 6750101.55],
                [10125152.32, 15187728.49, 2098781.57, 31, 10125152.32],
                [15187728.49, null, 3668180.19, 35, 15187728.49],
            ],
            4 => [
                [0, 666676.70, 0, 5, 0],
                [666676.70, 1333353.39, 33333.83, 9, 666676.70],
                [1333353.39, 2000030.09, 93334.74, 12, 1333353.39],
                [2000030.09, 3000045.13, 173335.94, 15, 2000030.09],
                [3000045.13, 6000090.27, 323338.20, 19, 3000045.13],
                [6000090.27, 9000135.40, 893346.77, 23, 6000090.27],
                [9000135.40, 13500203.10, 1583357.15, 27, 9000135.40],
                [13500203.10, 20250304.65, 2798375.43, 31, 13500203.10],
                [20250304.65, null, 4890906.91, 35, 20250304.65],
            ],
            5 => [
                [0, 833345.87, 0, 5, 0],
                [833345.87, 1666691.74, 41667.29, 9, 833345.87],
                [1666691.74, 2500037.61, 116668.42, 12, 1666691.74],
                [2500037.61, 3750056.42, 216669.93, 15, 2500037.61],
                [3750056.42, 7500112.83, 404172.75, 19, 3750056.42],
                [7500112.83, 11250169.25, 1116683.47, 23, 7500112.83],
                [11250169.25, 16875253.87, 1979196.44, 27, 11250169.25],
                [16875253.87, 25312880.81, 3497969.29, 31, 16875253.87],
                [25312880.81, null, 6113633.64, 35, 25312880.81],
            ],
            6 => [
                [0, 1000015.04, 0, 5, 0],
                [1000015.04, 2000030.08, 50000.75, 9, 1000015.04],
                [2000030.08, 3000045.13, 140002.11, 12, 2000030.08],
                [3000045.13, 4500067.70, 260003.91, 15, 3000045.13],
                [4500067.70, 9000135.40, 485007.30, 19, 4500067.70],
                [9000135.40, 13500203.10, 1340020.16, 23, 9000135.40],
                [13500203.10, 20250304.65, 2375035.73, 27, 13500203.10],
                [20250304.65, 30375456.98, 4197563.15, 31, 20250304.65],
                [30375456.98, null, 7336360.37, 35, 30375456.98],
            ],
            7 => [
                [0, 1166684.22, 0, 5, 0],
                [1166684.22, 2333368.43, 58334.21, 9, 1166684.22],
                [2333368.43, 3500052.65, 163335.79, 12, 2333368.43],
                [3500052.65, 5250078.98, 303337.90, 15, 3500052.65],
                [5250078.98, 10500157.97, 565841.85, 19, 5250078.98],
                [10500157.97, 15750236.95, 1563356.85, 23, 10500157.97],
                [15750236.95, 23625355.42, 2770875.02, 27, 15750236.95],
                [23625355.42, 35438033.14, 4897157.01, 31, 23625355.42],
                [35438033.14, null, 8559087.10, 35, 35438033.14],
            ],
            8 => [
                [0, 1333353.39, 0, 5, 0],
                [1333353.39, 2666706.78, 66667.67, 9, 1333353.39],
                [2666706.78, 4000060.17, 186669.47, 12, 2666706.78],
                [4000060.17, 6000090.27, 346671.88, 15, 4000060.17],
                [6000090.27, 12000180.53, 646676.40, 19, 6000090.27],
                [12000180.53, 18000270.80, 1786693.55, 23, 12000180.53],
                [18000270.80, 27000406.20, 3166714.31, 27, 18000270.80],
                [27000406.20, 40500609.30, 5596750.87, 31, 27000406.20],
                [40500609.30, null, 9781813.83, 35, 40500609.30],
            ],
            9 => [
                [0, 1500022.57, 0, 5, 0],
                [1500022.57, 3000045.13, 75001.13, 9, 1500022.57],
                [3000045.13, 4500067.69, 210003.16, 12, 3000045.13],
                [4500067.69, 6750101.55, 390005.87, 15, 4500067.69],
                [6750101.55, 13500203.10, 727510.95, 19, 6750101.55],
                [13500203.10, 20250304.65, 2010030.24, 23, 13500203.10],
                [20250304.65, 30375456.97, 3562553.60, 27, 20250304.65],
                [30375456.97, 45563185.47, 6296344.72, 31, 30375456.97],
                [45563185.47, null, 11004540.56, 35, 45563185.47],
            ],
            10 => [
                [0, 1666691.74, 0, 5, 0],
                [1666691.74, 3333383.47, 83334.59, 9, 1666691.74],
                [3333383.47, 5000075.22, 233336.84, 12, 3333383.47],
                [5000075.22, 7500112.83, 433339.85, 15, 5000075.22],
                [7500112.83, 15000225.67, 808345.49, 19, 7500112.83],
                [15000225.67, 22500338.50, 2233366.93, 23, 15000225.67],
                [22500338.50, 33750507.75, 3958392.88, 27, 22500338.50],
                [33750507.75, 50625761.63, 6995938.58, 31, 33750507.75],
                [50625761.63, null, 12227267.29, 35, 50625761.63],
            ],
            11 => [
                [0, 1833360.92, 0, 5, 0],
                [1833360.92, 3666721.82, 91668.05, 9, 1833360.92],
                [3666721.82, 5500082.74, 256670.53, 12, 3666721.82],
                [5500082.74, 8250124.12, 476673.84, 15, 5500082.74],
                [8250124.12, 16500248.23, 889180.04, 19, 8250124.12],
                [16500248.23, 24750372.35, 2456703.63, 23, 16500248.23],
                [24750372.35, 37125558.52, 4354232.17, 27, 24750372.35],
                [37125558.52, 55688337.79, 7695532.44, 31, 37125558.52],
                [55688337.79, null, 13449994.01, 35, 55688337.79],
            ],
            12 => [
                [0, 2000030.09, 0, 5, 0],
                [2000030.09, 4000060.17, 100001.50, 9, 2000030.09],
                [4000060.17, 6000090.26, 280004.21, 12, 4000060.17],
                [6000090.26, 9000135.40, 520007.82, 15, 6000090.26],
                [9000135.40, 18000270.80, 970014.59, 19, 9000135.40],
                [18000270.80, 27000406.20, 2680040.32, 23, 18000270.80],
                [27000406.20, 40500609.30, 4750071.46, 27, 27000406.20],
                [40500609.30, 60750913.96, 8395126.30, 31, 40500609.30],
                [60750913.96, null, 14672720.74, 35, 60750913.96],
            ],
        ];
    }

    /**
     * Deducciones Art. 30 acumuladas por mes 2026.
     * Claves: GNI, CONYUGE, HIJO, HIJO_INCAP, DE_ESP1, DE_ESP1_NP, DE_ESP2, TOPE_FAMILIAR
     *
     * @return array<string, array<int, float>>
     */
    public static function deduccionesArt30(): array
    {
        return [
            'GNI' => [
                1 => 429316.88, 2 => 858633.75, 3 => 1287950.63, 4 => 1717267.50,
                5 => 2146584.38, 6 => 2575901.25, 7 => 3005218.13, 8 => 3434535.00,
                9 => 3863851.88, 10 => 4293168.75, 11 => 4722485.63, 12 => 5151802.50,
            ],
            'TOPE_FAMILIAR' => [
                1 => 429316.88, 2 => 858633.75, 3 => 1287950.63, 4 => 1717267.50,
                5 => 2146584.38, 6 => 2575901.25, 7 => 3005218.13, 8 => 3434535.00,
                9 => 3863851.88, 10 => 4293168.75, 11 => 4722485.63, 12 => 5151802.50,
            ],
            'CONYUGE' => [
                1 => 404330.39, 2 => 808660.78, 3 => 1212991.17, 4 => 1617321.55,
                5 => 2021651.94, 6 => 2425982.33, 7 => 2830312.72, 8 => 3234643.11,
                9 => 3638973.50, 10 => 4043303.89, 11 => 4447634.28, 12 => 4851964.66,
            ],
            'HIJO' => [
                1 => 203905.29, 2 => 407810.58, 3 => 611715.87, 4 => 815621.16,
                5 => 1019526.45, 6 => 1223431.74, 7 => 1427337.03, 8 => 1631242.32,
                9 => 1835147.61, 10 => 2039052.90, 11 => 2242958.19, 12 => 2446863.48,
            ],
            'HIJO_INCAP' => [
                1 => 407810.58, 2 => 815621.16, 3 => 1223431.74, 4 => 1631242.32,
                5 => 2039052.90, 6 => 2446863.48, 7 => 2854674.06, 8 => 3262484.64,
                9 => 3670295.22, 10 => 4078105.80, 11 => 4485916.38, 12 => 4893726.96,
            ],
            'DE_ESP1' => [
                1 => 1502609.06, 2 => 3005218.13, 3 => 4507827.19, 4 => 6010436.25,
                5 => 7513045.32, 6 => 9015654.38, 7 => 10518263.44, 8 => 12020872.51,
                9 => 13523481.57, 10 => 15026090.63, 11 => 16528699.70, 12 => 18031308.76,
            ],
            'DE_ESP1_NP' => [
                1 => 1717267.50, 2 => 3434535.00, 3 => 5151802.50, 4 => 6869070.00,
                5 => 8586337.51, 6 => 10303605.01, 7 => 12020872.51, 8 => 13738140.01,
                9 => 15455407.51, 10 => 17172675.01, 11 => 18889942.51, 12 => 20607210.01,
            ],
            'DE_ESP2' => [
                1 => 2060721.00, 2 => 4121442.00, 3 => 6182163.01, 4 => 8242884.01,
                5 => 10303605.01, 6 => 12364326.01, 7 => 14425047.01, 8 => 16485768.01,
                9 => 18546489.02, 10 => 20607210.02, 11 => 22667931.02, 12 => 24728652.02,
            ],
        ];
    }

    /**
     * Plan de lineas por formula (equivalente al CSV / concgan, sin ifs por descripcion).
     *
     * @return list<array{codigo: string, descripcion: string, orden: int, origen: string, formula: ?string, deduccion_codigo: ?string}>
     */
    public static function planLineas(): array
    {
        return [
            // Entradas (desde liquidacion / movimiento / inyeccion de prueba)
            ['codigo' => 'SUJETO_APORTES', 'descripcion' => 'Sujeto a aportes', 'orden' => 10, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'PREMIO_DESEMP', 'descripcion' => 'Premio por desempeño', 'orden' => 20, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'FALLO_CAJA', 'descripcion' => 'Fallo de caja', 'orden' => 30, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'SUBTOTAL', 'descripcion' => 'Subtotal', 'orden' => 40, 'origen' => 'formula', 'formula' => 'linea("SUJETO_APORTES") + linea("PREMIO_DESEMP") + linea("FALLO_CAJA")', 'deduccion_codigo' => null],
            ['codigo' => 'INGRESO_OE', 'descripcion' => 'Ingreso otra empresa', 'orden' => 50, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'INGRESO_OE_CONC', 'descripcion' => 'Ingreso otra empresa conc.', 'orden' => 60, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'AGUINALDO_PAGO', 'descripcion' => 'Aguinaldo (pago)', 'orden' => 70, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'AGUINALDO_PRORR', 'descripcion' => 'Aguinaldo prorrateado', 'orden' => 80, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'AGUINALDO_2', 'descripcion' => 'Aguinaldo 2do semestre', 'orden' => 90, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'GRATIF_SUJETA', 'descripcion' => 'Gratificación sujeta', 'orden' => 100, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],

            ['codigo' => 'GAN_BRUTA_MES', 'descripcion' => 'Ganancia bruta mensual', 'orden' => 110, 'origen' => 'formula',
                'formula' => 'linea("SUBTOTAL") + linea("INGRESO_OE") + linea("INGRESO_OE_CONC") + linea("AGUINALDO_PAGO") + linea("AGUINALDO_PRORR") + linea("AGUINALDO_2") + linea("GRATIF_SUJETA")',
                'deduccion_codigo' => null],

            ['codigo' => 'APORTE_RNSS', 'descripcion' => 'Aportes a RNSS', 'orden' => 120, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'LEY_19032', 'descripcion' => 'Ley 19032', 'orden' => 130, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'OBRA_SOCIAL', 'descripcion' => 'Obra social', 'orden' => 140, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],

            ['codigo' => 'GAN_NETA_I', 'descripcion' => 'Ganancia neta I', 'orden' => 150, 'origen' => 'formula',
                'formula' => 'linea("GAN_BRUTA_MES") + linea("APORTE_RNSS") + linea("LEY_19032") + linea("OBRA_SOCIAL")',
                'deduccion_codigo' => null],
            ['codigo' => 'GAN_NETA_II', 'descripcion' => 'Ganancia neta II', 'orden' => 160, 'origen' => 'formula',
                'formula' => 'linea("GAN_NETA_I")', 'deduccion_codigo' => null],
            ['codigo' => 'GAN_NETA_ACUM_II', 'descripcion' => 'Ganancia neta acum. II', 'orden' => 170, 'origen' => 'formula',
                'formula' => 'linea_acum("GAN_NETA_II")', 'deduccion_codigo' => null],

            ['codigo' => 'GAN_NETA_III', 'descripcion' => 'Ganancia neta III', 'orden' => 180, 'origen' => 'formula',
                'formula' => 'linea("GAN_NETA_II")', 'deduccion_codigo' => null],
            ['codigo' => 'GAN_NETA_ACUM_III', 'descripcion' => 'Ganancia neta acum. III', 'orden' => 190, 'origen' => 'formula',
                'formula' => 'linea_acum("GAN_NETA_III")', 'deduccion_codigo' => null],

            // Deducciones generales (SIRADIG / movimiento; signo negativo en valor cargado)
            ['codigo' => 'GASTOS_INDUMENTARIA', 'descripcion' => 'Gastos indumentaria', 'orden' => 200, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'GASTOS_EDUCATIVOS', 'descripcion' => 'Gastos educativos', 'orden' => 210, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'HONORARIOS_MEDICOS', 'descripcion' => 'Honorarios asist. médica', 'orden' => 220, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'ALQUILERES', 'descripcion' => 'Alquileres', 'orden' => 230, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'INTERESES_HIPOT', 'descripcion' => 'Intereses hipotecarios', 'orden' => 240, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'DONACIONES', 'descripcion' => 'Donaciones', 'orden' => 250, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],
            ['codigo' => 'CUOTA_SINDICAL', 'descripcion' => 'Cuota sindical', 'orden' => 260, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],

            ['codigo' => 'GAN_NETA_IV', 'descripcion' => 'Ganancia neta IV', 'orden' => 270, 'origen' => 'formula',
                'formula' => 'linea("GAN_NETA_III") + linea("GASTOS_INDUMENTARIA") + linea("GASTOS_EDUCATIVOS") + linea("HONORARIOS_MEDICOS") + linea("ALQUILERES") + linea("INTERESES_HIPOT") + linea("DONACIONES") + linea("CUOTA_SINDICAL")',
                'deduccion_codigo' => null],
            ['codigo' => 'GAN_NETA_ACUM_IV', 'descripcion' => 'Ganancia neta acum. IV', 'orden' => 280, 'origen' => 'formula',
                'formula' => 'linea_acum("GAN_NETA_IV")', 'deduccion_codigo' => null],

            // Art. 30 (valores acumulados oficiales; cantidad de hijos vía param/familia)
            ['codigo' => 'GNI', 'descripcion' => 'Ganancia no imponible', 'orden' => 290, 'origen' => 'deduccion_art30', 'formula' => '-deduccion_art30("GNI")', 'deduccion_codigo' => 'GNI'],
            ['codigo' => 'DEDUC_ESPECIAL', 'descripcion' => 'Deducción especial (art.30 c-2)', 'orden' => 300, 'origen' => 'deduccion_art30', 'formula' => '-deduccion_art30("DE_ESP2")', 'deduccion_codigo' => 'DE_ESP2'],
            ['codigo' => 'CONYUGE', 'descripcion' => 'Cónyuge', 'orden' => 310, 'origen' => 'formula', 'formula' => '-deduccion_art30("CONYUGE") * cantidad("CONYUGE")', 'deduccion_codigo' => 'CONYUGE'],
            ['codigo' => 'HIJOS', 'descripcion' => 'Hijos', 'orden' => 320, 'origen' => 'formula', 'formula' => '-deduccion_art30("HIJO") * cantidad("HIJOS")', 'deduccion_codigo' => 'HIJO'],
            ['codigo' => 'HIJOS_50', 'descripcion' => 'Hijos al 50%', 'orden' => 330, 'origen' => 'formula', 'formula' => '-deduccion_art30("HIJO") * 0.5 * cantidad("HIJOS_50")', 'deduccion_codigo' => 'HIJO'],
            ['codigo' => 'HIJO_INCAP', 'descripcion' => 'Hijo incapacitado', 'orden' => 340, 'origen' => 'formula', 'formula' => '-deduccion_art30("HIJO_INCAP") * cantidad("HIJO_INCAP")', 'deduccion_codigo' => 'HIJO_INCAP'],

            ['codigo' => 'DEDUC_A_DESCONTAR', 'descripcion' => 'Deducción a descontar', 'orden' => 350, 'origen' => 'formula',
                'formula' => 'linea("GNI") + linea("DEDUC_ESPECIAL") + linea("CONYUGE") + linea("HIJOS") + linea("HIJOS_50") + linea("HIJO_INCAP")',
                'deduccion_codigo' => null],

            ['codigo' => 'GAN_SUJETA', 'descripcion' => 'Ganancia neta sujeta a impuesto', 'orden' => 360, 'origen' => 'formula',
                'formula' => 'max(0, linea("GAN_NETA_ACUM_IV") + linea("DEDUC_A_DESCONTAR"))',
                'deduccion_codigo' => null],

            ['codigo' => 'RET_4TA', 'descripcion' => 'Retención 4ta categoría (escala)', 'orden' => 370, 'origen' => 'formula',
                'formula' => 'escala_art94(linea("GAN_SUJETA"))', 'deduccion_codigo' => null],
            // Criterio AFIP: impuesto determinado (escala) menos lo ya retenido en el año.
            ['codigo' => 'RET_MES_ANT', 'descripcion' => 'Retenciones previas del año', 'orden' => 380, 'origen' => 'formula',
                'formula' => '-linea_acum_ant("RET_GANANCIAS")', 'deduccion_codigo' => null],
            ['codigo' => 'RET_OE', 'descripcion' => 'Ret. ganancias otra empresa', 'orden' => 390, 'origen' => 'entrada', 'formula' => null, 'deduccion_codigo' => null],

            ['codigo' => 'RET_GANANCIAS', 'descripcion' => 'Retención ganancias del mes', 'orden' => 400, 'origen' => 'formula',
                'formula' => 'linea("RET_4TA") + linea("RET_MES_ANT") + linea("RET_OE")',
                'deduccion_codigo' => null],
            ['codigo' => 'RET_GANANCIAS_ACUM', 'descripcion' => 'Retención ganancias acumulada', 'orden' => 410, 'origen' => 'formula',
                'formula' => 'linea_acum("RET_GANANCIAS")', 'deduccion_codigo' => null],
        ];
    }
}
