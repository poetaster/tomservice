<?php
/*
 * Plugin Name: VG TOM Service Plugin
 * Plugin URI: http://www.netzpolitik.org/wp-tomservice.tar.gz
 * Description: Verwaltung der VG Wort Zählpixel, Autoren Kartei Nummern, TOM Konto zugriff und Text Meldung
 * Version: 1.00
 * Author: Mark Washeim mwa@newthinking.de
 * Author URI: http://netzpolitik.org
 * License: GPL v2.0 http://www.gnu.org/licenses/gpl-2.0
 * Vgwort services at
 * pixel service https://tom.vgwort.de/services/1.0/pixelService.wsdl
 * message service https://tom.vgwort.de/services/1.2/messageService.wsdl
 */

include('admin-menu.php');
define('PLUGINNAME','wp-tomservice');
define('TOMMETA', get_option('wp_tommeta', 'wp_vgwortmarke'));
define('TOMMETA_PRIV', get_option('wp_tommeta_priv', 'wp_vgwortmarke_priv'));
define('TOMMETA_PUB', get_option('wp_tommeta_pub', 'wp_vgwortmarke_pub'));
define('TOMMETA_AUTH', get_option('wp_tommeta_auth', 'wp_vgwortmarke_auth'));

define('PIXEL_SERVICE_WSDL', 'https://tom.vgwort.de/services/1.0/pixelService.wsdl');
define('MESSAGE_SERVICE_WSDL', 'https://tom.vgwort.de/services/1.13/messageService.wsdl');
#define('PIXEL_SERVICE_WSDL', 'https://tom-test.vgwort.de/services/1.0/pixelService.wsdl');
#define('MESSAGE_SERVICE_WSDL', 'https://tom-test.vgwort.de/services/1.13/messageService.wsdl');

define('WORT_USER', getenv('WORT_USER'));
define('WORT_PASS', getenv('WORT_PASS'));
define('WORT_KARTEI', getenv('WORT_KARTEI'));

// Init Methods
// actions for showing user stats and or updating their card number
add_action( 'edit_user_profile' , 'wpTomServiceaddProfileData' );
add_action( 'show_user_profile' , 'wpTomServiceaddProfileData' );
add_action('personal_options_update', 'action_process_option_update');
add_action('edit_user_profile_update', 'action_process_option_update');

add_action( 'add_meta_boxes' ,  'wpTomServiceAddCustomMeta' );
//add_action( 'save_post' ,  'wpTomServiceSavePost' );
add_filter( 'the_content' ,   'wpTomServiceDisplay' );

/* actions to handle interface elements and counts for admin pages/posts */
add_action( 'manage_posts_custom_column' ,  'wpTomServiceCustomColumn' );
add_action( 'manage_pages_custom_column' ,  'wpTomServiceCustomColumn' );
add_action( 'admin_footer' ,     'wpTomServiceAdminFooter' );
add_filter( 'manage_posts_columns' ,  'wpTomServiceColumn' );
add_filter( 'manage_pages_columns' ,  'wpTomServiceColumn' );

add_action( 'admin_menu' ,  'wpTomServiceRegisterSettingsPage' );

/* various actions to handle moving from transitions. we fetch pixels on -> publish */
add_action( 'draft_to_publish'   ,  'wpTomServicePublish' );
add_action( 'pending_to_publish' ,  'wpTomServicePublish' );
add_action( 'private_to_publish' ,  'wpTomServicePublish' );
//add_action( 'publish_future_post'  ,  'wpTomServiceScheduled' );
// publish to future was doing this with postID but future_to transition with object? let's see
add_action( 'future_to_publish'  ,  'wpTomServicePublish', 10, 1);
//add_action( 'publish_to_pending' ,  'wpTomServiceDraft' );
//add_action( 'transition_post_status' ,  'wpTomServiceDraft' );

/* action for triggering submission to tom via cron */
//add_action('service_submit_event', array( &$this, 'wpTomServiceCron'), 10, 1);
add_action('service_submit_event', 'wpTomServiceCron',2);

/* Setup cron */
add_action( 'wpTomService_cron_hook', array('wpTomService', 'wpTomServiceCLI') );

if ( ! wp_next_scheduled( 'wpTomService_cron_hook' ) ) {
    wp_schedule_event( time(), 'five_seconds', 'wpTomService_cron_hook' );
}

/**
 *
 * register settingspage in wordpress
 * @param: none
 *
 */

