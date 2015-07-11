:: Delete old data
del wdrmediathek.host

:: get recent version of the provider base class
copy /Y ..\provider-boilerplate\src\provider.php provider.php

:: create the .tar.gz
7z a -ttar -so wdrmediathek INFO wdrmediathek.php provider.php | 7z a -si -tgzip wdrmediathek.host

del provider.php