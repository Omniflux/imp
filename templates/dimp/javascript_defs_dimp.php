<?php
/**
 * DIMP base JS file.
 *
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

$app_urls = $code = $flags = array();

foreach (IMP_Dimp::menuList() as $app) {
    $app_urls[$app] = (string) Horde::url($GLOBALS['registry']->getInitialPage($app), true);
}

include IMP_BASE . '/config/portal.php';
foreach ($dimp_block_list as $block) {
    if ($block['ob'] instanceof Horde_Block) {
        $app = $block['ob']->getApp();
        if (empty($app_urls[$app])) {
            $app_urls[$app] = (string) Horde::url($GLOBALS['registry']->getInitialPage($app), true);
        }
    }
}

/* Generate flag array. */
foreach ($GLOBALS['injector']->getInstance('IMP_Imap_Flags')->getList(array('fgcolor' => true)) as $val) {
    $flags[$val['flag']] = array_filter(array(
        'b' => isset($val['b']) ? $val['b'] : null,
        'c' => $val['c'],
        'f' => $val['f'],
        'l' => $val['l'],
        'n' => isset($val['n']) ? $val['n'] : null,
        // Indicate if this is a user *P*ref flag
        'p' => intval($val['t'] == 'imapp')
    ));
}

/* Variables used in core javascript files. */
$code['conf'] = array_filter(array(
    // URL variables
    'URI_AJAX' => (string) Horde::getServiceLink('ajax', 'imp'),
    'URI_COMPOSE' => (string) Horde::applicationUrl('compose-dimp.php'),
    'URI_DIMP' => (string) Horde::applicationUrl('index-dimp.php'),
    'URI_MESSAGE' => (string) Horde::applicationUrl('message-dimp.php'),
    'URI_PREFS' => (string) Horde::getServiceLink('prefsapi', 'imp'),
    'URI_PREFS_IMP' => str_replace('&amp;', '&', (string) Horde::getServiceLink('options', 'imp')),
    'URI_SEARCH' => (string) Horde::applicationUrl('search.php'),
    'URI_VIEW' => (string) Horde::applicationUrl('view.php'),

    'IDX_SEP' => IMP_Dimp::IDX_SEP,
    'SESSION_ID' => defined('SID') ? SID : '',

    // Other variables
    'app_urls' => $app_urls,
    'buffer_pages' => intval($GLOBALS['conf']['dimp']['viewport']['buffer_pages']),
    'disable_compose' => !IMP::canCompose(),
    'filter_any' => intval($GLOBALS['prefs']->getValue('filter_any_mailbox')),
    'fixed_folders' => empty($GLOBALS['conf']['server']['fixed_folders'])
        ? array()
        : array_map(array('IMP_Dimp', 'appendedFolderPref'), $GLOBALS['conf']['server']['fixed_folders']),
    'flags' => $flags,
    'fsearchid' => IMP_Search::MBOX_PREFIX . IMP_Search::DIMP_FILTERSEARCH,
    'ham_spammbox' => intval(!empty($GLOBALS['conf']['notspam']['spamfolder'])),
    'login_view' => $GLOBALS['prefs']->getValue('dimp_login_view'),
    'name' => $GLOBALS['registry']->get('name', 'imp'),
    'popup_height' => 610,
    'popup_width' => 820,
    'preview_pref' => ($GLOBALS['prefs']->getValue('dimp_show_preview') ? $GLOBALS['prefs']->getValue('dimp_show_preview') : 'horiz'),
    'qsearchid' => IMP_Search::MBOX_PREFIX . IMP_Search::DIMP_QUICKSEARCH,
    'qsearchfield' => $GLOBALS['prefs']->getValue('dimp_qsearch_field'),
    'refresh_time' => intval($GLOBALS['prefs']->getValue('refresh_time')),
    'searchprefix' => IMP_Search::MBOX_PREFIX,
    'sidebar_width' => max((int)$GLOBALS['prefs']->getValue('sidebar_width'), 150) . 'px',
    'sort' => array(
        'sequence' => array(
            't' => '',
            'v' => Horde_Imap_Client::SORT_SEQUENCE
        ),
        'from' => array(
            't' => _("From"),
            'v' => Horde_Imap_Client::SORT_FROM
        ),
        'to' => array(
            't' => _("To"),
            'v' => Horde_Imap_Client::SORT_TO
        ),
        'subject' => array(
            't' => _("Subject"),
            'v' => Horde_Imap_Client::SORT_SUBJECT
        ),
        'thread' => array(
            't' => _("Thread"),
            'v' => Horde_Imap_Client::SORT_THREAD
        ),
        'date' => array(
            't' => _("Date"),
            'v' => IMP::IMAP_SORT_DATE
        ),
        'size' => array(
            't' => _("Size"),
            'v' => Horde_Imap_Client::SORT_SIZE
        )
    ),
    'spam_mbox' => IMP::folderPref($GLOBALS['prefs']->getValue('spam_folder'), true),
    'spam_spammbox' => intval(!empty($GLOBALS['conf']['spam']['spamfolder'])),
    'splitbar_pos' => intval($GLOBALS['prefs']->getValue('dimp_splitbar')),

    'toggle_pref' => intval($GLOBALS['prefs']->getValue('dimp_toggle_headers')),
    'viewport_wait' => intval($GLOBALS['conf']['dimp']['viewport']['viewport_wait']),
));

