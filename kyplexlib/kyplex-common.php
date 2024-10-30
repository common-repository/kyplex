<?php

function kyplex_restore_file($filename, $data, $fsize, $group = '', $owner = '', $perm = 0 )
{
  $newfile = 0;
  $perm = @intval($perm);
  $kyplex_dir = @dirname(@dirname(@realpath(__FILE__)));
  @include_once($kyplex_dir."/kyplex-conf.php");
  global $kyplex_api_key;
  if (substr($backup_dir, -1) != '/')
    $backup_dir .= '/';
  $key = $_REQUEST['key'];
  if (!isset($key) || $key == '' || $key != $kyplex_api_key)
  {
    print "FAILED: Access denied.\n";
    exit;
  }
  if (!isset($filename) || $filename == '')
  {
    print "FAILED: File not specified.\n";
    exit;
  }
  $filename = str_replace(array("/../","/./","//"), array("/","/","/"), $filename);
  $filename = str_replace(array("/../","/./","//"), array("/","/","/"), $filename);
  if (substr($filename, 0, 1) == "/")
    $filename = substr($filename, 1);
  #print "full file: ".$backup_dir.$filename."\n";
  $file = $filename;
  if (strrpos($filename, "/") !== FALSE)
  {
    $file = substr($filename, strrpos($filename, "/")+1);
  }
  $dirs = explode("/", $filename, -1);
  $fsize = @intval($fsize);
  $data = @base64_decode($data);
  if (@strlen($data) != $fsize)
  {
    print "FAILED: Failed to upload file. Wrong file size.\n";
    exit;
  }
  if (@chdir($backup_dir) === FALSE)
  {
    print "FAILED: Failed to change directory to website base directory: $backup_dir\n";
    exit;
  }
  $parent = '';
  for ($i = 0; $i < count($dirs); $i++)
  {
    $dir = $dirs[$i];
    if (@file_exists($dir))
    {
      if (@is_dir($dir))
      {
        $parent .= "$dir/";
        #print "chdir ".$backup_dir.$parent."\n";
        if (!chdir($dir))
        {
          print "FAILED: Failed to change directory to: ".$backup_dir.$parent."\n";
          exit;
        }
      } else {
        # this is probably a file.
        # we can not delete this file.
        $parent .= "$dir/";
        print "FAILED: Can not change directory to: ".$backup_dir.$parent." . Probably a file.\n";
        exit;
      }
    } else {
      if ($newfile == 0)
      {
        print "FAILED: Can not change directory to: ".$backup_dir.join('/',$dirs)."/\n";
        exit;
      }

      # directory does not exists
      for (; $i < count($dirs); $i++)
      {
        $dir = $dirs[$i];
        $parent .= "$dir/";
        print "creating ".$backup_dir.$parent."\n";
        if (!mkdir($dir))
        {
          print "FAILED: Can not create parent directory: ".$backup_dir.$parent."\n";
          exit;
        }
        chdir($dir);
      }
    }
  }
  #print "checking $file in ".$backup_dir.$parent."\n";
  if (@is_dir($file))
  {
    print "FAILED: Can not overwrite dir with file contents: ".$backup_dir.$parent.$file."\n";
    exit;
  }
  $file_known = @file_exists($file);
  $fh = @fopen($file, "wb");
  if (!$fh)
  {
    if ($file_known)
    {
      print "FAILED: Access denied to modify file: ".$backup_dir.$parent.$file."\n";
    } else {
      print "FAILED: Failed to create file: ".$backup_dir.$parent.$file."\n";
    }
    exit;
  }
  @fwrite($fh, $data);
  @fclose($fh);
  if (!$file_known)
  {
    if ($perm)
    {
      @chmod($file, $perm);
    }
    if ($owner)
    {
      @chown($file, $owner);
    }
    if ($group)
    {
      @chgrp($file, $group);
    }
  }
  # if we got here the parent directories should be created
  print "DONE\n"; 
}


