<?php
/**
 * Defines the AJAX interface for IMP.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Ajax_Application extends Horde_Ajax_Application_Base
{
    /**
     * Determines if notification information is sent in response.
     *
     * @var boolean
     */
    public $notify = true;

    /**
     * The list of actions that require readonly access to the session.
     *
     * @var array
     */
    protected $_readOnly = array(
        'html2Text', 'text2Html'
    );

    /**
     * Determines the HTTP response output type.
     *
     * @see Horde::sendHTTPResponse().
     *
     * @return string  The output type.
     */
    public function responseType()
    {
        return ($this->_action == 'addAttachment')
            ? 'js-json'
            : parent::responseType();
    }

    /**
     * AJAX action: Create a mailbox.
     *
     * Variables used:
     * <pre>
     * 'mbox' - (string) The name of the new mailbox.
     * 'parent' - (string) The parent mailbox.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'mailbox' - (object) Mailboxes that were altered. Contains the
     *             following properties:
     *   'a' - (array) Mailboxes that were added.
     *   'c' - (array) Mailboxes that were changed.
     *   'd' - (array) Mailboxes that were deleted.
     * </pre>
     */
    public function createMailbox()
    {
        if (!$this->_vars->mbox) {
            return false;
        }

        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $imptree->eltDiffStart();

        $new = Horde_String::convertCharset($this->_vars->mbox, Horde_Nls::getCharset(), 'UTF7-IMAP');
        try {
            $new = $imptree->createMailboxName($this->_vars->parent, $new);

            if ($result =$GLOBALS['injector']->getInstance('IMP_Folder')->create($new, $GLOBALS['prefs']->getValue('subscribe'))) {
                $result = new stdClass;
                $result->mailbox = $this->_getMailboxResponse($imptree);
            }
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
            $result = false;
        }

        return $result;
    }

    /**
     * AJAX action: Delete a mailbox.
     *
     * Variables used:
     * <pre>
     * 'mbox' - (string) The full mailbox name to delete.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'mailbox' - (object) Mailboxes that were altered. Contains the
     *             following properties:
     *   'a' - (array) Mailboxes that were added.
     *   'c' - (array) Mailboxes that were changed.
     *   'd' - (array) Mailboxes that were deleted.
     * </pre>
     */
    public function deleteMailbox()
    {
        if (!$this->_vars->mbox) {
            return false;
        }

        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $imptree->eltDiffStart();

        if ($GLOBALS['imp_search']->isEditableVFolder($this->_vars->mbox)) {
            $GLOBALS['notification']->push(sprintf(_("Deleted Virtual Folder \"%s\"."), $GLOBALS['imp_search']->getLabel($this->_vars->mbox)), 'horde.success');
            $GLOBALS['imp_search']->deleteSearchQuery($this->_vars->mbox);
            $result = true;
        } else {
            $result = $GLOBALS['injector']->getInstance('IMP_Folder')->delete(array($this->_vars->mbox));
        }

        if ($result) {
            $result = new stdClass;
            $result->mailbox = $this->_getMailboxResponse($imptree);
        }

        return $result;
    }

    /**
     * AJAX action: Rename a mailbox.
     *
     * Variables used:
     * <pre>
     * 'new_name' - (string) New mailbox name (child node).
     * 'new_parent' - (string) New parent name.
     * 'old_name' - (string) Full name of old mailbox.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'mailbox' - (object) Mailboxes that were altered. Contains the
     *             following properties:
     *   'a' - (array) Mailboxes that were added.
     *   'c' - (array) Mailboxes that were changed.
     *   'd' - (array) Mailboxes that were deleted.
     * </pre>
     */
    public function renameMailbox()
    {
        if (!$this->_vars->old_name || !$this->_vars->new_name) {
            return false;
        }

        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $imptree->eltDiffStart();

        $result = false;

        try {
            $new = Horde_String::convertCharset($imptree->createMailboxName($this->_vars->new_parent, $this->_vars->new_name), Horde_Nls::getCharset(), 'UTF7-IMAP');

            if (($this->_vars->old_name != $new) &&
                $GLOBALS['injector']->getInstance('IMP_Folder')->rename($this->_vars->old_name, $new)) {
                $result = new stdClass;
                $result->mailbox = $this->_getMailboxResponse($imptree);
            }
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
        }

        return $result;
    }

    /**
     * AJAX action: Empty a mailbox.
     *
     * Variables used:
     * <pre>
     * 'mbox' - (string) The full mailbox name to empty.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'mbox' - (string) The mailbox that was emptied.
     * </pre>
     */
    public function emptyMailbox()
    {
        if (!$this->_vars->mbox) {
            return false;
        }

        $GLOBALS['injector']->getInstance('IMP_Message')->emptyMailbox(array($this->_vars->mbox));

        $result = new stdClass;
        $result->mbox = $this->_vars->mbox;

        return $result;
    }

    /**
     * AJAX action: Flag all messages in a mailbox.
     *
     * Variables used:
     * <pre>
     * 'flags' - (string) The flags to set (JSON serialized array).
     * 'mbox' - (string) The full malbox name.
     * 'set' - (integer) Set the flags?
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'flags' - (array) The list of flags that were set.
     * 'mbox' - (string) The full mailbox name.
     * 'poll' - (array) Mailbox names as the keys, number of unseen messages
     *          as the values.
     * 'set' - (integer) 1 if the flag was set. Unset otherwise.
     * </pre>
     */
    public function flagAll()
    {
        $flags = Horde_Serialize::unserialize($this->_vars->flags, Horde_Serialize::JSON);
        if (!$this->_vars->mbox || empty($flags)) {
            return false;
        }

        $result = $GLOBALS['injector']->getInstance('IMP_Message')->flagAllInMailbox($flags, array($this->_vars->mbox), $this->_vars->set);

        if ($result) {
            $result = new stdClass;
            $result->flags = $flags;
            $result->mbox = $this->_vars->mbox;
            if ($this->_vars->set) {
                $result->set = 1;
            }

            $poll = $this->_getPollInformation($this->_vars->mbox);
            if (!empty($poll)) {
                $result->poll = array($this->_vars->mbox => $poll[$this->_vars->mbox]);
            }
        }

        return $result;
    }

    /**
     * AJAX action: List mailboxes.
     *
     * Variables used:
     * <pre>
     * 'all' - (integer) 1 to show all mailboxes.
     * 'initial' - (string) 1 to indicate the initial request for mailbox
     *             list.
     * 'mboxes' - (string) The list of mailboxes to process (JSON encoded
     *            array).
     * 'reload' - (integer) 1 to force reload of mailboxes.
     * 'unsub' - (integer) 1 to show unsubscribed mailboxes.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'mailbox' - (object) Mailboxes that were altered. Contains the
     *             following properties:
     *   'a' - (array) Mailboxes that were added.
     *   'c' - (array) Mailboxes that were changed.
     *   'd' - (array) Mailboxes that were deleted.
     * 'quota' - (array) See _getQuota().
     * </pre>
     */
    public function listMailboxes()
    {
        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');

        $mask = IMP_Imap_Tree::FLIST_CONTAINER | IMP_Imap_Tree::FLIST_VFOLDER | IMP_Imap_Tree::FLIST_ELT;
        if ($this->_vars->unsub) {
            $mask |= IMP_Imap_Tree::FLIST_UNSUB;
        }

        if (!$this->_vars->all) {
            $mask |= IMP_Imap_Tree::FLIST_NOCHILDREN;
            if ($this->_vars->initial || $this->_vars->reload) {
                $mask |= IMP_Imap_Tree::FLIST_ANCESTORS | IMP_Imap_Tree::FLIST_SAMELEVEL;
            }
        }

        if ($this->_vars->reload) {
            $GLOBALS['injector']->getInstance('IMP_Folder')->clearFlistCache();
            $imptree->init();
        }

        $folder_list = array();
        if (!empty($this->_vars->mboxes)) {
            foreach (Horde_Serialize::unserialize($this->_vars->mboxes, Horde_Serialize::JSON) as $val) {
                $folder_list += $imptree->folderList($mask, $val);
            }

            if (($this->_vars->initial || $this->_vars->reload) && empty($folder_list)) {
                $folder_list = $imptree->folderList($mask, 'INBOX');
            }
        }

        /* Add special folders explicitly to the initial folder list, since
         * they are ALWAYS displayed and may appear outside of the folder
         * slice requested. */
        if ($this->_vars->initial || $this->_vars->reload) {
            foreach ($imptree->getSpecialMailboxes() as $val) {
                if (!is_array($val)) {
                    $val = array($val);
                }

                foreach ($val as $val2) {
                    if (!isset($folder_list[$val2]) &&
                        ($elt = $imptree->element($val2))) {
                        $folder_list[$val2] = $elt;
                    }
                }
            }
        }

        $result = new stdClass;
        $result->mailbox = $this->_getMailboxResponse($imptree, array(
            'a' => array_values($folder_list),
            'c' => array(),
            'd' => array()
        ));

        $quota = $this->_getQuota();
        if (!is_null($quota)) {
            $result->quota = $quota;
        }

        return $result;
    }

    /**
     * AJAX action: Poll mailboxes.
     *
     * See the list of variables needed for _changed() and _viewPortData().
     * Additional variables used:
     * <pre>
     * 'view' - (string) The current view (mailbox).
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'poll' - (array) Mailbox names as the keys, number of unseen messages
     *          as the values.
     * 'quota' - (array) See _getQuota().
     * 'ViewPort' - (object) See _viewPortData().
     * </pre>
     */
    public function poll()
    {
        $changed = false;

        $result = new stdClass;
        $result->poll = array();

        foreach ($GLOBALS['imp_imap']->ob()->statusMultiple($GLOBALS['injector']->getInstance('IMP_Imap_Tree')->getPollList(), Horde_Imap_Client::STATUS_UNSEEN) as $key => $val) {
            $result->poll[$key] = intval($val['unseen']);
        }

        if ($this->_vars->view &&
            ($changed = $this->_changed())) {
            $result->ViewPort = $this->_viewPortData(true);
        }

        if (!is_null($changed)) {
            $quota = $this->_getQuota();
            if (!is_null($quota)) {
                $result->quota = $quota;
            }
        }

        return $result;
    }

    /**
     * AJAX action: Modify list of polled mailboxes.
     *
     * Variables used:
     * <pre>
     * 'add' - (integer) 1 to add to the poll list, 0 to remove.
     * 'mbox' - (string) The full mailbox name to modify.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'add' - (integer) 1 if added to the poll list, 0 if removed.
     * 'mbox' - (string) The full mailbox name modified.
     * 'poll' - (array) Mailbox names as the keys, number of unseen messages
     *          as the values.
     * </pre>
     */
    public function modifyPoll()
    {
        if (!$this->_vars->mbox) {
            return false;
        }

        $display_folder = IMP::displayFolder($this->_vars->mbox);

        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');

        $result = new stdClass;
        $result->add = intval($this->_vars->add);
        $result->mbox = $this->_vars->mbox;

        if ($this->_vars->add) {
            $imptree->addPollList($this->_vars->view);
            try {
                if ($info = $GLOBALS['imp_imap']->ob()->status($this->_vars->view, Horde_Imap_Client::STATUS_UNSEEN)) {
                    $result->poll = array($this->_vars->view => intval($info['unseen']));
                }
            } catch (Horde_Imap_Client_Exception $e) {}
            $GLOBALS['notification']->push(sprintf(_("\"%s\" mailbox now polled for new mail."), $display_folder), 'horde.success');
        } else {
            $imptree->removePollList($this->_vars->view);
            $GLOBALS['notification']->push(sprintf(_("\"%s\" mailbox no longer polled for new mail."), $display_folder), 'horde.success');
        }

        return $result;
    }

    /**
     * AJAX action: [un]Subscribe to a mailbox.
     *
     * Variables used:
     * <pre>
     * 'mbox' - (string) The full mailbox name to [un]subscribe to.
     * 'sub' - (integer) 1 to subscribe, empty to unsubscribe.
     * </pre>
     *
     * @return boolean  True on success, false on failure.
     */
    public function subscribe()
    {
        if (!$GLOBALS['prefs']->getValue('subscribe')) {
            return false;
        }

        $imp_folder = $GLOBALS['injector']->getInstance('IMP_Folder');
        return $this->_vars->sub
            ? $imp_folder->subscribe(array($this->_vars->mbox))
            : $imp_folder->unsubscribe(array($this->_vars->mbox));
    }

    /**
     * AJAX action: Output ViewPort data.
     *
     * See the list of variables needed for _changed() and _viewPortData().
     * Additional variables used:
     * <pre>
     * 'checkcache' - (integer) If 1, only send data if cache has been
     *                invalidated.
     * 'rangeslice' - (string) Range slice. See js/ViewPort.js.
     * 'requestid' - (string) Request ID. See js/ViewPort.js.
     * 'sortby' - (integer) The Horde_Imap_Client sort constant.
     * 'sortdir' - (integer) 0 for ascending, 1 for descending.
     * 'view' - (string) The current full mailbox name.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'ViewPort' - (object) See _viewPortData().
     * </pre>
     */
    public function viewPort()
    {
        if (!$this->_vars->view) {
            return false;
        }

        /* Change sort preferences if necessary. */
        if (isset($this->_vars->sortby) || isset($this->_vars->sortdir)) {
            IMP::setSort($this->_vars->sortby, $this->_vars->sortdir, $this->_vars->view);
        }

        $changed = $this->_changed(false);

        if (is_null($changed)) {
            $list_msg = new IMP_Views_ListMessages();
            $result = new stdClass;
            $result->ViewPort = $list_msg->getBaseOb($this->_vars->view);

            $req_id = $this->_vars->requestid;
            if (!is_null($req_id)) {
                $result->ViewPort->requestid = intval($req_id);
            }
        } elseif ($changed || $this->_vars->rangeslice || !$this->_vars->checkcache) {
            $result = new stdClass;
            $result->ViewPort = $this->_viewPortData($changed);
        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * AJAX action: Move messages.
     *
     * See the list of variables needed for _changed(),
     * _generateDeleteResult(), and _checkUidvalidity(). Additional variables
     * used:
     * <pre>
     * 'mboxto' - (string) Mailbox to move the message to.
     * 'uid' - (string) Indices of the messages to move (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object (see
     *                _generateDeleteResult() for format).
     */
    public function moveMessages()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (!$this->_vars->mboxto || empty($indices)) {
            return false;
        }

        $change = $this->_changed(true);

        if (is_null($change)) {
            return false;
        }

        $result = $GLOBALS['injector']->getInstance('IMP_Message')->copy($this->_vars->mboxto, 'move', $indices);

        if ($result) {
            $result = $this->_generateDeleteResult($indices, $change);
            /* Need to manually set remove to true since we want to remove
             * message from the list no matter the current pref
             * settings. */
            $result->deleted->remove = 1;

            /* Update poll information for destination folder if necessary.
             * Poll information for current folder will be added by
             * _generateDeleteResult() call above. */
            if ($poll = $this->_getPollInformation($this->_vars->mboxto)) {
                $result->poll = array_merge(isset($result->poll) ? $result->poll : array(), $poll);
            }
        } else {
            $result = $this->_checkUidvalidity();
        }

        return $result;
    }

    /**
     * AJAX action: Copy messages.
     *
     * See the list of variables needed for _checkUidvalidity(). Additional
     * variables used:
     * <pre>
     * 'mboxto' - (string) Mailbox to move the message to.
     * 'uid' - (string) Indices of the messages to copy (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'ViewPort' - (object) See _viewPortData().
     * 'poll' - (array) Mailbox names as the keys, number of unseen messages
     *          as the values.
     * </pre>
     */
    public function copyMessages()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (!$this->_vars->mboxto || empty($indices)) {
            return false;
        }

        if ($result = $GLOBALS['injector']->getInstance('IMP_Message')->copy($this->_vars->mboxto, 'copy', $indices)) {
            if ($poll = $this->_getPollInformation($this->_vars->mboxto)) {
                $result->poll = array_merge(isset($result->poll) ? $result->poll : array(), $poll);
            }
        } else {
            $result = $this->_checkUidvalidity();
        }

        return $result;
    }

    /**
     * AJAX action: Flag messages.
     *
     * See the list of variables needed for _changed() and
     * _checkUidvalidity().  Additional variables used:
     * <pre>
     * 'flags' - (string) The flags to set (JSON serialized array).
     * 'uid' - (string) Indices of the messages to flag (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'ViewPort' - (object) See _viewPortData().
     * </pre>
     */
    public function flagMessages()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (!$this->_vars->flags || empty($indices)) {
            return false;
        }

        $change = $this->_changed(true);

        if (is_null($change)) {
            return false;
        }

        $flags = Horde_Serialize::unserialize($this->_vars->flags, Horde_Serialize::JSON);
        $set = $notset = array();

        foreach ($flags as $val) {
            if ($val[0] == '-') {
                $notset[] = substr($val, 1);
            } else {
                $set[] = $val;
            }
        }

        if (!empty($set)) {
            $result = $GLOBALS['injector']->getInstance('IMP_Message')->flag($set, $indices, true);
        }

        if (!empty($notset)) {
            $result = $GLOBALS['injector']->getInstance('IMP_Message')->flag($notset, $indices, false);
        }

        if ($result) {
            $result = new stdClass;
            if ($change) {
                $result->ViewPort = $this->_viewPortData(true);
            } else {
                $result->ViewPort = new stdClass;
                $result->ViewPort->updatecacheid = IMP_Mailbox::singleton($this->_vars->view)->getCacheID($this->_vars->view);
                $result->ViewPort->view = $this->_vars->view;
            }
            return $result;
        }

        return $this->_checkUidvalidity();
    }

    /**
     * AJAX action: Delete messages.
     *
     * See the list of variables needed for _changed(),
     * _generateDeleteResult(), and _checkUidvalidity(). Additional variables
     * used:
     * <pre>
     * 'uid' - (string) Indices of the messages to delete (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object (see
     *                _generateDeleteResult() for format).
     */
    public function deleteMessages()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (empty($indices)) {
            return false;
        }

        $change = $this->_changed(true);

        if ($GLOBALS['injector']->getInstance('IMP_Message')->delete($indices)) {
            return $this->_generateDeleteResult($indices, $change, !$GLOBALS['prefs']->getValue('hide_deleted') && !$GLOBALS['prefs']->getValue('use_trash'));
        }

        return is_null($change)
            ? false
            : $this->_checkUidvalidity();
    }

    /**
     * AJAX action: Add contact.
     *
     * Variables used:
     * <pre>
     * 'email' - (string) The email address to name.
     * 'name' - (string) The name associated with the email address.
     * </pre>
     *
     * @return boolean  True on success, false on failure.
     */
    public function addContact()
    {
        // Allow name to be empty.
        if (!$this->_vars->email) {
            return false;
        }

        try {
            IMP::addAddress($this->_vars->email, $this->_vars->name);
            $GLOBALS['notification']->push(sprintf(_("%s was successfully added to your address book."), $this->_vars->name ? $this->_vars->name : $this->_vars->email), 'horde.success');
            return true;
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
            return false;
        }
    }

    /**
     * AJAX action: Report message as [not]spam.
     *
     * See the list of variables needed for _changed(),
     * _generateDeleteResult(), and _checkUidvalidity(). Additional variables
     * used:
     * <pre>
     * 'spam' - (integer) 1 to mark as spam, 0 to mark as innocent.
     * 'uid' - (string) Indices of the messages to report (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object (see
     *                _generateDeleteResult() for format).
     */
    public function reportSpam()
    {
        $change = $this->_changed(false);
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        $result = false;

        if (IMP_Spam::reportSpam($indices, $this->_vars->spam ? 'spam' : 'notspam')) {
            $result = $this->_generateDeleteResult($indices, $change);
            /* If result of reportSpam() is non-zero, then we know the message
             * has been removed from the current mailbox. */
            $result->deleted->remove = 1;
        } elseif (!is_null($change)) {
            $result = $this->_checkUidvalidity();
        }

        return $result;
    }

    /**
     * AJAX action: Blacklist/whitelist addresses from messages.
     *
     * See the list of variables needed for _changed(),
     * _generateDeleteResult(), and _checkUidvalidity(). Additional variables
     * used:
     * <pre>
     * 'blacklist' - (integer) 1 to blacklist, 0 to whitelist.
     * 'uid' - (string) Indices of the messages to report (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object (see
     *                _generateDeleteResult() for format).
     */
    public function blacklist()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (empty($indices)) {
            return false;
        }

        $imp_filter = new IMP_Filter();
        $result = false;

        if ($this->_vars->blacklist) {
            $change = $this->_changed(false);
            if (!is_null($change)) {
                try {
                    if ($imp_filter->blacklistMessage($indices, false)) {
                        $result = $this->_generateDeleteResult($indices, $change);
                    }
                } catch (Horde_Exception $e) {
                    $result = $this->_checkUidvalidity();
                }
            }
        } else {
            try {
                $imp_filter->whitelistMessage($indices, false);
            } catch (Horde_Exception $e) {
                $result = $this->_checkUidvalidity();
            }
        }

        return $result;
    }

    /**
     * AJAX action: Generate data necessary to display preview message.
     *
     * See the list of variables needed for _changed() and
     * _checkUidvalidity().  Additional variables used:
     * <pre>
     * 'uid' - (string) Index of the messages to preview (IMAP sequence
     *         string) - must be single index.
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'preview' - (object) Return from IMP_View_ShowMessage::showMessage().
     * 'ViewPort' - (object) See _viewPortData(). (Only returns updatecacheid
     *                       entry - don't do mailbox poll here).
     * </pre>
     */
    public function showPreview()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        if (count($indices) != 1) {
            return false;
        }

        $change = $this->_changed($indices);
        if (is_null($change)) {
            return false;
        }

        $ptr = each($indices);
        $args = array(
            'mailbox' => $ptr['key'],
            'preview' => true,
            'uid' => intval(reset($ptr['value']))
        );
        $result = new stdClass;
        $result->preview = new stdClass;

        try {
            /* We know we are going to be exclusively dealing with this
             * mailbox, so select it on the IMAP server (saves some STATUS
             * calls). Open R/W to clear the RECENT flag. */
            $GLOBALS['imp_imap']->ob()->openMailbox($ptr['key'], Horde_Imap_Client::OPEN_READWRITE);
            $show_msg = new IMP_Views_ShowMessage();
            $result->preview = (object)$show_msg->showMessage($args);
            if (isset($result->preview->error)) {
                $result = $this->_checkUidvalidity($result);
            } elseif (!$change) {
                /* Only update cacheid info if it changed. */
                $cacheid = IMP_Mailbox::singleton($this->_vars->view)->getCacheID($this->_vars->view);
                if ($cacheid != $this->_vars->cacheid) {
                    $result->ViewPort = new stdClass;
                    $result->ViewPort->updatecacheid = $cacheid;
                    $result->ViewPort->view = $this->_vars->view;
                }
            }
        } catch (Horde_Imap_Client_Exception $e) {
            $result->preview->error = $e->getMessage();
            $result->preview->errortype = 'horde.error';
            $result->preview->mailbox = $args['mailbox'];
            $result->preview->uid = $args['uid'];
        }

        return $result;
    }

    /**
     * AJAX action: Convert HTML to text.
     *
     * Variables used:
     * <pre>
     * 'text' - (string) The text to convert.
     * </pre>
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'text' - (string) The converted text.
     * </pre>
     */
    public function html2Text()
    {
        $result = new stdClass;
        // Need to replace line endings or else IE won't display line endings
        // properly.
        $result->text = str_replace("\n", "\r\n", Horde_Text_Filter::filter($this->_vars->text, 'Html2text', array('charset' => Horde_Nls::getCharset())));

        return $result;
    }

    /**
     * AJAX action: Convert text to HTML.
     *
     * Variables used:
     * <pre>
     * 'text' - (string) The text to convert.
     * </pre>
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'text' - (string) The converted text.
     * </pre>
     */
    public function text2Html()
    {
        $result = new stdClass;
        $result->text = Horde_Text_Filter::filter($this->_vars->text, 'text2html', array('parselevel' => Horde_Text_Filter_Text2html::MICRO_LINKURL));

        return $result;
    }

    /**
     * AJAX action: Get forward compose data.
     *
     * See the list of variables needed for _checkUidvalidity(). Additional
     * variables used:
     * <pre>
     * 'dataonly' - (boolean) Only return data information (DEFAULT:
     *              false).
     * 'imp_compose' - (string) The IMP_Compose cache identifier.
     * 'type' - (string) See IMP_Compose::forwardMessage().
     * 'uid' - (string) Indices of the messages to forward (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'body' - (string) The body text of the message.
     * 'format' - (string) Either 'text' or 'html'.
     * 'fwd_list' - (array) See IMP_Dimp::getAttachmentInfo().
     * 'header' - (array) The headers of the message.
     * 'identity' - (integer) The identity ID to use for this message.
     * 'imp_compose'- (string) The IMP_Compose cache identifier.
     * 'opts' - (array) Additional options needed for DimpCompose.fillForm().
     * 'type' - (string) The input 'type' value.
     * 'ViewPort' - (object) See _viewPortData().
     * </pre>
     */
    public function getForwardData()
    {
        try {
            $imp_compose = IMP_Compose::singleton($this->_vars->imp_compose);
            if (!($imp_contents = $imp_compose->getContentsOb())) {
                $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
                $i = each($indices);
                $imp_contents = IMP_Contents::singleton(reset($i['value']) . IMP::IDX_SEP . $i['key']);
            }

            $fwd_msg = $imp_compose->forwardMessage($this->_vars->type, $imp_contents);

            /* Can't open session read-only since we need to store the message
             * cache id. */
            $result = new stdClass;
            $result->opts = new stdClass;
            $result->opts->fwd_list = IMP_Dimp::getAttachmentInfo($imp_compose);
            $result->body = $fwd_msg['body'];
            $result->type = $this->_vars->type;
            if (!$this->_vars->dataonly) {
                $result->format = $fwd_msg['format'];
                $fwd_msg['headers']['replytype'] = 'forward';
                $result->header = $fwd_msg['headers'];
                $result->identity = $fwd_msg['identity'];
                $result->imp_compose = $imp_compose->getCacheId();
                if ($this->_vars->type == 'forward_auto') {
                    $result->opts->auto = $fwd_msg['type'];
                }
            }
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
            $result = $this->_checkUidvalidity();
        }

        return $result;
    }

    /**
     * AJAX action: Get reply data.
     *
     * See the list of variables needed for _checkUidvalidity(). Additional
     * variables used:
     * <pre>
     * 'headeronly' - (boolean) Only return header information (DEFAULT:
     *                false).
     * 'imp_compose' - (string) The IMP_Compose cache identifier.
     * 'type' - (string) See IMP_Compose::replyMessage().
     * 'uid' - (string) Indices of the messages to reply to (IMAP sequence
     *         string).
     * </pre>
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'body' - (string) The body text of the message.
     * 'format' - (string) Either 'text' or 'html'.
     * 'header' - (array) The headers of the message.
     * 'identity' - (integer) The identity ID to use for this message.
     * 'imp_compose'- (string) The IMP_Compose cache identifier.
     * 'opts' - (array) Additional options needed for DimpCompose.fillForm().
     * 'ViewPort' - (object) See _viewPortData().
     * </pre>
     */
    public function getReplyData()
    {
        try {
            $imp_compose = IMP_Compose::singleton($this->_vars->imp_compose);
            if (!($imp_contents = $imp_compose->getContentsOb())) {
                $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
                $i = each($indices);
                $imp_contents = IMP_Contents::singleton(reset($i['value']) . IMP::IDX_SEP . $i['key']);
            }

            $reply_msg = $imp_compose->replyMessage($this->_vars->type, $imp_contents);
            $reply_msg['headers']['replytype'] = 'reply';

            /* Can't open session read-only since we need to store the message
             * cache id. */
            $result = new stdClass;
            $result->header = $reply_msg['headers'];
            if (!$this->_vars->headeronly) {
                $result->body = $reply_msg['body'];
                $result->format = $reply_msg['format'];
                $result->identity = $reply_msg['identity'];
                $result->imp_compose = $imp_compose->getCacheId();
                if ($this->_vars->type == 'reply_auto') {
                    $result->opts = array('auto' => $reply_msg['type']);
                }
            }
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
            $result = $this->_checkUidvalidity();
        }

        return $result;
    }

    /**
     * AJAX action: Cancel compose.
     *
     * Variables used:
     * <pre>
     * 'imp_compose' - (string) The IMP_Compose cache identifier.
     * </pre>
     *
     * @return boolean  True.
     */
    public function cancelCompose()
    {
        $imp_compose = IMP_Compose::singleton($this->_vars->imp_compose);
        $imp_compose->destroy(false);

        return true;
    }

    /**
     * AJAX action: Delete a draft.
     *
     * Variables used:
     * <pre>
     * 'imp_compose' - (string) The IMP_Compose cache identifier.
     * </pre>
     *
     * @return boolean  True.
     */
    public function deleteDraft()
    {
        $imp_compose = IMP_Compose::singleton($this->_vars->imp_compose);
        $imp_compose->destroy(false);

        if ($draft_uid = $imp_compose->getMetadata('draft_uid')) {
            $idx_array = array($draft_uid . IMP::IDX_SEP . IMP::folderPref($GLOBALS['prefs']->getValue('drafts_folder'), true));
            $GLOBALS['injector']->getInstance('IMP_Message')->delete($idx_array, array('nuke' => true));
        }

        return true;
    }

    /**
     * AJAX action: Delete an attachment from compose data.
     *
     * Variables used:
     * <pre>
     * 'atc_indices' - (string) Attachment IDs to delete.
     * 'imp_compose' - (string) The IMP_Compose cache identifier.
     * </pre>
     *
     * @return boolean  True.
     */
    public function deleteAttach()
    {
        if (isset($this->_vars->atc_indices)) {
            $imp_compose = IMP_Compose::singleton($this->_vars->imp_compose);
            foreach ($imp_compose->deleteAttachment($this->_vars->atc_indices) as $val) {
                $GLOBALS['notification']->push(sprintf(_("Deleted attachment \"%s\"."), Horde_Mime::decode($val)), 'horde.success');
            }
        }

        return true;
    }

    /**
     * AJAX action: Generate data necessary to display preview message.
     *
     * @return mixed  False on failure, or an object with the following
     *                entries:
     * <pre>
     * 'linkTags' - (array) TODO
     * 'portal' - (string) The portal HTML data.
     * </pre>
     */
    public function showPortal()
    {
        // Load the block list. Blocks are located in $dimp_block_list.
        // KEY: Block label; VALUE: Horde_Block object
        require IMP_BASE . '/config/portal.php';

        $blocks = $linkTags = array();
        $css_load = array('imp' => true);

        foreach ($dimp_block_list as $title => $block) {
            if ($block['ob'] instanceof Horde_Block) {
                $app = $block['ob']->getApp();
                // TODO: Fix CSS.
                $content = $block['ob']->getContent();
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
            $t = $GLOBALS['injector']->createInstance('Horde_Template');
            $t->set('block', $blocks);
            $result->portal = $t->fetch(IMP_TEMPLATES . '/imp/portal.html');
        }
        $result->linkTags = $linkTags;

        return $result;
    }

    /**
     * AJAX action: Purge deleted messages.
     *
     * See the list of variables needed for _changed(), and
     * _generateDeleteResult().  Additional variables used:
     * <pre>
     * 'uid' - (string) Indices of the messages to purge (IMAP sequence
     *         string).
     * 'view' - (string) The current full mailbox.
     * </pre>
     *
     * @return mixed  False on failure, or an object (see
     *                _generateDeleteResult() for format).
     */
    public function purgeDeleted()
    {
        $indices = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($this->_vars->uid);
        $change = $this->_changed($indices);

        if (is_null($change)) {
            return false;
        }

        if (!$change) {
            $sort = IMP::getSort($this->_vars->view);
            $change = ($sort['by'] == Horde_Imap_Client::SORT_THREAD);
        }

        $expunged = $GLOBALS['injector']->getInstance('IMP_Message')->expungeMailbox(array($this->_vars->view => 1), array('list' => true));

        if (empty($expunged[$this->_vars->view])) {
            return false;
        }

        $expunge_count = count($expunged[$this->_vars->view]);
        $display_folder = IMP::displayFolder($this->_vars->view);
        if ($expunge_count == 1) {
            $GLOBALS['notification']->push(sprintf(_("1 message was purged from \"%s\"."), $display_folder), 'horde.success');
        } else {
            $GLOBALS['notification']->push(sprintf(_("%s messages were purged from \"%s\"."), $expunge_count, $display_folder), 'horde.success');
        }
        $result = $this->_generateDeleteResult($expunged, $change);

        /* Need to manually set remove to true since we want to remove message
         * from the list no matter the current pref settings. */
        $result->deleted->remove = 1;

        return $result;
    }

    /**
     * AJAX action: Send a Message Disposition Notification (MDN).
     *
     * Variables used:
     * <pre>
     * 'uid' - (string) Indices of the messages to isend MDN for (IMAP sequence
     *         string).
     * 'view' - (string) The current full mailbox.
     * </pre>
     *
     * @return boolean  True on success, false on failure.
     */
    public function sendMDN()
    {
        if (!$this->_vars->view || !$this->_vars->uid) {
            return false;
        }

        try {
            $fetch_ret = $GLOBALS['imp_imap']->ob()->fetch($this->_vars->view, array(
                Horde_Imap_Client::FETCH_HEADERTEXT => array(array('parse' => true, 'peek' => false))
            ), array('ids' => array($this->_vars->uid)));
        } catch (Horde_Imap_Client_Exception $e) {
            return false;
        }

        $imp_ui = new IMP_Ui_Message();
        $imp_ui->MDNCheck($this->_vars->view, $this->_vars->uid, reset($fetch_ret[$this->_vars->uid]['headertext']), true);

        return true;
    }

    /**
     * AJAX action: Add an attachment to a compose message.
     *
     * Variables used:
     * <pre>
     * 'composeCache' - (string) The IMP_Compose cache identifier.
     * </pre>
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'atc' - TODO
     * 'error' - (string) An error message.
     * 'imp_compose' - TODO
     * 'success' - (integer) 1 on success, 0 on failure.
     * </pre>
     */
    public function addAttachment()
    {
        $imp_compose = IMP_Compose::singleton($this->_vars->composeCache);

        $result = new stdClass;
        $result->action = 'addAttachment';
        $result->success = 0;

        if ($_SESSION['imp']['file_upload'] &&
            $imp_compose->addFilesFromUpload('file_')) {
            $result->atc = end(IMP_Dimp::getAttachmentInfo($imp_compose));
            $result->success = 1;
            $result->imp_compose = $imp_compose->getCacheId();
        }

        return $result;
    }

    /**
     * AJAX action: Auto save a draft message.
     *
     * @return object  See self::_dimpDraftAction().
     */
    public function autoSaveDraft()
    {
        return $this->_dimpDraftAction();
    }

    /**
     * AJAX action: Save a draft message.
     *
     * @return object  See self::_dimpDraftAction().
     */
    public function saveDraft()
    {
        return $this->_dimpDraftAction();
    }

    /**
     * AJAX action: Send a message.
     *
     * See the list of variables needed for _dimpComposeSetup(). Additional
     * variables used:
     * <pre>
     * 'html' - (integer) In HTML compose mode?
     * 'message' - (string) The message text.
     * 'priority' - TODO
     * 'request_read_receipt' - TODO
     * 'save_attachments_select' - TODO
     * 'save_sent_mail' - TODO
     * 'save_sent_mail_folder' - (string) TODO
     * </pre>
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'action' - (string) The AJAX action string
     * 'draft_delete' - (integer) TODO
     * 'log' - (array) TODO
     * 'mailbox' - (array) TODO
     * 'reply_folder' - (string) TODO
     * 'reply_type' - (string) TODO
     * 'success' - (integer) 1 on success, 0 on failure.
     * 'uid' - (integer) TODO
     * </pre>
     */
    public function sendMessage()
    {
        list($result, $imp_compose, $headers, $identity) = $this->_dimpComposeSetup();
        if (!IMP::canCompose()) {
            $result->success = 0;
        }
        if (!$result->success) {
            return $result;
        }

        $headers['replyto'] = $identity->getValue('replyto_addr');

        $result->uid = $imp_compose->getMetadata('uid');

        if ($reply_type = $imp_compose->getMetadata('reply_type')) {
            $result->reply_folder = $imp_compose->getMetadata('mailbox');
            $result->reply_type = $reply_type;
        }

        /* Use IMP_Tree to determine whether the sent mail folder was
         * created. */
        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $imptree->eltDiffStart();

        $options = array(
            'priority' => $this->_vars->priority,
            'readreceipt' => $this->_vars->request_read_receipt,
            'save_attachments' => $this->_vars->save_attachments_select,
            'save_sent' => (($GLOBALS['prefs']->isLocked('save_sent_mail'))
                            ? $identity->getValue('save_sent_mail')
                            : (bool)$this->_vars->save_sent_mail),
            'sent_folder' => (($GLOBALS['prefs']->isLocked('save_sent_mail'))
                              ? $identity->getValue('sent_mail_folder')
                              : (isset($this->_vars->save_sent_mail_folder) ? $this->_vars->save_sent_mail_folder : $identity->getValue('sent_mail_folder')))
        );

        try {
            // TODO: Use 'sending_charset'
            $sent = $imp_compose->buildAndSendMessage($this->_vars->message, $headers, Horde_Nls::getEmailCharset(), $this->_vars->html, $options);
        } catch (IMP_Compose_Exception $e) {
            $result->success = 0;
            $GLOBALS['notification']->push($e);
            return $result;
        }

        /* Remove any auto-saved drafts. */
        if ($GLOBALS['prefs']->getValue('auto_save_drafts') ||
            $GLOBALS['prefs']->getValue('auto_delete_drafts')) {
            $result->draft_delete = 1;
        }

        if ($sent && $GLOBALS['prefs']->getValue('compose_confirm')) {
            $GLOBALS['notification']->push(empty($headers['subject']) ? _("Message sent successfully.") : sprintf(_("Message \"%s\" sent successfully."), Horde_String::truncate($headers['subject'])), 'horde.success');
        }

        /* Update maillog information. */
        if (!empty($GLOBALS['conf']['maillog']['use_maillog'])) {
            $in_reply_to = $imp_compose->getMetadata('in_reply_to');
            if (!empty($in_reply_to) &&
                ($tmp = IMP_Dimp::getMsgLogInfo($in_reply_to))) {
                $result->log = $tmp;
            }
        }

        $imp_compose->destroy();

        $result->mailbox = $this->_getMailboxResponse($imptree);

        return $result;
    }

    /**
     * Setup environment for dimp compose actions.
     *
     * <pre>
     * 'composeCache' - (string) The IMP_Compose cache identifier.
     * </pre>
     * from, identity, composeCache
     *
     * @return array  An array with the following values:
     * <pre>
     * [0] (object) AJAX base return object (with action and success
     *     parameters defined).
     * [1] (IMP_Compose) The IMP_Compose object for the message.
     * [2] (array) The list of headers for the object.
     * [3] (Horde_Prefs_Identity) The identity used for the composition.
     * </pre>
     */
    protected function _dimpComposeSetup()
    {
        $result = new stdClass;
        $result->action = $this->_action;
        $result->success = 1;

        /* Set up identity. */
        $identity = Horde_Prefs_Identity::singleton(array('imp', 'imp'));
        if (isset($this->_vars->identity) &&
            !$GLOBALS['prefs']->isLocked('default_identity')) {
            $identity->setDefault($this->_vars->identity);
        }

        /* Set up the From address based on the identity. */
        $headers = array();
        try {
            $headers['from'] = $identity->getFromLine(null, $this->_vars->from);
        } catch (Horde_Exception $e) {
            $GLOBALS['notification']->push($e);
            $result->success = 1;
            return array($result);
        }

        $imp_ui = new IMP_Ui_Compose();
        $headers['to'] = $imp_ui->getAddressList($this->_vars->to);
        if ($GLOBALS['prefs']->getValue('compose_cc')) {
            $headers['cc'] = $imp_ui->getAddressList($this->_vars->cc);
        }
        if ($GLOBALS['prefs']->getValue('compose_bcc')) {
            $headers['bcc'] = $imp_ui->getAddressList($this->_vars->bcc);
        }
        $headers['subject'] = $this->_vars->subject;

        $imp_compose = IMP_Compose::singleton($this->_vars->composeCache);

        return array($result, $imp_compose, $headers, $identity);
    }

    /**
     * Save a draft composed message.
     *
     * See the list of variables needed for _dimpComposeSetup(). Additional
     * variables used:
     * <pre>
     * 'html' - (integer) In HTML compose mode?
     * 'message' - (string) The message text.
     * </pre>
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'action' - (string) The AJAX action string
     * 'success' - (integer) 1 on success, 0 on failure.
     * </pre>
     */
    protected function _dimpDraftAction()
    {
        list($result, $imp_compose, $headers, $identity) = $this->_dimpComposeSetup();
        if (!$result->success) {
            return $result;
        }

        try {
            $res = $imp_compose->saveDraft($headers, $this->_vars->message, Horde_Nls::getCharset(), $this->_vars->html);
            if ($this->_action == 'autoSaveDraft') {
                $GLOBALS['notification']->push(_("Draft automatically saved."), 'horde.message');
            } else {
                $GLOBALS['notification']->push($res);
                if ($GLOBALS['prefs']->getValue('close_draft')) {
                    $imp_compose->destroy();
                }
            }
        } catch (IMP_Compose_Exception $e) {
            $result->success = 0;
            $GLOBALS['notification']->push($e);
        }

        return $result;
    }

    /**
     * Check the UID validity of the mailbox.
     *
     * See the list of variables needed for _viewPortData().
     *
     * @return mixed  The JSON result, possibly with ViewPort information
     *                added if UID validity has changed.
     */
    protected function _checkUidvalidity($result = false)
    {
        try {
            $GLOBALS['imp_imap']->checkUidvalidity($this->_vars->view);
        } catch (Horde_Exception $e) {
            if (!is_object($result)) {
                $result = new stdClass;
            }
            $result->ViewPort = $this->_viewPortData(true);
        }

        return $result;
    }

    /**
     * Generates the delete data needed for DimpBase.js.
     *
     * See the list of variables needed for _viewPortData().
     *
     * @param array $indices         The list of indices that were deleted.
     * @param boolean $changed       If true, add ViewPort information.
     * @param boolean $nothread      If true, don't do thread sort check.
     *
     * @return object  An object with the following entries:
     * <pre>
     * 'deleted' - (object) Contains the following properties:
     *   mbox - (string) The current mailbox.
     *   remove - (integer) True if messages should be removed from the
     *            viewport.
     *   uids - (string) The list of messages to delete.
     * 'ViewPort' - (object) See _viewPortData().
     * 'poll' - (array) Mailbox names as the keys, number of unseen messages
     *          as the values.
     * </pre>
     */
    protected function _generateDeleteResult($indices, $change, $nothread = false)
    {
        $del = new stdClass;
        $del->mbox = $this->_vars->view;
        $del->uids = $GLOBALS['imp_imap']->ob()->utils->toSequenceString($indices, array('mailbox' => true));
        $del->remove = intval($GLOBALS['prefs']->getValue('hide_deleted') ||
                              $GLOBALS['prefs']->getValue('use_trash'));

        $result = new stdClass;
        $result->deleted = $del;

        /* Check if we need to update thread information. */
        if (!$change && !$nothread) {
            $sort = IMP::getSort($this->_vars->view);
            $change = ($sort['by'] == Horde_Imap_Client::SORT_THREAD);
        }

        if ($change) {
            $result->ViewPort = $this->_viewPortData(true);
        } else {
            $result->ViewPort = new stdClass;
            $result->ViewPort->updatecacheid = IMP_Mailbox::singleton($this->_vars->view)->getCacheID($this->_vars->view);
            $result->ViewPort->view = $this->_vars->view;
        }

        $poll = $this->_getPollInformation($this->_vars->view);
        if (!empty($poll)) {
            $result->poll = $poll;
        }

        return $result;
    }

    /**
     * Determine if the cache information has changed.
     *
     * The following variables:
     * <pre>
     * 'cacheid' - (string) The browser (ViewPort) cache identifier.
     * 'forceUpdate' - (integer) If 1, forces an update,
     * 'view' - (string) The current ViewPort view (mailbox).
     * </pre>
     *
     * @param boolean $rw            Open mailbox as READ+WRITE?
     *
     * @return boolean  True if the server state differs from the browser
     *                  state.
     */
    protected function _changed($rw = null)
    {
        /* Only update search mailboxes on forced refreshes. */
        if ($GLOBALS['imp_search']->isSearchMbox($this->_vars->view)) {
            return ($this->_action == 'viewPort') || $this->_vars->forceUpdate;
        }

        /* We know we are going to be dealing with this mailbox, so select it
         * on the IMAP server (saves some STATUS calls). */
        if (!is_null($rw)) {
            try {
                $GLOBALS['imp_imap']->ob()->openMailbox($this->_vars->view, $rw ? Horde_Imap_Client::OPEN_READWRITE : Horde_Imap_Client::OPEN_AUTO);
            } catch (Horde_Imap_Client_Exception $e) {
                $GLOBALS['notification']->push($e);
                return null;
            }
        }

        return (IMP_Mailbox::singleton($this->_vars->view)->getCacheID($this->_vars->view) != $this->_vars->cacheid);
    }

    /**
     * Generate the information necessary for a ViewPort request from/to the
     * browser.
     *
     * @param boolean $change        True if cache information has changed.
     *
     * @return array  See IMP_Views_ListMessages::listMessages().
     */
    protected function _viewPortData($change)
    {
        $args = array(
            'applyfilter' => $this->_vars->applyfilter,
            'cache' => $this->_vars->cache,
            'cacheid' => $this->_vars->cacheid,
            'change' => $change,
            'initial' => $this->_vars->initial,
            'mbox' => $this->_vars->view,
            'rangeslice' => $this->_vars->rangeslice,
            'requestid' => $this->_vars->requestid,
            'qsearch' => $this->_vars->qsearch,
            'qsearchflag' => $this->_vars->qsearchflag,
            'qsearchmbox' => $this->_vars->qsearchmbox,
            'qsearchflagnot' => $this->_vars->qsearchflagnot,
            'sortby' => $this->_vars->sortby,
            'sortdir' => $this->_vars->sortdir
        );

        if (!$this->_vars->search || $args['initial']) {
            $args += array(
                'after' => intval($this->_vars->after),
                'before' => intval($this->_vars->before)
            );
        }

        if (!$this->_vars->search) {
            list($slice_start, $slice_end) = explode(':', $this->_vars->slice, 2);
            $args += array(
                'slice_start' => intval($slice_start),
                'slice_end' => intval($slice_end)
            );
        } else {
            $search = Horde_Serialize::unserialize($this->_vars->search, Horde_Serialize::JSON);
            $args += array(
                'search_uid' => isset($search->imapuid) ? $search->imapuid : null,
                'search_unseen' => isset($search->unseen) ? $search->unseen : null
            );
        }

        $list_msg = new IMP_Views_ListMessages();
        return $list_msg->listMessages($args);
    }

    /**
     * Generate poll information for a single mailbox.
     *
     * @param string $mbox  The full mailbox name.
     *
     * @return array  Key is the mailbox name, value is the number of unseen
     *                messages.
     */
    protected function _getPollInformation($mbox)
    {
        $imptree = $GLOBALS['injector']->getInstance('IMP_Imap_Tree');
        $elt = $imptree->get($mbox);
        if (!$imptree->isPolled($elt)) {
            return array();
        }

        try {
            $count = ($info = $GLOBALS['imp_imap']->ob()->status($mbox, Horde_Imap_Client::STATUS_UNSEEN))
                ? intval($info['unseen'])
                : 0;
        } catch (Horde_Imap_Client_Exception $e) {
            $count = 0;
        }

        return array($mbox => $count);
    }

    /**
     * Generate quota information.
     *
     * @return array  'p': Quota percentage; 'm': Quota message
     */
    protected function _getQuota()
    {
        if (isset($_SESSION['imp']['imap']['quota']) &&
            is_array($_SESSION['imp']['imap']['quota'])) {
            $quotadata = IMP::quotaData(false);
            if (!empty($quotadata)) {
                return array(
                    'm' => $quotadata['message'],
                    'p' => round($quotadata['percent'])
                );
            }
        }

        return null;
    }

    /**
     * Formats the response to send to javascript code when dealing with
     * mailbox operations.
     *
     * @param IMP_Tree $imptree  An IMP_Tree object.
     * @param array $changes     An array with three sub arrays - to be used
     *                           instead of the return from
     *                           $imptree->eltDiff():
     *                           'a' - a list of mailboxes/objects to add
     *                           'c' - a list of changed mailboxes
     *                           'd' - a list of mailboxes to delete
     *
     * @return array  The object used by the JS code to update the folder
     *                tree.
     */
    protected function _getMailboxResponse($imptree, $changes = null)
    {
        if (is_null($changes)) {
            $changes = $imptree->eltDiff();
        }
        if (empty($changes)) {
            return false;
        }

        $result = array();

        if (!empty($changes['a'])) {
            $result['a'] = array();
            foreach ($changes['a'] as $val) {
                $result['a'][] = $this->_createMailboxElt(is_array($val) ? $val : $imptree->element($val));
            }
        }

        if (!empty($changes['c'])) {
            $result['c'] = array();
            foreach ($changes['c'] as $val) {
                // Skip the base element, since any change there won't ever be
                // updated on-screen.
                if ($val != IMP_Imap_Tree::BASE_ELT) {
                    $result['c'][] = $this->_createMailboxElt($imptree->element($val));
                }
            }
        }

        if (!empty($changes['d'])) {
            $result['d'] = array_reverse($changes['d']);
        }

        return $result;
    }

    /**
     * Create an object used by DimpCore to generate the folder tree.
     *
     * @param array $elt  The output from IMP_Tree::element().
     *
     * @return stdClass  The element object. Contains the following items:
     * <pre>
     * 'ch' (children) = Does the mailbox contain children? [boolean]
     *                   [DEFAULT: no]
     * 'cl' (class) = The CSS class. [string] [DEFAULT: 'base']
     * 'co' (container) = Is this mailbox a container element? [boolean]
     *                    [DEFAULT: no]
     * 'i' (icon) = A user defined icon to use. [string] [DEFAULT: none]
     * 'l' (label) = The mailbox display label. [string] [DEFAULT: 'm' val]
     * 'm' (mbox) = The mailbox value. [string]
     * 'n' (non-imap) = A non-IMAP element? [boolean] [DEFAULT: no]
     * 'pa' (parent) = The parent element. [string] [DEFAULT:
     *                 DIMP.conf.base_mbox]
     * 'po' (polled) = Is the element polled? [boolean] [DEFAULT: no]
     * 's' (special) = Is this a "special" element? [boolean] [DEFAULT: no]
     * 't' (title) = The title value. [string] [DEFAULT: 'm' val]
     * 'u' (unseen) = The number of unseen messages. [integer]
     * 'un' (unsubscribed) = Is this mailbox unsubscribed? [boolean]
     *                       [DEFAULT: no]
     * 'v' (virtual) = Virtual folder? 0 = not vfolder, 1 = system vfolder,
     *                 2 = user vfolder [integer] [DEFAULT: 0]
     * </pre>
     */
    protected function _createMailboxElt($elt)
    {
        $ob = new stdClass;

        if ($elt['children']) {
            $ob->ch = 1;
        }
        $ob->cl = $elt['class'];
        $ob->m = $elt['value'];
        if ($ob->m != $elt['name']) {
            $ob->l = $elt['name'];
        }
        if ($elt['parent'] != IMP_Imap_Tree::BASE_ELT) {
            $ob->pa = $elt['parent'];
        }
        if ($elt['polled']) {
            $ob->po = 1;
        }
        if ($elt['vfolder']) {
            $ob->v = $GLOBALS['imp_search']->isEditableVFolder($elt['value']) ? 2 : 1;
        }
        if (!$elt['sub']) {
            $ob->un = 1;
        }

        $tmp = IMP::getLabel($ob->m);
        if ($tmp != $ob->m) {
            $ob->t = $tmp;
        }

        if ($elt['container']) {
            $ob->cl = 'exp';
            $ob->co = 1;
            if ($elt['nonimap']) {
                $ob->n = 1;
            }
        } else {
            if ($elt['polled']) {
                $ob->u = intval($elt['unseen']);
            }

            if ($elt['special']) {
                $ob->s = 1;
            } elseif (!$elt['vfolder'] && $elt['children']) {
                $ob->cl = 'exp';
            }
        }

        if ($elt['user_icon']) {
            $ob->cl = 'customimg';
            $dir = empty($elt['icondir'])
                ? $GLOBALS['registry']->getImageDir()
                : $elt['icondir'];
            $ob->i = empty($dir)
                ? $elt['icon']
                : $dir . '/' . $elt['icon'];
        }

        return $ob;
    }

}
