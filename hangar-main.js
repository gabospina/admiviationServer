// File: hangar_main.js
// Purpose: The main entry point for all JavaScript on the Hangar page.

$(document).ready(function () {
    // Initialize all feature modules for the hangar page.
    initializePersonalInfo();
    initializePersonalInfoModal();
    initializeAssignments();
    initializeDutySchedule();
    initializeValidityChecks();
    initializeProfilePictureUploader();
    initializePasswordChanger();
    
});