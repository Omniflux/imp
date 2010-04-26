<?php
/**
 * Implementation of IMP_Quota API for a generic hook function.  This
 * requires the quota hook to be set in config/hooks.php.
 *
 * You must configure this driver in imp/config/servers.php.  The driver
 * supports the following parameters:
 * <pre>
 * 'params' - (array) Parameters to pass to the quota function.
 * </pre>
 *
 * Copyright 2002-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Redinger <Michael.Redinger@uibk.ac.at>
 * @package IMP
 */
class IMP_Quota_Hook extends IMP_Quota
{
    /**
     * Get quota information (used/allocated), in bytes.
     *
     * @return array  An array with the following keys:
     *                'limit' = Maximum quota allowed
     *                'usage' = Currently used portion of quota (in bytes)
     * @throws IMP_Exception
     */
    public function getQuota()
    {
        try {
            $quota = Horde::callHook('quota', array($this->_params), 'imp');
        } catch (Horde_Exception_HookNotSet $e) {
            throw new IMP_Exception($e->getMessage());
        }

        if (count($quota) != 2) {
            Horde::logMessage('Incorrect number of return values from quota hook.', 'ERR');
            throw new IMP_Exception(_("Unable to retrieve quota"));
        }

        return array('usage' => $quota[0], 'limit' => $quota[1]);
    }

}
