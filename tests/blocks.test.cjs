const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function load(settings = {}, states = ['bn', 'FAKE-REFERENCE']) {
    let registration, callback;
    const elements = [];
    const context = {
        navigator: {userAgent: 'Android'},
        wc: {wcSettings: {getSetting: () => ({title: 'SINPE', showBanks: true, askVoucher: true, showLink: true, banks: [{id:'none',label:'Select',number:''},{id:'bn',label:'BN',number:'2627'}], ...settings})}, wcBlocksRegistry: {registerPaymentMethod: (method) => {registration=method;}}},
        wp: {element: {createElement: (type, props, ...children) => {const e={type,props:props||{},children};elements.push(e);return e;}, useState: () => [states.shift(),()=>{}], useEffect: (effect) => effect()}, htmlEntities: {decodeEntities: (text)=>text}, i18n: {__: (text)=>text,sprintf: (text,...values)=>text.replace(/%([0-9]+\$)?s/g,(match,index)=>String(values[index?Number(index.slice(0,-1))-1:0]))}}
    };
    vm.runInNewContext(fs.readFileSync('includes/checkout.js','utf8'), context);
    function render(overrides = {}) {
        const props={cartData:{extensions:{'mojito-sinpe':{available:true,cart_total:2000,amount:'20',recipient:'88889999',message:'Pase 20 88889999'}}},billing:{cartTotal:{value:2000}},checkoutStatus:{isCalculating:false},eventRegistration:{onPaymentSetup:(fn)=>{callback=fn;return ()=>{};}},emitResponse:{responseTypes:{ERROR:'error',SUCCESS:'success'}},...overrides};
        registration.content.type(props);
        return {result: callback(), elements};
    }
    return {registration, render, context};
}
test('payment only available with authoritative cart extension',()=>{
 const {registration:r}=load();
 assert.equal(r.name,'mojito-sinpe');assert.equal(r.canMakePayment({cart:{extensions:{'mojito-sinpe':{available:true}}}}),true);
 assert.equal(r.canMakePayment({cart:{extensions:{}}}),false);
});
test('bank and voucher are sent with expected payment_data names',()=>{
 const {result}=load().render();assert.equal(result.type,'success');
 assert.equal(result.meta.paymentMethodData.mojito_sinpe_bank,'bn');assert.equal(result.meta.paymentMethodData.mojito_sinpe_voucher_id,'FAKE-REFERENCE');
});
test('malformed or unselected bank blocks checkout',()=>assert.equal(load({},['none','FAKE']).render().result.type,'error'));
test('blank required voucher blocks checkout',()=>assert.equal(load({},['bn','  ']).render().result.type,'error'));
test('stale server total blocks checkout and removes SMS link',()=>{
 const {result,elements}=load().render({billing:{cartTotal:{value:3500}}});assert.equal(result.type,'error');assert.equal(elements.filter(e=>e.type==='a').length,0);
});
test('calculation in progress blocks checkout and removes SMS link',()=>{
 const {result,elements}=load().render({checkoutStatus:{isCalculating:true}});assert.equal(result.type,'error');assert.equal(elements.filter(e=>e.type==='a').length,0);
});
test('fresh total uses refreshed server amount in SMS',()=>{
 const {elements}=load().render({cartData:{extensions:{'mojito-sinpe':{available:true,cart_total:2000,amount:'10205',message:'Pase 10205 88889999'}}}});
 assert.equal(elements.find(e=>e.type==='a').props.href,'sms:2627?body=Pase%2010205%2088889999');
});
test('iPhone URL uses the same protocol convention as PHP',()=>{
 const app=load();app.context.navigator.userAgent='iPhone';const {elements}=app.render();assert.equal(elements.find(e=>e.type==='a').props.href,'sms:2627&body=Pase%2020%2088889999');
});
test('hidden bank/voucher configuration permits manual transfer',()=>assert.equal(load({showBanks:false,askVoucher:false},['none','']).render().result.type,'success'));
test('hidden checkout link leaves amount visible and voucher validation active',()=>{
 const {result,elements}=load({showLink:false}).render();assert.equal(result.type,'success');assert.equal(elements.filter(e=>e.type==='a').length,0);
});

test('required voucher with hidden SMS link still shows app payment recipient',()=>{
 const {elements}=load({showLink:false}).render();assert.ok(elements.some(e=>e.props.key==='manual' && e.children[0].includes('88889999')));
});
