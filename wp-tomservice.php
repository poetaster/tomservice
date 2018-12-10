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

define(PLUGINNAME,'wp-tomservice');
define(TOMMETA, get_option('wp_tommeta', 'wp_vgwortmarke'));
define(TOMMETA_PRIV, get_option('wp_tommeta_priv', 'wp_vgwortmarke_priv'));
define(TOMMETA_PUB, get_option('wp_tommeta_pub', 'wp_vgwortmarke_pub'));
define(TOMMETA_AUTH, get_option('wp_tommeta_auth', 'wp_vgwortmarke_auth'));

define(PIXEL_SERVICE_WSDL, 'https://tom.vgwort.de/services/1.0/pixelService.wsdl');
define(MESSAGE_SERVICE_WSDL, 'https://tom.vgwort.de/services/1.2/messageService.wsdl');
//define(PIXEL_SERVICE_WSDL, 'https://tom-test.vgwort.de/services/1.0/pixelService.wsdl');
//define(MESSAGE_SERVICE_WSDL, 'https://tom-test.vgwort.de/services/1.2/messageService.wsdl');

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
add_action( 'save_post' ,  'wpTomServiceSavePost' );
add_filter( 'the_content' ,   'wpTomServiceDisplay' );

/* actions to handle interface elements and counts for admin pages/posts */
add_action( 'manage_posts_custom_column' ,  'wpTomServiceCustomColumn' );
add_action( 'manage_pages_custom_column' ,  'wpTomServiceCustomColumn' );
add_action( 'admin_footer' ,     'wpTomServiceAdminFooter' );
add_filter( 'manage_posts_columns' ,  'wpTomServiceColumn' );
add_filter( 'manage_pages_columns' ,  'wpTomServiceColumn' );
add_action( 'admin_menu' ,  'wpTomServiceRegisterSettingsPage' );

/* various actions to handle moving from transitions. we fetch pixels on -> publish */
add_action( 'draft_to_publish' ,  'wpTomServicePublish' );
add_action( 'pending_to_publish' ,  'wpTomServicePublish' );
add_action( 'private_to_publish' ,  'wpTomServicePublish' );
add_action( 'publish_future_post' ,  'wpTomServiceScheduled' );
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
  add_submenu_page( 'options-general.php' , 'VG TOM Service', 'VG TOM Service' , 'add_users', 'wpTomServiceSettings',     'wpTomServiceSettingsPage' );    
}

function wpTomServiceCLI(){
  global $wpdb;
  // REQUEST
  $results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta PM INNER JOIN $wpdb->posts P ON P.ID = PM.post_id WHERE PM.meta_key = 'tom_submitted' AND PM.meta_value ='pending' AND P.post_status = 'publish' AND P.post_type IN ('post') AND P.ID > 50000 ORDER BY P.post_date ASC LIMIT 20");

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
      if ($elapsed > 4) {
        $soapResult = wpTomServiceCron($result->ID, $code[0], $cardNumber[0]);
        //echo $soapResult;
      }
    }
}

/**
 *
 * Add Html for the settingspage
 * @param: none
 *
 */
function wpTomServiceSettingsPage (){
  global $wpdb;
  // REQUEST
  $results = $wpdb->get_results("SELECT * FROM $wpdb->postmeta PM INNER JOIN $wpdb->posts P ON P.ID = PM.post_id WHERE PM.meta_key = 'tom_submitted' AND PM.meta_value ='pending' AND P.post_status = 'publish' AND P.post_type IN ('post') AND P.ID > 50000 ORDER BY P.post_date ASC LIMIT 20");

  // we should do our time checking at query .. select TIMESTAMPDIFF(MINUTE, NOW(), timestamp_column) FROM my_table 
  // but we don't as yet 
  // also, the limit here is a lousy solution

  if(isset($_POST[ 'save' ])){
    //update_option('wp_tommeta' , $_POST['wptommeta']);
    $t_time = time();
    echo '<ul> <div class="wrap">';
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
      if ($elapsed > 4) {
        $soapResult = wpTomServiceCron($result->ID, $code[0], $cardNumber[0]); 
      }
      echo '<li>Artikel ID/Titel '. $result->ID . ' <b> ' . $result->post_title . '</b> vom Datum '. $date->format("Y-m-d")  . ' und Alter ' . $elapsed . ' (mind. 5 tage) vom Autor ' . $cardNumber['0'] . ' - status:</li>';
      sleep(3);

    }
  }
  echo '</ul>';
