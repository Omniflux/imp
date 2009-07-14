<?php
/**
 * Functions required to create/initialize an IMP session.
 *
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Jon Parise <jon@horde.org>
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package IMP
 */
class IMP_Session
{
    /**
     * The preferred server based on the value from the login form.
     *
     * @var string
     */
    static public $prefServer = null;

    /**
     * Take information posted from a login attempt and try setting up
     * an initial IMP session. Handle Horde authentication, if
     * required, and only do enough work to see if the user can log
     * in. This function should only be called once, when the user
     * first logs in. On success, logs a message to the Horde log.
     *
     * Creates the 'imp' session variable with the following entries:
     * 'autologin'     -- Is autologin available?
     * 'cache'         -- Various IMP libraries can use this variable to cache
     *                    data.
     * 'file_upload'   -- If file uploads are allowed, the max size.
     * 'filteravail'   -- Can we apply filters manually?
     * 'imap'          -- Config for various IMAP resources (acl, admin,
     *                    namespace, quota)
     * 'imap_ob'       -- The serialized Horde_Imap_Client object.
     * 'logintasks'    -- Have the login tasks been completed?
     * 'maildomain'    -- See config/servers.php.
     * 'notepadavail'  -- Is listing of notepads available?
     * 'protocol'      -- Either 'imap' or 'pop'.
     * 'rteavail'      -- Is the HTML editor available?
     * 'search'        -- Settings used by the IMP_Search library.
     * 'server_key'    -- Server used to login.
     * 'smime'         -- Settings related to the S/MIME viewer.
     * 'smtp'          -- SMTP options ('host' and 'port')
     * 'showunsub'     -- Show unsusubscribed mailboxes on the folders screen.
     * 'tasklistavail' -- Is listing of tasklists available?
     * 'uniquser'      -- The unique user name.
     * 'view'          -- The imp view mode (currently dimp, imp, or mimp)
     *
     * @param string $imapuser  The username of the user.
     * @param string $password  The password of the user.
     * @param string $server    The server to use (see config/servers.php).
     *
     * @return boolean  True on success, false on failure.
     */
    static public function createSession($imapuser, $password, $server)
    {
        global $conf, $registry;

        /* We need both a username and password. */
        if (!strlen($imapuser) || !strlen($password)) {
            return false;
        }

        /* Create the imp session variable. */
        $_SESSION['imp'] = array(
            'cache' => array(),
            'imap' => array(),
            'logintasks' => false,
            'server_key' => $server,
            'showunsub' => false
        );
        $sess = &$_SESSION['imp'];

        /* Run the username through virtualhost expansion functions if
         * necessary. */
        if (!empty($conf['hooks']['vinfo'])) {
            try {
                $imapuser = Horde::callHook('_imp_hook_vinfo', array('username', $imapuser), 'imp');
            } catch (Horde_Exception $e) {}
        }

        /* Load the server configuration. */
        $ptr = $GLOBALS['imp_imap']->loadServerConfig($server);
        if ($ptr === false) {
            return false;
        }

        /* Determine the unique user name. */
        if (Horde_Auth::isAuthenticated()) {
            $sess['uniquser'] = Horde_Auth::removeHook(Horde_Auth::getAuth());
        } else {
            $sess['uniquser'] = $imapuser;
            if (!empty($ptr['realm'])) {
                $sess['uniquser'] .= '@' . $ptr['realm'];
            }
        }

        /* Create the Horde_Imap_Client object now. */
        if ($GLOBALS['imp_imap']->createImapObject($imapuser, $password, $server) === false) {
            unset($_SESSION['imp']);
            return false;
        }

        /* Do necessary authentication now (since Horde_Auth:: may need to set
         * values in Horde-land). */
        $auth_imp = new IMP_Auth();
        if ($auth_imp->authenticate($sess['uniquser'], array('password' => $password), true) !== true) {
            unset($_SESSION['imp']);
            return false;
        }

        /* Set the protocol. */
        $sess['protocol'] = isset($ptr['protocol']) ? $ptr['protocol'] : 'imap';

        /* Set the maildomain. */
        $maildomain = $GLOBALS['prefs']->getValue('mail_domain');
        $sess['maildomain'] = ($maildomain) ? $maildomain : $ptr['maildomain'];

        /* Store some basic IMAP server information. */
        if ($sess['protocol'] == 'imap') {
            foreach (array('acl', 'admin', 'namespace', 'quota') as $val) {
                if (isset($ptr[$val])) {
                    $sess['imap'][$val] = $ptr[$val];

                    /* 'admin' and 'quota' have password entries - encrypt
                     * these entries in the session if they exist. */
                    if (isset($ptr[$val]['params']['password'])) {
                        $sess['imap'][$val]['params']['password'] = Horde_Secret::write(IMP::getAuthKey(), $ptr[$val]['params']['password']);
                    }
                }
            }
        }

        /* Set the SMTP options, if needed. */
        if ($conf['mailer']['type'] == 'smtp') {
            $sess['smtp'] = array();
            foreach (array('smtphost' => 'host', 'smtpport' => 'port') as $key => $val) {
                if (!empty($ptr[$key])) {
                    $sess['smtp'][$val] = $ptr[$key];
                }
            }
        }

        /* Does the server allow file uploads? If yes, store the
         * value, in bytes, of the maximum file size. */
        $sess['file_upload'] = $GLOBALS['browser']->allowFileUploads();

        /* Is the 'mail/canApplyFilters' API call available? */
        try {
            if ($registry->call('mail/canApplyFilters')) {
                $sess['filteravail'] = true;
            }
        } catch (Horde_Exception $e) {}

        /* Is the 'tasks/listTasklists' call available? */
        if ($conf['tasklist']['use_tasklist'] &&
            $registry->hasMethod('tasks/listTasklists')) {
            $sess['tasklistavail'] = true;
        }

        /* Is the 'notes/listNotepads' call available? */
        if ($conf['notepad']['use_notepad'] &&
            $registry->hasMethod('notes/listNotepads')) {
            $sess['notepadavail'] = true;
        }

        /* Is the HTML editor available? */
        $imp_ui = new IMP_UI_Compose();
        $editor = $imp_ui->initRTE(null, true);
        $sess['rteavail'] = $editor->supportedByBrowser();

        /* Cache autologin check. */
        $sess['autologin'] = self::canAutologin();

        /* Set up search information for the session. */
        $GLOBALS['imp_search']->sessionSetup();

        IMP::loginLogMessage('login', __FILE__, __LINE__, PEAR_LOG_NOTICE);

        return true;
    }

