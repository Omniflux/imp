<?php
/**
 * The IMP_Filter:: class contains all functions related to handling
 * filtering messages in IMP.
 *
 * For full use, the following Horde API calls should be defined
 * (These API methods are not defined in IMP):
 *   mail/applyFilters
 *   mail/canApplyFilters
 *   mail/showFilters
 *   mail/blacklistFrom
 *   mail/showBlacklist
 *   mail/whitelistFrom
 *   mail/showWhitelist
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Filter
{
    /**
     * Runs the filters if they are able to be applied manually.
     *
     * @param string $mbox  The mailbox to apply the filters to.
     * @throws Horde_Exception
     */
    public function filter($mbox)
    {
        if (empty($_SESSION['imp']['filteravail'])) {
            return;
        }

        $mbox_list = $GLOBALS['imp_search']->isSearchMbox($mbox)
            ? $GLOBALS['imp_search']->getSearchFolders($mbox)
            : array($mbox);

        foreach ($mbox_list as $val) {
            $GLOBALS['registry']->call('mail/applyFilters', array(array('mailbox' => $val)));
        }
    }

    /**
     * Adds the From address from the message(s) to the blacklist and deletes
     * the message(s).
     *
     * @param array $indices      See IMP::parseIndicesList().
     * @param boolean $show_link  Show link to the blacklist management in the
     *                            notification message?
     *
     * @return boolean  True if the messages(s) were deleted.
     * @throws Horde_Exception
     */
    public function blacklistMessage($indices, $show_link = true)
    {
        if ($this->_processBWlist($indices, _("your blacklist"), 'blacklistFrom', 'showBlacklist', $show_link)) {
            $imp_message = IMP_Message::singleton();

            if (($msg_count = $imp_message->delete($indices))) {
                if ($msg_count == 1) {
                    $GLOBALS['notification']->push(_("The message has been deleted."), 'horde.message');
                } else {
                    $GLOBALS['notification']->push(_("The messages have been deleted."), 'horde.message');
                }
                return true;
            }
        }

        return false;
    }

    /**
     * Adds the From address from the message(s) to the whitelist.
     *
     * @param array $indices      See IMP::parseIndicesList().
     * @param boolean $show_link  Show link to the whitelist management in the
     *                            notification message?
     *
     * @return boolean  True if the messages(s) were whitelisted.
     * @throws Horde_Exception
     */
    public function whitelistMessage($indices, $show_link = true)
    {
        return $this->_processBWlist($indices, _("your whitelist"), 'whitelistFrom', 'showWhitelist', $show_link);
    }

    /**
     * Internal function to handle adding addresses to [black|white]list.
     *
     * @param array $indices   See IMP::parseIndicesList().
     * @param string $descrip  The textual description to use.
     * @param string $reg1     The name of the mail/ registry call to use for
     *                         adding the addresses.
     * @param string $reg2     The name of the mail/ registry call to use for
     *                         linking to the filter management page.
     * @param boolean $link    Show link to the whitelist management in the
     *                         notification message?
     *
     * @return boolean  True on success.
     * @throws Horde_Exception
     */
    protected function _processBWlist($indices, $descrip, $reg1, $reg2, $link)
    {
        if (!($msgList = IMP::parseIndicesList($indices))) {
            return false;
        }

        /* Get the list of from addresses. */
        $addr = array();
        foreach ($msgList as $mbox => $msgIndices) {
            $GLOBALS['imp_imap']->checkUidvalidity($mbox);

            foreach ($msgIndices as $idx) {
                $contents = IMP_Contents::singleton($idx . IMP::IDX_SEP . $mbox);
                $hdr = $contents->getHeaderOb();
                $addr[] = Horde_Mime_Address::bareAddress($hdr->getValue('from'));
            }
        }

        $GLOBALS['registry']->call('mail/' . $reg1, array($addr));

        /* Add link to filter management page. */
        if ($link && $GLOBALS['registry']->hasMethod('mail/' . $reg2)) {
            $manage_link = Horde::link(Horde::url($GLOBALS['registry']->link('mail/' . $reg2)), sprintf(_("Filters: %s management page"), $descrip)) . _("HERE") . '</a>';
            $GLOBALS['notification']->push(sprintf(_("Click %s to go to %s management page."), $manage_link, $descrip), 'horde.message', array('content.raw'));
        }

        return true;
    }
}
