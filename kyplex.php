<?php
/*
Plugin Name: Kyplex Anti-Malware Service
Plugin URI: http://www.kyplex.com/
Description: Kyplex keeps your website clean from viruses, malware, webshells, etc...
Version: 1.4
Author: Kyplex Team
Author URI: http://www.kyplex.com/about.html
License: GPL3
*/

if ( @is_admin() ) {
  add_action( 'admin_menu', 'kyplex_menu' );
  register_deactivation_hook( __FILE__ ,  'kyplex_uninstall' );
  register_uninstall_hook( __FILE__ ,     'kyplex_uninstall' );
}

$kyplex_seal_disable = get_option( 'kyplex_seal_disable', 0 );
if ($kyplex_seal_disable == 0)
{
  add_action('wp_footer', kyplex_display_seal);
}

#
# The following function ads Kyplex administration menu
#
function kyplex_menu()
{
  add_menu_page( 'Kyplex', 'Kyplex', 'manage_options', 'kyplex_dashboard', 'kyplex_display', plugin_dir_url( __FILE__ ).'images/kyplex-arrow-16x16.png');
  add_submenu_page( 'kyplex_dashboard', 'Kyplex Dashboard', 'Dashboard', 'manage_options', 'kyplex_dashboard', 'kyplex_display');
  add_submenu_page( 'kyplex_dashboard', 'Kyplex Security Seal', 'Security Seal', 'manage_options','kyplex_seal','kyplex_seal_selector');
  add_submenu_page( 'kyplex_dashboard', 'Kyplex Support', 'Support Request', 'manage_options','kyplex_support','kyplex_support_page');
  add_submenu_page( 'kyplex_dashboard', 'Kyplex Compatibility Test', ' Compatibility Test', 'manage_options','kyplex_health_test','kyplex_helth_test_page');
}

#
# The following function displays Kyplex Seaal in the footer of the page
#
function kyplex_display_seal()
{
  $kyplex_seal_size = get_option( 'kyplex_seal_size', "small" );
  $kyplex_seal_brand = get_option( 'kyplex_seal_brand', "wordpress");
  $kyplex_seal_color = get_option( 'kyplex_seal_color', "gray");
  $site = get_settings('siteurl');

  if ($kyplex_seal_size == "small")
  {
    $code = '<a href="http://www.kyplex.com/security-seal.html#domain='.$site.
            '" target="_blank"><img src="https://seal.kyplex.com/seal2.php?c='.$kyplex_seal_color.
            '&b='.$kyplex_seal_brand.'&domain='.$site.'" alt="Kyplex Cloud Security Seal - Click for Verification"></a>';
  } else {
    $code = '<a href="http://www.kyplex.com/security-seal.html#domain='.$site.
            '" target="_blank"><img src="https://seal.kyplex.com/seal1.php?c='.$kyplex_seal_color.
            '&b='.$kyplex_seal_brand.'&domain='.$site.'" alt="Kyplex Cloud Security Seal - Click for Verification"></a>';
  }
  echo "<center>$code</center>\n"; 
//  if ($kyplex_seal_brand)
//  {
//    echo '<center><img id="kyplex_seal" src="'.$dir.'images/'.$kyplex_seal_brand.'-'.$kyplex_seal_size.'-'.
//         $kyplex_seal_color.'1.png" /></center>'."\n";
//  } else {
//    echo '<center><img id="kyplex_seal" src="'.$dir.'images/'.$kyplex_seal_size.'-'.
//         $kyplex_seal_color.'1.png" /></center>'."\n";
//  }

}

