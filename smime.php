<?php
/**
 * S/MIME utilities.
 *
 * Copyright 2002-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Mike Cochrane <mike@graftonhall.co.nz>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */

require_once dirname(__FILE__) . '/lib/Application.php';
Horde_Registry::appInit('imp');

$imp_smime = $injector->getInstance('IMP_Crypt_Smime');
$vars = Horde_Variables::getDefaultVariables();

/* Run through the action handlers */
switch ($vars->actionID) {
case 'import_public_key':
    $imp_smime->importKeyDialog('process_import_public_key', $vars->reload);
    exit;

case 'process_import_public_key':
    try {
        $publicKey = $imp_smime->getImportKey($vars->import_key);

        /* Add the public key to the storage system. */
        $imp_smime->addPublicKey($publicKey);
        $notification->push(_("S/MIME Public Key successfully added."), 'horde.success');
        $imp_smime->reloadWindow($vars->reload);
    } catch (Horde_Browser_Exception $e) {
        $notification->push(_("No S/MIME public key imported."), 'horde.error');
        throw new IMP_Exception($e);
    } catch (Horde_Exception $e) {
        $notification->push($e);
        $vars->actionID = 'import_public_key';
        $imp_smime->importKeyDialog('process_import_public_key', $vars->reload);
    }
    exit;

case 'view_public_key':
case 'info_public_key':
    try {
        $key = $imp_smime->getPublicKey($vars->email);
    } catch (Horde_Exception $e) {
        $key = $e->getMessage();
    }
    if ($vars->actionID == 'view_public_key') {
        $imp_smime->textWindowOutput('S/MIME Public Key', $key);
    } else {
        $imp_smime->printCertInfo($key);
    }
    exit;

case 'view_personal_public_key':
    $imp_smime->textWindowOutput('S/MIME Personal Public Key', $imp_smime->getPersonalPublicKey());
    exit;

case 'info_personal_public_key':
    $imp_smime->printCertInfo($imp_smime->getPersonalPublicKey());
    exit;

case 'view_personal_private_key':
    $imp_smime->textWindowOutput('S/MIME Personal Private Key', $imp_smime->getPersonalPrivateKey());
    exit;

case 'import_personal_certs':
    $imp_smime->importKeyDialog('process_import_personal_certs', $vars->reload);
    exit;

case 'process_import_personal_certs':
    try {
        $pkcs12 = $imp_smime->getImportKey($vars->import_key);
        $imp_smime->addFromPKCS12($pkcs12, $vars->upload_key_pass, $vars->upload_key_pk_pass);
        $notification->push(_("S/MIME Public/Private Keypair successfully added."), 'horde.success');
        $imp_smime->reloadWindow($vars->reload);
    } catch (Horde_Browser_Exception $e) {
        throw new IMP_Exception($e);
    } catch (Horde_Exception $e) {
        $notification->push(_("Personal S/MIME certificates NOT imported: ") . $e->getMessage(), 'horde.error');
        $vars->actionID = 'import_personal_certs';
        $imp_smime->importKeyDialog('process_import_personal_certs', $vars->reload);
    }
    exit;

case 'save_attachment_public_key':
    /* Retrieve the key from the message. */
    $contents = $injector->getInstance('IMP_Contents')->getOb(new IMP_Indices($vars->mailbox, $vars->uid));
    $mime_part = $contents->getMIMEPart($vars->mime_id);
    if (empty($mime_part)) {
        throw new IMP_Exception('Cannot retrieve public key from message.');
    }

    /* Add the public key to the storage system. */
    try {
        $imp_smime->addPublicKey($mime_part);
        echo Horde::wrapInlineScript(array('window.close();'));
    } catch (Horde_Exception $e) {
        $notification->push(_("No Certificate found"), 'horde.error');
    }
    exit;
}
