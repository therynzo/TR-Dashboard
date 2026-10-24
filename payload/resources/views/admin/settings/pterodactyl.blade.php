@extends('layouts.app')
@section('title','Pterodactyl API')
@section('crumb','Admin / Pterodactyl API')
@section('content')
<div class="page-heading"><div><div class="eyebrow">INTEGRATION</div><h1>Pterodactyl Application API</h1><p>Connect first, test the API, then load and save your panel's locations, nodes, software and allocations.</p></div></div>

<section class="settings-card">
  <h3>1. Panel connection</h3>
  <p class="settings-desc">Enter the panel's root URL, not the Client API URL. Use an Application API key (normally starts with <code>ptla_</code>).</p>
  <form method="POST" action="{{ route('admin.settings.pterodactyl.save') }}">
    @csrf
    <div class="field"><label for="ptero_url">Pterodactyl panel URL</label><input class="input" id="ptero_url" name="ptero_url" type="url" value="{{ old('ptero_url',$settings['ptero_url']) }}" placeholder="https://gp.aliennode.in" required></div>
    <div class="field"><label for="ptero_api_key">Application API key</label><input class="input mono" id="ptero_api_key" name="ptero_api_key" type="password" autocomplete="new-password" value="" placeholder="{{ $settings['ptero_api_key_configured'] ? 'Key saved — leave blank to keep it' : 'ptla_…' }}"><div class="field-help">@if($settings['ptero_api_key_configured'])<span style="color:#55df9b">● API key stored encrypted.</span>@else No API key saved yet.@endif The key is never sent to the browser. Leave blank to retain the saved key.</div></div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save connection <span>✓</span></button></div>
  </form>
  <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:15px">
    <div class="data-row" style="flex:1;margin:0"><span>Saved panel</span><strong>{{ $settings['ptero_url'] ? parse_url($settings['ptero_url'], PHP_URL_HOST) : 'Not configured' }}</strong></div>
    <form method="POST" action="{{ route('admin.settings.pterodactyl.test') }}">@csrf<button class="btn btn-secondary" type="submit">Test API connection ↗</button></form>
  </div>
</section>

