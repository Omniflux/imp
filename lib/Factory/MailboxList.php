<?php
/**
 * Copyright 2010-2013 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2010-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * A Horde_Injector based IMP_Mailbox_List factory.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2013 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Factory_MailboxList extends Horde_Core_Factory_Base
{
    /** Session storage key for list objects. */
    const STORAGE_KEY = 'mboxlist/';

    /**
     * Instances.
     *
     * @var array
     */
    private $_instances = array();

    /**
     */
    public function __construct(Horde_Injector $injector)
    {
        parent::__construct($injector);

        register_shutdown_function(array($this, 'shutdown'));
    }

    /**
     * Return the mailbox list instance.
     *
     * @param string $mailbox  The mailbox name.
     *
     * @return IMP_Mailbox_List  The singleton instance.
     * @throws IMP_Exception
     */
    public function create($mailbox)
    {
        global $injector, $registry, $session;

        $key = strval($mailbox);

        if (!isset($this->_instances[$key])) {
            try {
                $ob = $session->get('imp', self::STORAGE_KEY . $key);
            } catch (Exception $e) {
                $ob = null;
            }

            if (is_null($ob)) {
                $mailbox = IMP_Mailbox::get($mailbox);
                if ($mailbox->search) {
                    $ob = new IMP_Mailbox_List_Virtual($mailbox);
                } else {
                    $ob = $injector->getInstance('IMP_Imap')->pop3
                        ? new IMP_Mailbox_List_Pop3($mailbox)
                        : new IMP_Mailbox_List($mailbox);
                }
            }

            $this->_instances[$key] = $ob;
        }

        return $this->_instances[$key];
    }

    /**
     * Tasks to perform on shutdown.
     */
    public function shutdown()
    {
        foreach ($this->_instances as $key => $val) {
            if ($val->changed) {
                $GLOBALS['session']->set('imp', self::STORAGE_KEY . $key, $val);
            }
        }
    }

    /**
     * Expires cached entries.
     */
    public function expireAll()
    {
        $GLOBALS['session']->remove('imp', self::STORAGE_KEY);
        $this->_instances = array();
    }

}
