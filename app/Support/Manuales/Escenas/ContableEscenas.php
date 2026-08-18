<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class ContableEscenas
{
    public static function todas(): array
    {
        $sb = ['Cierre período', 'Agenda', 'Aperturas', 'Histórico', 'Mayor'];

        return [
            'cierre_agenda' => [
                'archivo' => 'cierre-agenda.png',
                'modulo' => 'Contable',
                'pantalla' => 'Agenda',
                'card_titulo' => 'Cierre de período — agenda del mes',
                'breadcrumb' => 'Contable › Cierre de período › Agenda',
                'sidebar' => $sb,
                'filtros' => ['Mes: Agosto 2026', 'Empresa: Biyemas'],
                'columnas' => ['Módulo', 'Estado', 'Programado', 'Cerrado', 'Pendientes'],
                'filas' => [
                    ['Ventas', 'Abierto', '31/08/2026', '—', '2'],
                    ['Compras', 'Programado', '31/08/2026', '—', '0'],
                    ['Stock', 'Abierto', '31/08/2026', '—', '1'],
                    ['Caja', 'Cerrado', '15/08/2026', '15/08/2026', '0'],
                    ['Sueldos', 'Abierto', '31/08/2026', '—', '0'],
                ],
                'tools' => ['Programar todos', 'Cerrar todos', 'Aplicar pendientes'],
            ],
            'cierre_herramientas' => [
                'archivo' => 'cierre-herramientas.png',
                'modulo' => 'Contable',
                'pantalla' => 'Agenda',
                'card_titulo' => 'Barra de herramientas de la agenda',
                'breadcrumb' => 'Contable › Cierre de período',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Programar todos fija fecha de cierre; Cerrar todos ejecuta el bloqueo; Aplicar pendientes reintenta fallidos.', 'tipo' => 'info'],
                ],
                'tools' => ['Programar todos', 'Cerrar todos', 'Aplicar pendientes', 'Exportar'],
                'columnas' => ['Acción', 'Efecto', 'Requiere'],
                'filas' => [
                    ['Programar todos', 'Agenda fecha por módulo', 'Permiso programar'],
                    ['Cerrar todos', 'Bloquea carga del período', 'Sin pendientes críticos'],
                    ['Aplicar pendientes', 'Reprocesa cierres fallidos', 'Diagnóstico OK'],
                ],
            ],
            'cierre_programar_todos' => [
                'archivo' => 'cierre-programar-todos.png',
                'modulo' => 'Contable',
                'pantalla' => 'Agenda',
                'card_titulo' => 'Agenda agosto 2026',
                'breadcrumb' => 'Contable › Cierre de período',
                'sidebar' => $sb,
                'modal' => [
                    'titulo' => 'Programar todos los módulos',
                    'texto' => 'Se programará el cierre para la fecha indicada en todos los módulos abiertos.',
                    'columnas' => ['Campo', 'Valor'],
                    'filas' => [
                        ['Fecha de cierre', '31/08/2026'],
                        ['Módulos incluidos', 'Ventas, Compras, Stock, Sueldos'],
                        ['Excluidos', 'Caja (ya cerrado)'],
                    ],
                    'botones' => [
                        ['texto' => 'Programar', 'estilo' => 'primary'],
                        ['texto' => 'Cancelar', 'estilo' => 'outline'],
                    ],
                ],
            ],
            'cierre_historico' => [
                'archivo' => 'cierre-historico.png',
                'modulo' => 'Contable',
                'pantalla' => 'Histórico',
                'card_titulo' => 'Histórico de cierres',
                'breadcrumb' => 'Contable › Cierre › Histórico',
                'sidebar' => $sb,
                'filtros' => ['Año: 2026', 'Empresa: Biyemas'],
                'columnas' => ['Período', 'Módulo', 'Cerrado el', 'Usuario', 'Resultado'],
                'filas' => [
                    ['07/2026', 'Ventas', '05/08/2026', 'conta01', 'OK'],
                    ['07/2026', 'Compras', '05/08/2026', 'conta01', 'OK'],
                    ['07/2026', 'Stock', '06/08/2026', 'conta02', 'OK c/avisos'],
                    ['06/2026', 'Caja', '03/07/2026', 'caja01', 'OK'],
                ],
            ],
            'apertura_listado' => [
                'archivo' => 'apertura-listado.png',
                'modulo' => 'Contable',
                'pantalla' => 'Aperturas',
                'card_titulo' => 'Aperturas programadas',
                'breadcrumb' => 'Contable › Apertura de período',
                'sidebar' => $sb,
                'columnas' => ['Módulo', 'Período', 'Programada', 'Estado', 'Solicitante'],
                'filas' => [
                    ['Compras', '08/2026', '01/09/2026 08:00', 'Programada', 'conta01'],
                    ['Ventas', '08/2026', '01/09/2026 08:00', 'Programada', 'conta01'],
                    ['Stock', '07/2026', '—', 'Ejecutada', 'conta02'],
                ],
                'botones' => [['texto' => 'Nueva apertura', 'estilo' => 'primary']],
            ],
        ];
    }
}
