(function(){
  var bp=(document.querySelector('form[action*="/especialista/"]')||{}).action?.split('/especialista/')[0]||'';
  setInterval(function(){fetch(bp+'/especialista/notificacoes',{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(!d.ok)return;var n=d.ofertas.filter(function(x){return x.status==='ofertado'}).length;document.title=n?'('+n+') Chamado disponível — Especialista':'Painel do especialista';}).catch(function(){});},10000);
  var forms=document.querySelectorAll('form[action*="/especialista/atendimento/"]');
  if(!navigator.geolocation)return;
  var csrf=document.querySelector('input[name="csrf_token"]')?.value||'';
  forms.forEach(function(f){var m=f.action.match(/atendimento\/(?:aceitar|status|diagnostico|chegada|localizacao)\/(\d+)/);if(!m)return;var id=m[1];setInterval(function(){navigator.geolocation.getCurrentPosition(function(p){fetch(f.action.split('/atendimento/')[0]+'/atendimento/localizacao/'+id,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf_token:csrf,lat:p.coords.latitude,lng:p.coords.longitude,accuracy:p.coords.accuracy||0})});},function(){},{enableHighAccuracy:true,maximumAge:10000,timeout:8000});},15000);});
})();
