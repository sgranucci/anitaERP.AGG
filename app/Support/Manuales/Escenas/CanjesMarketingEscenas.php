<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class CanjesMarketingEscenas
{
    public static function todas(): array
    {
        $sb = ['Clientes VIP', 'Facturador canjes', 'Listado marketing'];

        return [
            'cliente_vip_listado' => [
                'archivo' => 'cliente-vip-listado.png',
                'modulo' => 'Canjes',
                'pantalla' => 'Clientes VIP',
                'card_titulo' => 'Listado de clientes VIP',
                'breadcrumb' => 'Ventas › Canjes marketing › Clientes VIP',
                'sidebar' => $sb,
                'filtros' => ['Texto: vip', 'Estado: Activo'],
                'tools' => ['Nuevo', 'PDF', 'Excel'],
                'columnas' => ['Código', 'Nombre', 'Documento', 'Saldo canjes', 'Estado'],
                'filas' => [
                    ['VIP-102', 'Club Ejemplo', '30-11112222-3', '12', 'Activo'],
                    ['VIP-108', 'Hotel Centro', '30-33334444-5', '4', 'Activo'],
                    ['VIP-115', 'Agencia Tours', '30-55556666-7', '0', 'Suspendido'],
                ],
            ],
            'cliente_vip_crear' => [
                'archivo' => 'cliente-vip-crear.png',
                'modulo' => 'Canjes',
                'pantalla' => 'Clientes VIP',
                'card_titulo' => 'Alta de cliente VIP',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Canjes › Clientes VIP › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Código', 'valor' => 'VIP-120'],
                    ['label' => 'Nombre', 'valor' => 'Restaurante Norte'],
                    ['label' => 'Documento', 'valor' => '30-77778888-9'],
                    ['label' => 'Email', 'valor' => 'vip@ejemplo.com'],
                    ['label' => 'Cupo mensual', 'valor' => '30 canjes'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary']],
            ],
            'facturador_login' => [
                'archivo' => 'facturador-canjes-login.png',
                'modulo' => 'Canjes',
                'pantalla' => 'Facturador',
                'tipo' => 'login',
                'card_titulo' => 'Facturador canjes — ingreso de mozo',
            ],
            'facturador_pantalla' => [
                'archivo' => 'facturador-canjes-pantalla.png',
                'modulo' => 'Canjes',
                'pantalla' => 'Facturador',
                'card_titulo' => 'Facturador canjes — carga',
                'breadcrumb' => 'Ventas › Canjes › Facturador',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Cliente VIP', 'valor' => 'VIP-102 Club Ejemplo'],
                    ['label' => 'Mozo', 'valor' => 'jperez'],
                    ['label' => 'PV', 'valor' => '03 Salón'],
                    ['label' => 'Total canje', 'valor' => '$ 0 (cortesía)'],
                ],
                'columnas' => ['Artículo', 'Cant.', 'Precio lista', 'Canje'],
                'filas' => [
                    ['Menú ejecutivo', '2', '8.500', 'Sí'],
                    ['Bebida sin alcohol', '2', '2.400', 'Sí'],
                ],
                'botones' => [['texto' => 'Confirmar canje', 'estilo' => 'success']],
            ],
            'listado_marketing' => [
                'archivo' => 'listado-marketing.png',
                'modulo' => 'Canjes',
                'pantalla' => 'Listado',
                'card_titulo' => 'Listado de canjes marketing',
                'breadcrumb' => 'Ventas › Canjes › Listado marketing',
                'sidebar' => $sb,
                'filtros' => ['Desde: 01/08/2026', 'VIP: Todos', 'PV: 03'],
                'tools' => ['PDF', 'Excel', 'CSV'],
                'columnas' => ['Fecha', 'VIP', 'Artículos', 'Importe lista', 'Mozo'],
                'filas' => [
                    ['15/08/2026', 'VIP-102', '2', '21.800', 'jperez'],
                    ['14/08/2026', 'VIP-108', '1', '8.500', 'agomez'],
                    ['13/08/2026', 'VIP-102', '3', '28.100', 'jperez'],
                ],
            ],
        ];
    }
}
