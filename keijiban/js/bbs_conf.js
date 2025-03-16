var USER_NAME = 'kasukabetennis';		// ユーザ名設定

var CHK_TITLE = 0;				// 0:タイトル空白時 DEF_TITLE を設定, 1:エラーにする

var CHK_AUTH = 0;				// 0:お名前空白時 DEF_AUTH を設定, 1:エラーにする

var DEF_TITLE = 'タイトルなし';	// タイトル 空白時の文字設定

var DEF_AUTH = 'ななしサマ';	// お名前 空白時の文字設定







var API_SERVER = location.hostname;

var APIURL_SELECT = 'http://' + API_SERVER + '/.api/bbs/select';

var APIURL_INSERT = 'http://' + API_SERVER + '/.api/bbs/insert';

var TITLE_LENGTH = 40;

var AUTHOR_LENGTH = 20;

var VIEW_NUM = 10;

