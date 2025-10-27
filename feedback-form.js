// feedback-form.js - THE UNIFIED HANDLER for all contact/feedback forms

$(document).ready(function() {
    
    // --- Handler for the PUBLIC contact form on index.php (#sendMessage) ---
    $('#sendMessage').on('click', function() {
        const $button = $(this);
        const name = $('#msg-name').val().trim();
        const email = $('#msg-email').val().trim();
        const message = $('#msg-content').val().trim();
        const csrfToken = $('#publicContactForm input[name="csrf_token"]').val(); // Get CSRF token

        // Validation
        if (email === '' || message === '') {
            alert('Please provide an email and a message.');
            return;
        }

        $button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: base_url + 'feedback_contact_email.php', // Points to the new unified handler
            type: 'POST',
            data: {
                name: name,
                email: email,
                message: message,
                csrf_token: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert(response.message || 'Message Sent!');
                    $('#msg-name, #msg-email, #msg-content').val('');
                } else {
                    alert('Error: ' + (response.error || 'Could not send message.'));
                }
            },
            error: function(xhr) { // THE FIX IS HERE
                // Log the actual server response to the console for debugging
                console.error("AJAX Error Response:", xhr.responseText);
                alert('A network error occurred. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false).text('Send Message');
            }
        });
    });

    // --- Handler for the LOGGED-IN "Thoughts" form (#submitThoughts) ---
    $('#submitThoughts').on('click', function() {
        const $button = $(this);
        const name = $('#thoughtsName').val().trim();
        const email = $('#thoughtsEmail').val().trim();
        const message = $('#thoughtsMessage').val().trim();
        // No CSRF needed for logged-in users, but we send the data anyway
        
        if (message === '') {
            alert('Please enter a message before sending.');
            return;
        }

        $button.prop('disabled', true).text('Sending...');

        $.ajax({
            url: base_url + 'feedback_contact_email.php', // Also points to the new unified handler
            type: 'POST',
            data: {
                name: name,
                email: email,
                message: message
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#thoughtsFeedback').html('<div class="alert alert-success">' + (response.message || 'Message Sent!') + '</div>');
                    $('#thoughtsMessage').val(''); // Only clear the message
                } else {
                    $('#thoughtsFeedback').html('<div class="alert alert-danger">' + (response.error || 'Could not send message.') + '</div>');
                }
            },
            error: function(xhr) { // THE FIX IS HERE
                console.error("AJAX Error Response:", xhr.responseText);
                $('#thoughtsFeedback').html('<div class="alert alert-danger">A network error occurred. Please try again.</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Send');
            }
        });
    });
});