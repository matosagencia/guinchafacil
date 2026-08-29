(function () {
    'use strict';
    const gps = document.getElementById('btnGps');
    const status = document.getElementById('gpsStatus');
    const lat = document.getElementById('lat_origem');
    const lng = document.getElementById('lng_origem');
    const address = document.getElementById('localizacao');
    const destination = document.getElementById('destino');
    if (!gps || !status || !lat || !lng) return;

    function setupAddressAutocomplete(input, latInput, lngInput, label) {
        if (!input || !latInput || !lngInput) return;
        const wrapper = input.parentElement;
        wrapper.style.position = 'relative';
        const list = document.createElement('div');
        list.className = 'public-address-suggestions'; list.setAttribute('role', 'listbox'); list.hidden = true;
        wrapper.appendChild(list);
        let timer = null; let requestId = 0; let selected = false;
        function clearList() { list.innerHTML = ''; list.hidden = true; }
        function choose(item) { input.value=item.display_name||''; latInput.value=item.lat; lngInput.value=item.lng; selected=true; clearList(); if(label==='origem') status.textContent='Endereço selecionado. Agora informe a situação.'; }
        async function search() {
            const query=input.value.trim(); if(query.length<4||selected){clearList();return;} const current=++requestId;
            try { const res=await fetch((document.body.dataset.basePath||'')+'/geocode/public?q='+encodeURIComponent(query),{headers:{Accept:'application/json'}}); if(current!==requestId)return; const payload=await res.json(); const items=payload.items||[]; list.innerHTML=''; if(!items.length){clearList();return;}
                items.forEach(function(item){const button=document.createElement('button');button.type='button';button.className='public-address-suggestion';button.textContent=item.display_name||'Endereço encontrado';button.addEventListener('mousedown',function(e){e.preventDefault();choose(item);});list.appendChild(button);}); list.hidden=false;
            } catch(e){clearList();}
        }
        input.addEventListener('input',function(){selected=false;latInput.value='';lngInput.value='';clearTimeout(timer);timer=setTimeout(search,500);}); input.addEventListener('blur',function(){setTimeout(clearList,180);}); input.addEventListener('keydown',function(e){if(e.key==='Escape')clearList();});
    }
    setupAddressAutocomplete(address, lat, lng, 'origem');
    setupAddressAutocomplete(destination, document.getElementById('lat_destino'), document.getElementById('lng_destino'), 'destino');

    document.querySelectorAll('[data-choice-group][data-choice-value]').forEach(function (card) {
        card.addEventListener('click', function () {
            const group = card.dataset.choiceGroup;
            const hidden = document.getElementById(group);
            if (hidden) hidden.value = card.dataset.choiceValue;
            document.querySelectorAll('[data-choice-group="' + group + '"]').forEach(function (item) {
                const active = item === card;
                item.classList.toggle('is-selected', active);
                item.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            if (group === 'tipo_problema') document.dispatchEvent(new Event('prequote:type-change'));
        });
    });

    gps.addEventListener('click', function () {
        if (!navigator.geolocation) {
            status.textContent = 'Seu navegador nao oferece localizacao automatica.';
            return;
        }
        gps.disabled = true;
        status.textContent = 'Obtendo sua localizacao...';
        navigator.geolocation.getCurrentPosition(function (position) {
            lat.value = position.coords.latitude;
            lng.value = position.coords.longitude;
            if (address) address.value = 'Localizacao atual confirmada';
            status.textContent = 'Localizacao confirmada. Agora informe a situacao.';
            gps.disabled = false;
        }, function () {
            status.textContent = 'Nao foi possivel obter o GPS. Autorize a localizacao e tente novamente.';
            gps.disabled = false;
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });
}());
