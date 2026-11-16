<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use RuntimeException;

/**
 * Parsers CAMT.053 (ISO 20022) y MT940 → formato persistencia Interbanking.
 */
final class ConciliacionBancariaCamtMt940ParserSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function parseCamt053(string $ruta): array
    {
        $xml = @file_get_contents($ruta);
        if ($xml === false || trim($xml) === '') {
            throw new RuntimeException('No se pudo leer el CAMT.053.');
        }

        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new RuntimeException('CAMT.053 XML inválido.');
        }

        $doc->registerXPathNamespace('a', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.02');
        $doc->registerXPathNamespace('b', 'urn:iso:std:iso:20022:tech:xsd:camt.053.001.08');

        $nodos = $doc->xpath('//*[local-name()="Ntry"]') ?: [];
        $movimientos = [];
        foreach ($nodos as $ntry) {
            $amtNode = $ntry->xpath('.//*[local-name()="Amt"]')[0] ?? null;
            $importe = $amtNode !== null ? (float) str_replace(',', '.', (string) $amtNode) : 0.0;
            if (abs($importe) < 0.00001) {
                continue;
            }
            $cdtDbt = strtoupper((string) ($ntry->xpath('.//*[local-name()="CdtDbtInd"]')[0] ?? 'DBIT'));
            $dh = str_starts_with($cdtDbt, 'C') ? 'C' : 'D';
            $fechaRaw = (string) ($ntry->xpath('.//*[local-name()="BookgDt"]/*[local-name()="Dt"]')[0]
                ?? $ntry->xpath('.//*[local-name()="ValDt"]/*[local-name()="Dt"]')[0]
                ?? '');
            $fecha = self::ymd($fechaRaw);
            if ($fecha === null) {
                continue;
            }
            $memo = (string) ($ntry->xpath('.//*[local-name()="Ustrd"]')[0]
                ?? $ntry->xpath('.//*[local-name()="AddtlNtryInf"]')[0]
                ?? 'CAMT');
            $refs = (string) ($ntry->xpath('.//*[local-name()="AcctSvcrRef"]')[0]
                ?? $ntry->xpath('.//*[local-name()="EndToEndId"]')[0]
                ?? '');

            $movimientos[] = [
                'process_date' => $fecha,
                'debit_credit_type' => $dh,
                'amount' => abs($importe),
                'code_description_ib' => mb_substr($memo, 0, 80),
                'code_description_bank' => mb_substr($memo, 0, 120),
                'voucher_number' => $refs !== '' ? mb_substr($refs, 0, 40) : null,
                'operation_code_ib' => 'CAMT053',
                'grouping_code_ib' => null,
            ];
        }

        if ($movimientos === []) {
            throw new RuntimeException('El CAMT.053 no tiene entradas Ntry legibles.');
        }

        return $movimientos;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseMt940(string $ruta): array
    {
        $raw = @file_get_contents($ruta);
        if ($raw === false || trim($raw) === '') {
            throw new RuntimeException('No se pudo leer el MT940.');
        }

        $texto = str_replace(["\r\n", "\r"], "\n", $raw);
        $bloques = preg_split('/(?=^:61:)/m', $texto) ?: [];
        $movimientos = [];

        foreach ($bloques as $bloque) {
            if (! preg_match('/^:61:(\d{6})(\d{4})?([CD])([A-Z]?)([\d,\.]+)([A-Z]{0,4})?(.*)$/m', $bloque, $m)) {
                continue;
            }
            $yymmdd = $m[1];
            $anio = (int) ('20'.substr($yymmdd, 0, 2));
            $mes = (int) substr($yymmdd, 2, 2);
            $dia = (int) substr($yymmdd, 4, 2);
            if (! checkdate($mes, $dia, $anio)) {
                continue;
            }
            $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
            $dh = strtoupper($m[3]) === 'C' ? 'C' : 'D';
            $importe = (float) str_replace(',', '.', $m[5]);
            if (abs($importe) < 0.00001) {
                continue;
            }
            $memo = '';
            if (preg_match('/:86:(.+?)(?=^:|$)/s', $bloque, $m86)) {
                $memo = trim(preg_replace('/\s+/', ' ', $m86[1]) ?? '');
            }
            $ref = trim((string) ($m[7] ?? ''));

            $movimientos[] = [
                'process_date' => $fecha,
                'debit_credit_type' => $dh,
                'amount' => abs($importe),
                'code_description_ib' => mb_substr($memo !== '' ? $memo : 'MT940', 0, 80),
                'code_description_bank' => mb_substr($memo !== '' ? $memo : 'MT940', 0, 120),
                'voucher_number' => $ref !== '' ? mb_substr($ref, 0, 40) : null,
                'operation_code_ib' => 'MT940',
                'grouping_code_ib' => null,
            ];
        }

        if ($movimientos === []) {
            throw new RuntimeException('El MT940 no tiene líneas :61: legibles.');
        }

        return $movimientos;
    }

    private static function ymd(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return null;
    }
}
