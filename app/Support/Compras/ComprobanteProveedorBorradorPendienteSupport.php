<?php

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Digest de facturas de proveedor en BORRADOR (aún no contabilizadas).
 */
final class ComprobanteProveedorBorradorPendienteSupport
{
    /**
     * @return array{
     *     fecha: string,
     *     cantidad: int,
     *     facturas: list<array<string, mixed>>,
     *     facturas_mail: list<array<string, mixed>>
     * }
     */
    public static function recopilar(?int $empresaId = null, ?int $limite = null): array
    {
        $limite = max(1, min(500, (int) ($limite ?? config('compras.factura_borrador_aviso.limite_mail', 80))));
        $hoy = Carbon::today();

        $filas = self::consultar($empresaId);
        $items = [];
        foreach ($filas as $comp) {
            $items[] = self::mapear($comp, $hoy);
        }

        return [
            'fecha' => $hoy->format('d/m/Y'),
            'cantidad' => count($items),
            'facturas' => $items,
            'facturas_mail' => array_slice($items, 0, $limite),
        ];
    }

    /**
     * @param  array<string, mixed>  $digest
     */
    public static function hayPendientes(array $digest): bool
    {
        return ((int) ($digest['cantidad'] ?? 0)) > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public static function formatearLista(array $items, int $totalReal): string
    {
        if ($items === []) {
            return '(ninguna)';
        }

        $lineas = [];
        foreach ($items as $item) {
            $lineas[] = sprintf(
                '#%d | %s | %s | %s | IVA %s | $ %s | %s | Cargó %s',
                (int) ($item['id'] ?? 0),
                (string) ($item['empresa'] ?? '—'),
                (string) ($item['comprobante'] ?? '—'),
                (string) ($item['proveedor'] ?? '—'),
                (string) ($item['fecha_iva'] ?? '—'),
                (string) ($item['total'] ?? '0,00'),
                (string) ($item['antiguedad'] ?? '—'),
                (string) ($item['usuario'] ?? '—')
            );
        }

        $texto = implode("\n", $lineas);
        $mostrados = count($items);
        if ($totalReal > $mostrados) {
            $texto .= "\n… y ".($totalReal - $mostrados).' más';
        }

        return $texto;
    }

    /**
     * @return Collection<int, Comprobante_Proveedor>
     */
    private static function consultar(?int $empresaId): Collection
    {
        $query = Comprobante_Proveedor::query()
            ->with([
                'empresas:id,nombre',
                'proveedores:id,codigo,nombre',
                'tipotransaccion_compras:id,abreviatura',
                'creousuarios:id,nombre,usuario',
            ])
            ->where('estado', ComprobanteProveedorEstados::BORRADOR)
            ->orderBy('fechaiva')
            ->orderBy('id');

        if ($empresaId !== null && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->get();
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapear(Comprobante_Proveedor $comp, Carbon $hoy): array
    {
        $abrev = (string) ($comp->tipotransaccion_compras?->abreviatura ?? 'FAC');
        $comprobante = trim(sprintf(
            '%s %s %s-%s',
            $abrev,
            (string) ($comp->letra ?? ''),
            str_pad((string) ((int) ($comp->sucursal ?? 0)), 4, '0', STR_PAD_LEFT),
            (string) ($comp->numerocomprobante ?? '')
        ));

        $alta = $comp->created_at ? Carbon::parse($comp->created_at)->startOfDay() : $hoy->copy();
        $dias = max(0, $alta->diffInDays($hoy));
        $antiguedad = $dias === 0
            ? 'hoy'
            : ($dias === 1 ? '1 día' : $dias.' días');

        return [
            'id' => (int) $comp->id,
            'empresa_id' => (int) ($comp->empresa_id ?? 0),
            'empresa' => (string) ($comp->empresas?->nombre ?? '—'),
            'proveedor' => (string) ($comp->proveedores?->nombre ?? '—'),
            'comprobante' => $comprobante,
            'fecha_iva' => $comp->fechaiva ? $comp->fechaiva->format('d/m/Y') : '—',
            'total' => number_format((float) ($comp->total ?? 0), 2, ',', '.'),
            'usuario' => (string) ($comp->creousuarios?->nombre ?? $comp->creousuarios?->usuario ?? '—'),
            'antiguedad' => $antiguedad,
            'dias' => $dias,
        ];
    }
}
