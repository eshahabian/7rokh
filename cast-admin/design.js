(() => {
  const properties=[
    ['font-family','خانوادهٔ فونت','Tahoma, Arial, sans-serif'],['letter-spacing','فاصلهٔ حروف','0px'],['order','ترتیب در چیدمان','0'],['flex-wrap','شکستن ردیف','wrap',['nowrap','wrap','wrap-reverse']],['position','نوع موقعیت','static',['static','relative','absolute','sticky','fixed']],['top','فاصله از بالا','0px'],['right','فاصله از راست','0px'],['bottom','فاصله از پایین','0px'],['left','فاصله از چپ','0px'],['z-index','لایهٔ نمایش','1'],['overflow','سرریز','visible',['visible','hidden','auto','scroll']],['aspect-ratio','نسبت تصویر','16 / 9'],['transform','تبدیل اندازه و موقعیت','scale(1)'],['color','رنگ متن','#203746'],['background-color','رنگ پس‌زمینه','#ffffff'],['font-size','اندازهٔ متن','18px'],['font-weight','ضخامت متن','700'],['line-height','ارتفاع خط','1.8'],['text-align','تراز متن','start', ['start','center','end','justify']],
    ['padding','فاصلهٔ داخلی','16px 24px'],['margin','فاصلهٔ بیرونی','0 auto'],['gap','فاصلهٔ اجزا','16px'],['width','عرض','100%'],['max-width','حداکثر عرض','1200px'],['height','ارتفاع','auto'],['min-height','حداقل ارتفاع','200px'],
    ['border-radius','گردی گوشه‌ها','12px'],['border-width','ضخامت حاشیه','1px'],['border-color','رنگ حاشیه','#dddddd'],['border-style','نوع حاشیه','solid',['none','solid','dashed','dotted']],
    ['display','چیدمان','block',['block','flex','grid','inline-block','none']],['flex-direction','جهت چیدمان','row',['row','column','row-reverse','column-reverse']],['justify-content','تراز افقی','center',['start','center','end','space-between','space-around']],['align-items','تراز عمودی','center',['start','center','end','stretch']],['grid-template-columns','ستون‌ها','repeat(3, 1fr)'],
    ['object-fit','برش عکس','cover',['cover','contain','fill','scale-down']],['opacity','شفافیت','1'],['box-shadow','سایه','0 6px 20px #00000020'],['background-size','اندازهٔ پس‌زمینه','cover',['cover','contain','auto']],['background-position','موقعیت پس‌زمینه','center']
  ];
  let ctx,data,selected,viewport='desktop',draft={},cssDraft='',siteDraft='',pending=false;
  const $=id=>document.getElementById(id),el=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;return e};
  function changed(){ctx.onDirty(pending||cssDraft!==data.css||siteDraft!==data.siteCss)}
  function preview(){ctx.preview({type:'casting-design-draft',id:selected?.id,properties:draft,viewport,css:cssDraft,siteCss:siteDraft})}
  function pick(id){if(!data)return;if(pending&&!confirm('تغییر ظاهر این بخش ذخیره نشده است. بخش دیگری انتخاب شود؟'))return;selected=data.elements.find(e=>e.id===id)||null;pending=false;render();preview();changed();ctx.preview({type:'casting-design-focus',id:selected?.id})}
  function render(){
    const selector=$('design-element');selector.replaceChildren(new Option('یک بخش را در پیش‌نمایش انتخاب کنید',''),...data.elements.map(e=>new Option(e.label+(e.scope==='page'?'':' · مشترک'),e.id)));selector.value=selected?.id||'';selector.onchange=()=>{const id=selector.value;selector.value=selected?.id||'';pick(id)};
    $('design-scope').textContent=selected?(selected.scope==='page'?'تغییر ظاهر فقط در همین صفحه اعمال می‌شود.':'این بخش مشترک است؛ تغییرش روی تمام صفحات اعمال می‌شود.'):'برای انتخاب دقیق، روی بخش کلیک کنید؛ با «بخش والد» می‌توانید کادر بزرگ‌تر را انتخاب کنید.';
    const root=$('design-properties');root.replaceChildren();if(!selected)return;
    draft={...(selected.styles[viewport]||{})};
    for(const [name,title,hint,choices] of properties){const label=el('label',title),input=el(choices?'select':'input');if(choices){input.append(new Option('پیش‌فرض قالب',''),...choices.map(v=>new Option(v,v)))}else{input.type='text';input.placeholder=hint;input.maxLength=240}input.value=draft[name]||'';input.dir='ltr';input.dataset.property=name;input.oninput=()=>{if(input.value.trim())draft[name]=input.value.trim();else delete draft[name];pending=true;preview();changed()};label.append(input);root.append(label)}
    const label=el('label','عکس پس‌زمینه'),images=el('select');images.append(new Option('پیش‌فرض قالب',''),new Option('بدون تصویر','none'),...ctx.images.map(i=>new Option(i.label,i.id)));images.value=draft['background-image']||'';images.onchange=()=>{if(images.value)draft['background-image']=images.value;else delete draft['background-image'];pending=true;preview();changed()};label.append(images);root.append(label);
  }
  window.castingDesign={
    async open(context){ctx=context;data=await ctx.api('/api/design?page='+encodeURIComponent(ctx.page));selected=null;pending=false;cssDraft=data.css;siteDraft=data.siteCss;
      $('design-css-section').hidden=!data.canCss;$('design-css').value=cssDraft;$('design-site-css').value=siteDraft;
      $('design-css').oninput=()=>{cssDraft=$('design-css').value;preview();changed()};$('design-site-css').oninput=()=>{siteDraft=$('design-site-css').value;preview();changed()};
      $('design-viewport').value=viewport;$('design-viewport').onchange=()=>{if(pending&&!confirm('تغییرات این اندازه ذخیره نشده است. ادامه می‌دهی؟')){$('design-viewport').value=viewport;return}viewport=$('design-viewport').value;pending=false;render();ctx.viewport(viewport);changed()};
      $('design-parent').onclick=()=>ctx.preview({type:'casting-design-parent'});
      $('design-reset').onclick=()=>{if(!selected)return;draft={};pending=true;rootClear();preview();changed()};
      function rootClear(){$('design-properties').querySelectorAll('input,select').forEach(i=>i.value='')}
      render();ctx.viewport(viewport);changed();
    },
    select:pick,
    async save(){
      const body={page:ctx.page,template:data.template};
      if(pending&&selected)Object.assign(body,{element:selected.id,viewport,properties:draft});
      if(cssDraft!==data.css)body.css=cssDraft;
      if(siteDraft!==data.siteCss)body.siteCss=siteDraft;
      await ctx.api('/api/design',body);
      if(pending&&selected)selected.styles[viewport]={...draft};pending=false;data.css=cssDraft;data.siteCss=siteDraft;
      changed();
    }
  };
})();
