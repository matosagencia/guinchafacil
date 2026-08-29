(function(){'use strict';
 var d=window.GF_MARKETING_DATA||{}; if(typeof Chart==='undefined')return;
 function make(id,labels,values,type,colors){var el=document.getElementById(id);if(!el)return;new Chart(el,{type:type,data:{labels:labels,datasets:[{data:values,backgroundColor:colors||['#2fb34a','#4285f4','#f9ab00','#dc3545','#6f42c1','#20c997','#fd7e14','#6c757d']}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});}
 make('mkDemandChart',(d.servicos||[]).map(x=>x.service_key||'outro'),(d.servicos||[]).map(x=>Number(x.total||0)),'doughnut');
 make('mkChannelChart',(d.canais||[]).map(x=>x.canal||'organico'),(d.canais||[]).map(x=>Number(x.pedidos||0)),'bar');
 make('mkServiceChart',(d.servicos||[]).map(x=>x.service_key||'outro'),(d.servicos||[]).map(x=>Number(x.total||0)),'bar');
})();
