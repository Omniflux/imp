<?php
/**
 * Copyright 2014-2016 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2014-2016 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Redirect log entry.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2016 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 *
 * @property-read string $recipients  List of recipients.
 */
class IMP_Maillog_Log_Redirect
extends IMP_Maillog_Log_Sentmail
{
    /**
     */
    protected $_action = 'redirect';

    /**
     * Message ID header label.
     *
     * @var string
     */
    protected $_msgidHeader = 'Resent-Message-ID';

    /**
     * List of recipients.
     *
     * @var string
     */
    protected $_recipients;

    /**
     * @param array $params  Parameters:
     *   - recipients: (string) Recipient list.
     */
    public function __construct(array $params = array())
    {
        $this->_recipients = strval($params['recipients']);
        parent::__construct($params);
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'recipients':
            return $this->_recipients;
        }

        return parent::__get($name);
    }

    /**
     */
    public function addData()
    {
        return array_merge(parent::addData(), array(
            'recipients' => $this->recipients
        ));
    }

    /**
     */
    protected function _getMessage()
    {
        return sprintf(
            _("You redirected this message on %s to: %s."),
            $this->date,
            $this->recipients
        );
    }

}
