file=./files/Case.php

perl -p -i -e 's/civicrm_case"/civicrm_brisskit_case"/g' $file
perl -p -i -e 's/civicrm_case\./civicrm_brisskit_case\./g' $file
perl -p -i -e 's/civicrm_case /civicrm_brisskit_case /g' $file
perl -p -i -e 's/civicrm_case\n/civicrm_brisskit_case\n/g' $file


#perl -p -i -e 's/civicrm_case_type"/civicrm_brisskit_case_type"/g' $file
#perl -p -i -e 's/civicrm_case_type\./civicrm_brisskit_case_type\./g' $file
#perl -p -i -e 's/civicrm_case_type /civicrm_brisskit_case_type /g' $file
#perl -p -i -e 's/civicrm_case_type\n/civicrm_brisskit_case_type\n/g' $file


