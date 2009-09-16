/**
 * Provides the javascript for the search.php script (advanced view).
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 */

var ImpSearch = {
    // The following variables are defined in search.php:
    //   months, text, types
    criteria: {},
    saved_searches: {},
    show_unsub: false,

    _getAll: function()
    {
        return $('search_form').getInputs(null, 'search_folders_form[]');
    },

    selectFolders: function(checked)
    {
        this._getAll().each(function(e) {
            if (!e.disabled) {
                e.checked = Boolean(checked);
            }
        });
    },

    updateFolderList: function(folders)
    {
        var fragment = document.createDocumentFragment(),
            node = $($('folder_row').cloneNode(true)).writeAttribute('id', false).show();

        folders.each(function(f) {
            var n = $(node.cloneNode(true));
            n.down().writeAttribute({ disabled: !Boolean(f.v), value: (f.v ? f.v.escapeHTML() : null) }).insert({ after: f.l });
            fragment.appendChild(n);
        });

        $('search_folders_hdr').next('DIV').update('').appendChild(fragment);
    },

    updateSavedSearches: function(searches)
    {
        var fragment = document.createDocumentFragment(),
            node = new Element('OPTION');

        $('recent_searches_div').show();

        $H(searches).each(function(s) {
            fragment.appendChild($(node.cloneNode(false)).writeAttribute({ value: s.value.v.escapeHTML() }).update(s.value.l.escapeHTML()));
            this.saved_searches[s.key] = s.value.c;
        }, this);

        $('recent_searches').appendChild(fragment);
    },

    updateSearchCriteria: function(criteria)
    {
        this.resetCriteria();

        criteria.each(function(c) {
            switch (this.types[c.t]) {
            case 'header':
            case 'body':
            case 'text':
                this.insertText(c.t, c.v, c.n);
                break;

            case 'customhdr':
                this.insertCustomHdr(c.v, c.n);
                break;

            case 'size':
                this.insertSize(c.t, c.v);
                break;

            case 'date':
                this.insertDate(c.t, c.v);
                break;

            case 'flag':
                this.insertFlag(c.v);
                break;
            }
        }, this);
    },

    updateSelectedFolders: function(folders)
    {
        var tmp = $('search_folders_hdr').next();
        this.selectFolders(false);
        folders.each(function(f) {
            var i = tmp.down('INPUT[value=' + f + ']');
            if (i) {
                i.checked = true;
            }
        });
    },

    resetCriteria: function()
    {
        $('search_criteria_table').childElements().invoke('remove');
    },

    changeHandler: function(e)
    {
        var elt = e.element(), val = $F(elt);

        switch (elt.readAttribute('id')) {
        case 'recent_searches':
            this.updateSearchCriteria(this.saved_searches[$F(elt)]);
            elt.setValue('');
            break;

        case 'search_criteria':
            switch (this.types[val]) {
            case 'header':
            case 'body':
            case 'text':
                this.insertText(val);
                break;

            case 'customhdr':
                this.insertCustomHdr();
                break;

            case 'size':
                this.insertSize(val);
                break;

            case 'date':
                this.insertDate(val);
                break;

            case 'flag':
                this.insertFlag(val);
                break;
            }
            break;
        }

        e.stop();
    },

    getLabel: function(id)
    {
        return $('search_criteria').down('[value=' + id + ']').getText() + ': ';
    },

    insertCriteria: function(tds)
    {
        var tr = new Element('TR');
        tds.each(function(td) {
            tr.insert(new Element('TD').insert(td));
        });
        tr.childElements().last().insert(new Element('A', { href: '#', className: 'searchuiImg searchuiDelete' }));
        $('search_criteria').setValue('');
        $('search_criteria_table').insert(tr);
        return tr.identify();
    },

    insertText: function(id, text, not)
    {
        var tmp = [
            new Element('EM').insert(this.getLabel(id)),
            new Element('INPUT', { type: 'text', size: 25 }),
            new Element('SPAN').insert(new Element('INPUT', { checked: Boolean(not), className: 'checkbox', type: 'checkbox' })).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
    },

    insertCustomHdr: function(text, not)
    {
        text = text || { h: '', s: '' };

        var tmp = [
            new Element('EM').insert(this.text.customhdr),
            new Element('INPUT', { type: 'text', size: 25 }).setValue(text.h),
            new Element('SPAN').insert(new Element('EM').insert(this.text.search_term + ' ')).insert(new Element('INPUT', { type: 'text', size: 25 }).setValue(text.s)),
            new Element('SPAN').insert(new Element('INPUT', { checked: Boolean(not), className: 'checkbox', type: 'checkbox' })).insert(this.text.not_match)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: 'customhdr' };
    },

    insertSize: function(id, size)
    {
        var tmp = [
            new Element('EM').insert(this.getLabel(id)),
            // Convert from bytes to KB
            new Element('INPUT', { type: 'text', size: 10 }).setValue(Object.isNumber(size) ? Math.round(size / 1024) : '')
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
    },

    insertDate: function(id, data)
    {
        var d = (data ? new Date(data.y, data.m, data.d) : new Date()),
            tmp = [
                new Element('EM').insert(this.getLabel(id)),
                new Element('SPAN').insert(new Element('SPAN')).insert(new Element('A', { href: '#', className: 'calendarPopup', title: this.text.dateselection }).insert(new Element('SPAN', { className: 'searchuiImg searchuiCalendar' })))
            ];
        this.replaceDate(this.insertCriteria(tmp), id, { y: d.getFullYear(), m: d.getMonth(), d: d.getDate() });
    },

    replaceDate: function(id, type, data)
    {
        $(id).down('TD SPAN SPAN').update(this.months[data.m] + ' ' + data.d + ', ' + data.y);
        // Need to store date information at all times in criteria, since we
        // have no other way to track this information (there is not form
        // field for this type).
        this.criteria[id] = { t: type, v: data };
    },

    insertFlag: function(id)
    {
        var tmp = [
            new Element('EM').insert(this.text.flag),
            this.getLabel(id).slice(0, -2)
        ];
        this.criteria[this.insertCriteria(tmp)] = { t: id };
    },

    _submit: function()
    {
        var data = [], tmp;

        if (!this._getAll().findAll(function(i) { return i.checked; }).size()) {
            alert(this.text.need_folder);
        } else if ($F('vfolder_save') && $F('vfolder_label').empty()) {
            alert(this.text.need_vfolder_label);
        } else {
            tmp = $('search_criteria_table').childElements().pluck('id');
            if (tmp.size()) {
                tmp.each(function(c) {
                    var tmp2;

                    switch (this.types[this.criteria[c].t]) {
                    case 'header':
                    case 'body':
                    case 'text':
                        this.criteria[c].n = Number(Boolean($F($(c).down('INPUT[type=checkbox]'))));
                        this.criteria[c].v = $F($(c).down('INPUT[type=text]'));
                        data.push(this.criteria[c]);
                        break;

                    case 'customhdr':
                        this.criteria[c].v = { h: $F($(c).down('INPUT')), s: $F($(c).down('INPUT', 1)) };
                        data.push(this.criteria[c]);
                        break;

                    case 'size':
                        tmp2 = Number($F($(c).down('INPUT')));
                        if (!isNaN(tmp2)) {
                            // Convert KB to bytes
                            this.criteria[c].v = tmp2 * 1024;
                            data.push(this.criteria[c]);
                        }
                        break;

                    case 'date':
                        data.push(this.criteria[c]);
                        break;

                    case 'flag':
                        data.push({ t: 'flag', v: this.criteria[c].t });
                        break;
                    }
                }, this);
                $('criteria_form').setValue(data.toJSON());
                $('search_form').submit();
            } else {
                alert(this.text.need_criteria);
            }
        }
    },

    clickHandler: function(e)
    {
        if (e.isRightClick()) {
            return;
        }

        var id, tmp,
            elt = e.element();

        while (Object.isElement(elt)) {
            id = elt.readAttribute('id');

            switch (id) {
            case 'search_submit':
                this._submit();
                e.stop();
                return;

            case 'search_reset':
                this.resetCriteria();
                this.selectFolders(false);
                return;

            case 'link_sel_all':
            case 'link_sel_none':
                this.selectFolders(id == 'link_sel_all');
                e.stop();
                return;

            case 'link_sub':
                tmp = this._getAll();
                this.show_unsub = !this.show_unsub;
                $('search_folders_hdr').next('DIV').update(this.text.loading);
                new Ajax.Request($('search_form').readAttribute('action'), {
                    parameters: { show_unsub: Number(this.show_unsub) },
                    onComplete: this._showFoldersCallback.bind(this, tmp)
                });
                elt.childElements().invoke('toggle');
                e.stop();
                return;

            default:
                if (elt.hasClassName('arrowExpanded') ||
                    elt.hasClassName('arrowCollapsed')) {
                    elt.up().down().toggle().next().toggle().up().next().toggle();
                    if (elt.descendantOf('search_folders_hdr')) {
                        elt.next('SPAN.searchFoldersActions').toggle();
                    }
                } else if (elt.hasClassName('searchuiDelete')) {
                    tmp = elt.up('TR');
                    delete this.criteria[tmp.identify()];
                    tmp.remove();
                    e.stop();
                    return;
                } else if (elt.hasClassName('searchuiCalendar')) {
                    tmp = this.criteria[elt.up('TR').identify()];
                    Horde_Calendar.open(elt.identify(), { y: tmp.v.y, m: tmp.v.m + 1, d: tmp.v.d }, this.replaceDate.bind(this, elt.up('TR').identify(), tmp.t));
                    e.stop();
                    return;
                }
                break;
            }

            elt = elt.up();
        }
    },

    _showFoldersCallback: function(flist, r)
    {
        this.updateFolderList(r.responseJSON);
        this.updateSelectedFolders(flist);
    }

};

document.observe('change', ImpSearch.changeHandler.bind(ImpSearch));
document.observe('click', ImpSearch.clickHandler.bind(ImpSearch));
