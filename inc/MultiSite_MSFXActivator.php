<?php


class MultiSite_MSFXActivator
{
    public static function run()
    {
        ob_start();
        global $wpdb;
        add_option('MSFX_DB_PREFIX', $wpdb->prefix);
        self::copyDB();
        self::editWPConfig();
        ob_clean();
        return true;
    }



    public function copyDB()
    {
        copy(plugin_dir_path(__FILE__).'../exports/db.php', ABSPATH.'/wp-content/db.php');
        copy(plugin_dir_path(__FILE__).'../exports/db-error.php', ABSPATH.'/wp-content/db-error.php');
        return true;
    }


    public static function editWPConfig()
    {
        $dbname_and_prefix = "'".DB_NAME.'.'.get_option('MSFX_DB_PREFIX')."'";
        $prefix = "'".get_option('MSFX_DB_PREFIX')."'";

        $data = "define('DB_COLLATE', ''); \n\n #Extended WPConfiguration From Multisite Plugin; \n\n"."define('MSFX_DEFAULT_DB_PREFIX', ".$prefix.");\n\n"."define('MSFX_DEFAULT_DB_PLUS_PREFIX', ".$dbname_and_prefix.")";

        $content =file_get_contents(ABSPATH.'/wp-config.php');
        if (strpos($content, 'MSFX_DEFAULT_DB_PREFIX') !==false) {
            return true;
        }
        if ($content && $content !='') {
            if (strpos($content, "define('DB_COLLATE', '')") !==false) {
                $contents = str_replace("define('DB_COLLATE', '')", $data, $content);
            } elseif (strpos($content, "define( 'DB_COLLATE', '' )") !==false) {
                $contents = str_replace("define( 'DB_COLLATE', '' )", $data, $content);
            }

            file_put_contents(ABSPATH.'/wp-config.php', $contents);
            return true;
        }
        return true;
    }
}
