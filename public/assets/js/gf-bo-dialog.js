(function(){
  function open(selector, opener){var dialog=document.querySelector(selector);if(!dialog||typeof dialog.showModal!=='function')return;dialog.__opener=opener;dialog.showModal();}
  document.addEventListener('click',function(e){var opener=e.target.closest('[data-bo-open]');if(opener){e.preventDefault();open(opener.getAttribute('data-bo-open'),opener);}var closer=e.target.closest('[data-bo-close]');if(closer){var d=closer.closest('dialog');if(d)d.close();}});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('dialog[open]').forEach(function(d){d.close();});});
  document.addEventListener('close',function(e){if(e.target.matches&&e.target.matches('dialog')&&e.target.__opener)e.target.__opener.focus();},true);
})();
