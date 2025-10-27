$(document).ready(function () {
    console.log("AI Assistant module loaded (Phase 3 - Execution).");

    // 1. Check for browser support
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const SpeechSynthesis = window.speechSynthesis;
    if (!SpeechRecognition) {
        console.warn("AI Assistant: Browser does not support Web Speech API (Recognition).");
        $('#ai-assistant-btn').hide();
        return;
    }
    if (!SpeechSynthesis) {
        console.warn("AI Assistant: Browser does not support Web Speech API (Synthesis). Text-to-speech disabled.");
        new Noty({ text: 'Warning: Text-to-speech is not supported in this browser.', type: 'warning', timeout: 5000 }).show();
    }

    // 2. Initialize variables
    const recognition = new SpeechRecognition();
    const aiButton = $('#ai-assistant-btn');
    const aiButtonIcon = aiButton.find('i');
    const aiButtonText = aiButton.find('span');
    let isListening = false;
    let currentStep = null; // Tracks pilot creation steps: null, 'username', 'confirm_username', 'email', 'confirm_email'

    // 3. Configure the Speech Recognition service
    recognition.continuous = false;
    recognition.interimResults = true;
    recognition.lang = 'en-US';
    recognition.maxAlternatives = 3; // Capture multiple alternatives
    recognition.timeout = 10000;

    // 4. Configure Text-to-Speech
    function speakMessage(message) {
        if (!SpeechSynthesis) return;
        console.log("AI Assistant: Speaking message:", message);
        const utterance = new SpeechSynthesisUtterance(message);
        utterance.lang = 'en-US';
        utterance.pitch = 1;
        utterance.rate = 1;
        utterance.volume = 0.9;
        const voices = window.speechSynthesis.getVoices();
        if (voices.length === 0) {
            console.warn("AI Assistant: No TTS voices available.");
            return;
        }
        const preferredVoice = voices.find(voice => voice.lang === 'en-US' && (voice.name.includes('Google') || voice.name.includes('Natural'))) || voices.find(voice => voice.lang === 'en-US');
        if (preferredVoice) {
            utterance.voice = preferredVoice;
            console.log("AI Assistant: Using TTS voice:", preferredVoice.name);
        } else {
            console.warn("AI Assistant: No en-US voice found for TTS.");
        }
        window.speechSynthesis.speak(utterance);
    }

    // Ensure voices are loaded
    if (SpeechSynthesis) {
        window.speechSynthesis.onvoiceschanged = () => {
            const voices = window.speechSynthesis.getVoices();
            console.log("AI Assistant: TTS voices loaded:", voices.map(v => v.name));
        };
    }

    // 5. Define event handlers for the recognition process
    recognition.onresult = function (event) {
        let interim_transcript = '';
        let final_transcripts = [];

        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                for (let j = 0; j < event.results[i].length; j++) {
                    final_transcripts.push(event.results[i][j].transcript);
                }
            } else {
                interim_transcript += event.results[i][0].transcript;
            }
        }

        if (interim_transcript) {
            aiButtonText.text(interim_transcript + '...');
        }

        if (final_transcripts.length > 0 && isListening) {
            const commandText = final_transcripts[0].trim(); // Use the top alternative
            console.log("AI Assistant: Final command captured ->", commandText, "Alternatives:", final_transcripts);
            console.log("Data being sent:", { command: commandText, step: currentStep, alternatives: final_transcripts });

            isListening = false;
            recognition.stop();

            processVoiceCommand(commandText, final_transcripts);
        }
    };

    recognition.onend = function () {
        console.log("AI Assistant: Recognition service ended.");
        if (isListening) {
            recognition.start();
        } else {
            aiButton.removeClass('listening');
            aiButtonIcon.removeClass('fa-stop-circle').addClass('fa-microphone');
            aiButtonText.text(currentStep ? `Say the ${currentStep.replace('confirm_', '')} confirmation...` : 'AI Assistant');
        }
    };

    recognition.onerror = function (event) {
        console.error("AI Assistant Error:", event.error);
        isListening = false;
        aiButton.removeClass('listening');
        aiButtonIcon.removeClass('fa-stop-circle').addClass('fa-microphone');
        aiButtonText.text(currentStep ? `Say the ${currentStep.replace('confirm_', '')} confirmation...` : 'AI Assistant');
        const message = event.error === 'no-speech'
            ? `AI: No speech detected. Please click the button and speak clearly for the ${currentStep || 'command'}.`
            : `AI Error: Speech recognition failed (${event.error}). Please try again.`;
        new Noty({ text: message, type: 'error', timeout: 4000 }).show();
        speakMessage(message);
    };

    // 6. The main button click handler (toggles listening on/off)
    aiButton.on('click', function () {
        if (isListening) {
            isListening = false;
            recognition.stop();
        } else {
            aiButton.addClass('listening');
            aiButtonIcon.removeClass('fa-microphone').addClass('fa-stop-circle');
            aiButtonText.text(currentStep ? `Say the ${currentStep.replace('confirm_', '')} confirmation...` : 'Listening...');
            isListening = true;
            try {
                recognition.start();
            } catch (e) {
                console.error("Error starting recognition:", e);
                isListening = false;
                aiButton.removeClass('listening');
                aiButtonIcon.removeClass('fa-stop-circle').addClass('fa-microphone');
                aiButtonText.text('AI Assistant');
                const message = 'AI Error: Failed to start speech recognition. Please try again.';
                new Noty({ text: message, type: 'error', timeout: 4000 }).show();
                speakMessage(message);
            }
        }
    });

    // 7. Function to send the command to the PHP "AI Brain"
    function processVoiceCommand(commandText, alternatives) {
        if (!commandText || commandText.trim() === '') {
            const message = 'Error: Empty command received';
            new Noty({ text: message, type: 'error', timeout: 4000 }).show();
            speakMessage(message);
            return;
        }

        console.log("Sending command to AI Core:", commandText, "Step:", currentStep);
        aiButtonText.text('Processing...');
        aiButton.prop('disabled', true);

        $.ajax({
            type: 'POST',
            url: 'daily_manager_ai_voice_command.php',
            // url: 'daily_manager_ai_voice_command2.php', // NO
            // url: 'daily_manager_ai_voice_command_GK4_pilotExist.php', // This works well. username many attends ok, 
            contentType: 'application/json',
            data: JSON.stringify({ command: commandText, step: currentStep, alternatives: alternatives }),
            dataType: 'json'
        })
            .done(function (response) {
                console.log("AI Core Response:", response);
                handleAiResponse(response);
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown, "Response:", jqXHR.responseText);
                const message = 'Error: Could not communicate with the AI Assistant service.';
                new Noty({ text: message, type: 'error', timeout: 4000 }).show();
                speakMessage(message);
            })
            .always(function () {
                aiButton.prop('disabled', false);
                aiButtonText.text('AI Assistant');
            });
    }

    // 8. Function to interpret the AI's plan and handle responses
    function handleAiResponse(response) {
        if (!response || response.status === 'error') {
            const message = `AI Error: ${response.message || "I'm sorry, I didn't understand that command."}`;
            new Noty({ text: message, type: 'error', timeout: 4000 }).show();
            speakMessage(message);
            currentStep = null;
            return;
        }

        if (response.status === 'next') {
            currentStep = response.nextStep;
            const notyMessage = `AI: ${response.message}`;
            new Noty({ text: notyMessage, type: 'info', timeout: 7000 }).show();
            speakMessage(response.message);
            aiButtonText.text(`Waiting for ${currentStep.replace('confirm_', '')}...`);
            setTimeout(() => {
                if (currentStep && !isListening) {
                    console.log("AI Assistant: Automatically activating microphone for next step.");
                    aiButton.click();
                }
            }, 3500);
        } else if (response.status === 'success') {
            currentStep = null;
            new Noty({ text: `AI: ${response.message}`, type: 'success', timeout: 3000 }).show();
            speakMessage(response.message);
            if (response.action === 'ADD_CRAFT' && typeof loadAvailableCrafts === 'function') {
                loadAvailableCrafts();
                if (typeof loadAndBuildManagerCraftsTable === 'function') {
                    loadAndBuildManagerCraftsTable();
                }
            } else if (response.action === 'CREATE_CONTRACT' && typeof loadAvailableContracts === 'function') {
                loadAvailableContracts();
            }
        }
    }
});