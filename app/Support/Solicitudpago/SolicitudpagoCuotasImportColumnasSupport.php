<?php

namespace App\Support\Solicitudpago;

use App\Imports\Solicitudpago\SolicitudpagoCuotasImportLecturaCruda;
use App\Support\Stock\PrecioImportColumnasSupport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Import flexible de cuotas SP (alias de columnas estilo PrecioImportColumnasSupport).
 */
final class SolicitudpagoCuotasImportColumnasSupport
{
    public const MAX_FILAS_BUSQUEDA_ENCABEZADO = 15;

    /** @var list<string> */
    public const ALIAS_NRO = ['nro', 'nro_cuota', 'numero', 'numero_cuota', 'cuota', 'nrocuota', 'n'];

    /** @var list<string> */
    public const ALIAS_VTO = [
        'vto', 'vencimiento', 'fecha_vto', 'fecha_vencimiento', 'fecha', 'fvto', 'vence',
    ];

    /** @var list<string> */
    public const ALIAS_MONTO = ['monto', 'importe', 'valor', 'importe_cuota', 'monto_cuota'];

    /**
     * @return list<array{nro_cuota: int, fecha_vencimiento: string, monto: float}>
     */
    public static function leerFilas(UploadedFile|string $archivo): array
    {
        $path = $archivo instanceof UploadedFile
            ? ($archivo->getRealPath() ?: $archivo->path())
            : $archivo;
        if ($path === false || $path === '') {
            return [];
        }

        $sheets = Excel::toArray(new SolicitudpagoCuotasImportLecturaCruda(), $path);
        $matriz = $sheets[0] ?? [];
        if ($matriz === []) {
            return [];
        }

        $headerRowIdx = self::encontrarFilaEncabezado($matriz);
        if ($headerRowIdx === null) {
            return [];
        }

        $headers = array_map(
            fn ($h) => PrecioImportColumnasSupport::normalizarNombreColumna((string) $h),
            $matriz[$headerRowIdx]
        );

        $colNro = self::resolverColumna($headers, self::ALIAS_NRO);
        $colVto = self::resolverColumna($headers, self::ALIAS_VTO);
        $colMonto = self::resolverColumna($headers, self::ALIAS_MONTO);
        if ($colVto === null || $colMonto === null) {
            throw new \RuntimeException('El Excel debe tener columnas de vencimiento y monto (acepta alias).');
        }

        $filas = [];
        $nroAuto = 1;
        for ($i = $headerRowIdx + 1; $i < count($matriz); $i++) {
            $row = $matriz[$i];
            $vtoRaw = trim((string) ($row[$colVto] ?? ''));
            $montoRaw = trim((string) ($row[$colMonto] ?? ''));
            if ($vtoRaw === '' && $montoRaw === '') {
                continue;
            }
            $fecha = self::parseFecha($vtoRaw);
            $monto = self::parseMonto($montoRaw);
            if ($fecha === null || $monto == 0.0) {
                continue;
            }
            $nro = $colNro !== null ? (int) ($row[$colNro] ?? 0) : 0;
            if ($nro <= 0) {
                $nro = $nroAuto;
            }
            $nroAuto = max($nroAuto, $nro) + 1;
            $filas[] = [
                'nro_cuota' => $nro,
                'fecha_vencimiento' => $fecha,
                'monto' => $monto,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<list<mixed>>  $matriz
     */
    private static function encontrarFilaEncabezado(array $matriz): ?int
    {
        $limite = min(count($matriz), self::MAX_FILAS_BUSQUEDA_ENCABEZADO);
        for ($i = 0; $i < $limite; $i++) {
            $norm = array_map(
                fn ($h) => PrecioImportColumnasSupport::normalizarNombreColumna((string) $h),
                $matriz[$i]
            );
            $tieneVto = self::resolverColumna($norm, self::ALIAS_VTO) !== null;
            $tieneMonto = self::resolverColumna($norm, self::ALIAS_MONTO) !== null;
            if ($tieneVto && $tieneMonto) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $alias
     */
    private static function resolverColumna(array $headers, array $alias): ?int
    {
        foreach ($headers as $idx => $h) {
            if ($h !== '' && in_array($h, $alias, true)) {
                return (int) $idx;
            }
        }
        foreach ($headers as $idx => $h) {
            foreach ($alias as $a) {
                if ($h !== '' && (str_contains($h, $a) || str_contains($a, $h))) {
                    return (int) $idx;
                }
            }
        }

        return null;
    }

    private static function parseFecha(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (is_numeric($raw)) {
            try {
                $dt = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $raw);

                return $dt->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }
        $raw = str_replace('/', '-', $raw);
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseMonto(string $raw): float
    {
        $raw = trim(Str::of($raw)->replace(['$', ' '], '')->__toString());
        if ($raw === '') {
            return 0.0;
        }
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        return (float) $raw;
    }
}
