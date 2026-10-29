<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
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

    /**
     * Contexto de la pantalla (cobranza, pedido, factura…) → disparador del aviso.
     */
    public static function disparadorDeContexto(string $contexto): ?string
    {
        return match (strtolower(trim($contexto))) {
            self::DISPARADOR_PEDIDO => self::DISPARADOR_PEDIDO,
            self::DISPARADOR_FACTURA, 'facturacion' => self::DISPARADOR_FACTURA,
            self::DISPARADOR_RECIBO, 'cobranza', 'cobro' => self::DISPARADOR_RECIBO,
            self::DISPARADOR_BOLETA, 'ot', 'ordentrabajo' => self::DISPARADOR_BOLETA,
            self::DISPARADOR_REMITO => self::DISPARADOR_REMITO,
            default => null,
        };
    }

    /**
     * Avisos vigentes de hoy, para que la pantalla los muestre al cargar el cliente.
     *
     * @return list<array{id: int, disparador: string, modo: string, accion: string, texto: string, mensaje: string}>
     */
    public static function payloadVigentes(?Cliente $cliente, ?string $fecha = null): array
    {
        if ($cliente === null) {
            return [];
        }

        $dia = substr(trim((string) ($fecha ?? '')), 0, 10);
        if ($dia === '') {
            $dia = now()->toDateString();
        }

        $avisos = $cliente->relationLoaded('cliente_avisos')
            ? $cliente->cliente_avisos
            : $cliente->cliente_avisos()->orderBy('orden')->orderBy('id')->get();

        $salida = [];
        foreach ($avisos as $aviso) {
            if (! $aviso instanceof Cliente_Aviso || ! self::estaVigente($aviso, $dia)) {
                continue;
            }
            $texto = trim((string) ($aviso->texto ?? ''));
            $accion = strtolower(trim((string) ($aviso->accion ?? '')));
            $mensaje = $texto !== '' ? $texto : self::etiquetaAccion($accion);
            if ($mensaje === '') {
                continue;
            }
            $salida[] = [
                'id' => (int) $aviso->id,
                'disparador' => (string) $aviso->disparador,
                'modo' => (string) $aviso->modo,
                'accion' => $accion,
                'texto' => $texto,
                'mensaje' => $mensaje,
            ];
        }

        return $salida;
    }

    public static function estaVigente(Cliente_Aviso $aviso, string $dia): bool
    {
        $desde = $aviso->desdefecha?->format('Y-m-d');
        $hasta = $aviso->hastafecha?->format('Y-m-d');
        if ($desde !== null && $desde !== '' && $desde > $dia) {
            return false;
        }
        if ($hasta !== null && $hasta !== '' && $hasta < $dia) {
            return false;
        }

        return true;
    }
}
