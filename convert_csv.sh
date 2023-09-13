#!/bin/bash

cd languages || exit
POT_FILE="en_GB.pot"
PO_FILE="mollie-payments-for-woocommerce-nl_NL.po"
TEMP_POT="temp_untranslated.pot"
CSV_FILE="en_GB.csv"

if [[ ! -e $POT_FILE ]]; then
    echo "File $POT_FILE not found."
    exit 1
fi

if [[ ! -e $PO_FILE ]]; then
    echo "File $POT_FILE not found."
    exit 1
fi

current_msgid=""
current_msgctxt=""
block=""

while IFS= read -r line; do
    block="$block$line\n"
    if [[ "$line" =~ ^msgid\ \"(.+)\"$ ]]; then
        current_msgid="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgctxt\ \"(.+)\"$ ]]; then
        current_msgctxt="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgstr ]]; then
        if ! grep -Fq "msgid \"$current_msgid\"" "$PO_FILE" || ( [ -n "$current_msgctxt" ] && ! grep -Fq "msgctxt \"$current_msgctxt\"" "$PO_FILE" ); then
            echo -e "$block" >> $TEMP_POT
        fi
        current_msgid=""
        current_msgctxt=""
        block=""
    fi
done < "$POT_FILE"

echo 'msgid,msgstr,msgctxt,location' > $CSV_FILE
location=""
msgid=""
msgstr=""
msgctxt=""

while IFS= read -r line; do
    if [[ "$line" =~ ^\#:\ (.+)$ ]]; then
        location="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgid\ \"(.+)\"$ ]]; then
        msgid="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgstr\ \"(.+)\"$ ]]; then
        msgstr="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgctxt\ \"(.+)\"$ ]]; then
        msgctxt="${BASH_REMATCH[1]}"
    fi

    if [[ "$line" =~ ^msgstr ]]; then
        echo "\"$msgid\",\"$msgstr\",\"$msgctxt\",\"$location\"" >> $CSV_FILE
        location=""
        msgid=""
        msgstr=""
        msgctxt=""
    fi

done < "$TEMP_POT"

rm $TEMP_POT
