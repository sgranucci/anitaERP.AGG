<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ejes del lienzo de cobranzas. El listado operativo no se reemplaza.
 */
final class CobranzaListadoVisual
{
    public const RECURSO = 'caja.cobranza';

    /**
     * @return array<string, array{column: string, type: string, label: string, attr: string}>
     */
    public static function campos(): array
    {
        return [
            'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa', 'attr' => 'nombreempresa'],
            'nombrecliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente', 'attr' => 'nombrecliente'],
            'estado' => ['column' => 'cobranza.estado', 'type' => 'texto', 'label' => 'Estado', 'attr' => 'estado'],
            'moneda' => ['column' => 'moneda.abreviatura', 'type' => 'texto', 'label' => 'Moneda', 'attr' => 'moneda'],
            'monto' => ['column' => 'cobranza.monto', 'type' => 'decimal', 'label' => 'Monto', 'attr' => 'monto'],
        ];
    }

    /**
     * @return list<array{key: string, column: string, label: string}>
     */
    public static function medidas(): array
    {
        return [
            ['key' => 'monto', 'column' => 'cobranza.monto', 'label' => 'Monto'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $qbe
     */
    public static function aplicarQbe(Builder $query, ?array $qbe): void
    {
        if (! is_array($qbe) || ! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }

        ListadoQbeSupport::aplicar($query, $qbe, function ($q, array $criterio): void {
            $campo = (string) $criterio['campo'];
            $meta = self::campos()[$campo] ?? null;
            if ($meta === null) {
                return;
            }
            $columna = (string) $meta['column'];
            if (($meta['type'] ?? '') === 'decimal') {
                $q->where($columna, '=', $criterio['valor']);

                return;
            }
            $q->where(SqlDialectSupport::castTexto($columna), '=', (string) $criterio['valor']);
        });
    }
}
