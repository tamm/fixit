<?php
/*
 *  fixit.php was created to move WordPress between domains or directories
 *  Author: Tamm Sjödin
 *  Created: February 2011
 *  Latest update: December 1st 2011
 *  Version: 1.0
*/


//Start timer to check script performance
$mtime = explode(' ', microtime());
$starttime = $mtime[1] + $mtime[0];

$status=array();

//Ignore errors due to funcionality loaded in the wp-config.php -> wp-settings.php related to magic quotes
error_reporting("E_ALL & ~E_DEPRECATED");
//define the current dir the same way wordpress does
define( 'ABSPATH', dirname(__FILE__) . '/' );
?>
<html>
<head>
  <meta http-equiv="Content-type" content="text/html; charset=utf-8">
  <title>fixit - fix those urls in wordpress database</title>
  <style type="text/css" media="screen">
    body {
      background: #eaeaea center 150px repeat-x;
      background-image: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAA0CAIAAAAxPk5wAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA2ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuMC1jMDYwIDYxLjEzNDc3NywgMjAxMC8wMi8xMi0xNzozMjowMCAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDpGQzdGMTE3NDA3MjA2ODExOTEwOUJDODY0NDYyOUE3QiIgeG1wTU06RG9jdW1lbnRJRD0ieG1wLmRpZDo2MjAyREQ4RUFCQTIxMUUwQjMyQjkxRjNFNjIyOTAwRSIgeG1wTU06SW5zdGFuY2VJRD0ieG1wLmlpZDo2MjAyREQ4REFCQTIxMUUwQjMyQjkxRjNFNjIyOTAwRSIgeG1wOkNyZWF0b3JUb29sPSJBZG9iZSBQaG90b3Nob3AgQ1M1IE1hY2ludG9zaCI+IDx4bXBNTTpEZXJpdmVkRnJvbSBzdFJlZjppbnN0YW5jZUlEPSJ4bXAuaWlkOkMzMEM0MzQzNzI2QjExRTA5Q0M2OUE1REQwMzQ2M0I0IiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOkMzMEM0MzQ0NzI2QjExRTA5Q0M2OUE1REQwMzQ2M0I0Ii8+IDwvcmRmOkRlc2NyaXB0aW9uPiA8L3JkZjpSREY+IDwveDp4bXBtZXRhPiA8P3hwYWNrZXQgZW5kPSJyIj8+qIOJpQAAACZJREFUeNpiZNnziYEQYGIgAowqGlU0qmhU0aiiUUWjikhRBBBgAEicAhpMT1fJAAAAAElFTkSuQmCC); /* this is the nindev stripe in base64*/
      font: 14px normal;
      font-family: sans-serif;
      margin: 0;
    }
    #wrapper {
      width: 500px;
      margin: 60px auto;
      background: #fff;
      padding: 20px;
      -webkit-border-radius: 10px;
      -moz-border-radius: 10px;
      border-radius: 10px;
    }
    #wrapper p {
      text-align: center;
    }
    #wrapper form table {
      margin: 0 auto;
    }
    #footer {
      font-size: 0.7em;
      color: #999;
      text-align: center;
    }
    p.error{
      color: #f00;
    }
    ul.status {
      text-align: center;
      padding: 0;
    }
    ul.status li{
      display: inline-block;
      position: relative;
      list-style: none;
      width: 20px;
      height: 20px;
      -moz-border-radius: 15px; /* FF1+ */
      -webkit-border-radius: 15px; /* Saf3+, Chrome */
      border-radius: 15px; /* Opera 10.5, IE 9 */
      text-align: center;
      vertical-align: middle;
      margin: 0 5px;
      -moz-box-shadow: 0px 2px 2px rgba(0,0,0,0.2); /* FF3.5+ */
      -webkit-box-shadow: 0px 2px 2px rgba(0,0,0,0.2); /* Saf3.0+, Chrome */
      box-shadow: 0px 2px 2px rgba(0,0,0,0.2); /* Opera 10.5, IE 9.0 */
    }
    ul.status li span{
      display: inline-block;
      padding: 0.25em 0;
      font-weight: bold;
      text-shadow: -0px -1px rgba(0,0,0,0.2);
    }
    li.green{
      background-color: #00ee00;
      background-image: -moz-linear-gradient(top, #00ee00, #00aa00); /* FF3.6 */
      background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0, #00ee00),color-stop(1, #00aa00)); /* Saf4+, Chrome */
      filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#00ee00', endColorstr='#00aa00'); /* IE6,IE7 */
      -ms-filter: "progid:DXImageTransform.Microsoft.gradient(startColorstr='#00ee00', endColorstr='#00aa00')"; /* IE8 */
      color: #fff;
    }
    li.red{
      background-color: #ff0000;
      background-image: -moz-linear-gradient(top, #ff6666, #bb0000); /* FF3.6 */
      background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0, #ff6666),color-stop(1, #bb0000)); /* Saf4+, Chrome */
      filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff6666', endColorstr='#bb0000'); /* IE6,IE7 */
      -ms-filter: "progid:DXImageTransform.Microsoft.gradient(startColorstr='#ff6666', endColorstr='#bb0000')"; /* IE8 */
      color: #fff;
    }
    a:link, a:visited {
      color: #04bbf2;
    }
    
    #submit{
      background-color: #04baf0;
      background-image: -moz-linear-gradient(top, #02aee7, #0088b6); /* FF3.6 */
      background-image: -webkit-gradient(linear,left top,left bottom,color-stop(0, #02aee7),color-stop(1, #0088b6)); /* Saf4+, Chrome */
      filter:  progid:DXImageTransform.Microsoft.gradient(startColorstr='#02aee7', endColorstr='#0088b6'); /* IE6,IE7 */
      -ms-filter: "progid:DXImageTransform.Microsoft.gradient(startColorstr='#02aee7', endColorstr='#0088b6')"; /* IE8 */
      color: #fff;
      border: 1px solid #06c7ee;
      -moz-box-shadow: 0px 0px 0px 1px #0088b0; /* FF3.5+ */
      -webkit-box-shadow: 0px 0px 0px 1px #0088b0; /* Saf3.0+, Chrome */
      box-shadow: 0px 0px 0px 1px #0088b0; /* Opera 10.5, IE 9.0 */
      -moz-border-radius: 4px; /* FF1+ */
      -webkit-border-radius: 4px; /* Saf3+, Chrome */
      border-radius: 4px; /* Opera 10.5, IE 9 */
      width: 70%;
      margin: 0 auto;
      display: block;
      height: 36px;
      font-weight: bold;
      font-size: 16px;
      font-family: 'Helvetica Neue',Helvetica,sans-serif;
    }
    input[type=text]{
      border: 1px solid #d9d9d9;
      background: #f6f6f6;
      -moz-border-radius: 3px; /* FF1+ */
      -webkit-border-radius: 3px; /* Saf3+, Chrome */
      border-radius: 3px; /* Opera 10.5, IE 9 */
      padding: 0.3em;
    }
    #results{
      margin: 0 auto;
    }
    .title{
      color: #777;
      text-align: right;
      font-size: 0.8em;
    }
    .number{
      width: 60px;
    }
  </style>
</head>
<body>
<div id="wrapper">
<?php
//load the wp-config.php, this was stolen from wp-load.php and modified for reporting success/error
if ( file_exists( ABSPATH . 'wp-config.php') ) {
	/** The config file resides in ABSPATH */
	require_once( ABSPATH . 'wp-config.php' );
  $status[1]='green';
} elseif ( file_exists( dirname(ABSPATH) . '/wp-config.php' ) && ! file_exists( dirname(ABSPATH) . '/wp-settings.php' ) ) {
	/** The config file resides one level above ABSPATH but is not part of another install*/
	require_once( dirname(ABSPATH) . '/wp-config.php' );
  $status[1]='green';
} else {
  echo "<p class='error'><strong>wp-config.php</strong> failed to load</p>";
  $status[1]='red';
}

//connect to database using the variables defined in wp-config.php
mysql_connect(DB_HOST,DB_USER,DB_PASSWORD);
@mysql_select_db(DB_NAME) or die( "Unable to select database");
$status[2]='green';

//get the variables from the form for search and replace
$searchanddestroy = array(mysql_real_escape_string($_POST['search']),mysql_real_escape_string($_POST['replace']));
$defaults=array('search'=>$searchanddestroy[0],'replace'=>$searchanddestroy[1]);

//if there is no search input we find the most likely strings
if($defaults['search'] == ""){//usually we're replacing the string currently stored in the db as the siteurl (standard for moving a WordPress)
  //query for tables
  $query = "SHOW TABLES LIKE '%options'";
  $result=mysql_query($query);
  $optionstables=array();
  while ($row = mysql_fetch_array($result)) {
    $optionstables[]=$row[0];
  }
  $search_table=$optionstables[0];
  if(count($optionstables)<1){
    $search_table="wp_options";
    echo "<p class='error'>There was more no options table for WordPress, trying: <strong>".$search_table."</strong></p>";
    $status[3]='red';
  }
  elseif(count($optionstables)>1){
    echo "<p class='error'>There was more than one prefix for WordPress, using: <strong>".$search_table."</strong></p>";
    $status[3]='red';
  }else{
    $status[3]='green';
  }
  
  $search_q = "SELECT * FROM ".$search_table." WHERE option_name = 'siteurl'";
  $search_result=mysql_query($search_q);
  while ($search_row = mysql_fetch_array($search_result)) {
    $defaults['search'] = $search_row['option_value'];
  }
}else{
  $status[3]='green';
}
if($defaults['replace'] == ""){//the value we want to replace with should be the current directory on the current domain without a trailing '/'
  $defaults['replace'] = 'http://' . $_SERVER['HTTP_HOST'] . substr($_SERVER['REQUEST_URI'], 0, strrpos($_SERVER['REQUEST_URI'],'/'));
}

//status lights so we know everything is okay
echo "<ul class='status'>";
foreach($status as $lamp=>$colour){
  echo "<li class='".$colour."'><span>" . $lamp . "</span></li>";
}
$status=array();
echo "</ul>";
?>

<form action="fixit.php" method="POST" accept-charset="utf-8">
  <table border="0" cellspacing="5" cellpadding="5">
    <tr>
      <td class="title"><label for="search">Search</label></td>
      <td><input type="text" name="search" value="<?php print $defaults['search']; ?>" id="search" size="50"></td>
    </tr>
    <tr>
      <td class="title"><label for="replace">Replace</label></td>
      <td><input type="text" name="replace" value="<?php print $defaults['replace']; ?>" id="replace" size="50"></td>
    </tr>
  </table>
  <input id="submit" type="submit" value="Replace &rarr;">
</form>


<?php


global$serialized_count_occurance;

function recursive_array_replace($find, $replace, &$data) {
global$serialized_count_occurance;
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                recursive_array_replace($find, $replace, $data[$key]);
            } else {
                // have to check if it's string to ensure no switching to string for booleans/numbers/nulls - don't need any nasty conversions
                if (is_string($value)) {
                  if (strpos($value,$find) !== false ){
                    // print '<pre>'.print_r($value,true).'</pre>';
                    $serialized_count_occurance++;
                  }
                  $data[$key] = str_replace($find, $replace, $value);
                }
                
            }
        }
    } else {
      if (is_string($value)) {
        if (strpos($value,$find)){
          print '<pre>'.print_r($value,true).'</pre>';
        }
        $data[$key] = str_replace($find, $replace, $value);
      }
    }

}
//only run replace if the form has been submitted
if (($_POST['search'] && !$_POST['replace']) || (!$_POST['search'] && $_POST['replace'])) $status[1]='red';
if ($_POST['search'] && $_POST['replace']) :
$status[1]='green';


