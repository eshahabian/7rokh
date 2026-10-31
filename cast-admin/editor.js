(() => {
  const $=id=>document.getElementById(id);
  const base=document.querySelector('meta[name=site-base]').content;
  const token=document.querySelector('meta[name="editor-token"]').content;
  const designUser=JSON.parse(document.querySelector('meta[name="current-user"]').content);
  if(designUser.role!=='admin')for(const type of ['menus','images'])document.querySelector('[data-tab="'+type+'"]').hidden=!(designUser.permissions?.[type]||[]).length;
  let serverVersion=0,loadNumber=0,loadAbort=null;
  const canDesign=designUser.role==='admin'||!!designUser.permissions?.design?.length;document.querySelector('[data-tab=design]').hidden=!canDesign;
  const state={mode:'pages',pageId:'home',page:null,pages:[],changes:new Map(),scope:'all',query:'',busy:false,menu:null,menuOriginal:'',selected:null,canUndo:false,designDirty:false,loading:false,images:[]};
  const scopes={page:'محتوای صفحه',header:'مشترک در تمام صفحات',footer:'فوتر مشترک'};
  const element=(tag,className,text)=>{const e=document.createElement(tag);if(className)e.className=className;if(text!==undefined)e.textContent=text;return e};
  let toastTimer;
  function toast(message,error=false){$('toast').textContent=message;$('toast').classList.toggle('error',error);$('toast').hidden=false;clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('toast').hidden=true,5500)}
  async function api(route,body){const response=await fetch(base+'/cast-admin/api.php?r='+route.slice(5).replace('?','&'),{method:body===undefined?'GET':'POST',headers:{'X-Editor-Token':token,...(body===undefined?{}:{'Content-Type':'application/json'})},...(body===undefined?{}:{body:JSON.stringify({...body,stateVersion:serverVersion})})});let data;try{data=await response.json()}catch{throw Error('پاسخ پنل قابل خواندن نیست؛ اتصال و خطای PHP هاست را بررسی کنید.')}if(response.ok&&Number.isInteger(data.stateVersion))serverVersion=Math.max(serverVersion,data.stateVersion);if(response.status===401){location.reload();throw Error('نشست تمام شده است.')}if(!response.ok)throw Error(data.error||'درخواست انجام نشد.');return data}
  const dirty=()=>state.mode==='design'?state.designDirty:state.mode==='menus'?!!state.menu&&JSON.stringify(state.menu.items)!==state.menuOriginal:state.changes.size>0;
  function controls(){const changed=dirty();$('save').disabled=state.busy||state.loading||!changed;$('save').textContent=state.busy?'در حال ذخیره…':state.mode==='menus'?'ذخیرهٔ منو':'ذخیرهٔ تغییرات';$('undo').disabled=state.busy||!state.canUndo;if(!state.busy&&!state.loading)$('status').textContent=changed?(state.mode==='menus'?'منو تغییر کرده؛ برای اعمال در همهٔ صفحات ذخیره کن.':state.mode==='design'?'طراحی تغییر کرده و هنوز ذخیره نشده است.':state.changes.size.toLocaleString('fa-IR')+' متن تغییر کرده و هنوز ذخیره نشده است.'):'تغییرات این صفحه ذخیره است.';}
  function canLeave(){return !dirty()||confirm('تغییرات هنوز ذخیره نشده‌اند. بدون ذخیره ادامه می‌دهی؟')}
  function setPreview(url){$('preview').src=url+(url.includes('?')?'&':'?')+'v='+Date.now();$('open-site').href=state.page?.publicUrl||base+'/index.php';}
  function previewMessage(message){$('preview').contentWindow?.postMessage(message,location.origin)}
  function previewMode(){previewMessage({type:'casting-preview-mode',mode:state.mode,images:state.images})}
  function setViewport(size){$('preview-size').value=size;document.querySelector('.preview-frame').dataset.size=size}
  async function openDesign(){await window.castingDesign.open({api,page:state.pageId,images:state.images,onDirty:value=>{state.designDirty=value;controls()},preview:previewMessage,viewport:setViewport});previewMode()}
  async function loadPage(id,{force=false}={}){
    if(state.busy||(!force&&!canLeave())){$('page-select').value=state.pageId;return}
    const number=++loadNumber;loadAbort?.abort();const abort=new AbortController();loadAbort=abort;const timer=setTimeout(()=>abort.abort(),30000);
    state.loading=true;$('design-editor').inert=true;state.changes.clear();state.designDirty=false;state.selected=null;state.scope='all';state.query='';$('search').value='';
    const requestedMode=state.mode==='design'?'design':'pages';state.mode=requestedMode;state.pageId=id;$('page-select').value=id;updateMode();$('fields').replaceChildren();$('design-properties').replaceChildren();$('status').textContent='در حال بارگذاری صفحه و خواندن بخش‌های قابل ویرایش…';$('preview-status').textContent='در حال آماده‌سازی پیش‌نمایش…';controls();
    document.querySelectorAll('.page-button').forEach(b=>b.classList.toggle('active',b.dataset.page===id));
    try{
      let page=await api('/api/page?id='+encodeURIComponent(id));if(number!==loadNumber)return;state.page=page;$('page-title').textContent=page.title;$('open-site').href=page.publicUrl;
      const response=await fetch(page.url,{credentials:'same-origin',cache:'no-store',signal:abort.signal});const html=await response.text();if(number!==loadNumber)return;
      if(!response.ok)throw Error('پیش‌نمایش صفحه بارگذاری نشد (کد '+response.status+'). صفحه را در سایت باز کنید یا دوباره تلاش کنید.');
      if(response.headers.get('X-Casting-Preview-Page')!==id||!html.includes('/cast-admin/preview-ready.js'))throw Error('این صفحه به ورود هدایت شده یا خروجی قابل ویرایش ندارد. برای صفحه‌های اعضا ابتدا با حساب مجاز خود سایت وارد شوید و سپس پیش‌نمایش را تازه کنید.');
      page=await api('/api/page?id='+encodeURIComponent(id));if(number!==loadNumber)return;state.page=page;
      state.images=(await api('/api/images')).images;if(number!==loadNumber)return;
      if(state.mode==='design'){await openDesign();if(number===loadNumber)$('design-editor').inert=false}else renderFields();if(number!==loadNumber)return;
      setPreview(page.url);$('preview-status').textContent='صفحه آمادهٔ ویرایش است.';
      document.querySelectorAll('.filter').forEach(b=>b.classList.toggle('active',b.dataset.scope==='all'));
      state.loading=false;controls();if(!page.fields.length&&state.mode==='pages')$('status').textContent='این صفحه متن مجاز ندارد؛ برای تغییر ظاهر یا عکس از بخش مربوط استفاده کنید.';
    }catch(error){if(number!==loadNumber)return;state.loading=false;$('preview').removeAttribute('src');const message=error.name==='AbortError'?'زمان بارگذاری صفحه تمام شد. با دکمهٔ تازه‌سازی دوباره تلاش کنید.':error.message;$('preview-status').textContent=message;$('status').textContent=message;$('fields').replaceChildren(element('p','empty',message));toast(message,true);controls();$('status').textContent=message;
    }finally{clearTimeout(timer)}
  }
  function updateMode(){previewMode();const menus=state.mode==='menus';$('section-label').textContent=menus?'تنظیمات سایت':state.mode==='design'?'طراحی و چیدمان':'متن صفحات';$('page-tools').hidden=state.mode!=='pages';$('fields').hidden=state.mode!=='pages';$('design-editor').hidden=state.mode!=='design';$('image-editor').hidden=state.mode!=='images';$('menu-editor').hidden=!menus;document.querySelector('.sidebar-pages').hidden=!['pages','design'].includes(state.mode);document.querySelector('.editing-pane').scrollTop=0;document.querySelectorAll('.side-tab').forEach(b=>b.classList.toggle('active',b.dataset.tab===state.mode));}
  function normalize(t){return t.replace(/[ي]/g,'ی').replace(/[ك]/g,'ک').replace(/\u200c/g,' ').toLowerCase()}
  function visibleFields(){return state.page.fields.filter(f=>(state.scope==='all'||f.scope===state.scope)&&(!state.query||normalize((state.changes.get(f.id)??f.value)+' '+f.label).includes(normalize(state.query))))}
  function renderFields(){
    const root=$('fields');root.replaceChildren();const fields=visibleFields();$('field-count').textContent=fields.length.toLocaleString('fa-IR')+' متن';
    if(!fields.length){root.append(element('p','empty',state.page.fields.length?'متنی با این جست‌وجو پیدا نشد.':'بخش قابل‌ویرایش این صفحه را ادمین طراحی مشخص می‌کند.'));return}
    for(const f of fields){
      const card=element('div','field-card');card.dataset.field=f.id;card.classList.toggle('changed',state.changes.has(f.id));card.classList.toggle('selected',state.selected===f.id);
      const row=element('div','field-label'),label=element('label','',f.label),tag=element('span','field-tag'+(f.scope!=='page'?' shared':''),scopes[f.scope]);const input=element('textarea');input.value=state.changes.get(f.id)??f.value;input.dir='auto';input.rows=Math.max(1,Math.min(8,Math.ceil(input.value.length/47)));input.id='field-'+state.page.fields.indexOf(f);label.htmlFor=input.id;
      input.addEventListener('input',()=>{if(input.value.trim()===f.value)state.changes.delete(f.id);else state.changes.set(f.id,input.value);card.classList.toggle('changed',state.changes.has(f.id));controls()});
      input.addEventListener('focus',()=>{state.selected=f.id;root.querySelectorAll('.selected').forEach(e=>e.classList.remove('selected'));card.classList.add('selected');$('preview').contentWindow?.postMessage({type:'fara-focus-field',id:f.id},location.origin)});
      row.append(label,tag);card.append(row,input);root.append(card);
    }
  }
  function selectField(id){if(state.mode!=='pages'||!state.page.fields.some(f=>f.id===id))return;state.scope='all';state.query='';state.selected=id;$('search').value='';document.querySelectorAll('.filter').forEach(b=>b.classList.toggle('active',b.dataset.scope==='all'));renderFields();const card=[...$('fields').children].find(c=>c.dataset.field===id);if(card){card.scrollIntoView({block:'center',behavior:'smooth'});card.querySelector('textarea').focus({preventScroll:true})}}
  async function loadImages(focusId=null){if(state.busy||!canLeave())return;loadNumber++;loadAbort?.abort();state.loading=false;state.designDirty=false;state.changes.clear();state.mode='images';updateMode();$('page-title').textContent='مدیریت عکس‌ها';$('section-label').textContent='رسانه‌های سایت';await window.faraImages({api,pages:state.pages,focusId,pageId:state.pageId,onSave:()=>{state.canUndo=true;controls();setPreview(state.page?.url||base+'/cast-admin/preview.php?page=home')},toast,lock:value=>{state.busy=value;controls()}});controls()}
  async function loadMenu(){if(state.busy||!canLeave())return;loadNumber++;loadAbort?.abort();state.loading=false;state.designDirty=false;state.menu=await api('/api/menu'+(state.menu?.group?'?group='+encodeURIComponent(state.menu.group):''));state.menuOriginal=JSON.stringify(state.menu.items);state.mode='menus';state.changes.clear();updateMode();$('page-title').textContent='مدیریت منوها';renderMenus();setPreview(base+'/cast-admin/preview.php?page=home');controls()}
  function renderMenus(){
    const selector=$('menu-group');selector.replaceChildren(...state.menu.groups.map(g=>new Option(g.title,g.id)));selector.value=state.menu.group;selector.onchange=async()=>{if(!canLeave()){selector.value=state.menu.group;return}try{state.menu=await api('/api/menu?group='+encodeURIComponent(selector.value));state.menuOriginal=JSON.stringify(state.menu.items);renderMenus();controls()}catch(e){toast(e.message,true)}};const root=$('menu-items');root.replaceChildren();if(!state.menu.groups.length)root.append(element('p','empty','ابتدا پیش‌نمایش صفحه را باز کنید تا منوهای واقعی شناسایی شوند.'));
    state.menu.items.forEach((item,index)=>{
      const card=element('article','menu-card'),head=element('div','menu-card-header'),title=element('b','',item.label||'گزینهٔ جدید'),buttons=element('div','menu-controls');
      const move=(direction)=>{const target=index+direction;if(target<0||target>=state.menu.items.length)return;[state.menu.items[index],state.menu.items[target]]=[state.menu.items[target],state.menu.items[index]];renderMenus();controls()};
      for(const [text,label,action,disabled] of [['↑','جابه‌جایی به بالا',()=>move(-1),index===0],['↓','جابه‌جایی به پایین',()=>move(1),index===state.menu.items.length-1],['×','حذف گزینه',()=>{state.menu.items.splice(index,1);renderMenus();controls()},false]]){const b=element('button',text==='×'?'remove-item':'',text);b.type='button';b.title=label;b.setAttribute('aria-label',label);b.disabled=disabled;b.addEventListener('click',action);buttons.append(b)}
      head.append(title,buttons);const nameLabel=element('label','','عنوان گزینه'),name=element('input');name.value=item.label;name.maxLength=80;name.setAttribute('aria-label','عنوان گزینه '+(index+1));name.addEventListener('input',()=>{item.label=name.value;title.textContent=name.value||'گزینهٔ جدید';controls()});
      const destinationLabel=element('label','','صفحهٔ مقصد'),select=element('select');select.setAttribute('aria-label','مقصد گزینه '+(index+1));for(const target of state.menu.destinations){const option=element('option','',target.title);option.value=target.href;select.append(option)}const other=element('option','','لینک دلخواه یا بخش مشخص صفحه');other.value='__custom__';select.append(other);
      const custom=element('input','custom-link');custom.dir='ltr';custom.placeholder='https://example.com یا index.php#about';custom.setAttribute('aria-label','لینک دلخواه گزینه '+(index+1));const known=state.menu.destinations.some(t=>t.href===item.href);select.value=known?item.href:'__custom__';custom.hidden=known;custom.value=known?'':item.href;
      select.addEventListener('change',()=>{custom.hidden=select.value!=='__custom__';item.href=custom.hidden?select.value:custom.value;controls()});custom.addEventListener('input',()=>{item.href=custom.value;controls()});
      card.append(head,nameLabel,name,destinationLabel,select,custom);root.append(card);
    });$('add-menu').disabled=state.menu.items.length>=20;
  }
  async function save(){if(state.busy||state.loading||!dirty())return;state.busy=true;controls();document.querySelectorAll('.editing-pane input,.editing-pane textarea,.editing-pane select,.editing-pane button').forEach(e=>e.disabled=true);
    try{const result=state.mode==='design'?(await window.castingDesign.save(),{message:'طراحی ذخیره شد.'}):state.mode==='menus'?await api('/api/menu',{group:state.menu.group,revision:state.menu.revision,items:state.menu.items}):await api('/api/save',{page:state.pageId,revision:state.page.revision,changes:[...state.changes].map(([id,value])=>({id,value}))});state.canUndo=true;state.busy=false;state.changes.clear();if(state.mode==='design'){state.designDirty=false;await loadPage(state.pageId,{force:true})}else if(state.mode==='menus'){state.menu=await api('/api/menu');state.menuOriginal=JSON.stringify(state.menu.items);renderMenus();setPreview(base+'/cast-admin/preview.php?page=home')}else await loadPage(state.pageId,{force:true});controls();$('status').textContent='ذخیره شد · '+new Intl.DateTimeFormat('fa-IR',{hour:'2-digit',minute:'2-digit',timeZone:'Asia/Tehran'}).format(new Date());toast(result.message)}catch(error){state.busy=false;controls();toast(error.message,true)}finally{document.querySelectorAll('.editing-pane input,.editing-pane textarea,.editing-pane select,.editing-pane button').forEach(e=>e.disabled=false);if(state.mode==='menus')renderMenus()}}
  $('save').addEventListener('click',save);
  $('undo').addEventListener('click',async()=>{if(state.busy||!confirm('آخرین ذخیرهٔ محتوا، تصویر یا طراحی به حالت قبل برگردد؟'))return;state.busy=true;controls();try{const result=await api('/api/undo',{});state.busy=false;state.changes.clear();if(state.mode==='images'){await loadImages()}else if(state.mode==='menus'){state.menu=await api('/api/menu');state.menuOriginal=JSON.stringify(state.menu.items);renderMenus();setPreview(base+'/cast-admin/preview.php?page=home')}else await loadPage(state.pageId,{force:true});controls();toast(result.message)}catch(error){state.busy=false;controls();toast(error.message,true)}});
  $('search').addEventListener('input',()=>{state.query=$('search').value;renderFields()});
  document.querySelectorAll('.filter').forEach(button=>button.addEventListener('click',()=>{state.scope=button.dataset.scope;document.querySelectorAll('.filter').forEach(b=>b.classList.toggle('active',b===button));renderFields()}));
  document.querySelectorAll('.side-tab').forEach(button=>button.addEventListener('click',()=>{if(button.dataset.tab===state.mode)return;(button.dataset.tab==='images'?loadImages():button.dataset.tab==='menus'?loadMenu():(async()=>{if(!canLeave())return;state.changes.clear();state.designDirty=false;state.mode=button.dataset.tab;await loadPage(state.pageId,{force:true})})()).catch(e=>toast(e.message,true))}));
  $('add-menu').addEventListener('click',()=>{state.menu.items.push({id:'item-'+crypto.randomUUID(),label:'گزینهٔ جدید',href:'index.php'});renderMenus();controls();$('menu-items').lastElementChild?.scrollIntoView({behavior:'smooth',block:'center'})});
  $('refresh-preview').addEventListener('click',()=>{if(['pages','design'].includes(state.mode))loadPage(state.pageId).catch(e=>toast(e.message,true));else if(state.page)setPreview(state.page.url)});
  $('preview-size').onchange=()=>setViewport($('preview-size').value);
  $('page-select').onchange=()=>loadPage($('page-select').value).catch(e=>toast(e.message,true));
  $('page-search').oninput=()=>{const query=normalize($('page-search').value);document.querySelectorAll('.page-button').forEach(b=>b.hidden=!normalize(b.textContent+' '+b.dataset.page).includes(query))};
  $('preview').addEventListener('load',previewMode);
  window.addEventListener('message',event=>{if(event.origin!==location.origin||event.source!==$('preview').contentWindow)return;
    if(event.data?.type==='fara-select-field')selectField(event.data.id);
    if(event.data?.type==='casting-select-design'&&state.mode==='design'&&!state.loading)window.castingDesign.select(event.data.id);
    if(event.data?.type==='casting-select-image')loadImages(event.data.id).catch(e=>toast(e.message,true));
    if(event.data?.type==='casting-preview-ready')previewMode();
  });
  document.addEventListener('keydown',event=>{if((event.ctrlKey||event.metaKey)&&event.key.toLowerCase()==='s'){event.preventDefault();save()}});
  window.addEventListener('beforeunload',event=>{if(dirty()){event.preventDefault();event.returnValue=''}});
  async function init(){const data=await api('/api/pages');state.pages=data.pages;state.canUndo=data.canUndo;$('page-select').replaceChildren(...data.pages.map(p=>new Option(p.title+' · '+p.id,p.id)));for(const page of data.pages){const b=element('button','page-button',page.title);b.dataset.page=page.id;b.addEventListener('click',()=>loadPage(page.id).catch(e=>toast(e.message,true)));$('page-list').append(b)}if(data.pages.length)await loadPage(data.pages[0].id,{force:true});else{$('page-title').textContent='دسترسی طراحی تعیین نشده';$('status').textContent='از ادمین بخواهید صفحه‌ها و بخش‌های مجاز این حساب طراحی را انتخاب کند.';controls();$('status').textContent='ادمین هنوز بخشی برای ویرایش به شما اختصاص نداده است.'}}
  init().catch(error=>{$('page-title').textContent='پنل آماده نشد';$('status').textContent=error.message;toast(error.message,true)});
})();
