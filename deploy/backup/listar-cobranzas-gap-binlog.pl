#!/usr/bin/env perl
use strict;
use warnings;

my $en = 0;
my @vals;
my %cob;

while (my $line = <STDIN>) {
    if ($line =~ /^### INSERT INTO `anitaERP`\.`cobranza`/) {
        $en = 1; @vals = (); next;
    }
    if ($en && $line =~ /^###   @(\d+)=(.*)$/) { $vals[$1] = $2; next; }
    if ($en && ($line =~ /^# at / || $line =~ /^### INSERT INTO `/)) {
        if (@vals >= 11) {
            my $id = limpiar($vals[1]);
            $cob{$id} = {
                id => $id,
                fecha => limpiar($vals[5]),
                monto => limpiar($vals[11]),
                venta_id => limpiar($vals[8]),
                cliente_id => limpiar($vals[7]),
                detalle => limpiar($vals[9]),
            };
        }
        $en = 0; @vals = ();
        redo if $line =~ /^### INSERT INTO `anitaERP`\.`cobranza`/;
    }
}

sub limpiar {
    my ($v) = @_;
    return '' unless defined $v;
    $v =~ s/^NULL$//; $v =~ s/^'(.*)'$/$1/; return $v;
}

print "cobranza_id\tfecha\tmonto\tventa_id\tcliente_id\tdetalle\n";
for my $id (sort { $a <=> $b } keys %cob) {
    my $c = $cob{$id};
    print join("\t", map { $c->{$_}//'' } qw(id fecha monto venta_id cliente_id detalle)), "\n";
}
print STDERR 'cobranzas=', scalar(keys %cob), "\n";
