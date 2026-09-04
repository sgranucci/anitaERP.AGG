<?php

declare(strict_types=1);

namespace App\Support\Ayuda;

/**
 * Guías HTML interactivas (paso a paso) servidas solo autenticadas.
 * Archivos en docs/guias/ — no en public/.
 */
class GuiaPasoAPasoCatalogo
{
    /**
     * @return array<string, array{archivo: string, titulo: string, bajada: string, icono: string, modulo: string}>
     */
    public static function todas(): array
    {
        return [
            'ingreso-proveedores' => [
                'archivo' => 'ingreso-proveedores.html',
                'titulo' => 'Ingreso de proveedores',
                'bajada' => 'Cargar un ticket de visita: listado, alta y archivos asociados (ART, DNI, OC).',
                'icono' => 'fa-id-badge',
                'modulo' => 'Seguridad',
            ],
            'autorizacion-ingresos' => [
                'archivo' => 'autorizacion-ingresos.html',
                'titulo' => 'Autorización y control de ingresos',
                'bajada' => 'Pendientes de autorizar, detalle del ticket y registro de entró/salió en portería.',
                'icono' => 'fa-shield-alt',
                'modulo' => 'Seguridad',
            ],
            'bandeja-legajos' => [
                'archivo' => 'bandeja-legajos.html',
                'titulo' => 'Bandeja de legajos',
                'bajada' => 'Asignar COM a la factura y enviar el legajo a Cuentas a pagar o Gastronomía.',
                'icono' => 'fa-folder-open',
                'modulo' => 'Compras',
            ],
        ];
    }

    /**
     * @return array{archivo: string, titulo: string, bajada: string, icono: string, modulo: string}|null
     */
    public static function porSlug(string $slug): ?array
    {
        $slug = trim($slug);

        return self::todas()[$slug] ?? null;
    }

    public static function rutaArchivo(string $slug): ?string
    {
        $meta = self::porSlug($slug);
        if ($meta === null) {
            return null;
        }

        $path = base_path('docs/guias/'.$meta['archivo']);

        return is_file($path) ? $path : null;
    }
}
