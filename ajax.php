<?php
/**
 * Performs the AJAX-requested action.
 *
 * Copyright 2005-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */

function _generateDeleteResult($mbox, $indices, $change, $nothread = false)
{
    $imp_mailbox = IMP_Mailbox::singleton($mbox);

    $result = new stdClass;
    $result->folder = $mbox;
    $result->uids = $GLOBALS['imp_imap']->ob->utils->toSequenceString($indices, array('mailbox' => true));
    $result->remove = intval($GLOBALS['prefs']->getValue('hide_deleted') ||
                             $GLOBALS['prefs']->getValue('use_trash'));
    $result->cacheid = $imp_mailbox->getCacheID($mbox);

    /* Check if we need to update thread information. */
    if (!$change && !$nothread) {
        $sort = IMP::getSort($mbox);
        $change = ($sort['by'] == Horde_Imap_Client::SORT_THREAD);
    }

    if ($change) {
        $result->ViewPort = _getListMessages($mbox, true);
    }

    $poll = _getPollInformation($mbox);
    if (!empty($poll)) {
        $result->poll = $poll;
    }

    return $result;
}

function _changed($mbox, $compare, $rw = null)
{
    if ($GLOBALS['imp_search']->isVFolder($mbox)) {
        return true;
    }

    /* We know we are going to be dealing with this mailbox, so select it on
     * the IMAP server (saves some STATUS calls). */
    if (!is_null($rw) && !$GLOBALS['imp_search']->isSearchMbox($mbox)) {
        try {
            $GLOBALS['imp_imap']->ob->openMailbox($mbox, $rw ? Horde_Imap_Client::OPEN_READWRITE : Horde_Imap_Client::OPEN_AUTO);
        } catch (Horde_Imap_Client_Exception $e) {
            return false;
        }
    }

    $imp_mailbox = IMP_Mailbox::singleton($mbox);
    if ($imp_mailbox->getCacheID($mbox) != $compare) {
        return true;
    }

    return false;
}

function _getListMessages($mbox, $change)
{
    $args = array(
        'applyfilter' => Horde_Util::getPost('applyfilter'),
        'cached' => Horde_Util::getPost('cached'),
        'cacheid' => Horde_Util::getPost('cacheid'),
        'initial' => Horde_Util::getPost('initial'),
        'mbox' => $mbox,
        'rangeslice' => Horde_Util::getPost('rangeslice'),
        'qsearch' => Horde_Util::getPost('qsearch'),
        'qsearchmbox' => Horde_Util::getPost('qsearchmbox'),
    );

    $search = Horde_Util::getPost('search');

    if (!empty($search) || $args['initial']) {
        $args += array(
            'search_before' => intval(Horde_Util::getPost('search_before')),
            'search_after' => intval(Horde_Util::getPost('search_after'))
        );
    }

    if (empty($search)) {
        list($slice_start, $slice_end) = explode(':', Horde_Util::getPost('slice'), 2);
        $args += array(
            'slice_start' => intval($slice_start),
            'slice_end' => intval($slice_end)
        );
    } else {
        $search = Horde_Serialize::unserialize($search, Horde_Serialize::JSON);
        $args += array(
            'search_uid' => $search->imapuid,
            'search_view' => $search->view,
        );
    }

    $list_msg = new IMP_Views_ListMessages();
    $res = $list_msg->listMessages($args);

    // TODO: This can potentially be optimized for arrival time sort - if the
    // cache ID changes, we know the changes must occur at end of mailbox.
    if (empty($res->reset) && (Horde_Util::getPost('purge') || $change)) {
        $res->update = 1;
    }

    $req_id = Horde_Util::getPost('request_id');
    if (!is_null($req_id)) {
        $res->request_id = intval($req_id);
    }

    return $res;
}

function _getIdxString($indices)
{
    $i = each($indices);
    return reset($i['value']) . IMP::IDX_SEP . $i['key'];
}

function _getPollInformation($mbox)
{
    $imptree = IMP_Imap_Tree::singleton();
    $elt = $imptree->get($mbox);
    if ($imptree->isPolled($elt)) {
        $info = $imptree->getElementInfo($mbox);
        return array($mbox => isset($info['unseen']) ? intval($info['unseen']) : 0);
    }
    return array();
}

