(function (window) {
    'use strict';

    function pluralize(value, singular, plural) {
        return value === 1 ? singular : plural;
    }

    function formatEta(minutes) {
        minutes = Number(minutes || 0);
        if (minutes <= 0) return 'Chegada iminente';
        if (minutes < 60) return minutes + ' ' + pluralize(minutes, 'minuto', 'minutos');

        var hours = Math.floor(minutes / 60);
        var remaining = minutes % 60;
        if (remaining === 0) return hours + ' ' + pluralize(hours, 'hora', 'horas');
        return hours + ' ' + pluralize(hours, 'hora', 'horas') + ' e ' + remaining + ' ' + pluralize(remaining, 'minuto', 'minutos');
    }

    function formatDistance(meters) {
        meters = Number(meters || 0);
        if (meters < 1000) return Math.round(meters) + ' m';
        return (meters / 1000).toFixed(1).replace('.', ',') + ' km';
    }

    function formatInstruction(text) {
        var value = String(text || '').trim();
        if (value === '') return '';

        return value
            .replace(/^Head/i, 'Siga')
            .replace(/^Continue/i, 'Continue')
            .replace(/^Turn left/i, 'Vire à esquerda')
            .replace(/^Turn right/i, 'Vire à direita')
            .replace(/^Slight left/i, 'Curva suave à esquerda')
            .replace(/^Slight right/i, 'Curva suave à direita')
            .replace(/^Arrive at/i, 'Chegue em')
            .replace(/\bDestination\b/i, 'destino');
    }

    window.RouteFormatterPtBr = {
        formatEta: formatEta,
        formatDistance: formatDistance,
        formatInstruction: formatInstruction
    };
})(window);
