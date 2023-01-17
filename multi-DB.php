<?php

/*
Plugin Name: Multisite Multi-DB
Plugin URI: https://mrparagon.me/multisite-multi-database
Description: Multisite Multiple Database solution. Each site has its own database
Author: Kingsley Paragon
Version: 1.0.0
Author URI: https://mrparagon.me
*/

require_once plugin_dir_path(__FILE__) . '/classes/MultiSite_MultiDB_Boss.php';
require_once plugin_dir_path(__FILE__) .'/inc/MultiSite_MSFXActivator.php';
require_once plugin_dir_path(__FILE__) . '/inc/MultiSite_MSFXDeactivator.php';

class MultisiteExtraDbUnit
{
    public $errors = array();

    public function __construct()
    {
        add_action('wp_insert_site', array($this, 'processNewSite'));
        add_action('wp_initialize_site', array($this, 'initializeNewSite'));
        add_action('admin_init', array($this, 'manageTable'));
        add_action('activated_plugin', array($this, 'manageTable'));
        add_action('wp_delete_site', array($this, 'deleteSite'));
        add_filter('wp_mail', array($this, 'logEmailMessages'));
        add_action('admin_menu', array($this, 'messageLogMenu'));
    }


    public function testMysql()
    {
        global $pdolinker;
        $database = 'mytestdbone1';

        $sql = 'CREATE DATABASE '.$database.';';

        $pdolinker->inewcon->query($sql);
    }


    public function successFulGrace()
    {
        global $pdolinker;
        $database = 'mytestdbone1';

        $pdolinker->inewcon->select_db($database);
        $t = 'wp_users';
        $sql ='SET sql_mode = ""; 
                 USE '.$database.';
                CREATE TABLE '.$t.' LIKE '.DB_NAME.'.'.$t.';
                INSERT INTO '.$t.' (SELECT * FROM '.DB_NAME.'.'.$t.');';

        file_put_contents(dirname(__FILE__).'/thesql.txt', $sql);

        try {
            $pdolinker->inewcon->query($sql);
        } catch(Exception $e) {
            file_put_contents(dirname(__FILE__).'/greenunionlight.txt', print_r($e->getMessage()));
        }


        return true;


        //$drop_sql.=' DROP TABLE '.$t.'; ';
    }


    public function messageLogMenu()
    {
        $this->successFulGrace();

        add_menu_page(esc_html__('E-mail Message Logs', 'multisite'), esc_html__('Email Messages', 'multisite'), 'manage_options', 'multisite_email_msges', array($this, 'thePage'), 'dashicons-exerpt-view', '3.3');
    }




     public function thePage()
     {
         if (file_exists(dirname(__FILE__).'/logs/email_logs.txt')) {
             $contents = file_get_contents(dirname(__FILE__).'/logs/email_logs.txt');
             $alltr = explode("\n\n\n", $contents);

             if (isset($alltr) && is_array($alltr) && count($alltr) >0) {
                 echo '<h1 style="text-align:center; margin-top:20px;"> E-mail Message Logs </h1>';
                 foreach ($alltr as $tr) {
                     if (trim($tr) =='') {
                         continue;
                     }
                     echo '<div style="border:4px solid #000; width:80%; margin-top:10px; margin-bottom:10px; padding:12px;">'.$tr.'

           </div>';
                 }
             }
         }
     }



    public function processNewSite($sitedata)
    {
        global $pdolinker;
        $database = $pdolinker->getDatabaseFromSite($sitedata);
        $this->createNewDatabase($database);
        update_option('MSFX_PENDING_DB', $database);
        update_option('MSFX_PENDING_BLOG_ID', $sitedata->blog_id);
        sleep(10);
        return true;
    }


     public function logEmailMessages($data)
     {
         file_put_contents(dirname(__FILE__).'/logs/email_logs.txt', date('Y-m-d H:i:s').' '.$data['message']."\n\n\n", FILE_APPEND);
         return $data;
     }



     public function moveToSeparateBD()
     {
         global $pdolinker;
         $stm = $pdolinker->activecon->prepare('show tables;');
         $stm->execute();
         $alltables = $stm->fetchAll(PDO::FETCH_COLUMN);

         if (!defined('MSFX_DEFAULT_DB_PREFIX')) {
             return false;
         }
         $prefix = MSFX_DEFAULT_DB_PREFIX;

         $ss = $pdolinker->activecon->prepare('SELECT * FROM '.$prefix.'blogs');
         $ss->execute();
         $sites = $ss->fetchAll(PDO::FETCH_OBJ);

         if (is_array($sites) && count($sites) >0) {
             foreach ($sites as $site) {
                 if ($site->blog_id ==1) {
                     continue;
                 }


                 if ($this->createNewDatabaseForSite($site)) {
                     $this->makeNewPluginTables($site, $alltables);
                 }
             }
         }
     }

     public function createNewDatabaseForSite($site)
     {
         global $pdolinker;

         $database = $pdolinker->getDatabaseFromSite($site);
         if ($database =='' || $database==false) {
             return false;
         }
         if (count($this->errors) ==0) {
             $sql = 'CREATE DATABASE '.$database.';';
             try {
                 $pdolinker->newcon->exec($sql);
                 return true;
             } catch (PDOException $e) {
            //
                 return false;
             }
         }
     }


