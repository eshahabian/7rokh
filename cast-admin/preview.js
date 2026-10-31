(() => {
  let mode='pages',images=[],designSelected=null;
  function notify(element){if(!element)return;designSelected=element;document.querySelectorAll('.casting-design-selected').forEach(e=>e.classList.remove('casting-design-selected'));element.classList.add('casting-design-selected');parent.postMessage({type:'casting-select-design',id:element.dataset.cmsDesign},location.origin)}
  function imageFor(target){for(let e=target;e&&e!==document.body;e=e.parentElement){const urls=e.tagName==='IMG'?[e.currentSrc||e.src]:[getComputedStyle(e).backgroundImage];for(const image of images)if(urls.some(raw=>raw&&(raw.includes(image.id)||raw.includes(encodeURIComponent(image.id)))))return image}return null}
  const designStyle=document.createElement('style');designStyle.textContent='.casting-design-selected{outline:3px solid #b6934b!important;outline-offset:3px!important}body.casting-design-mode [data-cms-selectable]:hover{outline:1px dashed #b6934b;cursor:crosshair}';document.head.append(designStyle);
  const draftStyle=document.createElement('style');draftStyle.id='casting-design-draft';document.head.append(draftStyle);
  window.addEventListener('message',event=>{
    if(event.origin!==location.origin||event.source!==parent)return;const message=event.data||{};
    if(message.type==='casting-preview-mode'){mode=message.mode;images=message.images||[];document.body.classList.toggle('casting-design-mode',mode==='design')}
    if(message.type==='casting-design-parent')notify(designSelected?.parentElement?.closest('[data-cms-selectable]'));
    if(message.type==='casting-design-focus'){const target=document.querySelector('[data-cms-design="'+message.id+'"]');if(target){designSelected=target;document.querySelectorAll('.casting-design-selected').forEach(e=>e.classList.remove('casting-design-selected'));target.classList.add('casting-design-selected');target.scrollIntoView({block:'center',behavior:'smooth'})}}
    if(message.type==='casting-design-draft'){
      let css='';if(/^d-[a-f0-9]{24}$/.test(message.id)){for(const [property,raw] of Object.entries(message.properties||{})){let value=raw;if(property==='background-image'&&raw!=='none'){const image=images.find(i=>i.id===raw);if(!image)continue;value='url("'+image.url+'")'}if(!/^[a-z-]+$/.test(property)||/[{};<>]/.test(value))continue;css+=property+':'+value+'!important;'}css='[data-cms-design="'+message.id+'"]{'+css+'}';if(message.viewport!=='desktop')css='@media(max-width:'+(message.viewport==='mobile'?'600':'1024')+'px){'+css+'}'}
      // Draft CSS is text, never HTML; the server validates it again on save.
      draftStyle.textContent=css+'\n'+(message.siteCss||'')+'\n'+(message.css||'');
    }
  });
  const style=document.createElement('style');style.textContent='[data-editor-fields]{cursor:text!important}.fara-editor-hover{outline:2px dashed #b59447!important;outline-offset:4px!important}.fara-editor-selected{outline:2px solid #b59447!important;outline-offset:4px!important;background-color:#b5944710!important}';document.head.append(style);
  let hovered,selected;
  document.addEventListener('mouseover',event=>{const field=event.target.closest('[data-editor-fields]');if(hovered&&hovered!==field)hovered.classList.remove('fara-editor-hover');hovered=field;if(field)field.classList.add('fara-editor-hover')});
  document.addEventListener('mouseout',event=>{if(!event.relatedTarget&&hovered){hovered.classList.remove('fara-editor-hover');hovered=null}});
  document.addEventListener('click',event=>{if(mode==='design'){event.preventDefault();event.stopImmediatePropagation();notify(event.target.closest('[data-cms-selectable]'));return}const image=imageFor(event.target);if(image){event.preventDefault();event.stopImmediatePropagation();parent.postMessage({type:'casting-select-image',id:image.id},location.origin);return}const field=event.target.closest('[data-editor-fields]');if(field){event.preventDefault();event.stopImmediatePropagation();const ids=JSON.parse(field.dataset.editorFields);if(selected)selected.classList.remove('fara-editor-selected');selected=field;field.classList.add('fara-editor-selected');parent.postMessage({type:'fara-select-field',id:ids[0]},location.origin)}else if(event.target.closest('a'))event.preventDefault()},true);
  window.addEventListener('message',event=>{if(event.origin!==location.origin||event.source!==parent||event.data?.type!=='fara-focus-field')return;const field=[...document.querySelectorAll('[data-editor-fields]')].find(e=>JSON.parse(e.dataset.editorFields).includes(event.data.id));if(!field)return;if(selected)selected.classList.remove('fara-editor-selected');selected=field;field.classList.add('fara-editor-selected');field.scrollIntoView({behavior:'smooth',block:'center'})});
})();
