<?php

class MultiSite_MSFXDeactivator
{
    public static function run()
    {
        ob_start();
        self::clearEdits();
        self::unlinkFiles();
        ob_clean();
        return true;
    }


   public static function unlinkFiles()
   {
       @unlink(ABSPATH.'/wp-content/db.php');
       @unlink(ABSPATH.'/wp-content/db-error.php');
       return true;
   }

    public static function clearEdits()
    {
        $dbname_and_prefix = "'".DB_NAME.'.'.get_option('MSFX_DB_PREFIX')."'";
        $prefix = "'".get_option('MSFX_DB_PREFIX')."'";

        $one = "define('MSFX_DEFAULT_DB_PREFIX', ".$prefix.");";
        $two = "define('MSFX_DEFAULT_DB_PLUS_PREFIX', ".$dbname_and_prefix.");";
        $three = "#Extended WPConfiguration From Multisite Plugin;";

        $content =file_get_contents(ABSPATH.'/wp-config.php');
        if ($content && $content !='') {
            $contents = str_replace([$one, $two, $three], ['', '', ''], $content);
            file_put_contents(ABSPATH.'/wp-config.php', $contents);
            return true;
        }
    }
}
