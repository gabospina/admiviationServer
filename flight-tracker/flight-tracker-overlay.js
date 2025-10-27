// flight-tracker-overlay.js
var FlightTrackerOverlay = FlightTrackerOverlay || {};

FlightTrackerOverlay.dragData = {
    isDragging: false,
    startX: 0,
    startY: 0,
    startLeft: 0,
    startTop: 0,
    originalPosition: {}
};

FlightTrackerOverlay.init = function () {
    const $weatherPanel = $('#weather-map-wrapper .panel'); // Target the entire panel
    const $panelHeading = FlightTracker.elements.weatherWrapper.find('.panel-heading');
    const $panelBody = FlightTracker.elements.weatherWrapper.find('.panel-body');

    // Initialize opacity
    FlightTracker.elements.weatherIframe.css('opacity', 0.4);
    FlightTracker.elements.opacitySlider.val(4);

    // Make the entire panel resizable
    $weatherPanel.resizable({
        handles: 'se', // Bottom-right corner handle
        minHeight: 400,
        minWidth: 500,
        maxHeight: 1000,
        maxWidth: 1500,
        disabled: true, // Disabled by default, enabled in overlay mode
        resize: function (event, ui) {
            // Adjust the panel-body and iframe wrapper to fill the panel
            const panelHeight = ui.size.height - $panelHeading.outerHeight();
            $panelBody.css({
                height: panelHeight + 'px'
            });
            $('.weather-iframe-wrapper').css({
                width: '100%',
                height: panelHeight + 'px'
            });
            // Ensure iframe fills the wrapper
            FlightTracker.elements.weatherIframe.css({
                width: '100%',
                height: '100%'
            });
        },
        stop: function (event, ui) {
            // Refresh iframe to prevent rendering issues
            const iframe = FlightTracker.elements.weatherIframe[0];
            const tempSrc = iframe.src;
            iframe.src = '';
            setTimeout(() => { iframe.src = tempSrc; }, 50);

            // Re-center if in overlay mode
            if (FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
                FlightTrackerOverlay.snapToCenter();
            }
        }
    });

    // Set up event handlers
    FlightTrackerOverlay.setupOverlayEventHandlers($panelHeading, $panelBody);

    console.log('Draggable overlay initialized');
};

FlightTrackerOverlay.setupOverlayEventHandlers = function ($panelHeading, $panelBody) {
    // Drag handlers
    $panelHeading.on('mousedown', FlightTrackerOverlay.handleMouseDown);
    $panelBody.on('mousedown', FlightTrackerOverlay.handleMouseDown);
    $(document).on('mousemove.overlay', FlightTrackerOverlay.handleMouseMove);
    $(document).on('mouseup.overlay', FlightTrackerOverlay.handleMouseUp);

    // Prevent resize handle from triggering drag
    $panelHeading.on('mousedown', '.ui-resizable-handle', function (e) {
        e.stopPropagation();
    });

    // Opacity slider
    FlightTracker.elements.opacitySlider.on('mousedown', function (e) {
        e.stopPropagation();
    });

    FlightTracker.elements.opacitySlider.on('input change', function (e) {
        const opacity = parseInt(this.value) / 10;
        FlightTracker.elements.weatherIframe.css('opacity', opacity);
        e.stopPropagation();
    });

    // Overlay controls
    $('#snap-to-fit').on('click', FlightTrackerOverlay.snapToCenter);
    $('#close-overlay').on('click', FlightTrackerOverlay.exitOverlayMode);

    // Activation handlers
    FlightTracker.elements.weatherWrapper.on('dblclick', '.panel-heading', function (e) {
        if (!FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
            FlightTrackerOverlay.enterOverlayMode();
            e.preventDefault();
        }
    });

    // Prevent iframe from blocking drag
    FlightTracker.elements.weatherIframe.on('mousedown', function (e) {
        if (FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
            FlightTrackerOverlay.handleMouseDown(e);
            return false;
        }
    });

    // Escape key to exit overlay
    $(document).on('keydown.overlay', function (e) {
        if (e.key === 'Escape' && FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
            FlightTrackerOverlay.exitOverlayMode();
        }
    });
};

FlightTrackerOverlay.handleMouseDown = function (e) {
    // Prevent drag if clicking on resize handle
    if ($(e.target).hasClass('ui-resizable-handle') || $(e.target).closest('.ui-resizable-handle').length) {
        return;
    }

    if (FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
        FlightTrackerOverlay.dragData.isDragging = true;
        FlightTrackerOverlay.dragData.startX = e.clientX;
        FlightTrackerOverlay.dragData.startY = e.clientY;

        // Get current computed position
        const computedStyle = window.getComputedStyle(FlightTracker.elements.weatherWrapper[0]);
        FlightTrackerOverlay.dragData.startLeft = parseInt(computedStyle.left) || 0;
        FlightTrackerOverlay.dragData.startTop = parseInt(computedStyle.top) || 0;

        // Account for transform if present
        if (computedStyle.transform && computedStyle.transform !== 'none') {
            const rect = FlightTracker.elements.weatherWrapper[0].getBoundingClientRect();
            FlightTrackerOverlay.dragData.startLeft = rect.left;
            FlightTrackerOverlay.dragData.startTop = rect.top;
        }

        // Remove transform when dragging starts
        FlightTracker.elements.weatherWrapper.css('transform', 'none');

        $('body').css('cursor', 'move');
        e.preventDefault();
        e.stopPropagation();
    }
};