<section class="settings-card" style="margin-top:16px">
  <div style="display:flex;gap:16px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap"><div><h3>2. Discover panel options</h3><p class="settings-desc">After saving a working connection, load live values from Pterodactyl. The API key stays on the server.</p></div><button class="btn btn-secondary" id="pteroLoadData" type="button">Load panel data ⟳</button></div>
  <div id="pteroDiscoveryStatus" class="field-help" role="status" aria-live="polite">Save your connection, test it, then load panel data.</div>
  <form method="POST" action="{{ route('admin.settings.pterodactyl.defaults') }}" id="pteroDefaultsForm">
    @csrf
    <div class="field-row">
      <div class="field"><label for="ptero_location_id">Location</label><select class="input" id="ptero_location_id" name="ptero_location_id" required><option value="">Load locations first</option></select></div>
      <div class="field"><label for="ptero_node_id">Node</label><select class="input" id="ptero_node_id" name="ptero_node_id" required><option value="">Select a location first</option></select></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="ptero_nest_id">Software category / Nest</label><select class="input" id="ptero_nest_id" name="ptero_nest_id" required><option value="">Load software categories first</option></select></div>
      <div class="field"><label for="ptero_egg_id">Software / Egg</label><select class="input" id="ptero_egg_id" name="ptero_egg_id" required><option value="">Select a software category first</option></select></div>
    </div>
    <div class="field"><label for="ptero_allocation_id">Available allocation / port</label><select class="input" id="ptero_allocation_id" name="ptero_allocation_id" required><option value="">Select a node first</option></select><div class="field-help">Only unassigned allocations are listed. Pterodactyl re-checks availability during deployment.</div></div>

    <div class="field-row">
      <div class="field"><label for="ptero_docker_image">Docker image (from Egg)</label><input class="input" id="ptero_docker_image" name="ptero_docker_image" value="{{ old('ptero_docker_image',$settings['ptero_docker_image']) }}" required></div>
      <div class="field"><label for="ptero_startup">Startup command (from Egg)</label><input class="input" id="ptero_startup" name="ptero_startup" value="{{ old('ptero_startup',$settings['ptero_startup']) }}" required></div>
    </div>
    <div class="field"><label for="ptero_environment">Default Egg environment (JSON object)</label><textarea class="textarea mono" id="ptero_environment" name="ptero_environment" rows="5" required>{{ old('ptero_environment',$settings['ptero_environment']) }}</textarea><div class="field-help">Auto-filled with the selected Egg's environment variable defaults. Edit only when you know the Egg's required values.</div></div>
    <div class="field-row three">
      <div class="field"><label for="ptero_memory">Memory (MB)</label><input class="input" id="ptero_memory" name="ptero_memory" type="number" min="128" value="{{ old('ptero_memory',$settings['ptero_memory']) }}" required></div>
      <div class="field"><label for="ptero_disk">Disk (MB)</label><input class="input" id="ptero_disk" name="ptero_disk" type="number" min="256" value="{{ old('ptero_disk',$settings['ptero_disk']) }}" required></div>
      <div class="field"><label for="ptero_cpu">CPU (%)</label><input class="input" id="ptero_cpu" name="ptero_cpu" type="number" min="1" max="10000" value="{{ old('ptero_cpu',$settings['ptero_cpu']) }}" required></div>
    </div>
    <div class="field-row three">
      <div class="field"><label for="ptero_swap">Swap (MB)</label><input class="input" id="ptero_swap" name="ptero_swap" type="number" min="-1" value="{{ old('ptero_swap',$settings['ptero_swap']) }}" required></div>
      <div class="field"><label for="ptero_io">Block IO weight</label><input class="input" id="ptero_io" name="ptero_io" type="number" min="10" max="1000" value="{{ old('ptero_io',$settings['ptero_io']) }}" required></div>
      <div class="field"><label for="ptero_databases">Databases</label><input class="input" id="ptero_databases" name="ptero_databases" type="number" min="0" max="100" value="{{ old('ptero_databases',$settings['ptero_databases']) }}" required></div>
    </div>
    <div class="field-row">
      <div class="field"><label for="ptero_allocations">Allocation limit</label><input class="input" id="ptero_allocations" name="ptero_allocations" type="number" min="1" max="100" value="{{ old('ptero_allocations',$settings['ptero_allocations']) }}" required></div>
      <div class="field"><label for="ptero_backups">Backup limit</label><input class="input" id="ptero_backups" name="ptero_backups" type="number" min="0" max="100" value="{{ old('ptero_backups',$settings['ptero_backups']) }}" required></div>
    </div>
    <div class="form-actions"><button class="btn btn-primary" type="submit">Save selected node, software & deployment defaults ✓</button></div>
  </form>
</section>

