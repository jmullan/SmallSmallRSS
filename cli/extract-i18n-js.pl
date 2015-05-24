#!/usr/bin/perl -w
#
use strict;

while (<STDIN>) {
	chomp;

	if (/(__|notify_progress|notify|notify_info|notify_error)\(['"](.*?)['"]\)/) {
		my $msg = $2;

		$msg =~ s/\"/\\\"/g;

		print "print renderJsDeclaration(\"$msg\");\n";
	}
}
