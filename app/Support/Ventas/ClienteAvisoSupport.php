<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Aviso;
use Illuminate\Support\Collection;

/**
 * Avisos del cliente: qué mostrar o qué hacer al cargar un comprobante, dentro de una vigencia.
 */
final class ClienteAvisoSupport
{
    public const MODO_AVISO = 'aviso';

    public const MODO_ACCION = 'accion';

    public const ACCION_BLOQUEAR = 'bloquear';

    public const ACCION_CONFIRMAR = 'confirmar';

    public const DISPARADOR_PEDIDO = 'pedido';

    public const DISPARADOR_FACTURA = 'factura';

    public const DISPARADOR_RECIBO = 'recibo';

    public const DISPARADOR_BOLETA = 'boleta';

    public const DISPARADOR_REMITO = 'remito';

    /**
     * @return array<string, string>
     */
    public static function disparadores(): array
    {
        return [
            self::DISPARADOR_PEDIDO => 'Al cargar un pedido',
            self::DISPARADOR_FACTURA => 'Al cargar una factura',
            self::DISPARADOR_RECIBO => 'Al cargar un recibo',
            self::DISPARADOR_BOLETA => 'Al cargar una boleta',
            self::DISPARADOR_REMITO => 'Al cargar un remito',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function modos(): array
    {
        return [
            self::MODO_AVISO => 'Mostrar aviso',
            self::MODO_ACCION => 'Ejecutar acción',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function acciones(): array
    {
        return [
            self::ACCION_BLOQUEAR => 'Bloquear la carga',
            self::ACCION_CONFIRMAR => 'Pedir confirmación',
        ];
    }

    public static function etiquetaDisparador(?string $codigo): string
    {
        return self::disparadores()[$codigo] ?? (string) $codigo;
    }

    public static function etiquetaAccion(?string $codigo): string
    {
        return self::acciones()[$codigo] ?? (string) $codigo;
    }

    /**
     * Avisos vigentes de un cliente para un disparador en una fecha (hoy si no se indica).
     * Fecha hasta vacía = sin vencimiento. Fecha desde vacía = vigente desde siempre.
     *
     * @return Collection<int, Cliente_Aviso>
     */
    public static function vigentes(int $clienteId, string $disparador, ?string $fecha = null): Collection
    {
        $codigo = strtolower(trim($disparador));
        if ($clienteId <= 0 || ! isset(self::disparadores()[$codigo])) {
            return collect();
        }

        $dia = substr(trim((string) ($fecha ?? '')), 0, 10);
        if ($dia === '') {
            $dia = now()->toDateString();
        }

        return Cliente_Aviso::query()
            ->where('cliente_id', $clienteId)
            ->where('disparador', $codigo)
            ->where(function ($query) use ($dia) {
                $query->whereNull('desdefecha')
                    ->orWhere('desdefecha', '<=', $dia);
            })
            ->where(function ($query) use ($dia) {
                $query->whereNull('hastafecha')
                    ->orWhere('hastafecha', '>=', $dia);
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }
}
