<?php

include_once 'dbconfig.php';
include_once 'process.php';
include_once 'ucrm_api.php';

$mode = 'Live';

$hookData = [];
if($mode == 'TEST')
{
    $myfile = fopen("test.txt", "r") or die("Unable to open file!");
    $decodedText = html_entity_decode(fread($myfile, filesize("test.txt")));
    $testCase = json_decode($decodedText, true);
}

if($json = json_decode(file_get_contents("php://input"), true)) {
    $hookData = $json;
    if($mode == 'TEST') $hookData = $testCase;
    
    ////HookFile Write
    //$myfile = fopen("hookdata.txt", "w") or die("Unable to open file!");
    //fwrite($myfile, json_encode($hookData));
    //fclose($myfile);
    ////--End File Write---////

    //echo (json_encode($hookData['data']));exit;
    if(array_key_exists('eventName', $hookData) && array_key_exists('extraData', $hookData))
    {
        if($hookData['eventName'] == 'payment.add')// || $hookData['eventName'] == 'payment.edit')
        {
            $hook_uuid = $hookData['uuid'];
            $transId = $hookData['extraData']['entity']['id'];
            $clientId = $hookData['extraData']['entity']['clientId'];
            $paymentMethodId = $hookData['extraData']['entity']['methodId'];
            $amount = $hookData['extraData']['entity']['amount'];
            $currencyCode = $hookData['extraData']['entity']['currencyCode'];
            $note = $hookData['extraData']['entity']['note'];
            $receiptSentDate = $hookData['extraData']['entity']['receiptSentDate'] ?? '';
            $createdDate = $hookData['extraData']['entity']['createdDate'];
            //Insert Invoice Data
            $invocieStrLst = [];
            if(array_key_exists('paymentCovers', $hookData['extraData']['entity']))
            {
                foreach($hookData['extraData']['entity']['paymentCovers'] as $val)
                {
                    if(array_key_exists('invoiceId', $val))
                    {
                        $invoiceId = $val['invoiceId'];
                        //fetch invoice info
                        $invoiceQuery = "Select * From invoices where id='$invoiceId'";
                        $invoices = getData($connection, $invoiceQuery) ?? [];
                        if(sizeof($invoices) == 0) 
                        {
                            $url = "$ucrm_api_url/invoices/$invoiceId";
                            $invoiceInfo = fetchApiData($url, $ucrm_api_token) ?? [];
                            $clientId = $invoiceInfo['clientId'];
                            $number = $invoiceInfo['number'];
                            $subtotal = $invoiceInfo['subtotal'];
                            $create_date = $invoiceInfo['createdDate'];
                            $due_date = $invoiceInfo['dueDate'];
                            $currency = $invoiceInfo['currencyCode'];
                            $status = $invoiceInfo['status'];
                            $invoice_templete = $invoiceInfo['invoiceTemplateId'];
                            $payment_covers = serialize($invoiceInfo['paymentCovers'] ?? []);
                            $invocieStrLst[] = $number. '('. $val['amount'].$currency.')';
                            $insertQuery = "Insert into invoices values('$invoiceId', '$clientId', '$number','$subtotal','$currency','$status','$invoice_templete','$payment_covers','$due_date','$create_date')";
                            insertData($connection, $insertQuery);
                        } else if(sizeof($invoices) == 1)
                        {
                            $invocieStrLst[] = $invoices[0]['number']. '('. $val['amount'].$invoices[0]['currency'].')';
                        } else {
                            continue;
                        }
                    }
                }
            }

            //Get Client Name
            $clientQuery = "Select * From clients where id='$clientId'";
            $clients = getData($connection, $clientQuery) ?? [];
            if(sizeof($clients) == 0) //Get Client Info & Insert Db from fetch Api....
            {
                $url = "$ucrm_api_url/clients/$clientId";
                $clientInfo = fetchApiData($url, $ucrm_api_token) ?? [];
                if(array_key_exists('contacts', $clientInfo))
                {
                    $clientName = $clientInfo['contacts'][0]['name'];
                    if($clientName == null || $clientName == '') $clientName = $clientInfo['firstName'].' '.$clientInfo['lastName'];
                    $email = $clientInfo['contacts'][0]['email'];
                    $phone = $clientInfo['contacts'][0]['phone'];
                    $is_billing = $clientInfo['contacts'][0]['isBilling'] ? 1 : 0;
                    $balance = $clientInfo['accountBalance'];
                    $credit = $clientInfo['accountCredit'];
                    $out_standing = $clientInfo['accountOutstanding'];
                    $currency = $clientInfo['currencyCode'];
                    $organization = $clientInfo['organizationName'];
                    $created_at = $clientInfo['registrationDate'];
                    $insertQuery = "Insert into clients values('$clientId', '$clientName', '$email', '$phone', '$is_billing', '$balance', '$credit', '$out_standing', '$currency', '$organization', '$created_at');";
                    insertData($connection, $insertQuery);
                }
            }
            //Get Payment Method Name
            $methodQuery = "Select * From payment_methods where id='$paymentMethodId'";
            $methods = getData($connection, $methodQuery) ?? [];
            if(sizeof($methods) == 0) //Get payment method Info & Insert Db from fetch Api....
            {
                $url = "$ucrm_api_url/payment-methods/$paymentMethodId";
                $paymentMethodInfo = fetchApiData($url, $ucrm_api_token) ?? [];
                if(array_key_exists('name', $paymentMethodInfo))
                {
                    $paymentName = $paymentMethodInfo['name'];
                    $isSystem = $paymentMethodInfo['isSystem'] ? 1 : 0;
                    $insertQuery = "Insert into payment_methods values('$paymentMethodId', '$paymentName', '$isSystem');";
                    insertData($connection, $insertQuery);
                }
            }

            
            $invocieStr = implode(",", $invocieStrLst);
            $insertQuery = "Insert into payment_history values('$hook_uuid', '$transId', '$clientId', '$paymentMethodId', '$amount', '$invocieStr','$currencyCode','$note', '$receiptSentDate', '$createdDate')";
            insertData($connection, $insertQuery);
            echo json_encode('{message:"success", code:200}'); 
        } else 
        {
            echo json_encode('{message:"error", code:202}'); 
        }
    }
} else {
    echo json_encode('{message:"error", code:400}'); 
}

?>