    /**
     * Perform IMP login tasks.
     *
     * @param string $url  The URL to use for the Horde_LoginTasks redirect.
     */
    static public function loginTasks($url = null)
    {
        if (!empty($_SESSION['imp']['logintasks'])) {
            return;
        }

        /* Do login tasks. */
        $tasks = Horde_LoginTasks::singleton('imp', is_null($url) ? Horde::selfUrl(true, true, true) : $url);
        $tasks->runTasks();

        /* If the user wants to run filters on login, make sure they get
           run. */
        if ($GLOBALS['prefs']->getValue('filter_on_login')) {
            /* Run filters. */
            $imp_filter = new IMP_Filter();
            $imp_filter->filter('INBOX');
        }

        /* Check for drafts due to session timeouts. */
        $imp_compose = IMP_Compose::singleton();
        $imp_compose->recoverSessionExpireDraft();

        $_SESSION['imp']['logintasks'] = true;
    }

    /**
     * Returns the autologin server key.
     *
     * @return string  The server key, or null if none available.
     */
    static public function getAutoLoginServer()
    {
        if (($servers = $GLOBALS['imp_imap']->loadServerConfig()) === false) {
            return null;
        }

        $server_key = null;
        foreach ($servers as $key => $val) {
            if (is_null($server_key) && substr($key, 0, 1) != '_') {
                $server_key = $key;
            }
            if (self::isPreferredServer($val, $key)) {
                $server_key = $key;
                break;
            }
        }

        return $server_key;
    }