function _getQuota()
{
    if (isset($_SESSION['imp']['quota']) &&
        is_array($_SESSION['imp']['quota'])) {
        $quotadata = IMP::quotaData(false);
        if (!empty($quotadata)) {
            return array('p' => round($quotadata['percent']), 'm' => $quotadata['message']);
        }
    }

    return null;
}

// Need to load Horde_Util:: to give us access to Horde_Util::getPathInfo().
require_once dirname(__FILE__) . '/lib/base.load.php';
require_once HORDE_BASE . '/lib/core.php';
$action = basename(Horde_Util::getPathInfo());
if (empty($action)) {
    // This is the only case where we really don't return anything, since
    // the frontend can be presumed not to make this request on purpose.
    // Other missing data cases we return a response of boolean false.
    exit;
}

// The following actions do not need write access to the session and
// should be opened read-only for performance reasons.
if (in_array($action, array('chunkContent', 'Html2Text', 'Text2Html', 'GetReplyData', 'FetchmailDialog'))) {
    $imp_session_control = 'readonly';
}

$imp_dimp_logout = ($action == 'LogOut');
$session_timeout = 'json';
require_once IMP_BASE . '/lib/base.php';

// Process common request variables.
$mbox = Horde_Util::getPost('view');
$indices = $imp_imap->ob->utils->fromSequenceString(Horde_Util::getPost('uid'));
$cacheid = Horde_Util::getPost('cacheid');

// Open an output buffer to ensure that we catch errors that might break JSON
// encoding.
ob_start();

$notify = true;
$result = false;

