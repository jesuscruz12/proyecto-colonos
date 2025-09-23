app.globales.cv_temporal = 0;
app.core.index = {
  modelo: function () {
    return {};
  },

  controlador: function () {
    // NOTICE!! DO NOT USE ANY OF THIS JAVASCRIPT
    // IT'S ALL JUST JUNK FOR DEMO
    // ++++++++++++++++++++++++++++++++++++++++++

    const sales_chart_options = {
      series: [
        {
          name: "Digital Goods",
          data: [28, 48, 40, 19, 86, 27, 90],
        },
        {
          name: "Electronics",
          data: [65, 59, 80, 81, 56, 55, 40],
        },
      ],
      chart: {
        height: 300,
        type: "area",
        toolbar: {
          show: false,
        },
      },
      legend: {
        show: false,
      },
      colors: ["#0d6efd", "#20c997"],
      dataLabels: {
        enabled: false,
      },
      stroke: {
        curve: "smooth",
      },
      xaxis: {
        type: "datetime",
        categories: [
          "2023-01-01",
          "2023-02-01",
          "2023-03-01",
          "2023-04-01",
          "2023-05-01",
          "2023-06-01",
          "2023-07-01",
        ],
      },
      tooltip: {
        x: {
          format: "MMMM yyyy",
        },
      },
    };

    const sales_chart = new ApexCharts(
      document.querySelector("#revenue-chart"),
      sales_chart_options
    );
    sales_chart.render();

    // ***********************************************************

    const visitorsData = {
      US: 398, // USA
      SA: 400, // Saudi Arabia
      CA: 1000, // Canada
      DE: 500, // Germany
      FR: 760, // France
      CN: 300, // China
      AU: 700, // Australia
      BR: 600, // Brazil
      IN: 800, // India
      GB: 320, // Great Britain
      RU: 3000, // Russia
    };

    // World map by jsVectorMap
    const map = new jsVectorMap({
      selector: "#world-map",
      map: "world",
    });

    // Sparkline charts
    const option_sparkline1 = {
      series: [
        {
          data: [1000, 1200, 920, 927, 931, 1027, 819, 930, 1021],
        },
      ],
      chart: {
        type: "area",
        height: 50,
        sparkline: {
          enabled: true,
        },
      },
      stroke: {
        curve: "straight",
      },
      fill: {
        opacity: 0.3,
      },
      yaxis: {
        min: 0,
      },
      colors: ["#DCE6EC"],
    };

    const sparkline1 = new ApexCharts(
      document.querySelector("#sparkline-1"),
      option_sparkline1
    );
    sparkline1.render();

    const option_sparkline2 = {
      series: [
        {
          data: [515, 519, 520, 522, 652, 810, 370, 627, 319, 630, 921],
        },
      ],
      chart: {
        type: "area",
        height: 50,
        sparkline: {
          enabled: true,
        },
      },
      stroke: {
        curve: "straight",
      },
      fill: {
        opacity: 0.3,
      },
      yaxis: {
        min: 0,
      },
      colors: ["#DCE6EC"],
    };

    const sparkline2 = new ApexCharts(
      document.querySelector("#sparkline-2"),
      option_sparkline2
    );
    sparkline2.render();

    const option_sparkline3 = {
      series: [
        {
          data: [15, 19, 20, 22, 33, 27, 31, 27, 19, 30, 21],
        },
      ],
      chart: {
        type: "area",
        height: 50,
        sparkline: {
          enabled: true,
        },
      },
      stroke: {
        curve: "straight",
      },
      fill: {
        opacity: 0.3,
      },
      yaxis: {
        min: 0,
      },
      colors: ["#DCE6EC"],
    };

    const sparkline3 = new ApexCharts(
      document.querySelector("#sparkline-3"),
      option_sparkline3
    );
    sparkline3.render();
  },
};

$(document).ready(function () {
  app.core.index.controlador();
});