#
# The following function displays Kyplex Dashboard and performs user activation for new users
#
function kyplex_display()
{
  if ( !is_admin() && !current_user_can( 'manage_options' ) )  {
    wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
  }

  $userip = @$_SERVER['REMOTE_ADDR'];
  @include_once(plugin_dir_path(__FILE__).'kyplex-conf.php');
  $action = 'login';

  if (!isset($kyplex_api_key) || $kyplex_api_key == "" || !isset($kyplex_account_email) || $kyplex_account_email == "")
  {
    # check if we need to confirm user email address
    if (!isset($_POST['kyplex_account_email']) || strlen($_POST['kyplex_account_email']) == 0)
    {
      kyplex_email_confirmation();
      return;    
    }
    $kyplex_account_email = @trim($_POST['kyplex_account_email']);
    $kyplex_cookie = "";

    # first we need to fetch token
    $result = kyplex_fetch_url("http://management.kyplex.com/plugin-register.php?".
        "action=newuser&email=$kyplex_account_email&site=$kyplex_site&plugin_version=$plugin_version&cms=$cms&userip=$userip");
    if (substr($result, 0, 6) != "TOKEN:")
    {
      wp_die($result);
      return;
    }
    $token = @trim(substr($result, 6));
    update_option( 'kyplex_key', $token );

    $os = urlencode(strtolower(PHP_OS));

    # we need to register now
    $result = kyplex_fetch_url("http://management.kyplex.com/plugin-register.php?".
          "action=validate&email=$kyplex_account_email&site=$kyplex_site&plugin_version=$plugin_version&".
          "cms=$cms&cms_version=$cms_version&location=$plugin_location&".
          "language=$default_language&os=$os&userip=$userip");
    if (substr($result, 0, 7) != "APIKEY:")
    {
      wp_die($result);
      return;
    }
    $kyplex_api_key = @trim(substr($result, 7));
    #print "apikey: $result";
    kyplex_save_config_file($kyplex_site, $kyplex_account_email, $kyplex_api_key);
    $action = 'wizard';
  }

  # account is registered

  $cmd = urlencode($cms);
  $site = urlencode($kyplex_site);
  $apikey = urlencode($kyplex_api_key);
  $cms_version = urlencode($cms_version);
  $email = urlencode($kyplex_account_email);
  $plugin_version = urlencode($plugin_version);

  #if (isset($_GET['action']) && $_GET['action'] == "wizard")
  #{
  #  $action = "wizard";
  #}
  $args = "action=$action&email=$email&site=$site&apikey=$apikey&cms=$cms&cms_version=$cms_version&plugin_version=$plugin_version&plugin_location=$plugin_location";
  $args = base64_encode($args);

?>
<iframe id="kyplex_iframe" src="https://management.kyplex.com/plugin-login.php?args=<?php echo $args; ?>" width=100% height="1000" ></iframe>
<script language="javascript">
var frame = document.getElementById('kyplex_iframe');
frame.height = document.body.scrollHeight - 110;
</script>
<?php

}

#
# The following function is used during Kyplex Account Activation
#
function kyplex_email_confirmation()
{
# this function confirms to use admin email as a kyplex account email
$kyplex_account_email = get_settings('admin_email');

?>
<div style="width:100%;display:block;height:100px;">&nbsp;</div>
<div style="clear:both;"></div>
<center>
<div style="margin:0 auto;width:500px; border:5px solid #51859B;border-radius: 7px;background:#EEE;">
<div class="header" style="background: url('http://www.kyplex.com/images/bg.jpg') no-repeat scroll center top #0179AB;">
<img alt="kyplex logo" src="http://www.kyplex.com/images/kyplex-logo.png" style="padding:20px;"/>
</div>
<div style="padding:10px;text-align:left;">
 <h2 style="padding:0 0 5px 0;margin:0;">Kyplex Plugin Activation / Registration</h2>
 <div style="display:block;height:20px;"></div>
<i>Type your email address bellow to activate your Kyplex account.</i>
  <form accept-charset="UTF-8" method="post" id="activationForm">
 <input type="hidden" name="action" value="registration">
 <div class="form-item" id="edit-mail-wrapper" style="padding-top:10px;">
   <label for="edit-mail" style="float:left;">E-mail address:</label>
   <input type="text" maxlength="54" name="kyplex_account_email" id="edit-mail" size="50" value="<?php echo $kyplex_account_email; ?>" style="float:right;width:315px;" />
  <div style="clear:both;"></div>
  </div>
  <div class="form-item" id="submit-wrapper" style="clear:left;padding-top:10px;">
  <input type="submit" name="register" id="edit-submit" value="Activate" class="form-submit" style="margin-left:165px;" />
 </div>
</form>
</div>
<hr>
<center>
<a href="http://www.kyplex.com/" target="_blank">Kyplex Cloud Solutions</a>
</center>
</div>
</center>
<?php
}

