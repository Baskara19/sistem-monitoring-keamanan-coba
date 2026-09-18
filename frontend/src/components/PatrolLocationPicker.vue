<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";

const props = defineProps({
  latitude: { type: [String, Number], default: "" },
  longitude: { type: [String, Number], default: "" },
  existingPoints: { type: Array, default: () => [] },
  excludeId: { type: [String, Number], default: null },
});

const emit = defineEmits(["update:latitude", "update:longitude"]);
const mapElement = ref(null);
let map;
let marker;
let existingLayer;
let leafletPromise;

const defaultCenter = [-7.7956, 110.3695];
const displayPoints = computed(() => props.existingPoints.filter((point) => {
  if (props.excludeId !== null && String(point.id) === String(props.excludeId)) return false;
  return Number.isFinite(Number(point.latitude)) && Number.isFinite(Number(point.longitude));
}));

const loadLeaflet = () => {
  if (window.L) return Promise.resolve(window.L);
  if (leafletPromise) return leafletPromise;

  const css = document.createElement("link");
  css.rel = "stylesheet";
  css.href = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css";
  document.head.appendChild(css);

  leafletPromise = new Promise((resolve, reject) => {
    const script = document.createElement("script");
    script.src = "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js";
    script.onload = () => resolve(window.L);
    script.onerror = reject;
    document.head.appendChild(script);
  });

  return leafletPromise;
};

const coordinates = () => {
  const lat = Number(props.latitude);
  const lng = Number(props.longitude);
  return Number.isFinite(lat) && Number.isFinite(lng) && props.latitude !== "" && props.longitude !== ""
    ? [lat, lng]
    : defaultCenter;
};

const setLocation = (latlng) => {
  const lat = Number(latlng.lat).toFixed(7);
  const lng = Number(latlng.lng).toFixed(7);
  emit("update:latitude", lat);
  emit("update:longitude", lng);
  marker.setLatLng(latlng);
};

const renderExistingPoints = () => {
  if (!map || !window.L) return;
  existingLayer?.clearLayers();

  displayPoints.value.forEach((point) => {
    const lat = Number(point.latitude);
    const lng = Number(point.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    window.L.marker([lat, lng], { opacity: 0.82 })
      .bindTooltip(point.name || "Titik patroli", { direction: "top", offset: [0, -8] })
      .bindPopup(`<strong>${point.name || "Titik patroli"}</strong><br>${point.location_address || ""}`)
      .addTo(existingLayer);
  });
};

const locateUser = () => {
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(
    ({ coords }) => {
      const latlng = { lat: coords.latitude, lng: coords.longitude };
      map.setView(latlng, 17);
      setLocation(latlng);
    },
    () => {},
    { enableHighAccuracy: true, timeout: 10000 },
  );
};

onMounted(async () => {
  const L = await loadLeaflet();
  await nextTick();
  const center = coordinates();
  map = L.map(mapElement.value).setView(center, center === defaultCenter ? 15 : 18);
  existingLayer = L.layerGroup().addTo(map);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map);
  marker = L.marker(center, { draggable: true }).addTo(map);
  marker.on("dragend", () => setLocation(marker.getLatLng()));
  map.on("click", (event) => setLocation(event.latlng));
  renderExistingPoints();
  window.setTimeout(() => map.invalidateSize(), 0);
});

watch(() => [props.latitude, props.longitude], () => {
  if (!map || !marker) return;
  const [lat, lng] = coordinates();
  marker.setLatLng([lat, lng]);
  map.panTo([lat, lng]);
});

watch(() => props.existingPoints, renderExistingPoints, { deep: true });

onBeforeUnmount(() => {
  if (map) map.remove();
});
</script>

<template>
  <div class="location-picker">
    <div ref="mapElement" class="map"></div>
    <button type="button" class="location-button" @click="locateUser">Gunakan lokasi saya</button>
    <p class="map-help">Klik peta atau geser marker ke lokasi checkpoint. Koordinat akan terisi otomatis.</p>
    <div v-if="displayPoints.length" class="existing-points">
      <span class="existing-title">Titik patroli yang sudah ada</span>
      <div v-for="point in displayPoints" :key="point.id" class="existing-point">
        <strong>{{ point.name }}</strong>
        <span>{{ point.location_address || "Alamat belum tersedia" }}</span>
      </div>
    </div>
    <div class="coordinates">
      <span>Latitude: <strong>{{ latitude || "Belum dipilih" }}</strong></span>
      <span>Longitude: <strong>{{ longitude || "Belum dipilih" }}</strong></span>
    </div>
  </div>
</template>

<style scoped>
.location-picker { position: relative; }
.map { height: 330px; border: 1px solid #e2e4ea; border-radius: 12px; overflow: hidden; z-index: 0; }
.location-button { position: absolute; top: 12px; right: 12px; z-index: 401; border: 0; border-radius: 8px; padding: 9px 12px; background: #1f2454; color: white; font-size: 12px; font-weight: 700; cursor: pointer; box-shadow: 0 3px 10px rgba(0,0,0,.18); }
.map-help { margin: 8px 0 0; color: #8a8d9c; font-size: 11px; }
.existing-points { display: grid; gap: 6px; margin-top: 12px; padding: 10px 12px; border: 1px solid #e2e4ea; border-radius: 10px; background: #f8f9fb; }
.existing-title { color: #1f2454; font-size: 11px; font-weight: 800; }
.existing-point { display: flex; justify-content: space-between; gap: 12px; color: #8a8d9c; font-size: 11px; }
.existing-point strong { color: #1f2454; }
@media (max-width: 650px) { .existing-point { flex-direction: column; gap: 2px; } }
.coordinates { display: flex; gap: 18px; flex-wrap: wrap; margin-top: 8px; color: #8a8d9c; font-size: 11px; }
.coordinates strong { color: #1f2454; }
</style>
