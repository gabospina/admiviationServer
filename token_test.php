<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate a simple, known token for this test.
$_SESSION['test_token'] = 'THIS_IS_A_TEST_TOKEN_12345';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server POST Test</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
</head>
<body>
    <h1>Server POST Data Test</h1>
    <p>This page will test if the server is stripping form fields from a POST request.</p>

    <input type="hidden" id="test_token_field" value="<?php echo $_SESSION['test_token']; ?>">
    
    <button id="testButton">Run Test</button>
    
    <hr>
    <h2>Results:</h2>
    <pre id="results"></pre>

    <script>
        $('#testButton').on('click', function() {
            const tokenValue = $('#test_token_field').val();
            $('#results').text('Sending request...');

            $.ajax({
                url: 'token_handler.php',
                type: 'POST',
                data: {
                    some_other_field: 'This is normal data',
                    form_token: tokenValue // This is the field we are testing
                },
                dataType: 'json',
                success: function(response) {
                    // Display the raw JSON response from the server
                    $('#results').text(JSON.stringify(response, null, 2));
                },
                error: function(xhr) {
                    $('#results').text('AJAX Error:\n' + xhr.responseText);
                }
            });
        });
    </script>
</body>
</html>