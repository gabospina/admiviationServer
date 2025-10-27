// training-main.js
// This file is the main entry point for the training page.
// It initializes the application, fetches initial data, and sets up global event listeners.

// Use the safe jQuery wrapper to avoid conflicts and ensure jQuery is loaded.
jQuery(function ($) {

    /**
     * Shows the global loading modal used during application startup.
     * Assumes a modal with ID #yearLoadingModal exists in the HTML.
     */
    function showLoadingModal() {
        const $modal = $("#yearLoadingModal");
        if ($modal.length) {
            $modal.modal("show");
        } else {
            console.error("showLoadingModal: Modal with ID #yearLoadingModal not found.");
        }
    }

    /**
     * Hides the global loading modal.
     */
    function hideLoadingModal() {
        const $modal = $("#yearLoadingModal");
        if ($modal.length) {
            $modal.modal("hide");
        }
    }

    /**
     * Main application initialization function.
     * Manages the asynchronous startup sequence.
     */
    function initializeApplication() {
        showLoadingModal(); // From training-modal-handlers.js

        // Use Promise.all to fetch all necessary startup data in parallel.
        Promise.all([
            fetchAllCrafts(),       // From training-ajax-operations.js
            fetchTrainingDates()    // From training-ajax-operations.js
        ]).then(() => {
            // This code runs ONLY after ALL data has been successfully fetched.
            console.log("Initial data loaded successfully. Initializing main calendar.");

            // Initialize the main calendar (which is on the active tab by default).
            initializeMainCalendar(); // From training-calendar.js

            hideLoadingModal(); // From training-modal-handlers.js
        }).catch(error => {
            // This code runs if ANY of the initial AJAX calls fail.
            console.error("Failed to initialize application:", error);
            hideLoadingModal();
            showNotification('error', 'A critical error occurred while loading page data. Please refresh.');
        });
    }

    /**
     * Sets up all static event listeners for the page.
     */
    function setupEventListeners() {

        // --- Tab Handling ---
        $(".tab").on("click", function () {
            const $this = $(this);
            if ($this.hasClass("disabled") || $this.hasClass("active")) {
                return;
            }

            $(".tab-pane, .tab").removeClass("active");
            $this.addClass("active");
            $(`.tab-pane[data-tab='${$this.data("tab-toggle")}']`).addClass("active");

            // Check if calendars exist before calling methods on them for safety
            if ($('#calendar').hasClass('fc')) {
                $('#calendar').fullCalendar('render');
            }
            if ($('#trainer-calendar').hasClass('fc')) {
                $('#trainer-calendar').fullCalendar('render');
            }

            const tabId = $this.data("tab-toggle");
            if (tabId === "trainer") {
                // Initialize the trainer calendar only if it hasn't been already
                if (!$('#trainer-calendar').children().length) {
                    console.log("Trainer tab clicked, initializing trainer calendar.");
                    initializeTrainerCalendar(); // From training-calendar.js
                }
            }
        });

        // --- View As Buttons (Trainee/Trainer) ---
        $(".view-as-btn").on("click", function () {
            const $this = $(this);
            if ($this.hasClass("active")) return;

            $(".view-as-btn").removeClass("active");
            $this.addClass("active");

            // Refetch events for the main calendar with the new viewType parameter
            $('#calendar').fullCalendar('refetchEvents');
        });

        // --- Search Functionality (with debounce) ---
        let searchTimeout;
        $("#search_trainees").on("keyup", function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                // The search logic is handled by the eventRender function.
                // We just need to trigger a re-render to apply the filter.
                $('#calendar').fullCalendar('rerenderEvents');
            }, 300); // Wait 300ms after the user stops typing
        });

        // --- Print Modal Functionality ---
        $("#printType").on("change", function () {
            if ($(this).val()) {
                $(".printDates").slideDown();
            } else {
                $(".printDates").slideUp();
            }
        });

        $("#printBtn").on("click", function () {
            const printType = $("#printType").val();
            const format = $("#outputFormat").val();
            const startDate = $("#printStart").val();
            const endDate = $("#printEnd").val();

            if (!printType || !startDate || !endDate) {
                showNotification('warning', 'Please select a type and a full date range to print.');
                return;
            }

            // This function would live in a training-print.js file if created
            // generatePrintReport(printType, format, startDate, endDate);
            console.log("Print functionality to be implemented.");
        });
    }

    /**
     * Sets up initial page styles and states.
     */
    function initialPageSetup() {
        // Set active state for sidebar navigation
        $(".sidebar-list a[href='training.php'] li").addClass("active");
        // Add background class to body
        $("body").addClass("body-bg");
        // Hide print date selectors initially
        $(".printDates").hide();
    }

    // --- SCRIPT EXECUTION STARTS HERE ---

    initialPageSetup();
    setupEventListeners();
    initializeApplication(); // Kick off the main data loading and calendar setup

}); // This is the closing tag for the jQuery wrapper. This was likely missing.