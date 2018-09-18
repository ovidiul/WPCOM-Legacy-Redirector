var fromURL = document.getElementById('from_url');
var redirectURL = document.getElementById('redirect_to');

function redirect_top_label( labelID, inputValue) {
    labelID.onkeyup = function() {
        document.getElementById(inputValue).innerHTML = WPURLS.siteurl + labelID.value;
    }
}
redirect_top_label( fromURL, 'from_url_value' );
redirect_top_label( redirectURL, 'redirect_to_value' );