<script>
(() => {
 const $ = id => document.getElementById(id);
 const selects = {
  location: $('ptero_location_id'), node: $('ptero_node_id'), nest: $('ptero_nest_id'),
  egg: $('ptero_egg_id'), allocation: $('ptero_allocation_id')
 };
 const status = $('pteroDiscoveryStatus');
 const saved = {
  location: @json((string) old('ptero_location_id', $settings['ptero_location_id'] ?? '')),
  node: @json((string) old('ptero_node_id', $settings['ptero_node_id'] ?? '')),
  nest: @json((string) old('ptero_nest_id', $settings['ptero_nest_id'] ?? '')),
  egg: @json((string) old('ptero_egg_id', $settings['ptero_egg_id'] ?? '')),
  allocation: @json((string) old('ptero_allocation_id', $settings['ptero_allocation_id'] ?? ''))
 };
 let currentEggs = [];
 const setStatus = (message, isError=false) => {
  status.textContent = message;
  status.style.color = isError ? '#ff7b89' : '#aeb4c6';
 };
 async function fetchData(resource, params={}) {
  const qs = new URLSearchParams(params);
  const response = await fetch('/pterodactyl/discovery/' + resource + (qs.size ? '?' + qs : ''), {
   headers: {'Accept':'application/json'}, credentials:'same-origin'
  });
  const json = await response.json().catch(() => ({}));
  if (!response.ok || !json.ok) throw new Error(json.message || 'Could not load ' + resource + '.');
  return json.data || [];
 }
 function fill(select, items, selected='', label=item=>item.name, disabled=item=>false, empty='No options available') {
  select.replaceChildren();
  const placeholder = document.createElement('option'); placeholder.value=''; placeholder.textContent=empty; select.appendChild(placeholder);
  items.forEach(item => {
   const option=document.createElement('option');
   option.value=String(item.id); option.textContent=label(item); option.disabled=disabled(item);
   if (String(item.id)===String(selected)) option.selected=true;
   select.appendChild(option);
  });
 }
 async function loadNodes(selected=saved.node) {
  const locationId=selects.location.value;
  fill(selects.node, [], '', x=>x.name, ()=>false, 'Loading nodes…');
  fill(selects.allocation, [], '', x=>x.name, ()=>false, 'Select a node first');
  if (!locationId) { fill(selects.node, [], '', x=>x.name, ()=>false, 'Select a location first'); return; }
  const nodes=await fetchData('nodes',{location_id:locationId});
  fill(selects.node,nodes,selected,x=>x.name+(x.maintenance_mode?' (maintenance)':''),x=>x.maintenance_mode,'Choose a node');
  if (!selects.node.value && nodes.length===1 && !nodes[0].maintenance_mode) selects.node.value=String(nodes[0].id);
  await loadAllocations(saved.allocation);
 }
 async function loadAllocations(selected=saved.allocation) {
  const nodeId=selects.node.value;
  if (!nodeId) { fill(selects.allocation, [], '', x=>x.name, ()=>false, 'Select a node first'); return; }
  const items=await fetchData('allocations',{node_id:nodeId});
  const free=items.filter(x=>!x.assigned);
  fill(selects.allocation,free,selected,x=>(x.ip_alias||x.ip)+':'+x.port+(x.notes?' — '+x.notes:''),()=>false,'Choose an unassigned allocation');
  if (!selects.allocation.value && free.length===1) selects.allocation.value=String(free[0].id);
 }
 async function loadEggs(selected=saved.egg) {
  const nestId=selects.nest.value;
  fill(selects.egg, [], '', x=>x.name, ()=>false, 'Loading Eggs…');
  currentEggs=[];
  if (!nestId) { fill(selects.egg, [], '', x=>x.name, ()=>false, 'Select software first'); return; }
  currentEggs=await fetchData('eggs',{nest_id:nestId});
  fill(selects.egg,currentEggs,selected,x=>x.name,'','Choose software / Egg');
  if (!selects.egg.value && currentEggs.length===1) selects.egg.value=String(currentEggs[0].id);
  applyEgg();
 }
 function applyEgg() {
  const egg=currentEggs.find(x=>String(x.id)===String(selects.egg.value));
  if (!egg) return;
  const images=egg.docker_images||{};
  const image=Object.keys(images)[0]||'';
  if (image) $('ptero_docker_image').value=image;
  if (egg.startup) $('ptero_startup').value=egg.startup;
  const env={};
  (egg.variables||[]).forEach(v=> { if (v.env_variable) env[v.env_variable]=v.default_value??''; });
  $('ptero_environment').value=JSON.stringify(env,null,2);
 }
 async function loadPanelData() {
  $('pteroLoadData').disabled=true;
  setStatus('Connecting to Pterodactyl and loading locations, nodes and software…');
  try {
   const [locations,nests]=await Promise.all([fetchData('locations'),fetchData('nests')]);
   fill(selects.location,locations,saved.location,x=>x.name,false,'Choose a location');
   fill(selects.nest,nests,saved.nest,x=>x.name,false,'Choose software category');
   await Promise.all([loadNodes(saved.node),loadEggs(saved.egg)]);
   applyEgg();
   setStatus('Loaded '+locations.length+' locations, '+nests.length+' software categories, '+currentEggs.length+' Eggs. Review selections, then save deployment defaults.');
  } catch(e) { setStatus(e.message||'Pterodactyl discovery failed.',true); }
  finally { $('pteroLoadData').disabled=false; }
 }
 selects.location.addEventListener('change',()=>{saved.node='';saved.allocation='';loadNodes('');});
 selects.node.addEventListener('change',()=>{saved.allocation='';loadAllocations('');});
 selects.nest.addEventListener('change',()=>{saved.egg='';loadEggs('');});
 selects.egg.addEventListener('change',applyEgg);
 $('pteroLoadData').addEventListener('click',loadPanelData);
 @if($settings['ptero_api_key_configured'])
 loadPanelData();
 @endif
})();
</script>
@endsection