    public function manageTable($plugin)
    {
        global $pdolinker;
        $stm = $pdolinker->activecon->prepare('show tables;');
        $stm->execute();
        $alltables = $stm->fetchAll(PDO::FETCH_COLUMN);

        if (!defined('MSFX_DEFAULT_DB_PREFIX')) {
            return false;
        }
        $prefix = MSFX_DEFAULT_DB_PREFIX;

        $ss = $pdolinker->activecon->prepare('SELECT * FROM '.$prefix.'blogs');
        $ss->execute();
        $sites = $ss->fetchAll(PDO::FETCH_OBJ);

        if (is_array($sites) && count($sites) >0) {
            foreach ($sites as $site) {
                if ($site->blog_id ==1) {
                    continue;
                }

                $this->makeNewPluginTables($site, $alltables);
            }
        }
    }

    public function startsWith($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
    }

    public function makeNewPluginTables($site, $tables)
    {
        global $pdolinker;
        $database = $pdolinker->getDatabaseFromSite($site);
        $drop_sql ='';
        $t_prefix = MSFX_DEFAULT_DB_PREFIX;
        foreach ($tables as $t) {
            $pre =  $t_prefix.$site->blog_id.'_';

            if ($this->startsWith($t, $pre) ===true) {
                if ($pdolinker->checkTableExists($database, $t) ===false) {
                    $sql ='SET sql_mode = ""; 
                    USE '.$database.';
                CREATE TABLE '.$t.' LIKE '.DB_NAME.'.'.$t.';
                INSERT INTO '.$t.' (SELECT * FROM '.DB_NAME.'.'.$t.');';
                    $drop_sql.=' DROP TABLE '.$t.'; ';

                    try {
                        $pdolinker->newcon->exec($sql);
                    } catch (PDOException $e) {
                    }
                }
            }
        }


        if ($drop_sql !='') {
            try {
                $pdolinker->activecon->exec($drop_sql);
            } catch (PDOException $e) {
                return false;
            }
        }
    }



    public function initializeNewSite()
    {
        $database = get_option('MSFX_PENDING_DB');
        $blog_id = (int) get_option('MSFX_PENDING_BLOG_ID');
        if ($blog_id >0 && $database !='') {
            $this->copyTables($database, $blog_id);
            update_option('MSFX_PENDING_BLOG_ID', 0);
            update_option('MSFX_PENDING_DB', '');
            return true;
        }
        return true;
    }

    public function deleteSite($old_site)
    {
        global $pdolinker;
        $database = $pdolinker->getDatabaseFromSite($old_site);
        if ($database !='' && $old_site->blog_id !=1) {
            $pdolinker->newcon->exec('DROP DATABASE '.$database.';');
            return true;
        }
    }

    public function defaultTable()
    {
        $table_prefix = get_option('MSFX_DB_PREFIX');
        return array($table_prefix.'users', $table_prefix.'usermeta', $table_prefix.'sitemeta', $table_prefix.'site', $table_prefix.'registration_log', $table_prefix.'signups', $table_prefix.'blogs', $table_prefix.'blogmeta');
    }

    public function createNewDatabase($database)
    {
        global $pdolinker;
        if (count($this->errors) ==0) {
            $sql = 'CREATE DATABASE '.$database.';';
            try {
                $pdolinker->newcon->exec($sql);
            } catch (PDOException $e) {
            //
                return false;
            }
        }
    }


    public function copyTables($database, $blogid)
    {
        global $pdolinker;
        $defaulttables = $this->defaultTable();
        $sitetables = $this->tables($blogid);
        if (is_array($defaulttables) && count($defaulttables) >0) {
            foreach ($defaulttables as $t) {
                $sql ='SET sql_mode = "";
                       USE '.$database.';
                      CREATE TABLE '.$t.' LIKE '.DB_NAME.'.'.$t.';
                      INSERT INTO '.$t.' SELECT * FROM '.DB_NAME.'.'.$t.';';
                try {
                    $pdolinker->newcon->exec($sql);
                } catch (PDOException $e) {
                }
            }
        }

        $drop_sql = '';

        if (is_array($sitetables) && count($sitetables) >0) {
            foreach ($sitetables as $st) {
                $sql ='SET sql_mode = "";
                USE '.$database.';
                CREATE TABLE '.$st.' LIKE '.DB_NAME.'.'.$st.';
                INSERT INTO '.$st.' (SELECT * FROM '.DB_NAME.'.'.$st.');';
                $drop_sql.=' DROP TABLE '.$st.'; ';

                try {
                    $pdolinker->newcon->exec($sql);
                } catch (PDOException $e) {
                }
            }

            if ($drop_sql !='') {
                try {
                    $pdolinker->activecon->exec($drop_sql);
                } catch (PDOException $e) {
                    return false;
                }
            }
        }


        return true;
    }



    public function tables($blogid)
    {
        $table_prefix = get_option('MSFX_DB_PREFIX');
        $tables = [];
        $names = array('commentmeta', 'comments', 'links', 'options', 'posts', 'postmeta', 'termmeta', 'terms', 'term_relationships', 'term_taxonomy');
        foreach ($names as $n) {
            $tables[] = $table_prefix.$blogid.'_'.$n;
        }

        return $tables;
    }
}

new MultisiteExtraDbUnit();


function doMSFXCallActivationBoss()
{
    MultiSite_MSFXActivator::run();
}

function doMSFXCallDeactivationBoss()
{
    MultiSite_MSFXDeactivator::run();
}

register_activation_hook(__FILE__, 'doMSFXCallActivationBoss');

register_deactivation_hook(__FILE__, 'doMSFXCallDeactivationBoss');
