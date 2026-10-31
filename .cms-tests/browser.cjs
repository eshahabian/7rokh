// Run against the disposable fixture created by CMS_KEEP_SERVER=1 integration.cjs.
const assert=require('node:assert/strict'),path=require('node:path'),os=require('node:os');
const {chromium}=require(process.env.CMS_PLAYWRIGHT_MODULE||path.join(os.tmpdir(),'casting-cms-browser-qa/node_modules/playwright-core'));
const port=Number(process.env.CMS_TEST_PORT||18795);assert(port>=18000&&port<=19000);
const base=`http://127.0.0.1:${port}/casting-portal`;
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  // Field loading must work even when the child never sends its ready message.
  await page.route('**/cast-admin/preview-ready.js',route=>route.abort());
  await page.goto(base+'/cast-admin/');await page.locator('#login-form [name=password]').fill('Test-Permanent-Password-981!');await page.locator('#login-form button').click();
  await page.waitForFunction(()=>document.querySelectorAll('#fields textarea').length>0);
  assert.equal(await page.locator('#page-select').inputValue(),'home');console.log('PASS browser initial page loads without iframe ready message');
  await page.locator('#page-select').selectOption('rules');await page.waitForFunction(()=>document.querySelector('#page-title').textContent==='قوانین'&&document.querySelector('#preview-status').textContent.includes('آمادهٔ'));
  assert(await page.locator('#fields textarea').count()>0);console.log('PASS browser dropdown loads selected page');
  await page.locator('.page-button[data-page=home]').click();await page.waitForFunction(()=>document.querySelector('#page-title').textContent==='خانه'&&document.querySelector('#preview-status').textContent.includes('آمادهٔ'));console.log('PASS browser page list loads selected page');
  await page.route(/preview\.php\?page=rules(?:&|$)/,route=>route.fulfill({status:200,contentType:'text/html',body:'<!doctype html><html><body>Login required</body></html>'}));await page.locator('#page-select').selectOption('rules');await page.waitForFunction(()=>document.querySelector('#preview-status').textContent.includes('هدایت'));assert.equal(await page.locator('#fields textarea').count(),0);console.log('PASS browser auth redirect reports cause without showing previous fields');await page.unroute(/preview\.php\?page=rules(?:&|$)/);await page.locator('#page-select').selectOption('home');await page.waitForFunction(()=>document.querySelector('#preview-status').textContent.includes('آمادهٔ'));
  await page.route(/api\.php\?r=page&id=rules/,async route=>{await new Promise(r=>setTimeout(r,200));await route.continue()});await page.locator('#page-select').selectOption('rules');await page.locator('#page-select').selectOption('home');await page.waitForFunction(()=>document.querySelector('#preview-status').textContent.includes('آمادهٔ'));await page.waitForTimeout(400);assert.equal(await page.locator('#page-title').textContent(),'خانه');console.log('PASS browser rapid selection keeps latest page');await page.unroute(/api\.php\?r=page&id=rules/);

  await page.locator('[data-tab=design]').click();await page.waitForFunction(()=>document.querySelector('#design-element').options.length>1);
  const preview=page.frameLocator('#preview');await preview.locator('h1').click();await page.waitForFunction(()=>document.querySelector('#design-element').value&&document.querySelector('[data-property=color]'));
  await page.locator('[data-property=color]').fill('#774411');assert.equal(await page.locator('#save').isEnabled(),true);await page.locator('#save').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('ذخیره شد'));
  const response=await page.request.get(base+'/index.php');assert((await response.text()).includes('color:#774411!important'));console.log('PASS browser click block and save design to public site');
  await preview.locator('h1').click();await page.locator('#design-viewport').selectOption('mobile');await page.locator('[data-property=font-size]').fill('22px');await page.locator('#save').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('ذخیره شد'));
  assert((await (await page.request.get(base+'/index.php')).text()).includes('font-size:22px!important'));console.log('PASS browser responsive design save');
  await page.locator('[data-tab=pages]').click();await page.waitForFunction(()=>document.querySelectorAll('#fields textarea').length>0&&document.querySelector('#preview-status').textContent.includes('آمادهٔ'));
  await preview.locator('img').click();await page.waitForFunction(()=>!document.querySelector('#image-editor').hidden&&document.querySelectorAll('.image-card').length>0);assert.equal(await page.locator('.selected-image').count(),1);console.log('PASS browser click actual image opens its replacement card');
  const png=Buffer.from(await page.evaluate(()=>{const canvas=document.createElement('canvas');canvas.width=5;canvas.height=4;canvas.getContext('2d').fillRect(0,0,5,4);return canvas.toDataURL('image/png').split(',')[1]}),'base64');
  await page.locator('.image-card input[type=file]').setInputFiles({name:'replacement.png',mimeType:'image/png',buffer:png});await page.locator('.image-card button', {hasText:'ذخیرهٔ عکس جدید'}).click();await page.waitForFunction(()=>document.querySelector('#toast').textContent.includes('عکس در همه'));
  console.log('PASS browser image replacement');
  await page.setViewportSize({width:390,height:844});await page.locator('[data-tab=pages]').click();await page.waitForFunction(()=>document.querySelectorAll('#fields textarea').length>0);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);assert.equal(await page.locator('#page-select').isVisible(),true);console.log('PASS browser mobile page selection and no horizontal overflow');
  if(process.env.CMS_QA_SCREENSHOT)await page.screenshot({path:process.env.CMS_QA_SCREENSHOT,fullPage:true});
  assert.deepEqual(errors,[]);console.log('PASS browser no JavaScript errors');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exitCode=1});