switch ($action) {
case 'CreateFolder':
    if (empty($mbox)) {
        break;
    }

    $imptree = IMP_Imap_Tree::singleton();
    $imptree->eltDiffStart();

    $imp_folder = IMP_Folder::singleton();

    $new = Horde_String::convertCharset($mbox, Horde_Nls::getCharset(), 'UTF7-IMAP');
    try {
        $new = $imptree->createMailboxName(Horde_Util::getPost('parent'), $new);
        $result = $imp_folder->create($new, $prefs->getValue('subscribe'));
        if ($result) {
            $result = DIMP::getFolderResponse($imptree);
        }
    } catch (Horde_Exception $e) {
        $notification->push($e, 'horde.error');
        $result = false;
    }
    break;

case 'DeleteFolder':
    if (empty($mbox)) {
        break;
    }

    $imptree = IMP_Imap_Tree::singleton();
    $imptree->eltDiffStart();

    $imp_folder = IMP_Folder::singleton();
    $result = $imp_folder->delete(array($mbox));
    if ($result) {
        $result = DIMP::getFolderResponse($imptree);
    }
    break;

case 'RenameFolder':
    $old = Horde_Util::getPost('old_name');
    $new_parent = Horde_Util::getPost('new_parent');
    $new = Horde_Util::getPost('new_name');
    if (!$old || !$new) {
        break;
    }

    $imptree = IMP_Imap_Tree::singleton();
    $imptree->eltDiffStart();

    $imp_folder = IMP_Folder::singleton();

    try {
        $new = $imptree->createMailboxName($new_parent, $new);

        $new = Horde_String::convertCharset($new, Horde_Nls::getCharset(), 'UTF7-IMAP');
        if ($old != $new) {
            $result = $imp_folder->rename($old, $new);
            if ($result) {
                $result = DIMP::getFolderResponse($imptree);
            }
        }
    } catch (Horde_Exception $e) {
        $notification->push($e, 'horde.error');
        $result = false;
    }
    break;

case 'EmptyFolder':
    if (empty($mbox)) {
        break;
    }

    $imp_message = IMP_Message::singleton();
    $imp_message->emptyMailbox(array($mbox));
    $result = new stdClass;
    $result->mbox = $mbox;
    break;

case 'FlagAll':
    $flags = Horde_Serialize::unserialize(Horde_Util::getPost('flags'), Horde_Serialize::JSON);
    if (empty($mbox) || empty($flags)) {
        break;
    }

    $set = Horde_Util::getPost('set');

    $imp_message = IMP_Message::singleton();
    $result = $imp_message->flagAllInMailbox($flags, array($mbox), $set);

    if ($result) {
        $result = new stdClass;
        $result->flags = $flags;
        $result->mbox = $mbox;
        if ($set) {
            $result->set = 1;
        }

        $poll = _getPollInformation($mbox);
        if (!empty($poll)) {
            $result->poll = array($mbox => $poll[$mbox]);
        }
    }
    break;

case 'ListFolders':
    $imptree = IMP_Imap_Tree::singleton();
    $mask = IMP_Imap_Tree::FLIST_CONTAINER | IMP_Imap_Tree::FLIST_VFOLDER | IMP_Imap_Tree::FLIST_ELT;
    if (Horde_Util::getPost('unsub')) {
        $mask |= IMP_Imap_Tree::FLIST_UNSUB;
    }
    $result = DIMP::getFolderResponse($imptree, array('a' => $imptree->folderList($mask), 'c' => array(), 'd' => array()));

    $quota = _getQuota();
    if (!is_null($quota)) {
        $result['quota'] = $quota;
    }
    break;

case 'Poll':
    $result = new stdClass;

    $imptree = IMP_Imap_Tree::singleton();

    $result->poll = array();
    foreach ($imptree->getPollList(true) as $val) {
        if ($info = $imptree->getElementInfo($val)) {
            $result->poll[$val] = intval($info['unseen']);
        }
    }

    if (!empty($mbox) && _changed($mbox, $cacheid)) {
        $result->ViewPort = _getListMessages($mbox, true);
    }

    $quota = _getQuota();
    if (!is_null($quota)) {
        $result->quota = $quota;
    }
    break;

case 'Subscribe':
    if ($prefs->getValue('subscribe')) {
        $imp_folder = IMP_Folder::singleton();
        $result = Horde_Util::getPost('sub')
            ? $imp_folder->subscribe(array($mbox))
            : $imp_folder->unsubscribe(array($mbox));
    }
    break;

case 'ViewPort':
    if (empty($mbox)) {
        break;
    }

    /* Change sort preferences if necessary. */
    $sortby = Horde_Util::getPost('sortby');
    $sortdir = Horde_Util::getPost('sortdir');
    if (!is_null($sortby) || !is_null($sortdir)) {
        IMP::setSort($sortby, $sortdir, $mbox);
    }

    $result = new stdClass;
    $changed = _changed($mbox, $cacheid, false);

    if (Horde_Util::getPost('rangeslice') ||
        !Horde_Util::getPost('checkcache') ||
        $changed) {
        $result->ViewPort = _getListMessages($mbox, $changed);
    }
    break;

case 'MoveMessage':
case 'CopyMessage':
    $to = Horde_Util::getPost('tofld');
    if (!$to || empty($indices)) {
        break;
    }

    if ($action == 'MoveMessage') {
        $change = _changed($mbox, $cacheid, true);
    }

    $imp_message = IMP_Message::singleton();

    $result = $imp_message->copy($to, ($action == 'MoveMessage') ? 'move' : 'copy', $indices);

    if ($result) {
        if ($action == 'MoveMessage') {
            $result = _generateDeleteResult($mbox, $indices, $change);
            // Need to manually set remove to true since we want to remove
            // message from the list no matter the current pref settings.
            $result->remove = 1;
        }

        // Update poll information for destination folder if necessary.
        // Poll information for current folder will be added by
        // _generateDeleteResult() call above.
        $poll = _getPollInformation($to);
        if (!empty($poll)) {
            if (!isset($result->poll)) {
                $result->poll = array();
            }
            $result->poll = array_merge($result->poll, $poll);
        }
    }
    break;

case 'FlagMessage':
    $flags = Horde_Util::getPost('flags');
    if (!$flags || empty($indices)) {
        break;
    }
    $flags = Horde_Serialize::unserialize($flags, Horde_Serialize::JSON);

    $set = $notset = array();
    foreach ($flags as $val) {
        if ($val[0] == '-') {
            $notset[] = substr($val, 1);
        } else {
            $set[] = $val;
        }
    }

    $imp_message = IMP_Message::singleton();
    if (!empty($set)) {
        $result = $imp_message->flag($set, $indices, true);
    }
    if (!empty($notset)) {
        $result = $imp_message->flag($notset, $indices, false);
    }

    if ($result) {
        $result = new stdClass;
    }
    break;

case 'DeleteMessage':
    if (empty($indices)) {
        break;
    }

    $imp_message = IMP_Message::singleton();
    $change = _changed($mbox, $cacheid, true);
    $result = $imp_message->delete($indices);
    if ($result) {
        $result = _generateDeleteResult($mbox, $indices, $change, !$prefs->getValue('hide_deleted') && !$prefs->getValue('use_trash'));
    }
    break;

case 'AddContact':
    $email = Horde_Util::getPost('email');
    $name = Horde_Util::getPost('name');
    // Allow $name to be empty.
    if (empty($email)) {
        break;
    }

    try {
        IMP::addAddress($email, $name);
        $result = true;
        $notification->push(sprintf(_("%s was successfully added to your address book."), $name ? $name : $email), 'horde.success');
    } catch (Horde_Exception $e) {
        $notification->push($e, 'horde.error');
        $result = false;
    }
    break;

case 'ReportSpam':
    $change = _changed($mbox, $cacheid, false);
    $spam_result = IMP_Spam::reportSpam($indices, Horde_Util::getPost('spam') ? 'spam' : 'notspam');
    if ($spam_result) {
        $result = _generateDeleteResult($mbox, $indices, $change);
        // If $spam_result is non-zero, then we know the message has been
        // removed from the current mailbox.
        $result->remove = 1;
    }
    break;

case 'Blacklist':
    if (empty($indices)) {
        break;
    }

    $imp_filter = new IMP_Filter();
    if (Horde_Util::getPost('blacklist')) {
        $change = _changed($mbox, $cacheid, false);
        if ($imp_filter->blacklistMessage($indices, false)) {
            $result = _generateDeleteResult($mbox, $indices, $change);
        }
    } else {
        $imp_filter->whitelistMessage($indices, false);
    }
    break;

case 'ShowPreview':
    if (count($indices) != 1) {
        break;
    }

    $ptr = each($indices);
    $args = array(
        'index' => intval(reset($ptr['value'])),
        'mailbox' => $ptr['key'],
        'preview' => true,
    );

    $show_msg = new IMP_Views_ShowMessage();
    $result = (object)$show_msg->showMessage($args);
    break;

case 'Html2Text':
    $result = new stdClass;
    // Need to replace line endings or else IE won't display line endings
    // properly.
    $result->text = str_replace("\n", "\r\n", Horde_Text_Filter::filter(Horde_Util::getPost('text'), 'html2text'));
    break;

case 'Text2Html':
    $result = new stdClass;
    $result->text = Horde_Text_Filter::filter(Horde_Util::getPost('text'), 'text2html', array('parselevel' => Horde_Text_Filter_Text2html::MICRO_LINKURL, 'class' => null, 'callback' => null));
    break;

case 'GetForwardData':
    $header = array();
    $msg = $header = null;
    $idx_string = _getIdxString($indices);

    $imp_compose = IMP_Compose::singleton(Horde_Util::getPost('imp_compose'));
    $imp_contents = IMP_Contents::singleton($idx_string);
    $imp_ui = new IMP_UI_Compose();
    $fwd_msg = $imp_ui->getForwardData($imp_compose, $imp_contents, $idx_string);
    $header = $fwd_msg['headers'];
    $header['replytype'] = 'forward';

    $result = new stdClass;
    // Can't open read-only since we need to store the message cache id.
    $result->imp_compose = $imp_compose->getCacheId();
    $result->fwd_list = DIMP::getAttachmentInfo($imp_compose);
    $result->body = $fwd_msg['body'];
    $result->header = $header;
    $result->format = $fwd_msg['format'];
    $result->identity = $fwd_msg['identity'];
    break;

case 'GetReplyData':
    $imp_compose = IMP_Compose::singleton(Horde_Util::getPost('imp_compose'));
    $imp_contents = IMP_Contents::singleton(_getIdxString($indices));
    $reply_msg = $imp_compose->replyMessage(Horde_Util::getPost('type'), $imp_contents);
    $header = $reply_msg['headers'];
    $header['replytype'] = 'reply';

    $result = new stdClass;
    $result->format = $reply_msg['format'];
    $result->body = $reply_msg['body'];
    $result->header = $header;
    $result->identity = $reply_msg['identity'];
    break;

case 'CancelCompose':
case 'DeleteDraft':
    $imp_compose = IMP_Compose::singleton(Horde_Util::getPost('imp_compose'));
    $imp_compose->destroy();
    if ($action == 'DeleteDraft') {
        $imp_message = IMP_Message::singleton();
        $idx_array = array($imp_compose->getMetadata('draft_index') . IMP::IDX_SEP . IMP::folderPref($prefs->getValue('drafts_folder'), true));
        $imp_message->delete($idx_array, array('nuke' => true));
    }
    $result = true;
    break;

case 'DeleteAttach':
    $atc = Horde_Util::getPost('atc_indices');
    if (!is_null($atc)) {
        $imp_compose = IMP_Compose::singleton(Horde_Util::getPost('imp_compose'));
        foreach ($imp_compose->deleteAttachment($atc) as $val) {
            $notification->push(sprintf(_("Deleted the attachment \"%s\"."), Horde_Mime::decode($val)), 'horde.success');
        }
    }
    break;

case 'ShowPortal':
    // Load the block list. Blocks are located in $dimp_block_list.
    // KEY: Block label; VALUE: Horde_Block object
    require IMP_BASE . '/config/portal.php';

    $blocks = $linkTags = array();
    $css_load = array('imp' => true);
    foreach ($dimp_block_list as $title => $block) {
        if ($block['ob'] instanceof Horde_Block) {
            $app = $block['ob']->getApp();
            $content = (empty($css_load[$app]) ? Horde::styleSheetLink($app, '', false) : '') . $block['ob']->getContent();
            $css_load[$app] = true;
            // Don't do substitutions on our own blocks.
            if ($app != 'imp') {
                $content = preg_replace('/<a href="([^"]+)"/',
                                        '<a onclick="DimpBase.go(\'app:' . $app . '\', \'$1\');return false"',
                                        $content);
                if (preg_match_all('/<link .*?rel="stylesheet".*?\/>/',
                                   $content, $links)) {
                    $content = str_replace($links[0], '', $content);
                    foreach ($links[0] as $link) {
                        if (preg_match('/href="(.*?)"/', $link, $href)) {
                            $linkOb = new stdClass;
                            $linkOb->href = $href[1];
                            if (preg_match('/media="(.*?)"/', $link, $media)) {
                                $linkOb->media = $media[1];
                            }
                            $linkTags[] = $linkOb;
                        }
                    }
                }
            }
            if (!empty($content)) {
                $entry = array(
                    'app' => $app,
                    'content' => $content,
                    'title' => $title,
                    'class' => empty($block['class']) ? 'headerbox' : $block['class'],
                );
                if (!empty($block['domid'])) {
                    $entry['domid'] = $block['domid'];
                }
                if (!empty($block['tag'])) {
                    $entry[$block['tag']] = true;
                }
                $blocks[] = $entry;
            }
        }
    }

    $result = new stdClass;
    $result->portal = '';
    if (!empty($blocks)) {
        $t = new Horde_Template(IMP_TEMPLATES . '/imp/');
        $t->set('block', $blocks);
        $result->portal = $t->fetch('portal.html');
    }
    $result->linkTags = $linkTags;
    break;

case 'chunkContent':
    $chunk = basename(Horde_Util::getPost('chunk'));
    if (!empty($chunk)) {
        $result = new stdClass;
        $result->chunk = Horde_Util::bufferOutput('include', IMP_TEMPLATES . '/chunks/' . $chunk . '.php');
    }
    break;

case 'PurgeDeleted':
    $change = _changed($mbox, $cacheid, $indices);
    if (!$change) {
        $sort = IMP::getSort($mbox);
        $change = ($sort['by'] == Horde_Imap_Client::SORT_THREAD);
    }
    $imp_message = IMP_Message::singleton();
    $expunged = $imp_message->expungeMailbox(array($mbox => 1), array('list' => true));
    if (!empty($expunged[$mbox])) {
        $expunge_count = count($expunged[$mbox]);
        $display_folder = IMP::displayFolder($mbox);
        if ($expunge_count == 1) {
            $notification->push(sprintf(_("1 message was purged from \"%s\"."), $display_folder), 'horde.success');
        } else {
            $notification->push(sprintf(_("%s messages were purged from \"%s\"."), $expunge_count, $display_folder), 'horde.success');
        }
        $result = _generateDeleteResult($mbox, $expunged, $change);
        // Need to manually set remove to true since we want to remove
        // message from the list no matter the current pref settings.
        $result->remove = 1;
    }
    break;

case 'ModifyPollFolder':
    if (empty($mbox)) {
        break;
    }

    $add = Horde_Util::getPost('add');
    $display_folder = IMP::displayFolder($mbox);

    $imptree = IMP_Imap_Tree::singleton();

    $result = new stdClass;
    $result->add = (bool)$add;
    $result->folder = $mbox;

    if ($add) {
        $imptree->addPollList($mbox);
        if ($info = $imptree->getElementInfo($mbox)) {
            $result->poll = array($mbox => $info['unseen']);
        }
        $notification->push(sprintf(_("\"%s\" mailbox now polled for new mail."), $display_folder), 'horde.success');
    } else {
        $imptree->removePollList($mbox);
        $notification->push(sprintf(_("\"%s\" mailbox no longer polled for new mail."), $display_folder), 'horde.success');
    }
    break;

case 'SendMDN':
    $index = Horde_Util::getPost('index');
    if (empty($mbox) || empty($index)) {
        break;
    }

    /* Get the IMP_Headers:: object. */
    try {
        $fetch_ret = $imp_imap->ob->fetch($mbox, array(
            Horde_Imap_Client::FETCH_HEADERTEXT => array(array('parse' => true, 'peek' => false))
        ), array('ids' => array($index)));
    } catch (Horde_Imap_Client_Exception $e) {
        break;
    }

    $imp_ui = new IMP_UI_Message();
    $imp_ui->MDNCheck($mbox, $index, $reset($fetch_ret[$index]['headertext']), true);
    break;

case 'PGPSymmetric':
case 'PGPPersonal':
case 'SMIMEPersonal':
    $result = new stdClass;
    $result->success = false;

    $passphrase = Horde_Util::getFormData('dialog_input');

    if ($action == 'SMIMEPersonal') {
        $imp_smime = Horde_Crypt::singleton(array('IMP', 'Smime'));
        try {
            Horde::requireSecureConnection();
            if ($passphrase) {
                if ($imp_smime->storePassphrase($passphrase)) {
                    $result->success = 1;
                } else {
                    $result->error = _("Invalid passphrase entered.");
                }
            } else {
                $result->error = _("No passphrase entered.");
            }
        } catch (Horde_Exception $e) {
            $result->error = $e->getMessage();
        }
    } else {
        $imp_pgp = Horde_Crypt::singleton(array('IMP', 'Pgp'));
        try {
            Horde::requireSecureConnection();
            if ($passphrase) {
                if ($imp_pgp->storePassphrase(($action == 'PGPSymmetric') ? 'symmetric' : 'personal', $passphrase, Horde_Util::getFormData('symmetricid'))) {
                    $result->success = 1;
                } else {
                    $result->error = _("Invalid passphrase entered.");
                }
            } else {
                $result->error = _("No passphrase entered.");
            }
        } catch (Horde_Exception $e) {
            $result->error = $e->getMessage();
        }
    }

    if ($_SESSION['imp']['view'] != 'dimp') {
        $notify = false;
    }

    break;

case 'Fetchmail':
    $fetch_list = Horde_Util::getFormData('accounts');
    if (empty($fetch_list)) {
        $result->error = _("No accounts selected.");
    } else {
        IMP_Fetchmail::fetchmail($fetch_list);
        $result->success = 1;
    }

    /* Don't send dimp notifications via this response since the listener
     * on the browser (dialog.js) doesn't know what to do with them. Instead,
     * notifications will be picked up via the PollFolders() call that is
     * done on success. */
    $notify = false;

    break;

case 'FetchmailDialog':
    $result = IMP_Fetchmail::fetchmailDialogForm();
    $notify = false;
    break;
}

// Clear the output buffer that we started above, and log any unexpected
// output at a DEBUG level.
$errors = ob_get_clean();
if ($errors) {
    Horde::logMessage('DIMP: unexpected output: ' .
                      $errors, __FILE__, __LINE__, PEAR_LOG_DEBUG);
}

// Send the final result.
Horde::sendHTTPResponse(Horde::prepareResponse($result, $notify ? $GLOBALS['imp_notify'] : null), 'json');
