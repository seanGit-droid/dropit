(() => {
  'use strict';
  const cache = new Map();
  const versions = new WeakMap();
  let queue = Promise.resolve(), lastRequest = 0;
  function readableAddress(properties) {
    const street = [properties.housenumber, properties.street].filter(Boolean).join(' ');
    const parts = [properties.name, street, properties.locality, properties.district, properties.city, properties.county, properties.state, properties.postcode, properties.country];
    const unique = [];
    for (const part of parts) {
      if (typeof part !== 'string' || !part.trim()) continue;
      const value = part.trim();
      if (!unique.some(existing => existing.toLowerCase() === value.toLowerCase())) unique.push(value);
    }
    return unique.join(', ');
  }
  function lookup(latitude, longitude, endpoint) {
    const key = `${endpoint}|${latitude},${longitude}`;
    if (cache.has(key)) return cache.get(key);
    const result = queue.catch(() => {}).then(async () => {
      const wait = Math.max(0, 1100 - (Date.now() - lastRequest));
      if (wait) await new Promise(resolve => setTimeout(resolve, wait));
      lastRequest = Date.now();
      const url = new URL(endpoint);
      url.searchParams.set('lat', String(latitude));
      url.searchParams.set('lon', String(longitude));
      url.searchParams.set('limit', '1');
      url.searchParams.set('lang', 'en');
      const controller = new AbortController();
      const timer = setTimeout(() => controller.abort(), 8000);
      try {
        const response = await fetch(url.href, {credentials:'omit',signal:controller.signal,headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error('Address service unavailable');
        const data = await response.json();
        const feature = data.features?.[0];
        const address = feature?.properties ? readableAddress(feature.properties) : '';
        if (!address) throw new Error('No readable address found');
        return address;
      } finally { clearTimeout(timer); }
    });
    cache.set(key, result);
    // Serialize lookups and reuse results for repeated captures at the same coordinates.
    queue = result.catch(() => {});
    result.catch(() => { cache.delete(key); });
    if (cache.size > 50) cache.delete(cache.keys().next().value);
    return result;
  }
  function clear(container) {
    if (!container) return;
    versions.set(container, (versions.get(container) || 0) + 1);
    container.hidden = true;
    for (const selector of ['[data-location-address]','[data-location-coordinates]','[data-location-meta]']) {
      const element = container.querySelector(selector);
      if (element) element.textContent = '';
    }
  }
  async function show(container, {latitude,longitude,accuracy,capturedAt}) {
    if (!container || !Number.isFinite(latitude) || !Number.isFinite(longitude)) return;
    const version = (versions.get(container) || 0) + 1;
    versions.set(container, version);
    const address = container.querySelector('[data-location-address]');
    const coordinates = container.querySelector('[data-location-coordinates]');
    const meta = container.querySelector('[data-location-meta]');
    container.hidden = false;
    address.textContent = 'Location: Looking up address…';
    // Use device decimals verbatim. Geocoding must never replace the captured GPS values.
    coordinates.textContent = `Latitude: ${latitude}   Longitude: ${longitude}`;
    if (meta) meta.textContent = `Accuracy: ±${Math.round(accuracy)} m · ${capturedAt}`;
    try {
      const name = await lookup(latitude, longitude, container.dataset.geocoderUrl || 'https://photon.komoot.io/reverse');
      if (versions.get(container) === version) address.textContent = `Location: ${name}`;
    } catch (e) {
      if (versions.get(container) === version) address.textContent = 'Location: Address unavailable — GPS coordinates are shown below.';
    }
  }
  window.DropITPhotoLocation = {show, clear};
  // The saved-photo view uses the same display, without changing stored photo metadata.
  document.querySelectorAll('[data-pod-location][data-latitude]').forEach(container => {
    show(container, {
      latitude:Number(container.dataset.latitude),longitude:Number(container.dataset.longitude),
      accuracy:Number(container.dataset.accuracy),capturedAt:container.dataset.capturedAt,
    });
  });
})();