    /**
     * Returns whether we can log in without a login screen for $server_key.
     *
     * @param string $server_key  The server to check. Defaults to the
     *                            autologin server.
     * @param boolean $force      If true, check $server_key even if there is
     *                            more than one server available.
     *
     * @return mixed  The autologin user if autologin is available, or false.
     */
    static public function canAutoLogin($server_key = null, $force = false)
    {
        if (($servers = $GLOBALS['imp_imap']->loadServerConfig()) === false) {
            return false;
        }

        if (is_null($server_key) || !$force) {
            $auto_server = self::getAutoLoginServer();
            if (is_null($server_key)) {
                $server_key = $auto_server;
            }
        }

        if ((!empty($auto_server) || $force) &&
            Horde_Auth::getAuth() &&
            !empty($servers[$server_key]['hordeauth'])) {
            return (strcasecmp($servers[$server_key]['hordeauth'], 'full') == 0)
                ? Horde_Auth::getAuth()
                : Horde_Auth::getBareAuth();
        }

        return false;
    }

    /**
     * Determines if the given mail server is the "preferred" mail server for
     * this web server.  This decision is based on the global 'SERVER_NAME'
     * and 'HTTP_HOST' server variables and the contents of the 'preferred'
     * field in the server's definition.  The 'preferred' field may take a
     * single value or an array of multiple values.
     *
     * @param string $server  A complete server entry from the $servers hash.
     * @param string $key     The server key entry.
     *
     * @return boolean  True if this entry is "preferred".
     */
    static public function isPreferredServer($server, $key = null)
    {
        if (!is_null(self::$prefServer)) {
            return ($key == self::$prefServer);
        }

        if (!empty($server['preferred'])) {
            if (is_array($server['preferred'])) {
                if (in_array($_SERVER['SERVER_NAME'], $server['preferred']) ||
                    in_array($_SERVER['HTTP_HOST'], $server['preferred'])) {
                    return true;
                }
            } elseif (($server['preferred'] == $_SERVER['SERVER_NAME']) ||
                      ($server['preferred'] == $_SERVER['HTTP_HOST'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the initial URL.
     *
     * @param string $actionID  The action ID to perform on the initial page.
     * @param boolean $encode   If true the argument separator gets encoded.
     *
     * @return string  The initial URL.
     */
    static public function getInitialUrl($actionID = null, $encode = true)
    {
        /* TODO: For now, redirect MIMP to mailbox page. */
        if ($_SESSION['imp']['view'] == 'mimp') {
            $url = Horde_Util::addParameter(Horde::applicationUrl('mailbox-mimp.php', true), array('mailbox' => 'INBOX'));
            if (!empty($actionID)) {
                $url = Horde_Util::addParameter($url, array('actionID' => $actionID), null, false);
            }
            return $url;
        }

        /* Redirect DIMP to index page. */
        if ($_SESSION['imp']['view'] == 'dimp') {
            return Horde::applicationUrl('index-dimp.php', true);
        }

        $init_url = ($_SESSION['imp']['protocol'] == 'pop')
            ? 'INBOX'
            : $GLOBALS['prefs']->getValue('initial_page');

        $imp_search = new IMP_Search();

        if (!$GLOBALS['prefs']->getValue('use_vinbox') &&
            $imp_search->isVINBOXFolder($init_url)) {
            $init_url = 'folders.php';
        } elseif (($imp_search->createSearchID($init_url) == $init_url) &&
                  !$imp_search->isVFolder($init_url)) {
            $init_url = 'INBOX';
            if (!$GLOBALS['prefs']->isLocked('initial_page')) {
                $GLOBALS['prefs']->setValue('initial_page', $init_url);
            }
        }

        if ($init_url == 'folders.php') {
            $url = Horde_Util::addParameter(Horde::applicationUrl($init_url, !$encode), array_merge(array('folders_token' => Horde::getRequestToken('imp.folders')), IMP::getComposeArgs()), null, $encode);
        } else {
            $url = Horde_Util::addParameter(Horde::applicationUrl('mailbox.php', !$encode), array_merge(array('mailbox' => $init_url, 'mailbox_token' => Horde::getRequestToken('imp.mailbox')), IMP::getComposeArgs()), null, $encode);
        }

        if (!empty($actionID)) {
            $url = Horde_Util::addParameter($url, 'actionID', $actionID, $encode);
        }

        return $url;
    }
}