#
# The following function is used during website validation and registration
#
function kyplex_fetch_url($url)
{
  global $kyplex_cookie;
  $uinfo = parse_url($url);
  $host = $uinfo['host'];
  $path = $uinfo['path'];
  if (!$path)
    $path = '/';
  if (isset($uinfo['query']) && strlen($uinfo['query']) > 0)
    $path .= '?'.$uinfo['query'];

  #print "fetch: $url <br>\n";
  #print "connect $path\n";
  $request = "GET $path HTTP/1.1\r\n".
             "Host: $host\r\n".
             "User-Agent: kyplex-plugin-register\r\n".
             "Connection: close\r\n";
  if (strlen($kyplex_cookie)> 0)
    $request .= "Cookie: $kyplex_cookie\r\n\r\n";
  else
    $request .= "\r\n";
  $timeout = 20;
  $socket = @fsockopen($host, 80, $errno, $errstr, $timeout);
  $result = '';
  if (!$socket) {
     $result["errno"] = $errno;
     $result["errstr"] = $errstr;
   return $result;
  }
  #print "<pre>req: $request</pre>";
  fputs($socket, $request);
  while (!feof($socket)) {
    $result .= fgets($socket, 4096);
  }
  fclose($socket);
  list($header, $data) = explode("\r\n\r\n", $result, 2);

  # check if we need to look for additional HTTP header
  if (strpos($header, "100 Continue") !== false)
    list($header, $data) = explode("\r\n\r\n", $data, 2);

  $hlines = explode("\r\n", $header);
  foreach ($hlines as $line)
  {
    $line = trim($line);
    if (strlen($line) <= strlen('Set-Cookie:'))
      continue;
    if (stripos($line, 'Set-Cookie:') === false)
      continue;
    list($setcookie, $value) = explode(':', $line,2);
    $kyplex_cookie = trim($value);
    #print "found : $kyplex_cookie\n";
  }
  #print "header: $header\n";
  #print "data: $data\n";
  return trim($data);
}

#
# Thw following function saves Kyplex account settings
#
function kyplex_save_config_file($site, $email, $apikey)
{
  $email = urldecode($email);
  $site = urldecode($site);
  $apikey = urldecode($apikey);
  /* safe to wordpress internal configuration db */
  update_option( 'kyplex_account_email', $email );
  update_option( 'kyplex_api_key', $apikey );
  update_option( 'kyplex_site', $site );
}

