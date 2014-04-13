<?php
/*
 * Plugin Name: Activity of Links
 * Description: A table containing activity from blogs that user's blog is linked to under Links Manager.
 * Author: Kinzie Brooks (Josh Stemmler & Paul Underwood, initial template)
 * Version: 1.0
 */

if(is_admin())
{
    new Activities_View_Table();
	if( ! function_exists('getUserId') ){
	include(ABSPATH . 'wp-content/plugins/sharedFunctions.php' );
	}
}

/**
 * Activities_View_Table class will create the page to load the table
 */
class Activities_View_Table
{

    /**
     * Display the list table page
     *
     * @return Void
     */
    public function list_table_page()
    {
        $activityViewTable = new Activity_View_Table();
        $activityViewTable->prepare_items();
        ?>
            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2>Activity of Linked Blogs</h2>
                <?php $actvityViewTable->display(); ?>
            </div>
        <?php
    }
}

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Create a new table class that will extend the WP_List_Table
 */
class Activity_View_Table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = 25;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        //$this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
			'blog_author' => "Blog Author",
			'blog_title' => "Blog",
			'post_title' => "Post",
            'comment_content' => 'Comment',
            'comment_date' => 'Date',
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array('blog_author' => array('blog_author', false), 'blog_title' => array('blog_title', false), 'post_title' => array('post_title', false), 'comment_date' => array('comment_date', false), 'comment_author' => array('comment_author', false));
    }

	private function table_data()
    {
	global $wpdb;
	$userId = getUserId();
	$sqlstr = '';
	$blog_list = wp_get_sites($args);
	$sqlstr = "SELECT 1 as blog_id, comment_date, comment_id, comment_post_id, comment_content, comment_date_gmt, comment_author from ".$wpdb->base_prefix ."comments where comment_approved = 1 AND comment_author = \"". $userId . "\"";
	$uni = '';
	foreach ($blog_list AS $blog) {
		if($blog['blog_id'] != 1){
			$uni = ' union ';
			$sqlstr .= $uni . " SELECT ".$blog['blog_id']." as blog_id, comment_date, comment_id, comment_post_id, comment_content, comment_date_gmt, comment_author from ".$wpdb->base_prefix .$blog['blog_id']."_comments where comment_approved = 1 AND comment_author = \"". $userId . "\"";                
		}
	}
	$limit = 50; //set your limit
	$limit = ' LIMIT 0, '. $limit;
	$sqlstr .= " ORDER BY comment_date_gmt desc " . $limit; 
	//echo($sqlstr);
	//echo($current_user->user_login);
	$comm_list = $wpdb->get_results($sqlstr);
	$blognamequery1 = "SELECT option_value FROM ". $wpdb->base_prefix . "options WHERE option_name = \"blogname\"";
	$postnamequery1 = "SELECT post_title FROM ". $wpdb->base_prefix ."posts WHERE ID = {$comment->comment_post_id}";
	$blogurlquery1 = "SELECT option_value FROM ". $wpdb->base_prefix . "options WHERE option_name = \"siteurl\"";
	$posturlquery1 = "SELECT guid FROM ". $wpdb->base_prefix . "posts WHERE ID = {$comment->comment_post_id}";
	$data = array();
	
	foreach($comm_list as $comment){
		//echo $comment->comment_post_id;
		if($comment->blog_id !=1){
			$data[] = array(
					'comment_content' => $comment->comment_content,
					'comment_date' => $comment->comment_date,
					'blog_title' => $wpdb->get_var("SELECT option_value FROM ". $wpdb->base_prefix . $comment->blog_id . "_options WHERE option_name = \"blogname\""),
					'post_title' => $wpdb->get_var("SELECT post_title FROM ". $wpdb->base_prefix . $comment->blog_id . "_posts WHERE ID = ". $comment->comment_post_id),
					'blog_url' => $wpdb->get_var("SELECT option_value FROM ". $wpdb->base_prefix . $comment->blog_id . "_options WHERE option_name = \"siteurl\""),
					'post_url' => $wpdb->get_var("SELECT guid FROM ". $wpdb->base_prefix . $comment->blog_id . "_posts WHERE ID = " . $comment->comment_post_id)
					);
		}
	    else{

			$data[] = array(
					'comment_content' => $comment->comment_content,
					'comment_date' => $comment->comment_date,
					'blog_title' => $wpdb->get_var($blognamequery1),
					'post_title' => $wpdb->get_var($postnamequery1. $comment->comment_post_id),
					'blog_url' => $wpdb->get_var($blogurlquery1),
					'post_url' => $wpdb->get_var($posturlquery1. $comment->comment_post_id)
					);
				}
			}
	return $data;
	}

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
			case 'blog_author':
				return;
			case 'blog_title':
				return "<a href =\"". $item["blog_url"]."\">" . $item[$column_name] . "</a>";
			case 'post_title':
				return "<a href =\"".$item["post_url"]."\">" . $item[$column_name] . "</a>";
            case 'comment_content':
				if(strlen($item[$column_name]) > 50){
				return substr($item[$column_name],0,50)."...";
				}
				if(strlen($item[$column_name])<50){
				return $item[$column_name];
				}
            case 'comment_date':
                return $item[$column_name];
            default:
                return print_r( $item, true ) ;
        }
    }
	
		 /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'blog_title';
        $order = 'asc';
 
        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }
 
        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }
 
 
        $result = strcmp( $a[$orderby], $b[$orderby] );
 
        if($order === 'asc')
        {
            return $result;
        }
 
        return -$result;
    }
}

add_action( 'wp_dashboard_setup', 'activity_links_dashboard_setup' );
function activity_links_dashboard_setup() {
    wp_add_dashboard_widget(
        'activity-links-dashboard-widget',
        'Activity of Linked Blogs',
        'activity_links_dashboard_content',
        $control_callback = null
    );
}

function activity_links_dashboard_content() {
		$activityViewTable = new Activity_View_Table();
        $activityViewTable->prepare_items();
		$activityViewTable->display();
}
?>