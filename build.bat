:: Delete old data
del wdrmediathek.host
:: create the .tar.gz
7z a -ttar -so wdrmediathek INFO wdrmediathek.php | 7z a -si -tgzip wdrmediathek.host
