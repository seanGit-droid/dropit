(() => {
  'use strict';
  const form = document.getElementById('podForm');
  if (!form) return;
  const video = document.getElementById('podVideo');
  const preview = document.getElementById('podPreview');
  const canvas = document.getElementById('podCanvas');
  const start = document.getElementById('podStart');
  const capture = document.getElementById('podCapture');
  const retake = document.getElementById('podRetake');
  const submit = document.getElementById('submitBtn');
  const status = document.getElementById('podCaptureStatus');
  const coordinates = document.getElementById('podCoordinates');
  let stream = null, photo = null, objectUrl = null, busy = false;
  const field = name => document.getElementById(`pod_${name}`);
  const stop = () => { if (stream) stream.getTracks().forEach(t => t.stop()); stream = null; video.srcObject = null; };
  function reset() {
    photo = null; submit.disabled = true; preview.hidden = true; retake.hidden = true;
    coordinates.textContent = '';
    window.DropITPhotoLocation?.clear(document.getElementById('podLocationDisplay'));
    ['latitude','longitude','accuracy','captured_at','location_at'].forEach(k => { field(k).value = ''; });
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    objectUrl = null;
  }
  async function openCamera() {
    if (busy) return;
    reset(); stop(); busy = true; start.disabled = true; capture.disabled = true;
    status.textContent = 'Opening camera…';
    try {
      if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia || !navigator.geolocation) {
        throw new Error('Camera and location need HTTPS (or localhost) and a supported browser.');
      }
      stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1600}},audio:false});
      video.srcObject = stream; video.hidden = false;
      await video.play();
      capture.disabled = false;
      status.textContent = 'Camera ready. Frame the parcel, then take the photo.';
    } catch (e) {
      stop(); video.hidden = true;
      status.textContent = e.name === 'NotAllowedError' ? 'Camera permission denied. Allow camera access in your browser, then retry.' : e.message;
    } finally { busy = false; start.disabled = false; }
  }
  start.addEventListener('click', openCamera);
  retake.addEventListener('click', openCamera);
  capture.addEventListener('click', async () => {
    if (busy || !stream) return;
    busy = true; capture.disabled = true; start.disabled = true; submit.disabled = true;
    status.textContent = 'Getting a fresh device location. Keep the parcel in the frame…';
    try {
      const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy:true, maximumAge:0, timeout:25000,
      }));
      const capturedAt = Date.now();
      if (Math.abs(capturedAt - position.timestamp) > 10000) throw new Error('The location is stale. Please try taking the photo again.');
      if (!video.videoWidth || !video.videoHeight || !stream.getVideoTracks().some(t => t.readyState === 'live')) {
        throw new Error('The camera stopped. Open the camera and try again.');
      }
      const scale = Math.min(1, 1600 / Math.max(video.videoWidth, video.videoHeight));
      canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
      canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
      photo = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.88));
      if (!photo) throw new Error('The photo could not be captured. Please retry.');
      const c = position.coords;
      const values = {latitude:c.latitude, longitude:c.longitude, accuracy:c.accuracy, captured_at:capturedAt, location_at:position.timestamp};
      for (const [key,value] of Object.entries(values)) {
        if (!Number.isFinite(value)) throw new Error('The location is invalid. Please retry.');
        field(key).value = value;
      }
      objectUrl = URL.createObjectURL(photo); preview.src = objectUrl; preview.hidden = false;
      stop(); video.hidden = true; retake.hidden = false;
      coordinates.textContent = `Latitude: ${c.latitude}   Longitude: ${c.longitude}`;
      window.DropITPhotoLocation?.show(document.getElementById('podLocationDisplay'), {
        latitude:c.latitude,longitude:c.longitude,accuracy:c.accuracy,capturedAt:new Date(capturedAt).toISOString(),
      });
      status.textContent = 'Photo and device location captured together. Add the receiver signature to complete delivery.';
      submit.disabled = false;
    } catch (e) {
      reset();
      status.textContent = e.code === 1 ? 'Location permission denied. Allow location access and retake the photo. Delivery has not been completed.'
        : e.code === 3 ? 'Location timed out. Move to an area with a better signal and retry.' : (e.message || 'Could not capture location. Please retry.');
      capture.disabled = !stream;
    } finally { busy = false; start.disabled = false; }
  });
  // Preserve the existing required receiver signature, using pointer events on phones and desktops.
  const sig = document.getElementById('sig');
  sig.width = Math.max(1, Math.round(sig.getBoundingClientRect().width));
  const ctx = sig.getContext('2d');
  ctx.strokeStyle = '#0b1e3f'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
  let drawing = false, signed = false;
  const point = e => { const r = sig.getBoundingClientRect(); return [(e.clientX-r.left)*sig.width/r.width, (e.clientY-r.top)*sig.height/r.height]; };
  sig.addEventListener('pointerdown', e => {
    drawing = true; sig.setPointerCapture(e.pointerId); const [x,y] = point(e); ctx.beginPath(); ctx.moveTo(x,y); e.preventDefault();
  });
  sig.addEventListener('pointermove', e => { if (!drawing) return; const [x,y] = point(e); ctx.lineTo(x,y); ctx.stroke(); signed = true; });
  ['pointerup','pointercancel'].forEach(name => sig.addEventListener(name, () => { drawing = false; }));
  document.getElementById('sigClear').addEventListener('click', () => { ctx.clearRect(0,0,sig.width,sig.height); signed = false; });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (busy) return;
    if (!photo) { status.textContent = 'Take a camera photo with a fresh device location first.'; return; }
    if (!signed) { status.textContent = 'Please ask the receiver to sign first.'; return; }
    if (Date.now() - Number(field('captured_at').value) > 1800000) {
      reset(); status.textContent = 'This capture expired. Please retake the photo.'; return;
    }
    document.getElementById('sigData').value = sig.toDataURL('image/png');
    const data = new FormData(form); data.set('photo', photo, 'delivery.jpg');
    busy = true; submit.disabled = true; start.disabled = true; retake.disabled = true;
    status.textContent = 'Saving photo, location, and delivery…';
    try {
      const response = await fetch(form.action, {method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});
      if (response.redirected) throw new Error('Your session expired. Reload the page and sign in again.');
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Could not save delivery. Please retry.');
      location.assign(result.redirect);
    } catch (e) {
      status.textContent = `${e.message || 'Connection failed.'} If you lost the connection, check the parcel status before retrying.`;
      busy = false; submit.disabled = false; start.disabled = false; retake.disabled = false;
    }
  });
  window.addEventListener('pagehide', stop);
})();
