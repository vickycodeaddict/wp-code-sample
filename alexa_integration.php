<?php

define("ALEXA_APP_ID", "app id replaced by vicky");

define("ALEXA_LAUNCH", "Welcome msg replaced by vicky");
define("ALEXA_STOP_END", "Good Bye.");
define("ALEXA_HELP", "help msg replaced by vicky");
define("ALEXA_THANKS", "Thanks for listening, You can filter activities by Age and seasons.");
define("ALEXA_THANKS_END", "Thanks for listening, You can filter activities by Age and seasons.");
define("ALEXA_NOT_FOUND", "Sorry, we don't have any activities which fit those criteria ");
define("ALEXA_NOT_RECOGNIZE", "Sorry *** cant find your request.");

require_once ('./../wp-load.php');
require_once __DIR__ . '/alexa_logger.php';
require_once __DIR__ . '/alexa_functions.php';

$debug = is_debug();
if ($debug) {
    set_debug();
}

$jsonRequest = file_get_contents('php://input');
$data = json_decode($jsonRequest, true);

alexa_log($data);

if (!$debug) {
    check_validation_alexa($data);
}

$intent = !empty($data['request']['intent']['name']) ? $data['request']['intent']['name'] : 'default';
$intent_type = !empty($data['request']['type']) ? $data['request']['type'] : '';
$session = !empty($data['session']['attributes']) ? $data['session']['attributes'] : array();

if ($intent_type == 'LaunchRequest') {
    $speach = ALEXA_LAUNCH;
    $responseArray = build_response_array($speach);
    alexa_response($responseArray);
} elseif ($intent == 'AMAZON.StopIntent' || $intent_type == 'SessionEndedRequest') {
    $speach = ALEXA_STOP_END;
    $responseArray = build_response_array($speach, array(), true);
    alexa_response($responseArray);
} elseif ($intent == 'AMAZON.HelpIntent') {
    $speach = ALEXA_HELP;
    $responseArray = build_response_array($speach);
    alexa_response($responseArray);
} else {

    if ($intent == 'AMAZON.YesIntent' && !empty($session)) {

        $speach_arr = $session;
        $que = end($speach_arr['viewed']);
        //$speach = $speach_arr['data'][$que]['desc'];
        $speach_id = $speach_arr['data'][$que]['ID'];
        $speach = clean_input_speach(get_field('about_activity_for_alexa', $speach_id));

        $next_que = end($speach_arr['viewed']) + 1;
        $speach_arr['viewed'][] = $next_que;
        if (!empty($speach_arr['data'][$next_que])) {
            $speach .= $speach_arr['data'][$next_que]['title'];
        } else {
            $speach .= ALEXA_THANKS;
        }
        $responseArray = build_response_array($speach, $speach_arr);
        alexa_response($responseArray);
    } elseif ($intent == 'AMAZON.NoIntent' && !empty($session)) {

        $speach_arr = $session;
        $next_que = end($speach_arr['viewed']) + 1;
        $speach_arr['viewed'][] = $next_que;
        if (!empty($speach_arr['data'][$next_que])) {
            $speach = $speach_arr['data'][$next_que]['title'];
        } else {
            $speach = ALEXA_THANKS_END;
        }

        $responseArray = build_response_array($speach, $speach_arr);
        alexa_response($responseArray);
    } elseif ($intent == 'ideas' || $debug) {
        
        $day = get_slot_value($data, 'day');
        $kid = get_slot_value($data, 'kid');
        $location = get_slot_value($data, 'location');
        $players = get_slot_value($data, 'players');
        
        $age_row = get_slot_value($data, 'age');
        $age = parce_age($age_row);
        
        $activityDuration_row = get_slot_value($data, 'activityDuration');
        $activityDuration = parce_duration($activityDuration_row);
        
        $equipment = get_slot_value($data, 'equipment');
        if ($equipment == 'with equipment') {
            $equipment = '1';
        } elseif ($equipment == 'without equipment') {
            $equipment = '2';
        } else {
            $equipment = false;
        }

        $meta_q = array('relation' => 'AND');
        
        $meta_q[] = array(
            'key' => 'exclude_from_alexa',
            'value' => '1',
            'compare' => '!='
        );
         

        if ($age) {
            $meta_q[] = array('key' => 'how_old_children', 'value' => $age, 'compare' => 'LIKE');
        }
        if ($day) {
            $meta_q[] = array('key' => 'when', 'value' => $day, 'compare' => 'LIKE');
        }
        if ($location) {
            $meta_q[] = array('key' => 'where_activity', 'value' => $location, 'compare' => 'LIKE');
        }
        if ($players) {
            $meta_q[] = array('key' => 'how_many_children', 'value' => $players, 'compare' => 'LIKE');
        }
        if ($equipment) {
            $meta_q[] = array('key' => 'needs_equipement_activitie', 'value' => $equipment, 'compare' => '=');
        }
        if ($activityDuration) {
            $meta_q[] = array('key' => 'how_long_activitie', 'value' => $activityDuration, 'compare' => 'LIKE');
        }
        
        $args = array(
            'posts_per_page' => 50,
            'post_type' => 'activities',
            'post_status' => 'publish',
            'orderby' => 'rand',
            'meta_query' => $meta_q
            
        );
        
        alexa_log($args);
        $ac_query = new WP_Query($args);

        $speach_arr = array('data' => array(), 'viewed' => array(), 'total' => 0);

        if ($ac_query->have_posts()) {
            $i = 1;
            while ($ac_query->have_posts()) {
                $ac_query->the_post();

                if ($i == 1) {
                    $count = ($ac_query->post_count > 1) ? 'The first' : '';
                } else {
                    $count = ' Another';
                }
                $speach_arr['data'][$i]['title'] = $count . ' activity is ' . clean_input_speach(get_the_title()) . '. Do you want to know more?';
                //$speach_arr['data'][$i]['desc'] = clean_input_speach(get_the_content()) . clean_input_speach(get_field('about_the_activity_text', $ac_query->post->ID));
                $speach_arr['data'][$i]['ID'] = $ac_query->post->ID;
                $i++;
            }
            $speach = 'We found ' . $ac_query->found_posts . ' activities. ' . $speach_arr['data'][1]['title'];
            $speach_arr['viewed'][] = 1;
            $speach_arr['total'] = $ac_query->post_count;
        } else {
            $speach = ALEXA_NOT_FOUND;
        }
        wp_reset_postdata();

        if ($debug) {
            echo $speach;
        }

        $responseArray = build_response_array($speach, $speach_arr);
        alexa_response($responseArray);
    } else {
        $speach = ALEXA_NOT_RECOGNIZE;
        $responseArray = build_response_array($speach);
        alexa_response($responseArray);
    }
}


