// loginfunctions.js - CLEANED UP VERSION

function displayNoty(type, text) {
    new Noty({
        layout: "top",
        type: type,
        text: text,
        timeout: 10000,
        killer: true
    }).show();
}

function logIn(form) {
    const $form = $(form);
    const username = $form.find("#user").val().trim();
    const password = $form.find("#password").val();

    if (!username || !password) {
        $("#loginError").text("Please enter your username and password.");
        return;
    }

    // Disable the button to prevent multiple submissions
    $form.find("#submitLoginBtn, #loginBtn").prop('disabled', true).val('Logging In...');

    // Use .serialize() to gather ALL form data, including the hidden CSRF token
    const formData = $form.serialize();
    console.log('Data being sent to server:', formData);

    $.ajax({
        type: "POST",
        url: "login.php",
        data: formData,
        dataType: "json",
        success: function (response) {
            console.log("Raw Response from login.php:", response);
            if (response.success) {
                // Use the redirect URL provided by the server
                // window.location.href = "/admviation/home.php";
                window.location.href = response.redirect;
            } else {
                $("#loginError").text(response.error || "Invalid username or password.");
            }
        },
        error: function (xhr) {
            console.error("AJAX Error:", xhr.responseText);
            displayNoty("error", "Login failed due to a server error. Please try again.");
        },
        complete: function () {
            // Re-enable the button after the request is complete
            $form.find("#submitLoginBtn, #loginBtn").prop('disabled', false).val('Log In');
        }
    });
}

function signUp(form) {
    const $form = $(form);

    // Disable the button to prevent multiple submissions
    $('#submitSignupBtn').prop('disabled', true).val('Signing Up...');

    $.ajax({
        type: "POST",
        url: "login_signup.php", // The PHP script to handle the signup
        data: $form.serialize() + "&ajax=true", // Add ajax=true to the data
        success: function (response) {
            console.log("Raw Response:", response);
            if (response.trim() === "success") {
                // On success, redirect to the home page
                window.location.href = "home.php";
            } else {
                displayNoty("error", response); // Show any errors from PHP
            }
        },
        error: function (xhr) {
            // This handles network errors or if the PHP script has a fatal error.
            displayNoty("error", "A server error occurred during signup. Please try again.");
            console.error("AJAX Error (signup):", xhr.responseText);
        },
        complete: function () {
            // Re-enable the button whether the call succeeded or failed.
            $('#submitSignupBtn').prop('disabled', false).val('Sign Up Now!');
        }
    });
}

$(document).ready(function () {
    // --- LOGIN FORM HANDLING ---

    $('a[data-target="#loginModal"]').on('click', function (e) {
        e.preventDefault(); // Stop the default link behavior
        e.stopImmediatePropagation(); // STOP ALL OTHER CLICK HANDLERS ON THIS BUTTON
        $('#loginModal').modal('show'); // Force the modal to show
    });

    // Display any login errors passed via URL parameters (if any)
    var error = window.location.search.substring(7);
    if (error) {
        $("#loginError").text(decodeURIComponent(error.replace(/\+/g, " ")));
    }

    // Handler for the Login button (using the correct ID)
    $("#submitLoginBtn").on('click', function (e) {
        e.preventDefault(); // Prevent default form submission
        logIn($('#loginForm')[0]); // Call the logIn function
    });

    // Allow pressing 'Enter' in the password field to submit the login form
    $("#loginForm #password").keydown(function (e) {
        if (e.which === 13) {
            $("#submitLoginBtn").trigger("click");
        }
    });

    // --- SIGNUP WIZARD HANDLING ---

    // 1. Wizard Step Navigation
    var step = 1;
    // Hide all steps initially, then show the first one
    $("#signUpForm .step").hide();
    $("#signUpForm .step[data-step='1']").show();

    // The click handler for the "Next" and "Back" buttons
    $("#signUpForm .step-btn").click(function () {
        // Get the increment value from the button's data attribute
        step += parseInt($(this).data("increment"));

        // Ensure step number stays within bounds (1 to 3)
        if (step < 1) step = 1;
        if (step > 3) step = 3;

        // Hide all step containers
        $("#signUpForm .step").hide();

        // Show only the container for the new, current step
        $("#signUpForm .step[data-step='" + step + "']").show();
    });

    // 2. Final Submission Handler (The "Sign Up Now!" button) - COMPLETE VERSION
    $('#submitSignupBtn').on('click', function () {
        const form = $('#signUpForm')[0];

        // Get all values from the form fields
        const companyName = $("#signup_companyName").val().trim();
        const fname = $("#signup_firstname").val().trim();
        const lname = $("#signup_lastname").val().trim();
        const email = $("#signup_email").val().trim();
        const username = $("#signup_username").val().trim();
        const password = $("#signup_password").val();
        const confpassword = $("#signup_confpassword").val();

        // --- CORRECTED & COMPLETE CLIENT-SIDE VALIDATION ---

        // Check for required fields
        if (!companyName || !fname || !lname || !email || !username || !password) {
            displayNoty("error", "Please fill out all required fields marked with *.");
            return;
        }

        // Check if passwords match
        if (password !== confpassword) {
            displayNoty("error", "Passwords do not match. Please re-enter them.");
            return;
        }

        // Check if the username input has an error class from the live check
        if ($("#signup_username").hasClass("input-error")) {
            displayNoty("error", "The chosen username is not available or invalid. Please choose another.");
            return;
        }

        // ADDED BACK: Check if the agreement checkbox is checked directly
        if (!$("#user-agreement-check").is(":checked")) {
            displayNoty("error", "You must agree to the Terms of Use to sign up.");
            return;
        }

        // If all checks pass, call the AJAX signUp function
        signUp(form);
    });

    // 3. Password Confirmation Visual Feedback
    $("#signUpForm #signup_password").keyup(function () {
        // When typing in the first password field, remove any error from the confirmation field
        $("#signUpForm #signup_confpassword").removeClass("input-error");
    });

    $("#signUpForm #signup_confpassword").keyup(function () {
        if ($(this).val() !== $("#signUpForm #signup_password").val()) {
            $(this).addClass("input-error");
        } else {
            $(this).removeClass("input-error");
        }
    });

    // 4. Real-time Username Availability Check
    $("#signUpForm #signup_username").keyup(function () {
        const $this = $(this);
        const $feedbackSpan = $("#usernameTaken"); // The span next to the label
        const username = $this.val().trim();

        $this.val(username); // Update the input with the trimmed value

        // Clear feedback if username is too short
        if (username.length < 4) {
            $this.removeClass("input-success input-error");
            $feedbackSpan.text('').removeClass('text-success text-danger');
            return;
        }

        // Send the request to your PHP script to check the username
        $.ajax({
            type: "GET", // Use GET to match your check_username.php script
            url: "login_check_username.php",
            data: { username: username }, // Send username as a URL parameter
            dataType: "json",
            success: function (response) {
                // Check the 'status' field from your PHP response
                if (response.status === "available") {
                    $this.removeClass("input-error").addClass("input-success");
                    $feedbackSpan.text('✔ Available!').removeClass('text-danger').addClass('text-success');
                } else { // This handles 'taken' and any other 'invalid'/'error' statuses
                    $this.removeClass("input-success").addClass("input-error");
                    $feedbackSpan.text('✖ ' + response.message).removeClass('text-success').addClass('text-danger');
                }
            },
            error: function (xhr) {
                // Handle cases where the AJAX call itself fails
                console.error("Error checking username:", xhr.responseText);
                $feedbackSpan.text('✖ Error checking availability.').removeClass('text-success').addClass('text-danger');
            }
        });
    });
});