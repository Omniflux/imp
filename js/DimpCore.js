/**
 * DimpCore.js - Dimp UI application logic.
 *
 * Copyright 2005-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

/* DimpCore object. */
var DimpCore = {
    // Vars used and defaulting to null/false:
    //   DMenu, Growler, inAjaxCallback, is_init, is_logout
    //   onDoActionComplete
    alarms: {},
    growler_log: true,
    server_error: 0,

    doActionOpts: {
        onException: function(r, e) { DimpCore.debug('onException', e); },
        onFailure: function(t, o) { DimpCore.debug('onFailure', t); },
        evalJS: false,
        evalJSON: true
    },

    debug: function(label, e)
    {
        if (!this.is_logout && window.console && window.console.error) {
            window.console.error(label, Prototype.Browser.Gecko ? e : $H(e).inspect());
        }
    },

    // Convert object to an IMP UID Range string. See IMP::toRangeString()
    // ob = (object) mailbox name as keys, values are array of uids.
    toRangeString: function(ob)
    {
        var str = '';

        $H(ob).each(function(o) {
            if (!o.value.size()) {
                return;
            }

            var u = o.value.numericSort(),
                first = u.shift(),
                last = first,
                out = [];

            u.each(function(k) {
                if (last + 1 == k) {
                    last = k;
                } else {
                    out.push(first + (last == first ? '' : (':' + last)));
                    first = last = k;
                }
            });
            out.push(first + (last == first ? '' : (':' + last)));
            str += '{' + o.key.length + '}' + o.key + out.join(',');
        });

        return str;
    },

    // Parses an IMP UID Range string. See IMP::parseRangeString()
    // str = (string) An IMP UID range string.
    parseRangeString: function(str)
    {
        var count, end, i, mbox, uidstr,
            mlist = {},
            uids = [];
        str = str.strip();

        while (!str.blank()) {
            if (!str.startsWith('{')) {
                break;
            }
            i = str.indexOf('}');
            count = Number(str.substr(1, i - 1));
            mbox = str.substr(i + 1, count);
            i += count + 1;
            end = str.indexOf('{', i);
            if (end == -1) {
                uidstr = str.substr(i);
                str = '';
            } else {
                uidstr = str.substr(i, end - i);
                str = str.substr(end);
            }

            uidstr.split(',').each(function(e) {
                var r = e.split(':');
                if (r.size() == 1) {
                    uids.push(Number(e));
                } else {
                    uids = uids.concat($A($R(Number(r[0]), Number(r[1]))));
                }
            });

            mlist[mbox] = uids;
        }

        return mlist;
    },

    // 'opts' -> ajaxopts, callback, uids
    doAction: function(action, params, opts)
    {
        params = $H(params);
        opts = opts || {};

        var ajaxopts = Object.extend(Object.clone(this.doActionOpts), opts.ajaxopts || {});

        if (opts.uids) {
            if (opts.uids.viewport_selection) {
                opts.uids = this.selectionToRange(opts.uids);
            }
            params.set('uid', this.toRangeString(opts.uids));
        }

        ajaxopts.parameters = this.addRequestParams(params);
        ajaxopts.onComplete = function(t, o) { this.doActionComplete(t, opts.callback); }.bind(this);

        new Ajax.Request(DIMP.conf.URI_AJAX + action, ajaxopts);
    },

    // 'opts' -> ajaxopts, callback
    submitForm: function(form, opts)
    {
        opts = opts || {};
        var ajaxopts = Object.extend(Object.clone(this.doActionOpts), opts.ajaxopts || {});
        ajaxopts.onComplete = function(t, o) { this.doActionComplete(t, opts.callback); }.bind(this);
        $(form).request(ajaxopts);
    },

    selectionToRange: function(s)
    {
        var b = s.getBuffer(),
            tmp = {};

        if (b.getMetaData('search')) {
            s.get('dataob').each(function(r) {
                if (!tmp[r.view]) {
                    tmp[r.view] = [];
                }
                tmp[r.view].push(r.imapuid);
            });
        } else {
            tmp[b.getView()] = s.get('uid');
        }

        return tmp;
    },

    // params - (Hash)
    addRequestParams: function(params)
    {
        var p = params.clone();

        if (DIMP.conf.SESSION_ID) {
            p.update(DIMP.conf.SESSION_ID.toQueryParams());
        }

        return p;
    },

    doActionComplete: function(request, callback)
    {
        this.inAjaxCallback = true;

        if (!request.responseJSON) {
            if (++this.server_error == 3) {
                this.showNotifications([ { type: 'horde.error', message: DIMP.text.ajax_timeout } ]);
            }
            this.inAjaxCallback = false;
            return;
        }

        var r = request.responseJSON;

        if (!r.msgs) {
            r.msgs = [];
        }

        if (r.response && Object.isFunction(callback)) {
            try {
                callback(r);
            } catch (e) {
                this.debug('doActionComplete', e);
            }
        }

        if (this.server_error >= 3) {
            r.msgs.push({ type: 'horde.success', message: DIMP.text.ajax_recover });
        }
        this.server_error = 0;

        this.showNotifications(r.msgs);

        if (r.response && this.onDoActionComplete) {
            this.onDoActionComplete(r.response);
        }

        this.inAjaxCallback = false;
    },

    setTitle: function(title)
    {
        document.title = DIMP.conf.name + ' :: ' + title;
    },

    showNotifications: function(msgs)
    {
        if (!msgs.size() || this.is_logout) {
            return;
        }

        msgs.find(function(m) {
            switch (m.type) {
            case 'horde.ajaxtimeout':
                this.logout(m.message);
                return true;

            case 'horde.alarm':
                if (!this.alarms[m.flags.alarm.id]) {
                    this.Growler.growl(m.flags.alarm.title + ': ' + m.flags.alarm.text, {
                        className: 'horde-alarm',
                        sticky: 1,
                        log: 1
                    });
                    this.alarms[m.flags.alarm.id] = 1;
                }
                break;

            case 'horde.error':
            case 'horde.message':
            case 'horde.success':
            case 'horde.warning':
                this.Growler.growl(m.message, {
                    className: m.type.replace('.', '-'),
                    life: (m.type == 'horde.error' ? 12 : 8),
                    log: 1
                });
                break;

            case 'imp.reply':
            case 'imp.forward':
            case 'imp.redirect':
                this.Growler.growl(m.message, {
                    className: m.type.replace('.', '-'),
                    life: 8
                });
                break;
            }
        }, this);
    },

    compose: function(type, args)
    {
        var url = DIMP.conf.URI_COMPOSE;
        args = args || {};
        if (type) {
            args.type = type;
        }
        this.popupWindow(this.addURLParam(url, args), 'compose' + new Date().getTime());
    },

    popupWindow: function(url, name)
    {
        if (!(window.open(url, name.replace(/\W/g, '_'), 'width=' + DIMP.conf.popup_width + ',height=' + DIMP.conf.popup_height + ',status=1,scrollbars=yes,resizable=yes'))) {
            this.showNotifications([ { type: 'horde.warning', message: DIMP.text.popup_block } ]);
        }
    },

    closePopup: function()
    {
        // Mozilla bug/feature: it will not close a browser window
        // automatically if there is code remaining to be performed (or, at
        // least, not here) unless the mouse is moved or a keyboard event
        // is triggered after the callback is complete. (As of FF 2.0.0.3 and
        // 1.5.0.11).  So wait for the callback to complete before attempting
        // to close the window.
        if (this.inAjaxCallback) {
            this.closePopup.bind(this).defer();
        } else {
            window.close();
        }
    },

    logout: function(url)
    {
        this.is_logout = true;
        this.redirect(url || (DIMP.conf.URI_AJAX + 'logOut'));
    },

    redirect: function(url, force)
    {
        var ptr = parent.frames.horde_main ? parent : window;

        ptr.location.assign(this.addURLParam(url));

        // Catch browsers that don't redirect on assign().
        if (force && !Prototype.Browser.WebKit) {
            (function() { ptr.location.reload(); }).delay(0.5);
        }
    },

    loadingImg: function(elt, id, show, offsetTop)
    {
        elt = $(elt);

        if (show) {
            elt.clonePosition(id, { offsetTop: offsetTop || 0, setHeight: false, setLeft: false, setWidth: false }).show();
        } else {
            elt.fade({ duration: 0.2 });
        }
    },

    toggleButtons: function(elts, disable)
    {
        elts.each(function(b) {
            [ b.up() ].invoke(disable ? 'addClassName' : 'removeClassName', 'disabled');
            if (this.DMenu) {
                this.DMenu.disable(b.identify() + '_img', true, disable);
            }
        }, this);
    },

    /* Add dropdown menus to addresses. */
    buildAddressLinks: function(alist, elt)
    {
        var base, tmp,
            cnt = alist.size();

        if (cnt > 15) {
            tmp = $('largeaddrspan').cloneNode(true).writeAttribute('id', 'largeaddrspan_active');
            elt.insert(tmp);
            base = tmp.down('.dispaddrlist');
            tmp = tmp.down('.largeaddrlist');
            tmp.setText(tmp.getText().replace('%d', cnt));
        } else {
            base = elt;
        }

        alist.each(function(o, i) {
            var a;
            if (o.raw) {
                a = o.raw;
            } else {
                a = new Element('A', { className: 'address' }).store({ personal: o.personal, email: o.inner, address: (o.personal ? (o.personal + ' <' + o.inner + '>') : o.inner) });
                if (o.personal) {
                    a.writeAttribute({ title: o.inner }).insert(o.personal.escapeHTML());
                } else {
                    a.insert(o.inner.escapeHTML());
                }
                this.DMenu.addElement(a.identify(), 'ctx_contacts', { offset: a, left: true });
            }
            base.insert(a);
            if (i + 1 != cnt) {
                base.insert(', ');
            }
        }, this);

        return elt;
    },

    /* Add message log info to message view. */
    updateMsgLog: function(log)
    {
        var tmp = '';
        log.each(function(entry) {
            tmp += '<li><span class="iconImg imp-' + entry.t + '"></span>' + entry.m + '</li>';
        });
        $('msgloglist').down('UL').update(tmp);
    },

    /* Removes event handlers from address links. */
    removeAddressLinks: function(id)
    {
        id.select('.address').each(function(elt) {
            this.DMenu.removeElement(elt.identify());
        }, this);
    },

    addURLParam: function(url, params)
    {
        var q = url.indexOf('?');
        params = $H(params);

        if (DIMP.conf.SESSION_ID) {
            params.update(DIMP.conf.SESSION_ID.toQueryParams());
        }

        if (q != -1) {
            params.update(url.toQueryParams());
            url = url.substring(0, q);
        }

        return params.size() ? (url + '?' + params.toQueryString()) : url;
    },

    reloadMessage: function(params)
    {
        if (typeof DimpFullmessage != 'undefined') {
            window.location = this.addURLParam(document.location.href, params);
        } else {
            DimpBase.loadPreview(null, params);
        }
    },

    /* Mouse click handler. */
    clickHandler: function(e)
    {
        if (e.isRightClick()) {
            return;
        }

        var elt = e.element(), id, tmp;

        while (Object.isElement(elt)) {
            id = elt.readAttribute('id');

            switch (id) {
            case 'largeaddrspan_active':
                tmp = elt.down();
                if (!tmp.next().visible() ||
                    e.element().hasClassName('largeaddrlist')) {
                    [ tmp.down(), tmp.down(1), tmp.next() ].invoke('toggle');
                }
                break;

            default:
                // CSS class based matching
                if (elt.hasClassName('unblockImageLink')) {
                    IMP.unblockImages(e);
                } else if (elt.hasClassName('toggleQuoteShow')) {
                    [ elt, elt.next() ].invoke('toggle');
                    elt.next(1).blindDown({ duration: 0.2, queue: { position: 'end', scope: 'showquote', limit: 2 } });
                } else if (elt.hasClassName('toggleQuoteHide')) {
                    [ elt, elt.previous() ].invoke('toggle');
                    elt.next().blindUp({ duration: 0.2, queue: { position: 'end', scope: 'showquote', limit: 2 } });
                } else if (elt.hasClassName('pgpVerifyMsg')) {
                    elt.replace(DIMP.text.verify);
                    DimpCore.reloadMessage({ pgp_verify_msg: 1 });
                    e.stop();
                } else if (elt.hasClassName('smimeVerifyMsg')) {
                    elt.replace(DIMP.text.verify);
                    DimpCore.reloadMessage({ smime_verify_msg: 1 });
                    e.stop();
                }
                break;
            }

            elt = elt.up();
        }
    },

    contextOnShow: function(e)
    {
        var tmp;

        switch (e.memo) {
        case 'ctx_contacts':
            tmp = $(e.memo).down('DIV.contactAddr');
            if (tmp) {
                tmp.next().remove();
                tmp.remove();
            }

            // Add e-mail info to context menu if personal name is shown on
            // page.
            if (e.element().retrieve('personal')) {
                $(e.memo)
                    .insert({ top: new Element('DIV', { className: 'sep' }) })
                    .insert({ top: new Element('DIV', { className: 'contactAddr' }).insert(e.element().retrieve('email').escapeHTML()) });
            }
            break;
        }
    },

    contextOnClick: function(e)
    {
        var baseelt = e.element();

        switch (e.memo.elt.readAttribute('id')) {
        case 'ctx_contacts_new':
            this.compose('new', { to: baseelt.retrieve('address') });
            break;

        case 'ctx_contacts_add':
            this.doAction('addContact', { name: baseelt.retrieve('personal'), email: baseelt.retrieve('email') }, {}, true);
            break;
        }
    },

    /* DIMP initialization function. */
    init: function()
    {
        if (this.is_init) {
            return;
        }
        this.is_init = true;

        if (typeof ContextSensitive != 'undefined') {
            this.DMenu = new ContextSensitive();
            document.observe('ContextSensitive:click', this.contextOnClick.bindAsEventListener(this));
            document.observe('ContextSensitive:show', this.contextOnShow.bindAsEventListener(this));
        }

        /* Add Growler notification handler. */
        this.Growler = new Growler({
            location: 'br',
            log: this.growler_log,
            noalerts: DIMP.text.noalerts
        });

        /* Add click handler. */
        document.observe('click', DimpCore.clickHandler.bindAsEventListener(DimpCore));

        /* Catch dialog actions. */
        document.observe('IMPDialog:success', function(e) {
            switch (e.memo) {
            case 'pgpPersonal':
            case 'pgpSymmetric':
            case 'smimePersonal':
                IMPDialog.noreload = true;
                this.reloadMessage({});
                break;
            }
        }.bindAsEventListener(this));

        /* Determine base window. Need a try/catch block here since, if the
         * page was loaded by an opener out of this current domain, this will
         * throw an exception. */
        try {
            if (parent.opener &&
                parent.opener.location.host == window.location.host &&
                parent.opener.DimpCore) {
                DIMP.baseWindow = parent.opener.DIMP.baseWindow || parent.opener;
            }
        } catch (e) {}
    }

};
