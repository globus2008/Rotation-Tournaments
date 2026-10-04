// --- doroto-frontend-scripts.js ---

// 1. Reload the page after the number of seconds in data-seconds
document.addEventListener("DOMContentLoaded", function () {
  const refreshContainer = document.getElementById("doroto-refresh-container");
  if (refreshContainer) {
    const seconds = parseInt(refreshContainer.getAttribute("data-seconds"), 10);
    if (!isNaN(seconds) && seconds > 0) {
      setTimeout(() => location.reload(), seconds * 1000);
    }
  }
});

// 2. Expanding the main panels (clickable titles)
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

// 3. Expanding submenus and showing the map
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

// 4. "Edit result" links in the table of played matches (organizers only):
//    select the match and its current result in the change form and scroll to it.
//    The form is printed inside a <table>, so the browser moves the <form>
//    element out of it; the fields are reached through form.elements.
document.addEventListener("DOMContentLoaded", function () {
  const links = document.querySelectorAll(".doroto-edit-result");
  if (!links.length) return;
  const form = document.getElementById("doroto_change_game_result_form");
  if (!form || !form.elements["match_to_change"]) {
    // No change form on this page: the links would lead nowhere.
    links.forEach(function (link) {
      link.style.display = "none";
    });
    return;
  }
  const target = document.getElementById("doroto-change-match-result") || form;

  links.forEach(function (link) {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      const match = form.elements["match_to_change"];
      const result1 = form.elements["game_result_1"];
      const result2 = form.elements["game_result_2"];
      // getAttribute: dataset keeps "result-1" (a dash before a digit is not camel-cased).
      match.value = link.getAttribute("data-match");
      if (result1) result1.value = link.getAttribute("data-result-1");
      if (result2) result2.value = link.getAttribute("data-result-2");
      target.scrollIntoView({ behavior: "smooth", block: "center" });
      target.classList.add("doroto-highlight");
      setTimeout(function () {
        target.classList.remove("doroto-highlight");
      }, 2000);
      if (result1) result1.focus({ preventScroll: true });
    });
  });
});

window.dorotoCopyCoordinates = function () {
  const input = document.getElementById("doroto-coordinates-input");
  if (!input) return;
  input.select();
  input.setSelectionRange(0, 99999); // for phones
  document.execCommand("copy");
  alert("Coordinates copied: " + input.value);
};
