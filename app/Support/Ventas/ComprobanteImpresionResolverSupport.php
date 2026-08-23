<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\ComprobanteImpresionCopia;
use App\Models\Ventas\ComprobanteImpresionFormularioLinea;
use App\Models\Ventas\ComprobanteImpresionPrograma;
use App\Models\Ventas\ComprobanteImpresionRegla;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Remito;
use App\Models\Ventas\Venta;

final class ComprobanteImpresionResolverSupport
{
    /**
     * @return array{programa: ?ComprobanteImpresionPrograma, motivo: string, empresa_id: ?int, transporte_id: ?int, provincia_entrega_id: ?int}
     */
    public static function contextoDesdeVenta(Venta $venta): array
    {
        $venta->loadMissing(['puntoventas', 'transportes']);
        $empresaId = $venta->puntoventas->empresa_id ?? null;
        $transporteId = $venta->transporte_id ? (int) $venta->transporte_id : null;
        $provinciaId = self::provinciaEntregaId(
            $venta->cliente_entrega_id ? (int) $venta->cliente_entrega_id : null
        );

        return self::resolver($empresaId ? (int) $empresaId : null, $transporteId, $provinciaId);
    }

    /**
     * @return array{programa: ?ComprobanteImpresionPrograma, motivo: string, empresa_id: ?int, transporte_id: ?int, provincia_entrega_id: ?int}
     */
    public static function contextoDesdePedido(Pedido $pedido): array
    {
        $pedido->loadMissing(['transportes', 'vendedores']);
        $empresaId = $pedido->vendedores->empresa_id ?? null;
        $transporteId = $pedido->transporte_id ? (int) $pedido->transporte_id : null;
        $provinciaId = self::provinciaEntregaId(
            $pedido->cliente_entrega_id ? (int) $pedido->cliente_entrega_id : null
        );

        return self::resolver($empresaId ? (int) $empresaId : null, $transporteId, $provinciaId);
    }

    /**
     * @return array{programa: ?ComprobanteImpresionPrograma, motivo: string, empresa_id: ?int, transporte_id: ?int, provincia_entrega_id: ?int}
     */
    public static function contextoDesdeRemito(Remito $remito): array
    {
        $remito->loadMissing(['transportes', 'puntoventas']);
        $empresaId = $remito->puntoventas->empresa_id ?? null;
        $transporteId = $remito->transporte_id ? (int) $remito->transporte_id : null;
        $provinciaId = self::provinciaEntregaId(
            $remito->cliente_entrega_id ? (int) $remito->cliente_entrega_id : null
        );

        return self::resolver($empresaId ? (int) $empresaId : null, $transporteId, $provinciaId);
    }

