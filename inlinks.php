<?php

/*
Plugin Name: Text Link Ads InLinks
Plugin URI: http://www.matomyseo.com/?ref=267085
Description: Text Link Ads InLinks program sells links within your blog posts
Author: Text Link Ads
Version: 3.0.0
Author URI: http://www.matomyseo.com/?ref=267085
*/

if (!function_exists('add_action')) {
    header('HTTP/1.0 404 Not Found');
    header('Location: ../../');
    exit;
}

function inlinks_disable_plugin($inlinks = false)
{
    $pluginName = basename(__FILE__);
    $plugins = get_option('active_plugins');
    $index = array_search($pluginName, $plugins);
    if ($index !== false) {
        array_splice($plugins, $index, 1);
        update_option('active_plugins', $plugins);
        do_action('deactivate_'.$pluginName);
    }
}

$inlinks_object = null;
$inlinks_object = new inlinksObject;

// general/syncing hooks
add_action('init', 'inlinks_initialize');
add_action('publish_post', 'inlinks_send_new_post_alert');
add_action('publish_page', 'inlinks_send_new_post_alert');
add_action('delete_post', 'inlinks_send_deleted_post_alert');
add_action('update_option_inlinks_site_key', 'inlinks_check_installation');

add_action('admin_init', 'inlinks_admin_init');
add_action('admin_menu', 'inlinks_admin_menu');
add_action('admin_notices', 'inlinks_admin_notices');

$tlaPluginName = plugin_basename(__FILE__);
add_filter("plugin_action_links_$tlaPluginName", 'inlinks_settings_link');

/**
 *  Syndicated Posts & Links Settings
    Formatting filters: Exposes syndicated posts to formating filters
    http://wordpress.org/support/topic/including-custom-fields-in-the_content-for-posts?replies=1#post-1542139
 */

remove_filter('the_content', 'feedwordpress_preserve_syndicated_content', -10000);
remove_filter('the_content', 'feedwordpress_restore_syndicated_content', 10000);

