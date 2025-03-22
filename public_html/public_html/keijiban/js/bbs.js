/**

 * Emulates OmniOutliner's task view.  The check box marks a task complete.

 * It is a simulated form field with three states ...

 * 0=unchecked, 1=some children checked, 2=all children checked

 * When a task is clicked, the state of the nodes and parent and children

 * are updated, and this behavior cascades.

 *

 * @extends YAHOO.widget.TextNode

 * @constructor

 * @param oData {object} a string or object containing the data that will

 * be used to render this node

 * @param oParent {YAHOO.widget.Node} this node's parent node

 * @param expanded {boolean} the initial expanded/collapsed state

 * @param checked {boolean} the initial checked/unchecked state

 */

YAHOO.widget.BBSNode = function(oData, oParent, expanded, checked) {

	if (oParent) { 

		this.init(oData, oParent, expanded);

		this.setUpLabel(oData);

		this.checked = checked;

	}

};



YAHOO.widget.BBSNode.prototype = new YAHOO.widget.TextNode();



/**

 * True if checkstate is 1 (some children checked) or 2 (all children checked),

 * false if 0.

 *

 * @type boolean

 */

YAHOO.widget.BBSNode.prototype.checked = true;



// split title 60 byte

YAHOO.widget.BBSNode.prototype.splitString = function( str, length ) {

	var len  = 0;

	splitStr = "";

	for( i = 0; len < length; i++ ) { 

        var code = str.charCodeAt(i); 

        len += ((code >= 0 && code <= 255) || (code >= 0xff61 && code <= 0xff9f)) ? 1 : 2;

        splitStr += str.substr(i, 1);

    }

	return splitStr;

}



// create page send HTML

YAHOO.widget.BBSNode.genPageSend = function( data ) {

	var body = new Array();

	var chkBack = data.offset > 0;

	var chkNext = eval(data.offset) + VIEW_NUM < data.count;



	var back_offset = eval(data.offset) - VIEW_NUM;

	if (chkBack) {

		body[body.length] = '<b><a href="index.html?o=' + back_offset + '&n=' + VIEW_NUM + '">前へ</a></b>';

		body[body.length] = '<a href="index.html?o=' + back_offset + '&n=' + VIEW_NUM + '">' + 

			'<img src="http://i.yimg.jp/images/geo/bbs/user/ar_back.gif" width="6" height="11" border=0 alt="前へ"></a>&nbsp;';

	}



	if (chkBack || chkNext) {

		var all = eval(data.count);

		for ( var i = 0; i < all; i += VIEW_NUM ) {

			var number = (i / VIEW_NUM) + 1;

			if( i == data.offset ) {

				body[body.length] = '<b>' + number + '</b>' + '&nbsp;';

			} else {

				body[body.length] = '<a href="index.html?o=' + i + '&n=' + VIEW_NUM + '">' + number + '</a>&nbsp;';

			}

		}

	}



	var next_offset = eval(data.offset) + VIEW_NUM;

	if(chkNext) {

		body[body.length] = '<a href="index.html?o=' + next_offset + '&n=' + VIEW_NUM + '">' + 

			'<img src="http://i.yimg.jp/images/geo/bbs/user/ar_next.gif" width="6" height="11" border=0 alt="次へ"></a>&nbsp;';

		body[body.length] = '<b><a href="index.html?o=' + next_offset + '&n=' + VIEW_NUM + '">次へ</a></b>';

	}



	return body.join('');

}



// Overrides YAHOO.widget.TextNode

YAHOO.widget.BBSNode.prototype.getNodeHtml = function() { 

	var title = new Array();

	var	classlebel = '';

	var csslabel = 'main';



	if( this.depth >0){

		classlebel = '2';

		csslabel='res';

	}

	title[title.length] = 

		'<table width="100%" border="0" class="'+csslabel+'cont">' + 

		'<tr>';



	title[title.length] = 

			'<td id="cont' + this.data.id + '" class="' + csslabel + 'title">' + this.splitString(this.data.replyList.title, TITLE_LENGTH) + '</td>'; 



	title[title.length] = 

		'<td class="' + csslabel + 'author">' + this.splitString(this.data.replyList.author, AUTHOR_LENGTH);

 

	if ( this.data.replyList.em != "" ) {

		title[title.length] = '<a href="mailto:' + this.data.replyList.em + '" target="new">';

		title[title.length] = '<img src="http://i.yimg.jp/images/geo/bbs/user/bbs_mail.gif" alt="メール" border="0" width="15" height="12"></a>';

	}

	if ( this.data.replyList.url != "" ) {

		title[title.length] = '<a href="' + this.data.replyList.url + '" target="new">';

		title[title.length] = '<img src="http://i.yimg.jp/images/geo/bbs/user/bbs_hp.gif" alt="ほーむぺーじ" border="0" width="15" height="12"></a>';

	}



	title[title.length] = 

		'　' + this.data.replyList.timestamp + '';



	if( this.depth <= 0) {

		title[title.length] = 

			'<input type=button value="返信" onClick="javascript:openCommentWindow(' + this.data.id + ')"' + 

				'title="このスレッドに投稿します" class="resBtn"' + 

				'onMouseover="this.className=\'rollResBtn\';" onMouseout="this.className=\'resBtn\';">';

	}



	title[title.length] = 

		'</td></tr>' + 

		'<tr><td colspan="2" class="' + csslabel + 'line"></td></tr>';



	title[title.length] = 

		'<tr><td colspan="2"><div class="'+csslabel+'msg">' + 

		'<p>' + this.data.replyList.body.replace(/\n/g, "<br>").replace(/\r/g, "") + '</p></div>' +

		'</td></tr>';



	title[title.length] = '</table>\n';



	return title.join('');



};



