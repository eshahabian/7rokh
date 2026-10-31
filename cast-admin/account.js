(()=>{
 const meta=n=>document.querySelector(`meta[name="${n}"]`).content;
 const base=meta('site-base'),token=meta('editor-token'),user=JSON.parse(meta('current-user')),$=id=>document.getElementById(id);
 async function api(route,body){
  const r=await fetch(base+'/cast-admin/api.php?r='+route,{method:body?'POST':'GET',headers:{'X-Editor-Token':token,...(body?{'Content-Type':'application/json'}:{})},...(body?{body:JSON.stringify(body)}:{})});
  const d=await r.json();if(r.status===401){location.reload();throw Error('نشست تمام شده است.')}if(!r.ok)throw Error(d.error||'درخواست انجام نشد.');return d;
 }
 $('account-name').textContent=user.username+(user.role==='admin'?' · مدیر طراحی':' · ویرایشگر طراحی');$('manage-users').hidden=user.role!=='admin';
 const dialog=$('account-dialog'),message=$('account-message');
 function open(mode){dialog.showModal();message.textContent='';$('users-panel').hidden=mode!=='users';$('change-panel').hidden=mode!=='password';$('account-title').textContent=mode==='users'?'حساب‌ها و دسترسی‌های طراحی':'تغییر رمز حساب طراحی';if(mode==='users')loadUsers().catch(e=>message.textContent=e.message)}
 $('manage-users').onclick=()=>open('users');$('change-password').onclick=()=>open('password');$('close-account').onclick=()=>dialog.close();
 $('logout').onclick=async()=>{if(!confirm('از پنل طراحی خارج می‌شوی؟'))return;try{await api('logout',{});location.reload()}catch(e){alert(e.message)}};
 function el(tag,text){const e=document.createElement(tag);if(text)e.textContent=text;return e}
 function permissionEditor(u,options){
  const box=el('details');box.className='design-permissions';box.append(el('summary','تعیین بخش‌های قابل ویرایش'));
  box.append(el('p','فقط بخش‌های انتخاب‌شده قابل ویرایش‌اند. هدر و فوتر مشترک روی همهٔ صفحات اثر دارند. تغییر دسترسی، نشست قبلی این حساب را باطل می‌کند.'));
  const values={},titles={pages:'تمام محتوای یک صفحه',fields:'متن‌های مشخص داخل صفحه',shared:'بخش‌های مشترک',menus:'منوهای مشخص',images:'تصویرهای مشخص',design:'طراحی و ظاهر بخش‌ها'};
  for(const [type,title] of Object.entries(titles)){
   const fieldset=el('fieldset');fieldset.append(el('legend',title));values[type]=[];
   const choices=options[type]||[];if(!choices.length)fieldset.append(el('small',type==='menus'?'برای شناسایی منوها، ابتدا پیش‌نمایش صفحهٔ موردنظر را باز کنید.':type==='fields'?'ابتدا پیش‌نمایش صفحه را باز کنید تا متن‌های قابل انتخاب شناسایی شوند.':'موردی برای انتخاب وجود ندارد.'));
   const list=el('div');list.className='design-permission-list';
   for(const choice of choices){const check=el('input');check.type='checkbox';check.checked=(u.permissions?.[type]||[]).includes(choice.id);check.value=choice.id;values[type].push(check);const label=el('label',choice.title);label.prepend(check);list.append(label)}
   fieldset.append(list);box.append(fieldset);
  }
  const save=el('button','ذخیرهٔ دسترسی‌های طراحی');save.type='button';save.className='button primary';save.onclick=()=>mutate({action:'permissions',id:u.id,permissions:Object.fromEntries(Object.entries(values).map(([type,checks])=>[type,checks.filter(c=>c.checked).map(c=>c.value)]))});box.append(save);return box;
 }
 async function loadUsers(){
  const {users,permissionOptions}=await api('users');const root=$('user-list');root.replaceChildren();
  for(const u of users){
   const card=el('article');card.className='user-card';card.append(el('b',u.username),el('p',(u.role==='admin'?'مدیر طراحی · تمام بخش‌های طراحی':'ویرایشگر · فقط بخش‌های تعیین‌شده')+' · '+(u.active?'فعال':'غیرفعال')+(u.mustChange?' · نیازمند تغییر رمز':'')));
   const role=el('select');role.append(new Option('مدیر طراحی','admin'),new Option('ویرایشگر طراحی','editor'));role.value=u.role;role.setAttribute('aria-label','نقش '+u.username);
   const active=el('input');active.type='checkbox';active.checked=u.active;const label=el('label','حساب طراحی فعال');label.prepend(active);
   const apply=el('button','ذخیرهٔ نقش و وضعیت');apply.type='button';apply.className='button';apply.onclick=()=>mutate({action:'update',id:u.id,role:role.value,active:active.checked});
   const pass=el('input');pass.type='password';pass.autocomplete='new-password';pass.placeholder='رمز موقت جدید (حداقل ۱۲ نویسه)';pass.minLength=12;pass.maxLength=128;pass.setAttribute('aria-label','رمز موقت '+u.username);
   const reset=el('button','تعیین رمز موقت');reset.type='button';reset.className='button';reset.onclick=()=>mutate({action:'reset',id:u.id,password:pass.value});
   card.append(role,label,apply);if(u.role==='editor')card.append(permissionEditor(u,permissionOptions));card.append(pass,reset);root.append(card);
  }
 }
 async function mutate(data){
  message.textContent='';const password=$('admin-confirm').value;if(!password){message.textContent='برای تنظیم حساب‌های طراحی، رمز فعلی خودت را وارد کن.';return}
  dialog.querySelectorAll('button').forEach(b=>b.disabled=true);
  try{await api('users',{...data,adminPassword:password});message.textContent=data.action==='add'?'حساب طراحی ساخته شد؛ اکنون بخش‌های مجاز آن را مشخص کنید.':'ذخیره شد.';$('new-user').reset();await loadUsers()}catch(e){message.textContent=e.message}finally{dialog.querySelectorAll('button').forEach(b=>b.disabled=false)}
 }
 $('new-user').onsubmit=e=>{e.preventDefault();mutate({action:'add',...Object.fromEntries(new FormData(e.target))})};
 $('change-form').onsubmit=async e=>{e.preventDefault();message.textContent='';try{await api('password',Object.fromEntries(new FormData(e.target)));e.target.reset();message.textContent='رمز حساب طراحی تغییر کرد.'}catch(e){message.textContent=e.message}};
})();
