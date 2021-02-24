<?php
    include_once 'dbconfig.php';
    include_once 'process.php';
    include_once 'ucrm_api.php';
    session_start();
    $res = [];
    $_SESSION['success'] = false;
    
    if(isset($_POST['logout']) && $_POST['logout'] == 'logout')
    {
        session_destroy();        
    }
    //Ajax Request Processor
    if(isset($_POST['ajax_type']) && $_POST['ajax_type'] == 'payment_methods')
    {
        $url = "$ucrm_api_url/payment-methods";
        $paymentMethodInfo = fetchApiData($url, $ucrm_api_token) ?? [];
        echo json_encode($paymentMethodInfo);
        exit;
        
    } 

    if(isset($_POST['ajax_type']) && $_POST['ajax_type'] == 'invoices')
    {
        $url = "$ucrm_api_url/invoices?clientId={$_SESSION['user']['id']}&statuses%5B%5D=1&statuses%5B%5D=2";
        $invoicesList = fetchApiData($url, $ucrm_api_token) ?? [];
        echo json_encode($invoicesList);
        exit;
        
    } 
    //////////////////////////////////   Modal submit ///////////////////////////
    
    if(isset($_POST['add_payment']) && $_POST['add_payment'] == 'add_payment')
    {
        $invoiceIds = $_POST['pay_invoices'] ?? [];
        $amount = floatval($_POST['pay_amount']);
        $clientId = $_SESSION['user']['id'];
        $methodId = $_POST['pay_payment_method'];
        $createdDate = date( "Y-m-d\TH:i:s+0000", strtotime($_POST['pay_created_date']));
        $note = $_POST['pay_note'];
        $sendReceipt = $_POST['pay_send_receipt'] ?? 'off';
        if($amount == 0) die('Amount Error!');

        $sendJson = json_encode([
            "currencyCode" => "USD",
            "applyToInvoicesAutomatically" => true,
            "invoiceIds" => $invoiceIds,
            "clientId" => $clientId,
            "methodId" => $methodId,
            "createdDate" => $createdDate,
            "amount" => $amount,
            "note" => $note,
            "providerPaymentTime" => $createdDate
        ]);
        
        $postUrl = "$ucrm_api_url/payments";
        $res = postDataApi($postUrl, $ucrm_api_token, $sendJson);
        if(array_key_exists('id', $res)) $_SESSION['success'] = true;

        if($sendReceipt == "on" && array_key_exists('id', $res))
        {
            $paymentID = $res['id'];
            $url = "$ucrm_api_url/payments/$paymentID/send-receipt";
            $sendReceiptRes = patchApiData($url, $ucrm_api_token);
        }
    }
    ////////////////////////////////////

    //Access Login Process
    if(!isset($_SESSION['user']))
    {
        if(isset($_POST['email']) && $_POST['email'] != '') {
            $url = "$ucrm_api_url/clients?email={$_POST['email']}";
            $res_user = fetchApiData($url, $ucrm_api_token);
            if(sizeof($res_user) == 0)
            {
                $_SESSION['user_valid'] = 'error';
            } else 
            {
                $_SESSION['user'] = ['id'=>$res_user[0]['id'], 'name' =>$res_user[0]['firstName'].' '.$res_user[0]['lastName'], 'email'=>$res_user[0]['username']];
                $_SESSION['user_valid'] = 'success';
                $res = getData($connection, "SELECT CONCAT(T2.name,'(', T2.email ,')') AS client_info, T3.name AS payment_method, CONCAT(T1.amount,T1.currency) AS amount, T1.invoice_data, T1.created_date, T1.note FROM payment_history AS T1 LEFT JOIN clients AS T2 ON(T1.client_id = T2.id) LEFT JOIN payment_methods AS T3 ON(T1.payment_method_id=T3.id) WHERE email='{$_POST['email']}' ORDER BY T1.created_date DESC") ?? [];
            }
        }    
    } else 
    {
        $res = getData($connection, "SELECT CONCAT(T2.name,'(', T2.email ,')') AS client_info, T3.name AS payment_method, CONCAT(T1.amount,T1.currency) AS amount, T1.invoice_data, T1.created_date, T1.note FROM payment_history AS T1 LEFT JOIN clients AS T2 ON(T1.client_id = T2.id) LEFT JOIN payment_methods AS T3 ON(T1.payment_method_id=T3.id) WHERE email='{$_SESSION['user']['email']}' ORDER BY T1.created_date DESC") ?? [];
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Payment History from UCRM WebHook</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta content="Payment History" name="description" />
    <meta content="Coderthemes" name="author" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <!-- App favicon -->
    <link rel="shortcut icon" href="https://img.mydesignlist.com/pub/media/favicon/default/MOCKUP_02Sep17_1702_B35604_1.ico">

    <!-- third party css -->
    <link href="./assets/libs/datatables.net-bs4/css/dataTables.bootstrap4.min.css" rel="stylesheet" type="text/css" />
    <link href="./assets/libs/datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css" rel="stylesheet" type="text/css" />
    <!-- third party css end -->

    <!-- App css -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet" type="text/css" id="bs-default-stylesheet" />
    <link href="./assets/css/app.min.css" rel="stylesheet" type="text/css" id="app-default-stylesheet" />
    <!-- icons -->
    <link href="./assets/css/icons.min.css" rel="stylesheet" type="text/css" />
    <!-- Select2 -->
    <link href="./assets/libs/select2/select2.min.css" rel="stylesheet" type="text/css" />
    <!-- Swtichery -->
    <link href="./assets/libs/switchery/switchery.min.css" rel="stylesheet" type="text/css" />
    <!-- Toastr -->
    <link href="./assets/libs/toastr/toastr.min.css" rel="stylesheet" type="text/css" />
    <!-- Flatpicker -->
    <link href="./assets/libs/flatpickr/flatpickr.min.css" rel="stylesheet" type="text/css" />
    <!-- Block Ui -->
    <link href="./assets/libs/blockui/blockUI.css" rel="stylesheet" type="text/css" />
    <!-- Preloader Ui -->
    <link href="./assets/css/preloader.min.css" rel="stylesheet" type="text/css" />
    <style>
        .spinner{
            border-left: 5px solid #2B88C2 !important;
        }

        body, .form-group
        {
            background: white;
        }

        #datatable
        {
            display: none;
        }
        .blockUI
        {
            border: none !important;
        }
        .modal-dialog .row {
            background-color: white;
        }

        .item-hide
        {
            display: none;
        }

        .flatpickr-calendar
        {
            position: fixed !important;
        }
        
        p {
          margin-bottom: 0px;  
        }
        a:hover {
            color: red;
        }
        td {
            vertical-align: middle !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__choice
        {
            background-color: #3a3a3a;
            box-shadow: inset 1px 1px 4px rgba(0, 0, 0, 0.12);
        }
        .select2-container{
            background: #F7F7F7;
            box-shadow: inset 1px 1px 4px rgba(0, 0, 0, 0.12);
        }
        .select2-selection--single{
            height: 40px !important;
            border-color: #ced4da !important;
            box-shadow: inset 1px 1px 4px rgba(0, 0, 0, 0.12);
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow
        {
            top:6px !important;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered
        {
            height: 32px;
            padding: 3px 5px;
        }
        .select2-selection__rendered
        {
            margin-top: 7px;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered
        {
            height: auto !important;
        }
        .datatable_wrapper{
            background-color:#eee;
        }
        table,td,tr,th {
            background-color: white;
        }
        .card-box-shadow {
            box-shadow: 0 0px 1px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19) !important;
        }
        .page-item.active .page-link {
            background-color: #00bcd4 !important;
        }
    </style>
</head>

<body>
<!-- Pre-loader -->
<!-- <div id="preloader">
    <div id="status">
        <div class="spinner">Loading...</div>
    </div>
</div> -->

<!-- End Preloader-->
<div class="row mt-3">
    <div class="col-md-11 m-auto" style="background: white;">
        <?php if(isset($_SESSION['user']) && !isset($_POST['logout'])) { ?>
        <div class="card card-box-shadow mt-3">
            <div class="ml-3 mt-3">
                <button class="btn btn-outline-info waves-effect waves-light btn-rounded" id="btn_add"> <i class="fe-plus"></i>Agregar pago </button> <span class="ml-1 font-weight-bold font-16"><?=$_SESSION['user']['name']?>(<?=$_SESSION['user']['email']?>)</span>
                <form method="post" class="float-right">
                    <button name="logout" type="submit" class="btn btn-danger mr-3 btn-rounded waves-effect btn-sm waves-light float-right" id='btn-logout' value="logout"> <i class="fe-power"></i></button>
                </form>
            </div>
            
            <div class="card-body">
                <h3 class="mb-3 item-hide">Impresión de archivos: Mi lista de diseños</h3>
                <?php if($_SESSION['success']) { ?>
                <div class="alert alert-success alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                    El pago se registró correctamente.
                </div>
                <?php } ?>
                <table id="datatable" class="table dt-responsive nowrap w-100">
                    <thead>
                        <tr>
                            <th>No.</th>    
                            <th>Cliente</th>
                            <th>Método de pago</th>
                            <th>Cantidad</th>
                            <th>Datos de facturación</th>
                            <th>Fecha de realización</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                        $cnt = 0;
                        foreach($res as $row) {
                    ?>
                        <tr>
                            <td width="5%"><?=++$cnt?></td>
                            <td><?=$row['client_info']?></td>
                            <td>
                                <?php
                                    if(strpos(strtoupper($row['payment_method']), 'BANK TRANSFER') !== false || strpos($row['payment_method'], 'Transferencia bancaria') !== false)
                                        echo "Transferencia bancaria";
                                    if(strpos(strtoupper($row['payment_method']), 'CASH') !== false || strpos($row['payment_method'], 'Efectivo') !== false)
                                        echo "Efectivo";
                                    if(strpos(strtoupper($row['payment_method']), 'CHECK') !== false || strpos($row['payment_method'], 'Cheque') !== false)
                                        echo "Cheque";
                                ?>
                            </td>
                            <td><?=$row['amount']?></td>
                            <td><?=str_replace('Nubmer:', '', $row['invoice_data'])?></td>
                            <td><?=str_replace('T',' ', substr($row['created_date'], 0, 19))?></td>
                            <td><?=$row['note']?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div> <!-- end card body-->
        </div> <!-- end card -->
        <?php } else { ?>
        <div>
            <form method="post">
                <div style="margin-top:20vh;">
                    <div class="d-flex justify-content-center">
                            <img src="./assets/images/logo.png"  width="200"/>
                    </div>
                    <h4 class="text-center mt-4">Ingrese su usuario (mail)</h4>
                    <div class="row form-group justify-content-center">
                        <div class="col-md-3">
                            <input class="form-control" type="email" id="email" name="email" placeholder="Correo electrónico" autofocus required />
                            <?php 
                                if(isset( $_SESSION['user_valid']) && $_SESSION['user_valid'] == 'error') { ?>
                                    <p class="text-danger font-weight-bold">* Tu Correo Electrónico es Inválido.</p>
                            <?php } ?>
                            <div class="justify-content-center d-flex mt-1">
                                <button class="btn btn-outline-info waves-effect waves-light w-100" type="submit" id="btn_submit">
                                <i class=" dripicons-lock-open"></i><strong> Acceso</strong>
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </form>
        </div>
        <?php } ?>    
    </div>
</div>
<form method="post">
    <div id="payment_add_modal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="border-bottom: 1px solid #e5e8eb;">
                    <h4 class="modal-title">Informe de Pagos</h4>
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">×</button>
                </div>
                <div class="modal-body p-3">
                    <div class="row">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Monto <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-5">
                            <input type="number" class="form-control" min="0.001" max="10000000" step="0.001" name="pay_amount" id="pay_amount" placeholder="" required>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Facturas <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-8">
                            <select class="form-control" name="pay_invoices[]" id="pay_invoices" multiple required>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Método de pago <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-8">
                            <select class="form-control" name="pay_payment_method" id="pay_payment_method">
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Fecha de creación <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-md-8">
                            <div id="picker_container">
                                <input type="number" class="form-control" name="pay_created_date" id="pay_created_date" placeholder="" required>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Nota</label>
                        </div>
                        <div class="col-md-8">
                            <textarea class="form-control" rows="3" name="pay_note" id="pay_note" placeholder="Escribe algo sobre la nota"></textarea>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Enviar recibo</label>
                        </div>
                        <div class="col-md-8">
                            <input type="checkbox" class="form-control" name="pay_send_receipt" id="pay_send_receipt" placeholder="">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-blue waves-effect waves-light">Salvar</button>
                    <input type="hidden" name="add_payment" value="add_payment">
                </div>
            </div>
        </div>
    </div>
</form>

<script src="./assets/js/vendor.min.js"></script>


<!-- third party js -->
<script src="./assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
<script src="./assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="./assets/libs/datatables.net-responsive/js/dataTables.responsive.min.js"></script>
<script src="./assets/libs/datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js"></script>
<script src="./assets/libs/lazyload/lazyload.js"></script>
<script src="./assets/libs/select2/select2.min.js"></script>
<script src="./assets/libs/switchery/switchery.min.js"></script>
<script src="./assets/libs/toastr/toastr.min.js"></script>
<script src="./assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="./assets/libs/flatpickr/lang/es.min.js"></script>
<script src="./assets/libs/toastr/toastr.min.js"></script>
<script src="./assets/libs/blockui/blockUI.js"></script>


<!-- third party js ends -->

<!-- App js -->
<script src="./assets/js/app.min.js"></script>
<script>
    $(document).ready(function(){
        $(".alert").fadeTo(4000, 500).slideUp(500, function(){
            $(".alert").slideUp(500);
        });

        $("#datatable").DataTable({
            language:{
                paginate:{ 
                    previous:"<i class='mdi mdi-chevron-left'>",
                    next:"<i class='mdi mdi-chevron-right'>"
                },
                infoEmpty: "No hay registros disponibles",
                info: "Demostración _START_ a _END_ de _TOTAL_ entradas",
                search: "Buscar:",
                lengthMenu: "Show _MENU_ entradas",
                zeroRecords: "No hay datos disponibles en la tabla",
            },
            drawCallback:function(){
                $(".dataTables_paginate > .pagination").addClass("pagination-rounded");
            },
            initComplete: function(settings, json) {
                $('#datatable').show();
            }
        });

        
        new Switchery(document.getElementById('pay_send_receipt'),{ color: '#41b7f1' });
        
        $('#pay_created_date').flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            dateFormat: "Y-m-d H:i",
            defaultDate : "<?=date('Y-m-d H:i')?>",
            locale: 'es',
            appendTo: document.getElementById('picker_container')
        });

        $('#btn_add').click(() => {
            UIBlock('bars4');
            $('#pay_payment_method').empty();
            $('#pay_invoices').empty();
            $.post("/",{ajax_type: 'payment_methods'}, function () {
            }).done(function(res) {
                let pMethods = JSON.parse(res);
                pMethods.forEach(val => {
                    if(val.name.toUpperCase().indexOf('BANK TRANSFER') > -1 || val.name.indexOf('Transferencia Bancaria') > -1)
                        $('#pay_payment_method').append(`<option value='${val.id}' selected>Transferencia bancaria</option>`);
                    else if(val.name.toUpperCase().indexOf('CASH') > -1 || val.name.indexOf('Efectivo') > -1)
                        $('#pay_payment_method').append(`<option value='${val.id}'>Efectivo</option>`);
                    else if(val.name.toUpperCase().indexOf('CHECK') > -1 || val.name.indexOf('Cheque') > -1)
                        $('#pay_payment_method').append(`<option value='${val.id}'>Cheque</option>`);
                });
                $('#pay_payment_method').select2();
                $.post("/",{ajax_type: 'invoices'}, function () {
                }).done(function(res) {
                    let invoicesList = JSON.parse(res);
                    invoicesList.forEach(val => {
                        $('#pay_invoices').append(`<option value='${val.id}'>${val.number} (${val.amountToPay}${val.currencyCode})</option>`);
                    });
                    $('#pay_invoices').select2();
                    $('#payment_add_modal').modal({backdrop: 'static', keyboard: false});
                    UnBlockUi();
                }).fail(function(res) {
                    UnBlockUi();
                });
            }).fail(function(res) {
                UnBlockUi();
            });
            
        });
    });

    let UnBlockUi = () => {
        $.unblockUI();
    }

    let UIBlock = (type) => {
        $.blockUI({ message: `
        <div id="${type}">
            <span></span>
            <span></span>
            <span></span>
            <span></span>
            <span></span>
        </div>` });
    }
    
</script>
</body>
</html>