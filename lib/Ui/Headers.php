<?php
/**
 * The IMP_Ui_Headers:: class is designed to provide a place to store common
 * code shared among IMP's various UI views for header information.
 *
 * Copyright 2009-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Ui_Headers
{
    /**
     * Determines the priority of the message based on the headers.
     *
     * @param Horde_Mime_Headers $header  The headers object.
     *
     * @return string  'high', 'low', or 'normal'.
     */
    public function getPriority($header)
    {
        if (preg_match('/\s*(\d+)\s*/', $header->getValue('x-priority'), $matches)) {
            if (in_array($matches[1], array(1, 2))) {
                return 'high';
            } elseif (in_array($matches[1], array(4, 5))) {
                return 'low';
            }
        } elseif (preg_match('/:\s*(\w+)\s*/', $header->getValue('importance'), $matches)) {
            if (strcasecmp($matches[1], 'high') === 0) {
                return 'high';
            } elseif (strcasecmp($matches[1], 'low') === 0) {
                return 'low';
            }
        }

        return 'normal';
    }

}
