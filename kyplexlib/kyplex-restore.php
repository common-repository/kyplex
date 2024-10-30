<?php

global $debug;
global $failed;
global $restored;
global $num_errors;
global $max_restored;
$kyplex_req = 0;
$debug = 0;
$num_errors = 0;
$failed = array();
$restored = array();
$max_restored = 10;

global $kyplex_cron;
if (!isset($kyplex_cron) && PHP_SAPI !== 'cli')
{
  print "You can run this script from shell only.\n\n";
  print "Example: php ./kyplex-restore.php --db --req=1234 --debug\n";
  exit;
}

@include_once(dirname(realpath(__FILE__))."/kyplex-common.php");

$long = array("site", "db", "site", "debug", "req:", "host:","hostip:");
$options = _getopt("", $long);
if (isset($options['debug']))
  $debug = 1;
if (isset($options['db']))
  $method = 'db';
else if (isset($options['site']))
  $method = 'site';
else
{
  print "ERROR: Restore method not specified.\n\n";
  print "Example: php ./kyplex-restore.php --db --req=1234 --debug\n";
  exit;
}

if (isset($options['req']) && intval($options['req']) > 0)
{
  $kyplex_req = intval($options['req']);
} else {
  if ($debug)
  {
    echo "--req= can not be empty\n";
  }
  exit;
}
global $kyplex_host;
global $kyplex_host_ip;

if (isset($options['host']) && strlen($options['host']) > 0)
  $kyplex_host = $options['host'];
if (isset($options['hostip']) && strlen($options['hostip']) > 0)
  $kyplex_host_ip = $options['host'];

$kyplex_dir = dirname(dirname(realpath(__FILE__)));
@include_once($kyplex_dir."/kyplex-conf.php");

$ftp_user = $kyplex_account_email;
$ftp_pass = $kyplex_api_key;
$ftp_port = 21;
$ftp_srv = "ftp.kyplex.com";
$backup_dir = realpath($backup_dir);

@set_time_limit(0);
@ini_set("memory_limit","64M");

#print "ftp user: $ftp_user $ftp_pass\n";
$ftp = @ftp_connect($ftp_srv, $ftp_port);
if (!@ftp_login($ftp, $ftp_user, $ftp_pass))
{
  if ($debug)
  {
    echo "failed to login\n";
  }
  exit;
}
#ftp_chdir($ftp, $ftp_new);
#exit;
$site = @trim($kyplex_site);
$client_site = $site;
if (!$site)
{
  if ($debug)
  {
    echo "site is empty\n";
  }
  exit;
}
$sinfo = parse_url($site);
if ($sinfo['scheme'] == 'https')
{
  $site = 'https.' . $sinfo['host'];
} else {
  $site = $sinfo['host'];
}

#
# Notify kyplex that restoration started
#
kyplex_fetch_direct_url("http://management.kyplex.com/plugin-status.php?action=restore&".
                 "method=$method&req=$kyplex_req&pid=".getmypid()."&email=$kyplex_account_email&apikey=$kyplex_api_key&site=$client_site");


$site = kyplex_normalize_filename($site, false);
$db_restore_from = "/$site/restore-db-$kyplex_req";
$site_restore_from = "/$site/restore-site-$kyplex_req";

if ($debug)
{
  print "method: $method\n";
}
if ($method == "all" || $method == "db")
{
  kyplex_restore_database($ftp, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name, $db_restore_from);
  if ($debug)
  {
    echo "db backup finished\n";
  }
}

if ($method == "all" || $method == "site")
{
  kyplex_restore_files($ftp, $backup_dir, $backup_dir, $site_restore_from);
  if ($debug)
  {
    echo "file backup finished\n";
  }
}

#
# Notify kyplex that restoration finished
#
$restored_files = implode(";", $restored);
$failed_files = implode(";", $failed);
kyplex_fetch_direct_url("http://management.kyplex.com/plugin-status.php?action=restored&".
                 "method=$method&errors=$num_errors&req=$kyplex_req&email=$kyplex_account_email&apikey=$kyplex_api_key&site=$client_site&restored=$restored_files&failed=$failed_files");

exit;

