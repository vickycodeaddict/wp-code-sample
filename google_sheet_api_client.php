<?php
/**
 * Plugin Name: Google Sheet API
 * Plugin URI: #
 * Description: Google APIs Client Library to sync delegate with google sheet.
 * Version: 1.0
 * */


include( plugin_dir_path(__FILE__) . 'vendor/autoload.php');

/**
 * @var string  $rows Associative array of rows and cell.
 */

function add_delegate_to_sheet( $rows = array(), $purchase_id) {
    $cron_log_dir = plugin_dir_path( __FILE__ ).'log';
    if(!is_dir($cron_log_dir)){
        mkdir($cron_log_dir, 0755);
    }
    
    $client = new \Google_Client();
    $client->setApplicationName('C21 PHP App');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    $auth = json_decode(get_option('gapi_auth_json'), true);
    $client->setAuthConfig($auth);
    $sheets = new \Google_Service_Sheets($client);
    $spreadsheetId = trim(get_option('gapi_spreadsheet_id'));
    $sheet_name = trim(get_option('gapi_sheet_name'));

    if(!empty($rows)){
        $aff_emails = array();
        foreach ($rows as $key => $value) {
            $updateRange = $sheet_name .'!A:U';
            $updateBody = new \Google_Service_Sheets_ValueRange([
                'range' => $updateRange,
                'majorDimension' => 'ROWS',
                'values' => ['values' => $value],
            ]);
            try{
                $sheets->spreadsheets_values->append($spreadsheetId, $updateRange, $updateBody, ['valueInputOption' => 'USER_ENTERED']);
                $aff_emails[] = $key;
            }catch(Exception $e) {
                wpsc_update_purchase_meta( $purchase_id, 'delegate_processed_sheet', 'no');
                $log  = "Date: ".date("F j, Y, g:i a").PHP_EOL."Error: ".$e->getMessage().PHP_EOL."Following Entry may not be written into sheet: ".$key.PHP_EOL."-------------------------".PHP_EOL;
                file_put_contents($cron_log_dir.'/log_write_sheet_'.date('m_Y').'.log', $log, FILE_APPEND);
            }
        }
        if(!empty($aff_emails)){
            $aff_emails = implode(", ",$aff_emails);
            $log  = "Date: ".date("F j, Y, g:i a").PHP_EOL."Success: Affected Entries email ".$aff_emails.PHP_EOL."-------------------------".PHP_EOL;
            file_put_contents($cron_log_dir.'/log_write_sheet_'.date('m_Y').'.log', $log, FILE_APPEND);
        }
    }
}