function wpTomServiceRegisterSettingsPage() {
  add_submenu_page( 'options-general.php' , '', 'VG TOM Service' , 'add_users', 'wpTomServiceSettings',     'wpTomServiceSettingsPage' );
  add_submenu_page( 'options-general.php' , 'wpTomServiceSettings', 'VG TOM Request' , 'add_users', 'wpTomServiceRequest',     'wpTomServiceRequestPage' );
}

function wpTomServiceCLI(){
  global $wpdb;
  // REQUEST
  $results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta PM INNER JOIN $wpdb->posts P ON P.ID = PM.post_id WHERE PM.meta_key = 'tom_submitted' AND PM.meta_value ='pending' AND P.post_status = 'publish' AND P.post_type IN ('post') AND P.ID > 50000 ORDER BY P.post_date ASC LIMIT 100");

  // we should do our time checking at query .. select TIMESTAMPDIFF(MINUTE, NOW(), timestamp_column) FROM my_table 
  // but we don't as yet 
  // also, the limit here is a lousy solution

    //update_option('wp_tommeta' , $_POST['wptommeta']);
    $t_time = time();
    foreach($results as $result){
      // fetch the time the pixels were ordered
      $vgwort = get_post_meta( $result->ID , 'tom_orderDateTime' , false );
      $code = get_post_meta( $result->ID , 'tom_privateIdentificationId' , false );
      $cardNumber = get_user_meta($result->post_author, 'wp_tommeta_auth', false);
      $status = get_user_meta($result->post_author, 'tom_status', false);
      //determine elapsed hours post date in U is seconds unixtime
      $date = new DateTime($result->post_date_gmt);
      $postdate = $date->format("U");
      // now days
      $elapsed = (time() - $postdate )  / 86400;

      // only submit if older than 5 days
      //echo $result->post_date_gmt . "\n\n";
      //echo $result->ID . "\n\n";
      //
      if ($elapsed > 5) {
        $soapResult = wpTomServiceCron($result->ID, $code[0], $cardNumber[0]);
        //echo $soapResult;
      }
    }
}



/*
 * Used on transition _to_publish to fetch pixels and submit to tom
 *
 */
function wpTomServicePublish( $post ) {
  // obtain author info to checkAuthor at tom
  $surName = get_user_meta($post->post_author, 'last_name', true) ;
  $cardNumber = get_user_meta($post->post_author, 'wp_tommeta_auth', true); 
  // only obtain rest if we have enough characters
  if ( ( tomCharCount( $post->post_content )  > 1800 ) && ( $cardNumber != '')  ) {
    $fvar = get_post_meta($post->ID , 'tom_publicIdentificationId') ;
    $fvar = $fvar['0'];
    // only proceed if we don't already have a pixel code
    if( ($fvar == 'pending')  || ($fvar == '') ) {
      // these are set in wp-config
      $vgWortUserId = WORT_USER;
      $vgWortUserPassword = WORT_PASS;
      try {
        // catch and throw an exception if the authentication or an authorization error occurs
        /*
        if(!@fopen(str_replace('://', '://'.$vgWortUserId.':'.$vgWortUserPassword.'@', PIXEL_SERVICE_WSDL), 'r')) {
          // THIS was the message that worked till 07 2015
          // the method without gets a default soapFault object with methods
          $httpString = explode(" ", $http_response_header[0]);
          throw new SoapFault('httpError', $httpString[1]);
        }
         */
        // initialize client
        $client = new SoapClient(PIXEL_SERVICE_WSDL, array('login' => $vgWortUserId, 'password' => $vgWortUserPassword, 'exceptions'=>true, 'trace'=>1, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS));
        $result = $client->orderPixel(array("count"=>'1'));
        $pix = (array) $result;
        // ok, we have a legit response
        $pixarr =    (array) $pix['pixels'];
        $ident = (array) $pixarr['pixel'][0];
        // record the domain, date and pub/private pair
        update_post_meta($post->ID , 'tom_Domain' , $pix['domain']  );
        update_post_meta($post->ID , 'tom_orderDateTime' , time()  );
        update_post_meta($post->ID , 'tom_publicIdentificationId' , $ident['publicIdentificationId']  );
        update_post_meta($post->ID , 'tom_privateIdentificationId' , $ident['privateIdentificationId'] );
        update_post_meta($post->ID , 'tom_submitted' , 'pending'  );
        // sadly unable to get this method working
        // schedule cron to do the submit, 1 week later
        //wp_schedule_single_event(time()+110, 'service_submit_event' , array($post->ID, $ident['privateIdentificationId']));
      }
      catch (SoapFault $soapFault) {
        // we add a meta field to the article with the error string
        if($soapFault->faultcode == 'noWSDL' || $soapFault->faultcode == 'httpError') {
          update_post_meta($post->ID , 'tom_fault',  $soapFault->faultstring);
        }
        $detail = $soapFault->detail;
        $function = $detail->orderPixelFault;
        error_log(print_r($soapFault->getMessage(),true),0);
        error_log($soapFault->faultcode,0);
        error_log($soapFault->faultstring,0);
        update_post_meta($post->ID , 'tom_fault',  $soapFault->getMessage());
      }
    }
  }
}