FlightTrackerOverlay.handleMouseMove = function (e) {
    if (FlightTrackerOverlay.dragData.isDragging && FlightTracker.elements.weatherWrapper.hasClass('overlay-mode')) {
        const deltaX = e.clientX - FlightTrackerOverlay.dragData.startX;
        const deltaY = e.clientY - FlightTrackerOverlay.dragData.startY;

        const newLeft = FlightTrackerOverlay.dragData.startLeft + deltaX;
        const newTop = FlightTrackerOverlay.dragData.startTop + deltaY;

        FlightTracker.elements.weatherWrapper.css({
            left: newLeft + 'px',
            top: newTop + 'px'
        });
    }
};

FlightTrackerOverlay.handleMouseUp = function () {
    if (FlightTrackerOverlay.dragData.isDragging) {
        FlightTrackerOverlay.dragData.isDragging = false;
        $('body').css('cursor', '');
    }
};

FlightTrackerOverlay.enterOverlayMode = function () {
    // ADD THIS LINE to change the instruction text
    $('#weather-map-instruction').text('(Hold and drag header to reposition overlay)');

    FlightTrackerOverlay.storeOriginalPosition();

    // Add overlay-active class to map container
    $('.map-container').addClass('overlay-active');
    FlightTracker.elements.weatherWrapper.addClass('overlay-mode');
    FlightTracker.elements.overlayControls.show();
    FlightTracker.elements.advisoryMessage.hide();

    // Enable resizable on the panel
    $('#weather-map-wrapper .panel').resizable('option', 'disabled', false);

    // Apply current slider opacity
    const opacity = parseInt(FlightTracker.elements.opacitySlider.val()) / 10;
    $('.weather-iframe-wrapper').css('opacity', opacity);

    // Center on screen
    FlightTrackerOverlay.snapToCenter();

    // Force map resize
    setTimeout(() => {
        if (FlightTracker.state.map) {
            FlightTracker.state.map.invalidateSize();
        }
    }, 300);

    FlightTrackerUtils.showNotification('info', 'Drag the weather map header to position it. Use slider to adjust transparency.', 4000);
};

FlightTrackerOverlay.exitOverlayMode = function () {
    // ADD THIS LINE to change the instruction text back
    $('#weather-map-instruction').text('(Double-click header to overlay flight tracker)');

    $('.map-container').removeClass('overlay-active');
    FlightTracker.elements.weatherWrapper.removeClass('overlay-mode');
    FlightTracker.elements.overlayControls.hide();
    FlightTracker.elements.advisoryMessage.show();

    // Disable resizable on the panel
    $('#weather-map-wrapper .panel').resizable('option', 'disabled', true);

    // Reset panel and iframe wrapper sizes
    $('#weather-map-wrapper .panel').css({
        width: '',
        height: ''
    });
    $('.panel-body').css({
        height: ''
    });
    $('.weather-iframe-wrapper').css({
        width: '100%',
        height: '60vh',
        opacity: '1'
    });
    FlightTracker.elements.weatherIframe.css({
        width: '100%',
        height: '100%'
    });

    // Restore original position
    FlightTracker.elements.weatherWrapper.removeAttr('style');

    // Force map resize
    setTimeout(() => {
        if (FlightTracker.state.map) {
            FlightTracker.state.map.invalidateSize();
        }
    }, 300);

    // Reset drag state
    FlightTrackerOverlay.dragData.isDragging = false;
    $('body').css('cursor', '');
};

FlightTrackerOverlay.snapToCenter = function () {
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const panelWidth = FlightTracker.elements.weatherWrapper.outerWidth();
    const panelHeight = FlightTracker.elements.weatherWrapper.outerHeight();

    const centerLeft = (viewportWidth - panelWidth) / 2;
    const centerTop = (viewportHeight - panelHeight) / 2;

    FlightTracker.elements.weatherWrapper.css({
        left: centerLeft + 'px',
        top: centerTop + 'px',
        transform: 'none'
    });
};

FlightTrackerOverlay.storeOriginalPosition = function () {
    const offset = FlightTracker.elements.weatherWrapper.offset();
    FlightTrackerOverlay.dragData.originalPosition = {
        position: FlightTracker.elements.weatherWrapper.css('position'),
        top: FlightTracker.elements.weatherWrapper.css('top'),
        left: FlightTracker.elements.weatherWrapper.css('left'),
        width: FlightTracker.elements.weatherWrapper.css('width'),
        height: FlightTracker.elements.weatherWrapper.css('height'),
        transform: FlightTracker.elements.weatherWrapper.css('transform'),
        margin: FlightTracker.elements.weatherWrapper.css('margin'),
        offsetTop: offset.top,
        offsetLeft: offset.left
    };
};