//query for tables
$query = "SHOW TABLES";
$result=mysql_query($query);
//save the number of tables
$num=mysql_num_rows($result);
//make sure the numbers start at 0 instead of NULL
$i=0;
$u=0;
$serialized_count=0;
$serialized_count_occurance=0;


while ($row = mysql_fetch_array($result)) {
  //for each table, show the columns
  $cq = "show columns from ".$row[0]." where Type like '%char%' or Type like '%text'";
  $cresult=mysql_query($cq);
  while ($crow = mysql_fetch_array($cresult)) {
    //for each column, update the value
    $rq = "UPDATE ".$row[0]." SET ".$crow[0]." = replace(".$crow[0].", '".$searchanddestroy[0]."', '".$searchanddestroy[1]."') WHERE ".$crow[0]." NOT LIKE '_:%'";
    $i++;//count the columns
    $rresult=mysql_query($rq);
    
    $numr=mysql_affected_rows();
    $u+=$numr;//count affected rows
  }
  if($u>0){$status[2]='green';}else{$status[2]='red';}
  //for each table, show the columns which are serialized
  $cq = "SHOW columns FROM ".$row[0]." WHERE (Type LIKE '%char%' OR Type LIKE '%text')";
  $cresult=mysql_query($cq);
  while ($crow = mysql_fetch_array($cresult)) {
    //select the rows that seem to be serialized
    $serial_q = "SELECT ".$crow[0]." FROM ".$row[0]." WHERE ".$crow[0]." LIKE '_:%' AND ".$crow[0]." LIKE '%".$searchanddestroy[0]."%'";
    $serial_result=mysql_query($serial_q);
    while ($serial_row = mysql_fetch_array($serial_result)) {
      //check if really serialized
      if(is_serialized( $serial_row[0] )) {
        $serialized_count++;
        
        //unserialize -> replace in array -> serialize
        $new_serial_row = maybe_unserialize($serial_row[0]); 
        recursive_array_replace($searchanddestroy[0], $searchanddestroy[1], $new_serial_row);
        $new_serial_row = maybe_serialize($new_serial_row); 
        
        //lets update
        $rq = "UPDATE ".$row[0]." SET ".$crow[0]." = '".$new_serial_row."' WHERE ".$crow[0]." = '".$serial_row[0]."'";
        $rresult=mysql_query($rq);

        //and count those rows
        $numr=mysql_affected_rows();
        $u+=$numr;//count affected rows
      } 
    }
    if($serialized_count_occurance>0){$status[3]='green';}else{$status[3]='red';}
    $columns++;//count the columns
  }
}

