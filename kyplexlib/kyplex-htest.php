<?php

$kyplex_dir = dirname(dirname(realpath(__FILE__)));
@include_once($kyplex_dir."/kyplex-conf.php");

@include_once(dirname(realpath(__FILE__))."/kyplex-common.php");

$test_exec_php = 0;
$exec = '';
$exec_cli = '';
$exec_php = '';
$proc_open = '';
$sql = '';
$sql_status = '';
$sql_cli_status = '';
$sql_dump = '';
$sql_dump_status = '';
$sql_cli_dump_status = '';
$ftp = '';
$url = '';
$safe = '';
$safe_cli = '';
$res = array();
$result = '';
$yes = '<img src="https://management.kyplex.com/images/yes.png" />';
$disabled = explode(',', ini_get('disable_functions'));
$disabled = array_map('trim', $disabled);
#in_array  ('curl', get_loaded_extensions()
$php = kyplex_get_php_location();
$mysqldump = kyplex_get_mysqldump_location($mysqldump);
$pgdump = kyplex_get_pgdump_location($pgdump);
#$db_type = "psql";

if (!function_exists('ftp_connect'))
{
  $ftp = "<font color='red'>PHP ftp module is missing</font>";
} else if (in_array('ftp_connect', $disabled))
{
  $ftp = "<font color='red'>PHP ftp_connect() function is disabled</font>";
} else {
  $ftp = $yes;
}

if( ini_get('allow_url_fopen') ) {
  $url = $yes;
} else {
  if (!function_exists('curl_open'))
  {
    $url = "<font color='red'>fopen() can not open urls and PHP CURL module is disabled</font>";
  }
  else if (in_array('curl_init', $disabled))
  {
    $url = "<font color='red'>fopen() can not open urls and PHP curl_init() function is disabled</font>";
  } else {
    $url = "PHP CURL will be used instead";
  }
}
$safe_desc_long = "When PHP safe mode is on, PHP can execute commands just in specific directoris. In order to allow mysqldump or pg_dump to work you need to add mysqldump or pd_dump directory to the list of allowed directories using <a href='http://www.php.net/manual/en/ini.sect.safe-mode.php#ini.safe-mode-exec-dir'>safe_mode_exec_dir directive</a>. Another option is simply to copy mysqldump or pg_dump to kyples plugin directory.";

if (ini_get('safe_mode'))
{
  $safe = "On";
} else {
  $safe = "Off";
}
if (in_array('proc_open', $disabled))
{
  $proc_open = "<font color='red'>PHP proc_open() command is disabled</font>";
  $sql_dump_status = "can not check"; 
  $exec_php = "can not check";
} else {
  $proc_open = $yes;

  $res = array();
  $descriptorspec = array(
    0 => array("pipe", "r"),  // stdin
    1 => array("pipe", "w"),  // stdout
    2 => array("pipe", "w")   // error
  );
  $env= array();
  if (!isset($db_type) || !$db_type || $db_type == "mysql")
  {
    $sql_dump = "mysqldump";
    $process = @proc_open($mysqldump, $descriptorspec, $pipes, '', $env);
    if (!is_resource($process)) {
      $sql_dump_status = "<font color='red'>mysqldump command is missing</font>";
    } else {
      @fclose($pipes[0]);
      $result = '';
      while(!@feof($pipes[1])) {
       $result .= @fgets($pipes[1],1024);
      }

      if (strpos($result, "Usage:") === false)
      {
        $sql_dump_status = "<font color='red'>mysqldump command is missing</font>";
      } else {
        $sql_dump_status = $yes;
      }
    }
  } else {
    $sql_dump = "pg_dump";
    $process = @proc_open($pgdump.' --help', $descriptorspec, $pipes, '', $env);
    if (!is_resource($process)) {
      $sql_dump_status = "<font color='red'>pg_dump command is missing</font>";
    } else {
      @fclose($pipes[0]);
      $result = '';
      while(!@feof($pipes[1])) {
       $result .= @fgets($pipes[1],1024);
      }
      if (strpos($result, "pg_dump dumps") === false)
      {
        $sql_dump_status = "<font color='red'>pg_dump command is missing</font>";
      } else {
        $sql_dump_status = $yes;
      }
    }
  }  
}

