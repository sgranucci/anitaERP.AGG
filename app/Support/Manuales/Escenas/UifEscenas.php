<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class UifEscenas
{
    public static function todas(): array
    {
        $sb = ['Clientes UIF', 'Premios', 'Informe mensual', 'Congelados', 'Conciliación Wigos'];

        return [
            'clientes_listado' => [
                'archivo' => 'clientes-listado.png',
                'modulo' => 'UIF',
                'pantalla' => 'Clientes',
                'card_titulo' => 'Listado de clientes UIF',
                'breadcrumb' => 'UIF › Clientes',
                'sidebar' => $sb,
                'filtros' => ['Documento: 20.123', 'Estado: Activo', 'Empresa: Biyemas'],
                'tools' => ['Nuevo', 'PDF', 'Excel', 'CSV'],
                'columnas' => ['Doc.', 'Apellido y nombre', 'CUIT/CUIL', 'PEP', 'Estado'],
                'filas' => [
                    ['20123456', 'García, Ana María', '27-20123456-3', 'No', 'Activo'],
                    ['25999887', 'López, Carlos', '20-25999887-1', 'Sí', 'Activo'],
                    ['30111222', 'Ruiz, Elena', '27-30111222-8', 'No', 'Congelado'],
                ],
            ],
            'clientes_alta' => [
                'archivo' => 'clientes-alta.png',
                'modulo' => 'UIF',
                'pantalla' => 'Clientes',
                'card_titulo' => 'Alta / edición de cliente UIF',
                'card_color' => 'primary',
                'breadcrumb' => 'UIF › Clientes › Editar',
                'sidebar' => $sb,
                'tabs' => ['Personales', 'Cumplimiento', 'Documentación'],
                'campos' => [
                    ['label' => 'Documento', 'valor' => '20.123.456'],
                    ['label' => 'Apellido y nombre', 'valor' => 'García, Ana María'],
                    ['label' => 'CUIT/CUIL', 'valor' => '27-20123456-3'],
                    ['label' => 'Domicilio', 'valor' => 'Av. Ejemplo 1234'],
                    ['label' => 'PEP', 'valor' => 'No'],
                    ['label' => 'Sujeto obligado', 'valor' => 'No'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary']],
            ],
            'premios_listado' => [
                'archivo' => 'premios-listado.png',
                'modulo' => 'UIF',
                'pantalla' => 'Premios',
                'card_titulo' => 'Listado de premios UIF',
                'breadcrumb' => 'UIF › Premios',
                'sidebar' => $sb,
                'filtros' => ['Mes: 08/2026', 'Importe ≥ umbral'],
                'columnas' => ['Fecha', 'Cliente', 'Juego', 'Importe', 'Reportable'],
                'filas' => [
                    ['10/08/2026', 'García A.', 'Ruleta', '850.000', 'Sí'],
                    ['12/08/2026', 'López C.', 'Tragamonedas', '420.000', 'Sí'],
                    ['14/08/2026', 'Ruiz E.', 'Poker', '95.000', 'No'],
                ],
                'tools' => ['PDF', 'Excel'],
            ],
            'informe_consulta' => [
                'archivo' => 'informe-consulta.png',
                'modulo' => 'UIF',
                'pantalla' => 'Informe',
                'card_titulo' => 'Informe de datos por mes — consulta',
                'breadcrumb' => 'UIF › Informe mensual',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Período', 'valor' => 'Agosto 2026'],
                    ['label' => 'Umbral importe', 'valor' => '$ 300.000'],
                    ['label' => 'Incluir congelados', 'valor' => 'No'],
                ],
                'botones' => [['texto' => 'Consultar', 'estilo' => 'primary']],
            ],
            'informe_resultado' => [
                'archivo' => 'informe-resultado.png',
                'modulo' => 'UIF',
                'pantalla' => 'Informe',
                'card_titulo' => 'Resultado del informe — premios reportables',
                'breadcrumb' => 'UIF › Informe mensual › Resultado',
                'sidebar' => $sb,
                'tools' => ['Excel', 'PDF', 'XML'],
                'columnas' => ['Cliente', 'Doc.', 'Fecha premio', 'Importe', 'XML'],
                'filas' => [
                    ['García, Ana', '20123456', '10/08/2026', '850.000', 'Incluido'],
                    ['López, Carlos', '25999887', '12/08/2026', '420.000', 'Incluido'],
                ],
                'nota' => 'Exportar XML genera el archivo de presentación UIF del período.',
            ],
            'congelados_listado' => [
                'archivo' => 'congelados-listado.png',
                'modulo' => 'UIF',
                'pantalla' => 'Congelados',
                'card_titulo' => 'Clientes congelados UIF',
                'breadcrumb' => 'UIF › Congelados',
                'sidebar' => $sb,
                'columnas' => ['Doc.', 'Cliente', 'Motivo', 'Desde', 'Usuario'],
                'filas' => [
                    ['30111222', 'Ruiz, Elena', 'Orden judicial', '01/07/2026', 'uif01'],
                    ['28444555', 'Pérez, Juan', 'Revisión KYC', '20/07/2026', 'uif02'],
                ],
                'alertas' => [
                    ['texto' => 'Un cliente congelado no puede cobrar premios reportables hasta liberarlo.', 'tipo' => 'warning'],
                ],
            ],
            'conciliacion_wigos' => [
                'archivo' => 'conciliacion-wigos.png',
                'modulo' => 'UIF',
                'pantalla' => 'Conciliación',
                'card_titulo' => 'Conciliación Wigos UIF',
                'breadcrumb' => 'UIF › Conciliación Wigos',
                'sidebar' => $sb,
                'filtros' => ['Fecha: 15/08/2026', 'Sala: Principal'],
                'columnas' => ['Origen', 'Premios', 'Importe', 'Diferencia', 'Estado'],
                'filas' => [
                    ['Wigos', '42', '3.250.000', '0', 'OK'],
                    ['ERP UIF', '42', '3.250.000', '0', 'OK'],
                    ['Solo Wigos', '1', '15.000', '15.000', 'Revisar'],
                ],
                'botones' => [['texto' => 'Importar faltantes', 'estilo' => 'primary']],
            ],
        ];
    }
}