?>
            <?php screen_icon( 'plugins' ); ?> 
            <form method="POST" action="">
                <h2>Einstellungen VG-Wort Plugin</h2>
        <?php echo 'Insgesammt:' . count($results) . ' Artikel zu melden.';   ?>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row"> <label for="Metaname">VG Wort Daten (see wp-config.php): </label> </th>
                        <td>
                        user: <?php echo WORT_USER; ?> <br />
                        card: <?php echo WORT_KARTEI; ?> <br />
                        pass: somepassword :) <br />
                     </td>
                    </tr>
                    <tr valign="top">
                    <th scope="row"> <label for="speichern">Artikel anmelden (5 auf einmal):</label> </th>
                    <td>
                    <input type="submit" name="save" value="Bei T.O.M anmelden" class="button-primary" / >
                 </td>
                </tr>
             </form>
            </table>
            <p> Many thanks to <b>Marcus Franke</b> <a href="http://www.internet-marketing-dresden.de" title="internet-marketing-dresden.de">internet-marketing-dresden.de</a> for examples and feedback.</p>
            <p> Many thanks for examples/code  <a href="http://pkp.sfu.ca/wiki/index.php/VGWortPlugIn_Doku"> Funktionaler Ausbau von und Mehrwertdienste für "Open Journal Systems"</a>, Freie Universität Berlin, http://www.cedis.fu-berlin.de/ojs-de</p>
            <p>License: GPL v2.0 http://www.gnu.org/licenses/gpl-2.0</p>
            <p>NO WARRANTY: BECAUSE THE PROGRAM IS LICENSED FREE OF CHARGE, THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION. IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MAY MODIFY AND/OR REDISTRIBUTE THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.</p>
        </div>
<?php
}

/**
 *
 * Add the value of counted Chars in the Footer of RTE
 * @param: none
 *
 */
function wpTomServiceAdminFooter() {
  global $post;

  if(!empty( $post->post_content )) { 
    printf('<script language="javascript" type="text/javascript"> var div = document.getElementById("post-status-info"); if (div != undefined) { div.innerHTML = div.innerHTML + \'%s\'; } </script>', str_replace("'", "\'", sprintf('<span class="inside">Zeichen:'.' %d'.'</span> ', tomCharCount( $post->post_content ) )));
  }
}

/**
 *
 * Add heading in overview of posts/pages
 * @param: none
 *
 */
function wpTomServiceColumn( $defaults )    { 
  $defaults['vgwort'] = 'VG Wort';
  return $defaults;
}

/**
 *
 * Add a custom row for displaying the WGWort status
 * @param: none
 *
 */
function wpTomServiceCustomColumn( $column ) { 
  global $post;
  if($column == 'vgwort') {
    // VG vorhanden?
    $vgwort = get_post_meta( $post->ID , 'tom_publicIdentificationId' , true );
    if($vgwort)    {
      echo '<br/><span style="color:green">vorhanden</span><br />';
      echo tomCharCount($post->post_content).' '.' Zeichen';
    }
    else {
      echo '<br/><span style="color:red">nicht vorhanden</span><br />';
      echo tomCharCount($post->post_content).' '.' Zeichen';
      //echo sprintf('<a href="user-edit.php?user_id=%d">(überprüfen)</a>', $post->post_author );
    }
  }
}

/**
 * 
 * show the available posts/pages that could be used for WGWort
 * @param: object $user;
 *
 */
function wpTomServiceaddProfileData( $user ) {
?>
     <h3 id="vgwortanchor">VG Wort</h3>
     <table>
     <tr>
      <th><label for="something"><?php _e('VG Wort Karteinummer'); ?></label></th>
      <td><input type="text" name="wp_tommeta_auth" id="" value="<?php echo esc_attr(get_the_author_meta('wp_tommeta_auth', $user->ID) ); ?>" /></td>
     </tr>
     </table>

<?php }