function kyplex_delete_file($filename )
{
  if (!isset($filename) || $filename == '')
  {
    print"FAILED: File not specified.\n";
    exit;
  }
  $kyplex_dir = @dirname(@dirname(@realpath(__FILE__)));
  @include_once($kyplex_dir."/kyplex-conf.php");
  global $kyplex_api_key;
  if (substr($backup_dir, -1) != '/')
    $backup_dir .= '/';
  $key = $_REQUEST['key'];
  if (!isset($key) || $key == '' || $key != $kyplex_api_key)
  {
    print "FAILED: Access denied.\n";
    exit;
  }
  $filename = str_replace(array("/../","/./","//"), array("/","/","/"), $filename);
  $filename = str_replace(array("/../","/./","//"), array("/","/","/"), $filename);
  if (substr($filename, 0, 1) == "/")
    $filename = substr($filename, 1);
  #print "full file: ".$backup_dir.$filename."\n";
  $file = $filename;
  if (strrpos($filename, "/") !== FALSE)
  {
    $file = substr($filename, strrpos($filename, "/")+1);
  }
  $dirs = explode("/", $filename, -1);
  if (@chdir($backup_dir) === FALSE)
  {
    print "FAILED: Failed to change directory to website base directory: $backup_dir\n";
    exit;
  }
  $file_known = @file_exists($filename);
  if (!$file_known)
  {
    print "GOOD: File does not exists: ".$backup_dir.$filename."\n";
    exit;
  }
  if (!@is_dir($filename))
  {
    if (@unlink($filename))
    {
      print "DONE";
    } else {
      print "FAILED: Failed to delete: ".$backup_dir.$filename." . Probably no permission.\n";
    }
    exit;
  }
  if ($filename == '')
  {
    print "FAILED: Can not remove all your website dirs.";
    exit;
  }
  // this is a dir - we need to delete it recursivly
  // in some cases when phishing kit is detected we need to delete the whole directory
  kyplex_recursive_del($filename);
  if (@file_exists($filename))
  {
    print "FAILED: Failed to delete directory: ".$backup_dir.$filename." .\n";
    exit;
  }
  print "DONE";
  exit;
}

function kyplex_recursive_del($dir) {
  $files = array_diff(@scandir($dir), array('.','..'));
  foreach ($files as $file)
  {
    (@is_dir("$dir/$file")) ? kyplex_recursive_del("$dir/$file") : @unlink("$dir/$file");
  }
  return @rmdir($dir);
}

function kyplex_get_php_location()
{
  $files = array('/bin/php', '/usr/bin/php', '/sbin/php', '/usr/bin/php5', '/usr/local/bin/php');
  foreach ($files as $f)
  {
    if (@file_exists($f))
    {
      return $f;
    }
  }
  return 'php';
}

function kyplex_get_mysqldump_location($mysqldump)
{
  if (isset($mysqldump) && strlen($mysqldump) > 0)
  {
    if (strpos($mysqldump, "/") !== false || strpos($mysqldump, "\\") !== false )
    {
      return $mysqldump;
    }
  }
  $files = array('/bin/mysqldump', '/usr/bin/mysqldump', '/sbin/mysqldump', '/usr/bin/mysqldump', '/usr/local/bin/mysqldump');
  foreach ($files as $f)
  {
    if (@file_exists($f))
    {
      return $f;
    }
  }
  return 'mysqldump';
}

function kyplex_get_mysql_location($mysql)
{
  if (isset($mysql) && strlen($mysql) > 0)
  {
    if (strpos($mysql, "/") !== false || strpos($mysql, "\\") !== false )
    {
      return $mysql;
    }
  }
  $files = array('/bin/mysql', '/usr/bin/mysql', '/sbin/mysql', '/usr/bin/mysql', '/usr/local/bin/mysql');
  foreach ($files as $f)
  {
    if (@file_exists($f))
    {
      return $f;
    }
  }
  return 'mysql';
}

function kyplex_get_pgdump_location($pgdump)
{
  if (isset($pgdump) && strlen($pgdump) > 0)
  {
    if (strpos($pgdump, "/") !== false || strpos($pgdump, "\\") !== false )
    {
      return $pgdump;
    }
  }
  $files = array('/bin/pg_dump', '/usr/bin/pg_dump', '/sbin/pg_dump', '/usr/bin/pg_dump', '/usr/local/bin/pg_dump');
  foreach ($files as $f)
  {
    if (@file_exists($f))
    {
      return $f;
    }
  }
  return 'pg_dump';
}