#
# The following function is used to disable / enable Kyplex Cloud Security Seal
#
function kyplex_seal_selector()
{
$dir = plugin_dir_url(__FILE__);
if (isset($_POST['submit']))
{
  if ($_POST['submit'] == 'Hide Seal')
  {
    update_option( 'kyplex_seal_disable', 1 );
  } else if ($_POST['submit'] == 'Save Changes' || $_POST['submit'] == 'Display Seal')
  {
    $kyplex_seal_size = @trim($_POST['seal_size']);
    $kyplex_seal_brand = @trim($_POST['seal_brand']);
    $kyplex_seal_color = @trim($_POST['seal_color']);
    if ($kyplex_seal_size != "big" && $kyplex_seal_size != "small")
      $kyplex_seal_size = "small";
    if ($kyplex_seal_brand != "wordpress" && $kyplex_seal_brand != "")
      $kyplex_seal_brand = "wordpress";
    if ($kyplex_seal_color != "gray" && $kyplex_seal_color != "blue" &&
        $kyplex_seal_color != "green" && $kyplex_seal_color != "yellow")
      $kyplex_seal_color = "gray";
    update_option( 'kyplex_seal_size', $kyplex_seal_size);
    update_option( 'kyplex_seal_brand', $kyplex_seal_brand);
    update_option( 'kyplex_seal_color', $kyplex_seal_color);
    update_option( 'kyplex_seal_disable', 0);
  }
}
  $kyplex_seal_size = get_option( 'kyplex_seal_size', "small" );
  $kyplex_seal_brand = get_option( 'kyplex_seal_brand', "wordpress");
  $kyplex_seal_color = get_option( 'kyplex_seal_color', "gray");

  $dir = plugin_dir_url(__FILE__);
  if ($kyplex_seal_brand)
  {
    $img_src = $dir.'images/'.$kyplex_seal_brand.'-'.$kyplex_seal_size.'-'.$kyplex_seal_color.'1.png';
  } else {
    $img_src = $dir.'images/'.$kyplex_seal_size.'-'.$kyplex_seal_color.'1.png';
  }

?>
<h2>Kyplex Cloud Security Seal</h2>
<p>You can improve your website conversion rate by showing the cloud security seal on your website.</p>
<form method="post">
<table class="wp-list-table widefat fixed users" style="width:500px;float:left;">
<tr><th>Select Type</th><td><input type="radio" name="seal_size" value="small" checked>&nbsp;Small (174x38)</td><td><input type="radio" name="seal_size" value="big">Big (143x113)</td></tr>
<tr><th>Branding</th><td><input type="radio" name="seal_brand" value="wordpress" checked>&nbsp;Wordpress</td><td><input type="radio" name="seal_brand" value="">&nbsp;Regular</td></tr>
<tr><th>Color</th><td><input type="radio" name="seal_color" value="gray" checked>&nbsp;Gray<br/><input type="radio" name="seal_color" value="blue">&nbsp;Blue</td>
    <td><input type="radio" name="seal_color" value="green">&nbsp;Green<br/><input type="radio" name="seal_color" value="yellow">&nbsp;Yellow</td></tr>
</table>
<img style="float:left;margin:0 0 0 20px;" id="seal" src="<?php echo $img_src; ?>" />
<script type="text/javascript">
jQuery(document).ready(function(){
function redisplay_seal()
{
  var dir = "<?php echo $dir; ?>";
  var brand = jQuery("input[name='seal_brand']:checked").val();
  var size = jQuery("input[name='seal_size']:checked").val();
  var color = jQuery("input[name='seal_color']:checked").val();
  if (brand)
  {
    dir = dir+'/images/'+brand+'-'+size+'-'+color+'1.png';
  } else {
    dir = dir+'/images/'+size+'-'+color+'1.png';
  }
  jQuery('#seal').attr('src', dir);
}
  jQuery("input[name='seal_color']").change(redisplay_seal);
  jQuery("input[name='seal_brand']").change(redisplay_seal);
  jQuery("input[name='seal_size']").change(redisplay_seal);

});
</script>
<div style="clear:both;"></div>
<i>Cloud Security Seal displayed in your website footer will include date of the last scan.</i><br/>
<br/>
<?php
$kyplex_seal_disable = get_option( 'kyplex_seal_disable', 0 );
if ($kyplex_seal_disable)
{
  echo '<input id="submit" class="button-primary" type="submit" value="Display Seal" name="submit">';
} else {
  echo '<input id="submit" class="button-primary" type="submit" value="Save Changes" name="submit">&nbsp;&nbsp;'.
       '<input id="submit" class="button-primary" type="submit" value="Hide Seal" name="submit">';
}
echo '</form>';

}

function kyplex_support_page()
{

@include_once(plugin_dir_path(__FILE__).'kyplex-conf.php');

if (!isset($kyplex_account_email) || strlen($kyplex_account_email) == 0)
{
  $kyplex_account_email = get_settings('admin_email');
}

?>
<iframe id="kyplex_iframe" src="https://management.kyplex.com/contact.php?email=<?php echo $kyplex_account_email; ?>" width=100% height="1000" ></iframe>
<script language="javascript">
var frame = document.getElementById('kyplex_iframe');
frame.height = document.body.scrollHeight - 110;
</script>
<?php
}

