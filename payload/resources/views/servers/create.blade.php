@extends('layouts.app')
@section('title', 'Create server')
@section('crumb', 'Servers / Create')
@section('content')
<div class="page-heading"><div><span class="eyebrow">NEW DEPLOYMENT</span><h1>Create a server</h1><p>Choose software and a free node allocation from the connected Pterodactyl panel.</p></div><div class="heading-actions"><a class="btn btn-secondary" href="{{ route('servers.index') }}">← Back to servers</a></div></div>
<div class="detail-grid">
<section class="panel">
  <div class="panel-head"><div><h2>Server configuration</h2><p>Options are loaded live from Pterodactyl</p></div><span class="badge badge-blue">PTERODACTYL</span></div>
  <div class="panel-body">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px">
      <div id="pteroCreateStatus" class="field-help" role="status" aria-live="polite">Loading locations, nodes and available software…</div>
      <button class="btn btn-secondary" id="pteroReloadOptions" type="button">Reload panel options ⟳</button>
    </div>
    <form method="POST" action="{{ route('servers.store') }}" id="pteroCreateForm">
      @csrf
      <div class="field"><label for="name">Server name</label><input class="input" id="name" name="name" value="{{ old('name') }}" placeholder="My Minecraft Server" minlength="3" maxlength="40" required><div class="field-help">Letters, numbers, spaces, dashes, and underscores.</div></div>
      <div class="field-row">
        <div class="field"><label for="location_id">Location</label><select class="input" id="location_id" name="location_id" required><option value="">Loading locations…</option></select></div>
        <div class="field"><label for="node_id">Node</label><select class="input" id="node_id" name="node_id" required><option value="">Select a location first</option></select></div>
      </div>
      <div class="field-row">
        <div class="field"><label for="nest_id">Software category</label><select class="input" id="nest_id" name="nest_id" required><option value="">Loading software…</option></select><div class="field-help">For example, Minecraft, Discord bots, or other installed Eggs.</div></div>
        <div class="field"><label for="egg_id">Software / Egg</label><select class="input" id="egg_id" name="egg_id" required><option value="">Select a software category first</option></select></div>
      </div>
      <div class="field"><label>Allocation</label><div class="field-help" id="allocationPreview" role="status" aria-live="polite">A free allocation will be selected automatically when you deploy.</div></div>
      <div class="settings-card" style="margin:5px 0 18px;padding:16px">
        <h3 style="margin:0 0 6px">Software configuration</h3>
        <p class="settings-desc">The selected Egg determines the Docker image, startup command, and environment variables.</p>
        <div id="pteroEnvironmentFields" class="field-help">Choose a software Egg to load its settings.</div>
      </div>
      <div class="field-row">
        <div class="field"><label>Memory</label><input class="input" value="{{ (int)\App\Support\Settings::get('ptero_memory',2048) }} MB" disabled></div>
        <div class="field"><label>Disk</label><input class="input" value="{{ (int)\App\Support\Settings::get('ptero_disk',5120) }} MB" disabled></div>
      </div>
      <div class="field-row">
        <div class="field"><label>CPU limit</label><input class="input" value="{{ (int)\App\Support\Settings::get('ptero_cpu',100) }}%" disabled></div>
        <div class="field"><label>Creation cost</label><input class="input" value="{{ number_format((float)\App\Support\Settings::get('server_create_cost',0),2) }} credits" disabled></div>
      </div>
      <div class="form-actions"><button class="btn btn-primary" id="pteroDeployButton" type="submit">Deploy server →</button><a class="btn btn-secondary" href="{{ route('servers.index') }}">Cancel</a></div>
    </form>
  </div>
