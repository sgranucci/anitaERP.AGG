<?php

namespace App\Support\Ai;

/**
 * Asistentes por rol (estilo Joule Assistants): filtran herramientas, ejemplos y brief.
 */
final class AiConsultaOperativaAsistenteRolSupport
{
    public const AUTO = 'auto';

    public const COMPRAS = 'compras';

    public const TESORERIA = 'tesoreria';

    public const CONTABLE = 'contable';

    public const VENTAS = 'ventas';

    public const STOCK = 'stock';

    public const SUELDOS = 'sueldos';

    public const TICKETS = 'tickets';

    /** @return array<string, string> */
    public static function etiquetas(): array
    {
        return [
            self::AUTO => 'Automático (detecta por permisos)',
            self::COMPRAS => 'Compras / AP',
            self::TESORERIA => 'Tesorería / caja',
            self::CONTABLE => 'Contable',
            self::VENTAS => 'Ventas / AR',
            self::STOCK => 'Stock / logística',
            self::SUELDOS => 'Sueldos',
            self::TICKETS => 'Tickets',
        ];
    }

    public static function normalizar(?string $rol): string
    {
        $rol = strtolower(trim((string) $rol));
        if ($rol === '' || $rol === self::AUTO) {
            return self::AUTO;
        }

        return array_key_exists($rol, self::etiquetas()) ? $rol : self::AUTO;
    }

    /**
     * Sugiere un rol según permisos del usuario (ganador único; empate → AUTO).
     */
    public static function detectarDesdePermisos(): string
    {
        $score = [
            self::COMPRAS => (int) (
                can('listar-ordencompra', false)
                || can('listar-precarga-proveedores', false)
                || can('listar-comprobante-proveedor', false)
                || can('listar-kpi-compras', false)
                || can('listar-propuesta-pago', false)
            ),
            self::TESORERIA => (int) (
                can('listar-cheque', false) || can('listar-ingresos-egresos-caja', false)
            ),
            self::CONTABLE => (int) (
                can('consulta-ia-contable', false)
                && (can('listar-mayor-plano-cuenta', false) || can('listar-asiento', false))
            ),
            self::VENTAS => (int) (
                can('listar-clientes', false) || can('listar-cuentacorriente-cliente', false)
            ),
            self::STOCK => (int) (
                can('listar-articulos', false)
                || can('listar-recepcion-proveedor', false)
                || can('listar-transferencias-pendientes', false)
            ),
            self::SUELDOS => (int) (
                can('listar-empleado-sueldos', false) || can('listar-liquidacion-sueldos', false)
            ),
            self::TICKETS => (int) (
                can('listar-ticket', false) || can('listar-administracion-ticket', false)
            ),
        ];

        arsort($score);
        $keys = array_keys($score);
        $top = $keys[0] ?? self::AUTO;
        $topScore = (int) ($score[$top] ?? 0);
        if ($topScore <= 0) {
            return self::AUTO;
        }
        $segundo = (int) ($score[$keys[1] ?? ''] ?? 0);
        // Empate entre módulos → seguir en AUTO (todos los grupos permitidos)
        if ($segundo === $topScore) {
            return self::AUTO;
        }

        return $top;
    }

    /**
     * Rol efectivo: si prefs=auto, intenta detectar; si no hay ganador único, AUTO.
     */
    public static function efectivo(?string $rolPrefs): string
    {
        $rol = self::normalizar($rolPrefs);
        if ($rol !== self::AUTO) {
            return $rol;
        }
        $detectado = self::detectarDesdePermisos();

        return $detectado !== self::AUTO ? $detectado : self::AUTO;
    }

    /**
     * Grupos de herramientas del FAB asociados al rol.
     *
     * @return list<string>|null null = todos
     */
    public static function gruposPermitidos(string $rol): ?array
    {
        return match (self::normalizar($rol)) {
            self::COMPRAS => ['compras', 'ayuda'],
            self::TESORERIA => ['tesoreria', 'compras', 'ayuda'],
            self::CONTABLE => ['contable', 'ayuda'],
            self::VENTAS => ['ventas', 'ayuda'],
            self::STOCK => ['stock', 'ayuda'],
            self::SUELDOS => ['sueldos', 'ayuda'],
            self::TICKETS => ['tickets', 'ayuda'],
            default => null,
        };
    }

    /**
     * Frases sugeridas al abrir el FAB según rol.
     *
     * @return list<string>
     */
    public static function ejemplos(string $rol): array
    {
        return match (self::normalizar($rol)) {
            self::COMPRAS => [
                'armar brief del día',
                'cash position',
                'aging de proveedores',
                'propuestas de pago',
                'OC vencidas sin recepción',
                'precargas pendientes',
                'recepciones en borrador',
            ],
            self::TESORERIA => [
                'armar brief del día',
                'aging de cheques',
                'cashflow de cheques 8 semanas',
                'cash position',
                'ingresos y egresos este mes',
                'cola HITL',
            ],
            self::CONTABLE => [
                'movimientos sin centro de costo este mes',
                'mayor cuenta caja y bancos este mes',
                'conciliacion bancaria cuenta de caja 300 mes pasado',
                'asiento 12500',
            ],
            self::VENTAS => [
                'aging de clientes',
                'top deudores',
                'saldo del cliente 10025 este mes',
                'diferencias del turno de gastronomia',
            ],
            self::STOCK => [
                'stock critico de insumos',
                'transferencias pendientes',
                'recepciones en borrador',
                'kardex del insumo muzarella este mes',
            ],
            self::SUELDOS => [
                'empleado legajo 1234',
                'liquidación 45',
                'liquidaciones este mes',
                'liquidaciones pendientes de cierre',
                'empleados ingresados este mes',
                'liquidaciones de noviembre 2026',
            ],
            self::TICKETS => [
                'resumen de tickets',
                'tickets abiertos',
                'tickets vencidos 7 días',
            ],
            default => AiConsultaOperativaRouterSupport::ejemplosPermitidos(),
        };
    }

    /**
     * ¿Incluir este bloque del brief según el rol?
     *
     * @param  array{titulo?: string, frase?: string}  $item
     */
    public static function briefIncluye(string $rol, array $item): bool
    {
        $rol = self::normalizar($rol);
        if ($rol === self::AUTO) {
            return true;
        }

        $titulo = mb_strtolower((string) ($item['titulo'] ?? ''));
        $frase = mb_strtolower((string) ($item['frase'] ?? ''));
        $hay = static fn (string ...$needles): bool => self::contieneAlguno($titulo.' '.$frase, $needles);

        return match ($rol) {
            self::COMPRAS => $hay('compra', 'oc ', 'propuesta', 'cash position', 'aging proveedor', 'ap ', 'hitl'),
            self::TESORERIA => $hay('cheque', 'tesorer', 'cash position', 'aging', 'hitl', 'propuesta'),
            self::CONTABLE => $hay('mayor', 'asiento', 'conciliacion', 'contable', 'hitl'),
            self::VENTAS => $hay('cliente', 'deudor', 'ar ', 'gastronom', 'venta'),
            self::STOCK => $hay('stock', 'insumo', 'recepcion', 'transferencia', 'tm '),
            self::SUELDOS => $hay('empleado', 'liquidacion', 'liquidaciones', 'sueldo', 'corrida'),
            self::TICKETS => $hay('ticket'),
            default => true,
        };
    }


    /**
     * @param  list<string>  $needles
     */
    private static function contieneAlguno(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }

        return false;
    }
}
