// stats-graphs.js - FINAL VERSION

var currentGraphData = null;
var currentHoverData = null;
var currentDailyLimit = null;
var plotInstance = null;

function statsGraph(start) {
    var type = $(".view-change.active").data("view");
    $("#graphSection").html('<div class="text-center py-5"><i class="fa fa-spinner fa-spin fa-2x"></i> Loading Chart...</div>');
    currentGraphData = null;
    currentHoverData = null;
    currentDailyLimit = null;

    $.ajax({
        type: "GET",
        data: { type: type, start: start },
        url: "stats_get_stats_graph.php",
        dataType: "json",
        success: function (response) {
            if (response && response.success && response.data) {
                var res = response.data;
                $("#totalHours").text(res.total + " Hour" + (res.total != 1 ? "s" : ""));
                currentGraphData = res.graphData;
                currentHoverData = res.hoverData;
                currentDailyLimit = res.dailyLimit; // We still get the value, but won't use it for the red line
                if ($('#graphs').is(':visible')) {
                    plotGraph();
                }
            } else {
                $("#graphSection").html('<div class="alert alert-danger text-center">Error loading graph data. ' + (response.message || '') + '</div>');
                $("#totalHours").text("Error");
            }
        },
        error: () => {
            $("#graphSection").html('<div class="alert alert-danger text-center">AJAX Error loading graph data.</div>');
            $("#totalHours").text("Error");
        }
    });
}

function plotGraph() {
    if (!$('#graphs').is(':visible')) return;
    if (!currentGraphData || currentGraphData.length === 0) {
        $("#graphSection").html('<div class="alert alert-info text-center">No flight data found for this period.</div>');
        return;
    }

    $("#graphSection").html('').css('min-height', '400px');
    var type = $(".view-change.active").data("view");

    var markings = [];
    if (type !== 'year') {
        // === THE FIX: Hard-code the red line to 12.0 ===
        // This ignores the value from the server and enforces the visual rule.
        markings.push({
            yaxis: { from: 12.0, to: 12.0 },
            color: "#e74c3c",
            lineWidth: 2
        });
    }

    plotInstance = $.plot("#graphSection", currentGraphData, {
        series: {
            stack: (type !== 'year'),
            bars: { show: true, barWidth: 0.6, align: "center", lineWidth: 0, fill: 0.9 }
        },
        grid: {
            hoverable: true,
            borderWidth: 1,
            borderColor: '#ddd',
            backgroundColor: '#f9f9f9',
            markings: markings
        },
        legend: {
            show: (type !== 'year'),
            position: 'ne',
            backgroundColor: "#FFF",
            backgroundOpacity: 0.85,
            noColumns: 1,
            margin: [5, 5]
        },
        xaxis: {
            mode: "categories",
            tickLength: 0
        },
        // Use a hard-coded, static Y-axis range to prevent rescaling.
        yaxis: {
            min: 0,
            max: (type === 'year') ? null : 16.0,
            tickSize: (type === 'year') ? null : 2,      // NEW: Set the step between ticks to 2 hours
            tickDecimals: (type === 'year') ? null : 0   // NEW: Display whole numbers (e.g., "2" instead of
        }
    });

    // ... (Tooltip logic is unchanged and correct) ...
    if ($("#datatooltip").length == 0) {
        $("<div id='datatooltip'></div>").css({
            position: "absolute", display: "none", border: "1px solid #ccc",
            padding: "4px 8px", "background-color": "#f2f2f2", opacity: 0.90,
            "z-index": 1050, "border-radius": "4px", "font-size": "12px"
        }).appendTo("body");
    }
    $("#graphSection").off("plothover").on("plothover", function (event, pos, item) {
        if (item) {
            var x_label = item.series.data[item.dataIndex][0];
            if (currentHoverData && currentHoverData[x_label]) {
                const dataPoint = currentHoverData[x_label];
                let tooltipContent = `<strong>${x_label}</strong><br/>Total: <strong>${dataPoint.total.toFixed(1)} hours</strong>`;

                if (type !== 'year' && dataPoint.flights && dataPoint.flights.length > 0) {
                    tooltipContent += "<hr style='margin: 4px 0;'><strong>Breakdown:</strong><br/>";
                    let flightDetails = dataPoint.flights.map(flight => `${flight.h.toFixed(1)}h (${flight.r})`);
                    tooltipContent += flightDetails.join('<br/>');
                }

                $("#datatooltip").html(tooltipContent).css({ top: item.pageY + 10, left: item.pageX + 10 }).fadeIn(200);
            }
        } else {
            $("#datatooltip").hide();
        }
    });
}

// ... initializeGraphControls and document.ready remain the same ...
function initializeGraphControls() {
    $(".view-change").click(function () {
        const $button = $(this);
        if ($button.hasClass("active")) return;
        $(".view-change").removeClass("active");
        $button.addClass("active");
        const viewType = $button.data("view");
        let startDate = moment().format('YYYY-MM-DD');
        if (viewType === 'month' || viewType === 'year') {
            startDate = moment().startOf(viewType).format('YYYY-MM-DD');
        }
        statsGraph(startDate);
    });
}
$(document).ready(function () {
    initializeGraphControls();
    statsGraph(moment().format('YYYY-MM-DD'));
});