function kyplex_restore_files($ftp, $start_dir, $local_dir, $site_restore_from)
{
  global $debug;
  global $failed;
  global $num_errors;
  global $restored;
  global $max_restored;
  $res = true;

  if ($debug)
  {
    echo "restore : $site_restore_from\n";
  }
  $restore_files = kyplex_parse_file_list($ftp, $site_restore_from);

  $files = array();
  $dirs = array();

  if (!isset($restore_files) || count($restore_files) == 0)
  {
    @ftp_delete($ftp, $site_restore_from.'/attrib');
    if ($debug)
    {
      echo "deleting $site_restore_from\n";
    }
    @ftp_rmdir($ftp, $site_restore_from);
    return;
  }
  foreach ($restore_files as $fname =>$fobj)
  {
    $filename = $local_dir . $fname;
    $ftype= $fobj['type'];
    if ($debug)
    {
      echo "restore : $site_restore_from/$fname -> $local_dir/$fname\n";
      print_r($fobj);
    }

    if ($ftype == 'd')
    {
      $res = true;
      if (file_exists($local_dir.'/'.$fname))
      {
        if (is_dir($local_dir.'/'.$fname))
        {
          # directory exists
          $res = true;
        } else {
          # try to unlink file, if it is not a dir
          @unlink($local_dir.'/'.$fname);
          $res = @mkdir($local_dir.'/'.$fname);
          if ($res && count($restored) < $max_restored)
            $restored[] = $local_dir.'/'.$fname;
          else if (!$res && count($failed) < $max_restored)
            $failed[] = $local_dir.'/'.$fname;
        }
      } else {
        $res = @mkdir($local_dir.'/'.$fname);
        if ($res && count($restored) < $max_restored)
          $restored[] = $local_dir.'/'.$fname;
        else if (!$res && count($failed) < $max_restored)
          $failed[] = $local_dir.'/'.$fname;

      }
      if (!$res)
      {
        $num_errors++;
      } else {
        $dirs[] = $fname;
        @chmod($local_dir.'/'.$fname, $fobj['mode']);
        @chown($local_dir.'/'.$fname, $fobj['uid']);
        @chgrp($local_dir.'/'.$fname, $fobj['gid']);
      }
    }
    else if ($ftype == 'f')
    {
      $ftype = 'f';
      $res = @ftp_get($ftp, $local_dir.'/'.$fname, $site_restore_from.'/'.kyplex_normalize_filename($fname), FTP_BINARY);
      if ($debug)
      {
        echo "restoring $local_dir/$fname from $site_restore_from/".kyplex_normalize_filename($fname).
             " . Result: " .(($res)? "ok": "failed")."\n";
      }
      @ftp_delete($ftp, $site_restore_from.'/'.kyplex_normalize_filename($fname));
      if (!$res)
      {
        $num_errors++;
        if (count($failed) < $max_restored)
          $failed[] = $local_dir.'/'.$fname;
      } else {
        if (count($restored) < $max_restored)
            $restored[] = $local_dir.'/'.$fname;
        @chmod($local_dir.'/'.$fname, $fobj['mode']);
        @chown($local_dir.'/'.$fname, $fobj['uid']);
        @chgrp($local_dir.'/'.$fname, $fobj['gid']);
      }
    }
    else if ($ftype == 'l')
    {
      $temp = tmpfile();
      @ftp_get($ftp, $temp, $site_restore_from.'/'.kyplex_normalize_filename($fname), FTP_BINARY);
      @ftp_delete($ftp, $site_restore_from.'/'.kyplex_normalize_filename($fname));
      @fseek($temp, 0);
      $target = @fread($temp, 1024);
      @fclose($temp);
      if ($target)
      {
        # try to remove old link if exists
        @unlink($local_dir.'/'.$fname);
        $res = @symlink($target, $local_dir.'/'.$fname);
        if ($debug)
        {
          echo "restoring link: " . $local_dir.'/'.$fname . " -> $target\n";
        }
        if (!$res)
        {
          $num_errors++;
          if (count($failed) < $max_restored)
            $failed[] = $local_dir.'/'.$fname;
        } else {
          if (count($restored) < $max_restored)
            $restored[] = $local_dir.'/'.$fname;
        }
      }
    } else 
    {
      if ($debug)
      {
        print "ignoring file $filename : $ftype\n";
      }
      continue;
    }
  }

  shuffle($dirs);
  foreach($dirs as $d=>$dir)
  {
    kyplex_restore_files($ftp, $start_dir, $local_dir . '/'. $dir, $site_restore_from . '/' . $dir );
    #echo "checking $start_dir, $local_dir . $dir . '/', $site_restore_from . '/' . $dir\n";
  }
  @ftp_delete($ftp, $site_restore_from.'/attrib');
  if ($debug)
  {
    echo "deleting $site_restore_from\n";
  }
  @ftp_rmdir($ftp, $site_restore_from);
}

