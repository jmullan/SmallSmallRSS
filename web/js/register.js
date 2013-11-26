function checkUsername() {
    try {
        var f = document.forms['register_form'];
        var login = f.login.value;
        if (login == "") {
            new Effect.Highlight(f.login);
            f.sub_btn.disabled = true;
            return false;
        }
        var query = "register.php?action=check&login=" + param_escape(login);
        new Ajax.Request(query, {
            onComplete: function(transport) {
                try {
                    var reply = transport.responseXML;
                    var result = reply.getElementsByTagName('result')[0];
                    var result_code = result.firstChild.nodeValue;
                    if (result_code == 0) {
                        new Effect.Highlight(f.login, {startcolor : '#00ff00'});
                        f.sub_btn.disabled = false;
                    } else {
                        new Effect.Highlight(f.login, {startcolor : '#ff0000'});
                        f.sub_btn.disabled = true;
                    }
                } catch (e) {
                    exception_error("checkUsername_callback", e);
                }
            } });
    } catch (e) {
        exception_error("checkUsername", e);
    }
    return false;
}

function validateRegForm() {
    try {
        var f = document.forms['register_form'];
        if (f.login.value.length == 0) {
            new Effect.Highlight(f.login);
            return false;
        }
        if (f.email.value.length == 0) {
            new Effect.Highlight(f.email);
            return false;
        }
        if (f.turing_test.value.length == 0) {
            new Effect.Highlight(f.turing_test);
            return false;
        }
        return true;
    } catch (e) {
        exception_error("validateRegForm", e);
        return false;
    }
}
