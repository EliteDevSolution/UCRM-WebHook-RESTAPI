<?php
    function getData($conn, $sql)
    {
        $result = mysqli_query($conn, $sql);
        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    function insertData($conn, $sql)
    {
        mysqli_query($conn, $sql);
    }

    function get_file_data($conn, $sql)
    {
        $file_data = array();
        $init_data = getData($conn, $sql);

        foreach($init_data as $row)
        {
            if(!isset($file_data[$row["trans_id"]]))
            {
                $file_data[$row["trans_id"]] = array();
            }
            array_push($file_data[$row["trans_id"]], $row);
        }

        return $file_data;
    }

    function upload_file_delete($conn, $sql, $limit_date)
    {
        $file_delete_data = getData($conn, $sql);
        
        foreach($file_delete_data as $row)
        {
            @unlink("./upload/".$row['file_name']);
        }
            
        $delete_query = "DELETE FROM file_data WHERE file_create_date < '".$limit_date."'";

        return mysqli_query($conn, $delete_query);
    }
    
?>