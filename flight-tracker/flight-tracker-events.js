// flight-tracker-events.js - Event bus for communication between modules
var FlightTrackerEvents = FlightTrackerEvents || {};

// Event storage
FlightTrackerEvents._events = {};

/**
 * Subscribe to an event
 * @param {string} eventName - The name of the event to subscribe to
 * @param {function} callback - The function to call when the event is published
 * @param {object} context - The context (this) for the callback function
 * @returns {function} - Unsubscribe function
 */
FlightTrackerEvents.subscribe = function (eventName, callback, context) {
    if (!FlightTrackerEvents._events[eventName]) {
        FlightTrackerEvents._events[eventName] = [];
    }

    const subscriber = {
        callback: callback,
        context: context || window
    };

    FlightTrackerEvents._events[eventName].push(subscriber);

    // Return unsubscribe function
    return function () {
        FlightTrackerEvents.unsubscribe(eventName, callback, context);
    };
};

/**
 * Unsubscribe from an event
 * @param {string} eventName - The name of the event to unsubscribe from
 * @param {function} callback - The callback function to remove
 * @param {object} context - The context of the callback function
 */
FlightTrackerEvents.unsubscribe = function (eventName, callback, context) {
    if (!FlightTrackerEvents._events[eventName]) return;

    FlightTrackerEvents._events[eventName] = FlightTrackerEvents._events[eventName].filter(
        subscriber => !(subscriber.callback === callback && subscriber.context === (context || window))
    );
};

/**
 * Publish an event
 * @param {string} eventName - The name of the event to publish
 * @param {...any} args - Arguments to pass to subscribers
 */
FlightTrackerEvents.publish = function (eventName, ...args) {
    if (!FlightTrackerEvents._events[eventName]) return;

    FlightTrackerEvents._events[eventName].forEach(subscriber => {
        try {
            subscriber.callback.apply(subscriber.context, args);
        } catch (error) {
            console.error(`Error in event subscriber for ${eventName}:`, error);
        }
    });
};

/**
 * Get all subscribers for an event
 * @param {string} eventName - The name of the event
 * @returns {array} - Array of subscribers
 */
FlightTrackerEvents.getSubscribers = function (eventName) {
    return FlightTrackerEvents._events[eventName] || [];
};

/**
 * Remove all subscribers for an event
 * @param {string} eventName - The name of the event
 */
FlightTrackerEvents.clearEvent = function (eventName) {
    if (FlightTrackerEvents._events[eventName]) {
        FlightTrackerEvents._events[eventName] = [];
    }
};

/**
 * Remove all events and subscribers
 */
FlightTrackerEvents.clearAll = function () {
    FlightTrackerEvents._events = {};
};

// Pre-defined events for common actions
FlightTrackerEvents.EVENTS = {
    // Map events
    MAP_READY: 'map:ready',
    MAP_LAYER_CHANGED: 'map:layerChanged',
    LOCATION_MARKERS_UPDATED: 'map:locationMarkersUpdated',

    // Flight events
    FLIGHT_ADDED: 'flight:added',
    FLIGHT_REMOVED: 'flight:removed',
    FLIGHT_UPDATED: 'flight:updated',
    FLIGHT_ANIMATION_START: 'flight:animationStart',
    FLIGHT_ANIMATION_STOP: 'flight:animationStop',

    // Location events
    LOCATIONS_LOADED: 'locations:loaded',
    LOCATION_ADDED: 'location:added',
    LOCATION_UPDATED: 'location:updated',
    LOCATION_REMOVED: 'location:removed',

    // Overlay events
    OVERLAY_MODE_ENTER: 'overlay:enter',
    OVERLAY_MODE_EXIT: 'overlay:exit',
    OVERLAY_POSITION_CHANGED: 'overlay:positionChanged',
    OVERLAY_OPACITY_CHANGED: 'overlay:opacityChanged',

    // UI events
    UI_PLANNER_UPDATED: 'ui:plannerUpdated',
    UI_NOTIFICATION_SHOW: 'ui:notificationShow',
    UI_NOTIFICATION_HIDE: 'ui:notificationHide',

    // Data events
    DATA_LOAD_START: 'data:loadStart',
    DATA_LOAD_COMPLETE: 'data:loadComplete',
    DATA_LOAD_ERROR: 'data:loadError'
};

// Helper function for common event patterns
FlightTrackerEvents.once = function (eventName, callback, context) {
    const onceCallback = function () {
        FlightTrackerEvents.unsubscribe(eventName, onceCallback, context);
        callback.apply(context || window, arguments);
    };
    FlightTrackerEvents.subscribe(eventName, onceCallback, context);
};

// Debug function to log all events
FlightTrackerEvents.debug = function (enable) {
    if (enable) {
        // Subscribe to all events and log them
        Object.values(FlightTrackerEvents.EVENTS).forEach(eventName => {
            FlightTrackerEvents.subscribe(eventName, function () {
                console.log(`[Event] ${eventName}`, arguments);
            });
        });
    }
};

// Initialize debug mode in development
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    FlightTrackerEvents.debug(true);
}