// treeview object

var tree = null;



// comment id

var comment_id = null;



var xy = null;



// node comment toggle style

function htmlToggle( index ) {

	var id = "comment" + index;



	document.getElementById(id).style.display = '';

	tree.getNodeByIndex(index).data.style = '';

}



// comment window open

function openCommentWindow(no) {



	closeCommentWindow();

	var resTitle = '';



	if (no == 0) {

		document.getElementById('handle').innerHTML = '<span>新しく投稿</span>';

		document.getElementById('title').value = '';

	} else {

		resTitle = document.getElementById('cont' + no).innerHTML;

		document.getElementById('handle').innerHTML = 

			'<span>Re: ' + resTitle + '　（返信を投稿）</span>';

		document.getElementById('title').value = "Re: " + resTitle;

	}



	document.getElementById('frmTitle').style.display = 'block';

	document.getElementById('formline').style.display = '';



	var width  = (YAHOO.util.Dom.getViewportWidth() - 427) / 2;

	var scrollTop  = document.body.scrollTop  || document.documentElement.scrollTop;

	var height = (YAHOO.util.Dom.getViewportHeight() - 400) / 2 + scrollTop;



	if (height < 0) {

		height = 0;

	}



	xy = [width, height];

	YAHOO.util.Dom.setXY('formline', xy);

	comment_id = no;

	document.newMessage.p_msgid.value = no;

	document.newMessage.postb.disabled = false;

	document.newMessage.cancelb.disabled = false;



	if (no == 0) {

		document.newMessage.title.focus();

	} else {

		document.newMessage.author.focus();

	}

}



// close comment window

function closeCommentWindow() {

	document.getElementById('formline').style.display = 'none';

	document.newMessage.p_msgid.value = '';

	var oForm = document.newMessage.elements;

	for ( var i = 1; i < oForm.length -2; i++ ) {

		oForm[i].value = '';

	}

}



// get word length

function getWordByte(str){

	count = 0;

	for (i = 0; i < str.length; i++) {

		n = escape(str.charAt(i));

		if (n.length < 4) {

			count++;

		} else {

			count += 2;

		}

	}

	return count;

}



// check double byte

function checkDoubleByte(str) {

	for (i = 0; i < str.length; i++) {

		n = escape(str.charAt(i));

		if (n.length >= 4) {

			return true;

		}

	}

	return false;

}



// post comment message

