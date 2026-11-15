const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const source=fs.readFileSync('app/views/admin/assign_driver.php','utf8');
const hide=source.slice(source.indexOf('  function hideModal(){'),source.indexOf('  dToggleBtn.onclick'));
const handler=source.slice(source.indexOf('  dConfirmBtn.onclick = async () => {'),source.indexOf('    // Price adjustment flow'))+'};';
function setup(driver='5') {
 const state={submitted:0,hidden:false,error:''};
 const ctx=vm.createContext({state});
 vm.runInContext(`
  let _adjusting=false,_parcelData={id:26};
  let _pendingForm={querySelector(){return {value:${JSON.stringify(driver)}}},submit(){state.submitted++}};
  const modal={style:{},classList:{add(){state.hidden=true}}};
  const dError={classList:{add(){},remove(){}},set textContent(v){state.error=v}};
  const dConfirmBtn={};
  ${hide}\n${handler}
  globalThis.confirmDispatch=()=>dConfirmBtn.onclick();
 `,ctx);
 return {state,confirm:()=>ctx.confirmDispatch()};
}
test('normal confirmation submits the selected form after closing the modal',async()=>{
 const t=setup(); await t.confirm();
 assert.equal(t.state.submitted,1); assert.equal(t.state.hidden,true);
});
test('missing driver stays open with feedback instead of dispatching',async()=>{
 const t=setup('');await t.confirm();
 assert.equal(t.state.submitted,0);assert.equal(t.state.hidden,false);
 assert.match(t.state.error,/select a driver/i);
});
test('a repeated confirmation cannot submit the cleared form twice',async()=>{
 const t=setup();await t.confirm();await t.confirm();
 assert.equal(t.state.submitted,1);
});