function kyplex_restore_database($ftp, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name, $db_restore_from)
{
  global $mysql;
  global $psql;
  global $debug;
  global $failed;
  global $restored;
  global $num_errors;
  global $max_restored;

  $cmd = '';
  $download = false;

  if ($debug)
  {
    echo "restore : $db_restore_from\n";
  }
  $restore_files = kyplex_parse_file_list($ftp, $db_restore_from);

  $descriptorspec = array(
    0 => array("pipe", "r"),  // stdin
    1 => array("pipe", "w"),  // stdout
    2 => array("pipe", "w")   // error
  );
  $env= array();
  if ($db_type == 'mysql')
  {
    $mysql = kyplex_get_mysql_location($mysql);
    if ($db_port > 0 && $db_port != 3306)
      $cmd = $mysql.' -b -s -h ' . escapeshellarg($db_srv) . ' -P ' . @intval($db_port);
    else
      $cmd = $mysql.' -b -s -h '.escapeshellarg($db_srv);
    $cmd .= ' -u '. escapeshellarg($db_user). ' -p'.escapeshellarg($db_pass).
            ' ' . escapeshellarg($db_name);
  } else if ($db_type == 'pgsql') {
    $psql = kyplex_get_psql_location($psql);
    //$cmd = 'export PGPASSWORD='.escapeshellarg($db_pass).'; ';
    $env['PGPASSWORD'] = $db_pass;
    $cmd = $psql.' -t -q --pset pager --host='.escapeshellarg($db_srv);
    if ($db_port > 0 && $db_port != 5432)
      $cmd .= ' --port=' . @intval($db_port);
    $cmd .= ' --username=' . escapeshellarg($db_user) . ' -d '.
         escapeshellarg($db_name) . ' -t ' . escapeshellarg($table);
  }

  if (!isset($restore_files) || count($restore_files) == 0)
  {
    @ftp_delete($ftp, $db_restore_from.'/attrib');
    if ($debug)
    {
      echo "deleting $db_restore_from\n";
    }
    @ftp_rmdir($ftp, $db_restore_from);
    return;
  }


  foreach ($restore_files as $fname =>$fobj)
  {
    $ftype= $fobj['type'];
    if ($debug)
    {
      echo "restore : $db_restore_from/$fname -> $db_type://$db_srv:$db_port/$db_name/$fname\n";
      print_r($fobj);
    }

    if ($ftype == 'f' && substr($fname, -4) == '.sql')
    {
      #@ftp_get($ftp, $local_dir.'/'.$fname, $site_restore_from.'/f'.$fname, FTP_BINARY);
      #@ftp_delete($ftp, $db_restore_from.'/f'.$fname);
      $fsize = @ftp_size($ftp, $db_restore_from.'/'. kyplex_normalize_filename($fname));
      if (!isset($fsize) || $fsize == -1 || $fsize == 0)
      {
        continue;
      }

      if ($debug)
      {
        echo "running: $cmd\n";
      }
      $process = @proc_open($cmd, $descriptorspec, $pipes, '', $env);
      if (!is_resource($process))
      {
        if ($debug)
        {
          echo "failed to run mysql/psql db shell\n";
        }
        return 0;
      }
      #fwrite($pipes[0], $ciphertext);
      #fclose($pipes[1]);

      #Debug test to proce that $pipes[1] is a valid stream
      #$content = '';
      #while(!feof($pipes[1])) {
      #  $content =  fgets($pipes[1],1024);
      #  echo "read: $content\n";
      #}
      $res = @ftp_fget($ftp, $pipes[0], $db_restore_from.'/'. kyplex_normalize_filename($fname), FTP_BINARY);
      fclose($pipes[0]);
      fclose($pipes[1]);
      fclose($pipes[2]);
      if ( function_exists('memory_get_usage') ) {
        memory_get_usage(true);
      }
      # Check upload status
      if ($debug)
      {
        echo ('downloaded '. ($res ? 'true':' false')."\n");
      }
      if (!$res)
      {
        $num_errors++;
        if (count($failed) < $max_restored)
          $failed[] = substr($fname, 0, -4);
      } else if ($res && count($restored) < $max_restored) {
        $restored[] = substr($fname, 0, -4);
      }

      @ftp_delete($ftp, $db_restore_from.'/'. kyplex_normalize_filename($fname));
    } else {
      # we do not handle other file types !!!
      if ($debug)
      {
         echo "ignoring file: $fname ($ftype)\n";
         if ($ftype == "d")
         {
           @ftp_rmdir($ftp, $db_restore_from.'/'.kyplex_normalize_filename($fname));
         } else {
           @ftp_delete($ftp, $db_restore_from.'/'.kyplex_normalize_filename($fname));
         }
      }
    }
  }
  @ftp_delete($ftp, $db_restore_from.'/attrib');
  if ($debug)
  {
    echo "deleting $db_restore_from\n";
  }
  @ftp_rmdir($ftp, $db_restore_from);
}

function kyplex_normalize_filename($file, $append=true)
{
  $file = str_replace("/", "%x2f", $file);
  $file = str_replace("%", "%x25", $file);
  if ($append)
    return "f".$file;
  return $file;
}

?>
