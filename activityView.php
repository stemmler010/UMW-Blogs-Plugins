<meta http-equiv="refresh" content="300">
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
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
			'blog_title' => "Blog",
			'post_title' => "Post Title",
			'activity_author' => "Activity Author",
			'activity_type' => "Activity Type",
            'recent_activity' => 'Activity Content',
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
        return array('activity_author' => array('activity_author', false), 'activity_type' => array('activity_type', false), 'post_title' => array('post_title', false), 'blog_title' => array('blog_title', false), 'comment_date' => array('comment_date', false));
    }

	private function table_data()
    {
	global $wpdb;
	$userId = getUserId();
		
	if(get_current_blog_id() == 1){
		$linkquery = "SELECT link_url FROM ". $wpdb->base_prefix ."links";
	}
	else{
		$linkquery = "SELECT link_url FROM ". $wpdb->base_prefix. get_current_blog_id() ."_links";

	}
	$domainPathQuery = "SELECT domain, path FROM " . $wpdb->base_prefix . "blogs";
	$domainPath = $wpdb->get_results($domainPathQuery);
	$link_list = $wpdb->get_results($linkquery);
	
	$first = 1;
	foreach($link_list as $link){
		foreach($domainPath as $paths) {
			$url = "http://".$paths->domain . $paths->path;
			if(substr($link->link_url, -1) != "/"){
				$compUrl = $link->link_url."/";
			}
			else{
				$compUrl = $link->link_url;
			}
			if ($url == $compUrl){
				if( $first == 1){
					$matchQuery = "SELECT blog_id FROM " . $wpdb->base_prefix . "blogs  WHERE domain = \"{$paths->domain}\" AND path = \"{$paths->path}\"";
					$uni = '';
					$first = 0;
				}
				else{
					$uni = ' union ';
					$matchQuery .= $uni . "SELECT blog_id FROM " . $wpdb->base_prefix . "blogs  WHERE domain = \"{$paths->domain}\" AND path = \"{$paths->path}\"";
				}
			}
		}
	}
	$match = $wpdb->get_results($matchQuery);
	$first = 1;
	// gets all comments
	foreach($match as $blogids){
		if($first == 1){
			$postsQuery = "SELECT ".$blogids->blog_id. " as blog_id, \"comment\" as type, comment_content as content, comment_date_gmt AS date, comment_author AS author FROM wp_".$blogids->blog_id."_comments";
			$uni = '';
			$first = 0;
		}
		else{
			$uni = ' UNION ';
			$postsQuery .= $uni . "SELECT ".$blogids->blog_id. " as blog_id, \"comment\" as type, comment_content as content, comment_date_gmt AS date, comment_author AS author from wp_".$blogids->blog_id."_comments";
		}
	}
	//gets all posts
	foreach($match as $blogids){
		if($blogids->blog_id == 1){
			$postsQuery .= " UNION SELECT " . $blogids->blog_id . " as blog_id, \"post\" as type, post_content as content, post_modified_gmt AS date, (SELECT user_login FROM wp_users where id = post_author) as author FROM wp_posts WHERE post_status = \"publish\"";
		}
		else{
			$postsQuery .= " UNION SELECT " . $blogids->blog_id . " as blog_id, \"post\" as type, post_content as content, post_modified_gmt AS date, (SELECT user_login FROM wp_users where id = post_author) as author FROM wp_".$blogids->blog_id."_posts WHERE post_status = \"publish\"";
		}
	}
	$limit = 50; //set your limit
	$limit = ' LIMIT 0, '. $limit;
	$postsQuery .= " ORDER BY date desc " . $limit; 
	$activities = $wpdb->get_results($postsQuery);
	
	$blogurlquery1 = "SELECT option_value FROM ". $wpdb->base_prefix . "options WHERE option_name = \"siteurl\"";
	$posturlquery1 = "SELECT guid FROM ". $wpdb->base_prefix . "posts WHERE ID = {$activity->comment_post_id}";
	
	$data = array();
	foreach($activities as $activity){
		if($activity->type == "post") {
			$title = $wpdb->get_var("SELECT post_title FROM ". $wpdb->base_prefix . $activity->blog_id . "_posts WHERE post_modified_gmt = \"{$activity->date}\"");
		} else {
			$id = $wpdb->get_var("SELECT comment_post_ID FROM ". $wpdb->base_prefix . $activity->blog_id . "_comments WHERE comment_content = \"{$activity->content}\"");
			$title = $wpdb->get_var("SELECT post_title FROM ". $wpdb->base_prefix . $activity->blog_id . "_posts WHERE ID = {$id}");
		}
		if($activity->blog_id !=1){
			$data[] = array(
					'recent_activity' => $activity->content,
					'activity_type' => strtoupper($activity->type),
					'comment_date' => $activity->date,
					'post_title' => $title,
					'blog_title' => $wpdb->get_var("SELECT option_value FROM ". $wpdb->base_prefix . $activity->blog_id . "_options WHERE option_name = \"blogname\""),
					'blog_url' => $wpdb->get_var("SELECT option_value FROM ". $wpdb->base_prefix . $activity->blog_id . "_options WHERE option_name = \"siteurl\""),
					'post_url' => $wpdb->get_var("SELECT guid FROM ". $wpdb->base_prefix . $activity->blog_id . "_posts WHERE post_title = \"{$title}\""),
					'activity_author' => $activity->author
					);
		}
	    else{

			$data[] = array(
					'recent_activity' => $activity->content,
					'activity_type' => strtoupper($activity->type),
					'comment_date' => $activity->date,				
					'blog_title' => $wpdb->get_var($blognamequery1),
					'post_title' => $title,
					'blog_url' => $wpdb->get_var($blogurlquery1),
					'post_url' => $wpdb->get_var($posturlquery1. $id)
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
			case 'activity_author':
				return $item[$column_name];
			case 'activity_type':
				return $item[$column_name];
			case 'blog_title':
				return "<a href =\"". $item["blog_url"]."\" target=\"_blank\">" . $item[$column_name] . "</a>";
			case 'post_title':
				return "<a href =\"".$item["post_url"]."\" target=\"_blank\">" . $item[$column_name] . "</a>";
            case 'recent_activity':
				if(strlen($item[$column_name]) > 50){
				return substr($item[$column_name],0,100)."...";
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