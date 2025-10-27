// js/ai-assistant.js - FINAL AND COMPLETE VERSION (with corrected filenames)

$(document).ready(function () {
    console.log("AI Assistant module loaded (Phase 3 - Execution).");

    // 1. Check for browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        console.warn("AI Assistant: Browser does not support Web Speech API.");
        $('#ai-assistant-btn').hide();
        return;
    }

    // 2. Initialize variables
    const recognition = new SpeechRecognition();
    const aiButton = $('#ai-assistant-btn');
    const aiButtonIcon = aiButton.find('i');
    const aiButtonText = aiButton.find('span');
    let isListening = false;

    // 3. Configure the Speech Recognition service
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = 'en-US';

    // 4. Define all event handlers for the recognition process

    recognition.onresult = function (event) {
        let interim_transcript = '';
        let final_transcript = '';

        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                final_transcript += event.results[i][0].transcript;
            } else {
                interim_transcript += event.results[i][0].transcript;
            }
        }

        if (interim_transcript) {
            aiButtonText.text(interim_transcript + '...');
        }

        if (final_transcript && isListening) {
            const commandText = final_transcript.trim();
            console.log("AI Assistant: Final command captured ->", commandText);
            console.log("Sending command:", commandText);
            console.log("Data being sent:", { command: commandText });

            isListening = false;
            recognition.stop();

            processVoiceCommand(commandText);
        }
    };

    recognition.onend = function () {
        console.log("AI Assistant: Recognition service ended.");
        if (isListening) {
            recognition.start();
        } else {
            aiButton.removeClass('listening');
            aiButtonIcon.removeClass('fa-stop-circle').addClass('fa-microphone');
            aiButtonText.text('AI Assistant');
        }
    };

    recognition.onerror = function (event) {
        console.error("AI Assistant Error:", event.error);
        isListening = false;
    };

    // 5. The main button click handler (toggles listening on/off)
    aiButton.on('click', function () {
        if (isListening) {
            isListening = false;
            recognition.stop();
        } else {
            aiButton.addClass('listening');
            aiButtonIcon.removeClass('fa-microphone').addClass('fa-stop-circle');
            aiButtonText.text('Listening...');
            isListening = true;
            recognition.start();
        }
    });

    // 6. Function to send the command to the PHP "AI Brain"
    function processVoiceCommand(commandText) {
        if (!commandText || commandText.trim() === '') {
            new Noty({ text: 'Error: Empty command received', type: 'error' }).show();
            return;
        }

        console.log("Sending command to AI Core:", commandText);
        aiButtonText.text('Processing...');
        aiButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: 'daily_manager_ai_process_command_v51.php',
            data: JSON.stringify({ command: commandText }),
            // contentType: 'application/json',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            dataType: 'json'
        })
            .done(function (response) {
                console.log("AI Core Response:", response);
                handleAiResponse(response);
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                new Noty({
                    text: 'Error: Could not communicate with the AI Assistant service. ' +
                        (errorThrown || textStatus),
                    type: 'error',
                    timeout: 5000
                }).show();
            })
            .always(function () {
                aiButton.prop('disabled', false);
                aiButtonText.text('AI Assistant');
            });
    }

    // 7. Function to interpret the AI's plan and pass it to the execution router
    function handleAiResponse(response) {
        if (!response || response.action === 'unknown') {
            new Noty({ text: "AI Assistant: I'm sorry, I didn't understand that command.", type: 'warning', timeout: 4000 }).show();
            return;
        }

        executeAiPlan(response.action, response.parameters);
    }

    // 8. === THE EXECUTION ROUTER ===
    function executeAiPlan(action, parameters) {
        console.log(`Executing AI Plan. Action: ${action}, Parameters:`, parameters);

        switch (action) {
            case 'CREATE_PILOT':
                if (!parameters.firstname || !parameters.lastname || !parameters.username) {
                    new Noty({ text: 'AI: I understood "create pilot", but I missed the full name or username. Please try again.', type: 'error', timeout: 4000 }).show();
                    return;
                }

                const pilotData = {
                    ...parameters,
                    password: 'Password123!',
                    confpassword: 'Password123!',
                    is_active: 1,
                    access_level: 1,
                    admin: 0
                };

                $.ajax({
                    type: 'POST',
                    // --- FILENAME UPDATED HERE ---
                    url: 'daily_manager_create_new_pilot.php',
                    data: pilotData,
                    dataType: 'json'
                })
                    .done(function (result) {
                        if (result.success) {
                            new Noty({ text: `AI: Successfully created pilot: ${parameters.firstname} ${parameters.lastname}`, type: 'success', timeout: 4000 }).show();
                        } else {
                            new Noty({ text: 'AI Error: ' + (result.error || result.message || 'Failed to create pilot.'), type: 'error', timeout: 4000 }).show();
                        }
                    })
                    .fail(function () {
                        new Noty({ text: 'AI Error: A server error occurred while creating the pilot.', type: 'error', timeout: 4000 }).show();
                    });
                break;

            case 'ADD_CRAFT':
                if (!parameters.registration || !parameters.craft_type) {
                    new Noty({ text: 'AI: I understood "add craft", but I missed the registration or type. Please try again.', type: 'error', timeout: 4000 }).show();
                    return;
                }

                const craftData = {
                    craft: parameters.craft_type,
                    registration: parameters.registration,
                    alive: parameters.alive,
                    tod: 'day'
                };

                $.ajax({
                    type: 'POST',
                    // --- FILENAME UPDATED HERE ---
                    url: 'daily_manager_create_craft_type.php',
                    data: craftData,
                    dataType: 'json'
                })
                    .done(function (result) {
                        if (result.success) {
                            new Noty({ text: `AI: Successfully added craft: ${parameters.registration}`, type: 'success', timeout: 4000 }).show();
                            if (typeof loadAndBuildManagerCraftsTable === 'function') {
                                loadAndBuildManagerCraftsTable();
                            }
                        } else {
                            new Noty({ text: 'AI Error: ' + (result.message || 'Failed to add craft.'), type: 'error', timeout: 4000 }).show();
                        }
                    })
                    .fail(function () {
                        new Noty({ text: 'AI Error: A server error occurred while adding the craft.', type: 'error', timeout: 4000 }).show();
                    });
                break;

            default:
                console.warn("AI: Unknown action received:", action);
                break;
        }
    }
});