<?php
/**
 * Change sentmail_id column to autoincrement.
 *
 * Copyright 2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.fsf.org/copyleft/gpl.html GPL
 * @package  IMP
 */
class ImpAutoIncrementSentmail extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $t->changeColumn('imp_sentmail', 'sentmail_id', 'integer', array('autoincrement' => true));
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        // No way to downgrade at this time.
    }

}
