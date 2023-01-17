<?php

require_once plugin_dir_path(dirname(__FILE__)) . 'wp-content'.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR.'multisite'.DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.'MultiSite_MultiDB_Boss.php';
class Multidb extends wpdb
{
    public function __construct($dbuser, $dbpassword, $dbname, $dbhost)
    {
        parent::__construct($dbuser, $dbpassword, $dbname, $dbhost);
        $this->db_connect();
    }


    public function getBlogData($blog_id)
    {
        global $pdolinker;
        if ($blog_id < 2) {
            return '';
        }
        try {
            $ss = $pdolinker->activecon->prepare("SELECT * FROM ".MSFX_DEFAULT_DB_PLUS_PREFIX."blogs WHERE blog_id = $blog_id ", );
            $ss->execute();
            $site = $ss->fetch(PDO::FETCH_OBJ);
            return $site;
        } catch (PDOException $e) {
            return '';
        }
    }



    public function getSiteDB($blog_id)
    {
        global $pdolinker;
        $data = $this->getBlogData($blog_id);
        return $pdolinker->getDatabaseFromSite($data);
    }

    public function tables($scope = 'all', $prefix = true, $blog_id = 0)
    {
        switch ($scope) {
            case 'all':
                $tables = array_merge($this->global_tables, $this->tables);
                if (is_multisite()) {
                    $tables = array_merge($tables, $this->ms_global_tables);
                }
                break;
            case 'blog':
                $tables = $this->tables;
                break;
            case 'global':
                $tables = $this->global_tables;
                if (is_multisite()) {
                    $tables = array_merge($tables, $this->ms_global_tables);
                }
                break;
            case 'ms_global':
                $tables = $this->ms_global_tables;
                break;
            case 'old':
                $tables = $this->old_tables;
                break;
            default:
                return array();
        }

        if ($prefix) {
            if (! $blog_id) {
                $blog_id = $this->blogid;
            }
            $blog_prefix   = $this->get_blog_prefix($blog_id);
            $base_prefix   = $this->base_prefix;

            $database = $this->getSiteDB($blog_id);


            $global_tables = array_merge($this->global_tables, $this->ms_global_tables);
            foreach ($tables as $k => $table) {
                if (in_array($table, $global_tables, true)) {
                    $tables[ $table ] = $base_prefix . $table;
                } else {
                    if ($database !='') {
                        $tables[ $table ] = $database.'.'.$blog_prefix . $table;
                    } else {
                        $tables[ $table ] = $blog_prefix . $table;
                    }
                }
                unset($tables[ $k ]);
            }

            if (isset($tables['users']) && defined('CUSTOM_USER_TABLE')) {
                $tables['users'] = CUSTOM_USER_TABLE;
            }

            if (isset($tables['usermeta']) && defined('CUSTOM_USER_META_TABLE')) {
                $tables['usermeta'] = CUSTOM_USER_META_TABLE;
            }
        }

        return $tables;
    }




     /*

         commom tables




     */


         public function getCommonTables()
         {
             return array(MSFX_DEFAULT_DB_PREFIX.'users', MSFX_DEFAULT_DB_PREFIX.'usermeta', MSFX_DEFAULT_DB_PREFIX.'sitemeta', MSFX_DEFAULT_DB_PREFIX.'site', MSFX_DEFAULT_DB_PREFIX.'registration_log', MSFX_DEFAULT_DB_PREFIX.'signups', MSFX_DEFAULT_DB_PREFIX.'blogs', MSFX_DEFAULT_DB_PREFIX.'blogmeta');
         }


       /**
     * Performs a database query, using current database connection.
     *
     * More information can be found on the documentation page.
     *
     * @since 0.71
     *
     * @link https://developer.wordpress.org/reference/classes/wpdb/
     *
     * @param string $query Database query.
     * @return int|bool Boolean true for CREATE, ALTER, TRUNCATE and DROP queries. Number of rows
     *                  affected/selected for all other queries. Boolean false on error.
     */

