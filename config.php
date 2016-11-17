<?php
/*
 * This document includes global system-specific settings
 *
 */
 
 
/*
 *---------------------------------------------------------------
 * GLOBAL SETTINGS
 *---------------------------------------------------------------
 */

	define('HOME_URL', getcwd()."/");
	
	define('SYS_TIMEZONE', "America/Los_Angeles");
	date_default_timezone_set(SYS_TIMEZONE);

/*
 *---------------------------------------------------------------
 * QUERY CACHE SETTINGS
 *---------------------------------------------------------------
 */

 	define('QUERY_FILE', HOME_URL.'/queries.php'); 
	
?>