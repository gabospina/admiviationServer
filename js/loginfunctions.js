function logIn(form, password) {
    if ($(form).find("#user").val() != "" && $(form).find("#password").val() != "") {
        var pass = document.createElement("input");

        form.appendChild(pass);
        pass.name = "pass";
        pass.type = "hidden";
        pass.value = hex_sha512(password.value);

        password.value = "";

        form.submit();
        return true;
    } else {
        $("#loginError").text("Please enter your username and password.");
    }
}
function signUp(form) {
    var accountName = $(form).find("#account").val(),
        accountNationality = $(form).find("#nationality").val(),
        // maxSeven = $(form).find("#maxSeven").val(),
        // max28 = $(form).find("#max28").val(),
        // maxShifts = $(form).find("#maxShifts").val(),
        fname = $(form).find("#firstname").val(),
        lname = $(form).find("#lastname").val(),
        userNationality = $(form).find("#user-nationality").val(),
        email = $(form).find("#email").val(),
        phone = $(form).find("#phone").val(),
        username = $(form).find("#username").val(),
        usernameValid = $(form).find("#username").hasClass("input-success"),
        password = $(form).find("#password").val(),
        confpassword = $(form).find("#confpassword").val();

    console.log("accountName:", accountName);
    console.log("accountNationality:", accountNationality);
    console.log("fname:", fname);
    console.log("lname:", lname);
    console.log("email:", email);
    console.log("username:", username);
    console.log("usernameValid:", usernameValid);
    console.log("password:", password);
    console.log("confpassword:", confpassword);

    if (accountName != "" && accountNationality != "") {
        // if(!isNaN(parseInt(maxSeven)) && !isNaN(parseInt(max28)) && !isNaN(parseInt(maxShifts))){
        if (fname != "" && lname != "" && email != "") {
            if (username != "" && usernameValid && password != "" && password == confpassword) {
                if ($("#user-agreement-check").prop("checked")) {
                    var pass = document.createElement("input");

                    form.appendChild(pass);
                    pass.name = "pass";
                    pass.type = "hidden";
                    pass.value = hex_sha512(password);
                    $(form).find("#password, #confpassword").val("");
                    form.submit();
                    return true;
                } else {
                    var n = noty({
                        layout: "top",//layout, // top, topLeft, topCenter, topRight, centerLeft, center, centerRight, bottomLeft, bottomCenter, bottomRight, bottom
                        type: "error",     // alert, success, error, warning, information, confirm (needs button options)
                        text: "Please read and agree to our terms of use.",     // text or HTML
                        timeout: 10000,  // time in ms before notification disappears
                        killer: true    // "kills" all other notifications
                    });
                }
            } else {
                //log in credentials invalid
                var n = noty({
                    layout: "top",//layout, // top, topLeft, topCenter, topRight, centerLeft, center, centerRight, bottomLeft, bottomCenter, bottomRight, bottom
                    type: "error",     // alert, success, error, warning, information, confirm (needs button options)
                    text: "Please fill out Log-In credentials and ensure they are correct2",     // text or HTML
                    timeout: 10000,  // time in ms before notification disappears
                    killer: true    // "kills" all other notifications
                });
            }
        } else {
            //required data not filled out
            var n = noty({
                layout: "top",//layout, // top, topLeft, topCenter, topRight, centerLeft, center, centerRight, bottomLeft, bottomCenter, bottomRight, bottom
                type: "error",     // alert, success, error, warning, information, confirm (needs button options)
                text: "Please fill out required user information",     // text or HTML
                timeout: 10000,  // time in ms before notification disappears
                killer: true    // "kills" all other notifications
            });
        }
        // }else{
        //     //max data wrong
        //     var n = noty({
        //         layout: "top",//layout, // top, topLeft, topCenter, topRight, centerLeft, center, centerRight, bottomLeft, bottomCenter, bottomRight, bottom
        //         type: "error",     // alert, success, error, warning, information, confirm (needs button options)
        //         text: "Please enter valid information for maximum hours and shifts",     // text or HTML
        //         timeout: 10000,  // time in ms before notification disappears
        //         killer: true    // "kills" all other notifications
        //     });
        // }
    } else {
        //account info blank
        var n = noty({
            layout: "top",//layout, // top, topLeft, topCenter, topRight, centerLeft, center, centerRight, bottomLeft, bottomCenter, bottomRight, bottom
            type: "error",     // alert, success, error, warning, information, confirm (needs button options)
            text: "Please fill out your account name and nationality",     // text or HTML
            timeout: 10000,  // time in ms before notification disappears
            killer: true    // "kills" all other notifications
        });
    }
}
$(document).ready(function () {
    var error = window.location.search.substring(7);
    $("#loginError").text(error.replace(/%20/g, " "));

    $("#loginForm #password").keydown(function (e) {
        if (e.which == 13) {
            $("#loginBtn").trigger("click");
        }
    })

    $("#signUpForm #password").keyup(function () {
        $("#signUpForm #confpassword").removeClass("input-error");
    })
    $("#signUpForm #confpassword").keyup(function () {
        if ($(this).val() != $("#signUpForm #password").val()) {
            $(this).addClass("input-error");
        } else {
            $(this).removeClass("input-error");
        }
    })
    $("#signUpForm #confpassword").focus(function () {
        if ($(this).val() != $("#signUpForm #password").val()) {
            $(this).addClass("input-error");
        } else {
            $(this).removeClass("input-error");
        }
    })

    $("#signUpForm #username").keyup(function () {
        $(this).val($.trim($(this).val()));
        var that = this;
        if ($(this).val().length < 4) {
            $(this).removeClass("input-success").addClass("input-error");
        } else {
            $.ajax({
                type: "GET",
                data: { username: $(this).val() },
                url: "../assets/php/check_username.php",
                success: function (result) {
                    if (result == "not taken") {
                        $(that).removeClass("input-error").addClass("input-success");
                        $("#usernameTaken").text("");
                    } else {
                        $(that).removeClass("input-success").addClass("input-error");
                        $("#usernameTaken").html(" <strong>This username is taken</strong>");
                    }
                }
            })
        }
    })
});

