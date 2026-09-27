<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Cheque;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoQbeSupport;

/**
 * Catálogo del workbench de cheques (grilla, QBE, cortes).
 */
final class ChequeListadoColumnas
{
    public const RECURSO = 'caja.cheque';

    /** @var list<array{key: string, column: string, label: string}> */
    public const MEDIDAS = [
        ['key' => 'monto', 'column' => 'cheque.monto', 'label' => 'Monto'],
    ];

    /**
     * @var array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}>
     */
    public const COLUMNAS = [
        'id' => ['label' => 'ID', 'default' => true, 'type' => 'entero', 'source' => 'cheque.id', 'attr' => 'id', 'group' => 'identificacion'],
        'numerocheque' => ['label' => 'Número', 'default' => true, 'type' => 'texto', 'source' => 'cheque.numerocheque', 'attr' => 'numerocheque', 'group' => 'identificacion'],
        'nro_interno_anita' => ['label' => 'Int.', 'default' => true, 'type' => 'entero', 'source' => 'cheque.nro_interno_anita', 'attr' => 'nro_interno_anita', 'group' => 'identificacion'],
        'origen' => ['label' => 'Origen', 'default' => true, 'type' => 'texto', 'source' => 'cheque.origen', 'attr' => 'origen', 'group' => 'clasificacion'],
        'tipo' => ['label' => 'Tipo', 'default' => true, 'type' => 'texto', 'source' => 'cheque.negociable', 'attr' => 'tipo', 'group' => 'clasificacion'],
        'estado' => ['label' => 'Estado', 'default' => true, 'type' => 'texto', 'source' => 'cheque.estado', 'attr' => 'estado', 'group' => 'clasificacion'],
        'fechaemision' => ['label' => 'Emisión', 'default' => true, 'type' => 'fecha', 'source' => 'cheque.fechaemision', 'attr' => 'fechaemision', 'group' => 'fechas'],
        'fechapago' => ['label' => 'Pago', 'default' => true, 'type' => 'fecha', 'source' => 'cheque.fechapago', 'attr' => 'fechapago', 'group' => 'fechas'],
        'banco_cta' => ['label' => 'Banco / Cta', 'default' => true, 'type' => 'texto', 'source' => 'banco.nombre', 'attr' => 'banco_cta', 'group' => 'banco'],
        'empresa' => ['label' => 'Empresa', 'default' => true, 'type' => 'texto', 'source' => 'empresa.nombre', 'attr' => 'empresa', 'group' => 'banco'],
        'monto' => ['label' => 'Monto', 'default' => true, 'type' => 'decimal', 'source' => 'cheque.monto', 'attr' => 'monto', 'group' => 'importe'],
        'moneda' => ['label' => 'Mon', 'default' => true, 'type' => 'texto', 'source' => 'moneda.abreviatura', 'attr' => 'moneda', 'group' => 'importe'],
        'beneficiario' => ['label' => 'Beneficiario', 'default' => true, 'type' => 'texto', 'source' => 'cheque.entregado', 'attr' => 'beneficiario', 'group' => 'beneficiario'],
        'cliente' => ['label' => 'Cliente', 'default' => false, 'type' => 'texto', 'source' => 'cliente.nombre', 'attr' => 'cliente', 'group' => 'beneficiario'],
    ];

    /** @return array<string, array{label: string, default: bool, type: string, source: string, attr: string, group: string}> */
    public static function catalogoActivo(): array
    {
        return self::COLUMNAS;
    }

    /** @return list<string> */
    public static function defaultsVisibles(): array
    {
        $keys = [];
        foreach (self::COLUMNAS as $key => $meta) {
            if ($meta['default']) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function normalizarVisibles(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (isset(self::COLUMNAS[$key]) && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
        }

        return $out !== [] ? $out : self::defaultsVisibles();
    }

    public static function valorCelda(object $data, string $key): string
    {
        return match ($key) {
            'origen' => self::etiquetaEnum(Cheque::$enumOrigen, (string) ($data->origen ?? '')),
            'tipo' => strtoupper(trim((string) ($data->negociable ?? $data->tipo ?? ''))) === 'E' ? 'e-cheq' : 'Físico',
            'estado' => self::etiquetaEnum(Cheque::$enumEstado, (string) ($data->estado ?? '')),
            'fechaemision' => ListadoQbeSupport::formatearFecha($data->fechaemision ?? ''),
            'fechapago' => ListadoQbeSupport::formatearFecha($data->fechapago ?? ''),
            'banco_cta' => (string) (($data->origen ?? '') === 'E'
                ? ($data->cuentacajas->nombre ?? $data->banco_cta ?? '')
                : ($data->bancos->nombre ?? $data->banco_cta ?? '')),
            'empresa' => (string) ($data->empresas->nombre ?? $data->empresa ?? ''),
            'monto' => number_format((float) ($data->monto ?? 0), 2, ',', '.'),
            'moneda' => (string) ($data->monedas->abreviatura ?? $data->moneda ?? ''),
            'beneficiario' => (string) ($data->entregado ?? $data->anombrede ?? ''),
            'cliente' => (string) ($data->clientes->nombre ?? $data->cliente ?? ''),
            default => (string) ($data->{$key} ?? ''),
        };
    }

    /**
     * @return array{select: list<string>, groupBy: list<string>}|null
     */
    public static function sqlAgrupacion(string $key): ?array
    {
        if ($key === 'banco_cta') {
            $expr = "CASE WHEN cheque.origen = 'E' THEN IFNULL(cuentacaja.nombre,'') ELSE IFNULL(banco.nombre,'') END";

            return [
                'select' => [$expr.' as banco_cta'],
                'groupBy' => [$expr],
            ];
        }

        if ($key === 'tipo') {
            return ListadoCortesSupport::sqlAgrupacionDefault('tipo', [
                'column' => 'cheque.negociable',
                'attr' => 'tipo',
                'type' => 'texto',
            ]);
        }

        if ($key === 'moneda') {
            return ListadoCortesSupport::sqlAgrupacionDefault('moneda', [
                'column' => 'moneda.abreviatura',
                'attr' => 'moneda',
                'type' => 'texto',
            ]);
        }

        $meta = self::COLUMNAS[$key] ?? null;
        if ($meta === null) {
            return null;
        }

        return ListadoCortesSupport::sqlAgrupacionDefault($key, [
            'column' => $meta['source'],
            'attr' => $meta['attr'],
            'type' => $meta['type'],
        ]);
    }

    /**
     * @param  list<array{valor?: string, nombre?: string}>  $enum
     */
    private static function etiquetaEnum(array $enum, string $valor): string
    {
        foreach ($enum as $item) {
            if ((string) ($item['valor'] ?? '') === $valor) {
                return (string) ($item['nombre'] ?? $valor);
            }
        }

        return $valor;
    }
}
