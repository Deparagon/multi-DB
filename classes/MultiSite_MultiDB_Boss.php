<?php




class MultiSite_MultiDB_Boss
{
    public $activecon;
    public $newcon;
    public $inewcon;
    public $iactivecon;
    public function __construct()
    {
        $this->getActiveCon();
        $this->getNewCon();
        $this->inewConnection();
        $this->iexistingConnection();
    }


    public function getActiveCon()
    {
        try {
            return  $this->activecon =  new PDO("mysql:dbname=".DB_NAME.";host=".DB_HOST, DB_USER, DB_PASSWORD);
        } catch (PDOException $e) {
            $this->error[] = $e->getMessage();
        }
    }

    public function getNewCon()
    {
        try {
            return $this->newcon =  new PDO("mysql:host=".DB_HOST, DB_USER, DB_PASSWORD);
        } catch (PDOException $e) {
            $this->error[] = $e->getMessage();
        }
    }

    public function inewConnection()
    {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD);
        if ($mysqli->connect_errno) {
            return false;
            echo "Failed to connect to MySQL: " . $mysqli ->connect_error;
            exit();
        }
        $this->inewcon = $mysqli;
        return true;
    }


    public function iexistingConnection()
    {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        if ($mysqli->connect_errno) {
            return false;
        }

        $this->iactivecon = $mysqli;
        return true;
    }



    public function getDatabaseFromSite($site)
    {
        if (!is_object($site)) {
            return '';
        }
        if (!isset($site->path) && !isset($site->domain)) {
            return '';
        }
        if ($site->path =='/') {
            return str_replace(['/', '.'], ['_',''], trim($site->domain, '/'));
        } else {
            return  str_replace(['/', '.'], ['_',''], trim($site->path, '/'));
        }
        return '';
    }


    public function checkTableExists($database, $table)
    {
        try {
            $sql = 'SELECT 1 FROM '.$database.'.'.$table.' LIMIT 1;';
            $this->newcon->query($sql);
            $errorlist = $this->newcon->errorInfo();
            if (is_array($errorlist) && isset($errorlist[2]) && strpos($errorlist[2], "doesn't exist")!==false) {
                return false;
            } else {
                return true;
            }
        } catch (PDOException $e) {
            return false;
        }
    }



    public function protectTableCreation($query)
    {
        $queries = explode(' (', $query);
        $default_date = date('Y-m-d H:i:s');
        $first = $queries[0];
        if ($first  && strpos($first, 'CREATE TABLE') !==false && strpos($first, 'IF NOT EXISTS') ===false) {
            $query = str_replace(['CREATE TABLE', '0000-00-00 00:00:00'], ['CREATE TABLE IF NOT EXISTS ',$default_date], $query);
        }

        return $query;
    }

    public function checkTableExistsFromQuery($query)
    {
        $queries = explode(' (', $query);
        $first = $queries[0];
        if ($first  && strpos($first, 'CREATE TABLE') !==false) {
            $first = str_replace('CREATE TABLE', '', $first);
            $sql = "SELECT 1 FROM $first LIMIT 1;";
            try {
                $this->newcon->query($sql);
                $errorlist = $this->newcon->errorInfo();
                if (is_array($errorlist) && isset($errorlist[2]) && strpos($errorlist[2], "doesn't exist")!==false) {
                    return false;
                } else {
                    return ' ';
                }
            } catch (PDOException $e) {
                return false;
            }
        }
        return false;
    }


    public function getAllSitesDb()
    {
        $stmt = $this->activecon->prepare('SELECT blog_id, `domain`, `path` FROM '.MSFX_DEFAULT_DB_PREFIX.'blogs');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }


    public function cleanQuery($query, $database, $blog_id)
    {
        $bsprefix = ' '.MSFX_DEFAULT_DB_PREFIX.$blog_id.'_';
        $bs_prefix = MSFX_DEFAULT_DB_PREFIX.$blog_id.'_';
        if (strpos($query, $bsprefix) !==false) {
            $query = str_replace([$bsprefix], [' '.$database.'.'.$bs_prefix], $query);
        }

        $db_n_pref = '`'.$database.'.'.MSFX_DEFAULT_DB_PREFIX.$blog_id.'_';

        if (strpos($query, $db_n_pref) !==false) {
            $query = str_replace([$db_n_pref], [' '.$database.'.`'.$bs_prefix], $query);
        }

        $csprefix = ' `'.MSFX_DEFAULT_DB_PREFIX.$blog_id.'_';
        if (strpos($query, $csprefix) !==false) {
            $query = str_replace([$csprefix], [' '.$database.'.`'.$bs_prefix], $query);
        }


        return $query;
    }
}



global $pdolinker;
$pdolinker = new MultiSite_MultiDB_Boss();