/*
* submit when future scheduled goes live
*
*/
function wpTomServiceScheduled( $post_id ) {
  $post = get_post($post_id);
   wpTomServicePublish( $post );
}

/*
 * Was intended to deal with removing posts from the published state.
 * Does this make sense?
 *
 **/
function wpTomServiceDraft( $post ) {

  // if a post goes to any other state reset FIX ME this will kill already assigned pixcodes
  //update_post_meta($post->ID , 'tom_publicIdentificationId' , 'pending' );
  //update_post_meta($post->ID , 'tom_privateIdentificationId' , 'pending' );
  //wp_clear_scheduled_hook( 'service_submit_event', array( $post->ID ) );
}

/* 
 * Submit the article to TOM.
 * @param $post_id int
 * @param $code int (privatepixelid)
 * @param $author int (tom author cardNumber)
 *
 * Helper function to respond to scheduled event (or post from settings page) and 
 * Submit all relevant Article and Author data to T.O.M.
 * Used by action service_submit_event (cron) which is used after state turns to published
 * and the settings page form action.
 * 
 */

function wpTomServiceCron($post_id, $code, $author = '') {
  $post = get_post($post_id);

  $surName = get_user_meta($post->post_author, 'last_name', true) ;
  $givenName = get_user_meta($post->post_author, 'first_name', true) ;
  $cardNumber = $author;

  if ($cardNumber == '' || $cardNumber == NULL || $cardNumber == '2051162') {
    $error =  "sorry! $cardNumber not a valid cardNumber"; 
    //echo $error . "\n\n";
    return $error;
  } 
  // this is weird, get post meta  won't work reliably this context!
  // $fpriv = get_post_meta($post->ID , 'tom_privateIdentificationId', true) ;
  // $fpriv = $fpriv[0] ;
  $fpriv = $code;
  
  // fetched/set from wp-config.php
  $vgWortUserId = WORT_USER;
  $vgWortUserPassword = WORT_PASS;

  $authors = array('author'=>array());

  // get authors information: vg wort card number, first (max. 40 characters) and last name
  if(function_exists("additional_authors_get_the_authors_ids")){
     foreach (additional_authors_get_the_authors_ids($post_id) as $author_id){
          $surName = get_user_meta($author_id, 'last_name', true) ;
          $givenName = get_user_meta($author_id, 'first_name', true) ;
          $cardNumbers = get_user_meta($author_id, 'wp_tommeta_auth', false);
          $cardNumber = $cardNumbers[0];
          if ($surName != 'Dinges') {
            $authors['author'][] = array('cardNumber'=>$cardNumber, 'firstName'=>substr($givenName, 0, 39), 'surName'=>$surName);
          }
     }
  } else {
    $authors['author'][] = array('cardNumber'=>$cardNumber, 'firstName'=>substr($givenName, 0, 39), 'surName'=>$surName);
  }

  $parties = array('authors'=>$authors);
  var_dump($parties);

  // shortext is title truncated
  $shortText = mb_substr($post->post_title, 0, 99);

  // webrange is the url(s).
  $webrange = array('url'=>array(get_permalink($post->ID)));
  $webranges['webrange'][] = $webrange;

  // is it a poem (NOT)
  $isLyric = false;

  // the actual article content without html
  $excpt = strip_tags(get_the_excerpt($post->ID));
  $maint = strip_shortcodes(strip_tags($post->post_content));
  $fullt = $excpt . $maint;
  $text = array('plainText'=>$fullt);

  // create a VG Wort message
  $message = array('shorttext'=>$shortText, 'text'=>$text , 'lyric' => $isLyric);
  try {
    // catch and throw an exception if the authentication or the authorization error occurs
    if(!@fopen(str_replace('://', '://'.WORT_USER.':'.WORT_PASS.'@', MESSAGE_SERVICE_WSDL), 'r')) {
      $httpString = explode(" ", $http_response_header[0]);
      throw new SoapFault('httpError', $httpString[1]);
    }
    $client = new SoapClient(MESSAGE_SERVICE_WSDL, array('login' => $vgWortUserId, 'password' => $vgWortUserPassword, 'exceptions'=>true, 'trace'=>1, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS ));

    $result = $client->newMessage(array("parties"=>$parties, "privateidentificationid"=>$fpriv, "messagetext"=>$message, "webranges"=>$webranges));
    // we flag it's been submitted to avoid processing again. 
    // used as flag in the settings page post action. 
    update_post_meta($post->ID , 'tom_submitted',  'submitted');
    // the following permits seeing the actual xml data submitted
    //error_log($client->__getLastRequest(),0);
    //error_log($client->__getLastRequest(),0);
    // var_dump($result);
    return $result;
    //wp_clear_scheduled_hook( 'service_submit_event', array( $post->ID, $fpriv ) );
  }
  catch (SoapFault $soapFault) {
    $detail = $soapFault->faultcode;
    error_log($soapFault,0);
    update_post_meta($post->ID , 'tom_fault',  $soapFault->detail);
    update_post_meta($post->ID , 'tom_submitted',  'pending');
    // echo $post->ID ."\n\n";
    // var_dump($soapFault->detail);
    return $soapFault->detail;
    
    // yup, trouble in paradise
    // wp_clear_scheduled_hook( 'service_submit_event', array( $post->ID ) );
    // wp_schedule_single_event(time()+700, 'service_submit_event' , array($post->ID));
  }

}

