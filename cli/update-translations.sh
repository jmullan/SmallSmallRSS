#!/bin/sh
TEMPLATE=messages.pot

xgettext -krenderJsDeclaration -kT_sprintf -kT_ngettext:1,2 -k__ -L PHP -o $TEMPLATE `find src web plugins -iname '*.php'`

xgettext --from-code utf-8 -k__ -knotify_info -knotify_progress -kngettext -L Java -j -o $TEMPLATE js/*.js `find plugins -iname '*.js'`

update_lang() {
    if [ -f $1.po ]; then
	msgmerge --no-wrap --width 1 -U $1.po $TEMPLATE
	msgfmt --statistics $1.po -o $1.mo
    else
	echo "Usage: $0 [-p|<basename>]"
    fi
}

LANGS=`find src/locale -name 'messages.po'`

for lang in $LANGS; do
    echo Updating $lang...
    PO_BASENAME=`echo $lang | sed s/.po//`
    update_lang $PO_BASENAME
done