function wpTomServiceaddExtraProfileData( $user ) {
  global $wpdb;
  if( user_can( $user->ID , 'edit_posts' ) ) {
?>
                <h3 id="vgwortanchor">VG Wort</h3>
                 <table class="form-table">
                    <tr>
                    <th><label for="vgwort">bisher eingebunden Wortmarken: <?php echo $wpdb->get_var($wpdb->prepare("SELECT count(P.ID) as count FROM wp_postmeta PM INNER JOIN wp_posts P ON P.ID = PM.post_id WHERE PM.meta_key = 'tom_publicIdentificationId' AND PM.meta_value != '' AND P.post_author = '%d'",$user->ID)); ?></label></th>
                     <td>
<?php
    $requiredChars = 1800;
    $results = $wpdb->get_results($wpdb->prepare("SELECT * , CHAR_LENGTH(`post_content`) as charlength FROM ".$wpdb->posts." WHERE post_status = 'publish' AND post_type IN ('post','page') AND post_author = '%d' HAVING charlength > '%d'",$user->ID,$requiredChars));
    if(!empty($results)) {
?>
                     <ul>
                        <li><h4>Mögliche Beiträge</h4></li>
<?php
      $clearContentCount = "";
      foreach($results as $result){
        $vgwort = get_post_meta( $result->ID , 'tom_publicIdentificationId' , true );
        if(empty($vgwort)){
          // Just Text nothing more :)
          $clearContentCount = tomCharCount( $result->post_content );
          if($clearContentCount > $requiredChars){
            echo '<li><a href="'.get_admin_url().'post.php?post='.$result->ID.'&action=edit" title="jetzt VG Wort einfügen">'.$result->post_title.' ('.$clearContentCount.' Zeichen)</a></li>';
          }
        }
      }
    }
  }
?>
            </ul>
        <span class="description">Diesen Beiträge sollten VG Wortmarken hinzugefügt werden</span>
        </td>
        </tr>
     </table>
     <table>
     <tr>
      <th><label for="something"><?php _e('VG Wort Karteinummer'); ?></label></th>
      <td><input type="text" name="wp_tommeta_auth" id="" value="<?php echo esc_attr(get_the_author_meta('wp_tommeta_auth', $user->ID) ); ?>" /></td>
     </tr>
     </table>

<?php }

/**
 * 
 * Update the users meta data with a Karteinummer
 * @param: string $user_id
 * @return null
 *
 */
function action_process_option_update($user_id) {
  update_usermeta($user_id, 'wp_tommeta_auth', ( isset($_POST['wp_tommeta_auth']) ? $_POST['wp_tommeta_auth'] : '' ) );
}

/**
 * 
 * Calculate the Chars of the delivered content
 * @param: string $content
 * @return int
 *
 */
function tomCharCount( $content ) {
  return mb_strlen(preg_replace("/\\015\\012|\\015|\\012| {2,}|\[[a-zA-Z0-9\_=\"\' \/]*\]/", "", strip_tags(html_entity_decode($result->post_title . "" . $content ))));
  //return strlen(preg_replace("/\\015\\012|\\015|\\012| {2,}|\[[a-zA-Z0-9\_=\"\' \/]*\]/", "", wp_strip_all_tags(html_entity_decode($content ))));
  //
  //$text = strip_tags(strip_shortcodes(html_entity_decode($content)));
  //return mb_strlen(preg_replace("/\\015\\012|\\015|\\012| {2,}|\[[a-zA-Z0-9\_=\"\' \/]*\]/", "", $result->post_title . "" . $text ));
  //
  //$text = strip_tags($content);
  //return strlen(  $text );
}

/**
 * 
 * append the Value of VGWORTMETA on the end of content, and will send back
 * @param: string $content
 * @return string $content
 *
 */
function wpTomServiceDisplay( $content ) {
  global $post;
  $pixel = get_post_meta( $post->ID , 'tom_publicIdentificationId' , false);
  $pixel = $pixel[0];
  $domain = get_post_meta( $post->ID , 'tom_Domain' , false );
  $domain = $domain[0];

  #echo $_SERVER['HTTPS'] . ' and  ' . $_SERVER['SERVER_PORT'];
  
  if(!empty( $pixel )){
    if ( $_SERVER['SERVER_PORT'] == '80' ) {
      $content .= '<div class="vgwort"><img src="http://'. $domain . '/na/' . $pixel .'" width="1" height="1" alt="" ></img></div>';
    } else {
      $content .= '<div class="vgwort"><img src="https://ssl-'. $domain . '/na/' . $pixel .'" width="1" height="1" alt="" ></img></div>';
    }
  }
  return $content;

}

/**
 * 
 * Adds a box to the main column on the Post and Page edit screens
 * @param: none
 *
 */

function wpTomServiceAddCustomMeta() {
  add_meta_box( 'VGWortCustomMeta', __( 'VG Wort', 'VG Wort' ),  'createVGWortCustomMeta' , 'post' , 'advanced','high' );
}

/**
 * displays the metabox in Posts and pages
 * @param: object $post
 */
