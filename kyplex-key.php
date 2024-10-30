<?php
@include_once(realpath(dirname(realpath(__FILE__))).'/kyplex-conf.php');
if (isset($kyplex_key) && strlen($kyplex_key) > 0)
  print $kyplex_key;
?>