function kyplex_uninstall()
{
  @include_once(plugin_dir_path(__FILE__).'kyplex-conf.php');
  if (!isset($kyplex_account_email))
  {
    return;
  }

  kyplex_fetch_url("http://management.kyplex.com/plugin-status.php?action=uninstall&".
                 "email=$kyplex_account_email&apikey=$kyplex_api_key&site=$kyplex_site");

  delete_option( 'kyplex_account_email' );
  delete_option( 'kyplex_api_key' );
  delete_option( 'kyplex_site' );
  delete_option( 'kyplex_key' );
}

function kyplex_helth_test_page()
{
@include_once(plugin_dir_path(__FILE__).'kyplexlib/kyplex-htest.php');
$php_version = phpversion();

$out= <<<HTML_CODE
<h1 class="title">Kyplex Plugin Compatibility Test</h1>
<table class="wp-list-table widefat fixed users" style="border:1px solid black;width:50%;" cellspacing="0">
<tr><th style="width:50%;">PHP Version</th><td style="width:50%;">$php_version</td></tr>
<tr><th>PHP safe mode</th><td>$safe</td></tr>
<tr><th>PHP FTP Extension</th><td>$ftp</td></tr>
<tr><th>PHP fopen() can open urls</th><td>$url</td></tr>
<tr><th>PHP proc_open() enabled</th><td>$proc_open</td></tr>
<!--
<tr><th>PHP exec() enabled</th><td>$exec</td></tr>
<tr><th>PHP can start external php script</th><td>$exec_php</td></tr>
-->
<tr><th>$sql</th><td>$sql_status</td></tr>
<tr><th>$sql_dump command</th><td>$sql_dump_status</td></tr>
</table>
<br/>
HTML_CODE;

/*
$out.= <<<HTML_CODE
<h1 class="title">PHP command line scripts (PHP CLI)</h1>
<p>When performing backup or restoration Kyplex starts background shell PHP scripts. Some distributions have different configuration file for php scripts running from command line.</p>
<table class="wp-list-table widefat fixed users" style="border:1px solid black;width:50%;" cellspacing="0">
<tr><th style="width:50%;">PHP safe mode</th><td>$safe_cli</td></tr>
<tr><th>PHP FTP Extension</th><td style="width:50%;">$ftp_cli</td></tr>
<tr><th>PHP exec() enabled</th><td>$exec_cli</td></tr>
<tr><th>$sql</th><td>$sql_cli_status</td></tr>
<tr><th>$sql_dump command</th><td>$sql_cli_dump_status</td></tr>
</table>
HTML_CODE;
*/

if (($sql_cli_dump_status != $yes && $safe_cli == 'On') || ($sql_cli_dump_status != $yes && $safe_cli == 'On') )
{
$out .= <<<HTML_CODE
<br/>
<h2>PHP safe mode recomendation</h2>
<br/>
<p>
When PHP safe mode is on, PHP can execute commands just in specific directoris. In order to allow mysqldump or pg_dump to work you need to add mysqldump or pd_dump directory to the list of allowed directories using <a style="color:blue;text-decoration:underline;" href='http://www.php.net/manual/en/ini.sect.safe-mode.php#ini.safe-mode-exec-dir'>safe_mode_exec_dir directive</a>. Another option is simply to copy mysqldump or pg_dump to kyples plugin directory.
</p>
HTML_CODE;
}

if ($ftp != $yes || $url != $yes || $exec != $yes || $exec_php != $yes || $sql_status != $yes || $sql_dump_status != $yes ||
    $ftp_cli != $yes || $exec_cli != $yes || $sql_cli_status != $yes || $sql_cli_dump_status != $yes)
{
$out .= <<<HTML_CODE
<br/>
<h2>Need Help?</h2>
<br/>
<p>
If one of the PHP extensions or functions is disabled and your hosting company is not helpfull in fixing this issue, you can enable Kyplex to backup and restore your website from remote using FTP or SSH. It is easily done using <a href="https://management.kyplex.com/">Kyplex Management console</a>. Contact Kylex support for more information: <u>support@kyplex.com</u> .</p>
HTML_CODE;
} else {
  $out .= '<br/><h2>Everything looks great.</h2>';
}

print $out;
}
?>
