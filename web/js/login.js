function init() {
    require(['dojo/parser','dijit/form/Button','dijit/form/CheckBox','dijit/form/Form',
             'dijit/form/Select','dijit/form/TextBox','dijit/form/ValidationTextBox'], function (parser) {
                 parser.parse();
                 //show tooltip node only after this widget is instaniated.
                 dojo.query('div[dojoType="dijit.Tooltip"]').style({
                     display:''
                 });
                 fetchProfiles();
                 dijit.byId("bw_limit").attr("checked", getCookie("ttrss_bwlimit") == 'true');
                 document.forms.loginForm.login.focus();
             });
}
function fetchProfiles()
{
    try {
        var query = "op=getProfiles&login=" + param_escape(document.forms["loginForm"].login.value);
        if (query) {
            new Ajax.Request("public.php", {
                parameters: query,
                onComplete: function (transport) {
                    if (transport.responseText.match("select")) {
                        $('profile_box').innerHTML = transport.responseText;
                        dojo.parser.parse('profile_box');
                    }
                } });
        }
    } catch (e) {
        exception_error("fetchProfiles", e);
    }
}
function gotoRegForm()
{
    window.location.href = "register.php";
    return false;
}
