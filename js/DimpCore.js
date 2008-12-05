var frames={horde_main:true},DimpCore={acount:0,remove_gc:[],server_error:0,view_id:1,buttons:["button_reply","button_forward","button_spam","button_ham","button_deleted"],debug:function(A,B){if(!this.is_logout&&DIMP.conf.debug){alert(A+": "+(B instanceof Error?B.name+"-"+B.message:Object.inspect(B)))}},toRangeString:function(A){var B="";$H(A).each(function(F){if(!F.value.size()){return}var D=F.value.numericSort(),E=last=D.shift(),C=[];D.each(function(G){if(last+1==G){last=G}else{C.push(E+(last==E?"":(":"+last)));E=last=G}});C.push(E+(last==E?"":(":"+last)));B+="{"+F.key.length+"}"+F.key+C.join(",")});return B},parseRangeString:function(G){var E,B,C,F,A={},D=[];G=G.strip();while(!G.blank()){if(!G.startsWith("{")){break}C=G.indexOf("}");E=parseInt(G.substr(1,C-1));F=G.substr(C+1,E);C+=E+1;B=G.indexOf("{",C);if(B==-1){uidstr=G.substr(C);G=""}else{uidstr=G.substr(C,B-C);G=G.substr(B)}uidstr.split(",").each(function(I){var H=I.split(":");if(H.size()==1){D.push(parseInt(I,10))}else{D=D.concat($A($R(parseInt(H[0],10),parseInt(H[1],10))))}});A[F]=D}return A},doAction:function(D,E,C,F,B){var A={};if(!this.doActionOpts){this.doActionOpts={onException:function(G,H){this.debug("onException",H)}.bind(this),onFailure:function(G,H){this.debug("onFailure",G)}.bind(this)}}B=Object.extend(this.doActionOpts,B||{});E=$H(E);D=D.startsWith("*")?D.substring(1):DIMP.conf.URI_IMP+"/"+D;if(C){if(C.viewport_selection){C.get("dataob").each(function(G){if(!A[G.view]){A[G.view]=[]}A[G.view].push(G.imapuid)});C=A}E.set("uid",DimpCore.toRangeString(C))}if(DIMP.conf.SESSION_ID){E.update(DIMP.conf.SESSION_ID.toQueryParams())}B.parameters=E.toQueryString();B.onComplete=function(G,H){this.doActionComplete(G,F)}.bind(this);new Ajax.Request(D,B)},doActionComplete:function(C,E){this.inAjaxCallback=true;var A=false,B={};if(!C.responseText||!C.responseText.length){A=true}else{try{B=C.responseText.evalJSON(true)}catch(D){this.debug("doActionComplete",D);A=true}}if(!B.msgs){B.msgs=[]}if(A){if(++this.server_error==3){this.showNotifications([{type:"horde.error",message:DIMP.text.ajax_timeout}])}this.inAjaxCallback=false;return}if(B.response&&Object.isFunction(E)){if(DIMP.conf.debug){E(B)}else{try{E(B)}catch(D){}}}if(this.server_error>=3){B.msgs.push({type:"horde.success",message:DIMP.text.ajax_recover})}this.server_error=0;if(!B.msgs_noauto){this.showNotifications(B.msgs)}if(this.onDoActionComplete){this.onDoActionComplete(B)}this.inAjaxCallback=false},setTitle:function(A){document.title=DIMP.conf.name+" :: "+A},showNotifications:function(A){if(!A.size()||this.is_logout){return}A.find(function(D){switch(D.type){case"dimp.timeout":this.is_logout=true;this.redirect(DIMP.conf.timeout_url);return true;case"horde.error":case"horde.message":case"horde.success":case"horde.warning":case"imp.reply":case"imp.forward":case"imp.redirect":case"dimp.request":case"dimp.sticky":var H,I,K,E,J,F,G=$("alerts"),B=new Element("DIV",{className:D.type.replace(".","-")}),C=D.message;if(!G){G=new Element("DIV",{id:"alerts"});$(document.body).insert(G)}if($w("dimp.request dimp.sticky").indexOf(D.type)==-1){C=C.unescapeHTML().unescapeHTML()}G.insert(B.update(C));if(DIMP.conf.is_ie6){K=new Element("DIV",{className:"ie6alertsfix"}).clonePosition(B,{setLeft:false,setTop:false});H=K;K.insert(B.remove());G.insert(K)}else{H=B}I=Effect.Fade.bind(this,B,{duration:1.5,afterFinish:this.removeAlert.bind(this)});H.observe("click",I);if($w("horde.error dimp.request dimp.sticky").indexOf(D.type)==-1){I.delay(D.type=="horde.warning"?10:3)}if(D.type=="dimp.request"){J=function(){I();document.stopObserving("click",J)};document.observe("click",J)}if(F=$("alertslog")){switch(D.type){case"horde.error":E=DIMP.text.alog_error;break;case"horde.message":E=DIMP.text.alog_message;break;case"horde.success":E=DIMP.text.alog_success;break;case"horde.warning":E=DIMP.text.alog_warning;break}if(E){F=F.down("DIV UL");if(F.down().hasClassName("noalerts")){F.down().remove()}F.insert(new Element("LI").insert(new Element("P",{className:"label"}).insert(E)).insert(new Element("P",{className:"indent"}).insert(C).insert(new Element("SPAN",{className:"alertdate"}).insert("["+(new Date).toLocaleString()+"]"))))}}}},this)},toggleAlertsLog:function(){var A=$("alertsloglink").down("A"),C=$("alertslog").down("DIV"),B={duration:0.5};if(C.visible()){Effect.BlindUp(C,B);A.update(DIMP.text.showalog)}else{Effect.BlindDown(C,B);A.update(DIMP.text.hidealog)}},removeAlert:function(C){try{var A=$(C.element),B=A.up();if(B&&B.parentNode){this.addGC(A.remove());if(!B.childElements().size()&&B.hasClassName("ie6alertsfix")){this.addGC(B.remove())}}}catch(D){this.debug("removeAlert",D)}},compose:function(C,B){var A=DIMP.conf.compose_url;B=B||{};if(C){B.type=C}this.popupWindow(this.addURLParam(A,B),"compose"+new Date().getTime())},popupWindow:function(B,A){if(!(window.open(B,A.replace(/\W/g,"_"),"width="+DIMP.conf.popup_width+",height="+DIMP.conf.popup_height+",status=1,scrollbars=yes,resizable=yes"))){this.showNotifications([{type:"horde.warning",message:DIMP.text.popup_block}])}},closePopup:function(){if(this.inAjaxCallback){this.closePopup.bind(this).defer()}else{window.close()}},logout:function(){this.is_logout=true;this.redirect(DIMP.conf.URI_IMP+"/LogOut")},redirect:function(A){A=this.addSID(A);if(parent.frames.horde_main){parent.location=A}else{window.location=A}},addMouseEvents:function(A){this.DMenu.addElement(A.id,"ctx_"+A.type,A)},removeMouseEvents:function(A){this.DMenu.removeElement($(A).readAttribute("id"));this.addGC(A)},addPopdown:function(B,A){var C=$(B);C.insert({after:$($("popdown_img").cloneNode(false)).writeAttribute("id",B+"_img").show()});this.addMouseEvents({id:B+"_img",type:A,offset:C.up(),left:true})},buildAddressLinks:function(D,A){var E,C,B=D.size();if(B>15){C=$("largeaddrspan").cloneNode(true);A.insert(C);E=C.down(".dispaddrlist");C=C.down();this.clickObserveHandler({d:C,f:function(F){[F.down(),F.down(1),F.next()].invoke("toggle")}.curry(C)});C=C.down();C.setText(C.getText().replace("%d",B))}else{E=A}D.each(function(H,G){var F;if(H.raw){F=H.raw}else{F=new Element("A",{className:"address",id:"addr"+this.acount++,personal:H.personal,email:H.inner,address:H.address}).insert(H.display?H.display:H.address);F.observe("mouseover",function(){F.stopObserving("mouseover");this.addMouseEvents({id:F.id,type:"contacts",offset:F,left:true})}.bind(this))}E.insert(F);if(G+1!=B){E.insert(", ")}},this);return A},removeAddressLinks:function(A){[A.select(".address"),A.select(".largeaddrtoggle")].flatten().compact().each(this.removeMouseEvents.bind(this))},messageOnLoad:function(){var B=this.clickObserveHandler,A;if($("partlist")){B({d:$("partlist_col").up(),f:function(){$("partlist","partlist_col","partlist_exp").invoke("toggle")}})}if(A=$("msg_print")){B({d:A,f:function(){window.print()}})}if(A=$("msg_view_source")){B({d:A,f:function(){view(DimpCore.addSID(DIMP.conf.URI_VIEW)+"&index="+DIMP.conf.msg_index+"&mailbox="+DIMP.conf.msg_folder,DIMP.conf.msg_index+"|"+DIMP.conf.msg_folder)}})}B({d:$("ctx_contacts_new"),f:function(){this.compose("new",{to:this.DMenu.element().readAttribute("address")})}.bind(this),ns:true});B({d:$("ctx_contacts_add"),f:function(){this.doAction("AddContact",{name:this.DMenu.element().readAttribute("personal"),email:this.DMenu.element().readAttribute("email")},null,true)}.bind(this),ns:true});if($("alertslog")){B({d:$("alertsloglink"),f:this.toggleAlertsLog.bind(this)})}},addGC:function(A){this.remove_gc=this.remove_gc.concat(A)},clickObserveHandler:function(A){return A.d.observe("click",DimpCore._clickFunc.curry(A))},_clickFunc:function(B,A){B.p?B.f(A):B.f();if(!B.ns){A.stop()}},addSID:function(A){if(!DIMP.conf.SESSION_ID){return A}return this.addURLParam(A,DIMP.conf.SESSION_ID.toQueryParams())},addURLParam:function(A,C){var B=A.indexOf("?");if(B!=-1){C=$H(A.toQueryParams()).merge(C).toObject();A=A.substring(0,B)}return A+"?"+Object.toQueryString(C)},reloadMessage:function(A){if(typeof DimpFullmessage!="undefined"){window.location=this.addURLParam(document.location.href,A)}else{DimpBase.loadPreview(null,A)}}};if(typeof ContextSensitive!="undefined"){DimpCore.DMenu=new ContextSensitive()}document.observe("dom:loaded",function(){try{if(parent.opener&&parent.opener.location.host==window.location.host&&parent.opener.DimpCore){DIMP.baseWindow=parent.opener.DIMP.baseWindow||parent.opener}}catch(A){}if(!DIMP.conf.spam_reporting){DimpCore.buttons=DimpCore.buttons.without("button_spam")}if(!DIMP.conf.ham_reporting){DimpCore.buttons=DimpCore.buttons.without("button_ham")}new PeriodicalExecuter(function(){if(DimpCore.remove_gc.size()){try{$A(DimpCore.remove_gc.splice(0,75)).compact().invoke("stopObserving")}catch(B){DimpCore.debug("remove_gc[].stopObserving",B)}}},10)});Event.observe(window,"load",function(){DimpCore.window_load=true});Element.addMethods({setText:function(B,C){var A=0;$A(B.childNodes).each(function(D){if(D.nodeType==3){if(A++){Element.remove(D)}else{D.nodeValue=C}}});if(!A){$(B).insert(C)}},getText:function(B,A){var C="";$A(B.childNodes).each(function(D){if(D.nodeType==3){C+=D.nodeValue}else{if(A&&D.hasChildNodes()){C+=$(D).getText(true)}}});return C}});Object.extend(Array.prototype,{numericSort:function(){return this.sort(function(B,A){if(B>A){return 1}else{if(B<A){return-1}}return 0})}});Object.extend(String.prototype,{evalScripts:function(){var re=/function\s+([^\s(]+)/g;this.extractScripts().each(function(s){var func;eval(s);while(func=re.exec(s)){window[func[1]]=eval(func[1])}})}});function popup_imp(C,A,D,B){DimpCore.compose("new",B.toQueryParams().toObject())}function view(A,B){window.open(A,++DimpCore.view_id+B.replace(/\W/g,"_"),"menubar=yes,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes")};