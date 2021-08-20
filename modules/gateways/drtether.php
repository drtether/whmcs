<?php
/*
 - Author : Dr.Tether
 - Module Designed For The : www.DrTether.com
 - Mail : info@drtether.com
*/

use WHMCS\Database\Capsule;

if (isset($_REQUEST['invoiceId']) && is_numeric($_REQUEST['invoiceId'])) {
    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';
    $gatewayParams = getGatewayVariables('drtether');
    if (isset($_GET['txnid']) && $_REQUEST['callback'] == 1) {

        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->first();
        if (!$invoice) {
            die("Invoice not found");
        }

        if ($gatewayParams['currencyType'] == "USD") {
            $amount = $invoice->total;
        } else {
            $amount = $invoice->total;
        }

        $api = $gatewayParams['api_key'];
        $txnid = $_GET['txnid'];
        $result = verify($txnid);

        $responseAsArray = json_decode($result, true);
        $confirm = $responseAsArray['confirmed'];
        $txnhash = $responseAsArray['hash'];
        
        if (isset($result)) {
            if ($confirm == 1) {
                logTransaction($gatewayParams['name'], $_REQUEST, 'Success');
                addInvoicePayment(
                    $invoice->id,
                    $txnhash,
                    $invoice->total,
                    0,
                    'drtether'
                );
            } else {
                logTransaction($gatewayParams['name'], array(
                    'Code' => 'drtether Status Code',
                    'Message' => 'Sorry-1',
                    'Transaction' => $txnhash,
                    'Invoice' => $invoice->id,
                    'Amount' => $invoice->total,
                ), 'Failure');
            }
        } else {
            if ($confirm == 0) {
                logTransaction($gatewayParams['name'], array(
                    'Code' => 'drtether Status Code',
                    'Message' => 'Sorry-2',
                    'Transaction' => $txnhash,
                    'Invoice' => $invoice->id,
                    'Amount' => $invoice->total,
                ), 'Failure');
            }
        }

        $go = $gatewayParams['systemurl'] . 'viewinvoice.php?id=' . $invoice->id ;
        header("Location: $go");

    } else if (isset($_SESSION['uid'])) {
        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->first();
        if (!$invoice) {
            die("Invoice not found");
        }
        $client = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();

        if ($gatewayParams['currencyType'] == "USD") {
            $amount = $invoice->total;
        } else {
            $amount = $invoice->total;
        }

        $result = send($gatewayParams['api_key'], $amount, $gatewayParams['systemurl'] . 'modules/gateways/drtether.php?invoiceId=' . $invoice->id . '&callback=1');
        
        $responseAsArray = json_decode($result, true);
        $hash = $responseAsArray['data']['hash'];
        $status = $responseAsArray['status'];
        $result = json_decode($result);

        if ($status == 200) {
            $go = "https://drtether.com/api/v1/pay/transaction/".$hash;
            header("Location: $go");
        } else {
            print_r($result);
        }
    }
    return;
}

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function drtether_MetaData()
{
    return array(
        'DisplayName' => 'Dr.Tether Gateway for WHMCS',
        'APIVersion' => '1.0',
    );
}

function verify($token1)
{
    $ch = curl_init('https://drtether.com/api/v1/transaction/'.$token1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'GET' );

    $response = curl_exec($ch);

    curl_close($ch);
    return $response;
}

function send($api, $amount, $redirect)
{
    $post = [
        'merchant' => $api,
        'callback' => $redirect,
        'amount'   => $amount,
    ];
    
    $ch = curl_init('https://drtether.com/api/v1/make/transaction');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

function drtether_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Dr.Tether Gateway',
        ),
        'currencyType' => array(
            'FriendlyName' => 'Currenct Type',
            'Type' => 'dropdown',
            'Options' => array(
                'USD' => 'US Dollar',
            ),
        ),
        'api_key' => array(
            'FriendlyName' => 'Merchant ID',
            'Type' => 'text',
            'Size' => '255',
            'Default' => '',
            'Description' => 'Merchant ID you recived from our website.'
        ),

    );
}

function drtether_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/drtether.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] . '">';
    $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}