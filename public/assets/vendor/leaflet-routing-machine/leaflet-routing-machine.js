(function(){function r(e,n,t){function o(i,f){if(!n[i]){if(!e[i]){var c="function"==typeof require&&require;if(!f&&c)return c(i,!0);if(u)return u(i,!0);var a=new Error("Cannot find module '"+i+"'");throw a.code="MODULE_NOT_FOUND",a}var p=n[i]={exports:{}};e[i][0].call(p.exports,function(r){var n=e[i][1][r];return o(n||r)},p,p.exports,r,e,n,t)}return n[i].exports}for(var u="function"==typeof require&&require,i=0;i<t.length;i++)o(t[i]);return o}return r})()({1:[function(_dereq_,module,exports){

function corslite(url, callback, cors) {

    var sent = false;



    if (typeof window.XMLHttpRequest === 'undefined') {

        return callback(Error('Browser not supported'));

    }



    if (typeof cors === 'undefined') {

        var m = url.match(/^\s*https?:\/\/[^\/]*/);

        cors = m && (m[0] !== location.protocol + '//' + location.hostname +

                (location.port ? ':' + location.port : ''));

    }



    var x = new window.XMLHttpRequest();



    function isSuccessful(status) {

        return status >= 200 && status < 300 || status === 304;

    }



    if (cors && !('withCredentials' in x)) {

        // IE8-9

        x = new window.XDomainRequest();



        // Ensure callback is never called synchronously, i.e., before

        // x.send() returns (this has been observed in the wild).

        // See https://github.com/mapbox/mapbox.js/issues/472

        var original = callback;

        callback = function() {

            if (sent) {

                original.apply(this, arguments);

            } else {

                var that = this, args = arguments;

                setTimeout(function() {

                    original.apply(that, args);

                }, 0);

            }

        }

    }



    function loaded() {

        if (

            // XDomainRequest

            x.status === undefined ||

            // modern browsers

            isSuccessful(x.status)) callback.call(x, null, x);

        else callback.call(x, x, null);

    }



    // Both `onreadystatechange` and `onload` can fire. `onreadystatechange`

    // has [been supported for longer](http://stackoverflow.com/a/9181508/229001).

    if ('onload' in x) {

        x.onload = loaded;

    } else {

        x.onreadystatechange = function readystate() {

            if (x.readyState === 4) {

                loaded();

            }

        };

    }



    // Call the callback with the XMLHttpRequest object as an error and prevent

    // it from ever being called again by reassigning it to `noop`

    x.onerror = function error(evt) {

        // XDomainRequest provides no evt parameter

        callback.call(this, evt || true, null);

        callback = function() { };

    };



    // IE9 must have onprogress be set to a unique function.

    x.onprogress = function() { };



    x.ontimeout = function(evt) {

        callback.call(this, evt, null);

        callback = function() { };

    };



    x.onabort = function(evt) {

        callback.call(this, evt, null);

        callback = function() { };

    };



    // GET is the only supported HTTP Verb by XDomainRequest and is the

    // only one supported here.

    x.open('GET', url, true);



    // Send the request. Sending data is not supported.

    x.send(null);

    sent = true;



    return x;

}



if (typeof module !== 'undefined') module.exports = corslite;



},{}],2:[function(_dereq_,module,exports){

'use strict';



/**

 * Based off of [the offical Google document](https://developers.google.com/maps/documentation/utilities/polylinealgorithm)

 *

 * Some parts from [this implementation](http://facstaff.unca.edu/mcmcclur/GoogleMaps/EncodePolyline/PolylineEncoder.js)

 * by [Mark McClure](http://facstaff.unca.edu/mcmcclur/)

 *

 * @module polyline

 */



var polyline = {};



function py2_round(value) {

    // Google's polyline algorithm uses the same rounding strategy as Python 2, which is different from JS for negative values

    return Math.floor(Math.abs(value) + 0.5) * Math.sign(value);

}



function encode(current, previous, factor) {

    current = py2_round(current * factor);

    previous = py2_round(previous * factor);

    var coordinate = current - previous;

    coordinate <<= 1;

    if (current - previous < 0) {

        coordinate = ~coordinate;

    }

    var output = '';

    while (coordinate >= 0x20) {

        output += String.fromCharCode((0x20 | (coordinate & 0x1f)) + 63);

        coordinate >>= 5;

    }

    output += String.fromCharCode(coordinate + 63);

    return output;

}



/**

 * Decodes to a [latitude, longitude] coordinates array.

 *

 * This is adapted from the implementation in Project-OSRM.

 *

 * @param {String} str

 * @param {Number} precision

 * @returns {Array}

 *

 * @see https://github.com/Project-OSRM/osrm-frontend/blob/master/WebContent/routing/OSRM.RoutingGeometry.js

 */

polyline.decode = function(str, precision) {

    var index = 0,

        lat = 0,

        lng = 0,

        coordinates = [],

        shift = 0,

        result = 0,

        byte = null,

        latitude_change,

        longitude_change,

        factor = Math.pow(10, precision || 5);



    // Coordinates have variable length when encoded, so just keep

    // track of whether we've hit the end of the string. In each

    // loop iteration, a single coordinate is decoded.

    while (index < str.length) {



        // Reset shift, result, and byte

        byte = null;

        shift = 0;

        result = 0;



        do {

            byte = str.charCodeAt(index++) - 63;

            result |= (byte & 0x1f) << shift;

            shift += 5;

        } while (byte >= 0x20);



        latitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));



        shift = result = 0;



        do {

            byte = str.charCodeAt(index++) - 63;

            result |= (byte & 0x1f) << shift;

            shift += 5;

        } while (byte >= 0x20);



        longitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));



        lat += latitude_change;

        lng += longitude_change;



        coordinates.push([lat / factor, lng / factor]);

    }



    return coordinates;

};



/**

 * Encodes the given [latitude, longitude] coordinates array.

 *

 * @param {Array.<Array.<Number>>} coordinates

 * @param {Number} precision

 * @returns {String}

 */

polyline.encode = function(coordinates, precision) {

    if (!coordinates.length) { return ''; }



    var factor = Math.pow(10, precision || 5),

        output = encode(coordinates[0][0], 0, factor) + encode(coordinates[0][1], 0, factor);



    for (var i = 1; i < coordinates.length; i++) {

        var a = coordinates[i], b = coordinates[i - 1];

        output += encode(a[0], b[0], factor);

        output += encode(a[1], b[1], factor);

    }



    return output;

};



function flipped(coords) {

    var flipped = [];

    for (var i = 0; i < coords.length; i++) {

        flipped.push(coords[i].slice().reverse());

    }

    return flipped;

}



/**

 * Encodes a GeoJSON LineString feature/geometry.

 *

 * @param {Object} geojson

 * @param {Number} precision

 * @returns {String}

 */

polyline.fromGeoJSON = function(geojson, precision) {

    if (geojson && geojson.type === 'Feature') {

        geojson = geojson.geometry;

    }

    if (!geojson || geojson.type !== 'LineString') {

        throw new Error('Input must be a GeoJSON LineString');

    }

    return polyline.encode(flipped(geojson.coordinates), precision);

};



/**

 * Decodes to a GeoJSON LineString geometry.

 *

 * @param {String} str

 * @param {Number} precision

 * @returns {Object}

 */

polyline.toGeoJSON = function(str, precision) {

    var coords = polyline.decode(str, precision);

    return {

        type: 'LineString',

        coordinates: flipped(coords)

    };

};



if (typeof module === 'object' && module.exports) {

    module.exports = polyline;

}



},{}],3:[function(_dereq_,module,exports){

var languages = _dereq_('./languages');

var instructions = languages.instructions;

var grammars = languages.grammars;

var abbreviations = languages.abbreviations;



module.exports = function(version) {

    Object.keys(instructions).forEach(function(code) {

        if (!instructions[code][version]) { throw 'invalid version ' + version + ': ' + code + ' not supported'; }

    });



    return {

        capitalizeFirstLetter: function(language, string) {

            return string.charAt(0).toLocaleUpperCase(language) + string.slice(1);

        },

        ordinalize: function(language, number) {

            // Transform numbers to their translated ordinalized value

            if (!language) throw new Error('No language code provided');



            return instructions[language][version].constants.ordinalize[number.toString()] || '';

        },

        directionFromDegree: function(language, degree) {

            // Transform degrees to their translated compass direction

            if (!language) throw new Error('No language code provided');

            if (!degree && degree !== 0) {

                // step had no bearing_after degree, ignoring

                return '';

            } else if (degree >= 0 && degree <= 20) {

                return instructions[language][version].constants.direction.north;

            } else if (degree > 20 && degree < 70) {

                return instructions[language][version].constants.direction.northeast;

            } else if (degree >= 70 && degree <= 110) {

                return instructions[language][version].constants.direction.east;

            } else if (degree > 110 && degree < 160) {

                return instructions[language][version].constants.direction.southeast;

            } else if (degree >= 160 && degree <= 200) {

                return instructions[language][version].constants.direction.south;

            } else if (degree > 200 && degree < 250) {

                return instructions[language][version].constants.direction.southwest;

            } else if (degree >= 250 && degree <= 290) {

                return instructions[language][version].constants.direction.west;

            } else if (degree > 290 && degree < 340) {

                return instructions[language][version].constants.direction.northwest;

            } else if (degree >= 340 && degree <= 360) {

                return instructions[language][version].constants.direction.north;

            } else {

                throw new Error('Degree ' + degree + ' invalid');

            }

        },

        laneConfig: function(step) {

            // Reduce any lane combination down to a contracted lane diagram

            if (!step.intersections || !step.intersections[0].lanes) throw new Error('No lanes object');



            var config = [];

            var currentLaneValidity = null;



            step.intersections[0].lanes.forEach(function (lane) {

                if (currentLaneValidity === null || currentLaneValidity !== lane.valid) {

                    if (lane.valid) {

                        config.push('o');

                    } else {

                        config.push('x');

                    }

                    currentLaneValidity = lane.valid;

                }

            });



            return config.join('');

        },

        getWayName: function(language, step, options) {

            var classes = options ? options.classes || [] : [];

            if (typeof step !== 'object') throw new Error('step must be an Object');

            if (!language) throw new Error('No language code provided');

            if (!Array.isArray(classes)) throw new Error('classes must be an Array or undefined');



            var wayName;

            var name = step.name || '';

            var ref = (step.ref || '').split(';')[0];



            // Remove hacks from Mapbox Directions mixing ref into name

            if (name === step.ref) {

                // if both are the same we assume that there used to be an empty name, with the ref being filled in for it

                // we only need to retain the ref then

                name = '';

            }

            name = name.replace(' (' + step.ref + ')', '');



            // In attempt to avoid using the highway name of a way,

            // check and see if the step has a class which should signal

            // the ref should be used instead of the name.

            var wayMotorway = classes.indexOf('motorway') !== -1;



            if (name && ref && name !== ref && !wayMotorway) {

                var phrase = instructions[language][version].phrase['name and ref'] ||

                    instructions.en[version].phrase['name and ref'];

                wayName = this.tokenize(language, phrase, {

                    name: name,

                    ref: ref

                }, options);

            } else if (name && ref && wayMotorway && (/\d/).test(ref)) {

                wayName = options && options.formatToken ? options.formatToken('ref', ref) : ref;

            } else if (!name && ref) {

                wayName = options && options.formatToken ? options.formatToken('ref', ref) : ref;

            } else {

                wayName = options && options.formatToken ? options.formatToken('name', name) : name;

            }



            return wayName;

        },



        /**

         * Formulate a localized text instruction from a step.

         *

         * @param  {string} language           Language code.

         * @param  {object} step               Step including maneuver property.

         * @param  {object} opts               Additional options.

         * @param  {string} opts.legIndex      Index of leg in the route.

         * @param  {string} opts.legCount      Total number of legs in the route.

         * @param  {array}  opts.classes       List of road classes.

         * @param  {string} opts.waypointName  Name of waypoint for arrival instruction.

         *

         * @return {string} Localized text instruction.

         */

        compile: function(language, step, opts) {

            if (!language) throw new Error('No language code provided');

            if (languages.supportedCodes.indexOf(language) === -1) throw new Error('language code ' + language + ' not loaded');

            if (!step.maneuver) throw new Error('No step maneuver provided');

            var options = opts || {};



            var type = step.maneuver.type;

            var modifier = step.maneuver.modifier;

            var mode = step.mode;

            // driving_side will only be defined in OSRM 5.14+

            var side = step.driving_side;



            if (!type) { throw new Error('Missing step maneuver type'); }

            if (type !== 'depart' && type !== 'arrive' && !modifier) { throw new Error('Missing step maneuver modifier'); }



            if (!instructions[language][version][type]) {

                // Log for debugging

                console.log('Encountered unknown instruction type: ' + type); // eslint-disable-line no-console

                // OSRM specification assumes turn types can be added without

                // major version changes. Unknown types are to be treated as

                // type `turn` by clients

                type = 'turn';

            }



            // Use special instructions if available, otherwise `defaultinstruction`

            var instructionObject;

            if (instructions[language][version].modes[mode]) {

                instructionObject = instructions[language][version].modes[mode];

            } else {

              // omit side from off ramp if same as driving_side

              // note: side will be undefined if the input is from OSRM <5.14

              // but the condition should still evaluate properly regardless

                var omitSide = type === 'off ramp' && modifier.indexOf(side) >= 0;

                if (instructions[language][version][type][modifier] && !omitSide) {

                    instructionObject = instructions[language][version][type][modifier];

                } else {

                    instructionObject = instructions[language][version][type].default;

                }

            }



            // Special case handling

            var laneInstruction;

            switch (type) {

            case 'use lane':

                laneInstruction = instructions[language][version].constants.lanes[this.laneConfig(step)];

                if (!laneInstruction) {

                    // If the lane combination is not found, default to continue straight

                    instructionObject = instructions[language][version]['use lane'].no_lanes;

                }

                break;

            case 'rotary':

            case 'roundabout':

                if (step.rotary_name && step.maneuver.exit && instructionObject.name_exit) {

                    instructionObject = instructionObject.name_exit;

                } else if (step.rotary_name && instructionObject.name) {

                    instructionObject = instructionObject.name;

                } else if (step.maneuver.exit && instructionObject.exit) {

                    instructionObject = instructionObject.exit;

                } else {

                    instructionObject = instructionObject.default;

                }

                break;

            default:

                // NOOP, since no special logic for that type

            }



            // Decide way_name with special handling for name and ref

            var wayName = this.getWayName(language, step, options);



            // Decide which instruction string to use

            // Destination takes precedence over name

            var instruction;

            if (step.destinations && step.exits && instructionObject.exit_destination) {

                instruction = instructionObject.exit_destination;

            } else if (step.destinations && instructionObject.destination) {

                instruction = instructionObject.destination;

            } else if (step.exits && instructionObject.exit) {

                instruction = instructionObject.exit;

            } else if (wayName && instructionObject.name) {

                instruction = instructionObject.name;

            } else if (options.waypointName && instructionObject.named) {

                instruction = instructionObject.named;

            } else {

                instruction = instructionObject.default;

            }



            var destinations = step.destinations && step.destinations.split(': ');

            var destinationRef = destinations && destinations[0].split(',')[0];

            var destination = destinations && destinations[1] && destinations[1].split(',')[0];

            var firstDestination;

            if (destination && destinationRef) {

                firstDestination = destinationRef + ': ' + destination;

            } else {

                firstDestination = destinationRef || destination || '';

            }



            var nthWaypoint = options.legIndex >= 0 && options.legIndex !== options.legCount - 1 ? this.ordinalize(language, options.legIndex + 1) : '';



            // Replace tokens

            // NOOP if they don't exist

            var replaceTokens = {

                'way_name': wayName,

                'destination': firstDestination,

                'exit': (step.exits || '').split(';')[0],

                'exit_number': this.ordinalize(language, step.maneuver.exit || 1),

                'rotary_name': step.rotary_name,

                'lane_instruction': laneInstruction,

                'modifier': instructions[language][version].constants.modifier[modifier],

                'direction': this.directionFromDegree(language, step.maneuver.bearing_after),

                'nth': nthWaypoint,

                'waypoint_name': options.waypointName

            };



            return this.tokenize(language, instruction, replaceTokens, options);

        },

        grammarize: function(language, name, grammar) {

            if (!language) throw new Error('No language code provided');

            // Process way/rotary name with applying grammar rules if any

            if (name && grammar && grammars && grammars[language] && grammars[language][version]) {

                var rules = grammars[language][version][grammar];

                if (rules) {

                    // Pass original name to rules' regular expressions enclosed with spaces for simplier parsing

                    var n = ' ' + name + ' ';

                    var flags = grammars[language].meta.regExpFlags || '';

                    rules.forEach(function(rule) {

                        var re = new RegExp(rule[0], flags);

                        n = n.replace(re, rule[1]);

                    });



                    return n.trim();

                }

            }



            return name;

        },

        abbreviations: abbreviations,

        tokenize: function(language, instruction, tokens, options) {

            if (!language) throw new Error('No language code provided');

            // Keep this function context to use in inline function below (no arrow functions in ES4)

            var that = this;

            var startedWithToken = false;

            var output = instruction.replace(/\{(\w+)(?::(\w+))?\}/g, function(token, tag, grammar, offset) {

                var value = tokens[tag];



                // Return unknown token unchanged

                if (typeof value === 'undefined') {

                    return token;

                }



                value = that.grammarize(language, value, grammar);



                // If this token appears at the beginning of the instruction, capitalize it.

                if (offset === 0 && instructions[language].meta.capitalizeFirstLetter) {

                    startedWithToken = true;

                    value = that.capitalizeFirstLetter(language, value);

                }



                if (options && options.formatToken) {

                    value = options.formatToken(tag, value);

                }



                return value;

            })

            .replace(/ {2}/g, ' '); // remove excess spaces



            if (!startedWithToken && instructions[language].meta.capitalizeFirstLetter) {

                return this.capitalizeFirstLetter(language, output);

            }



            return output;

        }

    };

};



},{"./languages":4}],4:[function(_dereq_,module,exports){

// Load all language files explicitly to allow integration

// with bundling tools like webpack and browserify

var instructionsDa = _dereq_('./languages/translations/da.json');

var instructionsDe = _dereq_('./languages/translations/de.json');

var instructionsEn = _dereq_('./languages/translations/en.json');

var instructionsEo = _dereq_('./languages/translations/eo.json');

var instructionsEs = _dereq_('./languages/translations/es.json');

var instructionsEsEs = _dereq_('./languages/translations/es-ES.json');

var instructionsFi = _dereq_('./languages/translations/fi.json');

var instructionsFr = _dereq_('./languages/translations/fr.json');

var instructionsHe = _dereq_('./languages/translations/he.json');

var instructionsId = _dereq_('./languages/translations/id.json');

var instructionsIt = _dereq_('./languages/translations/it.json');

var instructionsKo = _dereq_('./languages/translations/ko.json');

var instructionsMy = _dereq_('./languages/translations/my.json');

var instructionsNl = _dereq_('./languages/translations/nl.json');

var instructionsNo = _dereq_('./languages/translations/no.json');

var instructionsPl = _dereq_('./languages/translations/pl.json');

var instructionsPtBr = _dereq_('./languages/translations/pt-BR.json');

var instructionsPtPt = _dereq_('./languages/translations/pt-PT.json');

var instructionsRo = _dereq_('./languages/translations/ro.json');

var instructionsRu = _dereq_('./languages/translations/ru.json');

var instructionsSv = _dereq_('./languages/translations/sv.json');

var instructionsTr = _dereq_('./languages/translations/tr.json');

var instructionsUk = _dereq_('./languages/translations/uk.json');

var instructionsVi = _dereq_('./languages/translations/vi.json');

var instructionsZhHans = _dereq_('./languages/translations/zh-Hans.json');



// Load all grammar files

var grammarFr = _dereq_('./languages/grammar/fr.json');

var grammarRu = _dereq_('./languages/grammar/ru.json');



// Load all abbreviations files

var abbreviationsBg = _dereq_('./languages/abbreviations/bg.json');

var abbreviationsCa = _dereq_('./languages/abbreviations/ca.json');

var abbreviationsDa = _dereq_('./languages/abbreviations/da.json');

var ebbreviationsDe = _dereq_('./languages/abbreviations/de.json');

var abbreviationsEn = _dereq_('./languages/abbreviations/en.json');

var abbreviationsEs = _dereq_('./languages/abbreviations/es.json');

var abbreviationsFr = _dereq_('./languages/abbreviations/fr.json');

var abbreviationsHe = _dereq_('./languages/abbreviations/he.json');

var abbreviationsHu = _dereq_('./languages/abbreviations/hu.json');

var abbreviationsLt = _dereq_('./languages/abbreviations/lt.json');

var abbreviationsNl = _dereq_('./languages/abbreviations/nl.json');

var abbreviationsRu = _dereq_('./languages/abbreviations/ru.json');

var abbreviationsSl = _dereq_('./languages/abbreviations/sl.json');

var abbreviationsSv = _dereq_('./languages/abbreviations/sv.json');

var abbreviationsUk = _dereq_('./languages/abbreviations/uk.json');

var abbreviationsVi = _dereq_('./languages/abbreviations/vi.json');



// Create a list of supported codes

var instructions = {

    'da': instructionsDa,

    'de': instructionsDe,

    'en': instructionsEn,

    'eo': instructionsEo,

    'es': instructionsEs,

    'es-ES': instructionsEsEs,

    'fi': instructionsFi,

    'fr': instructionsFr,

    'he': instructionsHe,

    'id': instructionsId,

    'it': instructionsIt,

    'ko': instructionsKo,

    'my': instructionsMy,

    'nl': instructionsNl,

    'no': instructionsNo,

    'pl': instructionsPl,

    'pt-BR': instructionsPtBr,

    'pt-PT': instructionsPtPt,

    'ro': instructionsRo,

    'ru': instructionsRu,

    'sv': instructionsSv,

    'tr': instructionsTr,

    'uk': instructionsUk,

    'vi': instructionsVi,

    'zh-Hans': instructionsZhHans

};



// Create list of supported grammar

var grammars = {

    'fr': grammarFr,

    'ru': grammarRu

};



// Create list of supported abbrevations

var abbreviations = {

    'bg': abbreviationsBg,

    'ca': abbreviationsCa,

    'da': abbreviationsDa,

    'de': ebbreviationsDe,

    'en': abbreviationsEn,

    'es': abbreviationsEs,

    'fr': abbreviationsFr,

    'he': abbreviationsHe,

    'hu': abbreviationsHu,

    'lt': abbreviationsLt,

    'nl': abbreviationsNl,

    'ru': abbreviationsRu,

    'sl': abbreviationsSl,

    'sv': abbreviationsSv,

    'uk': abbreviationsUk,

    'vi': abbreviationsVi

};

module.exports = {

    supportedCodes: Object.keys(instructions),

    instructions: instructions,

    grammars: grammars,

    abbreviations: abbreviations

};



},{"./languages/abbreviations/bg.json":5,"./languages/abbreviations/ca.json":6,"./languages/abbreviations/da.json":7,"./languages/abbreviations/de.json":8,"./languages/abbreviations/en.json":9,"./languages/abbreviations/es.json":10,"./languages/abbreviations/fr.json":11,"./languages/abbreviations/he.json":12,"./languages/abbreviations/hu.json":13,"./languages/abbreviations/lt.json":14,"./languages/abbreviations/nl.json":15,"./languages/abbreviations/ru.json":16,"./languages/abbreviations/sl.json":17,"./languages/abbreviations/sv.json":18,"./languages/abbreviations/uk.json":19,"./languages/abbreviations/vi.json":20,"./languages/grammar/fr.json":21,"./languages/grammar/ru.json":22,"./languages/translations/da.json":23,"./languages/translations/de.json":24,"./languages/translations/en.json":25,"./languages/translations/eo.json":26,"./languages/translations/es-ES.json":27,"./languages/translations/es.json":28,"./languages/translations/fi.json":29,"./languages/translations/fr.json":30,"./languages/translations/he.json":31,"./languages/translations/id.json":32,"./languages/translations/it.json":33,"./languages/translations/ko.json":34,"./languages/translations/my.json":35,"./languages/translations/nl.json":36,"./languages/translations/no.json":37,"./languages/translations/pl.json":38,"./languages/translations/pt-BR.json":39,"./languages/translations/pt-PT.json":40,"./languages/translations/ro.json":41,"./languages/translations/ru.json":42,"./languages/translations/sv.json":43,"./languages/translations/tr.json":44,"./languages/translations/uk.json":45,"./languages/translations/vi.json":46,"./languages/translations/zh-Hans.json":47}],5:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "????????????": "????",

        "??????": "????",

        "?????": "???",

        "?????": "Mkt",

        "??????????": "????",

        "??????": "???",

        "?????": "??",

        "???????????": "??",

        "?????": "??",

        "?????": "??",

        "??????": "?-?",

        "????": "??",

        "???????": "?-?",

        "??????": "??",

        "??????????": "???",

        "???????": "??",

        "????": "???",

        "?????": "?-?",

        "?????": "?-?",

        "???????": "?-?",

        "????????": "???",

        "???????????": "???",

        "????": "??",

        "?????": "???",

        "???????": "??",

        "????": "?.",

        "????????": "???",

        "??????": "??",

        "???????": "?-?",

        "??????": "????",

        "????": "??"

    },

    "classifications": {

        "????????": "???",

        "??????": "??",

        "?????": "??",

        "??????": "???",

        "??????????": "?-??",

        "??????": "??",

        "???": "???",

        "?????": "?-?",

        "??????": "?-??",

        "??????": "??",

        "?????": "??",

        "????": "??",

        "?????????": "???",

        "?????": "??",

        "???????????": "????",

        "???????": "??",

        "????": "??",

        "???": "??",

        "????????": "???",

        "???": "???",

        "?????????": "????",

        "????": "??"

    },

    "directions": {

        "???????????": "??",

        "???????????": "??",

        "????????": "??",

        "????????": "??",

        "?????": "?",

        "?????": "?",

        "??": "?"

    }

}



},{}],6:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "comunicacions": "Com.",

        "entitat de poblaci�": "Nucli",

        "disseminat": "Diss.",

        "cap de municipi": "Cap",

        "indret": "Indr.",

        "comarca": "Cca.",

        "relleu del litoral": "Lit.",

        "municipi": "Mun.",

        "xarxa hidrogr�fica": "Curs Fluv.",

        "equipament": "Equip.",

        "orografia": "Orogr.",

        "barri": "Barri",

        "edificaci�": "Edif.",

        "edificaci� hist�rica": "Edif. Hist.",

        "entitat descentralitzada": "E.M.D.",

        "element hidrogr�fic": "Hidr."

    },

    "classifications": {

        "rotonda": "Rot.",

        "carrerada": "Ca.",

        "jard�": "J.",

        "paratge": "Pge.",

        "pont": "Pont",

        "lloc": "Lloc",

        "rambla": "Rbla.",

        "cases": "Cses.",

        "barranc": "Bnc.",

        "plana": "Plana",

        "pol�gon": "Pol.",

        "muralla": "Mur.",

        "enlla�": "Ella�",

        "antiga carretera": "Actra",

        "glorieta": "Glor.",

        "autovia": "Autv.",

        "prolongaci�": "Prol.",

        "cal�ada": "Cda.",

        "carretera": "Ctra.",

        "pujada": "Pda.",

        "torrent": "T.",

        "disseminat": "Disse",

        "barri": "B.",

        "cintur�": "Cinto",

        "passera": "Psera",

        "sender": "Send.",

        "carrer": "C.",

        "s�quia": "S�q.",

        "blocs": "Bloc",

        "rambleta": "Rblt.",

        "partida": "Par.",

        "costa": "Cos.",

        "sector": "Sec.",

        "corral�": "Crral",

        "urbanitzaci�": "Urb.",

        "autopista": "Autp.",

        "grup": "Gr.",

        "platja": "Pja.",

        "jardins": "J.",

        "complex": "Comp.",

        "portals": "Ptals",

        "finca": "Fin.",

        "travessera": "Trav.",

        "pla�a": "Pl.",

        "travessia": "Trv.",

        "pol�gon industrial": "PI.",

        "passatge": "Ptge.",

        "apartaments": "Apmt.",

        "mirador": "Mira.",

        "antic": "Antic",

        "acc�s": "Acc.",

        "col�nia": "Col.",

        "corriol": "Crol.",

        "portal": "Ptal.",

        "porta": "Pta.",

        "port": "Port",

        "carrer�": "Cr�.",

        "riera": "Ra.",

        "circumval�laci�": "Cval.",

        "baixada": "Bda.",

        "placeta": "Plta.",

        "escala": "Esc.",

        "gran via": "GV",

        "rial": "Rial",

        "conjunt": "Conj.",

        "avinguda": "Av.",

        "esplanada": "Esp.",

        "cantonada": "Cant.",

        "ronda": "Rda.",

        "corredor": "Cdor.",

        "drecera": "Drec.",

        "passad�s": "Pd�s.",

        "viaducte": "Vdct.",

        "passeig": "Pg.",

        "ve�nat": "Ve�."

    },

    "directions": {

        "sudest": "SE",

        "sudoest": "SO",

        "nordest": "NE",

        "nordoest": "NO",

        "est": "E",

        "nord": "N",

        "oest": "O",

        "sud": "S"

    }

}



},{}],7:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "skole": "Sk.",

        "ved": "v.",

        "centrum": "C.",

        "sankt": "Skt.",

        "vestre": "v.",

        "hospital": "Hosp.",

        "str�de": "Str.",

        "nordre": "Nr.",

        "plads": "Pl.",

        "universitet": "Uni.",

        "v�nge": "vg.",

        "station": "St."

    },

    "classifications": {

        "avenue": "Ave",

        "gammel": "Gl.",

        "dronning": "Dronn.",

        "s�nder": "Sdr.",

        "n�rre": "Nr.",

        "vester": "V.",

        "vestre": "V.",

        "�ster": "�.",

        "�stre": "�.",

        "boulevard": "Boul."

    },

    "directions": {

        "syd�st": "S�",

        "nordvest": "NV",

        "syd": "S",

        "nord�st": "N�",

        "sydvest": "SV",

        "vest": "V",

        "nord": "N",

        "�st": "�"

    }

}



},{}],8:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {},

    "classifications": {},

    "directions": {

        "osten": "O",

        "nordosten": "NO",

        "s�den": "S",

        "nordwest": "NW",

        "norden": "N",

        "s�dost": "SO",

        "s�dwest": "SW",

        "westen": "W"

    }

}



},{}],9:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "square": "Sq",

        "centre": "Ctr",

        "sister": "Sr",

        "lake": "Lk",

        "fort": "Ft",

        "route": "Rte",

        "william": "Wm",

        "national": "Nat'l",

        "junction": "Jct",

        "center": "Ctr",

        "saint": "St",

        "saints": "SS",

        "station": "Sta",

        "mount": "Mt",

        "junior": "Jr",

        "mountain": "Mtn",

        "heights": "Hts",

        "university": "Univ",

        "school": "Sch",

        "international": "Int'l",

        "apartments": "Apts",

        "crossing": "Xing",

        "creek": "Crk",

        "township": "Twp",

        "downtown": "Dtwn",

        "father": "Fr",

        "senior": "Sr",

        "point": "Pt",

        "river": "Riv",

        "market": "Mkt",

        "village": "Vil",

        "park": "Pk",

        "memorial": "Mem"

    },

    "classifications": {

        "place": "Pl",

        "circle": "Cir",

        "bypass": "Byp",

        "motorway": "Mwy",

        "crescent": "Cres",

        "road": "Rd",

        "cove": "Cv",

        "lane": "Ln",

        "square": "Sq",

        "street": "St",

        "freeway": "Fwy",

        "walk": "Wk",

        "plaza": "Plz",

        "parkway": "Pky",

        "avenue": "Ave",

        "pike": "Pk",

        "drive": "Dr",

        "highway": "Hwy",

        "footway": "Ftwy",

        "point": "Pt",

        "court": "Ct",

        "terrace": "Ter",

        "walkway": "Wky",

        "alley": "Aly",

        "expressway": "Expy",

        "bridge": "Br",

        "boulevard": "Blvd",

        "turnpike": "Tpk"

    },

    "directions": {

        "southeast": "SE",

        "northwest": "NW",

        "south": "S",

        "west": "W",

        "southwest": "SW",

        "north": "N",

        "east": "E",

        "northeast": "NE"

    }

}



},{}],10:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "segunda": "2�",

        "octubre": "8bre",

        "doctores": "Drs",

        "doctora": "Dra",

        "internacional": "Intl",

        "doctor": "Dr",

        "segundo": "2�",

        "se�orita": "Srta",

        "doctoras": "Drs",

        "primera": "1�",

        "primero": "1�",

        "san": "S",

        "colonia": "Col",

        "do�a": "D�a",

        "septiembre": "7bre",

        "diciembre": "10bre",

        "se�or": "Sr",

        "ayuntamiento": "Ayto",

        "se�ora": "Sra",

        "tercera": "3�",

        "tercero": "3�",

        "don": "D",

        "santa": "Sta",

        "ciudad": "Cdad",

        "noviembre": "9bre",

        "departamento": "Dep"

    },

    "classifications": {

        "camino": "Cmno",

        "avenida": "Av",

        "paseo": "P�",

        "autopista": "Auto",

        "calle": "C",

        "plaza": "Pza",

        "carretera": "Crta"

    },

    "directions": {

        "este": "E",

        "noreste": "NE",

        "sur": "S",

        "suroeste": "SO",

        "noroeste": "NO",

        "oeste": "O",

        "sureste": "SE",

        "norte": "N"

    }

}



},{}],11:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "all�e": "All",

        "a�rodrome": "A�rod",

        "a�roport": "A�rop"

    },

    "classifications": {

        "centrale": "Ctrale",

        "campings": "Camp.",

        "urbains": "Urb.",

        "mineure": "Min.",

        "publique": "Publ.",

        "sup�rieur": "Sup.",

        "f�d�ration": "F�d.",

        "notre-dame": "ND",

        "saint": "St",

        "centre hospitalier r�gional": "CHR",

        "exploitation": "Exploit.",

        "g�n�ral": "Gal",

        "civiles": "Civ.",

        "maritimes": "Marit.",

        "aviation": "Aviat.",

        "iii": "3",

        "arch�ologique": "Arch�o.",

        "musical": "Music.",

        "musicale": "Music.",

        "immeuble": "Imm.",

        "xv": "15",

        "h�tel": "H�t.",

        "alpine": "Alp.",

        "communale": "Commun.",

        "v": "5",

        "global": "Glob.",

        "universit�": "Univ.",

        "conf�d�ral": "Conf�d.",

        "xx": "20",

        "x": "10",

        "piscine": "Pisc.",

        "dimanche": "di.",

        "fleuve": "Flv",

        "postaux": "Post.",

        "musicienne": "Music.",

        "d�partement": "D�pt",

        "f�vrier": "F�vr.",

        "municipales": "Munic.",

        "province": "Prov.",

        "communaut�s": "Commt�s",

        "barrage": "Barr.",

        "mercredi": "me.",

        "pr�sidentes": "Pdtes",

        "caf�t�rias": "Caf�t.",

        "th��tral": "Th�.",

        "viticulteur": "Vitic.",

        "poste": "Post.",

        "sp�cialis�e": "Sp�c.",

        "agriculture": "Agric.",

        "infirmier": "Infirm.",

        "animation": "Anim.",

        "mondiale": "Mond.",

        "arr�t": "Arr.",

        "zone": "zon.",

        "municipaux": "Munic.",

        "grand": "Gd",

        "janvier": "Janv.",

        "fondateur": "Fond.",

        "premi�re": "1re",

        "municipale": "Munic.",

        "direction": "Dir.",

        "anonyme": "Anon.",

        "d�partementale": "D�pt",

        "moyens": "Moy.",

        "novembre": "Nov.",

        "jardin": "Jard.",

        "petites": "Pet.",

        "priv�": "Priv.",

        "centres": "Ctres",

        "forestier": "Forest.",

        "xiv": "14",

        "africaines": "Afric.",

        "sergent": "Sgt",

        "europ�enne": "Eur.",

        "priv�e": "Priv.",

        "caf�": "Cf�",

        "xix": "19",

        "hautes": "Htes",

        "major": "Mjr",

        "vendredi": "ve.",

        "municipalit�": "Munic.",

        "sous-pr�fecture": "Ss-pr�f.",

        "sp�ciales": "Sp�c.",

        "secondaires": "Second.",

        "viie": "7e",

        "moyenne": "Moy.",

        "commerciale": "Commerc.",

        "r�gion": "R�g.",

        "am�ricaines": "Am�r.",

        "am�ricains": "Am�r.",

        "service": "Sce",

        "professeur": "Prof.",

        "d�partemental": "D�pt",

        "h�tels": "H�t.",

        "mondiales": "Mond.",

        "ire": "1re",

        "caporal": "Capo.",

        "militaire": "Milit.",

        "lyc�e d'enseignement professionnel": "LEP",

        "adjudant": "Adj.",

        "m�dicale": "M�d.",

        "conf�rences": "Conf�r.",

        "universelle": "Univ.",

        "xiie": "12e",

        "sup�rieures": "Sup.",

        "naturel": "Natur.",

        "soci�t� nationale": "SN",

        "hospitalier": "Hosp.",

        "culturelle": "Cult.",

        "am�ricain": "Am�r.",

        "son altesse royale": "S.A.R.",

        "infirmi�re": "Infirm.",

        "viii": "8",

        "fondatrice": "Fond.",

        "madame": "Mme",

        "m�tropolitain": "M�trop.",

        "ophtalmologues": "Ophtalmos",

        "xviie": "18e",

        "viiie": "8e",

        "commer�ante": "Commer�.",

        "centre d'enseignement du second degr�": "CES",

        "septembre": "Sept.",

        "agriculteur": "Agric.",

        "xiii": "13",

        "pontifical": "Pontif.",

        "caf�t�ria": "Caf�t.",

        "prince": "Pce",

        "vie": "6e",

        "archiduchesse": "Archid.",

        "occidental": "Occ.",

        "spectacles": "Spect.",

        "camping": "Camp.",

        "m�tro": "M�",

        "arrondissement": "Arrond.",

        "viticole": "Vitic.",

        "ii": "2",

        "si�cle": "Si.",

        "chapelles": "Chap.",

        "centre": "Ctre",

        "sapeur-pompiers": "Sap.-pomp.",

        "�tablissements": "�tabts",

        "soci�t� anonyme": "SA",

        "directeurs": "Dir.",

        "vii": "7",

        "culturel": "Cult.",

        "central": "Ctral",

        "m�tropolitaine": "M�trop.",

        "administrations": "Admin.",

        "amiraux": "Amir.",

        "sur": "s/",

        "premiers": "1ers",

        "provence-alpes-c�te d'azur": "PACA",

        "cath�drale": "Cath�d.",

        "iv": "4",

        "postale": "Post.",

        "social": "Soc.",

        "sp�cialis�": "Sp�c.",

        "district": "Distr.",

        "technologique": "Techno.",

        "viticoles": "Vitic.",

        "ix": "9",

        "prot�g�s": "Prot.",

        "historiques": "Hist.",

        "sous": "s/s",

        "national": "Nal",

        "ambassade": "Amb.",

        "caf�s": "Cf�s",

        "agronomie": "Agro.",

        "sapeurs": "Sap.",

        "petits": "Pet.",

        "monsieur": "M.",

        "boucher": "Bouch.",

        "restaurant": "Restau.",

        "lyc�e": "Lyc.",

        "urbaine": "Urb.",

        "pr�fecture": "Pr�f.",

        "districts": "Distr.",

        "civil": "Civ.",

        "prot�g�es": "Prot.",

        "sapeur": "Sap.",

        "th��tre": "Th�.",

        "coll�ge": "Coll.",

        "mardi": "ma.",

        "m�morial": "M�mor.",

        "africain": "Afric.",

        "r�publicaine": "R�publ.",

        "sociale": "Soc.",

        "sp�cial": "Sp�c.",

        "technologie": "Techno.",

        "charcuterie": "Charc.",

        "commerces": "Commerc.",

        "fluviale": "Flv",

        "parachutistes": "Para.",

        "primaires": "Prim.",

        "directions": "Dir.",

        "pr�sidentiel": "Pdtl",

        "nationales": "Nales",

        "apr�s": "apr.",

        "samedi": "sa.",

        "unit�": "U.",

        "xxiii": "23",

        "associ�": "Assoc.",

        "�lectrique": "�lectr.",

        "populaire": "Pop.",

        "asiatique": "Asiat.",

        "navigable": "Navig.",

        "pr�sidente": "Pdte",

        "xive": "14e",

        "associ�s": "Assoc.",

        "pompiers": "Pomp.",

        "agricoles": "Agric.",

        "�l�m": "�l�m.",

        "d�cembre": "D�c.",

        "son altesse": "S.Alt.",

        "apr�s-midi": "a.-m.",

        "mineures": "Min.",

        "juillet": "Juil.",

        "aviatrices": "Aviat.",

        "fondation": "Fond.",

        "pontificaux": "Pontif.",

        "temple": "Tple",

        "europ�ennes": "Eur.",

        "r�gionale": "R�g.",

        "informations": "Infos",

        "mondiaux": "Mond.",

        "infanterie": "Infant.",

        "arch�ologie": "Arch�o.",

        "dans": "d/",

        "hospice": "Hosp.",

        "spectacle": "Spect.",

        "h�tels-restaurants": "H�t.-Rest.",

        "h�tel-restaurant": "H�t.-Rest.",

        "h�licopt�re": "h�lico",

        "xixe": "19e",

        "cliniques": "Clin.",

        "docteur": "Dr",

        "secondaire": "Second.",

        "municipal": "Munic.",

        "g�n�rale": "Gale",

        "ch�teau": "Ch�t.",

        "commer�ant": "Commer�.",

        "avril": "Avr.",

        "clinique": "Clin.",

        "urbaines": "Urb.",

        "navale": "Nav.",

        "navigation": "Navig.",

        "asiatiques": "Asiat.",

        "pontificales": "Pontif.",

        "administrative": "Admin.",

        "syndicat": "Synd.",

        "lundi": "lu.",

        "petite": "Pet.",

        "maritime": "Marit.",

        "m�tros": "M�",

        "enseignement": "Enseign.",

        "fluviales": "Flv",

        "historique": "Hist.",

        "comt�s": "Ct�s",

        "r�sidentiel": "R�sid.",

        "international": "Int.",

        "sup�rieure": "Sup.",

        "centre hospitalier universitaire": "CHU",

        "conf�d�ration": "Conf�d.",

        "boucherie": "Bouch.",

        "fondatrices": "Fond.",

        "m�dicaux": "M�d.",

        "europ�ens": "Eur.",

        "orientaux": "Ori.",

        "naval": "Nav.",

        "�tang": "�tg",

        "provincial": "Prov.",

        "junior": "Jr",

        "d�partementales": "D�pt",

        "musique": "Musiq.",

        "directrices": "Dir.",

        "mar�chal": "Mal",

        "civils": "Civ.",

        "prot�g�": "Prot.",

        "�tablissement": "�tabt",

        "trafic": "Traf.",

        "aviateur": "Aviat.",

        "archives": "Arch.",

        "africains": "Afric.",

        "maternelle": "Matern.",

        "industrielle": "Ind.",

        "administratif": "Admin.",

        "oriental": "Ori.",

        "universitaire": "Univ.",

        "majeur": "Maj.",

        "haute": "Hte",

        "communal": "Commun.",

        "petit": "Pet.",

        "commune": "Commun.",

        "exploitant": "Exploit.",

        "conf�rence": "Conf�r.",

        "monseigneur": "Mgr",

        "pharmacien": "Pharm.",

        "jeudi": "je.",

        "primaire": "Prim.",

        "h�licopt�res": "h�licos",

        "agronomique": "Agro.",

        "m�decin": "M�d.",

        "ve": "5e",

        "pontificale": "Pontif.",

        "ier": "1er",

        "cin�ma": "Cin�",

        "fluvial": "Flv",

        "occidentaux": "Occ.",

        "commer�ants": "Commer�.",

        "banque": "Bq",

        "moyennes": "Moy.",

        "pharmacienne": "Pharm.",

        "d�mocratique": "D�m.",

        "cin�mas": "Cin�s",

        "sp�ciale": "Sp�c.",

        "pr�sidents": "Pdts",

        "directrice": "Dir.",

        "vi": "6",

        "basse": "Bas.",

        "xve": "15e",

        "�tat": "�.",

        "aviateurs": "Aviat.",

        "majeurs": "Maj.",

        "infirmiers": "Infirm.",

        "�glise": "�gl.",

        "conf�d�rale": "Conf�d.",

        "xxie": "21e",

        "comte": "Cte",

        "europ�en": "Eur.",

        "union": "U.",

        "pharmacie": "Pharm.",

        "infirmi�res": "Infirm.",

        "comt�": "Ct�",

        "sportive": "Sport.",

        "deuxi�me": "2e",

        "xvi": "17",

        "haut": "Ht",

        "m�dicales": "M�d.",

        "d�velopp�": "D�velop.",

        "b�timent": "B�t.",

        "commerce": "Commerc.",

        "ive": "4e",

        "associatif": "Assoc.",

        "rural": "Rur.",

        "cimeti�re": "Cim.",

        "r�gional": "R�g.",

        "ferroviaire": "Ferr.",

        "vers": "v/",

        "mosqu�e": "Mosq.",

        "mineurs": "Min.",

        "nautique": "Naut.",

        "ch�teaux": "Ch�t.",

        "sportif": "Sport.",

        "mademoiselle": "Mle",

        "�cole": "�c.",

        "doyen": "Doy.",

        "industriel": "Ind.",

        "chapelle": "Chap.",

        "soci�t�s": "St�s",

        "internationale": "Int.",

        "coop�ratif": "Coop.",

        "hospices": "Hosp.",

        "xxii": "22",

        "parachutiste": "Para.",

        "alpines": "Alp.",

        "civile": "Civ.",

        "xvie": "17e",

        "�tats": "�.",

        "mus�e": "Ms�e",

        "centrales": "Ctrales",

        "globaux": "Glob.",

        "sup�rieurs": "Sup.",

        "syndicats": "Synd.",

        "archev�que": "Archev.",

        "docteurs": "Drs",

        "biblioth�que": "Biblio.",

        "lieutenant": "Lieut.",

        "r�publique": "R�p.",

        "v�t�rinaire": "V�t.",

        "d�partementaux": "D�pt",

        "premier": "1er",

        "fluviaux": "Flv",

        "anim�": "Anim.",

        "orientales": "Ori.",

        "technologiques": "Techno.",

        "princesse": "Pse",

        "routi�re": "Rout.",

        "coop�rative": "Coop.",

        "scolaire": "Scol.",

        "�coles": "�c.",

        "football": "Foot",

        "territoriale": "Territ.",

        "commercial": "Commerc.",

        "mineur": "Min.",

        "mill�naires": "Mill.",

        "association": "Assoc.",

        "catholique": "Cathol.",

        "administration": "Admin.",

        "mairie": "Mair.",

        "portuaire": "Port.",

        "tertiaires": "Terti.",

        "th��trale": "Th�.",

        "palais": "Pal.",

        "troisi�me": "3e",

        "directeur": "Dir.",

        "v�t�rinaires": "V�t.",

        "facult�": "Fac.",

        "occidentales": "Occ.",

        "viticulteurs": "Vitic.",

        "xvii": "18",

        "occidentale": "Occ.",

        "amiral": "Amir.",

        "professionnel": "Profess.",

        "administratives": "Admin.",

        "commerciales": "Commerc.",

        "saints": "Sts",

        "agronomes": "Agro.",

        "stade": "Std",

        "sous-pr�fet": "Ss-pr�f.",

        "senior": "Sr",

        "agronome": "Agro.",

        "terrain": "Terr.",

        "catholiques": "Cathol.",

        "r�sidentielle": "R�sid.",

        "grands": "Gds",

        "exploitants": "Exploit.",

        "xiiie": "13e",

        "croix": "Cx",

        "g�n�raux": "Gaux",

        "cr�dit": "Cr�d.",

        "cimeti�res": "Cim.",

        "antenne": "Ant.",

        "m�dical": "M�d.",

        "coll�ges": "Coll.",

        "musicien": "Music.",

        "apostolique": "Apost.",

        "postal": "Post.",

        "territorial": "Territ.",

        "urbanisme": "Urb.",

        "pr�fectorale": "Pr�f.",

        "fondateurs": "Fond.",

        "information": "Info.",

        "�glises": "�gl.",

        "ophtalmologue": "Ophtalmo",

        "congr�gation": "Congr�g.",

        "charcutier": "Charc.",

        "�tage": "�t.",

        "consulat": "Consul.",

        "public": "Publ.",

        "ferr�e": "Ferr.",

        "matin": "mat.",

        "soci�t� anonyme � responsabilit� limit�e": "SARL",

        "monuments": "Mmts",

        "protection": "Prot.",

        "universel": "Univ.",

        "nationale": "Nale",

        "pr�sident": "Pdt",

        "provinciale": "Prov.",

        "agriculteurs": "Agric.",

        "pr�fectoral": "Pr�f.",

        "xxe": "20e",

        "alpins": "Alp.",

        "avant": "av.",

        "infirmerie": "Infirm.",

        "deux mil": "2000",

        "rurale": "Rur.",

        "administratifs": "Admin.",

        "octobre": "Oct.",

        "archipel": "Archip.",

        "communaut�": "Commt�",

        "globales": "Glob.",

        "alpin": "Alp.",

        "num�ros": "N�?",

        "lieutenant-colonel": "Lieut.-Col.",

        "j�sus-christ": "J.-C.",

        "agricole": "Agric.",

        "sa majest�": "S.Maj.",

        "associative": "Assoc.",

        "xxi": "21",

        "pr�sidentielle": "Pdtle",

        "moyen": "Moy.",

        "f�d�ral": "F�d.",

        "professionnelle": "Profess.",

        "tertiaire": "Terti.",

        "ixe": "9e",

        "h�pital": "H�p.",

        "technologies": "Techno.",

        "iiie": "3e",

        "d�veloppement": "D�velop.",

        "monument": "Mmt",

        "foresti�re": "Forest.",

        "num�ro": "N�",

        "viticulture": "Vitic.",

        "traversi�re": "Traver.",

        "technique": "Tech.",

        "�lectriques": "�lectr.",

        "militaires": "Milit.",

        "pompier": "Pomp.",

        "am�ricaine": "Am�r.",

        "pr�fet": "Pr�f.",

        "congr�gations": "Congr�g.",

        "p�tissier": "P�tiss.",

        "mondial": "Mond.",

        "ophtalmologie": "Ophtalm.",

        "sainte": "Ste",

        "africaine": "Afric.",

        "aviatrice": "Aviat.",

        "doyens": "Doy.",

        "soci�t�": "St�",

        "majeures": "Maj.",

        "orientale": "Ori.",

        "minist�re": "Min.",

        "archiduc": "Archid.",

        "territoire": "Territ.",

        "techniques": "Tech.",

        "�le-de-france": "IDF",

        "globale": "Glob.",

        "xe": "10e",

        "xie": "11e",

        "majeure": "Maj.",

        "commerciaux": "Commerc.",

        "maire": "Mair.",

        "sp�ciaux": "Sp�c.",

        "grande": "Gde",

        "messieurs": "MM",

        "colonel": "Col.",

        "mill�naire": "Mill.",

        "xi": "11",

        "urbain": "Urb.",

        "f�d�rale": "F�d.",

        "ferr�": "Ferr.",

        "rivi�re": "Riv.",

        "r�publicain": "R�publ.",

        "grandes": "Gdes",

        "r�giment": "R�gim.",

        "hauts": "Hts",

        "cat�gorie": "Cat�g.",

        "basses": "Bas.",

        "xii": "12",

        "agronomiques": "Agro.",

        "iie": "2e",

        "prot�g�e": "Prot.",

        "sapeur-pompier": "Sap.-pomp."

    },

    "directions": {

        "est-nord-est": "ENE",

        "nord-est": "NE",

        "ouest": "O",

        "sud-est": "SE",

        "est-sud-est": "ESE",

        "nord-nord-est": "NNE",

        "sud": "S",

        "nord-nord-ouest": "NNO",

        "nord-ouest": "NO",

        "nord": "N",

        "ouest-sud-ouest": "OSO",

        "ouest-nord-ouest": "ONO",

        "sud-ouest": "SO",

        "sud-sud-est": "SSE",

        "sud-sud-ouest": "SSO",

        "est": "E"

    }

}



},{}],12:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "?????": "??'"

    },

    "classifications": {},

    "directions": {}

}



},{}],13:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {},

    "classifications": {},

    "directions": {

        "kelet": "K",

        "�szakkelet": "�K",

        "d�l": "D",

        "�szaknyugat": "�NY",

        "�szak": "�",

        "d�lkelet": "DK",

        "d�lnyugat": "DNY",

        "nyugat": "NY"

    }

}



},{}],14:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "apartamentai": "Apt",

        "aukstumos": "Auks",

        "centras": "Ctr",

        "ezeras": "Ez",

        "fortas": "Ft",

        "greitkelis": "Grtkl",

        "juosta": "Jst",

        "kaimas": "Km",

        "kalnas": "Kln",

        "kelias": "Kl",

        "kiemelis": "Kml",

        "miestelis": "Mstl",

        "miesto centras": "M.Ctr",

        "mokykla": "Mok",

        "nacionalinis": "Nac",

        "paminklas": "Pmkl",

        "parkas": "Pk",

        "pusratis": "Psrt",

        "sankryza": "Skrz",

        "sese": "Sese",

        "skveras": "Skv",

        "stotis": "St",

        "sv": "Sv",

        "tarptautinis": "Trptaut",

        "taskas": "Tsk",

        "tevas": "Tev",

        "turgus": "Tgs",

        "universitetas": "Univ",

        "upe": "Up",

        "upelis": "Up",

        "vieta": "Vt"

    },

    "classifications": {

        "aikste": "a.",

        "aleja": "al.",

        "aplinkkelis": "aplinkl.",

        "autostrada": "auto.",

        "bulvaras": "b.",

        "gatve": "g.",

        "kelias": "kel.",

        "krantine": "krant.",

        "prospektas": "pr.",

        "plentas": "pl.",

        "skersgatvis": "skg.",

        "takas": "tak.",

        "tiltas": "tlt."

    },

    "directions": {

        "pietus": "P",

        "vakarai": "V",

        "siaure": "S",

        "siaures vakarai": "SV",

        "pietryciai": "PR",

        "siaures rytai": "SR",

        "rytai": "R",

        "pietvakariai": "PV"

    }

}



},{}],15:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "centrum": "Cntrm",

        "nationaal": "Nat'l",

        "berg": "Brg",

        "meer": "Mr",

        "kruising": "Krsng",

        "toetreden": "Ttrdn"

    },

    "classifications": {

        "bypass": "Pass",

        "brug": "Br",

        "straat": "Str",

        "rechtbank": "Rbank",

        "snoek": "Snk",

        "autobaan": "Baan",

        "terras": "Trrs",

        "punt": "Pt",

        "plaza": "Plz",

        "rijden": "Rijd",

        "parkway": "Pky",

        "inham": "Nham",

        "snelweg": "Weg",

        "halve maan": "Maan",

        "cirkel": "Crkl",

        "laan": "Ln",

        "rijbaan": "Strook",

        "weg": "Weg",

        "lopen": "Lpn",

        "autoweg": "Weg",

        "boulevard": "Blvd",

        "plaats": "Plts",

        "steeg": "Stg",

        "voetpad": "Stoep"

    },

    "directions": {

        "noordoost": "NO",

        "westen": "W",

        "zuiden": "Z",

        "zuidwest": "ZW",

        "oost": "O",

        "zuidoost": "ZO",

        "noordwest": "NW",

        "noorden": "N"

    }

}



},{}],16:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "????????": "??.",

        "?????????": "???.",

        "??????????????": "???",

        "??????????????": "???.",

        "????????": "??.",

        "?????": "?.",

        "???????": "?.",

        "?????": "??.",

        "????????":"??.",

        "?????????": "???.",

        "???????": "???.",

        "????????": "??.",

        "?????": "?.",

        "???????": "?.",

        "????????????":  "???.",

        "???????????": "????.",

        "????": "?.",

        "??????????": "????.",

        "?????????": "???.",

        "????????????????": "????.",

        "?????????????????": "?????.",

        "???????": "??.",

        "???????": "??."

    },

    "classifications": {

        "??????": "??-?",

        "????????": "??.",

        "????????": "???.",

        "??????????": "???.",

        "???????": "??.",

        "?????": "?.",

        "???????": "?.",

        "?????": "???.",

        "?????": "??."

    },

    "directions": {

        "??????": "?",

        "??????-??????": "??",

        "???-??????": "??",

        "???-?????": "??",

        "??????-?????": "??",

        "?????": "?",

        "?????": "?",

        "??": "?"

    }

}



},{}],17:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {},

    "classifications": {},

    "directions": {

        "vzhod": "V",

        "severovzhod": "SV",

        "jug": "J",

        "severozahod": "SZ",

        "sever": "S",

        "jugovzhod": "JV",

        "jugozahod": "JZ",

        "zahod": "Z"

    }

}



},{}],18:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "sankta": "s:ta",

        "gamla": "G:la",

        "sankt": "s:t"

    },

    "classifications": {

        "Bro": "Br"

    },

    "directions": {

        "norr": "N",

        "syd�st": "SO",

        "v�ster": "V",

        "�ster": "O",

        "nordv�st": "NV",

        "sydv�st": "SV",

        "s�der": "S",

        "nord�st": "NO"

    }

}



},{}],19:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {},

    "classifications": {},

    "directions": {

        "????": "??",

        "????????? ????": "????",

        "???????": "??",

        "????????? ?????": "????",

        "??????": "??",

        "????????? ????": "????",

        "????????? ?????": "????",

        "?????": "??"

    }

}



},{}],20:[function(_dereq_,module,exports){

module.exports={

    "abbreviations": {

        "vi?n b?o t�ng": "VBT",

        "th? tr?n": "Tt",

        "d?i h?c": "DH",

        "can c? kh�ng quan": "CCKQ",

        "c�u l?c b?": "CLB",

        "buu di?n": "BD",

        "kh�ch s?n": "KS",

        "khu du l?ch": "KDL",

        "khu c�ng nghi?p": "KCN",

        "khu ngh? m�t": "KNM",

        "th? x�": "Tx",

        "khu chung cu": "KCC",

        "phi tru?ng": "PT",

        "trung t�m": "TT",

        "t?ng c�ng ty": "TCty",

        "trung h?c co s?": "THCS",

        "s�n bay qu?c t?": "SBQT",

        "trung h?c ph? th�ng": "THPT",

        "cao d?ng": "CD",

        "c�ng ty": "Cty",

        "s�n bay": "SB",

        "th�nh ph?": "Tp",

        "c�ng vi�n": "CV",

        "s�n v?n d?ng": "SVD",

        "linh m?c": "LM",

        "vu?n qu?c gia": "VQG"

    },

    "classifications": {

        "huy?n l?": "HL",

        "du?ng t?nh": "DT",

        "qu?c l?": "QL",

        "xa l?": "XL",

        "huong l?": "HL",

        "t?nh l?": "TL",

        "du?ng huy?n": "DH",

        "du?ng cao t?c": "DCT",

        "d?i l?": "DL",

        "vi?t nam": "VN",

        "qu?ng tru?ng": "QT",

        "du?ng b?": "DB"

    },

    "directions": {

        "t�y": "T",

        "nam": "N",

        "d�ng nam": "DN",

        "d�ng b?c": "DB",

        "t�y nam": "TN",

        "d�ng": "D",

        "b?c": "B"

    }

}



},{}],21:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "regExpFlags": "gi"

    },

    "v5": {

        "article": [

            ["^ Acc[�e]s ", " l'acc�s "],

            ["^ Aire ", " l'aire "],

            ["^ All[�e]e ", " l'all�e "],

            ["^ Anse ", " l'anse "],

            ["^ (L[''])?Autoroute ", " l'autoroute "],

            ["^ Avenue ", " l'avenue "],

            ["^ Barreau ", " le barreau "],

            ["^ Boulevard ", " le boulevard "],

            ["^ Chemin ", " le chemin "],

            ["^ Petit[\\- ]Chemin ", " le petit chemin "],

            ["^ Cit[�e] ", " la cit� "],

            ["^ Clos ", " le clos "],

            ["^ Corniche ", " la corniche "],

            ["^ Cour ", " la cour "],

            ["^ Cours ", " le cours "],

            ["^ D[�e]viation ", " la d�viation "],

            ["^ Entr[�e]e ", " l'entr�e "],

            ["^ Esplanade ", " l'esplanade "],

            ["^ Galerie ", " la galerie "],

            ["^ Impasse ", " l'impasse "],

            ["^ Lotissement ", " le lotissement "],

            ["^ Mont[�e]e ", " la mont�e "],

            ["^ Parc ", " le parc "],

            ["^ Parvis ", " le parvis "],

            ["^ Passage ", " le passage "],

            ["^ Place ", " la place "],

            ["^ Petit[\\- ]Pont ", " le petit-pont "],

            ["^ Pont ", " le pont "],

            ["^ Promenade ", " la promenade "],

            ["^ Quai ", " le quai "],

            ["^ Rocade ", " la rocade "],

            ["^ Rond[\\- ]?Point ", " le rond-point "],

            ["^ Route ", " la route "],

            ["^ Rue ", " la rue "],

            ["^ Grande Rue ", " la grande rue "],

            ["^ Sente ", " la sente "],

            ["^ Sentier ", " le sentier "],

            ["^ Sortie ", " la sortie "],

            ["^ Souterrain ", " le souterrain "],

            ["^ Square ", " le square "],

            ["^ Terrasse ", " la terrasse "],

            ["^ Traverse ", " la traverse "],

            ["^ Tunnel ", " le tunnel "],

            ["^ Viaduc ", " le viaduc "],

            ["^ Villa ", " la villa "],

            ["^ Village ", " le village "],

            ["^ Voie ", " la voie "],



            [" ([dl])'", " $1'"]

        ],

        "preposition": [

            ["^ Le ", "  du "],

            ["^ Les ", "  des "],

            ["^ La ", "  de La "],



            ["^ Acc[�e]s ", "  de l'acc�s "],

            ["^ Aire ", "  de l'aire "],

            ["^ All[�e]e ", "  de l'all�e "],

            ["^ Anse ", "  de l'anse "],

            ["^ (L[''])?Autoroute ", "  de l'autoroute "],

            ["^ Avenue ", "  de l'avenue "],

            ["^ Barreau ", "  du barreau "],

            ["^ Boulevard ", "  du boulevard "],

            ["^ Chemin ", "  du chemin "],

            ["^ Petit[\\- ]Chemin ", "  du petit chemin "],

            ["^ Cit[�e] ", "  de la cit� "],

            ["^ Clos ", "  du clos "],

            ["^ Corniche ", "  de la corniche "],

            ["^ Cour ", "  de la cour "],

            ["^ Cours ", "  du cours "],

            ["^ D[�e]viation ", "  de la d�viation "],

            ["^ Entr[�e]e ", "  de l'entr�e "],

            ["^ Esplanade ", "  de l'esplanade "],

            ["^ Galerie ", "  de la galerie "],

            ["^ Impasse ", "  de l'impasse "],

            ["^ Lotissement ", "  du lotissement "],

            ["^ Mont[�e]e ", "  de la mont�e "],

            ["^ Parc ", "  du parc "],

            ["^ Parvis ", "  du parvis "],

            ["^ Passage ", "  du passage "],

            ["^ Place ", "  de la place "],

            ["^ Petit[\\- ]Pont ", "  du petit-pont "],

            ["^ Pont ", "  du pont "],

            ["^ Promenade ", "  de la promenade "],

            ["^ Quai ", "  du quai "],

            ["^ Rocade ", "  de la rocade "],

            ["^ Rond[\\- ]?Point ", "  du rond-point "],

            ["^ Route ", "  de la route "],

            ["^ Rue ", "  de la rue "],

            ["^ Grande Rue ", "  de la grande rue "],

            ["^ Sente ", "  de la sente "],

            ["^ Sentier ", "  du sentier "],

            ["^ Sortie ", "  de la sortie "],

            ["^ Souterrain ", "  du souterrain "],

            ["^ Square ", "  du square "],

            ["^ Terrasse ", "  de la terrasse "],

            ["^ Traverse ", "  de la traverse "],

            ["^ Tunnel ", "  du tunnel "],

            ["^ Viaduc ", "  du viaduc "],

            ["^ Villa ", "  de la villa "],

            ["^ Village ", "  du village "],

            ["^ Voie ", "  de la voie "],



            ["^ ([A��E����I��O�U���YY�O])", "  d'$1"],

            ["^ (\\S)", "  de $1"],

            [" ([dl])'", " $1'"]

        ],

        "rotary": [

            ["^ Le ", "  le rond-point du "],

            ["^ Les ", "  le rond-point des "],

            ["^ La ", "  le rond-point de La "],



            ["^ Acc[�e]s ", " le rond-point de l'acc�s "],

            ["^ Aire ", "  le rond-point de l'aire "],

            ["^ All[�e]e ", "  le rond-point de l'all�e "],

            ["^ Anse ", "  le rond-point de l'anse "],

            ["^ (L[''])?Autoroute ", "  le rond-point de l'autoroute "],

            ["^ Avenue ", "  le rond-point de l'avenue "],

            ["^ Barreau ", "  le rond-point du barreau "],

            ["^ Boulevard ", "  le rond-point du boulevard "],

            ["^ Chemin ", "  le rond-point du chemin "],

            ["^ Petit[\\- ]Chemin ", "  le rond-point du petit chemin "],

            ["^ Cit[�e] ", "  le rond-point de la cit� "],

            ["^ Clos ", "  le rond-point du clos "],

            ["^ Corniche ", "  le rond-point de la corniche "],

            ["^ Cour ", "  le rond-point de la cour "],

            ["^ Cours ", "  le rond-point du cours "],

            ["^ D[�e]viation ", "  le rond-point de la d�viation "],

            ["^ Entr[�e]e ", "  le rond-point de l'entr�e "],

            ["^ Esplanade ", "  le rond-point de l'esplanade "],

            ["^ Galerie ", "  le rond-point de la galerie "],

            ["^ Impasse ", "  le rond-point de l'impasse "],

            ["^ Lotissement ", "  le rond-point du lotissement "],

            ["^ Mont[�e]e ", "  le rond-point de la mont�e "],

            ["^ Parc ", "  le rond-point du parc "],

            ["^ Parvis ", "  le rond-point du parvis "],

            ["^ Passage ", "  le rond-point du passage "],

            ["^ Place ", "  le rond-point de la place "],

            ["^ Petit[\\- ]Pont ", "  le rond-point du petit-pont "],

            ["^ Pont ", "  le rond-point du pont "],

            ["^ Promenade ", "  le rond-point de la promenade "],

            ["^ Quai ", "  le rond-point du quai "],

            ["^ Rocade ", "  le rond-point de la rocade "],

            ["^ Rond[\\- ]?Point ", "  le rond-point "],

            ["^ Route ", "  le rond-point de la route "],

            ["^ Rue ", "  le rond-point de la rue "],

            ["^ Grande Rue ", "  le rond-point de la grande rue "],

            ["^ Sente ", "  le rond-point de la sente "],

            ["^ Sentier ", "  le rond-point du sentier "],

            ["^ Sortie ", "  le rond-point de la sortie "],

            ["^ Souterrain ", "  le rond-point du souterrain "],

            ["^ Square ", "  le rond-point du square "],

            ["^ Terrasse ", "  le rond-point de la terrasse "],

            ["^ Traverse ", "  le rond-point de la traverse "],

            ["^ Tunnel ", "  le rond-point du tunnel "],

            ["^ Viaduc ", "  le rond-point du viaduc "],

            ["^ Villa ", "  le rond-point de la villa "],

            ["^ Village ", "  le rond-point du village "],

            ["^ Voie ", "  le rond-point de la voie "],



            ["^ ([A��E����I��O�U���YY�O])", "  le rond-point d'$1"],

            ["^ (\\S)", "  le rond-point de $1"],

            [" ([dl])'", " $1'"]

        ],

        "arrival": [

            ["^ Le ", "  au "],

            ["^ Les ", "  aux "],

            ["^ La ", "  � La "],

            ["^ (\\S)", "  � $1"],



            [" ([dl])'", " $1'"]

        ]

    }

}



},{}],22:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "regExpFlags": ""

    },

    "v5": {

        "accusative": [

            ["^ ([�\"])", " ?????? $1"],



            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\S+)??-(\\S+)?? [??]???? ", " $1??-$2?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ (\\S+)?? (\\S+)?? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+[??])? [??]???? ", " $1? ????? "],

            ["^ (\\S+)?? (\\S+[??])? [??]???? ", " $1?? $2? ????? "],

            ["^ ?????????? [??]???? ", " ?????????? ????? "],

            ["^ ??????? [??]???? ", " ??????? ????? "],

            ["^ ???????? [??]???? ", " ???????? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+[??])? [??]?????? ", " $1? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? [??]?????? ", " $1-? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? (\\S+)?? [??]????????? ", " $1?? $2?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????? ", " $1-? $2?? ?????? "],

            ["^ [??]????? ", " ?????? "],



            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+)?? ([??]???)?[??]????? ", " $1-? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],



            ["^ [??]???[??]? ", " ?????? "]

        ],

        "dative": [

            ["^ ([�\"])", " ?????? $1"],



            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\S+)??-(\\S+)?? [??]???? ", " $1??-$2?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ (\\S+)?? (\\S+)?? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+[??])? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ ?????????? [??]???? ", " ??????????? ????? "],

            ["^ ??????? [??]???? ", " ???????? ????? "],

            ["^ ???????? [??]???? ", " ????????? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ?????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+[??])? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? [??]?????? ", " $1-? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? (\\S+)?? [??]????????? ", " $1?? $2?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1??? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????? ", " $1-? $2?? ?????? "],

            ["^ [??]????? ", " ?????? "],



            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1??? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+)?? ([??]???)?[??]????? ", " $1-? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1??? ??????? "],



            ["^ (\\S+?)?? [??]?????? ", " $1??? ???????? "],

            ["^ (\\S+)[???]? [??]?????? ", " $1??? ???????? "],

            ["^ (\\S+[??]?) [??]?????? ", " $1? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]?????? ", " $1??? $2? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]?????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]?????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]?????? ", " $1-?? $2? ???????? "],

            ["^ [??]?????? ", " ???????? "],



            ["^ [??]???[??]? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1??? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1??? ????? "],

            ["^ (\\S+[???]?) [??]??? ", " $1? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??? ", " $1??? $2? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1??? $2? ????? "],

            ["^ (\\d+)-? [??]??? ", " $1-?? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??? ", " $1-?? $2? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-?? $2? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1??? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1??? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1??? $2? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-?? $2? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1???-$2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1-?? $2???-$3??? ???????? "],

            ["^ (\\S+?)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+[???]?) [??]??????? ", " $1? ???????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??????? ", " $1??? $2? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1??? $2? ???????? "],

            ["^ (\\d+)-? [??]??????? ", " $1-?? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??????? ", " $1-?? $2? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-?? $2? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ [??]?????? ", " ???????? "],



            ["^ (\\S+[???]?)-(\\S+)[???]? [??]????? ", " $1?-$2??? ??????? "],

            ["^ (\\S+?)?? [??]????? ", " $1??? ??????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1??? ??????? "],

            ["^ (\\S+[???]?) [??]????? ", " $1? ??????? "],

            ["^ (\\S+[??]?) [??]????? ", " $1? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]????? ", " $1??? $2? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????? ", " $1??? $2? ??????? "],

            ["^ (\\d+)-? [??]????? ", " $1-?? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]????? ", " $1-?? $2? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????? ", " $1-?? $2? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? (\\S+)[???]? [??]????? ", " $1-?? $2??? $3??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1-?? $2??? $3??? ??????? "],

            ["^ [??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]??????? ", " $1??? ????????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1??? ????????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1? ????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1??? $2? ????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-?? $2??? ????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-?? $2??? ????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-?? $2? ????????? "],

            ["^ [??]??????? ", " ????????? "],



            ["^ (\\S+?)?? [??]????????? ", " $1??? ??????????? "],

            ["^ (\\S+)[???]? [??]????????? ", " $1??? ??????????? "],

            ["^ (\\S+[??]?) [??]????????? ", " $1? ??????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????????? ", " $1??? $2? ??????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????????? ", " $1-?? $2??? ??????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????????? ", " $1-?? $2??? ??????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????????? ", " $1-?? $2? ??????????? "],

            ["^ [??]????????? ", " ??????????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??][??]????? ", " $1??? ??????? "],

            ["^ (\\S+)[???]? [??][??]????? ", " $1??? ??????? "],

            ["^ (\\S+[??]?) [??][??]????? ", " $1? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??][??]????? ", " $1??? $2? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??][??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??][??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??][??]????? ", " $1-?? $2? ??????? "],

            ["^ [??][??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? [??]???? ", " $1-?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+??) ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+[??])? (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\S+??) (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+[??])? ([??]???)?[??]????? ", " $1-?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+??) ([??]???)?[??]????? ", " $1-?? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+[??])? [??]???? ", " $1?? ????? "],

            ["^ (\\S+??) [??]???? ", " $1?? ????? "],

            ["^ (\\S+[??])? (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+??) (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\d+)-? (\\S+[??])? [??]???? ", " $1-?? $2?? ????? "],

            ["^ (\\d+)-? (\\S+??) [??]???? ", " $1-?? $2?? ????? "],



            [" ([??])?????? ", " $1??????? "],

            ["([??])??? ", "$1???? "],

            ["([??])?? ", "$1?? "]

        ],

        "genitive": [

            ["^ ([�\"])", " ?????? $1"],



            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\S+)??-(\\S+)?? [??]???? ", " $1??-$2?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ (\\S+)?? (\\S+)?? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+[??])? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ ?????????? [??]???? ", " ??????????? ????? "],

            ["^ ??????? [??]???? ", " ???????? ????? "],

            ["^ ???????? [??]???? ", " ????????? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+[??])? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? [??]?????? ", " $1-? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? (\\S+)?? [??]????????? ", " $1?? $2?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1??? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????? ", " $1-? $2?? ?????? "],

            ["^ [??]????? ", " ?????? "],



            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1??? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+)?? ([??]???)?[??]????? ", " $1-? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1??? ??????? "],



            ["^ (\\S+?)?? [??]?????? ", " $1??? ???????? "],

            ["^ (\\S+)[???]? [??]?????? ", " $1??? ???????? "],

            ["^ (\\S+[??]?) [??]?????? ", " $1??? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]?????? ", " $1??? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]?????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]?????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]?????? ", " $1-?? $2??? ???????? "],

            ["^ [??]?????? ", " ???????? "],



            ["^ [??]???[??]? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1??? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1??? ????? "],

            ["^ (\\S+[???]?) [??]??? ", " $1? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??? ", " $1??? $2? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1??? $2? ????? "],

            ["^ (\\d+)-? [??]??? ", " $1-?? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??? ", " $1-?? $2? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-?? $2? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1??? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1??? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1??? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1??? $2??? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-?? $2??? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-?? $2??? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1???-$2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1-?? $2???-$3??? ???????? "],

            ["^ (\\S+?)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+[???]?) [??]??????? ", " $1? ???????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1??? $2??? ???????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??????? ", " $1??? $2? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1??? $2? ???????? "],

            ["^ (\\d+)-? [??]??????? ", " $1-?? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-?? $2??? ???????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??????? ", " $1-?? $2? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-?? $2? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ [??]?????? ", " ???????? "],



            ["^ (\\S+[???]?)-(\\S+)[???]? [??]????? ", " $1?-$2??? ??????? "],

            ["^ (\\S+?)?? [??]????? ", " $1??? ??????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1??? ??????? "],

            ["^ (\\S+[???]?) [??]????? ", " $1? ??????? "],

            ["^ (\\S+[??]?) [??]????? ", " $1? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]????? ", " $1??? $2? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????? ", " $1??? $2? ??????? "],

            ["^ (\\d+)-? [??]????? ", " $1-?? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]????? ", " $1-?? $2? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????? ", " $1-?? $2? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? (\\S+)[???]? [??]????? ", " $1-?? $2??? $3??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1-?? $2??? $3??? ??????? "],

            ["^ [??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]??????? ", " $1??? ????????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1??? ????????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1??? ????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1??? $2??? ????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-?? $2??? ????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-?? $2??? ????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-?? $2??? ????????? "],

            ["^ [??]??????? ", " ????????? "],



            ["^ (\\S+?)?? [??]????????? ", " $1??? ??????????? "],

            ["^ (\\S+)[???]? [??]????????? ", " $1??? ??????????? "],

            ["^ (\\S+[??]?) [??]????????? ", " $1??? ??????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????????? ", " $1??? $2??? ??????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????????? ", " $1-?? $2??? ??????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????????? ", " $1-?? $2??? ??????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????????? ", " $1-?? $2??? ??????????? "],

            ["^ [??]????????? ", " ??????????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2??? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??][??]????? ", " $1??? ??????? "],

            ["^ (\\S+)[???]? [??][??]????? ", " $1??? ??????? "],

            ["^ (\\S+[??]?) [??][??]????? ", " $1??? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??][??]????? ", " $1??? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??][??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??][??]????? ", " $1-?? $2??? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??][??]????? ", " $1-?? $2??? ??????? "],

            ["^ [??][??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1??? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1??? $2??? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1??? $2? ?????? "],

            ["^ (\\d+)-? [??]???? ", " $1-?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-?? $2??? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-?? $2? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+??) ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+[??])? (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\S+??) (\\S+[??])? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+[??])? ([??]???)?[??]????? ", " $1-?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+??) ([??]???)?[??]????? ", " $1-?? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+[??])? [??]???? ", " $1?? ????? "],

            ["^ (\\S+??) [??]???? ", " $1?? ????? "],

            ["^ (\\S+[??])? (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+??) (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\d+)-? (\\S+[??])? [??]???? ", " $1-?? $2?? ????? "],

            ["^ (\\d+)-? (\\S+??) [??]???? ", " $1-?? $2?? ????? "],



            [" ([??])?????? ", " $1??????? "],

            ["([??])??? ", "$1???? "]

        ],

        "prepositional": [

            ["^ ([�\"])", " ?????? $1"],



            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\S+)??-(\\S+)?? [??]???? ", " $1??-$2?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ (\\S+)?? (\\S+)?? [??]???? ", " $1?? $2?? ????? "],

            ["^ (\\S+[??])? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? (\\S+[??])? [??]???? ", " $1?? $2?? ????? "],

            ["^ ?????????? [??]???? ", " ??????????? ????? "],

            ["^ ??????? [??]???? ", " ???????? ????? "],

            ["^ ???????? [??]???? ", " ????????? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-? [??]???? ", " $1-? ????? "],

            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1??? ????? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\d+)-? (\\S+)?? [??]???? ", " $1-? $2?? ????? "],

            ["^ [??]???? ", " ????? "],



            ["^ (\\d+)-(\\d+)-? [??]???? ", " $1-$2-? ?????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+[??])? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? [??]?????? ", " $1-? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1??? ?????????? "],

            ["^ (\\S+)?? [??]????????? ", " $1?? ?????????? "],

            ["^ (\\S+)?? (\\S+)?? [??]????????? ", " $1?? $2?? ?????????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????????? ", " $1-? $2?? ?????????? "],

            ["^ [??]????????? ", " ?????????? "],



            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1??? ???????? "],

            ["^ (\\S+)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\d+)-? (\\S+)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1??? ?????? "],

            ["^ (\\S+)?? [??]????? ", " $1?? ?????? "],

            ["^ (\\d+)-? (\\S+)?? [??]????? ", " $1-? $2?? ?????? "],

            ["^ [??]????? ", " ?????? "],



            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1??? $2?????? "],

            ["^ (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?????? "],

            ["^ (\\S+)?? (\\S+)?? ([??]???)?[??]????? ", " $1?? $2?? $3?????? "],

            ["^ (\\d+)-? (\\S+)?? ([??]???)?[??]????? ", " $1-? $2?? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1??? ??????? "],

            ["^ (\\S+)?? [??]?????? ", " $1?? ??????? "],

            ["^ (\\d+)-? (\\S+)?? [??]?????? ", " $1-? $2?? ??????? "],

            ["^ [??]?????? ", " ??????? "],



            ["^ (\\S+)?? [??]??? ", " $1??? ???? "],

            ["^ (\\S+)?? [??]??? ", " $1?? ???? "],

            ["^ (\\S+)?? [??]???? ", " $1?? ????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1?? ??????? "],



            ["^ (\\S+?)?? [??]?????? ", " $1?? ???????? "],

            ["^ (\\S+)[???]? [??]?????? ", " $1?? ???????? "],

            ["^ (\\S+[??]?) [??]?????? ", " $1?? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]?????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]?????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]?????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]?????? ", " $1?? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]?????? ", " $1-? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]?????? ", " $1-? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]?????? ", " $1-? $2?? ???????? "],

            ["^ [??]?????? ", " ???????? "],



            ["^ [??]???[??]? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1?? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1?? ????? "],

            ["^ (\\S+[???]?) [??]??? ", " $1?? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1?? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\d+)-? [??]??? ", " $1-? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-? $2?? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-? $2?? ????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??? ", " $1-? $2?? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-? $2?? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]??? ", " $1?? ????? "],

            ["^ (\\S+)[???]? [??]??? ", " $1?? ????? "],

            ["^ (\\S+[??]?) [??]??? ", " $1?? ????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??? ", " $1?? $2?? ????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??? ", " $1-? $2?? ????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??? ", " $1-? $2?? ????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??? ", " $1-? $2?? ????? "],

            ["^ [??]??? ", " ????? "],



            ["^ (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1??-$2?? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]?-(\\S+)[???]? [??]??????? ", " $1-? $2??-$3?? ???????? "],

            ["^ (\\S+?)?? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+[???]?) [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1?? ???????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]??????? ", " $1?? $2?? ???????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1?? $2?? ???????? "],

            ["^ (\\d+)-? [??]??????? ", " $1-? ???????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]??????? ", " $1-? $2?? ???????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-? $2?? ???????? "],

            ["^ [??]??????? ", " ???????? "],



            ["^ [??]?????? ", " ???????? "],



            ["^ (\\S+[???]?)-(\\S+)[???]? [??]????? ", " $1??-$2?? ??????? "],

            ["^ (\\S+?)?? [??]????? ", " $1?? ??????? "],

            ["^ (\\S+)[???]? [??]????? ", " $1?? ??????? "],

            ["^ (\\S+[???]?) [??]????? ", " $1?? ??????? "],

            ["^ (\\S+[??]?) [??]????? ", " $1?? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\d+)-? [??]????? ", " $1-? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? (\\S+)[???]? [??]????? ", " $1-? $2?? $3?? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? (\\S+)[???]? [??]????? ", " $1-? $2?? $3?? ??????? "],

            ["^ [??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]??????? ", " $1?? ????????? "],

            ["^ (\\S+)[???]? [??]??????? ", " $1?? ????????? "],

            ["^ (\\S+[??]?) [??]??????? ", " $1?? ????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]??????? ", " $1?? $2?? ????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]??????? ", " $1?? $2?? ????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]??????? ", " $1?? $2?? ????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]??????? ", " $1?? $2?? ????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]??????? ", " $1-? $2?? ????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]??????? ", " $1-? $2?? ????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]??????? ", " $1-? $2?? ????????? "],

            ["^ [??]??????? ", " ????????? "],



            ["^ (\\S+?)?? [??]????????? ", " $1?? ??????????? "],

            ["^ (\\S+)[???]? [??]????????? ", " $1?? ??????????? "],

            ["^ (\\S+[??]?) [??]????????? ", " $1?? ??????????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]????????? ", " $1?? $2?? ??????????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]????????? ", " $1?? $2?? ??????????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]????????? ", " $1?? $2?? ??????????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]????????? ", " $1?? $2?? ??????????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]????????? ", " $1-? $2?? ??????????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]????????? ", " $1-? $2?? ??????????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]????????? ", " $1-? $2?? ??????????? "],

            ["^ [??]????????? ", " ??????????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??][??]????? ", " $1?? ??????? "],

            ["^ (\\S+)[???]? [??][??]????? ", " $1?? ??????? "],

            ["^ (\\S+[??]?) [??][??]????? ", " $1?? ??????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??][??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??][??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??][??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??][??]????? ", " $1?? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+?)?? [??][??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??][??]????? ", " $1-? $2?? ??????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??][??]????? ", " $1-? $2?? ??????? "],

            ["^ [??][??]????? ", " ??????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+?)?? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[???]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+[??]?) [??]???? ", " $1?? ?????? "],

            ["^ (\\S+)[???]? (\\S+?)?? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+?)?? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+)[???]? [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[???]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\S+)[???]? (\\S+[??]?) [??]???? ", " $1?? $2?? ?????? "],

            ["^ (\\d+)-? [??]???? ", " $1-? ?????? "],

            ["^ (\\d+)-? (\\S+?)?? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+)[???]? [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[???]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ (\\d+)-? (\\S+[??]?) [??]???? ", " $1-? $2?? ?????? "],

            ["^ [??]???? ", " ?????? "],



            ["^ (\\S+[??])? ([??]???)?[??]????? ", " $1? $2?????? "],

            ["^ (\\S+??) ([??]???)?[??]????? ", " $1? $2?????? "],

            ["^ (\\S+[??])? (\\S+[??])? ([??]???)?[??]????? ", " $1? $2? $3?????? "],

            ["^ (\\S+??) (\\S+[??])? ([??]???)?[??]????? ", " $1? $2? $3?????? "],

            ["^ (\\d+)-? (\\S+[??])? ([??]???)?[??]????? ", " $1-? $2? $3?????? "],

            ["^ (\\d+)-? (\\S+??) ([??]???)?[??]????? ", " $1-? $2? $3?????? "],

            ["^ ([??]???)?[??]????? ", " $1?????? "],



            ["^ (\\S+[??])? [??]???? ", " $1? ????? "],

            ["^ (\\S+??) [??]???? ", " $1? ????? "],

            ["^ (\\S+[??])? (\\S+[??])? [??]???? ", " $1? $2? ????? "],

            ["^ (\\S+??) (\\S+[??])? [??]???? ", " $1? $2? ????? "],

            ["^ (\\d+)-? (\\S+[??])? [??]???? ", " $1-? $2? ????? "],

            ["^ (\\d+)-? (\\S+??) [??]???? ", " $1-? $2? ????? "],



            [" ([??])????? ", " $1?????? "],

            ["([??])?? ", "$1??? "]

        ]

    }

}



},{}],23:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "f�rste",

                "2": "anden",

                "3": "tredje",

                "4": "fjerde",

                "5": "femte",

                "6": "sjette",

                "7": "syvende",

                "8": "ottende",

                "9": "niende",

                "10": "tiende"

            },

            "direction": {

                "north": "Nord",

                "northeast": "Nord�st",

                "east": "�st",

                "southeast": "Syd�st",

                "south": "Syd",

                "southwest": "Sydvest",

                "west": "Vest",

                "northwest": "Nordvest"

            },

            "modifier": {

                "left": "venstresving",

                "right": "h�jresving",

                "sharp left": "skarpt venstresving",

                "sharp right": "skarpt h�jresving",

                "slight left": "svagt venstresving",

                "slight right": "svagt h�jresving",

                "straight": "ligeud",

                "uturn": "U-vending"

            },

            "lanes": {

                "xo": "Hold til h�jre",

                "ox": "Hold til venstre",

                "xox": "Benyt midterste spor",

                "oxo": "Hold til h�jre eller venstre"

            }

        },

        "modes": {

            "ferry": {

                "default": "Tag f�rgen",

                "name": "Tag f�rgen {way_name}",

                "destination": "Tag f�rgen i retning {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} derefter, efter {distance}, {instruction_two}",

            "two linked": "{instruction_one}, derefter {instruction_two}",

            "one in distance": "Efter {distance} {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "afk�rsel {exit}"

        },

        "arrive": {

            "default": {

                "default": "Du er ankommet til din {nth} destination",

                "upcoming": "Du vil ankomme til din {nth} destination",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}"

            },

            "left": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til venstre",

                "upcoming": "Du vil ankomme til din {nth} destination p� venstre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til venstre"

            },

            "right": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til h�jre",

                "upcoming": "Du vil ankomme til din {nth} destination p� h�jre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til h�jre"

            },

            "sharp left": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til venstre",

                "upcoming": "Du vil ankomme til din {nth} destination p� venstre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til venstre"

            },

            "sharp right": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til h�jre",

                "upcoming": "Du vil ankomme til din {nth} destination p� h�jre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til h�jre"

            },

            "slight right": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til h�jre",

                "upcoming": "Du vil ankomme til din {nth} destination p� h�jre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til h�jre"

            },

            "slight left": {

                "default": "Du er ankommet til din {nth} destination, som befinder sig til venstre",

                "upcoming": "Du vil ankomme til din {nth} destination p� venstre h�nd",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, som befinder sig til venstre"

            },

            "straight": {

                "default": "Du er ankommet til din {nth} destination, der befinder sig lige frem",

                "upcoming": "Du vil ankomme til din {nth} destination foran dig",

                "short": "Du er ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du er ankommet til {waypoint_name}, der befinder sig lige frem"

            }

        },

        "continue": {

            "default": {

                "default": "Drej til {modifier}",

                "name": "Drej til {modifier} videre ad {way_name}",

                "destination": "Drej til {modifier} mod {destination}",

                "exit": "Drej til {modifier} ad {way_name}"

            },

            "straight": {

                "default": "Forts�t ligeud",

                "name": "Forts�t ligeud ad {way_name}",

                "destination": "Forts�t mod {destination}",

                "distance": "Forts�t {distance} ligeud",

                "namedistance": "Forts�t {distance} ad {way_name}"

            },

            "sharp left": {

                "default": "Drej skarpt til venstre",

                "name": "Drej skarpt til venstre videre ad {way_name}",

                "destination": "Drej skarpt til venstre mod {destination}"

            },

            "sharp right": {

                "default": "Drej skarpt til h�jre",

                "name": "Drej skarpt til h�jre videre ad {way_name}",

                "destination": "Drej skarpt til h�jre mod {destination}"

            },

            "slight left": {

                "default": "Drej left til venstre",

                "name": "Drej let til venstre videre ad {way_name}",

                "destination": "Drej let til venstre mod {destination}"

            },

            "slight right": {

                "default": "Drej let til h�jre",

                "name": "Drej let til h�jre videre ad {way_name}",

                "destination": "Drej let til h�jre mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending",

                "name": "Foretag en U-vending tilbage ad {way_name}",

                "destination": "Foretag en U-vending mod {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "K�r mod {direction}",

                "name": "K�r mod {direction} ad {way_name}",

                "namedistance": "Forts�t {distance} ad {way_name}mod {direction}"

            }

        },

        "end of road": {

            "default": {

                "default": "Drej til {modifier}",

                "name": "Drej til {modifier} ad {way_name}",

                "destination": "Drej til {modifier} mof {destination}"

            },

            "straight": {

                "default": "Forts�t ligeud",

                "name": "Forts�t ligeud ad {way_name}",

                "destination": "Forts�t ligeud mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending for enden af vejen",

                "name": "Foretag en U-vending ad {way_name} for enden af vejen",

                "destination": "Foretag en U-vending mod {destination} for enden af vejen"

            }

        },

        "fork": {

            "default": {

                "default": "Hold til {modifier} ved udfletningen",

                "name": "Hold mod {modifier} p� {way_name}",

                "destination": "Hold mod {modifier} mod {destination}"

            },

            "slight left": {

                "default": "Hold til venstre ved udfletningen",

                "name": "Hold til venstre p� {way_name}",

                "destination": "Hold til venstre mod {destination}"

            },

            "slight right": {

                "default": "Hold til h�jre ved udfletningen",

                "name": "Hold til h�jre p� {way_name}",

                "destination": "Hold til h�jre mod {destination}"

            },

            "sharp left": {

                "default": "Drej skarpt til venstre ved udfletningen",

                "name": "Drej skarpt til venstre ad {way_name}",

                "destination": "Drej skarpt til venstre mod {destination}"

            },

            "sharp right": {

                "default": "Drej skarpt til h�jre ved udfletningen",

                "name": "Drej skarpt til h�jre ad {way_name}",

                "destination": "Drej skarpt til h�jre mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending",

                "name": "Foretag en U-vending ad {way_name}",

                "destination": "Foretag en U-vending mod {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Flet til {modifier}",

                "name": "Flet til {modifier} ad {way_name}",

                "destination": "Flet til {modifier} mod {destination}"

            },

            "straight": {

                "default": "Flet",

                "name": "Flet ind p� {way_name}",

                "destination": "Flet ind mod {destination}"

            },

            "slight left": {

                "default": "Flet til venstre",

                "name": "Flet til venstre ad {way_name}",

                "destination": "Flet til venstre mod {destination}"

            },

            "slight right": {

                "default": "Flet til h�jre",

                "name": "Flet til h�jre ad {way_name}",

                "destination": "Flet til h�jre mod {destination}"

            },

            "sharp left": {

                "default": "Flet til venstre",

                "name": "Flet til venstre ad {way_name}",

                "destination": "Flet til venstre mod {destination}"

            },

            "sharp right": {

                "default": "Flet til h�jre",

                "name": "Flet til h�jre ad {way_name}",

                "destination": "Flet til h�jre mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending",

                "name": "Foretag en U-vending ad {way_name}",

                "destination": "Foretag en U-vending mod {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Forts�t {modifier}",

                "name": "Forts�t {modifier} ad {way_name}",

                "destination": "Forts�t {modifier} mod {destination}"

            },

            "straight": {

                "default": "Forts�t ligeud",

                "name": "Forts�t ad {way_name}",

                "destination": "Forts�t mod {destination}"

            },

            "sharp left": {

                "default": "Drej skarpt til venstre",

                "name": "Drej skarpt til venstre ad {way_name}",

                "destination": "Drej skarpt til venstre mod {destination}"

            },

            "sharp right": {

                "default": "Drej skarpt til h�jre",

                "name": "Drej skarpt til h�jre ad {way_name}",

                "destination": "Drej skarpt til h�jre mod {destination}"

            },

            "slight left": {

                "default": "Forts�t til venstre",

                "name": "Forts�t til venstre ad {way_name}",

                "destination": "Forts�t til venstre mod {destination}"

            },

            "slight right": {

                "default": "Forts�t til h�jre",

                "name": "Forts�t til h�jre ad {way_name}",

                "destination": "Forts�t til h�jre mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending",

                "name": "Foretag en U-vending ad {way_name}",

                "destination": "Foretag en U-vending mod {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Forts�t {modifier}",

                "name": "Forts�t {modifier} ad {way_name}",

                "destination": "Forts�t {modifier} mod {destination}"

            },

            "uturn": {

                "default": "Foretag en U-vending",

                "name": "Foretag en U-vending ad {way_name}",

                "destination": "Foretag en U-vending mod {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Tag afk�rslen",

                "name": "Tag afk�rslen ad {way_name}",

                "destination": "Tag afk�rslen mod {destination}",

                "exit": "V�lg afk�rsel {exit}",

                "exit_destination": "V�lg afk�rsel {exit} mod {destination}"

            },

            "left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til venstre",

                "exit_destination": "V�lg afk�rsel {exit} til venstre mod {destination}\n"

            },

            "right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til h�jre",

                "exit_destination": "V�lg afk�rsel {exit} til h�jre mod {destination}"

            },

            "sharp left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til venstre",

                "exit_destination": "V�lg afk�rsel {exit} til venstre mod {destination}\n"

            },

            "sharp right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til h�jre",

                "exit_destination": "V�lg afk�rsel {exit} til h�jre mod {destination}"

            },

            "slight left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til venstre",

                "exit_destination": "V�lg afk�rsel {exit} til venstre mod {destination}\n"

            },

            "slight right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}",

                "exit": "V�lg afk�rsel {exit} til h�jre",

                "exit_destination": "V�lg afk�rsel {exit} til h�jre mod {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Tag afk�rslen",

                "name": "Tag afk�rslen ad {way_name}",

                "destination": "Tag afk�rslen mod {destination}"

            },

            "left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}"

            },

            "right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}"

            },

            "sharp left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}"

            },

            "sharp right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}"

            },

            "slight left": {

                "default": "Tag afk�rslen til venstre",

                "name": "Tag afk�rslen til venstre ad {way_name}",

                "destination": "Tag afk�rslen til venstre mod {destination}"

            },

            "slight right": {

                "default": "Tag afk�rslen til h�jre",

                "name": "Tag afk�rslen til h�jre ad {way_name}",

                "destination": "Tag afk�rslen til h�jre mod {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "K�r ind i rundk�rslen",

                    "name": "Tag rundk�rslen og k�r fra ad {way_name}",

                    "destination": "Tag rundk�rslen og k�r mod {destination}"

                },

                "name": {

                    "default": "K�r ind i {rotary_name}",

                    "name": "K�r ind i {rotary_name} og k�r ad {way_name} ",

                    "destination": "K�r ind i {rotary_name} og k�r mod {destination}"

                },

                "exit": {

                    "default": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel",

                    "name": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel ad {way_name}",

                    "destination": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel mod {destination}"

                },

                "name_exit": {

                    "default": "K�r ind i {rotary_name} og forlad ved {exit_number} afk�rsel",

                    "name": "K�r ind i {rotary_name} og forlad ved {exit_number} afk�rsel ad {way_name}",

                    "destination": "K�r ind i {rotary_name} og forlad ved {exit_number} afk�rsel mod {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel",

                    "name": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel ad {way_name}",

                    "destination": "Tag rundk�rslen og forlad ved {exit_number} afk�rsel mod {destination}"

                },

                "default": {

                    "default": "K�r ind i rundk�rslen",

                    "name": "Tag rundk�rslen og k�r fra ad {way_name}",

                    "destination": "Tag rundk�rslen og k�r mod {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Foretag et {modifier}",

                "name": "Foretag et {modifier} ad {way_name}",

                "destination": "Foretag et {modifier} mod {destination}"

            },

            "left": {

                "default": "Drej til venstre",

                "name": "Drej til venstre ad {way_name}",

                "destination": "Drej til venstre mod {destination}"

            },

            "right": {

                "default": "Drej til h�jre",

                "name": "Drej til h�jre ad {way_name}",

                "destination": "Drej til h�jre mod {destination}"

            },

            "straight": {

                "default": "Forts�t ligeud",

                "name": "Forts�t ligeud ad {way_name}",

                "destination": "Forts�t ligeud mod {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Forlad rundk�rslen",

                "name": "Forlad rundk�rslen ad {way_name}",

                "destination": "Forlad rundk�rslen mod  {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Forlad rundk�rslen",

                "name": "Forlad rundk�rslen ad {way_name}",

                "destination": "Forlad rundk�rslen mod {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Foretag et {modifier}",

                "name": "Foretag et {modifier} ad {way_name}",

                "destination": "Foretag et {modifier} mod {destination}"

            },

            "left": {

                "default": "Drej til venstre",

                "name": "Drej til venstre ad {way_name}",

                "destination": "Drej til venstre mod {destination}"

            },

            "right": {

                "default": "Drej til h�jre",

                "name": "Drej til h�jre ad {way_name}",

                "destination": "Drej til h�jre mod {destination}"

            },

            "straight": {

                "default": "Forts�t ligeud",

                "name": "K�r ligeud ad {way_name}",

                "destination": "K�r ligeud mod {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Forts�t ligeud"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],24:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "erste",

                "2": "zweite",

                "3": "dritte",

                "4": "vierte",

                "5": "f�nfte",

                "6": "sechste",

                "7": "siebente",

                "8": "achte",

                "9": "neunte",

                "10": "zehnte"

            },

            "direction": {

                "north": "Norden",

                "northeast": "Nordosten",

                "east": "Osten",

                "southeast": "S�dosten",

                "south": "S�den",

                "southwest": "S�dwesten",

                "west": "Westen",

                "northwest": "Nordwesten"

            },

            "modifier": {

                "left": "links",

                "right": "rechts",

                "sharp left": "scharf links",

                "sharp right": "scharf rechts",

                "slight left": "leicht links",

                "slight right": "leicht rechts",

                "straight": "geradeaus",

                "uturn": "180°-Wendung"

            },

            "lanes": {

                "xo": "Rechts halten",

                "ox": "Links halten",

                "xox": "Mittlere Spur nutzen",

                "oxo": "Rechts oder links halten"

            }

        },

        "modes": {

            "ferry": {

                "default": "F�hre nehmen",

                "name": "F�hre nehmen {way_name}",

                "destination": "F�hre nehmen Richtung {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} danach in {distance} {instruction_two}",

            "two linked": "{instruction_one} danach {instruction_two}",

            "one in distance": "In {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "Sie haben Ihr {nth} Ziel erreicht",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}"

            },

            "left": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich links"

            },

            "right": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich rechts"

            },

            "sharp left": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich links"

            },

            "sharp right": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich rechts"

            },

            "slight right": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich rechts",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich rechts"

            },

            "slight left": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich links",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich links"

            },

            "straight": {

                "default": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich geradeaus",

                "upcoming": "Sie haben Ihr {nth} Ziel erreicht, es befindet sich geradeaus",

                "short": "Sie haben Ihr {nth} Ziel erreicht",

                "short-upcoming": "Sie haben Ihr {nth} Ziel erreicht",

                "named": "Sie haben Ihr {waypoint_name}, es befindet sich geradeaus"

            }

        },

        "continue": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} weiterfahren auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}",

                "exit": "{modifier} abbiegen auf {way_name}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Weiterfahren in Richtung {destination}",

                "distance": "Geradeaus weiterfahren f�r {distance}",

                "namedistance": "Geradeaus weiterfahren auf {way_name} f�r {distance}"

            },

            "sharp left": {

                "default": "Scharf links",

                "name": "Scharf links weiterfahren auf {way_name}",

                "destination": "Scharf links Richtung {destination}"

            },

            "sharp right": {

                "default": "Scharf rechts",

                "name": "Scharf rechts weiterfahren auf {way_name}",

                "destination": "Scharf rechts Richtung {destination}"

            },

            "slight left": {

                "default": "Leicht links",

                "name": "Leicht links weiter auf {way_name}",

                "destination": "Leicht links weiter Richtung {destination}"

            },

            "slight right": {

                "default": "Leicht rechts weiter",

                "name": "Leicht rechts weiter auf {way_name}",

                "destination": "Leicht rechts weiter Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung",

                "name": "180�-Wendung auf {way_name}",

                "destination": "180�-Wendung Richtung {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Fahren Sie Richtung {direction}",

                "name": "Fahren Sie Richtung {direction} auf {way_name}",

                "namedistance": "Fahren Sie Richtung {direction} auf {way_name} f�r {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} abbiegen auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Geradeaus weiterfahren Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung am Ende der Stra�e",

                "name": "180�-Wendung auf {way_name} am Ende der Stra�e",

                "destination": "180�-Wendung Richtung {destination} am Ende der Stra�e"

            }

        },

        "fork": {

            "default": {

                "default": "{modifier} halten an der Gabelung",

                "name": "{modifier} halten an der Gabelung auf {way_name}",

                "destination": "{modifier}  halten an der Gabelung Richtung {destination}"

            },

            "slight left": {

                "default": "Links halten an der Gabelung",

                "name": "Links halten an der Gabelung auf {way_name}",

                "destination": "Links halten an der Gabelung Richtung {destination}"

            },

            "slight right": {

                "default": "Rechts halten an der Gabelung",

                "name": "Rechts halten an der Gabelung auf {way_name}",

                "destination": "Rechts halten an der Gabelung Richtung {destination}"

            },

            "sharp left": {

                "default": "Scharf links abbiegen an der Gabelung",

                "name": "Scharf links auf {way_name}",

                "destination": "Scharf links Richtung {destination}"

            },

            "sharp right": {

                "default": "Scharf rechts abbiegen an der Gabelung",

                "name": "Scharf rechts auf {way_name}",

                "destination": "Scharf rechts Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung",

                "name": "180�-Wendung auf {way_name}",

                "destination": "180�-Wendung Richtung {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "{modifier} auffahren",

                "name": "{modifier} auffahren auf {way_name}",

                "destination": "{modifier} auffahren Richtung {destination}"

            },

            "straight": {

                "default": "geradeaus auffahren",

                "name": "geradeaus auffahren auf {way_name}",

                "destination": "geradeaus auffahren Richtung {destination}"

            },

            "slight left": {

                "default": "Leicht links auffahren",

                "name": "Leicht links auffahren auf {way_name}",

                "destination": "Leicht links auffahren Richtung {destination}"

            },

            "slight right": {

                "default": "Leicht rechts auffahren",

                "name": "Leicht rechts auffahren auf {way_name}",

                "destination": "Leicht rechts auffahren Richtung {destination}"

            },

            "sharp left": {

                "default": "Scharf links auffahren",

                "name": "Scharf links auffahren auf {way_name}",

                "destination": "Scharf links auffahren Richtung {destination}"

            },

            "sharp right": {

                "default": "Scharf rechts auffahren",

                "name": "Scharf rechts auffahren auf {way_name}",

                "destination": "Scharf rechts auffahren Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung",

                "name": "180�-Wendung auf {way_name}",

                "destination": "180�-Wendung Richtung {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "{modifier} weiterfahren",

                "name": "{modifier} weiterfahren auf {way_name}",

                "destination": "{modifier} weiterfahren Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Weiterfahren auf {way_name}",

                "destination": "Weiterfahren in Richtung {destination}"

            },

            "sharp left": {

                "default": "Scharf links",

                "name": "Scharf links auf {way_name}",

                "destination": "Scharf links Richtung {destination}"

            },

            "sharp right": {

                "default": "Scharf rechts",

                "name": "Scharf rechts auf {way_name}",

                "destination": "Scharf rechts Richtung {destination}"

            },

            "slight left": {

                "default": "Leicht links weiter",

                "name": "Leicht links weiter auf {way_name}",

                "destination": "Leicht links weiter Richtung {destination}"

            },

            "slight right": {

                "default": "Leicht rechts weiter",

                "name": "Leicht rechts weiter auf {way_name}",

                "destination": "Leicht rechts weiter Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung",

                "name": "180�-Wendung auf {way_name}",

                "destination": "180�-Wendung Richtung {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "{modifier} weiterfahren",

                "name": "{modifier} weiterfahren auf {way_name}",

                "destination": "{modifier} weiterfahren Richtung {destination}"

            },

            "uturn": {

                "default": "180�-Wendung",

                "name": "180�-Wendung auf {way_name}",

                "destination": "180�-Wendung Richtung {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Ausfahrt nehmen",

                "name": "Ausfahrt nehmen auf {way_name}",

                "destination": "Ausfahrt nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} nehmen",

                "exit_destination": "Ausfahrt {exit} nehmen Richtung {destination}"

            },

            "left": {

                "default": "Ausfahrt links nehmen",

                "name": "Ausfahrt links nehmen auf {way_name}",

                "destination": "Ausfahrt links nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} links nehmen",

                "exit_destination": "Ausfahrt {exit} links nehmen Richtung {destination}"

            },

            "right": {

                "default": "Ausfahrt rechts nehmen",

                "name": "Ausfahrt rechts nehmen Richtung {way_name}",

                "destination": "Ausfahrt rechts nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} rechts nehmen",

                "exit_destination": "Ausfahrt {exit} nehmen Richtung {destination}"

            },

            "sharp left": {

                "default": "Ausfahrt links nehmen",

                "name": "Ausfahrt links Seite nehmen auf {way_name}",

                "destination": "Ausfahrt links nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} links nehmen",

                "exit_destination": "Ausfahrt{exit} links nehmen Richtung {destination}"

            },

            "sharp right": {

                "default": "Ausfahrt rechts nehmen",

                "name": "Ausfahrt rechts nehmen auf {way_name}",

                "destination": "Ausfahrt rechts nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} rechts nehmen",

                "exit_destination": "Ausfahrt {exit} nehmen Richtung {destination}"

            },

            "slight left": {

                "default": "Ausfahrt links nehmen",

                "name": "Ausfahrt links nehmen auf {way_name}",

                "destination": "Ausfahrt links nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} nehmen",

                "exit_destination": "Ausfahrt {exit} links nehmen Richtung {destination}"

            },

            "slight right": {

                "default": "Ausfahrt rechts nehmen",

                "name": "Ausfahrt rechts nehmen auf {way_name}",

                "destination": "Ausfahrt rechts nehmen Richtung {destination}",

                "exit": "Ausfahrt {exit} rechts nehmen",

                "exit_destination": "Ausfahrt {exit} nehmen Richtung {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Auffahrt nehmen",

                "name": "Auffahrt nehmen auf {way_name}",

                "destination": "Auffahrt nehmen Richtung {destination}"

            },

            "left": {

                "default": "Auffahrt links nehmen",

                "name": "Auffahrt links nehmen auf {way_name}",

                "destination": "Auffahrt links nehmen Richtung {destination}"

            },

            "right": {

                "default": "Auffahrt rechts nehmen",

                "name": "Auffahrt rechts nehmen auf {way_name}",

                "destination": "Auffahrt rechts nehmen Richtung {destination}"

            },

            "sharp left": {

                "default": "Auffahrt links nehmen",

                "name": "Auffahrt links nehmen auf {way_name}",

                "destination": "Auffahrt links nehmen Richtung {destination}"

            },

            "sharp right": {

                "default": "Auffahrt rechts nehmen",

                "name": "Auffahrt rechts nehmen auf {way_name}",

                "destination": "Auffahrt rechts nehmen Richtung {destination}"

            },

            "slight left": {

                "default": "Auffahrt links Seite nehmen",

                "name": "Auffahrt links nehmen auf {way_name}",

                "destination": "Auffahrt links nehmen Richtung {destination}"

            },

            "slight right": {

                "default": "Auffahrt rechts nehmen",

                "name": "Auffahrt rechts nehmen auf {way_name}",

                "destination": "Auffahrt rechts nehmen Richtung {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "In den Kreisverkehr fahren",

                    "name": "Im Kreisverkehr die Ausfahrt auf {way_name} nehmen",

                    "destination": "Im Kreisverkehr die Ausfahrt Richtung {destination} nehmen"

                },

                "name": {

                    "default": "In {rotary_name} fahren",

                    "name": "In {rotary_name} die Ausfahrt auf {way_name} nehmen",

                    "destination": "In {rotary_name} die Ausfahrt Richtung {destination} nehmen"

                },

                "exit": {

                    "default": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen",

                    "name": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen auf {way_name}",

                    "destination": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen Richtung {destination}"

                },

                "name_exit": {

                    "default": "In den Kreisverkehr fahren und {exit_number} Ausfahrt nehmen",

                    "name": "In den Kreisverkehr fahren und {exit_number} Ausfahrt nehmen auf {way_name}",

                    "destination": "In den Kreisverkehr fahren und {exit_number} Ausfahrt nehmen Richtung {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen",

                    "name": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen auf {way_name}",

                    "destination": "Im Kreisverkehr die {exit_number} Ausfahrt nehmen Richtung {destination}"

                },

                "default": {

                    "default": "In den Kreisverkehr fahren",

                    "name": "Im Kreisverkehr die Ausfahrt auf {way_name} nehmen",

                    "destination": "Im Kreisverkehr die Ausfahrt Richtung {destination} nehmen"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} abbiegen auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}"

            },

            "left": {

                "default": "Links abbiegen",

                "name": "Links abbiegen auf {way_name}",

                "destination": "Links abbiegen Richtung {destination}"

            },

            "right": {

                "default": "Rechts abbiegen",

                "name": "Rechts abbiegen auf {way_name}",

                "destination": "Rechts abbiegen Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Geradeaus weiterfahren Richtung {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} abbiegen auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}"

            },

            "left": {

                "default": "Links abbiegen",

                "name": "Links abbiegen auf {way_name}",

                "destination": "Links abbiegen Richtung {destination}"

            },

            "right": {

                "default": "Rechts abbiegen",

                "name": "Rechts abbiegen auf {way_name}",

                "destination": "Rechts abbiegen Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Geradeaus weiterfahren Richtung {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} abbiegen auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}"

            },

            "left": {

                "default": "Links abbiegen",

                "name": "Links abbiegen auf {way_name}",

                "destination": "Links abbiegen Richtung {destination}"

            },

            "right": {

                "default": "Rechts abbiegen",

                "name": "Rechts abbiegen auf {way_name}",

                "destination": "Rechts abbiegen Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Geradeaus weiterfahren Richtung {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "{modifier} abbiegen",

                "name": "{modifier} abbiegen auf {way_name}",

                "destination": "{modifier} abbiegen Richtung {destination}"

            },

            "left": {

                "default": "Links abbiegen",

                "name": "Links abbiegen auf {way_name}",

                "destination": "Links abbiegen Richtung {destination}"

            },

            "right": {

                "default": "Rechts abbiegen",

                "name": "Rechts abbiegen auf {way_name}",

                "destination": "Rechts abbiegen Richtung {destination}"

            },

            "straight": {

                "default": "Geradeaus weiterfahren",

                "name": "Geradeaus weiterfahren auf {way_name}",

                "destination": "Geradeaus weiterfahren Richtung {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Geradeaus weiterfahren"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],25:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1st",

                "2": "2nd",

                "3": "3rd",

                "4": "4th",

                "5": "5th",

                "6": "6th",

                "7": "7th",

                "8": "8th",

                "9": "9th",

                "10": "10th"

            },

            "direction": {

                "north": "north",

                "northeast": "northeast",

                "east": "east",

                "southeast": "southeast",

                "south": "south",

                "southwest": "southwest",

                "west": "west",

                "northwest": "northwest"

            },

            "modifier": {

                "left": "left",

                "right": "right",

                "sharp left": "sharp left",

                "sharp right": "sharp right",

                "slight left": "slight left",

                "slight right": "slight right",

                "straight": "straight",

                "uturn": "U-turn"

            },

            "lanes": {

                "xo": "Keep right",

                "ox": "Keep left",

                "xox": "Keep in the middle",

                "oxo": "Keep left or right"

            }

        },

        "modes": {

            "ferry": {

                "default": "Take the ferry",

                "name": "Take the ferry {way_name}",

                "destination": "Take the ferry towards {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, then, in {distance}, {instruction_two}",

            "two linked": "{instruction_one}, then {instruction_two}",

            "one in distance": "In {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "You have arrived at your {nth} destination",

                "upcoming": "You will arrive at your {nth} destination",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}"

            },

            "left": {

                "default": "You have arrived at your {nth} destination, on the left",

                "upcoming": "You will arrive at your {nth} destination, on the left",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the left"

            },

            "right": {

                "default": "You have arrived at your {nth} destination, on the right",

                "upcoming": "You will arrive at your {nth} destination, on the right",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the right"

            },

            "sharp left": {

                "default": "You have arrived at your {nth} destination, on the left",

                "upcoming": "You will arrive at your {nth} destination, on the left",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the left"

            },

            "sharp right": {

                "default": "You have arrived at your {nth} destination, on the right",

                "upcoming": "You will arrive at your {nth} destination, on the right",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the right"

            },

            "slight right": {

                "default": "You have arrived at your {nth} destination, on the right",

                "upcoming": "You will arrive at your {nth} destination, on the right",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the right"

            },

            "slight left": {

                "default": "You have arrived at your {nth} destination, on the left",

                "upcoming": "You will arrive at your {nth} destination, on the left",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, on the left"

            },

            "straight": {

                "default": "You have arrived at your {nth} destination, straight ahead",

                "upcoming": "You will arrive at your {nth} destination, straight ahead",

                "short": "You have arrived",

                "short-upcoming": "You will arrive",

                "named": "You have arrived at {waypoint_name}, straight ahead"

            }

        },

        "continue": {

            "default": {

                "default": "Turn {modifier}",

                "name": "Turn {modifier} to stay on {way_name}",

                "destination": "Turn {modifier} towards {destination}",

                "exit": "Turn {modifier} onto {way_name}"

            },

            "straight": {

                "default": "Continue straight",

                "name": "Continue straight to stay on {way_name}",

                "destination": "Continue towards {destination}",

                "distance": "Continue straight for {distance}",

                "namedistance": "Continue on {way_name} for {distance}"

            },

            "sharp left": {

                "default": "Make a sharp left",

                "name": "Make a sharp left to stay on {way_name}",

                "destination": "Make a sharp left towards {destination}"

            },

            "sharp right": {

                "default": "Make a sharp right",

                "name": "Make a sharp right to stay on {way_name}",

                "destination": "Make a sharp right towards {destination}"

            },

            "slight left": {

                "default": "Make a slight left",

                "name": "Make a slight left to stay on {way_name}",

                "destination": "Make a slight left towards {destination}"

            },

            "slight right": {

                "default": "Make a slight right",

                "name": "Make a slight right to stay on {way_name}",

                "destination": "Make a slight right towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn",

                "name": "Make a U-turn and continue on {way_name}",

                "destination": "Make a U-turn towards {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Head {direction}",

                "name": "Head {direction} on {way_name}",

                "namedistance": "Head {direction} on {way_name} for {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Turn {modifier}",

                "name": "Turn {modifier} onto {way_name}",

                "destination": "Turn {modifier} towards {destination}"

            },

            "straight": {

                "default": "Continue straight",

                "name": "Continue straight onto {way_name}",

                "destination": "Continue straight towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn at the end of the road",

                "name": "Make a U-turn onto {way_name} at the end of the road",

                "destination": "Make a U-turn towards {destination} at the end of the road"

            }

        },

        "fork": {

            "default": {

                "default": "Keep {modifier} at the fork",

                "name": "Keep {modifier} onto {way_name}",

                "destination": "Keep {modifier} towards {destination}"

            },

            "slight left": {

                "default": "Keep left at the fork",

                "name": "Keep left onto {way_name}",

                "destination": "Keep left towards {destination}"

            },

            "slight right": {

                "default": "Keep right at the fork",

                "name": "Keep right onto {way_name}",

                "destination": "Keep right towards {destination}"

            },

            "sharp left": {

                "default": "Take a sharp left at the fork",

                "name": "Take a sharp left onto {way_name}",

                "destination": "Take a sharp left towards {destination}"

            },

            "sharp right": {

                "default": "Take a sharp right at the fork",

                "name": "Take a sharp right onto {way_name}",

                "destination": "Take a sharp right towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn",

                "name": "Make a U-turn onto {way_name}",

                "destination": "Make a U-turn towards {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Merge {modifier}",

                "name": "Merge {modifier} onto {way_name}",

                "destination": "Merge {modifier} towards {destination}"

            },

            "straight": {

                "default": "Merge",

                "name": "Merge onto {way_name}",

                "destination": "Merge towards {destination}"

            },

            "slight left": {

                "default": "Merge left",

                "name": "Merge left onto {way_name}",

                "destination": "Merge left towards {destination}"

            },

            "slight right": {

                "default": "Merge right",

                "name": "Merge right onto {way_name}",

                "destination": "Merge right towards {destination}"

            },

            "sharp left": {

                "default": "Merge left",

                "name": "Merge left onto {way_name}",

                "destination": "Merge left towards {destination}"

            },

            "sharp right": {

                "default": "Merge right",

                "name": "Merge right onto {way_name}",

                "destination": "Merge right towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn",

                "name": "Make a U-turn onto {way_name}",

                "destination": "Make a U-turn towards {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} onto {way_name}",

                "destination": "Continue {modifier} towards {destination}"

            },

            "straight": {

                "default": "Continue straight",

                "name": "Continue onto {way_name}",

                "destination": "Continue towards {destination}"

            },

            "sharp left": {

                "default": "Take a sharp left",

                "name": "Take a sharp left onto {way_name}",

                "destination": "Take a sharp left towards {destination}"

            },

            "sharp right": {

                "default": "Take a sharp right",

                "name": "Take a sharp right onto {way_name}",

                "destination": "Take a sharp right towards {destination}"

            },

            "slight left": {

                "default": "Continue slightly left",

                "name": "Continue slightly left onto {way_name}",

                "destination": "Continue slightly left towards {destination}"

            },

            "slight right": {

                "default": "Continue slightly right",

                "name": "Continue slightly right onto {way_name}",

                "destination": "Continue slightly right towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn",

                "name": "Make a U-turn onto {way_name}",

                "destination": "Make a U-turn towards {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} onto {way_name}",

                "destination": "Continue {modifier} towards {destination}"

            },

            "uturn": {

                "default": "Make a U-turn",

                "name": "Make a U-turn onto {way_name}",

                "destination": "Make a U-turn towards {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Take the ramp",

                "name": "Take the ramp onto {way_name}",

                "destination": "Take the ramp towards {destination}",

                "exit": "Take exit {exit}",

                "exit_destination": "Take exit {exit} towards {destination}"

            },

            "left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            },

            "sharp left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "sharp right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            },

            "slight left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "slight right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Take the ramp",

                "name": "Take the ramp onto {way_name}",

                "destination": "Take the ramp towards {destination}"

            },

            "left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}"

            },

            "right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}"

            },

            "sharp left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}"

            },

            "sharp right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}"

            },

            "slight left": {

                "default": "Take the ramp on the left",

                "name": "Take the ramp on the left onto {way_name}",

                "destination": "Take the ramp on the left towards {destination}"

            },

            "slight right": {

                "default": "Take the ramp on the right",

                "name": "Take the ramp on the right onto {way_name}",

                "destination": "Take the ramp on the right towards {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Enter the traffic circle",

                    "name": "Enter the traffic circle and exit onto {way_name}",

                    "destination": "Enter the traffic circle and exit towards {destination}"

                },

                "name": {

                    "default": "Enter {rotary_name}",

                    "name": "Enter {rotary_name} and exit onto {way_name}",

                    "destination": "Enter {rotary_name} and exit towards {destination}"

                },

                "exit": {

                    "default": "Enter the traffic circle and take the {exit_number} exit",

                    "name": "Enter the traffic circle and take the {exit_number} exit onto {way_name}",

                    "destination": "Enter the traffic circle and take the {exit_number} exit towards {destination}"

                },

                "name_exit": {

                    "default": "Enter {rotary_name} and take the {exit_number} exit",

                    "name": "Enter {rotary_name} and take the {exit_number} exit onto {way_name}",

                    "destination": "Enter {rotary_name} and take the {exit_number} exit towards {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Enter the traffic circle and take the {exit_number} exit",

                    "name": "Enter the traffic circle and take the {exit_number} exit onto {way_name}",

                    "destination": "Enter the traffic circle and take the {exit_number} exit towards {destination}"

                },

                "default": {

                    "default": "Enter the traffic circle",

                    "name": "Enter the traffic circle and exit onto {way_name}",

                    "destination": "Enter the traffic circle and exit towards {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Make a {modifier}",

                "name": "Make a {modifier} onto {way_name}",

                "destination": "Make a {modifier} towards {destination}"

            },

            "left": {

                "default": "Turn left",

                "name": "Turn left onto {way_name}",

                "destination": "Turn left towards {destination}"

            },

            "right": {

                "default": "Turn right",

                "name": "Turn right onto {way_name}",

                "destination": "Turn right towards {destination}"

            },

            "straight": {

                "default": "Continue straight",

                "name": "Continue straight onto {way_name}",

                "destination": "Continue straight towards {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Exit the traffic circle",

                "name": "Exit the traffic circle onto {way_name}",

                "destination": "Exit the traffic circle towards {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Exit the traffic circle",

                "name": "Exit the traffic circle onto {way_name}",

                "destination": "Exit the traffic circle towards {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Make a {modifier}",

                "name": "Make a {modifier} onto {way_name}",

                "destination": "Make a {modifier} towards {destination}"

            },

            "left": {

                "default": "Turn left",

                "name": "Turn left onto {way_name}",

                "destination": "Turn left towards {destination}"

            },

            "right": {

                "default": "Turn right",

                "name": "Turn right onto {way_name}",

                "destination": "Turn right towards {destination}"

            },

            "straight": {

                "default": "Go straight",

                "name": "Go straight onto {way_name}",

                "destination": "Go straight towards {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Continue straight"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],26:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1.",

                "2": "2.",

                "3": "3.",

                "4": "4.",

                "5": "5.",

                "6": "6.",

                "7": "7.",

                "8": "8.",

                "9": "9.",

                "10": "10."

            },

            "direction": {

                "north": "norden",

                "northeast": "nord-orienten",

                "east": "orienten",

                "southeast": "sud-orienten",

                "south": "suden",

                "southwest": "sud-okcidenten",

                "west": "okcidenten",

                "northwest": "nord-okcidenten"

            },

            "modifier": {

                "left": "maldekstren",

                "right": "dekstren",

                "sharp left": "maldekstregen",

                "sharp right": "dekstregen",

                "slight left": "maldekstreten",

                "slight right": "dekstreten",

                "straight": "rekten",

                "uturn": "turnigu malantauen"

            },

            "lanes": {

                "xo": "Veturu dekstre",

                "ox": "Veturu maldekstre",

                "xox": "Veturu meze",

                "oxo": "Veturu dekstre au maldekstre"

            }

        },

        "modes": {

            "ferry": {

                "default": "Enpramigu",

                "name": "Enpramigu {way_name}",

                "destination": "Enpramigu direkte al {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} kaj post {distance} {instruction_two}",

            "two linked": "{instruction_one} kaj sekve {instruction_two}",

            "one in distance": "Post {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "elveturejo {exit}"

        },

        "arrive": {

            "default": {

                "default": "Vi atingis vian {nth} celon",

                "upcoming": "Vi atingos vian {nth} celon",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}"

            },

            "left": {

                "default": "Vi atingis vian {nth} celon ce maldekstre",

                "upcoming": "Vi atingos vian {nth} celon ce maldekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce maldekstre"

            },

            "right": {

                "default": "Vi atingis vian {nth} celon ce dekstre",

                "upcoming": "Vi atingos vian {nth} celon ce dekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce dekstre"

            },

            "sharp left": {

                "default": "Vi atingis vian {nth} celon ce maldekstre",

                "upcoming": "Vi atingos vian {nth} celon ce maldekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce maldekstre"

            },

            "sharp right": {

                "default": "Vi atingis vian {nth} celon ce dekstre",

                "upcoming": "Vi atingos vian {nth} celon ce dekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce dekstre"

            },

            "slight right": {

                "default": "Vi atingis vian {nth} celon ce dekstre",

                "upcoming": "Vi atingos vian {nth} celon ce dekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce dekstre"

            },

            "slight left": {

                "default": "Vi atingis vian {nth} celon ce maldekstre",

                "upcoming": "Vi atingos vian {nth} celon ce maldekstre",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name}, ce maldekstre"

            },

            "straight": {

                "default": "Vi atingis vian {nth} celon",

                "upcoming": "Vi atingos vian {nth} celon rekte",

                "short": "Vi atingis",

                "short-upcoming": "Vi atingos",

                "named": "Vi atingis {waypoint_name} antaue"

            }

        },

        "continue": {

            "default": {

                "default": "Veturu {modifier}",

                "name": "Veturu {modifier} al {way_name}",

                "destination": "Veturu {modifier} direkte al {destination}",

                "exit": "Veturu {modifier} direkte al {way_name}"

            },

            "straight": {

                "default": "Veturu rekten",

                "name": "Veturu rekten al {way_name}",

                "destination": "Veturu rekten direkte al {destination}",

                "distance": "Veturu rekten dum {distance}",

                "namedistance": "Veturu rekten al {way_name} dum {distance}"

            },

            "sharp left": {

                "default": "Turnigu ege maldekstren",

                "name": "Turnigu ege maldekstren al {way_name}",

                "destination": "Turnigu ege maldekstren direkte al {destination}"

            },

            "sharp right": {

                "default": "Turnigu ege dekstren",

                "name": "Turnigu ege dekstren al {way_name}",

                "destination": "Turnigu ege dekstren direkte al {destination}"

            },

            "slight left": {

                "default": "Turnigu ete maldekstren",

                "name": "Turnigu ete maldekstren al {way_name}",

                "destination": "Turnigu ete maldekstren direkte al {destination}"

            },

            "slight right": {

                "default": "Turnigu ete dekstren",

                "name": "Turnigu ete dekstren al {way_name}",

                "destination": "Turnigu ete dekstren direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen",

                "name": "Turnigu malantauen al {way_name}",

                "destination": "Turnigu malantauen direkte al {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Direktigu {direction}",

                "name": "Direktigu {direction} al {way_name}",

                "namedistance": "Direktigu {direction} al {way_name} tra {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Veturu {modifier}",

                "name": "Veturu {modifier} direkte al {way_name}",

                "destination": "Veturu {modifier} direkte al {destination}"

            },

            "straight": {

                "default": "Veturu rekten",

                "name": "Veturu rekten al {way_name}",

                "destination": "Veturu rekten direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen ce fino de la vojo",

                "name": "Turnigu malantauen al {way_name} ce fino de la vojo",

                "destination": "Turnigu malantauen direkte al {destination} ce fino de la vojo"

            }

        },

        "fork": {

            "default": {

                "default": "Dauru {modifier} ce la vojforko",

                "name": "Pluu {modifier} al {way_name}",

                "destination": "Pluu {modifier} direkte al {destination}"

            },

            "slight left": {

                "default": "Maldekstren ce la vojforko",

                "name": "Pluu maldekstren al {way_name}",

                "destination": "Pluu maldekstren direkte al {destination}"

            },

            "slight right": {

                "default": "Dekstren ce la vojforko",

                "name": "Pluu dekstren al {way_name}",

                "destination": "Pluu dekstren direkte al {destination}"

            },

            "sharp left": {

                "default": "Ege maldekstren ce la vojforko",

                "name": "Turnigu ege maldekstren al {way_name}",

                "destination": "Turnigu ege maldekstren direkte al {destination}"

            },

            "sharp right": {

                "default": "Ege dekstren ce la vojforko",

                "name": "Turnigu ege dekstren al {way_name}",

                "destination": "Turnigu ege dekstren direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen",

                "name": "Turnigu malantauen al {way_name}",

                "destination": "Turnigu malantauen direkte al {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Enveturu {modifier}",

                "name": "Enveturu {modifier} al {way_name}",

                "destination": "Enveturu {modifier} direkte al {destination}"

            },

            "straight": {

                "default": "Enveturu",

                "name": "Enveturu al {way_name}",

                "destination": "Enveturu direkte al {destination}"

            },

            "slight left": {

                "default": "Enveturu de maldekstre",

                "name": "Enveturu de maldekstre al {way_name}",

                "destination": "Enveturu de maldekstre direkte al {destination}"

            },

            "slight right": {

                "default": "Enveturu de dekstre",

                "name": "Enveturu de dekstre al {way_name}",

                "destination": "Enveturu de dekstre direkte al {destination}"

            },

            "sharp left": {

                "default": "Enveturu de maldekstre",

                "name": "Enveture de maldekstre al {way_name}",

                "destination": "Enveturu de maldekstre direkte al {destination}"

            },

            "sharp right": {

                "default": "Enveturu de dekstre",

                "name": "Enveturu de dekstre al {way_name}",

                "destination": "Enveturu de dekstre direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen",

                "name": "Turnigu malantauen al {way_name}",

                "destination": "Turnigu malantauen direkte al {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Pluu {modifier}",

                "name": "Pluu {modifier} al {way_name}",

                "destination": "Pluu {modifier} direkte al {destination}"

            },

            "straight": {

                "default": "Veturu rekten",

                "name": "Veturu rekten al {way_name}",

                "destination": "Veturu rekten direkte al {destination}"

            },

            "sharp left": {

                "default": "Turnigu ege maldekstren",

                "name": "Turnigu ege maldekstren al {way_name}",

                "destination": "Turnigu ege maldekstren direkte al {destination}"

            },

            "sharp right": {

                "default": "Turnigu ege dekstren",

                "name": "Turnigu ege dekstren al {way_name}",

                "destination": "Turnigu ege dekstren direkte al {destination}"

            },

            "slight left": {

                "default": "Pluu ete maldekstren",

                "name": "Pluu ete maldekstren al {way_name}",

                "destination": "Pluu ete maldekstren direkte al {destination}"

            },

            "slight right": {

                "default": "Pluu ete dekstren",

                "name": "Pluu ete dekstren al {way_name}",

                "destination": "Pluu ete dekstren direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen",

                "name": "Turnigu malantauen al {way_name}",

                "destination": "Turnigu malantauen direkte al {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Pluu {modifier}",

                "name": "Pluu {modifier} al {way_name}",

                "destination": "Pluu {modifier} direkte al {destination}"

            },

            "uturn": {

                "default": "Turnigu malantauen",

                "name": "Turnigu malantauen al {way_name}",

                "destination": "Turnigu malantauen direkte al {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Direktigu al enveturejo",

                "name": "Direktigu al enveturejo al {way_name}",

                "destination": "Direktigu al enveturejo direkte al {destination}",

                "exit": "Direktigu al elveturejo {exit}",

                "exit_destination": "Direktigu al elveturejo {exit} direkte al {destination}"

            },

            "left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}",

                "exit": "Direktigu al elveturejo {exit} ce maldekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce maldekstre direkte al {destination}"

            },

            "right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}",

                "exit": "Direktigu al {exit} elveturejo ce ldekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce dekstre direkte al {destination}"

            },

            "sharp left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}",

                "exit": "Direktigu al {exit} elveturejo ce maldekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce maldekstre direkte al {destination}"

            },

            "sharp right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}",

                "exit": "Direktigu al elveturejo {exit} ce dekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce dekstre direkte al {destination}"

            },

            "slight left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}",

                "exit": "Direktigu al {exit} elveturejo ce maldekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce maldekstre direkte al {destination}"

            },

            "slight right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}",

                "exit": "Direktigu al {exit} elveturejo ce ldekstre",

                "exit_destination": "Direktigu al elveturejo {exit} ce dekstre direkte al {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Direktigu al enveturejo",

                "name": "Direktigu al enveturejo al {way_name}",

                "destination": "Direktigu al enveturejo direkte al {destination}"

            },

            "left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}"

            },

            "right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}"

            },

            "sharp left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}"

            },

            "sharp right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}"

            },

            "slight left": {

                "default": "Direktigu al enveturejo ce maldekstre",

                "name": "Direktigu al enveturejo ce maldekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce maldekstre al {destination}"

            },

            "slight right": {

                "default": "Direktigu al enveturejo ce dekstre",

                "name": "Direktigu al enveturejo ce dekstre al {way_name}",

                "destination": "Direktigu al enveturejo ce dekstre al {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Enveturu trafikcirklegon",

                    "name": "Enveturu trafikcirklegon kaj elveturu al {way_name}",

                    "destination": "Enveturu trafikcirklegon kaj elveturu direkte al {destination}"

                },

                "name": {

                    "default": "Enveturu {rotary_name}",

                    "name": "Enveturu {rotary_name} kaj elveturu al {way_name}",

                    "destination": "Enveturu {rotary_name} kaj elveturu direkte al {destination}"

                },

                "exit": {

                    "default": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo",

                    "name": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo al {way_name}",

                    "destination": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo direkte al {destination}"

                },

                "name_exit": {

                    "default": "Enveturu {rotary_name} kaj sekve al {exit_number} elveturejo",

                    "name": "Enveturu {rotary_name} kaj sekve al {exit_number} elveturejo al {way_name}",

                    "destination": "Enveturu {rotary_name} kaj sekve al {exit_number} elveturejo direkte al {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo",

                    "name": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo al {way_name}",

                    "destination": "Enveturu trafikcirklegon kaj sekve al {exit_number} elveturejo direkte al {destination}"

                },

                "default": {

                    "default": "Enveturu trafikcirklegon",

                    "name": "Enveturu trafikcirklegon kaj elveturu al {way_name}",

                    "destination": "Enveturu trafikcirklegon kaj elveturu direkte al {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Veturu {modifier}",

                "name": "Veturu {modifier} al {way_name}",

                "destination": "Veturu {modifier} direkte al {destination}"

            },

            "left": {

                "default": "Turnigu maldekstren",

                "name": "Turnigu maldekstren al {way_name}",

                "destination": "Turnigu maldekstren direkte al {destination}"

            },

            "right": {

                "default": "Turnigu dekstren",

                "name": "Turnigu dekstren al {way_name}",

                "destination": "Turnigu dekstren direkte al {destination}"

            },

            "straight": {

                "default": "Pluu rekten",

                "name": "Veturu rekten al {way_name}",

                "destination": "Veturu rekten direkte al {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Elveturu trafikcirklegon",

                "name": "Elveturu trafikcirklegon al {way_name}",

                "destination": "Elveturu trafikcirklegon direkte al {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Eliru trafikcirklegon",

                "name": "Elveturu trafikcirklegon al {way_name}",

                "destination": "Elveturu trafikcirklegon direkte al {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Veturu {modifier}",

                "name": "Veturu {modifier} al {way_name}",

                "destination": "Veturu {modifier} direkte al {destination}"

            },

            "left": {

                "default": "Turnigu maldekstren",

                "name": "Turnigu maldekstren al {way_name}",

                "destination": "Turnigu maldekstren direkte al {destination}"

            },

            "right": {

                "default": "Turnigu dekstren",

                "name": "Turnigu dekstren al {way_name}",

                "destination": "Turnigu dekstren direkte al {destination}"

            },

            "straight": {

                "default": "Veturu rekten",

                "name": "Veturu rekten al {way_name}",

                "destination": "Veturu rekten direkte al {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Pluu rekten"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],27:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1�",

                "2": "2�",

                "3": "3�",

                "4": "4�",

                "5": "5�",

                "6": "6�",

                "7": "7�",

                "8": "8�",

                "9": "9�",

                "10": "10�"

            },

            "direction": {

                "north": "norte",

                "northeast": "noreste",

                "east": "este",

                "southeast": "sureste",

                "south": "sur",

                "southwest": "suroeste",

                "west": "oeste",

                "northwest": "noroeste"

            },

            "modifier": {

                "left": "a la izquierda",

                "right": "a la derecha",

                "sharp left": "cerrada a la izquierda",

                "sharp right": "cerrada a la derecha",

                "slight left": "ligeramente a la izquierda",

                "slight right": "ligeramente a la derecha",

                "straight": "recto",

                "uturn": "cambio de sentido"

            },

            "lanes": {

                "xo": "Mantente a la derecha",

                "ox": "Mantente a la izquierda",

                "xox": "Mantente en el medio",

                "oxo": "Mantente a la izquierda o a la derecha"

            }

        },

        "modes": {

            "ferry": {

                "default": "Coge el ferry",

                "name": "Coge el ferry {way_name}",

                "destination": "Coge el ferry hacia {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} y luego en {distance}, {instruction_two}",

            "two linked": "{instruction_one} y luego {instruction_two}",

            "one in distance": "A {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "salida {exit}"

        },

        "arrive": {

            "default": {

                "default": "Has llegado a tu {nth} destino",

                "upcoming": "Vas a llegar a tu {nth} destino",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}"

            },

            "left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "sharp left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "sharp right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "slight right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "slight left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "straight": {

                "default": "Has llegado a tu {nth} destino, en frente",

                "upcoming": "Vas a llegar a tu {nth} destino, en frente",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, en frente"

            }

        },

        "continue": {

            "default": {

                "default": "Gire {modifier}",

                "name": "Cruce {modifier} en {way_name}",

                "destination": "Gire {modifier} hacia {destination}",

                "exit": "Gire {modifier} en {way_name}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a en {way_name}",

                "destination": "Contin�a hacia {destination}",

                "distance": "Contin�a recto por {distance}",

                "namedistance": "Contin�a recto en {way_name} por {distance}"

            },

            "sharp left": {

                "default": "Gire a la izquierda",

                "name": "Gire a la izquierda en {way_name}",

                "destination": "Gire a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gire a la derecha",

                "name": "Gire a la derecha en {way_name}",

                "destination": "Gire a la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Gire a la izquierda",

                "name": "Doble levemente a la izquierda en {way_name}",

                "destination": "Gire a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Gire a la izquierda",

                "name": "Doble levemente a la derecha en {way_name}",

                "destination": "Gire a la izquierda hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido y contin�a en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Dir�gete al {direction}",

                "name": "Dir�gete al {direction} por {way_name}",

                "namedistance": "Dir�gete al {direction} en {way_name} por {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Al final de la calle gira {modifier}",

                "name": "Al final de la calle gira {modifier} por {way_name}",

                "destination": "Al final de la calle gira {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Al final de la calle contin�a recto",

                "name": "Al final de la calle contin�a recto por {way_name}",

                "destination": "Al final de la calle contin�a recto hacia {destination}"

            },

            "uturn": {

                "default": "Al final de la calle haz un cambio de sentido",

                "name": "Al final de la calle haz un cambio de sentido en {way_name}",

                "destination": "Al final de la calle haz un cambio de sentido hacia {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "Mantente {modifier} en el cruce",

                "name": "Mantente {modifier} por {way_name}",

                "destination": "Mantente {modifier} hacia {destination}"

            },

            "slight left": {

                "default": "Mantente a la izquierda en el cruce",

                "name": "Mantente a la izquierda por {way_name}",

                "destination": "Mantente a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Mantente a la derecha en el cruce",

                "name": "Mantente a la derecha por {way_name}",

                "destination": "Mantente a la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Gira la izquierda en el cruce",

                "name": "Gira a la izquierda por {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gira a la derecha en el cruce",

                "name": "Gira a la derecha por {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Incorp�rate {modifier}",

                "name": "Incorp�rate {modifier} por {way_name}",

                "destination": "Incorp�rate {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Incorp�rate",

                "name": "Incorp�rate por {way_name}",

                "destination": "Incorp�rate hacia {destination}"

            },

            "slight left": {

                "default": "Incorp�rate a la izquierda",

                "name": "Incorp�rate a la izquierda por {way_name}",

                "destination": "Incorp�rate a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Incorp�rate a la derecha",

                "name": "Incorp�rate a la derecha por {way_name}",

                "destination": "Incorp�rate a la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Incorp�rate a la izquierda",

                "name": "Incorp�rate a la izquierda por {way_name}",

                "destination": "Incorp�rate a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Incorp�rate a la derecha",

                "name": "Incorp�rate a la derecha por {way_name}",

                "destination": "Incorp�rate a la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Contin�a {modifier}",

                "name": "Contin�a {modifier} por {way_name}",

                "destination": "Contin�a {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a por {way_name}",

                "destination": "Contin�a hacia {destination}"

            },

            "sharp left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda por {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha por {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Contin�a ligeramente por la izquierda",

                "name": "Contin�a ligeramente por la izquierda por {way_name}",

                "destination": "Contin�a ligeramente por la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Contin�a ligeramente por la derecha",

                "name": "Contin�a ligeramente por la derecha por {way_name}",

                "destination": "Contin�a ligeramente por la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Contin�a {modifier}",

                "name": "Contin�a {modifier} por {way_name}",

                "destination": "Contin�a {modifier} hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Coge la cuesta abajo",

                "name": "Coge la cuesta abajo por {way_name}",

                "destination": "Coge la cuesta abajo hacia {destination}",

                "exit": "Coge la cuesta abajo {exit}",

                "exit_destination": "Coge la cuesta abajo {exit} hacia {destination}"

            },

            "left": {

                "default": "Coge la cuesta abajo de la izquierda",

                "name": "Coge la cuesta abajo de la izquierda por {way_name}",

                "destination": "Coge la cuesta abajo de la izquierda hacia {destination}",

                "exit": "Coge la cuesta abajo {exit} a tu izquierda",

                "exit_destination": "Coge la cuesta abajo {exit} a tu izquierda hacia {destination}"

            },

            "right": {

                "default": "Coge la cuesta abajo de la derecha",

                "name": "Coge la cuesta abajo de la derecha por {way_name}",

                "destination": "Coge la cuesta abajo de la derecha hacia {destination}",

                "exit": "Coge la cuesta abajo {exit}",

                "exit_destination": "Coge la cuesta abajo {exit} hacia {destination}"

            },

            "sharp left": {

                "default": "Coge la cuesta abajo de la izquierda",

                "name": "Coge la cuesta abajo de la izquierda por {way_name}",

                "destination": "Coge la cuesta abajo de la izquierda hacia {destination}",

                "exit": "Coge la cuesta abajo {exit} a tu izquierda",

                "exit_destination": "Coge la cuesta abajo {exit} a tu izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Coge la cuesta abajo de la derecha",

                "name": "Coge la cuesta abajo de la derecha por {way_name}",

                "destination": "Coge la cuesta abajo de la derecha hacia {destination}",

                "exit": "Coge la cuesta abajo {exit}",

                "exit_destination": "Coge la cuesta abajo {exit} hacia {destination}"

            },

            "slight left": {

                "default": "Coge la cuesta abajo de la izquierda",

                "name": "Coge la cuesta abajo de la izquierda por {way_name}",

                "destination": "Coge la cuesta abajo de la izquierda hacia {destination}",

                "exit": "Coge la cuesta abajo {exit} a tu izquierda",

                "exit_destination": "Coge la cuesta abajo�{exit} a tu izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Coge la cuesta abajo de la derecha",

                "name": "Coge la cuesta abajo de la derecha por {way_name}",

                "destination": "Coge la cuesta abajo de la derecha hacia {destination}",

                "exit": "Coge la cuesta abajo {exit}",

                "exit_destination": "Coge la cuesta abajo {exit} hacia {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Coge la cuesta",

                "name": "Coge la cuesta por {way_name}",

                "destination": "Coge la cuesta hacia {destination}"

            },

            "left": {

                "default": "Coge la cuesta de la izquierda",

                "name": "Coge la cuesta de la izquierda por {way_name}",

                "destination": "Coge la cuesta de la izquierda hacia {destination}"

            },

            "right": {

                "default": "Coge la cuesta de la derecha",

                "name": "Coge la cuesta de la derecha por {way_name}",

                "destination": "Coge la cuesta de la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Coge la cuesta de la izquierda",

                "name": "Coge la cuesta de la izquierda por {way_name}",

                "destination": "Coge la cuesta de la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Coge la cuesta de la derecha",

                "name": "Coge la cuesta de la derecha por {way_name}",

                "destination": "Coge la cuesta de la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Coge la cuesta de la izquierda",

                "name": "Coge la cuesta de la izquierda por {way_name}",

                "destination": "Coge la cuesta de la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Coge la cuesta de la derecha",

                "name": "Coge la cuesta de la derecha por {way_name}",

                "destination": "Coge la cuesta de la derecha hacia {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Incorp�rate en la rotonda",

                    "name": "En la rotonda sal por {way_name}",

                    "destination": "En la rotonda sal hacia {destination}"

                },

                "name": {

                    "default": "En {rotary_name}",

                    "name": "En {rotary_name} sal por {way_name}",

                    "destination": "En {rotary_name} sal hacia {destination}"

                },

                "exit": {

                    "default": "En la rotonda toma la {exit_number} salida",

                    "name": "En la rotonda toma la {exit_number} salida por {way_name}",

                    "destination": "En la rotonda toma la {exit_number} salida hacia {destination}"

                },

                "name_exit": {

                    "default": "En {rotary_name} toma la {exit_number} salida",

                    "name": "En {rotary_name} toma la {exit_number} salida por {way_name}",

                    "destination": "En {rotary_name} toma la {exit_number} salida hacia {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "En la rotonda toma la {exit_number} salida",

                    "name": "En la rotonda toma la {exit_number} salida por {way_name}",

                    "destination": "En la rotonda toma la {exit_number} salida hacia {destination}"

                },

                "default": {

                    "default": "Incorp�rate en la rotonda",

                    "name": "Incorp�rate en la rotonda y sal en {way_name}",

                    "destination": "Incorp�rate en la rotonda y sal hacia {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Siga {modifier}",

                "name": "Siga {modifier} en {way_name}",

                "destination": "Siga {modifier} hacia {destination}"

            },

            "left": {

                "default": "Gire a la izquierda",

                "name": "Gire a la izquierda en {way_name}",

                "destination": "Gire a la izquierda hacia {destination}"

            },

            "right": {

                "default": "Gire a la derecha",

                "name": "Gire a la derecha en {way_name}",

                "destination": "Gire a la derecha hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a recto por {way_name}",

                "destination": "Contin�a recto hacia {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Sal la rotonda",

                "name": "Toma la salida por {way_name}",

                "destination": "Toma la salida hacia {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Sal la rotonda",

                "name": "Toma la salida por {way_name}",

                "destination": "Toma la salida hacia {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Gira {modifier}",

                "name": "Gira {modifier} por {way_name}",

                "destination": "Gira {modifier} hacia {destination}"

            },

            "left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda por {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha por {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a recto por {way_name}",

                "destination": "Contin�a recto hacia {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Contin�a recto"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],28:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1�",

                "2": "2�",

                "3": "3�",

                "4": "4�",

                "5": "5�",

                "6": "6�",

                "7": "7�",

                "8": "8�",

                "9": "9�",

                "10": "10�"

            },

            "direction": {

                "north": "norte",

                "northeast": "noreste",

                "east": "este",

                "southeast": "sureste",

                "south": "sur",

                "southwest": "suroeste",

                "west": "oeste",

                "northwest": "noroeste"

            },

            "modifier": {

                "left": "izquierda",

                "right": "derecha",

                "sharp left": "cerrada a la izquierda",

                "sharp right": "cerrada a la derecha",

                "slight left": "levemente a la izquierda",

                "slight right": "levemente a la derecha",

                "straight": "recto",

                "uturn": "cambio de sentido"

            },

            "lanes": {

                "xo": "Mantente a la derecha",

                "ox": "Mantente a la izquierda",

                "xox": "Mantente en el medio",

                "oxo": "Mantente a la izquierda o derecha"

            }

        },

        "modes": {

            "ferry": {

                "default": "Coge el ferry",

                "name": "Coge el ferry {way_name}",

                "destination": "Coge el ferry a {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} y luego a {distance}, {instruction_two}",

            "two linked": "{instruction_one} y luego {instruction_two}",

            "one in distance": "A {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "salida {exit}"

        },

        "arrive": {

            "default": {

                "default": "Has llegado a tu {nth} destino",

                "upcoming": "Vas a llegar a tu {nth} destino",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}"

            },

            "left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "sharp left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "sharp right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "slight right": {

                "default": "Has llegado a tu {nth} destino, a la derecha",

                "upcoming": "Vas a llegar a tu {nth} destino, a la derecha",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la derecha"

            },

            "slight left": {

                "default": "Has llegado a tu {nth} destino, a la izquierda",

                "upcoming": "Vas a llegar a tu {nth} destino, a la izquierda",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, a la izquierda"

            },

            "straight": {

                "default": "Has llegado a tu {nth} destino, en frente",

                "upcoming": "Vas a llegar a tu {nth} destino, en frente",

                "short": "Has llegado",

                "short-upcoming": "Vas a llegar",

                "named": "Has llegado a {waypoint_name}, en frente"

            }

        },

        "continue": {

            "default": {

                "default": "Gira a {modifier}",

                "name": "Cruza a la{modifier}  en {way_name}",

                "destination": "Gira a {modifier} hacia {destination}",

                "exit": "Gira a {modifier} en {way_name}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a en {way_name}",

                "destination": "Contin�a hacia {destination}",

                "distance": "Contin�a recto por {distance}",

                "namedistance": "Contin�a recto en {way_name} por {distance}"

            },

            "sharp left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha en {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Gira a la izquierda",

                "name": "Dobla levemente a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Gira a la izquierda",

                "name": "Dobla levemente a la derecha en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido y contin�a en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Ve a {direction}",

                "name": "Ve a {direction} en {way_name}",

                "namedistance": "Ve a {direction} en {way_name} por {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Gira  a {modifier}",

                "name": "Gira a {modifier} en {way_name}",

                "destination": "Gira a {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a recto en {way_name}",

                "destination": "Contin�a recto hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido al final de la via",

                "name": "Haz un cambio de sentido en {way_name} al final de la via",

                "destination": "Haz un cambio de sentido hacia {destination} al final de la via"

            }

        },

        "fork": {

            "default": {

                "default": "Mantente  {modifier} en el cruza",

                "name": "Mantente {modifier} en {way_name}",

                "destination": "Mantente {modifier} hacia {destination}"

            },

            "slight left": {

                "default": "Mantente a la izquierda en el cruza",

                "name": "Mantente a la izquierda en {way_name}",

                "destination": "Mantente a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Mantente a la derecha en el cruza",

                "name": "Mantente a la derecha en {way_name}",

                "destination": "Mantente a la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Gira a la izquierda en el cruza",

                "name": "Gira a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gira a la derecha en el cruza",

                "name": "Gira a la derecha en {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Incorp�rate a {modifier}",

                "name": "Incorp�rate a {modifier} en {way_name}",

                "destination": "Incorp�rate a {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Incorp�rate",

                "name": "Incorp�rate a {way_name}",

                "destination": "Incorp�rate hacia {destination}"

            },

            "slight left": {

                "default": "Incorp�rate a la izquierda",

                "name": "Incorp�rate a la izquierda en {way_name}",

                "destination": "Incorp�rate a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Incorp�rate a la derecha",

                "name": "Incorp�rate a la derecha en {way_name}",

                "destination": "Incorp�rate a la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Incorp�rate a la izquierda",

                "name": "Incorp�rate a la izquierda en {way_name}",

                "destination": "Incorp�rate a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Incorp�rate a la derecha",

                "name": "Incorp�rate a la derecha en {way_name}",

                "destination": "Incorp�rate a la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Contin�a {modifier}",

                "name": "Contin�a {modifier} en {way_name}",

                "destination": "Contin�a {modifier} hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a en {way_name}",

                "destination": "Contin�a hacia {destination}"

            },

            "sharp left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha en {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Contin�a levemente a la izquierda",

                "name": "Contin�a levemente a la izquierda en {way_name}",

                "destination": "Contin�a levemente a la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Contin�a levemente a la derecha",

                "name": "Contin�a levemente a la derecha en {way_name}",

                "destination": "Contin�a levemente a la derecha hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Contin�a {modifier}",

                "name": "Contin�a {modifier} en {way_name}",

                "destination": "Contin�a {modifier} hacia {destination}"

            },

            "uturn": {

                "default": "Haz un cambio de sentido",

                "name": "Haz un cambio de sentido en {way_name}",

                "destination": "Haz un cambio de sentido hacia {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Toma la salida",

                "name": "Toma la salida en {way_name}",

                "destination": "Toma la salida hacia {destination}",

                "exit": "Toma la salida {exit}",

                "exit_destination": "Toma la salida {exit} hacia {destination}"

            },

            "left": {

                "default": "Toma la salida en la izquierda",

                "name": "Toma la salida en la izquierda en {way_name}",

                "destination": "Toma la salida en la izquierda en {destination}",

                "exit": "Toma la salida {exit} en la izquierda",

                "exit_destination": "Toma la salida {exit} en la izquierda hacia {destination}"

            },

            "right": {

                "default": "Toma la salida en la derecha",

                "name": "Toma la salida en la derecha en {way_name}",

                "destination": "Toma la salida en la derecha hacia {destination}",

                "exit": "Toma la salida {exit} en la derecha",

                "exit_destination": "Toma la salida {exit} en la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Ve cuesta abajo en la izquierda",

                "name": "Ve cuesta abajo en la izquierda en {way_name}",

                "destination": "Ve cuesta abajo en la izquierda hacia {destination}",

                "exit": "Toma la salida {exit} en la izquierda",

                "exit_destination": "Toma la salida {exit} en la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Ve cuesta abajo en la derecha",

                "name": "Ve cuesta abajo en la derecha en {way_name}",

                "destination": "Ve cuesta abajo en la derecha hacia {destination}",

                "exit": "Toma la salida {exit} en la derecha",

                "exit_destination": "Toma la salida {exit} en la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Ve cuesta abajo en la izquierda",

                "name": "Ve cuesta abajo en la izquierda en {way_name}",

                "destination": "Ve cuesta abajo en la izquierda hacia {destination}",

                "exit": "Toma la salida {exit} en la izquierda",

                "exit_destination": "Toma la salida {exit} en la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Toma la salida en la derecha",

                "name": "Toma la salida en la derecha en {way_name}",

                "destination": "Toma la salida en la derecha hacia {destination}",

                "exit": "Toma la salida {exit} en la derecha",

                "exit_destination": "Toma la salida {exit} en la derecha hacia {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Toma la rampa",

                "name": "Toma la rampa en {way_name}",

                "destination": "Toma la rampa hacia {destination}"

            },

            "left": {

                "default": "Toma la rampa en la izquierda",

                "name": "Toma la rampa en la izquierda en {way_name}",

                "destination": "Toma la rampa en la izquierda hacia {destination}"

            },

            "right": {

                "default": "Toma la rampa en la derecha",

                "name": "Toma la rampa en la derecha en {way_name}",

                "destination": "Toma la rampa en la derecha hacia {destination}"

            },

            "sharp left": {

                "default": "Toma la rampa en la izquierda",

                "name": "Toma la rampa en la izquierda en {way_name}",

                "destination": "Toma la rampa en la izquierda hacia {destination}"

            },

            "sharp right": {

                "default": "Toma la rampa en la derecha",

                "name": "Toma la rampa en la derecha en {way_name}",

                "destination": "Toma la rampa en la derecha hacia {destination}"

            },

            "slight left": {

                "default": "Toma la rampa en la izquierda",

                "name": "Toma la rampa en la izquierda en {way_name}",

                "destination": "Toma la rampa en la izquierda hacia {destination}"

            },

            "slight right": {

                "default": "Toma la rampa en la derecha",

                "name": "Toma la rampa en la derecha en {way_name}",

                "destination": "Toma la rampa en la derecha hacia {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Entra en la rotonda",

                    "name": "Entra en la rotonda y sal en {way_name}",

                    "destination": "Entra en la rotonda y sal hacia {destination}"

                },

                "name": {

                    "default": "Entra en {rotary_name}",

                    "name": "Entra en {rotary_name} y sal en {way_name}",

                    "destination": "Entra en {rotary_name} y sal hacia {destination}"

                },

                "exit": {

                    "default": "Entra en la rotonda y toma la {exit_number} salida",

                    "name": "Entra en la rotonda y toma la {exit_number} salida a {way_name}",

                    "destination": "Entra en la rotonda y toma la {exit_number} salida hacia {destination}"

                },

                "name_exit": {

                    "default": "Entra en {rotary_name} y coge la {exit_number} salida",

                    "name": "Entra en {rotary_name} y coge la {exit_number} salida en {way_name}",

                    "destination": "Entra en {rotary_name} y coge la {exit_number} salida hacia {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Entra en la rotonda y toma la {exit_number} salida",

                    "name": "Entra en la rotonda y toma la {exit_number} salida a {way_name}",

                    "destination": "Entra en la rotonda y toma la {exit_number} salida hacia {destination}"

                },

                "default": {

                    "default": "Entra en la rotonda",

                    "name": "Entra en la rotonda y sal en {way_name}",

                    "destination": "Entra en la rotonda y sal hacia {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Sigue {modifier}",

                "name": "Sigue {modifier} en {way_name}",

                "destination": "Sigue {modifier} hacia {destination}"

            },

            "left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha en {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "straight": {

                "default": "Contin�a recto",

                "name": "Contin�a recto en {way_name}",

                "destination": "Contin�a recto hacia {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Sal la rotonda",

                "name": "Sal la rotonda en {way_name}",

                "destination": "Sal la rotonda hacia {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Sal la rotonda",

                "name": "Sal la rotonda en {way_name}",

                "destination": "Sal la rotonda hacia {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Sigue {modifier}",

                "name": "Sigue {modifier} en {way_name}",

                "destination": "Sigue {modifier} hacia {destination}"

            },

            "left": {

                "default": "Gira a la izquierda",

                "name": "Gira a la izquierda en {way_name}",

                "destination": "Gira a la izquierda hacia {destination}"

            },

            "right": {

                "default": "Gira a la derecha",

                "name": "Gira a la derecha en {way_name}",

                "destination": "Gira a la derecha hacia {destination}"

            },

            "straight": {

                "default": "Ve recto",

                "name": "Ve recto en {way_name}",

                "destination": "Ve recto hacia {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Contin�a recto"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],29:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1.",

                "2": "2.",

                "3": "3.",

                "4": "4.",

                "5": "5.",

                "6": "6.",

                "7": "7.",

                "8": "8.",

                "9": "9.",

                "10": "10."

            },

            "direction": {

                "north": "pohjoiseen",

                "northeast": "koilliseen",

                "east": "it��n",

                "southeast": "kaakkoon",

                "south": "etel��n",

                "southwest": "lounaaseen",

                "west": "l�nteen",

                "northwest": "luoteeseen"

            },

            "modifier": {

                "left": "vasemmall(e/a)",

                "right": "oikeall(e/a)",

                "sharp left": "jyrk�sti vasempaan",

                "sharp right": "jyrk�sti oikeaan",

                "slight left": "loivasti vasempaan",

                "slight right": "loivasti oikeaan",

                "straight": "suoraan eteenp�in",

                "uturn": "U-k��nn�s"

            },

            "lanes": {

                "xo": "Pysy oikealla",

                "ox": "Pysy vasemmalla",

                "xox": "Pysy keskell�",

                "oxo": "Pysy vasemmalla tai oikealla"

            }

        },

        "modes": {

            "ferry": {

                "default": "Aja lautalle",

                "name": "Aja lautalle {way_name}",

                "destination": "Aja lautalle, jonka m��r�np�� on {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, sitten {distance} p��st�, {instruction_two}",

            "two linked": "{instruction_one}, sitten {instruction_two}",

            "one in distance": "{distance} p��st�, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "{exit}"

        },

        "arrive": {

            "default": {

                "default": "Olet saapunut {nth} m��r�np��h�si",

                "upcoming": "Saavut {nth} m��r�np��h�si",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}"

            },

            "left": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on vasemmalla puolellasi"

            },

            "right": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on oikealla puolellasi"

            },

            "sharp left": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on vasemmalla puolellasi"

            },

            "sharp right": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on oikealla puolellasi"

            },

            "slight right": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on oikealla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on oikealla puolellasi"

            },

            "slight left": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "upcoming": "Saavut {nth} m��r�np��h�si, joka on vasemmalla puolellasi",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on vasemmalla puolellasi"

            },

            "straight": {

                "default": "Olet saapunut {nth} m��r�np��h�si, joka on suoraan edess�si",

                "upcoming": "Saavut {nth} m��r�np��h�si, suoraan edess�",

                "short": "Olet saapunut",

                "short-upcoming": "Saavut",

                "named": "Olet saapunut m��r�np��h�n {waypoint_name}, joka on suoraan edess�si"

            }

        },

        "continue": {

            "default": {

                "default": "K��nny {modifier}",

                "name": "K��nny {modifier} pysy�ksesi tiell� {way_name}",

                "destination": "K��nny {modifier} suuntana {destination}",

                "exit": "K��nny {modifier} tielle {way_name}"

            },

            "straight": {

                "default": "Jatka suoraan eteenp�in",

                "name": "Jatka suoraan pysy�ksesi tiell� {way_name}",

                "destination": "Jatka suuntana {destination}",

                "distance": "Jatka suoraan {distance}",

                "namedistance": "Jatka tiell� {way_name} {distance}"

            },

            "sharp left": {

                "default": "Jatka jyrk�sti vasempaan",

                "name": "Jatka jyrk�sti vasempaan pysy�ksesi tiell� {way_name}",

                "destination": "Jatka jyrk�sti vasempaan suuntana {destination}"

            },

            "sharp right": {

                "default": "Jatka jyrk�sti oikeaan",

                "name": "Jatka jyrk�sti oikeaan pysy�ksesi tiell� {way_name}",

                "destination": "Jatka jyrk�sti oikeaan suuntana {destination}"

            },

            "slight left": {

                "default": "Jatka loivasti vasempaan",

                "name": "Jatka loivasti vasempaan pysy�ksesi tiell� {way_name}",

                "destination": "Jatka loivasti vasempaan suuntana {destination}"

            },

            "slight right": {

                "default": "Jatka loivasti oikeaan",

                "name": "Jatka loivasti oikeaan pysy�ksesi tiell� {way_name}",

                "destination": "Jatka loivasti oikeaan suuntana {destination}"

            },

            "uturn": {

                "default": "Tee U-k��nn�s",

                "name": "Tee U-k��nn�s ja jatka tiet� {way_name}",

                "destination": "Tee U-k��nn�s suuntana {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Aja {direction}",

                "name": "Aja tiet� {way_name} {direction}",

                "namedistance": "Aja {distance} {direction} tiet� {way_name} "

            }

        },

        "end of road": {

            "default": {

                "default": "K��nny {modifier}",

                "name": "K��nny {modifier} tielle {way_name}",

                "destination": "K��nny {modifier} suuntana {destination}"

            },

            "straight": {

                "default": "Jatka suoraan eteenp�in",

                "name": "Jatka suoraan eteenp�in tielle {way_name}",

                "destination": "Jatka suoraan eteenp�in suuntana {destination}"

            },

            "uturn": {

                "default": "Tien p��ss� tee U-k��nn�s",

                "name": "Tien p��ss� tee U-k��nn�s tielle {way_name}",

                "destination": "Tien p��ss� tee U-k��nn�s suuntana {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "Jatka tienhaarassa {modifier}",

                "name": "Jatka {modifier} tielle {way_name}",

                "destination": "Jatka {modifier} suuntana {destination}"

            },

            "slight left": {

                "default": "Pysy vasemmalla tienhaarassa",

                "name": "Pysy vasemmalla tielle {way_name}",

                "destination": "Pysy vasemmalla suuntana {destination}"

            },

            "slight right": {

                "default": "Pysy oikealla tienhaarassa",

                "name": "Pysy oikealla tielle {way_name}",

                "destination": "Pysy oikealla suuntana {destination}"

            },

            "sharp left": {

                "default": "K��nny tienhaarassa jyrk�sti vasempaan",

                "name": "K��nny tienhaarassa jyrk�sti vasempaan tielle {way_name}",

                "destination": "K��nny tienhaarassa jyrk�sti vasempaan suuntana {destination}"

            },

            "sharp right": {

                "default": "K��nny tienhaarassa jyrk�sti oikeaan",

                "name": "K��nny tienhaarassa jyrk�sti oikeaan tielle {way_name}",

                "destination": "K��nny tienhaarassa jyrk�sti oikeaan suuntana {destination}"

            },

            "uturn": {

                "default": "Tee U-k��nn�s",

                "name": "Tee U-k��nn�s tielle {way_name}",

                "destination": "Tee U-k��nn�s suuntana {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Liity {modifier}",

                "name": "Liity {modifier}, tielle {way_name}",

                "destination": "Liity {modifier}, suuntana {destination}"

            },

            "straight": {

                "default": "Liity",

                "name": "Liity tielle {way_name}",

                "destination": "Liity suuntana {destination}"

            },

            "slight left": {

                "default": "Liity vasemmalle",

                "name": "Liity vasemmalle, tielle {way_name}",

                "destination": "Liity vasemmalle, suuntana {destination}"

            },

            "slight right": {

                "default": "Liity oikealle",

                "name": "Liity oikealle, tielle {way_name}",

                "destination": "Liity oikealle, suuntana {destination}"

            },

            "sharp left": {

                "default": "Liity vasemmalle",

                "name": "Liity vasemmalle, tielle {way_name}",

                "destination": "Liity vasemmalle, suuntana {destination}"

            },

            "sharp right": {

                "default": "Liity oikealle",

                "name": "Liity oikealle, tielle {way_name}",

                "destination": "Liity oikealle, suuntana {destination}"

            },

            "uturn": {

                "default": "Tee U-k��nn�s",

                "name": "Tee U-k��nn�s tielle {way_name}",

                "destination": "Tee U-k��nn�s suuntana {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Jatka {modifier}",

                "name": "Jatka {modifier} tielle {way_name}",

                "destination": "Jatka {modifier} suuntana {destination}"

            },

            "straight": {

                "default": "Jatka suoraan eteenp�in",

                "name": "Jatka tielle {way_name}",

                "destination": "Jatka suuntana {destination}"

            },

            "sharp left": {

                "default": "K��nny jyrk�sti vasempaan",

                "name": "K��nny jyrk�sti vasempaan tielle {way_name}",

                "destination": "K��nny jyrk�sti vasempaan suuntana {destination}"

            },

            "sharp right": {

                "default": "K��nny jyrk�sti oikeaan",

                "name": "K��nny jyrk�sti oikeaan tielle {way_name}",

                "destination": "K��nny jyrk�sti oikeaan suuntana {destination}"

            },

            "slight left": {

                "default": "Jatka loivasti vasempaan",

                "name": "Jatka loivasti vasempaan tielle {way_name}",

                "destination": "Jatka loivasti vasempaan suuntana {destination}"

            },

            "slight right": {

                "default": "Jatka loivasti oikeaan",

                "name": "Jatka loivasti oikeaan tielle {way_name}",

                "destination": "Jatka loivasti oikeaan suuntana {destination}"

            },

            "uturn": {

                "default": "Tee U-k��nn�s",

                "name": "Tee U-k��nn�s tielle {way_name}",

                "destination": "Tee U-k��nn�s suuntana {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Jatka {modifier}",

                "name": "Jatka {modifier} tielle {way_name}",

                "destination": "Jatka {modifier} suuntana {destination}"

            },

            "uturn": {

                "default": "Tee U-k��nn�s",

                "name": "Tee U-k��nn�s tielle {way_name}",

                "destination": "Tee U-k��nn�s suuntana {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Aja erkanemiskaistalle",

                "name": "Aja erkanemiskaistaa tielle {way_name}",

                "destination": "Aja erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit}",

                "exit_destination": "Ota poistuminen {exit}, suuntana {destination}"

            },

            "left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} vasemmalla",

                "exit_destination": "Ota poistuminen {exit} vasemmalla, suuntana {destination}"

            },

            "right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} oikealla",

                "exit_destination": "Ota poistuminen {exit} oikealla, suuntana {destination}"

            },

            "sharp left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} vasemmalla",

                "exit_destination": "Ota poistuminen {exit} vasemmalla, suuntana {destination}"

            },

            "sharp right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} oikealla",

                "exit_destination": "Ota poistuminen {exit} oikealla, suuntana {destination}"

            },

            "slight left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} vasemmalla",

                "exit_destination": "Ota poistuminen {exit} vasemmalla, suuntana {destination}"

            },

            "slight right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}",

                "exit": "Ota poistuminen {exit} oikealla",

                "exit_destination": "Ota poistuminen {exit} oikealla, suuntana {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Aja erkanemiskaistalle",

                "name": "Aja erkanemiskaistaa tielle {way_name}",

                "destination": "Aja erkanemiskaistalle suuntana {destination}"

            },

            "left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}"

            },

            "right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}"

            },

            "sharp left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}"

            },

            "sharp right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}"

            },

            "slight left": {

                "default": "Aja vasemmalla olevalle erkanemiskaistalle",

                "name": "Aja vasemmalla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja vasemmalla olevalle erkanemiskaistalle suuntana {destination}"

            },

            "slight right": {

                "default": "Aja oikealla olevalle erkanemiskaistalle",

                "name": "Aja oikealla olevaa erkanemiskaistaa tielle {way_name}",

                "destination": "Aja oikealla olevalle erkanemiskaistalle suuntana {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Aja liikenneympyr��n",

                    "name": "Aja liikenneympyr��n ja valitse erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n ja valitse erkanemiskaista suuntana {destination}"

                },

                "name": {

                    "default": "Aja liikenneympyr��n {rotary_name}",

                    "name": "Aja liikenneympyr��n {rotary_name} ja valitse erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n {rotary_name} ja valitse erkanemiskaista suuntana {destination}"

                },

                "exit": {

                    "default": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista",

                    "name": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista suuntana {destination}"

                },

                "name_exit": {

                    "default": "Aja liikenneympyr��n {rotary_name} ja valitse {exit_number} erkanemiskaista",

                    "name": "Aja liikenneympyr��n {rotary_name} ja valitse {exit_number} erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n {rotary_name} ja valitse {exit_number} erkanemiskaista suuntana {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista",

                    "name": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n ja valitse {exit_number} erkanemiskaista suuntana {destination}"

                },

                "default": {

                    "default": "Aja liikenneympyr��n",

                    "name": "Aja liikenneympyr��n ja valitse erkanemiskaista tielle {way_name}",

                    "destination": "Aja liikenneympyr��n ja valitse erkanemiskaista suuntana {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "K��nny {modifier}",

                "name": "K��nny {modifier} tielle {way_name}",

                "destination": "K��nny {modifier} suuntana {destination}"

            },

            "left": {

                "default": "K��nny vasempaan",

                "name": "K��nny vasempaan tielle {way_name}",

                "destination": "K��nny vasempaan suuntana {destination}"

            },

            "right": {

                "default": "K��nny oikeaan",

                "name": "K��nny oikeaan tielle {way_name}",

                "destination": "K��nny oikeaan suuntana {destination}"

            },

            "straight": {

                "default": "Jatka suoraan eteenp�in",

                "name": "Jatka suoraan eteenp�in tielle {way_name}",

                "destination": "Jatka suoraan eteenp�in suuntana {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Poistu liikenneympyr�st�",

                "name": "Poistu liikenneympyr�st� tielle {way_name}",

                "destination": "Poistu liikenneympyr�st� suuntana {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Poistu liikenneympyr�st�",

                "name": "Poistu liikenneympyr�st� tielle {way_name}",

                "destination": "Poistu liikenneympyr�st� suuntana {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "K��nny {modifier}",

                "name": "K��nny {modifier} tielle {way_name}",

                "destination": "K��nny {modifier} suuntana {destination}"

            },

            "left": {

                "default": "K��nny vasempaan",

                "name": "K��nny vasempaan tielle {way_name}",

                "destination": "K��nny vasempaan suuntana {destination}"

            },

            "right": {

                "default": "K��nny oikeaan",

                "name": "K��nny oikeaan tielle {way_name}",

                "destination": "K��nny oikeaan suuntana {destination}"

            },

            "straight": {

                "default": "Aja suoraan eteenp�in",

                "name": "Aja suoraan eteenp�in tielle {way_name}",

                "destination": "Aja suoraan eteenp�in suuntana {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Jatka suoraan eteenp�in"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],30:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "premi�re",

                "2": "seconde",

                "3": "troisi�me",

                "4": "quatri�me",

                "5": "cinqui�me",

                "6": "sixi�me",

                "7": "septi�me",

                "8": "huiti�me",

                "9": "neuvi�me",

                "10": "dixi�me"

            },

            "direction": {

                "north": "le nord",

                "northeast": "le nord-est",

                "east": "l'est",

                "southeast": "le sud-est",

                "south": "le sud",

                "southwest": "le sud-ouest",

                "west": "l'ouest",

                "northwest": "le nord-ouest"

            },

            "modifier": {

                "left": "� gauche",

                "right": "� droite",

                "sharp left": "franchement � gauche",

                "sharp right": "franchement � droite",

                "slight left": "l�g�rement � gauche",

                "slight right": "l�g�rement � droite",

                "straight": "tout droit",

                "uturn": "demi-tour"

            },

            "lanes": {

                "xo": "Tenir la droite",

                "ox": "Tenir la gauche",

                "xox": "Rester au milieu",

                "oxo": "Tenir la gauche ou la droite"

            }

        },

        "modes": {

            "ferry": {

                "default": "Prendre le ferry",

                "name": "Prendre le ferry {way_name:article}",

                "destination": "Prendre le ferry en direction {destination:preposition}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, puis, dans {distance}, {instruction_two}",

            "two linked": "{instruction_one}, puis {instruction_two}",

            "one in distance": "Dans {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "sortie n°{exit}"

        },

        "arrive": {

            "default": {

                "default": "Vous �tes arriv� � votre {nth} destination",

                "upcoming": "Vous arriverez � votre {nth} destination",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� {waypoint_name:arrival}"

            },

            "left": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la gauche",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la gauche",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, sur la gauche"

            },

            "right": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la droite",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la droite",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� �  {waypoint_name:arrival}, sur la droite"

            },

            "sharp left": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la gauche",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la gauche",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, sur la gauche"

            },

            "sharp right": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la droite",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la droite",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, sur la droite"

            },

            "slight right": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la droite",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la droite",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous arriverez",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, sur la droite"

            },

            "slight left": {

                "default": "Vous �tes arriv� � votre {nth} destination, sur la gauche",

                "upcoming": "Vous arriverez � votre {nth} destination, sur la gauche",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous �tes arriv�",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, sur la gauche"

            },

            "straight": {

                "default": "Vous �tes arriv� � votre {nth} destination, droit devant",

                "upcoming": "Vous arriverez � votre {nth} destination, droit devant",

                "short": "Vous �tes arriv�",

                "short-upcoming": "Vous �tes arriv�",

                "named": "Vous �tes arriv� {waypoint_name:arrival}, droit devant"

            }

        },

        "continue": {

            "default": {

                "default": "Tourner {modifier}",

                "name": "Tourner {modifier} pour rester sur {way_name:article}",

                "destination": "Tourner {modifier} en direction {destination:preposition}",

                "exit": "Tourner {modifier} sur {way_name:article}"

            },

            "straight": {

                "default": "Continuer tout droit",

                "name": "Continuer tout droit pour rester sur {way_name:article}",

                "destination": "Continuer tout droit en direction {destination:preposition}",

                "distance": "Continuer tout droit sur {distance}",

                "namedistance": "Continuer sur {way_name:article} sur {distance}"

            },

            "sharp left": {

                "default": "Tourner franchement � gauche",

                "name": "Tourner franchement � gauche pour rester sur {way_name:article}",

                "destination": "Tourner franchement � gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "Tourner franchement � droite",

                "name": "Tourner franchement � droite pour rester sur {way_name:article}",

                "destination": "Tourner franchement � droite en direction {destination:preposition}"

            },

            "slight left": {

                "default": "Tourner l�g�rement � gauche",

                "name": "Tourner l�g�rement � gauche pour rester sur {way_name:article}",

                "destination": "Tourner l�g�rement � gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "Tourner l�g�rement � droite",

                "name": "Tourner l�g�rement � droite pour rester sur {way_name:article}",

                "destination": "Tourner l�g�rement � droite en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour",

                "name": "Faire demi-tour et continuer sur {way_name:article}",

                "destination": "Faire demi-tour en direction {destination:preposition}"

            }

        },

        "depart": {

            "default": {

                "default": "Se diriger vers {direction}",

                "name": "Se diriger vers {direction} sur {way_name:article}",

                "namedistance": "Se diriger vers {direction} sur {way_name:article} sur {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Tourner {modifier}",

                "name": "Tourner {modifier} sur {way_name:article}",

                "destination": "Tourner {modifier} en direction {destination:preposition}"

            },

            "straight": {

                "default": "Continuer tout droit",

                "name": "Continuer tout droit sur {way_name:article}",

                "destination": "Continuer tout droit en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour � la fin de la route",

                "name": "Faire demi-tour � la fin {way_name:preposition}",

                "destination": "Faire demi-tour � la fin de la route en direction {destination:preposition}"

            }

        },

        "fork": {

            "default": {

                "default": "Tenir {modifier} � l'embranchement",

                "name": "Tenir {modifier} sur {way_name:article}",

                "destination": "Tenir {modifier} en direction {destination:preposition}"

            },

            "slight left": {

                "default": "Tenir la gauche � l'embranchement",

                "name": "Tenir la gauche sur {way_name:article}",

                "destination": "Tenir la gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "Tenir la droite � l'embranchement",

                "name": "Tenir la droite sur {way_name:article}",

                "destination": "Tenir la droite en direction {destination:preposition}"

            },

            "sharp left": {

                "default": "Tourner franchement � gauche � l'embranchement",

                "name": "Tourner franchement � gauche sur {way_name:article}",

                "destination": "Tourner franchement � gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "Tourner franchement � droite � l'embranchement",

                "name": "Tourner franchement � droite sur {way_name:article}",

                "destination": "Tourner franchement � droite en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour",

                "name": "Faire demi-tour sur {way_name:article}",

                "destination": "Faire demi-tour en direction {destination:preposition}"

            }

        },

        "merge": {

            "default": {

                "default": "S'ins�rer {modifier}",

                "name": "S'ins�rer {modifier} sur {way_name:article}",

                "destination": "S'ins�rer {modifier} en direction {destination:preposition}"

            },

            "straight": {

                "default": "S'ins�rer",

                "name": "S'ins�rer sur {way_name:article}",

                "destination": "S'ins�rer en direction {destination:preposition}"

            },

            "slight left": {

                "default": "S'ins�rer l�g�rement � gauche",

                "name": "S'ins�rer l�g�rement � gauche sur {way_name:article}",

                "destination": "S'ins�rer l�g�rement � gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "S'ins�rer l�g�rement � droite",

                "name": "S'ins�rer l�g�rement � droite sur {way_name:article}",

                "destination": "S'ins�rer � droite en direction {destination:preposition}"

            },

            "sharp left": {

                "default": "S'ins�rer � gauche",

                "name": "S'ins�rer � gauche sur {way_name:article}",

                "destination": "S'ins�rer � gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "S'ins�rer � droite",

                "name": "S'ins�rer � droite sur {way_name:article}",

                "destination": "S'ins�rer � droite en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour",

                "name": "Faire demi-tour sur {way_name:article}",

                "destination": "Faire demi-tour en direction {destination:preposition}"

            }

        },

        "new name": {

            "default": {

                "default": "Continuer {modifier}",

                "name": "Continuer {modifier} sur {way_name:article}",

                "destination": "Continuer {modifier} en direction {destination:preposition}"

            },

            "straight": {

                "default": "Continuer tout droit",

                "name": "Continuer tout droit sur {way_name:article}",

                "destination": "Continuer tout droit en direction {destination:preposition}"

            },

            "sharp left": {

                "default": "Tourner franchement � gauche",

                "name": "Tourner franchement � gauche sur {way_name:article}",

                "destination": "Tourner franchement � gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "Tourner franchement � droite",

                "name": "Tourner franchement � droite sur {way_name:article}",

                "destination": "Tourner franchement � droite en direction {destination:preposition}"

            },

            "slight left": {

                "default": "Continuer l�g�rement � gauche",

                "name": "Continuer l�g�rement � gauche sur {way_name:article}",

                "destination": "Continuer l�g�rement � gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "Continuer l�g�rement � droite",

                "name": "Continuer l�g�rement � droite sur {way_name:article}",

                "destination": "Continuer l�g�rement � droite en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour",

                "name": "Faire demi-tour sur {way_name:article}",

                "destination": "Faire demi-tour en direction {destination:preposition}"

            }

        },

        "notification": {

            "default": {

                "default": "Continuer {modifier}",

                "name": "Continuer {modifier} sur {way_name:article}",

                "destination": "Continuer {modifier} en direction {destination:preposition}"

            },

            "uturn": {

                "default": "Faire demi-tour",

                "name": "Faire demi-tour sur {way_name:article}",

                "destination": "Faire demi-tour en direction {destination:preposition}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Prendre la sortie",

                "name": "Prendre la sortie sur {way_name:article}",

                "destination": "Prendre la sortie en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit}",

                "exit_destination": "Prendre la sortie {exit} en direction {destination:preposition}"

            },

            "left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la gauche",

                "exit_destination": "Prendre la sortie {exit} sur la gauche en direction {destination:preposition}"

            },

            "right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la droite",

                "exit_destination": "Prendre la sortie {exit} sur la droite en direction {destination:preposition}"

            },

            "sharp left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la gauche",

                "exit_destination": "Prendre la sortie {exit} sur la gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la droite",

                "exit_destination": "Prendre la sortie {exit} sur la droite en direction {destination:preposition}"

            },

            "slight left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la gauche",

                "exit_destination": "Prendre la sortie {exit} sur la gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}",

                "exit": "Prendre la sortie {exit} sur la droite",

                "exit_destination": "Prendre la sortie {exit} sur la droite en direction {destination:preposition}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Prendre la sortie",

                "name": "Prendre la sortie sur {way_name:article}",

                "destination": "Prendre la sortie en direction {destination:preposition}"

            },

            "left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}"

            },

            "right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}"

            },

            "sharp left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}"

            },

            "sharp right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}"

            },

            "slight left": {

                "default": "Prendre la sortie � gauche",

                "name": "Prendre la sortie � gauche sur {way_name:article}",

                "destination": "Prendre la sortie � gauche en direction {destination:preposition}"

            },

            "slight right": {

                "default": "Prendre la sortie � droite",

                "name": "Prendre la sortie � droite sur {way_name:article}",

                "destination": "Prendre la sortie � droite en direction {destination:preposition}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Prendre le rond-point",

                    "name": "Prendre le rond-point, puis sortir sur {way_name:article}",

                    "destination": "Prendre le rond-point, puis sortir en direction {destination:preposition}"

                },

                "name": {

                    "default": "Prendre {rotary_name:rotary}",

                    "name": "Prendre {rotary_name:rotary}, puis sortir par {way_name:article}",

                    "destination": "Prendre {rotary_name:rotary}, puis sortir en direction {destination:preposition}"

                },

                "exit": {

                    "default": "Prendre le rond-point, puis la {exit_number} sortie",

                    "name": "Prendre le rond-point, puis la {exit_number} sortie sur {way_name:article}",

                    "destination": "Prendre le rond-point, puis la {exit_number} sortie en direction {destination:preposition}"

                },

                "name_exit": {

                    "default": "Prendre {rotary_name:rotary}, puis la {exit_number} sortie",

                    "name": "Prendre {rotary_name:rotary}, puis la {exit_number} sortie sur {way_name:article}",

                    "destination": "Prendre {rotary_name:rotary}, puis la {exit_number} sortie en direction {destination:preposition}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Prendre le rond-point, puis la {exit_number} sortie",

                    "name": "Prendre le rond-point, puis la {exit_number} sortie sur {way_name:article}",

                    "destination": "Prendre le rond-point, puis la {exit_number} sortie en direction {destination:preposition}"

                },

                "default": {

                    "default": "Prendre le rond-point",

                    "name": "Prendre le rond-point, puis sortir sur {way_name:article}",

                    "destination": "Prendre le rond-point, puis sortir en direction {destination:preposition}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Tourner {modifier}",

                "name": "Tourner {modifier} sur {way_name:article}",

                "destination": "Tourner {modifier} en direction {destination:preposition}"

            },

            "left": {

                "default": "Tourner � gauche",

                "name": "Tourner � gauche sur {way_name:article}",

                "destination": "Tourner � gauche en direction {destination:preposition}"

            },

            "right": {

                "default": "Tourner � droite",

                "name": "Tourner � droite sur {way_name:article}",

                "destination": "Tourner � droite en direction {destination:preposition}"

            },

            "straight": {

                "default": "Continuer tout droit",

                "name": "Continuer tout droit sur {way_name:article}",

                "destination": "Continuer tout droit en direction {destination:preposition}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Sortir du rond-point",

                "name": "Sortir du rond-point sur {way_name:article}",

                "destination": "Sortir du rond-point en direction {destination:preposition}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Sortir du rond-point",

                "name": "Sortir du rond-point sur {way_name:article}",

                "destination": "Sortir du rond-point en direction {destination:preposition}"

            }

        },

        "turn": {

            "default": {

                "default": "Tourner {modifier}",

                "name": "Tourner {modifier} sur {way_name:article}",

                "destination": "Tourner {modifier} en direction {destination:preposition}"

            },

            "left": {

                "default": "Tourner � gauche",

                "name": "Tourner � gauche sur {way_name:article}",

                "destination": "Tourner � gauche en direction {destination:preposition}"

            },

            "right": {

                "default": "Tourner � droite",

                "name": "Tourner � droite sur {way_name:article}",

                "destination": "Tourner � droite en direction {destination:preposition}"

            },

            "straight": {

                "default": "Aller tout droit",

                "name": "Aller tout droit sur {way_name:article}",

                "destination": "Aller tout droit en direction {destination:preposition}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Continuer tout droit"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],31:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "??????",

                "2": "????",

                "3": "??????",

                "4": "??????",

                "5": "??????",

                "6": "?????",

                "7": "??????",

                "8": "??????",

                "9": "??????",

                "10": "??????"

            },

            "direction": {

                "north": "????",

                "northeast": "???? ????",

                "east": "????",

                "southeast": "???? ????",

                "south": "????",

                "southwest": "???? ????",

                "west": "????",

                "northwest": "???? ????"

            },

            "modifier": {

                "left": "?????",

                "right": "?????",

                "sharp left": "??? ?????",

                "sharp right": "??? ?????",

                "slight left": "??? ?????",

                "slight right": "??? ?????",

                "straight": "???",

                "uturn": "????? ????"

            },

            "lanes": {

                "xo": "????? ?????",

                "ox": "????? ?????",

                "xox": "???? ????? ??????",

                "oxo": "????? ????? ?? ?????"

            }

        },

        "modes": {

            "ferry": {

                "default": "??? ?? ???????",

                "name": "??? ?? ??????? {way_name}",

                "destination": "??? ?? ??????? ?????? {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, ???, ????{distance}, {instruction_two}",

            "two linked": "{instruction_one}, ??? {instruction_two}",

            "one in distance": "???? {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "????? {exit}"

        },

        "arrive": {

            "default": {

                "default": "???? ?? ???? ?{nth} ???",

                "upcoming": "??? ???? ?? ???? ?{nth} ???",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name}"

            },

            "left": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "right": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "sharp left": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "sharp right": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "slight right": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "slight left": {

                "default": "???? ?? ???? ?{nth} ??? ??????",

                "upcoming": "??? ???? ?? ???? ?{nth} ??? ??????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name} ??? ??????"

            },

            "straight": {

                "default": "???? ?? ???? ?{nth} ???, ?????",

                "upcoming": "??? ???? ?? ???? ?{nth} ???, ?????",

                "short": "????",

                "short-upcoming": "????",

                "named": "???? ?? {waypoint_name}, ?????"

            }

        },

        "continue": {

            "default": {

                "default": "??? {modifier}",

                "name": "??? {modifier} ??? ?????? ?{way_name}",

                "destination": "??? {modifier} ?????? {destination}",

                "exit": "??? {modifier} ?? {way_name}"

            },

            "straight": {

                "default": "???? ???",

                "name": "???? ??? ??? ?????? ?? {way_name}",

                "destination": "???? ?????? {destination}",

                "distance": "???? ??? ????? {distance}",

                "namedistance": "???? ?? {way_name} ????? {distance}"

            },

            "sharp left": {

                "default": "??? ????? ?????",

                "name": "??? ????? ????? ??? ?????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "sharp right": {

                "default": "??? ????? ?????",

                "name": "??? ????? ????? ??? ?????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "slight left": {

                "default": "??? ???? ?????",

                "name": "??? ???? ????? ??? ?????? ?? {way_name}",

                "destination": "??? ???? ????? ?????? {destination}"

            },

            "slight right": {

                "default": "??? ???? ?????",

                "name": "??? ???? ????? ??? ?????? ?? {way_name}",

                "destination": "??? ???? ????? ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ????",

                "name": "??? ????? ???? ????? ?? {way_name}",

                "destination": "??? ????? ???? ?????? {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "??????? {direction}",

                "name": "??????? {direction} ?? {way_name}",

                "namedistance": "??????? {direction} ?? {way_name} ????? {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "??? {modifier}",

                "name": "??? {modifier} ?? {way_name}",

                "destination": "??? {modifier} ?????? {destination}"

            },

            "straight": {

                "default": "???? ???",

                "name": "???? ??? ?? {way_name}",

                "destination": "???? ??? ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ???? ???? ????",

                "name": "??? ????? ???? ?? {way_name} ???? ????",

                "destination": "??? ????? ???? ?????? {destination} ???? ????"

            }

        },

        "fork": {

            "default": {

                "default": "????? {modifier} ????????",

                "name": "????? {modifier} ?? {way_name}",

                "destination": "????? {modifier} ?????? {destination}"

            },

            "slight left": {

                "default": "????? ????? ????????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "slight right": {

                "default": "????? ????? ????????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "sharp left": {

                "default": "??? ????? ????? ????????",

                "name": "??? ????? ????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "sharp right": {

                "default": "??? ????? ????? ????????",

                "name": "??? ????? ????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ????",

                "name": "??? ????? ???? ?? {way_name}",

                "destination": "??? ????? ???? ?????? {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "????? {modifier}",

                "name": "????? {modifier} ?? {way_name}",

                "destination": "????? {modifier} ?????? {destination}"

            },

            "straight": {

                "default": "?????",

                "name": "????? ?? {way_name}",

                "destination": "????? ?????? {destination}"

            },

            "slight left": {

                "default": "????? ?????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "slight right": {

                "default": "????? ?????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "sharp left": {

                "default": "????? ?????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "sharp right": {

                "default": "????? ?????",

                "name": "????? ????? ?? {way_name}",

                "destination": "????? ????? ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ????",

                "name": "??? ????? ???? ?? {way_name}",

                "destination": "??? ????? ???? ?????? {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "???? {modifier}",

                "name": "???? {modifier} ?? {way_name}",

                "destination": "???? {modifier} ?????? {destination}"

            },

            "straight": {

                "default": "???? ???",

                "name": "???? ?? {way_name}",

                "destination": "???? ?????? {destination}"

            },

            "sharp left": {

                "default": "??? ????? ?????",

                "name": "??? ????? ????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "sharp right": {

                "default": "??? ????? ?????",

                "name": "??? ????? ????? ?? {way_name}",

                "destination": "??? ????? ????? ?????? {destination}"

            },

            "slight left": {

                "default": "???? ?????? ??? ?????",

                "name": "???? ?????? ??? ????? ?? {way_name}",

                "destination": "???? ?????? ??? ????? ?????? {destination}"

            },

            "slight right": {

                "default": "???? ?????? ??? ?????",

                "name": "???? ?????? ??? ????? ?? {way_name}",

                "destination": "???? ?????? ??? ????? ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ????",

                "name": "??? ????? ???? ?? {way_name}",

                "destination": "??? ????? ???? ?????? {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "???? {modifier}",

                "name": "???? {modifier} ?? {way_name}",

                "destination": "???? {modifier} ?????? {destination}"

            },

            "uturn": {

                "default": "??? ????? ????",

                "name": "??? ????? ???? ?? {way_name}",

                "destination": "??? ????? ???? ?????? {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "?? ??????",

                "name": "?? ?????? ?? {way_name}",

                "destination": "?? ?????? ?????? {destination}",

                "exit": "?? ?????? {exit}",

                "exit_destination": "?? ?????? {exit} ?????? {destination}"

            },

            "left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            },

            "right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            },

            "sharp left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            },

            "sharp right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            },

            "slight left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            },

            "slight right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}",

                "exit": "?? ?????? {exit} ??????",

                "exit_destination": "?? ?????? {exit} ?????? ?????? {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "?? ??????",

                "name": "?? ?????? ?? {way_name}",

                "destination": "?? ?????? ?????? {destination}"

            },

            "left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            },

            "right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            },

            "sharp left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            },

            "sharp right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            },

            "slight left": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            },

            "slight right": {

                "default": "?? ?????? ???????",

                "name": "?? ?????? ??????? ?? {way_name}",

                "destination": "?? ?????? ??????? ?????? {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "????? ????? ??????",

                    "name": "????? ????? ?????? ??? ?? {way_name}",

                    "destination": "????? ????? ?????? ??? ?????? {destination}"

                },

                "name": {

                    "default": "????? ?{rotary_name}",

                    "name": "????? ?{rotary_name} ??? ?? {way_name}",

                    "destination": "????? ?{rotary_name} ??? ?????? {destination}"

                },

                "exit": {

                    "default": "????? ????? ?????? ??? ?????? {exit_number}",

                    "name": "????? ????? ?????? ??? ?????? {exit_number} ?{way_name}",

                    "destination": "????? ????? ?????? ??? ?????? {exit_number} ?????? {destination}"

                },

                "name_exit": {

                    "default": "????? ?{rotary_name} ??? ?????? ?{exit_number}",

                    "name": "????? ?{rotary_name} ??? ?????? ?{exit_number} ?{way_name}",

                    "destination": "????? ?{rotary_name} ??? ?????? ?{exit_number} ?????? {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "????? ????? ?????? ??? ?????? {exit_number}",

                    "name": "????? ????? ?????? ??? ?????? {exit_number} ?{way_name}",

                    "destination": "????? ????? ?????? ??? ?????? {exit_number} ?????? {destination}"

                },

                "default": {

                    "default": "????? ????? ??????",

                    "name": "????? ????? ?????? ??? ?? {way_name}",

                    "destination": "????? ????? ?????? ??? ?????? {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "??? {modifier}",

                "name": "??? {modifier} ?? {way_name}",

                "destination": "??? {modifier} ?????? {destination}"

            },

            "left": {

                "default": "??? ?????",

                "name": "??? ????? ?{way_name}",

                "destination": "??? ????? ?????? {destination}"

            },

            "right": {

                "default": "??? ?????",

                "name": "??? ????? ?{way_name}",

                "destination": "??? ????? ?????? {destination}"

            },

            "straight": {

                "default": "???? ???",

                "name": "???? ??? ?? {way_name}",

                "destination": "???? ??? ?????? {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "?? ????? ??????",

                "name": "?? ????? ?????? ?{way_name}",

                "destination": "?? ????? ?????? ?????? {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "?? ????? ??????",

                "name": "?? ????? ?????? ?{way_name}",

                "destination": "?? ????? ?????? ?????? {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "??? {modifier}",

                "name": "??? {modifier} ?? {way_name}",

                "destination": "??? {modifier} ?????? {destination}"

            },

            "left": {

                "default": "??? ?????",

                "name": "??? ????? ?{way_name}",

                "destination": "??? ????? ?????? {destination}"

            },

            "right": {

                "default": "??? ?????",

                "name": "??? ????? ?{way_name}",

                "destination": "??? ????? ?????? {destination}"

            },

            "straight": {

                "default": "???? ???",

                "name": "???? ??? ?{way_name}",

                "destination": "???? ??? ?????? {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "???? ???"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],32:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1",

                "2": "2",

                "3": "3",

                "4": "4",

                "5": "5",

                "6": "6",

                "7": "7",

                "8": "8",

                "9": "9",

                "10": "10"

            },

            "direction": {

                "north": "utara",

                "northeast": "timur laut",

                "east": "timur",

                "southeast": "tenggara",

                "south": "selatan",

                "southwest": "barat daya",

                "west": "barat",

                "northwest": "barat laut"

            },

            "modifier": {

                "left": "kiri",

                "right": "kanan",

                "sharp left": "tajam kiri",

                "sharp right": "tajam kanan",

                "slight left": "agak ke kiri",

                "slight right": "agak ke kanan",

                "straight": "lurus",

                "uturn": "putar balik"

            },

            "lanes": {

                "xo": "Tetap di kanan",

                "ox": "Tetap di kiri",

                "xox": "Tetap di tengah",

                "oxo": "Tetap di kiri atau kanan"

            }

        },

        "modes": {

            "ferry": {

                "default": "Naik ferry",

                "name": "Naik ferry di {way_name}",

                "destination": "Naik ferry menuju {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, then, in {distance}, {instruction_two}",

            "two linked": "{instruction_one}, then {instruction_two}",

            "one in distance": "In {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "Anda telah tiba di tujuan ke-{nth}",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}"

            },

            "left": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kiri"

            },

            "right": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kanan"

            },

            "sharp left": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kiri"

            },

            "sharp right": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kanan"

            },

            "slight right": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kanan",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kanan"

            },

            "slight left": {

                "default": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, di sebelah kiri",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, di sebelah kiri"

            },

            "straight": {

                "default": "Anda telah tiba di tujuan ke-{nth}, lurus saja",

                "upcoming": "Anda telah tiba di tujuan ke-{nth}, lurus saja",

                "short": "Anda telah tiba di tujuan ke-{nth}",

                "short-upcoming": "Anda telah tiba di tujuan ke-{nth}",

                "named": "Anda telah tiba di {waypoint_name}, lurus saja"

            }

        },

        "continue": {

            "default": {

                "default": "Belok {modifier}",

                "name": "Terus {modifier} ke {way_name}",

                "destination": "Belok {modifier} menuju {destination}",

                "exit": "Belok {modifier} ke {way_name}"

            },

            "straight": {

                "default": "Lurus terus",

                "name": "Terus ke {way_name}",

                "destination": "Terus menuju {destination}",

                "distance": "Continue straight for {distance}",

                "namedistance": "Continue on {way_name} for {distance}"

            },

            "sharp left": {

                "default": "Belok kiri tajam",

                "name": "Make a sharp left to stay on {way_name}",

                "destination": "Belok kiri tajam menuju {destination}"

            },

            "sharp right": {

                "default": "Belok kanan tajam",

                "name": "Make a sharp right to stay on {way_name}",

                "destination": "Belok kanan tajam menuju {destination}"

            },

            "slight left": {

                "default": "Tetap agak di kiri",

                "name": "Tetap agak di kiri ke {way_name}",

                "destination": "Tetap agak di kiri menuju {destination}"

            },

            "slight right": {

                "default": "Tetap agak di kanan",

                "name": "Tetap agak di kanan ke {way_name}",

                "destination": "Tetap agak di kanan menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik",

                "name": "Putar balik ke arah {way_name}",

                "destination": "Putar balik menuju {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Arah {direction}",

                "name": "Arah {direction} di {way_name}",

                "namedistance": "Head {direction} on {way_name} for {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Belok {modifier}",

                "name": "Belok {modifier} ke {way_name}",

                "destination": "Belok {modifier} menuju {destination}"

            },

            "straight": {

                "default": "Lurus terus",

                "name": "Tetap lurus ke {way_name} ",

                "destination": "Tetap lurus menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik di akhir jalan",

                "name": "Putar balik di {way_name} di akhir jalan",

                "destination": "Putar balik menuju {destination} di akhir jalan"

            }

        },

        "fork": {

            "default": {

                "default": "Tetap {modifier} di pertigaan",

                "name": "Tetap {modifier} di pertigaan ke {way_name}",

                "destination": "Tetap {modifier} di pertigaan menuju {destination}"

            },

            "slight left": {

                "default": "Tetap di kiri pada pertigaan",

                "name": "Tetap di kiri pada pertigaan ke arah {way_name}",

                "destination": "Tetap di kiri pada pertigaan menuju {destination}"

            },

            "slight right": {

                "default": "Tetap di kanan pada pertigaan",

                "name": "Tetap di kanan pada pertigaan ke arah {way_name}",

                "destination": "Tetap di kanan pada pertigaan menuju {destination}"

            },

            "sharp left": {

                "default": "Belok kiri pada pertigaan",

                "name": "Belok kiri tajam ke arah {way_name}",

                "destination": "Belok kiri tajam menuju {destination}"

            },

            "sharp right": {

                "default": "Belok kanan pada pertigaan",

                "name": "Belok kanan tajam ke arah {way_name}",

                "destination": "Belok kanan tajam menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik",

                "name": "Putar balik ke arah {way_name}",

                "destination": "Putar balik menuju {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Bergabung {modifier}",

                "name": "Bergabung {modifier} ke arah {way_name}",

                "destination": "Bergabung {modifier} menuju {destination}"

            },

            "straight": {

                "default": "Bergabung lurus",

                "name": "Bergabung lurus ke arah {way_name}",

                "destination": "Bergabung lurus menuju {destination}"

            },

            "slight left": {

                "default": "Bergabung di kiri",

                "name": "Bergabung di kiri ke arah {way_name}",

                "destination": "Bergabung di kiri menuju {destination}"

            },

            "slight right": {

                "default": "Bergabung di kanan",

                "name": "Bergabung di kanan ke arah {way_name}",

                "destination": "Bergabung di kanan menuju {destination}"

            },

            "sharp left": {

                "default": "Bergabung di kiri",

                "name": "Bergabung di kiri ke arah {way_name}",

                "destination": "Bergabung di kiri menuju {destination}"

            },

            "sharp right": {

                "default": "Bergabung di kanan",

                "name": "Bergabung di kanan ke arah {way_name}",

                "destination": "Bergabung di kanan menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik",

                "name": "Putar balik ke arah {way_name}",

                "destination": "Putar balik menuju {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Lanjutkan {modifier}",

                "name": "Lanjutkan {modifier} menuju {way_name}",

                "destination": "Lanjutkan {modifier} menuju {destination}"

            },

            "straight": {

                "default": "Lurus terus",

                "name": "Terus ke {way_name}",

                "destination": "Terus menuju {destination}"

            },

            "sharp left": {

                "default": "Belok kiri tajam",

                "name": "Belok kiri tajam ke arah {way_name}",

                "destination": "Belok kiri tajam menuju {destination}"

            },

            "sharp right": {

                "default": "Belok kanan tajam",

                "name": "Belok kanan tajam ke arah {way_name}",

                "destination": "Belok kanan tajam menuju {destination}"

            },

            "slight left": {

                "default": "Lanjut dengan agak ke kiri",

                "name": "Lanjut dengan agak di kiri ke {way_name}",

                "destination": "Tetap agak di kiri menuju {destination}"

            },

            "slight right": {

                "default": "Tetap agak di kanan",

                "name": "Tetap agak di kanan ke {way_name}",

                "destination": "Tetap agak di kanan menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik",

                "name": "Putar balik ke arah {way_name}",

                "destination": "Putar balik menuju {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Lanjutkan {modifier}",

                "name": "Lanjutkan {modifier} menuju {way_name}",

                "destination": "Lanjutkan {modifier} menuju {destination}"

            },

            "uturn": {

                "default": "Putar balik",

                "name": "Putar balik ke arah {way_name}",

                "destination": "Putar balik menuju {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Ambil jalan melandai",

                "name": "Ambil jalan melandai ke {way_name}",

                "destination": "Ambil jalan melandai menuju {destination}",

                "exit": "Take exit {exit}",

                "exit_destination": "Take exit {exit} towards {destination}"

            },

            "left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan menuju {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            },

            "sharp left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "sharp right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan menuju {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            },

            "slight left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}",

                "exit": "Take exit {exit} on the left",

                "exit_destination": "Take exit {exit} on the left towards {destination}"

            },

            "slight right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan  menuju {destination}",

                "exit": "Take exit {exit} on the right",

                "exit_destination": "Take exit {exit} on the right towards {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Ambil jalan melandai",

                "name": "Ambil jalan melandai ke {way_name}",

                "destination": "Ambil jalan melandai menuju {destination}"

            },

            "left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}"

            },

            "right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan  menuju {destination}"

            },

            "sharp left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}"

            },

            "sharp right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan  menuju {destination}"

            },

            "slight left": {

                "default": "Ambil jalan yang melandai di sebelah kiri",

                "name": "Ambil jalan melandai di sebelah kiri ke arah {way_name}",

                "destination": "Ambil jalan melandai di sebelah kiri menuju {destination}"

            },

            "slight right": {

                "default": "Ambil jalan melandai di sebelah kanan",

                "name": "Ambil jalan melandai di sebelah kanan ke {way_name}",

                "destination": "Ambil jalan melandai di sebelah kanan  menuju {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Masuk bundaran",

                    "name": "Masuk bundaran dan keluar arah {way_name}",

                    "destination": "Masuk bundaran dan keluar menuju {destination}"

                },

                "name": {

                    "default": "Masuk {rotary_name}",

                    "name": "Masuk {rotary_name} dan keluar arah {way_name}",

                    "destination": "Masuk {rotary_name} dan keluar menuju {destination}"

                },

                "exit": {

                    "default": "Masuk bundaran dan ambil jalan keluar {exit_number}",

                    "name": "Masuk bundaran dan ambil jalan keluar {exit_number} arah {way_name}",

                    "destination": "Masuk bundaran dan ambil jalan keluar {exit_number} menuju {destination}"

                },

                "name_exit": {

                    "default": "Masuk {rotary_name} dan ambil jalan keluar {exit_number}",

                    "name": "Masuk {rotary_name} dan ambil jalan keluar {exit_number} arah {way_name}",

                    "destination": "Masuk {rotary_name} dan ambil jalan keluar {exit_number} menuju {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Masuk bundaran dan ambil jalan keluar {exit_number}",

                    "name": "Masuk bundaran dan ambil jalan keluar {exit_number} arah {way_name}",

                    "destination": "Masuk bundaran dan ambil jalan keluar {exit_number} menuju {destination}"

                },

                "default": {

                    "default": "Masuk bundaran",

                    "name": "Masuk bundaran dan keluar arah {way_name}",

                    "destination": "Masuk bundaran dan keluar menuju {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Lakukan {modifier}",

                "name": "Lakukan {modifier} ke arah {way_name}",

                "destination": "Lakukan {modifier} menuju {destination}"

            },

            "left": {

                "default": "Belok kiri",

                "name": "Belok kiri ke {way_name}",

                "destination": "Belok kiri menuju {destination}"

            },

            "right": {

                "default": "Belok kanan",

                "name": "Belok kanan ke {way_name}",

                "destination": "Belok kanan menuju {destination}"

            },

            "straight": {

                "default": "Lurus terus",

                "name": "Tetap lurus ke {way_name} ",

                "destination": "Tetap lurus menuju {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Lakukan {modifier}",

                "name": "Lakukan {modifier} ke arah {way_name}",

                "destination": "Lakukan {modifier} menuju {destination}"

            },

            "left": {

                "default": "Belok kiri",

                "name": "Belok kiri ke {way_name}",

                "destination": "Belok kiri menuju {destination}"

            },

            "right": {

                "default": "Belok kanan",

                "name": "Belok kanan ke {way_name}",

                "destination": "Belok kanan menuju {destination}"

            },

            "straight": {

                "default": "Lurus terus",

                "name": "Tetap lurus ke {way_name} ",

                "destination": "Tetap lurus menuju {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Lakukan {modifier}",

                "name": "Lakukan {modifier} ke arah {way_name}",

                "destination": "Lakukan {modifier} menuju {destination}"

            },

            "left": {

                "default": "Belok kiri",

                "name": "Belok kiri ke {way_name}",

                "destination": "Belok kiri menuju {destination}"

            },

            "right": {

                "default": "Belok kanan",

                "name": "Belok kanan ke {way_name}",

                "destination": "Belok kanan menuju {destination}"

            },

            "straight": {

                "default": "Lurus",

                "name": "Lurus arah {way_name}",

                "destination": "Lurus menuju {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Lakukan {modifier}",

                "name": "Lakukan {modifier} ke arah {way_name}",

                "destination": "Lakukan {modifier} menuju {destination}"

            },

            "left": {

                "default": "Belok kiri",

                "name": "Belok kiri ke {way_name}",

                "destination": "Belok kiri menuju {destination}"

            },

            "right": {

                "default": "Belok kanan",

                "name": "Belok kanan ke {way_name}",

                "destination": "Belok kanan menuju {destination}"

            },

            "straight": {

                "default": "Lurus",

                "name": "Lurus arah {way_name}",

                "destination": "Lurus menuju {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Lurus terus"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],33:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1�",

                "2": "2�",

                "3": "3�",

                "4": "4�",

                "5": "5�",

                "6": "6�",

                "7": "7�",

                "8": "8�",

                "9": "9�",

                "10": "10�"

            },

            "direction": {

                "north": "nord",

                "northeast": "nord-est",

                "east": "est",

                "southeast": "sud-est",

                "south": "sud",

                "southwest": "sud-ovest",

                "west": "ovest",

                "northwest": "nord-ovest"

            },

            "modifier": {

                "left": "sinistra",

                "right": "destra",

                "sharp left": "sinistra",

                "sharp right": "destra",

                "slight left": "sinistra leggermente",

                "slight right": "destra leggermente",

                "straight": "dritto",

                "uturn": "inversione a U"

            },

            "lanes": {

                "xo": "Mantieni la destra",

                "ox": "Mantieni la sinistra",

                "xox": "Rimani in mezzo",

                "oxo": "Mantieni la destra o la sinistra"

            }

        },

        "modes": {

            "ferry": {

                "default": "Prendi il traghetto",

                "name": "Prendi il traghetto {way_name}",

                "destination": "Prendi il traghetto verso {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, poi tra {distance},{instruction_two}",

            "two linked": "{instruction_one}, poi {instruction_two}",

            "one in distance": "tra {distance} {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "Sei arrivato alla tua {nth} destinazione",

                "upcoming": "Sei arrivato alla tua {nth} destinazione",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "Sei arrivato a {waypoint_name}"

            },

            "left": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla sinistra"

            },

            "right": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla destra"

            },

            "sharp left": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla sinistra"

            },

            "sharp right": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla destra"

            },

            "slight right": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla destra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla destra"

            },

            "slight left": {

                "default": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "upcoming": "sei arrivato alla tua {nth} destinazione, sulla sinistra",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, sulla sinistra"

            },

            "straight": {

                "default": "sei arrivato alla tua {nth} destinazione, si trova davanti a te",

                "upcoming": "sei arrivato alla tua {nth} destinazione, si trova davanti a te",

                "short": "Sei arrivato alla tua {nth} destinazione",

                "short-upcoming": "Sei arrivato alla tua {nth} destinazione",

                "named": "sei arrivato a {waypoint_name}, si trova davanti a te"

            }

        },

        "continue": {

            "default": {

                "default": "Gira a {modifier}",

                "name": "Gira a {modifier} per stare su {way_name}",

                "destination": "Gira a {modifier} verso {destination}",

                "exit": "Gira a {modifier} in {way_name}"

            },

            "straight": {

                "default": "Continua dritto",

                "name": "Continua dritto per stare su {way_name}",

                "destination": "Continua verso {destination}",

                "distance": "Continua dritto per {distance}",

                "namedistance": "Continua su {way_name} per {distance}"

            },

            "sharp left": {

                "default": "Svolta a sinistra",

                "name": "Fai una stretta curva a sinistra per stare su {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Svolta a destra",

                "name": "Fau una stretta curva a destra per stare su {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "slight left": {

                "default": "Fai una leggera curva a sinistra",

                "name": "Fai una leggera curva a sinistra per stare su {way_name}",

                "destination": "Fai una leggera curva a sinistra verso {destination}"

            },

            "slight right": {

                "default": "Fai una leggera curva a destra",

                "name": "Fai una leggera curva a destra per stare su {way_name}",

                "destination": "Fai una leggera curva a destra verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U",

                "name": "Fai un'inversione ad U poi continua su {way_name}",

                "destination": "Fai un'inversione a U verso {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Continua verso {direction}",

                "name": "Continua verso {direction} in {way_name}",

                "namedistance": "Head {direction} on {way_name} for {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Gira a {modifier}",

                "name": "Gira a {modifier} in {way_name}",

                "destination": "Gira a {modifier} verso {destination}"

            },

            "straight": {

                "default": "Continua dritto",

                "name": "Continua dritto in {way_name}",

                "destination": "Continua dritto verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U alla fine della strada",

                "name": "Fai un'inversione a U in {way_name} alla fine della strada",

                "destination": "Fai un'inversione a U verso {destination} alla fine della strada"

            }

        },

        "fork": {

            "default": {

                "default": "Mantieni la {modifier} al bivio",

                "name": "Mantieni la {modifier} al bivio in {way_name}",

                "destination": "Mantieni la {modifier} al bivio verso {destination}"

            },

            "slight left": {

                "default": "Mantieni la sinistra al bivio",

                "name": "Mantieni la sinistra al bivio in {way_name}",

                "destination": "Mantieni la sinistra al bivio verso {destination}"

            },

            "slight right": {

                "default": "Mantieni la destra al bivio",

                "name": "Mantieni la destra al bivio in {way_name}",

                "destination": "Mantieni la destra al bivio verso {destination}"

            },

            "sharp left": {

                "default": "Svolta a sinistra al bivio",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Svolta a destra al bivio",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U",

                "name": "Fai un'inversione a U in {way_name}",

                "destination": "Fai un'inversione a U verso {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Immettiti a {modifier}",

                "name": "Immettiti {modifier} in {way_name}",

                "destination": "Immettiti {modifier} verso {destination}"

            },

            "straight": {

                "default": "Immettiti a dritto",

                "name": "Immettiti dritto in {way_name}",

                "destination": "Immettiti dritto verso {destination}"

            },

            "slight left": {

                "default": "Immettiti a sinistra",

                "name": "Immettiti a sinistra in {way_name}",

                "destination": "Immettiti a sinistra verso {destination}"

            },

            "slight right": {

                "default": "Immettiti a destra",

                "name": "Immettiti a destra in {way_name}",

                "destination": "Immettiti a destra verso {destination}"

            },

            "sharp left": {

                "default": "Immettiti a sinistra",

                "name": "Immettiti a sinistra in {way_name}",

                "destination": "Immettiti a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Immettiti a destra",

                "name": "Immettiti a destra in {way_name}",

                "destination": "Immettiti a destra verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U",

                "name": "Fai un'inversione a U in {way_name}",

                "destination": "Fai un'inversione a U verso {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Continua a {modifier}",

                "name": "Continua a {modifier} in {way_name}",

                "destination": "Continua a {modifier} verso {destination}"

            },

            "straight": {

                "default": "Continua dritto",

                "name": "Continua in {way_name}",

                "destination": "Continua verso {destination}"

            },

            "sharp left": {

                "default": "Svolta a sinistra",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Svolta a destra",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "slight left": {

                "default": "Continua leggermente a sinistra",

                "name": "Continua leggermente a sinistra in {way_name}",

                "destination": "Continua leggermente a sinistra verso {destination}"

            },

            "slight right": {

                "default": "Continua leggermente a destra",

                "name": "Continua leggermente a destra in {way_name} ",

                "destination": "Continua leggermente a destra verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U",

                "name": "Fai un'inversione a U in {way_name}",

                "destination": "Fai un'inversione a U verso {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Continua a {modifier}",

                "name": "Continua a {modifier} in {way_name}",

                "destination": "Continua a {modifier} verso {destination}"

            },

            "uturn": {

                "default": "Fai un'inversione a U",

                "name": "Fai un'inversione a U in {way_name}",

                "destination": "Fai un'inversione a U verso {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Prendi la rampa",

                "name": "Prendi la rampa in {way_name}",

                "destination": "Prendi la rampa verso {destination}",

                "exit": "Prendi l'uscita {exit}",

                "exit_destination": "Prendi l'uscita  {exit} verso {destination}"

            },

            "left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}",

                "exit": "Prendi l'uscita {exit} a sinistra",

                "exit_destination": "Prendi la {exit}  uscita a sinistra verso {destination}"

            },

            "right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}",

                "exit": "Prendi la {exit} uscita a destra",

                "exit_destination": "Prendi la {exit} uscita a destra verso {destination}"

            },

            "sharp left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}",

                "exit": "Prendi l'uscita {exit} a sinistra",

                "exit_destination": "Prendi la {exit}  uscita a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}",

                "exit": "Prendi la {exit} uscita a destra",

                "exit_destination": "Prendi la {exit} uscita a destra verso {destination}"

            },

            "slight left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}",

                "exit": "Prendi l'uscita {exit} a sinistra",

                "exit_destination": "Prendi la {exit}  uscita a sinistra verso {destination}"

            },

            "slight right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}",

                "exit": "Prendi la {exit} uscita a destra",

                "exit_destination": "Prendi la {exit} uscita a destra verso {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Prendi la rampa",

                "name": "Prendi la rampa in {way_name}",

                "destination": "Prendi la rampa verso {destination}"

            },

            "left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}"

            },

            "right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}"

            },

            "sharp left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}"

            },

            "sharp right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}"

            },

            "slight left": {

                "default": "Prendi la rampa a sinistra",

                "name": "Prendi la rampa a sinistra in {way_name}",

                "destination": "Prendi la rampa a sinistra verso {destination}"

            },

            "slight right": {

                "default": "Prendi la rampa a destra",

                "name": "Prendi la rampa a destra in {way_name}",

                "destination": "Prendi la rampa a destra verso {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Immettiti nella rotonda",

                    "name": "Immettiti nella ritonda ed esci in {way_name}",

                    "destination": "Immettiti nella ritonda ed esci verso {destination}"

                },

                "name": {

                    "default": "Immettiti in {rotary_name}",

                    "name": "Immettiti in {rotary_name} ed esci su {way_name}",

                    "destination": "Immettiti in {rotary_name} ed esci verso {destination}"

                },

                "exit": {

                    "default": "Immettiti nella rotonda e prendi la {exit_number} uscita",

                    "name": "Immettiti nella rotonda e prendi la {exit_number} uscita in {way_name}",

                    "destination": "Immettiti nella rotonda e prendi la {exit_number} uscita verso   {destination}"

                },

                "name_exit": {

                    "default": "Immettiti in {rotary_name} e prendi la {exit_number} uscita",

                    "name": "Immettiti in {rotary_name} e prendi la {exit_number} uscita in {way_name}",

                    "destination": "Immettiti in {rotary_name} e prendi la {exit_number}  uscita verso {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Immettiti nella rotonda e prendi la {exit_number} uscita",

                    "name": "Immettiti nella rotonda e prendi la {exit_number} uscita in {way_name}",

                    "destination": "Immettiti nella rotonda e prendi la {exit_number} uscita verso {destination}"

                },

                "default": {

                    "default": "Entra nella rotonda",

                    "name": "Entra nella rotonda e prendi l'uscita in {way_name}",

                    "destination": "Entra nella rotonda e prendi l'uscita verso {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Fai una {modifier}",

                "name": "Fai una {modifier} in {way_name}",

                "destination": "Fai una {modifier} verso {destination}"

            },

            "left": {

                "default": "Svolta a sinistra",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "right": {

                "default": "Gira a destra",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "straight": {

                "default": "Continua dritto",

                "name": "Continua dritto in {way_name}",

                "destination": "Continua dritto verso {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Fai una {modifier}",

                "name": "Fai una {modifier} in {way_name}",

                "destination": "Fai una {modifier} verso {destination}"

            },

            "left": {

                "default": "Svolta a sinistra",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "right": {

                "default": "Gira a destra",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "straight": {

                "default": "Continua dritto",

                "name": "Continua dritto in {way_name}",

                "destination": "Continua dritto verso {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Fai una {modifier}",

                "name": "Fai una {modifier} in {way_name}",

                "destination": "Fai una {modifier} verso {destination}"

            },

            "left": {

                "default": "Svolta a sinistra",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "right": {

                "default": "Gira a destra",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "straight": {

                "default": "Prosegui dritto",

                "name": "Continua su {way_name}",

                "destination": "Continua verso {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Fai una {modifier}",

                "name": "Fai una {modifier} in {way_name}",

                "destination": "Fai una {modifier} verso {destination}"

            },

            "left": {

                "default": "Svolta a sinistra",

                "name": "Svolta a sinistra in {way_name}",

                "destination": "Svolta a sinistra verso {destination}"

            },

            "right": {

                "default": "Gira a destra",

                "name": "Svolta a destra in {way_name}",

                "destination": "Svolta a destra verso {destination}"

            },

            "straight": {

                "default": "Prosegui dritto",

                "name": "Continua su {way_name}",

                "destination": "Continua verso {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Continua dritto"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],34:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": false

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "???",

                "2": "???",

                "3": "???",

                "4": "???",

                "5": "????",

                "6": "????",

                "7": "????",

                "8": "????",

                "9": "????",

                "10": "???"

            },

            "direction": {

                "north": "??",

                "northeast": "???",

                "east": "??",

                "southeast": "???",

                "south": "??",

                "southwest": "???",

                "west": "??",

                "northwest": "???"

            },

            "modifier": {

                "left": "???",

                "right": "???",

                "sharp left": "?????",

                "sharp right": "?????",

                "slight left": "????",

                "slight right": "?????",

                "straight": "??",

                "uturn": "??"

            },

            "lanes": {

                "xo": "???? ??",

                "ox": "???? ??",

                "xox": "????",

                "oxo": "?? ?? ?? ?? ??"

            }

        },

        "modes": {

            "ferry": {

                "default": "??? ???",

                "name": "??? ??? {way_name}",

                "destination": "??? ?? {destination}?? ???."

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, ???, {distance} ??, {instruction_two}",

            "two linked": "{instruction_one}, ??? {instruction_two}",

            "one in distance": "{distance} ??, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "{exit}??? ????."

        },

        "arrive": {

            "default": {

                "default": " {nth}???? ???????.",

                "upcoming": "{nth}???? ? ??? ?????.",

                "short": "???????",

                "short-upcoming": "??? ?????.",

                "named": "??? {waypoint_name}? ???????."

            },

            "left": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "right": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "sharp left": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "sharp right": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "slight right": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "slight left": {

                "default": "??? {nth} ???? ????.",

                "upcoming": "??? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            },

            "straight": {

                "default": "?? ?? {nth} ???? ????.",

                "upcoming": "????? {nth} ???? ??? ?????.",

                "short": "???????",

                "short-upcoming": "???? ? ??? ?????.",

                "named": "??? ??? {waypoint_name}? ???????."

            }

        },

        "continue": {

            "default": {

                "default": "{modifier} ??",

                "name": "{modifier} ???? {way_name}? ??? ???.",

                "destination": "{modifier} ???? {destination}?? ???.",

                "exit": "{way_name} ??? {modifier} ?? ???."

            },

            "straight": {

                "default": "?? ??? ???.",

                "name": "{way_name} ? ?? ??? ???.",

                "destination": "{destination}?? ??? ???.",

                "distance": "{distance}?? ??? ???.",

                "namedistance": "{distance}?? {way_name}? ????."

            },

            "sharp left": {

                "default": "???? ???.",

                "name": "???? ?? ? {way_name}? ???.",

                "destination": "???? ?? ? {destination}? ???."

            },

            "sharp right": {

                "default": "???? ???.",

                "name": "???? ?? {way_name}? ???.",

                "destination": "???? ?? ? {destination}? ???."

            },

            "slight left": {

                "default": "?? ??????.",

                "name": "?? ??? ?? {way_name}? ???.",

                "destination": "?? ??? ?? ? {destination}? ???."

            },

            "slight right": {

                "default": "?? ??????.",

                "name": "?? ??? ?? {way_name}? ???.",

                "destination": "?? ??? ?? ? {destination}? ???."

            },

            "uturn": {

                "default": "?? ???",

                "name": "???? {way_name}? ???.",

                "destination": "???? ? {destination}? ???."

            }

        },

        "depart": {

            "default": {

                "default": "{direction}? ???",

                "name": "{direction} ? ?? {way_name} ? ?????. ",

                "namedistance": "{direction}? ??{way_name} ? {distance}?? ???."

            }

        },

        "end of road": {

            "default": {

                "default": "{modifier} ?????.",

                "name": "{modifier}???? {way_name}? ???.",

                "destination": "{modifier}?? ?? ? {destination}? ???."

            },

            "straight": {

                "default": "?? ??? ???.",

                "name": "{way_name}? ?? ??? ???.",

                "destination": "{destination}?? ??? ???."

            },

            "uturn": {

                "default": "?? ??? ?? ??? ???.",

                "name": "?? ??? ?? ???? {way_name}? ???.",

                "destination": "?? ??? ?? ???? {destination} ?? ???."

            }

        },

        "fork": {

            "default": {

                "default": "????? {modifier} ?? ???.",

                "name": "{modifier}?? {way_name}? ???.",

                "destination": "{modifier}?? {destination}?? ???."

            },

            "slight left": {

                "default": "????? ??? ???.",

                "name": "??? ?? {way_name}? ???.",

                "destination": "??? ?? {destination}?? ???."

            },

            "slight right": {

                "default": "????? ??? ???.",

                "name": "??? ?? {way_name}? ???.",

                "destination": "??? ?? {destination}?? ???."

            },

            "sharp left": {

                "default": "????? ???? ???.",

                "name": "???? ?? {way_name}? ???.",

                "destination": "???? ?? {destination}?? ???."

            },

            "sharp right": {

                "default": "????? ???? ???.",

                "name": "???? ?? {way_name}? ???.",

                "destination": "???? ?? {destination}?? ???."

            },

            "uturn": {

                "default": "?????.",

                "name": "???? {way_name}? ???.",

                "destination": "???? {destination}?? ???."

            }

        },

        "merge": {

            "default": {

                "default": "{modifier} ??",

                "name": "{modifier} ???? {way_name}? ???.",

                "destination": "{modifier} ???? {destination}? ???."

            },

            "straight": {

                "default": "??",

                "name": "{way_name}? ?????.",

                "destination": "{destination}? ?????."

            },

            "slight left": {

                "default": "???? ?????.",

                "name": "??{way_name}? ?????.",

                "destination": "???? ???? {destination}?? ???."

            },

            "slight right": {

                "default": "???? ?????.",

                "name": "??{way_name}? ?????.",

                "destination": "???? ???? {destination}?? ???."

            },

            "sharp left": {

                "default": "???? ?????.",

                "name": "??{way_name}? ?????.",

                "destination": "???? ???? {destination}?? ???."

            },

            "sharp right": {

                "default": "???? ?????.",

                "name": "??{way_name}? ?????.",

                "destination": "???? ???? {destination}?? ???."

            },

            "uturn": {

                "default": "?????.",

                "name": "???? {way_name}? ???.",

                "destination": "???? {destination}?? ???."

            }

        },

        "new name": {

            "default": {

                "default": "{modifier} ?????.",

                "name": "{modifier} ???? {way_name}? ???.",

                "destination": "{modifier} ???? {destination}?? ???."

            },

            "straight": {

                "default": "??????.",

                "name": "{way_name}? ?? ???.",

                "destination": "{destination}?? ?? ???."

            },

            "sharp left": {

                "default": "???? ???.",

                "name": "???? ?? {way_name}? ???.",

                "destination": "???? ?? {destination}?? ???."

            },

            "sharp right": {

                "default": "???? ???.",

                "name": "???? ?? {way_name}? ???.",

                "destination": "???? ?? {destination}?? ???."

            },

            "slight left": {

                "default": "?? ??? ???.",

                "name": "?? ????? {way_name}? ???.",

                "destination": "?? ??? ?? {destination}?? ???."

            },

            "slight right": {

                "default": "?? ??? ???.",

                "name": "?? ????? {way_name}? ???.",

                "destination": "?? ??? ?? {destination}?? ???."

            },

            "uturn": {

                "default": "??????.",

                "name": "???? {way_name}? ???.",

                "destination": "???? {destination}?? ???."

            }

        },

        "notification": {

            "default": {

                "default": "{modifier} ???.",

                "name": "{modifier}?? {way_name}? ???.",

                "destination": "{modifier}?? {destination}?? ???."

            },

            "uturn": {

                "default": "?????.",

                "name": "???? {way_name}? ???.",

                "destination": "???? {destination}?? ???."

            }

        },

        "off ramp": {

            "default": {

                "default": "??? ??? ???..",

                "name": "??? ???? {way_name}? ???.",

                "destination": "??? ???? {destination}?? ???.",

                "exit": "{exit} ??? ????.",

                "exit_destination": "{exit} ??? ??? {destination}?? ???."

            },

            "left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???.",

                "exit": "{exit} ??? ??? ????.",

                "exit_destination": "{exit} ??? ??? ??? {destination}?? ???."

            },

            "right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???.",

                "exit": "{exit} ???? ??? ????.",

                "exit_destination": "{exit} ???? ??? ??? {destination}?? ???."

            },

            "sharp left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???.",

                "exit": "{exit} ??? ??? ????.",

                "exit_destination": "{exit} ??? ??? ??? {destination}?? ???."

            },

            "sharp right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???.",

                "exit": "{exit} ???? ??? ????.",

                "exit_destination": "{exit} ???? ??? ??? {destination}?? ???."

            },

            "slight left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???.",

                "exit": "{exit} ??? ??? ????.",

                "exit_destination": "{exit} ??? ??? ??? {destination}?? ???."

            },

            "slight right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???.",

                "exit": "{exit} ???? ??? ????.",

                "exit_destination": "{exit} ???? ??? ??? {destination}?? ???."

            }

        },

        "on ramp": {

            "default": {

                "default": "??? ??? ???..",

                "name": "??? ???? {way_name}? ???.",

                "destination": "??? ???? {destination}?? ???."

            },

            "left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???."

            },

            "right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???."

            },

            "sharp left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???."

            },

            "sharp right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???."

            },

            "slight left": {

                "default": "??? ??? ??? ???.",

                "name": "??? ??? ???? {way_name}? ???.",

                "destination": "??? ??? ???? {destination}?? ???."

            },

            "slight right": {

                "default": "???? ??? ??? ???.",

                "name": "???? ??? ???? {way_name}? ???.",

                "destination": "???? ??? ???? {destination}?? ???."

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "???? ?????.",

                    "name": "???? ???? {way_name} ????.",

                    "destination": "???? ???? {destination}? ????."

                },

                "name": {

                    "default": "{rotary_name}? ?????.",

                    "name": "{rotary_name}? ???? {way_name}? ????.",

                    "destination": "{rotary_name}? ???? {destination}? ????."

                },

                "exit": {

                    "default": "???? ???? {exit_number} ??? ????.",

                    "name": "???? ???? {exit_number} ??? ?? {way_name}? ???.",

                    "destination": "???? ???? {exit_number} ??? ?? {destination}? ???."

                },

                "name_exit": {

                    "default": "{rotary_name}? ???? {exit_number}? ??? ????.",

                    "name": "{rotary_name}? ???? {exit_number}? ??? ?? {way_name}? ???.",

                    "destination": "{rotary_name}? ???? {exit_number}? ??? ?? {destination}? ???."

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "???? ???? {exit_number}? ????.",

                    "name": "???? ???? {exit_number}? ??? {way_name}? ???.",

                    "destination": "???? ???? {exit_number}? ??? {destination}? ???."

                },

                "default": {

                    "default": "???? ?????.",

                    "name": "???? ???? {way_name} ????.",

                    "destination": "???? ???? {destination}? ????."

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier} ???.",

                "name": "{modifier} ??? {way_name}? ???.",

                "destination": "{modifier} ??? {destination}?? ???."

            },

            "left": {

                "default": "??? ???.",

                "name": "??? ??? {way_name}? ???.",

                "destination": "??? ??? {destination}?? ???."

            },

            "right": {

                "default": "??? ???.",

                "name": "??? ??? {way_name}? ???.",

                "destination": "??? ??? {destination}?? ???."

            },

            "straight": {

                "default": "?? ???.",

                "name": "????? {way_name}? ???.",

                "destination": "????? {destination}?? ???."

            }

        },

        "exit roundabout": {

            "default": {

                "default": "????? ?????.",

                "name": "????? ???? {way_name}? ???.",

                "destination": "????? ???? {destination}?? ???."

            }

        },

        "exit rotary": {

            "default": {

                "default": "????? ?????.",

                "name": "????? ???? {way_name}? ???.",

                "destination": "????? ???? {destination}?? ???."

            }

        },

        "turn": {

            "default": {

                "default": "{modifier} ???.",

                "name": "{modifier} ??? {way_name}? ???.",

                "destination": "{modifier} ??? {destination}?? ???."

            },

            "left": {

                "default": "??? ???.",

                "name": "??? ??? {way_name}? ???.",

                "destination": "??? ??? {destination}?? ???."

            },

            "right": {

                "default": "??? ???.",

                "name": "??? ??? {way_name}? ???.",

                "destination": "??? ??? {destination}?? ???."

            },

            "straight": {

                "default": "?? ???.",

                "name": "????? {way_name}? ???.",

                "destination": "????? {destination}?? ???."

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "?????."

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],35:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": false

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "???",

                "2": "?????",

                "3": "????",

                "4": "?????",

                "5": "????",

                "6": "???",

                "7": "????",

                "8": "???",

                "9": "???",

                "10": "???"

            },

            "direction": {

                "north": "??????????",

                "northeast": "???????????????",

                "east": "?????????",

                "southeast": "??????????????",

                "south": "?????????",

                "southwest": "???????????????",

                "west": "??????????",

                "northwest": "????????????????"

            },

            "modifier": {

                "left": "??????",

                "right": "?????",

                "sharp left": "?????? ???????????",

                "sharp right": "????? ???????????",

                "slight left": "?????? ????????",

                "slight right": "????? ????????",

                "straight": "??????????????????????",

                "uturn": "?-????"

            },

            "lanes": {

                "xo": "??????????????????",

                "ox": "???????????????????",

                "xox": "???????????????",

                "oxo": "??? ????????? ????????? ?????????"

            }

        },

        "modes": {

            "ferry": {

                "default": "????? ?????????",

                "name": "{way_name}??? ??????????????",

                "destination": "{destination}?????? ??????????????"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}????????? {distance}?????? {instruction_two}",

            "two linked": "{instruction_one}????????? {instruction_two}",

            "one in distance": "{distance}?????? {instruction_one}",

            "name and ref": "{name}( {ref})",

            "exit with number": "{exit}????????"

        },

        "arrive": {

            "default": {

                "default": "{nth}??? ?????????? ????????????????????????????",

                "upcoming": "??? ?????????? {nth}??????????????????????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name} ??? ???????????"

            },

            "left": {

                "default": "??? ?????????? {nth}??????????????????????????????????????",

                "upcoming": "??? ?????????? {nth}????????????????????????????????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name}??????????????? ???????????"

            },

            "right": {

                "default": "??? ?????????? {nth}????????????????? ??????????? ???????????",

                "upcoming": "??? ??????????{nth} ????????????????? ??????????? ?????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name} ?????????????? ???????????"

            },

            "sharp left": {

                "default": "??? ?????????? {nth}??????????????????????????????????????",

                "upcoming": "??? ?????????? {nth}??????????????????????????????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name}??????????????? ???????????"

            },

            "sharp right": {

                "default": "??? ?????????? {nth}????????????????? ??????????? ???????????",

                "upcoming": "??? ??????????{nth} ????????????????? ??????????? ?????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name} ?????????????? ???????????"

            },

            "slight right": {

                "default": "??? ?????????? {nth}????????????????? ??????????? ???????????",

                "upcoming": "??? ??????????{nth} ????????????????? ??????????? ?????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name} ?????????????? ???????????"

            },

            "slight left": {

                "default": "??? ?????????? {nth}??????????????????????????????????????",

                "upcoming": "??? ?????????? {nth}??????????????????????????????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name}??????????????? ???????????"

            },

            "straight": {

                "default": "??? ?????????? {nth}?????????????????????????????????????????",

                "upcoming": "??? ?????????? {nth}?????????????????????????????????????????",

                "short": "????????????? ???????? ???????????",

                "short-upcoming": "????????????? ???????? ?????????????",

                "named": "??? ??? {waypoint_name}????????????????? ???????????"

            }

        },

        "continue": {

            "default": {

                "default": "{modifier}??????????",

                "name": "{way_name}?????????????? {modifier}??????????",

                "destination": "{destination}?????? {modifier}??? ???????",

                "exit": "{way_name}????????? {modifier}??????????"

            },

            "straight": {

                "default": "?????????????????????? ?????????",

                "name": "{way_name}?????????????????????????????",

                "destination": "{destination}???????????????",

                "distance": "{distance}????? ???????? ?????????",

                "namedistance": "{way_name}?????????{distance}??????????????"

            },

            "sharp left": {

                "default": "???????????????????????",

                "name": "{way_name}????????????? ???????????????????????",

                "destination": "{destination}?????? ???????????????????????"

            },

            "sharp right": {

                "default": "????? ?????????????????",

                "name": "{way_name}????????????? ??????????????????????",

                "destination": "{destination}?????? ??????????????????????"

            },

            "slight left": {

                "default": "?????? ??????????????",

                "name": "{way_name}????????????? ????????????????????",

                "destination": "{destination}?????? ?????????????????????????"

            },

            "slight right": {

                "default": "????? ???????????????????",

                "name": "{way_name}????????????? ???????????????????",

                "destination": "{destination}?????? ????????????????????????"

            },

            "uturn": {

                "default": "?-???? ??????",

                "name": "{way_name}??????????? ?-?????????????????????",

                "destination": "{destination}?????? ????????????????"

            }

        },

        "depart": {

            "default": {

                "default": "{direction}???? ????????",

                "name": "{direction}??? {way_name}???????? ????????",

                "namedistance": "{direction}??? {way_name}????????{distance}????? ???????????????"

            }

        },

        "end of road": {

            "default": {

                "default": "{modifier}???????????",

                "name": "{way_name}????????? {modifier}??????????",

                "destination": "{destination}?????? {modifier}??? ???????"

            },

            "straight": {

                "default": "?????????????????????? ?????????",

                "name": "{way_name}",

                "destination": "{destination}???????????????????????"

            },

            "uturn": {

                "default": "????????????? ?-??????????",

                "name": "????????????? {way_name}??????????-??????????",

                "destination": "?????????????{destination}?????? ????????????????"

            }

        },

        "fork": {

            "default": {

                "default": "????????????????? {modifier}????????????",

                "name": "{way_name}????????? {modifier}????????????",

                "destination": "{destination}?????? {modifier}??? ?????????"

            },

            "slight left": {

                "default": "???????????????????????????????????",

                "name": "{way_name}???????????????????????????",

                "destination": "{destination}??????????????? ?????????"

            },

            "slight right": {

                "default": "??????????????????????????????????",

                "name": "{way_name}",

                "destination": "{destination}?????????????? ?????????"

            },

            "sharp left": {

                "default": "???????????????????????????????????????????",

                "name": "{way_name}????????????? ?????????????????????",

                "destination": "{destination}??????????????????????? ??????"

            },

            "sharp right": {

                "default": "??????????????????????????????????????????",

                "name": "{way_name}???????? ????????????????????",

                "destination": "{destination}?????????????????????? ??????"

            },

            "uturn": {

                "default": "?-???? ??????",

                "name": "{way_name}?????-??????????",

                "destination": "{destination}?????? ????????????????"

            }

        },

        "merge": {

            "default": {

                "default": "{modifier}?????????????????????",

                "name": "{way_name}????????? {modifier}?????????????????????",

                "destination": "{destination}?????? {modifier}??? ??????????????????"

            },

            "straight": {

                "default": "??????????????????",

                "name": "{way_name}???????????????????????????",

                "destination": "{destination}?????? ??????????????????"

            },

            "slight left": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????????????????????????????",

                "destination": "{destination}??????????????? ??????????????????"

            },

            "slight right": {

                "default": "???????????????????????????",

                "name": "{way_name}???????????????????????????????????",

                "destination": "{destination}?????????????? ??????????????????"

            },

            "sharp left": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????????????????????????????",

                "destination": "{destination}??????????????? ??????????????????"

            },

            "sharp right": {

                "default": "???????????????????????????",

                "name": "{way_name}???????????????????????????????????",

                "destination": "{destination}?????????????? ??????????????????"

            },

            "uturn": {

                "default": "?-???? ??????",

                "name": "{way_name}??????????? ?-???? ?????? ",

                "destination": "{destination}?????? ????????????????"

            }

        },

        "new name": {

            "default": {

                "default": "{modifier}????????????",

                "name": "{way_name}????????? {modifier}????????????",

                "destination": "{destination}?????? {modifier}??? ?????????"

            },

            "straight": {

                "default": "?????????????????????? ?????????",

                "name": "{way_name}??????????????????",

                "destination": "{destination}???????????????"

            },

            "sharp left": {

                "default": "?????????????????????",

                "name": "{way_name}????????????? ?????????????????????",

                "destination": "{destination}??????????????????????? ??????"

            },

            "sharp right": {

                "default": "????? ???????????????",

                "name": "{way_name}???????? ????????????????????",

                "destination": "{destination}?????????????????????? ??????"

            },

            "slight left": {

                "default": "?????? ?????????????????",

                "name": "{way_name}??????????????? ?????????????????",

                "destination": "{destination}?????????????????????????????"

            },

            "slight right": {

                "default": "????? ?????????????????",

                "name": "{way_name}?????????????? ?????????????????",

                "destination": "{destination}????????????????????????????"

            },

            "uturn": {

                "default": "?-???? ??????",

                "name": "{way_name}??????????? ?-???? ??????",

                "destination": "{destination}?????? ????????????????"

            }

        },

        "notification": {

            "default": {

                "default": "{modifier}????????????",

                "name": "{way_name}????????? {modifier}????????????",

                "destination": "{destination}?????? {modifier}??? ?????????"

            },

            "uturn": {

                "default": "?-???? ??????",

                "name": "{way_name}??????????? ?-???? ??????",

                "destination": "{destination}?????? ????????????????"

            }

        },

        "off ramp": {

            "default": {

                "default": "???????????????????",

                "name": "{way_name}???????????????????????????",

                "destination": "{destination}?????? ???????????????????",

                "exit": "{exit}??? ????",

                "exit_destination": "{destination}?????? {exit} ???????"

            },

            "left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????",

                "exit": "??????????{exit}??? ????",

                "exit_destination": "{destination}?????????????? {exit} ???????"

            },

            "right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????",

                "exit": "?????????{exit}??? ????",

                "exit_destination": "{destination}????????????? {exit} ???????"

            },

            "sharp left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????",

                "exit": "??????????{exit}??? ????",

                "exit_destination": "{destination}?????????????? {exit} ???????"

            },

            "sharp right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????",

                "exit": "?????????{exit}??? ????",

                "exit_destination": "{destination}????????????? {exit} ???????"

            },

            "slight left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????",

                "exit": "??????????{exit}??? ????",

                "exit_destination": "{destination}?????????????? {exit} ???????"

            },

            "slight right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????",

                "exit": "?????????{exit}??? ????",

                "exit_destination": "{destination}????????????? {exit} ???????"

            }

        },

        "on ramp": {

            "default": {

                "default": "???????????????????",

                "name": "{way_name}???????????????????????????",

                "destination": "{destination}?????? ???????????????????"

            },

            "left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????"

            },

            "right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????"

            },

            "sharp left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????"

            },

            "sharp right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????"

            },

            "slight left": {

                "default": "?????????????????????????????",

                "name": "{way_name}?????????????? ???????????????????????????",

                "destination": "{destination}?????? ?????????????????????????????????"

            },

            "slight right": {

                "default": "????????????????????????????",

                "name": "{way_name}????????????? ???????????????????????????",

                "destination": "{destination}?????? ????????????????????????????????"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "???????????????????",

                "name": "{way_name}",

                    "destination": "{destination}?????????????????????????????"

                },

                "name": {

                    "default": "{rotary_name}?????????",

                    "name": "{rotary_name}?????????????????{way_name}?????????????",

                    "destination": "{rotary_name}?????????????????{destination}????????????"

                },

                "exit": {

                    "default": "?????????????????{exit_number}?????????????????",

                    "name": "?????????????????????{exit_number}???????{way_name}?????????????",

                    "destination": "?????????????????{exit_number}???????{destination}????????????"

                },

                "name_exit": {

                    "default": "{rotary_name}?????????? {exit_number}?????????????",

                    "name": "{rotary_name}??????????{exit_number}???????{way_name}?????????????",

                    "destination": "{rotary_name}???????{exit_number}???????{destination}????????????"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "{exit_number}?????????????????????????????",

                    "name": "?????????????????{exit_number}???????{way_name}?????????????",

                    "destination": "?????????????????{exit_number}???????{destination}????????????"

                },

                "default": {

                    "default": "???????????????",

                    "name": "{way_name}?????????????????????????????",

                    "destination": "{destination}?????????????????????????????"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier}??????????�",

                "name": "{modifier}???????{way_name}????????????�",

                "destination": "{modifier}??????{destination}??? ?????????�"

            },

            "left": {

                "default": "?????????????????????",

                "name": "{way_name}??????????????????????????�",

                "destination": "{destination}?????????????? ??????"

            },

            "right": {

                "default": "????????????????????",

                "name": "{way_name}",

                "destination": "{destination}????????? ??????"

            },

            "straight": {

                "default": "?????????????????????? ?????????",

                "name": "{way_name}??????????????????????????",

                "destination": "{destination}???????????????????????"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "??????????????????????",

                "name": "{way_name}?????????????????????????????",

                "destination": "????????????????????????????{destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "??????????????????????????????????????????????????",

                "name": "{way_name}?????????????????????????????",

                "destination": "????????????????????????????{destination}"

            }

        },

        "turn": {

            "default": {

                "default": "{modifier}??????????�",

                "name": "{modifier}???????{way_name}????????????�",

                "destination": "{modifier}??????{destination}??? ?????????�"

            },

            "left": {

                "default": "?????????????????????",

                "name": "{way_name}??????????????????????????�",

                "destination": "{destination}?????????? ??????"

            },

            "right": {

                "default": "????????????????????",

                "name": "{way_name}",

                "destination": "{destination}????????? ??????"

            },

            "straight": {

                "default": "??????????????",

                "name": "{way_name}",

                "destination": "{destination}????????????????????"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "?????????????????????? ?????????"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],36:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1e",

                "2": "2e",

                "3": "3e",

                "4": "4e",

                "5": "5e",

                "6": "6e",

                "7": "7e",

                "8": "8e",

                "9": "9e",

                "10": "10e"

            },

            "direction": {

                "north": "noord",

                "northeast": "noordoost",

                "east": "oost",

                "southeast": "zuidoost",

                "south": "zuid",

                "southwest": "zuidwest",

                "west": "west",

                "northwest": "noordwest"

            },

            "modifier": {

                "left": "links",

                "right": "rechts",

                "sharp left": "scherpe bocht naar links",

                "sharp right": "scherpe bocht naar rechts",

                "slight left": "iets naar links",

                "slight right": "iets naar rechts",

                "straight": "rechtdoor",

                "uturn": "omkeren"

            },

            "lanes": {

                "xo": "Rechts aanhouden",

                "ox": "Links aanhouden",

                "xox": "In het midden blijven",

                "oxo": "Links of rechts blijven"

            }

        },

        "modes": {

            "ferry": {

                "default": "Neem de veerpont",

                "name": "Neem de veerpont {way_name}",

                "destination": "Neem de veerpont richting {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, dan na {distance}, {instruction_two}",

            "two linked": "{instruction_one}, daarna {instruction_two}",

            "one in distance": "Over {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "afslag {exit}"

        },

        "arrive": {

            "default": {

                "default": "Je bent gearriveerd op de {nth} bestemming.",

                "upcoming": "U arriveert op de {nth} bestemming",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name}"

            },

            "left": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich links.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de linkerkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name}, de bestemming is aan de linkerkant"

            },

            "right": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich rechts.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de rechterkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name}, de bestemming is aan de  rechterkant"

            },

            "sharp left": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich links.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de linkerkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name}, de bestemming is aan de linkerkant"

            },

            "sharp right": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich rechts.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de rechterkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name},  de bestemming is aan de rechterkant"

            },

            "slight right": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich rechts.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de rechterkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name},  de bestemming is aan de rechterkant"

            },

            "slight left": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich links.",

                "upcoming": "Uw {nth} bestemming bevindt zich aan de linkerkant",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name},  de bestemming is aan de linkerkant"

            },

            "straight": {

                "default": "Je bent gearriveerd. De {nth} bestemming bevindt zich voor je.",

                "upcoming": "Uw {nth} bestemming is recht voor u",

                "short": "U bent gearriveerd",

                "short-upcoming": "U zult aankomen",

                "named": "U bent gearriveerd bij {waypoint_name}, de bestemming is recht voor u"

            }

        },

        "continue": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Sla {modifier} om op {way_name} te blijven",

                "destination": "Ga {modifier} richting {destination}",

                "exit": "Ga {modifier} naar {way_name}"

            },

            "straight": {

                "default": "Ga rechtdoor",

                "name": "Blijf rechtdoor gaan op {way_name}",

                "destination": "Ga rechtdoor richting {destination}",

                "distance": "Ga rechtdoor voor {distance}",

                "namedistance": "Ga verder op {way_name} voor {distance}"

            },

            "sharp left": {

                "default": "Linksaf",

                "name": "Sla scherp links af om op {way_name} te blijven",

                "destination": "Linksaf richting {destination}"

            },

            "sharp right": {

                "default": "Rechtsaf",

                "name": "Sla scherp rechts af om op {way_name} te blijven",

                "destination": "Rechtsaf richting {destination}"

            },

            "slight left": {

                "default": "Ga links",

                "name": "Links afbuigen om op {way_name} te blijven",

                "destination": "Rechts afbuigen om op {destination} te blijven"

            },

            "slight right": {

                "default": "Rechts afbuigen",

                "name": "Rechts afbuigen om op {way_name} te blijven",

                "destination": "Rechts afbuigen richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Draai om en ga verder op {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Vertrek in {direction}elijke richting",

                "name": "Neem {way_name} in {direction}elijke richting",

                "namedistance": "Ga richting {direction} op {way_name} voor {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Ga {modifier} naar {way_name}",

                "destination": "Ga {modifier} richting {destination}"

            },

            "straight": {

                "default": "Ga in de aangegeven richting",

                "name": "Ga naar {way_name}",

                "destination": "Ga richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Keer om naar {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "Ga {modifier} op de splitsing",

                "name": "Houd {modifier} aan, tot {way_name}",

                "destination": "Houd {modifier}, in de richting van {destination}"

            },

            "slight left": {

                "default": "Links aanhouden op de splitsing",

                "name": "Houd links aan, tot {way_name}",

                "destination": "Houd links aan, richting {destination}"

            },

            "slight right": {

                "default": "Rechts aanhouden op de splitsing",

                "name": "Houd rechts aan, tot {way_name}",

                "destination": "Houd rechts aan, richting {destination}"

            },

            "sharp left": {

                "default": "Neem bij de splitsing, een scherpe bocht, naar links ",

                "name": "Neem een scherpe bocht naar links, tot aan {way_name}",

                "destination": "Neem een scherpe bocht naar links, richting {destination}"

            },

            "sharp right": {

                "default": "Neem  op de splitsing, een scherpe bocht, naar rechts",

                "name": "Neem een scherpe bocht naar rechts, tot aan {way_name}",

                "destination": "Neem een scherpe bocht naar rechts, richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Keer om naar {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Bij de splitsing {modifier}",

                "name": "Bij de splitsing {modifier} naar {way_name}",

                "destination": "Bij de splitsing {modifier} richting {destination}"

            },

            "straight": {

                "default": "Samenvoegen",

                "name": "Ga verder op {way_name}",

                "destination": "Ga verder richting {destination}"

            },

            "slight left": {

                "default": "Bij de splitsing links aanhouden",

                "name": "Bij de splitsing links aanhouden naar {way_name}",

                "destination": "Bij de splitsing links aanhouden richting {destination}"

            },

            "slight right": {

                "default": "Bij de splitsing rechts aanhouden",

                "name": "Bij de splitsing rechts aanhouden naar {way_name}",

                "destination": "Bij de splitsing rechts aanhouden richting {destination}"

            },

            "sharp left": {

                "default": "Bij de splitsing linksaf",

                "name": "Bij de splitsing linksaf naar {way_name}",

                "destination": "Bij de splitsing linksaf richting {destination}"

            },

            "sharp right": {

                "default": "Bij de splitsing rechtsaf",

                "name": "Bij de splitsing rechtsaf naar {way_name}",

                "destination": "Bij de splitsing rechtsaf richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Keer om naar {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Ga {modifier} naar {way_name}",

                "destination": "Ga {modifier} richting {destination}"

            },

            "straight": {

                "default": "Ga in de aangegeven richting",

                "name": "Ga rechtdoor naar {way_name}",

                "destination": "Ga rechtdoor richting {destination}"

            },

            "sharp left": {

                "default": "Neem een scherpe bocht, naar links",

                "name": "Linksaf naar {way_name}",

                "destination": "Linksaf richting {destination}"

            },

            "sharp right": {

                "default": "Neem een scherpe bocht, naar rechts",

                "name": "Rechtsaf naar {way_name}",

                "destination": "Rechtsaf richting {destination}"

            },

            "slight left": {

                "default": "Links aanhouden",

                "name": "Links aanhouden naar {way_name}",

                "destination": "Links aanhouden richting {destination}"

            },

            "slight right": {

                "default": "Rechts aanhouden",

                "name": "Rechts aanhouden naar {way_name}",

                "destination": "Rechts aanhouden richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Keer om naar {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Ga {modifier} naar {way_name}",

                "destination": "Ga {modifier} richting {destination}"

            },

            "uturn": {

                "default": "Keer om",

                "name": "Keer om naar {way_name}",

                "destination": "Keer om richting {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Neem de afrit",

                "name": "Neem de afrit naar {way_name}",

                "destination": "Neem de afrit richting {destination}",

                "exit": "Neem afslag {exit}",

                "exit_destination": "Neem afslag {exit} richting {destination}"

            },

            "left": {

                "default": "Neem de afrit links",

                "name": "Neem de afrit links naar {way_name}",

                "destination": "Neem de afrit links richting {destination}",

                "exit": "Neem afslag {exit} aan de linkerkant",

                "exit_destination": "Neem afslag {exit} aan de linkerkant richting {destination}"

            },

            "right": {

                "default": "Neem de afrit rechts",

                "name": "Neem de afrit rechts naar {way_name}",

                "destination": "Neem de afrit rechts richting {destination}",

                "exit": "Neem afslag {exit} aan de rechterkant",

                "exit_destination": "Neem afslag {exit} aan de rechterkant richting {destination}"

            },

            "sharp left": {

                "default": "Neem de afrit links",

                "name": "Neem de afrit links naar {way_name}",

                "destination": "Neem de afrit links richting {destination}",

                "exit": "Neem afslag {exit} aan de linkerkant",

                "exit_destination": "Neem afslag {exit} aan de linkerkant richting {destination}"

            },

            "sharp right": {

                "default": "Neem de afrit rechts",

                "name": "Neem de afrit rechts naar {way_name}",

                "destination": "Neem de afrit rechts richting {destination}",

                "exit": "Neem afslag {exit} aan de rechterkant",

                "exit_destination": "Neem afslag {exit} aan de rechterkant richting {destination}"

            },

            "slight left": {

                "default": "Neem de afrit links",

                "name": "Neem de afrit links naar {way_name}",

                "destination": "Neem de afrit links richting {destination}",

                "exit": "Neem afslag {exit} aan de linkerkant",

                "exit_destination": "Neem afslag {exit} aan de linkerkant richting {destination}"

            },

            "slight right": {

                "default": "Neem de afrit rechts",

                "name": "Neem de afrit rechts naar {way_name}",

                "destination": "Neem de afrit rechts richting {destination}",

                "exit": "Neem afslag {exit} aan de rechterkant",

                "exit_destination": "Neem afslag {exit} aan de rechterkant richting {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Neem de oprit",

                "name": "Neem de oprit naar {way_name}",

                "destination": "Neem de oprit richting {destination}"

            },

            "left": {

                "default": "Neem de oprit links",

                "name": "Neem de oprit links naar {way_name}",

                "destination": "Neem de oprit links richting {destination}"

            },

            "right": {

                "default": "Neem de oprit rechts",

                "name": "Neem de oprit rechts naar {way_name}",

                "destination": "Neem de oprit rechts richting {destination}"

            },

            "sharp left": {

                "default": "Neem de oprit links",

                "name": "Neem de oprit links naar {way_name}",

                "destination": "Neem de oprit links richting {destination}"

            },

            "sharp right": {

                "default": "Neem de oprit rechts",

                "name": "Neem de oprit rechts naar {way_name}",

                "destination": "Neem de oprit rechts richting {destination}"

            },

            "slight left": {

                "default": "Neem de oprit links",

                "name": "Neem de oprit links naar {way_name}",

                "destination": "Neem de oprit links richting {destination}"

            },

            "slight right": {

                "default": "Neem de oprit rechts",

                "name": "Neem de oprit rechts naar {way_name}",

                "destination": "Neem de oprit rechts richting {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Betreedt de rotonde",

                    "name": "Betreedt rotonde en sla af op {way_name}",

                    "destination": "Betreedt rotonde en sla af richting {destination}"

                },

                "name": {

                    "default": "Ga het knooppunt {rotary_name} op",

                    "name": "Verlaat het knooppunt {rotary_name} naar {way_name}",

                    "destination": "Verlaat het knooppunt {rotary_name} richting {destination}"

                },

                "exit": {

                    "default": "Betreedt rotonde en neem afslag {exit_number}",

                    "name": "Betreedt rotonde en neem afslag {exit_number} naar {way_name}",

                    "destination": "Betreedt rotonde en neem afslag {exit_number} richting {destination}"

                },

                "name_exit": {

                    "default": "Ga het knooppunt {rotary_name} op en neem afslag {exit_number}",

                    "name": "Ga het knooppunt {rotary_name} op en neem afslag {exit_number} naar {way_name}",

                    "destination": "Ga het knooppunt {rotary_name} op en neem afslag {exit_number} richting {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Betreedt rotonde en neem afslag {exit_number}",

                    "name": "Betreedt rotonde en neem afslag {exit_number} naar {way_name}",

                    "destination": "Betreedt rotonde en neem afslag {exit_number} richting {destination}"

                },

                "default": {

                    "default": "Betreedt de rotonde",

                    "name": "Betreedt rotonde en sla af op {way_name}",

                    "destination": "Betreedt rotonde en sla af richting {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Ga {modifier} naar {way_name}",

                "destination": "Ga {modifier} richting {destination}"

            },

            "left": {

                "default": "Ga linksaf",

                "name": "Ga linksaf naar {way_name}",

                "destination": "Ga linksaf richting {destination}"

            },

            "right": {

                "default": "Ga rechtsaf",

                "name": "Ga rechtsaf naar {way_name}",

                "destination": "Ga rechtsaf richting {destination}"

            },

            "straight": {

                "default": "Ga in de aangegeven richting",

                "name": "Ga naar {way_name}",

                "destination": "Ga richting {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Verlaat de rotonde",

                "name": "Verlaat de rotonde en ga verder op {way_name}",

                "destination": "Verlaat de rotonde richting {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Verlaat de rotonde",

                "name": "Verlaat de rotonde en ga verder op {way_name}",

                "destination": "Verlaat de rotonde richting {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Ga {modifier}",

                "name": "Ga {modifier} naar {way_name}",

                "destination": "Ga {modifier} richting {destination}"

            },

            "left": {

                "default": "Ga linksaf",

                "name": "Ga linksaf naar {way_name}",

                "destination": "Ga linksaf richting {destination}"

            },

            "right": {

                "default": "Ga rechtsaf",

                "name": "Ga rechtsaf naar {way_name}",

                "destination": "Ga rechtsaf richting {destination}"

            },

            "straight": {

                "default": "Ga rechtdoor",

                "name": "Ga rechtdoor naar {way_name}",

                "destination": "Ga rechtdoor richting {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Rechtdoor"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],37:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1.",

                "2": "2.",

                "3": "3.",

                "4": "4.",

                "5": "5.",

                "6": "6.",

                "7": "7.",

                "8": "8.",

                "9": "9.",

                "10": "10."

            },

            "direction": {

                "north": "nord",

                "northeast": "nord�st",

                "east": "�st",

                "southeast": "s�r�st",

                "south": "s�r",

                "southwest": "s�rvest",

                "west": "vest",

                "northwest": "nordvest"

            },

            "modifier": {

                "left": "venstre",

                "right": "h�yre",

                "sharp left": "skarp venstre",

                "sharp right": "skarp h�yre",

                "slight left": "litt til venstre",

                "slight right": "litt til h�yre",

                "straight": "rett frem",

                "uturn": "U-sving"

            },

            "lanes": {

                "xo": "Hold til h�yre",

                "ox": "Hold til venstre",

                "xox": "Hold deg i midten",

                "oxo": "Hold til venstre eller h�yre"

            }

        },

        "modes": {

            "ferry": {

                "default": "Ta ferja",

                "name": "Ta ferja {way_name}",

                "destination": "Ta ferja til {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, deretter {instruction_two} om {distance}",

            "two linked": "{instruction_one}, deretter {instruction_two}",

            "one in distance": "Om {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "avkj�rsel {exit}"

        },

        "arrive": {

            "default": {

                "default": "Du har ankommet din {nth} destinasjon",

                "upcoming": "Du vil ankomme din {nth} destinasjon",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}"

            },

            "left": {

                "default": "Du har ankommet din {nth} destinasjon, p� din venstre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din venstre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din venstre side"

            },

            "right": {

                "default": "Du har ankommet din {nth} destinasjon, p� din h�yre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din h�yre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din h�yre side"

            },

            "sharp left": {

                "default": "Du har ankommet din {nth} destinasjon, p� din venstre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din venstre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din venstre side"

            },

            "sharp right": {

                "default": "Du har ankommet din {nth} destinasjon, p� din h�yre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din h�yre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din h�yre side"

            },

            "slight right": {

                "default": "Du har ankommet din {nth} destinasjon, p� din h�yre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din h�yre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din h�yre side"

            },

            "slight left": {

                "default": "Du har ankommet din {nth} destinasjon, p� din venstre side",

                "upcoming": "Du vil ankomme din {nth} destinasjon, p� din venstre side",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, p� din venstre side"

            },

            "straight": {

                "default": "Du har ankommet din {nth} destinasjon, rett forut",

                "upcoming": "Du vil ankomme din {nth} destinasjon, rett forut",

                "short": "Du har ankommet",

                "short-upcoming": "Du vil ankomme",

                "named": "Du har ankommet {waypoint_name}, rett forut"

            }

        },

        "continue": {

            "default": {

                "default": "Ta til {modifier}",

                "name": "Ta til {modifier} for � bli v�rende p� {way_name}",

                "destination": "Ta til {modifier} mot {destination}",

                "exit": "Ta til {modifier} inn p� {way_name}"

            },

            "straight": {

                "default": "Fortsett rett frem",

                "name": "Fortsett rett frem for � bli v�rende p� {way_name}",

                "destination": "Fortsett mot {destination}",

                "distance": "Fortsett rett frem, {distance} ",

                "namedistance": "Fortsett p� {way_name}, {distance}"

            },

            "sharp left": {

                "default": "Sving skarpt til venstre",

                "name": "Sving skarpt til venstre for � bli v�rende p� {way_name}",

                "destination": "Sving skarpt til venstre mot {destination}"

            },

            "sharp right": {

                "default": "Sving skarpt til h�yre",

                "name": "Sving skarpt til h�yre for � bli v�rende p� {way_name}",

                "destination": "Sving skarpt mot {destination}"

            },

            "slight left": {

                "default": "Sving svakt til venstre",

                "name": "Sving svakt til venstre for � bli v�rende p� {way_name}",

                "destination": "Sving svakt til venstre mot {destination}"

            },

            "slight right": {

                "default": "Sving svakt til h�yre",

                "name": "Sving svakt til h�yre for � bli v�rende p� {way_name}",

                "destination": "Sving svakt til h�yre mot {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving",

                "name": "Ta en U-sving og fortsett p� {way_name}",

                "destination": "Ta en U-sving mot {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Kj�r i retning {direction}",

                "name": "Kj�r i retning {direction} p� {way_name}",

                "namedistance": "Kj�r i retning {direction} p� {way_name}, {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Sving {modifier}",

                "name": "Ta til {modifier} inn p� {way_name}",

                "destination": "Sving {modifier} mot {destination}"

            },

            "straight": {

                "default": "Fortsett rett frem",

                "name": "Fortsett rett frem til  {way_name}",

                "destination": "Fortsett rett frem mot {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving i enden av veien",

                "name": "Ta en U-sving til {way_name} i enden av veien",

                "destination": "Ta en U-sving mot {destination} i enden av veien"

            }

        },

        "fork": {

            "default": {

                "default": "Hold til {modifier} i veikrysset",

                "name": "Hold til {modifier} inn p� {way_name}",

                "destination": "Hold til {modifier} mot {destination}"

            },

            "slight left": {

                "default": "Hold til venstre i veikrysset",

                "name": "Hold til venstre inn p� {way_name}",

                "destination": "Hold til venstre mot {destination}"

            },

            "slight right": {

                "default": "Hold til h�yre i veikrysset",

                "name": "Hold til h�yre inn p� {way_name}",

                "destination": "Hold til h�yre mot {destination}"

            },

            "sharp left": {

                "default": "Sving skarpt til venstre i veikrysset",

                "name": "Sving skarpt til venstre inn p� {way_name}",

                "destination": "Sving skarpt til venstre mot {destination}"

            },

            "sharp right": {

                "default": "Sving skarpt til h�yre i veikrysset",

                "name": "Sving skarpt til h�yre inn p� {way_name}",

                "destination": "Svings skarpt til h�yre mot {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving",

                "name": "Ta en U-sving til {way_name}",

                "destination": "Ta en U-sving mot {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Hold {modifier} kj�refelt",

                "name": "Hold {modifier} kj�refelt inn p� {way_name}",

                "destination": "Hold {modifier} kj�refelt mot {destination}"

            },

            "straight": {

                "default": "Hold kj�refelt",

                "name": "Hold kj�refelt inn p� {way_name}",

                "destination": "Hold kj�refelt mot {destination}"

            },

            "slight left": {

                "default": "Hold venstre kj�refelt",

                "name": "Hold venstre kj�refelt inn p� {way_name}",

                "destination": "Hold venstre kj�refelt mot {destination}"

            },

            "slight right": {

                "default": "Hold h�yre kj�refelt",

                "name": "Hold h�yre kj�refelt inn p� {way_name}",

                "destination": "Hold h�yre kj�refelt mot {destination}"

            },

            "sharp left": {

                "default": "Hold venstre kj�refelt",

                "name": "Hold venstre kj�refelt inn p� {way_name}",

                "destination": "Hold venstre kj�refelt mot {destination}"

            },

            "sharp right": {

                "default": "Hold h�yre kj�refelt",

                "name": "Hold h�yre kj�refelt inn p� {way_name}",

                "destination": "Hold h�yre kj�refelt mot {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving",

                "name": "Ta en U-sving til {way_name}",

                "destination": "Ta en U-sving mot {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Fortsett {modifier}",

                "name": "Fortsett {modifier} til {way_name}",

                "destination": "Fortsett {modifier} mot  {destination}"

            },

            "straight": {

                "default": "Fortsett rett frem",

                "name": "Fortsett inn p� {way_name}",

                "destination": "Fortsett mot {destination}"

            },

            "sharp left": {

                "default": "Sving skarpt til venstre",

                "name": "Sving skarpt til venstre inn p� {way_name}",

                "destination": "Sving skarpt til venstre mot {destination}"

            },

            "sharp right": {

                "default": "Sving skarpt til h�yre",

                "name": "Sving skarpt til h�yre inn p� {way_name}",

                "destination": "Svings skarpt til h�yre mot {destination}"

            },

            "slight left": {

                "default": "Fortsett litt mot venstre",

                "name": "Fortsett litt mot venstre til {way_name}",

                "destination": "Fortsett litt mot venstre mot {destination}"

            },

            "slight right": {

                "default": "Fortsett litt mot h�yre",

                "name": "Fortsett litt mot h�yre til {way_name}",

                "destination": "Fortsett litt mot h�yre mot {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving",

                "name": "Ta en U-sving til {way_name}",

                "destination": "Ta en U-sving mot {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Fortsett {modifier}",

                "name": "Fortsett {modifier} til {way_name}",

                "destination": "Fortsett {modifier} mot  {destination}"

            },

            "uturn": {

                "default": "Ta en U-sving",

                "name": "Ta en U-sving til {way_name}",

                "destination": "Ta en U-sving mot {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Ta avkj�rselen",

                "name": "Ta avkj�rselen inn p� {way_name}",

                "destination": "Ta avkj�rselen mot {destination}",

                "exit": "Ta avkj�rsel {exit}",

                "exit_destination": "Ta avkj�rsel {exit} mot {destination}"

            },

            "left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� venstre side",

                "exit_destination": "Ta avkj�rsel {exit} p� venstre side mot {destination}"

            },

            "right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� h�yre side",

                "exit_destination": "Ta avkj�rsel {exit} p� h�yre side mot {destination}"

            },

            "sharp left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� venstre side",

                "exit_destination": "Ta avkj�rsel {exit} p� venstre side mot {destination}"

            },

            "sharp right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� h�yre side",

                "exit_destination": "Ta avkj�rsel {exit} p� h�yre side mot {destination}"

            },

            "slight left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� venstre side",

                "exit_destination": "Ta avkj�rsel {exit} p� venstre side mot {destination}"

            },

            "slight right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}",

                "exit": "Ta avkj�rsel {exit} p� h�yre side",

                "exit_destination": "Ta avkj�rsel {exit} p� h�yre side mot {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Ta avkj�rselen",

                "name": "Ta avkj�rselen inn p� {way_name}",

                "destination": "Ta avkj�rselen mot {destination}"

            },

            "left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}"

            },

            "right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}"

            },

            "sharp left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}"

            },

            "sharp right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}"

            },

            "slight left": {

                "default": "Ta avkj�rselen p� venstre side",

                "name": "Ta avkj�rselen p� venstre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� venstre side mot {destination}"

            },

            "slight right": {

                "default": "Ta avkj�rselen p� h�yre side",

                "name": "Ta avkj�rselen p� h�yre side inn p� {way_name}",

                "destination": "Ta avkj�rselen p� h�yre side mot {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Kj�r inn i rundkj�ringen",

                    "name": "Kj�r inn i rundkj�ringen og deretter ut p� {way_name}",

                    "destination": "Kj�r inn i rundkj�ringen og deretter ut mot {destination}"

                },

                "name": {

                    "default": "Kj�r inn i {rotary_name}",

                    "name": "Kj�r inn i {rotary_name} og deretter ut p� {way_name}",

                    "destination": "Kj�r inn i {rotary_name} og deretter ut mot {destination}"

                },

                "exit": {

                    "default": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel",

                    "name": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel ut p� {way_name}",

                    "destination": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel ut mot {destination} "

                },

                "name_exit": {

                    "default": "Kj�r inn i {rotary_name} og ta {exit_number} avkj�rsel",

                    "name": "Kj�r inn i {rotary_name} og ta {exit_number} avkj�rsel inn p� {way_name}",

                    "destination": "Kj�r inn i {rotary_name} og ta {exit_number} avkj�rsel mot {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel",

                    "name": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel inn p� {way_name}",

                    "destination": "Kj�r inn i rundkj�ringen og ta {exit_number} avkj�rsel ut mot {destination} "

                },

                "default": {

                    "default": "Kj�r inn i rundkj�ringen",

                    "name": "Kj�r inn i rundkj�ringen og deretter ut p� {way_name}",

                    "destination": "Kj�r inn i rundkj�ringen og deretter ut mot {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Ta en {modifier}",

                "name": "Ta en {modifier} inn p� {way_name}",

                "destination": "Ta en {modifier} mot {destination}"

            },

            "left": {

                "default": "Sving til venstre",

                "name": "Sving til venstre inn p� {way_name}",

                "destination": "Sving til venstre mot {destination}"

            },

            "right": {

                "default": "Sving til h�yre",

                "name": "Sving til h�yre inn p� {way_name}",

                "destination": "Sving til h�yre mot {destination}"

            },

            "straight": {

                "default": "Fortsett rett frem",

                "name": "Fortsett rett frem til  {way_name}",

                "destination": "Fortsett rett frem mot {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Kj�r ut av rundkj�ringen",

                "name": "Kj�r ut av rundkj�ringen og inn p� {way_name}",

                "destination": "Kj�r ut av rundkj�ringen mot {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Kj�r ut av rundkj�ringen",

                "name": "Kj�r ut av rundkj�ringen og inn p� {way_name}",

                "destination": "Kj�r ut av rundkj�ringen mot {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Ta en {modifier}",

                "name": "Ta en {modifier} inn p� {way_name}",

                "destination": "Ta en {modifier} mot {destination}"

            },

            "left": {

                "default": "Sving til venstre",

                "name": "Sving til venstre inn p� {way_name}",

                "destination": "Sving til venstre mot {destination}"

            },

            "right": {

                "default": "Sving til h�yre",

                "name": "Sving til h�yre inn p� {way_name}",

                "destination": "Sving til h�yre mot {destination}"

            },

            "straight": {

                "default": "Kj�r rett frem",

                "name": "Kj�r rett frem og inn p� {way_name}",

                "destination": "Kj�r rett frem mot {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Fortsett rett frem"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],38:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1.",

                "2": "2.",

                "3": "3.",

                "4": "4.",

                "5": "5.",

                "6": "6.",

                "7": "7.",

                "8": "8.",

                "9": "9.",

                "10": "10."

            },

            "direction": {

                "north": "p�lnoc",

                "northeast": "p�lnocny wsch�d",

                "east": "wsch�d",

                "southeast": "poludniowy wsch�d",

                "south": "poludnie",

                "southwest": "poludniowy zach�d",

                "west": "zach�d",

                "northwest": "p�lnocny zach�d"

            },

            "modifier": {

                "left": "lewo",

                "right": "prawo",

                "sharp left": "ostro w lewo",

                "sharp right": "ostro w prawo",

                "slight left": "lagodnie w lewo",

                "slight right": "lagodnie w prawo",

                "straight": "prosto",

                "uturn": "zawr�c"

            },

            "lanes": {

                "xo": "Trzymaj sie prawej strony",

                "ox": "Trzymaj sie lewej strony",

                "xox": "Trzymaj sie srodka",

                "oxo": "Trzymaj sie lewej lub prawej strony"

            }

        },

        "modes": {

            "ferry": {

                "default": "Wez prom",

                "name": "Wez prom {way_name}",

                "destination": "Wez prom w kierunku {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, nastepnie za {distance} {instruction_two}",

            "two linked": "{instruction_one}, nastepnie {instruction_two}",

            "one in distance": "Za {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "Dojechano do miejsca docelowego {nth}",

                "upcoming": "Dojechano do miejsca docelowego {nth}",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}"

            },

            "left": {

                "default": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po lewej stronie"

            },

            "right": {

                "default": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po prawej stronie"

            },

            "sharp left": {

                "default": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po lewej stronie"

            },

            "sharp right": {

                "default": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po prawej stronie"

            },

            "slight right": {

                "default": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po prawej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po prawej stronie"

            },

            "slight left": {

                "default": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "upcoming": "Dojechano do miejsca docelowego {nth}, po lewej stronie",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, po lewej stronie"

            },

            "straight": {

                "default": "Dojechano do miejsca docelowego {nth} , prosto",

                "upcoming": "Dojechano do miejsca docelowego {nth} , prosto",

                "short": "Dojechano do miejsca docelowego {nth}",

                "short-upcoming": "Dojechano do miejsca docelowego {nth}",

                "named": "Dojechano do {waypoint_name}, prosto"

            }

        },

        "continue": {

            "default": {

                "default": "Skrec {modifier}",

                "name": "Skrec w {modifier}, aby pozostac na {way_name}",

                "destination": "Skrec {modifier} w kierunku {destination}",

                "exit": "Skrec {modifier} na {way_name}"

            },

            "straight": {

                "default": "Kontynuuj prosto",

                "name": "Jedz dalej prosto, aby pozostac na {way_name}",

                "destination": "Kontynuuj w kierunku {destination}",

                "distance": "Jedz dalej prosto przez {distance}",

                "namedistance": "Jedz dalej {way_name} przez {distance}"

            },

            "sharp left": {

                "default": "Skrec ostro w lewo",

                "name": "Skrec w lewo w ostry zakret, aby pozostac na {way_name}",

                "destination": "Skrec ostro w lewo w kierunku {destination}"

            },

            "sharp right": {

                "default": "Skrec ostro w prawo",

                "name": "Skrec w prawo w ostry zakret, aby pozostac na {way_name}",

                "destination": "Skrec ostro w prawo w kierunku {destination}"

            },

            "slight left": {

                "default": "Skrec w lewo w lagodny zakret",

                "name": "Skrec w lewo w lagodny zakret, aby pozostac na {way_name}",

                "destination": "Skrec w lewo w lagodny zakret na {destination}"

            },

            "slight right": {

                "default": "Skrec w prawo w lagodny zakret",

                "name": "Skrec w prawo w lagodny zakret, aby pozostac na {way_name}",

                "destination": "Skrec w prawo w lagodny zakret na {destination}"

            },

            "uturn": {

                "default": "Zawr�c",

                "name": "Zawr�c i jedz dalej {way_name}",

                "destination": "Zawr�c w kierunku {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Kieruj sie {direction}",

                "name": "Kieruj sie {direction} na {way_name}",

                "namedistance": "Head {direction} on {way_name} for {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Skrec {modifier}",

                "name": "Skrec {modifier} na {way_name}",

                "destination": "Skrec {modifier} w kierunku {destination}"

            },

            "straight": {

                "default": "Kontynuuj prosto",

                "name": "Kontynuuj prosto na {way_name}",

                "destination": "Kontynuuj prosto w kierunku {destination}"

            },

            "uturn": {

                "default": "Zawr�c na koncu ulicy",

                "name": "Zawr�c na koncu ulicy na {way_name}",

                "destination": "Zawr�c na koncu ulicy w kierunku {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "Na rozwidleniu trzymaj sie {modifier}",

                "name": "Na rozwidleniu trzymaj sie {modifier} na {way_name}",

                "destination": "Na rozwidleniu trzymaj sie {modifier} w kierunku {destination}"

            },

            "slight left": {

                "default": "Na rozwidleniu trzymaj sie lewej strony",

                "name": "Na rozwidleniu trzymaj sie lewej strony w {way_name}",

                "destination": "Na rozwidleniu trzymaj sie lewej strony w kierunku {destination}"

            },

            "slight right": {

                "default": "Na rozwidleniu trzymaj sie prawej strony",

                "name": "Na rozwidleniu trzymaj sie prawej strony na {way_name}",

                "destination": "Na rozwidleniu trzymaj sie prawej strony w kierunku {destination}"

            },

            "sharp left": {

                "default": "Na rozwidleniu skrec ostro w lewo",

                "name": "Skrec ostro w lewo w {way_name}",

                "destination": "Skrec ostro w lewo w kierunku {destination}"

            },

            "sharp right": {

                "default": "Na rozwidleniu skrec ostro w prawo",

                "name": "Skrec ostro w prawo na {way_name}",

                "destination": "Skrec ostro w prawo w kierunku {destination}"

            },

            "uturn": {

                "default": "Zawr�c",

                "name": "Zawr�c na {way_name}",

                "destination": "Zawr�c w kierunku {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Wlacz sie {modifier}",

                "name": "Wlacz sie {modifier} na {way_name}",

                "destination": "Wlacz sie {modifier} w kierunku {destination}"

            },

            "straight": {

                "default": "Wlacz sie prosto",

                "name": "Wlacz sie prosto na {way_name}",

                "destination": "Wlacz sie prosto w kierunku {destination}"

            },

            "slight left": {

                "default": "Wlacz sie z lewej strony",

                "name": "Wlacz sie z lewej strony na {way_name}",

                "destination": "Wlacz sie z lewej strony w kierunku {destination}"

            },

            "slight right": {

                "default": "Wlacz sie z prawej strony",

                "name": "Wlacz sie z prawej strony na {way_name}",

                "destination": "Wlacz sie z prawej strony w kierunku {destination}"

            },

            "sharp left": {

                "default": "Wlacz sie z lewej strony",

                "name": "Wlacz sie z lewej strony na {way_name}",

                "destination": "Wlacz sie z lewej strony w kierunku {destination}"

            },

            "sharp right": {

                "default": "Wlacz sie z prawej strony",

                "name": "Wlacz sie z prawej strony na {way_name}",

                "destination": "Wlacz sie z prawej strony w kierunku {destination}"

            },

            "uturn": {

                "default": "Zawr�c",

                "name": "Zawr�c na {way_name}",

                "destination": "Zawr�c w kierunku {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Kontynuuj {modifier}",

                "name": "Kontynuuj {modifier} na {way_name}",

                "destination": "Kontynuuj {modifier} w kierunku {destination}"

            },

            "straight": {

                "default": "Kontynuuj prosto",

                "name": "Kontynuuj na {way_name}",

                "destination": "Kontynuuj w kierunku {destination}"

            },

            "sharp left": {

                "default": "Skrec ostro w lewo",

                "name": "Skrec ostro w lewo w {way_name}",

                "destination": "Skrec ostro w lewo w kierunku {destination}"

            },

            "sharp right": {

                "default": "Skrec ostro w prawo",

                "name": "Skrec ostro w prawo na {way_name}",

                "destination": "Skrec ostro w prawo w kierunku {destination}"

            },

            "slight left": {

                "default": "Kontynuuj lagodnie w lewo",

                "name": "Kontynuuj lagodnie w lewo na {way_name}",

                "destination": "Kontynuuj lagodnie w lewo w kierunku {destination}"

            },

            "slight right": {

                "default": "Kontynuuj lagodnie w prawo",

                "name": "Kontynuuj lagodnie w prawo na {way_name}",

                "destination": "Kontynuuj lagodnie w prawo w kierunku {destination}"

            },

            "uturn": {

                "default": "Zawr�c",

                "name": "Zawr�c na {way_name}",

                "destination": "Zawr�c w kierunku {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Kontynuuj {modifier}",

                "name": "Kontynuuj {modifier} na {way_name}",

                "destination": "Kontynuuj {modifier} w kierunku {destination}"

            },

            "uturn": {

                "default": "Zawr�c",

                "name": "Zawr�c na {way_name}",

                "destination": "Zawr�c w kierunku {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Zjedz",

                "name": "Wez zjazd na {way_name}",

                "destination": "Wez zjazd w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit}",

                "exit_destination": "Zjedz zjazdem {exit} na {destination}"

            },

            "left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po lewej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po lewej stronie na {destination}"

            },

            "right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po prawej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po prawej stronie na {destination}"

            },

            "sharp left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po lewej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po lewej stronie na {destination}"

            },

            "sharp right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po prawej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po prawej stronie na {destination}"

            },

            "slight left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po lewej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po lewej stronie na {destination}"

            },

            "slight right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}",

                "exit": "Zjedz zjazdem {exit} po prawej stronie",

                "exit_destination": "Zjedz zjazdem {exit} po prawej stronie na {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Wez zjazd",

                "name": "Wez zjazd na {way_name}",

                "destination": "Wez zjazd w kierunku {destination}"

            },

            "left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}"

            },

            "right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}"

            },

            "sharp left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}"

            },

            "sharp right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}"

            },

            "slight left": {

                "default": "Wez zjazd po lewej",

                "name": "Wez zjazd po lewej na {way_name}",

                "destination": "Wez zjazd po lewej w kierunku {destination}"

            },

            "slight right": {

                "default": "Wez zjazd po prawej",

                "name": "Wez zjazd po prawej na {way_name}",

                "destination": "Wez zjazd po prawej w kierunku {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Wjedz na rondo",

                    "name": "Wjedz na rondo i skrec na {way_name}",

                    "destination": "Wjedz na rondo i skrec w kierunku {destination}"

                },

                "name": {

                    "default": "Wjedz na {rotary_name}",

                    "name": "Wjedz na {rotary_name} i skrec na {way_name}",

                    "destination": "Wjedz na {rotary_name} i skrec w kierunku {destination}"

                },

                "exit": {

                    "default": "Wjedz na rondo i wyjedz {exit_number} zjazdem",

                    "name": "Wjedz na rondo i wyjedz {exit_number} zjazdem na {way_name}",

                    "destination": "Wjedz na rondo i wyjedz {exit_number} zjazdem w kierunku {destination}"

                },

                "name_exit": {

                    "default": "Wjedz na {rotary_name} i wyjedz {exit_number} zjazdem",

                    "name": "Wjedz na {rotary_name} i wyjedz {exit_number} zjazdem na {way_name}",

                    "destination": "Wjedz na {rotary_name} i wyjedz {exit_number} zjazdem w kierunku {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Wjedz na rondo i wyjedz {exit_number} zjazdem",

                    "name": "Wjedz na rondo i wyjedz {exit_number} zjazdem na {way_name}",

                    "destination": "Wjedz na rondo i wyjedz {exit_number} zjazdem w kierunku {destination}"

                },

                "default": {

                    "default": "Wjedz na rondo",

                    "name": "Wjedz na rondo i wyjedz na {way_name}",

                    "destination": "Wjedz na rondo i wyjedz w kierunku {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier}",

                "name": "{modifier} na {way_name}",

                "destination": "{modifier} w kierunku {destination}"

            },

            "left": {

                "default": "Skrec w lewo",

                "name": "Skrec w lewo na {way_name}",

                "destination": "Skrec w lewo w kierunku {destination}"

            },

            "right": {

                "default": "Skrec w prawo",

                "name": "Skrec w prawo na {way_name}",

                "destination": "Skrec w prawo w kierunku {destination}"

            },

            "straight": {

                "default": "Kontynuuj prosto",

                "name": "Kontynuuj prosto na {way_name}",

                "destination": "Kontynuuj prosto w kierunku {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "{modifier}",

                "name": "{modifier} na {way_name}",

                "destination": "{modifier} w kierunku {destination}"

            },

            "left": {

                "default": "Skrec w lewo",

                "name": "Skrec w lewo na {way_name}",

                "destination": "Skrec w lewo w kierunku {destination}"

            },

            "right": {

                "default": "Skrec w prawo",

                "name": "Skrec w prawo na {way_name}",

                "destination": "Skrec w prawo w kierunku {destination}"

            },

            "straight": {

                "default": "Kontynuuj prosto",

                "name": "Kontynuuj prosto na {way_name}",

                "destination": "Kontynuuj prosto w kierunku {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "{modifier}",

                "name": "{modifier} na {way_name}",

                "destination": "{modifier} w kierunku {destination}"

            },

            "left": {

                "default": "Skrec w lewo",

                "name": "Skrec w lewo na {way_name}",

                "destination": "Skrec w lewo w kierunku {destination}"

            },

            "right": {

                "default": "Skrec w prawo",

                "name": "Skrec w prawo na {way_name}",

                "destination": "Skrec w prawo w kierunku {destination}"

            },

            "straight": {

                "default": "Jedz prosto",

                "name": "Jedz prosto na {way_name}",

                "destination": "Jedz prosto w kierunku {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "{modifier}",

                "name": "{modifier} na {way_name}",

                "destination": "{modifier} w kierunku {destination}"

            },

            "left": {

                "default": "Skrec w lewo",

                "name": "Skrec w lewo na {way_name}",

                "destination": "Skrec w lewo w kierunku {destination}"

            },

            "right": {

                "default": "Skrec w prawo",

                "name": "Skrec w prawo na {way_name}",

                "destination": "Skrec w prawo w kierunku {destination}"

            },

            "straight": {

                "default": "Jedz prosto",

                "name": "Jedz prosto na {way_name}",

                "destination": "Jedz prosto w kierunku {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Kontynuuj prosto"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],39:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1�",

                "2": "2�",

                "3": "3�",

                "4": "4�",

                "5": "5�",

                "6": "6�",

                "7": "7�",

                "8": "8�",

                "9": "9�",

                "10": "10�"

            },

            "direction": {

                "north": "norte",

                "northeast": "nordeste",

                "east": "leste",

                "southeast": "sudeste",

                "south": "sul",

                "southwest": "sudoeste",

                "west": "oeste",

                "northwest": "noroeste"

            },

            "modifier": {

				Left: 'esquerda',

				Right: 'direita',

                "sharp left": "fechada � esquerda",

                "sharp right": "fechada � direita",

                "slight left": "suave � esquerda",

                "slight right": "suave � direita",

                "straight": "em frente",

                "uturn": "retorno"

            },

            "lanes": {

                "xo": "Mantenha-se � direita",

                "ox": "Mantenha-se � esquerda",

                "xox": "Mantenha-se ao centro",

                "oxo": "Mantenha-se � esquerda ou direita"

            }

        },

        "modes": {

            "ferry": {

                "default": "Pegue a balsa",

                "name": "Pegue a balsa {way_name}",

                "destination": "Pegue a balsa sentido {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, ent�o, em {distance}, {instruction_two}",

            "two linked": "{instruction_one}, ent�o {instruction_two}",

            "one in distance": "Em {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "sa�da {exit}"

        },

        "arrive": {

            "default": {

                "default": "Voc� chegou ao seu {nth} destino",

                "upcoming": "Voc� chegar� ao seu {nth} destino",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou a {waypoint_name}"

            },

            "left": {

                "default": "Voc� chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � esquerda",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � esquerda"

            },

            "right": {

                "default": "Voc� chegou ao seu {nth} destino, � direita",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � direita",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � direita"

            },

            "sharp left": {

                "default": "Voc� chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � esquerda",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � esquerda"

            },

            "sharp right": {

                "default": "Voc� chegou ao seu {nth} destino, � direita",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � direita",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � direita"

            },

            "slight right": {

                "default": "Voc� chegou ao seu {nth} destino, � direita",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � direita",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � direita"

            },

            "slight left": {

                "default": "Voc� chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Voc� chegar� ao seu {nth} destino, � esquerda",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "Voc� chegou {waypoint_name}, � esquerda"

            },

            "straight": {

                "default": "Voc� chegou ao seu {nth} destino, em frente",

                "upcoming": "Voc� vai chegar ao seu {nth} destino, em frente",

                "short": "Voc� chegou",

                "short-upcoming": "Voc� vai chegar",

                "named": "You have arrived at {waypoint_name}, straight ahead"

            }

        },

        "continue": {

            "default": {

                "default": "Vire {modifier}",

                "name": "Vire {modifier} para manter-se na {way_name}",

                "destination": "Vire {modifier} sentido {destination}",

                "exit": "Vire {modifier} em {way_name}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente para manter-se na {way_name}",

                "destination": "Continue em dire��o � {destination}",

                "distance": "Continue em frente por {distance}",

                "namedistance": "Continue na {way_name} por {distance}"

            },

            "sharp left": {

                "default": "Fa�a uma curva fechada a esquerda",

                "name": "Fa�a uma curva fechada a esquerda para manter-se na {way_name}",

                "destination": "Fa�a uma curva fechada a esquerda sentido {destination}"

            },

            "sharp right": {

                "default": "Fa�a uma curva fechada a direita",

                "name": "Fa�a uma curva fechada a direita para manter-se na {way_name}",

                "destination": "Fa�a uma curva fechada a direita sentido {destination}"

            },

            "slight left": {

                "default": "Fa�a uma curva suave a esquerda",

                "name": "Fa�a uma curva suave a esquerda para manter-se na {way_name}",

                "destination": "Fa�a uma curva suave a esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Fa�a uma curva suave a direita",

                "name": "Fa�a uma curva suave a direita para manter-se na {way_name}",

                "destination": "Fa�a uma curva suave a direita em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno",

                "name": "Fa�a o retorno e continue em {way_name}",

                "destination": "Fa�a o retorno sentido {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Siga {direction}",

                "name": "Siga {direction} em {way_name}",

                "namedistance": "Siga {direction} na {way_name} por {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Vire {modifier}",

                "name": "Vire {modifier} em {way_name}",

                "destination": "Vire {modifier} sentido {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente em {way_name}",

                "destination": "Continue em frente sentido {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno no fim da rua",

                "name": "Fa�a o retorno em {way_name} no fim da rua",

                "destination": "Fa�a o retorno sentido {destination} no fim da rua"

            }

        },

        "fork": {

            "default": {

                "default": "Mantenha-se {modifier} na bifurca��o",

                "name": "Mantenha-se {modifier} na bifurca��o em {way_name}",

                "destination": "Mantenha-se {modifier} na bifurca��o sentido {destination}"

            },

            "slight left": {

                "default": "Mantenha-se � esquerda na bifurca��o",

                "name": "Mantenha-se � esquerda na bifurca��o em {way_name}",

                "destination": "Mantenha-se � esquerda na bifurca��o sentido {destination}"

            },

            "slight right": {

                "default": "Mantenha-se � direita na bifurca��o",

                "name": "Mantenha-se � direita na bifurca��o em {way_name}",

                "destination": "Mantenha-se � direita na bifurca��o sentido {destination}"

            },

            "sharp left": {

                "default": "Fa�a uma curva fechada � esquerda na bifurca��o",

                "name": "Fa�a uma curva fechada � esquerda em {way_name}",

                "destination": "Fa�a uma curva fechada � esquerda sentido {destination}"

            },

            "sharp right": {

                "default": "Fa�a uma curva fechada � direita na bifurca��o",

                "name": "Fa�a uma curva fechada � direita em {way_name}",

                "destination": "Fa�a uma curva fechada � direita sentido {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno",

                "name": "Fa�a o retorno em {way_name}",

                "destination": "Fa�a o retorno sentido {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Entre {modifier}",

                "name": "Entre {modifier} na {way_name}",

                "destination": "Entre {modifier} em dire��o � {destination}"

            },

            "straight": {

                "default": "Mesclar",

                "name": "Entre reto na {way_name}",

                "destination": "Entre reto em dire��o � {destination}"

            },

            "slight left": {

                "default": "Entre � esquerda",

                "name": "Entre � esquerda na {way_name}",

                "destination": "Entre � esquerda em dire��o � {destination}"

            },

            "slight right": {

                "default": "Entre � direita",

                "name": "Entre � direita na {way_name}",

                "destination": "Entre � direita em dire��o � {destination}"

            },

            "sharp left": {

                "default": "Entre � esquerda",

                "name": "Entre � esquerda na {way_name}",

                "destination": "Entre � esquerda em dire��o � {destination}"

            },

            "sharp right": {

                "default": "Entre � direita",

                "name": "Entre � direita na {way_name}",

                "destination": "Entre � direita em dire��o � {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno",

                "name": "Fa�a o retorno em {way_name}",

                "destination": "Fa�a o retorno sentido {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} em {way_name}",

                "destination": "Continue {modifier} sentido {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em {way_name}",

                "destination": "Continue em dire��o � {destination}"

            },

            "sharp left": {

                "default": "Fa�a uma curva fechada � esquerda",

                "name": "Fa�a uma curva fechada � esquerda em {way_name}",

                "destination": "Fa�a uma curva fechada � esquerda sentido {destination}"

            },

            "sharp right": {

                "default": "Fa�a uma curva fechada � direita",

                "name": "Fa�a uma curva fechada � direita em {way_name}",

                "destination": "Fa�a uma curva fechada � direita sentido {destination}"

            },

            "slight left": {

                "default": "Continue ligeiramente � esquerda",

                "name": "Continue ligeiramente � esquerda em {way_name}",

                "destination": "Continue ligeiramente � esquerda sentido {destination}"

            },

            "slight right": {

                "default": "Continue ligeiramente � direita",

                "name": "Continue ligeiramente � direita em {way_name}",

                "destination": "Continue ligeiramente � direita sentido {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno",

                "name": "Fa�a o retorno em {way_name}",

                "destination": "Fa�a o retorno sentido {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} em {way_name}",

                "destination": "Continue {modifier} sentido {destination}"

            },

            "uturn": {

                "default": "Fa�a o retorno",

                "name": "Fa�a o retorno em {way_name}",

                "destination": "Fa�a o retorno sentido {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Pegue a rampa",

                "name": "Pegue a rampa em {way_name}",

                "destination": "Pegue a rampa sentido {destination}",

                "exit": "Pegue a sa�da {exit}",

                "exit_destination": "Pegue a sa�da {exit} em dire��o � {destination}"

            },

            "left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}",

                "exit": "Pegue a sa�da {exit} � esquerda",

                "exit_destination": "Pegue a sa�da {exit}  � esquerda em dire��o � {destination}"

            },

            "right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentido {destination}",

                "exit": "Pegue a sa�da {exit} � direita",

                "exit_destination": "Pegue a sa�da {exit} � direita em dire��o � {destination}"

            },

            "sharp left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}",

                "exit": "Pegue a sa�da {exit} � esquerda",

                "exit_destination": "Pegue a sa�da {exit}  � esquerda em dire��o � {destination}"

            },

            "sharp right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentido {destination}",

                "exit": "Pegue a sa�da {exit} � direita",

                "exit_destination": "Pegue a sa�da {exit} � direita em dire��o � {destination}"

            },

            "slight left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}",

                "exit": "Pegue a sa�da {exit} � esquerda",

                "exit_destination": "Pegue a sa�da {exit}  � esquerda em dire��o � {destination}"

            },

            "slight right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentido {destination}",

                "exit": "Pegue a sa�da {exit} � direita",

                "exit_destination": "Pegue a sa�da {exit} � direita em dire��o � {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Pegue a rampa",

                "name": "Pegue a rampa em {way_name}",

                "destination": "Pegue a rampa sentido {destination}"

            },

            "left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}"

            },

            "right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentid {destination}"

            },

            "sharp left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}"

            },

            "sharp right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentido {destination}"

            },

            "slight left": {

                "default": "Pegue a rampa � esquerda",

                "name": "Pegue a rampa � esquerda em {way_name}",

                "destination": "Pegue a rampa � esquerda sentido {destination}"

            },

            "slight right": {

                "default": "Pegue a rampa � direita",

                "name": "Pegue a rampa � direita em {way_name}",

                "destination": "Pegue a rampa � direita sentido {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Entre na rotat�ria",

                    "name": "Entre na rotat�ria e saia na {way_name}",

                    "destination": "Entre na rotat�ria e saia sentido {destination}"

                },

                "name": {

                    "default": "Entre em {rotary_name}",

                    "name": "Entre em {rotary_name} e saia em {way_name}",

                    "destination": "Entre em {rotary_name} e saia sentido {destination}"

                },

                "exit": {

                    "default": "Entre na rotat�ria e pegue a {exit_number} sa�da",

                    "name": "Entre na rotat�ria e pegue a {exit_number} sa�da na {way_name}",

                    "destination": "Entre na rotat�ria e pegue a {exit_number} sa�da sentido {destination}"

                },

                "name_exit": {

                    "default": "Entre em {rotary_name} e saia na {exit_number} sa�da",

                    "name": "Entre em {rotary_name} e saia na {exit_number} sa�da em {way_name}",

                    "destination": "Entre em {rotary_name} e saia na {exit_number} sa�da sentido {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Entre na rotat�ria e pegue a {exit_number} sa�da",

                    "name": "Entre na rotat�ria e pegue a {exit_number} sa�da na {way_name}",

                    "destination": "Entre na rotat�ria e pegue a {exit_number} sa�da sentido {destination}"

                },

                "default": {

                    "default": "Entre na rotat�ria",

                    "name": "Entre na rotat�ria e saia na {way_name}",

                    "destination": "Entre na rotat�ria e saia sentido {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Siga {modifier}",

                "name": "Siga {modifier} em {way_name}",

                "destination": "Siga {modifier} sentido {destination}"

            },

            "left": {

                "default": "Vire � esquerda",

                "name": "Vire � esquerda em {way_name}",

                "destination": "Vire � esquerda sentido {destination}"

            },

            "right": {

                "default": "Vire � direita",

                "name": "Vire � direita em {way_name}",

                "destination": "Vire � direita sentido {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente em {way_name}",

                "destination": "Continue em frente sentido {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Saia da rotat�ria",

                "name": "Exit the traffic circle onto {way_name}",

                "destination": "Exit the traffic circle towards {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Saia da rotat�ria",

                "name": "Exit the traffic circle onto {way_name}",

                "destination": "Exit the traffic circle towards {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Siga {modifier}",

                "name": "Siga {modifier} em {way_name}",

                "destination": "Siga {modifier} sentido {destination}"

            },

            "left": {

                "default": "Vire � esquerda",

                "name": "Vire � esquerda em {way_name}",

                "destination": "Vire � esquerda sentido {destination}"

            },

            "right": {

                "default": "Vire � direita",

                "name": "Vire � direita em {way_name}",

                "destination": "Vire � direita sentido {destination}"

            },

            "straight": {

                "default": "Siga em frente",

                "name": "Siga em frente em {way_name}",

                "destination": "Siga em frente sentido {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Continue em frente"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],40:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1�",

                "2": "2�",

                "3": "3�",

                "4": "4�",

                "5": "5�",

                "6": "6�",

                "7": "7�",

                "8": "8�",

                "9": "9�",

                "10": "10�"

            },

            "direction": {

                "north": "norte",

                "northeast": "nordeste",

                "east": "este",

                "southeast": "sudeste",

                "south": "sul",

                "southwest": "sudoeste",

                "west": "oeste",

                "northwest": "noroeste"

            },

            "modifier": {

				Left: 'esquerda',

				Right: 'direita',

                "sharp left": "acentuadamente � esquerda",

                "sharp right": "acentuadamente � direita",

                "slight left": "ligeiramente � esquerda",

                "slight right": "ligeiramente � direita",

                "straight": "em frente",

                "uturn": "invers�o de marcha"

            },

            "lanes": {

                "xo": "Mantenha-se � direita",

                "ox": "Mantenha-se � esquerda",

                "xox": "Mantenha-se ao meio",

                "oxo": "Mantenha-se � esquerda ou � direita"

            }

        },

        "modes": {

            "ferry": {

                "default": "Apanhe o ferry",

                "name": "Apanhe o ferry {way_name}",

                "destination": "Apanhe o ferry para {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, depois, a {distance}, {instruction_two}",

            "two linked": "{instruction_one}, depois {instruction_two}",

            "one in distance": "A {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "sa�da {exit}"

        },

        "arrive": {

            "default": {

                "default": "Chegou ao seu {nth} destino",

                "upcoming": "Est� a chegar ao seu {nth} destino",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}"

            },

            "left": {

                "default": "Chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Est� a chegar ao seu {nth} destino, � esquerda",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � esquerda"

            },

            "right": {

                "default": "Chegou ao seu {nth} destino, � direita",

                "upcoming": "Est� a chegar ao seu {nth} destino, � direita",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � direita"

            },

            "sharp left": {

                "default": "Chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Est� a chegar ao seu {nth} destino, � esquerda",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � esquerda"

            },

            "sharp right": {

                "default": "Chegou ao seu {nth} destino, � direita",

                "upcoming": "Est� a chegar ao seu {nth} destino, � direita",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � direita"

            },

            "slight right": {

                "default": "Chegou ao seu {nth} destino, � direita",

                "upcoming": "Est� a chegar ao seu {nth} destino, � direita",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � direita"

            },

            "slight left": {

                "default": "Chegou ao seu {nth} destino, � esquerda",

                "upcoming": "Est� a chegar ao seu {nth} destino, � esquerda",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, � esquerda"

            },

            "straight": {

                "default": "Chegou ao seu {nth} destino, em frente",

                "upcoming": "Est� a chegar ao seu {nth} destino, em frente",

                "short": "Chegou",

                "short-upcoming": "Est� a chegar",

                "named": "Chegou a {waypoint_name}, em frente"

            }

        },

        "continue": {

            "default": {

                "default": "Vire {modifier}",

                "name": "Vire {modifier} para se manter em {way_name}",

                "destination": "Vire {modifier} em dire��o a {destination}",

                "exit": "Vire {modifier} para {way_name}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente para se manter em {way_name}",

                "destination": "Continue em dire��o a {destination}",

                "distance": "Continue em frente por {distance}",

                "namedistance": "Continue em {way_name} por {distance}"

            },

            "sharp left": {

                "default": "Vire acentuadamente � esquerda",

                "name": "Vire acentuadamente � esquerda para se manter em {way_name}",

                "destination": "Vire acentuadamente � esquerda em dire��o a {destination}"

            },

            "sharp right": {

                "default": "Vire acentuadamente � direita",

                "name": "Vire acentuadamente � direita para se manter em {way_name}",

                "destination": "Vire acentuadamente � direita em dire��o a {destination}"

            },

            "slight left": {

                "default": "Vire ligeiramente � esquerda",

                "name": "Vire ligeiramente � esquerda para se manter em {way_name}",

                "destination": "Vire ligeiramente � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Vire ligeiramente � direita",

                "name": "Vire ligeiramente � direita para se manter em {way_name}",

                "destination": "Vire ligeiramente � direita em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a invers�o de marcha",

                "name": "Fa�a invers�o de marcha e continue em {way_name}",

                "destination": "Fa�a invers�o de marcha em dire��o a {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Dirija-se para {direction}",

                "name": "Dirija-se para {direction} em {way_name}",

                "namedistance": "Dirija-se para {direction} em {way_name} por {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Vire {modifier}",

                "name": "Vire {modifier} para {way_name}",

                "destination": "Vire {modifier} em dire��o a {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente para {way_name}",

                "destination": "Continue em frente em dire��o a {destination}"

            },

            "uturn": {

                "default": "No final da estrada fa�a uma invers�o de marcha",

                "name": "No final da estrada fa�a uma invers�o de marcha para {way_name} ",

                "destination": "No final da estrada fa�a uma invers�o de marcha em dire��o a {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "Na bifurca��o mantenha-se {modifier}",

                "name": "Mantenha-se {modifier} para {way_name}",

                "destination": "Mantenha-se {modifier} em dire��o a {destination}"

            },

            "slight left": {

                "default": "Na bifurca��o mantenha-se � esquerda",

                "name": "Mantenha-se � esquerda para {way_name}",

                "destination": "Mantenha-se � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Na bifurca��o mantenha-se � direita",

                "name": "Mantenha-se � direita para {way_name}",

                "destination": "Mantenha-se � direita em dire��o a {destination}"

            },

            "sharp left": {

                "default": "Na bifurca��o vire acentuadamente � esquerda",

                "name": "Vire acentuadamente � esquerda para {way_name}",

                "destination": "Vire acentuadamente � esquerda em dire��o a {destination}"

            },

            "sharp right": {

                "default": "Na bifurca��o vire acentuadamente � direita",

                "name": "Vire acentuadamente � direita para {way_name}",

                "destination": "Vire acentuadamente � direita em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a invers�o de marcha",

                "name": "Fa�a invers�o de marcha para {way_name}",

                "destination": "Fa�a invers�o de marcha em dire��o a {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Una-se ao tr�fego {modifier}",

                "name": "Una-se ao tr�fego {modifier} para {way_name}",

                "destination": "Una-se ao tr�fego {modifier} em dire��o a {destination}"

            },

            "straight": {

                "default": "Una-se ao tr�fego",

                "name": " Una-se ao tr�fego para {way_name}",

                "destination": "Una-se ao tr�fego em dire��o a {destination}"

            },

            "slight left": {

                "default": "Una-se ao tr�fego � esquerda",

                "name": "Una-se ao tr�fego � esquerda para {way_name}",

                "destination": "Una-se ao tr�fego � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Una-se ao tr�fego � direita",

                "name": "Una-se ao tr�fego � direita para {way_name}",

                "destination": "Una-se ao tr�fego � direita em dire��o a {destination}"

            },

            "sharp left": {

                "default": "Una-se ao tr�fego � esquerda",

                "name": "Una-se ao tr�fego � esquerda para {way_name}",

                "destination": "Una-se ao tr�fego � esquerda em dire��o a {destination}"

            },

            "sharp right": {

                "default": "Una-se ao tr�fego � direita",

                "name": "Una-se ao tr�fego � direita para {way_name}",

                "destination": "Una-se ao tr�fego � direita em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a invers�o de marcha",

                "name": "Fa�a invers�o de marcha para {way_name}",

                "destination": "Fa�a invers�o de marcha em dire��o a {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} para {way_name}",

                "destination": "Continue {modifier} em dire��o a {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue para {way_name}",

                "destination": "Continue em dire��o a {destination}"

            },

            "sharp left": {

                "default": "Vire acentuadamente � esquerda",

                "name": "Vire acentuadamente � esquerda para {way_name}",

                "destination": "Vire acentuadamente � esquerda em dire��o a{destination}"

            },

            "sharp right": {

                "default": "Vire acentuadamente � direita",

                "name": "Vire acentuadamente � direita para {way_name}",

                "destination": "Vire acentuadamente � direita em dire��o a {destination}"

            },

            "slight left": {

                "default": "Continue ligeiramente � esquerda",

                "name": "Continue ligeiramente � esquerda para {way_name}",

                "destination": "Continue ligeiramente � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Continue ligeiramente � direita",

                "name": "Continue ligeiramente � direita para {way_name}",

                "destination": "Continue ligeiramente � direita em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a invers�o de marcha",

                "name": "Fa�a invers�o de marcha para {way_name}",

                "destination": "Fa�a invers�o de marcha em dire��o a {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Continue {modifier}",

                "name": "Continue {modifier} para {way_name}",

                "destination": "Continue {modifier} em dire��o a {destination}"

            },

            "uturn": {

                "default": "Fa�a invers�o de marcha",

                "name": "Fa�a invers�o de marcha para {way_name}",

                "destination": "Fa�a invers�o de marcha em dire��o a {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Saia na sa�da",

                "name": "Saia na sa�da para {way_name}",

                "destination": "Saia na sa�da em dire��o a {destination}",

                "exit": "Saia na sa�da {exit}",

                "exit_destination": "Saia na sa�da {exit} em dire��o a {destination}"

            },

            "left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � esquerda",

                "exit_destination": "Saia na sa�da {exit} � esquerda em dire��o a {destination}"

            },

            "right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � direita",

                "exit_destination": "Saia na sa�da {exit} � direita em dire��o a {destination}"

            },

            "sharp left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � esquerda",

                "exit_destination": "Saia na sa�da {exit} � esquerda em dire��o a {destination}"

            },

            "sharp right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � direita",

                "exit_destination": "Saia na sa�da {exit} � direita em dire��o a {destination}"

            },

            "slight left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � esquerda",

                "exit_destination": "Saia na sa�da {exit} � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}",

                "exit": "Saia na sa�da {exit} � direita",

                "exit_destination": "Saia na sa�da {exit} � direita em dire��o a {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Saia na sa�da",

                "name": "Saia na sa�da para {way_name}",

                "destination": "Saia na sa�da em dire��o a {destination}"

            },

            "left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}"

            },

            "right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}"

            },

            "sharp left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}"

            },

            "sharp right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}"

            },

            "slight left": {

                "default": "Saia na sa�da � esquerda",

                "name": "Saia na sa�da � esquerda para {way_name}",

                "destination": "Saia na sa�da � esquerda em dire��o a {destination}"

            },

            "slight right": {

                "default": "Saia na sa�da � direita",

                "name": "Saia na sa�da � direita para {way_name}",

                "destination": "Saia na sa�da � direita em dire��o a {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Entre na rotunda",

                    "name": "Entre na rotunda e saia para {way_name}",

                    "destination": "Entre na rotunda e saia em dire��o a {destination}"

                },

                "name": {

                    "default": "Entre em {rotary_name}",

                    "name": "Entre em {rotary_name} e saia para {way_name}",

                    "destination": "Entre em {rotary_name} e saia em dire��o a {destination}"

                },

                "exit": {

                    "default": "Entre na rotunda e saia na sa�da {exit_number}",

                    "name": "Entre na rotunda e saia na sa�da {exit_number} para {way_name}",

                    "destination": "Entre na rotunda e saia na sa�da {exit_number} em dire��o a {destination}"

                },

                "name_exit": {

                    "default": "Entre em {rotary_name} e saia na sa�da {exit_number}",

                    "name": "Entre em {rotary_name} e saia na sa�da {exit_number} para {way_name}",

                    "destination": "Entre em{rotary_name} e saia na sa�da {exit_number} em dire��o a {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Entre na rotunda e saia na sa�da {exit_number}",

                    "name": "Entre na rotunda e saia na sa�da {exit_number} para {way_name}",

                    "destination": "Entre na rotunda e saia na sa�da {exit_number} em dire��o a {destination}"

                },

                "default": {

                    "default": "Entre na rotunda",

                    "name": "Entre na rotunda e saia para {way_name}",

                    "destination": "Entre na rotunda e saia em dire��o a {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Siga {modifier}",

                "name": "Siga {modifier} para {way_name}",

                "destination": "Siga {modifier} em dire��o a {destination}"

            },

            "left": {

                "default": "Vire � esquerda",

                "name": "Vire � esquerda para {way_name}",

                "destination": "Vire � esquerda em dire��o a {destination}"

            },

            "right": {

                "default": "Vire � direita",

                "name": "Vire � direita para {way_name}",

                "destination": "Vire � direita em dire��o a {destination}"

            },

            "straight": {

                "default": "Continue em frente",

                "name": "Continue em frente para {way_name}",

                "destination": "Continue em frente em dire��o a {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Saia da rotunda",

                "name": "Saia da rotunda para {way_name}",

                "destination": "Saia da rotunda em dire��o a {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Saia da rotunda",

                "name": "Saia da rotunda para {way_name}",

                "destination": "Saia da rotunda em dire��o a {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Siga {modifier}",

                "name": "Siga {modifier} para{way_name}",

                "destination": "Siga {modifier} em dire��o a {destination}"

            },

            "left": {

                "default": "Vire � esquerda",

                "name": "Vire � esquerda para {way_name}",

                "destination": "Vire � esquerda em dire��o a {destination}"

            },

            "right": {

                "default": "Vire � direita",

                "name": "Vire � direita para {way_name}",

                "destination": "Vire � direita em dire��o a {destination}"

            },

            "straight": {

                "default": "V� em frente",

                "name": "V� em frente para {way_name}",

                "destination": "V� em frente em dire��o a {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Continue em frente"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],41:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "prima",

                "2": "a doua",

                "3": "a treia",

                "4": "a patra",

                "5": "a cincea",

                "6": "a ?asea",

                "7": "a ?aptea",

                "8": "a opta",

                "9": "a noua",

                "10": "a zecea"

            },

            "direction": {

                "north": "nord",

                "northeast": "nord-est",

                "east": "est",

                "southeast": "sud-est",

                "south": "sud",

                "southwest": "sud-vest",

                "west": "vest",

                "northwest": "nord-vest"

            },

            "modifier": {

                "left": "st�nga",

                "right": "dreapta",

                "sharp left": "puternic st�nga",

                "sharp right": "puternic dreapta",

                "slight left": "u?or st�nga",

                "slight right": "u?or dreapta",

                "straight": "�nainte",

                "uturn": "�ntoarcere"

            },

            "lanes": {

                "xo": "?ine?i st�nga",

                "ox": "?ine?i dreapta",

                "xox": "?ine?i pe mijloc",

                "oxo": "?ine?i pe laterale"

            }

        },

        "modes": {

            "ferry": {

                "default": "Lua?i feribotul",

                "name": "Lua?i feribotul {way_name}",

                "destination": "Lua?i feribotul spre {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, apoi �n {distance}, {instruction_two}",

            "two linked": "{instruction_one} apoi {instruction_two}",

            "one in distance": "�n {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "ie?irea {exit}"

        },

        "arrive": {

            "default": {

                "default": "A?i ajuns la {nth} destina?ie",

                "upcoming": "A?i ajuns la {nth} destina?ie",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}"

            },

            "left": {

                "default": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe st�nga"

            },

            "right": {

                "default": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe dreapta"

            },

            "sharp left": {

                "default": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe st�nga"

            },

            "sharp right": {

                "default": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe dreapta"

            },

            "slight right": {

                "default": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe dreapta",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe dreapta"

            },

            "slight left": {

                "default": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "upcoming": "A?i ajuns la {nth} destina?ie, pe st�nga",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, pe st�nga"

            },

            "straight": {

                "default": "A?i ajuns la {nth} destina?ie, �n fa?a",

                "upcoming": "A?i ajuns la {nth} destina?ie, �n fa?a",

                "short": "A?i ajuns",

                "short-upcoming": "Ve?i ajunge",

                "named": "A?i ajuns {waypoint_name}, �n fa?a"

            }

        },

        "continue": {

            "default": {

                "default": "Vira?i {modifier}",

                "name": "Vira?i {modifier} pe {way_name}",

                "destination": "Vira?i {modifier} spre {destination}",

                "exit": "Vira?i {modifier} pe {way_name}"

            },

            "straight": {

                "default": "Merge?i �nainte",

                "name": "Merge?i �nainte pe {way_name}",

                "destination": "Continua?i spre {destination}",

                "distance": "Merge?i �nainte pentru {distance}",

                "namedistance": "Continua?i pe {way_name} pentru {distance}"

            },

            "sharp left": {

                "default": "Vira?i puternic la st�nga",

                "name": "Vira?i puternic la st�nga pe {way_name}",

                "destination": "Vira?i puternic la st�nga spre {destination}"

            },

            "sharp right": {

                "default": "Vira?i puternic la dreapta",

                "name": "Vira?i puternic la dreapta pe {way_name}",

                "destination": "Vira?i puternic la dreapta spre {destination}"

            },

            "slight left": {

                "default": "Vira?i u?or la st�nga",

                "name": "Vira?i u?or la st�nga pe {way_name}",

                "destination": "Vira?i u?or la st�nga spre {destination}"

            },

            "slight right": {

                "default": "Vira?i u?or la dreapta",

                "name": "Vira?i u?or la dreapta pe {way_name}",

                "destination": "Vira?i u?or la dreapta spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va",

                "name": "�ntoarce?i-va ?i continua?i pe {way_name}",

                "destination": "�ntoarce?i-va spre {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Merge?i spre {direction}",

                "name": "Merge?i spre {direction} pe {way_name}",

                "namedistance": "Merge?i spre {direction} pe {way_name} pentru {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Vira?i {modifier}",

                "name": "Vira?i {modifier} pe {way_name}",

                "destination": "Vira?i {modifier} spre {destination}"

            },

            "straight": {

                "default": "Continua?i �nainte",

                "name": "Continua?i �nainte pe {way_name}",

                "destination": "Continua?i �nainte spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va la sf�r?itul drumului",

                "name": "�ntoarce?i-va pe {way_name} la sf�r?itul drumului",

                "destination": "�ntoarce?i-va spre {destination} la sf�r?itul drumului"

            }

        },

        "fork": {

            "default": {

                "default": "?ine?i {modifier} la bifurca?ie",

                "name": "?ine?i {modifier} la bifurca?ie pe {way_name}",

                "destination": "?ine?i {modifier} la bifurca?ie spre {destination}"

            },

            "slight left": {

                "default": "?ine?i pe st�nga la bifurca?ie",

                "name": "?ine?i pe st�nga la bifurca?ie pe {way_name}",

                "destination": "?ine?i pe st�nga la bifurca?ie spre {destination}"

            },

            "slight right": {

                "default": "?ine?i pe dreapta la bifurca?ie",

                "name": "?ine?i pe dreapta la bifurca?ie pe {way_name}",

                "destination": "?ine?i pe dreapta la bifurca?ie spre {destination}"

            },

            "sharp left": {

                "default": "Vira?i puternic st�nga la bifurca?ie",

                "name": "Vira?i puternic st�nga la bifurca?ie pe {way_name}",

                "destination": "Vira?i puternic st�nga la bifurca?ie spre {destination}"

            },

            "sharp right": {

                "default": "Vira?i puternic dreapta la bifurca?ie",

                "name": "Vira?i puternic dreapta la bifurca?ie pe {way_name}",

                "destination": "Vira?i puternic dreapta la bifurca?ie spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va",

                "name": "�ntoarce?i-va pe {way_name}",

                "destination": "�ntoarce?i-va spre {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Intra?i �n {modifier}",

                "name": "Intra?i �n {modifier} pe {way_name}",

                "destination": "Intra?i �n {modifier} spre {destination}"

            },

            "straight": {

                "default": "Intra?i",

                "name": "Intra?i pe {way_name}",

                "destination": "Intra?i spre {destination}"

            },

            "slight left": {

                "default": "Intra?i �n st�nga",

                "name": "Intra?i �n st�nga pe {way_name}",

                "destination": "Intra?i �n st�nga spre {destination}"

            },

            "slight right": {

                "default": "Intra?i �n dreapta",

                "name": "Intra?i �n dreapta pe {way_name}",

                "destination": "Intra?i �n dreapta spre {destination}"

            },

            "sharp left": {

                "default": "Intra?i �n st�nga",

                "name": "Intra?i �n st�nga pe {way_name}",

                "destination": "Intra?i �n st�nga spre {destination}"

            },

            "sharp right": {

                "default": "Intra?i �n dreapta",

                "name": "Intra?i �n dreapta pe {way_name}",

                "destination": "Intra?i �n dreapta spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va",

                "name": "�ntoarce?i-va pe {way_name}",

                "destination": "�ntoarce?i-va spre {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Continua?i {modifier}",

                "name": "Continua?i {modifier} pe {way_name}",

                "destination": "Continua?i {modifier} spre {destination}"

            },

            "straight": {

                "default": "Continua?i �nainte",

                "name": "Continua?i pe {way_name}",

                "destination": "Continua?i spre {destination}"

            },

            "sharp left": {

                "default": "Vira?i puternic la st�nga",

                "name": "Vira?i puternic la st�nga pe {way_name}",

                "destination": "Vira?i puternic la st�nga spre {destination}"

            },

            "sharp right": {

                "default": "Vira?i puternic la dreapta",

                "name": "Vira?i puternic la dreapta pe {way_name}",

                "destination": "Vira?i puternic la dreapta spre {destination}"

            },

            "slight left": {

                "default": "Continua?i u?or la st�nga",

                "name": "Continua?i u?or la st�nga pe {way_name}",

                "destination": "Continua?i u?or la st�nga spre {destination}"

            },

            "slight right": {

                "default": "Continua?i u?or la dreapta",

                "name": "Continua?i u?or la dreapta pe {way_name}",

                "destination": "Continua?i u?or la dreapta spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va",

                "name": "�ntoarce?i-va pe {way_name}",

                "destination": "�ntoarce?i-va spre {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Continua?i {modifier}",

                "name": "Continua?i {modifier} pe {way_name}",

                "destination": "Continua?i {modifier} spre {destination}"

            },

            "uturn": {

                "default": "�ntoarce?i-va",

                "name": "�ntoarce?i-va pe {way_name}",

                "destination": "�ntoarce?i-va spre {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Urma?i breteaua",

                "name": "Urma?i breteaua pe {way_name}",

                "destination": "Urma?i breteaua spre {destination}",

                "exit": "Urma?i ie?irea {exit}",

                "exit_destination": "Urma?i ie?irea {exit} spre {destination}"

            },

            "left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe st�nga",

                "exit_destination": "Urma?i ie?irea {exit} pe st�nga spre {destination}"

            },

            "right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe dreapta",

                "exit_destination": "Urma?i ie?irea {exit} pe dreapta spre {destination}"

            },

            "sharp left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe st�nga",

                "exit_destination": "Urma?i ie?irea {exit} pe st�nga spre {destination}"

            },

            "sharp right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe dreapta",

                "exit_destination": "Urma?i ie?irea {exit} pe dreapta spre {destination}"

            },

            "slight left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe st�nga",

                "exit_destination": "Urma?i ie?irea {exit} pe st�nga spre {destination}"

            },

            "slight right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}",

                "exit": "Urma?i ie?irea {exit} pe dreapta",

                "exit_destination": "Urma?i ie?irea {exit} pe dreapta spre {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Urma?i breteaua de intrare",

                "name": "Urma?i breteaua pe {way_name}",

                "destination": "Urma?i breteaua spre {destination}"

            },

            "left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}"

            },

            "right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}"

            },

            "sharp left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}"

            },

            "sharp right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}"

            },

            "slight left": {

                "default": "Urma?i breteaua din st�nga",

                "name": "Urma?i breteaua din st�nga pe {way_name}",

                "destination": "Urma?i breteaua din st�nga spre {destination}"

            },

            "slight right": {

                "default": "Urma?i breteaua din dreapta",

                "name": "Urma?i breteaua din dreapta pe {way_name}",

                "destination": "Urma?i breteaua din dreapta spre {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Intra?i �n sensul giratoriu",

                    "name": "Intra?i �n sensul giratoriu ?i ie?i?i pe {way_name}",

                    "destination": "Intra?i �n sensul giratoriu ?i ie?i?i spre {destination}"

                },

                "name": {

                    "default": "Intra?i �n {rotary_name}",

                    "name": "Intra?i �n {rotary_name} ?i ie?i?i pe {way_name}",

                    "destination": "Intra?i �n {rotary_name} ?i ie?i?i spre {destination}"

                },

                "exit": {

                    "default": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire",

                    "name": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire pe {way_name}",

                    "destination": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire spre {destination}"

                },

                "name_exit": {

                    "default": "Intra?i �n {rotary_name} ?i urma?i {exit_number} ie?ire",

                    "name": "Intra?i �n {rotary_name} ?i urma?i {exit_number} ie?ire pe {way_name}",

                    "destination": "Intra?i �n  {rotary_name} ?i urma?i {exit_number} ie?ire spre {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire",

                    "name": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire pe {way_name}",

                    "destination": "Intra?i �n sensul giratoriu ?i urma?i {exit_number} ie?ire spre {destination}"

                },

                "default": {

                    "default": "Intra?i �n sensul giratoriu",

                    "name": "Intra?i �n sensul giratoriu ?i ie?i?i pe {way_name}",

                    "destination": "Intra?i �n sensul giratoriu ?i ie?i?i spre {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "La sensul giratoriu vira?i {modifier}",

                "name": "La sensul giratoriu vira?i {modifier} pe {way_name}",

                "destination": "La sensul giratoriu vira?i {modifier} spre {destination}"

            },

            "left": {

                "default": "La sensul giratoriu vira?i la st�nga",

                "name": "La sensul giratoriu vira?i la st�nga pe {way_name}",

                "destination": "La sensul giratoriu vira?i la st�nga spre {destination}"

            },

            "right": {

                "default": "La sensul giratoriu vira?i la dreapta",

                "name": "La sensul giratoriu vira?i la dreapta pe {way_name}",

                "destination": "La sensul giratoriu vira?i la dreapta spre {destination}"

            },

            "straight": {

                "default": "La sensul giratoriu continua?i �nainte",

                "name": "La sensul giratoriu continua?i �nainte pe {way_name}",

                "destination": "La sensul giratoriu continua?i �nainte spre {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Ie?i?i din sensul giratoriu",

                "name": "Ie?i?i din sensul giratoriu pe {way_name}",

                "destination": "Ie?i?i din sensul giratoriu spre {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Ie?i?i din sensul giratoriu",

                "name": "Ie?i?i din sensul giratoriu pe {way_name}",

                "destination": "Ie?i?i din sensul giratoriu spre {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Vira?i {modifier}",

                "name": "Vira?i {modifier} pe {way_name}",

                "destination": "Vira?i {modifier} spre {destination}"

            },

            "left": {

                "default": "Vira?i la st�nga",

                "name": "Vira?i la st�nga pe {way_name}",

                "destination": "Vira?i la st�nga spre {destination}"

            },

            "right": {

                "default": "Vira?i la dreapta",

                "name": "Vira?i la dreapta pe {way_name}",

                "destination": "Vira?i la dreapta spre {destination}"

            },

            "straight": {

                "default": "Merge?i �nainte",

                "name": "Merge?i �nainte pe {way_name}",

                "destination": "Merge?i �nainte spre {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Merge?i �nainte"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],42:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "??????",

                "2": "??????",

                "3": "??????",

                "4": "?????????",

                "5": "?????",

                "6": "??????",

                "7": "???????",

                "8": "???????",

                "9": "???????",

                "10": "???????"

            },

            "direction": {

                "north": "????????",

                "northeast": "??????-?????????",

                "east": "?????????",

                "southeast": "???-?????????",

                "south": "?????",

                "southwest": "???-????????",

                "west": "????????",

                "northwest": "??????-????????"

            },

            "modifier": {

                "left": "??????",

                "right": "???????",

                "sharp left": "??????",

                "sharp right": "???????",

                "slight left": "?????",

                "slight right": "??????",

                "straight": "?????",

                "uturn": "?? ????????"

            },

            "lanes": {

                "xo": "????????? ??????",

                "ox": "????????? ?????",

                "xox": "????????? ??????????",

                "oxo": "????????? ????? ??? ??????"

            }

        },

        "modes": {

            "ferry": {

                "default": "??????????? ?? ?????",

                "name": "??????????? ?? ????? {way_name}",

                "destination": "??????????? ?? ????? ? ??????????? {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, ????? ????? {distance} {instruction_two}",

            "two linked": "{instruction_one}, ????? {instruction_two}",

            "one in distance": "????? {distance} {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "????? {exit}"

        },

        "arrive": {

            "default": {

                "default": "?? ??????? ? {nth} ????? ??????????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}"

            },

            "left": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ?????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ?????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ?????"

            },

            "right": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ??????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ??????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ??????"

            },

            "sharp left": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ????? ?????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ????? ?????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ????? ?????"

            },

            "sharp right": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ?????? ?????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ?????? ?????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ?????? ?????"

            },

            "slight right": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ?????? ???????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ?????? ???????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ?????? ???????"

            },

            "slight left": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ????? ???????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ????? ???????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ????? ???????"

            },

            "straight": {

                "default": "?? ??????? ? {nth} ????? ??????????, ?? ????????? ????? ????",

                "upcoming": "?? ????????? ? {nth} ????? ??????????, ?? ????? ????? ????",

                "short": "?? ???????",

                "short-upcoming": "?? ????? ?????????",

                "named": "?? ??????? ? ????? ??????????, {waypoint_name}, ?? ????????? ????? ????"

            }

        },

        "continue": {

            "default": {

                "default": "?????????? {modifier}",

                "name": "?????????? {modifier} ?? {way_name:dative}",

                "destination": "?????????? {modifier} ? ??????????? {destination}",

                "exit": "?????????? {modifier} ?? {way_name:accusative}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ???????? ?? {way_name:dative}",

                "destination": "?????????? ???????? ? ??????????? {destination}",

                "distance": "?????????? ????? {distance}",

                "namedistance": "?????????? ????? {distance} ?? {way_name:dative}"

            },

            "sharp left": {

                "default": "????? ????????? ??????",

                "name": "????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "????? ????????? ?????? ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "????? ????????? ???????",

                "name": "????? ????????? ??????? ?? {way_name:accusative}",

                "destination": "????? ????????? ??????? ? ??????????? {destination}"

            },

            "slight left": {

                "default": "?????? ????????? ??????",

                "name": "?????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "?????? ????????? ?????? ? ??????????? {destination}"

            },

            "slight right": {

                "default": "?????? ????????? ???????",

                "name": "?????? ????????? ??????? ?? {way_name:accusative}",

                "destination": "?????? ????????? ??????? ? ??????????? {destination}"

            },

            "uturn": {

                "default": "????????????",

                "name": "???????????? ? ?????????? ???????? ?? {way_name:dative}",

                "destination": "???????????? ? ??????????? {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "?????????? ? {direction} ???????????",

                "name": "?????????? ? {direction} ??????????? ?? {way_name:dative}",

                "namedistance": "?????????? {distance} ? {direction} ??????????? ?? {way_name:dative}"

            }

        },

        "end of road": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name:accusative}",

                "destination": "????????? {modifier} ? ??????????? {destination}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ????? ?? {way_name:dative}",

                "destination": "?????????? ????? ? ??????????? {destination}"

            },

            "uturn": {

                "default": "? ????? ?????? ????????????",

                "name": "???????????? ? ????? {way_name:genitive}",

                "destination": "? ????? ?????? ???????????? ? ??????????? {destination}"

            }

        },

        "fork": {

            "default": {

                "default": "?? ???????? ?????????? {modifier}",

                "name": "?? ???????? ?????????? {modifier} ?? {way_name:accusative}",

                "destination": "?? ???????? ?????????? {modifier} ? ??????????? {destination}"

            },

            "slight left": {

                "default": "?? ???????? ????????? ?????",

                "name": "?? ???????? ????????? ????? ?? {way_name:accusative}",

                "destination": "?? ???????? ????????? ????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "slight right": {

                "default": "?? ???????? ????????? ??????",

                "name": "?? ???????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "?? ???????? ????????? ?????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "sharp left": {

                "default": "?? ???????? ????? ????????? ??????",

                "name": "????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "????? ????????? ?????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "?? ???????? ????? ????????? ???????",

                "name": "????? ????????? ??????? ?? {way_name:accusative}",

                "destination": "????? ????????? ??????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "uturn": {

                "default": "?? ???????? ????????????",

                "name": "?? ???????? ???????????? ?? {way_name:prepositional}",

                "destination": "?? ???????? ???????????? ? ?????????? ???????? ? ??????????? {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "????????????? {modifier}",

                "name": "????????????? {modifier} ?? {way_name:accusative}",

                "destination": "????????????? {modifier} ? ??????????? {destination}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ???????? ?? {way_name:dative}",

                "destination": "?????????? ???????? ? ??????????? {destination}"

            },

            "slight left": {

                "default": "????????????? ?????",

                "name": "????????????? ????? ?? {way_name:accusative}",

                "destination": "????????????? ????? ? ??????????? {destination}"

            },

            "slight right": {

                "default": "????????????? ??????",

                "name": "????????????? ?????? ?? {way_name:accusative}",

                "destination": "????????????? ?????? ? ??????????? {destination}"

            },

            "sharp left": {

                "default": "???????????????? ?????",

                "name": "???????????????? ????? ?? {way_name:accusative}",

                "destination": "???????????????? ????? ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "???????????????? ??????",

                "name": "???????????????? ?????? ?? {way_name:accusative}",

                "destination": "???????????????? ?????? ? ??????????? {destination}"

            },

            "uturn": {

                "default": "????????????",

                "name": "???????????? ?? {way_name:prepositional}",

                "destination": "???????????? ? ??????????? {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "?????????? {modifier}",

                "name": "?????????? {modifier} ?? {way_name:accusative}",

                "destination": "?????????? {modifier} ? ??????????? {destination}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ???????? ?? {way_name:dative}",

                "destination": "?????????? ???????? ? ??????????? {destination}"

            },

            "sharp left": {

                "default": "????? ????????? ??????",

                "name": "????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "????? ????????? ?????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "????? ????????? ???????",

                "name": "????? ????????? ??????? ?? {way_name:accusative}",

                "destination": "????? ????????? ??????? ? ?????????? ???????? ? ??????????? {destination}"

            },

            "slight left": {

                "default": "?????? ????????? ??????",

                "name": "?????? ????????? ?????? ?? {way_name:accusative}",

                "destination": "?????? ????????? ?????? ? ??????????? {destination}"

            },

            "slight right": {

                "default": "?????? ????????? ???????",

                "name": "?????? ????????? ??????? ?? {way_name:accusative}",

                "destination": "?????? ????????? ??????? ? ??????????? {destination}"

            },

            "uturn": {

                "default": "????????????",

                "name": "???????????? ?? {way_name:prepositional}",

                "destination": "???????????? ? ?????????? ???????? ? ??????????? {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "?????????? {modifier}",

                "name": "?????????? {modifier} ?? {way_name:dative}",

                "destination": "?????????? {modifier} ? ??????????? {destination}"

            },

            "uturn": {

                "default": "????????????",

                "name": "???????????? ?? {way_name:prepositional}",

                "destination": "???????????? ? ?????????? ???????? ? ??????????? {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "???????? ?? ?????",

                "name": "???????? ?? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ????? ? ??????????? {destination}",

                "exit": "???????? ?? ????? {exit}",

                "exit_destination": "???????? ?? ????? {exit} ? ??????????? {destination}"

            },

            "left": {

                "default": "???????? ?? ????? ?????",

                "name": "???????? ?? ????? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ????? ????? ? ??????????? {destination}",

                "exit": "???????? ?? ????? {exit} ?????",

                "exit_destination": "???????? ?? ????? {exit} ????? ? ??????????? {destination}"

            },

            "right": {

                "default": "???????? ?? ?????? ?????",

                "name": "???????? ?? ?????? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ?????? ????? ? ??????????? {destination}",

                "exit": "???????? ?? ????? {exit} ??????",

                "exit_destination": "???????? ?? ????? {exit} ?????? ? ??????????? {destination}"

            },

            "sharp left": {

                "default": "????????? ?????? ?? ?????",

                "name": "????????? ?????? ?? ????? ?? {way_name:accusative}",

                "destination": "????????? ?????? ?? ????? ? ??????????? {destination}",

                "exit": "????????? ?????? ?? ????? {exit}",

                "exit_destination": "????????? ?????? ?? ????? {exit} ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "????????? ??????? ?? ?????",

                "name": "????????? ??????? ?? ????? ?? {way_name:accusative}",

                "destination": "????????? ??????? ?? ????? ? ??????????? {destination}",

                "exit": "????????? ??????? ?? ????? {exit}",

                "exit_destination": "????????? ??????? ?? ????? {exit} ? ??????????? {destination}"

            },

            "slight left": {

                "default": "????????????? ????? ?? ?????",

                "name": "????????????? ????? ?? ????? ?? {way_name:accusative}",

                "destination": "????????????? ????? ?? ????? ? ??????????? {destination}",

                "exit": "????????????? ????? ?? {exit}",

                "exit_destination": "????????????? ????? ?? ????? {exit} ? ??????????? {destination}"

            },

            "slight right": {

                "default": "????????????? ?????? ?? ?????",

                "name": "????????????? ?????? ?? ????? ?? {way_name:accusative}",

                "destination": "????????????? ?????? ?? ????? ? ??????????? {destination}",

                "exit": "????????????? ?????? ?? ????? {exit}",

                "exit_destination": "????????????? ?????? ?? ????? {exit} ? ??????????? {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "???????? ?? ??????????????",

                "name": "???????? ?? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "left": {

                "default": "???????? ?? ????? ????? ?? ??????????????",

                "name": "???????? ?? ????? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ????? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "right": {

                "default": "???????? ?? ?????? ????? ?? ??????????????",

                "name": "???????? ?? ?????? ????? ?? {way_name:accusative}",

                "destination": "???????? ?? ?????? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "sharp left": {

                "default": "????????? ?? ????? ????? ?? ??????????????",

                "name": "????????? ?? ????? ????? ?? {way_name:accusative}",

                "destination": "????????? ?? ????? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "sharp right": {

                "default": "????????? ?? ?????? ????? ?? ??????????????",

                "name": "????????? ?? ?????? ????? ?? {way_name:accusative}",

                "destination": "????????? ?? ?????? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "slight left": {

                "default": "????????????? ????? ?? ????? ?? ??????????????",

                "name": "????????????? ????? ?? {way_name:accusative}",

                "destination": "????????????? ????? ?? ?????????????? ? ??????????? {destination}"

            },

            "slight right": {

                "default": "????????????? ?????? ?? ????? ?? ??????????????",

                "name": "????????????? ?????? ?? {way_name:accusative}",

                "destination": "????????????? ?????? ?? ?????????????? ? ??????????? {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "?????????? ???????? ?? ???????? ????????",

                    "name": "?? ???????? ???????? ???????? ?? {way_name:accusative}",

                    "destination": "?? ???????? ???????? ???????? ? ??????????? {destination}"

                },

                "name": {

                    "default": "?????????? ???????? ?? {rotary_name:dative}",

                    "name": "?? {rotary_name:prepositional} ???????? ?? {way_name:accusative}",

                    "destination": "?? {rotary_name:prepositional} ???????? ? ??????????? {destination}"

                },

                "exit": {

                    "default": "?? ???????? ???????? ???????? ?? {exit_number} ?????",

                    "name": "?? ???????? ???????? ???????? ?? {exit_number} ????? ?? {way_name:accusative}",

                    "destination": "?? ???????? ???????? ???????? ?? {exit_number} ????? ? ??????????? {destination}"

                },

                "name_exit": {

                    "default": "?? {rotary_name:prepositional} ???????? ?? {exit_number} ?????",

                    "name": "?? {rotary_name:prepositional} ???????? ?? {exit_number} ????? ?? {way_name:accusative}",

                    "destination": "?? {rotary_name:prepositional} ???????? ?? {exit_number} ????? ? ??????????? {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "?? ???????? ???????? ???????? ?? {exit_number} ?????",

                    "name": "?? ???????? ???????? ???????? ?? {exit_number} ????? ?? {way_name:accusative}",

                    "destination": "?? ???????? ???????? ???????? ?? {exit_number} ????? ? ??????????? {destination}"

                },

                "default": {

                    "default": "?????????? ???????? ?? ???????? ????????",

                    "name": "?? ???????? ???????? ???????? ?? {way_name:accusative}",

                    "destination": "?? ???????? ???????? ???????? ? ??????????? {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "?????????? {modifier}",

                "name": "?????????? {modifier} ?? {way_name:accusative}",

                "destination": "?????????? {modifier} ? ??????????? {destination}"

            },

            "left": {

                "default": "???????? ??????",

                "name": "???????? ?????? ?? {way_name:accusative}",

                "destination": "???????? ?????? ? ??????????? {destination}"

            },

            "right": {

                "default": "???????? ???????",

                "name": "???????? ??????? ?? {way_name:accusative}",

                "destination": "???????? ??????? ? ??????????? {destination}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ????? ?? {way_name:dative}",

                "destination": "?????????? ????? ? ??????????? {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "???????? ? ???????? ????????",

                "name": "???????? ? ???????? ???????? ?? {way_name:accusative}",

                "destination": "???????? ? ???????? ???????? ? ??????????? {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "???????? ? ???????? ????????",

                "name": "???????? ? ???????? ???????? ?? {way_name:accusative}",

                "destination": "???????? ? ???????? ???????? ? ??????????? {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "?????????? {modifier}",

                "name": "?????????? {modifier} ?? {way_name:accusative}",

                "destination": "?????????? {modifier}  ? ??????????? {destination}"

            },

            "left": {

                "default": "????????? ??????",

                "name": "????????? ?????? ?? {way_name:accusative}",

                "destination": "????????? ?????? ? ??????????? {destination}"

            },

            "right": {

                "default": "????????? ???????",

                "name": "????????? ??????? ?? {way_name:accusative}",

                "destination": "????????? ???????  ? ??????????? {destination}"

            },

            "straight": {

                "default": "?????????? ?????",

                "name": "?????????? ?? {way_name:dative}",

                "destination": "?????????? ? ??????????? {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "??????????? ???????? ?????"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],43:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1:a",

                "2": "2:a",

                "3": "3:e",

                "4": "4:e",

                "5": "5:e",

                "6": "6:e",

                "7": "7:e",

                "8": "8:e",

                "9": "9:e",

                "10": "10:e"

            },

            "direction": {

                "north": "norr",

                "northeast": "nordost",

                "east": "�ster",

                "southeast": "sydost",

                "south": "s�der",

                "southwest": "sydv�st",

                "west": "v�ster",

                "northwest": "nordv�st"

            },

            "modifier": {

                "left": "v�nster",

                "right": "h�ger",

                "sharp left": "v�nster",

                "sharp right": "h�ger",

                "slight left": "v�nster",

                "slight right": "h�ger",

                "straight": "rakt fram",

                "uturn": "U-sv�ng"

            },

            "lanes": {

                "xo": "H�ll till h�ger",

                "ox": "H�ll till v�nster",

                "xox": "H�ll till mitten",

                "oxo": "H�ll till v�nster eller h�ger"

            }

        },

        "modes": {

            "ferry": {

                "default": "Ta f�rjan",

                "name": "Ta f�rjan p� {way_name}",

                "destination": "Ta f�rjan mot {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, sedan efter {distance}, {instruction_two}",

            "two linked": "{instruction_one}, sedan {instruction_two}",

            "one in distance": "Om {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "Du �r framme vid din {nth} destination",

                "upcoming": "Du �r snart framme vid din {nth} destination",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}"

            },

            "left": {

                "default": "Du �r framme vid din {nth} destination, till v�nster",

                "upcoming": "Du �r snart framme vid din {nth} destination, till v�nster",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till v�nster"

            },

            "right": {

                "default": "Du �r framme vid din {nth} destination, till h�ger",

                "upcoming": "Du �r snart framme vid din {nth} destination, till h�ger",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till h�ger"

            },

            "sharp left": {

                "default": "Du �r framme vid din {nth} destination, till v�nster",

                "upcoming": "Du �r snart framme vid din {nth} destination, till v�nster",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till v�nster"

            },

            "sharp right": {

                "default": "Du �r framme vid din {nth} destination, till h�ger",

                "upcoming": "Du �r snart framme vid din {nth} destination, till h�ger",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till h�ger"

            },

            "slight right": {

                "default": "Du �r framme vid din {nth} destination, till h�ger",

                "upcoming": "Du �r snart framme vid din {nth} destination, till h�ger",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till h�ger"

            },

            "slight left": {

                "default": "Du �r framme vid din {nth} destination, till v�nster",

                "upcoming": "Du �r snart framme vid din {nth} destination, till v�nster",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, till v�nster"

            },

            "straight": {

                "default": "Du �r framme vid din {nth} destination, rakt fram",

                "upcoming": "Du �r snart framme vid din {nth} destination, rakt fram",

                "short": "Du �r framme",

                "short-upcoming": "Du �r snart framme",

                "named": "Du �r framme vid {waypoint_name}, rakt fram"

            }

        },

        "continue": {

            "default": {

                "default": "Sv�ng {modifier}",

                "name": "Sv�ng {modifier} och forts�tt p� {way_name}",

                "destination": "Sv�ng {modifier} mot {destination}",

                "exit": "Sv�ng {modifier} in p� {way_name}"

            },

            "straight": {

                "default": "Forts�tt rakt fram",

                "name": "K�r rakt fram och forts�tt p� {way_name}",

                "destination": "Forts�tt mot {destination}",

                "distance": "Forts�tt rakt fram i {distance}",

                "namedistance": "Forts�tt p� {way_name} i {distance}"

            },

            "sharp left": {

                "default": "Sv�ng v�nster",

                "name": "Sv�ng v�nster och forts�tt p� {way_name}",

                "destination": "Sv�ng v�nster mot {destination}"

            },

            "sharp right": {

                "default": "Sv�ng h�ger",

                "name": "Sv�ng h�ger och forts�tt p� {way_name}",

                "destination": "Sv�ng h�ger mot {destination}"

            },

            "slight left": {

                "default": "Sv�ng v�nster",

                "name": "Sv�ng v�nster och forts�tt p� {way_name}",

                "destination": "Sv�ng v�nster mot {destination}"

            },

            "slight right": {

                "default": "Sv�ng h�ger",

                "name": "Sv�ng h�ger och forts�tt p� {way_name}",

                "destination": "Sv�ng h�ger mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng",

                "name": "G�r en U-sv�ng och forts�tt p� {way_name}",

                "destination": "G�r en U-sv�ng mot {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "K�r �t {direction}",

                "name": "K�r �t {direction} p� {way_name}",

                "namedistance": "K�r {distance} �t {direction} p� {way_name}"

            }

        },

        "end of road": {

            "default": {

                "default": "Sv�ng {modifier}",

                "name": "Sv�ng {modifier} in p� {way_name}",

                "destination": "Sv�ng {modifier} mot {destination}"

            },

            "straight": {

                "default": "Forts�tt rakt fram",

                "name": "Forts�tt rakt fram in p� {way_name}",

                "destination": "Forts�tt rakt fram mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng i slutet av v�gen",

                "name": "G�r en U-sv�ng in p� {way_name} i slutet av v�gen",

                "destination": "G�r en U-sv�ng mot {destination} i slutet av v�gen"

            }

        },

        "fork": {

            "default": {

                "default": "H�ll till {modifier} d�r v�gen delar sig",

                "name": "H�ll till {modifier} in p� {way_name}",

                "destination": "H�ll till {modifier} mot {destination}"

            },

            "slight left": {

                "default": "H�ll till v�nster d�r v�gen delar sig",

                "name": "H�ll till v�nster in p� {way_name}",

                "destination": "H�ll till v�nster mot {destination}"

            },

            "slight right": {

                "default": "H�ll till h�ger d�r v�gen delar sig",

                "name": "H�ll till h�ger in p� {way_name}",

                "destination": "H�ll till h�ger mot {destination}"

            },

            "sharp left": {

                "default": "Sv�ng v�nster d�r v�gen delar sig",

                "name": "Sv�ng v�nster in p� {way_name}",

                "destination": "Sv�ng v�nster mot {destination}"

            },

            "sharp right": {

                "default": "Sv�ng h�ger d�r v�gen delar sig",

                "name": "Sv�ng h�ger in p� {way_name}",

                "destination": "Sv�ng h�ger mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng",

                "name": "G�r en U-sv�ng in p� {way_name}",

                "destination": "G�r en U-sv�ng mot {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Byt till {modifier} k�rf�lt",

                "name": "Byt till {modifier} k�rf�lt, in p� {way_name}",

                "destination": "Byt till {modifier} k�rf�lt, mot {destination}"

            },

            "straight": {

                "default": "Forts�tt",

                "name": "K�r in p� {way_name}",

                "destination": "K�r mot {destination}"

            },

            "slight left": {

                "default": "Byt till v�nstra k�rf�ltet",

                "name": "Byt till v�nstra k�rf�ltet, in p� {way_name}",

                "destination": "Byt till v�nstra k�rf�ltet, mot {destination}"

            },

            "slight right": {

                "default": "Byt till h�gra k�rf�ltet",

                "name": "Byt till h�gra k�rf�ltet, in p� {way_name}",

                "destination": "Byt till h�gra k�rf�ltet, mot {destination}"

            },

            "sharp left": {

                "default": "Byt till v�nstra k�rf�ltet",

                "name": "Byt till v�nstra k�rf�ltet, in p� {way_name}",

                "destination": "Byt till v�nstra k�rf�ltet, mot {destination}"

            },

            "sharp right": {

                "default": "Byt till h�gra k�rf�ltet",

                "name": "Byt till h�gra k�rf�ltet, in p� {way_name}",

                "destination": "Byt till h�gra k�rf�ltet, mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng",

                "name": "G�r en U-sv�ng in p� {way_name}",

                "destination": "G�r en U-sv�ng mot {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Forts�tt {modifier}",

                "name": "Forts�tt {modifier} p� {way_name}",

                "destination": "Forts�tt {modifier} mot {destination}"

            },

            "straight": {

                "default": "Forts�tt rakt fram",

                "name": "Forts�tt in p� {way_name}",

                "destination": "Forts�tt mot {destination}"

            },

            "sharp left": {

                "default": "G�r en skarp v�nstersv�ng",

                "name": "G�r en skarp v�nstersv�ng in p� {way_name}",

                "destination": "G�r en skarp v�nstersv�ng mot {destination}"

            },

            "sharp right": {

                "default": "G�r en skarp h�gersv�ng",

                "name": "G�r en skarp h�gersv�ng in p� {way_name}",

                "destination": "G�r en skarp h�gersv�ng mot {destination}"

            },

            "slight left": {

                "default": "Forts�tt med l�tt v�nstersv�ng",

                "name": "Forts�tt med l�tt v�nstersv�ng in p� {way_name}",

                "destination": "Forts�tt med l�tt v�nstersv�ng mot {destination}"

            },

            "slight right": {

                "default": "Forts�tt med l�tt h�gersv�ng",

                "name": "Forts�tt med l�tt h�gersv�ng in p� {way_name}",

                "destination": "Forts�tt med l�tt h�gersv�ng mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng",

                "name": "G�r en U-sv�ng in p� {way_name}",

                "destination": "G�r en U-sv�ng mot {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Forts�tt {modifier}",

                "name": "Forts�tt {modifier} p� {way_name}",

                "destination": "Forts�tt {modifier} mot {destination}"

            },

            "uturn": {

                "default": "G�r en U-sv�ng",

                "name": "G�r en U-sv�ng in p� {way_name}",

                "destination": "G�r en U-sv�ng mot {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Ta avfarten",

                "name": "Ta avfarten in p� {way_name}",

                "destination": "Ta avfarten mot {destination}",

                "exit": "Ta avfart {exit} ",

                "exit_destination": "Ta avfart {exit} mot {destination}"

            },

            "left": {

                "default": "Ta avfarten till v�nster",

                "name": "Ta avfarten till v�nster in p� {way_name}",

                "destination": "Ta avfarten till v�nster mot {destination}",

                "exit": "Ta avfart {exit} till v�nster",

                "exit_destination": "Ta avfart {exit} till v�nster mot {destination}"

            },

            "right": {

                "default": "Ta avfarten till h�ger",

                "name": "Ta avfarten till h�ger in p� {way_name}",

                "destination": "Ta avfarten till h�ger mot {destination}",

                "exit": "Ta avfart {exit} till h�ger",

                "exit_destination": "Ta avfart {exit} till h�ger mot {destination}"

            },

            "sharp left": {

                "default": "Ta avfarten till v�nster",

                "name": "Ta avfarten till v�nster in p� {way_name}",

                "destination": "Ta avfarten till v�nster mot {destination}",

                "exit": "Ta avfart {exit} till v�nster",

                "exit_destination": "Ta avfart {exit} till v�nster mot {destination}"

            },

            "sharp right": {

                "default": "Ta avfarten till h�ger",

                "name": "Ta avfarten till h�ger in p� {way_name}",

                "destination": "Ta avfarten till h�ger mot {destination}",

                "exit": "Ta avfart {exit} till h�ger",

                "exit_destination": "Ta avfart {exit} till h�ger mot {destination}"

            },

            "slight left": {

                "default": "Ta avfarten till v�nster",

                "name": "Ta avfarten till v�nster in p� {way_name}",

                "destination": "Ta avfarten till v�nster mot {destination}",

                "exit": "Ta avfart {exit} till v�nster",

                "exit_destination": "Ta avfart{exit} till v�nster mot {destination}"

            },

            "slight right": {

                "default": "Ta avfarten till h�ger",

                "name": "Ta avfarten till h�ger in p� {way_name}",

                "destination": "Ta avfarten till h�ger mot {destination}",

                "exit": "Ta avfart {exit} till h�ger",

                "exit_destination": "Ta avfart {exit} till h�ger mot {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Ta p�farten",

                "name": "Ta p�farten in p� {way_name}",

                "destination": "Ta p�farten mot {destination}"

            },

            "left": {

                "default": "Ta p�farten till v�nster",

                "name": "Ta p�farten till v�nster in p� {way_name}",

                "destination": "Ta p�farten till v�nster mot {destination}"

            },

            "right": {

                "default": "Ta p�farten till h�ger",

                "name": "Ta p�farten till h�ger in p� {way_name}",

                "destination": "Ta p�farten till h�ger mot {destination}"

            },

            "sharp left": {

                "default": "Ta p�farten till v�nster",

                "name": "Ta p�farten till v�nster in p� {way_name}",

                "destination": "Ta p�farten till v�nster mot {destination}"

            },

            "sharp right": {

                "default": "Ta p�farten till h�ger",

                "name": "Ta p�farten till h�ger in p� {way_name}",

                "destination": "Ta p�farten till h�ger mot {destination}"

            },

            "slight left": {

                "default": "Ta p�farten till v�nster",

                "name": "Ta p�farten till v�nster in p� {way_name}",

                "destination": "Ta p�farten till v�nster mot {destination}"

            },

            "slight right": {

                "default": "Ta p�farten till h�ger",

                "name": "Ta p�farten till h�ger in p� {way_name}",

                "destination": "Ta p�farten till h�ger mot {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "K�r in i rondellen",

                    "name": "I rondellen, ta avfarten in p� {way_name}",

                    "destination": "I rondellen, ta av mot {destination}"

                },

                "name": {

                    "default": "K�r in i {rotary_name}",

                    "name": "I {rotary_name}, ta av in p� {way_name}",

                    "destination": "I {rotary_name}, ta av mot {destination}"

                },

                "exit": {

                    "default": "I rondellen, ta {exit_number} avfarten",

                    "name": "I rondellen, ta {exit_number} avfarten in p� {way_name}",

                    "destination": "I rondellen, ta {exit_number} avfarten mot {destination}"

                },

                "name_exit": {

                    "default": "I {rotary_name}, ta {exit_number} avfarten",

                    "name": "I {rotary_name}, ta {exit_number}  avfarten in p� {way_name}",

                    "destination": "I {rotary_name}, ta {exit_number} avfarten mot {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "I rondellen, ta {exit_number} avfarten",

                    "name": "I rondellen, ta {exit_number} avfarten in p� {way_name}",

                    "destination": "I rondellen, ta {exit_number} avfarten mot {destination}"

                },

                "default": {

                    "default": "K�r in i rondellen",

                    "name": "I rondellen, ta avfarten in p� {way_name}",

                    "destination": "I rondellen, ta av mot {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Sv�ng {modifier}",

                "name": "Sv�ng {modifier} in p� {way_name}",

                "destination": "Sv�ng {modifier} mot {destination}"

            },

            "left": {

                "default": "Sv�ng v�nster",

                "name": "Sv�ng v�nster in p� {way_name}",

                "destination": "Sv�ng v�nster mot {destination}"

            },

            "right": {

                "default": "Sv�ng h�ger",

                "name": "Sv�ng h�ger in p� {way_name}",

                "destination": "Sv�ng h�ger mot {destination}"

            },

            "straight": {

                "default": "Forts�tt rakt fram",

                "name": "Forts�tt rakt fram in p� {way_name}",

                "destination": "Forts�tt rakt fram mot {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "K�r ut ur rondellen",

                "name": "K�r ut ur rondellen in p� {way_name}",

                "destination": "K�r ut ur rondellen mot {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "K�r ut ur rondellen",

                "name": "K�r ut ur rondellen in p� {way_name}",

                "destination": "K�r ut ur rondellen mot {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Sv�ng {modifier}",

                "name": "Sv�ng {modifier} in p� {way_name}",

                "destination": "Sv�ng {modifier} mot {destination}"

            },

            "left": {

                "default": "Sv�ng v�nster",

                "name": "Sv�ng v�nster in p� {way_name}",

                "destination": "Sv�ng v�nster mot {destination}"

            },

            "right": {

                "default": "Sv�ng h�ger",

                "name": "Sv�ng h�ger in p� {way_name}",

                "destination": "Sv�ng h�ger mot {destination}"

            },

            "straight": {

                "default": "K�r rakt fram",

                "name": "K�r rakt fram in p� {way_name}",

                "destination": "K�r rakt fram mot {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Forts�tt rakt fram"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],44:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "birinci",

                "2": "ikinci",

                "3": "���nc�",

                "4": "d�rd�nc�",

                "5": "besinci",

                "6": "altinci",

                "7": "yedinci",

                "8": "sekizinci",

                "9": "dokuzuncu",

                "10": "onuncu"

            },

            "direction": {

                "north": "kuzey",

                "northeast": "kuzeydogu",

                "east": "dogu",

                "southeast": "g�neydogu",

                "south": "g�ney",

                "southwest": "g�neybati",

                "west": "bati",

                "northwest": "kuzeybati"

            },

            "modifier": {

                "left": "sol",

                "right": "sag",

                "sharp left": "keskin sol",

                "sharp right": "keskin sag",

                "slight left": "hafif sol",

                "slight right": "hafif sag",

                "straight": "d�z",

                "uturn": "U d�n�s�"

            },

            "lanes": {

                "xo": "Sagda kalin",

                "ox": "Solda kalin",

                "xox": "Ortada kalin",

                "oxo": "Solda veya sagda kalin"

            }

        },

        "modes": {

            "ferry": {

                "default": "Vapur kullan",

                "name": "{way_name} vapurunu kullan",

                "destination": "{destination} istikametine giden vapuru kullan"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one} ve {distance} sonra {instruction_two}",

            "two linked": "{instruction_one} ve sonra {instruction_two}",

            "one in distance": "{distance} sonra, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "exit {exit}"

        },

        "arrive": {

            "default": {

                "default": "{nth} hedefinize ulastiniz",

                "upcoming": "{nth} hedefinize ulastiniz",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz"

            },

            "left": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz solunuzdadir"

            },

            "right": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz saginizdadir"

            },

            "sharp left": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz solunuzdadir"

            },

            "sharp right": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz saginizdadir"

            },

            "slight right": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz saginizdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz saginizdadir"

            },

            "slight left": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz solunuzdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz solunuzdadir"

            },

            "straight": {

                "default": "{nth} hedefinize ulastiniz, hedefiniz karsinizdadir",

                "upcoming": "{nth} hedefinize ulastiniz, hedefiniz karsinizdadir",

                "short": "{nth} hedefinize ulastiniz",

                "short-upcoming": "{nth} hedefinize ulastiniz",

                "named": "{waypoint_name} ulastiniz, hedefiniz karsinizdadir"

            }

        },

        "continue": {

            "default": {

                "default": "{modifier} y�ne d�n",

                "name": "{way_name} �zerinde kalmak i�in {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n",

                "exit": "{way_name} �zerinde {modifier} y�ne d�n"

            },

            "straight": {

                "default": "D�z devam edin",

                "name": "{way_name} �zerinde kalmak i�in d�z devam et",

                "destination": "{destination} istikametinde devam et",

                "distance": "{distance} boyunca d�z devam et",

                "namedistance": "{distance} boyunca {way_name} �zerinde devam et"

            },

            "sharp left": {

                "default": "Sola keskin d�n�s yap",

                "name": "{way_name} �zerinde kalmak i�in sola keskin d�n�s yap",

                "destination": "{destination} istikametinde sola keskin d�n�s yap"

            },

            "sharp right": {

                "default": "Saga keskin d�n�s yap",

                "name": "{way_name} �zerinde kalmak i�in saga keskin d�n�s yap",

                "destination": "{destination} istikametinde saga keskin d�n�s yap"

            },

            "slight left": {

                "default": "Sola hafif d�n�s yap",

                "name": "{way_name} �zerinde kalmak i�in sola hafif d�n�s yap",

                "destination": "{destination} istikametinde sola hafif d�n�s yap"

            },

            "slight right": {

                "default": "Saga hafif d�n�s yap",

                "name": "{way_name} �zerinde kalmak i�in saga hafif d�n�s yap",

                "destination": "{destination} istikametinde saga hafif d�n�s yap"

            },

            "uturn": {

                "default": "U d�n�s� yapin",

                "name": "Bir U-d�n�s� yap ve {way_name} devam et",

                "destination": "{destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "depart": {

            "default": {

                "default": "{direction} tarafina y�nelin",

                "name": "{way_name} �zerinde {direction} y�ne git",

                "namedistance": "Head {direction} on {way_name} for {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "{modifier} tarafa d�n�n",

                "name": "{way_name} �zerinde {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n"

            },

            "straight": {

                "default": "D�z devam edin",

                "name": "{way_name} �zerinde d�z devam et",

                "destination": "{destination} istikametinde d�z devam et"

            },

            "uturn": {

                "default": "Yolun sonunda U d�n�s� yapin",

                "name": "Yolun sonunda {way_name} �zerinde bir U-d�n�s� yap",

                "destination": "Yolun sonunda {destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "fork": {

            "default": {

                "default": "Yol ayriminda {modifier} y�nde kal",

                "name": "{way_name} �zerindeki yol ayriminda {modifier} y�nde kal",

                "destination": "{destination} istikametindeki yol ayriminda {modifier} y�nde kal"

            },

            "slight left": {

                "default": "�atalin solundan devam edin",

                "name": "�atalin solundan {way_name} yoluna dogru ",

                "destination": "{destination} istikametindeki yol ayriminda solda kal"

            },

            "slight right": {

                "default": "�atalin sagindan devam edin",

                "name": "{way_name} �zerindeki yol ayriminda sagda kal",

                "destination": "{destination} istikametindeki yol ayriminda sagda kal"

            },

            "sharp left": {

                "default": "�atalda keskin sola d�n�n",

                "name": "{way_name} yoluna dogru sola keskin d�n�s yapin",

                "destination": "{destination} istikametinde sola keskin d�n�s yap"

            },

            "sharp right": {

                "default": "�atalda keskin saga d�n�n",

                "name": "{way_name} yoluna dogru saga keskin d�n�s yapin",

                "destination": "{destination} istikametinde saga keskin d�n�s yap"

            },

            "uturn": {

                "default": "U d�n�s� yapin",

                "name": "{way_name} yoluna U d�n�s� yapin",

                "destination": "{destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "merge": {

            "default": {

                "default": "{modifier} y�ne gir",

                "name": "{way_name} �zerinde {modifier} y�ne gir",

                "destination": "{destination} istikametinde {modifier} y�ne gir"

            },

            "straight": {

                "default": "d�z y�ne gir",

                "name": "{way_name} �zerinde d�z y�ne gir",

                "destination": "{destination} istikametinde d�z y�ne gir"

            },

            "slight left": {

                "default": "Sola gir",

                "name": "{way_name} �zerinde sola gir",

                "destination": "{destination} istikametinde sola gir"

            },

            "slight right": {

                "default": "Saga gir",

                "name": "{way_name} �zerinde saga gir",

                "destination": "{destination} istikametinde saga gir"

            },

            "sharp left": {

                "default": "Sola gir",

                "name": "{way_name} �zerinde sola gir",

                "destination": "{destination} istikametinde sola gir"

            },

            "sharp right": {

                "default": "Saga gir",

                "name": "{way_name} �zerinde saga gir",

                "destination": "{destination} istikametinde saga gir"

            },

            "uturn": {

                "default": "U d�n�s� yapin",

                "name": "{way_name} yoluna U d�n�s� yapin",

                "destination": "{destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "new name": {

            "default": {

                "default": "{modifier} y�nde devam et",

                "name": "{way_name} �zerinde {modifier} y�nde devam et",

                "destination": "{destination} istikametinde {modifier} y�nde devam et"

            },

            "straight": {

                "default": "D�z devam et",

                "name": "{way_name} �zerinde devam et",

                "destination": "{destination} istikametinde devam et"

            },

            "sharp left": {

                "default": "Sola keskin d�n�s yapin",

                "name": "{way_name} yoluna dogru sola keskin d�n�s yapin",

                "destination": "{destination} istikametinde sola keskin d�n�s yap"

            },

            "sharp right": {

                "default": "Saga keskin d�n�s yapin",

                "name": "{way_name} yoluna dogru saga keskin d�n�s yapin",

                "destination": "{destination} istikametinde saga keskin d�n�s yap"

            },

            "slight left": {

                "default": "Hafif soldan devam edin",

                "name": "{way_name} �zerinde hafif solda devam et",

                "destination": "{destination} istikametinde hafif solda devam et"

            },

            "slight right": {

                "default": "Hafif sagdan devam edin",

                "name": "{way_name} �zerinde hafif sagda devam et",

                "destination": "{destination} istikametinde hafif sagda devam et"

            },

            "uturn": {

                "default": "U d�n�s� yapin",

                "name": "{way_name} yoluna U d�n�s� yapin",

                "destination": "{destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "notification": {

            "default": {

                "default": "{modifier} y�nde devam et",

                "name": "{way_name} �zerinde {modifier} y�nde devam et",

                "destination": "{destination} istikametinde {modifier} y�nde devam et"

            },

            "uturn": {

                "default": "U d�n�s� yapin",

                "name": "{way_name} yoluna U d�n�s� yapin",

                "destination": "{destination} istikametinde bir U-d�n�s� yap"

            }

        },

        "off ramp": {

            "default": {

                "default": "Baglanti yoluna ge�",

                "name": "{way_name} �zerindeki baglanti yoluna ge�",

                "destination": "{destination} istikametine giden baglanti yoluna ge�",

                "exit": "{exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} �ikis yoluna ge�"

            },

            "left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�",

                "exit": "Soldaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sol �ikis yoluna ge�"

            },

            "right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�",

                "exit": "Sagdaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sag �ikis yoluna ge�"

            },

            "sharp left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�",

                "exit": "Soldaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sol �ikis yoluna ge�"

            },

            "sharp right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�",

                "exit": "Sagdaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sag �ikis yoluna ge�"

            },

            "slight left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�",

                "exit": "Soldaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sol �ikis yoluna ge�"

            },

            "slight right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�",

                "exit": "Sagdaki {exit} �ikis yoluna ge�",

                "exit_destination": "{destination} istikametindeki {exit} sag �ikis yoluna ge�"

            }

        },

        "on ramp": {

            "default": {

                "default": "Baglanti yoluna ge�",

                "name": "{way_name} �zerindeki baglanti yoluna ge�",

                "destination": "{destination} istikametine giden baglanti yoluna ge�"

            },

            "left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�"

            },

            "right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�"

            },

            "sharp left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�"

            },

            "sharp right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�"

            },

            "slight left": {

                "default": "Soldaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sol baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sol baglanti yoluna ge�"

            },

            "slight right": {

                "default": "Sagdaki baglanti yoluna ge�",

                "name": "{way_name} �zerindeki sag baglanti yoluna ge�",

                "destination": "{destination} istikametine giden sag baglanti yoluna ge�"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "D�nel kavsaga gir",

                    "name": "D�nel kavsaga gir ve {way_name} �zerinde �ik",

                    "destination": "D�nel kavsaga gir ve {destination} istikametinde �ik"

                },

                "name": {

                    "default": "{rotary_name} d�nel kavsaga gir",

                    "name": "{rotary_name} d�nel kavsaga gir ve {way_name} �zerinde �ik",

                    "destination": "{rotary_name} d�nel kavsaga gir ve {destination} istikametinde �ik"

                },

                "exit": {

                    "default": "D�nel kavsaga gir ve {exit_number} numarali �ikisa gir",

                    "name": "D�nel kavsaga gir ve {way_name} �zerindeki {exit_number} numarali �ikisa gir",

                    "destination": "D�nel kavsaga gir ve {destination} istikametindeki {exit_number} numarali �ikisa gir"

                },

                "name_exit": {

                    "default": "{rotary_name} d�nel kavsaga gir ve {exit_number} numarali �ikisa gir",

                    "name": "{rotary_name} d�nel kavsaga gir ve {way_name} �zerindeki {exit_number} numarali �ikisa gir",

                    "destination": "{rotary_name} d�nel kavsaga gir ve {destination} istikametindeki {exit_number} numarali �ikisa gir"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "G�bekli kavsaga gir ve {exit_number} numarali �ikisa gir",

                    "name": "G�bekli kavsaga gir ve {way_name} �zerindeki {exit_number} numarali �ikisa gir",

                    "destination": "G�bekli kavsaga gir ve {destination} istikametindeki {exit_number} numarali �ikisa gir"

                },

                "default": {

                    "default": "G�bekli kavsaga gir",

                    "name": "G�bekli kavsaga gir ve {way_name} �zerinde �ik",

                    "destination": "G�bekli kavsaga gir ve {destination} istikametinde �ik"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier} y�ne d�n",

                "name": "{way_name} �zerinde {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n"

            },

            "left": {

                "default": "Sola d�n",

                "name": "{way_name} �zerinde sola d�n",

                "destination": "{destination} istikametinde sola d�n"

            },

            "right": {

                "default": "Saga d�n",

                "name": "{way_name} �zerinde saga d�n",

                "destination": "{destination} istikametinde saga d�n"

            },

            "straight": {

                "default": "D�z devam et",

                "name": "{way_name} �zerinde d�z devam et",

                "destination": "{destination} istikametinde d�z devam et"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "{modifier} y�ne d�n",

                "name": "{way_name} �zerinde {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n"

            },

            "left": {

                "default": "Sola d�n",

                "name": "{way_name} �zerinde sola d�n",

                "destination": "{destination} istikametinde sola d�n"

            },

            "right": {

                "default": "Saga d�n",

                "name": "{way_name} �zerinde saga d�n",

                "destination": "{destination} istikametinde saga d�n"

            },

            "straight": {

                "default": "D�z devam et",

                "name": "{way_name} �zerinde d�z devam et",

                "destination": "{destination} istikametinde d�z devam et"

            }

        },

        "exit rotary": {

            "default": {

                "default": "{modifier} y�ne d�n",

                "name": "{way_name} �zerinde {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n"

            },

            "left": {

                "default": "Sola d�n",

                "name": "{way_name} �zerinde sola d�n",

                "destination": "{destination} istikametinde sola d�n"

            },

            "right": {

                "default": "Saga d�n",

                "name": "{way_name} �zerinde saga d�n",

                "destination": "{destination} istikametinde saga d�n"

            },

            "straight": {

                "default": "D�z devam et",

                "name": "{way_name} �zerinde d�z devam et",

                "destination": "{destination} istikametinde d�z devam et"

            }

        },

        "turn": {

            "default": {

                "default": "{modifier} y�ne d�n",

                "name": "{way_name} �zerinde {modifier} y�ne d�n",

                "destination": "{destination} istikametinde {modifier} y�ne d�n"

            },

            "left": {

                "default": "Sola d�n�n",

                "name": "{way_name} �zerinde sola d�n",

                "destination": "{destination} istikametinde sola d�n"

            },

            "right": {

                "default": "Saga d�n�n",

                "name": "{way_name} �zerinde saga d�n",

                "destination": "{destination} istikametinde saga d�n"

            },

            "straight": {

                "default": "D�z git",

                "name": "{way_name} �zerinde d�z git",

                "destination": "{destination} istikametinde d�z git"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "D�z devam edin"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],45:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "1?",

                "2": "2?",

                "3": "3?",

                "4": "4?",

                "5": "5?",

                "6": "6?",

                "7": "7?",

                "8": "8?",

                "9": "9?",

                "10": "10?"

            },

            "direction": {

                "north": "??????",

                "northeast": "????????? ????",

                "east": "????",

                "southeast": "????????? ????",

                "south": "???????",

                "southwest": "????????? ?????",

                "west": "?????",

                "northwest": "????????? ?????"

            },

            "modifier": {

                "left": "???????",

                "right": "????????",

                "sharp left": "????? ???????",

                "sharp right": "????? ????????",

                "slight left": "?????? ???????",

                "slight right": "?????? ????????",

                "straight": "?????",

                "uturn": "????????"

            },

            "lanes": {

                "xo": "?????????? ????????",

                "ox": "?????????? ???????",

                "xox": "?????????? ? ????????",

                "oxo": "?????????? ???????? ??? ???????"

            }

        },

        "modes": {

            "ferry": {

                "default": "????????????? ???????",

                "name": "????????????? ??????? {way_name}",

                "destination": "????????????? ??????? ? ???????? {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, ?????, ????? {distance}, {instruction_two}",

            "two linked": "{instruction_one}, ????? {instruction_two}",

            "one in distance": "????? {distance}, {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "?'??? {exit}"

        },

        "arrive": {

            "default": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name}"

            },

            "left": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ???�- ???????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ???????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ???????"

            },

            "right": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ????????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ????????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ????????"

            },

            "sharp left": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ???????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ???????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ???????"

            },

            "sharp right": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ????????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ????????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ????????"

            },

            "slight right": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ????????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ????????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ????????"

            },

            "slight left": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ???????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ???????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ???????"

            },

            "straight": {

                "default": "?? ??????? ? ??? {nth} ????? ???????????, ??? - ????? ????? ????",

                "upcoming": "?? ???????????? ?? ?????? {nth} ????? ???????????, ????? ????? ????",

                "short": "?? ???????",

                "short-upcoming": "?? ?????????",

                "named": "?? ??????? ? {waypoint_name} ????? ????? ????"

            }

        },

        "continue": {

            "default": {

                "default": "????????? {modifier}",

                "name": "?????????{modifier} ??????????? ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}",

                "exit": "????????? {modifier} ?? {way_name}"

            },

            "straight": {

                "default": "??????????? ??? ?????",

                "name": "??????????? ??? ????? ??????????? ?? {way_name}",

                "destination": "????????? ? ???????? {destination}",

                "distance": "??????????? ??? ????? {distance}",

                "namedistance": "??????????? ??? ?? {way_name} {distance}"

            },

            "sharp left": {

                "default": "????????? ????? ???????",

                "name": "????????? ????? ??????? ??? ?????????? ?? {way_name}",

                "destination": "????????? ????? ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "????????? ????? ????????",

                "name": "????????? ????? ???????? ??? ?????????? ?? {way_name}",

                "destination": "????????? ????? ???????? ? ???????? {destination}"

            },

            "slight left": {

                "default": "????????? ????? ???????",

                "name": "????????? ?????? ??????? ??? ?????????? ?? {way_name}",

                "destination": "????????? ?????? ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "????????? ?????? ????????",

                "name": "????????? ?????? ???????? ??? ?????????? ?? {way_name}",

                "destination": "????????? ?????? ???????? ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? ????????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "???????? ?? {direction}",

                "name": "???????? ?? {direction} ?? {way_name}",

                "namedistance": "???????? ?? {direction} ?? {way_name} {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}"

            },

            "straight": {

                "default": "??????????? ??? ?????",

                "name": "??????????? ??? ????? ?? {way_name}",

                "destination": "??????????? ??? ????? ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ???????? ? ????? ??????",

                "name": "????????? ???????? ?? {way_name} ? ????? ??????",

                "destination": "????????? ???????? ? ???????? {destination} ? ????? ??????"

            }

        },

        "fork": {

            "default": {

                "default": "?? ?????????? ?????????? {modifier}",

                "name": "?????????? {modifier} ? ????????? ?? {way_name}",

                "destination": "?????????? {modifier} ? ???????? {destination}"

            },

            "slight left": {

                "default": "?? ?????????? ?????????? ???????",

                "name": "?????????? ??????? ? ????????? ?? {way_name}",

                "destination": "?????????? ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "?? ?????????? ?????????? ????????",

                "name": "?????????? ???????? ? ????????? ?? {way_name}",

                "destination": "?????????? ???????? ? ???????? {destination}"

            },

            "sharp left": {

                "default": "?? ?????????? ????? ????????? ???????",

                "name": "???????? ????? ??????? ?? {way_name}",

                "destination": "???????? ????? ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "?? ?????????? ????? ????????? ????????",

                "name": "???????? ????? ???????? ?? {way_name}",

                "destination": "???????? ????? ???????? ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "???????????? ?? ?????? {modifier}",

                "name": "???????????? ?? ?????? {modifier} ?? {way_name}",

                "destination": "???????????? ?? ?????? {modifier} ? ???????? {destination}"

            },

            "straight": {

                "default": "???????????? ?? ??????",

                "name": "???????????? ?? ?????? ?? {way_name}",

                "destination": "???????????? ?? ?????? ? ???????? {destination}"

            },

            "slight left": {

                "default": "???????????? ?? ?????? ???????",

                "name": "???????????? ?? ?????? ??????? ?? {way_name}",

                "destination": "???????????? ?? ?????? ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "???????????? ?? ?????? ????????",

                "name": "???????????? ?? ?????? ???????? ?? {way_name}",

                "destination": "???????????? ?? ?????? ???????? ? ???????? {destination}"

            },

            "sharp left": {

                "default": "???????????? ?? ?????? ???????",

                "name": "???????????? ?? ?????? ??????? ?? {way_name}",

                "destination": "???????????? ?? ?????? ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "???????????? ?? ?????? ????????",

                "name": "???????????? ?? ?????? ???????? ?? {way_name}",

                "destination": "???????????? ?? ?????? ???????? ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}"

            },

            "straight": {

                "default": "????????? ?????",

                "name": "????????? ?? {way_name}",

                "destination": "????????? ? ???????? {destination}"

            },

            "sharp left": {

                "default": "???????? ????? ???????",

                "name": "???????? ????? ??????? ?? {way_name}",

                "destination": "???????? ????? ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "???????? ????? ????????",

                "name": "???????? ????? ???????? ?? {way_name}",

                "destination": "???????? ????? ???????? ? ???????? {destination}"

            },

            "slight left": {

                "default": "????????? ?????? ???????",

                "name": "????????? ?????? ??????? ?? {way_name}",

                "destination": "????????? ?????? ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "????????? ?????? ????????",

                "name": "????????? ?????? ???????? ?? {way_name}",

                "destination": "????????? ?????? ???????? ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}"

            },

            "uturn": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "????????? ?? ?'???",

                "name": "????????? ?? ?'??? ?? {way_name}",

                "destination": "????????? ?? ?'??? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit}",

                "exit_destination": "??????? ?'??? {exit} ? ???????? {destination}"

            },

            "left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ???????",

                "exit_destination": "??????? ?'??? {exit} ??????? ? ???????? {destination}"

            },

            "right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ????????",

                "exit_destination": "??????? ?'??? {exit} ???????? ? ???????? {destination}"

            },

            "sharp left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ???????",

                "exit_destination": "??????? ?'??? {exit} ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ????????",

                "exit_destination": "??????? ?'??? {exit} ???????? ? ???????? {destination}"

            },

            "slight left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ???????",

                "exit_destination": "??????? ?'??? {exit} ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}",

                "exit": "??????? ?'??? {exit} ????????",

                "exit_destination": "??????? ?'??? {exit} ???????? ? ???????? {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "????????? ?? ?'???",

                "name": "????????? ?? ?'??? ?? {way_name}",

                "destination": "????????? ?? ?'??? ? ???????? {destination}"

            },

            "left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}"

            },

            "right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}"

            },

            "sharp left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}"

            },

            "sharp right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}"

            },

            "slight left": {

                "default": "????????? ?? ?'??? ???????",

                "name": "????????? ?? ?'??? ??????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ??????? ? ???????? {destination}"

            },

            "slight right": {

                "default": "????????? ?? ?'??? ????????",

                "name": "????????? ?? ?'??? ???????? ?? {way_name}",

                "destination": "????????? ?? ?'??? ???????? ? ???????? {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "????????? ?? ????",

                    "name": "????????? ?? ???? ?? {way_name}",

                    "destination": "????????? ?? ???? ? ???????? {destination}"

                },

                "name": {

                    "default": "????????? ?? {rotary_name}",

                    "name": "????????? ?? {rotary_name} ?? ????????? ?? {way_name}",

                    "destination": "????????? ?? {rotary_name} ?? ????????? ? ???????? {destination}"

                },

                "exit": {

                    "default": "????????? ?? ???? ?? ?????????? ? {exit_number} ?'???",

                    "name": "????????? ?? ???? ?? ????????? ? {exit_number} ?'??? ?? {way_name}",

                    "destination": "????????? ?? ???? ?? ????????? ? {exit_number} ?'??? ? ???????? {destination}"

                },

                "name_exit": {

                    "default": "????????? ?? {rotary_name} ?? ????????? ? {exit_number} ?'???",

                    "name": "????????? ?? {rotary_name} ?? ????????? ? {exit_number} ?'??? ?? {way_name}",

                    "destination": "????????? ?? {rotary_name} ?? ????????? ? {exit_number} ?'??? ? ???????? {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "????????? ?? ???? ?? ?????????? ? {exit_number} ?'???",

                    "name": "????????? ?? ???? ?? ????????? ? {exit_number} ?'??? ?? {way_name}",

                    "destination": "????????? ?? ???? ?? ????????? ? {exit_number} ?'??? ? ???????? {destination}"

                },

                "default": {

                    "default": "????????? ?? ????",

                    "name": "????????? ?? ???? ?? {way_name}",

                    "destination": "????????? ?? ???? ? ???????? {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}"

            },

            "left": {

                "default": "????????? ???????",

                "name": "????????? ??????? ?? {way_name}",

                "destination": "????????? ??????? ? ???????? {destination}"

            },

            "right": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            },

            "straight": {

                "default": "????????? ?????",

                "name": "??????????? ??? ????? ?? {way_name}",

                "destination": "??????????? ??? ????? ? ???????? {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "???????? ????",

                "name": "???????? ???? ?? {way_name} ?'????",

                "destination": "???????? ???? ? ???????? {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "???????? ????",

                "name": "???????? ???? ?? {way_name} ?'????",

                "destination": "???????? ???? ? ???????? {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "????????? {modifier}",

                "name": "????????? {modifier} ?? {way_name}",

                "destination": "????????? {modifier} ? ???????? {destination}"

            },

            "left": {

                "default": "????????? ???????",

                "name": "????????? ??????? ?? {way_name}",

                "destination": "????????? ??????? ? ???????? {destination}"

            },

            "right": {

                "default": "????????? ????????",

                "name": "????????? ???????? ?? {way_name}",

                "destination": "????????? ???????? ? ???????? {destination}"

            },

            "straight": {

                "default": "????????? ?????",

                "name": "????????? ????? ?? {way_name}",

                "destination": "????????? ????? ? ???????? {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "??????????? ??? ?????"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],46:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": true

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "d?u ti�n",

                "2": "th? 2",

                "3": "th? 3",

                "4": "th? 4",

                "5": "th? 5",

                "6": "th� 6",

                "7": "th? 7",

                "8": "th? 8",

                "9": "th? 9",

                "10": "th? 10"

            },

            "direction": {

                "north": "b?c",

                "northeast": "d�ng b?c",

                "east": "d�ng",

                "southeast": "d�ng nam",

                "south": "nam",

                "southwest": "t�y nam",

                "west": "t�y",

                "northwest": "t�y b?c"

            },

            "modifier": {

                "left": "tr�i",

                "right": "ph?i",

                "sharp left": "tr�i g?t",

                "sharp right": "ph?i g?t",

                "slight left": "tr�i nghi�ng",

                "slight right": "ph?i nghi�ng",

                "straight": "th?ng",

                "uturn": "ngu?c"

            },

            "lanes": {

                "xo": "Di b�n ph?i",

                "ox": "Di b�n tr�i",

                "xox": "Di v�o gi?a",

                "oxo": "Di b�n tr�i hay b�n ph?i"

            }

        },

        "modes": {

            "ferry": {

                "default": "L�n ph�",

                "name": "L�n ph� {way_name}",

                "destination": "L�n ph� di {destination}"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one}, r?i {distance} n?a th� {instruction_two}",

            "two linked": "{instruction_one}, r?i {instruction_two}",

            "one in distance": "{distance} n?a th� {instruction_one}",

            "name and ref": "{name} ({ref})",

            "exit with number": "l?i ra {exit}"

        },

        "arrive": {

            "default": {

                "default": "D?n noi {nth}",

                "upcoming": "D?n noi {nth}",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name}"

            },

            "left": {

                "default": "D?n noi {nth} ? b�n tr�i",

                "upcoming": "D?n noi {nth} ? b�n tr�i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n tr�i"

            },

            "right": {

                "default": "D?n noi {nth} ? b�n ph?i",

                "upcoming": "D?n noi {nth} ? b�n ph?i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n ph?i"

            },

            "sharp left": {

                "default": "D?n noi {nth} ? b�n tr�i",

                "upcoming": "D?n noi {nth} ? b�n tr�i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n tr�i"

            },

            "sharp right": {

                "default": "D?n noi {nth} ? b�n ph?i",

                "upcoming": "D?n noi {nth} ? b�n ph?i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n ph?i"

            },

            "slight right": {

                "default": "D?n noi {nth} ? b�n ph?i",

                "upcoming": "D?n noi {nth} ? b�n ph?i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n ph?i"

            },

            "slight left": {

                "default": "D?n noi {nth} ? b�n tr�i",

                "upcoming": "D?n noi {nth} ? b�n tr�i",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? b�n tr�i"

            },

            "straight": {

                "default": "D?n noi {nth} ? tru?c m?t",

                "upcoming": "D?n noi {nth} ? tru?c m?t",

                "short": "D?n noi",

                "short-upcoming": "D?n noi",

                "named": "D?n {waypoint_name} ? tru?c m?t"

            }

        },

        "continue": {

            "default": {

                "default": "Qu?o {modifier}",

                "name": "Qu?o {modifier} d? ch?y ti?p tr�n {way_name}",

                "destination": "Qu?o {modifier} v? {destination}",

                "exit": "Qu?o {modifier} v�o {way_name}"

            },

            "straight": {

                "default": "Ch?y th?ng",

                "name": "Ch?y ti?p tr�n {way_name}",

                "destination": "Ch?y ti?p v? {destination}",

                "distance": "Ch?y th?ng cho {distance}",

                "namedistance": "Ch?y ti?p tr�n {way_name} cho {distance}"

            },

            "sharp left": {

                "default": "Qu?o g?t b�n tr�i",

                "name": "Qu?o g?t b�n tr�i d? ch?y ti?p tr�n {way_name}",

                "destination": "Qu?o g?t b�n tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Qu?o g?t b�n ph?i",

                "name": "Qu?o g?t b�n ph?i d? ch?y ti?p tr�n {way_name}",

                "destination": "Qu?o g?t b�n ph?i v? {destination}"

            },

            "slight left": {

                "default": "Nghi�ng v? b�n tr�i",

                "name": "Nghi�ng v? b�n tr�i d? ch?y ti?p tr�n {way_name}",

                "destination": "Nghi�ng v? b�n tr�i v? {destination}"

            },

            "slight right": {

                "default": "Nghi�ng v? b�n ph?i",

                "name": "Nghi�ng v? b�n ph?i d? ch?y ti?p tr�n {way_name}",

                "destination": "Nghi�ng v? b�n ph?i v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i",

                "name": "Qu?o ngu?c l?i tr�n {way_name}",

                "destination": "Qu?o ngu?c v? {destination}"

            }

        },

        "depart": {

            "default": {

                "default": "Di v? hu?ng {direction}",

                "name": "Di v? hu?ng {direction} tr�n {way_name}",

                "namedistance": "Di v? hu?ng {direction} tr�n {way_name} cho {distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "Qu?o {modifier}",

                "name": "Qu?o {modifier} v�o {way_name}",

                "destination": "Qu?o {modifier} v? {destination}"

            },

            "straight": {

                "default": "Ch?y th?ng",

                "name": "Ch?y ti?p tr�n {way_name}",

                "destination": "Ch?y ti?p v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i t?i cu?i du?ng",

                "name": "Qu?o ngu?c v�o {way_name} t?i cu?i du?ng",

                "destination": "Qu?o ngu?c v? {destination} t?i cu?i du?ng"

            }

        },

        "fork": {

            "default": {

                "default": "Di b�n {modifier} ? ng� ba",

                "name": "Gi? b�n {modifier} v�o {way_name}",

                "destination": "Gi? b�n {modifier} v? {destination}"

            },

            "slight left": {

                "default": "Nghi�ng v? b�n tr�i ? ng� ba",

                "name": "Gi? b�n tr�i v�o {way_name}",

                "destination": "Gi? b�n tr�i v? {destination}"

            },

            "slight right": {

                "default": "Nghi�ng v? b�n ph?i ? ng� ba",

                "name": "Gi? b�n ph?i v�o {way_name}",

                "destination": "Gi? b�n ph?i v? {destination}"

            },

            "sharp left": {

                "default": "Qu?o g?t b�n tr�i ? ng� ba",

                "name": "Qu?o g?t b�n tr�i v�o {way_name}",

                "destination": "Qu?o g?t b�n tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Qu?o g?t b�n ph?i ? ng� ba",

                "name": "Qu?o g?t b�n ph?i v�o {way_name}",

                "destination": "Qu?o g?t b�n ph?i v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i",

                "name": "Qu?o ngu?c l?i {way_name}",

                "destination": "Qu?o ngu?c l?i v? {destination}"

            }

        },

        "merge": {

            "default": {

                "default": "Nh?p sang {modifier}",

                "name": "Nh?p sang {modifier} v�o {way_name}",

                "destination": "Nh?p sang {modifier} v? {destination}"

            },

            "straight": {

                "default": "Nh?p du?ng",

                "name": "Nh?p v�o {way_name}",

                "destination": "Nh?p du?ng v? {destination}"

            },

            "slight left": {

                "default": "Nh?p sang tr�i",

                "name": "Nh?p sang tr�i v�o {way_name}",

                "destination": "Nh?p sang tr�i v? {destination}"

            },

            "slight right": {

                "default": "Nh?p sang ph?i",

                "name": "Nh?p sang ph?i v�o {way_name}",

                "destination": "Nh?p sang ph?i v? {destination}"

            },

            "sharp left": {

                "default": "Nh?p sang tr�i",

                "name": "Nh?p sang tr�i v�o {way_name}",

                "destination": "Nh?p sang tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Nh?p sang ph?i",

                "name": "Nh?p sang ph?i v�o {way_name}",

                "destination": "Nh?p sang ph?i v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i",

                "name": "Qu?o ngu?c l?i {way_name}",

                "destination": "Qu?o ngu?c l?i v? {destination}"

            }

        },

        "new name": {

            "default": {

                "default": "Ch?y ti?p b�n {modifier}",

                "name": "Ch?y ti?p b�n {modifier} tr�n {way_name}",

                "destination": "Ch?y ti?p b�n {modifier} v? {destination}"

            },

            "straight": {

                "default": "Ch?y th?ng",

                "name": "Ch?y ti?p tr�n {way_name}",

                "destination": "Ch?y ti?p v? {destination}"

            },

            "sharp left": {

                "default": "Qu?o g?t b�n tr�i",

                "name": "Qu?o g?t b�n tr�i v�o {way_name}",

                "destination": "Qu?o g?t b�n tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Qu?o g?t b�n ph?i",

                "name": "Qu?o g?t b�n ph?i v�o {way_name}",

                "destination": "Qu?o g?t b�n ph?i v? {destination}"

            },

            "slight left": {

                "default": "Nghi�ng v? b�n tr�i",

                "name": "Nghi�ng v? b�n tr�i v�o {way_name}",

                "destination": "Nghi�ng v? b�n tr�i v? {destination}"

            },

            "slight right": {

                "default": "Nghi�ng v? b�n ph?i",

                "name": "Nghi�ng v? b�n ph?i v�o {way_name}",

                "destination": "Nghi�ng v? b�n ph?i v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i",

                "name": "Qu?o ngu?c l?i {way_name}",

                "destination": "Qu?o ngu?c l?i v? {destination}"

            }

        },

        "notification": {

            "default": {

                "default": "Ch?y ti?p b�n {modifier}",

                "name": "Ch?y ti?p b�n {modifier} tr�n {way_name}",

                "destination": "Ch?y ti?p b�n {modifier} v? {destination}"

            },

            "uturn": {

                "default": "Qu?o ngu?c l?i",

                "name": "Qu?o ngu?c l?i {way_name}",

                "destination": "Qu?o ngu?c l?i v? {destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "Di du?ng nh�nh",

                "name": "Di du?ng nh�nh {way_name}",

                "destination": "Di du?ng nh�nh v? {destination}",

                "exit": "Di theo l?i ra {exit}",

                "exit_destination": "Di theo l?i ra {exit} v? {destination}"

            },

            "left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n tr�i",

                "exit_destination": "Di theo l?i ra {exit} b�n tr�i v? {destination}"

            },

            "right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n ph?i",

                "exit_destination": "Di theo l?i ra {exit} b�n ph?i v? {destination}"

            },

            "sharp left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n tr�i",

                "exit_destination": "Di theo l?i ra {exit} b�n tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n ph?i",

                "exit_destination": "Di theo l?i ra {exit} b�n ph?i v? {destination}"

            },

            "slight left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n tr�i",

                "exit_destination": "Di theo l?i ra {exit} b�n tr�i v? {destination}"

            },

            "slight right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}",

                "exit": "Di theo l?i ra {exit} b�n ph?i",

                "exit_destination": "Di theo l?i ra {exit} b�n ph?i v? {destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "Di du?ng nh�nh",

                "name": "Di du?ng nh�nh {way_name}",

                "destination": "Di du?ng nh�nh v? {destination}"

            },

            "left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}"

            },

            "right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}"

            },

            "sharp left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}"

            },

            "sharp right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}"

            },

            "slight left": {

                "default": "Di du?ng nh�nh b�n tr�i",

                "name": "Di du?ng nh�nh {way_name} b�n tr�i",

                "destination": "Di du?ng nh�nh b�n tr�i v? {destination}"

            },

            "slight right": {

                "default": "Di du?ng nh�nh b�n ph?i",

                "name": "Di du?ng nh�nh {way_name} b�n ph?i",

                "destination": "Di du?ng nh�nh b�n ph?i v? {destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "Di v�o b�ng binh",

                    "name": "Di v�o b�ng binh v� ra t?i {way_name}",

                    "destination": "Di v�o b�ng binh v� ra v? {destination}"

                },

                "name": {

                    "default": "Di v�o {rotary_name}",

                    "name": "Di v�o {rotary_name} v� ra t?i {way_name}",

                    "destination": "Di v� {rotary_name} v� ra v? {destination}"

                },

                "exit": {

                    "default": "Di v�o b�ng binh v� ra t?i du?ng {exit_number}",

                    "name": "Di v�o b�ng binh v� ra t?i du?ng {exit_number} t?c {way_name}",

                    "destination": "Di v�o b�ng binh v� ra t?i du?ng {exit_number} v? {destination}"

                },

                "name_exit": {

                    "default": "Di v�o {rotary_name} v� ra t?i du?ng {exit_number}",

                    "name": "Di v�o {rotary_name} v� ra t?i du?ng {exit_number} t?c {way_name}",

                    "destination": "Di v�o {rotary_name} v� ra t?i du?ng {exit_number} v? {destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "Di v�o b�ng binh v� ra t?i du?ng {exit_number}",

                    "name": "Di v�o b�ng binh v� ra t?i du?ng {exit_number} t?c {way_name}",

                    "destination": "Di v�o b�ng binh v� ra t?i du?ng {exit_number} v? {destination}"

                },

                "default": {

                    "default": "Di v�o b�ng binh",

                    "name": "Di v�o b�ng binh v� ra t?i {way_name}",

                    "destination": "Di v�o b�ng binh v� ra v? {destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "Qu?o {modifier}",

                "name": "Qu?o {modifier} v�o {way_name}",

                "destination": "Qu?o {modifier} v? {destination}"

            },

            "left": {

                "default": "Qu?o tr�i",

                "name": "Qu?o tr�i v�o {way_name}",

                "destination": "Qu?o tr�i v? {destination}"

            },

            "right": {

                "default": "Qu?o ph?i",

                "name": "Qu?o ph?i v�o {way_name}",

                "destination": "Qu?o ph?i v? {destination}"

            },

            "straight": {

                "default": "Ch?y th?ng",

                "name": "Ch?y ti?p tr�n {way_name}",

                "destination": "Ch?y ti?p v? {destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "Ra b�ng binh",

                "name": "Ra b�ng binh v�o {way_name}",

                "destination": "Ra b�ng binh v? {destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "Ra b�ng binh",

                "name": "Ra b�ng binh v�o {way_name}",

                "destination": "Ra b�ng binh v? {destination}"

            }

        },

        "turn": {

            "default": {

                "default": "Qu?o {modifier}",

                "name": "Qu?o {modifier} v�o {way_name}",

                "destination": "Qu?o {modifier} v? {destination}"

            },

            "left": {

                "default": "Qu?o tr�i",

                "name": "Qu?o tr�i v�o {way_name}",

                "destination": "Qu?o tr�i v? {destination}"

            },

            "right": {

                "default": "Qu?o ph?i",

                "name": "Qu?o ph?i v�o {way_name}",

                "destination": "Qu?o ph?i v? {destination}"

            },

            "straight": {

                "default": "Ch?y th?ng",

                "name": "Ch?y th?ng v�o {way_name}",

                "destination": "Ch?y th?ng v? {destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "Ch?y th?ng"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],47:[function(_dereq_,module,exports){

module.exports={

    "meta": {

        "capitalizeFirstLetter": false

    },

    "v5": {

        "constants": {

            "ordinalize": {

                "1": "??",

                "2": "??",

                "3": "??",

                "4": "??",

                "5": "??",

                "6": "??",

                "7": "??",

                "8": "??",

                "9": "??",

                "10": "??"

            },

            "direction": {

                "north": "?",

                "northeast": "??",

                "east": "?",

                "southeast": "??",

                "south": "?",

                "southwest": "??",

                "west": "?",

                "northwest": "??"

            },

            "modifier": {

                "left": "??",

                "right": "??",

                "sharp left": "???",

                "sharp right": "???",

                "slight left": "???",

                "slight right": "???",

                "straight": "??",

                "uturn": "??"

            },

            "lanes": {

                "xo": "????",

                "ox": "????",

                "xox": "?????????",

                "oxo": "????????????"

            }

        },

        "modes": {

            "ferry": {

                "default": "????",

                "name": "??{way_name}??",

                "destination": "????{destination}???"

            }

        },

        "phrase": {

            "two linked by distance": "{instruction_one},{distance}?{instruction_two}",

            "two linked": "{instruction_one},??{instruction_two}",

            "one in distance": "{distance}?{instruction_one}",

            "name and ref": "{name}({ref})",

            "exit with number": "??{exit}"

        },

        "arrive": {

            "default": {

                "default": "???????{nth}????",

                "upcoming": "???????{nth}????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name}"

            },

            "left": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "right": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "sharp left": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "sharp right": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "slight right": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "slight left": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            },

            "straight": {

                "default": "???????{nth}????,????????",

                "upcoming": "???????{nth}????,????????",

                "short": "??????",

                "short-upcoming": "???????",

                "named": "????{waypoint_name},????????"

            }

        },

        "continue": {

            "default": {

                "default": "{modifier}??",

                "name": "?{way_name}???{modifier}??",

                "destination": "{modifier}??,{destination}??",

                "exit": "{modifier}??,??{way_name}"

            },

            "straight": {

                "default": "????",

                "name": "?{way_name}?????",

                "destination": "????,??{destination}",

                "distance": "????{distance}",

                "namedistance": "???{way_name}???{distance}"

            },

            "sharp left": {

                "default": "??????",

                "name": "??????,???{way_name}???",

                "destination": "????,??{destination}"

            },

            "sharp right": {

                "default": "??????",

                "name": "??????,???{way_name}???",

                "destination": "????,??{destination}"

            },

            "slight left": {

                "default": "??????",

                "name": "??????,???{way_name}???",

                "destination": "????,??{destination}"

            },

            "slight right": {

                "default": "??????",

                "name": "??????,???{way_name}???",

                "destination": "??????,??{destination}"

            },

            "uturn": {

                "default": "????",

                "name": "????,???{way_name}???",

                "destination": "????,??{destination}"

            }

        },

        "depart": {

            "default": {

                "default": "???{direction}",

                "name": "???{direction},??{way_name}",

                "namedistance": "???{direction},?{way_name}?????{distance}"

            }

        },

        "end of road": {

            "default": {

                "default": "{modifier}??",

                "name": "{modifier}??,??{way_name}",

                "destination": "{modifier}??,??{destination}"

            },

            "straight": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            },

            "uturn": {

                "default": "???????",

                "name": "?????????{way_name}",

                "destination": "???????,??{destination}"

            }

        },

        "fork": {

            "default": {

                "default": "?????{modifier}",

                "name": "??????{modifier},??{way_name}",

                "destination": "??????{modifier},??{destination}"

            },

            "slight left": {

                "default": "??????????",

                "name": "??????????,??{way_name}",

                "destination": "??????????,??{destination}"

            },

            "slight right": {

                "default": "??????????",

                "name": "??????????,??{way_name}",

                "destination": "??????????,??{destination}"

            },

            "sharp left": {

                "default": "????????",

                "name": "????????,??{way_name}",

                "destination": "????????,??{destination}"

            },

            "sharp right": {

                "default": "????????",

                "name": "????????,??{way_name}",

                "destination": "????????,??{destination}"

            },

            "uturn": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "merge": {

            "default": {

                "default": "{modifier}??",

                "name": "{modifier}??,??{way_name}",

                "destination": "{modifier}??,??{destination}"

            },

            "straight": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            },

            "slight left": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "slight right": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "sharp left": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "sharp right": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "uturn": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "new name": {

            "default": {

                "default": "??{modifier}",

                "name": "??{modifier},??{way_name}",

                "destination": "??{modifier},??{destination}"

            },

            "straight": {

                "default": "????",

                "name": "???{way_name}???",

                "destination": "????,??{destination}"

            },

            "sharp left": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "????,??{destination}"

            },

            "sharp right": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "????,??{destination}"

            },

            "slight left": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "slight right": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "uturn": {

                "default": "????",

                "name": "????,?{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "notification": {

            "default": {

                "default": "??{modifier}",

                "name": "??{modifier},??{way_name}",

                "destination": "??{modifier},??{destination}"

            },

            "uturn": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "off ramp": {

            "default": {

                "default": "???",

                "name": "???,??{way_name}",

                "destination": "???,??{destination}",

                "exit": "?{exit}????",

                "exit_destination": "?{exit}????,??{destination}"

            },

            "left": {

                "default": "?????",

                "name": "?????,?{way_name}",

                "destination": "?????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            },

            "right": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            },

            "sharp left": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            },

            "sharp right": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            },

            "slight left": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            },

            "slight right": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}",

                "exit": "???{exit}????",

                "exit_destination": "???{exit}????,??{destination}"

            }

        },

        "on ramp": {

            "default": {

                "default": "???",

                "name": "???,??{way_name}",

                "destination": "???,??{destination}"

            },

            "left": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "right": {

                "default": "?????",

                "name": "?????,??{way_name}",

                "destination": "?????,??{destination}"

            },

            "sharp left": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}"

            },

            "sharp right": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}"

            },

            "slight left": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}"

            },

            "slight right": {

                "default": "??????",

                "name": "??????,??{way_name}",

                "destination": "??????,??{destination}"

            }

        },

        "rotary": {

            "default": {

                "default": {

                    "default": "????",

                    "name": "???????{way_name}",

                    "destination": "???????{destination}"

                },

                "name": {

                    "default": "??{rotary_name}??",

                    "name": "??{rotary_name}?????{way_name}",

                    "destination": "??{rotary_name}?????{destination}"

                },

                "exit": {

                    "default": "??????{exit_number}????",

                    "name": "??????{exit_number}????,?{way_name}",

                    "destination": "??????{exit_number}????,??{destination}"

                },

                "name_exit": {

                    "default": "??{rotary_name}????{exit_number}????",

                    "name": "??{rotary_name}????{exit_number}????,?{way_name}",

                    "destination": "??{rotary_name}????{exit_number}????,??{destination}"

                }

            }

        },

        "roundabout": {

            "default": {

                "exit": {

                    "default": "??????{exit_number}????",

                    "name": "??????{exit_number}????,?{way_name}",

                    "destination": "??????{exit_number}????,??{destination}"

                },

                "default": {

                    "default": "????",

                    "name": "???????{way_name}",

                    "destination": "???????{destination}"

                }

            }

        },

        "roundabout turn": {

            "default": {

                "default": "{modifier}??",

                "name": "{modifier}??,??{way_name}",

                "destination": "{modifier}??,??{destination}"

            },

            "left": {

                "default": "??",

                "name": "??,??{way_name}",

                "destination": "??,??{destination}"

            },

            "right": {

                "default": "??",

                "name": "??,??{way_name}",

                "destination": "??,??{destination}"

            },

            "straight": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "exit roundabout": {

            "default": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "exit rotary": {

            "default": {

                "default": "????",

                "name": "????,??{way_name}",

                "destination": "????,??{destination}"

            }

        },

        "turn": {

            "default": {

                "default": "{modifier}??",

                "name": "{modifier}??,??{way_name}",

                "destination": "{modifier}??,??{destination}"

            },

            "left": {

                "default": "??",

                "name": "??,??{way_name}",

                "destination": "??,??{destination}"

            },

            "right": {

                "default": "??",

                "name": "??,??{way_name}",

                "destination": "??,??{destination}"

            },

            "straight": {

                "default": "??",

                "name": "??,??{way_name}",

                "destination": "??,??{destination}"

            }

        },

        "use lane": {

            "no_lanes": {

                "default": "????"

            },

            "default": {

                "default": "{lane_instruction}"

            }

        }

    }

}



},{}],48:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	module.exports = L.Class.extend({

		options: {

			timeout: 500,

			blurTimeout: 100,

			noResultsMessage: 'No results found.'

		},



		initialize: function(elem, callback, context, options) {

			L.setOptions(this, options);



			this._elem = elem;

			this._resultFn = options.resultFn ? L.Util.bind(options.resultFn, options.resultContext) : null;

			this._autocomplete = options.autocompleteFn ? L.Util.bind(options.autocompleteFn, options.autocompleteContext) : null;

			this._selectFn = L.Util.bind(callback, context);

			this._container = L.DomUtil.create('div', 'leaflet-routing-geocoder-result');

			this._resultTable = L.DomUtil.create('table', '', this._container);



			// TODO: looks a bit like a kludge to register same for input and keypress -

			// browsers supporting both will get duplicate events; just registering

			// input will not catch enter, though.

			L.DomEvent.addListener(this._elem, 'input', this._keyPressed, this);

			L.DomEvent.addListener(this._elem, 'keypress', this._keyPressed, this);

			L.DomEvent.addListener(this._elem, 'keydown', this._keyDown, this);

			L.DomEvent.addListener(this._elem, 'blur', function() {

				if (this._isOpen) {

					this.close();

				}

			}, this);

		},



		close: function() {

			L.DomUtil.removeClass(this._container, 'leaflet-routing-geocoder-result-open');

			this._isOpen = false;

		},



		_open: function() {

			var rect = this._elem.getBoundingClientRect();

			if (!this._container.parentElement) {

				// See notes section under https://developer.mozilla.org/en-US/docs/Web/API/Window/scrollX

				// This abomination is required to support all flavors of IE

				var scrollX = (window.pageXOffset !== undefined) ? window.pageXOffset

					: (document.documentElement || document.body.parentNode || document.body).scrollLeft;

				var scrollY = (window.pageYOffset !== undefined) ? window.pageYOffset

					: (document.documentElement || document.body.parentNode || document.body).scrollTop;

				this._container.style.left = (rect.left + scrollX) + 'px';

				this._container.style.top = (rect.bottom + scrollY) + 'px';

				this._container.style.width = (rect.right - rect.left) + 'px';

				document.body.appendChild(this._container);

			}



			L.DomUtil.addClass(this._container, 'leaflet-routing-geocoder-result-open');

			this._isOpen = true;

		},



		_setResults: function(results) {

			var i,

			    tr,

			    td,

			    text;



			delete this._selection;

			this._results = results;



			while (this._resultTable.firstChild) {

				this._resultTable.removeChild(this._resultTable.firstChild);

			}



			for (i = 0; i < results.length; i++) {

				tr = L.DomUtil.create('tr', '', this._resultTable);

				tr.setAttribute('data-result-index', i);

				td = L.DomUtil.create('td', '', tr);

				text = document.createTextNode(results[i].name);

				td.appendChild(text);

				// mousedown + click because:

				// http://stackoverflow.com/questions/10652852/jquery-fire-click-before-blur-event

				L.DomEvent.addListener(td, 'mousedown', L.DomEvent.preventDefault);

				L.DomEvent.addListener(td, 'click', this._createClickListener(results[i]));

			}



			if (!i) {

				tr = L.DomUtil.create('tr', '', this._resultTable);

				td = L.DomUtil.create('td', 'leaflet-routing-geocoder-no-results', tr);

				td.innerHTML = this.options.noResultsMessage;

			}



			this._open();



			if (results.length > 0) {

				// Select the first entry

				this._select(1);

			}

		},



		_createClickListener: function(r) {

			var resultSelected = this._resultSelected(r);

			return L.bind(function() {

				this._elem.blur();

				resultSelected();

			}, this);

		},



		_resultSelected: function(r) {

			return L.bind(function() {

				this.close();

				this._elem.value = r.name;

				this._lastCompletedText = r.name;

				this._selectFn(r);

			}, this);

		},



		_keyPressed: function(e) {

			var index;



			if (this._isOpen && e.keyCode === 13 && this._selection) {

				index = parseInt(this._selection.getAttribute('data-result-index'), 10);

				this._resultSelected(this._results[index])();

				L.DomEvent.preventDefault(e);

				return;

			}



			if (e.keyCode === 13) {

				L.DomEvent.preventDefault(e);

				this._complete(this._resultFn, true);

				return;

			}



			if (this._autocomplete && document.activeElement === this._elem) {

				if (this._timer) {

					clearTimeout(this._timer);

				}

				this._timer = setTimeout(L.Util.bind(function() { this._complete(this._autocomplete); }, this),

					this.options.timeout);

				return;

			}



			this._unselect();

		},



		_select: function(dir) {

			var sel = this._selection;

			if (sel) {

				L.DomUtil.removeClass(sel.firstChild, 'leaflet-routing-geocoder-selected');

				sel = sel[dir > 0 ? 'nextSibling' : 'previousSibling'];

			}

			if (!sel) {

				sel = this._resultTable[dir > 0 ? 'firstChild' : 'lastChild'];

			}



			if (sel) {

				L.DomUtil.addClass(sel.firstChild, 'leaflet-routing-geocoder-selected');

				this._selection = sel;

			}

		},



		_unselect: function() {

			if (this._selection) {

				L.DomUtil.removeClass(this._selection.firstChild, 'leaflet-routing-geocoder-selected');

			}

			delete this._selection;

		},



		_keyDown: function(e) {

			if (this._isOpen) {

				switch (e.keyCode) {

				// Escape

				case 27:

					this.close();

					L.DomEvent.preventDefault(e);

					return;

				// Up

				case 38:

					this._select(-1);

					L.DomEvent.preventDefault(e);

					return;

				// Down

				case 40:

					this._select(1);

					L.DomEvent.preventDefault(e);

					return;

				}

			}

		},



		_complete: function(completeFn, trySelect) {

			var v = this._elem.value;

			function completeResults(results) {

				this._lastCompletedText = v;

				if (trySelect && results.length === 1) {

					this._resultSelected(results[0])();

				} else {

					this._setResults(results);

				}

			}



			if (!v) {

				return;

			}



			if (v !== this._lastCompletedText) {

				completeFn(v, completeResults, this);

			} else if (trySelect) {

				completeResults.call(this, this._results);

			}

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{}],49:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	var Itinerary = _dereq_('./itinerary');

	var Line = _dereq_('./line');

	var Plan = _dereq_('./plan');

	var OSRMv1 = _dereq_('./osrm-v1');



	module.exports = Itinerary.extend({

		options: {

			fitSelectedRoutes: 'smart',

			routeLine: function(route, options) { return new Line(route, options); },

			autoRoute: true,

			routeWhileDragging: false,

			routeDragInterval: 500,

			waypointMode: 'connect',

			showAlternatives: false,

			defaultErrorHandler: function(e) {

				console.error('Routing error:', e.error);

			}

		},



		initialize: function(options) {

			L.Util.setOptions(this, options);



			this._router = this.options.router || new OSRMv1(options);

			this._plan = this.options.plan || new Plan(this.options.waypoints, options);

			this._requestCount = 0;



			Itinerary.prototype.initialize.call(this, options);



			this.on('routeselected', this._routeSelected, this);

			if (this.options.defaultErrorHandler) {

				this.on('routingerror', this.options.defaultErrorHandler);

			}

			this._plan.on('waypointschanged', this._onWaypointsChanged, this);

			if (options.routeWhileDragging) {

				this._setupRouteDragging();

			}

		},



		_onZoomEnd: function() {

			if (!this._selectedRoute ||

				!this._router.requiresMoreDetail) {

				return;

			}



			var map = this._map;

			if (this._router.requiresMoreDetail(this._selectedRoute,

					map.getZoom(), map.getBounds())) {

				this.route({

					callback: L.bind(function(err, routes) {

						var i;

						if (!err) {

							for (i = 0; i < routes.length; i++) {

								this._routes[i].properties = routes[i].properties;

							}

							this._updateLineCallback(err, routes);

						}



					}, this),

					simplifyGeometry: false,

					geometryOnly: true

				});

			}

		},



		onAdd: function(map) {

			if (this.options.autoRoute) {

				this.route();

			}



			var container = Itinerary.prototype.onAdd.call(this, map);



			this._map = map;

			this._map.addLayer(this._plan);



			this._map.on('zoomend', this._onZoomEnd, this);



			if (this._plan.options.geocoder) {

				container.insertBefore(this._plan.createGeocoders(), container.firstChild);

			}



			return container;

		},



		onRemove: function(map) {

			map.off('zoomend', this._onZoomEnd, this);

			if (this._line) {

				map.removeLayer(this._line);

			}

			map.removeLayer(this._plan);

			if (this._alternatives && this._alternatives.length > 0) {

				for (var i = 0, len = this._alternatives.length; i < len; i++) {

					map.removeLayer(this._alternatives[i]);

				}

			}

			return Itinerary.prototype.onRemove.call(this, map);

		},



		getWaypoints: function() {

			return this._plan.getWaypoints();

		},



		setWaypoints: function(waypoints) {

			this._plan.setWaypoints(waypoints);

			return this;

		},



		spliceWaypoints: function() {

			var removed = this._plan.spliceWaypoints.apply(this._plan, arguments);

			return removed;

		},



		getPlan: function() {

			return this._plan;

		},



		getRouter: function() {

			return this._router;

		},



		_routeSelected: function(e) {

			var route = this._selectedRoute = e.route,

				alternatives = this.options.showAlternatives && e.alternatives,

				fitMode = this.options.fitSelectedRoutes,

				fitBounds =

					(fitMode === 'smart' && !this._waypointsVisible()) ||

					(fitMode !== 'smart' && fitMode);



			this._updateLines({route: route, alternatives: alternatives});



			if (fitBounds) {

				this._map.fitBounds(this._line.getBounds());

			}



			if (this.options.waypointMode === 'snap') {

				this._plan.off('waypointschanged', this._onWaypointsChanged, this);

				this.setWaypoints(route.waypoints);

				this._plan.on('waypointschanged', this._onWaypointsChanged, this);

			}

		},



		_waypointsVisible: function() {

			var wps = this.getWaypoints(),

				mapSize,

				bounds,

				boundsSize,

				i,

				p;



			try {

				mapSize = this._map.getSize();



				for (i = 0; i < wps.length; i++) {

					p = this._map.latLngToLayerPoint(wps[i].latLng);



					if (bounds) {

						bounds.extend(p);

					} else {

						bounds = L.bounds([p]);

					}

				}



				boundsSize = bounds.getSize();

				return (boundsSize.x > mapSize.x / 5 ||

					boundsSize.y > mapSize.y / 5) && this._waypointsInViewport();



			} catch (e) {

				return false;

			}

		},



		_waypointsInViewport: function() {

			var wps = this.getWaypoints(),

				mapBounds,

				i;



			try {

				mapBounds = this._map.getBounds();

			} catch (e) {

				return false;

			}



			for (i = 0; i < wps.length; i++) {

				if (mapBounds.contains(wps[i].latLng)) {

					return true;

				}

			}



			return false;

		},



		_updateLines: function(routes) {

			var addWaypoints = this.options.addWaypoints !== undefined ?

				this.options.addWaypoints : true;

			this._clearLines();



			// add alternatives first so they lie below the main route

			this._alternatives = [];

			if (routes.alternatives) routes.alternatives.forEach(function(alt, i) {

				this._alternatives[i] = this.options.routeLine(alt,

					L.extend({

						isAlternative: true

					}, this.options.altLineOptions || this.options.lineOptions));

				this._alternatives[i].addTo(this._map);

				this._hookAltEvents(this._alternatives[i]);

			}, this);



			this._line = this.options.routeLine(routes.route,

				L.extend({

					addWaypoints: addWaypoints,

					extendToWaypoints: this.options.waypointMode === 'connect'

				}, this.options.lineOptions));

			this._line.addTo(this._map);

			this._hookEvents(this._line);

		},



		_hookEvents: function(l) {

			l.on('linetouched', function(e) {

				this._plan.dragNewWaypoint(e);

			}, this);

		},



		_hookAltEvents: function(l) {

			l.on('linetouched', function(e) {

				var alts = this._routes.slice();

				var selected = alts.splice(e.target._route.routesIndex, 1)[0];

				this.fire('routeselected', {route: selected, alternatives: alts});

			}, this);

		},



		_onWaypointsChanged: function(e) {

			if (this.options.autoRoute) {

				this.route({});

			}

			if (!this._plan.isReady()) {

				this._clearLines();

				this._clearAlts();

			}

			this.fire('waypointschanged', {waypoints: e.waypoints});

		},



		_setupRouteDragging: function() {

			var timer = 0,

				waypoints;



			this._plan.on('waypointdrag', L.bind(function(e) {

				waypoints = e.waypoints;



				if (!timer) {

					timer = setTimeout(L.bind(function() {

						this.route({

							waypoints: waypoints,

							geometryOnly: true,

							callback: L.bind(this._updateLineCallback, this)

						});

						timer = undefined;

					}, this), this.options.routeDragInterval);

				}

			}, this));

			this._plan.on('waypointdragend', function() {

				if (timer) {

					clearTimeout(timer);

					timer = undefined;

				}

				this.route();

			}, this);

		},



		_updateLineCallback: function(err, routes) {

			if (!err) {

				routes = routes.slice();

				var selected = routes.splice(this._selectedRoute.routesIndex, 1)[0];

				this._updateLines({

					route: selected,

					alternatives: this.options.showAlternatives ? routes : []

				});

			} else if (err.type !== 'abort') {

				this._clearLines();

			}

		},



		route: function(options) {

			var ts = ++this._requestCount,

				wps;



			if (this._pendingRequest && this._pendingRequest.abort) {

				this._pendingRequest.abort();

				this._pendingRequest = null;

			}



			options = options || {};



			if (this._plan.isReady()) {

				if (this.options.useZoomParameter) {

					options.z = this._map && this._map.getZoom();

				}



				wps = options && options.waypoints || this._plan.getWaypoints();

				this.fire('routingstart', {waypoints: wps});

				this._pendingRequest = this._router.route(wps, function(err, routes) {

					this._pendingRequest = null;



					if (options.callback) {

						return options.callback.call(this, err, routes);

					}



					// Prevent race among multiple requests,

					// by checking the current request's count

					// against the last request's; ignore result if

					// this isn't the last request.

					if (ts === this._requestCount) {

						this._clearLines();

						this._clearAlts();

						if (err && err.type !== 'abort') {

							this.fire('routingerror', {error: err});

							return;

						}



						routes.forEach(function(route, i) { route.routesIndex = i; });



						if (!options.geometryOnly) {

							this.fire('routesfound', {waypoints: wps, routes: routes});

							this.setAlternatives(routes);

						} else {

							var selectedRoute = routes.splice(0,1)[0];

							this._routeSelected({route: selectedRoute, alternatives: routes});

						}

					}

				}, this, options);

			}

		},



		_clearLines: function() {

			if (this._line) {

				this._map.removeLayer(this._line);

				delete this._line;

			}

			if (this._alternatives && this._alternatives.length) {

				for (var i in this._alternatives) {

					this._map.removeLayer(this._alternatives[i]);

				}

				this._alternatives = [];

			}

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./itinerary":55,"./line":56,"./osrm-v1":59,"./plan":60}],50:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	module.exports = L.Control.extend({

		options: {

			header: 'Routing error',

			formatMessage: function(error) {

				if (error.status < 0) {

					return 'Calculating the route caused an error. Technical description follows: <code><pre>' +

						error.message + '</pre></code';

				} else {

					return 'The route could not be calculated. ' +

						error.message;

				}

			}

		},



		initialize: function(routingControl, options) {

			L.Control.prototype.initialize.call(this, options);

			routingControl

				.on('routingerror', L.bind(function(e) {

					if (this._element) {

						this._element.children[1].innerHTML = this.options.formatMessage(e.error);

						this._element.style.visibility = 'visible';

					}

				}, this))

				.on('routingstart', L.bind(function() {

					if (this._element) {

						this._element.style.visibility = 'hidden';

					}

				}, this));

		},



		onAdd: function() {

			var header,

				message;



			this._element = L.DomUtil.create('div', 'leaflet-bar leaflet-routing-error');

			this._element.style.visibility = 'hidden';



			header = L.DomUtil.create('h3', null, this._element);

			message = L.DomUtil.create('span', null, this._element);



			header.innerHTML = this.options.header;



			return this._element;

		},



		onRemove: function() {

			delete this._element;

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{}],51:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	var Localization = _dereq_('./localization');



	module.exports = L.Class.extend({

		options: {

			units: 'metric',

			unitNames: null,

			language: 'en',

			roundingSensitivity: 1,

			distanceTemplate: '{value} {unit}'

		},



		initialize: function(options) {

			L.setOptions(this, options);



			var langs = L.Util.isArray(this.options.language) ?

				this.options.language :

				[this.options.language, 'en'];

			this._localization = new Localization(langs);

		},



		formatDistance: function(d /* Number (meters) */, sensitivity) {

			var un = this.options.unitNames || this._localization.localize('units'),

				simpleRounding = sensitivity <= 0,

				round = simpleRounding ? function(v) { return v; } : L.bind(this._round, this),

			    v,

			    yards,

				data,

				pow10;



			if (this.options.units === 'imperial') {

				yards = d / 0.9144;

				if (yards >= 1000) {

					data = {

						value: round(d / 1609.344, sensitivity),

						unit: un.miles

					};

				} else {

					data = {

						value: round(yards, sensitivity),

						unit: un.yards

					};

				}

			} else {

				v = round(d, sensitivity);

				data = {

					value: v >= 1000 ? (v / 1000) : v,

					unit: v >= 1000 ? un.kilometers : un.meters

				};

			}



			if (simpleRounding) {

				data.value = data.value.toFixed(-sensitivity);

			}



			return L.Util.template(this.options.distanceTemplate, data);

		},



		_round: function(d, sensitivity) {

			var s = sensitivity || this.options.roundingSensitivity,

				pow10 = Math.pow(10, (Math.floor(d / s) + '').length - 1),

				r = Math.floor(d / pow10),

				p = (r > 5) ? pow10 : pow10 / 2;



			return Math.round(d / p) * p;

		},



		formatTime: function(t /* Number (seconds) */) {

			var un = this.options.unitNames || this._localization.localize('units');

			// More than 30 seconds precision looks ridiculous

			t = Math.round(t / 30) * 30;



			if (t > 86400) {

				return Math.round(t / 3600) + ' ' + un.hours;

			} else if (t > 3600) {

				return Math.floor(t / 3600) + ' ' + un.hours + ' ' +

					Math.round((t % 3600) / 60) + ' ' + un.minutes;

			} else if (t > 300) {

				return Math.round(t / 60) + ' ' + un.minutes;

			} else if (t > 60) {

				return Math.floor(t / 60) + ' ' + un.minutes +

					(t % 60 !== 0 ? ' ' + (t % 60) + ' ' + un.seconds : '');

			} else {

				return t + ' ' + un.seconds;

			}

		},



		formatInstruction: function(instr, i) {

			if (instr.text === undefined) {

				return this.capitalize(L.Util.template(this._getInstructionTemplate(instr, i),

					L.extend({}, instr, {

						exitStr: instr.exit ? this._localization.localize('formatOrder')(instr.exit) : '',

						dir: this._localization.localize(['directions', instr.direction]),

						modifier: this._localization.localize(['directions', instr.modifier])

					})));

			} else {

				return instr.text;

			}

		},



		getIconName: function(instr, i) {

			switch (instr.type) {

			case 'Head':

				if (i === 0) {

					return 'depart';

				}

				break;

			case 'WaypointReached':

				return 'via';

			case 'Roundabout':

				return 'enter-roundabout';

			case 'DestinationReached':

				return 'arrive';

			}



			switch (instr.modifier) {

			case 'Straight':

				return 'continue';

			case 'SlightRight':

				return 'bear-right';

			case 'Right':

				return 'turn-right';

			case 'SharpRight':

				return 'sharp-right';

			case 'TurnAround':

			case 'Uturn':

				return 'u-turn';

			case 'SharpLeft':

				return 'sharp-left';

			case 'Left':

				return 'turn-left';

			case 'SlightLeft':

				return 'bear-left';

			}

		},



		capitalize: function(s) {

			return s.charAt(0).toUpperCase() + s.substring(1);

		},



		_getInstructionTemplate: function(instr, i) {

			var type = instr.type === 'Straight' ? (i === 0 ? 'Head' : 'Continue') : instr.type,

				strings = this._localization.localize(['instructions', type]);



			if (!strings) {

				strings = [

					this._localization.localize(['directions', type]),

					' ' + this._localization.localize(['instructions', 'Onto'])

				];

			}



			return strings[0] + (strings.length > 1 && instr.road ? strings[1] : '');

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./localization":57}],52:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);

	var Autocomplete = _dereq_('./autocomplete');

	var Localization = _dereq_('./localization');



	function selectInputText(input) {

		if (input.setSelectionRange) {

			// On iOS, select() doesn't work

			input.setSelectionRange(0, 9999);

		} else {

			// On at least IE8, setSeleectionRange doesn't exist

			input.select();

		}

	}



	module.exports = L.Class.extend({

		includes: ((typeof L.Evented !== 'undefined' && L.Evented.prototype) || L.Mixin.Events),



		options: {

			createGeocoder: function(i, nWps, options) {

				var container = L.DomUtil.create('div', 'leaflet-routing-geocoder'),

					input = L.DomUtil.create('input', '', container),

					remove = options.addWaypoints ? L.DomUtil.create('span', 'leaflet-routing-remove-waypoint', container) : undefined;



				input.disabled = !options.addWaypoints;



				return {

					container: container,

					input: input,

					closeButton: remove

				};

			},

			geocoderPlaceholder: function(i, numberWaypoints, geocoderElement) {

				var l = new Localization(geocoderElement.options.language).localize('ui');

				return i === 0 ?

					l.startPlaceholder :

					(i < numberWaypoints - 1 ?

						L.Util.template(l.viaPlaceholder, {viaNumber: i}) :

						l.endPlaceholder);

			},



			geocoderClass: function() {

				return '';

			},



			waypointNameFallback: function(latLng) {

				var ns = latLng.lat < 0 ? 'S' : 'N',

					ew = latLng.lng < 0 ? 'W' : 'E',

					lat = (Math.round(Math.abs(latLng.lat) * 10000) / 10000).toString(),

					lng = (Math.round(Math.abs(latLng.lng) * 10000) / 10000).toString();

				return ns + lat + ', ' + ew + lng;

			},

			maxGeocoderTolerance: 200,

			autocompleteOptions: {},

			language: 'en',

		},



		initialize: function(wp, i, nWps, options) {

			L.setOptions(this, options);



			var g = this.options.createGeocoder(i, nWps, this.options),

				closeButton = g.closeButton,

				geocoderInput = g.input;

			geocoderInput.setAttribute('placeholder', this.options.geocoderPlaceholder(i, nWps, this));

			geocoderInput.className = this.options.geocoderClass(i, nWps);



			this._element = g;

			this._waypoint = wp;



			this.update();

			// This has to be here, or geocoder's value will not be properly

			// initialized.

			// TODO: look into why and make _updateWaypointName fix this.

			geocoderInput.value = wp.name;



			L.DomEvent.addListener(geocoderInput, 'click', function() {

				selectInputText(this);

			}, geocoderInput);



			if (closeButton) {

				L.DomEvent.addListener(closeButton, 'click', function() {

					this.fire('delete', { waypoint: this._waypoint });

				}, this);

			}



			new Autocomplete(geocoderInput, function(r) {

					geocoderInput.value = r.name;

					wp.name = r.name;

					wp.latLng = r.center;

					this.fire('geocoded', { waypoint: wp, value: r });

				}, this, L.extend({

					resultFn: this.options.geocoder.geocode,

					resultContext: this.options.geocoder,

					autocompleteFn: this.options.geocoder.suggest,

					autocompleteContext: this.options.geocoder

				}, this.options.autocompleteOptions));

		},



		getContainer: function() {

			return this._element.container;

		},



		setValue: function(v) {

			this._element.input.value = v;

		},



		update: function(force) {

			var wp = this._waypoint,

				wpCoords;



			wp.name = wp.name || '';



			if (wp.latLng && (force || !wp.name)) {

				wpCoords = this.options.waypointNameFallback(wp.latLng);

				if (this.options.geocoder && this.options.geocoder.reverse) {

					this.options.geocoder.reverse(wp.latLng, 67108864 /* zoom 18 */, function(rs) {

						if (rs.length > 0 && rs[0].center.distanceTo(wp.latLng) < this.options.maxGeocoderTolerance) {

							wp.name = rs[0].name;

						} else {

							wp.name = wpCoords;

						}

						this._update();

					}, this);

				} else {

					wp.name = wpCoords;

					this._update();

				}

			}

		},



		focus: function() {

			var input = this._element.input;

			input.focus();

			selectInputText(input);

		},



		_update: function() {

			var wp = this._waypoint,

			    value = wp && wp.name ? wp.name : '';

			this.setValue(value);

			this.fire('reversegeocoded', {waypoint: wp, value: value});

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./autocomplete":48,"./localization":57}],53:[function(_dereq_,module,exports){

(function (global){

var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null),

    Control = _dereq_('./control'),

    Itinerary = _dereq_('./itinerary'),

    Line = _dereq_('./line'),

    OSRMv1 = _dereq_('./osrm-v1'),

    Plan = _dereq_('./plan'),

    Waypoint = _dereq_('./waypoint'),

    Autocomplete = _dereq_('./autocomplete'),

    Formatter = _dereq_('./formatter'),

    GeocoderElement = _dereq_('./geocoder-element'),

    Localization = _dereq_('./localization'),

    ItineraryBuilder = _dereq_('./itinerary-builder'),

    Mapbox = _dereq_('./mapbox'),

    ErrorControl = _dereq_('./error-control');



L.routing = {

    control: function(options) { return new Control(options); },

    itinerary: function(options) {

        return Itinerary(options);

    },

    line: function(route, options) {

        return new Line(route, options);

    },

    plan: function(waypoints, options) {

        return new Plan(waypoints, options);

    },

    waypoint: function(latLng, name, options) {

        return new Waypoint(latLng, name, options);

    },

    osrmv1: function(options) {

        return new OSRMv1(options);

    },

    localization: function(options) {

        return new Localization(options);

    },

    formatter: function(options) {

        return new Formatter(options);

    },

    geocoderElement: function(wp, i, nWps, plan) {

        return new L.Routing.GeocoderElement(wp, i, nWps, plan);

    },

    itineraryBuilder: function(options) {

        return new ItineraryBuilder(options);

    },

    mapbox: function(accessToken, options) {

        return new Mapbox(accessToken, options);

    },

    errorControl: function(routingControl, options) {

        return new ErrorControl(routingControl, options);

    },

    autocomplete: function(elem, callback, context, options) {

        return new Autocomplete(elem, callback, context, options);

    }

};



module.exports = L.Routing = {

    Control: Control,

    Itinerary: Itinerary,

    Line: Line,

    OSRMv1: OSRMv1,

    Plan: Plan,

    Waypoint: Waypoint,

    Autocomplete: Autocomplete,

    Formatter: Formatter,

    GeocoderElement: GeocoderElement,

    Localization: Localization,

    Formatter: Formatter,

    ItineraryBuilder: ItineraryBuilder,



    // Legacy; remove these in next major release

    control: L.routing.control,

    itinerary: L.routing.itinerary,

    line: L.routing.line,

    plan: L.routing.plan,

    waypoint: L.routing.waypoint,

    osrmv1: L.routing.osrmv1,

    geocoderElement: L.routing.geocoderElement,

    mapbox: L.routing.mapbox,

    errorControl: L.routing.errorControl,

};



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./autocomplete":48,"./control":49,"./error-control":50,"./formatter":51,"./geocoder-element":52,"./itinerary":55,"./itinerary-builder":54,"./line":56,"./localization":57,"./mapbox":58,"./osrm-v1":59,"./plan":60,"./waypoint":61}],54:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	module.exports = L.Class.extend({

		options: {

			containerClassName: ''

		},



		initialize: function(options) {

			L.setOptions(this, options);

		},



		createContainer: function(className) {

			var table = L.DomUtil.create('table', (className || '') + ' ' + this.options.containerClassName),

				colgroup = L.DomUtil.create('colgroup', '', table);



			L.DomUtil.create('col', 'leaflet-routing-instruction-icon', colgroup);

			L.DomUtil.create('col', 'leaflet-routing-instruction-text', colgroup);

			L.DomUtil.create('col', 'leaflet-routing-instruction-distance', colgroup);



			return table;

		},



		createStepsContainer: function() {

			return L.DomUtil.create('tbody', '');

		},



		createStep: function(text, distance, icon, steps) {

			var row = L.DomUtil.create('tr', '', steps),

				span,

				td;

			td = L.DomUtil.create('td', '', row);

			span = L.DomUtil.create('span', 'leaflet-routing-icon leaflet-routing-icon-'+icon, td);

			td.appendChild(span);

			td = L.DomUtil.create('td', '', row);

			td.appendChild(document.createTextNode(text));

			td = L.DomUtil.create('td', '', row);

			td.appendChild(document.createTextNode(distance));

			return row;

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{}],55:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);

	var Formatter = _dereq_('./formatter');

	var ItineraryBuilder = _dereq_('./itinerary-builder');



	module.exports = L.Control.extend({

		includes: ((typeof L.Evented !== 'undefined' && L.Evented.prototype) || L.Mixin.Events),



		options: {

			pointMarkerStyle: {

				radius: 5,

				color: '#03f',

				fillColor: 'white',

				opacity: 1,

				fillOpacity: 0.7

			},

			summaryTemplate: '<h2>{name}</h2><h3>{distance}, {time}</h3>',

			timeTemplate: '{time}',

			containerClassName: '',

			alternativeClassName: '',

			minimizedClassName: '',

			itineraryClassName: '',

			totalDistanceRoundingSensitivity: -1,

			show: true,

			collapsible: undefined,

			collapseBtn: function(itinerary) {

				var collapseBtn = L.DomUtil.create('span', itinerary.options.collapseBtnClass);

				L.DomEvent.on(collapseBtn, 'click', itinerary._toggle, itinerary);

				itinerary._container.insertBefore(collapseBtn, itinerary._container.firstChild);

			},

			collapseBtnClass: 'leaflet-routing-collapse-btn'

		},



		initialize: function(options) {

			L.setOptions(this, options);

			this._formatter = this.options.formatter || new Formatter(this.options);

			this._itineraryBuilder = this.options.itineraryBuilder || new ItineraryBuilder({

				containerClassName: this.options.itineraryClassName

			});

		},



		onAdd: function(map) {

			var collapsible = this.options.collapsible;



			collapsible = collapsible || (collapsible === undefined && map.getSize().x <= 640);



			this._container = L.DomUtil.create('div', 'leaflet-routing-container leaflet-bar ' +

				(!this.options.show ? 'leaflet-routing-container-hide ' : '') +

				(collapsible ? 'leaflet-routing-collapsible ' : '') +

				this.options.containerClassName);

			this._altContainer = this.createAlternativesContainer();

			this._container.appendChild(this._altContainer);

			L.DomEvent.disableClickPropagation(this._container);

			L.DomEvent.addListener(this._container, 'mousewheel', function(e) {

				L.DomEvent.stopPropagation(e);

			});



			if (collapsible) {

				this.options.collapseBtn(this);

			}



			return this._container;

		},



		onRemove: function() {

		},



		createAlternativesContainer: function() {

			return L.DomUtil.create('div', 'leaflet-routing-alternatives-container');

		},



		setAlternatives: function(routes) {

			var i,

			    alt,

			    altDiv;



			this._clearAlts();



			this._routes = routes;



			for (i = 0; i < this._routes.length; i++) {

				alt = this._routes[i];

				altDiv = this._createAlternative(alt, i);

				this._altContainer.appendChild(altDiv);

				this._altElements.push(altDiv);

			}



			this._selectRoute({route: this._routes[0], alternatives: this._routes.slice(1)});



			return this;

		},



		show: function() {

			L.DomUtil.removeClass(this._container, 'leaflet-routing-container-hide');

		},



		hide: function() {

			L.DomUtil.addClass(this._container, 'leaflet-routing-container-hide');

		},



		_toggle: function() {

			var collapsed = L.DomUtil.hasClass(this._container, 'leaflet-routing-container-hide');

			this[collapsed ? 'show' : 'hide']();

		},



		_createAlternative: function(alt, i) {

			var altDiv = L.DomUtil.create('div', 'leaflet-routing-alt ' +

				this.options.alternativeClassName +

				(i > 0 ? ' leaflet-routing-alt-minimized ' + this.options.minimizedClassName : '')),

				template = this.options.summaryTemplate,

				data = L.extend({

					name: alt.name,

					distance: this._formatter.formatDistance(alt.summary.totalDistance, this.options.totalDistanceRoundingSensitivity),

					time: this._formatter.formatTime(alt.summary.totalTime)

				}, alt);

			altDiv.innerHTML = typeof(template) === 'function' ? template(data) : L.Util.template(template, data);

			L.DomEvent.addListener(altDiv, 'click', this._onAltClicked, this);

			this.on('routeselected', this._selectAlt, this);



			altDiv.appendChild(this._createItineraryContainer(alt));

			return altDiv;

		},



		_clearAlts: function() {

			var el = this._altContainer;

			while (el && el.firstChild) {

				el.removeChild(el.firstChild);

			}



			this._altElements = [];

		},



		_createItineraryContainer: function(r) {

			var container = this._itineraryBuilder.createContainer(),

			    steps = this._itineraryBuilder.createStepsContainer(),

			    i,

			    instr,

			    step,

			    distance,

			    text,

			    icon;



			container.appendChild(steps);



			for (i = 0; i < r.instructions.length; i++) {

				instr = r.instructions[i];

				text = this._formatter.formatInstruction(instr, i);

				distance = this._formatter.formatDistance(instr.distance);

				icon = this._formatter.getIconName(instr, i);

				step = this._itineraryBuilder.createStep(text, distance, icon, steps);



				if(instr.index) {

					this._addRowListeners(step, r.coordinates[instr.index]);

				}

			}



			return container;

		},



		_addRowListeners: function(row, coordinate) {

			L.DomEvent.addListener(row, 'mouseover', function() {

				this._marker = L.circleMarker(coordinate,

					this.options.pointMarkerStyle).addTo(this._map);

			}, this);

			L.DomEvent.addListener(row, 'mouseout', function() {

				if (this._marker) {

					this._map.removeLayer(this._marker);

					delete this._marker;

				}

			}, this);

			L.DomEvent.addListener(row, 'click', function(e) {

				this._map.panTo(coordinate);

				L.DomEvent.stopPropagation(e);

			}, this);

		},



		_onAltClicked: function(e) {

			var altElem = e.target || window.event.srcElement;

			while (!L.DomUtil.hasClass(altElem, 'leaflet-routing-alt')) {

				altElem = altElem.parentElement;

			}



			var j = this._altElements.indexOf(altElem);

			var alts = this._routes.slice();

			var route = alts.splice(j, 1)[0];



			this.fire('routeselected', {

				route: route,

				alternatives: alts

			});

		},



		_selectAlt: function(e) {

			var altElem,

			    j,

			    n,

			    classFn;



			altElem = this._altElements[e.route.routesIndex];



			if (L.DomUtil.hasClass(altElem, 'leaflet-routing-alt-minimized')) {

				for (j = 0; j < this._altElements.length; j++) {

					n = this._altElements[j];

					classFn = j === e.route.routesIndex ? 'removeClass' : 'addClass';

					L.DomUtil[classFn](n, 'leaflet-routing-alt-minimized');

					if (this.options.minimizedClassName) {

						L.DomUtil[classFn](n, this.options.minimizedClassName);

					}



					if (j !== e.route.routesIndex) n.scrollTop = 0;

				}

			}



			L.DomEvent.stop(e);

		},



		_selectRoute: function(routes) {

			if (this._marker) {

				this._map.removeLayer(this._marker);

				delete this._marker;

			}

			this.fire('routeselected', routes);

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./formatter":51,"./itinerary-builder":54}],56:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	module.exports = L.LayerGroup.extend({

		includes: ((typeof L.Evented !== 'undefined' && L.Evented.prototype) || L.Mixin.Events),



		options: {

			styles: [

				{color: 'black', opacity: 0.15, weight: 9},

				{color: 'white', opacity: 0.8, weight: 6},

				{color: 'red', opacity: 1, weight: 2}

			],

			missingRouteStyles: [

				{color: 'black', opacity: 0.15, weight: 7},

				{color: 'white', opacity: 0.6, weight: 4},

				{color: 'gray', opacity: 0.8, weight: 2, dashArray: '7,12'}

			],

			addWaypoints: true,

			extendToWaypoints: true,

			missingRouteTolerance: 10

		},



		initialize: function(route, options) {

			L.setOptions(this, options);

			L.LayerGroup.prototype.initialize.call(this, options);

			this._route = route;



			if (this.options.extendToWaypoints) {

				this._extendToWaypoints();

			}



			this._addSegment(

				route.coordinates,

				this.options.styles,

				this.options.addWaypoints);

		},



		getBounds: function() {

			return L.latLngBounds(this._route.coordinates);

		},



		_findWaypointIndices: function() {

			var wps = this._route.inputWaypoints,

			    indices = [],

			    i;

			for (i = 0; i < wps.length; i++) {

				indices.push(this._findClosestRoutePoint(wps[i].latLng));

			}



			return indices;

		},



		_findClosestRoutePoint: function(latlng) {

			var minDist = Number.MAX_VALUE,

				minIndex,

			    i,

			    d;



			for (i = this._route.coordinates.length - 1; i >= 0 ; i--) {

				// TODO: maybe do this in pixel space instead?

				d = latlng.distanceTo(this._route.coordinates[i]);

				if (d < minDist) {

					minIndex = i;

					minDist = d;

				}

			}



			return minIndex;

		},



		_extendToWaypoints: function() {

			var wps = this._route.inputWaypoints,

				wpIndices = this._getWaypointIndices(),

			    i,

			    wpLatLng,

			    routeCoord;



			for (i = 0; i < wps.length; i++) {

				wpLatLng = wps[i].latLng;

				routeCoord = L.latLng(this._route.coordinates[wpIndices[i]]);

				if (wpLatLng.distanceTo(routeCoord) >

					this.options.missingRouteTolerance) {

					this._addSegment([wpLatLng, routeCoord],

						this.options.missingRouteStyles);

				}

			}

		},



		_addSegment: function(coords, styles, mouselistener) {

			var i,

				pl;



			for (i = 0; i < styles.length; i++) {

				pl = L.polyline(coords, styles[i]);

				this.addLayer(pl);

				if (mouselistener) {

					pl.on('mousedown', this._onLineTouched, this);

				}

			}

		},



		_findNearestWpBefore: function(i) {

			var wpIndices = this._getWaypointIndices(),

				j = wpIndices.length - 1;

			while (j >= 0 && wpIndices[j] > i) {

				j--;

			}



			return j;

		},



		_onLineTouched: function(e) {

			var afterIndex = this._findNearestWpBefore(this._findClosestRoutePoint(e.latlng));

			this.fire('linetouched', {

				afterIndex: afterIndex,

				latlng: e.latlng

			});

			L.DomEvent.stop(e);

		},



		_getWaypointIndices: function() {

			if (!this._wpIndices) {

				this._wpIndices = this._route.waypointIndices || this._findWaypointIndices();

			}



			return this._wpIndices;

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{}],57:[function(_dereq_,module,exports){

/* 

   NOTICE

   Since version 3.2.5, the functionality in this file is by

   default NOT used for localizing OSRM instructions.

   Instead, we rely on the module osrm-text-instructions (https://github.com/Project-OSRM/osrm-text-instructions/).

   

   This file can still be used for other routing backends, or if you specify the

   stepToText option in the OSRMv1 class.

*/



(function() {

	'use strict';



	var spanish = {

		directions: {

			N: 'norte',

			NE: 'noreste',

			E: 'este',

			SE: 'sureste',

			S: 'sur',

			SW: 'suroeste',

			W: 'oeste',

			NW: 'noroeste',

			SlightRight: 'leve giro a la derecha',

			Right: 'derecha',

			SharpRight: 'giro pronunciado a la derecha',

			SlightLeft: 'leve giro a la izquierda',

			Left: 'izquierda',

			SharpLeft: 'giro pronunciado a la izquierda',

			Uturn: 'media vuelta'

		},

		instructions: {

			// instruction, postfix if the road is named

			'Head':

				['Derecho {dir}', ' sobre {road}'],

			'Continue':

				['Continuar {dir}', ' en {road}'],

			'TurnAround':

				['Dar vuelta'],

			'WaypointReached':

				['Lleg� a un punto del camino'],

			'Roundabout':

				['Tomar {exitStr} salida en la rotonda', ' en {road}'],

			'DestinationReached':

				['Llegada a destino'],

			'Fork': ['En el cruce gira a {modifier}', ' hacia {road}'],

			'Merge': ['Incorp�rate {modifier}', ' hacia {road}'],

			'OnRamp': ['Gira {modifier} en la salida', ' hacia {road}'],

			'OffRamp': ['Toma la salida {modifier}', ' hacia {road}'],

			'EndOfRoad': ['Gira {modifier} al final de la carretera', ' hacia {road}'],

			'Onto': 'hacia {road}'

		},

		formatOrder: function(n) {

			return n + '�';

		},

		ui: {

			startPlaceholder: 'Inicio',

			viaPlaceholder: 'Via {viaNumber}',

			endPlaceholder: 'Destino'

		},

		units: {

			meters: 'm',

			kilometers: 'km',

			yards: 'yd',

			miles: 'mi',

			hours: 'h',

			minutes: 'min',

			seconds: 's'

		}

	};



	L.Routing = L.Routing || {};



	var Localization = L.Class.extend({

		initialize: function(langs) {

			this._langs = L.Util.isArray(langs) ? langs.slice() : [langs, 'en'];



			for (var i = 0, l = this._langs.length; i < l; i++) {

				var generalizedCode = /([A-Za-z]+)/.exec(this._langs[i])[1]

				if (!Localization[this._langs[i]]) {

					if (Localization[generalizedCode]) {

						this._langs[i] = generalizedCode;

					} else {

						throw new Error('No localization for language "' + this._langs[i] + '".');

					}

				}

			}

		},



		localize: function(keys) {

			var dict,

				key,

				value;



			keys = L.Util.isArray(keys) ? keys : [keys];



			for (var i = 0, l = this._langs.length; i < l; i++) {

				dict = Localization[this._langs[i]];

				for (var j = 0, nKeys = keys.length; dict && j < nKeys; j++) {

					key = keys[j];

					value = dict[key];

					dict = value;

				}



				if (value) {

					return value;

				}

			}

		}

	});



	module.exports = L.extend(Localization, {

		'en': {

			directions: {

				N: 'north',

				NE: 'northeast',

				E: 'east',

				SE: 'southeast',

				S: 'south',

				SW: 'southwest',

				W: 'west',

				NW: 'northwest',

				SlightRight: 'slight right',

				Right: 'right',

				SharpRight: 'sharp right',

				SlightLeft: 'slight left',

				Left: 'left',

				SharpLeft: 'sharp left',

				Uturn: 'Turn around'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Head {dir}', ' on {road}'],

				'Continue':

					['Continue {dir}'],

				'TurnAround':

					['Turn around'],

				'WaypointReached':

					['Waypoint reached'],

				'Roundabout':

					['Take the {exitStr} exit in the roundabout', ' onto {road}'],

				'DestinationReached':

					['Destination reached'],

				'Fork': ['At the fork, turn {modifier}', ' onto {road}'],

				'Merge': ['Merge {modifier}', ' onto {road}'],

				'OnRamp': ['Turn {modifier} on the ramp', ' onto {road}'],

				'OffRamp': ['Take the ramp on the {modifier}', ' onto {road}'],

				'EndOfRoad': ['Turn {modifier} at the end of the road', ' onto {road}'],

				'Onto': 'onto {road}'

			},

			formatOrder: function(n) {

				var i = n % 10 - 1,

				suffix = ['st', 'nd', 'rd'];



				return suffix[i] ? n + suffix[i] : n + 'th';

			},

			ui: {

				startPlaceholder: 'Start',

				viaPlaceholder: 'Via {viaNumber}',

				endPlaceholder: 'End'

			},

			units: {

				meters: 'm',

				kilometers: 'km',

				yards: 'yd',

				miles: 'mi',

				hours: 'h',

				minutes: 'min',

				seconds: 's'

			}

		},



		'de': {

			directions: {

				N: 'Norden',

				NE: 'Nordosten',

				E: 'Osten',

				SE: 'S�dosten',

				S: 'S�den',

				SW: 'S�dwesten',

				W: 'Westen',

				NW: 'Nordwesten',

				SlightRight: 'leicht rechts',

				Right: 'rechts',

				SharpRight: 'scharf rechts',

				SlightLeft: 'leicht links',

				Left: 'links',

				SharpLeft: 'scharf links',

				Uturn: 'Wenden'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Richtung {dir}', ' auf {road}'],

				'Continue':

					['Geradeaus Richtung {dir}', ' auf {road}'],

				'SlightRight':

					['Leicht rechts abbiegen', ' auf {road}'],

				'Right':

					['Rechts abbiegen', ' auf {road}'],

				'SharpRight':

					['Scharf rechts abbiegen', ' auf {road}'],

				'TurnAround':

					['Wenden'],

				'SharpLeft':

					['Scharf links abbiegen', ' auf {road}'],

				'Left':

					['Links abbiegen', ' auf {road}'],

				'SlightLeft':

					['Leicht links abbiegen', ' auf {road}'],

				'WaypointReached':

					['Zwischenhalt erreicht'],

				'Roundabout':

					['Nehmen Sie die {exitStr} Ausfahrt im Kreisverkehr', ' auf {road}'],

				'DestinationReached':

					['Sie haben ihr Ziel erreicht'],

				'Fork': ['An der Kreuzung {modifier}', ' auf {road}'],

				'Merge': ['Fahren Sie {modifier} weiter', ' auf {road}'],

				'OnRamp': ['Fahren Sie {modifier} auf die Auffahrt', ' auf {road}'],

				'OffRamp': ['Nehmen Sie die Ausfahrt {modifier}', ' auf {road}'],

				'EndOfRoad': ['Fahren Sie {modifier} am Ende der Stra�e', ' auf {road}'],

				'Onto': 'auf {road}'

			},

			formatOrder: function(n) {

				return n + '.';

			},

			ui: {

				startPlaceholder: 'Start',

				viaPlaceholder: 'Via {viaNumber}',

				endPlaceholder: 'Ziel'

			}

		},



		'sv': {

			directions: {

				N: 'norr',

				NE: 'nordost',

				E: '�st',

				SE: 'sydost',

				S: 'syd',

				SW: 'sydv�st',

				W: 'v�st',

				NW: 'nordv�st',

				SlightRight: 'svagt h�ger',

				Right: 'h�ger',

				SharpRight: 'skarpt h�ger',

				SlightLeft: 'svagt v�nster',

				Left: 'v�nster',

				SharpLeft: 'skarpt v�nster',

				Uturn: 'V�nd'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['�k �t {dir}', ' till {road}'],

				'Continue':

					['Forts�tt {dir}'],

				'SlightRight':

					['Svagt h�ger', ' till {road}'],

				'Right':

					['Sv�ng h�ger', ' till {road}'],

				'SharpRight':

					['Skarpt h�ger', ' till {road}'],

				'TurnAround':

					['V�nd'],

				'SharpLeft':

					['Skarpt v�nster', ' till {road}'],

				'Left':

					['Sv�ng v�nster', ' till {road}'],

				'SlightLeft':

					['Svagt v�nster', ' till {road}'],

				'WaypointReached':

					['Viapunkt n�dd'],

				'Roundabout':

					['Tag {exitStr} avfarten i rondellen', ' till {road}'],

				'DestinationReached':

					['Framme vid resans m�l'],

				'Fork': ['Tag av {modifier}', ' till {road}'],

				'Merge': ['Anslut {modifier} ', ' till {road}'],

				'OnRamp': ['Tag p�farten {modifier}', ' till {road}'],

				'OffRamp': ['Tag avfarten {modifier}', ' till {road}'],

				'EndOfRoad': ['Sv�ng {modifier} vid v�gens slut', ' till {road}'],

				'Onto': 'till {road}'

			},

			formatOrder: function(n) {

				return ['f�rsta', 'andra', 'tredje', 'fj�rde', 'femte',

					'sj�tte', 'sjunde', '�ttonde', 'nionde', 'tionde'

					/* Can't possibly be more than ten exits, can there? */][n - 1];

			},

			ui: {

				startPlaceholder: 'Fr�n',

				viaPlaceholder: 'Via {viaNumber}',

				endPlaceholder: 'Till'

			}

		},



		'es': spanish,

		'sp': spanish,

		

		'nl': {

			directions: {

				N: 'noordelijke',

				NE: 'noordoostelijke',

				E: 'oostelijke',

				SE: 'zuidoostelijke',

				S: 'zuidelijke',

				SW: 'zuidewestelijke',

				W: 'westelijke',

				NW: 'noordwestelijke'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Vertrek in {dir} richting', ' de {road} op'],

				'Continue':

					['Ga in {dir} richting', ' de {road} op'],

				'SlightRight':

					['Volg de weg naar rechts', ' de {road} op'],

				'Right':

					['Ga rechtsaf', ' de {road} op'],

				'SharpRight':

					['Ga scherpe bocht naar rechts', ' de {road} op'],

				'TurnAround':

					['Keer om'],

				'SharpLeft':

					['Ga scherpe bocht naar links', ' de {road} op'],

				'Left':

					['Ga linksaf', ' de {road} op'],

				'SlightLeft':

					['Volg de weg naar links', ' de {road} op'],

				'WaypointReached':

					['Aangekomen bij tussenpunt'],

				'Roundabout':

					['Neem de {exitStr} afslag op de rotonde', ' de {road} op'],

				'DestinationReached':

					['Aangekomen op eindpunt'],

			},

			formatOrder: function(n) {

				if (n === 1 || n >= 20) {

					return n + 'ste';

				} else {

					return n + 'de';

				}

			},

			ui: {

				startPlaceholder: 'Vertrekpunt',

				viaPlaceholder: 'Via {viaNumber}',

				endPlaceholder: 'Bestemming'

			}

		},

		'fr': {

			directions: {

				N: 'nord',

				NE: 'nord-est',

				E: 'est',

				SE: 'sud-est',

				S: 'sud',

				SW: 'sud-ouest',

				W: 'ouest',

				NW: 'nord-ouest'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Tout droit au {dir}', ' sur {road}'],

				'Continue':

					['Continuer au {dir}', ' sur {road}'],

				'SlightRight':

					['L�g�rement � droite', ' sur {road}'],

				'Right':

					['A droite', ' sur {road}'],

				'SharpRight':

					['Compl�tement � droite', ' sur {road}'],

				'TurnAround':

					['Faire demi-tour'],

				'SharpLeft':

					['Compl�tement � gauche', ' sur {road}'],

				'Left':

					['A gauche', ' sur {road}'],

				'SlightLeft':

					['L�g�rement � gauche', ' sur {road}'],

				'WaypointReached':

					['Point d\'�tape atteint'],

				'Roundabout':

					['Au rond-point, prenez la {exitStr} sortie', ' sur {road}'],

				'DestinationReached':

					['Destination atteinte'],

			},

			formatOrder: function(n) {

				return n + '�';

			},

			ui: {

				startPlaceholder: 'D�part',

				viaPlaceholder: 'Interm�diaire {viaNumber}',

				endPlaceholder: 'Arriv�e'

			}

		},

		'it': {

			directions: {

				N: 'nord',

				NE: 'nord-est',

				E: 'est',

				SE: 'sud-est',

				S: 'sud',

				SW: 'sud-ovest',

				W: 'ovest',

				NW: 'nord-ovest'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Dritto verso {dir}', ' su {road}'],

				'Continue':

					['Continuare verso {dir}', ' su {road}'],

				'SlightRight':

					['Mantenere la destra', ' su {road}'],

				'Right':

					['A destra', ' su {road}'],

				'SharpRight':

					['Strettamente a destra', ' su {road}'],

				'TurnAround':

					['Fare inversione di marcia'],

				'SharpLeft':

					['Strettamente a sinistra', ' su {road}'],

				'Left':

					['A sinistra', ' sur {road}'],

				'SlightLeft':

					['Mantenere la sinistra', ' su {road}'],

				'WaypointReached':

					['Punto di passaggio raggiunto'],

				'Roundabout':

					['Alla rotonda, prendere la {exitStr} uscita'],

				'DestinationReached':

					['Destinazione raggiunta'],

			},

			formatOrder: function(n) {

				return n + '�';

			},

			ui: {

				startPlaceholder: 'Partenza',

				viaPlaceholder: 'Intermedia {viaNumber}',

				endPlaceholder: 'Destinazione'

			}

		},

		'pt': {

			directions: {

				N: 'norte',

				NE: 'nordeste',

				E: 'leste',

				SE: 'sudeste',

				S: 'sul',

				SW: 'sudoeste',

				W: 'oeste',

				NW: 'noroeste',

				SlightRight: 'curva ligeira a direita',

				Right: 'direita',

				SharpRight: 'curva fechada a direita',

				SlightLeft: 'ligeira a esquerda',

				Left: 'esquerda',

				SharpLeft: 'curva fechada a esquerda',

				Uturn: 'Meia volta'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Siga {dir}', ' na {road}'],

				'Continue':

					['Continue {dir}', ' na {road}'],

				'SlightRight':

					['Curva ligeira a direita', ' na {road}'],

				'Right':

					['Curva a direita', ' na {road}'],

				'SharpRight':

					['Curva fechada a direita', ' na {road}'],

				'TurnAround':

					['Retorne'],

				'SharpLeft':

					['Curva fechada a esquerda', ' na {road}'],

				'Left':

					['Curva a esquerda', ' na {road}'],

				'SlightLeft':

					['Curva ligueira a esquerda', ' na {road}'],

				'WaypointReached':

					['Ponto de interesse atingido'],

				'Roundabout':

					['Pegue a {exitStr} sa�da na rotat�ria', ' na {road}'],

				'DestinationReached':

					['Destino atingido'],

				'Fork': ['Na encruzilhada, vire a {modifier}', ' na {road}'],

				'Merge': ['Entre � {modifier}', ' na {road}'],

				'OnRamp': ['Vire {modifier} na rampa', ' na {road}'],

				'OffRamp': ['Entre na rampa na {modifier}', ' na {road}'],

				'EndOfRoad': ['Vire {modifier} no fim da rua', ' na {road}'],

				'Onto': 'na {road}'

			},

			formatOrder: function(n) {

				return n + '�';

			},

			ui: {

				startPlaceholder: 'Origem',

				viaPlaceholder: 'Interm�dio {viaNumber}',

				endPlaceholder: 'Destino'

			}

		},

		'sk': {

			directions: {

				N: 'sever',

				NE: 'serverov�chod',

				E: 'v�chod',

				SE: 'juhov�chod',

				S: 'juh',

				SW: 'juhoz�pad',

				W: 'z�pad',

				NW: 'serveroz�pad'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Mierte na {dir}', ' na {road}'],

				'Continue':

					['Pokracujte na {dir}', ' na {road}'],

				'SlightRight':

					['Mierne doprava', ' na {road}'],

				'Right':

					['Doprava', ' na {road}'],

				'SharpRight':

					['Prudko doprava', ' na {road}'],

				'TurnAround':

					['Otocte sa'],

				'SharpLeft':

					['Prudko dolava', ' na {road}'],

				'Left':

					['Dolava', ' na {road}'],

				'SlightLeft':

					['Mierne dolava', ' na {road}'],

				'WaypointReached':

					['Ste v prejazdovom bode.'],

				'Roundabout':

					['Odbocte na {exitStr} v�jazde', ' na {road}'],

				'DestinationReached':

					['Prisli ste do ciela.'],

			},

			formatOrder: function(n) {

				var i = n % 10 - 1,

				suffix = ['.', '.', '.'];



				return suffix[i] ? n + suffix[i] : n + '.';

			},

			ui: {

				startPlaceholder: 'Zaciatok',

				viaPlaceholder: 'Cez {viaNumber}',

				endPlaceholder: 'Koniec'

			}

		},

		'el': {

			directions: {

				N: '??????',

				NE: '???????????????',

				E: '?????????',

				SE: '??????????????',

				S: '?????',

				SW: '???????????',

				W: '??????',

				NW: '????????????'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['????????????? {dir}', ' ???? {road}'],

				'Continue':

					['????????? {dir}', ' ???? {road}'],

				'SlightRight':

					['??????? ?????', ' ???? {road}'],

				'Right':

					['?????', ' ???? {road}'],

				'SharpRight':

					['??????? ????? ??????', ' ???? {road}'],

				'TurnAround':

					['????? ?????????'],

				'SharpLeft':

					['??????? ???????? ??????', ' ???? {road}'],

				'Left':

					['????????', ' ???? {road}'],

				'SlightLeft':

					['??????? ????????', ' ???? {road}'],

				'WaypointReached':

					['??????? ??? ?????? ????????'],

				'Roundabout':

					['??????????? ??? {exitStr} ????? ??? ??????? ?????', ' ???? {road}'],

				'DestinationReached':

					['??????? ???? ????????? ???'],

			},

			formatOrder: function(n) {

				return n + '�';

			},

			ui: {

				startPlaceholder: '????????',

				viaPlaceholder: '???? {viaNumber}',

				endPlaceholder: '??????????'

			}

		},

		'ca': {

			directions: {

				N: 'nord',

				NE: 'nord-est',

				E: 'est',

				SE: 'sud-est',

				S: 'sud',

				SW: 'sud-oest',

				W: 'oest',

				NW: 'nord-oest',

				SlightRight: 'lleu gir a la dreta',

				Right: 'dreta',

				SharpRight: 'gir pronunciat a la dreta',

				SlightLeft: 'gir pronunciat a l\'esquerra',

				Left: 'esquerra',

				SharpLeft: 'lleu gir a l\'esquerra',

				Uturn: 'mitja volta'

			},

			instructions: {

				'Head':

					['Recte {dir}', ' sobre {road}'],

				'Continue':

					['Continuar {dir}'],

				'TurnAround':

					['Donar la volta'],

				'WaypointReached':

					['Ha arribat a un punt del cam�'],

				'Roundabout':

					['Agafar {exitStr} sortida a la rotonda', ' a {road}'],

				'DestinationReached':

					['Arribada al dest�'],

				'Fork': ['A la cru�lla gira a la {modifier}', ' cap a {road}'],

				'Merge': ['Incorpora\'t {modifier}', ' a {road}'],

				'OnRamp': ['Gira {modifier} a la sortida', ' cap a {road}'],

				'OffRamp': ['Pren la sortida {modifier}', ' cap a {road}'],

				'EndOfRoad': ['Gira {modifier} al final de la carretera', ' cap a {road}'],

				'Onto': 'cap a {road}'

			},

			formatOrder: function(n) {

				return n + '�';

			},

			ui: {

				startPlaceholder: 'Origen',

				viaPlaceholder: 'Via {viaNumber}',

				endPlaceholder: 'Dest�'

			},

			units: {

				meters: 'm',

				kilometers: 'km',

				yards: 'yd',

				miles: 'mi',

				hours: 'h',

				minutes: 'min',

				seconds: 's'

			}

		},

		'ru': {

			directions: {

				N: '?????',

				NE: '????????????',

				E: '??????',

				SE: '?????????',

				S: '??',

				SW: '????????',

				W: '?????',

				NW: '???????????',

				SlightRight: '?????? ???????',

				Right: '???????',

				SharpRight: '????? ???????',

				SlightLeft: '?????? ??????',

				Left: '??????',

				SharpLeft: '????? ??????',

				Uturn: '????????????'

			},

			instructions: {

				'Head':

					['?????? ???????? ?? {dir}', ' ?? {road}'],

				'Continue':

					['?????????? ???????? ?? {dir}', ' ?? {road}'],

				'SlightRight':

					['??????? ??????? ???????', ' ?? {road}'],

				'Right':

					['???????', ' ?? {road}'],

				'SharpRight':

					['?????? ??????? ???????', ' ?? {road}'],

				'TurnAround':

					['????????????'],

				'SharpLeft':

					['?????? ??????? ??????', ' ?? {road}'],

				'Left':

					['??????? ??????', ' ?? {road}'],

				'SlightLeft':

					['??????? ??????? ??????', ' ?? {road}'],

				'WaypointReached':

					['????? ??????????'],

				'Roundabout':

					['{exitStr} ????? ? ??????', ' ?? {road}'],

				'DestinationReached':

					['????????? ????????'],

				'Fork': ['?? ???????? ????????? {modifier}', ' ?? {road}'],

				'Merge': ['????????????? {modifier}', ' ?? {road}'],

				'OnRamp': ['????????? {modifier} ?? ?????', ' ?? {road}'],

				'OffRamp': ['????????? ?? {modifier}', ' ?? {road}'],

				'EndOfRoad': ['????????? {modifier} ? ????? ??????', ' ?? {road}'],

				'Onto': '?? {road}'

			},

			formatOrder: function(n) {

				return n + '-?';

			},

			ui: {

				startPlaceholder: '??????',

				viaPlaceholder: '????? {viaNumber}',

				endPlaceholder: '?????'

			},

			units: {

				meters: '?',

				kilometers: '??',

				yards: '???',

				miles: '??',

				hours: '?',

				minutes: '?',

				seconds: '?'

			}

		},

                

                'pl': {

			directions: {

				N: 'p�lnoc',

				NE: 'p�lnocny wsch�d',

				E: 'wsch�d',

				SE: 'poludniowy wsch�d',

				S: 'poludnie',

				SW: 'poludniowy zach�d',

				W: 'zach�d',

				NW: 'p�lnocny zach�d',

				SlightRight: 'lekko w prawo',

				Right: 'w prawo',

				SharpRight: 'ostro w prawo',

				SlightLeft: 'lekko w lewo',

				Left: 'w lewo',

				SharpLeft: 'ostro w lewo',

				Uturn: 'zawr�c'

			},

			instructions: {

				// instruction, postfix if the road is named

				'Head':

					['Kieruj sie na {dir}', ' na {road}'],

				'Continue':

					['Jedz dalej przez {dir}'],

				'TurnAround':

					['Zawr�c'],

				'WaypointReached':

					['Punkt posredni'],

				'Roundabout':

					['Wyjedz {exitStr} zjazdem na rondzie', ' na {road}'],

				'DestinationReached':

					['Dojechano do miejsca docelowego'],

				'Fork': ['Na rozwidleniu {modifier}', ' na {road}'],

				'Merge': ['Zjedz {modifier}', ' na {road}'],

				'OnRamp': ['Wjazd {modifier}', ' na {road}'],

				'OffRamp': ['Zjazd {modifier}', ' na {road}'],

				'EndOfRoad': ['Skrec {modifier} na koncu drogi', ' na {road}'],

				'Onto': 'na {road}'

			},

			formatOrder: function(n) {

				return n + '.';

			},

			ui: {

				startPlaceholder: 'Poczatek',

				viaPlaceholder: 'Przez {viaNumber}',

				endPlaceholder: 'Koniec'

			},

			units: {

				meters: 'm',

				kilometers: 'km',

				yards: 'yd',

				miles: 'mi',

				hours: 'godz',

				minutes: 'min',

				seconds: 's'

			}

		}

	});

})();



},{}],58:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	var OSRMv1 = _dereq_('./osrm-v1');



	/**

	 * Works against OSRM's new API in version 5.0; this has

	 * the API version v1.

	 */

	module.exports = OSRMv1.extend({

		options: {

			serviceUrl: 'https://api.mapbox.com/directions/v5',

			profile: 'mapbox/driving',

			useHints: false

		},



		initialize: function(accessToken, options) {

			L.Routing.OSRMv1.prototype.initialize.call(this, options);

			this.options.requestParameters = this.options.requestParameters || {};

			/* jshint camelcase: false */

			this.options.requestParameters.access_token = accessToken;

			/* jshint camelcase: true */

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./osrm-v1":59}],59:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null),

		corslite = _dereq_('@mapbox/corslite'),

		polyline = _dereq_('@mapbox/polyline'),

		osrmTextInstructions = _dereq_('osrm-text-instructions')('v5');



	// Ignore camelcase naming for this file, since OSRM's API uses

	// underscores.

	/* jshint camelcase: false */



	var Waypoint = _dereq_('./waypoint');



	/**

	 * Works against OSRM's new API in version 5.0; this has

	 * the API version v1.

	 */

	module.exports = L.Class.extend({

		options: {

			serviceUrl: 'https://router.project-osrm.org/route/v1',

			profile: 'driving',

			timeout: 30 * 1000,

			routingOptions: {

				alternatives: true,

				steps: true

			},

			polylinePrecision: 5,

			useHints: true,

			suppressDemoServerWarning: false,

			language: 'en'

		},



		initialize: function(options) {

			L.Util.setOptions(this, options);

			this._hints = {

				locations: {}

			};



			if (!this.options.suppressDemoServerWarning &&

				this.options.serviceUrl.indexOf('//router.project-osrm.org') >= 0) {

				console.warn('You are using OSRM\'s demo server. ' +

					'Please note that it is **NOT SUITABLE FOR PRODUCTION USE**.\n' +

					'Refer to the demo server\'s usage policy: ' +

					'https://github.com/Project-OSRM/osrm-backend/wiki/Api-usage-policy\n\n' +

					'To change, set the serviceUrl option.\n\n' +

					'Please do not report issues with this server to neither ' +

					'Leaflet Routing Machine or OSRM - it\'s for\n' +

					'demo only, and will sometimes not be available, or work in ' +

					'unexpected ways.\n\n' +

					'Please set up your own OSRM server, or use a paid service ' +

					'provider for production.');

			}

		},



		route: function(waypoints, callback, context, options) {

			var timedOut = false,

				wps = [],

				url,

				timer,

				wp,

				i,

				xhr;



			options = L.extend({}, this.options.routingOptions, options);

			url = this.buildRouteUrl(waypoints, options);

			if (this.options.requestParameters) {

				url += L.Util.getParamString(this.options.requestParameters, url);

			}



			timer = setTimeout(function() {

				timedOut = true;

				callback.call(context || callback, {

					status: -1,

					message: 'OSRM request timed out.'

				});

			}, this.options.timeout);



			// Create a copy of the waypoints, since they

			// might otherwise be asynchronously modified while

			// the request is being processed.

			for (i = 0; i < waypoints.length; i++) {

				wp = waypoints[i];

				wps.push(new Waypoint(wp.latLng, wp.name, wp.options));

			}



			return xhr = corslite(url, L.bind(function(err, resp) {

				var data,

					error =  {};



				clearTimeout(timer);

				if (!timedOut) {

					if (!err) {

						try {

							data = JSON.parse(resp.responseText);

							try {

								return this._routeDone(data, wps, options, callback, context);

							} catch (ex) {

								error.status = -3;

								error.message = ex.toString();

							}

						} catch (ex) {

							error.status = -2;

							error.message = 'Error parsing OSRM response: ' + ex.toString();

						}

					} else {

						error.message = 'HTTP request failed: ' + err.type +

							(err.target && err.target.status ? ' HTTP ' + err.target.status + ': ' + err.target.statusText : '');

						error.url = url;

						error.status = -1;

						error.target = err;

					}



					callback.call(context || callback, error);

				} else {

					xhr.abort();

				}

			}, this));

		},



		requiresMoreDetail: function(route, zoom, bounds) {

			if (!route.properties.isSimplified) {

				return false;

			}



			var waypoints = route.inputWaypoints,

				i;

			for (i = 0; i < waypoints.length; ++i) {

				if (!bounds.contains(waypoints[i].latLng)) {

					return true;

				}

			}



			return false;

		},



		_routeDone: function(response, inputWaypoints, options, callback, context) {

			var alts = [],

			    actualWaypoints,

			    i,

			    route;



			context = context || callback;

			if (response.code !== 'Ok') {

				callback.call(context, {

					status: response.code

				});

				return;

			}



			actualWaypoints = this._toWaypoints(inputWaypoints, response.waypoints);



			for (i = 0; i < response.routes.length; i++) {

				route = this._convertRoute(response.routes[i]);

				route.inputWaypoints = inputWaypoints;

				route.waypoints = actualWaypoints;

				route.properties = {isSimplified: !options || !options.geometryOnly || options.simplifyGeometry};

				alts.push(route);

			}



			this._saveHintData(response.waypoints, inputWaypoints);



			callback.call(context, null, alts);

		},



		_convertRoute: function(responseRoute) {

			var result = {

					name: '',

					coordinates: [],

					instructions: [],

					summary: {

						totalDistance: responseRoute.distance,

						totalTime: responseRoute.duration

					}

				},

				legNames = [],

				waypointIndices = [],

				index = 0,

				legCount = responseRoute.legs.length,

				hasSteps = responseRoute.legs[0].steps.length > 0,

				i,

				j,

				leg,

				step,

				geometry,

				type,

				modifier,

				text,

				stepToText;



			if (this.options.stepToText) {

				stepToText = this.options.stepToText;

			} else {

				stepToText = L.bind(osrmTextInstructions.compile, osrmTextInstructions, this.options.language);

			}



			for (i = 0; i < legCount; i++) {

				leg = responseRoute.legs[i];

				legNames.push(leg.summary && leg.summary.charAt(0).toUpperCase() + leg.summary.substring(1));

				for (j = 0; j < leg.steps.length; j++) {

					step = leg.steps[j];

					geometry = this._decodePolyline(step.geometry);

					result.coordinates.push.apply(result.coordinates, geometry);

					type = this._maneuverToInstructionType(step.maneuver, i === legCount - 1);

					modifier = this._maneuverToModifier(step.maneuver);

					text = stepToText(step, {legCount: legCount, legIndex: i});



					if (type) {

						if ((i == 0 && step.maneuver.type == 'depart') || step.maneuver.type == 'arrive') {

							waypointIndices.push(index);

						}



						result.instructions.push({

							type: type,

							distance: step.distance,

							time: step.duration,

							road: step.name,

							direction: this._bearingToDirection(step.maneuver.bearing_after),

							exit: step.maneuver.exit,

							index: index,

							mode: step.mode,

							modifier: modifier,

							text: text

						});

					}



					index += geometry.length;

				}

			}



			result.name = legNames.join(', ');

			if (!hasSteps) {

				result.coordinates = this._decodePolyline(responseRoute.geometry);

			} else {

				result.waypointIndices = waypointIndices;

			}



			return result;

		},



		_bearingToDirection: function(bearing) {

			var oct = Math.round(bearing / 45) % 8;

			return ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'][oct];

		},



		_maneuverToInstructionType: function(maneuver, lastLeg) {

			switch (maneuver.type) {

			case 'new name':

				return 'Continue';

			case 'depart':

				return 'Head';

			case 'arrive':

				return lastLeg ? 'DestinationReached' : 'WaypointReached';

			case 'roundabout':

			case 'rotary':

				return 'Roundabout';

			case 'merge':

			case 'fork':

			case 'on ramp':

			case 'off ramp':

			case 'end of road':

				return this._camelCase(maneuver.type);

			// These are all reduced to the same instruction in the current model

			//case 'turn':

			//case 'ramp': // deprecated in v5.1

			default:

				return this._camelCase(maneuver.modifier);

			}

		},



		_maneuverToModifier: function(maneuver) {

			var modifier = maneuver.modifier;



			switch (maneuver.type) {

			case 'merge':

			case 'fork':

			case 'on ramp':

			case 'off ramp':

			case 'end of road':

				modifier = this._leftOrRight(modifier);

			}



			return modifier && this._camelCase(modifier);

		},



		_camelCase: function(s) {

			var words = s.split(' '),

				result = '';

			for (var i = 0, l = words.length; i < l; i++) {

				result += words[i].charAt(0).toUpperCase() + words[i].substring(1);

			}



			return result;

		},



		_leftOrRight: function(d) {

			return d.indexOf('left') >= 0 ? 'Left' : 'Right';

		},



		_decodePolyline: function(routeGeometry) {

			var cs = polyline.decode(routeGeometry, this.options.polylinePrecision),

				result = new Array(cs.length),

				i;

			for (i = cs.length - 1; i >= 0; i--) {

				result[i] = L.latLng(cs[i]);

			}



			return result;

		},



		_toWaypoints: function(inputWaypoints, vias) {

			var wps = [],

			    i,

			    viaLoc;

			for (i = 0; i < vias.length; i++) {

				viaLoc = vias[i].location;

				wps.push(new Waypoint(L.latLng(viaLoc[1], viaLoc[0]),

				                            inputWaypoints[i].name,

											inputWaypoints[i].options));

			}



			return wps;

		},



		buildRouteUrl: function(waypoints, options) {

			var locs = [],

				hints = [],

				wp,

				latLng,

			    computeInstructions,

			    computeAlternative = true;



			for (var i = 0; i < waypoints.length; i++) {

				wp = waypoints[i];

				latLng = wp.latLng;

				locs.push(latLng.lng + ',' + latLng.lat);

				hints.push(this._hints.locations[this._locationKey(latLng)] || '');

			}



			computeInstructions =

				true;



			return this.options.serviceUrl + '/' + this.options.profile + '/' +

				locs.join(';') + '?' +

				(options.geometryOnly ? (options.simplifyGeometry ? '' : 'overview=full') : 'overview=false') +

				'&alternatives=' + computeAlternative.toString() +

				'&steps=' + computeInstructions.toString() +

				(this.options.useHints ? '&hints=' + hints.join(';') : '') +

				(options.allowUTurns ? '&continue_straight=' + !options.allowUTurns : '');

		},



		_locationKey: function(location) {

			return location.lat + ',' + location.lng;

		},



		_saveHintData: function(actualWaypoints, waypoints) {

			var loc;

			this._hints = {

				locations: {}

			};

			for (var i = actualWaypoints.length - 1; i >= 0; i--) {

				loc = waypoints[i].latLng;

				this._hints.locations[this._locationKey(loc)] = actualWaypoints[i].hint;

			}

		},

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./waypoint":61,"@mapbox/corslite":1,"@mapbox/polyline":2,"osrm-text-instructions":3}],60:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);

	var GeocoderElement = _dereq_('./geocoder-element');

	var Waypoint = _dereq_('./waypoint');



	module.exports = (L.Layer || L.Class).extend({

		includes: ((typeof L.Evented !== 'undefined' && L.Evented.prototype) || L.Mixin.Events),



		options: {

			dragStyles: [

				{color: 'black', opacity: 0.15, weight: 9},

				{color: 'white', opacity: 0.8, weight: 6},

				{color: 'red', opacity: 1, weight: 2, dashArray: '7,12'}

			],

			draggableWaypoints: true,

			routeWhileDragging: false,

			addWaypoints: true,

			reverseWaypoints: false,

			addButtonClassName: '',

			language: 'en',

			createGeocoderElement: function(wp, i, nWps, plan) {

				return new GeocoderElement(wp, i, nWps, plan);

			},

			createMarker: function(i, wp) {

				var options = {

						draggable: this.draggableWaypoints

					},

				    marker = L.marker(wp.latLng, options);



				return marker;

			},

			geocodersClassName: ''

		},



		initialize: function(waypoints, options) {

			L.Util.setOptions(this, options);

			this._waypoints = [];

			this.setWaypoints(waypoints);

		},



		isReady: function() {

			var i;

			for (i = 0; i < this._waypoints.length; i++) {

				if (!this._waypoints[i].latLng) {

					return false;

				}

			}



			return true;

		},



		getWaypoints: function() {

			var i,

				wps = [];



			for (i = 0; i < this._waypoints.length; i++) {

				wps.push(this._waypoints[i]);

			}



			return wps;

		},



		setWaypoints: function(waypoints) {

			var args = [0, this._waypoints.length].concat(waypoints);

			this.spliceWaypoints.apply(this, args);

			return this;

		},



		spliceWaypoints: function() {

			var args = [arguments[0], arguments[1]],

			    i;



			for (i = 2; i < arguments.length; i++) {

				args.push(arguments[i] && arguments[i].hasOwnProperty('latLng') ? arguments[i] : new Waypoint(arguments[i]));

			}



			[].splice.apply(this._waypoints, args);



			// Make sure there's always at least two waypoints

			while (this._waypoints.length < 2) {

				this.spliceWaypoints(this._waypoints.length, 0, null);

			}



			this._updateMarkers();

			this._fireChanged.apply(this, args);

		},



		onAdd: function(map) {

			this._map = map;

			this._updateMarkers();

		},



		onRemove: function() {

			var i;

			this._removeMarkers();



			if (this._newWp) {

				for (i = 0; i < this._newWp.lines.length; i++) {

					this._map.removeLayer(this._newWp.lines[i]);

				}

			}



			delete this._map;

		},



		createGeocoders: function() {

			var container = L.DomUtil.create('div', 'leaflet-routing-geocoders ' + this.options.geocodersClassName),

				waypoints = this._waypoints,

			    addWpBtn,

			    reverseBtn;



			this._geocoderContainer = container;

			this._geocoderElems = [];





			if (this.options.addWaypoints) {

				addWpBtn = L.DomUtil.create('button', 'leaflet-routing-add-waypoint ' + this.options.addButtonClassName, container);

				addWpBtn.setAttribute('type', 'button');

				L.DomEvent.addListener(addWpBtn, 'click', function() {

					this.spliceWaypoints(waypoints.length, 0, null);

				}, this);

			}



			if (this.options.reverseWaypoints) {

				reverseBtn = L.DomUtil.create('button', 'leaflet-routing-reverse-waypoints', container);

				reverseBtn.setAttribute('type', 'button');

				L.DomEvent.addListener(reverseBtn, 'click', function() {

					this._waypoints.reverse();

					this.setWaypoints(this._waypoints);

				}, this);

			}



			this._updateGeocoders();

			this.on('waypointsspliced', this._updateGeocoders);



			return container;

		},



		_createGeocoder: function(i) {

			var geocoder = this.options.createGeocoderElement(this._waypoints[i], i, this._waypoints.length, this.options);

			geocoder

			.on('delete', function() {

				if (i > 0 || this._waypoints.length > 2) {

					this.spliceWaypoints(i, 1);

				} else {

					this.spliceWaypoints(i, 1, new Waypoint());

				}

			}, this)

			.on('geocoded', function(e) {

				this._updateMarkers();

				this._fireChanged();

				this._focusGeocoder(i + 1);

				this.fire('waypointgeocoded', {

					waypointIndex: i,

					waypoint: e.waypoint

				});

			}, this)

			.on('reversegeocoded', function(e) {

				this.fire('waypointgeocoded', {

					waypointIndex: i,

					waypoint: e.waypoint

				});

			}, this);



			return geocoder;

		},



		_updateGeocoders: function() {

			var elems = [],

				i,

			    geocoderElem;



			for (i = 0; i < this._geocoderElems.length; i++) {

				this._geocoderContainer.removeChild(this._geocoderElems[i].getContainer());

			}



			for (i = this._waypoints.length - 1; i >= 0; i--) {

				geocoderElem = this._createGeocoder(i);

				this._geocoderContainer.insertBefore(geocoderElem.getContainer(), this._geocoderContainer.firstChild);

				elems.push(geocoderElem);

			}



			this._geocoderElems = elems.reverse();

		},



		_removeMarkers: function() {

			var i;

			if (this._markers) {

				for (i = 0; i < this._markers.length; i++) {

					if (this._markers[i]) {

						this._map.removeLayer(this._markers[i]);

					}

				}

			}

			this._markers = [];

		},



		_updateMarkers: function() {

			var i,

			    m;



			if (!this._map) {

				return;

			}



			this._removeMarkers();



			for (i = 0; i < this._waypoints.length; i++) {

				if (this._waypoints[i].latLng) {

					m = this.options.createMarker(i, this._waypoints[i], this._waypoints.length);

					if (m) {

						m.addTo(this._map);

						if (this.options.draggableWaypoints) {

							this._hookWaypointEvents(m, i);

						}

					}

				} else {

					m = null;

				}

				this._markers.push(m);

			}

		},



		_fireChanged: function() {

			this.fire('waypointschanged', {waypoints: this.getWaypoints()});



			if (arguments.length >= 2) {

				this.fire('waypointsspliced', {

					index: Array.prototype.shift.call(arguments),

					nRemoved: Array.prototype.shift.call(arguments),

					added: arguments

				});

			}

		},



		_hookWaypointEvents: function(m, i, trackMouseMove) {

			var eventLatLng = function(e) {

					return trackMouseMove ? e.latlng : e.target.getLatLng();

				},

				dragStart = L.bind(function(e) {

					this.fire('waypointdragstart', {index: i, latlng: eventLatLng(e)});

				}, this),

				drag = L.bind(function(e) {

					this._waypoints[i].latLng = eventLatLng(e);

					this.fire('waypointdrag', {index: i, latlng: eventLatLng(e)});

				}, this),

				dragEnd = L.bind(function(e) {

					this._waypoints[i].latLng = eventLatLng(e);

					this._waypoints[i].name = '';

					if (this._geocoderElems) {

						this._geocoderElems[i].update(true);

					}

					this.fire('waypointdragend', {index: i, latlng: eventLatLng(e)});

					this._fireChanged();

				}, this),

				mouseMove,

				mouseUp;



			if (trackMouseMove) {

				mouseMove = L.bind(function(e) {

					this._markers[i].setLatLng(e.latlng);

					drag(e);

				}, this);

				mouseUp = L.bind(function(e) {

					this._map.dragging.enable();

					this._map.off('mouseup', mouseUp);

					this._map.off('mousemove', mouseMove);

					dragEnd(e);

				}, this);

				this._map.dragging.disable();

				this._map.on('mousemove', mouseMove);

				this._map.on('mouseup', mouseUp);

				dragStart({latlng: this._waypoints[i].latLng});

			} else {

				m.on('dragstart', dragStart);

				m.on('drag', drag);

				m.on('dragend', dragEnd);

			}

		},



		dragNewWaypoint: function(e) {

			var newWpIndex = e.afterIndex + 1;

			if (this.options.routeWhileDragging) {

				this.spliceWaypoints(newWpIndex, 0, e.latlng);

				this._hookWaypointEvents(this._markers[newWpIndex], newWpIndex, true);

			} else {

				this._dragNewWaypoint(newWpIndex, e.latlng);

			}

		},



		_dragNewWaypoint: function(newWpIndex, initialLatLng) {

			var wp = new Waypoint(initialLatLng),

				prevWp = this._waypoints[newWpIndex - 1],

				nextWp = this._waypoints[newWpIndex],

				marker = this.options.createMarker(newWpIndex, wp, this._waypoints.length + 1),

				lines = [],

				draggingEnabled = this._map.dragging.enabled(),

				mouseMove = L.bind(function(e) {

					var i,

						latLngs;

					if (marker) {

						marker.setLatLng(e.latlng);

					}

					for (i = 0; i < lines.length; i++) {

						latLngs = lines[i].getLatLngs();

						latLngs.splice(1, 1, e.latlng);

						lines[i].setLatLngs(latLngs);

					}



					L.DomEvent.stop(e);

				}, this),

				mouseUp = L.bind(function(e) {

					var i;

					if (marker) {

						this._map.removeLayer(marker);

					}

					for (i = 0; i < lines.length; i++) {

						this._map.removeLayer(lines[i]);

					}

					this._map.off('mousemove', mouseMove);

					this._map.off('mouseup', mouseUp);

					this.spliceWaypoints(newWpIndex, 0, e.latlng);

					if (draggingEnabled) {

						this._map.dragging.enable();

					}



					L.DomEvent.stop(e);

				}, this),

				i;



			if (marker) {

				marker.addTo(this._map);

			}



			for (i = 0; i < this.options.dragStyles.length; i++) {

				lines.push(L.polyline([prevWp.latLng, initialLatLng, nextWp.latLng],

					this.options.dragStyles[i]).addTo(this._map));

			}



			if (draggingEnabled) {

				this._map.dragging.disable();

			}



			this._map.on('mousemove', mouseMove);

			this._map.on('mouseup', mouseUp);

		},



		_focusGeocoder: function(i) {

			if (this._geocoderElems[i]) {

				this._geocoderElems[i].focus();

			} else {

				document.activeElement.blur();

			}

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{"./geocoder-element":52,"./waypoint":61}],61:[function(_dereq_,module,exports){

(function (global){

(function() {

	'use strict';



	var L = (typeof window !== "undefined" ? window['L'] : typeof global !== "undefined" ? global['L'] : null);



	module.exports = L.Class.extend({

		options: {

			allowUTurn: false,

		},

		initialize: function(latLng, name, options) {

			L.Util.setOptions(this, options);

			this.latLng = L.latLng(latLng);

			this.name = name;

		}

	});

})();



}).call(this,typeof global !== "undefined" ? global : typeof self !== "undefined" ? self : typeof window !== "undefined" ? window : {})

},{}]},{},[53]);