    /**
     * @return array{programa: ?ComprobanteImpresionPrograma, motivo: string, empresa_id: ?int, transporte_id: ?int, provincia_entrega_id: ?int}
     */
    public static function resolver(?int $empresaId, ?int $transporteId, ?int $provinciaEntregaId): array
    {
        $reglas = ComprobanteImpresionRegla::query()
            ->with('programa.formularios.copias.salida')
            ->orderByDesc('prioridad')
            ->get()
            ->filter(fn (ComprobanteImpresionRegla $r) => self::programaAplicaAEmpresa($r->programa, $empresaId))
            ->values();

        $candidatos = [
            [ComprobanteImpresionReglaClave::PROVINCIA_ENTREGA, $provinciaEntregaId],
            [ComprobanteImpresionReglaClave::TRANSPORTE, $transporteId],
            [ComprobanteImpresionReglaClave::EMPRESA, $empresaId],
            [ComprobanteImpresionReglaClave::DEFAULT, null],
        ];

        foreach ($candidatos as [$clave, $valor]) {
            if ($clave !== ComprobanteImpresionReglaClave::DEFAULT && ($valor === null || $valor <= 0)) {
                continue;
            }
            $regla = $reglas
                ->filter(function (ComprobanteImpresionRegla $r) use ($clave, $valor) {
                    if ($r->clave !== $clave) {
                        return false;
                    }
                    if ($clave === ComprobanteImpresionReglaClave::DEFAULT) {
                        return true;
                    }

                    return (int) $r->valor_id === (int) $valor;
                })
                ->sortByDesc(fn (ComprobanteImpresionRegla $r) => $r->programa && $r->programa->empresa_id ? 1 : 0)
                ->first();
            if ($regla && $regla->programa) {
                return [
                    'programa' => $regla->programa,
                    'motivo' => ComprobanteImpresionReglaClave::etiquetas()[$clave].($valor ? ' #'.$valor : ''),
                    'empresa_id' => $empresaId,
                    'transporte_id' => $transporteId,
                    'provincia_entrega_id' => $provinciaEntregaId,
                ];
            }
        }

        $fallback = ComprobanteImpresionPrograma::query()
            ->with('formularios.copias.salida')
            ->where('codigo', 'DEFAULT')
            ->get()
            ->filter(fn (ComprobanteImpresionPrograma $p) => self::programaAplicaAEmpresa($p, $empresaId))
            ->sortByDesc(fn (ComprobanteImpresionPrograma $p) => $p->empresa_id ? 1 : 0)
            ->first();

        return [
            'programa' => $fallback,
            'motivo' => $fallback ? 'Programa DEFAULT (sin regla)' : 'Sin programa configurado',
            'empresa_id' => $empresaId,
            'transporte_id' => $transporteId,
            'provincia_entrega_id' => $provinciaEntregaId,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function pack(
        ComprobanteImpresionPrograma $programa,
        array $documentosDisponibles,
        string $modo = 'OPERATIVO',
        ?string $soloFormulario = null,
        bool $soloOriginal = false
    ): array {
        $lineas = [];
        foreach ($programa->formularios as $form) {
            if ($soloFormulario !== null && $form->formulario !== $soloFormulario) {
                continue;
            }
            if (empty($documentosDisponibles[$form->formulario])) {
                continue;
            }
            foreach ($form->copias as $copia) {
                if ($soloOriginal && ! self::esOriginal($copia)) {
                    continue;
                }
                if ($modo === 'CONSULTA' && $form->formulario === ComprobanteImpresionFormulario::FACTURA && ! self::esOriginal($copia)) {
                    continue;
                }
                $lineas[] = self::lineaPack($form, $copia, $documentosDisponibles[$form->formulario]);
            }
        }

        return $lineas;
    }

    /**
     * @param  array{id: int, codigo: string, fecha: string}  $documento
     * @return array<string, mixed>
     */
    private static function lineaPack(
        ComprobanteImpresionFormularioLinea $form,
        ComprobanteImpresionCopia $copia,
        array $documento
    ): array {
        $comando = $copia->salida->comando ?? '';
        $esArchivo = ComprobanteImpresionNasPathSupport::esSalidaArchivo($comando);
        $destino = $esArchivo
            ? ComprobanteImpresionNasPathSupport::destino($form->formulario, $documento['fecha'], $documento['codigo'])
            : null;

        return [
            'formulario' => $form->formulario,
            'formulario_etiqueta' => ComprobanteImpresionFormulario::etiquetas()[$form->formulario] ?? $form->formulario,
            'copia_id' => $copia->id,
            'copia_codigo' => $copia->codigo,
            'leyenda' => $copia->leyenda,
            'destinatario' => $copia->destinatario,
            'salida_id' => $copia->salida_id,
            'salida_nombre' => $copia->salida->nombre ?? 'Heredar usuario',
            'incluir_en_pdf_sesion' => (bool) $copia->incluir_en_pdf_sesion,
            'medio' => $esArchivo ? 'ARCHIVO' : 'IMPRESORA',
            'destino_path' => $destino,
            'documento_id' => $documento['id'],
            'documento_codigo' => $documento['codigo'],
            'documento_fecha' => $documento['fecha'],
        ];
    }

    private static function esOriginal(ComprobanteImpresionCopia $copia): bool
    {
        $codigo = strtoupper((string) $copia->codigo);
        $leyenda = strtoupper((string) $copia->leyenda);

        return $codigo === 'ORI' || $codigo === 'ORIGINAL' || $leyenda === 'ORIGINAL';
    }

    private static function programaAplicaAEmpresa(?ComprobanteImpresionPrograma $programa, ?int $empresaId): bool
    {
        if (! $programa) {
            return false;
        }
        $progEmp = $programa->empresa_id ? (int) $programa->empresa_id : null;
        if ($progEmp === null) {
            return true;
        }
        if ($empresaId === null || $empresaId <= 0) {
            return false;
        }

        return $progEmp === (int) $empresaId;
    }

    private static function provinciaEntregaId(?int $clienteEntregaId): ?int
    {
        if ($clienteEntregaId === null || $clienteEntregaId <= 0) {
            return null;
        }
        $id = Cliente_Entrega::query()->whereKey($clienteEntregaId)->value('provincia_id');

        return $id ? (int) $id : null;
    }
}