<?php

function clean_input_speach($content) {
    $content = wp_strip_all_tags($content,true);
    $content = preg_replace("/&#?[a-z0-9]+;/i", "", $content);
    return $content;
}

function is_debug(){
    return (isset($_GET['debug']) && $_GET['debug'] == 1) ? true : false;
}

function set_debug(){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

function build_response_array($speach, $speach_arr = array(), $session_end = false, $ssml = null, $type = 'PlainText') {
    $response = [
        'version' => '1.0',
        'response' => [
            'outputSpeech' => [
                'type' => $type,
                'text' => $speach,
                'ssml' => $ssml
            ],
            
            'shouldEndSession' => $session_end
        ],
        'sessionAttributes' => $speach_arr
    ];
    return $response;
}

function alexa_response($responseArray){
    header('Content-Type: application/json');
    echo json_encode($responseArray);
    die();
}

function get_slot_value($data, $slot) {

    if (is_debug()) {
        return (isset($_GET[$slot]) && $_GET[$slot] != '') ? $_GET[$slot] : false;
    }

    if (!empty($data) && isset($slot) && isset($data['request']['intent']['slots'][$slot]['value']) && $data['request']['intent']['slots'][$slot]['value'] != '') {
        
        if(isset($data['request']['intent']['slots'][$slot]['resolutions']['resolutionsPerAuthority'][0]['status']['code']) && $data['request']['intent']['slots'][$slot]['resolutions']['resolutionsPerAuthority'][0]['status']['code'] == 'ER_SUCCESS_NO_MATCH'){
            return false;
        }
     
        $val = uk_language_parce($data['request']['intent']['slots'][$slot]['value']);
        return $val;
    } else {
        return false;
    }
}

function parce_age($age_row){
    if($age_row === 'year' || $age_row === 'old'){
        return false;
    }
    
    $arr_2_5 = array('little', '5 year-old', '4 year-old', '3 year-old', '2 year-old', '1 year-old');
    $arr_6_11 = array('young','11 year-old', '10 year-old', '9 year-old', '8 year-old', '7 year-old', '6 year-old');
    $arr_12_plus = array('younger', '20 year-old', '19 year-old', '18 year-old', '17 year-old', '16 year-old', '15 year-old', '14 year-old', '13 year-old', '12 year-old');

    if(in_array($age_row, $arr_2_5)){
        return '1';
    }elseif(in_array($age_row, $arr_6_11)){
        return '2';
    }elseif(in_array($age_row, $arr_12_plus)){
        return '3';
    }else{
        return false;
    }
}

function parce_duration($activityDuration_row){
    
    $arr_10_min = array('5 minutes','10 minutes','15 minutes');
    $arr_20_30_min = array('20 minutes','30 minutes','half an hour');
    $arr_1_hour = array('1 hour');
    $arr_2_4_hour = array('2 hour','3 hour','4 hour');
    
    if(in_array($activityDuration_row, $arr_10_min)){
        return '1';
    }elseif(in_array($activityDuration_row, $arr_20_30_min)){
        return '2';
    }elseif(in_array($activityDuration_row, $arr_1_hour)){
        return '3';
    }elseif(in_array($activityDuration_row, $arr_2_4_hour)){
        return '4';
    }else{
        return false;
    }

}

function uk_language_parce($str_row){
    
    switch ($str_row) {
        case "traveling":
            $str = "travelling";
            break;
        default:
            $str = $str_row;
    }
    
    return $str;
}

function validateKeychainUri($keychainUri) {

    $uriParts = parse_url($keychainUri);

    if (strcasecmp($uriParts['host'], 's3.amazonaws.com') != 0)
        fail('The host for the Certificate provided in the header is invalid');

    if (strpos($uriParts['path'], '/echo.api/') !== 0)
        fail('The URL path for the Certificate provided in the header is invalid');

    if (strcasecmp($uriParts['scheme'], 'https') != 0)
        fail('The URL is using an unsupported scheme. Should be https');

    if (array_key_exists('port', $uriParts) && $uriParts['port'] != '443')
        fail('The URL is using an unsupported https port');
}

function fail($message) {

    alexa_log($message);
    header('HTTP/1.1 400 BAD REQUEST');
    die();
}

function check_validation_alexa($data) {
    /**
     * Removed by vicky
     */
}
