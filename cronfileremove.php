<?php
    include_once 'dbconfig.php';
    include_once 'process.php';
    
    $d=strtotime("-3 Months");
    $limit_date = date("Y-m-d", $d);
    $file_delete_result = upload_file_delete($connection, "SELECT * FROM file_data where file_create_date < '".$limit_date."'", $limit_date);

?>