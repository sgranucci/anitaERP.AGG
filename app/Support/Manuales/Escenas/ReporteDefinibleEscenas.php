<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/**
 * Pantallas wireframe de reportes definibles → PNG.
 *
 * @return array<string, array<string, mixed>>
 */
final class ReporteDefinibleEscenas
{
    public static function todas(): array
    {
        $sb = ['Catálogo', 'Diseñar', 'Ejecutar', 'Publicar', 'Paridad'];

        return [
            'catalogo' => [
                'archivo' => 'pantalla-catalogo.png',
                'modulo' => 'Contable',
                'pantalla' => 'Catálogo',
                'card_titulo' => 'Catálogo de informes definibles',
                'breadcrumb' => 'Contable › Reportes definibles',
                'sidebar' => $sb,
                'filtros' => ['Texto: balance', 'Estado: Publicado'],
                'tools' => ['Nuevo informe', 'PDF', 'Excel'],
                'columnas' => ['Código', 'Nombre', 'Layout', 'Versión', 'Estado'],
                'filas' => [
                    ['BAL-01', 'Balance general', 'Columnas std', '3', 'Publicado'],
                    ['EERR-01', 'Estado de resultados', 'Mensual', '2', 'Publicado'],
                    ['CF-01', 'Cash flow gerencial', 'Trimestral', '1', 'Borrador'],
                ],
            ],
            'disenar_estructura' => [
                'archivo' => 'pantalla-disenar.png',
                'modulo' => 'Contable',
                'pantalla' => 'Diseñar',
                'card_titulo' => 'Diseñar: árbol de rubros y cuentas',
                'card_color' => 'primary',
                'breadcrumb' => 'Contable › Reportes › BAL-01 › Diseñar',
                'sidebar' => $sb,
                'tabs' => ['Estructura', 'Layouts', 'Consolidación', 'Notas'],
                'columnas' => ['Rubro', 'Tipo', 'Cuentas / fórmula', 'Signo'],
                'filas' => [
                    ['Activo corriente', 'Grupo', '—', '+'],
                    ['  Caja y bancos', 'Cuentas', '111xxx', '+'],
                    ['  Créditos', 'Cuentas', '113xxx', '+'],
                    ['Pasivo corriente', 'Grupo', '—', '-'],
                    ['Patrimonio neto', 'Fórmula', 'Activo − Pasivo', '='],
                ],
                'botones' => [['texto' => 'Guardar estructura', 'estilo' => 'primary']],
            ],
            'ejecutar' => [
                'archivo' => 'pantalla-ejecutar.png',
                'modulo' => 'Contable',
                'pantalla' => 'Ejecutar',
                'card_titulo' => 'Ejecutar informe — filtros y resultado',
                'breadcrumb' => 'Contable › Reportes › BAL-01 › Ejecutar',
                'sidebar' => $sb,
                'filtros' => ['Período: 07/2026', 'Empresa: Biyemas', 'Fuente: ERP'],
                'tools' => ['Ejecutar', 'Publicar', 'PDF', 'Excel'],
                'columnas' => ['Rubro', 'Saldo período', 'Saldo anterior', 'Variación'],
                'filas' => [
                    ['Caja y bancos', '12.450.000', '11.200.000', '+11%'],
                    ['Créditos', '28.100.000', '26.800.000', '+5%'],
                    ['Pasivo corriente', '19.300.000', '18.900.000', '+2%'],
                    ['Patrimonio neto', '45.200.000', '43.100.000', '+5%'],
                ],
                'nota' => 'Drill-down disponible desde cada rubro hacia mayor y asientos.',
            ],
        ];
    }
}