/* Gettext strings used in core javascript files. */
$code['text'] = array(
    'ajax_error' => _("Error when communicating with the server."),
    'ajax_recover' => _("The connection to the server has been restored."),
    'ajax_timeout' => _("There has been no contact with the server for several minutes. The server may be temporarily unavailable or network problems may be interrupting your session. You will not see any updates until the connection is restored."),
    'badaddr' => _("Invalid Address"),
    'badsubject' => _("Invalid Subject"),
    'baselevel' => _("base level of the folder tree"),
    'cancel' => _("Cancel"),
    'check' => _("Checking..."),
    'copyto' => _("Copy %s to %s"),
    'create_prompt' => _("Create folder:"),
    'createsub_prompt' => _("Create subfolder:"),
    'delete_folder' => _("Permanently delete %s?"),
    'empty_folder' => _("Permanently delete all messages in %s?"),
    'growlerinfo' => _("This is the notification backlog"),
    'hidealog' => Horde::highlightAccessKey(_("Hide Alerts _Log"), Horde::getAccessKey(_("Alerts _Log"), true)),
    'listmsg_wait' => _("The server is still generating the message list."),
    'listmsg_timeout' => _("The server was unable to generate the message list."),
    'loading' => _("Loading..."),
    'message' => _("Message"),
    'messages' => _("Messages"),
    'messagetitle' => _("%d - %d of %d Messages"),
    'moveto' => _("Move %s to %s"),
    'noalerts' => _("No Alerts"),
    'nomessages' => _("No Messages"),
    'ok' => _("Ok"),
    'onlogout' => _("Logging Out..."),
    'popup_block' => _("A popup window could not be opened. Your browser may be blocking popups."),
    'portal' => ("Portal"),
    'prefs' => _("User Options"),
    'rename_prompt' => _("Rename folder to:"),
    'search' => _("Search"),
    'verify' => _("Verifying..."),
    'vp_empty' => _("There are no messages in this mailbox."),
);

if (in_array(basename($_SERVER['PHP_SELF']), array('compose-dimp.php', 'message-dimp.php'))){
    $compose_cursor = $GLOBALS['prefs']->getValue('compose_cursor');

    /* Variables used in compose page. */
    $code['conf_compose'] = array_filter(array(
        'attach_limit' => ($GLOBALS['conf']['compose']['attach_count_limit'] ? intval($GLOBALS['conf']['compose']['attach_count_limit']) : -1),
        'auto_save_interval_val' => intval($GLOBALS['prefs']->getValue('auto_save_drafts')),
        'bcc' => intval($GLOBALS['prefs']->getValue('compose_bcc')),
        'cc' => intval($GLOBALS['prefs']->getValue('compose_cc')),
        'close_draft' => intval($GLOBALS['prefs']->getValue('close_draft')),
        'compose_cursor' => ($compose_cursor ? $compose_cursor : 'top'),
        'drafts_mbox' => IMP::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true),
        'rte_avail' => intval($GLOBALS['browser']->hasFeature('rte')),
        'spellcheck' => intval($GLOBALS['prefs']->getValue('compose_spellcheck')),
    ));

    /* Gettext strings used in compose page. */
    $code['text_compose'] = array(
        'cancel' => _("Cancelling this message will permanently discard its contents and will delete auto-saved drafts.\nAre you sure you want to do this?"),
        'nosubject' => _("The message does not have a Subject entered.") . "\n" . _("Send message without a Subject?"),
        'remove' => _("Remove"),
        'spell_noerror' => _("No spelling errors found."),
        'toggle_html' => _("Really discard all formatting information? This operation cannot be undone."),
        'uploading' => _("Uploading..."),
    );

    if ($GLOBALS['registry']->hasMethod('contacts/search')) {
        $code['conf_compose']['URI_ABOOK'] = (string) Horde::applicationUrl('contacts.php');
    }

    if ($GLOBALS['prefs']->getValue('set_priority')) {
        $code['conf_compose']['priority'] = array(
            array(
                'l' => _("High"),
                'v' => 'high'
            ),
            array(
                'l' => _("Normal"),
                's' => true,
                'v' => 'normal'
            ),
            array(
                'l' => _("Low"),
                'v' => 'low'
            )
        );
    }

    if (!($GLOBALS['prefs']->isLocked('default_encrypt')) &&
        ($GLOBALS['prefs']->getValue('use_pgp') ||
         $GLOBALS['prefs']->getValue('use_smime'))) {
        $encrypt = array();
        foreach (IMP::encryptList(null, true) as $key => $val) {
            $encrypt[] = array(
                'l' => htmlspecialchars($val),
                'v' => intval($key)
            );
        }
        $code['conf_compose']['encrypt'] = $encrypt;
    }
}

Horde::addInlineScript(array(
    'var DIMP = ' . Horde_Serialize::serialize($code, Horde_Serialize::JSON, Horde_Nls::getCharset())
), null, true);
