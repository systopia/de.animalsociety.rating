#!/bin/sh
# regenerate the POT files for translation

l10n_tools="../civi_l10n_tools"
mkdir -p l10n

# generate temporary php file for the labels of the custom data structures
echo '<?php\n function l10n() {' > l10n.php
cat resources/*.json | grep '"title":' | sed 's/"title": /ts(/' | sed 's/",/");/' >> l10n.php
cat resources/*.json | grep '"label":' | sed 's/"label": /ts(/' | sed 's/",/");/' >> l10n.php
echo '}' >> l10n.php

# run the string extraction
${l10n_tools}/bin/create-pot-files-extensions.sh de.animalsociety.rating ./ l10n

# clean up
rm l10n.php