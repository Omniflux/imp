#!/usr/bin/php
<?php
/**
 * This script bounces a message back to the sender and can be used with IMP's
 * spam reporting feature to bounce spam.
 *
 * It takes the orginal message from standard input and requires the bounce
 * message in the file imp/config/bounce.txt. Important: the bounce message
 * must be a complete message including headers!
 *
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */

require_once dirname(__FILE__) . '/../lib/Application.php';
Horde_Registry::appInit('imp', array(
    'authentication' => false,
    'cli' => true
));

/** Configuration **/

/**
 * Location of the bounce template.
 * The following strings will be replaced in the template:
 *   %TO%     - The spammer's e-mail address.
 *   %TARGET% - The target's e-mail address.
 */
$bounce_template = IMP_BASE . '/config/bounce.txt';

/** End Configuration **/

/* If there's no bounce template file then abort */
if (!file_exists($bounce_template)) {
    $cli->fatal('Bounce template does not exist.');
}

/* Read the message content. */
$data = $cli->readStdin();

/* Who's the spammer? */
$headers = Horde_Mime_Headers::parseHeaders($data);
$return_path = Horde_Mime_Address::bareAddress($headers->getValue('return-path'));

/* Who's the target? */
$delivered_to = Horde_Mime_Address::bareAddress($headers->getValue('delivered-to'));

/* Read the bounce template and construct the mail */
$bounce = str_replace(
    array('%TO%', '%TARGET%'),
    array($return_path, $delivered_to),
    file_get_contents($bounce_template)
);

/* Send the mail */
$sendmail = "/usr/sbin/sendmail -t -f ''";
$fd = popen($sendmail, 'w');
fputs($fd, preg_replace("/\n$/", "\r\n", $bounce . $data));
pclose($fd);
