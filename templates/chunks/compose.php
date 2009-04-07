<?php
/**
 * compose.php - Used by DIMP_Views_Compose:: to render the compose screen.
 *
 * Variables passed in from calling code:
 *   $args('folder', 'index'), $compose_html, $draft_index, $from, $id,
 *   $identity, $composeCache, $rte, $selected_identity, $sent_mail_folder
 *
 * Copyright 2005-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

$d_read = $GLOBALS['prefs']->getValue('disposition_request_read');
$save_attach = $GLOBALS['prefs']->getValue('save_attachments');

/* Determine if compose mode is disabled. */
$compose_disable = !empty($GLOBALS['conf']['hooks']['disable_compose']) &&
                   Horde::callHook('_imp_hook_disable_compose', array(), 'imp');

// Small utility function to simplify creating dimpactions buttons.
// As of right now, we don't show text only links.
function _createDAcompose($text, $image, $id)
{
    $params = array('icon' => $image, 'id' => $id);
    if (!in_array($GLOBALS['prefs']->getValue('menu_view'), array('text', 'both'))) {
        $params['tooltip'] = $text;
    } else {
        $params['title'] = $text;
    }
    echo DIMP::actionButton($params);
}

?>
<div id="composeloading"></div>
<form id="compose" name="compose" enctype="multipart/form-data" action="compose-dimp.php" method="post" target="submit_frame">
<?php echo Util::formInput() ?>
<input type="hidden" id="action" name="action" />
<input type="hidden" id="last_identity" name="last_identity" value="<?php echo (int)$selected_identity ?>" />
<input type="hidden" id="html" name="html" value="<?php echo intval($rte && $compose_html) ?>" />
<input type="hidden" id="in_reply_to" name="in_reply_to" />
<input type="hidden" id="references" name="references" />
<input type="hidden" id="folder" name="folder" value="<?php echo $args['folder'] ?>" />
<input type="hidden" id="index" name="index" value="<?php echo $args['index'] ?>" />
<input type="hidden" id="draft_index" name="draft_index" value="<?php echo $draft_index ?>" />
<input type="hidden" id="reply_type" name="reply_type" />
<input type="hidden" id="composeCache" name="composeCache" value="<?php echo $composeCache ?>" />

<div class="dimpActions dimpActionsCompose">
<?php if (!$compose_disable): ?>
 <span>
  <?php _createDAcompose(_("Send"), 'Forward', 'send_button') ?>
 </span>
<?php endif; ?>
 <span>
  <?php _createDAcompose(_("Check Spelling"), 'Spellcheck', 'spellcheck') ?>
 </span>
 <span>
  <?php _createDAcompose(_("Save as Draft"), 'Drafts', 'draft_button') ?>
 </span>
</div>

<div id="writemsg" class="noprint">
 <div class="msgwrite">
  <div class="dimpOptions">
   <label><input id="togglecc" name="togglecc" type="checkbox" class="checkbox" /> <?php echo _("Show Cc") ?></label>
   <label><input id="togglebcc" name="togglebcc" type="checkbox" class="checkbox" /> <?php echo _("Show Bcc") ?></label>
<?php if ($rte): ?>
   <label><input id="htmlcheckbox" type="checkbox" class="checkbox"<?php if ($compose_html) echo 'checked="checked"' ?> /> <?php echo _("HTML composition") ?></label>
<?php endif; ?>
<?php if ($GLOBALS['conf']['compose']['allow_receipts'] && $d_read != 'never'): ?>
   <label><input name="request_read_receipt" type="checkbox" class="checkbox"<?php if ($d_read != 'ask') echo ' checked="checked"' ?> /> <?php echo _("Read Receipt") ?></label>
<?php endif; ?>
<?php if ($GLOBALS['conf']['user']['allow_folders'] && !$GLOBALS['prefs']->isLocked('save_sent_mail')): ?>
   <label><input id="save_sent_mail" name="save_sent_mail" type="checkbox" class="checkbox"<?php if ($identity->saveSentmail()) echo ' checked="checked"' ?> /> <?php echo _("Save in ") ?><span id="sent_mail_folder_label"><?php echo $sent_mail_folder ?></span></label>
<?php endif; ?>
  </div>
  <table cellspacing="0">
   <tr>
    <td class="label"><?php echo _("From: ") ?></td>
    <td>
     <select id="identity" name="identity">
<?php foreach ($identity->getSelectList() as $id => $from): ?>
      <option value="<?php echo htmlspecialchars($id) ?>"<?php if ($id == $selected_identity) echo ' selected="selected"' ?>><?php echo htmlspecialchars($from) ?></option>
<?php endforeach; ?>
     </select>
    </td>
   </tr>
   <tr id="sendto">
    <td class="label"><span><?php echo _("To: ") ?></span></td>
    <td>
     <textarea id="to" name="to" rows="1" cols="75"></textarea>
     <div id="to_results" class="autocomplete" style="display:none"></div>
    </td>
    <td class="autocompleteImg">
     <span id="to_loading_img" class="loadingImg" style="display:none"></span>
    </td>
   </tr>
   <tr id="sendcc" style="display:none">
    <td class="label"><span><?php echo _("Cc: ") ?></span></td>
    <td>
     <textarea id="cc" name="cc" rows="1" cols="75"></textarea>
     <div id="cc_results" class="autocomplete" style="display:none"></div>
    </td>
    <td class="autocompleteImg">
     <span id="cc_loading_img" class="loadingImg" style="display:none"></span>
    </td>
   </tr>
   <tr id="sendbcc" style="display:none">
    <td class="label"><span><?php echo _("Bcc: ") ?></span></td>
    <td>
     <textarea id="bcc" name="bcc" rows="1" cols="75"></textarea>
     <div id="bcc_results" class="autocomplete" style="display:none"></div>
    </td>
    <td class="autocompleteImg">
     <span id="bcc_loading_img" class="loadingImg" style="display:none"></span>
    </td>
   </tr>
   <tr>
    <td class="label"><?php echo _("Subject: ") ?></td>
    <td class="subject"><input type="text" id="subject" name="subject" /></td>
   </tr>
   <tr class="atcrow">
    <td class="label"><span class="iconImg attachmentImg"></span>: </td>
    <td id="attach_cell">
     <input type="file" id="upload" name="file_1" />
<?php if (strpos($save_attach, 'prompt') !== false): ?>
     <label><input type="checkbox" class="checkbox" name="save_attachments_select"<?php if (strpos($save_attach, 'yes') !== false) echo ' checked="checked"' ?> /> <?php echo _("Save Attachments in sent folder") ?></label><br />
<?php endif; ?>
     <div id="attach_list"></div>
    </td>
   </tr>
  </table>
 </div>

 <div id="messageParent">
  <textarea name="message" rows="20" id="message" class="fixed"></textarea>
 </div>
</div>
</form>

<iframe name="submit_frame" id="submit_frame" style="display:none" src="javascript:false;"></iframe>