function kyplex_get_psql_location($psql)
{
  if (isset($psql) && strlen($psql) > 0)
  {
    if (strpos($psql, "/") !== false || strpos($psql, "\\") !== false )
    {
      return $psql;
    }
  }
  $files = array('/bin/psql', '/usr/bin/psql', '/sbin/psql', '/usr/bin/psql', '/usr/local/bin/psql');
  foreach ($files as $f)
  {
    if (@file_exists($f))
    {
      return $f;
    }
  }
  return 'psql';
}

function kyplex_fetch_direct_url($url)
{
  global $debug;
  if ($debug)
  {
    echo "fething: $url\n";
  }
  $uinfo = parse_url($url);
  $host = $uinfo['host'];
  $path = $uinfo['path'];
  if (!$path)
    $path = '/';
  if (isset($uinfo['query']) && strlen($uinfo['query']) > 0)
    $path .= '?'.$uinfo['query'];

  #print "connect $path\n";
  $request = "GET $path HTTP/1.1\r\n".
             "Host: $host\r\n".
             "User-Agent: kyplex-backup\r\n".
             "Connection: close\r\n\r\n";
  $timeout = 20;
  $socket = @fsockopen($host, 80, $errno, $errstr, $timeout);
  $result = '';
  if (!$socket) {
     $result["errno"] = $errno;
     $result["errstr"] = $errstr;
   return $result;
  }

  fputs($socket, $request);
  while (!feof($socket)) {
    $result .= fgets($socket, 4096);
  }
  fclose($socket);
  list($header, $data) = explode("\r\n\r\n", $result, 2);
  #print "header: $header\n";
  #print "data: $data\n";

  if (strpos($header, "100 Continue") === false)
    return $data;
  list($header, $data) = explode("\r\n\r\n", $data, 2);
  return $data;
}

