<?php
function getUserId(){
	if(!function_exists('wp_get_current_user'))
	    require_once(ABSPATH . "wp-includes/pluggable.php"); 
	wp_cookie_constants();
	$current_user = wp_get_current_user();
	return $current_user->user_login;
}
?>