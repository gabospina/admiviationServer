// training-main.js
// This file is the main entry point for the training page.
// It initializes the application, fetches initial data, and sets up global event listeners.

// Use the safe jQuery wrapper to avoid conflicts and ensure jQuery is loaded.
jQuery(function ($) {

    function initializeApplication() {
        showLoadingModal();
        Promise.all([
            fetchAllCrafts(),
            fetchTrainingDates()
        ]).then(() => {
            console.log("Initial data loaded. Initializing main calendar in READ-ONLY mode.");
            // --- THIS IS THE FIX ---
            // The calendar always loads in read-only mode first.
            initializeMainCalendar(false);
            // --- END OF FIX ---
            hideLoadingModal();
        }).catch(error => {
            console.error("Failed to initialize application:", error);
            hideLoadingModal();
            showNotification('error', 'A critical error occurred while loading page data.');
        });
    }

    /**
     * Sets up all static event listeners for the page using the modern FullCalendar v6 API.
     */
    function setupEventListeners() {

        // --- Tab Handling ---
        $(".tab").on("click", function () {
            const $this = $(this);
            if ($this.hasClass("active") || $this.hasClass("disabled")) {
                return;
            }

            $(".tab-pane, .tab").removeClass("active");
            $this.addClass("active");
            $(`.tab-pane[data-tab='${$this.data("tab-toggle")}']`).addClass("active");

            if ($this.data("tab-toggle") === "trainer") {
                // Initialize the trainer calendar only if it hasn't been already.
                // In v6, the presence of the instance is the best check.
                if (!trainerCalendarInstance) {
                    initializeTrainerCalendar();
                }
            }
        });

        // --- View As Buttons (Trainee/Trainer) ---
        $(".view-as-btn").on("click", function () {
            const $this = $(this);
            if ($this.hasClass("active")) {
                return;
            }

            $(".view-as-btn").removeClass("active");
            $this.addClass("active");

            // Use the v6 instance method to refetch and re-render events with the new title logic.
            if (mainCalendarInstance) {
                mainCalendarInstance.refetchEvents();
            }
        });

        // --- Search Functionality (with debounce) ---
        let searchTimeout;
        $("#search_trainees").on("keyup", function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function () {
                // The search logic is handled by the eventRender function.
                // We just need to trigger a re-render to apply the filter.
                // In v6, `refetchEvents` is the most reliable way to force a full redraw.
                if (mainCalendarInstance) {
                    mainCalendarInstance.refetchEvents();
                }
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

            console.log("Print functionality to be implemented.");
        });

        // --- "Enable Drag & Drop" Button Logic ---
        // $('#toggle-edit-mode-btn').on('click', function () {
        //     const $button = $(this);
        //     const currentMode = $button.data('mode');

        //     if (currentMode === 'read') {
        //         // Re-initialize the calendar with editing enabled.
        //         initializeMainCalendar(true);

        //         $button.data('mode', 'edit')
        //             .removeClass('btn-warning')
        //             .addClass('btn-success')
        //             .html('<i class="fa fa-check"></i> Done Editing');
        //         showNotification('info', 'Drag & Drop is now enabled. Display may be distorted in this mode.', 4000);
        //     } else {
        //         // Re-initialize the calendar in read-only mode, which fixes the display.
        //         initializeMainCalendar(false);

        //         $button.data('mode', 'read')
        //             .removeClass('btn-success')
        //             .addClass('btn-warning')
        //             .html('<i class="fa fa-pencil"></i> Enable Drag & Drop');
        //     }
        // });

        // --- "Add Selection" Button Handler ---
        // This call ensures the button inside the #eventModal is functional.
        setupAddEventButtonHandler(); // from training-modal-handlers.js

        // --- FINAL "ONE-SHOT" DRAG & DROP LOGIC ---
        $(document).off('click', '#toggle-edit-mode-btn').on('click', '#toggle-edit-mode-btn', function () {
            const $button = $(this);
            const currentMode = $button.data('mode');

            // Target the correct calendar instance based on the active tab.
            const activeTab = $(".tab.active").data("tab-toggle");
            const calendarInstance = (activeTab === 'trainer') ? trainerCalendarInstance : mainCalendarInstance;

            if (!calendarInstance) {
                console.error("Cannot toggle edit mode: active calendar instance not found.");
                return;
            }

            if (currentMode === 'read') {
                // --- ENTERING EDIT MODE ---
                // Use the official v6 API to make the calendar editable.
                calendarInstance.setOption('editable', true);

                $button.data('mode', 'edit')
                    .removeClass('btn-warning')
                    .addClass('btn-success')
                    .html('<i class="fa-solid fa-arrows-up-down-left-right"></i> Dragging Enabled'); // Or use another icon
                showNotification('info', 'Drag & Drop is now enabled for one move.', 3000);

            } else {
                // --- EXITING EDIT MODE (Manually clicked) ---
                // Use the official v6 API to make the calendar read-only.
                calendarInstance.setOption('editable', false);

                $button.data('mode', 'read')
                    .removeClass('btn-success')
                    .addClass('btn-warning')
                    .html('<i class="fa fa-pencil"></i> Enable Drag & Drop');
            }
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

    initialPageSetup();
    setupEventListeners();
    initializeApplication(); // Kick off the main data loading and calendar setup

}); // This is the closing tag for the jQuery wrapper. This was likely missing.