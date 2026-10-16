<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use App\Models\Compras\Concepto_Ivacompra;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ConceptoIvacompraFormulaSupport;
use Illuminate\Support\Collection;

/**
 * Anita Ferli no trae concc_tipo_conc: los 30 conceptos quedan en N y el Libro
 * IVA mandaba todo a «no gravado» (crédito fiscal 0). La fórmula con(COD)*alícuota
 * y la columna del libro viejo dicen qué es gravado, IVA, percepción o exento.
 */
final class LibroIvaDigitalComprasConceptoClasificacionSupport
{
    /** @var array<int, Concepto_Ivacompra>|null */
    private static ?array $porCodigo = null;

    public static function porCodigo(int $codigo): ?Concepto_Ivacompra
    {
        self::cargar();

        return self::$porCodigo[$codigo] ?? null;
    }

    /**
     * El renglón del comprobante sigue en N: usar el maestro ya inferido.
     * Si el tipo ya es G/I/E/…, se respeta (no pisa un maestro bien cargado).
     */
    public static function maestroInformado(Concepto_Ivacompra $concepto): Concepto_Ivacompra
    {
        $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
        if ($tipo !== '' && $tipo !== 'N') {
            return $concepto;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) ($concepto->codigo ?? ''));
        if ($codigo <= 0) {
            return $concepto;
        }

        return self::porCodigo($codigo) ?? $concepto;
    }

    /**
     * @return array{destino: string, tasa: float}
     */
    public static function clasificar(Concepto_Ivacompra $concepto, string $nombreVendedor = ''): array
    {
        $codigo = (string) ($concepto->codigo ?? '');
        $nombre = (string) ($concepto->nombre ?? '');
        $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
        $tasa = ConceptoIvacompraFormulaSupport::tasaEfectiva($concepto);

        if ($tipo === '' || $tipo === 'N') {
            $resuelto = self::resolverTipoNoInformado($concepto, $nombre);
            $tipo = $resuelto['tipo'];
            if ($tasa <= 0 && $resuelto['tasa'] > 0) {
                $tasa = $resuelto['tasa'];
            }
        }

        if (($tipo === 'G' || $tipo === 'I') && $tasa <= 0) {
            $tasa = self::tasaDesdeColumnaONombre($concepto, $nombre);
        }

        return [
            'destino' => LibroIvaDigitalComprasImportesSupport::destinoRubroLid(
                (int) preg_replace('/\D+/', '', $codigo),
                $nombre,
                $tipo,
                $nombreVendedor,
            ),
            'tasa' => $tasa,
        ];
    }

    private static function cargar(): void
    {
        if (self::$porCodigo !== null) {
            return;
        }

        self::$porCodigo = [];
        /** @var Collection<int, Concepto_Ivacompra> $conceptos */
        $conceptos = Concepto_Ivacompra::query()->with(['impuestos', 'columna_ivacompras'])->get();
        ConceptoIvacompraFormulaSupport::inferirTiposYTasasEnColeccion($conceptos);
        foreach ($conceptos as $concepto) {
            $codigo = (int) preg_replace('/\D+/', '', (string) $concepto->codigo);
            if ($codigo > 0) {
                self::$porCodigo[$codigo] = $concepto;
            }
        }
    }

    /**
     * @return array{tipo: string, tasa: float}
     */
    private static function resolverTipoNoInformado(Concepto_Ivacompra $concepto, string $nombre): array
    {
        $codigo = (string) ($concepto->codigo ?? '');
        if (ComprobanteProveedorConceptoIvaTipos::esExento('N', $codigo)) {
            return ['tipo' => 'E', 'tasa' => 0.0];
        }

        $columna = self::numeroColumna($concepto);
        if ($columna === '3') {
            return ['tipo' => self::tipoRetencionOPercepcion($nombre), 'tasa' => 0.0];
        }

        $tipo = match ($columna) {
            '1' => 'E',
            '4' => 'G',
            '5' => 'N',
            '6' => 'P',
            '7', '8' => 'I',
            // Columna 9: IVA importación sin neto en el comprobante. Va a otros
            // tributos (tipo vacío no entra a destinoRubroLid como IVA).
            '9' => '',
            default => 'N',
        };

        return [
            'tipo' => $tipo,
            'tasa' => $tipo === 'I' ? self::tasaDesdeColumnaONombre($concepto, $nombre) : 0.0,
        ];
    }

    private static function tipoRetencionOPercepcion(string $nombre): string
    {
        $n = strtoupper($nombre);
        $esIibb = str_contains($n, 'I.B')
            || str_contains($n, 'IIBB')
            || str_contains($n, 'IBR')
            || str_contains($n, 'SIRCREB');
        if ($esIibb) {
            return 'B';
        }
        if (str_contains($n, 'GANAN') || str_contains($n, 'GCIA')) {
            return 'A';
        }

        return 'N';
    }

    private static function tasaDesdeColumnaONombre(Concepto_Ivacompra $concepto, string $nombre): float
    {
        $columna = self::numeroColumna($concepto);
        if ($columna === '7') {
            return 21.0;
        }

        $n = strtoupper(str_replace(',', '.', $nombre));
        foreach ([27.0 => '27', 21.0 => '21', 10.5 => '10.5', 5.0 => '5', 2.5 => '2.5'] as $tasa => $marca) {
            if (str_contains($n, $marca)) {
                return (float) $tasa;
            }
        }

        return 0.0;
    }

    private static function numeroColumna(Concepto_Ivacompra $concepto): string
    {
        return trim((string) ($concepto->columna_ivacompras->numerocolumna ?? ''));
    }
}
