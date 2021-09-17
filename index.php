<?php
    include_once 'dbconfig.php';
    include_once 'process.php';
    include_once 'ucrm_api.php';
    session_start();
    $res = [];
    $_SESSION['success'] = false;
    $_SESSION['show_modal'] = false;
    setlocale(LC_MONETARY, 'es_AR');
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

        $uploadOk = 1;

        $file_new_name = "";
        $file_create_date = "";
        
        $invoiceIds = $_POST['pay_invoices'] ?? [];
        $amount = floatval($_POST['pay_amount']);
        $clientId = $_SESSION['user']['id'];
        $methodId = $_POST['pay_payment_method'];
        $createdDate = date( "Y-m-d\TH:i:s+0000", strtotime($_POST['pay_created_date']));
        $note = "";
        $sendReceipt = $_POST['pay_send_receipt'] ?? 'off';
        if($amount == 0) die('Amount Error!');
        

        $sendJson = json_encode([
            "currencyCode" => "ARS",
            "applyToInvoicesAutomatically" => true,
            "invoiceIds" => $invoiceIds,
            "clientId" => $clientId,
            "methodId" => $methodId,
            "createdDate" => $createdDate,
            "amount" => $amount,
            "note" => $note,
            "providerPaymentTime" => $createdDate,
        ]);
        
        $postUrl = "$ucrm_api_url/payments";
        $res = postDataApi($postUrl, $ucrm_api_token, $sendJson);
        
        if(array_key_exists('id', $res)) {
            $_SESSION['success'] = true;
            if($_FILES["photo"]["size"]>0){          

                $target_dir = "upload/";
                $resId = $res['id'];
                $file_new_name = date("YmdHis") . basename($_FILES["photo"]["name"]);
                $target_file = $target_dir . $file_new_name;
                $fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
                $fileCreateDate = date('Y-m-d');
    
                if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
                    $insertQuery = "Insert into file_data values('$resId', '$file_new_name', '$fileCreateDate')";
                    insertData($connection, $insertQuery);
                } 
            }
        }

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
            $password = $_POST['password'];
            $_SESSION['email'] = $_POST['email'];
            if(sizeof($res_user) == 0)
            {
                $_SESSION['user_valid'] = 'error';
            } else 
            {
                
                if(array_key_exists('attributes', $res_user[0]) && sizeof($res_user[0]['attributes']) > 0)
                {
                    foreach ($res_user[0]['attributes'] as $val)
                    {   
                        $authOk = 0;
                        if($val['key'] == 'cDigoDeTransferencia' && $val['value'] == $password)
                        {
                            $_SESSION['user'] = ['id'=>$res_user[0]['id'], 'name' =>$res_user[0]['firstName'].' '.$res_user[0]['lastName'], 'email'=>$res_user[0]['username']];
                            $_SESSION['user_valid'] = 'success';
                            $res = getData($connection, "SELECT T2.name AS client_info, T3.name AS payment_method, T1.amount AS amount, T1.invoice_data, T1.created_date, T1.trans_id, T1.note FROM payment_history AS T1 LEFT JOIN clients AS T2 ON(T1.client_id = T2.id) LEFT JOIN payment_methods AS T3 ON(T1.payment_method_id=T3.id) WHERE email='{$_POST['email']}' ORDER BY T1.created_date DESC") ?? [];            
                            $authOk = 1;
                            break;
                        }
                    }
                    $_SESSION['show_modal'] = true;

                    if($authOk == 0) $_SESSION['user_valid'] = 'error';
                } else {
                    $_SESSION['user_valid'] = 'error';
                }
            }
        }    
    } else 
    {
        $res = getData($connection, "SELECT T2.name AS client_info, T3.name AS payment_method, T1.amount AS amount, T1.invoice_data, T1.created_date, T1.trans_id, T1.note FROM payment_history AS T1 LEFT JOIN clients AS T2 ON(T1.client_id = T2.id) LEFT JOIN payment_methods AS T3 ON(T1.payment_method_id=T3.id) WHERE email='{$_SESSION['user']['email']}' ORDER BY T1.created_date DESC") ?? [];
    }
    $file_data = get_file_data($connection, 'SELECT * FROM file_data');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Oops SRL</title>
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
    <!-- Dropify Ui -->
    <link href="./assets/libs/dropify/dropify.min.css" rel="stylesheet" type="text/css" />
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

        .dropzone {
            border: 2px dashed rgb(65, 65, 149);
            max-height: 400px;	
            padding: 0px 20px !important;    
        }
    </style>
</head>

<body>
    
