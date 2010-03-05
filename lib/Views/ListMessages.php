<?php
/**
 * Dynamic (dimp) message list logic.
 *
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Views_ListMessages
{
    /**
     * Returns a list of messages for use with ViewPort.
     *
     * @var array $args  TODO (applyfilter, initial)
     *
     * @return array  TODO
     */
    public function listMessages($args)
    {
        $mbox = $args['mbox'];
        $is_search = false;

        $sortpref = IMP::getSort($mbox);

        /* Check for quicksearch request. */
        if (strlen($args['qsearchmbox'])) {
            /* Create the search query. */
            $query = new Horde_Imap_Client_Search_Query();

            if (strlen($args['qsearchflag'])) {
                $query->flag($args['qsearchflag'], empty($args['qsearchflagnot']));
                $is_search = true;
            } elseif (strlen($args['qsearch'])) {
                $field = $GLOBALS['prefs']->getValue('dimp_qsearch_field');
                switch ($field) {
                case 'body':
                    $query->text($args['qsearch'], true);
                    break;

                case 'from':
                case 'subject':
                    $query->headerText($field, $args['qsearch']);
                    break;

                case 'to':
                    $query2 = new Horde_Imap_Client_Search_Query();
                    $query2->headerText('cc', $args['qsearch']);

                    $query3 = new Horde_Imap_Client_Search_Query();
                    $query3->headerText('bcc', $args['qsearch']);

                    $query->headerText('to', $args['qsearch']);
                    $query->orSearch(array($query2, $query3));
                    break;

                case 'all':
                default:
                    $query->text($args['qsearch'], false);
                    break;
                }

                $is_search = true;
            }

            /* Set the search in the IMP session. */
            if ($is_search) {
                $GLOBALS['imp_search']->createSearchQuery($query, array($args['qsearchmbox']), array(), _("Search Results"), $mbox);
            }
        } else {
            $is_search = $GLOBALS['imp_search']->isSearchMbox($mbox);
        }

        /* Set the current time zone. */
        Horde_Nls::setTimeZone();

        /* Run filters now. */
        if (!$is_search &&
            !empty($_SESSION['imp']['filteravail']) &&
            !empty($args['applyfilter']) ||
            (($mbox == 'INBOX') &&
             $GLOBALS['prefs']->getValue('filter_on_display'))) {
            $imp_filter = new IMP_Filter();
            $imp_filter->filter($mbox);
        }

        /* Generate the sorted mailbox list now. */
        $imp_mailbox = IMP_Mailbox::singleton($mbox);
        $sorted_list = $imp_mailbox->getSortedList();
        $msgcount = count($sorted_list['s']);

        /* Create the base object. */
        $result = $this->getBaseOb($mbox);
        $result->cacheid = $imp_mailbox->getCacheID();
        if (!empty($args['requestid'])) {
            $result->requestid = intval($args['requestid']);
        }
        $result->totalrows = $msgcount;
        if (!$args['initial']) {
            unset($result->label);
        }

        /* Mail-specific viewport information. */
        $md = &$result->metadata;
        if (!IMP::threadSortAvailable($mbox)) {
            $md->nothread = 1;
        }
        if ($args['initial'] || !is_null($args['sortby'])) {
            $md->sortby = intval($sortpref['by']);
        }
        if ($args['initial'] || !is_null($args['sortdir'])) {
            $md->sortdir = intval($sortpref['dir']);
        }

        /* Actions only done on 'initial' request. */
        if ($args['initial']) {
            if (IMP::isSpecialFolder($mbox)) {
                $md->special = 1;
            }
            if ($is_search) {
                $md->search = 1;
            }

            /* Generate flag array. */
            $md->flags = array_keys($GLOBALS['injector']->getInstance('IMP_Imap_Flags')->getList(array('imap' => true, 'mailbox' => $is_search ? null : $mbox)));
        }

        /* These entries may change during a session, so always need to
         * update them. */
        $md->readonly = intval($GLOBALS['imp_imap']->isReadOnly($mbox));

        /* Check for mailbox existence now. If there are no messages, there
         * is a chance that the mailbox doesn't exist. If there is at least
         * 1 message, we don't need this check. */
        if (empty($msgcount) && !$is_search) {
            if (!$GLOBALS['injector']->getInstance('IMP_Folder')->exists($mbox)) {
                $GLOBALS['notification']->push(sprintf(_("Mailbox %s does not exist."), IMP::getLabel($mbox)), 'horde.error');
            }

            return $result;
        }

        /* Check for UIDVALIDITY expiration. It is the first element in the
         * cacheid returned from the browser. If it has changed, we need to
         * purge the cached items on the browser (send 'reset' param to
         * ViewPort). */
        if (!$is_search &&
            !empty($args['cacheid']) &&
            !empty($args['cache'])) {
            $uid_expire = false;
            try {
                $status = $GLOBALS['imp_imap']->ob()->status($mbox, Horde_Imap_Client::STATUS_UIDVALIDITY);
                $parsed = $GLOBALS['imp_imap']->ob()->parseCacheId($args['cacheid']);
                $uid_expire = ($parsed['uidvalidity'] != $status['uidvalidity']);
            } catch (Horde_Imap_Cache_Exception $e) {
                $uid_expire = true;
            }

            if ($uid_expire) {
                $args['cache'] = array();
                $result->reset = $result->resetmd = 1;
            }
        }

        /* TODO: This can potentially be optimized for arrival time sort - if
         * the cache ID changes, we know the changes must occur at end of
         * mailbox. */
        if (!isset($result->reset) && !empty($args['change'])) {
            $result->update = 1;
        }

        /* Get the cached list. */
        $cached = $changed = array();
        if (!empty($args['cache'])) {
            $cached = $GLOBALS['imp_imap']->ob()->utils->fromSequenceString($args['cache']);
            if ($is_search) {
                $cached = array_flip($cached);
            } else {
                $cached = array_flip(reset($cached));

                /* Check for cached entries marked as changed via CONDSTORE
                 * IMAP extension. If changed, resend the entire entry to
                 * update the browser cache (done below). */
                if ($args['change'] && $args['cacheid']) {
                    if (!isset($parsed)) {
                        $parsed = $GLOBALS['imp_imap']->ob()->parseCacheId($args['cacheid']);
                    }
                    if (!empty($parsed['highestmodseq'])) {
                        try {
                            $res = $GLOBALS['imp_imap']->ob()->fetch($mbox, array(Horde_Imap_Client::FETCH_UID => 1), array('changedsince' => $parsed['highestmodseq']));
                            if (!empty($res)) {
                                $changed = array_flip(array_keys($res));
                            }
                        } catch (Horde_Imap_Client_Exception $e) {}
                    }
                }
            }
        }

        if (!empty($args['search_unseen'])) {
            /* Do an unseen search.  We know what messages the browser
             * doesn't have based on $cached. Thus, search for the first
             * unseen message not located in $cached. */
            $unseen_search = $imp_mailbox->unseenMessages(Horde_Imap_Client::SORT_RESULTS_MATCH, true);
            if (!($uid_search = array_diff($unseen_search['match'], array_keys($cached)))) {
                return $result;
            }
            $rownum = array_search(reset($uid_search), $sorted_list['s']);
        } elseif (!empty($args['search_uid'])) {
            $rownum = 1;
            foreach (array_keys($sorted_list['s'], $args['search_uid']) as $val) {
                if (empty($sorted_list['m'][$val]) ||
                    ($sorted_list['m'][$val] == $args['search_mbox'])) {
                    $rownum = $val;
                    break;
                }
            }
        } else {
            /* If this is the initial request for a mailbox, figure out the
             * starting location based on user's preferences. */
            $rownum = $args['initial']
                ? intval($imp_mailbox->mailboxStart($msgcount))
                : null;
        }

        /* Determine the row slice to process. */
        if (is_null($rownum)) {
            $slice_start = $args['slice_start'];
            $slice_end = $args['slice_end'];
        } else {
            $slice_start = $rownum - $args['before'];
            $slice_end = $rownum + $args['after'];
            if ($slice_start < 1) {
                $slice_end += abs($slice_start) + 1;
            } elseif ($slice_end > $msgcount) {
                $slice_start -= $slice_end - $msgcount;
            }

            $result->rownum = $rownum;
        }

        $slice_start = max(1, $slice_start);
        $slice_end = min($msgcount, $slice_end);

        /* Generate the message list and the UID -> rownumber list. */
        $data = $msglist = $rowlist = array();
        $uidlist = $this->_getUidList($slice_start, $slice_end, $sorted_list);
        foreach ($uidlist as $uid => $seq) {
            $msglist[$seq] = $sorted_list['s'][$seq];
            $rowlist[$uid] = $seq;
            /* Send browser message data if not already cached or if CONDSTORE
             * has indiciated that data has changed. */
            if (!isset($cached[$uid]) || isset($changed[$uid])) {
                $data[$seq] = 1;
            }
        }
        $result->rowlist = $rowlist;

        /* If we are updating the rowlist on the browser, and we have cached
         * browser data information, we need to send a list of messages that
         * have 'disappeared'. */
        if (isset($result->update)) {
            if (($slice_start != 0) &&
                ($slice_end != count($sorted_list['s']))) {
                $uidlist = $this->_getUidList(1, count($sorted_list['s']), $sorted_list);
            }

            $disappear = array();
            foreach (array_keys($cached) as $val) {
                if (!isset($uidlist[$val])) {
                    $disappear[] = $val;
                }
            }

            if (!empty($disappear)) {
                $result->disappear = $disappear;
            }
        }

        /* Build the list for rangeslice information. */
        if ($args['rangeslice']) {
            $slice = new stdClass;
            $slice->rangelist = array_keys($rowlist);
            $slice->view = $mbox;

            return $slice;
        }

        /* Build the overview list. */
        $result->data = $this->_getOverviewData($imp_mailbox, $mbox, array_keys($data), $is_search);

        /* Get unseen/thread information. */
        if (!$is_search) {
            try {
                if ($info = $GLOBALS['imp_imap']->ob()->status($mbox, Horde_Imap_Client::STATUS_UNSEEN)) {
                    $md->unseen = intval($info['unseen']);
                }
            } catch (Horde_Imap_Client_Exception $e) {}

            if ($sortpref['by'] == Horde_Imap_Client::SORT_THREAD) {
                $threadob = $imp_mailbox->getThreadOb();
                $imp_thread = new IMP_Imap_Thread($threadob);
                $md->thread = $imp_thread->getThreadTreeOb($msglist, $sortpref['dir']);
            }
        } else {
            $result->search = 1;
        }

        return $result;
    }

    /**
     * Generates the list of unique UIDs for the current mailbox.
     *
     * @param integer $start      The slice start.
     * @param integer $end        The slice end.
     * @param array $sorted_list  The sorted list array.
     *
     * @param array  UIDs as the keys, and sequence numbers as the values.
     */
    protected function _getUidList($start, $end, $sorted_list)
    {
        $ret = array();

        for ($i = $start; $i <= $end; ++$i) {
            $uid = $sorted_list['s'][$i] .
                (isset($sorted_list['m'][$i])
                    ? IMP::IDX_SEP . $sorted_list['m'][$i]
                    : '');
            if ($uid) {
                $ret[$uid] = $i;
            }
        }

        return $ret;
    }

    /**
     * Obtains IMAP overview data for a given set of message UIDs.
     *
     * @param IMP_Mailbox $imp_mailbox  An IMP_Mailbox:: object.
     * @param string $folder            The current folder.
     * @param array $msglist            The list of message sequence numbers
     *                                  to process.
     * @param boolean $search           Is this a search mbox?
     *
     * @return array  TODO
     * @throws Horde_Exception
     */
    private function _getOverviewData($imp_mailbox, $folder, $msglist, $search)
    {
        $msgs = array();

        if (empty($msglist)) {
            return $msgs;
        }

        /* Get mailbox information. */
        $overview = $imp_mailbox->getMailboxArray($msglist, array('headers' => true, 'structure' => $GLOBALS['prefs']->getValue('atc_flag')));
        $charset = Horde_Nls::getCharset();
        $imp_ui = new IMP_Ui_Mailbox($folder);
        $no_flags_hook = false;

        /* Display message information. */
        reset($overview['overview']);
        while (list(,$ob) = each($overview['overview'])) {
            /* Initialize the header fields. */
            $msg = array(
                'imapuid' => intval($ob['uid']),
                'menutype' => 'message',
                'view' => $ob['mailbox'],
            );

            /* Get all the flag information. */
            if (!$no_flags_hook) {
                try {
                    $ob['flags'] = array_merge($ob['flags'], Horde::callHook('msglist_flags', array($ob, 'dimp'), 'imp'));
                } catch (Horde_Exception_HookNotSet $e) {
                    $no_flags_hook = true;
                }
            }

            $flag_parse = $GLOBALS['injector']->getInstance('IMP_Imap_Flags')->parse(array(
                'atc' => isset($ob['structure']) ? $ob['structure'] : null,
                'flags' => $ob['flags'],
                'personal' => Horde_Mime_Address::getAddressesFromObject($ob['envelope']['to']),
                'priority' => $ob['headers']
            ));

            if (!empty($flag_parse)) {
                $msg['flag'] = array();
                foreach ($flag_parse as $val) {
                    $msg['flag'][] = $val['flag'];
                }
            }

            /* Specific flag checking. */
            if (in_array('\\draft', $ob['flags'])) {
                $msg['menutype'] = 'draft';
                $msg['draft'] = 1;
            }

            /* Format size information. */
            $msg['size'] = htmlspecialchars($imp_ui->getSize($ob['size']), ENT_QUOTES, $charset);

            /* Format the Date: Header. */
            $msg['date'] = htmlspecialchars($imp_ui->getDate($ob['envelope']['date']), ENT_QUOTES, $charset);

            /* Format the From: Header. */
            $getfrom = $imp_ui->getFrom($ob['envelope'], array('specialchars' => $charset));
            $msg['from'] = $getfrom['from'];

            /* Format the Subject: Header. */
            $msg['subject'] = $imp_ui->getSubject($ob['envelope']['subject'], true);

            /* Check to see if this is a list message. Namely, we want to
             * check for 'List-Post' information because that is the header
             * that gives the e-mail address to reply to, which is all we
             * care about. */
            if ($ob['headers']->getValue('list-post')) {
                $msg['listmsg'] = 1;
            }

            /* Need both UID and mailbox to create a unique ID string if
             * using a search mailbox.  Otherwise, use only the UID. */
            if ($search) {
                $msgs[$ob['uid'] . IMP::IDX_SEP . $ob['mailbox']] = $msg;
            } else {
                $msgs[$ob['uid']] = $msg;
            }
        }

        /* Allow user to alter template array. */
        try {
            $msgs = Horde::callHook('mailboxarray', array($msgs, 'dimp'), 'imp');
        } catch (Horde_Exception_HookNotSet $e) {}

        return $msgs;
    }

    /**
     * Prepare the base object used by the ViewPort javascript class.
     *
     * @param string $mbox  The mailbox name.
     *
     * @return object  The base ViewPort object.
     */
    public function getBaseOb($mbox)
    {
        $ob = new stdClass;
        $ob->cacheid = 0;
        $ob->data = array();
        $ob->label = IMP::getLabel($mbox);
        $ob->metadata = new stdClass;
        $ob->rowlist = array();
        $ob->totalrows = 0;
        $ob->view = $mbox;

        return $ob;
    }

}