</section>
<aside class="panel">
  <div class="panel-head"><div><h2>Before you deploy</h2><p>Important server lifecycle details</p></div></div>
  <div class="panel-body">
    <div class="data-list">
      <div class="data-row"><span>Available slots</span><strong>{{ max(0, auth()->user()->server_slots - auth()->user()->servers()->whereIn('status',['active','suspended'])->count()) }}</strong></div>
      <div class="data-row"><span>Current credits</span><strong>{{ number_format((float)auth()->user()->credits,2) }}</strong></div>
      <div class="data-row"><span>Initial lifetime</span><strong>{{ (int)\App\Support\Settings::get('server_lifetime_hours',48) }} hours</strong></div>
      <div class="data-row"><span>Renewal cost</span><strong>{{ number_format((float)\App\Support\Settings::get('server_renew_cost',0),2) }} credits</strong></div>
    </div>
    <div class="alert alert-success" style="margin:19px 0 0"><span>i</span><div>When the lifetime ends, the server is suspended. It can be renewed during the grace period. After that, it is permanently deleted from Pterodactyl.</div></div>
  </div>
</aside>
</div>
<script>
(() => {
 const $ = id => document.getElementById(id);
 const selects={location:$('location_id'),node:$('node_id'),nest:$('nest_id'),egg:$('egg_id')};
 const allocationPreview=$('allocationPreview');
 const status=$('pteroCreateStatus');
 const envContainer=$('pteroEnvironmentFields');
 const saved={
  location:@json((string) old('location_id',\App\Support\Settings::get('ptero_location_id',''))),
  node:@json((string) old('node_id',\App\Support\Settings::get('ptero_node_id',''))),
  nest:@json((string) old('nest_id',\App\Support\Settings::get('ptero_nest_id',''))),
  egg:@json((string) old('egg_id',\App\Support\Settings::get('ptero_egg_id',''))),
 };
 let eggs=[];
 let loading=false;
 const say=(message,error=false)=>{status.textContent=message;status.style.color=error?'#ff7b89':'#aeb4c6';};
 async function fetchData(resource,params={}) {
  const qs=new URLSearchParams(params);
  const response=await fetch('/pterodactyl/discovery/'+resource+(qs.size?'?'+qs:''),{headers:{'Accept':'application/json'},credentials:'same-origin'});
  const json=await response.json().catch(()=>({}));
  if(!response.ok||!json.ok)throw new Error(json.message||'Could not load '+resource+'.');
  return json.data||[];
 }
 function fill(select,items,selected='',label=item=>item.name,disabled=item=>false,placeholder='Choose an option') {
  select.replaceChildren();
  const first=document.createElement('option');first.value='';first.textContent=placeholder;select.appendChild(first);
  items.forEach(item=>{
   const o=document.createElement('option');o.value=String(item.id);o.textContent=label(item);o.disabled=typeof disabled==='function'?Boolean(disabled(item)):Boolean(disabled);
   if(String(item.id)===String(selected))o.selected=true;
   select.appendChild(o);
  });
 }
 async function loadNodes(selected=saved.node) {
  fill(selects.node,[],'',x=>x.name,()=>false,'Loading nodes…');
  if(!selects.location.value){fill(selects.node,[],'',x=>x.name,()=>false,'Select a location first');return;}
  const nodes=await fetchData('nodes',{location_id:selects.location.value});
  fill(selects.node,nodes,selected,x=>x.name+(x.maintenance_mode?' (maintenance)':''),x=>x.maintenance_mode,'Choose a node');
  if(!selects.node.value){const healthy=nodes.filter(x=>!x.maintenance_mode);if(healthy.length===1)selects.node.value=String(healthy[0].id);}
  await loadAllocationPreview();
 }
 async function loadAllocationPreview() {
  const nodeId=selects.node.value;
  if(!nodeId) {
   allocationPreview.textContent='Choose a node; a free allocation will be selected automatically.';
   return;
  }
  allocationPreview.textContent='Checking free allocations on the selected node…';
  const items=await fetchData('allocations',{node_id:nodeId});
  const free=items.filter(x=>!x.assigned).sort((a,b)=>(Number(a.id)||0)-(Number(b.id)||0));
  if(!free.length) {
   allocationPreview.textContent='No free allocation is currently available on this node. Choose another node or ask the administrator to add free allocations.';
   return;
  }
  const next=free[0];
  allocationPreview.textContent='Free allocation detected: '+(next.ip_alias||next.ip)+':'+next.port+'. The server will automatically re-check and reserve a free allocation when deployed.';
 }
 async function loadEggs(selected=saved.egg) {
  fill(selects.egg,[],'',x=>x.name,()=>false,'Loading Eggs…');
  eggs=[];
  if(!selects.nest.value){fill(selects.egg,[],'',x=>x.name,()=>false,'Select a software category first');envContainer.textContent='Choose a software Egg to load its settings.';return;}
  eggs=await fetchData('eggs',{nest_id:selects.nest.value});
  fill(selects.egg,eggs,selected,x=>x.name,()=>false,'Choose software / Egg');
  if(!selects.egg.value&&eggs.length===1)selects.egg.value=String(eggs[0].id);
  renderEggEnvironment();
 }
 function renderEggEnvironment() {
  const egg=eggs.find(x=>String(x.id)===String(selects.egg.value));
  envContainer.replaceChildren();
  if(!egg){envContainer.textContent='Choose a software Egg to load its environment variables.';return;}
  const variables=egg.variables||[];
  const images=egg.docker_images||{};
  const envSummary=document.createElement('p');
  envSummary.className='field-help';
  envSummary.textContent='Docker image: '+(Object.values(images)[0]||'Egg default')+' · Startup command is managed by the selected Egg.';
  envContainer.appendChild(envSummary);
  let shown=0;
  variables.forEach(v=>{
   if(!v.env_variable||!/^[A-Za-z_][A-Za-z0-9_]*$/.test(v.env_variable))return;
   const row=document.createElement('div');row.className='field';
   const label=document.createElement('label');label.textContent=v.name||v.env_variable;label.htmlFor='env_'+v.env_variable;
   if(v.user_editable)label.textContent+=' (required/configurable)';
   row.appendChild(label);
   if(v.description){const help=document.createElement('div');help.className='field-help';help.textContent=v.description;row.appendChild(help);}
   const input=document.createElement('input');
   input.className='input';input.id='env_'+v.env_variable;input.name='environment['+v.env_variable+']';
   input.value=v.default_value??'';input.maxLength=2000;input.autocomplete='off';
   const required=String(v.rules||'').split('|').includes('required');
   if(v.user_editable){input.type='text';if(required)input.required=true;}
   else if(v.user_viewable){input.type='text';input.readOnly=true;}
   else {input.type='hidden';}
   row.appendChild(input);envContainer.appendChild(row);shown++;
  });
  if(!shown){const help=document.createElement('p');help.className='field-help';help.textContent='This Egg does not expose user-editable environment variables.';envContainer.appendChild(help);}
 }
 async function loadPanel() {
  if(loading)return;
  loading=true;$('pteroReloadOptions').disabled=true;$('pteroDeployButton').disabled=true;
  say('Connecting to Pterodactyl and reading locations/software…');
  try{
   const [locations,nests]=await Promise.all([fetchData('locations'),fetchData('nests')]);
   fill(selects.location,locations,saved.location,x=>x.name,()=>false,'Choose a location');
   fill(selects.nest,nests,saved.nest,x=>x.name,()=>false,'Choose software category');
   await Promise.all([loadNodes(saved.node),loadEggs(saved.egg)]);
   say('Loaded '+locations.length+' locations, '+nests.length+' software categories, '+eggs.length+' Eggs. A free allocation is selected automatically at deployment.');
  }catch(e){say(e.message||'Could not load Pterodactyl options.',true);}
  finally{loading=false;$('pteroReloadOptions').disabled=false;$('pteroDeployButton').disabled=false;}
 }
 selects.location.addEventListener('change',()=>{saved.node='';loadNodes('');});
 selects.node.addEventListener('change',()=>{loadAllocationPreview().catch(e=>say(e.message||'Could not check free allocations.',true));});
 selects.nest.addEventListener('change',()=>{saved.egg='';loadEggs('');});
 selects.egg.addEventListener('change',renderEggEnvironment);
 $('pteroReloadOptions').addEventListener('click',()=>{saved.location=selects.location.value;saved.node=selects.node.value;saved.nest=selects.nest.value;saved.egg=selects.egg.value;loadPanel();});
 $('pteroCreateForm').addEventListener('submit',event=>{
  if(!selects.location.value||!selects.node.value||!selects.nest.value||!selects.egg.value){
   event.preventDefault();say('Select a location, node, and software Egg before deploying. A free allocation is automatic.',true);
  }
 });
 loadPanel();
})();
</script>
@endsection
