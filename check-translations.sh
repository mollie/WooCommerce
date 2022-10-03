#! /bin/bash

#copy potFile
cp mollie-payments-for-woocommerce.pot en_GB.po
echo "en_GB.po created"
#remove header
sed -i '' -e '1,17d' en_GB.po
#remove msgctxt
sed -i '' -e 's/msgctxt.*//g' en_GB.po
#remove msgstr
sed -i '' -e 's/msgstr.*//g' en_GB.po
#remove comments
sed -i '' -e 's/#.*//g' en_GB.po
#remove empty lines
sed -i '' -e '/^$/d' en_GB.po
#create array of file names
files=($(ls -1 *.po))
#for each file in files
for file in "${files[@]}"
do
    #copy file to temp-file
    cp $file temp.po
    #remove header
    sed -i '' -e '1,20d' temp.po
    #remove msgctxt
    sed -i '' -e 's/msgctxt.*//g' temp.po
    #remove msgstr
    sed -i '' -e 's/msgstr.*//g' temp.po
    #remove comments
    sed -i '' -e 's/#.*//g' temp.po
    #remove empty lines
    sed -i '' -e '/^$/d' temp.po
    #diff en_GB.po with all other files
    diff en_GB.po temp.po > $file.diff
    #remove temp-file
    rm temp.po
done
