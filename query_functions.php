<?php
	# the database connection object
	global $db;
	# the memcached server connection
	global $memcached;

	# populate the global connection values
	$db = new mysqli(HOSTNAME, USERNAME, PASSWORD, DATABASE, DBPORT);
	# check if memcached is enabled before connecting
	if(extension_loaded('memcached')){
		$memcached = new Memcached();
		# assuming that that memcached server is the same as the mysql database server for manageability
		$memcached->addServer(HOSTNAME, 11211);
	}




	
	# Returns all fields in the format array('_FIELDNAME_', 'fieldvalue') which is expected by the database 
	# query processing function
	function format_field_for_query($queryData)
	{	
		log_message('debug', 'query_functions/format_field_for_query');
		log_message('debug', 'query_functions/format_field_for_query:: [1] queryData='.json_encode($queryData));
		
		$dataForQuery = array();
	
		foreach($queryData AS $key => $value)
		{
			#e.g., $queryData['_LIMIT_'] = "10";
			$dataForQuery['_'.strtoupper($key).'_'] = $value;
		}
		log_message('debug', 'query_functions/format_field_for_query:: [2] dataForQuery='.json_encode($dataForQuery));
		
		return $dataForQuery;
	}
	
	
	# Populate the query template with the provided values
	function populate_template($template, $queryData = array())
	{
		log_message('debug', 'query_functions/populate_template');
		log_message('debug', 'query_functions/populate_template:: [1] template='.$template.' queryData='.json_encode($queryData));
		log_message('debug', 'query_functions/populate_template:: [1] ENABLE_QUERY_CACHE='.ENABLE_QUERY_CACHE);
		
		if(ENABLE_QUERY_CACHE) $query = get_sys_query($template);
		if(empty($query)) {
			global $db;
			$qData = $db->query("SELECT * FROM queries WHERE code='".$template."'")->fetch_array(MYSQLI_ASSOC); 
			$query = !empty($qData['details'])? $qData['details']: '';
		}
		
		# Process the query data to fit the field format expected by the query
		$queryData = format_field_for_query($queryData);
		
		#replace place holders with actual data required in the string
		foreach($queryData AS $key => $value)
		{
			$query = str_replace("'".$key."'", "'".$value."'", $query);
		}
			
		#Then replace any other keys without quotes
		foreach($queryData AS $key => $value)
		{
			$query = str_replace($key, ''.$value, $query);
		}
		log_message('debug', 'query_functions/populate_template:: [1] query='.$query);
		
		return $query;
	}

	
	
	
	
	
	# Get the result after running on DB
	function run($queryCode, $queryData = array(), $updateStrict = FALSE)
	{
		log_message('debug', 'query_functions/run');
		log_message('debug', 'query_functions/run:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData).' updateStrict='.$updateStrict);
		
		global $db;
		if($result = $db->query(populate_template($queryCode, $queryData))){
			log_message('debug', 'query_functions/run:: [2] result='.json_encode($result));
			if($updateStrict) return !empty($db->affected_rows);
			else return TRUE;
		}
		return FALSE;
	}




	
	# read data from the database. using this function instead of the direct $db
	# takes queries through memcached for faster retrieval of cached results
	function read($query, $queryType)
	{
		log_message('debug', 'query_functions/read');
		log_message('debug', 'query_functions/read:: [1] query='.$query.' queryType='.$queryType);
		
		if(extension_loaded('memcached')){ 
			global $memcached;
			
			$queryKey = "KEY".md5($query); 
			$data = $memcached->get($queryKey);
			
			# query is not yet cached or expired, cache it again for MEMCACHED_PERIOD seconds
			if(!$data){
				$data = read_from_db($query, $queryType);
				$memcached->set($queryKey, $data, MEMCACHED_PERIOD);
			}
			
			return $data;
		}
		
		# memcached is not enabled
		else return read_from_db($query, $queryType);
	}
	
	
	
	# function to force the user to read directly from the database
	function read_from_db($query, $queryType)
	{
		log_message('debug', 'query_functions/read_from_db');
		log_message('debug', 'query_functions/read_from_db:: [1] query='.$query.' queryType='.$queryType);
		
		global $db;
		
		if($queryType == 'get_count') $data = $db->query($query)->num_rows;
		
		else if($queryType == 'get_row_as_array') $data = $db->query($query)->fetch_array(MYSQLI_ASSOC);
		
		else if($queryType == 'get_list') {
			$data = array();
			$result = $db->query($query);
			if($result) while ($row = $result->fetch_assoc()) array_push($data, $row);
  			# Free result set
  			$result->free();
		}
		else $data = array();
		
		return $data;
	}



	
	
	# get the count of results from a query 
	function get_count($queryCode, $queryData = array())
	{
		log_message('debug', 'query_functions/get_count');
		log_message('debug', 'query_functions/get_count:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		return read(populate_template($queryCode, $queryData), 'get_count');
	}


		
	
	# Given the query details, return the result as a single associated array
	function get_row_as_array($queryCode, $queryData = array())
	{
		log_message('debug', 'query_functions/get_row_as_array');
		log_message('debug', 'query_functions/get_row_as_array:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		return read(populate_template($queryCode, $queryData), 'get_row_as_array');
	}

			
	
	# Given the query details, return the result as an array of associated arrays
	function get_list($queryCode, $queryData = array())
	{
		log_message('debug', 'query_functions/get_list');
		log_message('debug', 'query_functions/get_list:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		return read(populate_template($queryCode, $queryData), 'get_list');
	}

			
	
	
	# Given the query details that return a single column, return the result as an array
	function get_single_column_as_array($queryCode, $columnName, $queryData = array())
	{
		log_message('debug', 'query_functions/get_single_column_as_array');
		log_message('debug', 'query_functions/get_single_column_as_array:: [1] queryCode='.$queryCode.' columnName='.$columnName.' queryData='.json_encode($queryData));
		
		$list = array();
		
		$results = get_list($queryCode, $queryData);

		# check if the column exists in the returned data
		if(!empty($results) && !empty($results[0][$columnName]))
		{
			foreach($results AS $row)
			{
				array_push($list, $row[$columnName]);
			}
		}
		log_message('debug', 'query_functions/get_single_column_as_array:: [1] list='.json_encode($list));
		
		return $list;
	}
	
	
	
	
			
	
	# Run an insert query and return the id of the record
	function add_data($queryCode, $queryData = array())
	{
		log_message('debug', 'query_functions/add_data');
		log_message('debug', 'query_functions/add_data:: [1] queryCode='.$queryCode.' queryData='.json_encode($queryData));
		
		global $db;
		$result = $db->query(populate_template($queryCode, $queryData));
		return $db->insert_id;
	}
	
	
	
	
	
	
	#Load queries into the cache file
	function load_queries_into_cache()
	{
		log_message('debug', 'query_functions/load_queries_into_cache');
		
		global $db;
		
		$result = $db->query("SELECT * FROM queries"); # read queries
		if($result){
			#Now load the queries into the file
			file_put_contents(QUERY_FILE, "<?php ".PHP_EOL."global \$sysQuery;".PHP_EOL); 
			# Fetch one row at a time
  			while($query = $result->fetch_assoc())
    		{ 
    			$queryString = "\$sysQuery['".$query['code']."'] = \"".str_replace('"', '\"', $query['details'])."\";".PHP_EOL;  
				file_put_contents(QUERY_FILE, $queryString, FILE_APPEND);
    		}
		
			file_put_contents(QUERY_FILE, PHP_EOL.PHP_EOL." function get_sys_query(\$code) { ".PHP_EOL."global \$sysQuery; ".PHP_EOL."return !empty(\$sysQuery[\$code])? \$sysQuery[\$code]: '';".PHP_EOL." }".PHP_EOL, FILE_APPEND); 
		
			echo "QUERY CACHE FILE HAS BEEN UPDATED [".date('F d, Y H:i:sA T')."]";
		}
  		# Free result set
  		$result->free();
	}

?>