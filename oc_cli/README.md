# oc_cli_mod
This is the modified version of oc_cli (https://github.com/iSenseLabs/oc_cli). The original version supports only OpenCart 2.2.x and 2.3.x at the time of writing (December 2017), whereas this version supports OpenCart 3.0.x as well.

For more information, please visit the original version of oc_cli at:
https://github.com/iSenseLabs/oc_cli


Installation
--------------
Just copy everything from the /upload directory to your OpenCart root directory. No original OpenCart files will be overwritten.


How it works
--------------
oc_cli introduces a new file in your OpenCart root directory: `oc_cli.php`. All you need to do is run this file from your command line with the appropriate parameters.
After v1.2.0, LiteSpeed Cache Plugin support the following cli commands:
```
php oc_cli.php catalog extension/module/lscache/recache
php oc_cli.php catalog extension/module/lscache/purgeAll
```


Copyright and license
---------------------
The license is available within the repository in the [LICENSE][license] file.


[license]: LICENSE

