document.addEventListener("DOMContentLoaded", function () {
  const mapContainer = document.getElementById("doroto-map");
  if (!mapContainer) return;

  const initialLat =
    typeof doroto_latitude !== "undefined" ? doroto_latitude : 50.05;
  const initialLng =
    typeof doroto_longitude !== "undefined" ? doroto_longitude : 14.47;

  const map = L.map("doroto-map").setView([initialLat, initialLng], 13);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap contributors",
  }).addTo(map);

  const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(
    map
  );

  marker.on("dragend", function (e) {
    const pos = e.target.getLatLng();
    document.querySelector('input[name="doroto_settings[latitude]"]').value =
      pos.lat;
    document.querySelector('input[name="doroto_settings[longitude]"]').value =
      pos.lng;
  });
});
