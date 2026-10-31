window.faraImages=async function({api,pages,onSave,toast,lock}){
 const root=document.getElementById('image-items'),filter=document.getElementById('image-page');
 const {images}=await api('/api/images');
 const selected=filter.value;filter.replaceChildren(new Option('تمام صفحات','all'),...pages.map(p=>new Option(p.title,p.id)));filter.value=selected||'all';
 function el(tag,text){const e=document.createElement(tag);if(text)e.textContent=text;return e}
 function render(){root.replaceChildren();for(const item of images.filter(i=>filter.value==='all'||!i.pages.length||i.pages.includes(filter.value))){
  const card=el('article');card.className='image-card';const current=el('img');current.src=item.url+'&v='+item.revision;current.alt='عکس فعلی '+item.label;
  const title=el('h3',item.label),usage=el('p',(item.usage||[]).join('، ')||'تصویر قالب سایت');
  const dimensions=el('small');current.onload=()=>dimensions.textContent=`${current.naturalWidth} × ${current.naturalHeight} پیکسل`;
  const input=el('input');input.type='file';input.accept='image/png,image/jpeg,image/webp';input.hidden=true;
  const choose=el('button','انتخاب عکس جدید از لپ‌تاپ');choose.className='button';choose.onclick=()=>input.click();
  const candidate=el('img');candidate.hidden=true;candidate.alt='پیش‌نمایش عکس جدید';const save=el('button','ذخیرهٔ عکس جدید');save.className='button primary';save.hidden=true;
  const cancel=el('button','انصراف');cancel.className='button subtle';cancel.hidden=true;let base64=null;
  function clear(){base64=null;candidate.removeAttribute('src');candidate.hidden=true;save.hidden=true;cancel.hidden=true;input.value=''}
  cancel.onclick=clear;
  input.onchange=async()=>{const file=input.files[0];if(!file)return;try{
   if(file.size>20*1024*1024)throw Error('عکس ورودی باید کمتر از ۲۰ مگابایت باشد.');
   const bitmap=await createImageBitmap(file);const canvas=document.createElement('canvas');const scale=Math.min(1,3840/Math.max(bitmap.width,bitmap.height));canvas.width=Math.max(1,Math.round(bitmap.width*scale));canvas.height=Math.max(1,Math.round(bitmap.height*scale));const ctx=canvas.getContext('2d');const mime=/\.png$/i.test(item.id)?'image/png':/\.webp$/i.test(item.id)?'image/webp':'image/jpeg';if(mime==='image/jpeg'){ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height)}ctx.drawImage(bitmap,0,0,canvas.width,canvas.height);bitmap.close();const url=canvas.toDataURL(mime,0.92);if(!url.startsWith('data:'+mime+';'))throw Error('مرورگر این قالب عکس را پشتیبانی نمی‌کند.');base64=url.split(',')[1];candidate.src=url;candidate.hidden=false;save.hidden=false;cancel.hidden=false;
  }catch(e){clear();toast(e.message||'عکس قابل خواندن نیست.',true)}};
  save.onclick=async()=>{if(!base64)return;lock(true);root.querySelectorAll('button').forEach(b=>b.disabled=true);try{const result=await api('/api/image',{id:item.id,revision:item.revision,base64});onSave();toast(result.message);await window.faraImages({api,pages,onSave,toast,lock})}catch(e){toast(e.message,true)}finally{lock(false);root.querySelectorAll('button').forEach(b=>b.disabled=false)}};
  card.append(current,title,usage,dimensions,choose,input,candidate,save,cancel);root.append(card);
 }}
 filter.onchange=render;render();
};
