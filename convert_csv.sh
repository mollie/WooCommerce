#!/bin/bash

cd languages || exit

POT_FILE="en_GB.pot"
CSV_DIR="untranslated_csv"

LANG_EXT=("-de_DE" "-de_DE_formal" "-es_ES" "-fr_FR" "-it_IT" "-nl_NL" "-nl_NL_formal" "-nl_BE" "-nl_BE_formal")

if [[ ! -e $POT_FILE ]]; then
    echo "File $POT_FILE not found."
    exit 1
fi

mkdir -p $CSV_DIR  # Create the directory if it does not exist

# Loop through each language extension
for lang in "${LANG_EXT[@]}"; do
    PO_FILE="mollie-payments-for-woocommerce${lang}.po"
    CSV_FILE="$CSV_DIR/mollie-payments-for-woocommerce${lang}.csv"
    UNTRANSLATED_FILE="$CSV_DIR/mollie-payments-for-woocommerce${lang}_untranslated.csv"

    if [[ -e $PO_FILE ]]; then
        msgmerge --update --no-wrap --backup=none "$PO_FILE" "$POT_FILE"  # Update PO file with POT file
        echo "Updated $PO_FILE with new strings from $POT_FILE"
    else
        echo "File $PO_FILE not found. Skipping..."
        continue
    fi

    echo 'location,msgid,msgstr,msgctxt' > "$CSV_FILE"
    echo 'line,msgid' > "$UNTRANSLATED_FILE"

    # Initialize variables
    location=""
    msgid=""
    msgstr=""
    msgctxt=""
    line_no=0

    # Function to write to CSV
    write_to_csv() {
        echo "\"$location\",\"$msgid\",\"$msgstr\",\"$msgctxt\"" >> "$CSV_FILE"
        if [[ -z "$msgstr" && -n "$msgid" ]]; then
            echo "$line_no,\"$msgid\"" >> "$UNTRANSLATED_FILE"
        fi
        # Reset variables
        location=""
        msgid=""
        msgstr=""
        msgctxt=""
    }

    while IFS= read -r line || [[ -n "$line" ]]; do  # also handle the last line
        ((line_no++))

        if [[ "$line" =~ ^\#:\ (.+)$ ]]; then
            location="${BASH_REMATCH[1]}"
        elif [[ "$line" =~ ^msgid\ \"(.*)\"$ ]]; then
            if [[ -n "$msgid" || -n "$msgstr" ]]; then
                write_to_csv
            fi
            msgid="${BASH_REMATCH[1]}"
        elif [[ "$line" =~ ^msgstr\ \"(.*)\"$ ]]; then
            msgstr="${BASH_REMATCH[1]}"
        elif [[ "$line" =~ ^msgctxt\ \"(.+)\"$ ]]; then
            msgctxt="${BASH_REMATCH[1]}"
        fi
    done < "$PO_FILE"

    # For the last msgid in the file
    if [[ -n "$msgid" || -n "$msgstr" ]]; then
        write_to_csv
    fi

    echo "Created CSV $CSV_FILE and $UNTRANSLATED_FILE for $PO_FILE"
done
