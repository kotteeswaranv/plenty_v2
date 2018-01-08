var $ = jQuery.noConflict();
var nnButton, nnIfrmButton, iframeWindow, targetOrigin;
nnButton = nnIfrmButton = iframeWindow = targetOrigin = false;

function initIframe()
{
    var request = {
        callBack: 'createElements',
        customStyle: {
			labelStyle : 'color:#001122;font-size:12px',
			inputStyle : 'border-color:blue',
			card_holder : {
			labelStyle : 'color:#001122;font-size:12px',
			inputStyle : 'border-color:blue',
			},
			card_number : {
			labelStyle : 'color:#001122;font-size:12px',
			inputStyle : 'border-color:blue',
			},
			expiry_date : {
			labelStyle : 'color:#001122;font-size:12px',
			inputStyle : 'border-color:blue',
			},
			cvc : {
			labelStyle : 'color:#001122;font-size:12px',
			inputStyle : 'border-color:blue',
			},
			styleText : 'body{background-color:#DDDDDD}div{float:left}.label-group{float:right}'
		}
    };

    var iframe = $('#nniframe')[0];
    iframeWindow = iframe.contentWindow ? iframe.contentWindow : iframe.contentDocument.defaultView;
    targetOrigin = 'https://secure.novalnet.de';
    iframeWindow.postMessage(JSON.stringify(request), targetOrigin);
}





function getHash(e)
{
	if($('#nn_pan_hash').val().trim() == '') {
	//e.preventDefault();
	iframeWindow.postMessage(
		JSON.stringify(
			{
			'callBack': 'getHash',
			}
		), targetOrigin
	);
	}
}

function reSize()
{
    if ($('#nniframe').length > 0) {
        var iframe = $('#nniframe')[0];
        iframeWindow = iframe.contentWindow ? iframe.contentWindow : iframe.contentDocument.defaultView;
        targetOrigin = 'https://secure.novalnet.de/';
        iframeWindow.postMessage(JSON.stringify({'callBack' : 'getHeight'}), targetOrigin);
    }
}

function novalnetCcIframe()
{
    $('#cc_loading').hide();
}

window.addEventListener(
    'message', function (e) {
    var data = (typeof e.data === 'string') ? eval('(' + e.data + ')') : e.data;
    if (e.origin === 'https://secure.novalnet.de') {
        if (data['callBack'] == 'getHash') {
            if (data['error_message'] != undefined) {
                alert($('<textarea />').html(data['error_message']).text());
            } else {
		$('#nn_pan_hash').val(data['hash']);
                $('#unique_id').val(data['unique_id']);
                $('#cc_form').submit();
            }
        }

        if (data['callBack'] == 'getHeight') {
            $('#nniframe').attr('height', data['contentHeight']);
        }
    }
    }, false
);

$(document).ready(
    function () {
    $(window).resize(
        function() {
        reSize();
        }
    );
    }
);