function postMessage() {

	var valList = new Array();

	valList[valList.length] = 'bbsname=' + USER_NAME;

	var error = false;



	var oForm = document.newMessage.elements;

	for (var i = 1; i < oForm.length-2 && !error; i++) {



		if (oForm[i].name == 'title') {

			if (oForm[i].value == '') {

				if (CHK_TITLE == 1) {

					alert('エラー : タイトルを入力して下さい。');

					error = true;

					document.newMessage.title.focus();

				} else {

					valList[valList.length] = oForm[i].name + '=' + encodeURIComponent(DEF_TITLE);

				}

			} else if(getWordByte(oForm[i].value) > 255) {

				alert('エラー : タイトルが長すぎます。');

				error = true;

			} else {

				valList[valList.length] = oForm[i].name + '=' + encodeURIComponent(oForm[i].value);

			}

		} else if (oForm[i].name == 'author') {

			if (oForm[i].value == '') {

				if (CHK_AUTH == 1) {

					alert('エラー : お名前を入力して下さい。');

					error = true;

					document.newMessage.author.focus();

				} else {

					valList[valList.length] = oForm[i].name + '=' + encodeURIComponent(DEF_AUTH);

				}

			} else if(getWordByte(oForm[i].value) > 255) {

				alert('エラー : 名前が長すぎます。');

				error = true;

			} else {

				valList[valList.length] = oForm[i].name + '=' + encodeURIComponent(oForm[i].value);

			}

		} else {

			valList[valList.length] = oForm[i].name + '=' + encodeURIComponent(oForm[i].value);

			if (oForm[i].name == 'body') {

				if (oForm[i].value == '') {

					alert('エラー : 本文を入力して下さい。');

					error = true;

					document.newMessage.body.focus();

				} else if(getWordByte(oForm[i].value) > 4000) {

					alert('エラー : 本文が長すぎます。');

					error = true;

				}

			} else if (oForm[i].name == 'em' && oForm[i].value != '') {

				if (getWordByte(oForm[i].value) > 255) {

					alert('エラー : メールアドレスが長すぎます。');

					error = true;

				} else if(checkDoubleByte(oForm[i].value)) {

					alert('エラー : メールアドレスは半角で入力してください。');

					error = true;

				} else if(!oForm[i].value.match(/.+@.+\..+/)) {

					alert('エラー : メールアドレス形式が間違っています。');

					error = true;

				}

			} else if (oForm[i].name == 'url' && oForm[i].value != '') {

				if (getWordByte(oForm[i].value) > 255) {

					alert('エラー : URLが長すぎます。');

					error = true;

				} else if(checkDoubleByte(oForm[i].value)) {

					alert('エラー : URLは半角で入力してください。');

					error = true;

				} else if(!oForm[i].value.match(/https?:\/\//)) {

					alert('エラー : URL形式が間違っています。');

					error = true;

				}

			}

		}

	}



	var callback = {

		success: function(result) {

			var responseText = eval('(' + result.responseText + ')');



			// エラーチェック

			if ((typeof(responseText.rc) != 'number') || (responseText.rc != 0)) {

				alert(responseText.errmsg);

				document.newMessage.postb.disabled = false;

				document.newMessage.cancelb.disabled = false;

			}

			else {

//				alert("ご投稿ありがとうございます。");

				document.newMessage.postb.disabled = false;

				document.newMessage.cancelb.disabled = false;

				closeCommentWindow();

				treeInit();

			}

			return;

		},

		failure: function() { alert("サーバーとの通信中にエラーが発生しました。"); }

	}

	if(!error) {

		document.newMessage.postb.disabled = true;

		document.newMessage.cancelb.disabled = true;

		var cObj = YAHOO.util.Connect.asyncRequest('POST', APIURL_INSERT, callback, valList.join('&'));

	}

}



// ajax responce callback function

var buildTreeCallback = function(result) {

	var responseText = eval('(' + result.responseText + ')');



	// check responce error

	if ((typeof(responseText.rc) != 'number') || (responseText.rc != 0)) {

		alert(responseText.errmsg);

		return;

	}



	var pNode = result.argument;

	var replyList = (typeof(responseText.replyList) == 'object') ? responseText.replyList : [];



	for (var i = 0 in replyList) {



		// myObj object substance

		// label : title

		// id    : message id

		// html  : comment

		// children : reply comment num

		// style : comment style, none or ''(blank)

		var myObj = {

			replyList: replyList[i],

			id:       replyList[i].msgid,

			children: replyList[i].cnt,

			style:    ''

		}

		// create BBSNode for this tree

		var tmpNode = new YAHOO.widget.BBSNode(myObj, pNode, true);

	}

	// create page send html

	var strPage = YAHOO.widget.BBSNode.genPageSend(responseText);

	document.getElementById('ygbbspg1').innerHTML = strPage;

	document.getElementById('ygbbspg2').innerHTML = strPage;



	tree.draw();

}



// request API for get build tree data

function buildTree(node, onCompleteCallback) {

	// -- code to get your data, possibly using Connect --

	var callback = {

		success: buildTreeCallback,

		failure: function() { alert("サーバーとの通信中にエラーが発生しました。"); },

		argument: node

	}

	var argv = new Array();

	argv[argv.length] = '?bbsname=' + USER_NAME;

	argv[argv.length] = 'msgid=' + node.data.id;



	var QS = new Array;

	if (location.search.length > 1) {

		var m_Array = location.search.substr(1).split("&"); 

		for (idx in m_Array) {

			var tmp = m_Array[idx].split("=");

			if(tmp[0] == 'o') {

				argv[argv.length] = 'o=' + tmp[1];

			}

		}

	}



	// send request

	var cObj = YAHOO.util.Connect.asyncRequest('GET', APIURL_SELECT + argv.join('&'), callback);



	// Be sure to notify the TreeView component when the data load is complete

	onCompleteCallback();

}



var move = function(e) {

	xy = [YAHOO.util.Event.getPageX(e), YAHOO.util.Event.getPageY(e)];

	if( comment_id != document.newMessage.p_msgid.value) { 

		YAHOO.util.Dom.setXY('formline', xy);

	}

}



// onload function

function treeInit() {

	tree = new YAHOO.widget.TreeView("contents");

	tree.setDynamicLoad(buildTree);



	//YAHOO.util.Event.addListener(document, 'click', move);



	dd = new ygDDOnTop("formline");

	dd.setHandleElId("handle");

	document.getElementById('formline').style.display = 'none';



	YAHOO.util.Event.addListener(document, 'click', move);



	var root = tree.getRoot();

	root.data = { id: '0' };

	buildTree(root, function(){});

}

