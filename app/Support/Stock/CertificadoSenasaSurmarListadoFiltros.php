<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CertificadoSenasaSurmarListadoFiltros
{
    public const CAMPOS = [
        'numero' => ['label' => 'Número', 'tipo' => 'entero'],
        'estado' => ['label' => 'Estado', 'tipo' => 'texto'],
        'cod_remito' => ['label' => 'Cód. remito AFIP', 'tipo' => 'texto'],
        'fecha' => ['label' => 'Fecha', 'tipo' => 'texto'],
    ];

    public const OPERADORES_TEXTO = ['contiene', 'igual', 'empieza'];

    public const OPERADORES_ENTERO = ['igual', 'mayor', 'menor'];

    public const COLUMNAS_COINCIDENCIA_FLEXIBLE = ['cod_remito'];

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'filtro_valor' => '',
            'filtro_campo' => '',
            'filtro_operador' => 'contiene',
            'filtro_busqueda_rapida' => false,
            'estado' => '',
        ];
    }

    /** @return array<string, mixed> */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $base = self::filtrosVacios();
        $base['filtro_valor'] = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $base['filtro_campo'] = (string) $request->input('filtro_campo', '');
        $base['filtro_operador'] = (string) $request->input('filtro_operador', 'contiene');
        $base['filtro_busqueda_rapida'] = (string) $request->input('filtro_busqueda_rapida', '') === '1';
        $base['estado'] = (string) $request->input('estado', '');

        return $base;
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            return true;
        }

        return trim((string) ($filtros['estado'] ?? '')) !== '';
    }

    /** @param array<string, mixed> $filtros @return array<string, string> */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        if (trim((string) ($filtros['filtro_valor'] ?? '')) !== '') {
            $out['filtro_valor'] = (string) $filtros['filtro_valor'];
        }
        if (! empty($filtros['filtro_campo'])) {
            $out['filtro_campo'] = (string) $filtros['filtro_campo'];
        }
        if (! empty($filtros['filtro_operador']) && $filtros['filtro_operador'] !== 'contiene') {
            $out['filtro_operador'] = (string) $filtros['filtro_operador'];
        }
        if (! empty($filtros['filtro_busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = '1';
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $out['estado'] = (string) $filtros['estado'];
        }

        return $out;
    }

    /** @param array<string, mixed> $filtros */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('certificado_senasa_surmar.estado', $estado);
        }

        $valor = trim((string) ($filtros['filtro_valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $campo = (string) ($filtros['filtro_campo'] ?? '');
        $operador = (string) ($filtros['filtro_operador'] ?? 'contiene');
        $rapida = ! empty($filtros['filtro_busqueda_rapida']) || $campo === '';

        if ($rapida) {
            $query->where(function (Builder $q) use ($valor) {
                if (ctype_digit($valor)) {
                    $q->orWhere('certificado_senasa_surmar.numero', (int) $valor);
                }
                $q->orWhere('certificado_senasa_surmar.cod_remito', 'like', '%'.$valor.'%');
                $q->orWhere('certificado_senasa_surmar.estado', 'like', '%'.$valor.'%');
                CoincidenciaFlexibleTexto::aplicar($q, 'certificado_senasa_surmar.cod_remito', $valor);
            });

            return;
        }

        if ($campo === 'numero' && is_numeric($valor)) {
            $n = (int) $valor;
            match ($operador) {
                'mayor' => $query->where('certificado_senasa_surmar.numero', '>', $n),
                'menor' => $query->where('certificado_senasa_surmar.numero', '<', $n),
                default => $query->where('certificado_senasa_surmar.numero', $n),
            };

            return;
        }

        $col = match ($campo) {
            'estado' => 'certificado_senasa_surmar.estado',
            'cod_remito' => 'certificado_senasa_surmar.cod_remito',
            'fecha' => 'certificado_senasa_surmar.fecha',
            default => 'certificado_senasa_surmar.cod_remito',
        };

        match ($operador) {
            'igual' => $query->where($col, $valor),
            'empieza' => $query->where($col, 'like', $valor.'%'),
            default => $query->where(function (Builder $q) use ($col, $valor) {
                $q->where($col, 'like', '%'.$valor.'%');
                if (in_array($col, ['certificado_senasa_surmar.cod_remito'], true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor);
                }
            }),
        };
    }

    /** @return list<array{campo: string, label: string, tipo: string}> */
    public static function camposFiltro(): array
    {
        $out = [];
        foreach (self::CAMPOS as $campo => $meta) {
            $out[] = [
                'campo' => $campo,
                'label' => $meta['label'],
                'tipo' => $meta['tipo'],
            ];
        }

        return $out;
    }
}
