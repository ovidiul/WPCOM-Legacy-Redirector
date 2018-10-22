/**
 * Provides helpful preivew of what redirect URLs will look like.
 */
var fromURL = document.getElementById('from_url');
var redirectURL = document.getElementById('redirect_to');

function redirect_top_label( labelID, inputValue ) {
    labelID.onkeyup = function() {
        var postid = '';
        if ( labelID.value.match(/^\d+$/) && labelID === redirectURL) {
            var postid = '?p='
        }
        document.getElementById(inputValue).innerHTML = WPURLS.siteurl + postid + labelID.value;
    }
}
redirect_top_label( fromURL, 'from_url_value' );
redirect_top_label( redirectURL, 'redirect_to_value' );
