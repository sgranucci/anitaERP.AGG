<?php

declare(strict_types=1);

namespace App\Support\Stock;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros / campos QBE para Consulta Inteligente sobre transferencia_mercaderia.
 */
final class TransferenciaMercaderiaListadoFiltros
{
    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'transferencia_mercaderia.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'transferencia_mercaderia.codigo', 'type' => 'texto', 'label' => 'Número'],
        'fecha' => ['column' => 'transferencia_mercaderia.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'estado' => ['column' => 'transferencia_mercaderia.estado', 'type' => 'texto', 'label' => 'Estado'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'deposito_origen' => ['column' => 'dep_o.nombre', 'type' => 'texto', 'label' => 'Depósito origen'],
        'deposito_destino' => ['column' => 'dep_d.nombre', 'type' => 'texto', 'label' => 'Depósito destino'],
        'tipo' => ['column' => 'tts.nombre', 'type' => 'texto', 'label' => 'Tipo'],
        'usuario_origen' => ['column' => 'u_orig.nombre', 'type' => 'texto', 'label' => 'Usuario origen'],
        'lote' => ['column' => 'transferencia_mercaderia.lote', 'type' => 'texto', 'label' => 'Lote'],
        'observacion' => ['column' => 'transferencia_mercaderia.observacion', 'type' => 'texto', 'label' => 'Observación'],
    ];

    /**
     * @return array{filtro_valor: string, busqueda: string, qbe?: array<string, mixed>, filtro_modo?: string}
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $valor = trim((string) $request->input('filtro_valor', $request->input('busqueda', '')));

        return [
            'filtro_valor' => $valor,
            'busqueda' => $valor,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        $valor = trim((string) ($filtros['filtro_valor'] ?? $filtros['busqueda'] ?? ''));
        if ($valor !== '') {
            $out['filtro_valor'] = $valor;
        }
        if (! empty($filtros['qbe']) && is_array($filtros['qbe'])) {
            $out['qbe'] = $filtros['qbe'];
            $out['filtro_modo'] = 'qbe';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['filtro_valor'] ?? $filtros['busqueda'] ?? ''));
        if ($valor === '') {
            return;
        }
        $like = '%'.$valor.'%';
        $query->where(function (Builder $q) use ($like, $valor) {
            $q->where('transferencia_mercaderia.codigo', 'like', $like)
                ->orWhere('transferencia_mercaderia.estado', 'like', $like)
                ->orWhere('transferencia_mercaderia.observacion', 'like', $like)
                ->orWhere('empresa.nombre', 'like', $like)
                ->orWhere('dep_o.nombre', 'like', $like)
                ->orWhere('dep_d.nombre', 'like', $like)
                ->orWhere('tts.nombre', 'like', $like)
                ->orWhere('u_orig.nombre', 'like', $like);
            if (ctype_digit($valor)) {
                $q->orWhere('transferencia_mercaderia.id', (int) $valor)
                    ->orWhere('transferencia_mercaderia.lote', (int) $valor);
            }
        });
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposOrdenables(): array
    {
        return self::CAMPOS;
    }
}
