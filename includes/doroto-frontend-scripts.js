// --- doroto-frontend-scripts.js ---

// 1. Automatické obnovení stránky podle atributu data-seconds
document.addEventListener("DOMContentLoaded", function () {
  const refreshContainer = document.getElementById("doroto-refresh-container");
  if (refreshContainer) {
    const seconds = parseInt(refreshContainer.getAttribute("data-seconds"), 10);
    if (!isNaN(seconds) && seconds > 0) {
      setTimeout(() => location.reload(), seconds * 1000);
    }
  }
});

// 2. Rozbalování hlavních panelů (klikací titulky)
jQuery(document).ready(function ($) {
  $(".doroto-clickable-title").click(function () {
    const containerId = $(this).next(".doroto-content-container").attr("id");
    $(".doroto-content-container")
      .not("#" + containerId)
      .slideUp();
    $("#" + containerId).slideToggle();
    sessionStorage.setItem("openedPanel", containerId);
  });

  const openedPanel = sessionStorage.getItem("openedPanel");
  if (openedPanel) {
    $("#" + openedPanel).show();
  }
});

// 3. Rozbalování submenu a zobrazení mapy
let dorotoMapInitialized = false;

jQuery(document).ready(function () {
  jQuery(".doroto-clickable-submenu").click(function () {
    const container = jQuery(this).next(".doroto-content-container");

    if (container.is(":visible")) {
      container.slideUp("fast");
    } else {
      container.slideDown("fast", function () {
        const mapContainer = document.getElementById("doroto-club-map");

        if (mapContainer && !dorotoMapInitialized && typeof L !== "undefined") {
          const latField = document.getElementById("doroto_latitude");
          const lngField = document.getElementById("doroto_longitude");

          const initialLat = parseFloat(latField?.value || "50.05");
          const initialLng = parseFloat(lngField?.value || "14.47");

          const map = L.map("doroto-club-map").setView(
            [initialLat, initialLng],
            13
          );

          L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: "&copy; OpenStreetMap",
            maxZoom: 19,
          }).addTo(map);

          const marker = L.marker([initialLat, initialLng], {
            draggable: true,
          }).addTo(map);

          marker.on("dragend", function () {
            const position = marker.getLatLng();
            latField.value = position.lat.toFixed(8);
            lngField.value = position.lng.toFixed(8);
          });

          dorotoMapInitialized = true;
        }
      });
    }
  });
});

window.dorotoCopyCoordinates = function () {
  const input = document.getElementById("doroto-coordinates-input");
  if (!input) return;
  input.select();
  input.setSelectionRange(0, 99999); // pro mobily
  document.execCommand("copy");
  alert("Coordinates copied: " + input.value);
};
