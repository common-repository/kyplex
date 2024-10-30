<?
#
# You can specify custom location of mysql and mysqldump if you use mysql
# or location of the psql and pd_dump file if you use postgresql.
# You need to do it if these programs are not in your web application PATH.
#
$mysqldump = "mysqldump";
$pgdump = "pg_dump";
$mysql = "mysql";
$psql = "psql";

$cms = "wordpress";
$plugin_version = 1;

global $kyplex_key;
global $kyplex_host;
global $kyplex_host_ip;
global $kyplex_api_key;
global $kyplex_account_email;

if (!isset($_SERVER['HTTP_HOST']) && !isset($_SERVER['SERVER_NAME']) &&
  isset($kyplex_host) && strlen($kyplex_host) > 0)
{
  $_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = $kyplex_host;
  if (!isset($_SERVER['REMOTE_ADDR']) || strlen($_SERVER['REMOTE_ADDR']) == 0)
  {
    if (isset($kyplex_host_ip) && strlen($kyplex_host_ip) > 0)
      $_SERVER['REMOTE_ADDR'] = $kyplex_host_ip;
    else
      $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  }
}

@include_once(realpath(dirname(realpath(__FILE__)).'/../../..').'/wp-load.php');
global $wp_version;

$cms_version = $wp_version;
$backup_dir = ABSPATH;
#$site = get_settings('siteurl');
$url_info = @parse_url(@plugin_dir_url( __FILE__ ));
$plugin_location = $url_info['path'];
$default_language = get_settings('blog_charset');

#echo get_settings('blog_charset')."\n";

$db_name = DB_NAME;
$db_user = DB_USER;
$db_pass = DB_PASSWORD;
$db_srv= DB_HOST;
$db_port = '';
$db_type = 'mysql';
$pos = 0;
if ( ($pos = strpos($db_srv, ":")) !== false)
{
$db_port = trim(substr($db_srv,$pos+1));
$db_srv = trim(substr($db_srv, 0, $pos));
}
$db_prefix = $table_prefix;
if (WP_ALLOW_MULTISITE)
{
  # In case of a multisite we will only backup table that belong to our site.
  # We will not backup shared tables.
  $db_prefix = substr($wpdb->posts,0, -5);
}

# Website
$kyplex_site = get_option( "kyplex_site", '');
if (!isset($kyplex_site) || strlen($kyplex_site) == 0)
{
  $kyplex_site = get_settings('siteurl');
}
# Kyplex account email
$kyplex_account_email = get_option( 'kyplex_account_email', '');
# Kyplex API Key
$kyplex_api_key = get_option( 'kyplex_api_key', '');
# Kyplex Validation Key
$kyplex_key = get_option( 'kyplex_key', '');
