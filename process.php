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
    
?>