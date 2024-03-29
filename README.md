tomservice
==========

Tom Service, a wordpress plugin, provides Soap calls to the German VG Wort royalty collection service. Texte Online Melden. Development funded by newthinking.de for netzpolitik.org.

=== WP-TOMSERVICE ===

* Contributors: blueprint@poetaster.de
* Url: http://github.com/poetaster/tomservice
* Tags: VG-Wort, T.O.M., Zählpixel
* Requires at least: 3.0
* Tested up to: 6.0.1
* Stable tag: 5.70
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

* Many thanks to Marcus Franke http://www.internet-marketing-dresden.de for examples and feedback.
* Many thanks for examples/code  http://pkp.sfu.ca/wiki/index.php/VGWortPlugIn_Doku, Freie Universität Berlin, http://www.cedis.fu-berlin.de/ojs-de

WP_TOMSERVICE add the "VG Wort Zaehlpixel" to your posts/pages. Assign authors
their 'cardNumbers from VG Wort'. Register the text URL, content and authors.

The details below are in German since the rest of you probably dont care :)

== Description ==

VG Tomservice Plugin ist ein Plugin mit den man die VG Wort Zählmarken mit den
T.O.M. Soap apis in einen Beitrag einfügen kann. Die Karteinummern der Autoren
sind einstellbar auf den Profilseiten. Auf der Settings Seite des Plugins kann
man anmeldungen (5 bei jedem submit) durchfuehren.

Folgende Funktionen bietet das Plugin

- Zählung der Zeichen inkl. Leerzeichen im Beitrag
- (Besonderheit) Shortcodes, Bilder und HTML Elemente werden nicht eingerechnet
- Einfügen einer VG Wort Zählmarke im Beitrag
- Übersicht in der Beitragsübersicht ob VG Wort Zählmarken eingebunden sind
- Übersicht im Profil in welchen Beiträgen VG Wort Zählmarken noch fehlen, welche die Bedingungen von VG Wort erfüllen
- Erstellen des Autoren 'cardNumber' (Karteinummer) Feldes im Autoren Profil
- Anmelden der Artikel (5 Tage mindest Alter) auf der Settings Seite
- Mittels Funktion Meldungen senden (bzw. cron) mit WP: sudo -u www wp eval 'wpTomServiceCLI();'
- Abruf der Meldungen per Nutzer (liste der letzten 100 Meldungen)
- Artikel mit mehrer Autoren werden auch mit dennen gemeldet. Benoetigt: https://de.wordpress.org/plugins/additional-authors/ aber modifiziert.


== Installation ==

* Entpacke das Archiv ins Wordpress Plugin Verzeichnis (wp-content/plugins/)
* Aktiviere das Plugin im Wordpress Backend.
* Erstelle folgende Zeilen im wp-config.php 
* - define(WORT_USER, 'vgWortVerlegerKonto');
* - define(WORT_PASS, 'passwort');
* - define(WORT_KARTEI, 'vgwortverlegerkartei');
* Erstelle die 'cardNumber' (Karteinummer) fuer mindestens ein Autor
* Richte einen cron job ein um Anmeldungen automatisch durchzufuehren:
    1 0 * * * cd /var/www/wp-root/;  /usr/local/bin/wp --allow-root eval 'wpTomServiceCLI();'  >> /var/log/wp-cron.log 2>&1

* Fertig, nun befindet sich in Beiträgen die von 'In Bearbeitung' auf 'Veroeffentlicht' geschaltet werden die Pixelcode img tags

== Frequently Asked Questions ==

Diskussion https://github.com/poetaster/tomservice/issues

== Screenshots ==

- no screenshots available

== NO WARRANTY ==

BECAUSE THE PROGRAM IS LICENSED FREE OF CHARGE, THERE IS NO WARRANTY FOR THE PROGRAM, TO THE EXTENT PERMITTED BY APPLICABLE LAW. EXCEPT WHEN OTHERWISE STATED IN WRITING THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY OF ANY KIND, EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE. THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

IN NO EVENT UNLESS REQUIRED BY APPLICABLE LAW OR AGREED TO IN WRITING WILL ANY COPYRIGHT HOLDER, OR ANY OTHER PARTY WHO MAY MODIFY AND/OR REDISTRIBUTE THE PROGRAM AS PERMITTED ABOVE, BE LIABLE TO YOU FOR DAMAGES, INCLUDING ANY GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE USE OR INABILITY TO USE THE PROGRAM (INCLUDING BUT NOT LIMITED TO LOSS OF DATA OR DATA BEING RENDERED INACCURATE OR LOSSES SUSTAINED BY YOU OR THIRD PARTIES OR A FAILURE OF THE PROGRAM TO OPERATE WITH ANY OTHER PROGRAMS), EVEN IF SUCH HOLDER OR OTHER PARTY HAS BEEN ADVISED OF THE POSSIBILITY OF SUCH DAMAGES.

== Changelog ==

= 5.7 = 

* move to version 2.0 of message service
 add required fields for publisheer to collect on 'meldung'

= 5.6 = 

* functional multi-author support, 
* admin menu elements broken out to a separate source file. 
* added the ability to list texts that succeeded in winding up in the tom db.

= 5.3 = working toward multiple author support

= 5.1 = debugging planned publishing.

= 5.0 =
* test of wp 5.0 and additional function for cron

= 3.5.b1 =
* start of Plugin

== NO WARRANTY ==
