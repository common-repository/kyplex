<?php

global $debug;
global $kyplex_cron;
$debug = 0;
if (!isset($kyplex_cron) && PHP_SAPI !== 'cli')
{
  echo "You can run this script from shell only.\n\n";
  echo "Example: php ./kyplex-backup.php --all\n";
  exit;
}

@include_once(dirname(realpath(__FILE__))."/kyplex-common.php");

$long = array("all", "site", "db", "site", "debug", "req:", "host:", "hostip:", "ignore:");
$options = _getopt("", $long);
if (isset($options['debug']))
  $debug = 1;
if (isset($options['all']))
  $method = 'all';
else if (isset($options['db']))
  $method = 'db';
else if (isset($options['site']))
  $method = 'site';
else 
{
  echo "ERROR: Backup method not specified.\n\n";
  echo "Example: php ./kyplex-backup.php --all\n";
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
$backup_dir = realpath($backup_dir).'/';

$ignore_ext = array();
if (isset($options['ignore']))
{
  foreach (explode(",", $options['ignore']) as $ext)
  {
    $ext = @trim($ext);
    if (strlen($ext) > 0)
      $ignore_ext[$ext] = $ext;
  }
}

@set_time_limit(0);
@ini_set("memory_limit","64M");

$ftp = @ftp_connect($ftp_srv, $ftp_port);
if (!$ftp)
{
  if ($debug)
  {
    echo "failed to connect to remote ftp server\n";
  }
  exit;
}
#echo "ftp user: $ftp_user $ftp_pass\n";
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
#create directory for our website
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
# Notify kyplex that backup started
#
kyplex_fetch_direct_url("http://management.kyplex.com/plugin-status.php?action=start&".
                 "method=$method&pid=".getmypid()."&email=$kyplex_account_email&apikey=$kyplex_api_key&site=$client_site");

$site = kyplex_normalize_filename($site, false);
$db_new = "/$site/db";
@ftp_mkdir($ftp, $site);
@ftp_mkdir($ftp, $db_new);
$ftp_new = "/$site/site";
@ftp_mkdir($ftp, $ftp_new);

if ($debug)
{
  echo "method: $method\n";
}

@srand(@time());
$site_first = @rand(0,1);

if ($debug)
{
  $site_first = 1;
  //$full_resume_dir = "wp-content/plugins";
}
$full_resume_dir = kyplex_load_current_dir($ftp, $ftp_new);
if ($debug && $full_resume_dir)
{
  echo "loaded restore point: $full_resume_dir\n";
}
if ($site_first > 0 && ($method == "all" || $method == "site"))
{
  kyplex_backup_files($ftp, $backup_dir, $backup_dir, $ftp_new, $full_resume_dir, $ignore_ext);
  if ($debug)
  {
    echo "file backup finished\n";
  }
} else {
  $site_first = 0;
}


if ($method == "all" || $method == "db")
{
  if ($db_type == "mysql")
  {
    if ($db_port == 3306)
    {
      $db = @mysql_connect($db_srv, $db_user, $db_pass);
    } else {
      $db = @mysql_connect($db_srv.':'.$db_port, $db_user, $db_pass);
    }
    if (!$db)
    {
      if ($debug)
      {
        echo "Failed to connect to MySQL database\n";
      }
      exit;
    }
    $result = @mysql_select_db($db_name, $db);
    if (!$result)
    {
      if ($debug)
      {
        echo "Failed to switch db\n";
      }
      exit;
    }
  } else if ($db_type == "pgsql")
  {
    $con_str = "host=$db_srv dbname=$db_name user=$db_user password=$db_pass options='--client_encoding=UTF8'";
    if (isset($db_port) && $db_port != 0)
    {
      $con_str = "port=$db_port ".$con_str;
    }
    $db = @pg_connect($con_str);
    if (!$db)
    {
      if ($debug)
      {
        echo "Failed to connect to PostgreSQL database.\n";
      }
      exit;
    }
  }
  kyplex_backup_database($ftp, $db, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name, $db_new, $db_prefix);
  if ($db_type == "mysql")
  {
    @mysql_close($db);
  } else if ($db_type == "pgsql")
  {
  
  }
  if ($debug)
  {
    echo "db backup finished\n";
  }
}

if ($site_first == 0 && ($method == "all" || $method == "site"))
{
  kyplex_backup_files($ftp, $backup_dir, $backup_dir, $ftp_new, $full_resume_dir, $ignore_ext);
  if ($debug)
  {
    echo "file backup finished\n";
  }
}

#
# Notify kyplex that backup finished
#
kyplex_save_current_dir($ftp, $ftp_new, "");
kyplex_fetch_direct_url("http://management.kyplex.com/plugin-status.php?action=finish&".
                 "method=$method&email=$kyplex_account_email&apikey=$kyplex_api_key&site=$client_site");

exit;

function kyplex_backup_files($ftp, $website_parent_dir, $local_dir, $ftp_dir, $full_resume_dir = '', &$ignore_ext)
{
  global $debug;
  global $ftp_new;
  if ($debug)
  {
    echo "backup : $local_dir\n";
    if ($full_resume_dir != '')
    {
      echo "resume backup dir: $full_resume_dir\n";
    }
  }
  #$old_files = kyplex_parse_file_list($ftp, $ftp_old);

  $dh = opendir($local_dir); 
  $files = array();
  $dirs = array();
  $resume_dir = '';
  $child_resume_dir = '';
  $found_resume_dir = 0;

  if ($full_resume_dir != '')
  {
    list($resume_dir, $child_resume_dir) = explode("/", $full_resume_dir, 2);
    echo "next resume dir: $child_resume_dir\n";
  }
  while (false !== ($file = readdir($dh))) { 
    if (in_array($file, array('.','..')))
      continue;
    if (strlen($file) == 0)
      continue;
    $filename = $local_dir . $file;

    $ftype = @filetype($filename);

    if ($full_resume_dir != '')
    {
      if ($ftype == 'dir')
      {
        if ($resume_dir == $file)
        {
          $found_resume_dir = 1;
        }
        if (strcmp($resume_dir, $file) >= 0)
        {
          $dirs[] = $file;
        }
        else if ($debug)
        {
          echo "ignore dir: $file\n";
        } 
        continue;
      } else {
        # no need to load these files, we have done it in previous not finished backup
        continue;
      }
      
    }
    $finfo = @stat($filename);
    if (!$finfo)
      continue;

    if ($ftype == 'dir')
    {
      $ftype = 'd';
      $dirs[] = $file;
    }
    else if ($ftype == 'file')
      $ftype = 'f';
    else if ($ftype == 'link')
    {
      $target = @readlink($filename);
      if ($target)
        $ftype = "l $target";
      else
        $ftype = "l";
      $finfo['size'] = @strlen($target);
    } else 
    {
      if ($debug)
      {
        echo "ignore file $filename : $ftype\n";
      }
      continue;
    }
    #echo "file: $filename : $ftype\n";
    $file_object = array(
                       #'name' => $file,
                       'size' => $finfo['size'],
                       'mode' => $finfo['mode'],
                       'type' => $ftype,
                       'mtime' => $finfo['mtime'],
                       'inode' => $finfo['ino'],
                       );
    $uid = $finfo['uid'];
    if (isset($uid))
    {
      if (function_exists('posix_getpwuid'))
      {
        $uid_str = @posix_getpwuid($uid);
        if (isset($uid_str) && strlen($uid_str['name']) > 0)
          $uid = $uid_str['name'];
      }
      $file_object['uid'] = $uid;
    }
    $gid = $finfo['gid'];
    if (isset($gid))
    {
      if (function_exists('posix_getgrgid'))
      {
        $gid_str = @posix_getgrgid($gid);
        if (isset($gid_str) && strlen($gid_str['name']) > 0)
          $gid = $gid_str['name'];
      }
      $file_object['gid'] = $gid;
    }
    # it is too heavy to do it on each new file !
    # we need to check inode instead
    #if ($ftype == 'f' && $finfo['size'] > 0 && $finfo['size'] < 1024*1024*100)
    #{
    #  $file_object['sha1'] = sha1_file($filename);
    #}

    $files[$file] = $file_object;
  }
  closedir($dh);

  if ($full_resume_dir == '')
  {
    $new_old_files = kyplex_parse_file_list($ftp, $ftp_dir);

    # at first we will remove from ftp mirror all files and subdirectories that were removed
    if (isset($new_old_files) && count($new_old_files) > 0)
    {
      foreach ($new_old_files as $fname => $old)
      {
        if (!array_key_exists($fname, $files))
        {
          if ($debug)
          {
            echo "file deleted: $fname\n";
          }
          $old = $new_old_files[$fname];
          if ($old['type'] == 'd')
          {
            # try to delete this directory from ftp server
            # run recursive directory delete function
            $cdir = @ftp_pwd($ftp);
            kyplex_ftp_del_dir($ftp, $ftp_dir.'/'.kyplex_normalize_filename($fname));
            if ($cdir != '')
            {
              @ftp_chdir($ftp, $cdir);
            }
          } else {
            @ftp_delete($ftp, $ftp_dir.'/'.kyplex_normalize_filename($fname));
          }
          unset($new_old_files[$fname]);
        }
      }
    }

    # go over new files and upload them
    $new_files_counter = 0;
    foreach ($files as $fname =>$fobj)
    {
      if ($fobj['type'] == 'd')
      {
        # check if we created this directory in previous not finished backup
        if (! (isset($new_old_files) && count($new_old_files) > 0 && array_key_exists($fname, $new_old_files) && $new_old_files[$fname]['type'] =='d') )
        {
          @ftp_mkdir($ftp, $ftp_dir.'/'. kyplex_normalize_filename($fname) );
          $new_files_counter++;
          $new_old_files[$fname] = $fobj;
        }
      }
      # check if current file exists in previous old backup
      #if (isset($old_files) && count($old_files) > 0 && array_key_exists($fname, $old_files))
      #{
      #  $old = $old_files[$fname];
      #  if ($old['type'] != $fobj['type'] || $old['size'] != $fobj['size'] ||
      #     (isset($old['sha1']) && isset($fobj['sha1'])&& $old['sha1'] != $fobj['sha1']))
      #  {
      #    echo "file changed: $fname\n";
      #  } else {
      #    echo "file known: $fname\n";
      #    continue;
      #  }
      #} else 
      # check if current file exists in previoud not finished backup
      if (isset($new_old_files) && count($new_old_files) > 0 && array_key_exists($fname, $new_old_files))
      {
        $old = $new_old_files[$fname];

        if ($old['type'] != $fobj['type'] || $old['size'] != $fobj['size'] ||
          (isset($old['inode']) && isset($fobj['inode']) && $old['inode'] != $fobj['inode']) ||
          ((!isset($old['inode']) || isset($old['inode']) == 0) && isset($fobj['inode']) && $fobj['inode'] != 0 ) )
        {
          if ($debug)
          {
            echo "file changed: $fname\n";
          }
          $ret = kyplex_check_good_extension($fname, $ignore_ext);
          if ($ret == 0)
          {
            // we will not upload new file version because we need to ignore this file' extension
            $fobj = $old;
            if ($debug)
            {
              echo "ignore changed file: $fname because of file extention\n";
            }
            $files[$fname] = $old;
            continue;
          } else {
            if ($fobj['type'] == 'f' && $fobj['size'] > 0 && $fobj['size'] < 1024*1024*100)
            {
              $fobj['sha1'] = sha1_file($local_dir.$fname);
              $files[$fname]['sha1'] = $fobj['sha1'];
            }
            $new_files_counter++;
            $new_old_files[$fname] = $fobj;
          }
        } else {
          if ($debug)
          {
            echo "file known: $fname\n";
          }
          continue;
        }
      } else {
        if ($debug)
        {
          echo "new file found: $fname\n";
        }
        // check if we need to ignore this file extension
        $ret = kyplex_check_good_extension($fname, $ignore_ext);
        if ($ret == 0)
        {
          if ($debug)
          {
            echo "ignore new file: $fname because of file extention\n";
          }
          unset($files[$fname]);
          continue;
        }
        if ($fobj['type'] == 'f' && $fobj['size'] > 0 && $fobj['size'] < 1024*1024*100)
        {
          $fobj['sha1'] = sha1_file($local_dir.$fname);
          $files[$fname]['sha1'] = $fobj['sha1'];
        }
        $new_files_counter++;
        $new_old_files[$fname] = $fobj;
      }
      if ($fobj['type'] == 'f')
      {
        $ret = @ftp_put($ftp, $ftp_dir.'/'. kyplex_normalize_filename($fname), $local_dir.$fname, FTP_BINARY);
        if ($ret)
        {
          if ($debug)
          {
            echo "uploading ".$local_dir.$fname." to ".$ftp_dir.'/'. kyplex_normalize_filename($fname)."\n";
          }
        } else {
          unset($new_old_files[$fname]);
          if ($debug)
          {
            echo "failed to upload ".$local_dir.$fname." to ".$ftp_dir.'/'. kyplex_normalize_filename($fname)."\n";
          }
        }
      } else if (substr($fobj['type'], 0,2) == "l ")
      {
        # create a link file in remote site
        $temp = tmpfile();
        # write link destination to the file
        fwrite($temp, substr($fobj['type'],2));
        fseek($temp, 0);
        @ftp_fput($ftp, $ftp_dir.'/'. kyplex_normalize_filename($fname), $temp, FTP_BINARY);
        fclose($temp);
        $files[$fname]['type'] = 'l';
      }
      if ($new_files_counter > 30)
      {
        if ( function_exists('memory_get_usage') ) {
            memory_get_usage(true);
        }

        # upload file list if we have a lot of new files in the same directory
        if ($debug)
        {
          echo "uploading big file list.\n";
        }
        kyplex_create_file_list($ftp, $ftp_dir, $new_old_files);
        $new_files_counter = 0;
      }
    }

    #echo_r($files);
    if ($new_files_counter != 0)
    {
      kyplex_create_file_list($ftp, $ftp_dir, $files);
    }
    kyplex_save_current_dir($ftp, $ftp_new, substr($local_dir, strlen($website_parent_dir))); 
  }

  sort($dirs, SORT_STRING);
  $dirs = array_reverse($dirs);
  if ($found_resume_dir == 0 && $full_resume_dir != '')
  {
    if ($debug && $child_resume_dir != '')
    {
      echo "resume dir is missing !\n";
    }
    $child_resume_dir = '';
  }
  #var_dump($dirs);
  #shuffle($dirs);
  foreach($dirs as $d=>$dir)
  {
    if ($resume_dir == $dir)
    {
      kyplex_backup_files($ftp, $website_parent_dir, $local_dir . $dir . '/', 
                 $ftp_dir . '/' . kyplex_normalize_filename( $dir ), $child_resume_dir, $ignore_ext );
    } else {
      kyplex_backup_files($ftp, $website_parent_dir, $local_dir . $dir . '/',
                 $ftp_dir . '/' . kyplex_normalize_filename( $dir ), '', $ignore_ext );
    }
    #echo "checking $website_parent_dir, $local_dir . $dir . '/', $ftp_old . '/f' . $dir, $ftp_new . '/f' . $dir\n";
  }
}

function kyplex_check_good_extension($fname, &$ignore_ext)
{
  global $debug;
  if (count($ignore_ext) == 0)
    return 1;
  $ext_pos = strrpos($fname,".");
  if ($ext_pos > 0)
  {
    $f_ext = substr($fname, $ext_pos+1);
    if (isset($ignore_ext[$f_ext]))
    {
      if ($debug)
      {
        //echo "file extension is in exclude list: $f_ext !\n";
      }
      return 0;
    }
  }
  return 1;
}
# the following is FTP recursive directory delete function
function kyplex_ftp_del_dir($ftp, $ftp_dir)
{
  global $debug;
  if ($debug)
  {
    echo "ftp delete $ftp_dir\n";
  }
  if (!@ftp_delete($ftp, $ftp_dir) && !@ftp_rmdir($ftp, $ftp_dir))
  {
    if ($debug)
    {
      echo "failed to delete $ftp_dir, probably a dir\n";
    }
    if ($children = @ftp_nlist($ftp, $ftp_dir)) {
      foreach ($children as $p)
      {
        kyplex_ftp_del_dir($ftp,  $p);
      }
    }
    if ($debug)
    {
      echo "ftp delete dir $ftp_dir\n";
    }
    @ftp_rmdir($ftp, $ftp_dir);
  }
}

function kyplex_save_current_dir($ftp, $ftp_dir, $dir)
{
  global $debug;
#  if ($dir == "")
#  {
#    echo "------------------------------ DIR IS EMPTY --------------\n";
#    return;
#  }
  $temp = tmpfile();
  fwrite($temp, $dir);
  fseek($temp, 0);
  $fname = $ftp_dir.'/current-dir.txt';
  ftp_fput($ftp, $fname, $temp, FTP_BINARY);
  fclose($temp);
  if ($debug)
  {
    echo "uploading $fname [$dir]\n";
  }
}
function kyplex_load_current_dir($ftp, $ftp_dir)
{
  global $debug;
  $temp = tmpfile();
  $fname = $ftp_dir.'/current-dir.txt';
  if ($debug)
  {
    echo "reading $fname\n";
  }
  $res = @ftp_fget($ftp, $temp, $fname, FTP_BINARY);
  if (!$res)
  {
    if ($debug)
    {
      echo "file not found : $fname\n";
    }
    return;
  }
  fseek($temp, 0);
  $data = fread($temp, 10240);
  fclose($temp);
  if ($data == "")
    return $data;
  $data = trim($data);
  if (substr($data, 0, 1) == "/")
    $data = substr($data, 1);
  return $data;
}

// this function dumps list of files in format that is known by kyplex backup service
function kyplex_create_file_list($ftp, $dir, $files)
{
  global $debug;

  ksort($files);
  $data = '<'.'?xml version="1.0" encoding="UTF-8"?'.'><files>'."\r\n";
  foreach ($files as $f => $info)
  {
    if (!isset($f))
      continue;
    if (strlen($f) == 0)
      continue;
    if ($f == "." || $f == "..")
      continue;
    $fsize = $info['size'];
    if ( $fsize > (4096 * 1024 * 1024) )
    {
      $info['sizeMod4GB'] = @intval($fsize % (4096 * 1024 * 1024));
      $info['sizeDiv4GB'] = @intval($fsize / (4096 * 1024 * 1024));
    } else {
      $info['sizeDiv4GB'] = 0;
      $info['sizeMod4GB'] = $fsize;
    }
    $l ='<r><f='.$f.'/><t='.$info['type'].'/><c='.$info['mtime'].'/>'.
        '<d='.$info['sizeDiv4GB'].'/><m='.$info['sizeMod4GB'].'/>';
    if (isset($info['mode']))  # file permissions
    {
      $l .= '<p='.$info['mode'].'/>';
    }
    if (isset($info['uid']))
    {
      $l .= '<'.'u='.$info['uid'].'/>'.
            '<g='.$info['gid'].'/>';
    }
    if (isset($info['nrecords']))   # number of records - for SQL
    {
      $l .= '<n='.$info['nrecords'].'/>';
    }
    if (isset($info['sha1']) && strlen($info['sha1']) > 0)
    {
      $l .= '<h='.$info['sha1'].'/>';
    }
    if (isset($info['inode']) && @intval($info['inode']) != 0)
    {
      $l .= '<i='.$info['inode'].'/>';
    }
    $l .= "</r>\r\n";
    $data .= $l;
  }
  $data .= '</files>';
  # create temprorary file and save data into it
  $temp = tmpfile();
  fwrite($temp, $data);
  fseek($temp, 0);
  $fname = $dir.'/attrib';
  ftp_fput($ftp, $dir.'/attrib', $temp, FTP_BINARY);
  fclose($temp);
  if ($debug)
  {
    echo "uploading $fname\n";
  }
  return;
}

function get_unix_perms($perms)
{
  //$perms = fileperms($filename);

  if     (($perms & 0xC000) == 0xC000) { $info = 's'; }
  elseif (($perms & 0xA000) == 0xA000) { $info = 'l'; }
  elseif (($perms & 0x8000) == 0x8000) { $info = '-'; }
  elseif (($perms & 0x6000) == 0x6000) { $info = 'b'; }
  elseif (($perms & 0x4000) == 0x4000) { $info = 'd'; }
  elseif (($perms & 0x2000) == 0x2000) { $info = 'c'; }
  elseif (($perms & 0x1000) == 0x1000) { $info = 'p'; }
  else                                 { $info = 'u'; }

  // owner
  $info .= (($perms & 0x0100) ? 'r' : '-');
  $info .= (($perms & 0x0080) ? 'w' : '-');
  $info .= (($perms & 0x0040) ? (($perms & 0x0800) ? 's' : 'x' ) : (($perms & 0x0800) ? 'S' : '-'));

  // group
  $info .= (($perms & 0x0020) ? 'r' : '-');
  $info .= (($perms & 0x0010) ? 'w' : '-');
  $info .= (($perms & 0x0008) ? (($perms & 0x0400) ? 's' : 'x' ) : (($perms & 0x0400) ? 'S' : '-'));

  // other / all
  $info .= (($perms & 0x0004) ? 'r' : '-');
  $info .= (($perms & 0x0002) ? 'w' : '-');
  $info .= (($perms & 0x0001) ? (($perms & 0x0200) ? 't' : 'x' ) : (($perms & 0x0200) ? 'T' : '-'));

  return $info;
}

function kyplex_backup_database($ftp, $db, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name, $db_new, $db_prefix)
{
  global $mysqldump;
  global $pgdump;
  global $debug;
  $new_files = array();

  if ($db_type == 'mysql')
  {
    $mysqldump = kyplex_get_mysqldump_location($mysqldump);
  } else if ($db_type == 'pgsql') {
    $pgdump = kyplex_get_pgdump_location($pgdump);  
  }

  # first create directory for our database
  #@ftp_mkdir($ftp, $db_new.'/'. kyplex_normalize_filename($db_name) );

  # load old backup info
  #$old_files = kyplex_parse_file_list($ftp, $db_old.'/'. kyplex_normalize_filename($db_name) );
  $new_old_files = kyplex_parse_file_list($ftp, $db_new );
  $tables = kyplex_db_show_tables($db, $db_type, $db_name, $db_prefix);
  # first create directory for our database
  #@ftp_mkdir($ftp, $db_new . '/'. kyplex_normalize_filename($db_name) );

  foreach ($tables as $fname => $finfo)
  {
    #if (isset($old_files) && count($old_files) > 0 && array_key_exists($fname, $old_files))
    #{
    #  $old = $old_files[$fname];
    #  if ($old['nrecords'] != $finfo['nrecords'] || $old['mtime'] != $finfo['mtime'])
    #  {
    #    echo "file changed: $fname\n";
    #  } else {
    #    echo "file known: $fname\n";
    #    $new_files[$fname] = $finfo;
    #    continue;
    #  }
    #} else 
    # check if current file exists in previoud not finished backup
    if (isset($new_old_files) && count($new_old_files) > 0 && array_key_exists($fname, $new_old_files)) {
      $old = $new_old_files[$fname];
      if ($old['nrecords'] != $finfo['nrecords'] || $old['mtime'] != $finfo['mtime'])
      {
        if ($debug)
        {
          echo "file changed: $fname\n";
        }
      } else {
        if ($debug)
        {
          echo "file known: $fname\n";
        }
        $new_files[$fname] = $finfo;
        continue;
      }
    } else {
      if ($debug)
      {
        echo "new file found: $fname\n";
      }
    }
    $result = kyplex_backup_table($ftp, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name,
                     $db_new, $finfo['table']);
    if ($result)
    {
      # we fill sale list of attributes eeach time we sucsessfully finish uploading a table
      $new_files[$fname] = $finfo;
      kyplex_create_file_list($ftp, $db_new, $new_files);
    } 
  }
  if (count($new_files) > 0)
    kyplex_create_file_list($ftp, $db_new, $new_files);
}

function kyplex_backup_table($ftp, $db_type, $db_srv, $db_port, $db_user, $db_pass, $db_name, $out_db_dir, $table)
{
  global $mysqldump;
  global $pgdump;
  global $debug;

  //The Descriptors
  $descriptorspec = array(
    0 => array("pipe", "r"),  // stdin
    1 => array("pipe", "w"),  // stdout
    2 => array("pipe", "w")   // error
  );
  $cmd = '';
  $env= array();
  if ($db_type == 'mysql')
  {
    if ($db_port > 0 && $db_port != 3306)
      $cmd = "$mysqldump -h " . escapeshellarg($db_srv) . ' -P ' . @intval($db_port);
    else
      $cmd = "$mysqldump -h ".escapeshellarg($db_srv);
    $cmd .= ' --compact --single-transaction --add-drop-table --skip-extended-insert '.
            '--quote-names --allow-keywords --complete-insert '.
            ' -u '. escapeshellarg($db_user). ' -p'.escapeshellarg($db_pass).
            ' ' . escapeshellarg($db_name). ' ' . escapeshellarg($table);
  } else if ($db_type == 'pgsql') {
    //$cmd = 'export PGPASSWORD='.escapeshellarg($db_pass).'; ';
    $env['PGPASSWORD'] = $db_pass;
    $cmd.= "$pgdump --host=".escapeshellarg($db_srv);
    if ($db_port > 0 && $db_port != 5432)
      $cmd .= ' --port=' . @intval($db_port);
    $cmd .= ' --username=' . escapeshellarg($db_user) . ' -x --inserts --column-inserts --blobs --clean --ignore-version '.
         escapeshellarg($db_name) . ' -t ' . escapeshellarg($table);
  }
  if ($debug)
  {
    echo "running: $cmd\n";
  }
  $process = @proc_open($cmd, $descriptorspec, $pipes, '', $env );
  if (!is_resource($process)) {
    if ($debug)
    {
      echo "failed to run mysqldump/pg_dump\n";
    }
    return 0;
  }
  #fwrite($pipes[0], $ciphertext);
  fclose($pipes[0]);

  //Debug test to proce that $pipes[1] is a valid stream
  #$content = '';
  #while(!feof($pipes[1])) {
  #  $content =  fgets($pipes[1],1024);
  #  echo "read: $content\n";
  #}
  $upload = @ftp_fput($ftp, $out_db_dir.'/'. kyplex_normalize_filename($table) . '.sql', $pipes[1], FTP_BINARY);
  fclose($pipes[1]);
  if ( function_exists('memory_get_usage') ) {
    memory_get_usage(true);
  }
  // Check upload status
  if ($debug)
  {
    echo ('upload '. ($upload ? 'true':' false')."\n");
  }
  if ($upload)
    return 1;
  return 0;
}

function kyplex_db_show_tables($db, $db_type, $db_name, $db_prefix)
{
  $tname = '';
  $mtime = 0;
  $nrecords = 0;
  $files = array();
  if ($db_type == "mysql")
  {
    if (isset($db_prefix) && strlen($db_prefix)> 0)
    {
      $db_prefix = trim($db_prefix);
    }
    $q = "SELECT table_name, unix_timestamp(update_time) FROM information_schema.tables WHERE table_schema='".
         mysql_real_escape_string($db_name, $db)."' ORDER BY table_name"; 
    $result = @mysql_query($q, $db);
    if (!$result)
    {
      return;
    }
    while ( ($row = @mysql_fetch_array($result)) )
    {
      $tname = $row[0];
      $mtime = $row[1];
      if (isset($db_prefix) && strlen($db_prefix) > 0)
      {
        if (strlen($db_prefix) > strlen($tname))
          continue;
        if ($db_prefix != substr($tname, 0, strlen($db_prefix)) )
          continue;
      }
      $q2 = "SELECT count(*) from `$tname`";
      $result2 = @mysql_query($q2, $db);
      if (!$result2)
      {
        next;
      }
      $row2 = @mysql_fetch_array($result2);
      $nrecords = $row2[0];
      @mysql_free_result($result2);
      $f = array(
        'table'  => $tname,
        'type'  => 'f',
        'mode'  => 777,
        'uid'   => 0,
        'gid'   => 0,
        'mtime' => $mtime,
        'nrecords'=> $nrecords,
      );
      $files[$tname.'.sql'] = $f;
    }
    @mysql_free_result($result);
  } else if ($db_type == "pgsql")
  {

    $schema_prefix = '';
    $table_prefix = '';
    if (isset($db_prefix) && strlen($db_prefix)> 0)
    {
      $db_prefix = trim($db_prefix);
    }
    if (isset($db_prefix) && strlen($db_prefix)> 0)
    {
      # first extract schema name
      if (substr($db_prefix, 0, 1) == '"')
      {
        $pos = @strpos($db_prefix, '"', 1 );
        if ($pos === false)
        {
          # broken string ?
          $table_prefix =@substr($db_prefix, 1);
          $db_prefix = '';
        } else if ($pos == strlen($db_prefix))
        {
          $table_prefix =@substr($db_prefix, 1, -1);
          $db_prefix = '';
        } else {
          $schema_prefix = @substr($db_prefix,1, $pos-1);
          $db_prefix = substr($db_prefix, $pos+2);
        }
      } else {
        $pos = strpos($db_prefix, '.');
        if ($pos === false)
        {
          $table_prefix = $db_prefix;
          $db_prefix = '';
        } else {
          $schema_prefix = substr($db_prefix, 0, $pos);
          $db_prefix = substr($db_prefix, $pos+1);
        }
      }
      if (strlen($db_prefix) > 0)
      {
        if (substr($db_prefix, 0, 1) == '"')
          $table_prefix = substr($db_prefix,1, -1);
        else
          $table_prefix = $db_prefix;
      }
    }

    $q = "SELECT '\"'||t.table_schema||'\".\"'||t.table_name||'\"', s.n_tup_upd + s.n_tup_ins + s.n_tup_del ".
         "FROM information_schema.tables t left outer join pg_stat_user_tables s ".
         "ON (t.table_schema = s.schemaname AND t.table_name = s.relname )  WHERE t.table_type='BASE TABLE' AND ";
    if (strlen($schema_prefix) > 0 && $schema_prefix != '*' && $schema_prefix != '%')
      $q .= "t.table_schema IN ('$schema_prefix') ";
    else 
      $q .= "t.table_schema NOT IN ('pg_catalog', 'pg_toast', 'information_schema') ";
    $q .= "order by t.table_schema,t.table_name"; 
    $result = @pg_query($db, $q);
    if (!$result)
    {
      return;
    }
    while ( ($row = @pg_fetch_row($result)) )
    {
      $tname = $row[0];
      $mtime = $row[1];
      if (strlen($table_prefix) > 0)
      {
        if (stripos($tname, '"."'.$table_prefix) === false)
          continue;
      }
      $q2 = "SELECT count(*) from $tname";
      $result2 = @pg_query($q2);
      if (!$result2)
      {
        next;
      }
      $row2 = @pg_fetch_row($result2);
      $nrecords = $row2[0];
      @pg_free_result($result2);
      $f = array(
        'table'  => $tname,
        'type'  => 'f',
        'mode'  => 777,
        'uid'   => 0,
        'gid'   => 0,
        'mtime' => $mtime,
        'nrecords'=> $nrecords,
      );
      $files[$tname.'.sql'] = $f;
    }
  }
  return $files;
}

function kyplex_normalize_filename($file, $append=true)
{
  $file = str_replace("/", "%x2f", $file);
  $file = str_replace("%", "%x25", $file);
  if ($append)
    return "f".$file;
  return $file;
}
