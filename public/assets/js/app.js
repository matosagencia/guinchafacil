class MapManager {
    static init(mapId) {
        const map = L.map(mapId).setView([-23.55052, -46.633308], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(map);
    }
}

class LocationService {
    static getCurrentPosition(successCallback, errorCallback) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
        } else {
            errorCallback('Geolocation is not supported by this browser.');
        }
    }
}

class AjaxService {
    static post(url, data, callback) {
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        })
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error:', error));
    }

    static get(url, callback) {
        fetch(url)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error:', error));
    }
}

class ChatManager {
    static startPolling() {
        setInterval(() => {
            console.log('Polling for new messages...');
            // Fetch new messages
        }, 10000);
    }
}

class MaskUtils {
    static applyCPF(input) {
        // Apply CPF mask
    }

    static applyPhone(input) {
        // Apply phone mask
    }
}

class AddressService {
    static searchByCEP(cep, callback) {
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
        .then(response => response.json())
        .then(data => callback(data))
        .catch(error => console.error('Error:', error));
    }
}

class CostCalculator {
    static calculate() {
        // Calculate costs dynamically
    }
}

class StatusPoller {
    static start() {
        setInterval(() => {
            console.log('Polling for status updates...');
            // Fetch status updates
        }, 10000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    MaskUtils.applyCPF(document.getElementById('cpf'));
    MaskUtils.applyPhone(document.getElementById('telefone'));
});
