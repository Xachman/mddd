<?php
/**
 * Data manager utility
 * 
 * frontend page to select site and set up config details
 * 
 * when it runs it will create a mysql dump of live site data and save it to a tmp file locally
 * then exec to selected db
 */
require 'src/DataManager.php';

$dm = new DataManager();

require 'view/inc/header.php';
require 'view/' . $dm->getView() . '.php';
require 'view/inc/footer.php';