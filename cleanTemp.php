<?php

  $dirname = dirname(__FILE__).'/temp/';
  $dir = Dir($dirname); 
  $_dirs = $_fils = array();
  while ($entry = $dir->Read()) { 
      if (($entry != basename(__FILE__)) and ($entry != '.ftpquota') and ($entry != '.') and ($entry != '..')) {
          if (FileType($dirname.$entry) != "dir") {
              unlink($dirname.$entry);
          }
      }
  }

?>