if($u<1){$status[4]='red';
}else{
  $status[4]='green';
}
//print info about the replace
?>
<table border='0' cellspacing='5' cellpadding='5' id='results'>
  <tr>
    <td class='title'>Tables found:</td>
    <td class='number'><?php print $num; ?></td>
  </tr>
  <tr>
    <td class='title'>Columns found:</td>
    <td class='number'><?php print $i; ?></td>
  </tr>
  <tr>
    <td class='title'>Serialized rows fixed:</td>
    <td class='number'><?php print $serialized_count; ?></td>
  </tr>
  <tr>
    <td class='title'>Serialized occurances:</td>
    <td class='number'><?php print $serialized_count_occurance; ?></td>
  </tr>
  <tr>
    <td class='title'>Updated rows:</td>
    <td class='number'><?php print $u; ?></td>
  </tr>
</table>
<?php

mysql_close();


endif;

if($status[1]){
  echo "<ul class='status'>";
  foreach($status as $lamp=>$colour){
    echo "<li class='".$colour."'><span>" . $lamp . "</span></li>";
  }
  echo "</ul>";
}
?>

  <div id="footer">
    &copy; 2011 Tamm Sjödin, <a href="http://nindev.se">Nindev AB</a> | 
  <?php 
  $mtime = explode(" ", microtime()); $endtime = $mtime[1] + $mtime[0]; $totaltime = ($endtime - $starttime);
  //print the time taken to execute the script
  echo 'Executed in ' .round($totaltime, 2). ' seconds.';
  ?>
  </div>
</div>
</body>
</html>