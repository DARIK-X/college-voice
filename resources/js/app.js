const token = document.querySelector('meta[name="csrf-token"]')?.content;
const colors=['#007f85','#c59a47','#3c9694','#315b71','#91b4a1','#c48366','#8d85a8'];
const labels={draft:'Скоро начнём',open:'Голосование открыто',closed:'Голосование завершено'};
const dialog=document.querySelector('#vote-dialog');
const grid=document.querySelector('.candidate-grid');
let selected=null;
document.querySelectorAll('.vote-button').forEach(button=>button.addEventListener('click',()=>{
 selected=button.dataset.id;document.querySelector('#chosen-name').textContent=button.dataset.name;
 document.querySelector('#vote-feedback').textContent='';dialog.showModal();
}));
document.querySelector('[data-close-dialog]')?.addEventListener('click',()=>dialog.close());
document.querySelector('#vote-confirm')?.addEventListener('submit',async e=>{
 e.preventDefault();const button=e.submitter;button.disabled=true;
 try{
  const response=await fetch('/vote',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({candidate_id:selected,voter_name:document.querySelector('#voter-name').value.trim(),voter_group:document.querySelector('#voter-group').value.trim()})});
  const data=await response.json().catch(()=>({message:'Обновите страницу и повторите попытку.'}));
  if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||data.message||'Не удалось отправить голос.');
  grid.dataset.voted='1';document.querySelectorAll('.vote-button').forEach(b=>{b.disabled=true;b.textContent='Голос принят';});
  const feedback=document.querySelector('#home-feedback');feedback.textContent=data.message;feedback.classList.remove('hidden');dialog.close();
 }catch(error){document.querySelector('#vote-feedback').textContent=error.message;}finally{button.disabled=false;}
});
let rows=new Map();
function makeRow(c,index){
 const row=document.createElement('article');row.className='rank-row';
 const img=document.createElement('img');img.src=c.photo||'/assets/logo.png';img.alt='';
 const info=document.createElement('div');const title=document.createElement('h3');const group=document.createElement('small');
 const bar=document.createElement('div');bar.className='bar';const fill=document.createElement('div');bar.append(fill);info.append(title,group,bar);
 const count=document.createElement('div');count.className='rank-number';const pct=document.createElement('span');count.append(pct);row.append(img,info,count);
 rows.set(c.id,{row,img,title,group,fill,pct});return rows.get(c.id);
}
function render(data){
 const container=document.querySelector('#ranking');if(!container)return;
 const shareSum=data.candidates.reduce((sum,c)=>sum+Number(c.percent),0);
 document.querySelector('#chart-share').textContent=shareSum>0?'100%':'0%';
 document.querySelector('#result-status').textContent=labels[data.status]||'';
 let offset=0;const segments=[];const ids=new Set();
 data.candidates.forEach((c,index)=>{
  ids.add(c.id);const empty=container.querySelector('[data-empty]');if(empty)empty.remove();const r=rows.get(c.id)||makeRow(c,index);const color=colors[index%colors.length];
  r.title.textContent=c.name;r.group.textContent=c.group+(c.active?'':' · Снят с выборов');r.pct.textContent=c.percent+'%';r.fill.style.width=c.percent+'%';r.fill.style.backgroundColor=color;
  r.img.src=c.photo||'/assets/logo.png';container.append(r.row);
  const end=offset+(shareSum>0?Number(c.percent)/shareSum*100:0);segments.push(`${color} ${offset}% ${end}%`);offset=end;
 });
 rows.forEach((r,id)=>{if(!ids.has(id)){r.row.remove();rows.delete(id);}});
 if(!data.candidates.length&&!container.querySelector('[data-empty]')){const empty=document.createElement('p');empty.dataset.empty='1';empty.textContent='Кандидатов пока нет.';container.append(empty);}
 document.querySelector('#donut').style.background=shareSum>0?`conic-gradient(${segments.join(',')})`:'#dfebe4';
}
let failures=0;
async function poll(){
 if(document.hidden){setTimeout(poll,5000);return;}
 try{
  const response=await fetch('/results/data',{headers:{Accept:'application/json'},signal:AbortSignal.timeout(10000)});
  if(!response.ok)throw new Error('offline');const data=await response.json();failures=0;render(data);
  const connection=document.querySelector('#connection');if(connection)connection.textContent='● Обновлено в '+new Date().toLocaleTimeString('ru');
  const status=document.querySelector('#election-status');if(status)status.textContent=labels[data.status]||'';
  if(grid){grid.dataset.open=data.status==='open'?'1':'0';document.querySelectorAll('.vote-button').forEach(b=>{
   const voted=grid.dataset.voted==='1';b.disabled=voted||data.status!=='open';b.textContent=voted?'Голос принят':data.status==='open'?'Отдать голос ↗':'Голосование закрыто';
  });}
 }catch(error){failures++;const connection=document.querySelector('#connection');if(connection)connection.textContent='Нет связи. Показываем последние полученные данные.';}
 setTimeout(poll,Math.min(30000,5000*Math.max(1,failures))+Math.random()*1000);
}
if(document.querySelector('#results')||grid)poll();