function createVGWortCustomMeta( $post ) {
  // Use nonce for verification
  wp_nonce_field( plugin_basename(__FILE__) , PLUGINNAME );
  // The actual fields for data entry
  //
  try {
    //$fault = get_post_meta( $post->ID , 'tom_fault' , true );
    //var_dump($fault);
    echo '<div class="vgwort-meta postbox">';
    echo '<div class="clearfix">VGwort Author Info : ' . get_user_meta($post->post_author, 'last_name', true) .' : '. get_user_meta($post->post_author, 'wp_tommeta_auth', true) . '</div>';
    //echo "<span>VGwort Messages : " . //get_post_meta( $post->ID , 'tom_fault' , true) . "</span><br />";
    if (get_post_meta( $post->ID , 'tom_publicIdentificationId' , true ) ) {
      echo '<div class="clearfix">public: <input type="input" size="40" name="tom_publicIdentificationId" value="'. get_post_meta( $post->ID , 'tom_publicIdentificationId' , true ) .'" /></div>';
      echo '<div class="clearfix">private: <input type="input" size="40" name="tom_privateIdentificationId" value="'. get_post_meta( $post->ID , 'tom_privateIdentificationId' , true ) .'" /></div>';
    }
    echo '</div>';
  } catch (Exception $e) {
   echo 'Caught exception: ',  $e->getMessage(), "\n";
   error_log($e->getMessage(),0);
  }
}

/**
 * displays the metabox users
 * @param: object $user
 */
function createVGWortUserMeta( $user ) {
  // Use nonce for verification
  wp_nonce_field( plugin_basename(__FILE__) , PLUGINNAME );
  // The actual fields for data entry
  echo '<input type="input" size="150" name="wp_vgwortmarke_auth" value="'.get_user_meta( $user->ID , TOMMETA_AUTH , true ).'" /><br />';
  echo 'VG WORT Autoren Kartei Nummer';
}

/**
 *
 * save the values of VGWort Meta
 * @param: int $post_id
 * 
 * FIXME, we're not using this at the moment. 
 */

function wpTomServiceSavePost( $post_id ) {
  // Erweiterung bei Einstellungen 
  $available_post = array( 'page' , 'post' );
  // AutoSave Methode
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ){ 
    return;
  }
  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times
  if ( !wp_verify_nonce( $_POST[PLUGINNAME], plugin_basename( __FILE__ ) ) )
    return;
  // Check permissions
  if ( in_array($_POST['post_type'],$available_post)) {
    if ( !current_user_can( 'edit_page', $post_id ) )
      return;
  }
  else{
    return;
  }
  if(!empty($_POST['tom_publicIdentificationId'])){
    //update_post_meta($post_id , 'tom_publicIdentificationId' , $_POST['tom_publicIdentificationId'] );
  }else{
    //delete_post_meta($post_id , 'tom_publicIdentificationId' , $_POST['tom_publicIdentificationId'] );
  }
  if(!empty($_POST['tom_privateIdentificationId'])){
    //update_post_meta($post_id , 'tom_privateIdentificationId' , $_POST['tom_privateIdentificationId'] );
  }else{
    //delete_post_meta($post_id , 'tom_privateIdentificationId' , $_POST['tom_privateIdentificationId'] );
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
  if ($cardNumber == '' || $cardNumber == NULL ) { 
    $error =  "sorry! $cardNumber not a valid cardNumber"; 
    return $error;
  } 
  // this is weird, get post meta  won't work reliably this context!
  // $fpriv = get_post_meta($post->ID , 'tom_privateIdentificationId', true) ;
  // $fpriv = $fpriv[0] ;
  $fpriv = $code;

  // fetched/set from wp-config.php
  $vgWortUserId = WORT_USER;
  $vgWortUserPassword = WORT_PASS;

  // get authors information: vg wort card number, first (max. 40 characters) and last name
  $authors = array('author'=>array());
  $authors['author'][] = array('cardNumber'=>$cardNumber, 'firstName'=>substr($givenName, 0, 39), 'surName'=>$surName);
  $parties = array('authors'=>$authors);

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
    error_log($client->__getLastRequest(),0);
    return $result;
    //wp_clear_scheduled_hook( 'service_submit_event', array( $post->ID, $fpriv ) );
  }
  catch (SoapFault $soapFault) {
    $detail = $soapFault->faultcode;
    error_log($soapFault,0);
    update_post_meta($post->ID , 'tom_fault',  $soapFault->detail);
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
 * Utility function.
 *
 * Fixme, currently not used
 */
function elapsed_time( $secs) { 
  $bit = array(
    'y' => $secs / 31556926 % 12,
    'w' => $secs / 604800 % 52,
    'd' => $secs / 86400 % 7,
    'h' => $secs / 3600 % 24,
    'm' => $secs / 60 % 60,
    's' => $secs % 60
  );
  foreach($bit as $k => $v)
    if($v > 0)$ret[] = $v . $k;
  return join(' ', $ret);
}

/*
 * $nowtime = time();
 * $oldtime = 1359939007;
 * echo elapsed_time($nowtime-$oldtime);
 **/



// end class
?>
