<?php
    include_once 'dbconfig.php';
    include_once 'process.php';
    if(!isset($_GET['_token']) )
    {
        echo json_encode('{message:"error", code:401}');
        exit;
    }

    if($_GET['_token'] !== $token)
    {
        echo json_encode('{message:"error", code:401}');
        exit;
    }

    $res = getData($connection, 'Select * From cron_jobs where download_flag = 0 limit 0,1;');
    if(sizeof($res) == 1)
    {
        try {
            $res = $res[0];
            $id = $res['id'];
            $hostUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 
                    "https" : "http") . "://" . $_SERVER['HTTP_HOST'];

            //Upload your server
            $detailZipUrl = $res['detail_zip_url'];
            $storeZipName = $res['store_zip_name'];
            @copy($detailZipUrl, "../Download/zip/$storeZipName");

            $detailPreviewUrl = $res['detail_preview_url']; 
            $storePreviewName = $res['store_preview_name'];
            @copy($detailPreviewUrl, "../Download/png/$storePreviewName");

            if(file_exists("../Download/zip/$storeZipName") && file_exists("../Download/png/$storePreviewName"))
            {
                insertData($connection, "Update cron_jobs set download_flag = 1 Where id='$id'");
                echo json_encode('{message:"success", code:200}');
            }
        } catch (Exception $e) {
                echo json_encode('{message:"error", code:402}'); 	
        }
    } else 
    {
        echo json_encode('{message:"error", code:400}'); 	
    }

?>