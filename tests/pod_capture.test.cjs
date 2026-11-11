// Deterministic camera/GPS tests. Hardware permissions still need an actual-device check.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname,'../resources/js/pod-capture.js'),'utf8');
function setup({cameraError, gpsError, stale=false, responseOk=true}={}) {
  const elements = new Map();
  const ctx = {beginPath(){},moveTo(){},lineTo(){},stroke(){},clearRect(){},drawImage(){ calls.frames++; }};
  const calls = {frames:0,requests:0,redirects:[],stops:0};
  function element(id) {
    if (!elements.has(id)) elements.set(id, {id,value:'',hidden:false,disabled:false,listeners:{},
      addEventListener(name,fn){this.listeners[name]=fn;},getContext(){return ctx;},
      getBoundingClientRect(){return {width:320,height:160,left:0,top:0};},setPointerCapture(){},
      toDataURL(){return 'data:image/png;base64,test';}, toBlob(fn){fn({type:'image/jpeg'});},
      videoWidth:1280,videoHeight:720,play:async()=>{},action:'/driver/pod/1',
    });
    return elements.get(id);
  }
  const track={readyState:'live',stop(){calls.stops++;this.readyState='ended';}};
  const stream={getTracks:()=>[track],getVideoTracks:()=>[track]};
  const sandbox={console,Number,Date,Math,Object,Error,Promise,
    document:{getElementById:element},
    window:{isSecureContext:true,addEventListener(){}},
    navigator:{mediaDevices:{getUserMedia:async options=>{calls.camera=options;if(cameraError)throw cameraError;track.readyState='live';return stream;}},
      geolocation:{getCurrentPosition(success,error,options){calls.gps=options;if(gpsError)error(gpsError);else success({timestamp:Date.now()-(stale?60000:0),coords:{latitude:14.5995123,longitude:120.9842345,accuracy:6.25}});}}},
    URL:{createObjectURL:()=> 'blob:test',revokeObjectURL(){}},
    FormData:class {constructor(){this.data={};} set(k,...v){this.data[k]=v;}},
    fetch:async (url,options)=>{calls.requests++;calls.request=options;return {ok:responseOk,redirected:false,json:async()=>responseOk?{ok:true,redirect:'/driver/dashboard'}:{ok:false,error:'Save failed'}};},
    location:{assign:url=>calls.redirects.push(url)},
  };
  vm.runInNewContext(source,sandbox);
  const event={preventDefault(){},pointerId:1,clientX:20,clientY:20};
  const click = id => element(id).listeners.click(event);
  const submit = () => element('podForm').listeners.submit(event);
  const sign = () => {element('sig').listeners.pointerdown(event);element('sig').listeners.pointermove({...event,clientX:40});element('sig').listeners.pointerup(event);};
  return {element,click,submit,sign,calls};
}
test('fresh GPS fix is paired with a live camera frame and exact hidden fields',async()=>{
  const t=setup(); await t.click('podStart'); await t.click('podCapture');
  assert.equal(t.calls.gps.maximumAge,0);assert.equal(t.calls.gps.enableHighAccuracy,true);
  assert.equal(t.calls.frames,1);assert.equal(t.element('pod_latitude').value,14.5995123);
  assert.equal(t.element('pod_longitude').value,120.9842345);assert.equal(t.element('pod_accuracy').value,6.25);
  assert.ok(Math.abs(t.element('pod_captured_at').value-t.element('pod_location_at').value)<10000);
  assert.equal(t.element('submitBtn').disabled,false); assert.equal(t.calls.stops,1);
});
test('location denial leaves delivery disabled and does not capture a photo',async()=>{
  const t=setup({gpsError:{code:1}});await t.click('podStart');await t.click('podCapture');await t.submit();
  assert.equal(t.calls.frames,0);assert.equal(t.calls.requests,0);assert.equal(t.element('submitBtn').disabled,true);
  assert.equal(t.element('pod_latitude').value,'');
});
test('stale GPS cannot be paired with a new camera frame',async()=>{
  const t=setup({stale:true});await t.click('podStart');await t.click('podCapture');
  assert.equal(t.calls.frames,0);assert.equal(t.element('submitBtn').disabled,true);
});
test('camera denial leaves delivery disabled',async()=>{
  const t=setup({cameraError:{name:'NotAllowedError'}});await t.click('podStart');await t.submit();
  assert.equal(t.calls.requests,0);assert.equal(t.element('submitBtn').disabled,true);
});
test('signature required; valid capture submits once and redirects only after successful save',async()=>{
  const t=setup();await t.click('podStart');await t.click('podCapture');await t.submit();
  assert.equal(t.calls.requests,0);t.sign();await Promise.all([t.submit(),t.submit()]);
  assert.equal(t.calls.requests,1);assert.deepEqual(t.calls.redirects,['/driver/dashboard']);
  assert.equal(t.calls.request.body.data.photo[1],'delivery.jpg');
});
test('retake discards previous photo coordinates until a new capture',async()=>{
  const t=setup();await t.click('podStart');await t.click('podCapture');await t.click('podRetake');
  assert.equal(t.element('pod_latitude').value,'');assert.equal(t.element('submitBtn').disabled,true);
  t.sign();await t.submit();assert.equal(t.calls.requests,0);
});
test('save failure stays on proof form and allows retry',async()=>{
  const t=setup({responseOk:false});await t.click('podStart');await t.click('podCapture');t.sign();await t.submit();
  assert.equal(t.calls.redirects.length,0);assert.equal(t.element('submitBtn').disabled,false);
  assert.match(t.element('podCaptureStatus').textContent,/Save failed/);
});
