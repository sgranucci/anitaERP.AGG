#!/usr/bin/env perl
# Omite sentencias SQL de UNA tabla en salida de mysqlbinlog (no toca DROP multi-tabla).
# Uso: mysqlbinlog ... | perl filter-binlog-skip-table.pl padron_iibb_arba | mysql ...

use strict;
use warnings;

my $skip_table = shift @ARGV // '';
die "Uso: $0 <nombre_tabla>\n" unless $skip_table ne '';

while (my $line = <STDIN>) {
    if ($line =~ /^(?:INSERT|UPDATE|DELETE|REPLACE) INTO `?(?:\w+`\.)?`?$skip_table`?\b/i) {
        next;
    }

    if ($line =~ /^(?:DROP|CREATE|TRUNCATE) TABLE(?: IF EXISTS)? `?$skip_table`?\b/i) {
        next;
    }

    print $line;
}
