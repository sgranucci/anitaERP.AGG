<?php

namespace App\Support\Ventas;

use Carbon\Carbon;

/**
 * Perfiles del batch legacy l-pedkilo (cron El Bierzo).
 */
final class KilosPedidosMailPerfiles
{
    public const MANANA_1 = 'manana_1';

    public const MANANA_2 = 'manana_2';

    public const TARDE_PARCIAL = 'tarde_parcial';

    public const TARDE_TOTAL = 'tarde_total';

    /**
     * @return array<string, array{
     *     codigo_aviso: string,
     *     nombre: string,
     *     hora: string,
     *     suma_dia: bool,
     *     reparto_desde: string,
     *     reparto_hasta: string,
     *     emails_default: list<string>
     * }>
     */
    public static function todos(): array
    {
        return [
            // En ERP, reparto_hasta=999999 NO es “todos”: KiloPedidoListadoFiltros lo
            // trata como rango abierto y filtra solo el desde. Vacío = sin filtro (todos).
            self::MANANA_1 => [
                'codigo_aviso' => 'kilos_pedidos_manana_1',
                'nombre' => 'Kilos pedidos mañana 06:50 (hoy, todos los repartos)',
                'hora' => '06:50',
                'suma_dia' => false,
                'reparto_desde' => '',
                'reparto_hasta' => '',
                'emails_default' => ['grupolistadokilos@elbierzo.com.ar'],
            ],
            self::MANANA_2 => [
                'codigo_aviso' => 'kilos_pedidos_manana_2',
                'nombre' => 'Kilos pedidos mañana 08:00 (hoy, todos los repartos)',
                'hora' => '08:00',
                'suma_dia' => false,
                'reparto_desde' => '',
                'reparto_hasta' => '',
                'emails_default' => ['grupolistadokilos@elbierzo.com.ar'],
            ],
            self::TARDE_PARCIAL => [
                'codigo_aviso' => 'kilos_pedidos_tarde_parcial',
                'nombre' => 'Kilos pedidos tarde parcial (mañana, repartos 1-101)',
                'hora' => '20:58',
                'suma_dia' => true,
                'reparto_desde' => '1',
                'reparto_hasta' => '101',
                'emails_default' => ['GrupoSocios@elbierzo.com.ar'],
            ],
            self::TARDE_TOTAL => [
                'codigo_aviso' => 'kilos_pedidos_tarde_total',
                'nombre' => 'Kilos pedidos tarde total (mañana, todos los repartos)',
                'hora' => '21:45',
                'suma_dia' => true,
                'reparto_desde' => '',
                'reparto_hasta' => '',
                'emails_default' => ['GrupoSocios@elbierzo.com.ar'],
            ],
        ];
    }

    /**
     * @return array{
     *     codigo_aviso: string,
     *     nombre: string,
     *     hora: string,
     *     suma_dia: bool,
     *     reparto_desde: string,
     *     reparto_hasta: string,
     *     emails_default: list<string>
     * }|null
     */
    public static function get(string $perfil): ?array
    {
        $perfil = strtolower(trim($perfil));

        return self::todos()[$perfil] ?? null;
    }

    /**
     * @param  array{suma_dia: bool, reparto_desde: string, reparto_hasta: string}  $perfil
     * @return array{fecha_desde: string, fecha_hasta: string, reparto_desde: string, reparto_hasta: string, tipolistado: string, estado: string}
     */
    public static function filtrosPara(array $perfil, ?Carbon $ahora = null, ?string $fechaForzada = null): array
    {
        $ahora = $ahora ?? Carbon::now();
        if ($fechaForzada !== null && trim($fechaForzada) !== '') {
            $fecha = Carbon::parse($fechaForzada)->toDateString();
        } else {
            $fecha = ! empty($perfil['suma_dia'])
                ? $ahora->copy()->addDay()->toDateString()
                : $ahora->toDateString();
        }

        return [
            'fecha_desde' => $fecha,
            'fecha_hasta' => $fecha,
            'reparto_desde' => (string) ($perfil['reparto_desde'] ?? ''),
            'reparto_hasta' => (string) ($perfil['reparto_hasta'] ?? ''),
            // l-pedkilo -b → REPARTO_AGRUP + TOTAL: total por artículo (sin abrir por reparto).
            'tipolistado' => 'ARTICULO',
            'estado' => 'TODO',
        ];
    }
}
