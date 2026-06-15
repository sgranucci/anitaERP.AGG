#!/usr/bin/env perl
# Extrae INSERT de venta/cobranza del binlog verbose (mysqlbinlog -v).
# Uso: mysqlbinlog ... --verbose | perl extract-gastronomia-binlog.pl > /tmp/gap_gastronomia.sql

use strict;
use warnings;

my @tablas = qw(
    venta venta_impuesto venta_emision venta_gastronomia_emision
    cuenta_gastronomia cuenta_gastronomia_linea
    cobranza cobranza_estado cobranza_comprobante cobranza_retencion
    caja_movimiento caja_movimiento_cuentacaja caja_movimiento_estado
    waitry_comanda_envio ticketcanje_gastronomia tickettarjeta_gastronomia
    rendicion_gastronomia_caja rendicion_gastronomia_movimiento_caja
    rendicion_gastronomia_secuencia_empresa turno_operativo_gastronomia
    articulo_movimiento articulo_saldo_deposito
    canje_marketing_entrega_gastronomia gastronomia_cierre_jornada_proceso_snapshot
);

my %tabla_ok = map { $_ => 1 } @tablas;

print "SET FOREIGN_KEY_CHECKS=0;\n";
print "SET UNIQUE_CHECKS=0;\n";
print "SET SQL_LOG_BIN=0;\n";

my $capturando = 0;
my $tabla      = '';
my @bloque;

sub flush_bloque {
    return unless $capturando && @bloque;
    print join("\n", @bloque), "\n";
    @bloque = ();
}

while (my $line = <STDIN>) {
    if ($line =~ /^### (INSERT INTO|UPDATE|DELETE FROM) `anitaERP`\.`([^`]+)`/) {
        flush_bloque();
        $tabla = $2;
        $capturando = $tabla_ok{$tabla} ? 1 : 0;
        if ($capturando) {
            @bloque = ($line =~ s/`anitaERP`/`anitaERP_gap`/r);
        }
        next;
    }

    if ($capturando) {
        if ($line =~ /^### (INSERT INTO|UPDATE|DELETE FROM) `anitaERP`\.`/ && $line !~ /`$tabla`/) {
            flush_bloque();
            $capturando = 0;
            $tabla      = '';
            redo;
        }
        if ($line =~ /^# at \d+/ && @bloque > 1) {
            flush_bloque();
            $capturando = 0;
            $tabla      = '';
            next;
        }
        push @bloque, $line =~ s/`anitaERP`/`anitaERP_gap`/r;
    }
}

flush_bloque();
print "SET FOREIGN_KEY_CHECKS=1;\n";
print "SET UNIQUE_CHECKS=1;\n";