function inlinks_settings_link($links)
{
    $plugin = plugin_basename(__FILE__);
    $settings_link = '<a href="options-general.php?page='.$plugin.'">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function inlinks_admin_notices()
{
    global $inlinks_object;

    if ($inlinks_object->websiteKey) {
        return;
    }

    $pluginName = plugin_basename(__FILE__);
    echo "<div class='updated' style='background-color:#f66;'><p>" . sprintf(__('<a href="%s">Text Link Ads</a> inLink needs attention: please enter a site key or disable the plugin.'), "options-general.php?page=$pluginName") . "</p></div>";
}

function inlinks_admin_init()
{
    if (function_exists('register_setting')) {
        register_setting('inlinks', 'inlinks_site_key');
        register_setting('inlinks', 'inlinks_fetch_method');
        register_setting('inlinks', 'inlinks_allow_caching');
    }
}

function inlinks_admin_menu()
{
    add_options_page('Text Link Ads Options', 'inLinks', 'manage_options', __FILE__, 'inlinks_options_page');
}

function inlinks_options_page()
{
    global $inlinks_object;
    ?>
    <div class="wrap">
        <h2>Text Link Ads</h2>
        <form method="post" action="options.php">
            <?php
            if (function_exists('settings_fields')) {
                settings_fields('inlinks');
            } else {
                echo "<input type='hidden' name='option_page' value='inlinks' />";
                echo '<input type="hidden" name="action" value="update" />';
                wp_nonce_field("inlinks-options");
            }
            ?>
            <style>
            .inlinks_setting th {
                text-align: center;
                padding-top: 10px;
                font-size: 14px;
                background-color:#73A43E;
                color:#fff;
                width:200px;
            }
            .inlinks_setting tr {
                border-bottom:5px solid #FFF;
            }.warning {
                color:red;
                border:1px solid #000;
                padding:5px;
            }
            </style>
            <table class="form-table inlinks_setting">
                <?php if (!is_file($inlinks_object->htaccess_file)): ?>
                <tr>
                    <td valign="top"><p ><div class="warning">YOUR install is not protected. We want to protect your privacy. Please make sure this <strong>plugin directory is writable</strong> or add a file named <strong>.htaccess</strong> here <strong><?php echo $inlinks_object->htaccess_file; ?></strong> with the code in the textbox to your right. This is optional but protects your privacy.</div> </strong></p></td>
                    <td><br /><textarea cols="30" rows="10"><?php echo $inlinks_object->htaccess(); ?></textarea>
                </td>
                </tr>
                <?php endif ?>
                <tr valign="top">
                    <th scope="row">Site Key</th>
                    <td><input type="text" name="inlinks_site_key" value="<?php echo get_option('inlinks_site_key') ? get_option('inlinks_site_key') : $inlinks_object->websiteKey ?>" size="30" /></td>
                </tr>
                <tr valign="top">
                    <td colspan="2">
                        <p>This key can be obtained logging into http://www.matomyseo.com/ and submitting your blog site. The line should look similar to <code>2CG2RAUOBQ1NZEXPBDZW</code> Copy and paste this code into the field above, hit the save button and then you should then be all set to go.</p>
                    </td>
                </tr>
                 <tr>
                    <th>Allow Cached Pages</th>
                    <td><input type="checkbox" name="inlinks_allow_caching" value='1' <?php echo get_option('inlinks_allow_caching') ? 'checked="checked"' :'' ?>' /></td>
                </tr>
                <?php if (!function_exists('wp_remote_get')): ?>
                <tr valign="top">
                    <th>Ad Retrieval Method</th>
                    <td>
                        <?php if (function_exists('curl_init')) : ?>Curl <input type=radio name="inlinks_fetch_method" value="curl" <?php echo get_option('inlinks_fetch_method') == 'curl' ? 'checked' : '' ?>" /><?php endif; ?>
                        <?php if (function_exists('file_get_contents')) :?>Php (file_get_contents)<input type=radio name="inlinks_fetch_method" value="native" <?php echo get_option('inlinks_fetch_method') == 'native' ? 'checked' : '' ?>" /><?php endif; ?>
                        Default (sockets)<input type=radio name="inlinks_fetch_method" value="0" <?php echo !get_option('inlinks_fetch_method') ? 'checked' : '' ?>" />
                    </td>
                </tr>
                <?php endif ?>
            </table>
            <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p>
        </form>
    </div>
    <?php
}

function inlinks_ads()
{
// Added to enforce that standard tla plugin is not running as well
}

function inlinks_initialize()
{
    global $wpdb, $inlinks_object;
    $inlinks_object = new inlinksObject;
    $inlinks_object->initialize();

    $requestKey = isset($_REQUEST['textlinkads_key']) ? $_REQUEST['textlinkads_key'] : (isset($_REQUEST['inlinks_key']) ? $_REQUEST['inlinks_key'] : '');
    $requestAction = isset($_REQUEST['textlinkads_action']) ? $_REQUEST['textlinkads_action'] : (isset($_REQUEST['inlinks_action']) ? $_REQUEST['inlinks_action'] : '');
    $requestPostId = (int)isset($_REQUEST['textlinkads_post_id']) ? $_REQUEST['textlinkads_post_id'] : (isset($_REQUEST['inlinks_post_id']) ? $_REQUEST['inlinks_post_id'] : '');

    if ($requestKey && $requestAction && $requestKey == $inlinks_object->websiteKey) {
        switch($requestAction) {
            case 'debug':
            case 'debug_inlinks':
                $inlinks_object->debug();
                exit;
                break;

            case 'refresh':
            case 'refresh_inlinks':
                $inlinks_object->updateLocalAds();
                exit;
                break;

            case 'search_posts':
                $inlinks_object->searchPosts($requestQuery);
                exit;
                break;

            case 'sync_posts':
                if ($requestPostId) {
                    $inlinks_object->outputPostForSyncing($requestPostId);
                } else {
                    $inlinks_object->initialPostSync();
                }
                exit;
                break;

            case 'reset_syncing':
                update_option($inlinks_object->lastSyncIdOption, '0');
                exit;
                break;

            case 'reset_sync_limit':
                $maxId = $wpdb->get_var("SELECT ID FROM $wpdb->posts ORDER BY ID DESC LIMIT 1");
                if ($maxId === '') {
                    $maxId = '0';
                }
                update_option($inlinks_object->maxSyncIdOption, $maxId);
                exit;
                break;
        }

    }
    if (!is_feed()) add_filter('the_content', 'inlinks_insert_inlink', 1);
}

function inlinks_check_installation()
{
    global $inlinks_object;
    $inlinks_object->updateUrl();
}

function inlinks_insert_inlink($content = '')
{
    global $inlinks_object, $wpdb, $post;
    $inlinks_object->initialize();

    if (is_object($post)) $content = $inlinks_object->insertInLinkAd($post->ID, $content);

    return $content;
}

function inlinks_send_new_post_alert($postId)
{
    global $inlinks_object;

    $inlinks_object->fetchLive($inlinks_object->PingUrl . '?action=add&inventory_key=' . $inlinks_object->websiteKey . '&post_id=' . $postId);
}

function inlinks_send_updated_post_alert($postId)
{
    global $inlinks_object;

    $inlinks_object->fetchLive($inlinks_object->PingUrl . '?action=update&inventory_key=' . $inlinks_object->websiteKey . '&post_id=' . $postId);
}

function inlinks_send_deleted_post_alert($postId)
{
    global $inlinks_object;

    $inlinks_object->fetchLive($inlinks_object->PingUrl . '?action=delete&inventory_key=' . $inlinks_object->websiteKey . '&post_id=' . $postId);
}

class inlinksObject
{
    var $websiteKey = '';

    // we do not recommend changing these values
    var $base_url = 'http://www.matomyseo.com/';
    var $PingUrl = 'http://www.matomyseo.com/post_level_sync.php';
    var $xmlRefreshTime = 3600;
    var $connectionTimeout = 15;
    var $version = '2.0.8';
    var $DataTable = 'inlinks_data';

    var $lastUpdateOption = 'inlinks_last_update';
    var $lastSyncIdOption = 'inlinks_last_sync_post_id';
    var $maxSyncIdOption = 'inlinks_max_sync_post_id';

    var $ads;

    function __construct()
    {
        global $table_prefix;

        $this->DataTable = $table_prefix . $this->DataTable;
        $this->allow_caching = get_option('inlinks_allow_caching', 0);
        //overwrite default key if set in options
        $siteKey = get_option('inlinks_site_key');
        if (!empty($siteKey)) {
            $this->websiteKey = $siteKey;
        }
    }

    function htaccess()
    {
        return "<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule $ /index.php/404
</IfModule>";
    }

    function debug()
    {
        global $wpdb;
        if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->DataTable . "'") != $this->DataTable) {
            $installed = 'N';
        } else {
            $installed = 'Y';
            $data = print_r($wpdb->get_results("SELECT * FROM `" . $this->DataTable . "`"), true);
        }
        header('Content-type: application/xml');
        echo "<?xml version=\"1.0\" ?>\n";
        ?>
        <info>
        <lastRefresh><?php echo get_option($this->lastUpdateOption);?></lastRefresh>
        <maxSyncId><?php echo get_option($this->maxSyncIdOption);?></maxSyncId>
        <lastSyncId><?php echo get_option($this->lastSyncIdOption);?></lastSyncId>
        <version><?php echo $this->version;?></version>
        <caching><?php echo defined('WP_CACHE') ? WP_CACHE ? 'Y' : 'N' : 'N'; ?></caching>
        <phpVersion><?php echo phpversion();?></phpVersion>
        <engineVersion><?php echo get_bloginfo('version'); ?></engineVersion>
        <installed><?php echo $installed ;?></installed>
        <data><![CDATA[<?php echo $data;?>]]></data>
        </info>
        <?php
    }

    function installDatabase()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $sql = "DROP TABLE IF EXISTS `" . $this->DataTable . "`";
        dbDelta($sql);

        $sql = "CREATE TABLE `" . $this->DataTable . "` (
                  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                  `post_id` int(10) UNSIGNED NOT NULL default '0',
                  `url` VARCHAR(255) NOT NULL,
                  `text` VARCHAR(255) NOT NULL,
                  `before_text` VARCHAR(255) NULL,
                  `after_text` VARCHAR(255) NULL,
                  PRIMARY KEY (`id`),
                  KEY `post_id` (`post_id`)
                ) AUTO_INCREMENT=1;";

        dbDelta($sql);

        $sql = "ALTER TABLE `" . $this->DataTable . "` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;";
        @dbDelta($sql);

        add_option($this->lastUpdateOption, '0000-00-00 00:00:00');

        if (get_option($this->maxSyncIdOption) > 0) return;

        $maxId = $wpdb->get_var("SELECT ID FROM $wpdb->posts ORDER BY ID DESC LIMIT 1");
        if ($maxId === '') $maxId = '0';

        add_option($this->lastSyncIdOption, '0');
        add_option($this->maxSyncIdOption, $maxId);
        add_option('inlinks_allow_caching', 0);

        $this->installUrl();

        //flushes the cache on install
        @include_once(ABSPATH . 'wp-content/plugins/wp-cache/wp-cache.php');
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        } else {
            //check wp-super-cache
            @include_once(ABSPATH.'wp-content/plugins/wp-super-cache/wp-cache.php');
            if (function_exists('wp_cache_flush')) {
                 wp_cache_flush();
            }
        }
    }

    function installUrl()
    {
        $this->fetchLive($this->PingUrl . '?action=install&inlinks=true&script_language=wordpress-inlinks-' . $this->version . '&inventory_key=' . $this->websiteKey . '&site_url=' . urlencode(get_option('siteurl')), 80);
    }

    function installPostLevel()
    {
        global $wpdb;

        require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

        $wpdb->query("ALTER TABLE `" . $this->DataTable . "` ADD `post_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `id`;");

        $wpdb->query("ALTER TABLE `" . $this->DataTable . "` ADD INDEX (`post_id`);");

        if (get_option($this->maxSyncIdOption) > 0) return;

        $maxId = $wpdb->get_var("SELECT ID FROM $wpdb->posts ORDER BY ID DESC LIMIT 1");
        if ($maxId === '') $maxId = '0';

        add_option($this->lastSyncIdOption, '0');
        add_option($this->maxSyncIdOption, $maxId);

    }

    function checkInstallation()
    {
        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '" . $this->DataTable . "'") != $this->DataTable) {
            $this->installDatabase();
        } else if ($wpdb->get_var("SHOW COLUMNS FROM " . $this->DataTable . " LIKE 'post_id'") != 'post_id') {
            $this->installPostLevel();
        }

        if (is_writable(dirname(__FILE__)) && !is_file($this->htaccess_file)) {
            $fh = fopen($this->htaccess_file, 'w+');
            fwrite($fh, $this->htaccess());
            fclose($fh);
        }
    }

    function initialize()
    {
        global $wpdb;
        $this->htaccess_file = dirname(__FILE__) . '/.htaccess';

        $this->checkInstallation();
        $this->fetch_method = get_option('inlinks_fetch_method');

        if (get_option($this->lastUpdateOption) < date('Y-m-d H:i:s', time() - $this->xmlRefreshTime) || get_option($this->lastUpdateOption) > date('Y-m-d H:i:s')) {
            $this->updateLocalAds();
        }

        $this->ads = array();

        $ads = $wpdb->get_results("SELECT * FROM " . $this->DataTable . " WHERE post_id > 0");

        if (!is_array($ads)) return;

        foreach ($ads as $ad) {
            if (isset($this->ads[$ad->post_id]) && is_array($this->ads[$ad->post_id])) {
                $this->ads[$ad->post_id][] = $ad;
            } else {
                $this->ads[$ad->post_id] = array($ad);
            }
        }
    }

    function updateUrl()
    {
        $this->checkInstallation();
        $this->installUrl();
    }

    function updateLocalAds()
    {
        global $wpdb, $cache_enabled;
        $cleanposts = array();
        $insert_query = '';
        $insert_query_arguments = array();
        $url = $this->base_url . 'xml.php?inlinks=true&k=' . $this->websiteKey . '&l=wordpress-inlinks-2.0.8';

        if (function_exists('json_decode') && is_array(json_decode('{"a":1}', true))) {
            $url .= '&f=json';
        }

        update_option($this->lastUpdateOption, date('Y-m-d H:i:s'));

        if ($xml = $this->fetchLive($url)) {
            $links = $this->decode($xml);
            $wpdb->query("TRUNCATE `" . $this->DataTable . "`");
            if ($links && is_array($links)) {
                foreach ($links as $link) {
                    $postId = isset($link['PostID']) ? $link['PostID'] : 0;
                    if (!$postId) {
                        continue;
                    }
                    if ($insert_query) {
                        $insert_query .= ", ";
                    }
                    $insert_query .= "(%s, %s, %s)";
                    $insert_query_arguments[] = $link['URL'];
                    $insert_query_arguments[] = $postId;
                    $insert_query_arguments[] = $link['Text'];
                    $cleanposts[] = $postId;
                }
                if ($insert_query_arguments) {
                    $prepare_string = "INSERT INTO " . $this->DataTable . " ( `url`, `post_id`, `text`) VALUES " . $insert_query;
                    $wpdb->query($wpdb->prepare($prepare_string, $insert_query_arguments));
                }
            }
        }

        if (count($cleanposts) > 0 && WP_CACHE) {
            $this->cleanCache($cleanposts);
        }
    }

    function fetchLive($url)
    {
        $results = '';
        if (!function_exists('wp_remote_get')) {
            switch ($this->fetch_method) {
                case 'curl':
                    if (function_exists('curl_init')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout);
                        curl_setopt($ch, CURLOPT_TIMEOUT, $this->connectionTimeout);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                        $results = curl_exec($ch);
                        curl_close($ch);
                        break;
                    }
                case 'native':
                    if (function_exists('file_get_contents')) {
                        if (PHP_VERSION >= '5.2.1') {
                            $fgt_options = stream_context_create(
                                array('http' => array('timeout' => $this->connectionTimeout))
                            );
                            $results = @file_get_contents($url, 0, $fgt_options);
                        } else {
                            ini_set('default_socket_timeout', $this->connectionTimeout);
                            $results = @file_get_contents($url);
                        }
                        break;
                    }
                default:
                    $url = parse_url($url);
                    if ($handle = @fsockopen($url["host"], 80)) {
                        if (function_exists("socket_set_timeout")) {
                            socket_set_timeout($handle, $this->connectionTimeout, 0);
                        } else if (function_exists("stream_set_timeout")) {
                            stream_set_timeout($handle, $this->connectionTimeout, 0);
                        }

                        fwrite($handle, 'GET ' . $url['path'] . '?' . $url['query'] ." HTTP/1.0\r\nHost: " . $url['host'] . "\r\nConnection: Close\r\n\r\n");
                        while (!feof($handle)) {
                            $results .= @fread($handle, 40960);
                        }
                        fclose($handle);
                    }
                    break;
            }
            $results = substr($results, strpos($results, '<?'));
        } else {
            $results = wp_remote_get($url, array('timeout' => 15));
            if (!is_wp_error($results)) {
                $results = substr($results['body'], strpos($results['body'], '<?'));
            } else {
                $results = '';
            }
        }

        $return = '';
        $capture = false;
        foreach (explode("\n", $results) as $line) {
            $char = substr(trim($line), 0, 1);
            if ($char == '[' || $char == '<') {
                $capture = true;
            }

            if ($capture) {
                $return .= $line . "\n";
            }
        }

        return $return;
    }

    function decode($str)
    {
        if (substr($str, 0, 1) == '[') {
            $arr = json_decode($str, true);
            foreach ($arr as $i => $a) {
                foreach ($a as $k => $v) {
                    $arr[$i][$k] = $this->decodeStr($v);
                }
            }

            return $arr;
        }

        $out = array();
        $returnData = array();

        preg_match_all("/<(.*?)>(.*?)</", $str, $out, PREG_SET_ORDER);
        $n = 0;
        while (isset($out[$n])) {
            $returnData[$out[$n][1]][] = $this->decodeStr($out[$n][0]);
            $n++;
        }

        if (!$returnData) {
            return false;
        }

        $arr = array();
        $count = count($returnData['URL']);
        for ($i = 0; $i < $count; $i++) {
            $arr[] = array(
                'BeforeText' => $returnData['BeforeText'][$i],
                'URL' => $returnData['URL'][$i],
                'Text' => $returnData['Text'][$i],
                'AfterText' => $returnData['AfterText'][$i],
                'PostID' => $returnData['PostID'][$i],
            );
        }

        return $arr;
    }

    function decodeStr($str)
    {
        $search_ar = array('&#60;', '&#62;', '&#34;');
        $replace_ar = array('<', '>', '"');
        return str_replace($search_ar, $replace_ar, html_entity_decode(strip_tags($str)));
    }

    function insertInLinkAd($postId, $content)
    {
        if (isset($this->ads[$postId]) && is_array($this->ads[$postId])) {
            if (!$this->allow_caching){
                define('DONOTCACHEPAGE', true);
            }
            foreach ($this->ads[$postId] as $ad) {
                $second = false;
                $specialChars = array('/', '*', '+', '?', '^', '$', '[', ']', '(', ')');
                $specialCharsEsc = array('\/', '\*', '\+', '\?', '\^', '\$', '\[', '\]', '\(', '\)');

                $specialMassage = '(\')?(s)?(-)?(s\')?';
                $escapedLinkText = str_replace($specialChars, $specialCharsEsc, $ad->text);
                if (strpos($escapedLinkText, ' ') !== false) {
                    $LinkTexts = explode(' ', $escapedLinkText);
                    $escapedLinkText = '';
                    foreach ($LinkTexts as $L) {
                        if ($second) {
                            $escapedLinkText .= ' ';
                        }
                        if (substr($L, -1) == 's') {
                            $L = substr($L, 0, -1);
                        } else if (substr($L, -2) == "s'") {
                            $L = substr($L, 0, -2);
                        }
                        $second = true;
                        $escapedLinkText .= $L . $specialMassage;
                        if ($L != end($LinkTexts)) {
                            $escapedLinkText .= '(\s)?';
                        }
                    }
                } else {
                    if (substr($escapedLinkText, -1) == 's') {
                        $escapedLinkText = substr($escapedLinkText, 0, -1);
                    } else if (substr($escapedLinkText, -2) == "s'") {
                        $escapedLinkText = substr($escapedLinkText, 0, -2);
                    }
                    $escapedLinkText .= $specialMassage;

                }
                $find = '/\b' . $escapedLinkText . '\b/i';
                $trueMatch = false;
                $matches = array();
                preg_match_all($find, $content, $matches, PREG_OFFSET_CAPTURE);
                $matchData = $matches[0];

                if (count($matchData) > 1) {
                    $invalidMatches = array(
                        '/<h[1-6][^>]*>[^<]*' . $escapedLinkText . '[^<]*<\/h[1-6]>/i',
                        '/<a[^>]+>[^<]*' . $escapedLinkText . '[^<]*<\/a>/i',
                        '/href=("|\')[^"\']+' . $escapedLinkText . '[^"\']+("|\')/i',
                        '/src=("|\')[^"\']*' . $escapedLinkText . '[^"\']*("|\')/i',
                        '/alt=("|\')[^"\']*' . $escapedLinkText . '[^"\']*("|\')/i',
                        '/title=("|\')[^"\']*' . $escapedLinkText . '[^"\']*("|\')/i',
                        '/content=("|\')[^"\']*' . $escapedLinkText . '[^"\']*("|\')/i',
                        '/<script[^>]*>[^<]*' . $escapedLinkText . '[^<]*<\/script>/i',
                        '/\<\!--[^<]*' . $escapedLinkText . '[^<]*--\>/i'
                    );

                    foreach ($invalidMatches as $invalidMatch) {
                        $this->flagInvalidMatch($matchData, $invalidMatch, $content);
                    }

                    foreach ($matchData as $index => $match) {
                        if (!isset($match[2]) || $match[2] != true) {
                            $trueMatch = $match;
                            break;
                        }
                    }
                } else {
                    $trueMatch = $matchData[0];
                }

                if (is_array($trueMatch)) {
                    $replacement = '<a href="' . $ad->url . '">' . $trueMatch[0] . '</a>';
                    $content = substr($content, 0, $trueMatch[1]) . $replacement . substr($content, $trueMatch[1] + strlen($trueMatch[0]));
                }

            }

        }

        return $content;
    }

    function flagInvalidMatch(&$matchData, $pattern, $content)
    {
        $results = array();
        preg_match_all($pattern, $content, $results, PREG_OFFSET_CAPTURE);
        $matches = $results[0];

        if (count($matches) == 0) return;

        foreach ($matches as $match) {
            $offsetMin = $match[1];
            $offsetMax = $match[1] + strlen($match[0]);
            foreach ($matchData as $index => $data) {
                if ($data[1] >= $offsetMin && $data[1] <= $offsetMax) {
                    $matchData[$index][2] = true;
                }
            }
        }
    }

    function searchPosts($query)
    {
        global $wpdb;

        $sql = "SELECT ID FROM $wpdb->posts
                WHERE (post_status = 'publish' OR post_status = 'static') AND post_content LIKE '%$query%'";

        if ($query != '') $posts = $wpdb->get_results($sql);

        echo "<?xml version=\"1.0\" ?>\n<posts>\n";
        if (is_array($posts)) {
            $lastIndex = count($posts) - 1;
            foreach ($posts as $index => $post) {
                echo $post->ID . ($index != $lastIndex ? ',' : '');
            }
        }
        echo "</posts>\n";
        exit;
    }

    function outputPostForSyncing($postId)
    {
        global $wpdb;
        $posts = $wpdb->get_results("SELECT ID, post_date_gmt, post_content, post_title FROM $wpdb->posts WHERE ID = '$postId'");
        $this->outputPostsForSyncing($posts);
    }

    function outputPostsForSyncing($posts)
    {
        header('Content-type: application/xml');
        echo "<?xml version=\"1.0\" ?>\n<posts>\n";
        if (is_array($posts)) {
            foreach ($posts as $post) {
                echo "<post>\n"
                        . "<id>" . $post->ID . "</id>\n"
                        . "<title>" . urlencode($post->post_title) . "</title>\n"
                        . "<date>" . $post->post_date_gmt . "</date>\n"
                        . "<url>" . get_permalink($post->ID) . "</url>\n"
                        . "<body>" . $this->prepareBody($post->post_content) . "</body>\n"
                    . "</post>\n";
            }
        }
        echo "</posts>\n";
        exit;
    }

    function prepareBody($body)
    {
        $search = array(
            '@<script[^>]*?>.*?</script>@si',
            '@<h[1-6][^>]*?>.*?</h[1-6]>@si',
            '@<a[^>]*?>.*?</a>@si',
            '@<[\/\!]*?[^<>]*?>@si',
            '@&[#a-z0-9]+;@si',
            '@"@',
            "@'@",
            '@>@',
            '@<@',
            '@[\r\n\t\s]+@'
        );
        $replace = array('', '', '', '', '', '', '', '', '', ' ');
        return urlencode(trim(preg_replace($search, $replace, $body)));
    }

    function initialPostSync()
    {
        global $wpdb;

        $lastId = get_option($this->lastSyncIdOption);
        $maxId = get_option($this->maxSyncIdOption);

        if ($lastId === '' || $lastId === false) $lastId = 0;
        if ($maxId === '' || $maxId === false) $maxId = 999999;

        $query = "SELECT ID, post_date_gmt, post_content, post_title FROM $wpdb->posts
                    WHERE (post_status = 'publish' OR post_status = 'static') AND ID > '$lastId' AND ID <= '$maxId'
                    ORDER BY ID ASC LIMIT 100";


        $posts = $wpdb->get_results($query);

        if (is_array($posts) && count($posts) > 0) {
            $lastIndex = count($posts) - 1;
            $lastId = $posts[$lastIndex]->ID;
            update_option($this->lastSyncIdOption, $lastId);
        }

        $this->outputPostsForSyncing($posts);
    }

    function cleanCache($posts = array())
    {
        if (count($posts) > 0) {
            //check wp-cache
            @include_once(ABSPATH.'wp-content/plugins/wp-cache/wp-cache.php');

            if (function_exists('wp_cache_post_change')) {
                foreach ($posts as $post_id) {
                    wp_cache_post_change($post_id);
                }
            } else {
                //check wp-super-cache
                @include_once(ABSPATH.'wp-content/plugins/wp-super-cache/wp-cache.php');
                if (function_exists('wp_cache_post_change')) {
                    foreach ($posts as $post_id) {
                        wp_cache_post_change($post_id);
                    }
                }
            }
        }
    }
}
?>