if ($test_exec_php == 0)
{
  $exec = "not tested";
  $exec_php = "not tested";
} else if (in_array('exec', $disabled))
{
  $exec = "<font color='red'>PHP exec() command is disabled</font>";
  $exec_php = "can not check";
} else {
  $exec = $yes;
  $res = array();
  @exec("echo '<"."?"."php print (12431+10); "."?".">' | $php", $res);
  ##@exec("$php -r 'print (12431+10);'", $res);
  $result = @join("\n", $res);
  if (!isset($result) || $result == '')
  {
    $exec_php = "<font color='red'>can not start external php script</font>";
  } else if (isset($res[0]) && (strpos($result, "12441")) !== false)
  {
    $exec_php = $yes;
  } else {
    $exec_php = "<font color='red'>can not start external php script</font><!-- ".$result." -->";
  }
  #@exec("$php ".dirname(realpath(__FILE__))."/kyplex-cron.php", $res);
  #$result = @join("\n", $res);
  #if (!isset($result) || $result == '')
  #{
  #  $exec_php = "<font color='red'>can not start external php script</font>";
  #} else if (strpos($result, "starting") !== false)
  #{
  #  $exec_php = $yes;
  #} else {
  #  $exec_php = "<font color='red'>can not start external php script</font><!-- ".$result." -->";
  #}
}

if (!isset($db_type) || !$db_type || $db_type == 'mysql')
{
  $sql = "PHP MySQL Extension";
  if (!function_exists('mysql_connect'))
  {
    $sql_status = "<font color='red'>Disabled / not installed</font>";
  } else if (in_array('mysql_connect', $disabled))
  {
    $sql_status = "<font color='red'>PHP mysql_connect() function is disabled</font>";
  } else {
    $sql_status = $yes;
  }
} else {
  $sql = "PHP PgSQL Extension";
  if (!function_exists('pg_connect'))
  {
    $sql_status = "<font color='red'>Disabled / not installed</font>";
  } else if (in_array('pg_connect', $disabled))
  {
    $sql_status = "<font color='red'>PHP pg_connect() function is disabled</font>";
  } else {
    $sql_status = $yes;
  }
}

# Disable cli test
if ($test_exec_php == 0)
{
  $safe_cli = $ftp_cli = $exec_cli = $sql_cli_status = $sql_cli_dump_status = "not tested";
  if (!isset($db_type) || !$db_type || $db_type = "mysql")
  {
    $sql_cli = "PHP MySQL Extension";
    $sql_cli_dump = "mysqldump";
  } else {
    $sql_cli = "PHP PgSQL Extension";
    $sql_cli_dump = "pg_dump";
  }
}
else
if ($exec_php == $yes)
{
  $res = array();
  $cmd = "if (function_exists('ftp_connect') === false) { echo 'missing'; } ".
         "else if (in_array('ftp_connect', array_map('trim', explode(',', ini_get('disable_functions'))))) ".
         "{ echo 'disabled'; } else { echo 'good'; }";
  @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
  #@exec("$php -r \"$cmd\"", $res);
  $result = @join("\n", $res);
  if (!isset($res[0]))
  {
    $ftp_cli = "unknown error";
  } else if (strpos($result, "missing") !== false)
  {
    $ftp_cli = "<font color='red'>PHP ftp module is missing</font>";
  } else if (strpos($result, "disabled") !== false)
  {
    $ftp_cli = "<font color='red'>PHP ftp_connect() function is disabled</font>";
  } else if (strpos($result, "good") !== false)
  {
    $ftp_cli = $yes;
  } else {
    $ftp_cli = @array_pop($res);
  }

  # check for MySQL extension
  $res = array();
  if (!isset($db_type) || $db_type == "mysql")
  {
    $cmd = "if (function_exists('mysql_connect') === false) { echo 'missing'; } ".
         "else if (in_array('mysql_connect', array_map('trim', explode(',', ini_get('disable_functions'))))) ".
         "{ echo 'disabled'; } else { echo 'good'; }";
  } else {
    $cmd = "if (function_exists('pg_connect') === false) { echo 'missing'; } ".
         "else if (in_array('pg_connect', array_map('trim', explode(',', ini_get('disable_functions'))))) ".
         "{ echo 'disabled'; } else { echo 'good'; }";
  }
  @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
  $result = @join("\n", $res);
  if (!isset($res[0]))
  {
    $sql_cli_status = "unknown error";
  } else if (strpos($result, "missing") !== false)
  {
    $sql_cli_status = "<font color='red'>PHP $db_type module is missing</font>";
  } else if (strpos($result, "disabled") !== false)
  {
    if ($db_type == "mysql")
      $sql_cli_status = "<font color='red'>PHP mysql_connect() function is disabled</font>";
    else
      $sql_cli_status = "<font color='red'>PHP pg_connect() function is disabled</font>";
  } else if (strpos($result, "good") !== false)
  {
    $sql_cli_status = $yes;
  } else {
    $sql_cli_status = @array_pop($res);
  }

  # check for PHP safe mode
  $res = array();
  $cmd = "if (ini_get('safe_mode')) { print 'enabled'; } else { print 'disabled'; }";
  @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
  if (isset($res[0]))
    $safe_cli = @array_pop($res);
  else
    $safe_cli = "unknown error";
  # check for php exec
  $res = array();
  $cmd = "if (in_array('ftp_connect', array_map('trim', explode(',', ini_get('disable_functions'))))) ".
         "{ echo 'disabled'; } else { echo 'good'; }";
  @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
  $result = @join("\n", $res);
  if (!isset($res[0]))
  {
    $exec_cli = "unknown error";
  } else if (strpos($result, "disabled") !== false)
  {
    $exec_cli = "<font color='red'>PHP exec() function is disabled</font>";
  } else if (strpos($result, "good") !== false)
  {
    $exec_cli = $yes;
  } else {
    $exec_cli = @array_pop($res);
  }
  $res = array();
  if ($exec_cli == $yes)
  {
    if (!isset($db_type) || !$db_type || $db_type == "mysql")
    {
      $sql_dump = "mysqldump";
      $cmd = "\\\$res = array(); @exec('$mysqldump', \\\$res);print \\\$res[0];";
      @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
      $result = join("\n", $res);
      if (strpos($result, "Usage:") === false)
      {
        $sql_cli_dump_status = "<font color='red'>mysqldump command is missing</font>";
      } else {
        $sql_cli_dump_status = $yes;
      }
    } else {
      $sql_dump = "pg_dump";
      $cmd = "\\\$res = array(); @exec('$pgdump --help', \\\$res);print \\\$res[0];";
      @exec("echo \"<"."?"."php $cmd "."?".">\" | $php", $res);
      $result = join("\n", $res);
      if (strpos($result, "pg_dump dumps") === false)
      {
        $sql_cli_dump_status = "<font color='red'>pg_dump command is missing</font>";
      } else {
        $sql_cli_dump_status = $yes;
      }
    }
  } else {
    $sql_cli_dump_status = "can not check";
  }
} else {
  $safe_cli = $ftp_cli = $exec_cli = $sql_cli_status = $sql_cli_dump_status = "can not check";
  if (!$db_type || $db_type = "mysql")
  {
    $sql_cli = "PHP MySQL Extension";
    $sql_cli_dump = "mysqldump";
  } else {
    $sql_cli = "PHP PgSQL Extension";
    $sql_cli_dump = "pg_dump";
  }
}

