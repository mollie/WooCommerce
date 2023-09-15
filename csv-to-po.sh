#!/bin/bash

cd languages || exit

CSV_FILE="en_GB.csv"
OUTPUT_DIR="intermediate_po"
TEMPLATE_POT="en_GB.pot"

if [[ ! -e $CSV_FILE ]]; then
    echo "File $CSV_FILE not found."
    exit 1
fi

mkdir -p $OUTPUT_DIR

# Remove surrounding quotes
trim_quotes() {
    local val="$1"
    echo "$val" | sed -e 's/^"//' -e 's/"$//'
}

# Parse the header to get the list of languages
IFS=',' read -ra HEADER <<< "$(head -n 1 $CSV_FILE)"

# For each language in the CSV, create an intermediate .po file
for i in "${!HEADER[@]}"; do
    if (( $i > 3 )); then
        LANGUAGE=$(trim_quotes "${HEADER[$i]}")
        echo -n "" > "$OUTPUT_DIR/$LANGUAGE.po"
        while IFS=',' read -ra LINE; do
            # Skip the header line
            if [[ "${LINE[0]}" != "msgid" ]]; then
                if [[ -n "${LINE[3]}" ]]; then
                    echo "#: $(trim_quotes "${LINE[3]}")" >> "$OUTPUT_DIR/$LANGUAGE.po"
                fi
                if [[ -n "${LINE[2]}" ]]; then
                    echo "msgctxt \"$(trim_quotes "${LINE[2]}")\"" >> "$OUTPUT_DIR/$LANGUAGE.po"
                fi
                echo "msgid \"$(trim_quotes "${LINE[0]}")\"" >> "$OUTPUT_DIR/$LANGUAGE.po"
                echo "msgstr \"$(trim_quotes "${LINE[$i]}")\"" >> "$OUTPUT_DIR/$LANGUAGE.po"
                echo "" >> "$OUTPUT_DIR/$LANGUAGE.po"
            fi
        done < "$CSV_FILE"
    fi
done
# Append intermediate .po to existing .po
for po in $OUTPUT_DIR/*.po; do
    BASENAME=$(basename "$po" ".po" | tr -d ' ')
    EXISTING_PO="mollie-payments-for-woocommerce${BASENAME}.po"
    if [[ -e $EXISTING_PO ]]; then
        cat "$po" >> $EXISTING_PO
    fi
done

# Compile .po to .mo
for po in *.po; do
    MO_FILE="${po%.po}.mo"
    msgfmt "$po" -o "$MO_FILE"
done
