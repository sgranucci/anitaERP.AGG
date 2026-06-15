#!/usr/bin/env perl
# Extrae filas completas de cobranza INSERT desde mysqlbinlog --verbose.
use strict;
use warnings;

my $en = 0;
my @vals;
my %rows;

while (my $line = <STDIN>) {
    if ($line =~ /^### INSERT INTO `anitaERP`\.`cobranza`/) {
        $en = 1; @vals = (); next;
    }
    if ($en && $line =~ /^###   @(\d+)=(.*)$/) { $vals[$1] = $2; next; }
    if ($en && ($line =~ /^# at / || ($line =~ /^### INSERT INTO `/ && $line !~ /cobranza`/))) {
        if (@vals >= 11) {
            my %r;
            @r{1..17} = @vals[1..17];
            my $id = limpiar($r{1});
            $rows{$id} = \%r if $id >= 10163 && $id <= 10186;
        }
        $en = 0; @vals = ();
    }
}

sub limpiar {
    my ($v) = @_;
    return undef unless defined $v;
    return undef if $v eq 'NULL';
    $v =~ s/^'(.*)'$/$1/; return $v;
}

sub sql_val {
    my ($v) = @_;
    return 'NULL' unless defined $v;
    return $v if $v =~ /^\d+(\.\d+)?$/;
    $v =~ s/'/\\'/g;
    return "'$v'";
}

for my $id (sort { $a <=> $b } keys %rows) {
    my $r = $rows{$id};
    print "UPDATE cobranza SET\n";
    print "  empresa_id = " . sql_val(limpiar($r->{2})) . ",\n";
    print "  tipotransaccion_caja_id = " . sql_val(limpiar($r->{3})) . ",\n";
    print "  numerotransaccion = " . sql_val(limpiar($r->{4})) . ",\n";
    print "  fecha = " . sql_val(limpiar($r->{5})) . ",\n";
    print "  caja_id = " . sql_val(limpiar($r->{6})) . ",\n";
    print "  cliente_id = " . sql_val(limpiar($r->{7})) . ",\n";
    print "  venta_id = " . sql_val(limpiar($r->{8})) . ",\n";
    print "  detalle = " . sql_val(limpiar($r->{9})) . ",\n";
    print "  estado = " . sql_val(limpiar($r->{10})) . ",\n";
    print "  monto = " . sql_val(limpiar($r->{11})) . ",\n";
    print "  cotizacion = " . sql_val(limpiar($r->{12})) . ",\n";
    print "  moneda_id = " . sql_val(limpiar($r->{13})) . ",\n";
    print "  usuario_id = " . sql_val(limpiar($r->{14})) . ",\n";
    print "  created_at = " . sql_val(limpiar($r->{16})) . ",\n";
    print "  updated_at = " . sql_val(limpiar($r->{17})) . "\n";
    print "WHERE id = $id;\n\n";
}

print STDERR 'cobranzas=', scalar(keys %rows), "\n";