if (isset($_GET['action']) && $_GET['action'] == 'status')
{
  #print "ok,error";
  #exit;
  #if ($ftp != $yes || $exec != $yes || $exec_php != $yes || $ftp_cli != $yes)
  if ($ftp != $yes)
  {
    print "error,error";
    kyplex_fetch_direct_url("http://management.kyplex.com/plugin-support.php?module=htest&status=error,error&".
                     "email=$kyplex_account_email&apikey=$kyplex_api_key&site=$kyplex_site&".
                     "ftp=".urlencode($ftp)."&exec=".urlencode($exec)."&sql_dump_status=".urlencode($sql_dump_status)."&".
                     "proc_open=".urlencode($proc_open)."&exec_php=".urlencode($exec_php)."&".
                     "exec_cli=".urlencode($exec_cli)."&ftp_cli=".urlencode($ftp_cli)."&php=".urlencode($php)."&".
                     "sql_cli_status=".urlencode($sql_cli_status)."&sql_cli_dump_status=".urlencode($sql_cli_dump_status));
  }
  #else if ($exec_cli != $yes || $sql_cli_status != $yes || $sql_cli_dump_status != $yes)
  else if ($sql_dump_status != $yes)
  {
    print "ok,error";
    kyplex_fetch_direct_url("http://management.kyplex.com/plugin-support.php?module=htest&status=ok,error&".
                     "email=$kyplex_account_email&apikey=$kyplex_api_key&site=$kyplex_site&".
                     "ftp=".urlencode($ftp)."&exec=".urlencode($exec)."&sql_dump_status=".urlencode($sql_dump_status)."&".
                     "proc_open=".urlencode($proc_open)."&exec_php=".urlencode($exec_php)."&".
                     "exec_cli=".urlencode($exec_cli)."&ftp_cli=".urlencode($ftp_cli)."&php=".urlencode($php)."&".
                     "sql_cli_status=".urlencode($sql_cli_status)."&sql_cli_dump_status=".urlencode($sql_cli_dump_status));
  } else {
    print "ok,ok";
  }
  exit;
}


?>