/**
 * Check if the card number is valid for the autor.
 * @param $cardNo int VG Wort card number
 * @param $surName string author last name
 * @return array (valid boolean, errorMsg string)
 *
 * FIX ME currently not used.
 */
function checkAuthor($cardNo, $surName) {
  $vgWortUserId = WORT_USER;
  $vgWortUserPassword = WORT_PASS;

  try {
    // catch and throw an exception if the authentication or the authorization error occurs
    if(!@fopen(str_replace('://', '://'.$vgWortUserId.':'.$vgWortUserPassword.'@', MESSAGE_SERVICE_WSDL), 'r')) {
      $httpString = explode(" ", $http_response_header[0]);
      throw new SoapFault('httpError', $httpString[1]);
    }
    $client = new SoapClient(MESSAGE_SERVICE_WSDL, array('login' => $vgWortUserId, 'password' => $vgWortUserPassword));
    $result = $client->checkAuthor(array("cardNumber"=>$cardNo, "surName"=>$surName));
    return (array) $result;
  }
  catch (SoapFault $soapFault) {
    if($soapFault->faultcode == 'noWSDL' || $soapFault->faultcode == 'httpError') {
      return array(false, $soapFault->faultstring);
    }
    $detail = $soapFault->detail;
    $function = $detail->checkAuthorFault;
    return array(false, $function->errorcode);
  }
}

/**
 * researchMetisMessagesRequest returns information about submission
 *
 * @param $cardNumber int
 * @return array (ResearchedMetisMessage xml, errorMsg string)
 *
 */
function researchMetisMessages($cardNumber) {
  $vgWortUserId = WORT_USER;
  $vgWortUserPassword = WORT_PASS;

  // webrange is the url(s).
  $webrange = array('url'=>array(get_permalink($post->ID)));
  $webranges['webrange'][] = $webrange;

  try {
    // catch and throw an exception if the authentication or the authorization error occurs
    if(!@fopen(str_replace('://', '://'.$vgWortUserId.':'.$vgWortUserPassword.'@', MESSAGE_SERVICE_WSDL), 'r')) {
      $httpString = explode(" ", $http_response_header[0]);
      throw new SoapFault('httpError', $httpString[1]);
    }
    $client = new SoapClient(MESSAGE_SERVICE_WSDL, array('login' => $vgWortUserId, 'password' => $vgWortUserPassword, 'exceptions'=>true, 'trace'=>1, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS ));

    //$result = $client->researchMetisMessages(array("title"=>$title,"offset"=>"10","amount">="5","webranges"=>$webranges));
    $result = $client->researchMetisMessages(array("offset"=>"0","cardNumber"=>$cardNumber));
    return (array) $result;
  }
  catch (SoapFault $soapFault) {
    if($soapFault->faultcode == 'noWSDL' || $soapFault->faultcode == 'httpError') {
      return array(false, $soapFault->faultstring);
    }
    return $soapFault;
  }
}

// end class
?>