    public function query($query)
    {
        global $pdolinker;
        if (! $this->ready) {
            $this->check_current_query = true;
            return false;
        }

        /**
         * Filters the database query.
         *
         * Some queries are made before the plugins have been loaded,
         * and thus cannot be filtered with this method.
         *
         * @since 2.1.0
         *
         * @param string $query Database query.
         */
        $query = apply_filters('query', $query);

        if (! $query) {
            $this->insert_id = 0;
            return false;
        }

        $this->flush();

        // Log how the function was called.
        $this->func_call = "\$db->query(\"$query\")";

        // If we're writing to the database, make sure the query will write safely.
        if ($this->check_current_query && ! $this->check_ascii($query)) {
            $stripped_query = $this->strip_invalid_text_from_query($query);
            // strip_invalid_text_from_query() can perform queries, so we need
            // to flush again, just to make sure everything is clear.
            $this->flush();
            if ($stripped_query !== $query) {
                $this->insert_id  = 0;
                $this->last_query = $query;

                wp_load_translations_early();

                $this->last_error = __('WordPress database error: Could not perform query because it contains invalid data.');

                return false;
            }
        }

        $this->check_current_query = true;

        // Keep track of the last query for debug.

        $blog_id = get_current_blog_id();
        $database = $this->getSiteDB($blog_id);
        $query = preg_replace('/\s+/', ' ', $query);
        if ($blog_id != 1) {
            if ($database !='' && $database !==false) {
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
            }
        } else {
            $sites = $pdolinker->getAllSitesDb();
            if ($sites && count($sites) >0) {
                foreach ($sites as $site) {
                    if ($site->blog_id ==1) {
                        continue;
                    }
                    $database = $pdolinker->getDatabaseFromSite($site);
                    if ($database =='' || $database===false) {
                        continue;
                    }


                    $query = $pdolinker->cleanQuery($query, $database, $site->blog_id);
                }
            }
        }



        $resp = $pdolinker->checkTableExistsFromQuery($query);
        if ($resp ===' ') {
            $query = $pdolinker->protectTableCreation($query);

            return true;
        }




        $this->last_query = $query;
        $this->do_query_run($query);

        // Database server has gone away, try to reconnect.
        $mysql_errno = 0;
        if (! empty($this->dbh)) {
            if ($this->use_mysqli) {
                if ($this->dbh instanceof mysqli) {
                    $mysql_errno = mysqli_errno($this->dbh);
                } else {
                    // $dbh is defined, but isn't a real connection.
                    // Something has gone horribly wrong, let's try a reconnect.
                    $mysql_errno = 2006;
                }
            } else {
                if (is_resource($this->dbh)) {
                    $mysql_errno = mysql_errno($this->dbh);
                } else {
                    $mysql_errno = 2006;
                }
            }
        }

        if (empty($this->dbh) || 2006 === $mysql_errno) {
            if ($this->check_connection()) {
                $this->do_query_run($query);
            } else {
                $this->insert_id = 0;
                return false;
            }
        }

        // If there is an error then take note of it.
        if ($this->use_mysqli) {
            if ($this->dbh instanceof mysqli) {
                $this->last_error = mysqli_error($this->dbh);
            } else {
                $this->last_error = __('Unable to retrieve the error message from MySQL');
            }
        } else {
            if (is_resource($this->dbh)) {
                $this->last_error = mysql_error($this->dbh);
            } else {
                $this->last_error = __('Unable to retrieve the error message from MySQL');
            }
        }

        if ($this->last_error) {
            // Clear insert_id on a subsequent failed insert.
            if ($this->insert_id && preg_match('/^\s*(insert|replace)\s/i', $query)) {
                $this->insert_id = 0;
            }

            $this->print_error();
            return false;
        }

        if (preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)) {
            $return_val = $this->result;
        } elseif (preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)) {
            if ($this->use_mysqli) {
                $this->rows_affected = mysqli_affected_rows($this->dbh);
            } else {
                $this->rows_affected = mysql_affected_rows($this->dbh);
            }
            // Take note of the insert_id.
            if (preg_match('/^\s*(insert|replace)\s/i', $query)) {
                if ($this->use_mysqli) {
                    $this->insert_id = mysqli_insert_id($this->dbh);
                } else {
                    $this->insert_id = mysql_insert_id($this->dbh);
                }
            }
            // Return number of rows affected.
            $return_val = $this->rows_affected;
        } else {
            $num_rows = 0;
            if ($this->use_mysqli && $this->result instanceof mysqli_result) {
                while ($row = mysqli_fetch_object($this->result)) {
                    $this->last_result[ $num_rows ] = $row;
                    $num_rows++;
                }
            } elseif (is_resource($this->result)) {
                while ($row = mysql_fetch_object($this->result)) {
                    $this->last_result[ $num_rows ] = $row;
                    $num_rows++;
                }
            }

            // Log and return the number of rows selected.
            $this->num_rows = $num_rows;
            $return_val     = $num_rows;
        }

        return $return_val;
    }



        private function do_query_run($query)
        {
            if (defined('SAVEQUERIES') && SAVEQUERIES) {
                $this->timer_start();
            }

            try {
                if (! empty($this->dbh) && $this->use_mysqli) {
                    $this->result = mysqli_query($this->dbh, $query);
                } elseif (! empty($this->dbh)) {
                    $this->result = mysql_query($query, $this->dbh);
                }
                $this->num_queries++;

                if (defined('SAVEQUERIES') && SAVEQUERIES) {
                    $this->log_query(
                        $query,
                        $this->timer_stop(),
                        $this->get_caller(),
                        $this->time_start,
                        array()
                    );
                }
            } catch(Exception $e) {
                //
                file_put_contents(dirname(__FILE__).'/db-error.php', date('Y-m-d H:i:s').' Database Error: '.$e->getMessage()."\n\n", FILE_APPEND);
            }
        }


    /**
         * Selects a database using the current or provided database connection.
         *
         * The database name will be changed based on the current database connection.
         * On failure, the execution will bail and display a DB error.
         *
         * @since 0.71
         *
         * @param string          $db  Database name.
         * @param mysqli|resource $dbh Optional database connection.
         */
    public function select($db, $dbh = null)
    {
        if (is_null($dbh)) {
            $dbh = $this->dbh;
        }

        if ($this->use_mysqli) {
            $success = mysqli_select_db($dbh, $db);
        } else {
            $success = mysql_select_db($db, $dbh);
        }
        if (! $success) {
            $this->ready = false;
            if (! did_action('template_redirect')) {
                wp_load_translations_early();

                $message = '<h1>' . __('Cannot select database') . "</h1>\n";

                $message .= '<p>' . sprintf(
                    /* translators: %s: Database name. */
                    __('The database server could be connected to (which means your username and password is okay) but the %s database could not be selected.'),
                    '<code>' . htmlspecialchars($db, ENT_QUOTES) . '</code>'
                ) . "</p>\n";

                $message .= "<ul>\n";
                $message .= '<li>' . __('Are you sure it exists?') . "</li>\n";

                $message .= '<li>' . sprintf(
                    /* translators: 1: Database user, 2: Database name. */
                    __('Does the user %1$s have permission to use the %2$s database?'),
                    '<code>' . htmlspecialchars($this->dbuser, ENT_QUOTES) . '</code>',
                    '<code>' . htmlspecialchars($db, ENT_QUOTES) . '</code>'
                ) . "</li>\n";

                $message .= '<li>' . sprintf(
                    /* translators: %s: Database name. */
                    __('On some systems the name of your database is prefixed with your username, so it would be like <code>username_%1$s</code>. Could that be the problem?'),
                    htmlspecialchars($db, ENT_QUOTES)
                ) . "</li>\n";

                $message .= "</ul>\n";

                $message .= '<p>' . sprintf(
                    /* translators: %s: Support forums URL. */
                    __('If you do not know how to set up a database you should <strong>contact your host</strong>. If all else fails you may find help at the <a href="%s">WordPress Support Forums</a>.'),
                    __('https://wordpress.org/support/forums/')
                ) . "</p>\n";

                $this->bail($message, 'db_select_fail');
            }
        }
    }



    /**
     * Helper function for insert and replace.
     *
     * Runs an insert or replace query based on $type argument.
     *
     * @since 3.0.0
     *
     * @see wpdb::prepare()
     * @see wpdb::$field_types
     * @see wp_set_wpdb_vars()
     *
     * @param string       $table  Table name.
     * @param array        $data   Data to insert (in column => value pairs).
     *                             Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     *                             Sending a null value will cause the column to be set to NULL - the corresponding
     *                             format is ignored in this case.
     * @param array|string $format Optional. An array of formats to be mapped to each of the value in $data.
     *                             If string, that format will be used for all of the values in $data.
     *                             A format is one of '%d', '%f', '%s' (integer, float, string).
     *                             If omitted, all values in $data will be treated as strings unless otherwise
     *                             specified in wpdb::$field_types.
     * @param string       $type   Optional. Type of operation. Possible values include 'INSERT' or 'REPLACE'.
     *                             Default 'INSERT'.
     * @return int|false The number of rows affected, or false on error.
     */
    public function _insert_replace_helper($table, $data, $format = null, $type = 'INSERT')
    {
        $this->insert_id = 0;

        if (! in_array(strtoupper($type), array( 'REPLACE', 'INSERT' ), true)) {
            return false;
        }

        $data = $this->process_fields($table, $data, $format);
        if (false === $data) {
            return false;
        }

        $formats = array();
        $values  = array();
        foreach ($data as $value) {
            if (is_null($value['value'])) {
                $formats[] = 'NULL';
                continue;
            }

            $formats[] = $value['format'];
            $values[]  = $value['value'];
        }

        $fields  = '`' . implode('`, `', array_keys($data)) . '`';
        $formats = implode(', ', $formats);

        $tablex = explode('.', $table);
        if (isset($tablex[1]) && $tablex[1] !='') {
            $sql = "$type INTO  $tablex[0].`$tablex[1]` ($fields) VALUES ($formats)";
        } else {
            $blog_id = get_current_blog_id();
            $database = $this->getSiteDB($blog_id);
            if ($database !='') {
                if (!in_array($table, $this->getCommonTables())) {
                    $sql = "$type INTO $database.`$table` ($fields) VALUES ($formats)";
                } else {
                    $sql = "$type INTO `$table` ($fields) VALUES ($formats)";
                }
            } else {
                $sql = "$type INTO `$table` ($fields) VALUES ($formats)";
            }
        }


        $this->check_current_query = false;

        return $this->query($this->prepare($sql, $values));
    }

    /**
     * Updates a row in the table.
     *
     * Examples:
     *
     *     wpdb::update( 'table', array( 'column' => 'foo', 'field' => 'bar' ), array( 'ID' => 1 ) )
     *     wpdb::update( 'table', array( 'column' => 'foo', 'field' => 1337 ), array( 'ID' => 1 ), array( '%s', '%d' ), array( '%d' ) )
     *
     * @since 2.5.0
     *
     * @see wpdb::prepare()
     * @see wpdb::$field_types
     * @see wp_set_wpdb_vars()
     *
     * @param string       $table        Table name.
     * @param array        $data         Data to update (in column => value pairs).
     *                                   Both $data columns and $data values should be "raw" (neither should be SQL escaped).
     *                                   Sending a null value will cause the column to be set to NULL - the corresponding
     *                                   format is ignored in this case.
     * @param array        $where        A named array of WHERE clauses (in column => value pairs).
     *                                   Multiple clauses will be joined with ANDs.
     *                                   Both $where columns and $where values should be "raw".
     *                                   Sending a null value will create an IS NULL comparison - the corresponding
     *                                   format will be ignored in this case.
     * @param array|string $format       Optional. An array of formats to be mapped to each of the values in $data.
     *                                   If string, that format will be used for all of the values in $data.
     *                                   A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                   If omitted, all values in $data will be treated as strings unless otherwise
     *                                   specified in wpdb::$field_types.
     * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where.
     *                                   If string, that format will be used for all of the items in $where.
     *                                   A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                   If omitted, all values in $where will be treated as strings.
     * @return int|false The number of rows updated, or false on error.
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        if (! is_array($data) || ! is_array($where)) {
            return false;
        }

        $data = $this->process_fields($table, $data, $format);
        if (false === $data) {
            return false;
        }
        $where = $this->process_fields($table, $where, $where_format);
        if (false === $where) {
            return false;
        }

        $fields     = array();
        $conditions = array();
        $values     = array();
        foreach ($data as $field => $value) {
            if (is_null($value['value'])) {
                $fields[] = "`$field` = NULL";
                continue;
            }

            $fields[] = "`$field` = " . $value['format'];
            $values[] = $value['value'];
        }
        foreach ($where as $field => $value) {
            if (is_null($value['value'])) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

            $conditions[] = "`$field` = " . $value['format'];
            $values[]     = $value['value'];
        }

        $fields     = implode(', ', $fields);
        $conditions = implode(' AND ', $conditions);

        $tablex = explode('.', $table);
        if (isset($tablex[1]) && $tablex[1] !='') {
            $sql = "UPDATE  $tablex[0].`$tablex[1]` SET $fields WHERE $conditions";
        } else {
            $sql = "UPDATE `$table` SET $fields WHERE $conditions";

            $blog_id = get_current_blog_id();
            $database = $this->getSiteDB($blog_id);
            if ($database !='') {
                if (!in_array($table, $this->getCommonTables())) {
                    $sql = "UPDATE $database.`$table` SET $fields WHERE $conditions";
                } else {
                    $sql = "UPDATE `$table` SET $fields WHERE $conditions";
                }
            } else {
                $sql = "UPDATE `$table` SET $fields WHERE $conditions";
            }
        }

        $this->check_current_query = false;
        return $this->query($this->prepare($sql, $values));
    }




        /**
     * Retrieves one row from the database.
     *
     * Executes a SQL query and returns the row from the SQL result.
     *
     * @since 0.71
     *
     * @param string|null $query  SQL query.
     * @param string      $output Optional. The required return type. One of OBJECT, ARRAY_A, or ARRAY_N, which
     *                            correspond to an stdClass object, an associative array, or a numeric array,
     *                            respectively. Default OBJECT.
     * @param int         $y      Optional. Row to return. Indexed from 0.
     * @return array|object|null|void Database query result in format specified by $output or null on failure.
     */
    public function get_row($query = null, $output = OBJECT, $y = 0)
    {
        $this->func_call = "\$db->get_row(\"$query\",$output,$y)";

        if ($query) {
            if ($this->check_current_query && $this->check_safe_collation($query)) {
                $this->check_current_query = false;
            }

            $this->query($query);
        } else {
            return null;
        }

        if (! isset($this->last_result[ $y ])) {
            return null;
        }

        if (OBJECT === $output) {
            return $this->last_result[ $y ] ? $this->last_result[ $y ] : null;
        } elseif (ARRAY_A === $output) {
            return $this->last_result[ $y ] ? get_object_vars($this->last_result[ $y ]) : null;
        } elseif (ARRAY_N === $output) {
            return $this->last_result[ $y ] ? array_values(get_object_vars($this->last_result[ $y ])) : null;
        } elseif (OBJECT === strtoupper($output)) {
            // Back compat for OBJECT being previously case-insensitive.
            return $this->last_result[ $y ] ? $this->last_result[ $y ] : null;
        } else {
            $this->print_error(' $db->get_row(string query, output type, int offset) -- Output type must be one of: OBJECT, ARRAY_A, ARRAY_N');
        }
    }




    /**
     * Deletes a row in the table.
     *
     * Examples:
     *
     *     wpdb::delete( 'table', array( 'ID' => 1 ) )
     *     wpdb::delete( 'table', array( 'ID' => 1 ), array( '%d' ) )
     *
     * @since 3.4.0
     *
     * @see wpdb::prepare()
     * @see wpdb::$field_types
     * @see wp_set_wpdb_vars()
     *
     * @param string       $table        Table name.
     * @param array        $where        A named array of WHERE clauses (in column => value pairs).
     *                                   Multiple clauses will be joined with ANDs.
     *                                   Both $where columns and $where values should be "raw".
     *                                   Sending a null value will create an IS NULL comparison - the corresponding
     *                                   format will be ignored in this case.
     * @param array|string $where_format Optional. An array of formats to be mapped to each of the values in $where.
     *                                   If string, that format will be used for all of the items in $where.
     *                                   A format is one of '%d', '%f', '%s' (integer, float, string).
     *                                   If omitted, all values in $data will be treated as strings unless otherwise
     *                                   specified in wpdb::$field_types.
     * @return int|false The number of rows updated, or false on error.
     */
    public function delete($table, $where, $where_format = null)
    {
        if (! is_array($where)) {
            return false;
        }

        $where = $this->process_fields($table, $where, $where_format);
        if (false === $where) {
            return false;
        }

        $conditions = array();
        $values     = array();
        foreach ($where as $field => $value) {
            if (is_null($value['value'])) {
                $conditions[] = "`$field` IS NULL";
                continue;
            }

            $conditions[] = "`$field` = " . $value['format'];
            $values[]     = $value['value'];
        }

        $conditions = implode(' AND ', $conditions);

        $tablex = explode('.', $table);
        if (isset($tablex[1]) && $tablex[1] !='') {
            $sql = "DELETE FROM  $tablex[0].`$tablex[1]` WHERE $conditions";
        } else {
            $blog_id = get_current_blog_id();
            $database = $this->getSiteDB($blog_id);
            if ($database !='') {
                if (!in_array($table, $this->getCommonTables())) {
                    $sql = "DELETE FROM $database.`$table` WHERE $conditions";
                } else {
                    $sql = "DELETE FROM `$table` WHERE $conditions";
                }
            } else {
                $sql = "DELETE FROM `$table` WHERE $conditions";
            }
        }

        $this->check_current_query = false;
        return $this->query($this->prepare($sql, $values));
    }
}

global $wpdb;
$wpdb = new Multidb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
