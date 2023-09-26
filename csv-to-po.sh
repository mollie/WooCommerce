#!/bin/bash

CSV_DIR="languages/translated_csv"
PO_DIR="languages"
MO_DIR="languages"

for csv_file in "$CSV_DIR"/*.csv; do
    [ -e "$csv_file" ] || continue

    base_name=$(basename -- "$csv_file" .csv)
    po_file="$PO_DIR/$base_name.po"
    mo_file="$MO_DIR/$base_name.mo"

    awk -F'"' '
        BEGIN { OFS=""; print "msgid \"\""; print "msgstr \"\""; print "\"Content-Type: text/plain; charset=UTF-8\\n\""; }
        NR > 1 {
            gsub(/\\/, "\\\\", $2);
            gsub(/\"/, "\\\"", $2);
            gsub(/\\/, "\\\\", $4);
            gsub(/\"/, "\\\"", $4);
            print "\n#: " $2;
            print "msgid \"" $4 "\"";
            print "msgstr \"" $6 "\"";
        }' "$csv_file" > "$po_file"

    echo "Created PO file: $po_file"

    msgfmt "$po_file" -o "$mo_file"
    echo "Created MO file: $mo_file"
done
