<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;

final class ClienteDocumentoUnicoSupport
{
    public static function normalizarDigitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }

    /**
     * Si true (CLIENTE_PERMITIR_CUIT_DUPLICADO), el ABM permite guardar un CUIT/documento
     * ya usado por otro cliente. Sigue avisando en pantalla; no bloquea ni falla validación.
     */
    public static function permiteCuitDuplicado(): bool
    {
        return filter_var(config('cliente.permitir_cuit_duplicado', false), FILTER_VALIDATE_BOOLEAN);
    }

    public static function findOtroCliente(?string $numerodocumento, ?int $excluirClienteId = null): ?Cliente
    {
        $digitos = self::normalizarDigitos($numerodocumento);
        if ($digitos === '') {
            return null;
        }

        $query = Cliente::query()
            ->whereRaw(
                "REPLACE(REPLACE(REPLACE(numerodocumento, '-', ''), '.', ''), ' ', '') = ?",
                [$digitos]
            );

        if ($excluirClienteId !== null && $excluirClienteId > 0) {
            $query->where('id', '!=', $excluirClienteId);
        }

        return $query->first();
    }

    public static function mensajeDuplicado(Cliente $cliente): string
    {
        $codigo = trim((string) ($cliente->codigo ?? ''));
        $nombre = trim((string) ($cliente->nombre ?? ''));

        return 'El CUIT/documento ya está cargado en el cliente '
            .($codigo !== '' ? $codigo.' - ' : '')
            .$nombre
            .' (ID '.$cliente->id.').';
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,numerodocumento:string,mensaje:string,url_consulta:?string}|null
     */
    public static function payloadDuplicado(Cliente $cliente, bool $incluirUrlConsulta = true): array
    {
        $urlConsulta = null;
        if ($incluirUrlConsulta && (can('editar-clientes', false) || can('actualizar-clientes', false))) {
            $urlConsulta = route('editar_cliente', [
                'id' => $cliente->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]);
        }

        return [
            'id' => (int) $cliente->id,
            'codigo' => trim((string) ($cliente->codigo ?? '')),
            'nombre' => trim((string) ($cliente->nombre ?? '')),
            'numerodocumento' => trim((string) ($cliente->numerodocumento ?? '')),
            'mensaje' => self::mensajeDuplicado($cliente),
            'url_consulta' => $urlConsulta,
        ];
    }
}
