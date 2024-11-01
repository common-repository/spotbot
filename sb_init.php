<?php
/*
Plugin Name: Spot Bot
Plugin URI: http://www.jakehelbig.com/
Description: SpotBot uses BotScout bot detection system to determine if a user is a threat.  When a threat is found all comment and login forms with appropriate php files are blocked from access.  Allows management of IPs to give or deny access.  Self-pruning to keep a low DB footprint.  Keep notes about problematic users, and search the entire database for keywords/ip's.    
Version: 0.1.8
Author: Jake Helbig
Author URI: http://www.jakehelbig.com
License: GPLv3
    Version 3, 29 June 2007
    Copyright (C) 2007 Free Software Foundation, Inc. <http://fsf.org/>
    Everyone is permitted to copy and distribute verbatim copies of this license document, but changing it is not allowed.
*/


//install of plugin
register_activation_hook(__FILE__, Array('SpotBot', 'install'));

//uninstall of plugin
register_uninstall_hook(__FILE__, Array('SpotBot', 'uninstall'));

//action triggers for spotbot


    //comments form replacement
    add_action('comment_form', Array('SpotBot', 'spot_the_bot'));

    //login , register , lost passwords
    add_action('login_form', Array('SpotBot', 'spot_the_bot'));  //displays warning on login form
    add_action('register_form', Array('SpotBot', 'spot_the_bot'));  //displays warning on registration form
    add_action('lostpassword_form', Array('SpotBot', 'spot_the_bot'));  //displays warning on lost password form
    
    //direct access to files
    add_action('register_post', Array('SpotBot', 'stop_the_bot'));  //stops access to submit registration form
    add_action('lostpassword_post', Array('SpotBot', 'stop_the_bot'));  //stops access to submit lost password form
    add_action('wp_authenticate', Array('SpotBot', 'stop_the_bot'));  //stops bots from accessing wp-login.php
    //add_action('wp_blacklist_check ', Array('SpotBot', 'stop_the_bot'));  //stops bots from accessing wp-comments-post.php
    add_action('preprocess_comment', Array('SpotBot', 'stop_the_bot'));  //stops access to wp-comments-post.php via the preprocess action.
    
    //  custom action to fire if needed on any php page
    //  paste this code into any php to block access to that file and any pages assocated with it
    //      do_action('stopthebot');
    //      !WARNING!  -- use with caution, may block your access to your site if handled incorrectly
    //  great to use in wp-comments-post.php after this line
    //      require( dirname(__FILE__) . '/wp-load.php' );
    add_action('stopthebot', Array('SpotBot', 'stop_the_bot'));
    
    //spotbot cleanup
    add_action('admin_init', Array('SpotBot', 'cleanup'));

    //action for menu creation
    add_action('admin_menu', 'spotbot_admin_menu');

    //action for admin menu scripts
    add_action('admin_init', Array('SpotBot', 'enq_scripts'));
    
    //warn admin about no API key
    add_action('admin_notices', Array('SpotBot', 'key_warning'));


function spotbot_admin_menu(){
    add_menu_page('SpotBot', 'SpotBot', 'activate_plugins', 'sb_list', Array('SpotBot', 'list_menu'), plugins_url('spotbot/images/spotbotmini.png'));
    add_submenu_page('sb_list', 'Settings', 'Settings', 'activate_plugins', 'sb_config', Array('SpotBot', 'config_menu'));
    add_submenu_page('sb_list', 'FAQ', 'FAQ', 'activate_plugins', 'sb_faq', Array('SpotBot', 'faq_menu'));
    //add_submenu_page('sb_list', 'DEBUG', 'DEBUG', 'activate_plugins', 'sb_debug', Array('SpotBot', 'debug'));
}



class SpotBot {
    
    private $debug_var;
    
    
    
    /*
******          INSTALL & UNINSTALL
     */
    
    
    //used to check if the plugin has been installed before
    //and verify that the table in the DB has been setup correctly
    static function install(){
        
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // create spotbot meta table, holds information that tracks a bot or users IP.
        // No other indetification is logged other than IP, so there's no way to monitor
        // individuals.
        if(!$wpdb->query('SELECT * FROM ' . $wpdb->prefix . 'spotbot_meta WHERE 1;')){
            
            //create sql query to run on DB
            /*
             *  spot_ip         - the ip logged for a user
             *  spot_hits       - how many times a user was blocked
             *  spot_verified   - sets user to 0(BOT)/1(HUMAN)
             *  spot_notes      - admin notes for individual users
             *  spot_time       - timestamp of last occurance
             *
             */
            $sql = "CREATE TABLE " . $wpdb->prefix . "spotbot_meta (
            id mediumint NOT NULL AUTO_INCREMENT,
            spot_ip VARCHAR(15) DEFAULT '255.255.255.255' NOT NULL,
            spot_hits smallint(7) DEFAULT '0' NOT NULL,
            spot_verified BOOLEAN DEFAULT '0' NOT NULL,
            spot_notes VARCHAR(256) DEFAULT '' NOT NULL,
            spot_time DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
            UNIQUE KEY id (id));";
            
            
            //run Query on DB
            if($wpdb->query($sql)){
                add_option( "spotbot_version", "0.1.8" );
            }
        }
        
        // create spotbot config table, holds information like botscount API key, and html
        // output when spotbot is triggered.
        if(!$wpdb->query('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;')){
            
            //create sql query to run on DB
            /*
             *  spot_key            - API key from BotScout
             *  spot_threshold      - how many hits from botscout are needed to trigger a bot status
             *  spot_active         - activates or deactivates spotbot for maintenence
             *  spot_cleanup        - activates or deactivates cleanup of old IPs
             *  spot_cleantime      - how many months old an IP must be before it can be cleaned
             *  spot_warning_html   - the warning message printed out to users that have been set as bot
             *
             */
            
            $sql = "CREATE TABLE " . $wpdb->prefix . "spotbot_config (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            spot_key VARCHAR(32) DEFAULT '' NOT NULL,
            spot_threshold smallint(3) DEFAULT '1' NOT NULL,
            spot_active BOOLEAN DEFAULT '1' NOT NULL,
            spot_cleanup BOOLEAN DEFAULT '0' NOT NULL,
            spot_cleantime smallint(2) DEFAULT '6' NOT NULL,
            spot_warning_html VARCHAR(512) DEFAULT 'You are not allowed to use this' NOT NULL,
            UNIQUE KEY id (id));";
            
