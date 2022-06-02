<?php

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

  if(isset($_POST[ 'save' ]) && isset( $_POST['tomsettings']) && wp_verify_nonce( $_POST['tomsettings'], 'wpTomServiceSettings' )){
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
                    <?php wp_nonce_field( 'wpTomServiceSettings', 'tomsettings' ); ?>
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
 * wpTomServiceRequestPage
 * takes a string and requests information from TOM about titles that match.
 *
 */

function wpTomServiceRequestPage() {
  echo '<div class="wrap"><h2>T.O.M. Meldungssuche</h2><p>Karteinummber angeben und suchen</p>';

    if(isset($_POST[ 'research' ]) && isset( $_POST['tomrequest']) && wp_verify_nonce( $_POST['tomrequest'], 'wpTomServiceRequest' )){
      $metisMessage = researchMetisMessages($_POST['cardNumber']);

      foreach($metisMessage['ResearchedMetisMessage'] as $row){
        echo '<span>' . $row->title . '</span>, <span>' . $row->createdDate . '</span><br>';
        echo '<span>&nbsp;&nbsp;-- Autoren: ';
        foreach($row->parties->authors->author as $author){
          echo  $author->surName . '&nbsp; &nbsp;';
        }
        echo '<br>';
      }
    }
?>
      <form method="POST" action="">
        <input type="text" name="cardNumber" value="" class="input" / >
        <?php wp_nonce_field( 'wpTomServiceRequest', 'tomrequest' ); ?>
        <input type="submit" name="research" value="Bei T.O.M Suchen" class="button-primary" / >
      </form>
<?php
  echo '</div>';

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

  if(!empty( $pixel )){
      $content .= '<span class="vgwort"><img src="https://'. $domain . '/na/' . $pixel .'" width="1" height="1" alt="" /></span>';
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

// end class
?>