function kyplex_parse_file_list($ftp, $dir)
{
  global $debug;
  $has_nrecords = 0;
  $files = array();
  $temp = tmpfile();
  $filename = '';
  $new_pos = 0;
  $nextChar = '';
  $nrecords = 0;
  $mtime = '';
  $inode = 0;
  $mod4 = 0;
  $div4 = 0;
  $mode = '';
  $type = '';
  $size = 0;
  $tag = '';
  $uid = '';
  $gid = '';
  $eof = 0;
  $pos = 0;
  
  $fname = $dir.'/attrib';
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
  $data = fread($temp, 10240000);
  fclose($temp);

  if (substr($data,0, 5) != "<?xml")
    return $files;

  $pos = strpos($data, '<r>');
  if ($pos === false)
  {
    return $files; 
  }
  $pos+=3;

  while ($pos < strlen($data))
  {

    $nextChar = substr($data, $pos, 1);
    if ($nextChar == '\r' || $nextChar == '\n')
    {
      $pos += 1;
      continue;
    }
    else if ($nextChar != '<')
    {

      return $files;
    }
    # nextChar should be '<'
    $tag = substr($data, $pos, 3);
    $pos += 3;
    if ($tag == '<r>')
    {
      $new_pos = $pos;
      $filename = $mode = $type = $uid = $gid = $mtime = $sha1 = "";
      $has_nrecords = $nrecords = $mod4 = $div4 = $size = $inode = 0;
      continue;
    } else if ($tag == '<f=')
    {
      $new_pos = strpos($data, '/', $pos);   # look for end of record
      $filename = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<p=')
    {
      $new_pos = strpos($data, '/', $pos);   # look for end of record
      $mode = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<'.'u=')
    {
      $new_pos = strpos($data, '/', $pos);
      $uid = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<g=')
    {
      $new_pos = strpos($data, '/', $pos);
      $gid = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<c=')
    {
      $new_pos = strpos($data, '/', $pos);
      $mtime = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<m=')
    {
      $new_pos = strpos($data, '/', $pos);
      $mod4 = @intval(substr($data, $pos, $new_pos-$pos));
    } else if ($tag == '<d=')
    {
      $new_pos = strpos($data, '/', $pos);
      $div4 = @intval(substr($data, $pos, $new_pos-$pos));
    } else if ($tag == '<n=')
    {
      $new_pos = strpos($data, '/', $pos);
      $nrecords = @intval(substr($data, $pos, $new_pos-$pos));
      $has_nrecords = 1;
    } else if ($tag == '<t=')
    {
      $new_pos = strpos($data, '/', $pos);
      $type = substr($data, $pos, $new_pos-$pos);
      if ($type == "5") { $type = "d"; } else
      if ($type == "0") { $type = "f"; } else
      if ($type == "2") { $type = "l"; }
    } else if ($tag == '<h=')
    {
      $new_pos = strpos($data, '/', $pos);
      $sha1 = substr($data, $pos, $new_pos-$pos);
    } else if ($tag == '<i=')
    {
      $new_pos = strpos($data, '/', $pos);
      $inode = @intval(substr($data, $pos, $new_pos-$pos));
    } else if ($tag == '</r')
    {
       $f = array(
                       #'name' => $filename,
                       'size' => ($mod4 + $div4 * 4096 * 1024 * 1024),
                       'mode' => $mode,
                       'type' => $type,
                       'mtime' => $mtime,
                       'uid' => $uid,
                       'gid' => $gid,
       );
       if (isset($sha1) && strlen($sha1)> 0)
         $f['sha1'] = $sha1;
       if ($inode != 0)
         $f['inode'] = $inode;
       if ($has_nrecords)
         $f['nrecords'] = $nrecords;
       $files[$filename] = $f;
       $pos += 3;
       $filename = $mode = $type = $uid = $gid = $mtime = $sha1 = "";
       $has_nrecords = $nrecords = $mod4 = $div4 = $size = $inode = 0;
       continue; 
    } else if ($tag == '</f')  #</files> - end of file section
    {
      return $files;
    }
    $pos = $new_pos+2;
  }
  return $files;
}

# parse_utf8_url
# taken from here: http://php.net/manual/en/function.parse-url.php
function kyplex_parse_url($url) 
{ 
    static $keys = array('scheme'=>0,'user'=>0,'pass'=>0,'host'=>0,'port'=>0,'path'=>0,'query'=>0,'fragment'=>0); 
    if (is_string($url) && preg_match( 
            '~^((?P<scheme>[^:/?#]+):(//))?((\\3|//)?(?:(?P<user>[^:]+):(?P<pass>[^@]+)@)?(?P<host>[^/?:#]*))(:(?P<port>\\d+))?' . 
            '(?P<path>[^?#]*)(\\?(?P<query>[^#]*))?(#(?P<fragment>.*))?~u', $url, $matches)) 
    { 
        foreach ($matches as $key => $value) 
            if (!isset($keys[$key]) || empty($value)) 
                unset($matches[$key]); 
        return $matches; 
    } 
    return false; 
}

function _getopt ( ) {

/* _getopt(): Ver. 1.3      2009/05/30
   My page: http://www.ntu.beautifulworldco.com/weblog/?p=526

Usage: _getopt ( [$flag,] $short_option [, $long_option] );

Note that another function split_para() is required, which can be found in the same
page.

_getopt() fully simulates getopt() which is described at
http://us.php.net/manual/en/function.getopt.php , including long options for PHP
version under 5.3.0. (Prior to 5.3.0, long options was only available on few systems)

Besides legacy usage of getopt(), I also added a new option to manipulate your own
argument lists instead of those from command lines. This new option can be a string
or an array such as 

$flag = "-f value_f -ab --required 9 --optional=PK --option -v test -k";
or
$flag = array ( "-f", "value_f", "-ab", "--required", "9", "--optional=PK", "--option" );

So there are four ways to work with _getopt(),

1. _getopt ( $short_option );

  it's a legacy usage, same as getopt ( $short_option ).

2. _getopt ( $short_option, $long_option );

  it's a legacy usage, same as getopt ( $short_option, $long_option ).

3. _getopt ( $flag, $short_option );

  use your own argument lists instead of command line arguments.

4. _getopt ( $flag, $short_option, $long_option );

  use your own argument lists instead of command line arguments.

*/

  if ( func_num_args() == 1 ) {
     $flag =  $flag_array = $GLOBALS['argv'];
     $short_option = func_get_arg ( 0 );
     $long_option = array ();
  } else if ( func_num_args() == 2 ) {
     if ( is_array ( func_get_arg ( 1 ) ) ) {
        $flag = $GLOBALS['argv'];
        $short_option = func_get_arg ( 0 );
        $long_option = func_get_arg ( 1 );
     } else {
        $flag = func_get_arg ( 0 );
        $short_option = func_get_arg ( 1 );
        $long_option = array ();
     }
  } else if ( func_num_args() == 3 ) {
     $flag = func_get_arg ( 0 );
     $short_option = func_get_arg ( 1 );
     $long_option = func_get_arg ( 2 );
  } else {
     exit ( "wrong options\n" );
  }

  $short_option = trim ( $short_option );

  $short_no_value = array();
  $short_required_value = array();
  $short_optional_value = array();
  $long_no_value = array();
  $long_required_value = array();
  $long_optional_value = array();
  $options = array();

  for ( $i = 0; $i < strlen ( $short_option ); ) {
     if ( $short_option{$i} != ":" ) {
        if ( $i == strlen ( $short_option ) - 1 ) {
          $short_no_value[] = $short_option{$i};
          break;
        } else if ( $short_option{$i+1} != ":" ) {
          $short_no_value[] = $short_option{$i};
          $i++;
          continue;
        } else if ( $short_option{$i+1} == ":" && $short_option{$i+2} != ":" ) {
          $short_required_value[] = $short_option{$i};
          $i += 2;
          continue;
        } else if ( $short_option{$i+1} == ":" && $short_option{$i+2} == ":" ) {
          $short_optional_value[] = $short_option{$i};
          $i += 3;
          continue;
        }
     } else {
        continue;
     }
  }

  foreach ( $long_option as $a ) {
     if ( substr( $a, -2 ) == "::" ) {
        $long_optional_value[] = substr( $a, 0, -2);
        continue;
     } else if ( substr( $a, -1 ) == ":" ) {
        $long_required_value[] = substr( $a, 0, -1 );
        continue;
     } else {
        $long_no_value[] = $a;
        continue;
     }
  }

  if ( is_array ( $flag ) )
     $flag_array = $flag;
  else {
     $flag = "- $flag";
     $flag_array = split_para( $flag );
  }

  for ( $i = 0; $i < count( $flag_array ); ) {

     if ( $i >= count ( $flag_array ) )
        break;

     if ( ! $flag_array[$i] || $flag_array[$i] == "-" ) {
        $i++;
        continue;
     }

     if ( $flag_array[$i]{0} != "-" ) {
        $i++;
        continue;

     }

     if ( substr( $flag_array[$i], 0, 2 ) == "--" ) {

        if (strpos($flag_array[$i], '=') != false) {
          list($key, $value) = explode('=', substr($flag_array[$i], 2), 2);
          if ( in_array ( $key, $long_required_value ) || in_array ( $key, $long_optional_value ) )
             $options[$key][] = $value;
          $i++;
          continue;
        }

        if (strpos($flag_array[$i], '=') == false) {
          $key = substr( $flag_array[$i], 2 );
          if ( in_array( substr( $flag_array[$i], 2 ), $long_required_value ) ) {
             $options[$key][] = $flag_array[$i+1];
             $i += 2;
             continue;
          } else if ( in_array( substr( $flag_array[$i], 2 ), $long_optional_value ) ) {
             if ( $flag_array[$i+1] != "" && $flag_array[$i+1]{0} != "-" ) {
                $options[$key][] = $flag_array[$i+1];
                $i += 2;
             } else {
                $options[$key][] = FALSE;
                $i ++;
             }
             continue;
          } else if ( in_array( substr( $flag_array[$i], 2 ), $long_no_value ) ) {
             $options[$key][] = FALSE;
             $i++;
             continue;
          } else {
             $i++;
             continue;
          }
        }

     } else if ( $flag_array[$i]{0} == "-" && $flag_array[$i]{1} != "-" ) {

        for ( $j=1; $j < strlen($flag_array[$i]); $j++ ) {
          if ( in_array( $flag_array[$i]{$j}, $short_required_value ) || in_array( $flag_array[$i]{$j}, $short_optional_value )) {

             if ( $j == strlen($flag_array[$i]) - 1  ) {
                if ( in_array( $flag_array[$i]{$j}, $short_required_value ) ) {
                  $options[$flag_array[$i]{$j}][] = $flag_array[$i+1];
                  $i += 2;
                } else if ( in_array( $flag_array[$i]{$j}, $short_optional_value ) && $flag_array[$i+1] != "" && $flag_array[$i+1]{0} != "-" ) {
                  $options[$flag_array[$i]{$j}][] = $flag_array[$i+1];
                  $i += 2;
                } else {
                  $options[$flag_array[$i]{$j}][] = FALSE;
                  $i ++;
                }
                $plus_i = 0;
                break;
             } else {
                $options[$flag_array[$i]{$j}][] = substr ( $flag_array[$i], $j + 1 );
                $i ++;
                $plus_i = 0;
                break;
             }

          } else if ( in_array ( $flag_array[$i]{$j}, $short_no_value ) ) {

             $options[$flag_array[$i]{$j}][] = FALSE;
             $plus_i = 1;
             continue;

          } else {
             $plus_i = 1;
             break;
          }
        }

        $i += $plus_i;
        continue;

     }

     $i++;
     continue;
  }

  foreach ( $options as $key => $value ) {
     if ( count ( $value ) == 1 ) {
        $options[ $key ] = $value[0];

     }

  }

  return $options;

}

function split_para ( $pattern ) {

/* split_para() version 1.0      2008/08/19
   My page: http://www.ntu.beautifulworldco.com/weblog/?p=526

This function is to parse parameters and split them into smaller pieces.
preg_split() does similar thing but in our function, besides "space", we
also take the three symbols " (double quote), '(single quote),
and \ (backslash) into consideration because things in a pair of " or '
should be grouped together.

As an example, this parameter list

-f "test 2" -ab --required "t\"est 1" --optional="te'st 3" --option -v 'test 4'

will be splited into

-f
t"est 2
-ab
--required
test 1
--optional=te'st 3
--option
-v
test 4

see the code below,

$pattern = "-f \"test 2\" -ab --required \"t\\\"est 1\" --optional=\"te'st 3\" --option -v 'test 4'";

$result = split_para( $pattern );

echo "ORIGINAL PATTERN: $pattern\n\n";

var_dump( $result );

*/

  $begin=0;
  $backslash = 0;
  $quote = "";
  $quote_mark = array();
  $result = array();

  $pattern = trim ( $pattern );

  for ( $end = 0; $end < strlen ( $pattern ) ; ) {

     if ( ! in_array ( $pattern{$end}, array ( " ", "\"", "'", "\\" ) ) ) {
        $backslash = 0;
        $end ++;
        continue;
     }

     if ( $pattern{$end} == "\\" ) {
        $backslash++;
        $end ++;
        continue;
     } else if ( $pattern{$end} == "\"" ) {
        if ( $backslash % 2 == 1 || $quote == "'" ) {
          $backslash = 0;
          $end ++;
          continue;
        }

        if ( $quote == "" ) {
          $quote_mark[] = $end - $begin;
          $quote = "\"";
        } else if ( $quote == "\"" ) {
          $quote_mark[] = $end - $begin;
          $quote = "";
        }

        $backslash = 0;
        $end ++;
        continue;
     } else if ( $pattern{$end} == "'" ) {
        if ( $backslash % 2 == 1 || $quote == "\"" ) {
          $backslash = 0;
          $end ++;
          continue;
        }

        if ( $quote == "" ) {
          $quote_mark[] = $end - $begin;
          $quote = "'";
        } else if ( $quote == "'" ) {
          $quote_mark[] = $end - $begin;
          $quote = "";
        }

        $backslash = 0;
        $end ++;
        continue;
     } else if ( $pattern{$end} == " " ) {
        if ( $quote != "" ) {
          $backslash = 0;
          $end ++;
          continue;
        } else {
          $backslash = 0;
          $cand = substr( $pattern, $begin, $end-$begin );
          for ( $j = 0; $j < strlen ( $cand ); $j ++ ) {
             if ( in_array ( $j, $quote_mark ) )
                continue;

             $cand1 .= $cand{$j};
          }
          if ( $cand1 ) {
             eval( "\$cand1 = \"$cand1\";" );
             $result[] = $cand1;
          }
          $quote_mark = array();
          $cand1 = "";
          $end ++;
          $begin = $end;
          continue;
       }
     }
  }

  $cand = substr( $pattern, $begin, $end-$begin );
  for ( $j = 0; $j < strlen ( $cand ); $j ++ ) {
     if ( in_array ( $j, $quote_mark ) )
        continue;

     $cand1 .= $cand{$j};
  }

  eval( "\$cand1 = \"$cand1\";" );

  if ( $cand1 )
     $result[] = $cand1;

  return $result;
}
