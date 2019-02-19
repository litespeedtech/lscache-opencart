<?php

if (version_compare(VERSION, '2.2.0.0', '<')) { // for OpenCart 2.1.0.2 or earlier.
  oc_cli_output('Your OpenCart version is not supported.');
  exit;
}

switch(true) {
  case version_compare(VERSION, '2.3.0.0', '<'):  // OpenCart 2.2.x
    require_once dirname(__FILE__) . '/oc_cli/2.2.x.php';
    break;
  case version_compare(VERSION, '3.0.0.0', '<'):  // OpenCart 2.3.x
    require_once dirname(__FILE__) . '/oc_cli/2.3.x.php';
    break;
  default: // OpenCart 3.0.0.0 or later.
    require_once dirname(__FILE__) . '/oc_cli/3.x.php';
}