            //run Query on DB
            if($wpdb->query($sql)){
                $wpdb->insert($wpdb->prefix . 'spotbot_config', Array('id' => null, 'spot_key' => null, 'spot_threshold' => '1', 'spot_active' => '1', 'spot_cleanup' => '0', 'spot_cleantime' => '6', 'spot_warning_html' => null));
                //$wpdb->query('INSERT INTO ' . $wpdb->prefix . 'spotbot_config VALUES(null,null,1,1,0,6,null);');
            }
        }
        
    }
    
    
    
    // remove any and all information assocated with the plugin from the server(s)
    static function uninstall(){
        
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        //remove tables
        $wpdb->query('DROP TABLE ' . $wpdb->prefix . 'spotbot_meta;');
        $wpdb->query('DROP TABLE ' . $wpdb->prefix . 'spotbot_config;');
        
    }
    
    
    
    //cleanup
    static function cleanup(){
        //if user can manage plugins continue
        if ( current_user_can( 'activate_plugins' ) )  {
            //if cookie is not set, continue
            if(!(isset($_COOKIE['spotbot_cleanup']))){
                
                //set wpdb object
                global $wpdb;
                
                //import upgrade functions built into WP
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                
                //get config data
                $config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', ARRAY_A);
                
                //if cleanup is on, clean DB
                if($config['spot_cleanup']){
                    //get the list of elements
                    $element = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'spotbot_meta WHERE 1;', ARRAY_A);
                    
                    foreach($element as $el){
                        //set string as new DateTime
                        $time1 = new DateTime($el['spot_time']);
                        $time2 = new DateTime(date('Y-m-d H:i:s'));
                        
                        //get the difference between element time and current time
                        $interval = $time1->diff($time2);
                        
                        //get how many years apart both are and multiply by 12 for amt of months
                        $years = ($interval->format('%Y')*12);
                        
                        //get how many months
                        $months = $interval->format('%m');
                        
                        //add years-months and months together for a total
                        $total = $years + $months;
                        
                        //unset both time1 and time2 objects to clear up any memory
                        unset($time1, $time2);
                        
                        if(($total > $config['spot_cleantime']) && ($el['spot_notes'] == "")){
                            $wpdb->delete($wpdb->prefix . 'spotbot_meta', Array('id' => $el['id']));
                        }
                    }
                    
                    //set cookie to denote a cleanup period, ends after an hour or at the end of the session
                    setcookie('spotbot_cleanup', 'cleaned', time()+3600);
                }
            }
        }
    }
    
    
    
    
    /*
******          ADMINISTRATION MENU OUTPUT
     */
    
    
    /*
     *  KEY WARNING
     *  
     *  WARN USER SPOTBOT HAS NO API KEY
     */
    static function key_warning(){
        
        if ( current_user_can( 'activate_plugins' ) )  {
            //set wpdb object
            global $wpdb;
            
            //import upgrade functions built into WP
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            
            $config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', ARRAY_A);
            
            if($config['spot_key'] == ""){
                printf( '<div class="update-nag"> %s </div>',__( 'SpotBot API key is not <a href="admin.php?page=sb_config">setup</a>!  You must apply for an API key from <a href="http://botscout.com/getkey.htm" target="_BLANK">BotScout</a> before using SpotBot!', 'plugin_domain' ) );
            }
        }
    }
    
    
    static function debug($data){
        if ( current_user_can( 'activate_plugins' ) )  {
            ?>
            <?php add_thickbox(); ?>
            <div id="spotbot_debug" style="display:none;">
                <p>
                    <pre>
                       <?php var_dump($data); ?>
                    </pre>
                </p>
            </div>
            <?php
            printf( '<div class="update-nag"> %s </div>',__( '<a href="#TB_inline?width=800&height=800&inlineId=spotbot_debug" class="thickbox">View the Debug Data</a>', 'plugin_domain' ) );
        }
    }
    
    
    
    
    /*
     *  CONFIG MENU
     *  
     *  Menu used to configure spotbot
     */
    static function config_menu(){
        
        if ( !current_user_can( 'activate_plugins' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        //enque scripts for SpotBot
        wp_enqueue_script('js_sb_functions');
        
        //enque styles & scripts needed for editor
        wp_enqueue_script( 'word-count' );
        wp_enqueue_script('post');
        if ( user_can_richedit() )
                wp_enqueue_script('editor');
        add_thickbox();
        wp_enqueue_script('media-upload');
        
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        //query db for config information
        $admin_config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', OBJECT);
        
        //check if anything has been posted to the page
        if(isset($_POST['temp'])){
            $reload = false;
            
            //botscout Enable/Disable SpotBot
            if(!($admin_config->spot_active == $_POST['spotbot_active'])){
                //update apikey
                $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_active' => $_POST['spotbot_active']), Array('id' => 1));
                $reload = true;
            }
            
            //botscout Enable/Disable Cleanup
            if(!($admin_config->spot_cleanup == $_POST['spotbot_clean'])){
                //update apikey
                $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_cleanup' => $_POST['spotbot_clean']), Array('id' => 1));
                $reload = true;
            }
            
            //cleanup time
            if(!($admin_config->spot_cleantime == $_POST['spotbot_cleantime'])){
                if(!($_POST['spotbot_cleantime'] <= 0)){
                    //update threshhold
                    $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_cleantime' => $_POST['spotbot_cleantime']), Array('id' => 1));
                    $reload = true;
                }
            }
            
            //botscout API key
            if(!($admin_config->spot_key == $_POST['spotbot_apikey'])){
                //update apikey
                $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_key' => $_POST['spotbot_apikey']), Array('id' => 1));
                $reload = true;
            }
            
            //threshold count
            if(!($admin_config->spot_threshold == $_POST['spotbot_threshold'])){
                if(!($_POST['spotbot_threshold'] <= 0)){
                    //update threshhold
                    $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_threshold' => $_POST['spotbot_threshold']), Array('id' => 1));
                    $reload = true;
                }
            }
            
            //warning HTML
            if(!($admin_config->spot_warning_html == addslashes($_POST['spotbot_warning']))){
                //update warning html
                $wpdb->update($wpdb->prefix . 'spotbot_config', Array('spot_warning_html' => addslashes($_POST['spotbot_warning'])), Array('id' => 1));
                $reload = true;
            }
            
            //if anything has changed then requery the DB for update versions of the DATA to print to the user
            if($reload){
                $admin_config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', OBJECT);
            }
            
        }
            
            
        ?>
        
        <div class="wrap">
            <div id="icon-options-general" class="icon32"></div>
            <h2>SpotBot Configuration</h2>
            <div id="poststuff">
                
                <form method="POST" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
                    
                    <!-- configuration settings -->
                    <div id="spotbot-post" class="postbox" style="display: block;margin-top: 20px;">
                        <div class="handlediv" title="Click to toggle"><br></div>
                        <h3 class="hndle"><span>SpotBot Settings</span></h3>
                        <div class="inside">
                            <div class="spotdiv" id="post_spotbot">
                                <table>
                                    <tr>
                                        <td>
                                            <label for="spotbot_active" title="">Enable SpotBot: </label>
                                        </td>
                                        <td>
                                            <input type="checkbox" id="spotbot_active" value="1" name="spotbot_active" <?php if($admin_config->spot_active){ echo 'checked="true"'; } ?> />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <label for="spotbot_clean" title="">Enable Cleanup: </label>
                                        </td>
                                        <td>
                                            <input type="checkbox" id="spotbot_clean" value="1" name="spotbot_clean" <?php if($admin_config->spot_cleanup){ echo 'checked="true"'; } ?> />
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <label for="spotbot_cleantime" title="How many months old an IP must be before spotbot deletes it.">Cleanup IPs older than: </label>
                                        </td>
                                        <td>
                                            <input id="spotbot_cleantime" maxlength="2" size="20" name="spotbot_cleantime" value="<?php echo $admin_config->spot_cleantime; ?>" /> month(s)
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <label for="spotbot_apikey" title="Get this key from BotScout.com when you register for an API key">API Key: </label>
                                        </td>
                                        <td>
                                            <input id="spotbot_apikey" maxlength="32" size="20" name="spotbot_apikey" value="<?php echo $admin_config->spot_key; ?>" />
                                            <span id="botscout_check_key" ip-test="<?php echo $_SERVER['REMOTE_ADDR']; ?>">Check it!</span>
                                        </td>
                                    </tr>
                                </table>
                            </div><!-- end.spotdiv -->
                        </div><!-- end.inside-->
                    </div><!-- end.postbox -->
                    
                    <!-- html warning -->
                    <div class="warn-wrapper" style="margin-bottom: 20px;">
                        <h3><label class="">Warning HTML: </label></h3>
                        <div id="postdivrich" class="postarea">
                            <?php the_editor(str_replace("\\", "", $admin_config->spot_warning_html), $id = 'spotbot_warning', $prev_id = 'title', $media_buttons = true, $tab_index = 2); ?>
                        </div>
                    </div>
                    
                    <input type="hidden" value="wut" name="temp" />
                    <input class="button-primary" type="submit" name="save" value="<?php _e('Save Options'); ?>" id=sb_configsubmit" />
                </form>
            </div>
            
        </div>
        
        <?php
        
    }
    
    
    
    /*
     *  LIST MENU
     *  
     *  Menu used to display logged IPs
     */
    static function list_menu(){
        
        if ( !current_user_can( 'activate_plugins' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        //query db for IP information
        $query = 'SELECT * FROM ' . $wpdb->prefix . 'spotbot_meta';
        
        //query to count the amount of elements returned from the DB for searches and normal use
        $pagecount_query = 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'spotbot_meta';
        
        /**********************************************************\
         *
         * $_COOKIE['spotbot_limit'] -> the limit of items per page
         *      DEFAULT: 10
         *
         * $_COOKIE['spotbot_page'] -> the page number requested
         *      DEFAULT: 1
         *
         * $_GET['spotbot_search'] -> the keyword(s) searched for
         *      DEFAULT: N/A
         *
        \**********************************************************/
        
        //search query requested
        if(isset($_GET['spotbot_search'])){
            //normal query
            $query .= ' WHERE spot_ip LIKE \'%' . urldecode($_GET['spotbot_search']) . '%\' OR spot_notes LIKE \'%' . urldecode($_GET['spotbot_search']) . '%\'';
            //page count query
            $pagecount_query .= ' WHERE spot_ip LIKE \'%' . urldecode($_GET['spotbot_search']) . '%\' OR spot_notes LIKE \'%' . urldecode($_GET['spotbot_search']) . '%\'';
        }
        
        //sort by timestamp newest to oldest
        $query .= ' ORDER BY spot_time DESC';
        
        
        //limit items per page query addition
        if(isset($_COOKIE['spotbot_limit'])){
            $limit = $_COOKIE['spotbot_limit'];
            $query .= ' LIMIT ' . $_COOKIE['spotbot_limit'];
        }else{
            $limit = 10;
        }
        
        
        //page number query addition
        if(isset($_GET['sbp'])){
            //offset cannot be 1 as it will not post the first element from the DB
            if($_GET['sbp'] <= 1){
                $offset = "0";
            }else{
                //limit offset for mysql pagination,  EX: ((2-1)*(10))+1 = ((2)*(10))+1 = (20)+1= 21 offset for page 2
                $offset = ((($_GET['sbp'] - 1) * ($_COOKIE['spotbot_limit'])));
            }
            $curpage = $_GET['sbp'];
            $query .= ' OFFSET ' . $offset;
        }else{
            $curpage = 1;
        }
        
        
        //set cookies and define query as default 10 items page 1
        if(!(isset($_COOKIE['spotbot_limit']))){
            ?>
            <script type="text/javascript">
                document.cookie='spotbot_limit = 10';
                document.cookie='spotbot_page = 1';
            </script>
            <?php
            
            //setcookie('spotbot_limit', 10);
            //setcookie('spotbot_page', 1);
            $query .= ' LIMIT 10 OFFSET 0';
        }
        
        //end the query statement
        $query .= ';';
        
        //perform query on DB
        $sb_meta = $wpdb->get_results($query, ARRAY_A);
        
        //count the amount of entires in the DB
        //$sbip_count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'spotbot_meta;');
        $sbip_count = $wpdb->get_var($pagecount_query . ';');
        
        //enque scripts for SpotBot
        wp_enqueue_script('js_sb_functions');
        
        
            //form actions from ajax
            if(isset($_POST['type'])){
                $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_verified' => $_POST['type']), Array('id' => $_POST['id']));
            }
            
            if(isset($_POST['note'])){
                $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_notes' => urldecode($_POST['note'])), Array('id' => $_POST['id']));
            }
            
            if(isset($_POST['action'])){
                //split post string into array to be looped
                $ids = explode(",", urldecode($_POST['id']));
                
                if(in_array('on', $ids)){
                    $ids = array_diff($ids, Array('on'));
                }
                
                foreach($ids as $id){
                    
                    settype($id, "integer");
                    
                    if($_POST['action'] == "del"){
                        //delete
                        $wpdb->delete($wpdb->prefix . 'spotbot_meta', Array('id' => $id));
                    }elseif($_POST['action'] == "bot"){
                        //update to bot
                        $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_verified' => 0), Array('id' => $id));
                    }if($_POST['action'] == "hum"){
                        //update to human
                        $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_verified' => 1), Array('id' => $id));
                    }
                }
            }
            //end form actions from ajax
        
        ?>
        <div id="" class="icon32"><img style="" src="<?php echo plugins_url('spotbot/images/spotbot.png'); ?>" alt="spotbot logo" width="32px" /></div>
        <h2>SpotBot IP Listing</h2>
        <div id="list" style="margin-right: 20px;" >
            <div class="tablenav top" style="overflow: hidden; padding: 4px 0px;">
                <label class="alignleft" style="margin-right: 20px;"> All Selected: 
                    <select class="selected_action">
                        <option></option>
                        <option value="delall">Delete</option>
                        <option value="botall">Set to Bot</option>
                        <option value="humall">Set to Human</option>
                    </select>
                </label>
                <span class="alignleft" style="margin-right: 20px;">
                    <img class="refload" src="<?php echo plugins_url('/spotbot/images/refresh.png'); ?>" height="20px" alt="refresh page" />
                </span>
                <label class="alignleft" style="margin-right: 20px;"> Number of Entries: 
                    <select class="limit">
                        <option></option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <label class="alignleft" style="margin-right: 20px;"> Find: 
                    <input type="input" id="searchquery" <?php if(isset($_GET['spotbot_search'])){ echo 'value="' . urldecode($_GET['spotbot_search']) . '"';}; ?> />
                    <button class="button-primary" id="searchbtn" >Search</button>
                    <a style="margin-left: 5px; color: #c10000;" href="<?php menu_page_url('sb_list', true); ?>" alt="reset" />Reset</a>
                </label>
                <label class="alignright">
                    <div class='tablenav-pages'>
                        <?php
                        
                        //get max page count
                        $maxpage = ceil($sbip_count/$limit);
                        
                        //echo out current ip's logged
                        echo '<span class="displaying-num">';
                        echo '' . $sbip_count . ' IP\'s Found';
                        echo '</span>';
                        
                        //set pagination variables
                        $page_links = paginate_links( array(
                                'base' => add_query_arg( 'sbp', '%#%' ),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $maxpage,
                                'current' => $curpage
                        ));
                        
                        //print out pagination
                        print_r($page_links);
                        ?>
                    </div>
                </label>
            </div>
            <div class="content-table" >
                <table class="wp-list-table widefat fixed" >
                    <thead>
                        <tr>
                            <th style="text-align: center;" width="5%"><input style="margin: 0px;" type="checkbox" class="checkem" /></th>
                            <th width="10%">IP Address</th>
                            <th width="10%">Hits</th>       
                            <th width="10%">Verified</th>
                            <th width="10%">Time <span title="current time">(<?php echo date('H:i:s'); ?>)</span></th>
                            <th width="55%">Notes</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th style="text-align: center;" width="40px;"><input style="margin: 0px;" type="checkbox" class="checkem" /></th>
                            <th>IP Address</th>
                            <th>Hits</th>       
                            <th>Verified</th>
                            <th>Time</th>
                            <th>Notes</th>
                        </tr>
                    </tfoot>
                    <tbody class="listbody">
                        <?php
                        foreach($sb_meta as $sm){
                            ?>
                            <?php if(($sm['spot_ip'] == $_SERVER['REMOTE_ADDR'])){
                                ?>
                                <tr style="font-weight: bold;background: #b1b5cf;" title="THIS IS YOU" >
                                    <td></td>
                                    <td><?php echo $sm['spot_ip']; ?></td>
                                    <td><?php echo $sm['spot_hits']; ?></td>
                                    <td><?php if($sm['spot_verified']){ echo '<span style="color:green;" >HUMAN</span>'; }else{ echo '<span style="color:red;" >BOT</span>'; } ?></td>
                                    <td><?php echo $sm['spot_time']; ?> UTC</td>
                                    <td><textarea class="notes" style="width: 100%;" rows="1" alt-data="<?php echo $sm['id']; ?>"><?php echo $sm['spot_notes']; ?></textarea></td>
                                </tr>
                                <?php
                            }else{
                                ?>
                                <tr entry-id="<?php echo $sm['id']; ?>">
                                    <td style="text-align: center;"><input type="checkbox" class="entry" value="<?php echo $sm['id']; ?>" /></td>
                                    <td><?php echo $sm['spot_ip']; ?></td>
                                    <td><?php echo $sm['spot_hits']; ?></td>
                                    <td><?php if($sm['spot_verified']){ echo '<span class="verified" style="color:green;" alt-data="' . $sm['id'] . '" >HUMAN</span>'; }else{ echo '<span class="verified" style="color:red;" alt-data="' . $sm['id'] . '" >BOT</span>'; } ?></td>
                                    <td><?php echo $sm['spot_time']; ?> UTC</td>
                                    <td><textarea class="notes" style="width: 100%;" rows="2" alt-data="<?php echo $sm['id']; ?>"><?php echo $sm['spot_notes']; ?></textarea></td>
                                </tr>
                            <?php
                            }//end if ip == current user ip
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php
    }
    
    static function faq_menu(){
        
        if ( !current_user_can( 'activate_plugins' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        
        
        //enque scripts for SpotBot
        //wp_enqueue_script('jquery-ui-accordion', 'jquery');
        wp_enqueue_script('rwdImageMaps');
        wp_enqueue_script('js_sb_functions');
        wp_enqueue_style('spotbot_style');
        
        ?>
        
        <div id="" class="icon32"><img style="" src="<?php echo plugins_url('spotbot/images/spotbot.png'); ?>" alt="spotbot logo" width="32px" /></div>
        <h2 id="top">SpotBot</h2>
        <div id="wrap" style="margin: 20px;">
            <h2 class="nav-tab-wrapper">
                <a href="<?php menu_page_url('sb_faq', true); ?>&tab=faq" class="nav-tab<?php if($_GET['tab'] != 'faq_about'){ echo ' nav-tab-active'; } ?>">FAQs</a>
                <a href="<?php menu_page_url('sb_faq', true); ?>&tab=faq_about" class="nav-tab<?php if($_GET['tab'] == 'faq_about'){ echo ' nav-tab-active'; } ?>">About</a>
            </h2>
            <?php if($_GET['tab'] != 'faq_about'){ ?>
                <!-- FAQ PAGE -->
                <div id="accordion">
                  <h2>Contents</h2>
                  <div>
                    <div class="ind-cont">
                      <ul>
                          <li><a href="#welcome" alt="welcome">Welcome</a></li>
                          <li>
                              <a href="#anatomy-of" alt="anatomy of spotbot">The Anatomy of SpotBot</a>
                              <ul class="ind-cont">
                                  <li><a href="#anat-list" alt="welcome">Bot List</a></li>
                                  <li><a href="#anat-settings" alt="welcome">Settings</a></li>
                              </ul>
                          </li>
                          <li><a href="#custom-action" alt="how to use the custom action">How to use the custom action</a></li>
                          <li><a href="#api-key" alt="where do you get an API key?">The API key, what it is and how it's used</a></li>
                      </ul>
                    </div>
                    <hr />
                  </div>
                  <h2 id="welcome">Welcome</h2>
                  <div>
                    <div class="ind-cont">
                        <p>
                            Welcome to the FAQ! Anything and everything you need to know about SpotBot can be found here.
                            Need to know how to set your BotScout API key?  It's here.  Need to figure out how to use a
                            custom action in your template?  That's here too!  I wish you luck and if anything isn't found
                            here, submit a request on SpotBots wordpress plugin page!
                        </p>
                    </div>
                    <a style="font-size: 6pt;" href="#top" alt="back to top">Top</a>
                    <hr />
                  </div>
                  <h2 id="anatomy-of">Whats does this button do?  The Anatomy of SpotBot!</h2>
                  <div>
                    <div class="ind-cont">
                        <p>
                            Below are some images from the interface of SpotBot.  The elements highlighted in red will tell you more information if you hover over them.
                        </p>
                        <p>
                            <h3 id="anat-list">Bot List</h3>
                            <center><img src="<?php echo plugins_url('spotbot/images/faq/list_anatomy.png'); ?>" usemap="#list_map" /></center>
                            <map id="list_map" name="list_map">
                                <area shape="rect" coords="160,70,343,107" href="#" alt="Contains actions to be done to all items selected via the check-box from the list below.  Action options are; change to bot, change to human, and delete." title="Contains actions to be done to all items selected via the check-box from the list below.  Action options are; change to bot, change to human, and delete."    />
                                <area shape="rect" coords="352,70,389,107" href="#" alt="Refresh Button/Process Indicator" title="Refresh Button/Process Indicator"    />
                                <area shape="rect" coords="396,70,566,107" href="#" alt="Shows number of items in the list below.  Change from 10, 25, 50, and 100." title="Shows number of items in the list below.  Change from 10, 25, 50, and 100."    />
                                <area shape="rect" coords="574,70,861,107" href="#" alt="Search for an item or multiple items.  Can search through notes left by admin or from a partial/whole IP address.  Reset clears the search and returns you back to a fresh list." title="Search for an item or multiple items.  Can search through notes left by admin or from a partial/whole IP address.  Reset clears the search and returns you back to a fresh list."    />
                                <area shape="rect" coords="1165,70,1261,107" href="#" alt="Indicator of how many total IPs are available.  When the page minimum is exceeded, page numbers are shown here as well." title="Indicator of how many total IPs are available.  When the page minimum is exceeded, page numbers are shown here as well."    />
                                <area shape="rect" coords="181,171,202,196" href="#" alt="Select individual elements here, or use the select all check-box at the top or bottom of the list.  Used in conjunction with the All Selected drop-down to commit an action to all selected elements." title="Select individual elements here, or use the select all check-box at the top or bottom of the list.  Used in conjunction with the All Selected drop-down to commit an action to all selected elements."    />
                                <area shape="rect" coords="225,171,303,196" href="#" alt="The IP address logged for the individual item." title="The IP address logged for the individual item."    />
                                <area shape="rect" coords="330,171,350,196" href="#" alt="Number of times this IP has been logged.  Will only count higher than 1 for bots!" title="Number of times this IP has been logged.  Will only count higher than 1 for bots!"    />
                                <area shape="rect" coords="441,171,493,196" href="#" alt="Verification indicator to show if logged as human or bot.  Click to change between bot or human, changes are saved immediately." title="Verification indicator to show if logged as human or bot.  Click to change between bot or human, changes are saved immediately."    />
                                <area shape="rect" coords="551,171,635,207" href="#" alt="Indicates when the last known presence of this IP was detected.  All time-stamps are in UTC.  Current UTC time is found at the top and bottom of the list.  Each time a bot is detected, their time-stamp is updated to keep the most active bots off your website." title="Indicates when the last known presence of this IP was detected.  All time-stamps are in UTC.  Current UTC time is found at the top and bottom of the list.  Each time a bot is detected, their time-stamp is updated to keep the most active bots off your website."    />
                                <area shape="rect" coords="661,171,1255,218" href="#" alt="Notes for the admin to leave about a particular IP.  Used mostly for easy to find access to particular IPs.  Also great for noting problem users directly.  After inserting a note, click outside of the box to submit." title="Notes for the admin to leave about a particular IP.  Used mostly for easy to find access to particular IPs.  Also great for noting problem users directly.  After inserting a note, click outside of the box to submit."    />
                                <area shape="rect" coords="160,275,1261,321" href="#" alt="This is you!  Cannot delete yourself, or change your status to a bot.  Can only write notes.  Highly recommended to tag work and home IPs as not to lock yourself out accidentally." title="This is you!  Cannot delete yourself, or change your status to a bot.  Can only write notes.  Highly recommended to tag work and home IPs as not to lock yourself out accidentally."    />
                            </map>
                        </p>
                        <p>
                            <h3 id="anat-settings">SpotBot Settings</h3>
                            <center><img src="<?php echo plugins_url('spotbot/images/faq/config_anatomy.png'); ?>" alt="" usemap="#config_map" /></center>
                            <map id="config_map" name="config_map">
                                <area shape="rect" coords="171,140,327,163" href="#" alt="Enable or Disable SpotBot" title="Enable or Disable SpotBot"    />
                                <area shape="rect" coords="171,160,327,183" href="#" alt="Enable or Disable Cleanup.  Cleanup will delete old IPs from the database that have not been seen recently.  You can change how old an IP must be in order for it to be removed.  By Default it is 6 months." title="Enable or Disable Cleanup.  Cleanup will delete old IPs from the database that have not been seen recently.  You can change how old an IP must be in order for it to be removed.  By Default it is 6 months."    />
                                <area shape="rect" coords="171,180,512,214" href="#" alt="Sets how old an IP must be to be removed from the database.  Default is 6 months, you may need to adjust this to a lower number if you have a higher volume site." title="Sets how old an IP must be to be removed from the database.  Default is 6 months, you may need to adjust this to a lower number if you have a higher volume site."    />
                                <area shape="rect" coords="171,214,512,248" href="#" alt="Your BotScout API key.  This is needed to allow spotbot to work!  After entering your key, click 'Check it!' to validate your key." title="Your BotScout API key.  This is needed to allow spotbot to work!  After entering your key, click 'Check it!' to validate your key."    />
                                <area shape="rect" coords="161,364,1259,599" href="#" alt="The warning message for users when SpotBot is triggered for browsers." title="The warning message for users when SpotBot is triggered for browsers."    />
                            </map>
                        </p>
                    </div>
                    <a style="font-size: 6pt;" href="#top" alt="back to top">Top</a>
                    <hr />
                  </div>
                  <h2 id="custom-action">How to use the custom action!</h2>
                  <div>
                    <div class="ind-cont">
                        <p>SpotBot uses actions within Wordpress to basically do what it does.  There is also a custom action built in that will allow you to block access to the entire website if you see fit.  Although I don't recommend blocking your entire site, it's ultimately your choice on how to use this feature.</p>
                        <p>While creating your custom template, post the following code within the php file:</p>
                        <pre style="background: #5f5f5f; color: #00ff00; font-weight: bold; border-radius: 5px;border: 1px solid #3a3a3a;padding: 10px">&lt;?php <span style="color: #ff8000;">doaction('stopthebot');</span> ?&gt;</pre>
                        <p>It must be inserted sometime after your header has been called, as none of the scripts required have been loaded before then.</p>
                    </div>
                    <a style="font-size: 6pt;" href="#top" alt="back to top">Top</a>
                    <hr />
                  </div>
                  <h2 id="api-key">What's an API key, and why you need one!</h2>
                  <div>
                    <div class="ind-cont">
                        <p>An API key will allow you access to the BotScout API.  Each time a user applies for an API key, an accout is generated with BotScout and is used to monitor traffic and query counts.  By default BotScout only allows 300 queries to be run per day from a user.  You can "buy" more if you wish by <a href="http://botscout.com/corp_users.htm" target="_BLANK" alt="buy more queries per day">donating</a> to the BotScout cause.  However signing up is free and easy.  Here's how easy it is:</p>
                        <p>Fill out the information <a href="http://botscout.com/getkey.htm" target="_BLANK" alt="botscout apply for api key">here</a>.  It may take up to 24 hours for you to get your API key, but it usually is a matter of minutes.  Be sure to check your spam filter, as sometimes email providers block keywords such as bot.</p>
                        <p>Once you get your email there will be an multi-case alphanumeric code.  It should be around 15 characters long.  Select and copy your key, then paste it in the API Key area in SpotBot settings.</p>
                        <p>To validate your key, click on "Check it!".  If it says <span style="color: green;">OK!</span>, then your key is valid and working(Don't forget to hit the Save Options button at the bottom!)</p>
                        <p>However, if your check comes back as <span style="color: red;">INVALID!</span>, there is either something wrong with your key or BotScout has not updated access for you yet.  Wait an hour, and try again.  If problems persist, <a href="http://botscout.com/contact.htm" target="_BLANK" alt="contact botscout">contact BotScout</a> for more details.</p>
                    </div>
                    <a style="font-size: 6pt;" href="#top" alt="back to top">Top</a>
                    <hr />
                  </div>
                </div>
                <?php }else{ ?>
                <!-- ABOUT PAGE -->
                <p>SpotBot was created for the purpose of controlling spam before it clutters up your server.  Gone are the days of constant comments about Gucci bags in broken English.  SpotBot not only stops spam from comments, but also stops bots from creating fake accounts or even logging in, but the fun doesn't stop there!  A custom action has been built in so you can add it into any template to stop access to that page!</p>
                
                
                <h3><span style="text-decoration: underline;font-weight: bold;">So how does it work?</span></h3>
                <p>Every task that starts or ends in WordPress starts with an action!  SpotBot utilizes these actions, to gain control over who has access to your site!  But how can it tell who's bad and who's good?  SpotBot uses a service from <a href="http://www.botscout.com/" target="_BLANK" alt="botscout">BotScout</a> to determine who the baddies are.  They have allowed public access to their database through an API that SpotBot utilizes.  This API access is limited though, and only allows around 300 queries to it in any 24-hour period.  You could <a href="http://botscout.com/corp_users.htm">pay BotScout</a> for more access as they're fairly cheap, but to help them keep their costs down and help you have control over your visitors, all IP's are logged to your database.  Each time a returning IP is found, it checks the cache first and if nothing is found checks BotScout for more information.</p>
                
                <p>It's as simple as that,  SpotBot can be a set-it-and-forget-it plugin, but it can be more than that.  Have a problem with a particular user that keeps making user accounts?  Flag them as a bot!  The harder they try, the more flags they generate and the higher they go on the bot score!</p>
                
                <p>SpotBot comes with a built in search function to make finding those troublesome bots easier than ever.  Don't want to save the IP in a list of trouble makers?  Make a note of them in each IPs note section for easy searching.  Can only remember the first 3 digits of the IP address?  Search for it!</p>
                
                &nbsp;
                <h3><span style="text-decoration: underline;font-weight: bold;">What can it do for me?</span></h3>
                <p>Spotbot will do anything it can to help you in your fight against spam and bots.  The list below should give you a better understanding of what SpotBot can do for you.</p>
                
                <ol>
                    <li>Spammers are blocked from:
                        <ul style="list-style-type: circle; padding-left: 20px !important; padding-bottom: 5px;">
                            <li>Logging in</li>
                            <li>Registering</li>
                            <li>Requesting a Lost Password</li>
                            <li>Submitting Comments</li>
                            <li>Submitting Trackbacks</li>
                        </ul>
                    </li>
                    <li>Spammers are blocked in one of two ways:
                        <ul style="list-style-type: circle; padding-left: 20px !important; padding-bottom: 5px;">
                            <li>Blocked from accessing the comment/login form on the physical site</li>
                            <li>Blocked from accessing the physical php files used to post comments or login</li>
                        </ul>
                    </li>
                    <li>The Administrator has control over:
                        <ul style="list-style-type: circle; padding-left: 20px !important; padding-bottom: 5px;">
                            <li>Change the status of an individual IP to Human or Bot</li>
                            <li>Add notes to an individual IP</li>
                            <li>Search for a keyword in Notes or an IP address</li>
                            <li>The warning message that is output to blocked IP's</li>
                            <li>Auto-prune the database by a user defined amount in months</li>
                        </ul>
                    </li>
                </ol>
                <p>As you can see, SpotBot is well suited for stopping unwanted traffic before it becomes a problem.  Eventually after so many failures, a bot will move on and hopefully never return.  That's why I built in the auto-prune feature.  When I first installed SpotBot on my personal site, the first two days produced over 200 individual IPs and each bot had several dozen hits each!  From this, I saw a developing issue of sustainability from SpotBot.  On one hand you want to keep as many IPs as possible, on the other hand you want to keep your database footprint small.  Thus the Auto-prune feature was added!  With Auto-prune enabled, you can have old IPs deleted from the database that are older than any amount of months you specify.  By default, Auto-prune is disabled with a monthly setting of 6 months.</p>
                
                <p>Worried that recurring IPs will be deleted?  Don't, every time a bot attacks your site a new time-stamp is generated and updated to keep the most current and active bots to the top of your list!  Human users are only logged once, and are generally discarded after the set monthly period.  This keeps your server happy, healthy, and up-to-date!</p>
                
                &nbsp;
                
                &nbsp;
                <h3><span style="text-decoration: underline;font-weight: bold;">What are the cons?</span></h3>
                <p>Other than a few disgruntled users that surf the internet from a proxy, nothing!  I suppose you could figure the extra queries add an extra .024 seconds to each page load, but isn't it worth it?  Wouldn't you like a site that's low maintenance, don't you deserve it?  SpotBot thinks you do!</p>
                
                &nbsp;
                
                &nbsp;
                <center>
                    <p>If you really like this plugin donate to help fund more awesome stuff!</p>
                    <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
                        <input type="hidden" name="cmd" value="_s-xclick">
                        <input type="hidden" name="hosted_button_id" value="3B89DNUZ4Q2BU">
                        <input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
                        <img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
                    </form>
                </center>
                
                <?php } ?>
            
        </div>
        
        <?php
    }
    
    
    
    
    /*
******          SPOTBOT ACTION FUNCTIONS
     */
    
    
    //checks the local SpotBot Table for any known IPs that should be banned,
    //if none are found it continues to BotScout.com to query their API
    //if IP is found login, comment forms are replaced with warning message
    static function spot_the_bot(){
        
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        //test the ip against the local spotbot DB
        $local_test = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_meta WHERE spot_ip = "' . $_SERVER['REMOTE_ADDR'] . '";', OBJECT);
        
        //get the config data stored
        $config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', OBJECT);
        
        if(($config->spot_active) && ($config->spot_key != "")){  
            // if IP is not found in the local DB
            // query botscout api for more info
            if(!$local_test){
                
                //test IP against botscout
                $test = simplexml_load_file("http://botscout.com/test/?ip=" . $_SERVER['REMOTE_ADDR'] . "&format=xml&key=" . $config->spot_key . "");
                
                
                if(((strtolower($test->matched) == "y") && ($test->count > $config->spot_threshold))){
                    
                    // IP has been marked as a bot
                    // remove forms
                    self::form_is_kill(str_replace("\\", "", $config->spot_warning_html));                
                    
                    //insert IP into database and mark as bot
                    $wpdb->insert($wpdb->prefix . 'spotbot_meta', Array('id' => 'null', 'spot_ip' => $_SERVER['REMOTE_ADDR'],'spot_hits' => 1, 'spot_time' => date('Y-m-d H:i:s')));
                    
                }elseif(strtolower($test->matched) == "n"){
                    
                    // First time IP, passed botscout
                    // insert ip into DB as verified
                    $wpdb->insert($wpdb->prefix . 'spotbot_meta', Array('id' => 'null', 'spot_ip' => $_SERVER['REMOTE_ADDR'],'spot_hits' => 1, 'spot_verified' => 1, 'spot_time' => date('Y-m-d H:i:s')));
                }else{
                    //There was a problem contacting the botscout service
                    
                }
                
            // else it was found AND is not human (!FALSE)==TRUE
            }else{
                if(!$local_test->spot_verified){
                    
                    //remove forms
                    self::form_is_kill(str_replace("\\", "", $config->spot_warning_html));
                    
                    //update DB count
                    $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_hits' => ($local_test->spot_hits + 1), 'spot_time' => date('Y-m-d H:i:s')), Array('spot_ip' => $_SERVER['REMOTE_ADDR']));
                    
                }
            }
        }//end spot_active check
    }
    
    
    //checks the local SpotBot Table for any known IPs that should be banned,
    //if none are found it continues to BotScout.com to query their API
    //if IP is found all actions are halted and loading is stopped
    static function stop_the_bot(){
        //set wpdb object
        global $wpdb;
        
        //import upgrade functions built into WP
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        //test the ip against the local spotbot DB
        $local_test = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_meta WHERE spot_ip = "' . $_SERVER['REMOTE_ADDR'] . '";', OBJECT);
        
        //get the config data stored
        $config = $wpdb->get_row('SELECT * FROM ' . $wpdb->prefix . 'spotbot_config WHERE 1;', OBJECT);
        
        if(($config->spot_active) && ($config->spot_key != "")){  
            
            // if IP is not found in the local DB
            // query botscout api for more info
            if(!$local_test){
                
                //test IP against botscout
                $test = simplexml_load_file("http://botscout.com/test/?ip=" . $_SERVER['REMOTE_ADDR'] . "&format=xml&key=" . $config->spot_key . "");
                
                
                if(((strtolower($test->matched) == "y") && ($test->count > $config->spot_threshold))){         
                    
                    //insert IP into database and mark as bot
                    $wpdb->insert($wpdb->prefix . 'spotbot_meta', Array('id' => 'null', 'spot_ip' => $_SERVER['REMOTE_ADDR'],'spot_hits' => 1, 'spot_time' => date('Y-m-d H:i:s')));
                    
                    //stop all other scripts from completing
                    exit;
                    
                }elseif(strtolower($test->matched) == "n"){
                    
                    // First time IP, passed botscout
                    // insert ip into DB as verified
                    $wpdb->insert($wpdb->prefix . 'spotbot_meta', Array('id' => 'null', 'spot_ip' => $_SERVER['REMOTE_ADDR'],'spot_hits' => 1, 'spot_verified' => 1, 'spot_time' => date('Y-m-d H:i:s')));
                }
                
            // else it was found AND is not human (!FALSE)==TRUE
            }else{
                if(!$local_test->spot_verified){
                    
                    //update DB count
                    $wpdb->update($wpdb->prefix . 'spotbot_meta', Array('spot_hits' => ($local_test->spot_hits + 1), 'spot_time' => date('Y-m-d H:i:s')), Array('spot_ip' => $_SERVER['REMOTE_ADDR']));
                    
                    //stop all other scripts from completing
                    exit;
                    
                }
            }
        }//end spot_active check
    }
    
    
    //enqueue scripts to be used
    static function enq_scripts(){
        wp_register_script('js_sb_functions', plugins_url('spotbot/js/js_sb_func.js'));
        wp_register_script('rwdImageMaps', plugins_url('spotbot/js/jquery.rwdImageMaps.min.js'));
        wp_register_style('spotbot_style', plugins_url('spotbot/css/spotbot.css'));
    }
    
    
    //kills forms with warning message
    static function form_is_kill($msg){
        ?>
        <script type="text/javascript">
        
        if(document.getElementById('login') !== null){
            document.getElementById('login').innerHTML = '<?php echo addslashes($msg); ?>';
        }
        
        if(document.getElementById('respond') !== null){
            document.getElementById('respond').innerHTML = '<?php echo addslashes($msg); ?>';
        }
        </script>
        <?php
    }
    
}