<?php

@include_once(dirname(realpath(__FILE__))."/kyplex-common.php");

$action = @strtolower(@trim($_GET['action']));
$db_ok = @intval(@trim($_GET['db_plugin']));
$site_ok = @intval(@trim($_GET['site_plugin']));
$ignore = @strtolower(@trim($_GET['ignore']));
$pid = @intval(@trim($_GET['pid']));
$php = kyplex_get_php_location();
$host = '';
global $kyplex_cron;
$kyplex_cron = 1;
if (isset($_SERVER['HTTP_HOST']) && strlen($_SERVER['HTTP_HOST']) > 0)
{
  $host = $_SERVER['HTTP_HOST'];
} else if (isset($_SERVER['SERVER_NAME']) && strlen($_SERVER['SERVER_NAME']) > 0)
{
  $host = $_SERVER['SERVER_NAME'];
}
$hostip = '';
if (isset($_SERVER['SERVER_ADDR']) && strlen($_SERVER['SERVER_ADDR']) > 0)
{
  $hostip = $_SERVER['SERVER_ADDR'];
}

if ($action == "restorefile")
{
  kyplex_restore_file($_POST['filename'], $_POST['base64'], $_POST['fsize'], $_POST['group'], $_POST['owner'], $_POST['perm']);
  exit;
} else if ($action == "deletefile")
{
  kyplex_delete_file($_POST['filename']);
  exit;
}

if ($action == "pause" && $pid > 0)
{
  print "stopping";
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
  {
    $out = array();
    @exec("taskkill /f /PID $pid 2>&1", $out);
    `taskkill /f /PID $pid 2>&1`;
  } else {
    @posix_kill($pid, 9);
  }
  exit;
}

#
# Disable process alive check. It turned out that it does not works good in Linux.
#
if (0 && $pid > 0)
{
  # first check if old backup script is running
  if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
  {
    # in windows posix_kill not supported
    # we will use shell commands instead
    $out = array();
    $res = @exec("tasklist /FI \"PID eq $pid\"", $out);
    if (!$res)
    {
      # failed to check, assuming dead
    } else {
      $result = join("\n", $out);
      if (strpos($result, $pid) !== FALSE)
      {
        print "alive\n";
        exit; 
      }
    }
    # no process found, we will start a new backup
  } else {
    $res = posix_kill($pid, 0);
    if ($res !== FALSE)
    {
      print "alive\n";
      exit;
    }
    $error = posix_get_last_error();
    // in linux if errno is 3 - no such process
    if ($error != 3)
    {
      print "alive\n";
      exit;
    }
  }
}

print "starting\n";

if ($action == "restore")
{
  $req = @intval(@trim($_GET['req']));
  $cmd = '';

  if ($req == 0)
    exit;
  if ($db_ok == 0 && $site_ok == 0)
    exit;

  if (!test_exec($php))
  {
    if ($db_ok > 0)
    {
      $GLOBALS['argv'] = array('--db', "--req=$req");
    } else if ($site_ok > 0)
    {
      $GLOBALS['argv'] = array('--site', "--req=$req");
    }
    @include_once(dirname(realpath(__FILE__))."/kyplex-restore.php");
    exit;
  }

  if ($req > 0 && $db_ok > 0)
  {
    $cmd = "$php ./kyplex-restore.php --db --req=$req";
  } else if ($req > 0 && $site_ok > 0)
  {
    $cmd = "$php ./kyplex-restore.php --site --req=$req";
  }
  if ($cmd == '')
    exit;
  if (isset($host) && strlen($host) > 0)
  {
    $cmd .= " --host=$host --hostip=$hostip";
  }
  if (stripos(PHP_OS,"win") === false)
  {
    @exec($cmd." > /dev/null 2>&1 & echo $!", $res);
  } else {
    @pclose(@popen("start $cmd","r"));
  }
  exit;
}

# php ./kyplex-backup.php > /dev/null 2>&1
$cmd = '';
if ($db_ok == 0 && $site_ok == 0)
{
  exit;
}

if (!test_exec($php))
{
  if ($db_ok > 0 && $site_ok > 0)
  {
    $GLOBALS['argv'] = array('--all');
  } else if ($db_ok > 0) {
    $GLOBALS['argv'] = array('--db');
  } else if ($site_ok > 0) {
    $GLOBALS['argv'] = array('--site');
  }
  if ($ignore)
  {
    $GLOBALS['argv'][] = "--ignore=$ignore";
  }
  @include_once(dirname(realpath(__FILE__))."/kyplex-backup.php");
  exit;
}

if ($db_ok > 0 && $site_ok > 0)
{
  $cmd = "$php ./kyplex-backup.php --all";
} else if ($db_ok > 0) {
  $cmd = "$php ./kyplex-backup.php --db";
} else if ($site_ok > 0) {
  $cmd = "$php ./kyplex-backup.php --site";
}

if ($cmd == '')
{
  exit;
}
if (isset($host) && strlen($host) > 0)
{
  $cmd .= " --host=$host --hostip=$hostip";
}
if ($ignore)
{
  $cmd .= "  -ignore=$ignore";
}
if (stripos(PHP_OS,"win") === false)
{
  @exec($cmd." > /dev/null 2>&1 & echo $!", $res);
} else {
  @pclose(@popen("start $cmd","r"));
}

function test_exec($php)
{
  # temproary fix for the bug
  # in some distibutions the next code somehow will lead to recursive execution of the same script !!!
  return 0;

  $res = array();
  @exec("$php ".dirname(realpath(__FILE__))."/kyplex-cron.php", $res);
  $result = @join("\n", $res);

  # uncomment the next line to load internal process only
  #return 0;
  if (!isset($result) || $result == '')
  {
    return 0;
  } else if (strpos($result, "starting") !== false)
  {
    return 1;
  }
  return 0;
}

?>
