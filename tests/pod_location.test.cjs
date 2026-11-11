const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const vm=require('node:vm');const path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../resources/js/pod-location.js'),'utf8');
function setup({failure=false,empty=false,deferred=false}={}){
 const calls=[];let release;const pending=new Promise(resolve=>{release=resolve;});
 const sandbox={Map,WeakMap,Promise,Date,Number,String,Math,URL,AbortController,Error,
  setTimeout:(fn,delay)=>{if(delay<8000)queueMicrotask(fn);return 1;},clearTimeout(){},
  window:{},document:{querySelectorAll:()=>[]},
  fetch:async(url,options)=>{calls.push({url,options});if(deferred)await pending;if(failure)throw Error('offline');return {ok:true,json:async()=>({features:empty?[]:[{properties:{name:'Manila City Hall',street:'Padre Burgos Avenue',city:'Manila',state:'Metro Manila',country:'Philippines'}}]})};},
 };
 vm.runInNewContext(source,sandbox);
 const elements=new Map();const container={dataset:{},hidden:true,querySelector(selector){if(!elements.has(selector))elements.set(selector,{textContent:''});return elements.get(selector);}};
 return {api:sandbox.window.DropITPhotoLocation,container,calls,release,text:selector=>container.querySelector(selector).textContent};
}
const sample={latitude:14.5995123456789,longitude:120.98423456789,accuracy:6.25,capturedAt:'2026-11-04T00:00:00.000Z'};
test('location address appears above full-precision coordinates, on a single coordinate line',async()=>{
 const t=setup();await t.api.show(t.container,sample);
 assert.match(t.text('[data-location-address]'),/^Location: Manila City Hall, Padre Burgos Avenue, Manila, Metro Manila, Philippines$/);
 assert.equal(t.text('[data-location-coordinates]'),'Latitude: 14.5995123456789   Longitude: 120.98423456789');
 const url=new URL(t.calls[0].url);assert.equal(url.searchParams.get('lat'),String(sample.latitude));assert.equal(url.searchParams.get('lon'),String(sample.longitude));assert.equal(t.calls[0].options.credentials,'omit');
});
test('lookup failure keeps raw coordinates and reports address unavailable',async()=>{const t=setup({failure:true});await t.api.show(t.container,sample);assert.match(t.text('[data-location-address]'),/Address unavailable/);assert.match(t.text('[data-location-coordinates]'),/14.5995123456789/);});
test('empty geocoding result has a graceful fallback',async()=>{const t=setup({empty:true});await t.api.show(t.container,sample);assert.match(t.text('[data-location-address]'),/Address unavailable/);});
test('retake clears the old label and ignores its delayed lookup response',async()=>{const t=setup({deferred:true});const request=t.api.show(t.container,sample);t.api.clear(t.container);t.release();await request;assert.equal(t.container.hidden,true);assert.equal(t.text('[data-location-address]'),'');assert.equal(t.text('[data-location-coordinates]'),'');});
test('same-coordinate captures reuse the address lookup',async()=>{const t=setup();await t.api.show(t.container,sample);await t.api.show(t.container,sample);assert.equal(t.calls.length,1);});
test('a previous address response cannot overwrite a later capture',async()=>{
 const t=setup({deferred:true});const a=t.api.show(t.container,sample);const b=t.api.show(t.container,{...sample,latitude:15.123456789});t.release();await Promise.all([a,b]);assert.equal(t.text('[data-location-coordinates]'),'Latitude: 15.123456789   Longitude: 120.98423456789');
});