<!-- End Preloader-->
<?php if($_SESSION['show_modal']==true){ ?>
    <input type="text" id="show-modal" value="show modal" style="display: none;">
<?php $_SESSION['show_modal'] = false; } else{ ?>
    <input type="text" id="show-modal" value="hide modal" style="display: none;">
<?php } ?>

<div class="row mt-3">
    <div class="col-md-11 m-auto" style="background: white;">
        <?php if(isset($_SESSION['user']) && !isset($_POST['logout'])) { ?>
        <div class="card card-box-shadow mt-3">
            <div class="ml-3 mt-3">
                <button class="btn btn-outline-info waves-effect waves-light btn-rounded" id="btn_add"> <i class="fe-plus"></i>Agregar pago </button> <span class="ml-1 font-weight-bold font-16"><?=$_SESSION['user']['name']?></span>
                <form method="post" class="float-right" id="main-body">
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
                            <th>Fecha de realización</th>
                            <!-- <th>Nota</th> -->
                            <th>Arquivo</th>
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
                                    if(strpos(strtoupper($row['payment_method']), 'BANK TRANSFER') !== false || strpos($row['payment_method'], 'Transferencia Bancaria') !== false)
                                        echo "Transferencia bancaria";
                                    if(strpos(strtoupper($row['payment_method']), 'CASH') !== false || strpos($row['payment_method'], 'Efectivo') !== false)
                                        echo "Efectivo";
                                    if(strpos(strtoupper($row['payment_method']), 'CHECK') !== false || strpos($row['payment_method'], 'Cheque') !== false)
                                        echo "Cheque";
                                ?>
                            </td>
                            <td>
                                <?php
                                    $fmt = numfmt_create( 'es_AR', NumberFormatter::CURRENCY );
                                    echo numfmt_format_currency($fmt, floatval($row["amount"]), "ARS");
                                ?>
                            </td>
                            <td><?=str_replace('T',' ', substr($row['created_date'], 0, 19))?></td>
                            <!-- <td><?=$row['note']?></td> -->
                            <td style="text-align:center;">
                                <?php
                                    if(isset($file_data[$row["trans_id"]]))
                                    {
                                        foreach($file_data[$row['trans_id']] as $row_file)
                                        {
                                            $file_name_array = explode(".", $row_file['file_name']);
                                            $file_type = $file_name_array[count($file_name_array)-1];
                                            if($file_type =="pdf")
                                            { ?>
                                                <a href="./upload/<?= $row_file['file_name'] ?>" target="_blank">
                                                    <img src="./assets/images/pdf_1.png"  width="26" height="30" title="<?= $row_file['file_name'] ?>"/>
                                                </a>
                                            <?php }else
                                            { ?>
                                                <a href="./upload/<?= $row_file['file_name'] ?>" target="_blank">
                                                    <img src="./assets/images/img_1.png"  width="24" height="30" title="<?= $row_file['file_name'] ?>"/>
                                            </a>
                                            <?php }
                                        }
                                    }
                                    else
                                    {
                                        echo "Nenhum arquivo";
                                    }
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div> <!-- end card body-->
        </div> <!-- end card -->
        <?php } else { ?>
        <div>
            <form method="post" id="login-body">
                <div style="margin-top:20vh;">
                    <div class="d-flex justify-content-center">
                            <img src="./assets/images/logo.png"  width="200"/>
                    </div>
                    <h4 class="text-center mt-4">Informe su transferencia bancaria</h4>
                    <div class="row form-group justify-content-center">
                        <div class="col-md-3">
                            <input class="form-control" type="email" id="email" name="email" placeholder="Correo electrónico" autofocus required value="<?=$_SESSION['email'] ?? ''?>"/>
                            <input class="form-control mt-1" type="password" id="password" name="password" placeholder="Contraseña WIFI" required />
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

<form method="post" enctype="multipart/form-data" id="modal_form">
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
                        <div class="col-md-8">
                            <input type="text" name="pay_amount_edit" id="pay_amount_edit" class="form-control" oninput="converter(this.value)" required>

                            <input type="number" class="form-control" min="100" max="10000000" step="0.01" name="pay_amount" id="pay_amount" placeholder="" style="display:none;" required>
                            <!-- <input type="number" class="form-control" min="100" max="10000000" step="0.01" name="pay_amount" id="pay_amount" placeholder="" required> -->
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
                    <!-- <div class="row mt-2" >
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Adjuntar comprobante</label>
                        </div>
                        <div class="col-md-8">
                            <textarea class="form-control" rows="3" name="pay_note" id="pay_note" placeholder="Escribe algo sobre la nota"></textarea>
                        </div>
                    </div> -->
                    <div class="row mt-2">
                        <div class="col-md-4">
                            <label for="field-1" class="control-label">Enviar recibo</label>
                        </div>
                        <div class="col-md-8">
                            <input type="checkbox" class="form-control" name="pay_send_receipt" id="pay_send_receipt" placeholder="">
                        </div>
                    </div>
                </div>
                <div class="form-group" style="padding:10px;">
                    <div class="col-md-12">
                        <label>Comprobante de Transferencia</label>
                        <input type="file" name="photo" class="dropify" id="file_data"
                        data-allowed-file-extensions="jpg png gif tif jpeg pdf" data-max-file-size="50M" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary waves-effect" data-dismiss="modal">Cancelar</button>
                    <button type="button" id="payment_btn" class="btn btn-blue waves-effect waves-light">Guardar</button>
                    <button type="submit" id="submit_btn" class="btn btn-blue waves-effect waves-light" style="display:none;"></button>
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
<script src="./assets/libs/dropify/dropify.min.js"></script>
<!-- third party js ends -->

<!-- App js -->
<script src="./assets/js/app.min.js"></script>
<script>
    function setInputFilter(textbox, inputFilter) {
    ["input", "keydown", "keyup", "mousedown", "mouseup", "select", "contextmenu", "drop"].forEach(function(event) {
        textbox.addEventListener(event, function() {
        if (inputFilter(this.value)) {
            this.oldValue = this.value;
            this.oldSelectionStart = this.selectionStart;
            this.oldSelectionEnd = this.selectionEnd;
        } else if (this.hasOwnProperty("oldValue")) {
            this.value = this.oldValue;
            this.setSelectionRange(this.oldSelectionStart, this.oldSelectionEnd);
        } else {
            this.value = "";
        }
        });
    });
    }

    function converter(value){

        if(value == ",") $("#pay_amount_edit").val("");

        if(value == ""){
            $("#pay_amount").val(0);
            return 0;
        }

        if(value.substr(0, 1)==",")
            value = value.substr(1);
        
        value = value.replace(",", ".");
        $("#pay_amount").val(Number(value));
    }

    $(document).ready(function(){
        
        var pay_invoices_array = [];

        setInputFilter(document.getElementById("pay_amount_edit"), function(value) {
            return /^\d*[,]?\d*$/.test(value); 
        });

        $("#show-modal").hide();      

        if($("#show-modal").val() == "show modal"){
            modal_data_load();
        }
        
            
        $(".alert").fadeTo(4000, 500).slideUp(500, function(){
            $(".alert").slideUp(500);
        });

        $("#payment_btn").click(function(){
            var i = 0;
            var pay_invoice = $("#pay_invoices").val();
            var total_amount = 0;

            for(i = 0 ; i<pay_invoice.length ; i++){
                total_amount += Number(pay_invoices_array[pay_invoice[i]]);
            }

            toastr.options = {
                "closeButton": true,
                "newestOnTop": false,
                "progressBar": true,
                // "positionClass": "toast-bottom-right",
                "preventDuplicates": false,
                "onclick": null,
                "showDuration": "300",
                "hideDuration": "1000",
                "timeOut": "5000",
                "extendedTimeOut": "1000",
                "showEasing": "swing",
                "hideEasing": "linear",
                "showMethod": "fadeIn",
                "hideMethod": "fadeOut"
            }

            if($("#pay_amount").val()<total_amount){

                $('#pay_amount_edit').focus();
                toastr.warning("O valor do pagamento não deve ser inferior à fatura.");
            }
            else{
                $("#submit_btn").click(); 
            }            
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
        $('#pay_send_receipt').click();
        
        $('#pay_created_date').flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i",
            dateFormat: "Y-m-d H:i",
            defaultDate : "<?=date('Y-m-d H:i')?>",
            locale: 'es',
            appendTo: document.getElementById('picker_container')
        });

        function modal_data_load(){
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
                    var i = 1;
                    invoicesList.forEach(val => {
                        let realCurrenyVal = val.amountToPay.toLocaleString('es-ar', {style: 'currency',currency: 'ARS'});
                        if(i == 1)
                            $('#pay_invoices').append(`<option value='${val.id}'  selected>${val.number} (${realCurrenyVal})</option>`);
                        else
                            $('#pay_invoices').append(`<option value='${val.id}'>${val.number} (${realCurrenyVal})</option>`);
                            
                        pay_invoices_array[val.id] = val.amountToPay;

                        i++;
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
        }

        $('#btn_add').click(function(){
            modal_data_load();            
        });

        $('.dropify').dropify({
            messages: {
                'default': '',
                'error':   'type error ',
            }
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